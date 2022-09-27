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

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Mail\Mail;

class Mailer
{
    private $enMailTemplate = 'sNOVALNETORDERMAILEN';
    private $deMailTemplate = 'sNOVALNETORDERMAILDE';

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * Installer constructor.
     *
     * @param ModelManager $modelManager
     */
    public function __construct(
        ModelManager $modelManager
    ) {
        $this->modelManager  = $modelManager;
    }

    /**
     * Create novalnet order mail template
     */
    public function createMailTemplate()
    {
        foreach ([$this->enMailTemplate, $this->deMailTemplate] as $mailTemplate) {
            $mail = new Mail();
            $language = substr($mailTemplate, -2);
            // After creating an empty instance, some technical info is set
            $mail->setName($mailTemplate);
            $mail->setMailtype(Mail::MAILTYPE_SYSTEM);

            // Now the templates basic information can be set
            $mail->setFromMail('{config name=mail}');
            $mail->setFromName('{config name=shopName}');
            if (strpos($language, 'EN') !== false) {
                $mail->setSubject($this->getSubjectEN());
                $mail->setContent($this->getContentEN());
                $mail->setContentHtml($this->getContentHtmlEN());
            } else {
                $mail->setSubject($this->getSubjectDE());
                $mail->setContent($this->getContentDE());
                $mail->setContentHtml($this->getContentHtmlDE());
            }
            $mail->setIsHtml(true);

            /**
             * Finally the new template can be persisted.
             *
             * transactional is a helper method which wraps the given function
             * in a transaction and executes a rollback if something goes wrong.
             * Any exception that occurs will be thrown again and, since we're in
             * the install method, shown in the backend as a growl message.
             */
            $this->modelManager->transactional(static function ($em) use ($mail) {
                /** @var ModelManager $em */
                $em->persist($mail);
            });
        }
    }

    /**
     * Remove novalnet order mail template
     */
    public function remove()
    {
        /** @var Mail $mailRepository */
        $mailRepository = $this->modelManager->getRepository(Mail::class);

        foreach ([$this->enMailTemplate, $this->deMailTemplate] as $mailTemplate) {
            // Find the mail-type we created
            $mail = $mailRepository->findOneBy(['name' => $mailTemplate]);

            $this->modelManager->transactional(static function ($em) use ($mail) {
                if ($mail) {
                    /** @var ModelManager $em */
                    $em->remove($mail);
                }
            });
        }
    }

    private function getSubjectEN()
    {
        return 'Order confirmation mail at {config name=shopName} order no: {$sOrderNumber}';
    }

    private function getSubjectDE()
    {
        return 'Bestellbestätigungsmail an {config name=shopName} Bestellnummer: {$sOrderNumber}';
    }

    private function getContentEN()
    {
        return <<<'EOD'
{include file="string:{config name=emailheaderplain}"}

Dear {$billingaddress.salutation|salutation} {$billingaddress.lastname},

{if $sInstalment}
The next instalment cycle have arrived for the instalment order (Number: {$sOrderNumber}) placed at the store {config name=shopName}, kindly refer further details below.
{else}
We are pleased to inform you that your order has been confirmed, kindly refer further details below.
{/if}
Information on your order:

Pos.  Art.No.               Description                                      Quantities       Price       Total
{foreach item=details key=position from=$sOrderDetails}
{{$position+1}|fill:4}  {$details.ordernumber|fill:20}  {$details.articlename|fill:49}  {$details.quantity|fill:6}  {$details.price|padding:8|currency|unescape:"htmlall"}      {$details.amount|padding:8|currency|unescape:"htmlall"}
{/foreach}

Shipping costs: {$sShippingCosts|currency|unescape:"htmlall"}
Net total: {$sAmountNet|currency|unescape:"htmlall"}
{if !$sNet}
{foreach $sTaxRates as $rate => $value}
plus {$rate|number_format:0}% MwSt. {$value|currency|unescape:"htmlall"}
{/foreach}
Total gross: {$sAmount|currency|unescape:"htmlall"}
{/if}

Selected payment type: {$additional.payment.description}
{$additional.payment.additionaldescription}


Selected shipping type: {$sDispatch.name}
{$sDispatch.description}

{if $sComment}
Your comment:
{$sComment}
{/if}

Billing address:
{$billingaddress.company}
{$billingaddress.firstname} {$billingaddress.lastname}
{$billingaddress.street} {$billingaddress.streetnumber}
{if {config name=showZipBeforeCity}}{$billingaddress.zipcode} {$billingaddress.city}{else}{$billingaddress.city} {$billingaddress.zipcode}{/if}

{$additional.country.countryname}

Shipping address:
{$shippingaddress.company}
{$shippingaddress.firstname} {$shippingaddress.lastname}
{$shippingaddress.street} {$shippingaddress.streetnumber}
{if {config name=showZipBeforeCity}}{$shippingaddress.zipcode} {$shippingaddress.city}{else}{$shippingaddress.city} {$shippingaddress.zipcode}{/if}

{$additional.countryShipping.countryname}

{if $billingaddress.ustid}
Your VAT-ID: {$billingaddress.ustid}
In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
{/if}

If you have any questions, do not hesitate to contact us.

{include file="string:{config name=emailfooterplain}"}
EOD;
    }

