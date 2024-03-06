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

use Enlight_Components_Snippet_Manager;
use Shopware\Bundle\AccountBundle\Form\Account\AddressFormType;
use Shopware\Bundle\AccountBundle\Form\Account\PersonalFormType;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Cart\Struct\DiscountContext;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Plugins\NovalPayment\Components\Classes\ArrayMapHelper;
use Shopware\Models\Shop\Locale;
use Shopware\Models\Shop\Shop;
use Shopware\Models\Order\Order;

define("NOVALNET_PAYMENT_NAME", "novalnetpay");
define("NOVALNET_ON_HOLD_STATUS", "ON_HOLD");
define("NOVALNET_PENDING_STATUS", "PENDING");
define("NOVALNET_CONFIRMED_STATUS", "CONFIRMED");
define("NOVALNET_DEACTIVATED_STATUS", "DEACTIVATED");
define("NOVALNET_PLUGIN_VERSION", "13.2.0");

class NovalnetHelper
{
    /**
     * @var string
     */
    private $endpoint = 'https://payport.novalnet.de/v2/';

    /**
     * @var Container
     */
    private $container;

    /**
     * @var Enlight_Components_Snippet_Manager
     */
    private $snippetManager;

    /**
     * @var array
     */
    public $onholdStatus = [ '91', '98', '99', '85'];

    /**
     * @var array
     */
    public $pendingStatus = [ '75', '86', '90'];

    /**
     * @var string
     */
    private $newLine;

    /**
     * Intialize the helper class.
     *
     * @param Container $container
     * @param Enlight_Components_Snippet_Manager $snippetManager
     */
    public function __construct(
        Container $container,
        Enlight_Components_Snippet_Manager $snippetManager
    ) {
        $this->container = $container;
        $this->snippetManager = $snippetManager;
        $this->newLine = '<br />';
    }

    /**
     * Load plugin configuration
     *
     * @return array
     */
    public function getConfigurations()
    {
        $shop = null;

        if ($this->container->has('shop')) {
            $shop = $this->container->get('shop');
        }

        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('NovalPayment', $shop);

        $tariff = explode('-', explode('(', $config['novalnet_tariff'])[1]);

        $config['novalnet_tariff'] = $tariff[0];

        return $config;
    }

    /**
     * Get current shop id
     *
     * @param string $parameter
     *
     * @return int
     */
    public function getShopId($parameter)
    {
        $shopId = 1;
        if (!empty($parameter) && preg_match_all("/\[[^\]]*\]/", $parameter, $matches)) {
            foreach ($matches as $shop) {
                $shopId = trim($shop[0], '[]');
            }
        }

        return (int) $shopId;
    }

    /**
     * Get shop frontend/backend locale
     *
     * @param bool $backend
     *
     * @return string
     */
    public function getLocale($backend = false, $needSubstr = false)
    {
        if ($this->container->has('shop')) {
            $locale = $this->container->get('shop')->getLocale()->getLocale();
        } elseif ($backend) {
            $locale = $this->container->get('auth')->getIdentity()->locale->getLocale();
        } else {
            $locale = $this->container->get('locale');
        }

        if ($needSubstr) {
            $locale = substr($locale, 0, 2);
        }

        return (string) $locale;
    }

    /**
     * Perform unserialize data.
     *
     * @param string|null $data
     * @param bool $needAsArray
     *
     * @return array|null
     */
    public function unserializeData($data, $needAsArray = true)
    {
        if (empty($data)) {
            return [];
        }
        $result = json_decode($data, $needAsArray, 512, JSON_BIGINT_AS_STRING);

        if (json_last_error() === 0) {
            return $result;
        }

        return ! empty($result) ? $result : [];
    }

    /**
     * Get action URL
     *
     * @param string $action
     *
     * @return string
     */
    public function getActionEndpoint($action = null)
    {
        return $this->endpoint . str_replace('_', '/', $action);
    }

    /**
     * To get payment type
     *
     * @param string $paymentKey
     * @return string
     */
    public function getPaymentType($paymentKey)
    {
        $paymentTypes =  [
            'novalnetsepa'              => 'DIRECT_DEBIT_SEPA',
            'novalnetcc'                => 'CREDITCARD',
            'novalnetapplepay'          => 'APPLEPAY',
            'novalnetinvoice'           => 'INVOICE',
            'novalnetgooglepay'         => 'GOOGLEPAY',
            'novalnetprepayment'        => 'PREPAYMENT',
            'novalnetinvoiceGuarantee'  => 'GUARANTEED_INVOICE',
            'novalnetsepaGuarantee'     => 'GUARANTEED_DIRECT_DEBIT_SEPA',
            'novalnetideal'             => 'IDEAL',
            'novalnetinstant'           => 'ONLINE_TRANSFER',
            'novalnetgiropay'           => 'GIROPAY',
            'novalnetcashpayment'       => 'CASHPAYMENT',
            'novalnetprzelewy24'        => 'PRZELEWY24',
            'novalneteps'               => 'EPS',
            'novalnetinvoiceinstalment' => 'INSTALMENT_INVOICE',
            'novalnetsepainstalment'    => 'INSTALMENT_DIRECT_DEBIT_SEPA',
            'novalnetpaypal'            => 'PAYPAL',
            'novalnetpostfinancecard'   => 'POSTFINANCE_CARD',
            'novalnetpostfinance'       => 'POSTFINANCE',
            'novalnetbancontact'        => 'BANCONTACT',
            'novalnetmultibanco'        => 'MULTIBANCO',
            'novalnetonlinebanktransfer' => 'ONLINE_BANK_TRANSFER',
            'novalnetalipay'            => 'ALIPAY',
            'novalnetwechatpay'         => 'WECHATPAY',
            'novalnettrustly'           => 'TRUSTLY'
        ];

        return (!empty($paymentTypes[$paymentKey])) ? $paymentTypes[$paymentKey] : $paymentKey;
    }

