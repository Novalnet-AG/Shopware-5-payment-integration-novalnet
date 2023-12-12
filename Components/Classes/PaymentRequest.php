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
use Shopware\Plugins\NovalPayment\Components\Classes\ArrayMapHelper;

use Shopware\Models\Shop\Template;

class PaymentRequest
{
    /** @var NovalnetHelper */
    private $helper;

    /** @var array */
    protected $configDetails;

    public function __construct(NovalnetHelper $helper)
    {
        $this->helper        = $helper;
        $this->configDetails = $this->helper->getConfigurations();
    }

    /*
     * Form server parameters
     *
     * @param boolean $getPaymentFrom
     *
     * @return array
     */
    public function getRequestParams($getPaymentFrom = false)
    {
        $sessionData = [];
        $userDetails = null;

        if (Shopware()->Container()->has('session')) {
            $sessionValue = Shopware()->Session()->offsetGet('novalnetPay');
            if (!empty($sessionValue)) {
                $sessionValue = is_array(Shopware()->Session()->offsetGet('novalnetPay')) ? Shopware()->Session()->offsetGet('novalnetPay') : Shopware()->Session()->offsetGet('novalnetPay')->getArrayCopy();
                $sessionData  = $getPaymentFrom ? [] : $sessionValue;
            }

            $userData    = Shopware()->Session()['sOrderVariables']['sUserData'];
            $GetuserData = $this->helper->getUserInfo();
            if (!empty($userData) || !empty($GetuserData)) {
                $userDetails = ! empty($userData) ? $userData : (!empty($GetuserData) ? $GetuserData : []);
            }
        }

        $data['merchant']    = $this->getMerchantDetails();
        $data['customer']    = $this->getCustomerData($userDetails, $sessionData);
        $data['transaction'] = $this->getTransactionDetails($sessionData);
        $data['custom']      = $this->getCustomDetails();


        if ($getPaymentFrom) {
            $themeId   = Shopware()->Db()->fetchOne('SELECT template_id FROM s_core_shops WHERE active = 1');
            $themeName = Shopware()->Container()->get('models')->getRepository('Shopware\Models\Shop\Template')->findOneBy(['id' => $themeId])->getTemplate();
            $data['transaction']['system_version'] = Shopware()->Config()->get('Version') .'-NN'. NOVALNET_PLUGIN_VERSION .'-NNT' .strtoupper($themeName) ;
            $data['hosted_page'] = $this->gethostedPageDetails();
        }

        if (!empty($sessionData['booking_details']['cycle'])) {
            $data['instalment'] = $this->getInstallmentDetails($sessionData);
        }

        if ($sessionData['payment_details']['type'] == 'PAYPAL') {
            $this->getCartInfo($data);
        }

        return $data;
    }

    /*
     * Form merchant parameters
     *
     * @return array
     */
    public function getMerchantDetails()
    {
        return[
            'signature' => $this->configDetails['novalnet_secret_key'],
            'tariff'    => $this->configDetails['novalnet_tariff']
        ];
    }

    /*
     * Form customer parameters
     *
     * @param $userDetails
     * @param $sessionData
     *
     * @return array
     */

    public function getCustomerData($userDetails, $sessionData = [])
    {
        $userDetails  = new ArrayMapHelper($userDetails);
        $userData     = $userDetails->getData('additional/user') ? $userDetails->getData('additional/user') : $userDetails->getData('customer') ;
        $userBilling  = $userDetails->getData('billingaddress') ? $userDetails->getData('billingaddress') : $userDetails->getData('billing');
        $userShipping = $userDetails->getData('shippingaddress') ? $userDetails->getData('shippingaddress') :$userDetails->getData('shipping');
        $userData     = new ArrayMapHelper($userData);

        $customerData = [
            'first_name'  => $userData->getData('firstname'),
            'last_name'   => $userData->getData('lastname'),
            'email'       => $userData->getData('email'),
            'gender'      => (('mr' === $userData->getData('salutation')) ? 'm' : (('mrs' === $userData->getData('salutation')) ? 'f' : 'u')),
            'customer_ip' => $this->helper->getIp(),
            'customer_no' => $userData->getData('customernumber') ? $userData->getData('customernumber') : $userData->getData('number'),
            'tel'         => $userBilling['phone'],
            'mobile'      => $userBilling['phone']
        ];

        if (!empty($userBilling['vatId'])) {
            $customerData['vat_id'] = $userBilling['vatId'];
        }

        $customerData['billing'] = $this->helper->getAddressData($userBilling);
        $shippingAddress = $this->helper->getAddressData($userShipping);

        $customerData['billing']['state'] = $userDetails->getData('additional/state/shortcode') ? $userDetails->getData('additional/state/shortcode') : $userDetails->getData('billing/state');
        $shippingAddress['state'] = $userDetails->getData('additional/stateShipping/shortcode') ? $userDetails->getData('additional/stateShipping/shortcode') : $userDetails->getData('shipping/state');

        if (!empty($sessionData['booking_details']['birth_date'])) {
            $customerData['birth_date'] = date('Y-m-d', strtotime($sessionData['booking_details']['birth_date']));
        }

        if ($customerData['billing']['street'] == $shippingAddress['street'] &&
            $customerData['billing']['city'] == $shippingAddress['city'] &&
            $customerData['billing']['zip'] == $shippingAddress['zip'] &&
            $customerData['billing']['country_code'] == $shippingAddress['country_code']
        ) {
            $customerData['shipping']['same_as_billing'] = 1;
        } else {
            $customerData['shipping'] = $shippingAddress;
        }

        return array_filter($customerData);
    }

