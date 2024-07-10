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

class PaymentRequest
{
    /** @var array */
    private $orderDetails;

    /** @var NovalnetHelper */
    private $helper;

    /** @var \Enlight_Components_Session_Namespace */
    protected $session;

    /** @var array */
    protected $configDetails;

    public function __construct(NovalnetHelper $helper, \Enlight_Components_Session_Namespace $session)
    {
        $this->session       = $session;
        $this->helper        = $helper;
        $this->orderDetails  = $this->session->get('sOrderVariables');
        $this->configDetails = $this->helper->getConfigurations();
    }

    /*
     * Form server parameters
     *
     * @param $uniquePaymentID
     * @param $router
     * @param $paymentName
     *
     * @return array
     */
    public function getRequestParams($uniquePaymentID, $router, $paymentName)
    {
        $data['merchant']    = $this->getMerchantDetails();
        $data['customer']    = $this->getCustomerData();
        $data['transaction'] = $this->getTransactionDetails($router, $uniquePaymentID, $paymentName);
        $data['custom']      = $this->getCustomDetails();

        if (in_array($this->orderDetails['sPayment']['name'], array('novalnetinvoiceinstalment','novalnetsepainstalment'))) {
            $data['instalment'] = $this->getInstallmentDetails();
        } elseif ($this->orderDetails['sPayment']['name'] == 'novalnetpaypal') {
            $this->paypalSheetDetails($data);
        }


        return $data;
    }

    /*
     * Form instalment details
     *
     *  @return array
     */
    public function getInstallmentDetails()
    {
        $sessionData = $this->session->offsetGet('novalnet')->getArrayCopy();
        $param['interval'] = '1m';
        $param['cycles']   = $sessionData['duration'];
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
            'lang'      => strtoupper(substr($this->helper->getLocale(), 0, 2)),
            'input1'    => 'session_id',
            'inputval1' => $this->session->offsetGet('sessionId')
        ];

        $orderId = Shopware()->Db()->fetchOne('SELECT * FROM s_order WHERE temporaryID = ? order by id desc', [$this->session->offsetGet('sessionId')]);

        // send temporary order ID to novalnet server for previous troubleshooting issues.
        if ($orderId) {
            $customDetails ['input2'] = 'temporary_order_id';
            $customDetails ['inputval2'] = $orderId;
        }

        return $customDetails;
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
     * Form transaction details
     *
     * @param $router
     * @param $uniquePaymentID
     * @param $currentPayment
     *
     * @return array
     */
    public function getTransactionDetails($router, $uniquePaymentID, $currentPayment, $isRecurring = false)
    {
        $sessionData = [];
        if (!empty($this->session->offsetGet('novalnet'))) {
            $sessionData = $this->session->offsetGet('novalnet')->getArrayCopy();
        }
        $transactionData = [
            'test_mode'      => (int) $this->configDetails[$currentPayment.'_test_mode'],
            'payment_type'   => $this->helper->getPaymentInfo()[$currentPayment],
            'amount'         => $this->helper->getAmount(),
            'currency'       => Shopware()->Shop()->getCurrency()->getCurrency(),
            'hook_url'       => $router->assemble(['action' => 'status','forceSecure' => true]),
            'system_name'    => 'Shopware',
            'system_version' => Shopware()->Config()->get('Version') . '-NN12.4.0',
            'system_ip'      => $this->helper->getIp('SERVER_ADDR')
        ];

        if (!empty($this->configDetails[$currentPayment.'_due_date'])) {
            $transactionData['due_date'] = $this->getDueDate($this->configDetails[$currentPayment.'_due_date']);
        }

        if (((in_array($currentPayment, $this->helper->getRedirectPayments())) || (in_array($currentPayment, $this->helper->getEnforcePayments()) && ($sessionData['doRedirect'] == 1 || $sessionData['doRedirect'] == 'true'))) && !$isRecurring) {
            $transactionData['return_url']   = $router->assemble(['action' => 'return','forceSecure' => true]) . '?uniquePaymentID=' . $uniquePaymentID;
            $transactionData['error_return_url'] = $router->assemble(['action' => 'cancel','forceSecure' => true]);
            if (!empty($this->configDetails[$currentPayment.'_enforcecc3D'])) {
                $transactionData['enforce_3d'] = 1;
            }
        }

        if ($this->configDetails[$currentPayment.'_shopping_type'] == '1' && $sessionData['paymentToken'] != 'new' && !empty($sessionData['paymentToken'])) {
            $transactionData['payment_data'] = ['token' => $sessionData['paymentToken'] ];
        } else {
            // parameters for SEPA payments
            if (in_array($currentPayment, array('novalnetsepa','novalnetsepaGuarantee','novalnetsepainstalment'))) {
                $transactionData['payment_data']['iban'] = strtoupper($sessionData['Iban']);
                if (!empty($sessionData['Bic']) && preg_match("/(?:CH|MC|SM|GB)/", $transactionData['payment_data']['iban'])) {
                    $transactionData['payment_data']['bic'] = strtoupper($sessionData['Bic']);
                }
            } elseif ($currentPayment === 'novalnetcc' && !empty($sessionData['panhash']) && !empty($sessionData['uniqueid'])) { // parameters for CreditCard payment
                $transactionData['payment_data']['pan_hash']  = $sessionData['panhash'];
                $transactionData['payment_data']['unique_id'] = $sessionData['uniqueid'];
            } elseif (($currentPayment === 'novalnetapplepay' || $currentPayment === 'novalnetgooglepay') && !empty($sessionData['walletToken'])) {
                $transactionData['payment_data']['wallet_token'] = $sessionData['walletToken'];
            }
        }

        if (($sessionData['saveCard'] == 1 || $sessionData['saveCard'] == true) && empty($transactionData['payment_data']['token']) && in_array($currentPayment, array('novalnetsepa','novalnetsepaGuarantee','novalnetsepainstalment', 'novalnetcc'))) {
            $transactionData['create_token'] = '1';
        }

        return $transactionData;
    }

