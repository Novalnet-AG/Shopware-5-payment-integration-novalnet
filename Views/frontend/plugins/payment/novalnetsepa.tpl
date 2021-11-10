{namespace name='frontend/novalnet/payment'}
{include file='frontend/plugins/payment/novalnetlogo.tpl'}
<div class="space"></div>
<div class="debit">
<input type="hidden" name="novalnetsepaShopVersion" id = "novalnetsepaShopVersion" value="{$shopVersion}"/>
<noscript>
<span style="color:red"> {s namespace='frontend/novalnet/payment' name='novalnet_no_script_enabled'}Aktivieren Sie bitte JavaScript in Ihrem Browser, um die Zahlung fortzusetzen.{/s} </span>
</noscript>

{assign var="nn_sepa_new_acc_details" value="1"}
{assign var="novalnetsepa_acc_display" value="block"}
{assign var="novalnetsepa_ref_details_display" value="none"}
{assign var="novalnetsepa_after_error" value="0"}
{if $smarty.get.sNNError }
{assign var="novalnetsepa_after_error" value="1"}
{/if}
    {if $novalnetsepa_account_details.iban neq ''}
        <p class="none" id="novalnetsepa_new_acc" style="color: blue; cursor: pointer;"><u><b>{s namespace='frontend/novalnet/payment' name='novalnetsepa_new_account'}Neue Kontodaten eingeben{/s}</u></b></p>
        {assign var="nn_sepa_new_acc_details" value="0"}
        {assign var="novalnetsepa_acc_display" value="none"}
        {assign var="novalnetsepa_ref_details_display" value="block"}
    {/if}

<div id="novalnetsepa_acc" style="display:{$novalnetsepa_acc_display}">
    <p class="none">
		<label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_account_holder'}Kontoinhaber{/s}<sup style='color:red;'>*</sup>:</label><br/>
        <input type="text" name="novalnet_sepa_account_holder" id="novalnet_sepa_account_holder" value="{$nn_customer_full_name }" placeholder="{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_account_holder'}Kontoinhaber{/s}" class="text" autocomplete="off" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" onkeypress="return sepaHolderFormat(event)"/>
    </p>
     <p class="none">
		 <label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_iban'}IBAN{/s}<sup style='color:red;'>*</sup>:</label><br/>
		 <input type="text" id="novalnet_sepa_iban" name="novalnet_sepa_iban" onchange="return NovalnetUtility.formatIban(event);" class="text" autocomplete="off" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" placeholder="DE00 0000 0000 0000 0000 00" />
    </p>
    {if $nnConfigArray.novalnetsepa_shopping_type eq 'one'}
		<label id="nn_sepa_confirm_save_check" style="display:{$novalnetsepa_acc_display}"><input type="checkbox" name="confirm_save_check" value="1"> {s namespace='frontend/novalnet/payment' name='frontend_novalnetsepa_save_card'} Meine Kontodaten für zukünftige Bestellungen speichern {/s}</label>
	{/if}
    <p class="none">
        <a id="sepa_mandate"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_mandate_confirm'}Ich erteile hiermit das SEPA-Lastschriftmandat (elektronische Übermittlung) und bestätige, dass die Bankverbindung korrekt ist.{/s}</strong></a>
        <p class="none">
	<div id="sepa_mandate_details_desc" style="display:none;border: 1px solid #c7c8ca;padding: 16px;padding: 1rem;border-radius: 5px;">
        <p>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_authorise'}Ich ermächtige den Zahlungsempfänger, Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von dem Zahlungsempfänger auf mein Konto gezogenen Lastschriften einzulösen.{/s}</p>

        <p><b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_mandate_creditor'}Gläubiger-Identifikationsnummer: DE53ZZZ00000004253{/s}</b></p>

        <p><b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_note'}Note:{/s}</b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_entitled'} Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.{/s}</p>
    </div>
    </p>
    </p>