    /*
     * Form transaction details
     *
     * @param $sessionData
     *
     * @return array
     */

    public function getTransactionDetails($sessionData = [])
    {
        $transactionData = [
            'amount'         => $this->helper->getAmount(),
            'currency'       => Shopware()->Shop()->getCurrency()->getCurrency(),
            'system_name'    => 'Shopware5',
            'system_version' => Shopware()->Config()->get('Version') . '-NN13.1.0',
            'system_ip'      => $this->helper->getIp('SERVER_ADDR')
        ];

        if (!empty($sessionData)) {
            $sessionData = new ArrayMapHelper($sessionData);

            $transactionData['payment_type'] = $sessionData->getData('payment_details/type');
            $transactionData['test_mode'] = $sessionData->getData('booking_details/test_mode');

            $paymentDataKeys = ['booking_details/pan_hash', 'booking_details/unique_id', 'booking_details/iban', 'booking_details/bic', 'booking_details/wallet_token', 'booking_details/payment_ref/token', 'booking_details/account_holder', 'booking_details/account_number', 'booking_details/routing_number'];
            foreach ($paymentDataKeys as $key) {
                if ($sessionData->getData($key)) {
                    $splitedArray = explode('/', $key);
                    $paymentDataKey = end($splitedArray);
                    $transactionData['payment_data'][$paymentDataKey] = $sessionData->getData($key);
                }
            }

            $transactionDataKeys = ['booking_details/due_date', 'booking_details/create_token', 'booking_details/enforce_3d'];
            foreach ($transactionDataKeys as $key) {
                if ($sessionData->getData($key)) {
                    $splitedArray = explode('/', $key);
                    $transactionDataKey = end($splitedArray);
                    $transactionData[$transactionDataKey] = ($transactionDataKey == 'due_date') ? date('Y-m-d', strtotime('+'. $sessionData->getData($key) .' days')) : $sessionData->getData($key);
                }
            }

            $doRedirect = $sessionData->getData('booking_details/do_redirect');
            if ($sessionData->getData('payment_details/process_mode') == 'redirect' || $doRedirect == '1' || $doRedirect == true) {
                $transactionData['return_url']   = Shopware()->Front()->Router()->assemble(['action' => 'return','forceSecure' => true]);
                $transactionData['error_return_url'] = Shopware()->Front()->Router()->assemble(['action' => 'cancel','forceSecure' => true]);
            }
        }

        return $transactionData;
    }

    /*
     * Form custom details
     *
     * @return array
     */
    public function gethostedPageDetails()
    {
        $hostedPageData  =  [
            'hide_blocks'    => ['ADDRESS_FORM', 'SHOP_INFO', 'LANGUAGE_MENU', 'TARIFF'],
            'skip_pages'     => ['CONFIRMATION_PAGE', 'SUCCESS_PAGE', 'PAYMENT_PAGE'],
            'form_version'   => 13,
            'type'           => 'PAYMENTFORM',
        ];
        $isAboProduct = $this->helper->isSubscriptionProduct();

        // payment check for abo commerce
        if ($isAboProduct) {
            $hostedPageData['display_payments_mode'] = ['SUBSCRIPTION'];
        }
        return $hostedPageData;
    }

    /*
     * Form instalment details
     *
     * @param $sessionData
     * @return array
     */
    public function getInstallmentDetails($sessionData)
    {
        $param['interval'] = '1m';
        $param['cycles']   = $sessionData['booking_details']['cycle'];
        return $param;
    }