    /*
     * Form payment token data for abo commerce plugin
     *
     * @param $data
     * @param $oneClickDetails
     *
     * @return void
     */
    public function getTokenDataForAboCommerce(&$data, $oneClickDetails)
    {
        $data['transaction']['payment_data'] = ['token' => $oneClickDetails['token'] ];
    }

    /*
     * Form customer parameters
     *
     * @return array
     */
    public function getCustomerData()
    {
        $userDetails = $this->session['sOrderVariables']['sUserData'] ? $this->session['sOrderVariables']['sUserData'] : $this->helper->getUserInfo();
        $sessionData = [];
        if (!empty($this->session->offsetGet('novalnet'))) {
            $sessionData = $this->session->offsetGet('novalnet')->getArrayCopy();
        }
        $customerData = [
            'first_name'  => $userDetails['billingaddress']['firstname'],
            'last_name'   => $userDetails['billingaddress']['lastname'],
            'email'       => $userDetails['additional']['user']['email'],
            'gender'      => (('mr' === $userDetails['additional']['user']['salutation']) ? 'm' : (('mrs' === $userDetails['additional']['user']['salutation']) ? 'f' : 'u')),
            'customer_ip' => $this->helper->getIp(),
            'customer_no' => $userDetails['additional']['user']['customernumber'],
            'tel'         => $userDetails['billingaddress']['phone'],
            'mobile'      => $userDetails['billingaddress']['phone']
        ];

        if ($userDetails['billingaddress']['company']) {
            $customerData['vat_id'] = $userDetails['billingaddress']['vatId'];
        }

        $customerData['billing'] = $this->helper->getAddressData($userDetails['billingaddress']);
        $shippingAddress         = $this->helper->getAddressData($userDetails['shippingaddress']);

        if (!empty($userDetails['additional']['state'])) {
            $customerData['billing']['state'] = $userDetails['additional']['state']['shortcode'];
        }

        if (!empty($userDetails['additional']['stateShipping'])) {
            $shippingAddress['state'] = $userDetails['additional']['stateShipping']['shortcode'];
        }

        if (!empty($sessionData['dob'])) {
            $customerData['birth_date'] = date('Y-m-d', strtotime($sessionData['dob']));
            unset($customerData['billing']['company']);
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

    /**
     * Form Due Date
     *
     * @return string
     */
    public function getDueDate($date)
    {
        return date('Y-m-d', strtotime('+'.$date.' days'));
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
        if (!empty($response['tid']) && Shopware()->Session()->offsetGet('novalnet_txn_secret') && !empty($response['status'])) {
            $tokenString = $response['tid'] . Shopware()->Session()->offsetGet('novalnet_txn_secret') . $response['status']. strrev($this->configDetails['novalnet_password']);
            $generatedChecksum = hash('sha256', $tokenString);
        }
        return $generatedChecksum;
    }

    /**
     * Generate & return the Creditcard parameters
     *
     * @return string
     */
    public function getCcIframeParams()
    {
        $userDetails = $this->helper->getUserInfo();

        $data['iframe'] = [
            'id' => 'nnIframe',
            'inline' => (int) $this->configDetails['novalnetcc_form_type'],
            'skip_auth' => 1,
            'text' => strtoupper(substr($this->helper->getLocale(), 0, 2)),
            'style' => [
                'container' => $this->configDetails['novalnetcc_standard_text'],
                'input' => $this->configDetails['novalnetcc_standard_field'],
                'label' => $this->configDetails['novalnetcc_standard_label']
            ]
        ];

        $data['customer'] = [
            'first_name' => $userDetails['billingaddress']['firstname'],
            'last_name' => $userDetails['billingaddress']['lastname'],
            'email' => $userDetails['additional']['user']['email']
        ];

        $data['customer']['billing'] = $this->helper->getAddressData($userDetails['billingaddress']);

        $data['transaction'] = [
            'amount' => $this->helper->getAmount(),
            'currency' => Shopware()->Shop()->getCurrency()->getCurrency(),
            'test_mode' => (int) $this->configDetails['novalnetcc_test_mode'],
            'enforce_3d' => (int) $this->configDetails['novalnetcc_enforcecc3D']
        ];

        $data['custom']['lang'] = strtoupper(substr($this->helper->getLocale(), 0, 2));
        $data['clientKey'] = $this->configDetails['novalnet_clientkey'];

        return $this->helper->serializeData($data);
    }

    /**
     * Generate & return the ApplePay parameters
     *
     * @param boolean $requiredFields
     * @param array $product
     *
     * @return string
     */
    public function getApplePayParams($requiredFields = false, $product = array())
    {
        $articleData = $this->helper->getCartItems();

        if (empty($articleData['displayItems'])) {
            $label = $product['articleName']. ' (1 x '. html_entity_decode(Shopware()->Shop()->getCurrency()->getSymbol()). sprintf('%0.2f', $product['price_numeric']).')';
            $cartItems[] = array('label' => $label, 'type' => 'SUBTOTAL', 'amount' => round(number_format($product['price_numeric'], 2, '.', '') * 100));
            $articleData['displayItems'] = $cartItems;
        }

        $data['clientKey'] = $this->configDetails['novalnet_clientkey'];

        $data['merchant'] = [
            'countryCode' => strtoupper(substr($this->helper->getLocale(), 3, 2))
        ];

        $data['transaction'] = [
            'amount' => !empty($articleData) ? $articleData['totalAmount'] : ($product['price_numeric'] ? round(number_format($product['price_numeric'], 2, '.', '') * 100) : ''),
            'currency' => Shopware()->Shop()->getCurrency()->getCurrency(),
            'paymentMethod' => 'APPLEPAY',
            'environment' => empty($this->configDetails['novalnetapplepay_test_mode']) ? 'PRODUCTION' : 'SANDBOX'
        ];

        $data['order'] = [
            'paymentDataPresent' => false,
            'merchantName' => $this->configDetails['novalnetapplepay_seller_name'] ? $this->configDetails['novalnetapplepay_seller_name'] : Shopware()->Config()->get('shopName'),
            'lineItems' => !empty($articleData) ? $articleData['displayItems'] : []
        ];

        $data['button'] = [
            'type'   => $this->configDetails['novalnetapplepay_button_type'],
            'style'  => $this->configDetails['novalnetapplepay_button_theme'],
            'locale' => str_replace('_', '-', $this->helper->getLocale()),
            'boxSizing' => 'border-box',
            'dimensions' => ['height' => $this->configDetails['novalnetgooglepay_button_height'], 'cornerRadius' => $this->configDetails['novalnetgooglepay_button_radius']]
        ];

        $data['custom']['lang'] = str_replace('_', '-', $this->helper->getLocale());

        if ($requiredFields) {
            $data['order']['billing'] = array('requiredFields' => array('postalAddress', 'phone'));
            $data['order']['shipping'] = array('requiredFields' => array('postalAddress', 'phone', 'email'), 'methodsUpdatedLater' => true);
        }
        return $this->helper->serializeData($data);
    }

    /**
     * Generate & return the GoolePay parameters
     *
     * @param boolean $requiredFields
     * @param array $product
     *
     * @return string
     */
    public function getGooglePayParams($requiredFields = false, $product = array())
    {
        $articleData = $this->helper->getCartItems();

        if (empty($articleData['displayItems'])) {
            $label = $product['articleName']. ' (1 x '. html_entity_decode(Shopware()->Shop()->getCurrency()->getSymbol()). sprintf('%0.2f', $product['price_numeric']).')';
            $cartItems[] = array('label' => $label, 'type' => 'SUBTOTAL', 'amount' => round(number_format($product['price_numeric'], 2, '.', '') * 100));
            $articleData['displayItems'] = $cartItems;
        }

        $data['clientKey'] = $this->configDetails['novalnet_clientkey'];

        $data['merchant'] = [
            'countryCode' => strtoupper(substr($this->helper->getLocale(), 3, 2)),
            'partnerId'   => $this->configDetails['novalnetgooglepay_merchant_id']
        ];

        $data['transaction'] = [
            'amount' => !empty($articleData) ? $articleData['totalAmount'] : 0,
            'currency' => Shopware()->Shop()->getCurrency()->getCurrency(),
            'paymentMethod' => 'GOOGLEPAY',
            'environment' => empty($this->configDetails['novalnetgooglepay_test_mode']) ? 'PRODUCTION' : 'SANDBOX',
            'enforce3d' => (bool) $this->configDetails['novalnetgooglepay_enforcecc3D']
        ];

        $data['order'] = [
            'paymentDataPresent' => false,
            'merchantName' => $this->configDetails['novalnetgooglepay_seller_name'] ? $this->configDetails['novalnetgooglepay_seller_name'] : Shopware()->Config()->get('shopName'),
            'lineItems' => !empty($articleData) ? $articleData['displayItems'] : []
        ];

        $data['button'] = [
            'type'   => $this->configDetails['novalnetgooglepay_button_type'],
            'locale' => str_replace('_', '-', $this->helper->getLocale()),
            'boxSizing' => 'fill',
            'dimensions' => ['height' => $this->configDetails['novalnetgooglepay_button_height']]
        ];

        $data['custom']['lang'] = str_replace('_', '-', $this->helper->getLocale());

        if ($requiredFields) {
            $data['order']['billing'] = array('requiredFields' => array('postalAddress', 'phone', 'email'));
            $data['order']['shipping'] = array('requiredFields' => array('postalAddress', 'phone'), 'methodsUpdatedLater' => true);
        }

        return $this->helper->serializeData($data);
    }

    /**
     * Fetch active wallet payment and return
     *
     * @param $products
     *
     * @return array
     */
    public function getActiveWalletPayments($products)
    {
        $walletPayment = [];
        $esd = 0;

        if (!empty($products['esd'])) {
            $esd = 1;
        } elseif (!empty(Shopware()->Modules()->Basket()->sGetBasket()) && !empty($products[0])) {
            foreach ($products as $product) {
                if ($product['esd']) {
                    $esd = 1;
                }
            }
        }

        if ($this->configDetails['novalnet_secret_key'] || $this->configDetails['novalnet_tariff']) {
            foreach (Shopware()->Modules()->Admin()->sGetPaymentMeans() as $payment) {
                if (in_array($payment['name'], array('novalnetapplepay', 'novalnetgooglepay'))) {
                    $walletPayment[] = empty($esd) ? $payment['name'] : (!empty($payment['esdactive']) ? $payment['name'] : '');
                }
            }
        }
        return array_filter($walletPayment);
    }

    /**
     * Built paypal lineItems to show in paypal page.
     *
     * @param array $parameters
     */
    public function paypalSheetDetails(&$parameters)
    {
        $country = $this->helper->getSelectedCountry();

        if (!empty($this->orderDetails['sBasket'])) {
            foreach ($this->orderDetails['sBasket']['content'] as $content) {
                $productType = !empty($content['esd']) ? 'digital' : 'physical';
                $parameters['cart_info']['line_items'][] = array( 'name'=> $content['articlename'], 'price' => round((float) sprintf('%0.2f', $content['netprice']) * 100), 'quantity' => $content['quantity'], 'category' => $productType );
            }
        }

        $shipping = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);

        if (Shopware()->Session()->offsetGet('sDispatch') && !empty($shipping['netto'])) {
            $parameters['cart_info']['items_shipping_price']  = round((float) sprintf('%0.2f', $shipping['netto']) * 100);
        }


        if (empty(Shopware()->Session()->offsetGet('taxFree'))) {
            foreach ($this->orderDetails['sBasket']['sTaxRates'] as $key => $taxRate) {
                $parameters['cart_info']['items_tax_price'] += round((float) sprintf('%0.2f', $taxRate) * 100);
            }
        }
    }
}
