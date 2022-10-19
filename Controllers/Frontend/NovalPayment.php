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
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetValidator;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentNotification;
use Shopware\Plugins\NovalPayment\Components\JsonableResponseTrait;

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
    private $uniquePaymentID;

    /**
     * @var string
     */
    private $errorUrl;

    /**
     * @var NovalnetValidator
     */
    private $validator;

    /**
     * @var string
     */
    private $paymentShortName;

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
        $this->router    = $this->Front()->Router();
        $this->errorUrl  = $this->router->assemble(['controller' =>'checkout', 'action' =>'shippingPayment','sTarget' =>'checkout']);
        $this->helper    = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $this->validator = new NovalnetValidator($this->helper);
        $this->session   = $this->get('session');
        $this->service   = new PaymentRequest($this->helper, $this->session);
        $this->requestHandler = new ManageRequest($this->helper);
        $this->dataHandler    = new DataHandler(Shopware()->Models());
        $this->configDetails  = $this->helper->getConfigurations();
        $this->orderDetails  = $this->session->get('sOrderVariables');
        $this->paymentShortName = $this->getPaymentShortName();
        $this->uniquePaymentID  = $this->createPaymentUniqueId();
    }

    /**
     * Return a list with names of whitelisted actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['return','cancel','status','recurring', 'deleteCard','getAvailableShipping','createApplePayOrder'];
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        if (array_key_exists($this->getPaymentShortName(), $this->helper->getPaymentInfo())) {
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
        $basket = $this->getBasket();
        $params = $this->service->getRequestParams($this->uniquePaymentID, $this->router, $this->paymentShortName);
        $params['transaction']['amount'] = number_format($this->getAmount(), 2, '.', '') * 100;
        foreach($basket['content'] as $content)
        {
			if (!empty($content['abo_attributes'])) {
				$params['custom']['input2'] = 'shop_subs';
				$params['custom']['inputval2'] = 1;
				if (!empty($params['transaction']['payment_data'])) {
					$params['transaction']['create_token'] = 1;
				}
			}
		}
        $endpoint = $this->helper->getActionEndpoint('payment');
        $sessionData = [];

        if (!empty($this->session->offsetGet('novalnet'))) {
            $sessionData = $this->session->offsetGet('novalnet')->getArrayCopy();
        }

        $isRedirect = (in_array($this->paymentShortName, $this->helper->getRedirectPayments())) || ($this->paymentShortName === 'novalnetcc' && ($sessionData['doRedirect'] == 1));
        $manualCheckLimit = ($this->configDetails[$this->paymentShortName . '_manual_check_limit']) ? $this->configDetails[$this->paymentShortName . '_manual_check_limit'] : 0;

        if (in_array($this->paymentShortName, ['novalnetinvoiceGuarantee', 'novalnetsepaGuarantee']) && (empty($params['customer']['birth_date']) || $this->validateAge($params['customer']['birth_date'])) && empty($params['customer']['billing']['company'])) {
            $sepaPayment = $this->dataHandler->getPaymentMethod(['name' => 'novalnetsepa']);
            $invoicePayment = $this->dataHandler->getPaymentMethod(['name' => 'novalnetinvoice']);

            if ($this->paymentShortName === 'novalnetsepaGuarantee' && !empty($sepaPayment) && $sepaPayment->getActive() && !empty($sessionData['doForceSepaPayment'])) {
                $params['transaction']['payment_type'] = 'DIRECT_DEBIT_SEPA';
                $this->paymentShortName = 'novalnetsepa';
                $this->session['sPaymentID'] = $sepaPayment->getId();
                $this->session['sOrderVariables']['sPayment'] = $this->session['sOrderVariables']['sUserData']['additional']['payment'] = Shopware()->Modules()->Admin()->sGetPaymentMeanById($sepaPayment->getId());
                Shopware()->Modules()->Admin()->sUpdatePayment($sepaPayment->getId());
            } elseif ($this->paymentShortName === 'novalnetinvoiceGuarantee' && !empty($invoicePayment) && $invoicePayment->getActive() && !empty($sessionData['doForceInvoicePayment'])) {
                $params['transaction']['payment_type'] = 'INVOICE';
                $this->paymentShortName = 'novalnetinvoice';
                $this->session['sPaymentID'] = $invoicePayment->getId();
                $this->session['sOrderVariables']['sPayment'] = $this->session['sOrderVariables']['sUserData']['additional']['payment'] = Shopware()->Modules()->Admin()->sGetPaymentMeanById($invoicePayment->getId());
                Shopware()->Modules()->Admin()->sUpdatePayment($invoicePayment->getId());
            } else {
                return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('invalidBirthDateError')));
            }
        }

        if (!empty($this->configDetails[$this->paymentShortName.'_due_date'])) {
            $params ['transaction']['due_date'] = $this->service->getDueDate($this->configDetails[$this->paymentShortName.'_due_date']);
        }

        if (in_array($this->paymentShortName, array('novalnetinvoiceGuarantee', 'novalnetsepaGuarantee', 'novalnetinvoiceinstalment', 'novalnetsepainstalment')) && !$this->validator->checkGuarantee($this->paymentShortName)) {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('frontend_guarantee_error')));
        }

        if (!empty($this->configDetails[$this->paymentShortName.'_capture']) && $this->configDetails[$this->paymentShortName.'_capture'] == 'authorize' && $params['transaction']['amount'] >= $manualCheckLimit && $params['transaction']['amount'] > 0) {
            $endpoint = $this->helper->getActionEndpoint('authorize');
        }
        
        $response = $this->requestHandler->curlRequest($params, $endpoint);

        if ($response['result']['status'] == 'SUCCESS') {
            if ($isRedirect) {
                $this->session->offsetSet('novalnet_txn_secret', $response['transaction']['txn_secret']);
                return $this->redirect($response['result']['redirect_url']);
            } else {
                //For handling the novalnet server response and complete the order
                $this->novalnetSaveOrder($response);
            }
        } else {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('orderProcessError'))));
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
            $this->novalnetSaveOrder($transactionDetails);
        } else {
            return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getLanguageFromSnippet('hashCheckFailedError')));
        }
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

        //Store order details in novalnet table
        $this->dataHandler->insertNovalnetTransaction([
            'tid' => $novalnetResponse['tid'],
            'payment_type' => $this->paymentShortName,
            'amount' => number_format($this->getAmount(), 2, '.', '') * 100,
            'paid_amount' => 0,
            'currency' => $this->getCurrencyShortName(),
            'gateway_status' => $transactionDetails['transaction']['status'] ? $transactionDetails['transaction']['status'] : $novalnetResponse['status'],
            'order_no' => ($transactionDetails['transaction']['order_no']) ? $transactionDetails['transaction']['order_no'] : '',
            'customer_id' => $transactionDetails['customer']['customer_no']
        ]);
        $this->helper->unsetSession();
        return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($transactionDetails, $this->helper->getLanguageFromSnippet('orderProcessError'))));
    }
    
    /**
     * Recurring payment action method for adapt the AboCommerce.
     * 
     * @return mixed
     */
    public function recurringAction()
    {
		$this->Front()->Plugins()->ViewRenderer()->setNoRender();
		$order = Shopware()->Modules()->Order()->getOrderById($this->Request()->getParam('orderId'));
		Shopware()->Session()->offsetSet('sPaymentID', $order['paymentID']);
        Shopware()->Session()->offsetSet('sUserId', $order['userID']);
        Shopware()->Session()->offsetSet('sDispatch', $order['dispatchID']);
        $userData    = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();
        $paymentData = Shopware()->Modules()->Admin()->sGetPaymentMeanById($order['paymentID'], $userData);
        $data['merchant']    = $this->service->getMerchantDetails();
        $data['customer']    = $this->service->getCustomerData();
        $data['transaction'] = $this->service->getTransactionDetails($this->router, $this->uniquePaymentID, $this->paymentShortName, true);
        $data['custom']      = $this->service->getCustomDetails();
        // Fetch reference details for recurring process
        if(in_array($this->paymentShortName, array('novalnetcc', 'novalnetsepa', 'novalnetsepaGuarantee', 'novalnetpaypal')))
        {
			$oneClickDetails = $this->dataHandler->getOneClickDetails($this->paymentShortName, $userData);
			if($oneClickDetails != null)
			{
				$oneClickDetails = $this->helper->unserializeData($oneClickDetails[0]);
				$this->service->getTokenDataForAboCommerce($data, $oneClickDetails);
			}
		}
		
		// For lower version compatibility
		$referenceDetails  = Shopware()->Db()->fetchRow('SELECT tid, configuration_details FROM s_novalnet_transaction_detail WHERE order_no = ? AND tid IS NOT NULL
            ORDER BY id DESC', array($order['ordernumber']));
		
		if(in_array($this->paymentShortName, array('novalnetcc', 'novalnetsepa', 'novalnetsepaGuarantee', 'novalnetpaypal')) && empty($data['transaction']['payment_data']))
		{
			$data['transaction']['payment_data']['payment_ref'] = $referenceDetails['tid'];
		} elseif (in_array($this->paymentShortName, array('novalnetsepaGuarantee', 'novalnetinvoiceGuarantee')) && empty($data['customer']['billing']['company']))
		{
			$data['customer']['birth_date'] = $this->helper->unserializeData($referenceDetails['configuration_details'])['birth_date'];
		}
		
		$endpoint = $this->helper->getActionEndpoint('payment');
		$manualCheckLimit = ($this->configDetails[$this->paymentShortName . '_manual_check_limit']) ? $this->configDetails[$this->paymentShortName . '_manual_check_limit'] : 0;
		
		// check if authorize is enabled or not.
		if (!empty($this->configDetails[$this->paymentShortName.'_capture']) && $this->configDetails[$this->paymentShortName.'_capture'] == 'authorize' && $data['transaction']['amount'] >= $manualCheckLimit && $data['transaction']['amount'] > 0) {
            $endpoint = $this->helper->getActionEndpoint('authorize');
        }
        
        $response = $this->requestHandler->curlRequest($data, $endpoint);
        
        //For handling the novalnet server response and complete the order
        return $this->handleResponse($response, $userData);
	}
	
	/**
     * Handle response for Abo commerce orders.
     *
     * @param array $response
     * @param array $userData
     * 
     * @return mixed
     */
    public function handleResponse($response, $userData)
    {
		$insertData = [
            'tid' => $response['transaction']['tid'] ? $response['transaction']['tid'] : '',
            'paid_amount'  => ($response['transaction']['status'] === 'CONFIRMED') ? $response['transaction']['amount'] : 0,
            'refunded_amount'  => 0,
            'gateway_status' => $response['transaction']['status'] ? $response['transaction']['status'] : $response['status'],
            'currency' => $this->getCurrencyShortName(),
            'payment_type' => $this->paymentShortName,
            'amount' => number_format($this->getAmount(), 2, '.', '') * 100,
            'customer_id' => $userData['additional']['user']['customernumber'],
            'order_no' => ''
        ];
        
        if ($response['result']['status'] == 'SUCCESS') {
			$paymentStatusId   = (($response['transaction']['status'] == 'ON_HOLD') ? '18' : (($response['transaction']['status'] == 'PENDING' && !in_array($this->paymentShortName, ['novalnetinvoice', 'novalnetprepayment', 'novalnetmultibanco', 'novalnetcashpayment'])) ? '17' : $this->configDetails[$this->paymentShortName . '_after_paymenstatus']));
			$novalnetTransNote = $this->helper->formCustomerComments($response, $this->paymentShortName, $this->getCurrencyShortName());
			
			$this->session->offsetSet('serverResponse', $response);
			$this->session->sComment = $novalnetTransNote;
			
			// Create the order for novalnet direct payments
			$orderNumber = $this->saveOrder($response['transaction']['tid'], $response['transaction']['tid'], $paymentStatusId);
			
			//Validate the backend configuration and send the order number to the server
            if ($response['transaction']['tid'] && $orderNumber && !in_array($this->paymentShortName, ['novalnetinvoiceGuarantee', 'novalnetinvoice', 'novalnetprepayment'])) {
                //update order number for transaction
                $this->requestHandler->postCallBackProcess($orderNumber, $response['transaction']['tid']);
            }
        
			$insertData['order_no'] = $orderNumber;
			if (! empty($response['transaction']['bank_details'])) {
                $insertData['configuration_details'] = $this->helper->serializeData($response['transaction']['bank_details']);
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
                echo Zend_Json::encode($data);
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
                echo Zend_Json::encode($data);
            } else {
				return $this->redirect($this->errorUrl . '?sNNError=' . urlencode($errorMessage));
            }
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
        $paymentStatusId   = (($result['transaction']['status'] == 'ON_HOLD') ? '18' : (($result['transaction']['status'] == 'PENDING' && !in_array($this->paymentShortName, ['novalnetinvoice', 'novalnetprepayment', 'novalnetmultibanco', 'novalnetcashpayment'])) ? '17' : $this->configDetails[$this->paymentShortName . '_after_paymenstatus']));
        $novalnetTransNote = $this->helper->formCustomerComments($result, $this->paymentShortName, $this->orderDetails['sBasketProportional']['sCurrencyName']);

        $this->session->sComment = $this->session->sComment . $novalnetTransNote;

        $this->session->offsetSet('serverResponse', $result);
        if ($this->paymentShortName == 'novalnetcashpayment' && !empty($result['transaction']['checkout_token'])) {
            $this->session->nncheckoutJs    = $result['transaction']['checkout_js'];
            $this->session->nncheckoutToken = $result['transaction']['checkout_token'];
        }

        // Create the order for novalnet direct payments
        $orderNumber = $this->saveOrder($result['transaction']['tid'], $result['transaction']['tid'], $paymentStatusId);

        if (!empty($orderNumber)) {
            //Validate the backend configuration and send the order number to the server
            if ($result['transaction']['tid'] && $orderNumber && $postback && !in_array($this->paymentShortName, ['novalnetinvoiceinstalment','novalnetinvoiceGuarantee', 'novalnetinvoice', 'novalnetprepayment'])) {
                //update order number for transaction
                $this->requestHandler->postCallBackProcess($orderNumber, $result['transaction']['tid']);
            }

            $insertData = [
                'payment_type' => $this->paymentShortName,
                'paid_amount'  => ($result['transaction']['status'] === 'CONFIRMED') ? $result['transaction']['amount'] : 0,
                'refunded_amount'  => 0
            ];

            foreach ([
                'tid'           => 'tid',
                'gateway_status' => 'status',
                'amount'        => 'amount',
                'order_no'       => 'order_no',
                'customer_id'    => 'customer_no',
                'currency'      => 'currency',
            ] as $key => $value) {
                if (! empty($result['transaction'][$value])) {
                    $insertData[$key] = $result['transaction'][$value];
                }
            }

            $insertData['customer_id'] = !empty($result['customer']['customer_no']) ? $result['customer']['customer_no'] : '';
            $insertData['order_no']    = $orderNumber;

            if (! empty($result['transaction']['bank_details'])) {
                $insertData['configuration_details'] = $result['transaction']['bank_details'];
            }

            if (!empty($result['transaction']['payment_data']['token'])) {
                $insertData['configuration_details']['token'] = $result['transaction']['payment_data']['token'];
                if (in_array($this->paymentShortName, ['novalnetsepa','novalnetsepaGuarantee', 'novalnetsepainstalment'])) {
                    $insertData['configuration_details'] = array_merge($insertData['configuration_details'], [
                        'account_holder' => $result['transaction']['payment_data']['account_holder'],
                        'iban' => $result['transaction']['payment_data']['iban']
                    ]);
                } elseif ($this->paymentShortName == 'novalnetcc') {
                    $insertData['configuration_details'] = array_merge($insertData['configuration_details'], [
                        'cardBrand' => $result['transaction']['payment_data']['card_brand'],
                        'expiryDate' => sprintf("%02d", $result['transaction']['payment_data']['card_expiry_month']) .'/'. $result['transaction']['payment_data']['card_expiry_year'],
                        'cardHolder' => $result['transaction']['payment_data']['card_holder'],
                        'accountData' => $result['transaction']['payment_data']['card_number']
                    ]);
                } elseif ($this->paymentShortName == 'novalnetpaypal') {
                    $insertData['configuration_details'] = array_merge($insertData['configuration_details'], [
                        'paypal_transaction_id' => $result['transaction']['payment_data']['paypal_transaction_id'],
                        'paypal_account' => $result['transaction']['payment_data']['paypal_account']
                    ]);
                }
            }

            if (! empty($result['instalment']['cycles_executed'])) {
                $insertData['configuration_details'] = $insertData['configuration_details'] ? array_merge($insertData['configuration_details'], $this->helper->getInstalmentInformation($result)) : $this->helper->getInstalmentInformation($result);
            }
            
            if (!empty($result['customer']['birth_date']) && in_array($this->paymentShortName, ['novalnetinvoiceGuarantee', 'novalnetsepaGuarantee'])) {
                $insertData['configuration_details']['birth_date'] = $result['customer']['birth_date'];
            }

            if (! empty($insertData['configuration_details'])) {
                $insertData['configuration_details'] = $this->helper->serializeData($insertData['configuration_details']);
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
     * Delete the card details
     *
     *
     * @return array
     */
    public function deleteCardAction()
    {
        $customerNumber =  Shopware()->Front()->Request()->get('customer_no');
        $token          =  Shopware()->Front()->Request()->get('token');
        $this->dataHandler->deleteCardToken(['token' => $token, 'customer_no' => $customerNumber]);
        return $this->redirect($this->errorUrl);
    }

    /**
     * Called when the novalnet callback-script execution
     */
    public function statusAction()
    {
        $callbackObj = new PaymentNotification($this->Request()->getRawBody(), $this->View());
    }

    /**
     * Get the available shipping and cost for the selected shipping
     *
     * @return void
     */
    public function getAvailableShippingAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $postData = $this->Request()->getPost();
        $shipping = $this->helper->getShippingMethods($postData);
        $article  = $this->helper->getCartItems();

        $this->jsonResponse([
            'success' => true,
            'shipping' => $shipping,
            'cartItems' => $article['displayItems'],
            'totalAmount' => $article['totalAmount'],
        ]);

        return;
    }

    /**
     * Create order and reinitialize the construct data for apple pay
     *
     * @return void
     */
    public function createApplePayOrderAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $postData = $this->Request()->getPost();
        $response = $this->helper->unserializeData($postData['serverResponse']);
        $userData = $this->helper->getUserInfo();

        $requiredFields = array('givenName', 'familyName', 'addressLines', 'postalCode', 'locality', 'countryCode');
        foreach ($requiredFields as $key) {
            if (empty($response['response']['wallet']['billing'][$key])) {
                $this->jsonResponse([
                    'success' => false,
                    'error'   => $this->helper->getLanguageFromSnippet('billingAddressError')
                ]);
                return;
            } elseif (empty($response['response']['wallet']['shipping'][$key])) {
                $this->jsonResponse([
                    'success' => false,
                    'error'   => $this->helper->getLanguageFromSnippet('shippingAddressError')
                ]);
                return;
            }
        }

        if (empty($userData['billingaddress'])) {
            $customer = $this->helper->createNewCustomer($response['response']);
            $this->session->offsetSet('sUserId', $customer->getId());
            $userData = $this->helper->getUserInfo();
        } elseif (!empty($response['response']['wallet']['billing']) && !empty($response['response']['wallet']['shipping'])) {
            $userData = $this->helper->updateCustomerData($userData, $response['response']);
        }

        $payment = Shopware()->Modules()->Admin()->sGetPaymentMeanById($this->session['sPaymentID'], $this->View()->$userData);
        $paymentID  = Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans WHERE name = ?', array('novalnetapplepay'));
        Shopware()->Session()->offsetSet('sPaymentID', $paymentID);

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

        $this->session->offsetSet('novalnet', new ArrayObject(['walletToken' => $response['response']['transaction']['token']], ArrayObject::ARRAY_AS_PROPS));
        $this->session->offsetSet('isExpressCheckout', true);

        $this->paymentShortName = $this->getPaymentShortName();
        // Process the payment call
        $params = $this->service->getRequestParams($this->uniquePaymentID, $this->router, $this->paymentShortName);
        $params['transaction']['amount'] = number_format($this->getAmount(), 2, '.', '') * 100;

        $endpoint = $this->helper->getActionEndpoint('payment');

        $manualCheckLimit = ($this->configDetails[$this->paymentShortName . '_manual_check_limit']) ? $this->configDetails[$this->paymentShortName . '_manual_check_limit'] : 0;

        if (!empty($this->configDetails[$this->paymentShortName.'_capture']) && $this->configDetails[$this->paymentShortName.'_capture'] == 'authorize' && $params['transaction']['amount'] >= $manualCheckLimit && $params['transaction']['amount'] > 0) {
            $endpoint = $this->helper->getActionEndpoint('authorize');
        }

        $response = $this->requestHandler->curlRequest($params, $endpoint);

        if ($response['result']['status'] == 'SUCCESS') {
            //For handling the novalnet server response and complete the order
            $url = $this->novalnetSaveOrder($response, true, true);
            $this->jsonResponse([
                'success' => true,
                'url' => $url
            ]);
            return;
        } else {
            $this->jsonResponse([
                'success' => true,
                'url' => $this->errorUrl . '?sNNError=' . urlencode($this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('orderProcessError')))
            ]);
            return;
        }
    }

    /**
     * Add article to cart and proceed the apple pay sheet
     *
     * @return void
     */
    public function addArticleAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $postData    = $this->Request()->getPost();
        $ordernumber = trim($postData['ordernumber']);
        $productId   = Shopware()->Modules()->Articles()->sGetArticleIdByOrderNumber($ordernumber);

        if (!empty($productId)) {
            Shopware()->Db()->query('DELETE FROM s_order_basket WHERE ordernumber = ? and articleID = ?', [$ordernumber, $productId]);
            Shopware()->Modules()->Basket()->sAddArticle($ordernumber, (int) $postData['quantity']);
            $this->View()->assign('sArticleName', Shopware()->Modules()->Articles()->sGetArticleNameByOrderNumber($ordernumber));
            $this->jsonResponse([
                'success' => true
            ]);
            return;
        }
    }

    /**
     * Validate for users over 18 only
     *
     * @param string $birthdate
     *
     * @return boolean
     */
    private function validateAge($birthdate)
    {
        $birthday = strtotime($birthdate);
        //The age to be over, over +18
        $min      = strtotime('+18 years', $birthday);
        return (empty($birthdate) || time() < $min) ? true : false;
    }
}
