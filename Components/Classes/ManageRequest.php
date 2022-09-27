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

use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;

class ManageRequest
{
    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var array
     */
    private $config;

    public function __construct(NovalnetHelper $helper)
    {
        $this->helper = $helper;
        $this->config = $this->helper->getConfigurations();
    }

    /*
     * server request handling process
     *
     * @param array $params
     * @param string $url
     * @param string|null $accessKey
     *
     * @return array
     */
    public function curlRequest($params, $url, $accessKey = null)
    {
        $accessKey = !is_null($accessKey) ? $accessKey : $this->config['novalnet_password'];
        // Initiate cURL
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        // Set the headers
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->SetHeader($accessKey));

        // Execute cURL
        $result = curl_exec($curl);

        // Handle cURL error
        if (curl_errno($curl)) {
            Shopware()->Container()->get('corelogger')->error("CURL Error as occured : ". curl_errno($curl));
            return $result;
        }
        curl_close($curl);

        // Decode the JSON string
        return $this->helper->unserializeData($result);
    }

    /**
     * Set header for curl call
     *
     * @param string $password
     *
     * @return array
     */
    public function SetHeader($password)
    {
        return [
            // The Content-Type should be "application/json"
            'Content-Type:application/json',

            // The charset should be "utf-8"
            'charset:utf-8',

            // Optional
            'Accept:application/json',

            // The formed authenticate value (case-sensitive)
            'WWW-Authenticate:Basic:' . base64_encode(str_replace(' ', '', $password)),
       ];
    }

    /*
     * post callback process to update order number
     *
     * @param string $orderNumber
     * @param string $tid
     *
     * @return array
     */
    public function postCallBackProcess($orderNumber, $tid)
    {
        $postbackParams = [
            'transaction' => [
                'tid'      => $tid,
                'order_no' => $orderNumber
            ],
            'custom' => [
                'lang' => strtoupper(substr($this->helper->getLocale(), 0, 2))
            ],
        ];
        $result = $this->curlRequest($postbackParams, $this->helper->getActionEndpoint('transaction_update'));
        return $result;
    }

    /*
     * Update invoice number to novalnet server for invoice & prepayment
     *
     * @param array $data
     *
     * @return array
     */
    public function updateInvoiceNumber($data)
    {
        $postbackParams = [
            'transaction' => $data,
            'custom' => [
                'lang' => strtoupper(substr($this->helper->getLocale(), 0, 2))
            ],
        ];
        $result = $this->curlRequest($postbackParams, $this->helper->getActionEndpoint('transaction_update'));
        return $result;
    }

    /*
     * Transaction retrieve call
     *
     * @param string $tid
     *
     * @return array
     */
     public function retrieveDetails($tid)
     {
         $data = [
             'transaction' => [
                 'tid'	=> $tid,
             ],
             'custom' => [
                 'lang' => strtoupper(substr($this->helper->getLocale(), 0, 2))
             ],
         ];
         $result = $this->curlRequest($data, $this->helper->getActionEndpoint('transaction_details'));
         return $result;
     }
}
