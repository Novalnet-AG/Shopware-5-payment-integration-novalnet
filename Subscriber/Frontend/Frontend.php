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

        if (!empty(Shopware()->Session()->offsetGet('novalnetPay')) && in_array($request->getActionName(), ['confirm','finish'])) {
            $sessionValue = is_array(Shopware()->Session()->offsetGet('novalnetPay')) ? Shopware()->Session()->offsetGet('novalnetPay') : Shopware()->Session()->offsetGet('novalnetPay')->getArrayCopy();
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
        $orderData     = Shopware()->Modules()->Order()->getOrderById($orderId);
        $orderNumber   = (! empty($orderData['ordernumber'])) ? $orderData['ordernumber'] : $orderData['order_number'];

        if ($request->getControllerName() == 'AboCommerce' && $request->getActionName() =='updateAboPayment' && $AboOrderData['selectedPaymentId'] == $AboOrderData['novalnetpayId']) {
            if ($formResponseData['result']['status'] == 'SUCCESS' && $formResponseData['result']['statusCode'] == 100) {
                    $changePaymentData = [
                        'abo_id' => $AboOrderData['subscriptionId'],
                        'customer_id' => $orderData['customerID'],
                        'order_no' => $orderNumber,
                        'payment_data' => $AboOrderData['novalnet_payment_data'],
                        'datum' => date("Y-m-d h:i:s"),
                    ];
                    $this->dataHandler->insertNNSubscriptionPaymentData($changePaymentData);
            }
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
            
            if (in_array(Shopware()->Config()->get('Version'), ['5.2.0','5.3.0','5.2.27','5.4.0','5.7.18'])) {
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
                if ($request->getActionName() == 'confirm' && $currentPayment == NOVALNET_PAYMENT_NAME && empty(Shopware()->Session()->offsetGet('novalnetPay'))) {
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
        
        if (preg_match("/novalnet/i", $document->_order->payment['name']) !== false) {
            $view      = $document->_view;
            $orderData = $view->getTemplateVars('Order');
            $orderData['_order']['customercomment'] = '<br />' . nl2br($orderData['_order']['customercomment']);
            $view->assign('Order', $orderData);
            
            if (!empty($orderData['_order']['transactionID'])) {
                $novalnetPayment = $this->helper->getPaymentType($this->dataHandler->checkTransactionExists(['tid' => $orderData['_order']['transactionID']])->getPaymentType());

                if (in_array($novalnetPayment, ['INVOICE', 'PREPAYMENT', 'GUARANTEED_INVOICE'])) {
                    //update order number for transaction
                    $result = $this->requestHandler->updateInvoiceNumber(['tid' => $orderData['_order']['transactionID'], 'invoice_no' => $document->_documentID]);
                }
            }
        }
    }
}