     /**
     * Get proper Status Text
     *
     * @param mixed $status
     * @param mixed $order
     * @param string $paymentType
     * @return string
     */
    public function getStatus($status, $order, $paymentType)
    {
        if (is_numeric($status) == true) {
            if (in_array($status, $this->onholdStatus)) {
                $status = NOVALNET_ON_HOLD_STATUS ;
            } elseif (in_array($status, $this->pendingStatus)) {
                 $status = NOVALNET_PENDING_STATUS;
            } elseif ($status == '100') {
                if (in_array(
                    $paymentType,
                    [
                        'INVOICE',
                        'PREPAYMENT',
                        'CASHPAYMENT'
                    ]
                )) {
                    if (is_array($order)) {
                        $paidAmount  = $order['paid_amount'];
                        $orderAmount = $order['amount'];
                    } else {
                        $paidAmount  = $order->getpaidAmount();
                        $orderAmount = $order->getAmount();
                    }

                    if ($paidAmount >= $orderAmount) {
                        $status = NOVALNET_CONFIRMED_STATUS;
                    } else {
                        $status = NOVALNET_PENDING_STATUS;
                    }
                } else {
                    $status = NOVALNET_CONFIRMED_STATUS;
                }
            } elseif ($status == '103') {
                $status = NOVALNET_DEACTIVATED_STATUS;
            } else {
                $status = 'FAILURE';
            }
        }

        return $status;
    }

