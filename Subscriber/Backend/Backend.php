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

namespace Shopware\Plugins\NovalPayment\Subscriber\Backend;

use ArrayObject;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Enlight\Event\SubscriberInterface;

class Backend implements SubscriberInterface
{
    /**
     * @var DataHandler
     */
    private $dataHandler;

    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var string
     */
    private $newLine;

    /**
     * @var ManageRequest
     */
    private $requestHandler;

    public function __construct(
        NovalnetHelper $helper,
        DataHandler $dataHandler,
        ManageRequest $requestHandler
    ) {
        $this->helper    = $helper;
        $this->dataHandler = $dataHandler;
        $this->requestHandler = $requestHandler;
        $this->newLine = '<br />';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_Backend_Order_Save' => 'onSaveBackendOrder',
            'Shopware_Controllers_Backend_Order::deletePositionAction::after' => 'onDeleteLineItem'
        ];
    }

    /**
     * Trigger refund call when position is deleted
     *
     * @param \Enlight_Hook_HookArgs $args
     */
    public function onDeleteLineItem(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $args->setProcessed(true);
        $parameters = $controller->Request()->getParams();
        $orderId    = $controller->Request()->getParam('orderID');
        $amount     = 0;
        // Get order number from database
        $orderNumber = Shopware()->Db()->fetchOne('SELECT ordernumber FROM s_order WHERE id = ?', array($orderId));

        $data = $this->dataHandler->checkTransactionExists(['orderNo' => $orderNumber]);

        if (($data->getGatewayStatus() == 'CONFIRMED' || ($data->getGatewayStatus() == 'PENDING' && in_array($data->getPaymentType(), [ 'novalnetinvoice', 'novalnetprepayment' , 'novalnetcashpayment']))) && $data->getRefundedAmount() < $data->getAmount()) {
            $amount = $parameters['total'];
            if (empty($amount) && !empty($parameters['positions'])) {
                foreach ($parameters['positions'] as $position) {
                    $amount += $position['total'];
                }
            }
            $amount = number_format($amount, 2, '.', '') * 100;

            if ($amount > 0) {
                $requestParams = [
                    'transaction'=> [
                        'tid'    => $data->getTid(),
                        'amount' => $amount,
                    ],
                    'custom' => [
                        'shop_invoked' => 1
                    ]
                ];

                $response = $this->requestHandler->curlRequest($requestParams, $this->helper->getActionEndpoint('transaction_refund'));

                if ($response['result']['status'] == 'SUCCESS') {
                    $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund'), $data->getTid(), sprintf('%.2f', $amount / 100) . ' ' . $data->getCurrency(), date('d.m.Y H:i:s'));

                    if (!empty($response['transaction']['refund']['tid'])) {
                        $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_refund1'), $data->getTid(), sprintf('%.2f', $amount / 100) . ' ' . $data->getCurrency(), date('d.m.Y H:i:s'), $response['transaction']['refund']['tid']);
                    }

                    $totalRefundedAmount = $data->getRefundedAmount() + $amount;

                    $this->dataHandler->insertNovalnetTransaction([
                        'tid' => $response['transaction']['tid'],
                        'refunded_amount' => $totalRefundedAmount
                    ]);

                    $this->dataHandler->postProcess($message, ['tid' => $data->getTid()]);
                }
            }
        }
    }

    /**
     * Do confirm (or) cancel action for payment
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onSaveBackendOrder(\Enlight_Event_EventArgs $args)
    {
        $subject = $args->get('subject');
        $request = $subject->Request();
        $postData = $request->getParams();

        if (isset($postData['payment'][0]['name']) && strpos($postData['payment'][0]['name'], 'novalnet') !== false) {
            $data = $this->dataHandler->checkTransactionExists(['tid' => $postData['transactionId']]);

            if (!empty($data) && $data->getGatewayStatus() == 'ON_HOLD' && in_array($postData['cleared'], array(35, 9, 10, 11, 12, 17))) {
                if ($postData['cleared'] != 35) {
                    $endpoint = $this->helper->getActionEndpoint('transaction_capture');
                } else {
                    $endpoint = $this->helper->getActionEndpoint('transaction_cancel');
                }

                $params = [
                    'transaction'=> [
                        'tid'    => $postData['transactionId'],
                    ],
                    'custom'	=> [
                        'shop_invoked' => 1
                    ]
                ];

                $captureResponse = $this->requestHandler->curlRequest($params, $endpoint);

                $appendComments = true;
                $serializedData = [];
                $paidAmount = 0;
                if ($captureResponse['result']['status'] == 'SUCCESS') {
                    if (in_array($captureResponse['transaction']['status'], ['CONFIRMED', 'PENDING'])) {
                        $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_amount_capture'), date('d.m.Y'), date(' H:i:s'));

                        if (in_array($data->getPaymentType(), ['novalnetinvoiceinstalment', 'novalnetsepainstalment', 'novalnetinvoiceGuarantee', 'novalnetinvoice'])) {
                            $appendComments = false;
                            $captureResponse['transaction']['bank_details'] = $this->helper->unserializeData($data->getConfigurationDetails());
                            $message .= $this->newLine . $this->newLine . $this->helper->formCustomerComments(array_filter($captureResponse), $data->getPaymentType(), $captureResponse['transaction']['currency']);
                            if (! empty($captureResponse['instalment']['cycles_executed'])) {
                                $serializedData = $this->helper->getInstalmentInformation($captureResponse);
                                $serializedData = $this->helper->serializeData($serializedData);
                            }
                        }

                        if ($captureResponse['transaction']['status'] == 'CONFIRMED') {
                            $paidAmount = $data->getAmount();
                        }
                    } else {
                        $message = sprintf($this->helper->getLanguageFromSnippet('frontend_novalnet_deactivated_message'), date('Y-m-d'), date('H:i:s'));
                    }

                    $comment = '<br />' . $message;
                    $comment = str_replace('<br />', PHP_EOL, $comment);


                    if (!empty($appendComments)) {
                        $request->setParam('customerComment', $postData['customerComment'] . $comment);
                    } else {
                        $request->setParam('customerComment', $comment);
                    }

                    $this->dataHandler->insertNovalnetTransaction(array_filter([
                        'tid' => $postData['transactionId'],
                        'gateway_status' => $captureResponse['transaction']['status'],
                        'paid_amount' => $paidAmount,
                        'configuration_details' => $serializedData
                    ]));
                }
            }
        }
    }
}
