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
class Shopware_Plugins_Frontend_NovalPayment_lib_classes_PaymentNotification
{
    private $processTestMode;
    private $novalnetLang;
    private $aryCaptureParams;
    private $nHelper;
    private $orderDetails;
    private $configDetails;
    private $nnSendMail;
    private $nnToMail;
    private $setViews;

    /** @Array Type of payment available - Level : 0 */
    private $aryPayments = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE', 'PAYPAL', 'ONLINE_TRANSFER', 'IDEAL', 'EPS', 'GIROPAY', 'PRZELEWY24', 'CASHPAYMENT');
    /** @Array Type of Chargebacks available - Level : 1 */
    private $aryChargebacks = array('RETURN_DEBIT_SEPA', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'REVERSAL', 'CASHPAYMENT_REFUND','GUARANTEED_INVOICE_BOOKBACK' , 'GUARANTEED_SEPA_BOOKBACK');
    /** @Array Type of Credit entry payment and Collections available - Level : 2 */
    private $aryCollection = array('INVOICE_CREDIT', 'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'DEBT_COLLECTION_CREDITCARD', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE');
    private $aPaymentTypes = array('novalnetcc' => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD'), 'novalnetsepa' => array('DIRECT_DEBIT_SEPA', 'GUARANTEED_SEPA_BOOKBACK', 'RETURN_DEBIT_SEPA', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA', 'REFUND_BY_BANK_TRANSFER_EU', 'GUARANTEED_DIRECT_DEBIT_SEPA'), 'novalnetideal' => array('IDEAL', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE'), 'novalnetinstant' => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE'), 'novalnetpaypal' => array('PAYPAL', 'PAYPAL_BOOKBACK'), 'novalnetprepayment' => array('INVOICE_START', 'INVOICE_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU'), 'novalnetcashpayment' => array('CASHPAYMENT', 'CASHPAYMENT_CREDIT', 'CASHPAYMENT_REFUND'), 'novalnetinvoice' => array('INVOICE_START', 'INVOICE_CREDIT', 'GUARANTEED_INVOICE', 'REFUND_BY_BANK_TRANSFER_EU', 'GUARANTEED_INVOICE_BOOKBACK','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE'), 'novalneteps' => array('EPS', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'REVERSAL','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE'), 'novalnetgiropay' => array('GIROPAY', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'REVERSAL','CREDIT_ENTRY_DE','DEBT_COLLECTION_DE'), 'novalnetprzelewy24' => array('PRZELEWY24', 'PRZELEWY24_REFUND'));

    /**
     * Constructor for initiate the callback process
     *
     * @param aryCaptureParams
     * @return null
     */
    public function __construct($aryCaptureParams, $view)
    {
        $this->aryCaptureParams = $aryCaptureParams;
        $this->setViews			= $view;
        $this->nHelper          = new Shopware_Plugins_Frontend_NovalPayment_lib_classes_NovalnetHelper();
        $this->startProcess();
    }

    /**
     * Processes the callback script
     *
     * @param null
     * @return null
     */
    public function startProcess()
    {
        $nnCaptureParams       = $this->getCaptureParams($this->aryCaptureParams);
        $this->novalnetLang    = Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper::novalnetGetLanguage(Shopware()->Container()->get('locale'));
        $sessionId             = $nnCaptureParams['inputval5'] ? $nnCaptureParams['inputval5'] : $nnCaptureParams['nn_sid'];
        $this->orderDetails    = Shopware()->Db()->fetchRow('SELECT * FROM s_order WHERE ordernumber = ? OR transactionID = ?', array($nnCaptureParams['order_no'], $nnCaptureParams['shop_tid']));
        if(empty($this->orderDetails))
        {
			$this->orderDetails    = Shopware()->Db()->fetchRow('SELECT * FROM s_order WHERE temporaryID = ?', array($sessionId));
		}
		
        $referenceId           = !empty($nnCaptureParams['order_no']) ? $nnCaptureParams['order_no'] : $nnCaptureParams['shop_tid'];
        $subShopId             = $this->nHelper->getShopId($referenceId);                
        $this->configDetails   = $this->nHelper->getNovalConfigDetails(Shopware()->Plugins()->Frontend()->NovalPayment()->Config());
        $this->processTestMode = $this->configDetails['novalnetcallback_test_mode'];
        $this->nnSendMail      = $this->configDetails['novalnet_callback_mail_send'];
        $this->nnToMail        = $this->configDetails['novalnet_callback_mail_send_to'];
        if (!$this->validateCaptureParams($this->aryCaptureParams)) {
            return false;
        }
        
        $nntransHistory       = $this->getOrderReference($nnCaptureParams); // Order reference of given callback request using novalnet_transaction_detail table
        if (empty($nntransHistory)) {
            return false;
        }
        $languages       = ($nntransHistory['lang']) ? $nntransHistory['lang'] : 'de';
        $orderPayment         = $nntransHistory['payment_type']; // Executed payment type for original transaction
        $afterPaymentStatus   = $this->configDetails[$orderPayment . '_after_paymenstatus'];
        $currentTotalAmount   = $nntransHistory['order_paid_amount'] + $nntransHistory['callback_amount'];
        // Log callback process (for all types of payments default)
        $callbacklogParams    = array(
            'payment_type' => $this->aryCaptureParams['payment_type'],
            'status' => $this->aryCaptureParams['status'],
            'callback_tid' => $this->aryCaptureParams['tid'],
            'original_tid' => $nntransHistory['tid'],
            'amount' => $this->aryCaptureParams['amount'],
            'currency' => $this->aryCaptureParams['currency'],
            'product_id' => $this->aryCaptureParams['product_id'],
            'order_no' => $nntransHistory['order_no'],
            'subs_billing' => $this->aryCaptureParams['subs_billing']
        );
        if ($this->getPaymentTypeLevel() == 'collections_payments') {
            // Credit entry payment and Collections available
            if ($nntransHistory['order_paid_amount'] < $nntransHistory['order_total_amount'] && in_array($nnCaptureParams['payment_type'], array('INVOICE_CREDIT','CASHPAYMENT_CREDIT','ONLINE_TRANSFER_CREDIT'))) {
                if ($this->aryCaptureParams['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {
                    $this->nHelper->updateStockReduce($nnCaptureParams);
                }
                // Log callback process
                $this->nHelper->insertCallbackTable($callbacklogParams);
                $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status'],'status' => $nnCaptureParams['status'],'amount' => $nnCaptureParams['amount']), 'tid = "' . $nnCaptureParams['shop_tid'] . '"');
                $id                 = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  transactionID = ?', array($nnCaptureParams['shop_tid']));
                $paidAmount = Shopware()->Db()->fetchOne('SELECT sum(amount) FROM s_novalnet_callback_history WHERE org_tid = ?', array($nnCaptureParams['shop_tid']));
                $status             = $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'] ;
                if ($paidAmount >= $nntransHistory['order_total_amount']) {
                    Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $status, false);
                }
            } elseif ($nntransHistory['order_paid_amount'] >= $nntransHistory['order_total_amount'] && in_array($this->aryCaptureParams['payment_type'], array('INVOICE_CREDIT','CASHPAYMENT_CREDIT','ONLINE_TRANSFER_CREDIT'))) {
                $this->debugMessage('Novalnet callback received. Callback Script executed already. Refer Order :' . $nntransHistory['order_no'], $nnCaptureParams['order_no']);
                return false;
            }
            $browserComments = $callbackComments = '<br />'. sprintf(PHP_EOL . $this->novalnetLang['novalnet_callback_executed_with_child_tid'], $this->aryCaptureParams['tid_payment'], sprintf('%.2f', $nntransHistory['callback_amount'] / 100) . ' ' . $this->aryCaptureParams['currency'], date('d-m-Y H:i:s'), $this->aryCaptureParams['tid']);
              
            if (!in_array($nnCaptureParams['payment_type'], array('INVOICE_CREDIT','CASHPAYMENT_CREDIT','ONLINE_TRANSFER_CREDIT'))) {
                // Log callback process
                $this->nHelper->insertCallbackTable($callbacklogParams);
            }
            $callbackComments = str_replace('<br />', PHP_EOL, $callbackComments);
            $this->updateCommentsDB(array($callbackComments,$nnCaptureParams['shop_tid']));
            // Send notification mail to Merchant
            $this->sendNotifyMail(array(
                    'comments' => $callbackComments,
                    'order_no' => $nntransHistory['order_no'],
                    'browserComments' => $browserComments
                ));
            return false;
        } elseif ($this->getPaymentTypeLevel() == 'charge_back_payments' && in_array($nntransHistory['gateway_status'], array(100, 99, 98, 75, 86, 85, 90, 91))) {
             //Level 1 payments - Type of Chargebacks
            // DO THE STEPS TO UPDATE THE STATUS OF THE ORDER OR THE USER AND NOTE THAT THE
            // PAYMENT WAS RECLAIMED FROM USER
            
            if (in_array($nnCaptureParams['payment_type'], array('PAYPAL_BOOKBACK','CREDITCARD_BOOKBACK','PRZELEWY24_REFUND','GUARANTEED_INVOICE_BOOKBACK','GUARANTEED_SEPA_BOOKBACK','CASHPAYMENT_REFUND','REFUND_BY_BANK_TRANSFER_EU'))) {
                $callbackComments   = sprintf('<br />' . $this->novalnetLang['novalnet_callback_bookback_executed'], $nnCaptureParams['tid_payment'], sprintf('%.2f', $nntransHistory['callback_amount'] / 100) . ' ' . $this->aryCaptureParams['currency'], date('d-m-Y H:i:s'), $nnCaptureParams['tid']);
            } else {
                $callbackComments   = sprintf('<br />' . $this->novalnetLang['novalnet_callback_chargeback_executed'], $nnCaptureParams['tid_payment'], sprintf('%.2f', $nntransHistory['callback_amount'] / 100) . ' ' . $this->aryCaptureParams['currency'], date('d-m-Y H:i:s'), $nnCaptureParams['tid']);
            }
            $callbackComments = str_replace('<br />', PHP_EOL, $callbackComments);
            $this->nHelper->updateCallbackComments(array(
                'order_no' => $nntransHistory['order_no'],
                'comments' => $callbackComments,
                'old_comments' => $nntransHistory['customercomment'],
                'orders_status_id' => '',
                'tid' => $nnCaptureParams['shop_tid'],
                'id' => $nntransHistory['id']
            ));
            // Send notification mail to Merchant
            $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
        } elseif ($this->getPaymentTypeLevel() == 'available_payments') { //level 0 payments - Type of payment
            if ($nnCaptureParams['payment_type'] == 'PAYPAL' && in_array($nntransHistory['gateway_status'], array('85','90'))) {
                if (($nnCaptureParams['tid_status'] == 100) && empty($nntransHistory['order_paid_amount'])) {
                    // Full Payment paid
                    // Update order status due to full payment
                    $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_callback_executed'], date('d-m-Y H:i:s'));
                    // Update callback comments in order status history table
                    $this->nHelper->updateCallbackComments(array(
                        'order_no' => $nntransHistory['order_no'],
                        'comments' => $callbackComments,
                        'old_comments' => $nntransHistory['customercomment'],
                        'orders_status_id' => $afterPaymentStatus,
                        'tid' => $nnCaptureParams['shop_tid'],
                        'id' => $nntransHistory['id']
                    ));
                    $sOrder['cleared'] = $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                    $this->nHelper->novalnetDbUpdate('s_order', $sOrder, 'ordernumber="' . $nnCaptureParams['order_no'] . '"');
                    $id                                                    = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array($nnCaptureParams['order_no']));
                    $s_order_attributes['novalnet_payment_tid']            = $nnCaptureParams['tid'];
                    $s_order_attributes['novalnet_payment_gateway_status'] = $nnCaptureParams['tid_status'];
                    $s_order_attributes['novalnet_payment_paid_amount']    = $currentTotalAmount;
                    $s_order_attributes['novalnet_payment_order_amount']   = $currentTotalAmount;
                    $s_order_attributes['novalnet_payment_current_amount'] = $currentTotalAmount;
                    $this->nHelper->novalnetDbUpdate('s_order_attributes', $s_order_attributes, 'orderID="' . $id . '"');
                    $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status'],'amount' => $nnCaptureParams['amount']), 'order_no = "' . $nntransHistory['order_no'] . '"');
                    // Logcallback process
                    $this->nHelper->insertCallbackTable($callbacklogParams);
                    // Send notification mail to Merchant
                    $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
                } else {
                    $this->debugMessage('Novalnet callback received. Order already paid.', $nnCaptureParams['order_no']);
                }
            } elseif ($nnCaptureParams['payment_type'] == 'PRZELEWY24' && $nntransHistory['gateway_status'] == '86') {
                if (in_array($nnCaptureParams['tid_status'], array('100','86'))) {
					
                    if (empty($nntransHistory['order_paid_amount'])) {
                        // Full Payment paid
                        // Update order status due to full payment
                        $callbackComments  = sprintf($this->novalnetLang['novalnet_callback_executed'], date('d-m-Y H:i:s'));                       
                        $sOrder['cleared'] = $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                        $this->updateCommentsDB(array($callbackComments,$nnCaptureParams['shop_tid']));
                        $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array(
                            'gateway_status' => $nnCaptureParams['tid_status'],
                            'amount' => $nnCaptureParams['amount']
                        ), 'order_no = "' . $nntransHistory['order_no'] . '"');
                        $id                        = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  transactionID = ?', array($nnCaptureParams['shop_tid']));
                        $status                    = ($nnCaptureParams['status'] == '100' && $nnCaptureParams['tid_status'] == '86') ? $this->configDetails[$nntransHistory['payment_type'] . '_before_paymenstatus'] : $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                        Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $status, false);
                        // Logcallback process
                        $this->nHelper->insertCallbackTable($callbacklogParams);
                        // Send notification mail to Merchant
                        $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
                    } else {
                        $this->debugMessage("Novalnet callback received. Order already paid.", $nnCaptureParams['order_no']);
                    }
                } else {
                    $message          = (($nnCaptureParams['status_message']) ? $nnCaptureParams['status_message'] : (($nnCaptureParams['status_text']) ? $nnCaptureParams['status_text'] : (($nnCaptureParams['status_desc']) ? $nnCaptureParams['status_desc'] : 'Payment was not successful. An error occurred')));
                    $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_callback_przelewy24_cancel'], $message);
                    $this->nHelper->updateStockRestore($nnCaptureParams);
                    $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status']), 'order_no = "' . $nntransHistory['order_no'] . '"');
                    // Logcallback process
                    $this->nHelper->insertCallbackTable($callbacklogParams);

                    // Send notification mail to Merchant
                    $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
                }
            } elseif (in_array($nnCaptureParams['payment_type'], array('INVOICE_START','GUARANTEED_INVOICE','DIRECT_DEBIT_SEPA','GUARANTEED_DIRECT_DEBIT_SEPA')) && in_array($nntransHistory['gateway_status'], array(75,91,99)) && in_array($nnCaptureParams['tid_status'], array(91,99,100)) && $nnCaptureParams['status'] == '100') {
                $id                        = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  transactionID = ?', array($nnCaptureParams['shop_tid']));
                $orderNumber               = ($nnCaptureParams['order_no']) ? $nnCaptureParams['order_no'] : $this->orderDetails['ordernumber'];
                $nnCaptureParams['amount'] = sprintf('%.2f', $nnCaptureParams['amount'] / 100);
                $comments = '';
                if (in_array($nnCaptureParams['payment_type'], array('INVOICE_START','GUARANTEED_INVOICE')) || ($nnCaptureParams['payment_type'] == 'GUARANTEED_DIRECT_DEBIT_SEPA' && $nnCaptureParams['tid_status'] == '100')) {
                    $comments                  = $this->nHelper->prepareComments($nntransHistory['payment_type'], $nnCaptureParams, $nnCaptureParams['currency'], $nnCaptureParams['test_mode'], $orderNumber, $nnCaptureParams['product_id'], $languages, $this->configDetails);
                }
                $status                    = ($nntransHistory['gateway_status'] == 91 && $nnCaptureParams['status'] == '100' && $nnCaptureParams['tid_status'] == '100' && $nnCaptureParams['payment_type'] == 'INVOICE_START') ? $this->configDetails[$nntransHistory['payment_type'] . '_before_paymenstatus'] : $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                if ($nnCaptureParams['due_date'] && $nnCaptureParams['tid_status'] == '100') {
                    $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_callback_executed_trans_confirm_due_date'], $nnCaptureParams['tid'], $nnCaptureParams['due_date']);
                } elseif (in_array($nntransHistory['gateway_status'], array('75', '91', '99')) && $nnCaptureParams['tid_status'] == '100') {
                    $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_guarantee_confirmation'], date('d-m-Y'), date('H:i:s'));
                } elseif (in_array($nnCaptureParams['tid_status'], array('99','91')) && $nntransHistory['gateway_status'] == 75) {
                    $status = (in_array($nnCaptureParams['payment_type'], array('GUARANTEED_INVOICE','GUARANTEED_DIRECT_DEBIT_SEPA'))) ? $this->configDetails['novalnet_onhold_order_complete'] : $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                    $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_guarantee_on_hold'], $nnCaptureParams['shop_tid'], date('d-m-Y'), date('H:i:s'));
                }
                if ($nnCaptureParams['due_date']) {
                    $this->nHelper->novalnetDbUpdate('s_novalnet_preinvoice_transaction_detail', array('due_date' => $nnCaptureParams['due_date']), 'tid=' . $nnCaptureParams['tid']);
                }
                $novalnet_comments = str_replace('<br />', PHP_EOL, $comments . $callbackComments);
                if ($nnCaptureParams['tid_status'] == 100 && $nnCaptureParams['payment_type'] != 'DIRECT_DEBIT_SEPA') {
                    $this->nHelper->novalnetDbUpdate('s_order', array('customercomment' => $novalnet_comments), 'transactionID=' . $nnCaptureParams['tid']);
                } else {
                    $this->updateCommentsDB(array($novalnet_comments,$nnCaptureParams['tid']));
                }
                
                $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status']), 'tid=' . $nnCaptureParams['tid']);
                $confirmComment = ((in_array($nntransHistory['gateway_status'], array(75, 91, 99)) && in_array($nnCaptureParams['tid_status'], array(91, 99, 100)) && in_array($nnCaptureParams['payment_type'], array('GUARANTEED_INVOICE','GUARANTEED_DIRECT_DEBIT_SEPA')))) ? $callbackComments . '<br> '. $comments : $callbackComments ;
                Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $status, false);
                if (in_array($nnCaptureParams['payment_type'], array('GUARANTEED_INVOICE','GUARANTEED_DIRECT_DEBIT_SEPA'))) {
                    // Send notification mail to Merchant
                    $context             = $this->novalnetMail($nnCaptureParams);
                    $context['sComment'] = $confirmComment;
                    $this->nHelper->sendNovalnetOrderMail($context, true);
                    $this->debugMessage($confirmComment);
                } else {
                    // Send notification mail to Merchant
                    $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
                }
            } elseif ($nnCaptureParams['payment_type'] == 'CREDITCARD' && $nntransHistory['gateway_status'] == '98' && $nnCaptureParams['tid_status'] == '100') {
                $id                        = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  transactionID = ?', array($nnCaptureParams['shop_tid']));
                $callbackComments = sprintf('<br />' . $this->novalnetLang['novalnet_guarantee_confirmation'], date('d-m-Y'), date('H:i:s'));
                $status                    = $this->configDetails[$nntransHistory['payment_type'] . '_after_paymenstatus'];
                $callbackComments = str_replace('<br />', PHP_EOL, $callbackComments);
                $this->updateCommentsDB(array($callbackComments,$nnCaptureParams['shop_tid']));
                $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status']), 'tid=' . $nnCaptureParams['tid']);
                Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $status, false);
                // Send notification mail to Merchant
                $this->sendNotifyMail(array('comments' => $callbackComments,'order_no' => $nntransHistory['order_no']));
            } else {
                $this->debugMessage('Novalnet callback received. Payment type ( ' . $nnCaptureParams['payment_type'] . ' ) is
                    not applicable for this process!', $nnCaptureParams['order_no']);
            }
        }
    }

    /**
     * Send notify email after callback process
     *
     * @param array $datas
     * @param boolean $missingTransactionNotify
     * @return null
     */
    public function sendNotifyMail($datas, $missingTransactionNotify = false)
    {
        $toEmail = '';
        $mail    = new Enlight_Components_Mail();
        $mailSubject = 'Novalnet Callback script notification - Order No : ' . $datas['order_no'] . 'in the ' . Shopware()->Config()->get('shopName');
        if ($missingTransactionNotify) { //This is only for missing transaction notification
            $toEmail     = 'technic@novalnet.de';
            $mailSubject = 'Critical error on shop system ' . Shopware()->Config()->get('shopName') . ': order not found for TID: ' . $datas['tid'];
            $mail->addTo($toEmail);
        } elseif ($this->nnSendMail && $toEmail = $this->splitValidateEmail($this->nnToMail)) {
            foreach ($toEmail as $value) {
                $mail->addTo($value);
            }
        } else {
            $novalnetComments = ($datas['browserComments']) ? $datas['browserComments'] : $datas['comments'];
            if ($datas['order_number']) {
                $this->debugMessage($novalnetComments, $datas['order_number']);
            } else {
                $this->debugMessage($novalnetComments, $this->aryCaptureParams['order_no']);
            }
            return;
        }
        $mail->setSubject($mailSubject);
        $mail->setBodyHtml(utf8_decode($datas['comments']));
        try {
            $mail->send();
            $comments = 'Mail Sent Successfully';
        } catch (Exception $e) {
            $comments = 'Error in sending the Mail <br>' . $e->getMessage();
        }
        $comments .= ($datas['browserComments']) ? $datas['browserComments'] : $datas['comments'];
        if ($datas['order_number']) {
            $this->debugMessage($comments, $datas['order_number']);
        } else {
            $this->debugMessage($comments, $this->aryCaptureParams['order_no']);
        }
    }
	
	/**
     * Send notify email after callback process
     *
     * @param string $mailSubject
     * @param string $mailBody
     * @return null
     */
    public function sendMerchantNotifyMail($mailSubject, $mailBody)
    {
        $toEmail = '';
        $mail    = new Enlight_Components_Mail();
        
        $template = clone Shopware()->Template();
		$shop = Shopware()->Shop();
		$inheritance = Shopware()->Container()->get('theme_inheritance');

		$config = $inheritance->buildConfig(
			$shop->getTemplate(),
			$shop,
			false
		);
		
		$template->assign('theme', $config);
        
		$subject = $template->fetch('string:' . $mailSubject, $template);
		$body = $template->fetch('string:' . $mailBody, $template);
			
        if ($this->nnSendMail && $toEmail = $this->splitValidateEmail($this->nnToMail)) {
            foreach ($toEmail as $value) {
                $mail->addTo($value);
            }
        } else {
			$mail->addTo(Shopware()->Config()->get('Mail'));
		}
        
        $mail->setSubject(utf8_decode($subject));
        $mail->setBodyHtml(utf8_decode($body));
        try {
            $mail->send();
            $comments = 'Mail Sent Successfully';
        } catch (Exception $e) {
            $comments = 'Error in sending the Mail <br>' . $e->getMessage();
        }
        
        $this->debugMessage($comments);
    }
    
    /**
     * split the email
     *
     * @param string $email
     * @return array
     */
    public function splitValidateEmail($email)
    {
        $email = explode(',', $email);
        foreach ($email as $value) {
            if ($value == '' || !$this->nHelper->validateEmail($value)) {
                return false;
            }
        }
        return $email;
    }

    /**
     * Validate the required params to process callback
     *
     * @param array $request
     * @return null
     */
    public function validateCaptureParams($request)
    {
        // Validate Authenticated IP
        $realHostIp = gethostbyname('pay-nn.de');
        if (empty($realHostIp)) {
            $this->debugMessage('Novalnet HOST IP missing', true);
            return false;
        }
        $callerIp = $this->nHelper->getIp();
        if ($callerIp != $realHostIp && !$this->processTestMode) {
            $this->debugMessage('Unauthorised access from the IP [' . $callerIp . ']');
            return false;
        }
        if (!array_filter($request)) {
            $this->debugMessage('Novalnet callback received. No params passed over!');
            return false;
        }
        $hParamsRequired = array('vendor_id','tid','payment_type','status','tid_status');
        
        if (in_array($request['payment_type'], array_merge($this->aryChargebacks, $this->aryCollection))) {
            array_push($hParamsRequired, 'tid_payment');
        }
        foreach ($hParamsRequired as $v) {
            if ($request[$v] == '') {
                $error = 'Novalnet callback received. Required param (' . $v . ') missing! <br>';
                break;
            } elseif (in_array($v, array('tid','signup_tid','tid_payment')) && !preg_match('/^[0-9]{17}$/', $request[$v])) {
                $error = 'Novalnet callback received. TID [' . $request[$v] . '] is not valid.';
            }
        }
        if ($error) {
            $this->debugMessage($error, '');
            return false;
        }
        return true;
    }

    /**
     * Perform parameter validation process
     * Set empty value if not exist in aryCapture
     *
     * @param $aryCapture
     * @return array
     */
    public function getCaptureParams($aryCapture = array())
    {
        $aryCapture['shop_tid'] = $aryCapture['tid'];
        if (in_array($aryCapture['payment_type'], array_merge($this->aryChargebacks, $this->aryCollection))) { // Collection Payments or Chargeback Payments
            $aryCapture['shop_tid'] = $aryCapture['tid_payment'];
        }
        return $aryCapture;
    }

    /**
     * Get given payment_type level for process
     *
     * @param null
     * @return string|null
     */
    public function getPaymentTypeLevel()
    {
        if (in_array($this->aryCaptureParams['payment_type'], $this->aryPayments)) {
            return 'available_payments';
        } elseif (in_array($this->aryCaptureParams['payment_type'], $this->aryChargebacks)) {
            return 'charge_back_payments';
        } elseif (in_array($this->aryCaptureParams['payment_type'], $this->aryCollection)) {
            return 'collections_payments';
        }
    }

    /**
     * Get failure transactions for redirection payments.
     *
     * @param array $nnCaptureParams
     * @return null
     */
    public function communicationFailure($nnCaptureParams)
    {
        $nnSid          = $nnCaptureParams['inputval5'] ? $nnCaptureParams['inputval5'] : $nnCaptureParams['nn_sid'];
        $orderId        = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE temporaryID = ?', array($nnSid));
        $orderData      = Shopware()->Modules()->Order()->getOrderById($orderId);
        $lang 			= (substr(Shopware()->Container()->get('locale'), 0, 2) == 'en') ? 'en' : 'de';
        Shopware()->Session()->offsetSet('sUserId', $orderData['userID']);
        Shopware()->Session()->offsetSet('sessionId', $nnSid);
        $userData		= Shopware()->System()->sMODULES['sAdmin']->sGetUserData();
		$paymentData	= Shopware()->Modules()->Admin()->sGetPaymentMeanById($orderData['paymentID'], $userData);
		$shop			= Shopware()->Shop();
		
        if(empty($orderData['ordernumber']) && !empty($orderData))
        {
			$userData['additional']['charge_vat'] = true;
			if($this->nHelper->isTaxFreeDelivery($userData))
			{
				$userData['additional']['charge_vat'] = false;
			}
			$basket = $this->nHelper->getBasket();
			
            if($this->nHelper->checkArticle($orderId))
            {
                # Insert order data in releated tables
                try {
                    $newLine = '<br />';
                    $note = $this->novalnetLang['novalnet_payment_transdetails_info'] . $newLine;
                    $note .= $this->novalnetLang['payment_name_' . $paymentData['name']] . $newLine;
                    $note .= $this->novalnetLang['novalnet_tid_label'] . ':  ' . $nnCaptureParams['tid'] . $newLine; 
                    $note .= ($nnCaptureParams['test_mode'] == '1') ? $this->novalnetLang['novalnet_message_test_order'] : '';
                    $orderNumber = '';
                    
                    if (empty($orderNumber)) {
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
						$order->bookingId = $nnCaptureParams['tid'];
						$order->dispatchId = Shopware()->Session()->get('sDispatch');
						$order->sNet = empty($userData['additional']['charge_vat']);
						$order->uniqueID = $nnCaptureParams['tid'];
						$order->deviceType = 'desktop';
						$orderNumber = $order->sSaveOrder();
						
						if(!empty($orderNumber) && !empty($this->configDetails[$paymentData['name'] . '_after_paymenstatus']))
						{
							$id	= Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  ordernumber = ?', array($orderNumber));
							Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $this->configDetails[$paymentData['name'] . '_after_paymenstatus']);
						}
						
						// Update transaction details
						$sOrder['customercomment'] = str_replace('<br />', PHP_EOL, $note);
						$sOrder['temporaryID']     = $nnCaptureParams['tid'];
						
						$this->nHelper->novalnetDbUpdate('s_order', $sOrder, "ordernumber='" . $orderNumber . "'");
					}
            
                    // Store order details in Novalnet table
                    $this->nHelper->logInitialTransaction(array(
                        'tid' => $nnCaptureParams['tid'],
                        'tariff_id' => $this->configDetails['novalnet_tariff'],
                        'payment_id' => $paymentData['id'],
                        'payment_key' =>  $this->nHelper->getPaymentKey($paymentData['name']),
                        'payment_type' => $paymentData['name'],
                        'amount' => (($paymentData['name'] == 'novalnetpaypal' && $nnCaptureParams['tid_status'] == '90') || ($paymentData['name'] == 'novalnetpaypal' && $this->configDetails[$paymentData['name'] . '_shopping_type'] == 'zero_amount_booking') ? 0 : $nnCaptureParams['amount']),
                        'currency' => $nnCaptureParams['currency'],
                        'status' => $nnCaptureParams['status'],
                        'gateway_status' => ($nnCaptureParams['tid_status']) ? $nnCaptureParams['tid_status'] : 0,
                        'test_mode' => $nnCaptureParams['test_mode'],
                        'customer_id' => $nnCaptureParams['customer_no'],
                        'order_no' => $orderNumber,
                        'date' => date('Y-m-d'),
                        'configuration_details' => serialize($this->configDetails),
                        'additional_note' => $note,
                        'lang' => substr(Shopware()->Container()->get('locale'), 0, 2)
                    ));
                    
                    //update order number to novalnet server
                    $callBackParams = array(
                            'vendor' => $this->configDetails['novalnet_vendor'],
                            'auth_code' => $this->configDetails['novalnet_auth_code'],
                            'product' => $this->configDetails['novalnet_product'],
                            'tariff' => $this->configDetails['novalnet_tariff'],
                            'remote_ip' => $this->nHelper->getIp(),
                            'status' => '100',
                            'tid' => $nnCaptureParams['tid'],
                            'order_no' => $orderNumber
                    );
                    $this->nHelper->curlCallRequest($callBackParams, $this->nHelper->novalnetGatewayUrl()['paygate_url']);
                    $this->debugMessage($newLine . $note, $orderNumber);
                } catch (Exception $e) {
                    Shopware()->Container()->get('pluginlogger')->error($e->getMessage());
                }
            } else {
				$articleName = $articleNumber = '';
				foreach ($basket['content'] as $article)
				{
					if($article['instock'] <= 0)
					{
						$articleName .= $article['articlename'] . ', ';
						$articleNumber .= $article['ordernumber'] . ', ';
					}
				}
				
				$emailPath = __DIR__ . '/mail/'.$lang. '/StockNotAvailableMail.tpl';
				$template = file_get_contents($emailPath);
				$template = str_replace('#TID#', $nnCaptureParams['tid'], $template);
				$template = str_replace('#AMOUNT#', (sprintf('%.2f', $nnCaptureParams['amount']/100) . ' '. $nnCaptureParams['currency']) , $template);
				$template = str_replace('#PRODUCT_NAME#', substr($articleName, 0, -2) , $template);
				$template = str_replace('#PRODUCT_NUMBER#', substr($articleNumber, 0, -2) , $template);
				$template = str_replace('#HEADER#', Shopware()->Config()->get('emailheaderhtml') , $template);
				$template = str_replace('#FOOTER#', Shopware()->Config()->get('emailfooterhtml') , $template);
				$template = str_replace('#DATE#', date('Y-m-d') , $template);
				$template = str_replace('#TIME#', date('H:i:s') , $template);
				$template = str_replace('#SHOP_URL#', ($shop->getSecure() ? 'https://' : 'http://') . $shop->getHost() . $shop->getBaseUrl() , $template);
				$template = str_replace('#CUSTOMER_EMAIL#', $nnCaptureParams['email'] , $template);
				
				$this->sendMerchantNotifyMail($this->novalnetLang['novalnet_order_stock_mail_subject'], $template);
            }
            
        } else {
			$emailPath = __DIR__ . '/mail/'.$lang. '/OrderNotMapped.tpl';
			$template = file_get_contents($emailPath);
			$template = str_replace('#TID#', $nnCaptureParams['tid'], $template);
			$template = str_replace('#AMOUNT#', (sprintf('%.2f', $nnCaptureParams['amount']/100) . ' '. $nnCaptureParams['currency']) , $template);
			$template = str_replace('#HEADER#', Shopware()->Config()->get('emailheaderhtml') , $template);
			$template = str_replace('#FOOTER#', Shopware()->Config()->get('emailfooterhtml') , $template);
			$template = str_replace('#DATE#', date('Y-m-d') , $template);
			$template = str_replace('#TIME#', date('H:i:s') , $template);
			$template = str_replace('#SHOP_URL#', ($shop->getSecure() ? 'https://' : 'http://') . $shop->getHost() . $shop->getBaseUrl() , $template);
			$template = str_replace('#CUSTOMER_NAME#', $nnCaptureParams['firstname']. ' '.$nnCaptureParams['lastname'] , $template);
			$template = str_replace('#CUSTOMER_EMAIL#', $nnCaptureParams['email'] , $template);
			
			$this->sendMerchantNotifyMail($this->novalnetLang['novalnet_order_missing_mail_subject'], $template);
        }
    }
    
    /**
     * Get failure transactions for redirection payments in lower version.
     *
     * @param array $nnCaptureParams
     * @return null
     */
    public function communicationFailureForLowerVersion($nnCaptureParams)
    {
        // Handling communication failure for redirection payments.
        $paymentDetails   = Shopware()->Db()->fetchRow('SELECT name,description FROM s_core_paymentmeans WHERE id = ?', array($this->orderDetails['paymentID']));
        
        if ((in_array($nnCaptureParams['status'], array('100','90'))) && in_array($nnCaptureParams['tid_status'], array(100,90,85,86,91,98,99,75))) { // Handle success transaction  
			$newLine     = '<br>';
            $note = $this->novalnetLang['novalnet_payment_transdetails_info'] . $newLine;
            $note .= $paymentDetails['description'] . $newLine;
            $note .= $this->novalnetLang['novalnet_tid_label'] . ':  ' . $nnCaptureParams['tid'] . $newLine;
            if ($nnCaptureParams['test_mode'] == 1) { // If test transaction
                $note .= $this->novalnetLang['novalnet_message_test_order'] . $newLine;
            }
            
            if ((!array_key_exists($paymentDetails['name'], $this->aPaymentTypes)) || !in_array($nnCaptureParams['payment_type'], $this->aPaymentTypes[$paymentDetails['name']])) {
                $this->debugMessage('Novalnet callback received. Payment type [' . $nnCaptureParams['payment_type'] . '] is mismatched!', $nnCaptureParams['order_no']);
                return false;
            }
            //pending
            $clearedStatus         = (in_array($nnCaptureParams['payment_type'], array('PAYPAL','PRZELEWY24')) && in_array($nnCaptureParams['tid_status'], array('90','86'))) ? $this->configDetails[$paymentDetails['name'] . '_before_paymenstatus'] : $this->configDetails[$paymentDetails['name'] . '_after_paymenstatus'];
            if(in_array($nnCaptureParams['payment_type'],array('PAYPAL','CREDITCARD','PAYPAL')) && in_array($nnCaptureParams['tid_status'],array('98','99','85'))) {
				$clearedStatus   = 	$this->configDetails['novalnet_onhold_order_complete'];
			}
            
            Shopware()->Modules()->Order()->setPaymentStatus((int) $this->orderDetails['id'], (int) $clearedStatus, false);
            $sOrder['temporaryID']     = $nnCaptureParams['tid'];
            $sOrder['transactionID']   = $nnCaptureParams['tid'];
            $sOrder['customercomment'] = str_replace('<br>', PHP_EOL, $note);
            $sOrder['cleareddate']     = date('Y-m-d');
            $this->nHelper->novalnetDbUpdate('s_order', $sOrder, 'ordernumber="' . $nnCaptureParams['order_no'] . '"');
            
            // Store order details in Novalnet table
            $this->nHelper->logInitialTransaction(array(
                'tid' => $nnCaptureParams['tid'],
                'tariff_id' => $this->configDetails['novalnet_tariff'],
                'payment_id' => (int) $this->orderDetails['paymentID'],
                'payment_key' =>  $this->nHelper->getPaymentKey($paymentDetails['name']),
                'payment_type' => $paymentDetails['name'],
                'amount' => (($paymentDetails['name'] == 'novalnetpaypal' && $nnCaptureParams['tid_status'] == '90') || ($paymentDetails['name'] == 'novalnetpaypal' && $this->configDetails[$paymentDetails['name'] . '_shopping_type'] == 'zero_amount_booking') ? 0 : $nnCaptureParams['amount']),
                'currency' => $nnCaptureParams['currency'],
                'status' => $nnCaptureParams['status'],
                'gateway_status' => ($nnCaptureParams['tid_status']) ? $nnCaptureParams['tid_status'] : 0,
                'test_mode' => $nnCaptureParams['test_mode'],
                'customer_id' => $nnCaptureParams['customer_no'],
                'order_no' => $nnCaptureParams['order_no'],
                'date' => date('Y-m-d'),
                'configuration_details' => serialize($this->configDetails),
                'additional_note' => $note,
                'lang' => substr(Shopware()->Container()->get('locale'), 0, 2)
            ));
            $context             = $this->novalnetMail($nnCaptureParams);
            $context['sComment'] = $note;
            $this->nHelper->sendNovalnetOrderMail($context);
            unset(Shopware()->Session()->novalnet);
            $this->debugMessage($newLine . $note, $nnCaptureParams['order_no']);
        } else { // Handle failure transaction
            $newLine     = '<br>';
            $orderStatus = Shopware()->Db()->fetchAll('SELECT * FROM  s_core_states ');
            $this->nHelper->updateStockRestore($nnCaptureParams);
            $note = $this->novalnetLang['novalnet_communication_failure_notification'] . $newLine;
            $note .= (($nnCaptureParams['status_message']) ? $nnCaptureParams['status_message'] : (($nnCaptureParams['status_text']) ? $nnCaptureParams['status_text'] : (($nnCaptureParams['status_desc']) ? $nnCaptureParams['status_desc'] : 'Payment was not successful. An error occurred'))) . $newLine;
            $note .= $this->novalnetLang['novalnet_tid_label'] . ':  ' . $nnCaptureParams['tid'] . $newLine;
            if ($nnCaptureParams['test_mode'] == 1) {
                $note .= $this->novalnetLang['novalnet_message_test_order'] . $newLine;
            }
            
            $sOrder['temporaryID']     = $nnCaptureParams['tid'];
            $sOrder['transactionID']   = $nnCaptureParams['tid'];
            $sOrder['customercomment'] = str_replace('<br>', PHP_EOL, $note);
            $sOrder['cleared']         = $orderStatus['28']['id'];
            $this->nHelper->novalnetDbUpdate('s_order', $sOrder, 'ordernumber="' . $nnCaptureParams['order_no'] . '"');
            //Store order details in novalnet table
            $this->nHelper->logInitialTransaction(array(
                'tid' => $nnCaptureParams['tid'],
                'tariff_id' => $this->configDetails['novalnet_tariff'],
                'payment_id' => (int) $this->orderDetails['paymentID'],
                'payment_key' =>  $this->nHelper->getPaymentKey($paymentDetails['name']),
                'payment_type' => $paymentDetails['name'],
                'amount' => $nnCaptureParams['amount'],
                'currency' => $nnCaptureParams['currency'],
                'status' => $nnCaptureParams['status'],
                'gateway_status' => ($nnCaptureParams['tid_status']) ? $nnCaptureParams['tid_status'] : 0,
                'test_mode' => $nnCaptureParams['test_mode'],
                'customer_id' => $this->orderDetails['userID'],
                'order_no' => $nnCaptureParams['order_no'],
                'date' => date('Y-m-d'),
                'additional_note' => $sOrder['customercomment'],
                'configuration_details' => serialize(array_filter($this->configDetails)),
                'lang' => substr(Shopware()->Container()->get('locale'), 0, 2)
            ));
            unset(Shopware()->Session()->novalnet);
            $this->debugMessage($newLine . $note, $nnCaptureParams['order_no']);
        }
    }

    /**
     * Get order reference from the novalnet_transaction_detail table on shop database
     *
     * @param array $nnCaptureParams
     * @return mixed
     */
    public function getOrderReference($nnCaptureParams)
    {
        $newLine = '<br />';
        $dbVal = Shopware()->Db()->fetchRow('SELECT lang, order_no, payment_type, gateway_status, subs_id, payment_key FROM s_novalnet_transaction_detail WHERE tid = ?', array($nnCaptureParams['shop_tid']));
        if ($nnCaptureParams['tid_status'] == '103' && $nnCaptureParams['payment_type'] == 'TRANSACTION_CANCELLATION') { 
        // transaction cancelled for invoice and sepa payments
            $callbackComments = sprintf($newLine . $this->novalnetLang['novalnet_guarantee_cancellation'], date('d-m-Y'), date('H:i:s'));
            $this->nHelper->novalnetDbUpdate('s_novalnet_transaction_detail', array('gateway_status' => $nnCaptureParams['tid_status']), 'tid=' . $nnCaptureParams['tid']);
            $id                        = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE  transactionID = ?', array($nnCaptureParams['shop_tid']));
            // Update callback comments in order status history table
            $callbackComments = str_replace('<br />', PHP_EOL, $callbackComments);
            $this->updateCommentsDB(array($callbackComments,$nnCaptureParams['shop_tid']));
            Shopware()->Modules()->Order()->setPaymentStatus((int) $id, (int) $this->configDetails['novalnet_onhold_order_cancelled'], false);
            $this->debugMessage($callbackComments);
            $this->sendNotifyMail(array(
                'comments' => $callbackComments,
                'tid' => $nnCaptureParams['shop_tid'],
                'order_no' => $nnCaptureParams['order_no']
                ));
            return false;
             
        }
        
        
        if (empty($dbVal['order_no']) && (in_array($nnCaptureParams['payment_type'], array('ONLINE_TRANSFER','PAYPAL','EPS','GIROPAY','PRZELEWY24','IDEAL' )) || ($nnCaptureParams['payment_type'] == 'CREDITCARD' && $this->configDetails['novalnetcc_cc3d'] == 1 || $this->configDetails['novalnetcc_force_cc3d'] == 1)) && !empty($this->orderDetails['temporaryID']) && ($this->orderDetails['temporaryID'] == $nnCaptureParams['inputval3'] || $this->orderDetails['temporaryID'] == $nnCaptureParams['payment_temporary_id'])) { // If transaction not found in Novalnet table but the order number available in Novalnet system and payment temprorary id matches, handle communication break
            $this->communicationFailureForLowerVersion($nnCaptureParams);
            return false;
        }
        
        if (empty($dbVal['order_no']) && (in_array($nnCaptureParams['payment_type'], array('ONLINE_TRANSFER','PAYPAL','EPS','GIROPAY','PRZELEWY24','IDEAL', 'ONLINE_TRANSFER_CREDIT' )) || ($nnCaptureParams['payment_type'] == 'CREDITCARD')) && ($this->orderDetails['temporaryID'] == $nnCaptureParams['inputval5'] || $this->orderDetails['temporaryID'] == $nnCaptureParams['nn_sid'] || empty($this->orderDetails)) && in_array($nnCaptureParams['tid_status'], array(100, 90, 86, 85, 98))) { // If transaction not found in Novalnet table but the order number available in Novalnet system and payment temprorary id matches, handle communication break
            $this->communicationFailure($nnCaptureParams);
            return false;
        }
        
        $paymentMethod = $dbVal['payment_type'];
        $orderNo       = $dbVal['order_no'];
        if (!empty($nnCaptureParams['order_no']) && $orderNo != $nnCaptureParams['order_no']) {
            $this->debugMessage('Novalnet callback received. Order no is not valid', $nnCaptureParams['order_no']);
            return false;
        } elseif ((!array_key_exists($paymentMethod, $this->aPaymentTypes)) || !in_array($nnCaptureParams['payment_type'], $this->aPaymentTypes[$paymentMethod])) {
            $this->debugMessage('Novalnet callback received. Payment type [' . $nnCaptureParams['payment_type'] . '] is mismatched!', $nnCaptureParams['order_no']);
            return false;
        }
        $dbVal['order_current_status'] = $this->nHelper->getOrderStatus($orderNo);
        $dbVal['callback_amount']      = $this->aryCaptureParams['amount'];
        // Collect given order's total amount
        $dbOrderDetails                = Shopware()->Db()->fetchRow('SELECT id, paymentID, invoice_amount, language, customercomment, currency,userID FROM s_order WHERE ordernumber = ?', array($orderNo));
        if (!$dbOrderDetails) {
            $this->debugMessage('Order Reference not exist in Database!', $nnCaptureParams['order_no']);
            return false;
        }
        $novalnetAmount              = Shopware()->Db()->fetchRow('SELECT novalnet_payment_current_amount FROM s_order_attributes WHERE orderID = ?', array($dbOrderDetails['id']));
        $dbVal['order_total_amount'] = ((int) $novalnetAmount['novalnet_payment_current_amount']) ? $novalnetAmount['novalnet_payment_current_amount'] : sprintf('%.2f', $dbOrderDetails['invoice_amount']) * 100;
        // Collect paid amount information from the novalnet_callback_history
        $dbVal['order_paid_amount']  = $this->nHelper->getOrderPaidAmount($nnCaptureParams['shop_tid']);
        $dbVal['language']           = $dbOrderDetails['language'];
        $dbVal['customercomment']    = $dbOrderDetails['customercomment'];
        $dbVal['tid']                = $nnCaptureParams['shop_tid'];
        $dbVal['id']                 = $dbOrderDetails['id'];
        $dbVal['currency']           = $dbOrderDetails['currency'];
        $dbVal['paymentID']          = $dbOrderDetails['paymentID'];
        $dbVal['userID']             = $dbOrderDetails['userID'];
        return $dbVal;
    }

    /**
     * Update customer comments
     *
     * @param array $commentId
     * @return null
     */
    public function updateCommentsDB($commentId)
    {
        Shopware()->Db()->query('update s_order set customercomment = CONCAT(customercomment,?) where transactionID = ?', $commentId);
    }

    /**
     * Get mail object from shop database
     *
     * @param array $nnCaptureParams
     * @return array
     */
    public function novalnetMail($nnCaptureParams)
    {
        $orderNumber = ($nnCaptureParams['order_no']) ? $nnCaptureParams['order_no'] : $this->orderDetails['ordernumber'];
        $paymentID = ($this->orderDetails['paymentID']) ? $this->orderDetails['paymentID'] : $nnCaptureParams['paymentID'];
        $paymentDetails        = Shopware()->Db()->fetchRow('SELECT * FROM s_core_paymentmeans WHERE id = ?', $paymentID);
        $orderAttributes       = Shopware()->Db()->fetchRow('SELECT id, dispatchID, invoice_shipping, invoice_amount, invoice_amount_net FROM s_order WHERE  ordernumber = ?', array($orderNumber));
        $sOrderBillingaddress  = Shopware()->Db()->fetchRow('SELECT * FROM s_order_billingaddress WHERE  orderID = ?', array($orderAttributes['id']));
        $sOrderShippingaddress = Shopware()->Db()->fetchRow('SELECT * FROM s_order_shippingaddress  WHERE  orderID = ?', array($orderAttributes['id']));
        $sOrderDetails         = Shopware()->Db()->fetchAll('SELECT * FROM s_order_details   WHERE  orderID = ?', array($orderAttributes['id']));
        $sOrderDetailValues    = array();
        for ($i = 0; $i < count($sOrderDetails); $i++) {
            $sOrderDetailValues[] = array(
                'articlename' => $sOrderDetails[$i]['name'],
                'ordernumber' => $sOrderDetails[$i]['articleordernumber'],
                'quantity' => $sOrderDetails[$i]['quantity'],
                'price' => $sOrderDetails[$i]['price'],
                'tax_rate' => $sOrderDetails[$i]['tax_rate'],
                'amount' => $sOrderDetails[$i]['price'] * $sOrderDetails[$i]['quantity'],
                'orderDetailId' => $sOrderDetails[$i]['id']
            );
        }
        $additional                                              = array('payment' => $paymentDetails);
        $referenceId                                             = !empty($nnCaptureParams['order_no']) ? $nnCaptureParams['order_no'] : $nnCaptureParams['shop_tid'];
        $subShopId                                               = $this->nHelper->getShopId($referenceId);
        $countryName                                         	 = Shopware()->Db()->fetchOne('SELECT countryname FROM s_core_countries WHERE  id = ?', array($nnCaptureParams['country_code']));
        $shippingAttributes                                      = Shopware()->Db()->fetchRow('SELECT * FROM s_premium_dispatch WHERE  id = ?', array($orderAttributes['dispatchID']));
        $context                                                 = array(
            'sOrderDetails' => $sOrderDetailValues,
            'billingaddress' => $sOrderBillingaddress,
            'shippingaddress' => $sOrderShippingaddress,
            'additional' => $additional,
            'sBookingID' => $nnCaptureParams['tid'],
            'sOrderNumber' => ($nnCaptureParams['signup_tid'] && $nnCaptureParams['subs_billing'] == '1') ? $nnCaptureParams['order_number'] : $nnCaptureParams['order_no'],
            'sOrderDay' => date('d.m.Y'),
            'sOrderTime' => date('H:i'),
            'ordernumber' => $orderNumber,
            'attributes' => '',
            'sCurrency' => $nnCaptureParams['currency'],
            'sShippingCosts' => $orderAttributes['invoice_shipping'],
            'sAmount' => $orderAttributes['invoice_amount'],
            'sAmountNet' => $orderAttributes['invoice_amount_net'],
            'sLanguage' => substr(Shopware()->Container()->get('locale'), 0, 2),
            'sDispatch' => $shippingAttributes,
            'sSubShop' => $subShopId,
            'sPaymentTable' => $subShopId
        );
        $context['additional']['user']['email']                  = $nnCaptureParams['email'];
        $context['additional']['country']['countryname']         = $countryName;
        $context['additional']['countryShipping']['countryname'] = $countryName;
        return $context;
    }

    /**
     * Display the error message
     *
     * @param string $errorMsg
     * @return null
     */
    public function debugMessage($errorMsg, $orderNo = null)
    {
        if ($orderNo) {
            $msg = 'message=' . $errorMsg . '&ordernumber=' . $orderNo;
        } else {
            $msg = 'message=' . $errorMsg;
        }
        $this->setViews->message = $msg;
    }
}
