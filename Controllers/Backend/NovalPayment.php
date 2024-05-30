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
        $this->lang = substr($this->helper->getLocale(true), 0, 2);
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
            $this->View()->assign(array(
                'success' => true,
                'data' => $response
            ));
        } else {
            $this->View()->assign(array(
                'success' => false,
                'data' => $response
            ));
        }
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
                $this->View()->assign([
                    'success' => true,
                    'data' => $tariffVal
                ]);
            } else {
                $this->View()->assign([
                    'success' => false,
                    'data' => []
                ]);
            }
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

            if ($response ['result']['status_code'] == '100') {
                $this->View()->assign([
                    'success' => true,
                    'data' => $response,
                    'error' => ''
                ]);
            } else {
                $this->View()->assign([
                    'success' => true,
                    'data' => $response,
                    'error' => $response ['result']['status_text']
                ]);
            }
        }
    }

    /**
     * Action listener to determine if the refund order operations tab will be displayed
     *
     * @return object
     */
    public function displayRefundTabAction()
    {
        if (!empty($this->reference)) {
            if (!in_array($this->reference->getPaymentType(), [ 'novalnetinvoiceinstalment', 'novalnetsepainstalment'])) {
                $balanceAmount = (int) $this->reference->getAmount() - (int) $this->reference->getRefundedAmount();
                return $this->View()->assign(['success' => (
                 ((in_array($this->reference->getGatewayStatus(), ['CONFIRMED', 100])) && ($this->reference->getRefundedAmount() < $this->reference->getAmount())) || (in_array($this->reference->getGatewayStatus(), ['PENDING' ,100]) && in_array($this->reference->getPaymentType(), ['novalnetinvoice', 'novalnetprepayment', 'novalnetcashpayment', 'novalnetmultibanco']))&&
                 $balanceAmount > 0 ) , 'amount' => $balanceAmount]);
            }
        }
        return $this->View()->assign(['success' => false]);
    }

    /**
     * Action listener to determine if the Onhold Order Operations Tab will be displayed
     *
     * @return object
     */
    public function displayOnholdTabAction()
    {
        return $this->View()->assign([
            'success' => ($this->reference->getGatewayStatus() === 'ON_HOLD' || (in_array($this->reference->getGatewayStatus(), array(98, 99, 91, 85))))
        ]);
    }

    /**
     * Action listener to determine if the Instalment Cancel operations tab will be displayed
     *
     * @return object
     */
    public function displayInstalmentCancelAction()
    {
        if (!empty($this->reference)) {
            $instalmentCancelExecuted = $this->helper->unserializeData($this->reference->getConfigurationDetails())['instalmentCancelExecuted'];
            return $this->View()->assign([
                'success' => (in_array($this->reference->getPaymentType(), ['novalnetinvoiceinstalment', 'novalnetsepainstalment']) &&
                              $this->reference->getGatewayStatus() == 'CONFIRMED' &&
                              ((int) $this->reference->getAmount() >= (int) $this->reference->getRefundedAmount()) &&
                              ($instalmentCancelExecuted == false) ),
                'displayAllInstalment' => $this->helper->canShowCancelAllInstalments($this->reference->getConfigurationDetails()),
                'displayRemainingInstalment' => $this->helper->canShowCancelRemainigInstalments($this->reference->getConfigurationDetails())

            ]);
        }

        return $this->View()->assign(['success' => false]);
    }

    /**
     * Action listener to determine if the Instalment Info Options will be displayed
     *
     * @return object
     */
    public function displayInstalmentInfoTabAction()
    {
        if (empty($this->reference)) {
            return $this->View()->assign(['success' => false]);
        } else {
            return $this->View()->assign([
                'success' => (in_array($this->reference->getPaymentType(), [
                    'novalnetinvoiceinstalment',
                    'novalnetsepainstalment'
                ]) && $this->reference->getGatewayStatus() == 'CONFIRMED')
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
        
        if (in_array($this->reference->getPaymentType(), [ 'novalnetinvoiceinstalment', 'novalnetsepainstalment' ]) && $this->reference->getGatewayStatus() == 'CONFIRMED') {
            $instalmentCancelExecuted = ( empty($this->helper->unserializeData($this->reference->getConfigurationDetails())['instalmentCancelExecuted']) ) ? false : $this->helper->unserializeData($this->reference->getConfigurationDetails())['instalmentCancelExecuted'] ;
            return $this->View()->assign(['success' => (!$instalmentCancelExecuted)]);
        }

        if (in_array($this->reference->getGatewayStatus(), array('ON_HOLD', 98, 99, 91, 85))) {
            return $this->View()->assign(['success' => true]);
        }

        if (( (in_array($this->reference->getGatewayStatus(), ['CONFIRMED', 100])) || ((in_array($this->reference->getGatewayStatus(), ['PENDING', 100])) && in_array($this->reference->getPaymentType(), ['novalnetinvoice', 'novalnetprepayment' , 'novalnetcashpayment', 'novalnetmultibanco']))) && $this->reference->getRefundedAmount() < $this->reference->getAmount()) {
            return $this->View()->assign(['success' => true]);
        }
        
        return $this->View()->assign(['success' => false]);
    }

    /**
     * Action Listener to show instalment details
     *
     * @return void
     */
    public function displayPaymentInstalmentDetailsAction()
    {
        if (!empty($this->reference->getConfigurationDetails())) {
            $configDetails  = $this->helper->unserializeData($this->reference->getConfigurationDetails());
            $instalmentInfo = [];

            foreach ($configDetails['InstalmentDetails'] as $key => $value) {
                if (is_array($value)) {
                    $instalmentInfo[] = array(
                        'status' => ($value['status']) ? $value['status'] : 'Pending',
                        'processedInstalment' => $key,
                        'date' => ($value['cycleDate']) ? $value['cycleDate'] : '-',
                        'amount' => ($value['amount']) ? str_replace('.', ',', $this->helper->amountInBiggerCurrencyUnit($value['amount'], 'EUR')): '-',
                        'reference' => ($value['reference']) ? strval($value['reference']) : '-',
                        'refundedAmount' => ($value['refundedAmount']) ? str_replace('.', ',', $this->helper->amountInBiggerCurrencyUnit($value['refundedAmount'], 'EUR')): '-',
                    );
                }
            }

            $this->view->assign([
                'success' => true,
                'total' => count($configDetails['InstalmentDetails']),
                'data' => $instalmentInfo,
            ]);
        } else {
            $this->view->assign([
                'success' => false
            ]);
        }
    }

    /**
     * Function to perform refund action
     *
     * @return object
     */
    public function processRefundAction()
    {
        $request      = $this->Request()->getParams();
        $amount       = $request['refund_amount'] ? $request['refund_amount'] : '';
        $success = true;
        $orderStatus = '';
        
        if (!empty($this->reference->getOrderNo())) {
            $this->helper->setLocale($this->reference->getOrderNo());
        }

        if (empty($amount)) {
            return $this->View()->assign([
                'success' => false,
                'code' => '',
                'message' => $this->helper->getLanguageFromSnippet('frontend_novalnet_amount_invalid')
            ]);
        }
        $data = [
                'transaction'=> [
                    'tid'    => $request['tid'] ? $request['tid'] : $this->reference->getTid(),
                    'amount' => $amount,
                ],
                'custom' => [
                    'lang'      => strtoupper($this->lang),
                    'shop_invoked' => 1
                ]
            ];

            $endPoint = $this->helper->getActionEndpoint('transaction_refund');

        if (!empty($request['refund_reason'])) {
            $data['transaction']['reason'] = $refundReason;
        }

        $response = $this->requestHandler->curlRequest($data, $endPoint);

        if ($response['result']['status'] === 'SUCCESS') {
            if (! empty($response['transaction']['refund']['amount'])) {
                $amountInBiggerCurrencyUnit = $this->helper->amountInBiggerCurrencyUnit($response['transaction']['refund']['amount'], $request['currency']);
            } else {
                $amountInBiggerCurrencyUnit = $this->helper->amountInBiggerCurrencyUnit($amount, $request['currency']);
            }
            
            $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund'), $this->reference->getTid(), $amountInBiggerCurrencyUnit, date($this->dateformatter. ' H:i:s'));

            if (!empty($response['transaction']['refund']['tid'])) {
                $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund1'), $this->reference->getTid(), $amountInBiggerCurrencyUnit, date($this->dateformatter. ' H:i:s'), $response['transaction']['refund']['tid']);
            }

            $totalRefundedAmount = (int) $this->reference->getRefundedAmount() + (int) $amount;

            if ($response['result']['status'] == 'DEACTIVATED' || ($totalRefundedAmount >= $this->reference->getAmount())) {
                $orderStatus = 35;
            }

            if (in_array($this->reference->getPaymentType(), ['novalnetinvoiceinstalment','novalnetsepainstalment'])) {
                $configDetails  = $this->helper->unserializeData($this->reference->getConfigurationDetails());
                $refundReference = [
                    'refundedAmount' => (int) $this->reference->getRefundedAmount(),
                    'toBeRefund' => ($response['transaction']['refund']['amount']) ? $response['transaction']['refund']['amount'] : $request['refund_amount'],
                    'tid'    => ($response['transaction']['tid']) ? $response['transaction']['tid'] : $request['tid'] ,
                ];

                $configurationDetails = $this->helper->updateConfigurationDetails($configDetails, null, $refundReference);
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

            $this->databaseHandler->postProcess($message, ['tid' => $this->reference->getTid()]);
        } else {
            $message = $this->helper->getStatusDesc($response, $this->helper->getLanguageFromSnippet('BasicParamError'));
            $success = false;
        }

        return $this->View()->assign([
           'success' => $success,
           'message' => $message
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
        $config     = $this->helper->getConfigurations($this->Request()->getParam('shopId'));
        $paidAmount = 0;
        
        if (!empty($this->reference->getOrderNo())) {
            $this->helper->setLocale($this->reference->getOrderNo());
        }

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

        $captureResponse = $this->requestHandler->curlRequest($data, $endpoint);
        $serializedData  = [];
        if ($captureResponse['result']['status'] === 'SUCCESS') {
            if (in_array($captureResponse['transaction']['status'], ['CONFIRMED', 'PENDING'])) {
                $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_amount_capture'), date($this->dateformatter), date(' H:i:s'));

                if (in_array($this->reference->getPaymentType(), ['novalnetinvoiceinstalment', 'novalnetsepainstalment', 'novalnetinvoiceGuarantee', 'novalnetinvoice'])) {
                    $appendComments = false;
                    $captureResponse['transaction']['bank_details'] = $this->helper->unserializeData($this->reference->getConfigurationDetails());
                    $message .= $this->newLine . $this->newLine . $this->helper->formCustomerComments(array_filter($captureResponse), $this->reference->getPaymentType(), $captureResponse['transaction']['currency']);
                    if (! empty($captureResponse['instalment']['cycles_executed'])) {
                        $serializedData = $this->helper->getInstalmentInformation($captureResponse);
                        $serializedData = $this->helper->serializeData($serializedData);
                    }
                }

                $status = $config[$this->reference->getPaymentType().'_after_paymenstatus'];

                if ($captureResponse['transaction']['status'] == 'CONFIRMED') {
                    $paidAmount = $this->reference->getAmount();
                }
            } else {
                $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_deactivated_message'), date('Y-m-d'), date('H:i:s'));
                $status  = 35;
            }

            $this->databaseHandler->insertNovalnetTransaction(array_filter([
                'tid' => $this->reference->getTid(),
                'gateway_status' => $captureResponse['transaction']['status'],
                'paid_amount' => $paidAmount,
                'configuration_details' => $serializedData
            ]));

            if (!empty($status)) {
                Shopware()->Modules()->Order()->setPaymentStatus($request['id'], (int) $status, false);
            }

            $this->databaseHandler->postProcess($message, ['tid' => $this->reference->getTid()], $appendComments);
        } else {
            $message = $this->helper->getStatusDesc($captureResponse, $this->helper->getLanguageFromSnippet('BasicParamError'));
            $success = false;
        }
        return $this->View()->assign([
                'success' => $success,
                'code' => $request['status'],
                'message' => $message
        ]);
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
        
        if (!empty($this->reference->getOrderNo())) {
            $this->helper->setLocale($this->reference->getOrderNo());
        }

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
        
        $instalmentcancelResponse = $this->requestHandler->curlRequest($data, $endpoint);

        if ($instalmentcancelResponse['result']['status'] === 'SUCCESS') {
            if (in_array($instalmentcancelResponse['transaction']['status'], ['CONFIRMED', 'PENDING'])) {
                $configDetails        = $this->helper->unserializeData($this->reference->getConfigurationDetails());
                $ConfigurationDetails = $this->helper->updateConfigurationDetails($configDetails, $cancelType, null);
                $this->databaseHandler->insertNovalnetTransaction([
                    'tid'                   => $instalmentcancelResponse['transaction']['tid'],
                    'configuration_details' => $ConfigurationDetails['instalmentConfigData'],
                    'refunded_amount'       => $ConfigurationDetails['refundTotalAmount'],
                    'gateway_status'        => $instalmentcancelResponse['transaction']['status'],
                ]);

                if ($cancelType == 'CANCEL_REMAINING_CYCLES') {
                    $success = true;
                    $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_remaining'), $this->reference->getTid(), date($this->dateformatter. ' H:i:s'));
                } elseif ($cancelType == 'CANCEL_ALL_CYCLES') {
                    $formattedRefundAmount = $this->helper->amountInBiggerCurrencyUnit($instalmentcancelResponse['transaction']['refund']['amount'], $request['currency']);
                    $success = true;
                    $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_instalment_cancel_all'), $this->reference->getTid(), date($this->dateformatter. ' H:i:s'), $formattedRefundAmount);
                }

                if ((int)$ConfigurationDetails['refundTotalAmount']  >= (int)$this->reference->getAmount()) {
                        Shopware()->Modules()->Order()->setPaymentStatus($request['id'], '35', false);
                }
            }
            $this->databaseHandler->postProcess($message, ['tid' => $instalmentcancelResponse['transaction']['tid']], true);
        } else {
            $message = $this->helper->getStatusDesc($instalmentcancelResponse, $this->helper->getLanguageFromSnippet('BasicParamError'));
            $success = false;
        }

        return $this->View()->assign([
            'success' => $success,
            'message' => $message
        ]);
    }
}
