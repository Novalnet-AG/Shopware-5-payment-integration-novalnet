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

namespace Shopware\Plugins\NovalPayment\Components\Classes;

use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Shopware\CustomModels\Transaction\Transaction;

class PaymentNotification
{
    /**
     * Allowed host from Novalnet.
     *
     * @var string
     */
    protected $novalnetHostName = 'pay-nn.de';

    /**
     * Mandatory Parameters.
     *
     * @var array
     */
    protected $mandatory = [
        'event' => [
            'type',
            'checksum',
            'tid'
        ],
        'result' => [
            'status'
        ],
    ];

    /**
     * Request parameters.
     *
     * @var array
     */
    protected $eventData = [];

    /**
     * Your configuration details
     *
     * @var array
     */
    protected $configDetails;

    /**
     * Order reference values.
     *
     * @var Transaction
     */
    protected $orderReference;

    /**
     * Received Event type.
     *
     * @var string
     */
    protected $eventType;

    /**
     * Received Event TID.
     *
     * @var int
     */
    protected $eventTid;

    /**
     * Received Event parent TID.
     *
     * @var int
     */
    protected $parentTid;


    /**
     * Order Data.
     *
     * @var array
     */
    protected $orderData;

    /**
     * Payment Details.
     *
     * @var array
     */
    protected $paymentDetails;

    /**
     * @var string
     */
    protected $formattedAmount;

    protected $setViews;

    /**
     * @var string
     */
    protected $formattedAmountRefund;

    /**
     * @var NovalnetHelper
     */
    protected $helper;

    /**
     * @var DataHandler
     */
    protected $queryHandler;

    /**
     * @var ManageRequest
     */
    protected $requestHandler;

    /**
     * @var string
     */
    protected $newLine = '<br />';

    /**
     * Novalnet_Webhooks constructor.
     *
     * @since 2.0.0
     */
    public function __construct($content, $view)
    {
        try {
            $this->setViews  = $view;
            $this->eventData = json_decode($content, true);
        } catch (\Exception $e) {
            $this->setViews->message = "Received data is not in the JSON format $e";
            return;
        }

        $this->helper       = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $this->queryHandler = new DataHandler(Shopware()->Models());
        $this->requestHandler = new ManageRequest($this->helper);
        $this->startProcess();
    }

    /**
     * Processes the callback script
     *
     * @return void
     */
    public function startProcess()
    {
        $this->configDetails    = $this->helper->getConfigurations();
        if (!$this->validateCaptureParams() || !$this->validateEventData()) {
            return;
        }

        // Set Event data
        $this->eventType = $this->eventData['event']['type'];

        $this->parentTid = ! empty($this->eventData['event']['parent_tid']) ? $this->eventData['event']['parent_tid'] : $this->eventData['event']['tid'];

        $this->eventTid  = $this->eventData['event']['tid'];
        $this->formattedAmountRefund  = $this->helper->amountInBiggerCurrencyUnit($this->eventData['transaction']['refund']['amount'], $this->eventData['transaction']['currency']);
        $this->formattedAmount  = $this->helper->amountInBiggerCurrencyUnit($this->eventData['transaction']['amount'], $this->eventData['transaction']['currency']);

        $referenceId = ($this->eventData['transaction']['order_no']) ? $this->eventData['transaction']['order_no'] : ($this->parentTid ? $this->parentTid : $this->eventTid);
        $this->orderData      = $this->queryHandler->getOrder($referenceId);
        $paymentId = $this->orderData['paymentID'] ? $this->orderData['paymentID'] : $this->orderData['paymentId'];
        $this->paymentDetails = Shopware()->Db()->fetchRow('SELECT * FROM s_core_paymentmeans WHERE id = ?', [$paymentId]);

        // Get order reference.
        $this->orderReference = $this->getOrderReference();

        if ('SUCCESS' == $this->eventData['result']['status'] && 'FAILURE' != $this->eventData['transaction']['status'] && (!empty($this->orderReference) || $this->eventType == 'PAYMENT')) {
            switch ($this->eventType) {
                case "PAYMENT":
                    if (empty($this->orderReference) && empty($this->orderData)) {
                        $this->communicationFailure();
                    } else {
                        $this->setViews->message = 'Novalnet Callback executed. The Transaction ID already existed';
                    }
                    break;

                case "TRANSACTION_CAPTURE":
                case "TRANSACTION_CANCEL":
                    $comments = $this->transactionCaptureVoid();
                    break;

                case "TRANSACTION_REFUND":
                    $comments = $this->transactionRefund();
                    break;

                case "TRANSACTION_UPDATE":
                    $comments = $this->transactionUpdate();
                    break;

                case "CREDIT":
                    $comments = $this->creditProcess();
                    break;

                case "INSTALMENT":
                    $comments = $this->instalmentProcess();
                    break;

                case "CHARGEBACK":
                case "RETURN_DEBIT":
                case "REVERSAL":
                    $comments = $this->chargebackProcess();
                    break;
                
                case "PAYMENT_REMINDER_1":
                case "PAYMENT_REMINDER_2":
                    $callbackComments = $this->paymentReminderProcess();
                    break;
                    
                case "SUBMISSION_TO_COLLECTION_AGENCY":
                    $callbackComments = $this->collectionProcess();
                    break;

                default:
                    $this->setViews->message = "The webhook notification has been received for the unhandled EVENT type($this->eventType)";
            }
        } elseif ($this->eventData['transaction']['payment_type'] != 'ONLINE_TRANSFER_CREDIT') {
            $this->setViews->message = ($this->eventData['result']['status'] != 'SUCCESS') ? 'Novalnet callback received. Status is not valid.' : 'Novalnet callback received. Callback Script executed already.';
        }

        if (!empty($comments)) {
            $this->setViews->message .= $comments;
            $this->sendNotifyMail();
        }
        return;
    }

