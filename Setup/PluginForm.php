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

namespace Shopware\Plugins\NovalPayment\Setup;

use Shopware\Models\Config\Element;
use Shopware\Models\Config\ElementTranslation;

class PluginForm
{
    /**
     * Return payment form
     */
    public function getPaymentForm()
    {
        $position = -10;
        
        $configElement = array(
            'novalnet_api' => array(
                'type' => 'button',
                'options' => array(
                    'label' => '<b>Novalnet API-Konfiguration</b>',
                    'position' => $position++,
                )
            ),
            'novalnet_secret_key' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Produktaktivierungsschlüssel',
                    'description' => 'Ihr Produktaktivierungsschlüssel ist ein eindeutiges Token für die Händlerauthentifizierung und Zahlungsabwicklung. Ihren Produktaktivierungsschlüssel finden Sie im Novalnet Admin-Portal: Projekts > Wählen Sie Ihr Projekt > API-Anmeldeinformationen > API-Signatur (Produktaktivierungsschlüssel)',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++
                )
            ),
            'novalnet_password' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Zahlungs-Zugriffsschlüssel',
                    'description' => 'Ihr geheimer Schlüssel zur Verschlüsselung der Daten, um Manipulation und Betrug zu vermeiden. Ihren Paymentzugriffsschlüssel finden Sie im Novalnet Admin-Portal: Projekts > Wählen Sie Ihr Projekt > API-Anmeldeinformationen > Paymentzugriffsschlüssel.',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++,
                )
            ),
            'novalnet_clientkey' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Novalnet Schlüsselkunde',
                    'hidden' => true,
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++
                )
            ),
            'NClass' => array(
                'type' => 'button',
                'options' => array(
                    'label' => '<h3><font color=#FF0000>Klicken Sie hier um die Novalnet API zu konfigurieren</font></h3>',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++,
                    'handler' => 'function (button){
                        /* get all values */
                        var novalnetApiKey = button.up("panel").down("[elementName=novalnet_secret_key]");
                        var novalnetAccessKey = button.up("panel").down("[elementName=novalnet_password]");
                        if(novalnetApiKey.getValue() == "" || novalnetAccessKey.getValue() == "")
                        {
                            var emptyMessage = ( Ext.userLanguage == "de" ? "Bitte füllen Sie die erforderlichen Felder unter Novalnet API-Konfiguration aus" : "Please enter the required fields under Novalnet API Configuration" );
                            Shopware.Notification.createStickyGrowlMessage({
                                text: emptyMessage,
                                width: 440
                            });
                            return;
                        }
                        Ext.Ajax.request({
                            url: "NovalPayment/validateApiKey",
                            params: {
                                "novalnetApiKey": novalnetApiKey.getValue(),
                                "novalnetAccessKey": novalnetAccessKey.getValue(),
                                "shopId": novalnetApiKey.getName(),
                            },
                            success: function (result) {
                            
                                var jsonData = JSON.parse(result.responseText);
                                
                                if(jsonData.data)
                                {
                                    if ( jsonData.data.result.status_code == 100 ) {
                                        var text = ( Ext.userLanguage == "de" ? "Novalnet-Händlerdetails sind erfolgreich konfiguriert. Um fortzufahren, wählen Sie bitte die Tarif-ID und speichern Sie die Novalnet-Zahlungskonfigurationen." : "Novalnet merchant details are configured successfully. To proceed further, Please select the tariff id and save the Novalnet payment configurations." );
                                        button.up("panel").down("[elementName=novalnet_clientkey]").setValue(jsonData.data.merchant.client_key);
                                    } else if (jsonData.data.result.status_code != 100){
                                        var text = jsonData.data.result.status_text;
                                        novalnetApiKey.setValue("");
                                        novalnetAccessKey.setValue("");
                                    }
                                } else {
                                
                                    var text = ( Ext.userLanguage == "de" ? "Bitte geben Sie den Produktaktivierungsschlüssel ein/prüfen Sie, ob das Plugin aktiviert ist" : "Please enter the product activation key (or) check if the plugin is activated" );
                                    novalnetApiKey.setValue("");
                                    novalnetAccessKey.setValue("");
                                }
                                
                                    Shopware.Notification.createStickyGrowlMessage({
                                            text: text,
                                            width: 440
                                    });
                                
                            },
                            failure: function () {
                            
                                var text = ( Ext.userLanguage == "de" ? "Bitte geben Sie den Produktaktivierungsschlüssel ein/prüfen Sie, ob das Plugin aktiviert ist" : "Please Enter the product activation key/check if the plugin is activated" );
                                novalnetApiKey.setValue("");
                                novalnetAccessKey.setValue("");
                                Ext.Msg.alert("Fehler", text);
                            },
                        });
                   }'
                )
            ),
            'novalnet_tariff' => array(
                'type' => 'combo',
                'options' => array(
                    'label' => 'Auswahl der Tarif-ID',
                    'queryCaching' => true,
                    'queryMode' => 'remote',
                    'displayField' => 'id',
                    'itemId' => 'novalnet_tariff',
                    'valueField' => 'id',
                    'position' => $position++,
                    'emptyText' => '-',
                    'description' => 'Wählen Sie eine Tarif-ID, die dem bevorzugten Tarifplan entspricht, den Sie im Novalnet Admin-Portal für dieses Projekt erstellt haben',
                    'scope' => Element::SCOPE_SHOP,
                    'store' => '
                        new Ext.data.Store({
                            parent: this,
                            extend : "Ext.data.Model",
                            fields: ["id"],
                            proxy : {
                             type : "ajax",
                             api : {
                                read: document.location.pathname + "?controller=NovalPayment&action=getTariff",
                             },
                             reader : {
                                 type : "json",
                                 root : "data"
                             },
                             extraParams : {
                                field_name: me.name
                             }
                            },
                            autoLoad: false
                        });
                    '
                )
            ),
            'novalnet_callback' => array(
                'type' => 'button',
                'options' => array(
                    'label' => '<b>Benachrichtigungs- / Webhook-URL festlegen</b>',
                    'position' => $position++
                )
            ),
            'novalnet_callback_url' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigungs- / Webhook-URL',
                    'value' => substr($_SERVER['HTTP_REFERER'], '0', strpos($_SERVER['HTTP_REFERER'], 'backend')) . 'NovalPayment/status',
                    'description' => 'Sie müssen die folgende Webhook-URL im Novalnet Admin-Portal hinzufügen. Dadurch können Sie Benachrichtigungen über den Transaktionsstatus erhalten',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++
                )
            ),
            'novalnetcallback' => array(
                'type' => 'button',
                'options' => array(
                    'label' => '<h3><font color=#FF0000>Klicken Sie hier, um die Callbackskript-/ Webhook-URL im Novalnet Admin Portal automatisch zu konfigurieren</font></h3>',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++,
                    'handler' => 'function(button) {
                        var webhookUrl = button.up("panel").down("[elementName=novalnet_callback_url]");
                        var novalnetApiKey = button.up("panel").down("[elementName=novalnet_secret_key]");
                        var novalnetAccessKey = button.up("panel").down("[elementName=novalnet_password]");
                        
                        if( novalnetApiKey.getValue() == "" || novalnetAccessKey.getValue() == "" )
                        {
                            var message = ( Ext.userLanguage == "de" ? "Bitte füllen Sie die erforderlichen Felder unter Novalnet API-Konfiguration aus" : "Please enter the required fields under Novalnet API Configuration" );
                            Shopware.Notification.createStickyGrowlMessage({
                                text: message,
                                width: 440
                            });
                            return;
                        } 
                        
                        if(webhookUrl.getValue() == "")
                        {
                            var emptyMessage = ( Ext.userLanguage == "de" ? "Bitte geben Sie eine gültige Webhook-URL ein" : "Please enter the valid Webhook URL" );
                            Shopware.Notification.createStickyGrowlMessage({
                                text: emptyMessage,
                                width: 440
                            });
                            return;
                        }
                        var confirmWebhook = ( Ext.userLanguage == "de" ? "Sind Sie sicher, dass Sie die Webhook-URL im Novalnet Admin Portal konfigurieren möchten?" : "Are you sure you want to configure the Webhook URL in Novalnet Admin Portal?" );
                        
                        Ext.Msg.confirm(
                        "",
                        confirmWebhook,
                            function (btn) {
                                if (btn === "yes") {
                                    Ext.Ajax.request({
                                        url: "NovalPayment/configureWebhook",
                                        params: {
                                            "novalnetWebhook": webhookUrl.getValue(),
                                            "novalnetApiKey": novalnetApiKey.getValue(),
                                            "novalnetAccessKey": novalnetAccessKey.getValue(),
                                        },
                                        success: function (result) {
                                            var jsonData = JSON.parse(result.responseText);
                                            
                                            if( jsonData.error != "" ) {
                                                
                                                Shopware.Notification.createStickyGrowlMessage({
                                                    text: jsonData.data.result.status_text,
                                                    width: 440
                                                });
                                                return;
                                            }
                                            
                                            if( jsonData.data.result.status_code == 100 ) {
                                                var successMessage = ( Ext.userLanguage == "de" ? "Callbackskript-/ Webhook-URL wurde erfolgreich im Novalnet Admin Portal konfiguriert" : "Notification / Webhook URL is configured successfully in Novalnet Admin Portal" );
                                                Shopware.Notification.createStickyGrowlMessage({
                                                    text: successMessage,
                                                    width: 440
                                                });
                                            }
                                        },
                                        failure: function () {
                                            var emptyMessage = ( Ext.userLanguage == "de" ? "Bitte geben Sie eine gültige Webhook-URL ein" : "Please enter the valid Webhook URL" );
                                            Ext.Msg.alert("Fehler", emptyMessage);
                                        },
                                    });
                                    
                                }
                            }
                        );
                    }'
                )
            ),
            'novalnetcallback_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Manuelles Testen der Benachrichtigungs / Webhook-URL erlauben',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Aktivieren Sie diese Option, um die Novalnet-Benachrichtigungs-/Webhook-URL manuell zu testen. Deaktivieren Sie die Option, bevor Sie Ihren Shop liveschalten, um unautorisierte Zugriffe von Dritten zu blockieren.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnet_callback_mail_send_to' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'E-Mails senden an',
                    'description' => 'E-Mail-Benachrichtigungen werden an diese E-Mail-Adresse gesendet',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnet_after_payment_status' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'position' => $position++,
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
        );
        
        return $configElement;
    }
    
    public function getPaymentFormTranslations($form)
    {
        $translations = array(
            'en_GB' => array(
                'novalnet_api' => '<b>Novalnet API Configuration</b>',
                'novalnet_secret_key' => 'Product activation key',
                'novalnet_password' => 'Payment access key',
                'NClass' => '<h3><font color=#FF0000>Click here to Configure Novalnet API</font></h3>',
                'novalnet_tariff' => 'Select Tariff ID',
                'novalnet_callback' => 'Notification / Webhook URL Setup',
                'novalnet_callback_url' => 'Notification / Webhook URL',
                'novalnetcallback' => '<h3><font color=#FF0000>Click here to auto configure Notification / Webhook URL in Novalnet Admin Portal</font></h3>',
                'novalnetcallback_test_mode' => 'Allow manual testing of the Notification / Webhook URL',
                'novalnet_callback_mail_send_to' => 'Send e-mail to',
                'novalnet_after_payment_status' => 'Payment completion status',
            ),
        );
        
        $descriptionTranslations = array(
            'en_GB' => array(
                'novalnet_secret_key' => 'Your product activation key is a unique token for merchant authentication and payment processing. Get your Product activation key from the Novalnet Admin Portal: PROJECT -> Choose your project > API credentials > API Signature (Product activation key)',
                'novalnet_password' => 'Your secret key used to encrypt the data to avoid user manipulation and fraud. Get your Payment access key from the Novalnet Admin Portal Projects > Choose your project > API credentials > Payment access key',
                'novalnet_tariff' => 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project',
                'novalnet_callback_url' => 'You must add the following webhook endpoint to your Novalnet Admin Portal . This will allow you to receive notifications about the transaction status.',
                'novalnetcallback_test_mode' => 'Enable this to test the Novalnet Notification / Webhook URL manually. Disable this before setting your shop live to block unauthorized calls from external parties',
                'novalnet_callback_mail_send_to' => 'Notification / Webhook URL execution messages will be sent to this e-mail',
                'novalnet_after_payment_status' => 'Status to be used for successful orders',
            ),
        );
        
        $shopRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale');
        
        foreach ($translations as $locale => $snippets) {
            /** @var Shopware\Models\Shop\Locale $localeModel */
            $localeModel = $shopRepository->findOneBy(array('locale' => $locale));
            
            foreach ($snippets as $element => $snippet) {
                if ($localeModel === null) {
                    continue;
                }
                $elementModel = $form->getElement($element);
                if ($elementModel === null) {
                    continue;
                }
                
                $isUpdate = false;
                foreach ($elementModel->getTranslations() as $existingTranslation) {
                    // Check if translation for this locale already exists
                    if ($existingTranslation->getLocale()->getLocale() != $locale) {
                        continue;
                    }
                    $existingTranslation->setLabel($snippet);
                    if (isset($descriptionTranslations[$locale][$element])) {
                        $existingTranslation->setDescription($descriptionTranslations[$locale][$element]);
                    }
                    $isUpdate = true;
                    break;
                }
                if (!$isUpdate) {
                    $translationModel = new ElementTranslation();
                    $translationModel->setLabel($snippet);
                    if (isset($descriptionTranslations[$locale][$element])) {
                        $translationModel->setDescription($descriptionTranslations[$locale][$element]);
                    }
                    $translationModel->setLocale($localeModel);
                    $elementModel->addTranslation($translationModel);
                }
            }
        }
        
        Shopware()->Models()->flush();
    }
}