</div>
<div id="novalnetsepa_ref_details" style="display:{$novalnetsepa_ref_details_display}">
    <p class="none">
        <label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_account_holder'}Kontoinhaber{/s}:</label>
        {if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" value="{$novalnetsepa_account_details.bankaccount_holder}" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" readonly/>
    </p>
    <p class="none">
        <label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_iban'}IBAN{/s}:</label>
        {if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" value="{$novalnetsepa_account_details.iban}" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" readonly/>
    </p>
</div>
{if $nnConfigArray.novalnetsepa_guarantee_payment && $date_birth_field && $get_birth_date eq '' && $companyValue eq ''}
    <label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum:{/s}<sup style='color:red;'>*</sup>:</label>
            <div class="register--birthday field--select">
            <div class="select-field">
                <select id="sepaDateOfBirthDay" name="sepaDateOfBirthDay">
                <option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_date'}Tag{/s}</option>
                    {for $day = 1 to 31}                        
                        <option value="{$day}" {if ! empty ($birthdate_val.day) && ($day == $birthdate_val.day) }selected{/if}>{$day}</option>
                    {/for}
                </select>
                </div>

                <div class="select-field">
                <select name="sepaDateOfBirthMonth" id="sepaDateOfBirthMonth">
                <option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_month'}Monat{/s}</option>
                    {for $month = 1 to 12}
                        <option value="{$month}" {if ! empty ($birthdate_val.month) && ($month == $birthdate_val.month) }selected{/if}>{$month}</option>
                    {/for}
                </select>
                </div>

                <div class="select-field">
                <select name="sepaDateOfBirthYear" id="sepaDateOfBirthYear">
                <option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_year'}Jahr{/s}</option>
                    {for $year = date("Y") to date("Y")-120 step=-1}
                        <option value="{$year}" {if ! empty ($birthdate_val.year) && ($year == $birthdate_val.year) }selected{/if}>{$year}</option>
                    {/for}
                </select>
                </div>
                </div>
{/if}
    <span id="nn_sepa_loader" style="display:none;" ></span>
    <input type="hidden" id="nn_sepa_paymentid"  name="nn_sepa_paymentid" value="{$payment_mean.id}"/>
    <input type="hidden" id="nn_sepa_id"  name="nn_sepa_id" value="37"/>
    <input type="hidden" id="nn_sepa_iban" value=""/>
    <input type="hidden" id="nn_sepa_iban_mask" name="nn_sepa_iban_mask" value=""/>
    <input type="hidden" id="nn_lang_valid_merchant_credentials" value="{s namespace='frontend/novalnet/payment' name='error_novalnet_basicparam'}Füllen Sie bitte alle Pflichtfelder aus.{/s}"/>
    <input type="hidden" id="nn_lang_valid_account_details" value="{s namespace='frontend/novalnet/payment' name='novalnet_payment_validate_invalid_directdebit_message'}Ihre Kontodaten sind ungültig.{/s} "/>
    <input type="hidden" id="novalnetsepa_pinbycallback_telephone_mobileno_error" value="{$novalnetsepa_pinbycallback_telephone_mobileno_error}"/>
    <input type="hidden" id="novalnetsepa_date_of_birth_error" value="{s namespace='frontend/novalnet/payment' name='novalnet_date_of_birth_error'}Geben Sie bitte Ihr Geburtsdatum ein{/s} "/>
    <input type="hidden" id="nn_sepa_ref_hash" name="nn_sepa_ref_hash" value="{$novalnetsepa_one_click_hash}">
    <input type="hidden" id="nn_sepa_new_acc_details"  name="nn_sepa_new_acc_details" value="{$nn_sepa_new_acc_details}"/>
    <input type="hidden" id="novalnetsepa_given_account"  name="novalnetsepa_given_account" value="{s namespace='frontend/novalnet/payment' name='novalnetsepa_given_account'}Eingegebene Kontodaten{/s}"/>
    <input type="hidden" id="novalnetsepa_new_account"  name="novalnetsepa_new_account" value="{s namespace='frontend/novalnet/payment' name='novalnetsepa_new_account'}Neue Kontodaten eingeben{/s}"/>
    <input type="hidden" id="novalnetsepa_after_error"  name="novalnetsepa_after_error" value="{$novalnetsepa_after_error}"/>
    <input type="hidden" id="novalnetsepa_force"  name="novalnetsepa_force" value="{$nnConfigArray.novalnetsepa_force_guarantee_payment}"/>
<script type="text/javascript">
if(typeof(jQuery) == 'undefined') {
    ﻿document.write('<scr'+'ipt src="{link file='frontend/_resources/js/jquery-2.1.4.min.js'}"></sc'+'ript>');
}
</script>
<link rel='stylesheet' type='text/css' media='all' href="{link file='frontend/_resources/css/nn_loader.css'}">
<link rel='stylesheet' type='text/css' media='all' href="{link file='frontend/_resources/css/novalnet_sepa.css'}">
<script src="{link file='frontend/_resources/js/novalnetsepa.js'}"></script>
</div>
