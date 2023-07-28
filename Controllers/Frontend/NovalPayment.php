<?php
/**
 * Novalnet payment plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 * that is bundled with this package in the file freeware_license_agreement.txt
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
 *
 * @category Novalnet
 * @package NovalPayment
 * @copyright Copyright (c) Novalnet
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentRequest;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Plugins\NovalPayment\Components\JsonableResponseTrait;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentNotification;
use SwagAboCommerce\Services\AboOrderException;
use Shopware\Plugins\NovalPayment\Components\Classes\ArrayMapHelper;
use Shopware\Components\Model\ModelManager;

/**
 * class Shopware_Controllers_Frontend_NovalPayment
 *
 * This class is hooking into the Frontend controller of Shopware.
 */
class Shopware_Controllers_Frontend_NovalPayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    use JsonableResponseTrait;

    /**
     * @var PaymentRequest
     */
    private $service;

    /**
     * @var \Enlight_Controller_Router
     */
    private $router;

    /**
     * @var NovalnetHelper
     */
    private $helper;

    private $session;

    /**
     * @var string
     */
    private $errorUrl;

    /**
     * @var array
     */
    private $configDetails;

    private $orderDetails;

    /**
     * @var ManageRequest
     */
    private $requestHandler;

    /**
     * @var DataHandler
     */
    private $dataHandler;

    /**
     * Initiate the novalnet configuration
     * Assign the configuration and user values
     *
     * @return void
     */
    public function preDispatch()
    {
        $this->router         = $this->Front()->Router();
        $this->errorUrl       = $this->router->assemble(['controller' =>'checkout', 'action' =>'shippingPayment','sTarget' =>'checkout']);
        $this->session        = $this->get('session');
        $this->orderDetails   = $this->session->get('sOrderVariables');
        $this->helper         = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $this->service        = new PaymentRequest($this->helper, $this->session);
        $this->configDetails  = $this->helper->getConfigurations();
        $this->requestHandler = new ManageRequest($this->helper);
        $this->dataHandler    = new DataHandler(Shopware()->Models());
    }

    /**
     * Return a list with names of whitelisted actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['return', 'cancel', 'status', 'recurring'];
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() == NOVALNET_PAYMENT_NAME) {
            return $this->redirect(['action' => 'gateway','forceSecure' => true]);
        }
        return $this->redirect(['controller' => 'checkout']);
    }

    /**
     * gatewayAction  method.
     *
     * Forwards to the correct action.
     */
    public function gatewayAction()
    {
        if (empty($this->orderDetails)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode('Could not find the order details'));
        } elseif (!$this->orderDetails->sUserData['billingaddress']) {
            $this->router->assemble(['controller' => 'checkout']);
        } elseif (!$this->configDetails['novalnet_secret_key'] || !$this->configDetails['novalnet_tariff']) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('BasicParamError')));
        }

        $this->forward('processRequest');
    }

    /**
     * Form Novalnet Request Params
     *
     * @return mixed
     */
    public function processRequestAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $sessionData = [];
        if (!empty($this->session->offsetGet('novalnetPay'))) {
            $sessionData = $this->session->offsetGet('novalnetPay')->getArrayCopy();
        }
        $paymentParams = $this->service->getRequestParams();
        $paymentParams['transaction']['amount'] = number_format($this->getAmount(), 2, '.', '') * 100;

        if ($sessionData['booking_details']['payment_action'] == 'zero_amount') {
            $paymentParams['transaction']['amount'] = (int) 0;
            $this->session->offsetSet('merchant_details', $paymentParams['merchant']);
        }

        $basket = $this->getBasket();
        foreach ($basket['content'] as $content) {
            if (! empty($content['abo_attributes'])) {
                $paymentParams['custom']['input2'] = 'shop_subs';
                $paymentParams['custom']['inputval2'] = 1;
                if (!empty($paymentParams['transaction']['payment_data']) || $paymentParams['transaction']['payment_type'] == 'PAYPAL') {
                    $paymentParams['transaction']['create_token'] = 1;
                }
            }
        }

        $endpoint = $this->helper->getActionEndpoint('payment');

        if ($sessionData['booking_details']['payment_action'] == 'authorized') {
            $endpoint = $this->helper->getActionEndpoint('authorize');
        }

        if (! empty($sessionData['payment_details']['name']) && $paymentParams['custom']['input2'] == 'temporary_order_id' && ! empty($paymentParams['custom']['inputval2'])) {
            $sql = 'UPDATE s_order_attributes SET novalnet_payment_name = ? WHERE orderID=?';
               Shopware()->Db()->query($sql, [ $sessionData['payment_details']['name'],  $paymentParams['custom']['inputval2'] ]);
        }
        
        $novalnetPaymentName = Shopware()->Db()->fetchOne('SELECT novalnet_payment_name FROM s_order_attributes WHERE orderID = ?', [ (int) $paymentParams['custom']['inputval2']]);
        
        if (empty($novalnetPaymentName) && $paymentParams['custom']['input2'] == 'temporary_order_id') {
            $db = Shopware()->Container()->get('models')->getConnection();
            $db->createQueryBuilder()
                ->insert('s_order_attributes')
                ->setValue('orderID', ':orderID')
                ->setValue('novalnet_payment_name', ':novalnet_payment_name')
                ->setParameter('orderID', $paymentParams['custom']['inputval2'])
                ->setParameter('novalnet_payment_name', $sessionData['payment_details']['name'])
                ->execute();
        }

                
        $response = $this->requestHandler->curlRequest($paymentParams, $endpoint);

        if ($response['result']['status'] == 'SUCCESS') {
            if (!empty($sessionData['booking_details']['payment_action']) && $sessionData['booking_details']['payment_action'] == 'zero_amount') {
                $response['transaction']['payment_action'] = $sessionData['booking_details']['payment_action'];
            }

            if (!empty($response['result']['redirect_url'])) {
                $this->session->offsetSet('novalnet_txn_secret', $response['transaction']['txn_secret']);
                return $this->redirect($response['result']['redirect_url']);
            }

            //For handling the novalnet server response and complete the order
            $response['transaction']['payment_name'] = $sessionData['payment_details']['name'];

            $this->novalnetSaveOrder($response);
        } else {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('orderProcessError'))));
        }
    }
    
    /**
     * Create order and reinitialize the construct data for apple pay and google pay
     *
     * @return void
     */
    public function createWalletPaymentOrderAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $postData       = $this->Request()->getPost();
        $response       = $this->helper->unserializeData($postData['serverResponse']);
        $novalPaymentId = $this->helper->unserializeData($postData['novalPaymentId']);
        $this->session->offsetSet('novalnetPay', $response);
        $userData = $this->helper->getUserInfo();
        $endpoint = $this->helper->getActionEndpoint('payment');
        $payment = Shopware()->Modules()->Admin()->sGetPaymentMeanById($this->session['sPaymentID'], $this->View()->$userData);
        
        if ($response['booking_details']['payment_action'] == 'authorized') {
            $endpoint = $this->helper->getActionEndpoint('authorize');
        }

        if (!empty($novalPaymentId)) {
            Shopware()->Session()->offsetSet('sPaymentID', $novalPaymentId);
        }

        $userData['additional']['payment'] = $payment;
        $userData['additional']['charge_vat'] = true;

        if ($this->helper->isTaxFreeDelivery($userData) || !empty($userData['additional']['countryShipping']['taxfree'])) {
            $userData['additional']['charge_vat'] = false;
            $this->session->offsetSet('taxFree', true);
            Shopware()->System()->sUSERGROUPDATA['tax'] = 0;
            Shopware()->System()->sCONFIG['sARTICLESOUTPUTNETTO'] = 1;
            Shopware()->Session()->set('sOutputNet', true);
        }

        $basket  = $this->helper->getBasket();
        $sOrderVariables['sBasketView'] = $sOrderVariables['sBasket'] = $basket;
        $sOrderVariables['sUserData'] = $userData;
        $sOrderVariables['sCountry'] = $userData['additional']['countryShipping'];
        $sOrderVariables['sDispatch'] = Shopware()->Db()->fetchRow('SELECT * FROM s_premium_dispatch WHERE  id = ?', [$this->session['sDispatch']]);
        $sOrderVariables['sPayment'] = $payment;
        $sOrderVariables['sLaststock'] = Shopware()->Modules()->Basket()->sCheckBasketQuantities();
        $sOrderVariables['sShippingcosts'] = $basket['sShippingcosts'];
        $sOrderVariables['sShippingcostsDifference'] = $basket['sShippingcostsDifference'];
        $sOrderVariables['sAmount'] = $sOrderVariables['Amount'] = $basket['sAmount'];
        $sOrderVariables['sAmountWithTax'] = $basket['sAmountWithTax'];
        $sOrderVariables['sAmountTax'] = $basket['sAmountTax'];
        $sOrderVariables['sAmountNet'] = $basket['AmountNetNumeric'];
        $sOrderVariables['AmountNumeric'] = $basket['AmountNumeric'];
        $sOrderVariables['AmountNetNumeric'] = $basket['AmountNetNumeric'];
        $this->session['sOrderVariables'] = new ArrayObject($sOrderVariables, ArrayObject::ARRAY_AS_PROPS);
                
        if (! empty($response['payment_details']['name']) && $paymentParams['custom']['input2'] == 'temporary_order_id' && ! empty($paymentParams['custom']['inputval2'])) {
            $sql = 'UPDATE s_order_attributes SET novalnet_payment_name = ? WHERE orderID=?';
               Shopware()->Db()->query($sql, [ $response['payment_details']['name'],  $paymentParams['custom']['inputval2'] ]);
        }
        
        $novalnetPaymentName = Shopware()->Db()->fetchOne('SELECT novalnet_payment_name FROM s_order_attributes WHERE orderID = ?', [ (int) $paymentParams['custom']['inputval2']]);
        
        if (empty($novalnetPaymentName) && $paymentParams['custom']['input2'] == 'temporary_order_id') {
            $db = Shopware()->Container()->get('models')->getConnection();
            $db->createQueryBuilder()
                ->insert('s_order_attributes')
                ->setValue('orderID', ':orderID')
                ->setValue('novalnet_payment_name', ':novalnet_payment_name')
                ->setParameter('orderID', $paymentParams['custom']['inputval2'])
                ->setParameter('novalnet_payment_name', $response['payment_details']['name'])
                ->execute();
        }

        $paymentParams = $this->service->getRequestParams();
        $serverResponse = $this->requestHandler->curlRequest($paymentParams, $endpoint);
        $serverResponse['transaction']['payment_name'] = $response['payment_details']['name'];
        
        if ($serverResponse['result']['status'] == 'SUCCESS') {
            //For Gpay enforce cc3d
            if (!empty($serverResponse['result']['redirect_url']) && $serverResponse['transaction']['payment_type'] == 'GOOGLEPAY') {
                $url = $serverResponse['result']['redirect_url'];
                $this->session->offsetSet('novalnet_txn_secret', $serverResponse['transaction']['txn_secret']);
            } else {
                //For handling the novalnet server response and complete the order
                $url = $this->novalnetSaveOrder($serverResponse, true, true);
            }
            $this->jsonResponse([
                'success' => true,
                'url' => $url
            ]);
            return;
        } else {
            $this->jsonResponse([
                'success' => true,
                'url' => $this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($serverResponse, $this->helper->getLanguageFromSnippet('orderProcessError')))
            ]);
            return;
        }
    }

    /**
     * Return action method.
     *
     * Forwards to the correct action.
     */
    public function returnAction()
    {
        $response = $this->Request()->getParams();

        if (empty(Shopware()->Modules()->Basket()->sGetBasket())) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode('Order is already mapped'));
        }

        if ($response['status'] !== 'SUCCESS') {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('orderProcessError')));
        }
        $generatedHash = $this->service->generateCheckSumToken($response);

        if ($response['checksum'] === $generatedHash) {
            $transactionDetails = $this->requestHandler->retrieveDetails($response['tid']);
            $sessionData = ! empty($this->session->offsetGet('novalnetPay')->getArrayCopy()) ? $this->session->offsetGet('novalnetPay')->getArrayCopy() : [] ;
            $transactionDetails['transaction']['payment_name'] = ! empty($sessionData['payment_details']['name']) ? $sessionData['payment_details']['name'] : null ;

            $this->novalnetSaveOrder($transactionDetails);
        } else {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('hashCheckFailedError')));
        }
    }

    /**
     * Save the successful transaction of the order
     *
     * @param array $result
     * @param boolean $postback
     * @return mixed
     */
    public function novalnetSaveOrder($result, $postback = true, $isExpressCheckout = false)
    {
        $novalnetTransNote = $this->helper->formCustomerComments($result, $this->orderDetails['sBasketProportional']['sCurrencyName']);
        
        if (in_array(Shopware()->Config()->get('Version'), ['5.2.0','5.3.0','5.2.27','5.4.0'])) {
            $this->session->sComment = $this->session->sComment . str_replace('<br />', PHP_EOL, $novalnetTransNote);
            $customercomment = $this->session->sComment;
        } else {
            $this->session->sComment = $this->session->sComment . $novalnetTransNote;
            $customercomment = str_replace('<br />', PHP_EOL, $this->session->sComment);
        }

        $this->session->offsetSet('serverResponse', $result);

        if (!empty($result['transaction']['checkout_token'])) {
            $this->session->nncheckoutJs    = $result['transaction']['checkout_js'];
            $this->session->nncheckoutToken = $result['transaction']['checkout_token'];
        }

        $orderNumber = $this->saveOrder($result['transaction']['tid'], $result['transaction']['tid'], $this->helper->getPaymentStatusId($result));

        if (!empty($orderNumber)) {
            //Validate the backend configuration and send the order number to the server
            if ($result['transaction']['tid'] && $orderNumber && $postback && !(preg_match("/INVOICE/i", $result['transaction']['payment_type']) || preg_match("/PREPAYMENT/i", $result['transaction']['payment_type']) )) {
                //update order number for transaction
                $this->requestHandler->postCallBackProcess($orderNumber, $result['transaction']['tid']);
            }

            $insertData = $this->helper->handleResponseData($result, $orderNumber);
            $customercomment = !empty($result['transaction']['bank_details']) ? str_replace('<br />', PHP_EOL, Shopware()->Modules()->Order()->sComment) : $customercomment;

            $sOrder = [
                'customercomment' => $customercomment,
                'ordernumber' => $orderNumber
            ];

            $OrderId = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', [$orderNumber]);
            $paymentName = $this->helper->unserializeData($insertData['configuration_details'])['payment_name'];

            if (! empty($paymentName) && ! empty($OrderId)) {
                $sql = 'UPDATE s_order_attributes SET novalnet_payment_name = ? WHERE orderID=?';
                Shopware()->Db()->query($sql, [ $paymentName,  $OrderId ]);
                $this->session->nnOrderPaymentName    = $paymentName;
            }

            // update order table
            $this->dataHandler->updateOrdertable($sOrder);

            //Store order details in novalnet table
            $this->dataHandler->insertNovalnetTransaction($insertData);

            $this->helper->unsetSession();

            if ($isExpressCheckout) {
                return $this->router->assemble(array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $result['transaction']['tid']
                ));
            } else {
                $this->redirect([
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $result['transaction']['tid']
                ]);
            }
        }
        
        return $this;
    }

    /**
     * Cancel action method.
     *
     * Forwards to the correct action.
     */
    public function cancelAction()
    {
        $novalnetResponse = $this->Request()->getParams();

        if (empty($this->orderDetails)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode('Could not find the order details'));
        }

        //Check the Novalnet server status for the failure order
        if (empty($novalnetResponse['status_code'])) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('orderProcessError')));
        }

        //retrive the transaction from the TID
        $transactionDetails = $this->requestHandler->retrieveDetails($novalnetResponse['tid']);

        $transactionDetails = new ArrayMapHelper($transactionDetails);

        if (empty($transactionDetails->getData('transaction/order_no')) ||  empty($transactionDetails->getData('transaction/payment_type'))) {
            $this->helper->unsetSession();
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($novalnetResponse['status_text']));
        }

        $this->dataHandler->insertNovalnetTransaction([
            'tid' => $novalnetResponse['tid'],
            'payment_type' => $transactionDetails->getData('transaction/payment_type') ,
            'amount' => number_format($this->getAmount(), 2, '.', '') * 100,
            'paid_amount' => 0,
            'currency' => $this->getCurrencyShortName(),
            'gateway_status' => $transactionDetails->getData('transaction/status') ?  $transactionDetails->getData('transaction/status') : $novalnetResponse['status'],
            'order_no' => $transactionDetails->getData('transaction/order_no') ,
            'customer_id' => $transactionDetails->getData('customer/customer_no') ,
        ]);

        $this->helper->unsetSession();

        return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($transactionDetails->getData(), $this->helper->getLanguageFromSnippet('orderProcessError'))));
    }

    /**
     * Called when the novalnet callback-script execution
     */
    public function statusAction()
    {
        $callbackObj = new PaymentNotification($this->Request()->getRawBody(), $this->View());
    }

    /**
     * Recurring payment action method for adapt the AboCommerce.
     *
     * @return mixed
     */
    public function recurringAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $order             = Shopware()->Modules()->Order()->getOrderById($this->Request()->getParam('orderId'));
        $GetParentOrderId  = "SELECT order_id FROM `s_plugin_swag_abo_commerce_orders` WHERE last_order_id = ?";
        $parentOrderId     = Shopware()->Db()->fetchOne($GetParentOrderId, [$this->Request()->getParam('orderId')]);
        $parentOrderData   = Shopware()->Modules()->Order()->getOrderById($parentOrderId);
        $paymentData       = Shopware()->Db()->fetchRow('SELECT * FROM s_novalnet_change_subscription_payment WHERE order_no = ? ORDER BY id DESC', array($order['ordernumber']));
        $orderNumber       = ! empty($parentOrderData['ordernumber']) ? $parentOrderData['ordernumber'] : (! empty($parentOrderData['order_number']) ? $parentOrderData['order_number'] : $order['ordernumber'] );

        $referenceDetails  = Shopware()->Db()->fetchRow('SELECT * FROM s_novalnet_transaction_detail WHERE order_no = ? AND tid != 0 ORDER BY id DESC', [$orderNumber]);
        $nnPaymentType   = $this->helper->getPaymentType($referenceDetails['payment_type']);
        $nnGatewayStatus = $this->helper->getStatus($referenceDetails['gateway_status'], $referenceDetails, $nnPaymentType);
        
        if (( in_array($nnPaymentType, ['INVOICE', 'PREPAYMENT', 'CASHPAYMENT', 'MULTIBANCO']) && $nnGatewayStatus == NOVALNET_PENDING_STATUS ) ||
             $nnGatewayStatus == NOVALNET_CONFIRMED_STATUS
        ) {
            Shopware()->Session()->offsetSet('sPaymentID', $order['paymentID']);
            Shopware()->Session()->offsetSet('sUserId', $order['userID']);
            Shopware()->Session()->offsetSet('sDispatch', $order['dispatchID']);

            $referenceData = Shopware()->Db()->fetchRow('SELECT * FROM s_novalnet_transaction_detail WHERE order_no = ? ORDER BY id DESC', array($order['ordernumber']));
            $ConfigDetails = $this->helper->unserializeData(! empty($paymentData['payment_data']) ? $paymentData['payment_data'] : $referenceData['configuration_details']);
            $userData    = Shopware()->Modules()->Admin()->sGetUserData();
            $transactionData = !empty($paymentData['payment_data']) ? $ConfigDetails : $referenceData;

            $data['merchant']    = $this->service->getMerchantDetails();
            $data['customer']    = $this->service->getCustomerData($userData);
            $data['transaction'] = $this->service->getTransactionDetails($transactionData);
            $data['custom']      = $this->service->getCustomDetails();

            if (!empty($transactionData)) {
                if (empty($data['transaction']['payment_type'])) {
                    $data['transaction']['payment_type'] = $this->helper->getPaymentType($transactionData['payment_type']);
                    $data['transaction']['test_mode']    = !empty($ConfigDetails['test_mode']) ? 1 : 0 ;
                }

                if (! empty($ConfigDetails['token'])) {
                    $data['transaction']['payment_data']['token'] = $ConfigDetails['token']  ;
                }

                if (empty($data['transaction']['payment_data']['token']) && !empty($ConfigDetails['payment_data'])) {
                    $data['transaction']['payment_data'] = $ConfigDetails['payment_data'];
                }
            }

            $birthDate = ! empty($ConfigDetails['booking_details']['birth_date']) ? $ConfigDetails['booking_details']['birth_date'] : $ConfigDetails['birth_date'];
            if (!empty($birthDate) && empty($data['customer']['billing']['company'])) {
                $data['customer']['birth_date'] = $birthDate;
            }
            
            // For lower version compatibility
            if (in_array($data['transaction']['payment_type'], ['DIRECT_DEBIT_SEPA', 'CREDITCARD', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'PAYPAL']) && empty($data['transaction']['payment_data'])) {
                $data['transaction']['payment_data']['payment_ref'] = $transactionData['tid'];
            }

            if (! empty($data['transaction']['payment_data'])) {
                $data['transaction']['create_token'] = 1;
            }

            $endpoint = $this->helper->getActionEndpoint('payment');

            $response = $this->requestHandler->curlRequest($data, $endpoint);

            $response['transaction']['payment_name'] = ! empty($ConfigDetails['payment_details']['name']) ? $ConfigDetails['payment_details']['name'] : $ConfigDetails['payment_name'];

            if (empty($response['transaction']['payment_data']['token']) && ! empty($data['transaction']['payment_data']['token'])) {
                $response['transaction']['payment_data']['token'] = $data['transaction']['payment_data']['token'];
            }

            if (empty($response['transaction']['payment_data']['token']) && ! empty($data['transaction']['payment_data'])) {
                $response['transaction']['ref_payment_data'] = $data['transaction']['payment_data'];
            }

            //For handling the novalnet server response and complete the order
            return $this->handleResponse($response, $userData['additional']['user']['customernumber']);
        } else {
            $errorMessage = $this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('orderProcessError'));
            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => false,
                    'message' => $errorMessage
                );
                echo $this->helper->serializeData($data);
                Shopware()->Container()->get('pluginlogger')->error($errorMessage);
            }
        }
    }

    /**
     * Handle response for Abo commerce orders.
     *
     * @param array $response
     * @param $customerNumber
     *
     * @return mixed
     */
    public function handleResponse($response, $customerNumber)
    {
        $insertData = $this->helper->handleResponseData($response);
        if ($response['result']['status'] == 'SUCCESS') {
            $novalnetTransNote = $this->helper->formCustomerComments($response, $this->getCurrencyShortName());
            $this->session->offsetSet('serverResponse', $response);
            $this->session->sComment = $novalnetTransNote;

            // Create the order for novalnet direct payments
            $orderNumber = $this->saveOrder($response['transaction']['tid'], $response['transaction']['tid'], $this->helper->getPaymentStatusId($response));

            //Validate the backend configuration and send the order number to the server
            if ($response['transaction']['tid'] && $orderNumber && !(preg_match("/INVOICE/i", $response['transaction']['payment_type']) || preg_match("/PREPAYMENT/i", $response['transaction']['payment_type']) )) {
                //update order number for transaction
                $this->requestHandler->postCallBackProcess($orderNumber, $response['transaction']['tid']);
            }

            if ($orderNumber) {
                $insertData['order_no'] = $orderNumber;
            }

            $sOrder = [
                'customercomment' => str_replace('<br />', PHP_EOL, $this->session->sComment),
                'ordernumber' => $orderNumber
            ];

            // update order table
            $this->dataHandler->updateOrdertable($sOrder);

            //Store order details in novalnet table
            $this->dataHandler->insertNovalnetTransaction($insertData);

            $this->helper->unsetSession();

            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => true,
                    'data' => array(
                        array(
                            'orderNumber' => $orderNumber,
                            'transactionId' => $response['transaction']['tid']
                        )
                    )
                );
                echo $this->helper->serializeData($data);
            } else {
                return $this->redirect(array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $response['transaction']['tid']
                ));
            }
        } else {
            //Store order details in novalnet table
            $this->dataHandler->insertNovalnetTransaction($insertData);

            $errorMessage = $this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('orderProcessError'));
            if ($this->Request()->isXmlHttpRequest()) {
                $data = array(
                    'success' => false,
                    'message' => $errorMessage
                );
                echo $this->helper->serializeData($data);
            } else {
                return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($errorMessage));
            }
        }
    }
}