    /**
     * Get user details from shop
     *
     * @return array
     */
    public function getUserInfo()
    {
        $system = Shopware()->System();
        $userData = Shopware()->Modules()->Admin()->sGetUserData();

        if (!empty($userData['additional']['countryShipping'])) {
            $system->sUSERGROUPDATA = Shopware()->Db()->fetchRow('
                SELECT * FROM s_core_customergroups
                WHERE groupkey = ?
            ', [$system->sUSERGROUP]);

            $taxFree = $this->isTaxFreeDelivery($userData);
            Shopware()->Session()->offsetSet('taxFree', $taxFree);

            if ($taxFree) {
                $system->sUSERGROUPDATA['tax'] = 0;
                $system->sCONFIG['sARTICLESOUTPUTNETTO'] = 1; // Old template
                Shopware()->Session()->offsetSet('sUserGroupData', $system->sUSERGROUPDATA);
                $userData['additional']['charge_vat'] = false;
                $userData['additional']['show_net'] = false;
                Shopware()->Session()->offsetSet('sOutputNet', true);
            } else {
                $userData['additional']['charge_vat'] = true;
                $userData['additional']['show_net'] = !empty($system->sUSERGROUPDATA['tax']);
                Shopware()->Session()->offsetSet('sOutputNet', empty($system->sUSERGROUPDATA['tax']));
            }
        }

        return $userData;
    }

    /**
     * Get ip address
     *
     * @param string $type
     *
     * @return string
     */
    public function getIp($type = 'REMOTE_ADDR')
    {
        // Check to determine the IP address type
        if ($type == 'SERVER_ADDR') {
            if (empty($_SERVER['SERVER_ADDR'])) {
                // Handled for IIS server
                $ipAddress = gethostbyname($_SERVER['SERVER_NAME']);
            } else {
                $ipAddress = $_SERVER['SERVER_ADDR'];
            }
        } else { // For remote address
            $ipAddress = $this->getRemoteAddress();
        }

        return $ipAddress;
    }

    /**
     * Get user remote ip address
     *
     * @return string|null
     */
    public function getRemoteAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    // trim for safety measures
                    return trim($ip);
                }
            }
        }
    }

    /**
     * Get country code from user address
     *
     * @param array $datas
     *
     * @return array
     */
    public function getAddressData($datas)
    {
        $countryId    = ! empty($datas['countryId']) ? $datas['countryId'] : (! empty($datas['country_id']) ? $datas['country_id'] : $datas['countryID']) ;
        $countryCode  = ! empty($datas['country']['iso']) ? $datas['country']['iso'] : Shopware()->Db()->fetchOne('SELECT countryiso FROM s_core_countries WHERE  id = ?', [$countryId]);
        $data = [
            'street'    => $datas['street'],
            'city'      => $datas['city'],
            'zip'       => ! empty($datas['zipcode']) ? $datas['zipcode'] : $datas['zipCode'] ,
            'country_code' => $countryCode,
            ];
        if ($datas['company']) {
            $data['company'] = $datas['company'];
        }
        return $data;
    }

    /**
     * Get Order Amount
     */
    public function getAmount()
    {
        $basket = $this->getBasket();
        $taxFree = Shopware()->Session()->offsetGet('taxFree');

        if (empty($taxFree)) {
            $amount = !empty($basket['AmountWithTaxNumeric']) ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];
            return round((number_format($amount, 2, '.', '')) * 100);
        }

        return number_format($basket['AmountNetNumeric'], 2, '.', '') * 100;
    }

    /**
     * Return complete basket data to view
     * Basket items / Shippingcosts / Amounts / Tax-Rates
     *
     * @return array
     */
    public function getBasket()
    {
        $shippingCosts = $this->getShippingCosts();
        $basket = Shopware()->Modules()->Basket()->sGetBasket();

        /** @var \Shopware\Models\Shop\Currency $currency */
        $currency = Shopware()->Shop()->getCurrency();
        $hasDifferentTaxes = $positions = '';

        if (version_compare(Shopware()->Config()->version, '5.5.0', '>=')) {
            $positions = Shopware()->Container()->get('shopware.cart.basket_helper')->getPositionPrices(
                new DiscountContext(
                    Shopware()->Session()->get('sessionId'),
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null
                )
            );

            $taxCalculator = Shopware()->Container()->get('shopware.cart.proportional_tax_calculator');
            $hasDifferentTaxes = $taxCalculator->hasDifferentTaxes($positions);
        }

        $basket['sCurrencyId'] = $currency->getId();
        $basket['sCurrencyName'] = $currency->getCurrency();
        $basket['sCurrencyFactor'] = $currency->getFactor();

        if ($hasDifferentTaxes && empty($shippingCosts['taxMode']) && !Shopware()->Session()->get('taxFree')) {
            $taxProportional = $taxCalculator->calculate($shippingCosts['brutto'], $positions, false);

            $basket['sShippingcostsTaxProportional'] = $taxProportional;

            $shippingNet = 0;

            foreach ($taxProportional as $shippingProportional) {
                $shippingNet += $shippingProportional->getNetPrice();
            }

            $basket['sShippingcostsWithTax'] = $shippingCosts['brutto'];
            $basket['sShippingcostsNet'] = $shippingNet;
            $basket['sShippingcostsTax'] = $shippingCosts['tax'];

            $shippingCosts['netto'] = $shippingNet;
        } else {
            $basket['sShippingcostsWithTax'] = $shippingCosts['brutto'];
            $basket['sShippingcostsNet'] = $shippingCosts['netto'];
            $basket['sShippingcostsTax'] = $shippingCosts['tax'];
        }

        if (!empty($shippingCosts['brutto'])) {
            $basket['AmountNetNumeric'] += $shippingCosts['netto'];
            $basket['AmountNumeric'] += $shippingCosts['brutto'];
            $basket['sShippingcostsDifference'] = $shippingCosts['difference']['float'];
        }
        if (!empty($basket['AmountWithTaxNumeric'])) {
            $basket['AmountWithTaxNumeric'] += $shippingCosts['brutto'];
        }

        if (!Shopware()->System()->sUSERGROUPDATA['tax'] && Shopware()->System()->sUSERGROUPDATA['id']) {
            $basket['sTaxRates'] = $this->getTaxRates($basket);

            $basket['sShippingcosts'] = $shippingCosts['netto'];
            $basket['sAmount'] = round($basket['AmountNetNumeric'], 2);
            $basket['sAmountTax'] = round($basket['AmountWithTaxNumeric'] - $basket['AmountNetNumeric'], 2);
            $basket['sAmountWithTax'] = round($basket['AmountWithTaxNumeric'], 2);
        } else {
            $basket['sTaxRates'] = $this->getTaxRates($basket);

            $basket['sShippingcosts'] = $shippingCosts['brutto'];
            $basket['sAmount'] = $basket['AmountNumeric'];

            $basket['sAmountTax'] = round($basket['AmountNumeric'] - $basket['AmountNetNumeric'], 2);
        }
        return $basket;
    }

    /**
     * Get shipping costs as an array (brutto / netto) depending on selected country / payment
     *
     * @return array
     */
    public function getShippingCosts()
    {
        $sCountry        = Shopware()->Session()->get('sCountry');
        $sGetCountry     = Shopware()->Modules()->Admin()->sGetCountry(Shopware()->Session()->get('sCountry'));
        $selectedCountry = $this->getSelectedCountry();
        $country         = ! empty($sCountry) ? $sGetCountry : $selectedCountry;
        $payment         = Shopware()->Session()->get('sPaymentID');

        if (empty($country) || empty($payment)) {
            return ['brutto' => 0, 'netto' => 0];
        }
        $shippingcosts = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);
        return empty($shippingcosts) ? ['brutto' => 0, 'netto' => 0] : $shippingcosts;
    }

    /**
     * Returns tax rates for all basket positions
     *
     * @param array $basket array
     *
     * @return array
     */
    public function getTaxRates($basket)
    {
        $result = [];

        if (!empty($basket['sShippingcostsTax'])) {
            if (!empty($basket['sShippingcostsTaxProportional'])) {
                /** @var \Shopware\Components\Cart\Struct\Price $shippingTax */
                foreach ($basket['sShippingcostsTaxProportional'] as $shippingTax) {
                    $result[number_format($shippingTax->getTaxRate(), 2)] += $shippingTax->getTax();
                }
            } else {
                $basket['sShippingcostsTax'] = number_format((float) $basket['sShippingcostsTax'], 2);

                $result[$basket['sShippingcostsTax']] = $basket['sShippingcostsWithTax'] - $basket['sShippingcostsNet'];
                if (empty($result[$basket['sShippingcostsTax']])) {
                    unset($result[$basket['sShippingcostsTax']]);
                }
            }
        }

        if (empty($basket['content'])) {
            ksort($result, SORT_NUMERIC);

            return $result;
        }

        foreach ($basket['content'] as $item) {
            if (!empty($item['tax_rate'])) {
            } elseif (!empty($item['taxPercent'])) {
                $item['tax_rate'] = $item['taxPercent'];
            } elseif ($item['modus'] == 2) {
                $resultVoucherTaxMode = Shopware()->Db()->fetchOne(
                    'SELECT taxconfig FROM s_emarketing_vouchers WHERE ordercode=?',
                    [$item['ordernumber']]
                );

                $tax = null;

                // Old behaviour
                if (empty($resultVoucherTaxMode) || $resultVoucherTaxMode === 'default') {
                    $tax = Shopware()->Config()->get('sVOUCHERTAX');
                } elseif ($resultVoucherTaxMode === 'auto') {
                    // Automatically determinate tax
                    $tax = Shopware()->Modules()->Basket()->getMaxTax();
                } elseif ($resultVoucherTaxMode === 'none') {
                    // No tax
                    $tax = '0';
                } elseif ((int) $resultVoucherTaxMode) {
                    // Fix defined tax
                    $tax = Shopware()->Db()->fetchOne('
                    SELECT tax FROM s_core_tax WHERE id = ?
                    ', [$resultVoucherTaxMode]);
                }
                $item['tax_rate'] = $tax;
            } else {
                $taxAutoMode = Shopware()->Config()->get('sTAXAUTOMODE');
                if (!empty($taxAutoMode)) {
                    $tax = Shopware()->Modules()->Basket()->getMaxTax();
                } else {
                    $tax = Shopware()->Config()->get('sDISCOUNTTAX');
                }
                $item['tax_rate'] = $tax;
            }

            if (empty($item['tax_rate']) || empty($item['tax'])) {
                continue;
            } // Ignore 0 % tax

            $taxKey = number_format((float) $item['tax_rate'], 2);

            $result[$taxKey] += str_replace(',', '.', $item['tax']);
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * Get language from snippet namespace
     *
     * @param string $snippetName
     * @param string $namespace
     *
     * @return \Shopware_Components_Snippet_Manager|string
     */
    public function getLanguageFromSnippet($snippetName, $ordernumber = null, $namespace = 'frontend/novalnet/payment')
    {
        if (! empty($ordernumber)) {
            $orderShopId = Shopware()->db()->fetchOne('SELECT language from s_order WHERE ordernumber = ?', [(string)$ordernumber]);
            $orderLanguageId = Shopware()->db()->fetchOne('SELECT locale_id from s_core_shops WHERE id = ?', [$orderShopId]);

            $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');
            $localeModel = $shopRepository->findOneBy(array('id' => $orderLanguageId));

            $orderLang = strtolower(substr($localeModel->getLocale(), 0, 2));

            if (! empty($orderLang) && ! in_array($orderLang, ['de','en'])) {
                $localeModel = $shopRepository->findOneBy(['locale' => 'de_DE']);
            }

            if (! empty($localeModel)) {
                $this->snippetManager->setLocale($localeModel);
            }
        }

        /** @var \Shopware_Components_Snippet_Manager $message */
        $message = $this->snippetManager->getNamespace($namespace)->get($snippetName);

        return !empty($message) ? $message : $snippetName;
    }

    /**
     * Get the server status text
     *
     * @param array $response
     * @param string $message
     * @return string
     */
    public function getStatusDesc($response, $message = null)
    {
        return $this->setHtmlEntity(((isset($response['result']['status_text'])) ? $response['result']['status_text'] : $message), 'decode');
    }

    /*
     * Custom comments for transaction
     *
     * @param $novalnetResponse
     * @param $payment
     * @param $novalnetResponse
     *
     * @return string
     */
    public function formCustomerComments($novalnetResponse, $currency)
    {
        $comments              = '';
        $novalnetResponse      = new ArrayMapHelper($novalnetResponse);
        $nnPaymentType         = $novalnetResponse->getData('transaction/payment_type');
        $nnGatewayStatus       = $novalnetResponse->getData('transaction/status');
        $instalmentCycleAmount = $novalnetResponse->getData('instalment/cycle_amount');
        $cardBrand             = $novalnetResponse->getData('transaction/payment_data/card_brand');
        $bankDetails           = $novalnetResponse->getData('transaction/bank_details');
        $dueDate               = $novalnetResponse->getData('transaction/due_date');
        $nnNearestStores       = $novalnetResponse->getData('transaction/nearest_stores');
        $transactionAmount     = ! empty($instalmentCycleAmount) ? $instalmentCycleAmount : $novalnetResponse->getData('transaction/amount');

        $amountInBiggerCurrencyUnit = $this->amountInBiggerCurrencyUnit($transactionAmount, $novalnetResponse->getData('transaction/currency'));

        if ($nnPaymentType == 'GOOGLEPAY' && !empty($cardBrand)) {
            $comments .= sprintf($this->getLanguageFromSnippet('googlePayCardInfo'), strtolower($cardBrand), $novalnetResponse->getData('transaction/payment_data/last_four')). $this->newLine;
        }

        if ($novalnetResponse->getData('transaction/payment_name')) {
            $comments .= $novalnetResponse->getData('transaction/payment_name') . $this->newLine;
        }

        $comments .= $this->getLanguageFromSnippet('tidLabel') . $novalnetResponse->getData('transaction/tid') . $this->newLine;

        if ($novalnetResponse->getData('transaction/test_mode')) {
            $comments .= $this->getLanguageFromSnippet('testOrderText') . $this->newLine;
        }

        if ($nnGatewayStatus === NOVALNET_PENDING_STATUS && (preg_match("/GUARANTEED/i", $nnPaymentType) || preg_match("/INSTALMENT/i", $nnPaymentType) ) && preg_match("/INVOICE/i", $nnPaymentType)) {
            $comments .= $this->getLanguageFromSnippet('guaranteePendingText') . $this->newLine;
        }

        if ($nnGatewayStatus === NOVALNET_PENDING_STATUS && ( preg_match("/GUARANTEED/i", $nnPaymentType) || preg_match("/INSTALMENT/i", $nnPaymentType) )  && preg_match("/DIRECT_DEBIT_SEPA/i", $nnPaymentType)) {
            $comments .= $this->getLanguageFromSnippet('sepaGuaranteePendingText') . $this->newLine;
        }

        if (!empty($bankDetails) && (! preg_match("/DIRECT_DEBIT_SEPA/i", $nnPaymentType)) &&
            !( $nnGatewayStatus == NOVALNET_PENDING_STATUS &&  (preg_match("/GUARANTEED/i", $nnPaymentType) ||  preg_match("/INSTALMENT/i", $nnPaymentType)) )
        ) {
            if (in_array($nnGatewayStatus, [NOVALNET_CONFIRMED_STATUS, NOVALNET_PENDING_STATUS]) && ! empty($dueDate)) {
                $comments .= sprintf($this->getLanguageFromSnippet('invoicePaymentBankText'), $amountInBiggerCurrencyUnit, date('d.m.Y', strtotime($dueDate))) . $this->newLine. $this->newLine;
            } else {
                $comments .= sprintf($this->getLanguageFromSnippet('invoicePaymentBankOnholdText'), $amountInBiggerCurrencyUnit) . $this->newLine. $this->newLine;
            }

            $nnBankDataText = [
                'account_holder' => $this->getLanguageFromSnippet('accountOwner'),
                'bank_name'      => $this->getLanguageFromSnippet('bankName'),
                'bank_place'     => $this->getLanguageFromSnippet('bankPlace'),
                'iban'           => $this->getLanguageFromSnippet('bankIban'),
                'bic'            => $this->getLanguageFromSnippet('bankBic'),
            ];

            foreach ($nnBankDataText as $key => $text) {
                if (! empty($bankDetails[$key])) {
                    $comments .= $text . $bankDetails[$key] . $this->newLine;
                }
            }

            $comments .= $this->newLine . $this->getLanguageFromSnippet('multipleReferenceText') . $this->newLine. $this->newLine;
            $comments .= $this->getLanguageFromSnippet('referenceText1') . 'TID ' . '&nbsp;' . $novalnetResponse->getData('transaction/tid');

            if ($novalnetResponse->getData('transaction/invoice_ref')) {
                $comments .= $this->newLine . $this->getLanguageFromSnippet('referenceText2') . $novalnetResponse->getData('transaction/invoice_ref');
            }
        }

        if (preg_match("/INSTALMENT/i", $nnPaymentType) && $nnGatewayStatus == NOVALNET_CONFIRMED_STATUS) {
            $comments .= $this->newLine . $this->formInstalmentComments($novalnetResponse->getData(), $amountInBiggerCurrencyUnit);
        }

        if (!empty($nnNearestStores)) {
            if (!empty($dueDate)) {
                $comments .= $this->getLanguageFromSnippet('slipExpiryDate') . ': ' . date('d.m.Y', strtotime($dueDate)) . $this->newLine;
            }

            $comments .= $this->newLine . $this->getLanguageFromSnippet('cashPaymentStores') . $this->newLine;

            foreach ($nnNearestStores as $nearestStore) {
                $storesAddressData = ['store_name','street','city','zip'];
                foreach ($storesAddressData as $addressData) {
                    if (!empty($nearestStore[$addressData])) {
                        $comments .= $nearestStore[$addressData] . $this->newLine;
                    }
                }

                if (!empty($nearestStore['country_code'])) {
                    $countryName    = Shopware()->Db()->fetchOne('SELECT countryname FROM s_core_countries WHERE  id = ?', array($nearestStore['country_code']));
                    $comments .= $countryName . $this->newLine;
                }
            }
        }

        if ($novalnetResponse->getData('transaction/partner_payment_reference')) {
            $comments .= $this->newLine . sprintf($this->getLanguageFromSnippet('multibancoReferenceText'), $amountInBiggerCurrencyUnit) . $this->newLine;
            $comments .= $this->getLanguageFromSnippet('referenceText1') . $novalnetResponse->getData('transaction/partner_payment_reference');
        }

        if ($novalnetResponse->getData('transaction/payment_action') == 'zero_amount') {
            $comments .= $this->newLine . $this->getLanguageFromSnippet('frontend_novalnet_booking_process_message') . $this->newLine;
        }

        $note = $this->setHtmlEntity($comments, 'decode');
        return $note;
    }

    /**
     * Converting given amount into bigger unit
     *
     * @param string $amount
     * @param string $currency
     *
     * @return string
     */
    public function amountInBiggerCurrencyUnit($amount, $currency = '')
    {
        $formatedAmount = number_format($amount / 100, 2, '.', '');
        $container      = Shopware()->Container()->get('currency');
        if (empty($currency)) {
            $currency = Shopware()->Shop()->getCurrency()->getSymbol();
        }
        $formattedValue = $container->toCurrency($formatedAmount, array('currency' => $currency));
        return $formattedValue;
    }

    /**
     * Return the server error text
     *
     * @param string $str
     * @param string $type
     * @return string
     */
    public function setHtmlEntity($str, $type = 'encode')
    {
        if (!is_string($str)) {
            throw new \Exception('Invalid encoding specified');
        }
        return ($type == 'encode') ? htmlentities($str) : html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Form instalment detail comments
     *
     * @param array $response
     * @param string $amount
     *
     * @return string
     */
    public function formInstalmentComments($response, $amount)
    {
        $comments = $this->newLine . $this->getLanguageFromSnippet('instalmentInformation') . $this->newLine;
        $comments .= $this->getLanguageFromSnippet('instalmentProcessed') . $response['instalment']['cycles_executed'] . $this->newLine;
        $comments .= $this->getLanguageFromSnippet('instalmentDue') . (! empty($response['instalment']['pending_cycles']) ? $response['instalment']['pending_cycles'] : '0') .$this->newLine;

        if ($response['instalment']['next_cycle_date']) {
            $comments .= $this->getLanguageFromSnippet('instalmentNext') . date('d.m.Y', strtotime($response['instalment']['next_cycle_date'])) . $this->newLine;
        }

        $comments .= $this->getLanguageFromSnippet('instalmentCycleAmount') . $amount . $this->newLine;

        return $comments;
    }

    /**
     * Form instalment information.
     *
     * @param array $response
     *
     * @return array
     */
    public function getInstalmentInformation(array $response)
    {
        $instalmentData = $response['instalment'];
        $additionalDetails = [];
        sort($instalmentData['cycle_dates']);

        foreach ($instalmentData['cycle_dates'] as $cycle => $futureInstalmentDate) {
            $cycle = $cycle + 1;
            $additionalDetails['InstalmentDetails'][$cycle] = [
                'amount'        => $instalmentData['cycle_amount'],
                'cycleDate'     => ! empty($futureInstalmentDate) ? date('Y-m-d', strtotime($futureInstalmentDate)) : '',
                'cycleExecuted' => ($cycle == 1 && !empty($instalmentData['cycles_executed'])) ? $instalmentData['cycles_executed'] : '',
                'dueCycles'     => ($cycle == 1 && !empty($instalmentData['pending_cycles'])) ? $instalmentData['pending_cycles'] : '',
                'paidDate'      => ($cycle == 1) ? date('Y-m-d') : '',
                'status'        => ($cycle == 1) ? $this->getLanguageFromSnippet('paidMsg') : $this->getLanguageFromSnippet('pendingMsg'),
                'reference'     => ($cycle == 1) ? $response['transaction']['tid'] : '',
                'refundedAmount'=> '0',
            ];

            if ($cycle == count($instalmentData['cycle_dates'])) {
                $amount = abs($response['transaction']['amount'] - ($instalmentData['cycle_amount'] * ($cycle - 1)));
                $additionalDetails['InstalmentDetails'][$cycle]['amount'] = (int)$amount;
            }
        }

        return $additionalDetails;
    }

    /**
     * Perform serialize data.
     *
     * @param array $data
     *
     * @return string
     */
    public function serializeData(array $data)
    {
        $result = '{}';

        if (! empty($data)) {
            $result = json_encode($data, JSON_UNESCAPED_SLASHES);
        }
        return $result;
    }

    /*
     * unset all session data
     *
     * @return void
     */
    public function unsetSession()
    {
        foreach (['novalnet_txn_secret', 'serverResponse', 'novalnetPay', 'sComment', 'merchant_details'] as $key) {
            Shopware()->Session()->offsetUnset($key);
        }
    }

    /**
     * Return the selected country.
     *
     * @return array
    */
    public function getSelectedCountry()
    {
        $countries = Shopware()->Modules()->Admin()->sGetCountryList();
        return ! empty($countries) ? reset($countries) : false;
    }

    /**
     * Validates if the provided customer should get a tax free delivery
     *
     * @param array $userData
     *
     * @return bool
     */
    public function isTaxFreeDelivery($userData)
    {
        if (!empty($userData['additional']['countryShipping']['taxfree'])) {
            return true;
        }

        if (empty($userData['additional']['countryShipping']['taxfree_ustid'])) {
            return false;
        }

        if (empty($userData['shippingaddress']['ustid'])
            && !empty($userData['billingaddress']['ustid'])
            && !empty($userData['additional']['country']['taxfree_ustid'])) {
            return true;
        }

        return !empty($userData['shippingaddress']['ustid']);
    }

     /*
     *  Update Instalment configuration details to insert in the Novalnet transaction table
     *
     * @param array $configDetails
     * @param string $cancelType
     * @param string $refundReference
     *
     * @return array
     */
    public function updateConfigurationDetails($configDetails, $cancelType = null, $refundReference = null)
    {
        $InstalmentRefundedAmount = 0;

        if (!empty($configDetails['InstalmentDetails'])) {
            $instalmentInfo = [];
            foreach ($configDetails['InstalmentDetails'] as $instalmentKey => $instalmentValue) {
                if (is_array($instalmentValue)) {
                    if ($refundReference != null) {
                        $refundedAmount = ($refundReference['tid'] == $instalmentValue['reference']) ? $instalmentValue['refundedAmount'] += $refundReference['toBeRefund'] : $instalmentValue['refundedAmount'];
                         $InstalmentRefundedAmount = $refundReference['refundedAmount'] + $refundReference['toBeRefund'];
                    }

                    if (in_array($cancelType, [ 'CANCEL_ALL_CYCLES','ALL_CYCLES']) || ( $instalmentValue['amount'] > 0 && $instalmentValue['refundedAmount'] >= $instalmentValue['amount'] )) {
                        if (! empty($instalmentValue['reference'])) {
                             $status = $this->getLanguageFromSnippet('refundedMsg');
                             $refundedAmount = $instalmentValue['amount'];
                        } elseif ($cancelType != null) {
                            $status = $this->getLanguageFromSnippet('canceledMsg');
                            $refundedAmount = 0;
                        }

                        $InstalmentRefundedAmount += $instalmentValue['amount'];
                    } elseif (in_array($cancelType, ['REMAINING_CYCLES', 'CANCEL_REMAINING_CYCLES']) && empty($instalmentValue['reference'])) {
                        $status = $this->getLanguageFromSnippet('canceledMsg');
                        $refundedAmount = 0;
                        $InstalmentRefundedAmount += $instalmentValue['amount'];
                    } else {
                        $status = $instalmentValue['status'];
                        $refundedAmount = $instalmentValue['refundedAmount'];
                    }

                    $instalmentInfo[$instalmentKey] = [
                        'amount'        => $instalmentValue['amount'],
                        'cycleDate'     => $instalmentValue['cycleDate'],
                        'cycleExecuted' => $instalmentValue['cycleExecuted'],
                        'dueCycles'     => $instalmentValue['dueCycles'],
                        'paidDate'      => $instalmentValue['paidDate'],
                        'status'        => $status,
                        'reference'     => $instalmentValue['reference'],
                        'refundedAmount'=> $refundedAmount,
                    ];
                }
            }

            $configDetails['InstalmentDetails'] = $instalmentInfo;

            if ($cancelType != null) {
                $configDetails['instalmentCancelExecuted'] = true;
            }

            return ['instalmentConfigData' => $this->serializeData($configDetails),'refundTotalAmount' => $InstalmentRefundedAmount];
        }

        $refundedAmount =  !empty($refundReference['refundedAmount']) ? $refundReference['refundedAmount'] : 0;

        return ['instalmentConfigData' => '','refundTotalAmount' => $refundedAmount];
    }

    /**
     * Send order confirmation email for merchant/enduser
     *
     * @param array $variables
     * @param bool $instalment
     * @return void
     */
    public function sendNovalnetOrderMail($variables, $instalment = false)
    {
        $variables = Shopware()->Events()->filter('Shopware_Modules_Order_SendMail_FilterVariables', $variables, [
            'subject' => $this
        ]);

        $context = $variables;
        $mail    = null;
        if ($event = Shopware()->Events()->notifyUntil('Shopware_Modules_Order_SendMail_Create', [
            'subject' => $this,
            'context' => $context,
            'variables' => $variables
        ])) {
            $mail = $event->getReturn();
        }

        $shopLang = strstr($this->getLocale(), 'en');
        $lang = ! empty($shopLang) ? 'en' : 'de';
        if (!($mail instanceof \Zend_Mail) && !$instalment) {
            $mail = Shopware()->TemplateMail()->createMail('sORDER', $context);
        } elseif (!($mail instanceof \Zend_Mail) && $instalment && $lang == 'de') {
            $mail = Shopware()->TemplateMail()->createMail('sNOVALNETORDERMAILDE', $context);
        } else {
            $mail = Shopware()->TemplateMail()->createMail('sNOVALNETORDERMAILEN', $context);
        }

        $mail->addTo($context['additional']['user']['email']);

        if (!Shopware()->Config()->get('sNO_ORDER_MAIL')) {
            $mail->addBcc(Shopware()->Config()->get('sMAIL'));
        }

        $mail = Shopware()->Events()->filter('Shopware_Modules_Order_SendMail_Filter', $mail, [
            'subject' => $this,
            'context' => $context,
            'variables' => $variables
        ]);
        if (!($mail instanceof \Zend_Mail)) {
            return;
        }

        Shopware()->Events()->notify('Shopware_Modules_Order_SendMail_BeforeSend', [
            'subject' => $this,
            'mail' => $mail,
            'context' => $context,
            'variables' => $variables
        ]);

        if (Shopware()->Config()->get('sendOrderMail')) {
            try {
                $mail->send();
            } catch (\Exception $e) {
                Shopware()->Container()->get('pluginlogger')->error($e->getMessage());
            }
        }
    }

    /**
     * Payment check for abo commerce
     *
     * @return bool
     */
    public function isSubscriptionProduct()
    {
        $basketContent = Shopware()->Modules()->Basket()->sGetBasket()['content'];
        if (! empty($basketContent)) {
            foreach ($basketContent as $content) {
                if (! empty($content['__s_order_basket_attributes_swag_abo_commerce_id'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * To get Novalnet payment form URL
     *
     * @param array $enabledPaymentMethods
     * @param bool $changeSupportedPayments
     * @return bool
     */
    public function getPaymentFormUrl($enabledPaymentMethods, $changeSupportedPayments = false)
    {
        $isActiveNNPay = false;
        $novalnetPaymentId = null;
        
        foreach ($enabledPaymentMethods as $paymentKey => $paymentValue) {
            if ($paymentValue['name'] == NOVALNET_PAYMENT_NAME) {
                $isActiveNNPay = true;
                $novalnetPaymentId = $paymentValue['id'];
            }
        }

        if ($isActiveNNPay) {
            $services        = new PaymentRequest($this, Shopware()->Session());
            $requestHandler  = new ManageRequest($this);
            
            if (!empty($novalnetPaymentId)) {
                Shopware()->Session()->offsetSet('sPaymentID', $novalnetPaymentId);
            }
            $paymentFormData = $services->getRequestParams('true');

            if ($changeSupportedPayments) {
                $paymentFormData['hosted_page']['display_payments_mode'] = ['SUBSCRIPTION'];
            }

            $paymentFormUrl  = $this->getActionEndpoint('seamless_payment');

            $paymentFormResponse = $requestHandler->curlRequest($paymentFormData, $paymentFormUrl, $this->getConfigurations()['novalnet_password']);

            if ($paymentFormResponse['result']['status'] == 'SUCCESS' && $paymentFormResponse['result']['status_code'] == '100') {
                return ['nnPaymentFromUrl' => $paymentFormResponse['result']['redirect_url'] , 'walletPaymentParams' => $services->getWalletPaymentParams(false) ];
            }
        }

        return false;
    }

     /*
     *  Set Configuration Details for Insert Novalnet Transaction Table
     *
     * @param array $response
     * @param string $orderNumber
     *
     * @return array
     */
    public function handleResponseData($response, $orderNumber = null)
    {
        $response = new ArrayMapHelper($response);
        $insertData = [];

        if (!empty($response)) {
            $insertData = [
                'payment_type'     => $response->getData('transaction/payment_type'),
                'paid_amount'      => ($response->getData('transaction/status') === NOVALNET_CONFIRMED_STATUS && $response->getData('transaction/amount') != 0 ) ? $response->getData('transaction/amount') : 0,
                'refunded_amount'  => 0,
                'tid'              => $response->getData('transaction/tid'),
                'gateway_status'   => $response->getData('transaction/status'),
                'amount'           => $response->getData('transaction/amount'),
                'customer_id'      => $response->getData('customer/customer_no'),
                'currency'         => $response->getData('transaction/currency'),
            ];

            if ($orderNumber) {
                $insertData['order_no'] = $orderNumber;
            }

            $insertData['configuration_details'] = [];
            if ($response->getData('transaction/bank_details')) {
                $insertData['configuration_details'] = array_merge($insertData['configuration_details'], $response->getData('transaction/bank_details'));
            }

            if ($response->getData('instalment/cycles_executed')) {
                $insertData['configuration_details'] = array_merge($insertData['configuration_details'], $this->getInstalmentInformation($response->getData()));
            }

            $insertKey = ['transaction/payment_name', 'transaction/test_mode', 'transaction/due_date', 'transaction/payment_data/token', 'customer/birth_date'];
            foreach ($insertKey as $key) {
                $keyValue = $response->getData($key);
                if (!empty($keyValue)) {
                    $splitedArray = explode('/', $key);
                    $configurationKey = end($splitedArray);
                    $insertData['configuration_details'][$configurationKey] = $keyValue;
                }
            }
            
            $merchantDetails = Shopware()->Session()->offsetGet('merchant_details');
            if (!empty($merchantDetails)) {
                $insertData['configuration_details']['merchant'] = $merchantDetails;
            }

            if (! empty($insertData['configuration_details'])) {
                $insertData['configuration_details'] = $this->serializeData($insertData['configuration_details']);
            }
        }

        return $insertData;
    }

    /*
     *  Get payment StatusId for Insert order Table
     *
     * @param array $response
     * @param string $orderNumber
     *
     * @return array
     */
    public function getPaymentStatusId($response)
    {
        $paymentStatusId = $this->getConfigurations()['novalnet_after_payment_status'];

        if ($response['transaction']['status'] == NOVALNET_ON_HOLD_STATUS) {
            $paymentStatusId = '18';
        } elseif (($response['transaction']['status'] == NOVALNET_PENDING_STATUS && in_array($response['transaction']['payment_type'], ['INVOICE', 'PREPAYMENT', 'MULTIBANCO', 'CASHPAYMENT'])) ||
            $response['transaction']['status'] == NOVALNET_PENDING_STATUS
        ) {
            $paymentStatusId = '17';
        } elseif ($response['transaction']['status'] == NOVALNET_DEACTIVATED_STATUS) {
            $paymentStatusId = '35';
        }

        return $paymentStatusId;
    }

   /**
     * Remove empty array Elements.
     *
     * @return array
    */
    public function removeEmptyArrayElements(array $array)
    {
        foreach ($array as $key => &$value) {
            if (empty($value)) {
                unset($array[$key]);
            } elseif (is_array($value)) {
                $value = $this->removeEmptyArrayElements($value);
            }
        }

        return $array;
    }
    
    /**
     * Fetch and return abo orders billing address
     *
     * @return array
    */
    public function getAboOrdersBillingAddresses($subscriptionId)
    {
        $aboBillingAddress = [];
        $db = Shopware()->Container()->get('models')->getConnection();
        $aboOrderBilling = $db->createQueryBuilder()
            ->select('subscription.id, address.*, country.countryname as countryName')
            ->from('s_user_addresses', 'address')
            ->leftJoin('address', 's_plugin_swag_abo_commerce_orders', 'subscription', 'address.id = subscription.billing_address_id')
            ->leftJoin('address', 's_core_countries', 'country', 'address.country_id = country.id')
            ->where('subscription.id IN (:id)')
            ->setParameter('id', $subscriptionId)
            ->execute()
            ->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
            
        $orderBilling = !empty($aboOrderBilling[$subscriptionId]) ? $aboOrderBilling[$subscriptionId] : [];
                
        if (!empty($orderBilling)) {
            $aboBillingAddress = $this->getAddressData($orderBilling);
        }
        return !empty($aboBillingAddress) ? $aboBillingAddress : [];
    }
   
   /**
     * Fetch and return abo orders shipping address
     *
     * @return array
    */
    public function getAboOrdersShippingAddresses($subscriptionId)
    {
        $aboShippingAddress = [];
        $db = Shopware()->Container()->get('models')->getConnection();
        $aboOrderShipping = $db->createQueryBuilder()
            ->select('subscription.id, address.*, country.countryname as countryName')
            ->from('s_user_addresses', 'address')
            ->leftJoin('address', 's_plugin_swag_abo_commerce_orders', 'subscription', 'address.id = subscription.shipping_address_id')
            ->leftJoin('address', 's_core_countries', 'country', 'address.country_id = country.id')
            ->where('subscription.id IN (:id)')
            ->setParameter('id', $subscriptionId)
            ->execute()
            ->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
        
        $orderShipping = !empty($aboOrderShipping[$subscriptionId]) ? $aboOrderShipping[$subscriptionId] : [];
                
        if (!empty($orderShipping)) {
            $aboShippingAddress = $this->getAddressData($orderShipping);
        }
        return !empty($aboShippingAddress) ? $aboShippingAddress : [];
    }
}
