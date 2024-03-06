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

use Shopware\CustomModels\Transaction\Transaction;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\PaymentRequest;
use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_NovalPayment extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var string
     */
    private $lang;

    /**
     * @var ManageRequest
     */
    private $requestHandler;

    /**
     * @var DataHandler
     */
    private $databaseHandler;

    /**
     * @var null|Transaction
     */
    private $reference;

    /**
     * @var string
     */
    private $dateformatter;

    /**
     * @var string
     */
    private $newLine;

    /**
     * Init method that get called automatically
     *
     * @return void
     */
    public function init()
    {
        $this->helper = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $this->requestHandler = new ManageRequest($this->helper);
        $this->databaseHandler = new DataHandler(Shopware()->Models());
        $this->reference = $this->databaseHandler->checkTransactionExists(['orderNo' => $this->Request()->getParam('number')]);
        $this->lang = $this->helper->getLocale(true, true);
        $this->dateformatter = 'd.m.Y';
        $this->newLine = '<br />';
    }

    /**
     * Novalnet auto configuration
     *
     * @return void
     */
    public function validateApiKeyAction()
    {
        $productActivationKey = trim($this->Request()->getParam('novalnetApiKey'));
        $paymentAccessKey     = trim($this->Request()->getParam('novalnetAccessKey'));
        $shopId               = $this->helper->getShopId($this->Request()->getParam('shopId'));
        $isSuccess            = false;

        // form API request parameters
        $serverParams['merchant']       = [
            // The API signature value
            'signature' => $productActivationKey,
        ];
        $serverParams['custom']       = [
            'lang'      => strtoupper($this->lang),
        ];

        $response = $this->requestHandler->curlRequest($serverParams, $this->helper->getActionEndpoint('merchant_details'), $paymentAccessKey);

        if ($response['result']['status'] === 'SUCCESS') {
            // insert/update API data
            $this->databaseHandler->insertApiData(['shop_id' => $shopId, 'api_key' => $productActivationKey, 'access_key' => $paymentAccessKey]);
            $isSuccess = true;
        }

        $this->View()->assign(array(
            'success' => $isSuccess,
            'data' => $response
        ));
    }

    /**
     * Novalnet tariff action
     *
     * @return void
     */
    public function getTariffAction()
    {
        $shopId = $this->helper->getShopId($this->Request()->getParam('field_name'));
        $getAPI = $this->databaseHandler->checkExistingApi($shopId);
        $tariffVal = [];
        $isSuccess = false;

        if (!empty($getAPI)) {
            $serverParams['merchant']       = [
                // The API signature value
                'signature' => $getAPI->getApiKey(),
            ];
            $serverParams['custom']       = [
                'lang'      => strtoupper($this->lang),
            ];

            $response = $this->requestHandler->curlRequest($serverParams, $this->helper->getActionEndpoint('merchant_details'), $getAPI->getAccessKey());

            if ($response['result']['status'] === 'SUCCESS') {
                $tariffArr    = $response['merchant']['tariff'];
                foreach ($tariffArr as $key => $value) {
                    $tariffVal[] = ['id' => $value['name'].' ('.$key.'-'.$value['type'].')'];
                }
                $isSuccess = true;
            }

            $this->View()->assign([
                   'success' => $isSuccess,
                   'data' => $tariffVal,
            ]);
        }
    }

    /**
     * Novalnet WebHook URL action
     *
     * @return void
     */
    public function configureWebhookAction()
    {
        $productActivationKey = trim($this->Request()->getParam('novalnetApiKey'));
        $paymentAccessKey     = trim($this->Request()->getParam('novalnetAccessKey'));
        $webhookUrl           = trim($this->Request()->getParam('novalnetWebhook'));

        if (!empty($productActivationKey) && !empty($paymentAccessKey)) {
            $request = [
                'merchant' => [
                    'signature' => $productActivationKey,
                ],
                'webhook'  => [
                    'url' => $webhookUrl,
                ],
                'custom'   => [
                    'lang' => strtoupper($this->lang),
                ],
            ];
            $response = $this->requestHandler->curlRequest($request, $this->helper->getActionEndpoint('webhook_configure'), $paymentAccessKey);

            $this->View()->assign([
                'success' => ($response['result']['status'] === 'SUCCESS') ? true : false,
                'data' => $response,
                'error' => ($response['result']['status'] === 'SUCCESS') ? '' : $response['result']['status_text']
            ]);
        }
    }

     /**
     * Action listener to show novalnet tab for our payment orders
     *
     * @return object
     */
    public function showTabForOurPaymentsAction()
    {
        if (empty($this->reference)) {
            return $this->View()->assign(['success' => false]);
        }
        $getConfig = $this->getNNConfigDetails();
        $instalmentDetails = (!empty($getConfig) && !empty($getConfig['InstalmentDetails']))? $getConfig['InstalmentDetails'] : [];
        if (preg_match("/INSTALMENT/i", $this->nnPaymentType()) && ! empty($instalmentDetails)) {
            $instalmentCancelExecuted = (!empty($getConfig) && empty($getConfig['instalmentCancelExecuted'])) ? false : $getConfig['instalmentCancelExecuted'] ;
            return $this->View()->assign(['success' => (!$instalmentCancelExecuted)]);
        }

        if ($this->nnGatewayStatus() == NOVALNET_ON_HOLD_STATUS) {
            return $this->View()->assign(['success' => true]);
        }

        if (($this->nnGatewayStatus()  == NOVALNET_CONFIRMED_STATUS ||
            ($this->nnGatewayStatus() == NOVALNET_PENDING_STATUS && in_array($this->nnPaymentType(), ['INVOICE', 'PREPAYMENT','CASHPAYMENT']))
            ) && $this->reference->getRefundedAmount() < $this->reference->getAmount() && ((int) $this->reference->getAmount() - (int) $this->reference->getRefundedAmount()) > 0 &&
            ! preg_match("/MULTIBANCO/i", $this->nnPaymentType())
        ) {
            return $this->View()->assign(['success' => true]);
        }

        if ((int) $this->reference->getAmount() == 0 && (int) $this->reference->getpaidAmount() == 0) {
            return $this->View()->assign(['success' => true]);
        }

        return $this->View()->assign(['success' => false]);
    }

    /**
     * Action listener to determine if the refund order operations tab will be displayed
     *
     * @return object
     */
    public function displayRefundTabAction()
    {
        if (!empty($this->reference)) {
            if (! (preg_match("/INSTALMENT/i", $this->nnPaymentType()) ||  preg_match("/MULTIBANCO/i", $this->nnPaymentType()))) {
                $balanceAmount = (int) $this->reference->getAmount() - (int) $this->reference->getRefundedAmount();

                return $this->View()->assign([
                    'success' => (($this->nnGatewayStatus()  == NOVALNET_CONFIRMED_STATUS || ($this->nnGatewayStatus() == NOVALNET_PENDING_STATUS &&
                                   in_array($this->nnPaymentType(), ['INVOICE', 'PREPAYMENT','CASHPAYMENT']))
                                  ) && $this->reference->getRefundedAmount() < $this->reference->getAmount() && $balanceAmount > 0
                                 ),
                    'amount' => $balanceAmount
                ]);
            }
        }

        return $this->View()->assign(['success' => false]);
    }

    /**
     * Action listener to determine if the Due Date Options will be displayed
     *
     * @param null
     * @return array
     */
    public function displayZeroAmountBookingTabAction()
    {
        if (!empty($this->reference)) {
            return $this->View()->assign(['success' =>  ( (int) $this->reference->getAmount() == 0 && (int) $this->reference->getpaidAmount() == 0  ) ]) ;
        }
        return $this->View()->assign(['success' => false]);
    }

    /**
     * Function to perform refund action
     *
     * @return object
     */
    public function processRefundAction()
    {
        $request      = $this->Request()->getParams();
        $amount       = ! empty($request['refund_amount']) ? $request['refund_amount'] : null;
        $success = true;
        $orderStatus = '';

        if (empty($amount)) {
            return $this->View()->assign([
                'success' => false,
                'code' => '',
                'message' => $this->helper->getLanguageFromSnippet('frontend_novalnet_amount_invalid', $request['number'])
            ]);
        }
        $data = [
                'transaction'=> [
                    'tid'    => ! empty($request['tid']) ? $request['tid'] : $this->reference->getTid(),
                    'amount' => $amount,
                ],
                'custom' => [
                    'lang'      => strtoupper($this->lang),
                    'shop_invoked' => 1
                ]
            ];

        $endPoint = $this->helper->getActionEndpoint('transaction_refund');

        if (!empty($request['refund_reason'])) {
            $data['transaction']['reason'] = $request['refund_reason'];
        }

        $response = $this->requestHandler->curlRequest($data, $endPoint, $this->getPaymentAccessKey());

        if ($response['result']['status'] === 'SUCCESS') {
            if (! empty($response['transaction']['refund']['amount'])) {
                $amountInBiggerCurrencyUnit = $this->helper->amountInBiggerCurrencyUnit($response['transaction']['refund']['amount'], $request['currency']);
            } else {
                $amountInBiggerCurrencyUnit = $this->helper->amountInBiggerCurrencyUnit($amount, $request['currency']);
            }

            if (! in_array($request['currency'], ['EUR','USD'])) {
                $amountInBiggerCurrencyUnit = $amountInBiggerCurrencyUnit.' '.$request['currency'];
            }

            $message = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund', $request['number']), $this->reference->getTid(), $amountInBiggerCurrencyUnit, date($this->dateformatter. ' H:i:s'));

            if (!empty($response['transaction']['refund']['tid'])) {
                $message .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund1', $request['number']), $response['transaction']['refund']['tid']);
            }

            $totalRefundedAmount = (int) $this->reference->getRefundedAmount() + (int) $amount;

            if ($response['result']['status'] == NOVALNET_DEACTIVATED_STATUS || ($totalRefundedAmount >= $this->reference->getAmount())) {
                $orderStatus = 35;
            }

            if (preg_match("/INSTALMENT/i", $this->nnPaymentType())) {
                $refundReference = [
                    'refundedAmount' => (int) $this->reference->getRefundedAmount(),
                    'toBeRefund' => ! empty($response['transaction']['refund']['amount']) ? $response['transaction']['refund']['amount'] : $amount,
                    'tid'    => ! empty($response['transaction']['tid']) ? $response['transaction']['tid'] : $request['tid'] ,
                ];

                $configurationDetails = $this->helper->updateConfigurationDetails($this->getNNConfigDetails(), null, $refundReference);

                $this->databaseHandler->insertNovalnetTransaction([
                    'tid' => $response['transaction']['tid'],
                    'configuration_details' => $configurationDetails['instalmentConfigData'],
                ]);
            }

            $this->databaseHandler->insertNovalnetTransaction([
                'tid' => $response['transaction']['tid'],
                'refunded_amount' => $totalRefundedAmount
            ]);

            if (!empty($orderStatus)) {
                Shopware()->Modules()->Order()->setPaymentStatus($request['id'], $orderStatus, false);
            }

            $this->databaseHandler->postProcess($message, ['tid' => $this->reference->getTid()], true, null);
        } else {
            $message = $this->newLine . $this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('BasicParamError', $request['number']));
            $success = false;
        }

        return $this->View()->assign([
           'success' => $success,
           'message' => $message
        ]);
    }

    /**
     * Function to perform zero amount booking action
     *
     * @return object
     */
    public function processBookingAmountAction()
    {
        $request = $this->Request()->getParams();
        $success = true;
        $message = '';

        if (empty($request['bookingAmount'])) {
            return $this->View()->assign([
                'success' => false,
                'code' => '',
                'message' => $this->helper->getLanguageFromSnippet('frontend_novalnet_amount_invalid', $request['number'])
            ]);
        }

        $orderData = $this->databaseHandler->getOrder($request['number']);
        $services  = new PaymentRequest($this->helper);
        $endPoint  = $this->helper->getActionEndpoint('payment');
        $getConfig = $this->getNNConfigDetails();
        $merchantData = (!empty($getConfig) && !empty($getConfig['merchant'])) ? $getConfig['merchant'] : $services->getMerchantDetails();

        $data = [
            'merchant'  => $merchantData,
            'customer'  => $services->getCustomerData($orderData),
            'transaction'=> [
                'payment_type'     => $this->nnPaymentType(),
                'amount'           => $request['bookingAmount'],
                'currency'         => $orderData['currency'],
                'test_mode'        => $getConfig['test_mode'],
                'order_no'         => $request['number'],
            ],
            'custom'    => [
                'lang'      => strtoupper($this->lang),
                'shop_invoked' => 1
            ]
        ];

        if (!empty($getConfig) && !empty($getConfig['token'])) {
            $data['transaction']['payment_data']['token'] = $getConfig['token'];
        }

        $bookingResponse = $this->requestHandler->curlRequest($data, $endPoint, $this->getPaymentAccessKey());

        if ($bookingResponse['result']['status'] === 'SUCCESS') {
                $upsertData = [
                    'id'              => $this->reference->getId(),
                    'paid_amount'     => ($bookingResponse['transaction']['status'] === NOVALNET_CONFIRMED_STATUS) ? $bookingResponse['transaction']['amount'] : 0,
                    'tid'             => $bookingResponse['transaction']['tid'],
                    'gateway_status'  => $bookingResponse['transaction']['status'],
                    'amount'          => $bookingResponse['transaction']['amount'],
                ];

                $bookingResponse['transaction']['payment_name'] = $getConfig['payment_name'];
                $formattedBookedAmount  = $this->helper->amountInBiggerCurrencyUnit($bookingResponse['transaction']['amount'], $bookingResponse['transaction']['currency']);
                $message  = $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_booking_amount_message', $request['number']), $formattedBookedAmount, $bookingResponse['transaction']['tid']);
                $success  = true;

                $this->databaseHandler->postProcess($message, $upsertData, false, $this->reference->getTid());
        } else {
            $message = $this->helper->getStatusDesc($bookingResponse, $this->helper->getLanguageFromSnippet('BasicParamError', $request['number']));
            $success = false;
        }
        return $this->View()->assign([
                'success' => $success,
                'message' => $message
        ]);
    }

    /**
     * Action listener to determine if the Onhold Order Operations Tab will be displayed
     *
     * @return object
     */
    public function displayOnholdTabAction()
    {
        return $this->View()->assign([
            'success' => ($this->nnGatewayStatus()  == NOVALNET_ON_HOLD_STATUS)
        ]);
    }

    /**
     * Function to perform capture action
     *
     * @return object
     */
    public function processCaptureAction()
    {
        $success    = $appendComments = true;
        $request    = $this->Request()->getParams();
        $config     = $this->helper->getConfigurations();
        $paidAmount = 0;

        if ($request['status'] == '100') {
            $endpoint = $this->helper->getActionEndpoint('transaction_capture');
        } else {
            $endpoint = $this->helper->getActionEndpoint('transaction_cancel');
        }

        $data = [
            'transaction'=> [
                'tid'    => $this->reference->getTid(),
            ],
            'custom'    => [
                'lang'      => strtoupper($this->lang),
                'shop_invoked' => 1
            ]
        ];

        $captureResponse = $this->requestHandler->curlRequest($data, $endpoint, $this->getPaymentAccessKey());

        $serializedData  = [];
        if ($captureResponse['result']['status'] === 'SUCCESS') {
            if (in_array($captureResponse['transaction']['status'], [ NOVALNET_CONFIRMED_STATUS, NOVALNET_PENDING_STATUS])) {
                if (preg_match("/INSTALMENT/i", $this->nnPaymentType()) || preg_match("/INVOICE/i", $this->nnPaymentType())) {
                    $appendComments = false;

                    if (empty($captureResponse['transaction']['bank_details']) && $this->hasBankData()) {
                        $bankData = ['account_holder','bank_name','bank_place','bic','iban'];
                        foreach ($bankData as $key) {
                            $captureResponse['transaction']['bank_details'][$key] = $this->getNNConfigDetails()[$key];
                        }
                    }

                    $captureResponse['transaction']['payment_name'] = $this->getNNConfigDetails()['payment_name'];
                    $message = $this->newLine . $this->helper->formCustomerComments(array_filter($captureResponse), $captureResponse['transaction']['currency']);
                    if (! empty($captureResponse['instalment']['cycles_executed'])) {
                        $serializedData = $this->helper->getInstalmentInformation($captureResponse);
                        $serializedData = $this->helper->serializeData($serializedData);
                    }
                }

                $message .= $this->newLine . sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_amount_capture', $request['number']), date($this->dateformatter), date(' H:i:s'));

                if ($captureResponse['transaction']['status'] == NOVALNET_CONFIRMED_STATUS) {
                    $paidAmount = $this->reference->getAmount();
                }
            } else {
                $message = $this->newLine . $this->newLine  . sprintf($this->helper->getLanguageFromSnippet('novalnet_transaction_canceled_message', $request['number']), date('Y-m-d'), date('H:i:s'));
                $paymentStatusID  = 35;
            }

            $this->databaseHandler->insertNovalnetTransaction(array_filter([
                'tid' => $this->reference->getTid(),
                'gateway_status' => ($captureResponse['transaction']['payment_type'] == 'INVOICE') ? NOVALNET_PENDING_STATUS : $captureResponse['transaction']['status'],
                'paid_amount' => $paidAmount,
                'configuration_details' => $serializedData
            ]));

            $paymentStatusID = $config['novalnet_after_payment_status'];

            if ($captureResponse['transaction']['status'] == NOVALNET_PENDING_STATUS || $captureResponse['transaction']['payment_type'] == 'INVOICE') {
                $paymentStatusID = '17';
            }

            if ($request['status'] == '103') {
                $paymentStatusID = '35';
            }

            if (!empty($paymentStatusID)) {
                Shopware()->Modules()->Order()->setPaymentStatus($request['id'], (int) $paymentStatusID, false);
            }

            $this->databaseHandler->postProcess($message, ['tid' => $this->reference->getTid()], $appendComments, false);
        } else {
            $message = $this->helper->getStatusDesc($captureResponse, $this->helper->getLanguageFromSnippet('BasicParamError', $request['number']));
            $success = false;
        }
        return $this->View()->assign([
                'success' => $success,
                'code' => $request['status'],
                'message' => $message
        ]);
    }

    /**
     * Action listener to determine if the Instalment Info Options will be displayed
     *
     * @return object
     */
    public function displayInstalmentInfoTabAction()
    {
        $displayInstalmentTab = false;

        if (! empty($this->reference)) {
            $isInstalment = preg_match("/INSTALMENT/i", $this->nnPaymentType());
            $getConfig = $this->getNNConfigDetails();
            $instalmentDetails = (!empty($getConfig) && !empty($getConfig['InstalmentDetails']))? $getConfig['InstalmentDetails'] : [];

            if (!empty($isInstalment) && $this->nnGatewayStatus() != NOVALNET_ON_HOLD_STATUS && !empty($instalmentDetails)) {
                $displayInstalmentTab = true;
            }
        }

        return $this->View()->assign(['success' => $displayInstalmentTab]);
    }

    /**
     * Action Listener to show instalment details
     *
     * @return void
     */
    public function displayPaymentInstalmentDetailsAction()
    {
        $getConfig = $this->getNNConfigDetails();
        $instalmentDetails = (!empty($getConfig) && !empty($getConfig['InstalmentDetails']))? $getConfig['InstalmentDetails'] : [];
        
        if (!empty($instalmentDetails)) {
            $instalmentInfo = [];

            foreach ($instalmentDetails as $key => $value) {
                if (is_array($value)) {
                    $instalmentInfo[] = array(
                        'status' => ! empty($value['status']) ? $value['status'] : $this->helper->getLanguageFromSnippet('pendingMsg'),
                        'processedInstalment' => $key,
                        'date' => ! empty($value['cycleDate']) ? $value['cycleDate'] : '-',
                        'amount' => ! empty($value['amount']) ? str_replace('.', ',', $this->helper->amountInBiggerCurrencyUnit($value['amount'], 'EUR')): '-',
                        'reference' => ! empty($value['reference']) ? strval($value['reference']) : '-',
                        'refundedAmount' => ! empty($value['refundedAmount']) ? str_replace('.', ',', $this->helper->amountInBiggerCurrencyUnit($value['refundedAmount'], 'EUR')): '-',
                    );
                }
            }

            $this->view->assign([
                'success' => true,
                'total' => count($instalmentDetails),
                'data' => $instalmentInfo,
            ]);
        } else {
            $this->view->assign([
                'success' => false
            ]);
        }
    }

     /**
     * Action listener to determine if the Instalment Cancel operations tab will be displayed
     *
     * @return object
     */
    public function displayInstalmentCancelAction()
    {
        if (!empty($this->reference)) {
            $getConfig = $this->getNNConfigDetails();
            $instalmentCancelExecuted = (!empty($getConfig) && empty($getConfig['instalmentCancelExecuted'])) ? false : $getConfig['instalmentCancelExecuted'] ;
            $isInstalment = preg_match("/INSTALMENT/i", $this->nnPaymentType());

            return $this->View()->assign([
                'success' => ( !empty($isInstalment) &&
                               $this->nnGatewayStatus()  == NOVALNET_CONFIRMED_STATUS &&
                               (int) $this->reference->getAmount() > (int) $this->reference->getRefundedAmount() &&
                               $instalmentCancelExecuted == false
                             ),
                'displayAllInstalment' => $this->canShowCancelAllInstalments(),
                'displayRemainingInstalment' => $this->canShowCancelRemainigInstalments()
            ]);
        }

        return $this->View()->assign(['success' => false]);
    }



    /**
     * Function to perform Instalment Cancel action
     *
     * @return object
     */
    public function processInstalmentCancelAction()
    {
        $request     = $this->Request()->getParams();
        $endpoint    = $this->helper->getActionEndpoint('instalment_cancel');
        $cancelType = $request['cancelType'];
        $data = [
            'instalment'=> [
                'tid'         => $request['transactionId'],
                'cancel_type' => $cancelType,
            ],
            'custom'    => [
                'lang'        => strtoupper($this->lang),
                'shop_invoked'=> 1,
            ]
        ];

        $instalmentcancelResponse = $this->requestHandler->curlRequest($data, $endpoint, $this->getPaymentAccessKey());

        if ($instalmentcancelResponse['result']['status'] === 'SUCCESS') {
            if (in_array($instalmentcancelResponse['transaction']['status'], [ NOVALNET_CONFIRMED_STATUS, NOVALNET_PENDING_STATUS])) {
                $ConfigurationDetails = $this->helper->updateConfigurationDetails($this->getNNConfigDetails(), $cancelType, null);
                $this->databaseHandler->insertNovalnetTransaction([
                    'tid'                   => $instalmentcancelResponse['transaction']['tid'],
                    'configuration_details' => $ConfigurationDetails['instalmentConfigData'],
                    'refunded_amount'       => $ConfigurationDetails['refundTotalAmount'],
                    'gateway_status'        => $instalmentcancelResponse['transaction']['status'],
                ]);

                if ($cancelType == 'CANCEL_REMAINING_CYCLES') {
                    $success = true;
                    $message = $this->newLine .sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_remaining', $request['number']), $this->reference->getTid(), date('d/m/Y'), date('H:i:s'));
                } elseif ($cancelType == 'CANCEL_ALL_CYCLES') {
                    $formattedRefundAmount = $this->helper->amountInBiggerCurrencyUnit($instalmentcancelResponse['transaction']['refund']['amount'], $request['currency']);
                    $success = true;
                    $message = $this->newLine .sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_all', $request['number']), $instalmentcancelResponse['transaction']['tid'], date($this->dateformatter. ' H:i:s'), $formattedRefundAmount);
                }

                if ((int)$ConfigurationDetails['refundTotalAmount']  >= (int)$this->reference->getAmount()) {
                    Shopware()->Modules()->Order()->setPaymentStatus($request['id'], '35', false);
                }
            }

            Shopware()->Modules()->Order()->setPaymentStatus($request['id'], '35', false);
            $this->databaseHandler->postProcess($message, ['tid' => $instalmentcancelResponse['transaction']['tid']], true, null);
        } else {
            $message = $this->helper->getStatusDesc($instalmentcancelResponse, $this->helper->getLanguageFromSnippet('BasicParamError', $request['number']));
            $success = false;
        }

        return $this->View()->assign([
            'success' => $success,
            'message' => $message
        ]);
    }

    /**
     * Function to encode configuration details
     *
     * @return object
     */
    public function getNNConfigDetails()
    {
        $nnCofigDetails = [];
        if (!empty($this->reference)) {
            $getConfigurationDetails = $this->reference->getConfigurationDetails();
            
            if (!empty($getConfigurationDetails)) {
                $nnCofigDetails = $this->helper->unserializeData($getConfigurationDetails);
            }
        }
        return !empty($nnCofigDetails) ? $nnCofigDetails : [] ;
    }
    
    /**
     * Function to get order payment type
     *
     * @return string
     */
    public function nnPaymentType()
    {
        $getPaymentType = $this->reference->getPaymentType();
        $nnPaymentType = (!empty($this->reference) && ! empty($getPaymentType)) ? $this->helper->getPaymentType($getPaymentType) :null;
        return !empty($nnPaymentType) ? $nnPaymentType : null;
    }

    /**
     * Function to get order gateway status
     *
     * @return string
     */
    public function nnGatewayStatus()
    {
        $getGatewayStatus = $this->reference->getGatewayStatus();
        $nnTransactionStatus = (! empty($this->reference) && ! empty($getGatewayStatus)) ? $this->helper->getStatus($getGatewayStatus, $this->reference, $this->nnPaymentType()) :null;

        return ! empty($nnTransactionStatus) ? $nnTransactionStatus : null;
    }

    public function canShowCancelAllInstalments()
    {
        $cancelAllInstalment = null;
        $getConfig = $this->getNNConfigDetails();
        $instalmentDetails = (!empty($getConfig) && !empty($getConfig['InstalmentDetails']))? $getConfig['InstalmentDetails'] : [];

        foreach ($instalmentDetails as $instalmentValue) {
            if (!empty($instalmentValue['reference'])) {
                $paidAmount     += $instalmentValue['amount'];
                $refundedAmount += $instalmentValue['refundedAmount'] ;
                if ($paidAmount > $refundedAmount) {
                    return $cancelAllInstalment = 'CANCEL_ALL_CYCLES';
                }
            }
        }
        return $cancelAllInstalment;
    }

    public function canShowCancelRemainigInstalments()
    {
        $cancelRemainingInstalment = null;
        $getConfig = $this->getNNConfigDetails();
        $instalmentDetails = (!empty($getConfig) && !empty($getConfig['InstalmentDetails']))? $getConfig['InstalmentDetails'] : [];

        foreach ($instalmentDetails as $instalmentValue) {
            if (empty($instalmentValue['reference'])) {
                return $cancelRemainingInstalment = 'CANCEL_REMAINING_CYCLES';
            }
        }

        return $cancelRemainingInstalment;
    }

    /**
     * Action to show Novalnet payment name
     *
     * @return object
     */
    public function getNovalnetPaymentNameAction()
    {
        $request = $this->Request()->getParams();
        $getConfig = $this->getNNConfigDetails();
        if (!empty($getConfig) && !empty($getConfig['payment_name'])) {
            $novalnetPaymentName = $getConfig['payment_name'];
        } else {
            $novalnetPaymentName = Shopware()->Db()->fetchOne('SELECT novalnet_payment_name FROM s_order_attributes WHERE  orderID = ?', [ (int) $request['id']]);
        }

        return $this->View()->assign([
            'novalnetPaymentName' => $novalnetPaymentName,
        ]);
    }

    /**
     * Function to check has bank details
     *
     * @return boolean
     */
    public function hasBankData()
    {
        $getConfig = $this->getNNConfigDetails();
        $hasBankData = !empty($getConfig) && $getConfig['account_holder'] && $getConfig['bank_name'] && $getConfig['bank_place'] && $getConfig['iban'] ? true : false;
        return $hasBankData;
    }

    /**
     * Function to get respective shop payment access
     *
     * @return boolean
     */
    public function getPaymentAccessKey()
    {
        $shopId      = $this->Request()->getParam('shopId');
        $languageIso = $this->Request()->getParam('languageIso');
        $shopId      = !empty($shopId) ? $shopId : $languageIso;
        $nnTariffElementId = Shopware()->Db()->fetchOne('SELECT id FROM `s_core_config_elements` WHERE `name` = "novalnet_password"');

        if (!empty($nnTariffElementId)) {
            $nnTariffConfigValue = Shopware()->Db()->fetchOne('SELECT value FROM s_core_config_values WHERE element_id ="' .$nnTariffElementId . '"AND shop_id ="'.$shopId .'"');
            if ($nnTariffConfigValue) {
                $accessKey = unserialize($nnTariffConfigValue);
            }
        }

        if (empty($accessKey)) {
            $accessKey = $this->helper->getConfigurations()['novalnet_password'];
        }
        return !empty($accessKey) ? $accessKey : null;
    }
}
