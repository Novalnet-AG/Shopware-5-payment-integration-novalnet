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

class NovalnetHelper
{
    /**
     * @var string
     */
    private $endpoint = 'https://payport.novalnet.de/v2/';

    /**
     * @var array
     */
    private $nnRedirectPayments = ['novalnetpaypal', 'novalnetonlinebanktransfer', 'novalnetideal', 'novalnetinstant', 'novalnetgiropay', 'novalnetprzelewy24', 'novalneteps', 'novalnetpostfinancecard', 'novalnetpostfinance', 'novalnetbancontact', 'novalnettrustly', 'novalnetalipay', 'novalnetwechatpay'];

    /**
     * @var array
     */
    private $nnFormTypePayments = ['novalnetsepa', 'novalnetsepaGuarantee', 'novalnetsepainstalment', 'novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment', 'novalnetcc'];

    /**
     * @var array
     */
    private $payLaterPayments = ['novalnetinvoice', 'novalnetprepayment', 'novalnetcashpayment', 'novalnetmultibanco'];

    /**
     * @var Container
     */
    private $container;

    /**
     * @var Enlight_Components_Snippet_Manager
     */
    private $snippetManager;

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
     * Function to return the all payment types
     *
     * @return array
     */
    public function getPaymentInfo()
    {
        return [
            'novalnetsepa' 				=> 'DIRECT_DEBIT_SEPA',
            'novalnetcc'				=> 'CREDITCARD',
            'novalnetapplepay' 			=> 'APPLEPAY',
            'novalnetinvoice'			=> 'INVOICE',
            'novalnetprepayment'		=> 'PREPAYMENT',
            'novalnetinvoiceGuarantee'	=> 'GUARANTEED_INVOICE',
            'novalnetsepaGuarantee'		=> 'GUARANTEED_DIRECT_DEBIT_SEPA',
            'novalnetideal'				=> 'IDEAL',
            'novalnetinstant'			=> 'ONLINE_TRANSFER',
            'novalnetgiropay'			=> 'GIROPAY',
            'novalnetcashpayment'		=> 'CASHPAYMENT',
            'novalnetprzelewy24'		=> 'PRZELEWY24',
            'novalneteps'				=> 'EPS',
            'novalnetinvoiceinstalment'	=> 'INSTALMENT_INVOICE',
            'novalnetsepainstalment'	=> 'INSTALMENT_DIRECT_DEBIT_SEPA',
            'novalnetpaypal'			=> 'PAYPAL',
            'novalnetpostfinancecard'	=> 'POSTFINANCE_CARD',
            'novalnetpostfinance' 		=> 'POSTFINANCE',
            'novalnetbancontact' 		=> 'BANCONTACT',
            'novalnetmultibanco' 		=> 'MULTIBANCO',
            'novalnetonlinebanktransfer' => 'ONLINE_BANK_TRANSFER',
            'novalnetalipay' 			=> 'ALIPAY',
            'novalnetwechatpay' 		=> 'WECHATPAY',
            'novalnettrustly' 			=> 'TRUSTLY',
        ];
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
                $shopId	= trim($shop[0], '[]');
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
    public function getLocale($backend = false)
    {
        if ($this->container->has('shop')) {
            $locale = $this->container->get('shop')->getLocale()->getLocale();
        } elseif ($backend) {
            $locale = $this->container->get('auth')->getIdentity()->locale->getLocale();
        } else {
            $locale = $this->container->get('locale');
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
            return null;
        }
        $result = json_decode($data, $needAsArray, 512, JSON_BIGINT_AS_STRING);

        if (json_last_error() === 0) {
            return $result;
        }

        return $result ? $result : null;
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

    /**
     * Get action URL
     *
     * @param string $action
     *
     * @return string
     */
    public function getActionEndpoint($action = '')
    {
        return $this->endpoint . str_replace('_', '/', $action);
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

		$tariff = explode('-',explode('(', $config['novalnet_tariff'])[1]);
		
		$config['novalnet_tariff'] = $tariff[0];
		
        return $config;
    }

    /**
     * Get user details from shop
     *
     * @return array
     */
    public function getUserInfo()
    {
        $system = Shopware()->System();
        $userData = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

        if (!empty($userData['additional']['countryShipping']) && !empty($userData['billingaddress']) && !empty($userData['shippingaddress'])) {
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
     * Get language from snippet namespace
     *
     * @param string $snippetName
     * @param string $namespace
     *
     * @return \Shopware_Components_Snippet_Manager|string
     */
    public function getLanguageFromSnippet($snippetName, $namespace = 'frontend/novalnet/payment')
    {
        /** @var \Shopware_Components_Snippet_Manager $message */
        $message = $this->snippetManager->getNamespace($namespace)->get($snippetName);
        return !empty($message) ? $message : $snippetName;
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

        return $_SERVER['REMOTE_ADDR'];
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
        $countryCode = Shopware()->Db()->fetchOne('SELECT countryiso FROM s_core_countries WHERE  id = ?', [$datas['countryId']]);
        $data = [
            'street'	=> $datas['street'],
            'city'		=> $datas['city'],
            'zip'		=> $datas['zipcode'],
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
        $user   = $this->getUserInfo();
        $basket	= Shopware()->Modules()->Basket()->sGetBasket();

        if (!empty($user['additional']['charge_vat']) && empty($this->isTaxFreeDelivery($user))) {
            return (empty($basket['AmountWithTaxNumeric']) ? sprintf('%.2f', $basket['AmountNumeric']) : number_format($basket['AmountWithTaxNumeric'], 2, '.', '')) * 100;
        }

        return number_format($basket['AmountNetNumeric'], 2, '.', '') * 100;
    }

    /**
     * Return the redirect payments
     *
     * @return array
     */
    public function getRedirectPayments()
    {
        return $this->nnRedirectPayments;
    }

    /**
     * Return the form type payments
     *
     * @return array
     */
    public function getFormTypePayments()
    {
        return $this->nnFormTypePayments;
    }

    /**
     * Return the pay later payments
     *
     * @return array
     */
    public function getPayLaterPayments()
    {
        return $this->payLaterPayments;
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
    public function formCustomerComments($novalnetResponse, $payment, $currency)
    {
        if (! empty($novalnetResponse['instalment']['cycle_amount'])) {
            $amountInBiggerCurrencyUnit = $this->amountInBiggerCurrencyUnit($novalnetResponse['instalment']['cycle_amount'], $novalnetResponse ['transaction']['currency']);
        } else {
            $amountInBiggerCurrencyUnit = $this->amountInBiggerCurrencyUnit($novalnetResponse ['transaction']['amount'], $novalnetResponse ['transaction']['currency']);
        }

        $comments = $this->getLanguageFromSnippet('tidLabel') . $novalnetResponse['transaction']['tid'] . $this->newLine;

        if ($novalnetResponse['transaction']['test_mode']) {
            $comments .= $this->getLanguageFromSnippet('testOrderText') . $this->newLine;
        }

        switch ($payment) {
            case 'novalnetinvoice':
            case 'novalnetinvoiceGuarantee':
            case 'novalnetinvoiceinstalment':
            case 'novalnetprepayment':

                if ($novalnetResponse['transaction']['status'] === 'PENDING' && in_array($payment, ['novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment'])) {
                    $comments .= $this->getLanguageFromSnippet('guaranteePendingText') . $this->newLine;
                } elseif (!empty($novalnetResponse['transaction']['bank_details'])) {
                    if (in_array($novalnetResponse['transaction']['status'], [ 'CONFIRMED', 'PENDING' ], true) && ! empty($novalnetResponse ['transaction']['due_date'])) {
                        $comments .= sprintf($this->getLanguageFromSnippet('invoicePaymentBankText'), $amountInBiggerCurrencyUnit, date('d.m.Y', strtotime($novalnetResponse['transaction']['due_date']))) . $this->newLine. $this->newLine;
                    } else {
                        $comments .= sprintf($this->getLanguageFromSnippet('invoicePaymentBankOnholdText'), $amountInBiggerCurrencyUnit) . $this->newLine. $this->newLine;
                    }

                    foreach ([
                        'account_holder' => $this->getLanguageFromSnippet('accountOwner'),
                        'bank_name'      => $this->getLanguageFromSnippet('bankName'),
                        'bank_place'     => $this->getLanguageFromSnippet('bankPlace'),
                        'iban'           => $this->getLanguageFromSnippet('bankIban'),
                        'bic'            => $this->getLanguageFromSnippet('bankBic'),
                    ] as $key => $text) {
                        if (! empty($novalnetResponse ['transaction']['bank_details'][ $key ])) {
                            $comments .= $text . $novalnetResponse ['transaction']['bank_details'][ $key ] . $this->newLine;
                        }
                    }

                    $comments .= $this->newLine . $this->getLanguageFromSnippet('multipleReferenceText') . $this->newLine. $this->newLine;
                    $comments .= $this->getLanguageFromSnippet('referenceText1') . 'TID ' . '&nbsp;' . $novalnetResponse['transaction']['tid'];

                    if (!empty($novalnetResponse['transaction']['invoice_ref'])) {
                        $comments .= $this->newLine . $this->getLanguageFromSnippet('referenceText2') . $novalnetResponse['transaction']['invoice_ref'];
                    }

                    if ($payment == 'novalnetinvoiceinstalment' && $novalnetResponse['transaction']['status'] == 'CONFIRMED') {
                        $comments .= $this->newLine . $this->formInstalmentComments($novalnetResponse, $amountInBiggerCurrencyUnit);
                    }
                }
                break;
            case 'novalnetcashpayment':
                if ($novalnetResponse['transaction']['due_date']) {
                    $comments .= $this->getLanguageFromSnippet('slipExpiryDate') . ': ' . date('d.m.Y', strtotime($novalnetResponse['transaction']['due_date'])) . $this->newLine;
                }

                $comments .= $this->newLine . $this->getLanguageFromSnippet('cashPaymentStores') . $this->newLine;

                foreach ($novalnetResponse['transaction']['nearest_stores'] as $nearestStore) {
                    foreach ([
                        'store_name',
                        'street',
                        'city',
                        'zip',
                    ] as $addressData) {
                        if (! empty($nearestStore[$addressData])) {
                            $comments .= $nearestStore[$addressData] . $this->newLine;
                        }
                    }

                    if (! empty($nearestStore['country_code'])) {
                        $countryName	= Shopware()->Db()->fetchOne('SELECT countryname FROM s_core_countries WHERE  id = ?', array($nearestStore['country_code']));
                        $comments .= $countryName . $this->newLine;
                    }
                }
                break;
            default:
                if ($novalnetResponse['transaction']['status'] === 'PENDING' && in_array($payment, ['novalnetsepaGuarantee', 'novalnetsepainstalment'])) {
                    $comments .= $this->getLanguageFromSnippet('sepaGuaranteePendingText') . $this->newLine;
                } elseif ($payment == 'novalnetsepainstalment' && $novalnetResponse['transaction']['status'] == 'CONFIRMED') {
                    $comments .= $this->newLine . $this->formInstalmentComments($novalnetResponse, $amountInBiggerCurrencyUnit);
                } elseif ($payment == 'novalnetmultibanco' && $novalnetResponse['transaction']['partner_payment_reference']) {
                    $comments .= $this->newLine . sprintf($this->getLanguageFromSnippet('multibancoReferenceText'), $amountInBiggerCurrencyUnit) . $this->newLine;
                    $comments .= $this->getLanguageFromSnippet('referenceText1') . $novalnetResponse['transaction']['partner_payment_reference'];
                }
                break;
        }
        $note = $this->setHtmlEntity($comments, 'decode');

        return $note;
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
        $comments .= $this->getLanguageFromSnippet('instalmentDue') . (($response['instalment']['pending_cycles']) ? $response['instalment']['pending_cycles'] : '0') .$this->newLine;

        if ($response['instalment']['next_cycle_date']) {
            $comments .= $this->getLanguageFromSnippet('instalmentNext') . date('d.m.Y', strtotime($response['instalment']['next_cycle_date'])) . $this->newLine;
        }

        $comments .= $this->getLanguageFromSnippet('instalmentCycleAmount') . $amount . $this->newLine;

        return $comments;
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
        $container		= Shopware()->Container()->get('currency');
        if (empty($currency)) {
            $currency = Shopware()->Shop()->getCurrency()->getSymbol();
        }
        $formattedValue = $container->toCurrency($formatedAmount, array('currency' => $currency));
        return $formattedValue;
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
                'cycleDate'     => $futureInstalmentDate ? date('Y-m-d', strtotime($futureInstalmentDate)) : '',
                'cycleExecuted' => '',
                'dueCycles'     => '',
                'paidDate'      => '',
                'status'        => $this->getLanguageFromSnippet('pendingMsg'),
                'reference'     => ''
            ];

            if ($cycle == count($instalmentData['cycle_dates'])) {
                $amount = abs($response['transaction']['amount'] - ($instalmentData['cycle_amount'] * ($cycle - 1)));
                $additionalDetails['InstalmentDetails'][$cycle] = array_merge($additionalDetails['InstalmentDetails'][$cycle], [
                        'amount'    => $amount
                ]);
            }

            if ($cycle == 1) {
                $additionalDetails['InstalmentDetails'][$cycle] = array_merge($additionalDetails['InstalmentDetails'][$cycle], [
                        'cycleExecuted' => !empty($instalmentData['cycles_executed']) ? $instalmentData['cycles_executed'] : '',
                        'dueCycles'     => !empty($instalmentData['pending_cycles']) ? $instalmentData['pending_cycles'] : '',
                        'paidDate'      => date('Y-m-d'),
                        'status'        => $this->getLanguageFromSnippet('paidMsg'),
                        'reference'     => $response['transaction']['tid']
                ]);
            }
        }
        return $additionalDetails;
    }

    /*
     * unset all session data
     *
     * @return void
     */
    public function unsetSession()
    {
        foreach (['novalnet_txn_secret', 'serverResponse', 'novalnet', 'sComment', 'taxFree', 'sOutputNet'] as $key) {
            Shopware()->Session()->offsetUnset($key);
        }
    }

    /*
     * Return the installment cycles
     *
     * @param string $paymentName
     *
     * @return null|array
     */
    public function getInstalmentCycles($paymentName)
    {
        $globalConfig = $this->getConfigurations();
        $cycles = $globalConfig[$paymentName . '_total_period'];
        $totalPeriod = is_string($cycles) ? array_map('trim', explode(',', $cycles)) : $cycles;
        foreach ($cycles as $cycles) {
            if (strpos($cycles, ',') !== false) {
                $totalPeriod = array_map('trim', explode(',', $cycles));
            }
        }
        if (!empty($totalPeriod)) {
            sort($totalPeriod);
        }
        return $totalPeriod;
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

        $shopLang = $this->getLocale();
        $lang = (strstr($shopLang, 'en')) ? 'en' : 'de';
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
            }
        }
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
        $paymentID  = Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans WHERE name = ?', array('novalnetapplepay'));
        $country = Shopware()->Session()->get('sCountry') ? Shopware()->Session()->get('sCountry') : $this->getSelectedCountry();
        $payment = Shopware()->Session()->get('sPaymentID') ? Shopware()->Session()->get('sPaymentID') : $paymentID;

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
     * Return the selected country.
     *
     * @return array
    */
    public function getSelectedCountry()
    {
        $countries = Shopware()->Modules()->Admin()->sGetCountryList();
        return $countries ? reset($countries) : false;
    }

    /**
     * Return the valid shipping details for the selected Country.
     *
     * @param array $data
     *
     * @return array
    */
    public function getShippingMethods($data)
    {
        $shippingDetails = [];
        $countryID       = null;
        Shopware()->Session()->offsetSet('sDispatch', '');

        if (!empty($data['shippingAddressChange'])) {
            Shopware()->Session()->offsetSet('taxFree', false);
            $decodeData = $this->unserializeData($data['shippingInfo']);
            $paymentID  = Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans WHERE name = ?', array('novalnetapplepay'));
            $country    = $this->getSelectedCountry();

            if ($decodeData['address']['countryCode']) {
                $countryID = Shopware()->Db()->fetchOne('SELECT id FROM s_core_countries WHERE countryiso = ?', array($decodeData['address']['countryCode']));
                $country   = Shopware()->Modules()->Admin()->sGetCountry($countryID);
            }

            $taxFree  = Shopware()->Db()->fetchOne('SELECT taxfree FROM s_core_countries WHERE countryiso = ?', array($country['countryiso']));
            Shopware()->Session()->offsetSet('taxFree', (bool) $taxFree);

            Shopware()->Session()->offsetSet('sCountry', $countryID);
            Shopware()->Session()->offsetSet('sPaymentID', $paymentID);

            $premiumDispatches = Shopware()->Modules()->Admin()->sGetPremiumDispatches($countryID, $paymentID, null);
            foreach (array_reverse($premiumDispatches) as $dispatch) {
                Shopware()->Session()->offsetSet('sDispatch', $dispatch['id']);
                $shipping = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);
                if (!$taxFree) {
                    $shippingDetails[] = array('label' => $dispatch['name'], 'amount' => round(number_format($shipping['brutto'], 2, '.', '') * 100), 'identifier' => $dispatch['id'], 'detail' => $dispatch['description']);
                } else {
                    $shippingDetails[] = array('label' => $dispatch['name'], 'amount' => round(number_format($shipping['netto'], 2, '.', '') * 100), 'identifier' => $dispatch['id'], 'detail' => $dispatch['description']);
                }
            }
        } else {
            $decodeData	= $this->unserializeData($data['shippingMethod']);
            Shopware()->Session()->offsetSet('sDispatch', $decodeData['shippingMethod']['identifier']);
        }
        return array_reverse($shippingDetails);
    }

    /**
     * Return the article, discount and shipping details to display in apple pay sheet.
     *
     * @return array
    */
    public function getCartItems()
    {
        $sBasket  = $this->getBasket();
        $country  = $this->getSelectedCountry();
        $sBasketAmount = Shopware()->Modules()->Basket()->sGetAmount();
        $cartItems     = [];
        $netPrice     = 0;

        if (!empty($sBasket)) {
            foreach ($sBasket['content'] as $value) {
                if (empty(Shopware()->Session()->offsetGet('taxFree'))) {
                    $label = $value['articlename']. ' ('. $value['quantity']. ' x '. html_entity_decode(Shopware()->Shop()->getCurrency()->getSymbol()). sprintf('%0.2f', $value['priceNumeric']).')';
                    $cartItems[] = array('label' => $label, 'amount' => round(number_format($value['amountNumeric'], 2, '.', '') * 100));
                } else {
                    $netPrice += $value['amountnetNumeric'];
                    $label = $value['articlename']. ' ('. $value['quantity']. ' x '. html_entity_decode(Shopware()->Shop()->getCurrency()->getSymbol()). sprintf('%0.2f', $value['netprice']).')';
                    $cartItems[] = array('label' => $label, 'amount' => round(number_format($value['amountnetNumeric'], 2, '.', '') * 100));
                }
            }

            $shipping = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts($country);

            if (Shopware()->Session()->offsetGet('sDispatch')) {
                if (empty(Shopware()->Session()->offsetGet('taxFree'))) {
                    $cartItems[]  = array('label' => $this->getLanguageFromSnippet('frontend_shipping_cost_label'), 'amount' => round(number_format($shipping['brutto'], 2, '.', '') * 100));
                } else {
                    $cartItems[]  = array('label' => $this->getLanguageFromSnippet('frontend_shipping_cost_label'), 'amount' => round(number_format($shipping['netto'], 2, '.', '') * 100));
                }
            }

            if (empty(Shopware()->Session()->offsetGet('taxFree'))) {
                $totalAmount = round((number_format($sBasketAmount['totalAmount'], 2, '.', '') + number_format($shipping['brutto'], 2, '.', '')) * 100);
            } else {
                $totalAmount = round((number_format($netPrice, 2, '.', '') + number_format($shipping['netto'], 2, '.', '')) * 100);
            }
            return array('displayItems' => $cartItems, 'totalAmount' => $totalAmount);
        }
        return $cartItems;
    }

    /**
     * Create and Login using customer details.
     *
     * @param array $response
     *
     * @return Customer
    */
    public function createNewCustomer($response)
    {
        $billingCountryStateId	= $this->getCountryStateId($response['wallet']['billing']);
        $shippingCountryStateId = $this->getCountryStateId($response['wallet']['shipping']);
        $customerData = $this->getCustomerAddress($response);
        $customerData['billing'] = $this->getCustomerAddress($response);
        $customerData['shipping'] = $this->getCustomerAddress($response, 'shipping');
        $customerData['password'] = $this->getRandomString();
        $customerData['email'] = $response['wallet']['shipping']['emailAddress'];
        $customerData['accountmode'] = 1;

        $customerModel = $this->registerCustomer($customerData);
        $this->loginCustomer($customerModel);
        return $customerModel;
    }

    /**
     * Register the customer using the apple pay data
     *
     * @param array $customerData
     *
     * @return Customer
     */
    private function registerCustomer($customerData)
    {
        $customer = new Customer();
        $form = Shopware()->Container()->get('shopware.form.factory')->create(PersonalFormType::class, $customer);
        $form->submit($customerData);

        $paymentID = Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans WHERE name = ?', array('novalnetapplepay'));
        $customer->setPaymentId($paymentID);

        $billingAddress = new Address();
        $form = Shopware()->Container()->get('shopware.form.factory')->create(AddressFormType::class, $billingAddress);
        $form->submit($customerData['billing']);

        $shippingAddress = new Address();
        $form = Shopware()->Container()->get('shopware.form.factory')->create(AddressFormType::class, $shippingAddress);
        $form->submit($customerData['shipping']);

        /** @var \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context */
        $context = Shopware()->Container()->get(\Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface::class)->getShopContext();

        /** @var \Shopware\Bundle\StoreFrontBundle\Struct\Shop $shop */
        $shop = $context->getShop();

        /** @var \Shopware\Bundle\AccountBundle\Service\RegisterServiceInterface $registerService */
        $registerService = Shopware()->Container()->get(\Shopware\Bundle\AccountBundle\Service\RegisterServiceInterface::class);

        $registerService->register($shop, $customer, $billingAddress, $shippingAddress);

        return $customer;
    }

    /**
     * Login the register customer
     *
     * @param Customer $customerModel
     *
     * @return void
     */
    private function loginCustomer(Customer $customerModel)
    {
        $request = Shopware()->Front()->Request();
        $request->setPost('email', $customerModel->getEmail());
        $request->setPost('passwordMD5', $customerModel->getPassword());
        Shopware()->Modules()->Admin()->sLogin(true);

        // Set country and area to session, so the cart will be calculated correctly,
        Shopware()->Session()->offsetSet('sCountry', $customerModel->getDefaultBillingAddress()->getCountry()->getId());
    }

    public function getCustomerAddress(array $response, $type = 'billing')
    {
        $serverData = $response['wallet'][$type];
        $countryStateId	= $this->getCountryStateId($serverData);

        return [
            'salutation' => $this->getSalutation(),
            'firstname' => $serverData['givenName'],
            'lastname' => $serverData['familyName'],
            'street' => $serverData['addressLines'][0].' '.$serverData['addressLines'][1],
            'zipcode' => $serverData['postalCode'],
            'city' => $serverData['locality'],
            'country' => $countryStateId['countryId'],
            'countryId' => $countryStateId['countryId'],
            'state' => $countryStateId['stateId'],
            'phone' => $serverData['phoneNumber']
        ];
    }

    /**
     * Return the countryID and stateID from billing details
     *
     * @param array $billing
     *
     * @return array
     */
    public function getCountryStateId($billing)
    {
        $countryId	= Shopware()->Db()->fetchOne('SELECT id FROM s_core_countries WHERE countryiso = ?', array($billing['countryCode']));
        $stateId	= null;
        if ($billing['subLocality']) {
            $stateId	= Shopware()->Db()->fetchOne('SELECT id FROM s_core_countries_states WHERE countryID = ? AND shortcode = ?', array($countryId, $billing['subLocality']));
        }
        return array('countryId' => $countryId, 'stateId' => $stateId);
    }

    /**
     * generate unique string.
     *
     * @return string
    */
    public function getRandomString()
    {
        $randomwordarray = explode(',', '8,7,6,5,4,3,2,1,9,0,9,7,6,1,2,3,4,5,6,7,8,9,0');
        shuffle($randomwordarray);
        return substr(implode('', $randomwordarray), 0, 16);
    }

    /**
     * Update the customer details from the apple pay sheet.
     *
     * @param array $userData
     * @param array $response
     *
     * @return array
    */
    public function updateCustomerData($userData, $response)
    {
        $sheetAry = array(
                'firstname'	=> 'givenName',
                'lastname'	=> 'familyName',
                'street'	=> 'addressLines',
                'zipcode'	=> 'postalCode',
                'city'		=> 'locality',
                'phone'		=> 'phoneNumber'
                );

        foreach ($sheetAry as $key => $value) {
            $userData['billingaddress'][$key]  = ($value == 'addressLines') ? $response['wallet']['billing'][$value][0].' '. $response['wallet']['billing'][$value][1] : $response['wallet']['billing'][$value];
            $userData['shippingaddress'][$key] = ($value == 'addressLines') ? $response['wallet']['shipping'][$value][0].' '. $response['wallet']['shipping'][$value][1] : $response['wallet']['shipping'][$value];
        }

        $billingCountryStateId	= $this->getCountryStateId($response['wallet']['billing']);
        $shippingCountryStateId = $this->getCountryStateId($response['wallet']['shipping']);
        $userData['billingaddress']['company'] = $userData['shippingaddress']['company'] = '';

        $billingCountry   = Shopware()->Db()->fetchRow('SELECT * FROM s_core_countries WHERE  id = ?', [$billingCountryStateId['countryId']]);
        $shippingCountry  = Shopware()->Db()->fetchRow('SELECT * FROM s_core_countries WHERE  id = ?', [$shippingCountryStateId['countryId']]);
        $userData['additional']['country'] = $billingCountry;
        $userData['additional']['countryShipping'] = $shippingCountry;
        $userData['billingaddress']['countryId'] = $userData['billingaddress']['countryID'] = $billingCountryStateId['countryId'];
        $userData['billingaddress']['countryId'] = $userData['billingaddress']['countryID'] = $billingCountryStateId['countryId'];
        $userData['shippingaddress']['countryId'] = $userData['shippingaddress']['countryID'] = $shippingCountryStateId['countryId'];

        return $userData;
    }

    /**
     * Return the possible salutation.
     *
     * @return string
    */
    private function getSalutation()
    {
        if (Shopware()->Config()->get('shopsalutations')) {
            $possibleSalutations = explode(',', Shopware()->Config()->get('shopsalutations'));
        }

        // we have to set one of the possible options
        return isset($possibleSalutations) ? $possibleSalutations[0] : 'mr';
    }
}
