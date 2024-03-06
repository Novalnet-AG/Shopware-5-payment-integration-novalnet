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

namespace Shopware\Plugins\NovalPayment\Subscriber\Frontend;

use ArrayObject;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentRequest;
use Enlight\Event\SubscriberInterface;

class Frontend implements SubscriberInterface
{
    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var ManageRequest
     */
    private $requestHandler;

    /**
     * @var DataHandler
     */
    private $dataHandler;

    public function __construct(
        NovalnetHelper $helper,
        ManageRequest $requestHandler,
        DataHandler $dataHandler
    ) {
        $this->helper    = $helper;
        $this->dataHandler = $dataHandler;
        $this->requestHandler = $requestHandler;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onPostDispatchSecureFrontend',
            'Shopware_Modules_Order_SaveOrder_FilterParams' =>'onOrderAddNovalnetCommet',
            'Shopware_Controllers_Frontend_AboCommerce::ajaxSelectionPaymentAction::after' =>'onajaxSelectionPayment',
            'Shopware_Controllers_Frontend_AboCommerce::updateAboPaymentAction::after' =>'onUpdateAbocommercePayment',
            'Shopware_Components_Document::assignValues::after' =>'onBeforeRenderDocument',

        ];
    }

     /**
     * Subscription payment validation
     *
     * @param \Enlight_Hook_HookArgs $args
     */

    public function onajaxSelectionPayment(\Enlight_Hook_HookArgs $args)
    {
        $controller = $args->getSubject();
        $request  = $controller->Request();
        $view     = $controller->View();
        $paymentMeans = Shopware()->Container()->get('modules')->getModule('Admin')->sGetPaymentMeans();

        if ($request->getControllerName() === 'AboCommerce' && $request->getActionName() === 'ajaxSelectionPayment') {
            $formPaymentData = $this->helper->getPaymentFormUrl($paymentMeans, true);

            if ($formPaymentData) {
                $view->nnPaymentFromUrl = $formPaymentData['nnPaymentFromUrl'];
                $view->walletPaymentParams =  $formPaymentData['walletPaymentParams'];
            }
            $view->extendsTemplate('frontend/plugins/abo_commerce/ajax_selection_payment.tpl');
        }
    }

    /*
     * Post dispatch event frontend
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchFrontend(\Enlight_Event_EventArgs $args)
    {
        $request       = $args->getSubject()->Request();
        $view          = $args->getSubject()->View();

        if (in_array($request->getControllerName(), ['account','checkout']) && in_array($request->getActionName(), ['shippingPayment', 'payment','confirm'])) {
            $enabledPaymentMethods = !empty($view->sPayments) ? $view->sPayments : $view->sPaymentMeans;
            $formPaymentData = $this->helper->getPaymentFormUrl($enabledPaymentMethods);

            if ($formPaymentData) {
                $view->nnPaymentFromUrl = $formPaymentData['nnPaymentFromUrl'];
                $view->walletPaymentParams  =  $formPaymentData['walletPaymentParams'];
            }
        } elseif ($request->getControllerName() === 'checkout' && $request->getActionName() === 'finish' && (Shopware()->Session()->sOrderVariables->sPayment['name'] == NOVALNET_PAYMENT_NAME )) {
            $order = $this->dataHandler->getOrder($view->sOrderNumber);
            $view->sComment = $order['customerComment'];

            if (!empty(Shopware()->Session()->nncheckoutToken)) {
                $view->nncheckoutJs = Shopware()->Session()->nncheckoutJs;
                $view->nncheckoutToken = Shopware()->Session()->nncheckoutToken;
                $view->timeStamp = time();
                Shopware()->Session()->offsetSet('nncheckoutJs', '');
                Shopware()->Session()->offsetSet('nncheckoutToken', '');
            }

            if (!empty(Shopware()->Session()->nnOrderPaymentName)) {
                $view->nnOrderPaymentName = Shopware()->Session()->nnOrderPaymentName;
                Shopware()->Session()->offsetSet('nnOrderPaymentName', '');
            }
        }

        $sessionValue = Shopware()->Session()->offsetGet('novalnetPay');
        if (!empty($sessionValue) && in_array($request->getActionName(), ['confirm','finish'])) {
            $sessionValue = is_array(Shopware()->Session()->offsetGet('novalnetPay')) ?
                Shopware()->Session()->offsetGet('novalnetPay') : Shopware()->Session()->offsetGet('novalnetPay')->getArrayCopy();
            $view->maskedDetails =  $sessionValue;
        }
    }

     /**
     * Subscription change payment method
     *
     * @param \Enlight_Hook_HookArgs $args
     */

    public function onUpdateAbocommercePayment(\Enlight_Hook_HookArgs $args)
    {
        $request           = $args->getSubject()->Request();
        $AboOrderData      = $request->getParams();
        $formResponseData  = $this->helper->unserializeData($AboOrderData['novalnet_payment_data']);
        $GetOrderId        = "SELECT last_order_id FROM `s_plugin_swag_abo_commerce_orders` WHERE id = ?";
        $orderId           = Shopware()->Db()->fetchOne($GetOrderId, [$AboOrderData['subscriptionId']]);
        $orderData         = Shopware()->Modules()->Order()->getOrderById($orderId);
        $orderNumber       = (! empty($orderData['ordernumber'])) ? $orderData['ordernumber'] : $orderData['order_number'];
        $endpoint          = $this->helper->getActionEndpoint('payment');
        $insertPaymentData = $AboOrderData['novalnet_payment_data'];
        $view              = $args->getSubject()->View();

        if ($request->getControllerName() == 'AboCommerce' && $request->getActionName() =='updateAboPayment' &&
           ($AboOrderData['selectedPaymentId'] == $AboOrderData['novalnetpayId'] || $AboOrderData['payment'] == $AboOrderData['novalnetpayId'])
        ) {
            if (!empty($formResponseData['payment_details']['type']) && in_array($formResponseData['payment_details']['type'], ['CREDITCARD', 'DIRECT_DEBIT_SEPA', 'PAYPAL'])) {
                if (!empty($formResponseData) && (!empty($formResponseData['booking_details']['payment_ref']['token']) || !empty($formResponseData['booking_details']['payment_ref_token']) )) {
                    $insertPaymentData = $AboOrderData['novalnet_payment_data'];
                } else {
                    $formResponseData['orderNumber'] = $orderNumber;
                    $formResponseData['subscriptionId'] = $AboOrderData['subscriptionId'];
                    $formResponseData['customerID'] = $orderData['customerID'];
                    Shopware()->Session()->offsetSet('novalnetPay', new ArrayObject($formResponseData, ArrayObject::ARRAY_AS_PROPS));
                    $service = new PaymentRequest($this->helper);
                    $paymentReqParams = $service->getRequestParams();
                    $paymentReqParams['transaction']['amount'] = 0;
                    $paymentReqParams['transaction']['order_no'] = $orderNumber;

                    if (empty($paymentReqParams['transaction']['create_token'])) {
                        $paymentReqParams['transaction']['create_token'] = '1';
                    }

                    if (!empty($paymentReqParams['transaction']['return_url']) && $paymentReqParams['transaction']['error_return_url']) {
                        $paymentReqParams['transaction']['return_url']   = Shopware()->Front()->Router()->assemble(['controller' => 'NovalPayment', 'action' => 'changeAboPayment','forceSecure' => true]);
                        $paymentReqParams['transaction']['error_return_url']   = Shopware()->Front()->Router()->assemble(['controller' => 'NovalPayment','action' => 'changeAboPayment','forceSecure' => true]);
                    }

                    if ($paymentReqParams['transaction']['payment_type'] == 'PAYPAL' && isset($paymentReqParams['cart_info'])) {
                        unset($paymentReqParams['cart_info']);
                    }

                    $response = $this->requestHandler->curlRequest($paymentReqParams, $endpoint);
                    if ($response['result']['status'] == 'SUCCESS') {
                        if (!empty($response['result']['redirect_url'])) {
                            Shopware()->Session()->offsetSet('novalnet_txn_secret', $response['transaction']['txn_secret']);
                            return $args->getSubject()->redirect($response['result']['redirect_url']);
                        }
                        $response['transaction']['payment_name'] = $formResponseData['payment_details']['name'];
                        $insertPaymentData = $this->helper->serializeData($response['transaction']);
                    } else {
                        $args->getSubject()->redirect([
                           'action' => 'orders',
                           'sAboChangeSuccess' => false,
                        ]);
                        return;
                    }
                }
            }

            if ($formResponseData['result']['status'] == 'SUCCESS' && $formResponseData['result']['statusCode'] == 100) {
                    $changePaymentData = [
                        'abo_id' => $AboOrderData['subscriptionId'],
                        'customer_id' => $orderData['customerID'],
                        'order_no' => $orderNumber,
                        'payment_data' => $insertPaymentData,
                        'datum' => date("Y-m-d h:i:s"),
                    ];
                    $this->dataHandler->insertNNSubscriptionPaymentData($changePaymentData);
            } else {
                $view->novalAboError = $response['result']['status_text'];
                if ($AboOrderData['payment'] == $AboOrderData['novalnetpayId']) {
                    $args->getSubject()->redirect([
                            'action' => 'orders',
                            'sAboChangeSuccess' => false,
                        ]);
                    return;
                }
            }
            $view->extendsTemplate('frontend/plugins/abo_commerce/orders/content.tpl');
        }
    }

     /*
     * load custom tpl forms for card payments
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchSecureFrontend(\Enlight_Event_EventArgs $args)
    {
        $request       = $args->getSubject()->Request();
        $responseData  = $this->helper->unserializeData($request->getPost()['novalnet_payment_data']);
        $action        = $args->getSubject();

        if (in_array($request->getControllerName(), ['checkout', 'account']) && in_array($request->getActionName(), ['confirm', 'saveShippingPayment', 'shippingPayment','savePayment' ])) {
            if ($responseData['result']['status'] == 'SUCCESS' || $responseData['result']['statusCode'] == '100') {
                Shopware()->Session()->offsetSet('novalnetPay', new ArrayObject($responseData, ArrayObject::ARRAY_AS_PROPS));
            } elseif ($responseData['result']['status'] == 'ERROR') {
                $error_url = Shopware()->Front()->Router()->assemble(['controller' =>'checkout', 'action' =>'shippingPayment','sTarget' =>'checkout']);
                $this->helper->unsetSession();
                $action->redirect($error_url . '?sNNError=' . urlencode($responseData['result']['message']));
            }
        }
    }

    /**
     * get mail comments and send the order mail
     *
     * @param \Enlight_Event_EventArgs $arguments
     */
    public function onOrderAddNovalnetCommet(\Enlight_Event_EventArgs $arguments)
    {
        $orderParams      = $arguments->getReturn();
        $novalnetResponse = Shopware()->Session()->offsetGet('serverResponse');
        $payment          = ! empty($orderParams['paymentID']) ? $this->dataHandler->getPaymentMethod(['id' => $orderParams['paymentID']]) : '';
        $paymentName      = $payment->getName();
        if (( $paymentName == NOVALNET_PAYMENT_NAME ) && !empty($novalnetResponse) && ( $novalnetResponse['result']['status'] == 'SUCCESS' ) && (preg_match("/INVOICE/i", $novalnetResponse['transaction']['payment_type']) || preg_match("/PREPAYMENT/i", $novalnetResponse['transaction']['payment_type']) )) {
            //update order number for transaction
            $result = $this->requestHandler->postCallBackProcess($orderParams['ordernumber'], $novalnetResponse['transaction']['tid']);
            $novalnetResponse['transaction']['invoice_ref'] = $result['transaction']['invoice_ref'];
            $novalnetResponse['transaction']['order_no'] = $orderParams['ordernumber'];
            $note = $this->helper->formCustomerComments($novalnetResponse, $orderParams['currency']);

            if (in_array(Shopware()->Config()->get('Version'), ['5.2.0','5.3.0','5.2.27','5.4.0'])) {
                $note = str_replace('<br />', PHP_EOL, $note);
                Shopware()->Modules()->Order()->sComment = Shopware()->Session()->sComment = $note;
                $orderParams['customercomment'] = $note;
            } else {
                Shopware()->Modules()->Order()->sComment = Shopware()->Session()->sComment = $note;
                $orderParams['customercomment'] = str_replace('<br />', PHP_EOL, $note);
            }
        }
        $arguments->setReturn($orderParams);
    }

     /*
     * Pre dispatch event
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPreDispatch(\Enlight_Event_EventArgs $args)
    {
        $request  = $args->getSubject()->Request();
        if ($request->getModuleName() == 'frontend') {
            $view     = $args->getSubject()->View();
            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            $currentPayment = !empty($userData['additional']['payment']['name']) ? $userData['additional']['payment']['name'] : (!empty(Shopware()->Session()->sOrderVariables) ? Shopware()->Session()->sOrderVariables->sPayment['name'] : '');

            if ($request->getControllerName() == 'checkout' || $request->getControllerName() == 'account') {
                $sessionData = Shopware()->Session()->offsetGet('novalnetPay');
                if ($request->getActionName() == 'confirm' && $currentPayment == NOVALNET_PAYMENT_NAME && empty($sessionData)) {
                    $args->getSubject()->redirect(Shopware()->Front()->Router()->assemble(array('controller' => 'checkout','action' => 'shippingPayment','sTarget' => 'checkout')));
                }
            }
        }
    }

    /*
     * Update invoice number to server
     *
     * @param \Enlight_Hook_HookArgs $args
     *
     */
    public function onBeforeRenderDocument(\Enlight_Hook_HookArgs $args)
    {
        $document = $args->getSubject();
        
        if (preg_match("/novalnet/i", $document->_order->payment['name']) != false) {
            $view                  = $document->_view;
            $orderData             = $view->getTemplateVars('Order');
            
            if (!empty($orderData)) {
                $orderData['_order']['customercomment'] = '<br />' . nl2br($orderData['_order']['customercomment']);
                $serverResponse        = Shopware()->Session()->offsetGet('serverResponse');
                $getTransaction        = $this->dataHandler->checkTransactionExists(['tid' => $orderData['_order']['transactionID']]);
                $novalnetPayment       = null;
                $novalnetPaymentDesc   = $orderData['_payment']['description'];
                $novalnetOrderAttrName = $document->_order->order->attributes['novalnet_payment_name'];
                
                if (!empty($getTransaction)) {
                    $getPaymentType  = $getTransaction->getPaymentType();
                    $novalnetPayment = !empty($getPaymentType) ? $this->helper->getPaymentType($getPaymentType) : null;
                    $configData      = $getTransaction->getConfigurationDetails();
                    $novalnetPaymentDesc = !empty($configData) ? $this->helper->unserializeData($configData)['payment_name'] : $novalnetOrderAttrName;
                } elseif (!empty($serverResponse)) {
                    $novalnetPayment = $serverResponse['transaction']['payment_type'];
                    $novalnetPaymentDesc = !empty($serverResponse['transaction']['payment_name']) ? $serverResponse['transaction']['payment_name'] : $novalnetOrderAttrName;
                }
                
                $orderData['_payment']['description'] = !empty($novalnetPaymentDesc) ? $novalnetPaymentDesc : $orderData['_payment']['description'];
                $view->assign('Order', $orderData);
                            
                if (!empty($novalnetPayment) && in_array($novalnetPayment, ['INVOICE', 'PREPAYMENT', 'GUARANTEED_INVOICE']) && !empty($orderData['_order']['transactionID'])) {
                    //update order number for transaction
                    $result = $this->requestHandler->updateInvoiceNumber(['tid' => $orderData['_order']['transactionID'], 'invoice_no' => $document->_documentID]);
                }
            }
        }
    }
}