    /**
     * Get order reference from the novalnet_transaction_detail table on shop database
     *
     * @return mixed
     */
    public function getOrderReference()
    {
        $tid   = !empty($this->parentTid) ? $this->parentTid : $this->eventTid;
        $dbVal = $this->queryHandler->checkTransactionExists(['tid' => $tid]);

        // Order number check.
        if (!empty($dbVal) && ! empty($this->eventData['transaction']['order_no']) && $dbVal->getOrderNo() !== $this->eventData['transaction']['order_no']) {
            $this->setViews->message = 'Order reference not matching.';
            return;
        }

        if (empty($this->orderData) && $this->eventData['transaction']['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {
            $this->communicationFailure();
            return;
        }

        return $dbVal;
    }

    /**
     * Validate the required params to process callback
     *
     * @return bool
     */
    public function validateCaptureParams()
    {
        // Authenticating the server request based on IP.
        $requestReceivedIp = $this->helper->getIp();

        if (!empty($this->novalnetHostName)) {
            $novalnetHostIp  = gethostbyname($this->novalnetHostName);
            if (!empty($novalnetHostIp) && ! empty($requestReceivedIp) && $novalnetHostIp !== $requestReceivedIp && empty($this->configDetails['novalnetcallback_test_mode'])) {
                $this->setViews->message = 'Unauthorised access from the IP ' . $requestReceivedIp;
                return false;
            }
        } else {
            $this->setViews->message = 'Unauthorised access from the IP';
            return false;
        }
        return true;
    }

    /**
     * Validate the event data
     *
     * @return bool
     */
    public function validateEventData()
    {
        if (! empty($this->eventData['custom']['shop_invoked'])) {
            $this->setViews->message = 'Process already handled in the shop.';
            return false;
        }

        // Validate required parameter
        foreach ($this->mandatory as $category => $parameters) {
            if (empty($this->eventData[$category])) {
                // Could be a possible manipulation in the notification data
                $this->setViews->message = "Required parameter category($category) not received";
                return false;
            } elseif (!empty($parameters)) {
                foreach ($parameters as $parameter) {
                    if (empty($this->eventData[$category][$parameter])) {
                        // Could be a possible manipulation in the notification data
                        $this->setViews->message = "Required parameter($parameter) in the category($category) not received";
                        return false;
                    } elseif (in_array($parameter, [ 'tid' ], true) && ! preg_match('/^\d{17}$/', $this->eventData[$category][$parameter])) {
                        $this->setViews->message = "Invalid TID received in the category($category) not received formattedAmount$parameter";
                        return false;
                    }
                }
            }
        }

        // Validate the received checksum.
        if (!$this->validateChecksum()) {
            return false;
        }

        // Validate TID's from the event data
        if (! preg_match('/^\d{17}$/', $this->eventData['event']['tid'])) {
            $this->setViews->message = "Invalid event TID: " . $this->eventData['event']['tid'] . " received for the event(" . $this->eventData['event']['type'] . ")";
            return false;
        } elseif ($this->eventData['event']['parent_tid'] && ! preg_match('/^\d{17}$/', $this->eventData['event']['parent_tid'])) {
            $this->setViews->message = "Invalid event TID: " . $this->eventData['event']['parent_tid'] . " received for the event(" . $this->eventData['event']['type'] . ")";
            return false;
        }
        return true;
    }

    /**
     * Validate checksum
     *
     * @return bool
     */
    public function validateChecksum()
    {
        $token_string  = $this->eventData['event']['tid'] . $this->eventData['event']['type'] . $this->eventData['result']['status'];
        if (isset($this->eventData['transaction']['amount'])) {
            $token_string .= $this->eventData['transaction']['amount'];
        }
        if (isset($this->eventData['transaction']['currency'])) {
            $token_string .= $this->eventData['transaction']['currency'];
        }
        
        if (!empty($this->configDetails['novalnet_password'])) {
            $token_string .= strrev($this->configDetails['novalnet_password']);
        }
        
        $generated_checksum = hash('sha256', trim($token_string));
        if ($generated_checksum !== $this->eventData['event']['checksum']) {
            $this->setViews->message = "While notifying some data has been changed. The hash check failed";
            return false;
        }
        return true;
    }

    /**
     * Complete the order in-case response failure from Novalnet server.
     *
     * @return void
     */
    public function communicationFailure()
    {
        $nnSid = $this->eventData['custom']['inputval1'] ? $this->eventData['custom']['inputval1'] : $this->eventData['custom']['session_id'];

        // check for lower version
        if (empty($nnSid)) {
            $nnSid = $this->eventData['custom']['inputval5'] ? $this->eventData['custom']['inputval5'] : $this->eventData['custom']['nn_sid'];
        }

        if (!$nnSid) {
            $this->setViews->message = 'Reference is empty, so not able to map the order';
            return;
        }
        $orderId = (int) Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE temporaryID = ?', array($nnSid));
        
        // check with temporary order ID if session ID changes
        if(empty($orderId))
        {
			$orderId = $this->eventData['custom']['inputval2'] ? $this->eventData['custom']['inputval2'] : $this->eventData['custom']['temporary_order_id'];
		}
		
        $orderData = Shopware()->Modules()->Order()->getOrderById($orderId);
        $orderDetailData = Shopware()->Modules()->Order()->getOrderDetailsByOrderId($orderId);
        Shopware()->Session()->offsetSet('sPaymentID', $orderData['paymentID']);
        Shopware()->Session()->offsetSet('sUserId', $orderData['userID']);
        Shopware()->Session()->offsetSet('sDispatch', $orderData['dispatchID']);
        Shopware()->Session()->offsetSet('sessionId', $nnSid);
        $userData    = Shopware()->Modules()->Admin()->sGetUserData();
        $paymentData = Shopware()->Modules()->Admin()->sGetPaymentMeanById($orderData['paymentID'], $userData);

        if (empty($orderData['ordernumber']) && !empty($orderData)) {
            $userData['additional']['charge_vat'] = true;
            if ($this->helper->isTaxFreeDelivery($userData)) {
                $userData['additional']['charge_vat'] = false;
            }
            $basket = $this->helper->getBasket();
			
			// load basket if emty
			if(empty($basket['content']))
            {
                foreach ($orderDetailData as $detail)
                {
                    $sql = 'SELECT * FROM s_core_customergroups WHERE groupkey=?';
                    Shopware()->Modules()->Basket()->sSYSTEM->sUSERGROUPDATA = Shopware()->Db()->fetchRow($sql, [$userData['additional']['user']['customergroup']]);
                    Shopware()->Session()->sUserGroupData = Shopware()->Db()->fetchRow($sql, [$userData['additional']['user']['customergroup']]);
                    Shopware()->Session()->sUserGroup = $userData['additional']['user']['customergroup'];
                    Shopware()->Config()->DontAttachSession = true;
                    Shopware()->Container()->get('shopware_storefront.context_service')->initializeShopContext();
                    Shopware()->Session()->set('Bot', false);
                    Shopware()->Modules()->Basket()->sAddArticle($detail['articleordernumber'], $detail['quantity']);
                }
                $basket = $this->helper->getBasket();
            }
            
            # Insert order data in releated tables
            try {
                $note = $this->helper->formCustomerComments($this->eventData, $paymentData['name'], $orderData['currency']);
                
                Shopware()->Session()->offsetSet('serverResponse', $this->eventData);

                $order = Shopware()->Modules()->Order();
                $order->sUserData = $userData;
                $order->sComment = $note;
                $order->sBasketData = $basket;
                $order->sAmount = $basket['sAmount'];
                $order->sAmountWithTax = !empty($basket['AmountWithTaxNumeric']) ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];
                $order->sAmountNet = $basket['AmountNetNumeric'];
                $order->sShippingcosts = $basket['sShippingcosts'];
                $order->sShippingcostsNumeric = $basket['sShippingcostsWithTax'];
                $order->sShippingcostsNumericNet = $basket['sShippingcostsNet'];
                $order->bookingId = (string) $this->eventTid;
                $order->dispatchId = Shopware()->Session()->get('sDispatch');
                $order->sNet = empty($userData['additional']['charge_vat']);
                $order->uniqueID = (string) $this->eventTid;
                $order->deviceType = 'desktop';
                $orderNumber = $order->sSaveOrder();
                
                $paymentStatusId   = (($this->eventData['transaction']['status'] == 'ON_HOLD') ? '18' : (($this->eventData['transaction']['status'] == 'PENDING' && !in_array($paymentData['name'], ['novalnetinvoice', 'novalnetprepayment', 'novalnetmultibanco', 'novalnetcashpayment'])) ? '17' : $this->configDetails[$paymentData['name'] . '_after_paymenstatus']));

                if (!empty($orderNumber) && !empty($paymentStatusId)) {
                    $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array($orderNumber));
                    Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $paymentStatusId);
                }

                $sOrder = [
                    'customercomment' => str_replace('<br />', PHP_EOL, $note),
                    'temporaryID' => $this->eventTid,
                    'transactionID' => $this->eventTid,
                    'ordernumber' => $orderNumber
                ];

                // update order table
                $this->queryHandler->updateOrdertable($sOrder);
                $configuration = [];
                if ($paymentData['name'] == 'novalnetcc' && !empty($this->eventData['transaction']['payment_data']['token'])) {
                    $configuration = [
                        'token' => $this->eventData['transaction']['payment_data']['token'],
                        'cardBrand' => $this->eventData['transaction']['payment_data']['card_brand'],
                        'expiryDate' => sprintf("%02d", $this->eventData['transaction']['payment_data']['card_expiry_month']) .'/'. $this->eventData['transaction']['payment_data']['card_expiry_year'],
                        'cardHolder' => $this->eventData['transaction']['payment_data']['card_holder'],
                        'accountData' => $this->eventData['transaction']['payment_data']['card_number']
                    ];
                }

                //Store order details in novalnet table
                $this->queryHandler->insertNovalnetTransaction([
                    'tid'    => $this->eventTid,
                    'amount' => $this->eventData['transaction']['amount'],
                    'order_no' => $orderNumber,
                    'currency' => $this->eventData['transaction']['currency'],
                    'customer_id'  => $this->eventData['customer']['customer_no'],
                    'payment_type' => $paymentData['name'],
                    'paid_amount'  => ($this->eventData['transaction']['status'] === 'CONFIRMED') ? $this->eventData['transaction']['amount'] : 0,
                    'refunded_amount' => 0,
                    'gateway_status'  => $this->eventData['transaction']['status'],
                    'configuration_details'  => !empty($configuration) ? $this->helper->serializeData($configuration) : ''
                ]);

                //update order number for transaction
                $this->requestHandler->postCallBackProcess($orderNumber, $this->eventData['transaction']['tid']);
                
                Shopware()->Session()->offsetUnset('serverResponse');

                $this->setViews->message = $this->newLine . $note;
                return;
            } catch (\Exception $e) {
                Shopware()->Container()->get('pluginlogger')->error($e->getMessage());
            }
        } else {
            $this->setViews->message = 'Order is already updated with Order No: '. $orderData['ordernumber']. '. So kindly take neccessary action for the payment which was already done with TID - '. $this->eventTid;
            return;
        }
    }

    /**
     * Form the instalment data into serialize
     *
     * @return string
     */
    private function updateInstalmentInfo()
    {
        $configurationDetails = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());
        $instalmentData       = $this->eventData['instalment'];
        $configurationDetails['InstalmentDetails'][$instalmentData['cycles_executed']] = [
            'amount'        => $instalmentData['cycle_amount'],
            'cycleDate'     => date('Y-m-d'),
            'cycleExecuted' => $instalmentData['cycles_executed'],
            'dueCycles'     => $instalmentData['pending_cycles'],
            'paidDate'      => date('Y-m-d'),
            'status'        => $this->helper->getLanguageFromSnippet('paidMsg'),
            'reference'     => $this->eventData['transaction']['tid']
        ];
        return $this->helper->serializeData($configurationDetails);
    }

    /**
     * Check transaction cancellation
     *
     * @return string
     */
    private function transactionCaptureVoid()
    {
        $callbackComments = $paymentStatusID = '';
        $appendComments = true;

        if (in_array($this->orderReference->getGatewayStatus(), ['ON_HOLD', 'PENDING']) || in_array($this->orderReference->getGatewayStatus(), ['98', '99', '91', '85', '90', '86', '75'])) {
            $upsertData = [
                'id'            => $this->orderReference->getId(),
                'tid'           => $this->orderReference->getTid(),
                'gateway_status' => $this->eventData['transaction']['status']
            ];

            if ($this->eventType === 'TRANSACTION_CAPTURE') {
                if ($this->orderReference->getPaymentType() == 'novalnetinvoice') {
                    $this->eventData['transaction']['status'] = $upsertData['gateway_status'] = 'PENDING';
                }

                $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_confirm_message'), date('d/m/Y H:i:s'));

                if (in_array($this->eventData['transaction']['status'], ['CONFIRMED', 'PENDING'])) {
                    if (!empty($this->orderReference->getConfigurationDetails() && !empty($this->orderReference->getPaymentType()) && in_array($this->orderReference->getPaymentType(), ['novalnetinvoice', 'novalnetinvoiceGuarantee','novalnetinvoiceinstalment', 'novalnetprepayment']))) {
                        $appendComments = false;
                        if (empty($this->eventData['transaction']['bank_details'])) {
                            $this->eventData['transaction']['bank_details'] = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());
                        }
                        $callbackComments .= $this->newLine . $this->newLine . $this->helper->formCustomerComments($this->eventData, $this->orderReference->getPaymentType(), $this->eventData['transaction']['currency']);
                    }

                    if ($this->eventData['transaction']['status'] == 'CONFIRMED') {
                        $upsertData['paid_amount'] = $this->orderReference->getAmount();
                        if (in_array($this->eventData['transaction']['payment_type'], ['INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA'])) {
                            $this->eventData['transaction']['amount'] = $this->orderReference->getAmount();
                            $upsertData['configuration_details'] = $this->helper->getInstalmentInformation($this->eventData);
                            $upsertData['configuration_details'] = $this->helper->serializeData($upsertData['configuration_details']);
                        }
                    }

                    if (in_array($this->orderReference->getPaymentType(), ['novalnetinvoice', 'novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment', 'novalnetsepaGuarantee', 'novalnetsepainstalment'])) {
                        $context             = $this->novalnetMailObj();
                        $context['sComment'] = $callbackComments;
                        $this->helper->sendNovalnetOrderMail($context, true);
                    }
                }
            } else {
                $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_failure_message'), date('d/m/Y H:i:s'));
            }

            $this->queryHandler->postProcess($callbackComments, $upsertData, $appendComments);

            $paymentStatusID = $this->configDetails[$this->paymentDetails['name'].'_after_paymenstatus'];

            if ($this->eventType == 'TRANSACTION_CANCEL') {
                $paymentStatusID = 35;
            }

            if (!empty($paymentStatusID)) {
                Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], $paymentStatusID, false);
            }
        } else {
            // transaction already captured or transaction not been authorized.
            $this->setViews->message = 'Order already processed.';
        }

        return $callbackComments;
    }

    /**
     * Check transaction Refund
     *
     * @return string
     */
    private function transactionRefund()
    {
        $callbackComments = '';

        if (! empty($this->eventData['transaction']['refund']['amount'])) {
            $refundAmount = $this->eventData['transaction']['refund']['amount'];
        } else {
            $refundAmount = (int) $this->orderReference->getAmount() - (int) $this->orderReference->getRefundedAmount();
        }

        if (! empty($refundAmount)) {
            $totalRefundedAmount = (int) $this->orderReference->getRefundedAmount() + (int) $refundAmount;

            if ($totalRefundedAmount <= $this->orderReference->getAmount()) {
                $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_refund_message'), $this->parentTid, $this->formattedAmountRefund);

                if (!empty($this->eventData['transaction']['refund']['tid'])) {
                    $callbackComments .= sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_refund_new_tid_message'), $this->eventData['transaction']['refund']['tid']);
                }

                $upsertData = [
                    'id'            => $this->orderReference->getId(),
                    'tid'           => $this->orderReference->getTid(),
                    'gateway_status' => $this->eventData['transaction']['status'],
                    'refunded_amount' => $totalRefundedAmount
                ];

                $this->queryHandler->postProcess($callbackComments, $upsertData);

                if ($totalRefundedAmount >= $this->orderReference->getAmount()) {
                    Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], 35, false);
                }
            } else {
                $this->setViews->message = 'Already full amount refunded for this TID';
            }
        } else {
            $this->setViews->message = 'Already full amount refunded for this TID';
        }
        return $callbackComments;
    }

    /**
     * Handle payformattedAmountment credit process
     *
     * @return string
     */
    private function creditProcess()
    {
        $callbackComments = '';

        if ($this->orderReference->getPaidAmount() < $this->orderReference->getAmount() && in_array($this->eventData['transaction']['payment_type'], [ 'INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'MULTIBANCO_CREDIT', 'ONLINE_TRANSFER_CREDIT'])) {
            // Calculate total amount.
            $paidAmount = (int) $this->orderReference->getPaidAmount() + (int) $this->eventData['transaction']['amount'];

            // Calculate including refunded amount.
            $amountToBePaid = (int) $this->orderReference->getAmount() - (int) $this->orderReference->getRefundedAmount();

            $upsertData['id']            = $this->orderReference->getId();
            $upsertData['tid']           = $this->orderReference->getTid();
            $upsertData['gateway_status'] = $this->eventData['transaction']['status'];
            $upsertData['paid_amount']    = $paidAmount;

            if ($this->eventData['transaction']['payment_type'] === 'ONLINE_TRANSFER_CREDIT') {
                $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message'), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->parentTid);
            } else {
                $callbackComments = $this->newLine. sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message'), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->eventTid);
            }

            $this->queryHandler->postProcess($callbackComments, $upsertData);

            if ($paidAmount >= $amountToBePaid || $this->eventData['transaction']['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {
                $paymentStatusID = !empty($this->configDetails[$this->paymentDetails['name'].'_before_paymenstatus']) ? $this->configDetails[$this->paymentDetails['name'].'_before_paymenstatus'] : $this->configDetails[$this->paymentDetails['name'].'_after_paymenstatus'];
                Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], $paymentStatusID, false);
            }
        } elseif (in_array($this->eventData['transaction']['payment_type'], [ 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD', 'CREDITCARD_REPRESENTMENT', 'BANK_TRANSFER_BY_END_CUSTOMER', 'APPLEPAY_REPRESENTMENT', 'DEBT_COLLECTION_DE', 'CREDIT_ENTRY_DE'])) {
            $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message'), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->eventTid);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()]);
        } else {
            $this->setViews->message = 'Novalnet webhook received. Order Already Paid';
        }
        return $callbackComments;
    }

    /**
     * Handle payment INSTALMENT process
     *
     * @return string
     */
    private function instalmentProcess()
    {
        $comments = sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_instalment_prepaid_message'), $this->parentTid, $this->formattedAmount, $this->eventTid).$this->newLine;

        if (in_array($this->eventData['transaction']['payment_type'], ['INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA']) && $this->orderReference->getGatewayStatus() == 'CONFIRMED' && empty($this->eventData['instalment']['prepaid'])) {
            $comments = $this->helper->formCustomerComments($this->eventData, $this->orderReference->getPaymentType(), $this->eventData['transaction']['currency']);
        }

        $upsertData['id']                   = $this->orderReference->getId();
        $upsertData['tid']                  = $this->orderReference->getTid();
        $upsertData['configuration_details']    = $this->updateInstalmentInfo();

        $this->queryHandler->postProcess($comments, $upsertData, false);
        $context             = $this->novalnetMailObj();
        $context['sComment'] = $comments;
        $this->helper->sendNovalnetOrderMail($context, true);
        return $comments;
    }

    /**
     * Handle payment CHARGEBACK/RETURN_DEBIT/REVERSAL process
     *
     * @return string
     */
    private function chargebackProcess()
    {
        $callbackComments = '';

        if (in_array($this->orderReference->getGatewayStatus(), ['CONFIRMED', '100']) && ! empty($this->eventData['transaction']['amount'])) {
            $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_chargeback_message'), $this->parentTid, $this->formattedAmount, date('d/m/Y H:i:s'), $this->eventTid);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()]);
        }
        return $callbackComments;
    }
    
    /**
     * Handle payment reminder process
     *
     * @return void
     */
    private function paymentReminderProcess()
    {
		$callbackComments = '';

        if (in_array($this->orderReference->getGatewayStatus(), ['CONFIRMED', 'PENDING', '100'])) {
			$reminderCount = explode('_', $this->eventType);
			$reminderCount = end($reminderCount);
            $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_payment_reminder_message'), $reminderCount);

            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()]);
        }
        return $callbackComments;
    }
    
    /**
     * Handle collection process
     *
     * @return void
     */
    private function collectionProcess()
    {
		$callbackComments = '';

        if (in_array($this->orderReference->getGatewayStatus(), ['CONFIRMED', 'PENDING', '100'])) {
            $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_collection_message'), $this->eventData['collection']['reference']);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()]);
        }
        return $callbackComments;
	}

    /**
     * Handle transaction update
     *
     * @return string
     */
    private function transactionUpdate()
    {
        $callbackComments = '';
        $appendComments = true;

        $upsertData = [
            'id' => $this->orderReference->getId(),
            'tid' => $this->orderReference->getTid(),
            'gateway_status' => $this->eventData['transaction']['status'],
        ];

        if (in_array($this->eventData['transaction']['status'], [ 'PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED' ])) {
            if (in_array($this->eventData['transaction']['update_type'], ['DUE_DATE', 'AMOUNT_DUE_DATE'])) {
                $upsertData['amount'] = $this->eventData['transaction']['amount'];

                $dueDate = date('d/m/Y', strtotime($this->eventData['transaction']['due_date']));

                if ($this->eventData['transaction']['payment_type'] === 'CASHPAYMENT') {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_cashpayment_message'), $this->formattedAmount, $dueDate);
                } else {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_duedate_update_message'), $this->formattedAmount, $dueDate);
                }
            } elseif ($this->eventData['transaction']['update_type'] === 'STATUS') {
                if (in_array($this->eventData['transaction']['payment_type'], ['GUARANTEED_INVOICE', 'INSTALMENT_INVOICE', 'INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INSTALMENT_DIRECT_DEBIT_SEPA']) && $this->eventData['transaction']['status'] !== 'DEACTIVATED') {
                    $appendComments = false;
                    if (!empty($this->orderReference->getConfigurationDetails()) && empty($this->eventData['transaction']['bank_details'])) {
                        $this->eventData['transaction']['bank_details'] = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());
                    }
                    $callbackComments = $this->helper->formCustomerComments($this->eventData, $this->orderReference->getPaymentType(), $this->eventData['transaction']['currency']);
                }

                if ($this->eventData['transaction']['status'] === 'DEACTIVATED') {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_failure_message'), date('d/m/Y H:i:s'));
                } elseif ($this->orderReference->getGatewayStatus() === 'PENDING' || in_array($this->orderReference->getGatewayStatus(), ['75', '86', '90', '100'])) {
                    $upsertData['amount'] = $this->eventData['transaction']['amount'];

                    if ($this->eventData['transaction']['status'] === 'ON_HOLD') {
                        $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_update_onhold_message'), $this->eventTid, date('d/m/Y H:i:s'));
                    // Payment not yet completed, set transaction status to "AUTHORIZE"
                    } elseif ($this->eventData['transaction']['status'] === 'CONFIRMED') {
                        $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_confirm_message'), date('d/m/Y H:i:s'));

                        if (in_array($this->eventData['transaction']['payment_type'], ['INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA'])) {
                            $this->eventData['transaction']['amount'] = $this->orderReference->getAmount();
                            $upsertData['configuration_details'] = $this->helper->serializeData($this->helper->getInstalmentInformation($this->eventData));
                        } elseif (in_array($this->eventData['transaction']['payment_type'], ['PAYPAL', 'PRZELEWY24'])) {
                            $callbackComments = $this->newLine. sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_redirect_update_message'), $this->eventTid, date('d/m/Y H:i:s'));
                        }

                        $upsertData['paid_amount'] = $this->eventData['transaction']['amount'];
                    }
                }
            } else {
                if (!empty($this->eventData['transaction']['amount'])) {
                    $upsertData['amount'] = $this->eventData['transaction']['amount'];
                }
                $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_update_message'), $this->eventTid, $this->formattedAmount, date('d/m/Y H:i:s'));
            }
        }
        $this->queryHandler->postProcess($callbackComments, $upsertData, $appendComments);

        $statusId = $this->configDetails[$this->paymentDetails['name'].'_after_paymenstatus'];

        if ($this->eventData['transaction']['status'] === 'DEACTIVATED') {
            $statusId = 35;
        } elseif ($this->eventData['transaction']['status'] === 'ON_HOLD') {
            $statusId = 18;
        }
        if (!empty($statusId)) {
            Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], $statusId, false);
        }

        if (in_array($this->orderReference->getPaymentType(), ['novalnetinvoice', 'novalnetinvoiceGuarantee', 'novalnetsepaGuarantee', 'novalnetinvoiceinstalment', 'novalnetsepainstalment']) && in_array($this->eventData['transaction']['status'], ['CONFIRMED', 'PENDING', 'ON_HOLD'])) {
            $context             = $this->novalnetMailObj();
            $context['sComment'] = $callbackComments;
            $this->helper->sendNovalnetOrderMail($context, true);
        }
        return $callbackComments;
    }

    /**
     * Send notify email after callback process
     *
     * @return void
     */
    public function sendNotifyMail()
    {
        $mail = clone Shopware()->Container()->get('mail');

        if (!empty($this->configDetails['novalnet_callback_mail_send_to'])) {
            $toEmail     = explode(',', $this->configDetails['novalnet_callback_mail_send_to']);
            $mailSubject = 'Novalnet Callback Script Access Report - Order No : ' . $this->eventData['transaction']['order_no'] . ' in the ' . Shopware()->Config()->get('shopName');
            if (!empty($toEmail)) {
                foreach ($toEmail as $value) {
                    $mail->addTo($value);
                }
            }

            $mail->setSubject($mailSubject);
            $mail->setBodyHtml($this->setViews->message);
            try {
                $mail->send();
                $this->setViews->message .= '<br />Mail Sent Successfully';
            } catch (\Exception $e) {
                $this->setViews->message .= '<br />Error in sending the Mail <br>' . $e->getMessage();
            }
        }
    }

    /**
     * Get mail object from shop database
     *
     * @return array
     */
    public function novalnetMailObj()
    {
        $sOrderDetails = array();
        $esdOrder = false;
        Shopware()->Session()->offsetSet('sUserId', $this->orderData['customerId']);
        $userData = $this->helper->getUserInfo();

        foreach (array('billing', 'shipping') as $field) {
            $this->orderData[$field]['firstname'] = $this->orderData[$field]['firstName'];
            $this->orderData[$field]['lastname']  = $this->orderData[$field]['lastName'];
        }
        foreach ($this->orderData['details'] as $detail) {
            if ($detail['esdArticle']) {
                $esdOrder = true;
            }
            $sOrderDetails[] = array(
                'articlename' => $detail['articleName'],
                'ordernumber' => $detail['articleNumber'],
                'quantity' => $detail['quantity'],
                'price' => $detail['price'],
                'tax_rate' => $detail['tax_rate'],
                'amount' => $detail['price'] * $detail['quantity'],
                'orderDetailId' => $detail['id']
            );
        }

        $context = array(
            'sOrderDetails' => $sOrderDetails,
            'billingaddress' => $this->orderData['billing'],
            'shippingaddress' => $this->orderData['shipping'],
            'additional' => $userData['additional'],
            'sBookingID' => $this->eventData['transaction']['tid'],
            'sOrderNumber' => $this->orderData['number'],
            'sOrderDay' => date('d.m.Y'),
            'sOrderTime' => date('H:i'),
            'ordernumber' => $this->orderData['number'],
            'attributes' => '',
            'sCurrency' => $this->orderData['currency'],
            'sShippingCosts' => $this->orderData['invoiceShipping'],
            'sAmount' => $this->orderData['invoiceAmount'],
            'sAmountNet' => $this->orderData['invoiceAmountNet'],
            'sLanguage' => $this->helper->getLocale(),
            'sDispatch' => $this->orderData['dispatch'],
            'sSubShop' => $this->orderData['shopId'],
            'sPaymentTable' => $this->orderData['shopId'],
            'sEsd' => $esdOrder
        );

        if ($this->eventType == 'INSTALMENT') {
            $context['sInstalment'] = true;
        }
        return $context;
    }
}