    /*
     * Form custom details
     *
     * @return array
     */
    public function getCustomDetails()
    {
        $customDetails =  [
            'lang'      => strtoupper($this->helper->getLocale(false, true)),
            'input1'    => 'session_id',
            'inputval1' => Shopware()->Session()->offsetGet('sessionId')
        ];
        $orderId = Shopware()->Db()->fetchOne('SELECT * FROM s_order WHERE temporaryID = ? order by id desc', [Shopware()->Session()->offsetGet('sessionId')]);

        // send temporary order ID to novalnet server for previous troubleshooting issues.
        if ($orderId) {
            $customDetails ['input2'] = 'temporary_order_id';
            $customDetails ['inputval2'] = $orderId;
        }

        return $customDetails;
    }

    /**
     * Generate Check Sum Token
     *
     * @param array $response
     *
     * @return string
     */
    public function generateCheckSumToken($response)
    {
        $generatedChecksum = '';
        if (! empty($response['checksum']) && !empty($response['tid']) && Shopware()->Session()->offsetGet('novalnet_txn_secret') && !empty($response['status'])) {
            $tokenString = $response['tid'] . Shopware()->Session()->offsetGet('novalnet_txn_secret') . $response['status']. strrev($this->configDetails['novalnet_password']);
            $generatedChecksum = hash('sha256', $tokenString);
        }
        return $generatedChecksum;
    }

    /**
     * Built paypal lineItems to show in paypal page.
     *
     * @param array $parameters
     */
    public function getCartInfo(&$parameters)
    {
        $country = $this->helper->getSelectedCountry();
        $sOrderVariables = Shopware()->Session()->get('sOrderVariables');

        if (!empty($sOrderVariables) && !empty($sOrderVariables['sBasket'])) {
            foreach ($sOrderVariables['sBasket']['content'] as $content) {
                $productType = !empty($content['esd']) ? 'digital' : 'physical';
                $parameters['cart_info']['line_items'][] = array( 'name'=> $content['articlename'], 'price' => round((float) sprintf('%0.2f', $content['netprice']) * 100), 'quantity' => $content['quantity'], 'category' => $productType );
            }
        }

        $shipping  = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);
        $sDispatch = Shopware()->Session()->offsetGet('sDispatch');

        if ($sDispatch && !empty($shipping['netto'])) {
            $parameters['cart_info']['items_shipping_price']  = round((float) sprintf('%0.2f', $shipping['netto']) * 100);
        }

        $isTaxFree = Shopware()->Session()->offsetGet('taxFree');
        if (empty($isTaxFree)) {
            foreach ($sOrderVariables['sBasket']['sTaxRates'] as $key => $taxRate) {
                $parameters['cart_info']['items_tax_price'] += round((float) sprintf('%0.2f', $taxRate) * 100);
            }
        }
    }

    /**
     * Generate & return the GoolePay parameters
     *
     * @param boolean $requiredFields
     * @param array $product
     *
     * @return string
     */
    public function getWalletPaymentParams($requiredFields = false, $product = array())
    {
        $sBasket   = $this->helper->getBasket();
        $country   = $this->helper->getSelectedCountry();
        $cartItems = [];

        if (!empty($sBasket)) {
            foreach ($sBasket['content'] as $value) {
                $label = $value['articlename']. ' ('. $value['quantity']. ' x '. html_entity_decode(Shopware()->Shop()->getCurrency()->getSymbol()). sprintf('%0.2f', $value['priceNumeric']).')';
                $cartItems[] = array('label' => $label, 'type' => 'SUBTOTAL', 'amount' => round(number_format($value['amountnetNumeric'], 2, '.', '') * 100));
            }
            $shipping = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);

            if (Shopware()->Session()->offsetGet('sDispatch')) {
                $cartItems[]  = array('label' => $this->helper->getLanguageFromSnippet('frontend_shipping_cost_label'), 'type' => 'SUBTOTAL', 'amount' => round(number_format($shipping['netto'], 2, '.', '') * 100));
            }
            
            $taxFree = Shopware()->Session()->offsetGet('taxFree');
            if (empty($taxFree)) {
                foreach ($sBasket['sTaxRates'] as $key => $taxRate) {
                    $taxLabel = sprintf($this->helper->getLanguageFromSnippet('frontend_tax_label'), round($key) . ' %');
                    $label = !empty($taxLabel) ? $taxLabel : 'TAX';
                    $cartItems[]  = array('label' => $label,'type' => 'SUBTOTAL', 'amount' => round(number_format($taxRate, 2, '.', '') * 100));
                }
            }
        }
        return $this->helper->serializeData($cartItems);
    }
}
