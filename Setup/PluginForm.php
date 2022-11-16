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
        // Payment settings
        $authorize = array(
            array('capture', array('en_GB' => 'Capture', 'de_DE' => 'Zahlung einziehen')),
            array('authorize', array('en_GB' => 'Authorize', 'de_DE' => 'Zahlung autorisieren'))
        );
        
        // Apple Pay Payment settings
        $applePayButtonType = array(
            array('plain', array('en_GB' => 'Default', 'de_DE' => 'Standard')),
            array('buy', array('en_GB' => 'Buy', 'de_DE' => 'Kaufen')),
            array('donate', array('en_GB' => 'Donate', 'de_DE' => 'Spenden')),
            array('book', array('en_GB' => 'Book', 'de_DE' => 'Buchen')),
            array('contribute', array('en_GB' => 'Contribute', 'de_DE' => 'Beitragen')),
            array('check-out', array('en_GB' => 'Check out', 'de_DE' => 'Bezahlen')),
            array('order', array('en_GB' => 'Order', 'de_DE' => 'Bestellen')),
            array('pay', array('en_GB' => 'Pay', 'de_DE' => 'Bezahlen')),
            array('subscribe', array('en_GB' => 'Subscribe', 'de_DE' => 'Abonnieren')),
            array('tip', array('en_GB' => 'Tip', 'de_DE' => 'Trinkgeld')),
            array('rent', array('en_GB' => 'Rent', 'de_DE' => 'Mieten')),
            array('reload', array('en_GB' => 'Reload', 'de_DE' => 'Aufladen')),
            array('support', array('en_GB' => 'Support', 'de_DE' => 'Unterstützen'))
        );
        
        $googlePayButtonType = array(
            array('book', array('en_GB' => 'Book', 'de_DE' => 'Buchen')),
            array('buy', array('en_GB' => 'Buy', 'de_DE' => 'Kaufen')),
            array('checkout', array('en_GB' => 'Checkout', 'de_DE' => 'Zur Kasse')),
            array('donate', array('en_GB' => 'Donate', 'de_DE' => 'Spenden')),
            array('order', array('en_GB' => 'Order', 'de_DE' => 'Bestellen')),
            array('pay', array('en_GB' => 'Pay', 'de_DE' => 'Bezahlen')),
            array('plain', array('en_GB' => 'Plain', 'de_DE' => 'Einfach')),
            array('subscribe', array('en_GB' => 'Subscribe', 'de_DE' => 'Abonnieren'))
        );
        
        $applePayButtonTheme = array(
            array('black', array('en_GB' => 'Dark', 'de_DE' => 'Dunkel')),
            array('white', array('en_GB' => 'Light', 'de_DE' => 'Hell')),
            array('white-outline', array('en_GB' => 'Light-Outline', 'de_DE' => 'An Hintergrund anpassen'))
        );
        
        $googlePayButtonTheme = array(
            array('default', array('en_GB' => 'Default', 'de_DE' => 'Standard')),
            array('black', array('en_GB' => 'Black', 'de_DE' => 'Schwarz')),
            array('white', array('en_GB' => 'White', 'de_DE' => 'Weiß'))
        );
        
        $displayField = array(
            array('index', array('en_GB' => 'Product page', 'de_DE' => 'Produktseite')),
            array('cart', array('en_GB' => 'Shopping cart page', 'de_DE' => 'Warenkorb')),
            array('ajaxCart', array('en_GB' => 'Mini cart page', 'de_DE' => 'Mini-Warenkorb')),
            array('register', array('en_GB' => 'Register Page', 'de_DE' => 'Seite registrieren'))
        );
        
        // Instalment Payment settings
        $instalmentCycles = array(
            array('2', array('en_GB' => '2 cycles', 'de_DE' => '2 Raten')),
            array('3', array('en_GB' => '3 cycles', 'de_DE' => '3 Raten')),
            array('4', array('en_GB' => '4 cycles', 'de_DE' => '4 Raten')),
            array('5', array('en_GB' => '5 cycles', 'de_DE' => '5 Raten')),
            array('6', array('en_GB' => '6 cycles', 'de_DE' => '6 Raten')),
            array('7', array('en_GB' => '7 cycles', 'de_DE' => '7 Raten')),
            array('8', array('en_GB' => '8 cycles', 'de_DE' => '8 Raten')),
            array('9', array('en_GB' => '9 cycles', 'de_DE' => '9 Raten')),
            array('10', array('en_GB' => '10 cycles', 'de_DE' => '10 Raten')),
            array('11', array('en_GB' => '11 cycles', 'de_DE' => '11 Raten')),
            array('12', array('en_GB' => '12 cycles', 'de_DE' => '12 Raten')),
            array('15', array('en_GB' => '15 cycles', 'de_DE' => '15 Raten')),
            array('18', array('en_GB' => '18 cycles', 'de_DE' => '18 Raten')),
            array('21', array('en_GB' => '21 cycles', 'de_DE' => '21 Raten')),
            array('24', array('en_GB' => '24 cycles', 'de_DE' => '24 Raten')),
        );
        
        $position = -10;
        
        $configElement = array(
            'novalnet_api' => array(
                'type' => 'button',
                'options' => array(
                    'label' => '<b>Novalnet API-Konfiguration</b>',
                    'position' => $position++
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
                    'position' => $position++
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
            'novalnet_payment_logo_display' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Zahlungslogo anzeigen',
                    'value' => true,
                    'description' => 'Das Logo der Zahlungsart wird auf der Checkout-Seite angezeigt',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++
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
            /* Direct Debit SEPA configuration settings/options */
            'novalnetsepa' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'SEPA-Lastschrift',
                    'position' => $position++,
                )
            ),
            'novalnetsepa_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepa_after_paymenstatus' => array(
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
            'novalnetsepa_shopping_type' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Kauf mit einem Klick',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'Zahlungsdaten, die während des Bestellvorgangs gespeichert werden, können für zukünftige Zahlungen verwendet werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepa_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Fälligkeitsdatum (in Tagen)',
                    'minValue' => 2,
                    'maxValue' => 14,
                    'position' => $position++,
                    'description' => 'Geben Sie die Anzahl der Tage ein, nach denen der Zahlungsbetrag eingezogen werden soll (muss zwischen 2 und 14 Tagen liegen).',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepa_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepa_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepa_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Guaranteed Direct Debit SEPA configuration settings/options */
            'novalnetsepaGuarantee' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'SEPA-Lastschrift mit Zahlungsgarantie',
                    'position' => $position++,
                )
            ),
            'novalnetsepaGuarantee_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => '
                        <ul>
                            <li>Grundanforderungen für die Zahlungsgarantie</li>
                            <li>Zugelassene Staaten: AT, DE, CH</li>
                            <li>Erlaubte B2B-Länder: Europa</li>
                            <li>Zugelassene Währung: EUR</li>
                            <li>Mindestbetrag der Bestellung: 9,99 EUR</li>
                            <li>Mindestalter: 18 Jahre</li>
                            <li>Rechnungsadresse und Lieferadresse müssen übereinstimmen</li>
                        </ul>',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_after_paymenstatus' => array(
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
            'novalnetsepaGuarantee_minimum_amount' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindestbestellbetrag für Zahlungsgarantie',
                    'value' => 999,
                    'minValue' => 999,
                    'position' => $position++,
                    'description' => 'Geben Sie den Mindestbetrag (in Cent) für die zu bearbeitende Transaktion mit Zahlungsgarantie ein. Geben Sie z.B. 100 ein, was 1,00 entspricht. Der Standbetrag ist 9,99 EUR.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_shopping_type' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Kauf mit einem Klick',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'Zahlungsdaten, die während des Bestellvorgangs gespeichert werden, können für zukünftige Zahlungen verwendet werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Fälligkeitsdatum (in Tagen)',
                    'minValue' => 2,
                    'maxValue' => 14,
                    'position' => $position++,
                    'description' => 'Geben Sie die Anzahl der Tage ein, nach denen der Zahlungsbetrag eingezogen werden soll (muss zwischen 2 und 14 Tagen liegen).',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_force_payment' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Zahlung ohne Zahlungsgarantie erzwingen',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Falls die Zahlungsgarantie zwar aktiviert ist, jedoch die Voraussetzungen für Zahlungsgarantie nicht erfüllt sind, wird die Zahlung ohne Zahlungsgarantie verarbeitet. Die Voraussetzungen finden Sie in der Installationsanleitung unter "Zahlungsgarantie aktivieren".',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_allow_b2b' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'B2B-Kunden erlauben',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'B2B-Kunden erlauben, Bestellungen aufzugeben.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepaGuarantee_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Credit Card configuration settings/options */
            'novalnetcc' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Kredit/Debitkarte',
                    'position' => $position++,
                )
            ),
            'novalnetcc_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_after_paymenstatus' => array(
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
            'novalnetcc_shopping_type' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Kauf mit einem Klick',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'Zahlungsdaten, die während des Bestellvorgangs gespeichert werden, können für zukünftige Zahlungen verwendet werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_form_type' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Inline-Formular ermöglichen',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'Inline-Zahlungsformular: Die folgenden Felder werden im Checkout in zwei Zeilen angezeigt: Karteninhaber , Kreditkartennummer / Ablaufdatum / CVC-Code',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_enforcecc3D' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => '3D-Secure-Zahlungen außerhalb der EU erzwingen',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Wenn Sie diese Option aktivieren, werden alle Zahlungen mit Karten, die außerhalb der EU ausgegeben wurden, mit der starken Kundenauthentifizierung (Strong Customer Authentication, SCA) von 3D-Secure 2.0 authentifiziert.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_css_button' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'CSS-Einstellungen für den iFrameformular',
                    'position' => $position++,
                )
            ),
            'novalnetcc_standard_label' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Beschriftung',
                    'scope' => Element::SCOPE_SHOP,
                    'position' => $position++,
                )
            ),
            'novalnetcc_standard_field' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Eingabe',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcc_standard_text' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Text für das CSS',
                    'position' => $position++,
                    'value' => 'body{color: #8798a9;font-family:Helvetica,Arial,sans-serif;font-weight: 500;}input{border-radius: 3px;background-clip: padding-box;box-sizing: border-box;line-height: 1.1875rem;padding: .625rem .625rem .5625rem .625rem;box-shadow: inset 0 1px 1px #dadae5;background: #f8f8fa;border: 1px solid #dadae5;border-top-color: #cbcbdb;color: #8798a9;text-align: left;font: inherit;letter-spacing: normal;margin: 0;word-spacing: normal;text-transform: none;text-indent: 0px;text-shadow: none;display: inline-block;height:40px;font-family:Helvetica,Arial,sans-serif;font-weight: 500;}input:focus{background-color: white;font-family:Helvetica,Arial,sans-serif;font-weight: 500;}',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Apple Pay configuration settings/options */
            'novalnetapplepay' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Apple Pay',
                    'position' => $position++,
                )
            ),
            'novalnetapplepay_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_after_paymenstatus' => array(
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
            'novalnetapplepay_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_button_design' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Button-Design',
                    'position' => $position++,
                )
            ),
            'novalnetapplepay_button_type' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Button-Typ',
                    'value' => 'plain',
                    'store' => $applePayButtonType,
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_button_theme' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Button-Farbe',
                    'position' => $position++,
                    'value' => 'black',
                    'store' => $applePayButtonTheme,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_seller_name' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Name des Geschäfts',
                    'description' => 'Der Name des Geschäfts wird in den Zahlungsbeleg von Apple Pay eingefügt und der Text wird als PAY "Name des Geschäfts" angezeigt, so dass der Endkunde weiß, an wen er zahlt. ',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_button_height' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Button-Höhe',
                    'value' => 40,
                    'position' => $position++,
                    'minValue' => 30,
                    'maxValue' => 64,
                    'description' => 'zwischen 30 und 64 Pixel.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_button_corner_radius' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Abrundungsgrad der Ecken des Buttons',
                    'position' => $position++,
                    'value' => 2,
                    'minValue' => 0,
                    'maxValue' => 10,
                    'description' => 'zwischen 0 und 10 Pixel.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetapplepay_button_display_fields' => array(
                'type' => 'combo',
                'options' => array(
                    'label' => 'Apple Pay-Button anzeigen auf',
                    'value' => array('index', 'cart', 'ajaxCart', 'register'),
                    'store' => $displayField,
                    'position' => $position++,
                    'multiSelect' => true,
                    'emptyText' => '-',
                    'description' => 'Die Apple Pay-Schaltfläche wird auf den ausgewählten Seiten direkt unter der Schaltfläche "Zur Kasse gehen" angezeigt',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Google Pay configuration settings/options */
            'novalnetgooglepay' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Google Pay',
                    'position' => $position++,
                )
            ),
            'novalnetgooglepay_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_merchant_id' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Google-Händler-ID',
                    'description' => 'Beachten Sie bitte, dass die Händler-ID von Google für die Ausführung dieser Zahlungsart in der Live-Umgebung benötigt wird. Die Händler-ID wird nach der Registrierung bei <a href="https://pay.google.com/business/console/" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Google Pay und der Wallet-Konsole</a> vergeben. Siehe auch: <a href="https://developers.google.com/pay/api/web/guides/test-and-deploy/request-prod-access" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Anfrage für Produktiv-Zugang stellen</a>, falls Sie mehr Informationen zum Genehmigungsverfahren benötigen und dazu, wie Sie eine Google Händler-ID erhalten. Die Registrierung beinhaltet auch, dass Sie Ihre Anbindung mit ausreichenden Screenshots einreichen, deshalb sammeln Sie diese Informationen, indem Sie die Zahlungsmethode im Testmodus aktivieren. Um die Validierung dieses Feldes zu überspringen, während Sie die Konfiguration speichern, verwenden Sie diese Test-ID, BCR2DN4XXXTN7FSI , zum Testen und Einreichen Ihrer Anbindung bei Google.',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_enforcecc3D' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => '3D-Secure-Zahlungen außerhalb der EU erzwingen',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Wenn Sie diese Option aktivieren, werden alle Zahlungen mit Karten, die außerhalb der EU ausgegeben wurden, mit der starken Kundenauthentifizierung (Strong Customer Authentication, SCA) von 3D-Secure 2.0 authentifiziert.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_after_paymenstatus' => array(
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
            'novalnetgooglepay_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_button_design' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Button-Design',
                    'position' => $position++,
                )
            ),
            'novalnetgooglepay_button_type' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Button-Typ',
                    'value' => 'book',
                    'store' => $googlePayButtonType,
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_button_theme' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Button-Farbe',
                    'position' => $position++,
                    'value' => 'default',
                    'store' => $googlePayButtonTheme,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_seller_name' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Name des Geschäfts',
                    'description' => 'Der Name des Geschäfts wird in den Zahlungsbeleg von Google Pay eingefügt und der Text wird als PAY "Name des Geschäfts" angezeigt, so dass der Endkunde weiß, an wen er zahlt. ',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_button_height' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Button-Höhe',
                    'value' => 50,
                    'position' => $position++,
                    'minValue' => 40,
                    'maxValue' => 100,
                    'description' => 'zwischen 40 und 100 Pixel.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgooglepay_button_display_fields' => array(
                'type' => 'combo',
                'options' => array(
                    'label' => 'Google Pay-Button anzeigen auf',
                    'value' => array('index', 'cart', 'ajaxCart', 'register'),
                    'store' => $displayField,
                    'position' => $position++,
                    'multiSelect' => true,
                    'emptyText' => '-',
                    'description' => 'Die Google Pay-Schaltfläche wird auf den ausgewählten Seiten direkt unter der Schaltfläche "Zur Kasse gehen" angezeigt',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Invoice configuration settings/options */
            'novalnetinvoice' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Rechnung',
                    'position' => $position++,
                )
            ),
            'novalnetinvoice_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'position' => $position++,
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 17, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Fälligkeitsdatum (in Tagen)',
                    'minValue' => 7,
                    'position' => $position++,
                    'description' => 'Anzahl der Tage, die der Käufer Zeit hat, um den Betrag an Novalnet zu überweisen (muss mehr als 7 Tage betragen). Wenn Sie dieses Feld leer lassen, werden standardmäßig 14 Tage als Fälligkeitsdatum festgelegt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'store' => $authorize,
                    'position' => $position++,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_before_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Callback / Webhook Bestellstatus',
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'position' => $position++,
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status nach der erfolgreichen Ausführung des Novalnet-Callback-Skripts (ausgelöst bei erfolgreicher Zahlung) verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoice_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Guaranteed Invoice configuration settings/options */
            'novalnetinvoiceGuarantee' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Rechnung mit Zahlungsgarantie',
                    'position' => $position++,
                )
            ),
            'novalnetinvoiceGuarantee_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => '
                        <ul>
                            <li>Grundanforderungen für die Zahlungsgarantie</li>
                            <li>Zugelassene Staaten: AT, DE, CH</li>
                            <li>Erlaubte B2B-Länder: Europa</li>
                            <li>Zugelassene Währung: EUR</li>
                            <li>Mindestbetrag der Bestellung: 9,99 EUR</li>
                            <li>Mindestalter: 18 Jahre</li>
                            <li>Rechnungsadresse und Lieferadresse müssen übereinstimmen</li>
                        </ul>',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Fälligkeitsdatum (in Tagen)',
                    'position' => $position++,
                    'minValue' => 7,
                    'description' => 'Anzahl der Tage, die der Käufer Zeit hat, um den Betrag an Novalnet zu überweisen (muss mehr als 7 Tage betragen). Wenn Sie dieses Feld leer lassen, werden standardmäßig 14 Tage als Fälligkeitsdatum festgelegt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_minimum_amount' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindestbestellbetrag für Zahlungsgarantie',
                    'position' => $position++,
                    'value' => 999,
                    'minValue' => 999,
                    'description' => 'Geben Sie den Mindestbetrag (in Cent) für die zu bearbeitende Transaktion mit Zahlungsgarantie ein. Geben Sie z.B. 100 ein, was 1,00 entspricht. Der Standbetrag ist 9,99 EUR.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'value' => 'capture',
                    'position' => $position++,
                    'store' => $authorize,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'value' => 0,
                    'minValue' => 0,
                    'position' => $position++,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_force_payment' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Zahlung ohne Zahlungsgarantie erzwingen',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Falls die Zahlungsgarantie zwar aktiviert ist, jedoch die Voraussetzungen für Zahlungsgarantie nicht erfüllt sind, wird die Zahlung ohne Zahlungsgarantie verarbeitet. Die Voraussetzungen finden Sie in der Installationsanleitung unter "Zahlungsgarantie aktivieren".',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_allow_b2b' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'B2B-Kunden erlauben',
                    'value' => true,
                    'position' => $position++,
                    'description' => 'B2B-Kunden erlauben, Bestellungen aufzugeben.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceGuarantee_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'position' => $position++,
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Prepayment configuration settings/options */
            'novalnetprepayment' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Vorkasse',
                    'position' => $position++,
                )
            ),
            'novalnetprepayment_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'value' => false,
                    'position' => $position++,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprepayment_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'position' => $position++,
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 17, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprepayment_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Fälligkeitsdatum (in Tagen)',
                    'position' => $position++,
                    'minValue' => 7,
                    'maxValue' => 28,
                    'description' => 'Anzahl der Tage, die der Käufer Zeit hat, um den Betrag an Novalnet zu überweisen (muss mehr als 7 Tage betragen). Wenn Sie dieses Feld leer lassen, werden standardmäßig 14 Tage als Fälligkeitsdatum festgelegt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprepayment_before_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Callback / Webhook Bestellstatus',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status nach der erfolgreichen Ausführung des Novalnet-Callback-Skripts (ausgelöst bei erfolgreicher Zahlung) verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprepayment_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* iDEAL configuration settings/options */
            'novalnetideal' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'iDEAL',
                    'position' => $position++,
                )
            ),
            'novalnetideal_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetideal_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetideal_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Sofort configuration settings/options */
            'novalnetinstant' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Sofortüberweisung',
                    'position' => $position++,
                )
            ),
            'novalnetinstant_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinstant_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinstant_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Online Bank Transfer configuration settings/options */
            'novalnetonlinebanktransfer' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Onlineüberweisung',
                    'position' => $position++,
                )
            ),
            'novalnetonlinebanktransfer_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetonlinebanktransfer_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetonlinebanktransfer_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Giropay configuration settings/options */
            'novalnetgiropay' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Giropay',
                    'position' => $position++,
                )
            ),
            'novalnetgiropay_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgiropay_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetgiropay_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Barzhlen configuration settings/options */
            'novalnetcashpayment' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Barzahlen/viacash',
                    'position' => $position++,
                )
            ),
            'novalnetcashpayment_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcashpayment_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 17, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcashpayment_due_date' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Verfallsdatum des Zahlscheins (in Tagen)',
                    'position' => $position++,
                    'minValue' => 1,
                    'maxValue' => 28,
                    'description' => 'Anzahl der Tage, die der Käufer Zeit hat, um den Betrag in einer Filiale zu bezahlen. Wenn Sie dieses Feld leer lassen, ist der Zahlschein standardmäßig 14 Tage lang gültig.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcashpayment_before_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Callback / Webhook Bestellstatus',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status nach der erfolgreichen Ausführung des Novalnet-Callback-Skripts (ausgelöst bei erfolgreicher Zahlung) verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetcashpayment_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Przelewy24 configuration settings/options */
            'novalnetprzelewy24' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Przelewy24',
                    'position' => $position++,
                )
            ),
            'novalnetprzelewy24_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprzelewy24_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetprzelewy24_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* EPS configuration settings/options */
            'novalneteps' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'EPS',
                    'position' => $position++,
                )
            ),
            'novalneteps_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalneteps_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalneteps_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Instalment by Invoice configuration settings/options */
            'novalnetinvoiceinstalment' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Ratenzahlung per Rechnung',
                    'position' => $position++,
                )
            ),
            'novalnetinvoiceinstalment_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => '
                        <ul>
                            <li>Grundanforderungen für die Zahlungsgarantie</li>
                            <li>Zugelassene Staaten: AT, DE, CH</li>
                            <li>Erlaubte B2B-Länder: Europa</li>
                            <li>Zugelassene Währung: EUR</li>
                            <li>Mindestbetrag der Bestellung: 19,98 EUR</li>
                            <li>Mindestalter: 18 Jahre</li>
                            <li>Rechnungsadresse und Lieferadresse müssen übereinstimmen</li>
                        </ul>',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_minimum_amount' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindestbestellbetrag für Zahlungsgarantie',
                    'position' => $position++,
                    'value' => 1998,
                    'minValue' => 1998,
                    'description' => 'Geben Sie den Mindestbetrag (in Cent) für die zu bearbeitende Transaktion mit Zahlungsgarantie ein. Geben Sie z.B. 100 ein, was 1,00 entspricht. Der Standbetrag ist 9,99 EUR.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'position' => $position++,
                    'value' => 'capture',
                    'store' => $authorize,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'position' => $position++,
                    'value' => 0,
                    'minValue' => 0,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_product_page_info' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Ratenzahlungsplan auf der Produktdetailseite anzeigen',
                    'position' => $position++,
                    'value' => true,
                    'description' => 'Legen Sie fest, ob ein Ratenzahlungsplan auf der Produktseite angezeigt werden soll oder nicht.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_total_period' => array(
                'type' => 'combo',
                'options' => array(
                    'label' => 'Anzahl der Raten',
                    'position' => $position++,
                    'value' => array('2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'),
                    'store' => $instalmentCycles,
                    'multiSelect' => true,
                    'emptyText' => '-',
                    'description' => 'Wählen Sie die Anzahl der Raten aus',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_allow_b2b' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'B2B-Kunden erlauben',
                    'position' => $position++,
                    'value' => true,
                    'description' => 'B2B-Kunden erlauben, Bestellungen aufzugeben.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetinvoiceinstalment_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Instalment by Direct Debit SEPA configuration settings/options */
            'novalnetsepainstalment' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Ratenzahlung per SEPA-Lastschrift',
                    'position' => $position++,
                )
            ),
            'novalnetsepainstalment_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => '
                        <ul>
                            <li>Grundanforderungen für die Zahlungsgarantie</li>
                            <li>Zugelassene Staaten: AT, DE, CH</li>
                            <li>Erlaubte B2B-Länder: Europa</li>
                            <li>Zugelassene Währung: EUR</li>
                            <li>Mindestbetrag der Bestellung: 19,98 EUR</li>
                            <li>Mindestalter: 18 Jahre</li>
                            <li>Rechnungsadresse und Lieferadresse müssen übereinstimmen</li>
                        </ul>',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_minimum_amount' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindestbestellbetrag für Zahlungsgarantie',
                    'position' => $position++,
                    'value' => 1998,
                    'minValue' => 1998,
                    'description' => 'Geben Sie den Mindestbetrag (in Cent) für die zu bearbeitende Transaktion mit Zahlungsgarantie ein. Geben Sie z.B. 100 ein, was 1,00 entspricht. Der Standbetrag ist 9,99 EUR.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'position' => $position++,
                    'value' => 'capture',
                    'store' => $authorize,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'position' => $position++,
                    'value' => 0,
                    'minValue' => 0,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_shopping_type' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Kauf mit einem Klick',
                    'position' => $position++,
                    'value' => true,
                    'description' => 'Zahlungsdaten, die während des Bestellvorgangs gespeichert werden, können für zukünftige Zahlungen verwendet werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_product_page_info' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Ratenzahlungsplan auf der Produktdetailseite anzeigen',
                    'position' => $position++,
                    'value' => true,
                    'description' => 'Legen Sie fest, ob ein Ratenzahlungsplan auf der Produktseite angezeigt werden soll oder nicht.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_total_period' => array(
                'type' => 'combo',
                'options' => array(
                    'label' => 'Anzahl der Raten',
                    'position' => $position++,
                    'value' => array('2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'),
                    'store' => $instalmentCycles,
                    'multiSelect' => true,
                    'emptyText' => '-',
                    'description' => 'Wählen Sie die Anzahl der Raten aus',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_allow_b2b' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'B2B-Kunden erlauben',
                    'position' => $position++,
                    'value' => true,
                    'description' => 'B2B-Kunden erlauben, Bestellungen aufzugeben.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetsepainstalment_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* PayPal configuration settings/options */
            'novalnetpaypal' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'PayPal',
                    'position' => $position++,
                )
            ),
            'novalnetpaypal_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpaypal_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpaypal_capture' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Aktion für vom Besteller autorisierte Zahlungen',
                    'position' => $position++,
                    'value' => 'capture',
                    'store' => $authorize,
                    'description' => 'Wählen Sie, ob die Zahlung sofort belastet werden soll oder nicht. Zahlung einziehen: Betrag sofort belasten. Zahlung autorisieren: Die Zahlung wird überprüft und autorisiert, aber erst zu einem späteren Zeitpunkt belastet. So haben Sie Zeit, über die Bestellung zu entscheiden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpaypal_manual_check_limit' => array(
                'type' => 'number',
                'options' => array(
                    'label' => 'Mindesttransaktionsbetrag für die Autorisierung',
                    'position' => $position++,
                    'value' => 0,
                    'minValue' => 0,
                    'description' => 'Übersteigt der Bestellbetrag das genannte Limit, wird die Transaktion, bis zu ihrer Bestätigung durch Sie, auf on hold gesetzt. Sie können das Feld leer lassen, wenn Sie möchten, dass alle Transaktionen als on hold behandelt werden.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpaypal_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* PostFinance Card configuration settings/options */
            'novalnetpostfinancecard' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'PostFinance Card',
                    'position' => $position++,
                )
            ),
            'novalnetpostfinancecard_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpostfinancecard_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpostfinancecard_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* PostFinance E-Finance configuration settings/options */
            'novalnetpostfinance' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'PostFinance E-Finance',
                    'position' => $position++,
                )
            ),
            'novalnetpostfinance_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpostfinance_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetpostfinance_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Bancontact configuration settings/options */
            'novalnetbancontact' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Bancontact',
                    'position' => $position++,
                )
            ),
            'novalnetbancontact_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetbancontact_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetbancontact_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Multibanco configuration settings/options */
            'novalnetmultibanco' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Multibanco',
                    'position' => $position++,
                )
            ),
            'novalnetmultibanco_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetmultibanco_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 17, // open
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetmultibanco_before_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Callback / Webhook Bestellstatus',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status nach der erfolgreichen Ausführung des Novalnet-Callback-Skripts (ausgelöst bei erfolgreicher Zahlung) verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetmultibanco_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* Trustly configuration settings/options */
            'novalnettrustly' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'Vertrauensvoll',
                    'position' => $position++,
                )
            ),
            'novalnettrustly_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnettrustly_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnettrustly_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* AliPay configuration settings/options */
            'novalnetalipay' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'AliPay',
                    'position' => $position++,
                )
            ),
            'novalnetalipay_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetalipay_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetalipay_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            /* WeChatPay configuration settings/options */
            'novalnetwechatpay' => array(
                'type' => 'button',
                'options' => array(
                    'label' => 'WeChatPay',
                    'position' => $position++,
                )
            ),
            'novalnetwechatpay_test_mode' => array(
                'type' => 'boolean',
                'options' => array(
                    'label' => 'Testmodus aktivieren',
                    'position' => $position++,
                    'value' => false,
                    'description' => 'Die Zahlung wird im Testmodus durchgeführt, daher wird der Betrag für diese Transaktion nicht eingezogen',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetwechatpay_after_paymenstatus' => array(
                'type' => 'select',
                'options' => array(
                    'label' => 'Status für abgeschlossene Zahlungen',
                    'position' => $position++,
                    'store' => 'Shopware.apps.Base.store.PaymentStatus',
                    'displayField' => 'description',
                    'valueField' => 'id',
                    'value' => 12, // completely paid
                    'description' => 'Wählen Sie, welcher Status für erfolgreich abgeschlossene Bestellungen verwendet wird',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
            'novalnetwechatpay_payment_notification_to_buyer' => array(
                'type' => 'text',
                'options' => array(
                    'label' => 'Benachrichtigung des Käufers',
                    'position' => $position++,
                    'description' => 'Der eingegebene Text wird auf der Checkout-Seite angezeigt.',
                    'scope' => Element::SCOPE_SHOP
                )
            ),
        );
        
        return $configElement;
    }
    
    public function getPaymentFormTranslations($form)
    {
        $defaultLocale = 'en_GB';
        $payments = array(
            'novalnetsepa' => 'Direct Debit SEPA',
            'novalnetsepaGuarantee' => 'Direct Debit SEPA with payment guarantee',
            'novalnetcc' => 'Credit/Debit Cards',
            'novalnetapplepay' => 'Apple Pay',
            'novalnetgooglepay' => 'Google Pay',
            'novalnetinvoice' => 'Invoice',
            'novalnetinvoiceGuarantee' => 'Invoice with payment guarantee',
            'novalnetprepayment' => 'Prepayment',
            'novalnetideal' => 'iDEAL',
            'novalnetinstant' => 'Sofort',
            'novalnetonlinebanktransfer' => 'Online bank transfer',
            'novalnetgiropay' => 'Giropay',
            'novalnetcashpayment' => 'Barzahlen/viacash',
            'novalnetprzelewy24' => 'Przelewy24',
            'novalneteps' => 'EPS',
            'novalnetinvoiceinstalment' => 'Instalment by Invoice',
            'novalnetsepainstalment' => 'Instalment by Direct Debit SEPA',
            'novalnetpaypal' => 'PayPal',
            'novalnetpostfinancecard' => 'PostFinance Card',
            'novalnetpostfinance' => 'PostFinance E-Finance',
            'novalnetbancontact' => 'Bancontact',
            'novalnetmultibanco' => 'Multibanco',
            'novalnettrustly' => 'Trustly',
            'novalnetalipay' => 'AliPay',
            'novalnetwechatpay' => 'WeChatPay'
        );
        
        $commonText = array(
            'test_mode' => array(
                'label' => 'Enable test mode',
                'description' => 'The payment will be processed in the test mode therefore amount for this transaction will not be charged'
            ),
            'after_paymenstatus' => array(
                'label' => 'Payment completion status',
                'description' => 'Status to be used for successful orders'
            ),
            'shopping_type' => array(
                'label' => 'One-click shopping',
                'description' => 'Payment details stored during the checkout process can be used for future payments.'
            ),
            'capture' => array(
                'label' => 'Payment Action',
                'description' => 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order'
            ),
            'manual_check_limit' => array(
                'label' => 'Minimum transaction amount for authorization',
                'description' => 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.'
            ),
            'payment_notification_to_buyer' => array(
                'label' => 'Notification for the buyer',
                'description' => 'The entered text will be displayed on the checkout page'
            ), 
            'force_payment' => array(
                'label' => 'Force non-guarantee payment',
                'description' => 'Even if payment guarantee is enabled, payments will still be processed as non-guarantee payments if the payment guarantee requirements are not met. Review the requirements under "Enable Payment Guarantee" in the Installation Guide.'
            ),
            'allow_b2b' => array(
                'label' => 'Allow B2B Customers',
                'description' => 'Allow B2B customers to place order.'
            ),
            'before_paymenstatus' => array(
                'label' => 'Callback / webhook order status',
                'description' => 'Status to be used when callback script is executed for payment received by Novalnet'
            ),
            'product_page_info' => array(
                'label' => 'Display Instalment Plan on Product Detail Page',
                'description' => 'Control whether or not an instalment plan should be displayed in the product page.'
            ),
            'total_period' => array(
                'label' => 'Instalment cycles',
                'description' => 'Select the available instalment cycles'
            ),
            'button_design' => array(
                'label' => 'Button Design'
            ),
            'button_type' => array(
                'label' => 'Button Type'
            ),
            'button_theme' => array(
                'label' => 'Button Theme'
            )
        );
        
        $translations = array(
            'en_GB' => array(
                'novalnet_api' => '<b>Novalnet API Configuration</b>',
                'novalnet_secret_key' => 'Product activation key',
                'novalnet_password' => 'Payment access key',
                'NClass' => '<h3><font color=#FF0000>Click here to Configure Novalnet API</font></h3>',
                'novalnet_tariff' => 'Select Tariff ID',
                'novalnet_payment_logo_display' => 'Display payment logo',
                'novalnet_callback' => 'Notification / Webhook URL Setup',
                'novalnet_callback_url' => 'Notification / Webhook URL',
                'novalnetcallback' => '<h3><font color=#FF0000>Click here to auto configure Notification / Webhook URL in Novalnet Admin Portal</font></h3>',
                'novalnetcallback_test_mode' => 'Allow manual testing of the Notification / Webhook URL',
                'novalnet_callback_mail_send_to' => 'Send e-mail to',
                'novalnetsepa_due_date' => 'Payment due date (in days)',
                'novalnetsepaGuarantee_due_date' => 'Payment due date (in days)',
                'novalnetinvoice_due_date' => 'Payment due date (in days)',
                'novalnetinvoiceGuarantee_due_date' => 'Payment due date (in days)',
                'novalnetprepayment_due_date' => 'Payment due date (in days)',
                'novalnetsepaGuarantee_minimum_amount' => 'Minimum order amount for payment guarantee',
                'novalnetinvoiceGuarantee_minimum_amount' => 'Minimum order amount for payment guarantee',
                'novalnetinvoiceinstalment_minimum_amount' => 'Minimum order amount',
                'novalnetsepainstalment_minimum_amount' => 'Minimum order amount',
                'novalnetcc_form_type' => 'Enable inline form',
                'novalnetcc_enforcecc3D' => 'Enforce 3D secure payment outside EU',
                'novalnetcc_css_button' => 'CSS settings for iframe form',
                'novalnetcc_standard_label' => 'Label',
                'novalnetcc_standard_field' => 'Input',
                'novalnetcc_standard_text' => 'Css Text',
                'novalnetapplepay_button_height' => 'Button Height',
                'novalnetgooglepay_button_height' => 'Button Height',
                'novalnetapplepay_button_corner_radius' => 'Button Corner Radius',
                'novalnetgooglepay_seller_name' => 'Business name',
                'novalnetapplepay_seller_name' => 'Business name',
                'novalnetapplepay_button_display_fields' => 'Display the Apple Pay Button on',
                'novalnetgooglepay_button_display_fields' => 'Display the Google Pay Button on',
                'novalnetgooglepay_merchant_id' => 'Google Merchant ID',
                'novalnetgooglepay_enforcecc3D' => 'Enforce 3D secure payment outside EU'
            ),
        );
        
        $descriptionTranslations = array(
            'en_GB' => array(
                'novalnet_secret_key' => 'Your product activation key is a unique token for merchant authentication and payment processing. Get your Product activation key from the Novalnet Admin Portal: PROJECT -> Choose your project > API credentials > API Signature (Product activation key)',
                'novalnet_password' => 'Your secret key used to encrypt the data to avoid user manipulation and fraud. Get your Payment access key from the Novalnet Admin Portal Projects > Choose your project > API credentials > Payment access key',
                'novalnet_tariff' => 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project',
                'novalnet_payment_logo_display' => 'The payment method logo(s) will be displayed on the checkout page',
                'novalnet_callback_url' => 'You must add the following webhook endpoint to your Novalnet Admin Portal . This will allow you to receive notifications about the transaction status.',
                'novalnetcallback_test_mode' => 'Enable this to test the Novalnet Notification / Webhook URL manually. Disable this before setting your shop live to block unauthorized calls from external parties',
                'novalnet_callback_mail_send_to' => 'Notification / Webhook URL execution messages will be sent to this e-mail',
                'novalnetsepa_due_date' => 'Number of days after which the payment is debited (must be between 2 and 14 days)',
                'novalnetsepaGuarantee_due_date' => 'Number of days after which the payment is debited (must be between 2 and 14 days)',
                'novalnetinvoice_due_date' => 'Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). If this field is left blank, 14 days will be set as due date by default.',
                'novalnetinvoiceGuarantee_due_date' => 'Number of days given to the buyer to transfer the amount to Novalnet (must be between 7 and 28 days). If this field is left blank, 14 days will be set as due date by default.',
                'novalnetprepayment_due_date' => 'Number of days given to the buyer to transfer the amount to Novalnet (must be between 7 and 28 days). If this field is left blank, 14 days will be set as due date by default',
                'novalnetsepaGuarantee_minimum_amount' => 'Enter the minimum amount (in cents) for the transaction to be processed with payment guarantee. For example, enter 100 which is equal to 1,00. By default, the amount will be 9,99 EUR.',
                'novalnetinvoiceGuarantee_minimum_amount' => 'Enter the minimum amount (in cents) for the transaction to be processed with payment guarantee. For example, enter 100 which is equal to 1,00. By default, the amount will be 9,99 EUR.',
                'novalnetinvoiceinstalment_minimum_amount' => 'Minimum order amount to display the selected payment method (s) at during checkout.',
                'novalnetsepainstalment_minimum_amount' => 'Minimum order amount to display the selected payment method (s) at during checkout.',
                'novalnetcc_form_type' => 'Inline form: The following fields will be shown in the checkout in two lines: card holder, credit card number / expiry date / CVC',
                'novalnetcc_enforcecc3D' => 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.',
                'novalnetapplepay_button_height' => 'Range from 30 to 64 pixels.',
                'novalnetgooglepay_button_height' => 'Range from 40 to 100 pixels.',
                'novalnetapplepay_button_corner_radius' => 'Range from 0 to 10 pixels.',
                'novalnetapplepay_button_display_fields' => 'The selected pages will display the Apple Pay Button, Just below the Proceed To Checkout button',
                'novalnetgooglepay_button_display_fields' => 'The selected pages will display the Google Pay Button, Just below the Proceed To Checkout button',
                'novalnetgooglepay_merchant_id' => 'Please note that Googles merchant identifier is required for processing the payment method in the live environment. Googles merchant identifier is issued after registration with the <a href="https://pay.google.com/business/console/" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Google Pay and Wallet Console</a>. See also: <a href="https://developers.google.com/pay/api/web/guides/test-and-deploy/request-prod-access" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Submit Request for Productive Access</a> if you need more information on the approval process and how to get a Google Merchant ID. Registration also involves submitting your connection with sufficient screenshots, so collect this information by enabling the payment method in test mode. To skip validating this field while saving the configuration, use this test ID, BCR2DN4XXXTN7FSI , to test and submit your connection to Google.',
                'novalnetgooglepay_enforcecc3D' => 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.',
                'novalnetgooglepay_seller_name' => 'The business name is rendered in the Google Pay payment sheet, and this text will appear as PAY "BUSINESS NAME" so that the customer knows where he is paying to.',
                'novalnetapplepay_seller_name' => 'The business name is rendered in the Apple Pay payment sheet, and this text will appear as PAY "BUSINESS NAME" so that the customer knows where he is paying to.',
            ),
        );
        
        foreach ($payments as $key => $payment)
        {
            $translations[$defaultLocale][$key] = $payment;
            foreach ($commonText as $commonKey => $commonLang)
            {
                $translations[$defaultLocale][$key.'_'.$commonKey] = $commonLang['label'];
                $descriptionTranslations[$defaultLocale][$key.'_'.$commonKey] = $commonLang['description'];
            }
        }
        
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
