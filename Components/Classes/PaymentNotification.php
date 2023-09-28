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
use Shopware\Components\Model\ModelManager;

class PaymentNotification
{
    /**
     * Allowed host from Novalnet.
     *
     * @var string
     */
    private $novalnetHostName = 'pay-nn.de';

    /**
     * Mandatory Parameters.
     *
     * @var array
     */
     
    private $mandatory = [
        'event'       => [
            'type',
            'checksum',
            'tid',
        ],
        'merchant'    => [
            'vendor',
            'project',
        ],
        'result'      => [
            'status',
        ],
        'transaction' => [
            'tid',
            'payment_type',
            'status',
        ],
    ];

    /**
     * Request parameters.
     *
     * @var array
     */
    private $eventData = [];

    /**
     * Your configuration details
     *
     * @var array
     */
    private $configDetails;

    /**
     * Order reference values.
     *
     * @var Transaction
     */
    private $orderReference;

    /**
     * Received Event type.
     *
     * @var string
     */
    private $eventType;

    /**
     * Received Event TID.
     *
     * @var int
     */
    private $eventTid;

    /**
     * Received Event parent TID.
     *
     * @var int
     */
    private $parentTid;

    /**
     * Order Data.
     *
     * @var array
     */

    private $orderData;

    /**
     * Payment Details.
     *
     * @var array
     */
    private $paymentDetails;

    /**
     * @var string
     */
    private $formattedAmount;

    private $setViews;

    /**
     * @var string
     */
    private $formattedAmountRefund;

    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var DataHandler
     */
    private $queryHandler;

    /**
     * @var ManageRequest
     */
    private $requestHandler;

