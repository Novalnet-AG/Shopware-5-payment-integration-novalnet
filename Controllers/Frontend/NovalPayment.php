<?php
/**
* Novalnet payment plugin
*
* NOTICE OF LICENSE
*
* This source file is subject to Novalnet End User License Agreement
*
* DISCLAIMER
*
* If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
*
* @author Novalnet AG
* @copyright Copyright (c) Novalnet
* @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz
* @link https://www.novalnet.de
*
* This free contribution made by request.
*
* If you have found this script useful a small
* recommendation as well as a comment on merchant
*
*/

use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_NovalPayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    private $nnCustomerData;
    private $novalnetPaymentKey;
    private $configDetails;
    private $nHelper;
    private $router;
    private $errorUrl;
    private $param;
    private $guaranteeCheck;
    private $amount;
    private $uniquePaymentID;
    private $user;
    private $lang;
    private $paymentShortName;
    private $session;
    private $errorMessage;
    private $guaranteeErrorMsg;
    private $novalnetGatewayUrl;
    private $novalnetLang = array();
    private $nnRedirectPayments = array('novalnetideal', 'novalnetinstant', 'novalnetpaypal', 'novalnetprzelewy24', 'novalneteps', 'novalnetgiropay');
    private $nnCurlPayments = array('novalnetinvoice', 'novalnetprepayment', 'novalnetsepa', 'novalnetcashpayment','novalnetcc');
    private $nnSecuredParams = array('auth_code', 'product', 'tariff', 'amount', 'test_mode');
    /**
     * Initiate the novalnet configuration
     * Assign the configuration and user values
     *
     * @param null
     * @return null
     */
    public function preDispatch()
    {
        $this->paymentShortName   = $this->getPaymentShortName();
        $this->uniquePaymentID    = $this->createPaymentUniqueId();
        $this->router             = $this->Front()->Router();
        $this->lang               = Shopware()->Container()->get('locale');
        $this->user               = $this->getUser();
        $this->nHelper            = new Shopware_Plugins_Frontend_NovalPayment_lib_classes_NovalnetHelper();
        $this->novalnetGatewayUrl = $this->nHelper->novalnetGatewayUrl();
        $this->novalnetPaymentKey = $this->nHelper->getPaymentKey($this->paymentShortName);
        $this->nnCustomerData     = $this->nHelper->getPaymentCustomerAddressInfo($this->user);
        $this->novalnetLang       = Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper::novalnetGetLanguage(Shopware()->Shop()->getLocale()->getLocale());
        $this->amount             = sprintf('%0.2f', $this->getAmount()) * 100;
        $this->errorUrl           = (version_compare(Shopware()->Config()->version, '5.0.0', '>=')) ? $this->router->assemble(array(
            'controller' => 'checkout',
            'action' => 'shippingPayment',
            'sTarget' => 'checkout'
        )) : (($this->user['additional']['user']['accountmode'] == 1) ? $this->router->assemble(array(
            'controller' => 'checkout',
            'action' => 'confirm'
        )) : $this->router->assemble(array(
            'controller' => 'account',
            'action' => 'payment',
            'sTarget' => 'checkout'
        )));
        $this->session            = Shopware()->Session();
        $billing                  = $this->user['billingaddress'];
        $shipping                 = $this->user['shippingaddress'];
        $this->configDetails  = $this->nHelper->getNovalConfigDetails(Shopware()->Plugins()->Frontend()->NovalPayment()->Config());
        $this->guaranteeCheck     = $this->nHelper->isguaranteed($this->amount, $billing, $shipping, $this->user['additional']['country']['countryiso'], $this->getCurrencyShortName(), $this->configDetails, $this->paymentShortName);
        $this->guaranteeErrorMsg  = $this->nHelper->guaranteedMsg($this->amount, $billing, $shipping, $this->user['additional']['country']['countryiso'], $this->getCurrencyShortName(), $this->configDetails, $this->paymentShortName);
    }

    /**
     * Index action method
     * Forwards the action to payment gateway
     *
     * @param null
     * @return array
     */
    public function indexAction()
    {
        if (in_array($this->paymentShortName, array_keys($this->nHelper->getPaymentTypeInfoAry()))) {
            //Proceed the gateway action for first call request to the server
            return $this->redirect(array(
                'action' => 'gateway',
                'forceSecure' => true
            ));
        } else {
            return $this->redirect(array(
                'controller' => 'checkout'
            ));
        }
    }

    /**
     * Gateway action handles payment request
     * Form the payment information and transmit to the payment gateway
     *
     * @param null
     * @return null
     */
    public function gatewayAction()
    {
        if (!$this->user['billingaddress']) {
            $this->router->assemble(array(
                'controller' => 'checkout'
            ));
        } elseif (!function_exists('curl_init') || !function_exists('md5') || !function_exists('base64_encode')) {
            throw new Enlight_Exception($this->novalnetLang['novalnet_php_Package_not_installed']);
        } elseif (!$this->nHelper->validateBackendConfig($this->configDetails)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->novalnetLang['error_novalnet_basicparam']));
        } elseif (!$this->nHelper->isDigits($this->amount)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->novalnetLang['error_novalnet_invalidamount']));
        }
        if ($this->paymentShortName != 'novalnetcc') {
            unset($this->session->novalnet['novalnetcc']);
        }
        if ($this->paymentShortName != 'novalnetsepa') {
            unset($this->session->novalnet['novalnetsepa']);
        }
        if ($this->paymentShortName != 'novalnetinvoice') {
            unset($this->session->novalnet['novalnetinvoice']);
        }
        // For AboCommerce plug-in adaptation
        $basket = $this->getBasket();
        if ($basket['content'][0]['abo_attributes']) {
            $this->param['create_payment_ref'] = 1;
            $this->param['shop_subs']          = '1';
        }
        //Form the common and payment related parameters to send the server
        $this->createBasicParams();
        $this->createAccountParams();
        $this->param['input4']    = 'payment_name';
        $this->param['inputval4'] = $this->paymentShortName;
        if ($this->errorMessage) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->errorMessage));
        }
 
        $this->param = array_map('trim', $this->param);
        //Make a Curl call for direct payment methods
        if ((in_array($this->paymentShortName, $this->nnCurlPayments) && $this->paymentShortName != 'novalnetcc') || ($this->paymentShortName == 'novalnetcc' && empty($this->session->novalnet['novalnetcc']['nn_cc_do_redirect'])) || ($this->paymentShortName == 'novalnetpaypal' && empty($this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details']))) {      

            
            if (in_array($this->paymentShortName, array(
                'novalnetsepa',
                'novalnetcc'
            )) && $this->configDetails[$this->paymentShortName . '_shopping_type'] == 'zero' && $this->configDetails['tariff_type'] == 2) {
                $storeparam = $this->param;
                $removeAry  = array(
                    'pan_hash',
                    'unique_id',
                    'bank_account_holder',
                    'iban_bic_confirmed'
                );
                foreach ($removeAry as $key) {
                    if (!empty($key)) {
                        unset($storeparam[$key]);
                    }
                }
                $this->session->novalnet_server_request = $storeparam;
            }
            $novalnetResponse = $this->nHelper->curlCallRequest($this->param, $this->novalnetGatewayUrl['paygate_url']);
            parse_str($novalnetResponse->getBody(), $novalnetResult);           
            if (!$this->nHelper->isSuccessTransaction($novalnetResult)) {
                if (in_array($this->paymentShortName, array(
                    'novalnetsepa',
                    'novalnetcc',
                    'novalnetinvoice'
                ))) {
                    $paymentOnly = str_replace('novalnet', '', $this->paymentShortName);
                    if (isset(Shopware()->Session()->novalnet[$this->paymentShortName]['nn_' . $paymentOnly . '_new_acc_details'])) {
                        unset(Shopware()->Session()->novalnet[$this->paymentShortName]['nn_' . $paymentOnly . '_new_acc_details']);
                    }
                }
                return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->nHelper->getStatusDesc($novalnetResult, $this->novalnetLang['novalnet_payment_validate_payment'])));
            }
            
            $this->session->novalnet = array ($this->paymentShortName => array('server_response' => $novalnetResult));
            //Check Novalnet server status is 100 (100 for success and other than 100 is failure)
            if ($novalnetResult['status'] == 100 && $novalnetResult['tid']) {
                if ($this->paymentShortName == 'novalnetinvoice' || $this->paymentShortName == 'novalnetprepayment') {
                    $this->nHelper->logPrepaymentInvoiceTransAccountInfo(array(
                        'tid' => $novalnetResult['tid'],
                        'test_mode' => ($novalnetResult['test_mode']) ? $novalnetResult['test_mode'] : $this->configDetails[$this->paymentShortName . '_test_mode'],
                        'account_holder' => $novalnetResult['invoice_account_holder'],
                        'account_number' => $novalnetResult['invoice_account'],
                        'bank_code' => $novalnetResult['invoice_bankcode'],
                        'bank_name' => $novalnetResult['invoice_bankname'],
                        'bank_city' => $novalnetResult['invoice_bankplace'],
                        'amount' => sprintf('%0.2f', $novalnetResult['amount']),
                        'currency' => $novalnetResult['currency'],
                        'bank_iban' => $novalnetResult['invoice_iban'],
                        'bank_bic' => $novalnetResult['invoice_bic'],
                        'due_date' => $novalnetResult['due_date']
                    ));
                }
                
                //For handling the novalnet server response and complete the order
                $this->novalnetSaveOrder($novalnetResult);
            }
        } elseif (in_array($this->paymentShortName, $this->nnRedirectPayments)) {                   
            if (in_array($this->paymentShortName, array(
                'novalnetpaypal',
                'novalnetcc'
            )) && $this->configDetails[$this->paymentShortName . '_shopping_type'] == 'zero' && $this->configDetails['tariff_type'] == 2) {
                $storeparam = $this->param;
                $this->nHelper->decodeParams($storeparam, $this->configDetails['novalnet_password'], $this->nnSecuredParams);
                $removeAry = array(
                    'uniqid',
                    'hash',
                    'implementation',
                    'user_variable_0',
                    'return_url',
                    'error_return_url',
                    'return_method',
                    'error_return_method',
                    'pan_hash',
                    'unique_id'
                );
                foreach ($removeAry as $key) {
                    if (!empty($key)) {
                        unset($storeparam[$key]);
                    }
                }
                $this->session->novalnet_server_request = $storeparam;
            }
            
            $shopId     = Shopware()->Shop()->getId() ? Shopware()->Shop()->getId() : 1;
            $mainShopId = Shopware()->Db()->fetchOne('SELECT main_id FROM s_core_shops WHERE id = ?', [$shopId]);
            if(empty($mainShopId))
                $mainShopId = $shopId;
            $nnSid      = Shopware()->Front()->Request()->getCookie('session-' . $mainShopId);
            if (!empty($nnSid)) {
                $this->param['input5'] = 'nn_sid';
                $this->param['inputval5'] = $nnSid;
            }            
            $this->View()->NovalParam      = $this->param;
            $this->View()->NovalGatewayUrl = $this->novalnetGatewayUrl[$this->paymentShortName . '_url'];            
        }
    }

    /**
     * Return a list with names of whitelisted actions
     *
     * @param null
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'return',
            'cancel',
            'status'
        );
    }

    /**
     * Return action handles payment confirmation
     * Get the transaction result and display it to user
     *
     * @param null
     * @return null
     */
    public function returnAction()
    {
        $novalnetResult	= $this->Request()->getPost();
        $paymentName	= !empty($novalnetResult['inputval4']) ? $novalnetResult['inputval4'] : $novalnetResult['payment_name'];
        
        if(!empty($paymentName) && $this->paymentShortName != $paymentName)
        {
			$this->paymentShortName	= $paymentName;
			$paymentID	= $this->get('db')->fetchOne(' SELECT id FROM s_core_paymentmeans WHERE name = ? ', array($paymentName));
			Shopware()->Modules()->Admin()->sUpdatePayment($paymentID);
			$payment = Shopware()->Modules()->Admin()->sGetPaymentMeanById($paymentID);
			$this->session->sOrderVariables['sUserData']['additional']['payment'] = $this->session->sOrderVariables['sPayment'] = $payment;
		}
        
        //Check the Novalnet server status for the success order
        if (!$this->nHelper->isSuccessTransaction($novalnetResult)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->nHelper->getStatusDesc($novalnetResult, $this->novalnetLang['novalnet_payment_validate_payment'])));
        }
        
        $sCurrent_hash = $this->nHelper->generateHash($novalnetResult, $this->configDetails['novalnet_password']);
        //Validate the hash value from the Novalnet server for redirection payments
        if (($novalnetResult['hash2'] != $sCurrent_hash)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->novalnetLang['novalnet_payment_validate_checkhash']));
        }
       
        if ($this->paymentShortName == 'novalnetcc') {
            array_push($this->nnRedirectPayments, 'novalnetcc');
        }
        $this->nHelper->decodeParams($novalnetResult, $this->configDetails['novalnet_password'], $this->nnSecuredParams);
        unset($_SESSION['novalnet']['isOrderCreated'], $_SESSION['novalnet']['novalnetOrderNo']);

        //For handling the novalnet server response and complete the order
        $this->novalnetSaveOrder($novalnetResult);
    }


    /**
     * Recurring payment action method for adapt the AboCommerce.
     *
     * @param null
     * @return null
     */
    public function recurringAction()
    {
        if (!$this->getAmount() || $this->getOrderNumber()) {
            $this->redirect(array(
                'controller' => 'checkout'
            ));
            return;
        }
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $orderId           = $this->Request()->getParam('orderId');
        $sql_order_no      = '
            SELECT ordernumber
            FROM s_order
            WHERE id = ?
            AND userID = ?
            AND ordernumber IS NOT NULL
            ORDER BY id DESC';
        $orderDetail       = $this->get('db')->fetchOne($sql_order_no, array(
            $orderId,
            $this->session->sUserId
        ));
        $sql_ref_tid       = '
            SELECT tid,configuration_details,payment_key,payment_type,currency,test_mode,customer_id,lang
            FROM s_novalnet_transaction_detail
            WHERE order_no = ?
            AND tid IS NOT NULL
            ORDER BY id DESC';
        $userDetails       = $this->get('db')->fetchRow(' SELECT countryID,firstname,lastname,street,city,zipcode FROM s_order_billingaddress
            WHERE orderID = ? ', array(
            $orderId
        ));
        $country           = $this->get('db')->fetchOne(' SELECT countryiso FROM s_core_countries
            WHERE id = ? ', array(
            $userDetails['countryID']
        ));
        $reference_details = $this->get('db')->fetchRow($sql_ref_tid, array(
            $orderDetail
        ));
        $payment_type = ($reference_details['payment_type']) ? $reference_details['payment_type'] : $this->getUser()['additional']['payment']['name'];
        $paymentID         = $this->get('db')->fetchOne(' SELECT id FROM s_core_paymentmeans
            WHERE name = ? ', array(
            $payment_type
        ));
        $config_details    = $this->nHelper->getUnserializedData($reference_details['configuration_details']);
        if ($this->getUser()['additional']['payment']['name'] !== $reference_details['payment_type']) {
            $changedPayment    = '
                SELECT configuration_details FROM s_novalnet_change_payment_subscription
                WHERE order_no = ? ORDER BY id DESC';
            $paymentDetails = $this->get('db')->fetchOne($changedPayment, array(
                $orderDetail));
            $paymentDetails = $this->nHelper->getUnserializedData($paymentDetails);
        }
        if (!in_array($this->getUser()['additional']['payment']['name'], array('novalnetinvoice','novalnetprepayment','novalnetcc','novalnetsepa','novalnetpaypal'))) {
            $errorMessage = $this->novalnetLang['error_novalnet_general_message'];
            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => false,
                    'message' => $errorMessage
                );
                echo Zend_Json::encode($data);
            } else {
                return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($errorMessage));
            }
        }
        $this->param['vendor']           = ($config_details['novalnet_vendor']) ? $config_details['novalnet_vendor'] : $paymentDetails['novalnet_vendor'];
        $this->param['auth_code']        = ($config_details['novalnet_auth_code'])?$config_details['novalnet_auth_code']:$paymentDetails['novalnet_auth_code'];
        $this->param['product']          = ($config_details['novalnet_product'])? $config_details['novalnet_product'] : $paymentDetails['novalnet_product'];
        $this->param['tariff']           = ($config_details['novalnet_tariff'])? $config_details['novalnet_tariff'] : $paymentDetails['novalnet_tariff'];
        $this->param['customer_no']      = ($reference_details['customer_id']) ? $reference_details['customer_id'] : $this->getUser()['additional']['user']['customernumber'];
        $this->param['firstname']        = $userDetails['firstname'];
        $this->param['lastname']         = $userDetails['lastname'];
        $this->param['street']           = $userDetails['street'];
        $this->param['search_in_street'] = '1';
        $this->param['city']             = $userDetails['city'];
        $this->param['zip']              = $userDetails['zipcode'];
        $this->param['country_code']     = $country;
        $this->param['amount']           = sprintf('%0.2f', $this->getAmount()) * 100;
        $this->param['lang']             = ($reference_details['lang']) ? strtoupper($reference_details['lang']) : $this->lang;
        $this->param['currency']         = ($reference_details['currency']) ? $reference_details['currency'] : $this->getCurrencyShortName();
        $this->param['system_name']      = 'shopware';
        $this->param['test_mode']        = ($reference_details['test_mode']) ? $reference_details['test_mode'] : $paymentDetails[$this->getUser()['additional']['payment']['name'].'_test_mode'];
        $this->param['system_version']   = Shopware()->Config()->get('Version') . '-NN' . '11.3.6';
        $this->param['system_url']       = $this->router->assemble(array(
            'controller' => 'index'
        ));
        $this->param['remote_ip']        = $this->nHelper->getIp();
        $this->param['system_ip']        = $this->nHelper->getIp('SERVER_ADDR');

        $this->param['shop_subs'] = '1';
      
        //recurring order with same payment method
        if ($this->getUser()['additional']['payment']['name'] === $reference_details['payment_type'] && !empty($reference_details)) {
            $this->param['key']          = $reference_details['payment_key'];
            $this->param['payment_type'] = $this->nHelper->getPaymentType($reference_details['payment_type']);
            $this->nHelper->formParams($this->param, $this->configDetails, $this->getUser(), $reference_details, false);
            if (!empty($paymentDetails['pan_hash'])) {
                $this->param['nn_it']     = 'iframe';
                $this->param['pan_hash']  = $paymentDetails['pan_hash'];
                $this->param['unique_id'] = $paymentDetails['unique_id'];
                $this->param['create_payment_ref'] = '1';
                unset($this->param['payment_ref']);
            }
            if (!empty($paymentDetails['novalnet_sepa_iban'])) {
                $this->param['bank_account_holder'] = $this->nHelper->getValidHolderName($paymentDetails['bank_account_holder']);
                $this->param['iban'] = $paymentDetails['novalnet_sepa_iban'];
                $this->param['create_payment_ref'] = '1';
                unset($this->param['payment_ref']);
            }
        }
        //recurring order with different payment method
        else {
            $this->param['key']          = $this->nHelper->getPaymentKey($this->getUser()['additional']['payment']['name']);
            $this->param['payment_type'] = $this->nHelper->getPaymentType($this->getUser()['additional']['payment']['name']);
            $this->nHelper->formParams($this->param, $this->configDetails, $this->getUser(), null, true);
            if ($paymentDetails['pan_hash'] != '') {
                $this->param['nn_it']     = 'iframe';
                $this->param['pan_hash']  = $paymentDetails['pan_hash'];
                $this->param['unique_id'] = $paymentDetails['unique_id'];
            }
            if ($paymentDetails['novalnet_sepa_iban'] != '') {
                $this->param['bank_account_holder'] = $this->nHelper->getValidHolderName($paymentDetails['bank_account_holder']);
                $this->param['iban'] = $paymentDetails['novalnet_sepa_iban'];
            }
        }

        $novalnetResponse = $this->nHelper->curlCallRequest($this->param, $this->novalnetGatewayUrl['paygate_url']);
        parse_str($novalnetResponse->getBody(), $novalnetResult);
        $testMode       = ((isset($novalnetResult['test_mode']) && $novalnetResult['test_mode'] == 1)) ? 1 : 0;
        $orderNumber = '';
        
        if ($novalnetResult['status'] == 100 && $novalnetResult['tid']) { // Transaction success
            if ($this->getUser()['additional']['payment']['name'] == 'novalnetinvoice' || $this->getUser()['additional']['payment']['name'] == 'novalnetprepayment') { // Only for Invoice/Prepayment
                $this->nHelper->logPrepaymentInvoiceTransAccountInfo(array(
                    'tid' => $novalnetResult['tid'],
                    'test_mode' => ($novalnetResult['test_mode']) ? $novalnetResult['test_mode'] : $reference_details['test_mode'],
                    'account_holder' => $novalnetResult['invoice_account_holder'],
                    'account_number' => $novalnetResult['invoice_account'],
                    'bank_code' => $novalnetResult['invoice_bankcode'],
                    'bank_name' => $novalnetResult['invoice_bankname'],
                    'bank_city' => $novalnetResult['invoice_bankplace'],
                    'amount' => $novalnetResult['amount'],
                    'currency' => $novalnetResult['currency'],
                    'bank_iban' => $novalnetResult['invoice_iban'],
                    'bank_bic' => $novalnetResult['invoice_bic'],
                    'due_date' => ($novalnetResult['due_date']) ? $novalnetResult['due_date'] : ''
                ));
            }
            $paymentPending                                      = (in_array($this->getUser()['additional']['payment']['name'], array(
                'novalnetinvoice',
                'novalnetprepayment'
            )) || ($this->getUser()['additional']['payment']['name'] == 'novalnetpaypal' && $novalnetResult['tid_status'] == 90));
            $paymentStatusId                                     = ($paymentPending === true) ? $this->configDetails[$this->getUser()['additional']['payment']['name'] . '_before_paymenstatus'] : $this->configDetails[$this->getUser()['additional']['payment']['name'] . '_after_paymenstatus'];
            $paidDate                                            = ($paymentPending === true) ? '' : date('Y-m-d');
            $orderNumber                                         = $this->saveOrder($novalnetResult['tid'], $this->uniquePaymentID, $paymentStatusId);
            $novalnetTransNote                                   = $this->nHelper->prepareComments($this->getUser()['additional']['payment']['name'], $novalnetResult, Shopware()->Currency()->getSymbol(), $testMode, $orderNumber, $config_details['novalnet_product'], $reference_details['lang'], $config_details);
            // Store the transaction details in s_order_attributes table
            $sOrderAttributes['novalnet_payment_tid']            = $novalnetResult['tid'];
            $sOrderAttributes['novalnet_payment_gateway_status'] = $novalnetResult['tid_status'];
            if (($this->getUser()['additional']['payment']['name'] == 'novalnetpaypal' && $novalnetResult['tid_status'] == 90)) {
                $sOrderAttributes['novalnet_payment_order_amount'] = 0;
            } else {
                $sOrderAttributes['novalnet_payment_order_amount'] = $novalnetResult['amount'] * 100;
            }
            if ($novalnetResult['due_date']) {
                $sOrderAttributes['novalnet_payment_due_date'] = $novalnetResult['due_date'];
            }
            $sOrderAttributes['novalnet_payment_order_amount']   = $novalnetResult['amount'] * 100;
            $sOrderAttributes['novalnet_payment_current_amount'] = $novalnetResult['amount'] * 100;
            
            $sOrder = Shopware()->Db()->fetchRow('SELECT id,temporaryID FROM s_order WHERE ordernumber = ?', array(
                $orderNumber
            ));
            $this->nHelper->novalnetDbUpdate('s_order_attributes', $sOrderAttributes, 'orderID="' . $sOrder['id'] . '"');
            // Update transaction details
            $sOrder['customercomment'] = str_replace('<br />', PHP_EOL, $novalnetTransNote);
            $sOrder['temporaryID']     = $novalnetResult['tid'];
            if ($paidDate) {
                $sOrder['cleareddate'] = $paidDate;
            }
            if (version_compare(Shopware()->Config()->version, '5.0.0', '>=')) {
                $sOrder['referer'] = '';
            }
            $this->nHelper->novalnetDbUpdate('s_order', $sOrder, "ordernumber='" . $orderNumber . "'");
            if (empty($config_details) && !empty($paymentDetails)) {
                $payment_params = array('nn_it','pan_hash','unique_id','bank_account_holder','novalnet_sepa_iban');
                foreach ($payment_params as $key) {
                    unset($paymentDetails[$key]);
                }
                $config_details = $paymentDetails;
            }
            // s_novalnet_transaction_detail
            $this->nHelper->logInitialTransaction(array(
                'tid' => $novalnetResult['tid'],
                'tariff_id' => $config_details['novalnet_tariff'],
                'payment_id' => $paymentID,
                'payment_key' => $novalnetResult['payment_id'],
                'payment_type' => $this->getUser()['additional']['payment']['name'],
                'amount' => $novalnetResult['amount'] * 100,
                'currency' => $this->getCurrencyShortName(),
                'status' => $novalnetResult['status'],
                'gateway_status' => ($novalnetResult['tid_status']) ? $novalnetResult['tid_status'] : 0,
                'test_mode' => $testMode,
                'customer_id' => $novalnetResult['customer_no'],
                'order_no' => $orderNumber,
                'callback_status' => 0,
                'date' => date('Y-m-d'),
                'account_holder' => $novalnetResult['first_name'] . ' ' . $novalnetResult['last_name'],
                'configuration_details' => serialize(array_filter($config_details)),
                'lang' => ($reference_details['lang']) ? $reference_details['lang'] : $this->lang
            ));
            // Set order number for last success transaction
            if ($novalnetResult['tid'] && $orderNumber) {
                $callBackParams = array(
                    'vendor' => $config_details['novalnet_vendor'],
                    'auth_code' => $config_details['novalnet_auth_code'],
                    'product' => $config_details['novalnet_product'],
                    'tariff' => $config_details['novalnet_tariff'],
                    'key' => $this->getUser()['additional']['payment']['name'],
                    'status' => '100',
                    'tid' => $novalnetResult['tid'],
                    'order_no' => $orderNumber
                );
                if (in_array($novalnetResult['payment_id'], array(
                    '27',
                    '41'
                ))) {
                    $callBackParams['invoice_ref'] = 'BNR-' . $config_details['novalnet_product'] . '-' . $orderNumber;
                }
                $callBackParams = array_map("trim", $callBackParams);
                $this->nHelper->curlCallRequest($callBackParams, $this->novalnetGatewayUrl['paygate_url']);
            }
            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => true,
                    'data' => array(
                        array(
                            'orderNumber' => $orderNumber,
                            'transactionId' => $novalnetResult['tid']
                        )
                    )
                );
                echo Zend_Json::encode($data);
            } else {
                $this->redirect(array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $novalnetResult['tid']
                ));
            }
        } else { // Transaction failure
            if ($this->getUser()['additional']['payment']['name'] == 'novalnetinvoice' || $this->getUser()['additional']['payment']['name'] == 'novalnetprepayment') {
                $this->nHelper->logPrepaymentInvoiceTransAccountInfo(array(
                    'tid' => $novalnetResult['tid'],
                    'test_mode' => ($novalnetResult['test_mode']) ? $novalnetResult['test_mode'] : $reference_details['test_mode'],
                    'account_holder' => $novalnetResult['invoice_account_holder'],
                    'account_number' => $novalnetResult['invoice_account'],
                    'bank_code' => $novalnetResult['invoice_bankcode'],
                    'bank_name' => $novalnetResult['invoice_bankname'],
                    'bank_city' => $novalnetResult['invoice_bankplace'],
                    'amount' => $novalnetResult['amount'],
                    'currency' => $novalnetResult['currency'],
                    'bank_iban' => $novalnetResult['invoice_iban'],
                    'bank_bic' => $novalnetResult['invoice_bic'],
                    'due_date' => ($novalnetResult['due_date']) ? $novalnetResult['due_date'] : ''
                ));
            }
            // s_novalnet_transaction_detail
            $this->nHelper->logInitialTransaction(array(
                'tid' => $novalnetResult['tid'],
                'tariff_id' => $config_details['novalnet_tariff'],
                'payment_id' => $paymentID,
                'payment_key' => $novalnetResult['payment_id'], // Novalnet payment key
                'payment_type' => $this->getUser()['additional']['payment']['name'],
                'amount' => $novalnetResult['amount'] * 100,
                'currency' => $this->getCurrencyShortName(),
                'status' => $novalnetResult['status'],
                'gateway_status' => ($novalnetResult['tid_status']) ? $novalnetResult['tid_status'] : 0,
                'test_mode' => $testMode,
                'customer_id' => $novalnetResult['customer_no'],
                'order_no' => $orderNumber,
                'callback_status' => 0,
                'date' => date('Y-m-d'),
                'account_holder' => $novalnetResult['first_name'] . ' ' . $novalnetResult['last_name'], // SEPA account holder
                'configuration_details' => serialize(array_filter($config_details)),
                'lang' => ($reference_details['lang']) ? $reference_details['lang'] : $this->lang
            ));
            $errorMessage = ($novalnetResult['status_desc'] != '') ? $novalnetResult['status_desc'] : $novalnetResult['status_text'];
            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => false,
                    'message' => $errorMessage
                );
                echo Zend_Json::encode($data);
            } else {
                return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($errorMessage));
            }
        }
    }

    /**
     * Cancel action handles nofity the cancelled transaction for redirect payments
     * Notify the cancelled transaction
     *
     * @param null
     * @return mixed
     */
    public function cancelAction()
    {
        $novalnetResult = $this->Request()->getPost();
        $id             = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array(
            $novalnetResult['order_no']
        ));
        if ((!in_array($novalnetResult['tid_status'], array(100,90,85,86,91,98,99,75)))) {
            $s_order_details = Shopware()->Db()->fetchAll('SELECT * FROM s_order_details   WHERE  orderID = ?', array(
                $id
            ));
            for ($i = 0; $i < count($s_order_details); $i++) {
                $stockStore                    = Shopware()->Db()->fetchAll('SELECT * FROM s_articles_details   WHERE  articleID = ?', array(
                    $s_order_details[$i]['articleID']
                ));
                $s_articles_details['instock'] = $stockStore[0]['instock'] + $s_order_details[$i]['quantity'];
                $this->nHelper->novalnetDbUpdate('s_articles_details ', $s_articles_details, 'articleID="' . $s_order_details[$i]['articleID'] . '"');
            }
            $orderStatus      = $this->configDetails['novalnet_onhold_order_cancelled'];
            $newLine          = PHP_EOL;
            $paymentShortName = !empty($novalnetResult['inputval4']) ? $novalnetResult['inputval4'] : $novalnetResult['payment_name'];
            $note = (($novalnetResult['status_message']) ? $novalnetResult['status_message'] : (($novalnetResult['status_text']) ? $novalnetResult['status_text'] : (($novalnetResult['status_desc']) ? $novalnetResult['status_desc'] : 'Payment was not successful. An error occurred'))) . $newLine;
            $note .= $this->novalnetLang['novalnet_payment_transdetails_info'] . $newLine;
            $note .= $this->novalnetLang['payment_name_' . $paymentShortName] . $newLine;
            $note .= $this->novalnetLang['novalnet_tid_label'] . ':  ' . $novalnetResult['tid'] . $newLine;
            $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array(
                $novalnetResult['order_no']
            ));
            Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $orderStatus, false);
            $sOrder['customercomment'] = $note;
            $sOrder['temporaryID']     = $novalnetResult['tid'];
            $sOrder['transactionID']   = $novalnetResult['tid'];
            $sOrder['cleared']         = $orderStatus;
            $this->nHelper->novalnetDbUpdate('s_order', $sOrder, 'ordernumber="' . $novalnetResult['order_no'] . '"');
        }
        //Store order details in novalnet table
        $this->nHelper->logInitialTransaction(array(
            'tid' => $novalnetResult['tid'],
            'tariff_id' => $this->configDetails['novalnet_tariff'],
            'payment_id' => (int) $this->user['additional']['payment']['id'],
            'payment_key' => $novalnetResult['key'],
            'payment_type' => $paymentShortName,
            'currency' => $novalnetResult['currency'],
            'status' => $novalnetResult['status'],
            'gateway_status' => ($novalnetResult['tid_status']) ? $novalnetResult['tid_status'] : 0,
            'customer_id' => $novalnetResult['customer_no'],
            'lang' => $this->lang,
            'order_no' => $novalnetResult['order_no'],
            'additional_note' => $note,
            'date' => date('Y-m-d')
        ));
        $this->session->nnErrormsg = $this->nHelper->getStatusDesc($this->Request()->getPost(), $this->novalnetLang['novalnet_payment_validate_payment']);
        $this->session->shopErrormsg = $this->novalnetLang['novalnet_shop_errormsg'];
        unset($_SESSION['novalnet']['isOrderCreated'], $_SESSION['novalnet']['novalnetOrderNo'], $this->session->create_payment_ref);
        return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->nHelper->getStatusDesc($this->Request()->getPost(), $this->novalnetLang['novalnet_payment_validate_payment'])));
    }

    /**
     * Create the basic parameter data
     *
     * @param null
     * @return null
     */
    public function createBasicParams()
    {
        $this->param['key']          = $this->novalnetPaymentKey;
        $this->param['payment_type'] = $this->nHelper->getPaymentType($this->paymentShortName);
        $this->param['amount']       = (in_array($this->paymentShortName, array(
            'novalnetcc',
            'novalnetsepa',
            'novalnetpaypal'
        )) && $this->configDetails[$this->paymentShortName . '_shopping_type'] == 'zero' && $this->configDetails['tariff_type'] == 2 && (($this->paymentShortName == 'novalnetsepa' && (($this->configDetails[$this->paymentShortName . '_guarantee_payment'] && !$this->guaranteeCheck && $this->configDetails[$this->paymentShortName . '_force_guarantee_payment']) || (!$this->configDetails[$this->paymentShortName . '_guarantee_payment']))) || ($this->paymentShortName != 'novalnetsepa'))) ? 0 : $this->amount;
        if ($this->param['amount'] == 0) {
            $this->param['create_payment_ref'] = '1';
        }
        $params = $this->nHelper->getCommonRequestParams($this->configDetails, $this->nnCustomerData, $this->paymentShortName);
        if (is_array($params)) {
            $this->param = array_merge($this->param, $params);
        } else {
            $this->errorMessage = $params;
            return;
        }        
        
        $manualCheckLimit = ($this->configDetails[$this->paymentShortName . '_manual_check_limit']) ? $this->configDetails[$this->paymentShortName . '_manual_check_limit'] : 0; 
        if (($this->configDetails[$this->paymentShortName.'_capture'] == 'authorize') && $this->nHelper->isDigits($manualCheckLimit) && (int) $this->amount >= (int) $manualCheckLimit && in_array($this->paymentShortName, array(
            'novalnetsepa',
            'novalnetinvoice',
            'novalnetcc',
            'novalnetpaypal'
        )) && $this->param['amount'] > 0) {
            
            $this->param['on_hold'] = 1;
        }
        //Form the encoded and hash parameter for redirection methods
        if ((in_array($this->paymentShortName, $this->nnRedirectPayments) && $this->paymentShortName != 'novalnetpaypal') || ($this->paymentShortName == 'novalnetcc' && ($this->configDetails['novalnetcc_force_cc3d'] || !empty($this->session->novalnet['novalnetcc']['nn_cc_do_redirect']))) || ($this->paymentShortName == 'novalnetpaypal' && ((isset($this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details']) && $this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details'] == 1)))) {
            $this->param['uniqid'] = $this->nHelper->getRandomString();
            $this->nHelper->encodeParams($this->param, $this->configDetails['novalnet_password'], $this->nnSecuredParams);

            $this->param['hash']           = $this->nHelper->generateHash($this->param, $this->configDetails['novalnet_password']);

            $this->param['implementation'] = 'ENC';
            if ($this->paymentShortName != 'novalnetcc') {
                $this->param['user_variable_0'] = $this->router->assemble(array(
                    'controller' => 'index'
                ));
            }
            $this->param['return_url']       = $this->router->assemble(array(
                'action' => 'return',
                'forceSecure' => true
            )) . '?uniquePaymentID=' . $this->uniquePaymentID;
            $this->param['error_return_url'] = $this->router->assemble(array(
                'action' => 'cancel',
                'forceSecure' => true
            ));
            $this->param['return_method']    = $this->param['error_return_method'] = 'POST';
            if ($this->paymentShortName == 'novalnetcc') {
                array_push($this->nnRedirectPayments, 'novalnetcc');
            }
        }
        //For guarantee payment
        $getBirthdate               = (!empty($this->session->novalnet[$this->paymentShortName]['birth_date'])) ? $this->session->novalnet[$this->paymentShortName]['birth_date'] : '';        
        $getBirthdates              = explode('-', $getBirthdate);
        $birthdate                  = ($getBirthdate) ? date('Y-m-d', strtotime($getBirthdate)) : '';
        $companyvalue = ($this->user['billingaddress']['company']) ? $this->user['billingaddress']['company'] : ($this->user['additional']['user']['company'] ? trim($this->user['additional']['user']['company']) : '');
                
        if (in_array($this->paymentShortName, array(
            'novalnetinvoice',
            'novalnetsepa',
        )) && $this->configDetails[$this->paymentShortName . '_guarantee_payment']) {
            $billing                    = $this->user['billingaddress'];
            $shipping                   = $this->user['shippingaddress'];
            if (!$this->guaranteeCheck && !$this->configDetails[$this->paymentShortName . '_force_guarantee_payment']) {
                $this->errorMessage = $this->guaranteeErrorMsg;
                return;
            } elseif ($getBirthdate == '' && !$this->configDetails[$this->paymentShortName . '_force_guarantee_payment'] && empty($companyvalue)) {
                $this->errorMessage = $this->novalnetLang['customer_age_empty'];
                return;
            } elseif ($getBirthdate && !checkdate($getBirthdates[1], $getBirthdates[2], $getBirthdates[0]) && empty($companyvalue)) {
                $this->errorMessage = $this->novalnetLang['customer_date_valid'];
                return;
            } elseif ($this->nHelper->validateAge($birthdate) && !$this->configDetails[$this->paymentShortName . '_force_guarantee_payment'] && empty($companyvalue)) {
                $this->errorMessage = $this->novalnetLang['customer_age_limit'];
                return;
            } elseif ($this->guaranteeCheck && (!$this->nHelper->validateAge($birthdate) || !empty($companyvalue))) {
                if ($this->paymentShortName == 'novalnetsepa') {
                    $this->param['key']          = 40;
                    $this->param['payment_type'] = 'GUARANTEED_DIRECT_DEBIT_SEPA';
                } else {
                    $this->param['key']          = 41;
                    $this->param['payment_type'] = 'GUARANTEED_INVOICE';
                }
                $this->param['birth_date']            = $birthdate;
            }
        }
        
        if (in_array($this->paymentShortName, array(
            'novalnetinvoice',
            'novalnetprepayment'
        ))) {
            $this->param['invoice_type'] = strtoupper(str_replace('novalnet', '', $this->paymentShortName));
            if ($this->configDetails[$this->paymentShortName . '_due_date'] && $this->nHelper->isDigits($this->configDetails[$this->paymentShortName . '_due_date'])) {
                $this->param['due_date'] = $this->nHelper->getInvoiceDueDate($this->configDetails['novalnetinvoice_due_date']);
            }
        }
        $novalnetcashpaymentDuedate = $this->nHelper->getInvoiceDueDate($this->configDetails['novalnetcashpayment_due_date']);
        if ($this->paymentShortName == 'novalnetcashpayment' && !empty($novalnetcashpaymentDuedate)) {
            $this->param['cp_due_date'] = $this->nHelper->getInvoiceDueDate($this->configDetails['novalnetcashpayment_due_date']);
        }
    }

    /**
     * Create the bank parameter data
     *
     * @param null
     * @return null
     */
    public function createAccountParams()
    {
        $this->session->novalnet['is_ref_order'] = 0;
        switch ($this->paymentShortName) {
            case 'novalnetcc':
            
                if ($this->configDetails['novalnetcc_force_cc3d']) {
                    $this->param['enforce_3d'] = 1;
                }
                $panhashVal = $this->session->novalnet['novalnetcc']['novalnet_cc_hash'];
                $uniqueId   = $this->session->novalnet['novalnetcc']['novalnet_cc_uniqueid'];
                if (empty($this->session->novalnet['novalnetcc']['nn_cc_new_acc_details'])) {
                    $paymentRefCC = (!empty($this->session->novalnet['novalnetcc_reference_tid']) ? $this->session->novalnet['novalnetcc_reference_tid'] : '');
                    if (!$this->errorMessage && empty($paymentRefCC)) {
                        $this->errorMessage = $this->novalnetLang['novalnet_payment_validate_invalid_cc_message'];
                        return;
                    }
                    $this->param['payment_ref']              = $paymentRefCC;
                    $this->session->novalnet['is_ref_order'] = 1;
                } elseif ($this->session->novalnet['novalnetcc']['nn_cc_new_acc_details'] == 1 || (!isset($this->session->novalnet['novalnetcc']['nn_cc_new_acc_details']))) {
                    if (!$this->errorMessage && !$panhashVal && !$uniqueId) {
                        $this->errorMessage = $this->novalnetLang['novalnet_payment_validate_invalid_cc_message'];
                        return;
                    }
                    $this->param['nn_it']     = 'iframe';
                    $this->param['pan_hash']  = $panhashVal;
                    $this->param['unique_id'] = $uniqueId;
                }
                if (!$this->param['payment_ref'] && (($this->configDetails['novalnetcc_shopping_type'] == 'one') || ($this->configDetails['tariff_type'] == 2 && $this->configDetails['novalnetcc_shopping_type'] == 'zero')) && $this->session->confirm_save) {
                    $this->param['create_payment_ref'] = $this->session->create_payment_ref = 1;
                }
                break;
            case 'novalnetsepa':
                $sepaDueDate = $this->configDetails['novalnetsepa_due_date'];
                if (!$this->errorMessage && $sepaDueDate != '' && ($sepaDueDate < 2 || $sepaDueDate > 14 || !$this->nHelper->isDigits($sepaDueDate))) {
                    $this->errorMessage = $this->novalnetLang['error_novalnet_sepa_due_date'];
                }
                if (empty($this->session->novalnet['novalnetsepa']['nn_sepa_new_acc_details'])) {
                    $paymentRefSepa = (!empty($this->session->novalnet['novalnetsepa_reference_tid']) ? $this->session->novalnet['novalnetsepa_reference_tid'] : '');
                    if (!$this->errorMessage && empty($paymentRefSepa)) {
                        $this->errorMessage = $this->novalnetLang['novalnet_payment_validate_invalid_directdebit_message'];
                        return;
                    }
                    $this->param['payment_ref']              = $paymentRefSepa;
                    $this->session->novalnet['is_ref_order'] = 1;
                } elseif ($this->session->novalnet['novalnetsepa']['nn_sepa_new_acc_details'] == 1 ||(!isset($this->session->novalnet['novalnetsepa']['nn_sepa_new_acc_details']))) {
                    if (!$this->errorMessage && (!$this->session->novalnet['novalnetsepa']['nn_sepa_owner'] || !$this->session->novalnet['novalnetsepa']['nn_sepa_iban'])) {
                        $this->errorMessage = $this->novalnetLang['novalnet_payment_validate_invalid_directdebit_message'];
                        return;
                    }
                    $this->param['bank_account_holder'] = $this->nHelper->getValidHolderName($this->session->novalnet['novalnetsepa']['nn_sepa_owner']);
                    $this->param['iban'] = $this->session->novalnet['novalnetsepa']['nn_sepa_iban'];
                }
                if (!$this->param['payment_ref'] && (($this->configDetails['novalnetsepa_shopping_type'] == 'one') || ($this->configDetails['tariff_type'] == 2 && $this->configDetails['novalnetsepa_shopping_type'] == 'zero' && !in_array($this->param['key'], array(40 , 41)))) && $this->session->confirm_save == 1) {
                    $this->param['create_payment_ref']             = $this->session->create_payment_ref = 1;
                }
                $due_date = ($sepaDueDate >= 2 && $sepaDueDate <= 14) ? date('Y-m-d', strtotime('+' . max(0, intval($sepaDueDate)) . ' days')) : '';
                if (!empty($due_date)) {
                    $this->param['sepa_due_date']                                   = $due_date;
                }
                $this->session->novalnet['novalnetsepa']['card_account_holder'] = $this->nHelper->getValidHolderName($this->param['bank_account_holder']);
                break;
            case 'novalnetinvoice':
                $invoiceDueDate = $this->configDetails['novalnetinvoice_due_date'];
                if (!$this->errorMessage && $invoiceDueDate != '' && ($invoiceDueDate <= 7    || !$this->nHelper->isDigits($invoiceDueDate))) {
                    $this->errorMessage = $this->novalnetLang['error_novalnet_invoice_due_date'];
                }
                break;
            case 'novalnetprepayment':
                $prepaymentDueDate = $this->configDetails['novalnetprepayment_due_date'];
                if (!$this->errorMessage && $prepaymentDueDate != '' && ($prepaymentDueDate < 7 || $prepaymentDueDate > 28 || !$this->nHelper->isDigits($prepaymentDueDate))) {
                    $this->errorMessage = $this->novalnetLang['error_novalnet_prepayment_due_date'];
                }
                break;
            case 'novalnetcashpayment':
                $cashpaymentDueDate = $this->configDetails['novalnetcashpayment_due_date'];
                if (!$this->errorMessage && $cashpaymentDueDate != '' && ($cashpaymentDueDate <= 2 || !$this->nHelper->isDigits($cashpaymentDueDate))) {
                    $this->errorMessage = $this->novalnetLang['error_novalnet_cashpayment_due_date'];
                }
                break;
            case 'novalnetpaypal': 
                if (((isset($this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details']) && $this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details'] == 0) || (!isset($this->session->novalnet['novalnetpaypal']['nn_paypal_new_acc_details'])))) {
                    
                    $paymentRefPaypal = (!empty($this->session->novalnet['novalnetpaypal_reference_tid']) ? $this->session->novalnet['novalnetpaypal_reference_tid'] : '');
                    if (!$this->errorMessage && empty($paymentRefPaypal)) {
                        $this->errorMessage = $this->novalnetLang['error_novalnet_general_message'];
                        return;
                    }
                    $this->param['payment_ref']              = $paymentRefPaypal;
                    $this->session->novalnet['is_ref_order'] = 1;
                }

                if (!$this->param['payment_ref'] && (($this->configDetails['novalnetpaypal_shopping_type'] == 'one') || ($this->configDetails['tariff_type'] == 2 && $this->configDetails['novalnetpaypal_shopping_type'] == 'zero')) && $this->session->confirm_save==1) {
                    $this->param['create_payment_ref']             = $this->session->create_payment_ref = 1;
                }
                break;
        }
    }

    /**
     * Save the successful transaction of the order
     *
     * @param array $novalnetResult
     * @param boolean $postback
     * @return mixed
     */
    public function novalnetSaveOrder($novalnetResult)
    {   
        $testMode                         = (int) ((isset($novalnetResult['test_mode']) && $novalnetResult['test_mode'] == 1) || ($this->configDetails[$this->paymentShortName . '_test_mode'] == 1));
        $this->session->nnCustomerComment = $this->session->sComment;
        $this->session->sComment          = '';
        $paymentPending                   = (in_array($novalnetResult['payment_id'], array('27','59')) || in_array($novalnetResult['tid_status'], array(90,86)));

        $paymentStatusId                  = ($paymentPending) ? $this->configDetails[$this->paymentShortName . '_before_paymenstatus'] : $this->configDetails[$this->paymentShortName . '_after_paymenstatus'];
        
        $paidDate                         = ($paymentPending || in_array($novalnetResult['tid_status'], array(91,85,99,98))) ? '' : date('Y-m-d');
        if (in_array($novalnetResult['tid_status'], array(91,85,99,98))) {
            $paymentStatusId                  = $this->configDetails['novalnet_onhold_order_complete'];
           
        }
        //Form the comments for invoice/prepayment payment method
        $paymentNameFilter                = ($this->paymentShortName == $novalnetResult['inputval4'] || ($this->paymentShortName == $novalnetResult['payment_name'])) ? $novalnetResult['inputval4'] : $this->paymentShortName;
        if ($novalnetResult['tid_status'] == '75') { // Infinite Pay updation for Guaratee invoice and Guaratee SEPA
            $paymentStatusId = $this->configDetails[$this->paymentShortName . '_guarantee_before_paymenstatus'];
        }
        $novalnetTransNote        = $this->nHelper->prepareComments($paymentNameFilter, $novalnetResult, $this->getCurrencyShortName(), $testMode, null, $this->configDetails['novalnet_product'], null, $this->configDetails);
        $this->session->sComment  = $novalnetTransNote . ((!in_array($this->paymentShortName, array(
            'novalnetinvoice',
            'novalnetprepayment'
        ))) ? $this->session->nnCustomerComment : '');
        $this->session->nnComment = $novalnetTransNote;
        // Assign Cashpayment checkout token and transaction mode in session
        if ($this->paymentShortName == 'novalnetcashpayment') {
            $this->session->cp_checkout_token = $novalnetResult['cp_checkout_token'];
            $this->session->transaction_mode  = $novalnetResult['test_mode'];
        }
        $orderNumber = $this->saveOrder($novalnetResult['tid'], $this->uniquePaymentID, $paymentStatusId);
        if (!empty($orderNumber)) {
            $sOrder['customercomment'] = str_replace('<br />', PHP_EOL, $this->session->sComment);
            $sOrder['temporaryID']     = $novalnetResult['tid'];
            $sOrder['transactionID']   = $novalnetResult['tid'];
            $sOrder['cleared']         = $paymentStatusId;
            if ($paidDate) {
                $sOrder['cleareddate'] = $paidDate;
            }
            if (version_compare(Shopware()->Config()->version, '5.0.0', '>=')) {
                $sOrder['referer'] = '';
            }
    
            $this->nHelper->novalnetDbUpdate('s_order', $sOrder, 'ordernumber="' . $orderNumber . '"');
            if ($this->paymentShortName == 'novalnetcc') {
                $this->configDetails = array_merge($this->configDetails, array(
                    'cc_no' => ($novalnetResult['cc_no']) ? $novalnetResult['cc_no'] : '',
                    'cc_holder' => ($novalnetResult['cc_holder']) ? $novalnetResult['cc_holder'] : '',
                    'cc_exp_year' => ($novalnetResult['cc_exp_year']) ? $novalnetResult['cc_exp_year'] : '',
                    'cc_exp_month' => ($novalnetResult['cc_exp_month']) ? $novalnetResult['cc_exp_month'] : '',
                    'cc_card_type' => ($novalnetResult['cc_card_type']) ? $novalnetResult['cc_card_type'] : ''
                ));
            } elseif ($this->paymentShortName == 'novalnetsepa') {
                $acc_holder          = ($novalnetResult['bankaccount_holder']) ? $novalnetResult['bankaccount_holder'] : '';
                $this->configDetails = array_merge($this->configDetails, array(
                    'bankaccount_holder' => $acc_holder,
                    'iban' => ($novalnetResult['iban']) ? $novalnetResult['iban'] : '',
                    'birth_date' => ($this->param['date_birth']) ? $this->param['date_birth'] : ''
                ));
            } elseif ($this->paymentShortName == 'novalnetpaypal') {
                $this->configDetails = array_merge($this->configDetails, array(
                    'paypal_transaction_id' => ($novalnetResult['paypal_transaction_id']) ? $novalnetResult['paypal_transaction_id'] : ''
                ));
            }
            $this->configDetails['holder_name'] = $this->param['first_name'] ? $this->param['first_name'] . ' ' . $this->param['last_name'] : utf8_decode($novalnetResult['first_name']) . ' ' . utf8_decode($novalnetResult['last_name']);
            
            if (in_array($this->paymentShortName, array(
                'novalnetpaypal',
                'novalnetcc',
                'novalnetsepa'
            )) && $this->configDetails[$this->paymentShortName . '_shopping_type'] == 'zero' && $this->configDetails['tariff_type'] == 2) {
                $this->configDetails = array_merge($this->configDetails, $this->session->novalnet_server_request);
            }            
         
            if (isset($this->session->create_payment_ref)) {
                $this->configDetails['create_payment_ref'] = $this->session->create_payment_ref;
            }
                        
            //Store order details in novalnet table
            $this->nHelper->logInitialTransaction(array(
                'tid' => $novalnetResult['tid'],
                'tariff_id' => $this->configDetails['novalnet_tariff'],
                'subs_id' => ($novalnetResult['subs_id']) ? $novalnetResult['subs_id'] : 0,
                'payment_id' => (int) $this->user['additional']['payment']['id'],
                'payment_key' => (!empty($this->param['key']) && !in_array($this->paymentShortName, $this->nnRedirectPayments)) ? $this->param['key'] : $this->novalnetPaymentKey,
                'payment_type' => $this->paymentShortName,
                'amount' => ($this->paymentShortName == 'novalnetpaypal' && $this->configDetails[$this->paymentShortName . '_shopping_type'] == 'zero' && $this->configDetails['tariff_type'] == 2) ? 0 : ((strpos($novalnetResult['amount'], '.')) ? sprintf('%0.2f', $novalnetResult['amount']) * 100 : $novalnetResult['amount']),
                'currency' => $this->getCurrencyShortName(),
                'status' => $novalnetResult['status'],
                'gateway_status' => ($novalnetResult['tid_status']) ? $novalnetResult['tid_status'] : 0,
                'test_mode' => $testMode,
                'customer_id' => $this->nnCustomerData['customer_no'],
                'order_no' => $orderNumber,
                'date' => date('Y-m-d'),
                'additional_note' => $sOrder['customercomment'],
                'configuration_details' => serialize(array_filter($this->configDetails)),
                'lang' => $this->lang,
                'is_ref_order' => (in_array($this->paymentShortName, array(
                    'novalnetcc',
                    'novalnetsepa',
                    'novalnetpaypal'
                ))) ? (int) $this->session->novalnet['is_ref_order'] : 0,
                'due_date' => (in_array($this->novalnetPaymentKey, array(
                    27,
                    59
                ))) ? ($novalnetResult['due_date']) ? $novalnetResult['due_date'] : $novalnetResult['cp_due_date'] : ''

            ));
                        
            //Validate the backend configuration and send the order number to the server
			if ($this->nHelper->isDigits($this->novalnetPaymentKey) && $novalnetResult['tid'] && $orderNumber) {
				$callBackParams = array(
					'vendor' => $this->configDetails['novalnet_vendor'],
					'auth_code' => $this->configDetails['novalnet_auth_code'],
					'product' => $this->configDetails['novalnet_product'],
					'tariff' => $this->configDetails['novalnet_tariff'],
					'key' => (in_array($this->paymentShortName, array(
						'novalnetinvoice',
						'novalnetsepa'
					)) && $this->param['key'] != '') ? $this->param['key'] : $this->novalnetPaymentKey,
					'remote_ip' => $this->nHelper->getIp(),
					'status' => '100',
					'tid' => $novalnetResult['tid'],
					'order_no' => $orderNumber
				);
				if (in_array($callBackParams['key'], array(
					'27',
					'41'
				))) {
					$callBackParams['invoice_ref'] = 'BNR-' . $this->configDetails['novalnet_product'] . '-' . $orderNumber;
				}
				$callBackParams = array_map('trim', $callBackParams);
				$this->nHelper->curlCallRequest($callBackParams, $this->novalnetGatewayUrl['paygate_url']);
			}
        }
        $removeFlag   = $this->paymentShortName . '_gatewayAction';
        
        unset($this->session->sComment, $this->session->novalnet, $this->session->nnCustomerComment, $this->session->novalnet_server_request,$this->session->create_payment_ref,$this->session->$removeFlag);
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'finish',
            'sUniqueID' => $novalnetResult['tid']
        ));
        return $this;
    }

    /**
     * Called when the novalnet callback-script execution
     *
     * @param null
     * @return null
     */
    public function statusAction()
    {
        $view = $this->View();
        $callObj = new Shopware_Plugins_Frontend_NovalPayment_lib_classes_PaymentNotification($this->Request()->getParams(), $view);
    }
}