    private function getContentDE()
    {
        return <<<'EOD'
{include file="string:{config name=emailheaderplain}"}

Hallo {$billingaddress.salutation|salutation} {$billingaddress.lastname},

{if $sInstalment}
Für Ihre Bestellung (Nummer: {$sOrderNumber}) bei {config name=shopName}, ist die nächste Rate fällig. Bitte beachten Sie weitere Details unten.
{else}
Wir freuen uns Ihnen mitteilen zu können, dass Ihre Bestellung bestätigt wurde. Bitte beachten Sie weitere Details unten.
{/if}
Informationen zu Ihrer Bestellung:

Pos.  Art.Nr.               Beschreibung                                      Menge       Preis       Summe
{foreach item=details key=position from=$sOrderDetails}
{{$position+1}|fill:4}  {$details.ordernumber|fill:20}  {$details.articlename|fill:49}  {$details.quantity|fill:6}  {$details.price|padding:8|currency|unescape:"htmlall"}      {$details.amount|padding:8|currency|unescape:"htmlall"}
{/foreach}

Versandkosten: {$sShippingCosts|currency|unescape:"htmlall"}
Gesamtkosten Netto: {$sAmountNet|currency|unescape:"htmlall"}

{if !$sNet}
{foreach $sTaxRates as $rate => $value}
zzgl. {$rate|number_format:0}% MwSt. {$value|currency|unescape:"htmlall"}
{/foreach}
Gesamtkosten Brutto: {$sAmount|currency|unescape:"htmlall"}
{/if}

Gewählte Zahlungsart: {$additional.payment.description}
{$additional.payment.additionaldescription}


Gewählte Versandart: {$sDispatch.name}
{$sDispatch.description}

{if $sComment}
Ihr Kommentar:
{$sComment}
{/if}

Rechnungsadresse:
{$billingaddress.company}
{$billingaddress.firstname} {$billingaddress.lastname}
{$billingaddress.street} {$billingaddress.streetnumber}
{if {config name=showZipBeforeCity}}{$billingaddress.zipcode} {$billingaddress.city}{else}{$billingaddress.city} {$billingaddress.zipcode}{/if}

{$additional.country.countryname}

Lieferadresse:
{$shippingaddress.company}
{$shippingaddress.firstname} {$shippingaddress.lastname}
{$shippingaddress.street} {$shippingaddress.streetnumber}
{if {config name=showZipBeforeCity}}{$shippingaddress.zipcode} {$shippingaddress.city}{else}{$shippingaddress.city} {$shippingaddress.zipcode}{/if}

{$additional.countryShipping.countryname}

{if $billingaddress.ustid}
Ihre Umsatzsteuer-ID: {$billingaddress.ustid}
Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.
{/if}

Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

{include file="string:{config name=emailfooterplain}"}
EOD;
    }