    /**
     * @var string
     */
    private $newLine = '<br />';

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
    private function startProcess()
    {
        $this->configDetails    = $this->helper->getConfigurations();
        if (!$this->checkIp() || !$this->validateEventData()) {
            return;
        }

        // Set Event data
        $this->eventType = $this->eventData['event']['type'];

        $this->eventTid  = $this->eventData['event']['tid'];

        $this->parentTid = ! empty($this->eventData['event']['parent_tid']) ? $this->eventData['event']['parent_tid'] : $this->eventTid;

        $this->formattedAmountRefund  = $this->helper->amountInBiggerCurrencyUnit($this->eventData['transaction']['refund']['amount'], $this->eventData['transaction']['currency']);

        $this->formattedAmount  = $this->helper->amountInBiggerCurrencyUnit($this->eventData['transaction']['amount'], $this->eventData['transaction']['currency']);

        $referenceId = ! empty($this->eventData['transaction']['order_no']) ? $this->eventData['transaction']['order_no'] : $this->parentTid;

        $this->orderData      = $this->queryHandler->getOrder($referenceId);

        $paymentId = ! empty($this->orderData['paymentID']) ? $this->orderData['paymentID'] : $this->orderData['paymentId'];

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

                case "INSTALMENT_CANCEL":
                    $comments = $this->instalmentCancelProcess();
                    break;

                case "CHARGEBACK":
                case "RETURN_DEBIT":
                case "REVERSAL":
                    $comments = $this->chargebackProcess();
                    break;

                case "PAYMENT_REMINDER_1":
                case "PAYMENT_REMINDER_2":
                    $comments = $this->paymentReminderProcess();
                    break;

                case "SUBMISSION_TO_COLLECTION_AGENCY":
                    $comments = $this->collectionProcess();
                    break;

                default:
                    $this->setViews->message = "The webhook notification has been received for the unhandled EVENT type($this->eventType)";
            }
        } elseif ($this->eventData['transaction']['payment_type'] != 'ONLINE_TRANSFER_CREDIT') {
            $this->setViews->message = (!empty($this->eventData['result']['status']) && $this->eventData['result']['status'] != 'SUCCESS') ? 'Novalnet callback received. Status is not valid.' : 'Novalnet callback received. Callback Script executed already.';
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
    private function getOrderReference()
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
     * Validate the required IPAddress to process callback
     *
     * @return bool
     */
    private function checkIp()
    {
        $novalnetHostIp    = gethostbyname($this->novalnetHostName);
        $validateRequestIp = $this->validateRequestIp($novalnetHostIp);

        if (!empty($novalnetHostIp)) {
            if (!$validateRequestIp && empty($this->configDetails['novalnetcallback_test_mode'])) {
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
     * Validate the request ip with Novalnet host Ip
     *
     * @param string $novalnetHostIp
     * @return boolean
     */
    private function validateRequestIp($novalnetHostIp)
    {
        $serverVariables = $_SERVER;
        $remoteAddrHeaders = ['HTTP_X_FORWARDED_HOST', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
        foreach ($remoteAddrHeaders as $header) {
            if (array_key_exists($header, $_SERVER) === true) {
                if (in_array($header, ['HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR'])) {
                    $forwardedIps = (!empty($serverVariables[$header])) ? explode(",", $serverVariables[$header]) : [];
                    if (in_array($novalnetHostIp, $forwardedIps)) {
                        return true;
                    }
                }
    
                if ($serverVariables[$header] ==  $novalnetHostIp) {
                    return true;
                }
            }
        }
    
        return false;
    }

    /**
     * Validate the event data
     *
     * @return bool
     */
    private function validateEventData()
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
    private function validateChecksum()
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
     * Send notify email after callback process
     *
     * @param null $mailSubject
     * @param null $mailBody
     * @return void
     */
    private function sendNotifyMail($mailSubject = null, $mailBody = null)
    {
        $mail        = clone Shopware()->Container()->get('mail');
        $toEmail     = !empty($this->configDetails['novalnet_callback_mail_send_to']) ? explode(',', $this->configDetails['novalnet_callback_mail_send_to']) : [];
        $toEmail     = array_merge($toEmail, [Shopware()->Config()->get('Mail')]);
        $localeValue = $this->helper->getLocale();
        $lang        = ! empty($localeValue) ? $localeValue : 'de_DE';

        if ($mailBody) {
            $template = clone Shopware()->Template();
            $shop = Shopware()->Shop();
            $inheritance = Shopware()->Container()->get('theme_inheritance');
            $config = $inheritance->buildConfig(
                $shop->getTemplate(),
                $shop,
                false
            );

            $template->assign('theme', $config);

            $mailSubject = $template->fetch('string:' . $mailSubject, $template);
            $mailBody = $template->fetch('string:' . $mailBody, $template);
        } else {
            if (strpos($lang, 'en') !== false) {
                $mailSubject = 'Novalnet Callback Script Access Report - Order No : ' . $this->eventData['transaction']['order_no'] . ' in the ' . Shopware()->Config()->get('shopName');
            } else {
                $mailSubject = 'Novalnet-Callback-Skript Zugriff Bericht - Bestellung No: ' . $this->eventData['transaction']['order_no'] . ' in der' . Shopware()->Config()->get('shopName');
            }
            
            $mailBody = $this->setViews->message;
        }

        if (!empty($toEmail)) {
            foreach ($toEmail as $value) {
                $mail->addTo($value);
            }
        }

        $mail->setSubject($mailSubject);
        $mail->setBodyHtml($mailBody);

        try {
            $mail->send();
            $this->setViews->message .= '<br />Mail Sent Successfully';
        } catch (\Exception $e) {
            $this->setViews->message .= '<br />Error in sending the Mail <br>' . $e->getMessage();
        }
    }

    /**
     * Complete the order in-case response failure from Novalnet server.
     *
     * @return void
     */
    private function communicationFailure()
    {
        $nnSid = ! empty($this->eventData['custom']['inputval1']) ? $this->eventData['custom']['inputval1'] : $this->eventData['custom']['session_id'];

        // check for lower version
        if (empty($nnSid)) {
            $nnSid = ! empty($this->eventData['custom']['inputval5']) ? $this->eventData['custom']['inputval5'] : $this->eventData['custom']['nn_sid'];
        }

        if (!$nnSid) {
            $this->setViews->message = 'Reference is empty, so not able to map the order';
            return;
        }
        $orderId = (int) Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE temporaryID = ?', array($nnSid));

        // check with temporary order ID if session ID changes
        if (empty($orderId)) {
            $orderId = ! empty($this->eventData['custom']['inputval2']) ? $this->eventData['custom']['inputval2'] : $this->eventData['custom']['temporary_order_id'];
        }

        $orderData = Shopware()->Modules()->Order()->getOrderById($orderId);
        $orderDetailData = Shopware()->Modules()->Order()->getOrderDetailsByOrderId($orderId);
        Shopware()->Session()->offsetSet('sPaymentID', $orderData['paymentID']);
        Shopware()->Session()->offsetSet('sUserId', $orderData['userID']);
        Shopware()->Session()->offsetSet('sDispatch', $orderData['dispatchID']);
        Shopware()->Session()->offsetSet('sessionId', $nnSid);
        $userData    = Shopware()->Modules()->Admin()->sGetUserData();
        $paymentData = Shopware()->Modules()->Admin()->sGetPaymentMeanById($orderData['paymentID'], $userData);
        $novalnetPaymentName = Shopware()->Db()->fetchOne('SELECT novalnet_payment_name FROM s_order_attributes WHERE  orderID = ?', [ (int) $orderId]);

        if (! empty($novalnetPaymentName)) {
            $this->eventData['transaction']['payment_name'] = $novalnetPaymentName;
        }

        if (empty($orderData['ordernumber']) && !empty($orderData)) {
            $userData['additional']['charge_vat'] = true;
            if ($this->helper->isTaxFreeDelivery($userData)) {
                $userData['additional']['charge_vat'] = false;
            }
            $basket = $this->helper->getBasket();

            // load basket if empty
            if (empty($basket['content'])) {
                foreach ($orderDetailData as $detail) {
                    $sql = 'SELECT * FROM s_core_customergroups WHERE groupkey=?';
                    Shopware()->Modules()->Basket()->sSYSTEM->sUSERGROUPDATA = Shopware()->Db()->fetchRow($sql, [$userData['additional']['user']['customergroup']]);
                    Shopware()->Session()->sUserGroupData = Shopware()->Db()->fetchRow($sql, [$userData['additional']['user']['customergroup']]);
                    Shopware()->Session()->sUserGroup = $userData['additional']['user']['customergroup'];
                    Shopware()->Config()->DontAttachSession = true;
                    Shopware()->Container()->get('shopware_storefront.context_service')->initializeShopContext();
                    Shopware()->Container()->get('Bot', false);
                    Shopware()->Modules()->Basket()->sAddArticle($detail['articleordernumber'], $detail['quantity']);
                }
                $basket = $this->helper->getBasket();
            }

            $BasketTotalAmount = $this->helper->getAmount();
            $langCode = strtolower($this->helper->getLocale(false, true));
            $language = in_array($langCode, ['en', 'de']) ? $langCode  : 'de' ;
            $formattedBasketTotalAmount = $this->helper->setHtmlEntity($this->helper->amountInBiggerCurrencyUnit($BasketTotalAmount, Shopware()->Shop()->getCurrency()->getCurrency()), $type = 'encode');
            $formattedNnBookedAmount = $this->helper->setHtmlEntity($this->helper->amountInBiggerCurrencyUnit($this->eventData['transaction']['amount'], $this->eventData['transaction']['currency']), $type = 'encode');

            if ($BasketTotalAmount != $this->eventData['transaction']['amount']) {
                $emailPath = __DIR__ . '/mail/'.$language. '/OrderAmountDiffer.tpl';
                $template = file_get_contents($emailPath);
                $template = str_replace('#TID#', $this->parentTid, $template);
                $template = str_replace('#PAYMENT_METHOD#', $this->eventData['transaction']['payment_name'], $template);
                $template = str_replace('#BASKET_AMOUNT#', $formattedBasketTotalAmount, $template);
                $template = str_replace('#NOVALNET_BOOKED_AMOUNT#', $formattedNnBookedAmount, $template);
                $template = str_replace('#HEADER#', Shopware()->Config()->get('emailheaderhtml'), $template);
                $template = str_replace('#FOOTER#', Shopware()->Config()->get('emailfooterhtml'), $template);
                $template = str_replace('#CUSTOMER_NAME#', $userData['billingaddress']['firstname']. ' '.$userData['billingaddress']['lastname'], $template);
                $template = str_replace('#CUSTOMER_EMAIL#', $userData['additional']['user']['email'], $template);

                $this->sendNotifyMail($this->helper->getLanguageFromSnippet('novalnet_order_amount_diff_mail_subject'), $template);

                $this->setViews->message = $this->newLine . 'The basket amount '. $formattedBasketTotalAmount. ' is  differs from the Novalnet Booked Amount (' . $formattedNnBookedAmount . ')for the TID :'. $this->parentTid;
                return;
            }

            # Insert order data in releated tables
            try {
                $note = $this->helper->formCustomerComments($this->eventData, $orderData['currency']);
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
                
                $newOrderId = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', [$orderNumber]);
                $newOrderAttrId = Shopware()->Db()->fetchOne('SELECT id FROM s_order_attributes WHERE  orderID = ?', [$newOrderId]);
                $checkPaymentValue =  (! empty($this->eventData['transaction']['payment_name']) && ! empty($newOrderId) ) ? true : false;
                
                if (!empty($newOrderAttrId)) {
                    if ($checkPaymentValue) {
                        $sql = 'UPDATE s_order_attributes SET novalnet_payment_name = ? WHERE orderID=?';
                        Shopware()->Db()->query($sql, [ $novalnetPaymentName,  $newOrderId ]);
                    }
                } else {
                    if ($checkPaymentValue) {
                        $db = Shopware()->Container()->get('models')->getConnection();
                        $db->createQueryBuilder()
                            ->insert('s_order_attributes')
                            ->setValue('orderID', ':orderID')
                            ->setValue('novalnet_payment_name', ':novalnet_payment_name')
                            ->setParameter('orderID', $paymentParams['custom']['inputval2'])
                            ->setParameter('novalnet_payment_name', $sessionData['payment_details']['name'])
                            ->execute();
                    }
                }
                
                $paymentStatusID = $this->helper->getPaymentStatusId($this->eventData) ;

                if (!empty($orderNumber) && !empty($paymentStatusID)) {
                    $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array($orderNumber));
                    Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $paymentStatusID);
                }

                $sOrder = [
                    'customercomment' => str_replace('<br />', PHP_EOL, $note),
                    'temporaryID' => $this->eventTid,
                    'transactionID' => $this->eventTid,
                    'ordernumber' => $orderNumber
                ];

                // update order table
                $this->queryHandler->updateOrdertable($sOrder);

                //Store order details in novalnet table
                $insertData = $this->helper->handleResponseData($this->eventData, $orderNumber);
                $this->queryHandler->insertNovalnetTransaction($insertData);

                //update order number for transaction
                $postCallBackProcess = $this->requestHandler->postCallBackProcess($orderNumber, $this->eventData['transaction']['tid']);
                $postCallBackProcess['transaction']['payment_name'] = $this->eventData['transaction']['payment_name'];
                
                if ($this->eventData['transaction']['payment_type'] != 'ONLINE_TRANSFER_CREDIT' && !empty($this->eventData['transaction']['bank_details'])) {
                    $note = $this->helper->formCustomerComments($postCallBackProcess, $orderData['currency']);
                }
                
                $this->setViews->message = $this->newLine . $note;
                Shopware()->Session()->offsetUnset('serverResponse');
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
     * Check transaction cancellation
     *
     * @return string
     */
    private function transactionCaptureVoid()
    {
        $callbackComments = $paymentStatusID = '';
        $appendComments = true;
        $nnPaymentType = $this->helper->getPaymentType($this->orderReference->getPaymentType());
        $configDatas = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());

        if (in_array($this->nnGatewayStatus(), [NOVALNET_ON_HOLD_STATUS , NOVALNET_PENDING_STATUS])) {
            $upsertData = [
                'id'            => $this->orderReference->getId(),
                'tid'           => $this->orderReference->getTid(),
                'gateway_status' => $this->eventData['transaction']['status']
            ];

            if ($this->eventType === 'TRANSACTION_CAPTURE') {
                if ($nnPaymentType == 'INVOICE') {
                    $this->eventData['transaction']['status'] = $upsertData['gateway_status'] = NOVALNET_PENDING_STATUS;
                }

                if (in_array($this->eventData['transaction']['status'], [NOVALNET_CONFIRMED_STATUS, NOVALNET_PENDING_STATUS])) {
                    if (!empty($configDatas) && !empty($nnPaymentType) &&
                         ( preg_match("/INVOICE/i", $nnPaymentType) || preg_match("/GUARANTEED/i", $nnPaymentType) || preg_match("/INSTALMENT/i", $nnPaymentType) || preg_match("/PREPAYMENT/i", $nnPaymentType)
                         )
                    ) {
                        $appendComments = false;
                       
                        if (empty($this->eventData['transaction']['bank_details'])) {
                            $bankDataKey = ['account_holder', 'bank_name', 'bank_place', 'bic', 'iban'];
                            foreach ($bankDataKey as $key) {
                                if (!empty($configDatas[$key])) {
                                    $this->eventData['transaction']['bank_details'][$key] = $configDatas[$key];
                                }
                            }
                        }
                        
                        $this->eventData['transaction']['payment_name'] = $configDatas['payment_name'];
                        $callbackComments = $this->newLine . $this->helper->formCustomerComments($this->eventData, $this->eventData['transaction']['currency']);
                    }

                    if ($this->eventData['transaction']['status'] == NOVALNET_CONFIRMED_STATUS) {
                        $upsertData['paid_amount'] = $this->orderReference->getAmount();

                        if (preg_match("/INSTALMENT/i", $this->eventData['transaction']['payment_type'])) {
                            $this->eventData['transaction']['amount'] = $this->orderReference->getAmount();
                            $upsertData['configuration_details'] = $this->helper->getInstalmentInformation($this->eventData);
                            $upsertData['configuration_details'] = $this->helper->serializeData($upsertData['configuration_details']);
                        }
                    }
                }
                
                $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_amount_capture', $this->orderData['number']), date('d/m/Y'), date('H:i:s'));
                
                if (preg_match("/INVOICE/i", $nnPaymentType) || preg_match("/GUARANTEED/i", $nnPaymentType) || preg_match("/INSTALMENT/i", $nnPaymentType)) {
                    $context             = $this->novalnetMailObj();
                    $context['sComment'] = $callbackComments;
                    $this->helper->sendNovalnetOrderMail($context, true);
                }
            } else {
                $callbackComments = $this->newLine . $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_transaction_canceled_message', $this->orderData['number']), date('Y-m-d'), date('H:i:s'));
            }
           
            $this->queryHandler->postProcess($callbackComments, $upsertData, $appendComments, null);

            $paymentStatusID = ($this->eventType == 'TRANSACTION_CANCEL') ? '35' : $this->helper->getPaymentStatusId($this->eventData) ;

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
                $callbackComments = $this->newLine .  sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund', $this->orderData['number']), $this->parentTid, $this->formattedAmountRefund, date('d/m/Y H:i:s'));

                if (!empty($this->eventData['transaction']['refund']['tid'])) {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund1', $this->orderData['number']), $this->eventData['transaction']['refund']['tid']);
                }

                $upsertData = [
                    'id'              => $this->orderReference->getId(),
                    'tid'             => $this->orderReference->getTid(),
                    'gateway_status'  => $this->eventData['transaction']['status'],
                    'refunded_amount' => $totalRefundedAmount
                ];

                if (preg_match("/INSTALMENT/i", $this->eventData['transaction']['payment_type'])) {
                    $upsertData['configuration_details'] = $this->updateInstalmentInfo($totalRefundedAmount);
                }

                $this->queryHandler->postProcess($callbackComments, $upsertData, true, null);

                if ($totalRefundedAmount >= $this->orderReference->getAmount()) {
                    Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], 35, false);
                }
            } else {
                $this->setViews->message = 'Refund amount is excess than paid amount for this TID';
            }
        } else {
            $this->setViews->message = 'Already full amount refunded for this TID';
        }
        return $callbackComments;
    }

    /**
     * Handle payment CHARGEBACK/RETURN_DEBIT/REVERSAL process
     *
     * @return string
     */
    private function chargebackProcess()
    {
        $callbackComments = '';

        if ($this->nnGatewayStatus() == NOVALNET_CONFIRMED_STATUS && ! empty($this->eventData['transaction']['amount'])) {
            $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_chargeback_message', $this->orderData['number']), $this->parentTid, $this->formattedAmount, date('d/m/Y'), date('H:i:s'), $this->eventTid);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()], true, null);
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

        if (in_array($this->nnGatewayStatus(), [NOVALNET_CONFIRMED_STATUS , NOVALNET_PENDING_STATUS])) {
            $callbackComments = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_collection_message', $this->orderData['number']), $this->eventData['collection']['reference']);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()], true, null);
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

        if (in_array($this->nnGatewayStatus(), [NOVALNET_CONFIRMED_STATUS , NOVALNET_PENDING_STATUS])) {
            $reminderCount = explode('_', $this->eventType);
            $reminderCount = end($reminderCount);
            $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_payment_reminder_message', $this->orderData['number']), $reminderCount);

            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()], true, null);
        }
        
        return $callbackComments;
    }

    /**
     * Form the instalment data into serialize
     *
     * @param null $totalRefundedAmount
     * @return string
     */
    private function updateInstalmentInfo($totalRefundedAmount = null)
    {
        $configurationDetails = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());
        $instalmentData       = $this->eventData['instalment'];
        
        if (empty($instalmentData['cycles_executed']) && ! empty($totalRefundedAmount)) {
            foreach ($configurationDetails['InstalmentDetails'] as $instalmentDataValue) {
                if ($instalmentDataValue['reference'] == $this->parentTid) {
                    $instalmentData = $instalmentDataValue;
                }
            }
        }
        
        $instalmentData['cycle_amount'] = ! empty($instalmentData['cycle_amount']) ? $instalmentData['cycle_amount'] : $instalmentData['amount'];
        $cycleExecuted = ! empty($instalmentData['cycles_executed']) ? $instalmentData['cycles_executed'] : $instalmentData['cycleExecuted'];
        
        $configurationDetails['InstalmentDetails'][$cycleExecuted ] = [
            'amount'        => $instalmentData['cycle_amount'],
            'cycleDate'     => date('Y-m-d'),
            'cycleExecuted' => $cycleExecuted,
            'dueCycles'     => ! empty($instalmentData['pending_cycles']) ? $instalmentData['pending_cycles'] : $instalmentData['dueCycles'],
            'paidDate'      => date('Y-m-d'),
            'status'        => ($totalRefundedAmount >= $instalmentData['cycle_amount']) ? $this->helper->getLanguageFromSnippet('refundedMsg', $this->orderData['number']) : $this->helper->getLanguageFromSnippet('paidMsg', $this->orderData['number']),
            'reference'     => $this->eventData['transaction']['tid'],
            'refundedAmount'=> ! empty($totalRefundedAmount) ? (int)$totalRefundedAmount : (int)$instalmentData['refundedAmount'] ,
        ];

        return $this->helper->serializeData($configurationDetails);
    }

    /**
     * Handle payment INSTALMENT process
     *
     * @return string
     */
    private function instalmentProcess()
    {
        $comments = '';
        if (preg_match("/INSTALMENT/i", $this->eventData['transaction']['payment_type']) &&
             $this->nnGatewayStatus() == NOVALNET_CONFIRMED_STATUS &&
             empty($this->eventData['instalment']['prepaid'])
        ) {
            $this->eventData['transaction']['payment_name'] = $this->helper->unserializeData($this->orderReference->getConfigurationDetails())['payment_name'];
            $comments = $this->newLine . $this->helper->formCustomerComments($this->eventData, $this->eventData['transaction']['currency']);
        }
        
        $instalmentAmount  = $this->helper->amountInBiggerCurrencyUnit($this->eventData['instalment']['cycle_amount'], $this->eventData['transaction']['currency']);
        
        $comments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_instalment_prepaid_message', $this->orderData['number']), $this->parentTid, $instalmentAmount, $this->eventTid).$this->newLine;

        $upsertData['id']                   = $this->orderReference->getId();
        $upsertData['tid']                  = $this->orderReference->getTid();
        $upsertData['configuration_details']    = $this->updateInstalmentInfo();

        $this->queryHandler->postProcess($comments, $upsertData, false, null);
        $context             = $this->novalnetMailObj();
        $context['sComment'] = $comments;
        $this->helper->sendNovalnetOrderMail($context, true);
        return $comments;
    }

    /**
     * Handle payment INSTALMENT_CANCEL process
     *
     * @return string
     */
    private function instalmentCancelProcess()
    {
        if (in_array($this->nnGatewayStatus(), [NOVALNET_CONFIRMED_STATUS , NOVALNET_PENDING_STATUS])) {
            $cancel_type = 'ALL_CYCLES';
            
            if (!empty($this->eventData['instalment']['cancel_type'])) {
                $cancel_type = $this->eventData['instalment']['cancel_type'];
            }
            $configurationDetails = $this->helper->unserializeData($this->orderReference->getConfigurationDetails());
            $configurationDetails = $this->helper->updateConfigurationDetails($configurationDetails, $cancel_type, null);
            
            $upsertData = [
                'id'                    => $this->orderReference->getId(),
                'tid'                   => $this->orderReference->getTid(),
                'configuration_details' => $configurationDetails['instalmentConfigData'],
                'refunded_amount'       => $configurationDetails['refundTotalAmount'],
                'gateway_status'        => $this->eventData['transaction']['status'],
            ];
            
            $callbackComments =  $this->newLine . $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_all', $this->orderData['number']), $this->orderReference->getTid(), date('d/m/Y H:i:s'), $this->formattedAmountRefund) ;

            if ($cancel_type == 'REMAINING_CYCLES') {
                $callbackComments = $this->newLine . $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_remaining', $this->orderData['number']), $this->orderReference->getTid(), date('d/m/Y'), date('H:i:s'));
            }
            
            Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], 35, false);
            $this->queryHandler->postProcess($callbackComments, $upsertData, true, null);
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
                $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message', $this->orderData['number']), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->parentTid);
            } else {
                $callbackComments .= $this->newLine. sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message', $this->orderData['number']), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->eventTid);
            }

            $this->queryHandler->postProcess($callbackComments, $upsertData, true, null);

            if ($paidAmount >= $amountToBePaid || $this->eventData['transaction']['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {
                Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], $this->configDetails['novalnet_after_payment_status'], false);
            }
        } elseif (in_array($this->eventData['transaction']['payment_type'], [ 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD', 'CREDITCARD_REPRESENTMENT', 'BANK_TRANSFER_BY_END_CUSTOMER', 'APPLEPAY_REPRESENTMENT', 'DEBT_COLLECTION_DE', 'CREDIT_ENTRY_DE'])) {
            $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_credit_message', $this->orderData['number']), $this->parentTid, $this->formattedAmount, date('d-m-Y H:i:s'), $this->eventTid);
            $this->queryHandler->postProcess($callbackComments, ['tid' => $this->orderReference->getTid()], true, null);
        } else {
            $this->setViews->message = 'Novalnet webhook received. Order Already Paid';
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
        $nnConfigData =  $this->helper->unserializeData($this->orderReference->getConfigurationDetails());

        $upsertData = [
            'id' => $this->orderReference->getId(),
            'tid' => $this->orderReference->getTid(),
            'gateway_status' => $this->eventData['transaction']['status'],
        ];
        
        $upsertData['configuration_details']['payment_name'] = $nnConfigData['payment_name'];

        $updateSupportedStatus = [NOVALNET_PENDING_STATUS, NOVALNET_ON_HOLD_STATUS, NOVALNET_CONFIRMED_STATUS, NOVALNET_DEACTIVATED_STATUS];

        if (in_array($this->eventData['transaction']['status'], $updateSupportedStatus)) {
            if (in_array($this->eventData['transaction']['update_type'], ['DUE_DATE', 'AMOUNT_DUE_DATE'])) {
                $upsertData['amount'] = $this->eventData['transaction']['amount'];
                $upsertData['configuration_details'] = array_merge($nnConfigData, ['due_date' => $this->eventData['transaction']['due_date'] ]);

                if (! empty($upsertData['configuration_details'])) {
                    $upsertData['configuration_details'] = $this->helper->serializeData($upsertData['configuration_details']);
                }

                $dueDate = date('d/m/Y', strtotime($this->eventData['transaction']['due_date']));

                if ($this->eventData['transaction']['payment_type'] === 'CASHPAYMENT') {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_cashpayment_message', $this->orderData['number']), $this->formattedAmount, $dueDate);
                } else {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_duedate_update_message', $this->orderData['number']), $this->formattedAmount, $dueDate);
                }
            } elseif ($this->eventData['transaction']['update_type'] === 'STATUS') {
                $this->eventData['transaction']['payment_name'] = $nnConfigData['payment_name'];
                
                if (( preg_match("/INSTALMENT/i", $this->eventData['transaction']['payment_type']) ||
                      preg_match("/GUARANTEED/i", $this->eventData['transaction']['payment_type']) ||
                      preg_match("/INVOICE/i", $this->eventData['transaction']['payment_type'])
                    ) && $this->eventData['transaction']['status'] !== NOVALNET_DEACTIVATED_STATUS
                ) {
                    $appendComments = false;
                    if (!empty($nnConfigData) && empty($this->eventData['transaction']['bank_details'])) {
                        $bankDataKey = ['account_holder', 'bank_name', 'bank_place', 'bic', 'iban'];
                        foreach ($bankDataKey as $key) {
                            if (!empty($nnConfigData[$key])) {
                                $this->eventData['transaction']['bank_details'][$key] = $nnConfigData[$key];
                            }
                        }
                    }
                    
                    $callbackComments .= $this->newLine . $this->helper->formCustomerComments($this->eventData, $this->eventData['transaction']['currency']);
                }

                if ($this->eventData['transaction']['status'] === NOVALNET_DEACTIVATED_STATUS) {
                    $callbackComments .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_transaction_canceled_message', $this->orderData['number']), date('d/m/Y H:i:s'));
                } elseif (in_array($this->nnGatewayStatus(), [NOVALNET_PENDING_STATUS, NOVALNET_ON_HOLD_STATUS ])) {
                    $upsertData['amount'] = $this->eventData['transaction']['amount'];

                    if ($this->eventData['transaction']['status'] === NOVALNET_ON_HOLD_STATUS) {
                        $callbackComments .= $this->newLine .$this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_update_onhold_message', $this->orderData['number']), $this->eventTid, date('d/m/Y'), date('H:i:s'));
                    // Payment not yet completed, set transaction status to "AUTHORIZE"
                    }
                    
                    if ($this->eventData['transaction']['status'] === NOVALNET_CONFIRMED_STATUS) {
                        $callbackComments .= $this->newLine. $this->newLine. sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_redirect_update_message', $this->orderData['number']), $this->eventTid, date('d/m/Y H:i:s'));
                        
                        if (preg_match("/INSTALMENT/i", $this->eventData['transaction']['payment_type'])) {
                            $this->eventData['transaction']['amount'] = $this->orderReference->getAmount();
                            $instalmentInfo = $this->helper->getInstalmentInformation($this->eventData);
                            $upsertData['configuration_details'] = $this->helper->serializeData(array_merge($upsertData['configuration_details'], $instalmentInfo));
                        }

                        $upsertData['paid_amount'] = $this->eventData['transaction']['amount'];
                    }
                }
            } else {
                if (!empty($this->eventData['transaction']['amount'])) {
                    $upsertData['amount'] = $this->eventData['transaction']['amount'];
                }
                
                $callbackComments .= $this->newLine . $this->newLine . sprintf($this->helper->getLanguageFromSnippet('novalnet_callback_update_message', $this->orderData['number']), $this->eventTid, $this->formattedAmount, date('d/m/Y H:i:s'));
            }
        }
                
        $this->queryHandler->postProcess($callbackComments, $upsertData, $appendComments, null);

        $statusId = $this->helper->getPaymentStatusId($this->eventData) ;

        if (!empty($statusId)) {
            Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderData['id'], $statusId, false);
        }

        $nnPaymentType = $this->helper->getPaymentType($this->orderReference->getPaymentType());

        if (( preg_match("/INVOICE/i", $nnPaymentType) || preg_match("/GUARANTEED/i", $nnPaymentType) || preg_match("/INSTALMENT/i", $nnPaymentType) ) &&
             in_array($this->eventData['transaction']['status'], [NOVALNET_PENDING_STATUS, NOVALNET_ON_HOLD_STATUS, NOVALNET_CONFIRMED_STATUS])
        ) {
            $context             = $this->novalnetMailObj();
            $context['sComment'] = $callbackComments;
            $this->helper->sendNovalnetOrderMail($context, true);
        }
                
        return $callbackComments;
    }

    /**
     * Get mail object from shop database
     *
     * @return array
     */
    private function novalnetMailObj()
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

    /**
     * Function to get order gateway status
     *
     * @return string
     */
    private function nnGatewayStatus()
    {
        $getGatewayStatus = $this->orderReference->getGatewayStatus();
        return ! empty($getGatewayStatus) ? $this->helper->getStatus($getGatewayStatus, $this->orderReference, $this->eventData['transaction']['payment_type']) : null ;
    }
}
