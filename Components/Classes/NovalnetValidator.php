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

class NovalnetValidator
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
     * Return the valid novalnet payments.
     *
     * @param array $enabledPaymentMethods
     *
     * @return array
     */
    public function displayValidPayments($enabledPaymentMethods)
    {
        $invoiceGuaranteeEnabled = in_array('novalnetinvoiceGuarantee', array_column($enabledPaymentMethods, 'name'));
        $sepaGuaranteeEnabled = in_array('novalnetsepaGuarantee', array_column($enabledPaymentMethods, 'name'));

        foreach ($enabledPaymentMethods as $key => $payment) {
            if (strpos($payment['name'], 'novalnet') !== false) {
                if (!$this->config['novalnet_secret_key'] || !$this->config['novalnet_tariff']) {
                    unset($enabledPaymentMethods[$key]);
                } elseif (in_array($payment['name'], ['novalnetsepaGuarantee','novalnetsepa', 'novalnetinvoice', 'novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment', 'novalnetsepainstalment'])) {
                    if (in_array($payment['name'], ['novalnetsepaGuarantee', 'novalnetsepa']) && $sepaGuaranteeEnabled) {
                        $guaranteeCheck = $this->checkGuarantee('novalnetsepaGuarantee');
                        if (!$guaranteeCheck && $payment['name'] == 'novalnetsepaGuarantee') {
                            unset($enabledPaymentMethods[$key]);
                        } elseif ($payment['name'] == 'novalnetsepa') {
                            if (!$guaranteeCheck && empty($this->config['novalnetsepaGuarantee_force_payment'])) {
                                unset($enabledPaymentMethods[$key]);
                            } elseif ($guaranteeCheck) {
                                unset($enabledPaymentMethods[$key]);
                            }
                        }
                    }

                    if (in_array($payment['name'], ['novalnetinvoiceGuarantee', 'novalnetinvoice']) && $invoiceGuaranteeEnabled) {
                        $guaranteeCheck = $this->checkGuarantee('novalnetinvoiceGuarantee');
                        if (!$guaranteeCheck && $payment['name'] == 'novalnetinvoiceGuarantee') {
                            unset($enabledPaymentMethods[$key]);
                        } elseif ($payment['name'] == 'novalnetinvoice') {
                            if (!$guaranteeCheck && empty($this->config['novalnetinvoiceGuarantee_force_payment'])) {
                                unset($enabledPaymentMethods[$key]);
                            } elseif ($guaranteeCheck) {
                                unset($enabledPaymentMethods[$key]);
                            }
                        }
                    }

                    if ($payment['name'] == 'novalnetinvoiceinstalment') {
                        $guaranteeCheck = $this->checkGuarantee('novalnetinvoiceinstalment');
                        if (!$guaranteeCheck) {
                            unset($enabledPaymentMethods[$key]);
                        }
                    }

                    if ($payment['name'] == 'novalnetsepainstalment') {
                        $guaranteeCheck = $this->checkGuarantee('novalnetsepainstalment');
                        if (!$guaranteeCheck) {
                            unset($enabledPaymentMethods[$key]);
                        }
                    }
                }
            }
        }
        return $enabledPaymentMethods;
    }

    /*
     * Checking the payment guarantee process.
     *
     * @param string $payment
     *
     * @return boolean
     */
    public function checkGuarantee(string $payment)
    {
        $sBasket  = Shopware()->Modules()->Basket();
        $userData = Shopware()->Session()->get('sOrderVariables')['sUserData'] ? Shopware()->Session()->get('sOrderVariables')['sUserData'] : $this->helper->getUserInfo();
        if (empty($sBasket)) {
            return false;
        }
        $basketAmount   = Shopware()->Modules()->Basket()->sGetAmount();
        $shippingAmount = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts();
        $sBasketAmount  = number_format(($basketAmount['totalAmount'] + $shippingAmount['value']), 2, '.', '') * 100;
        $billingAddress = $this->helper->getAddressData($userData['billingaddress']);
        $shippingAddres = $this->helper->getAddressData($userData['shippingaddress']);
        $countryCode    = $userData['additional']['country']['countryiso'];

        if (!empty($billingAddress['company']) && $this->config[$payment.'_allow_b2b'] != false) {
            $countriesList  = array('AT','DE','CH', 'BE', 'DK', 'BG', 'IT', 'ES', 'SE', 'PT', 'NL', 'IE', 'HU', 'GR', 'FR', 'FI', 'CZ');
        } else {
            $countriesList  = array('AT','DE','CH');
        }

        if (in_array($payment, ['novalnetinvoiceinstalment', 'novalnetsepainstalment'])) {
            $minAmount = (!empty($this->config[$payment.'_minimum_amount']) && $this->config[$payment.'_minimum_amount'] >= 1998) ? $this->config[$payment.'_minimum_amount'] : 1998;
            $installmentCycles = $this->helper->getInstalmentCycles($payment);
            $count = 0;

            foreach ($installmentCycles as $cycle) {
                if (($sBasketAmount / $cycle) >= 999) {
                    $count++;
                }
            }

            if ($count == 0  || empty($this->config[$payment.'_total_period'])) {
                return false;
            }
        } else {
            $minAmount = (!empty($this->config[$payment.'_minimum_amount']) && $this->config[$payment.'_minimum_amount'] >= 999) ? $this->config[$payment.'_minimum_amount'] : 999;
        }

        if (($billingAddress == $shippingAddres) && in_array($countryCode, $countriesList) && (Shopware()->Shop()->getCurrency()->getCurrency() =='EUR') && ($sBasketAmount > $minAmount)) {
            return true;
        }
        return false;
    }
}
