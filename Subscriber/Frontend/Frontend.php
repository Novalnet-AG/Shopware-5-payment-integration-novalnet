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
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetValidator;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentRequest;
use Enlight\Event\SubscriberInterface;

class Frontend implements SubscriberInterface
{
    /**
     * @var NovalnetValidator
     */
    private $validator;

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
        NovalnetValidator $validator,
        ManageRequest $requestHandler,
        DataHandler $dataHandler
    ) {
        $this->validator = $validator;
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
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Detail' => 'onPostDispatchDetail',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onPostDispatchSecureFrontend',
            'Shopware_Modules_Order_SaveOrder_FilterParams' =>'onOrderAddNovalnetCommet',
            'Shopware_Components_Document::assignValues::after' =>'onBeforeRenderDocument',
        ];
    }

    /**
     * get mail comments and send the order mail
     *
     * @param \Enlight_Event_EventArgs $arguments
     */
    public function onOrderAddNovalnetCommet(\Enlight_Event_EventArgs $arguments)
    {
        $orderParams      = $arguments->getReturn();
        $payment          = ($orderParams['paymentID']) ? $this->dataHandler->getPaymentMethod(['id' => $orderParams['paymentID']]) : '';
        $novalnetResponse = Shopware()->Session()->offsetGet('serverResponse');

        if (!empty($payment) && in_array($payment->getName(), ['novalnetinvoiceinstalment', 'novalnetinvoiceGuarantee', 'novalnetinvoice', 'novalnetprepayment'])) {
            $paymentName = $payment->getName();
            if ($novalnetResponse['transaction']['payment_type'] == 'INVOICE') {
                $paymentName = 'novalnetinvoice';
            }
            //update order number for transaction
            $result = $this->requestHandler->postCallBackProcess($orderParams['ordernumber'], $novalnetResponse['transaction']['tid']);
            $novalnetResponse['transaction']['invoice_ref'] = $result['transaction']['invoice_ref'];
            $novalnetResponse['transaction']['order_no'] = $orderParams['ordernumber'];
            $note = $this->helper->formCustomerComments($novalnetResponse, $paymentName, $orderParams['currency']);
            Shopware()->Modules()->Order()->sComment = Shopware()->Session()->sComment = $note;
            $orderParams['customercomment'] = str_replace('<br />', PHP_EOL, $note);
        }
        $arguments->setReturn($orderParams);
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
        
        if (strpos($document->_order->payment['name'], 'novalnet') !== false) {
            $view      = $document->_view;
            $orderData = $view->getTemplateVars('Order');
            $orderData['_order']['customercomment'] = nl2br($orderData['_order']['customercomment']);
            $view->assign('Order', $orderData);
            if (in_array($document->_order->payment['name'], array('novalnetinvoice', 'novalnetprepayment', 'novalnetinvoiceGuarantee')) && !empty($orderData['_order']['transactionID'])) {
                //update order number for transaction
                $result = $this->requestHandler->updateInvoiceNumber(['tid' => $orderData['_order']['transactionID'], 'invoice_no' => $document->_documentID]);
            }
        }
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
            $config   = $this->helper->getConfigurations();
            $services = new PaymentRequest($this->helper, Shopware()->Session());
            $currentPayment = !empty($userData['additional']['payment']['name']) ? $userData['additional']['payment']['name'] : (!empty(Shopware()->Session()->sOrderVariables) ? Shopware()->Session()->sOrderVariables->sPayment['name'] : '');
            $requiredFields = false;
            $view->isSepaInstalmentActive = $view->isInvoiceInstalmentActive = false;

            foreach (Shopware()->Modules()->Admin()->sGetPaymentMeans() as $paymentMeans) {
                if ($paymentMeans['name'] == 'novalnetinvoiceinstalment') {
                    $view->isInvoiceInstalmentActive = true;
                } elseif ($paymentMeans['name'] == 'novalnetsepainstalment') {
                    $view->isSepaInstalmentActive = true;
                }
            }
            if ($request->getActionName() != 'confirm') {
                $requiredFields = true;
            }
            $view->sepaInstalmentCycle    = $this->helper->getInstalmentCycles('novalnetsepainstalment');
            $view->invoiceInstalmentCycle = $this->helper->getInstalmentCycles('novalnetinvoiceinstalment');
            $view->nnConfig = $config;

            if (!empty(Shopware()->Modules()->Basket()->sGetBasket())) {
                $view->nnWalletPayments = $services->getActiveWalletPayments(Shopware()->Modules()->Basket()->sGetBasket()['content']);
                $view->applePayParams   = $services->getApplePayParams($requiredFields);
                $view->googlePayParams  = $services->getGooglePayParams($requiredFields);
            }
            if ($request->getControllerName() == 'checkout' || $request->getControllerName() == 'account') {
                $view->ccParams = $services->getCcIframeParams();

                if ($request->getActionName() == 'confirm' && in_array($currentPayment, $this->helper->getFormTypePayments()) && empty(Shopware()->Session()->offsetGet('novalnet'))) {
                    $args->getSubject()->redirect(Shopware()->Front()->Router()->assemble(array('controller' => 'checkout','action' => 'shippingPayment','sTarget' => 'checkout')));
                }

                //Fetch Oneclick record from table
                if (in_array($currentPayment, array('novalnetcc', 'novalnetsepa', 'novalnetsepaGuarantee', 'novalnetsepainstalment'))) {
                    if ($config[$currentPayment.'_shopping_type'] =='1') {
                        $oneClickDetails = $this->dataHandler->getOneClickDetails($currentPayment, $userData);
                        $cardData = [];
                        if (!empty($oneClickDetails)) {
                            foreach ($oneClickDetails as $detail) {
                                $cardData[] = $this->helper->unserializeData($detail);
                            }
                        }
                        $view->cardBundle = $cardData;
                    }
                }
            }
        }
    }

    /*
     * Post dispatch event frontend
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchDetail(\Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();
        $services = new PaymentRequest($this->helper, Shopware()->Session());
        $view->nnWalletPayments = $services->getActiveWalletPayments($view->getAssign('sArticle'));
        $view->applePayParams   = $services->getApplePayParams(true, $view->getAssign('sArticle'));
        $view->googlePayParams  = $services->getGooglePayParams(true, $view->getAssign('sArticle'));
    }

    /*
     * Post dispatch event frontend
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchFrontend(\Enlight_Event_EventArgs $args)
    {
        $request    = $args->getSubject()->Request();
        $view       = $args->getSubject()->View();
        $services   = new PaymentRequest($this->helper, Shopware()->Session());

        if (in_array($request->getControllerName(), ['account','checkout']) && in_array($request->getActionName(), ['confirm','shippingPayment', 'savePayment', 'saveShippingPayment'])) {
            if ($request->getControllerName() == 'account') {
                $view->sAmount = Shopware()->Modules()->Basket()->sGetAmount()['totalAmount'];
            }
            $enabledPaymentMethods = !empty($view->sPayments) ? $view->sPayments : $view->sPaymentMeans;
            if (!empty($enabledPaymentMethods)) {
                $view->sPayments = $view->sPaymentMeans = $this->validator->displayValidPayments($enabledPaymentMethods);
            }

            $canShowDobField = [];
            foreach (['novalnetinvoiceGuarantee' , 'novalnetsepaGuarantee','novalnetsepainstalment','novalnetinvoiceinstalment'] as $paymentName) {
                $canShowDobField[$paymentName] = $this->validator->canShowDobField($paymentName);
            }
            $view->canShowDobField = $canShowDobField;

            $view->applePayParams   = $services->getApplePayParams(false);
            $view->googlePayParams  = $services->getGooglePayParams(false);
        } elseif ($request->getControllerName() === 'checkout' && $request->getActionName() === 'finish' && array_key_exists(Shopware()->Session()->sOrderVariables->sPayment['name'], $this->helper->getPaymentInfo())) {
            $order = $this->dataHandler->getOrder($view->sOrderNumber);
            $view->sComment = $order['customerComment'];
            if (!empty(Shopware()->Session()->nncheckoutToken)) {
                $view->nncheckoutJs = Shopware()->Session()->nncheckoutJs;
                $view->nncheckoutToken = Shopware()->Session()->nncheckoutToken;
                Shopware()->Session()->offsetSet('nncheckoutJs', '');
                Shopware()->Session()->offsetSet('nncheckoutToken', '');
            }
        } elseif ($request->getControllerName() == 'account' && $request->getActionName() == 'orders') {
            $sepaInstalment = $this->dataHandler->getPaymentMethod(['name' => 'novalnetsepainstalment']);
            $invoiceInstalment = $this->dataHandler->getPaymentMethod(['name' => 'novalnetinvoiceinstalment']);

            $instalmentArray = [];
            foreach ($view->sOpenOrders as $order) {
                if ($sepaInstalment->getId() == $order['paymentID'] || $invoiceInstalment->getId() == $order['paymentID']) {
                    $data = $this->dataHandler->checkTransactionExists(['tid' => $order['transactionID']]);
                    if (!empty($data) && !empty($data->getConfigurationDetails()) && $data->getGatewayStatus() == 'CONFIRMED') {
                        $config = $this->helper->unserializeData($data->getConfigurationDetails());
                        $instalmentArray[] = array($order['ordernumber'] => $config['InstalmentDetails']);
                    }
                }
            }
            $view->nnInstalmentInfo = $instalmentArray;
        }

        if (!empty(Shopware()->Session()->offsetGet('novalnet')) && $request->getActionName() == 'confirm') {
            $view->maskedDetails =  Shopware()->Session()->offsetGet('novalnet')->getArrayCopy();
        }

        $view->nnSepaInstalmentCycles    = $this->helper->getInstalmentCycles('novalnetsepainstalment');
        $view->nnInvoiceInstalmentCycles = $this->helper->getInstalmentCycles('novalnetinvoiceinstalment');
    }

    /*
     * load custom tpl forms for card payments
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchSecureFrontend(\Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $data    = $request->getPost();
        $paymentId = (!empty($data['payment'])) ? $data['payment'] : $data['sPayment'];
        if ($paymentId) {
            $payment = !empty($paymentId) ? $this->dataHandler->getPaymentMethod(['id' => $paymentId]) : '';
            $paymentName = $payment->getName();
        } else {
            $paymentName = Shopware()->Session()->offsetGet('sOrderVariables')['sUserData']['additional']['payment']['name'];
        }
        if ((in_array($request->getActionName(), array('saveShippingPayment', 'payment')) && in_array($paymentName, ['novalnetsepa','novalnetcc','novalnetsepaGuarantee', 'novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment', 'novalnetsepainstalment']) && !empty($data[$paymentName. 'FormData'])) || ($request->getControllerName() === 'checkout' && $request->getActionName() === 'payment' && ($paymentName == 'novalnetapplepay' || $paymentName == 'novalnetgooglepay'))) {
            Shopware()->Session()->offsetSet('novalnet', new ArrayObject($data[$paymentName. 'FormData'], ArrayObject::ARRAY_AS_PROPS));
        }
    }
}