    private function getContentHtmlEN()
    {
        return <<<'EOD'

<div style="font-family:arial; font-size:12px;">
    {include file="string:{config name=emailheaderhtml}"}
    <br/><br/>
    <p>
		Dear {$billingaddress.salutation|salutation} {$billingaddress.lastname},<br/>
		<br/>
		{if $sInstalment}
		  The next instalment cycle have arrived for the instalment order (Nummer: {$sOrderNumber}) placed at the store {config name=shopName}, kindly refer further details below.<br/>
		{else}
		  We are pleased to inform you that your order has been confirmed, kindly refer further details below.<br/>
		{/if}
		<br/>
		<strong>Information on your order:</strong>
    </p>
    <br/>
    
    <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
        <tr>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Article</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
        </tr>
        {foreach item=details key=position from=$sOrderDetails}
        <tr>
            <td style="border-bottom:1px solid #cccccc;">{$position+1|fill:4} </td>
            <td style="border-bottom:1px solid #cccccc;">{if $details.image.src.0 && $details.modus == 0}<img style="height: 57px;" height="57" src="{$details.image.src.0}" alt="{$details.articlename}" />{else} {$details.articlename|wordwrap:80|indent:4} {/if}</td>
            <td style="border-bottom:1px solid #cccccc;">
              {$details.articlename|wordwrap:80|indent:4}<br/>
              Article-No: {$details.ordernumber|fill:20}
            </td>
            <td style="border-bottom:1px solid #cccccc;">{$details.quantity|fill:6}</td>
            <td style="border-bottom:1px solid #cccccc;">{$details.price|padding:8|currency}</td>
            <td style="border-bottom:1px solid #cccccc;">{$details.amount|padding:8|currency}</td>
        </tr>
        {/foreach}
    </table>
    
    <p>
        <br/>
        <br/>
        Shipping costs: {$sShippingCosts|currency}<br/>
        Net total: {$sAmountNet|currency}<br/>
        {if !$sNet}
		    {foreach $sTaxRates as $rate => $value}
               plus. {$rate|number_format:0}% MwSt. {$value|currency}<br/>
            {/foreach}
            <strong>Total gross: {$sAmount|currency}</strong><br/>
        {/if}
        <br/>
        <br/>
        <strong>Selected payment type:</strong> {$additional.payment.description}<br/>
        {$additional.payment.additionaldescription}<br/>
		<br/>
		<strong>Selected shipping type:</strong> {$sDispatch.name}<br/>
		{$sDispatch.description}<br/>
    </p>
    
    <p>
        {if $sComment}
           <strong>Your comment:</strong><br/>
           {$sComment}<br/>
        {/if}<br/><br/>
        
        <strong>Billing address:</strong><br/>
        {$billingaddress.company}<br/>
        {$billingaddress.firstname} {$billingaddress.lastname}<br/>
        {$billingaddress.street} {$billingaddress.streetnumber}<br/>
        {if {config name=showZipBeforeCity}}{$billingaddress.zipcode} {$billingaddress.city}{else}{$billingaddress.city} {$billingaddress.zipcode}{/if}<br/>
        {$additional.country.countryname}<br/><br/><br/>
        
        <strong>Shipping address:</strong><br/>
        {$shippingaddress.company}<br/>
        {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>
        {$shippingaddress.street} {$shippingaddress.streetnumber}<br/>
        {if {config name=showZipBeforeCity}}{$shippingaddress.zipcode} {$shippingaddress.city}{else}{$shippingaddress.city} {$shippingaddress.zipcode}{/if}<br/>
        {$additional.countryShipping.countryname}<br/><br/>
        
        {if $billingaddress.ustid}
           Your VAT-ID: {$billingaddress.ustid}<br/>
           In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br/>
        {/if}
        <br/>
        <br/>
        If you have any questions, do not hesitate to contact us.<br/>
        {include file="string:{config name=emailfooterhtml}"}
    </p>
</div>

EOD;
    }

    private function getContentHtmlDE()
    {
        return <<<'EOD'

<div style="font-family:arial; font-size:12px;">
    {include file="string:{config name=emailheaderhtml}"}
    <br/><br/>
    <p>
        Hallo {$billingaddress.salutation|salutation} {$billingaddress.lastname},<br/>
        <br/>
		{if $sInstalment}
		    Für Ihre Bestellung (Nummer: {$sOrderNumber}) bei {config name=shopName}, ist die nächste Rate fällig. Bitte beachten Sie weitere Details unten.<br/>
		{else}
	        Wir freuen uns Ihnen mitteilen zu können, dass Ihre Bestellung bestätigt wurde. Bitte beachten Sie weitere Details unten.<br/>
		{/if}
        <br/>
        <strong>Informationen zu Ihrer Bestellung:</strong>
    </p>
    <br/>
    
    <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
        <tr>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Artikel</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Bezeichnung</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
            <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
        </tr>
        {foreach item=details key=position from=$sOrderDetails}
        <tr>
            <td style="border-bottom:1px solid #cccccc;">{$position+1|fill:4} </td>
            <td style="border-bottom:1px solid #cccccc;">{if $details.image.src.0 && $details.modus == 0}<img style="height: 57px;" height="57" src="{$details.image.src.0}" alt="{$details.articlename}" />{else} {$details.articlename|wordwrap:80|indent:4} {/if}</td>
            <td style="border-bottom:1px solid #cccccc;">
              {$details.articlename|wordwrap:80|indent:4}<br>
              Artikel-Nr: {$details.ordernumber|fill:20}
            </td>
            <td style="border-bottom:1px solid #cccccc;">{$details.quantity|fill:6}</td>
            <td style="border-bottom:1px solid #cccccc;">{$details.price|padding:8|currency}</td>
            <td style="border-bottom:1px solid #cccccc;">{$details.amount|padding:8|currency}</td>
        </tr>
        {/foreach}
    </table>
        
    <p>
        <br/>
        <br/>
        Versandkosten: {$sShippingCosts|currency}<br/>
        Gesamtkosten Netto: {$sAmountNet|currency}<br/>
        {if !$sNet}
            {foreach $sTaxRates as $rate => $value}
                zzgl. {$rate|number_format:0}% MwSt. {$value|currency}<br/>
            {/foreach}
            <strong>Gesamtkosten Brutto: {$sAmount|currency}</strong><br/>
        {/if}
        <br/>
        <br/>
        <strong>Gewählte Zahlungsart:</strong> {$additional.payment.description}<br/>
        {$additional.payment.additionaldescription}<br/><br/>
        <strong>Gewählte Versandart:</strong> {$sDispatch.name}<br/>
        {$sDispatch.description}<br/>
    </p>
    
    <p>
        {if $sComment}
            <strong>Ihr Kommentar:</strong><br/>
            {$sComment}<br/>
        {/if}<br/><br/>
        
        <strong>Rechnungsadresse:</strong><br/>
        {$billingaddress.company}<br/>
        {$billingaddress.firstname} {$billingaddress.lastname}<br/>
        {$billingaddress.street} {$billingaddress.streetnumber}<br/>
        {if {config name=showZipBeforeCity}}{$billingaddress.zipcode} {$billingaddress.city}{else}{$billingaddress.city} {$billingaddress.zipcode}{/if}<br/>
        {$additional.country.countryname}<br/><br/><br/>
                
        <strong>Lieferadresse:</strong><br/>
        {$shippingaddress.company}<br/>
        {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>
        {$shippingaddress.street} {$shippingaddress.streetnumber}<br/>
        {if {config name=showZipBeforeCity}}{$shippingaddress.zipcode} {$shippingaddress.city}{else}{$shippingaddress.city} {$shippingaddress.zipcode}{/if}<br/>
        {$additional.countryShipping.countryname}<br/><br/>
        
        {if $billingaddress.ustid}
            Ihre Umsatzsteuer-ID: {$billingaddress.ustid}<br/>
            Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland<br/>
            bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.<br/>
        {/if}
        <br/>
        <br/>
        Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.<br/>
        {include file="string:{config name=emailfooterhtml}"}
    </p>
</div>
EOD;
    }
}
