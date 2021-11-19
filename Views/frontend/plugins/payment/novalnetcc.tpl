{namespace name='frontend/novalnet/payment'}
{include file='frontend/plugins/payment/novalnetlogo.tpl'}
<div class="space"></div>
<div class="debit">
<input type="hidden" name="novalnetccShopVersion" id = "novalnetccShopVersion" value="{$shopVersion}"/>
<noscript>
<span style="color:red">{s namespace='frontend/novalnet/payment' name='novalnet_no_script_enabled'}Aktivieren Sie bitte JavaScript in Ihrem Browser, um die Zahlung fortzusetzen.{/s} </span>
</noscript>

{assign var="nn_cc_new_acc_details" value="1"}
{assign var="novalnetcc_iframe_display" value="block"}
{assign var="novalnetcc_ref_details_display" value="none"}
    {if $novalnetcc_account_details.cc_no neq ''}
        <p class="none" id="novalnetcc_new_acc" style="color: blue; cursor:pointer;">
            <u>
                <b>
                    {s namespace='frontend/novalnet/payment' name='novalnetcc_new_account'}Neue Kartendaten eingeben{/s}
                </b>
            </u>
        </p>
        {assign var="nn_cc_new_acc_details" value="0"}
        {assign var="novalnetcc_ref_details_display" value="block"}
        {assign var="novalnetcc_iframe_display" value="none"}
    {/if}  
    
    <iframe id = "nnIframe" frameborder="0" scrolling="no" style="display:{$novalnetcc_iframe_display}" style="border:0"></iframe>
    
    <div id="novalnetcc_ref_details" style="display:{$novalnetcc_ref_details_display}">
		<p class="none">
			<label  style="width:50%;">{s namespace='frontend/novalnet/payment' name='novalnetcc_card_type'}Kreditkartentyp{/s}</label>
			{if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" value="{$novalnetcc_account_details.cc_card_type}" readonly/>
		</p>
		<p class="none">
			<label  style="width:50%;">{s namespace='frontend/novalnet/payment' name='novalnetcc_card_holder'}Name des Karteninhabers{/s}</label>
			{if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" value="{$novalnetcc_account_details.cc_holder}" readonly/>
		</p>
		<p class="none">
			<label  style="width:50%;">{s namespace='frontend/novalnet/payment' name='novalnetcc_card_number'}Kreditkartennummer{/s}</label>
			{if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" value="{$novalnetcc_account_details.cc_no}" readonly/>
		</p>
		<p class="none">
			<label  style="width:50%;">{s namespace='frontend/novalnet/payment' name='novalnetcc_card_date'}Ablaufdatum{/s}</label>
			{if $shopVersion gte '5.0.0'}<br />{/if}<input type="text" style="width:{if $shopVersion gte '5.0.0'}70%;{else}45%{/if}" value="{$novalnetcc_account_details.cc_exp_month} / {$novalnetcc_account_details.cc_exp_year}" readonly/>
		</p>
	</div>
	{if $nnConfigArray.novalnetcc_shopping_type eq 'one'  && $controller != 'AboCommerce'}
		<br>
		<label style="display:{$novalnetcc_iframe_display}" id="nn_cc_confirm_save_check"><input type="checkbox" name="confirm_save_check" value="1"> {s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_save_card'} Meine Kartendaten für zukünftige Bestellungen speichern {/s} </label>
    {/if}
    
		<input type="hidden" id="cc3d"  value="1"/>
		<input type="hidden" id="cc3d_lang"  value="{s namespace='frontend/novalnet/payment' name='frontend_description_novalnet_redirect'}Der Betrag wird von Ihrer Kreditkarte abgebucht, sobald die Bestellung abgeschickt wird.<br>Bitte schließen Sie den Browser nach der erfolgreichen Zahlung nicht, bis Sie zum Shop zurückgeleitet wurden.{/s}"/>

    <input type="hidden" id="novalnetcc_given_account"  name="novalnetcc_given_account" value="{s namespace='frontend/novalnet/payment' name='novalnetcc_given_account'}Eingegebene Kartendaten{/s}"/>
    <input type="hidden" id="novalnetcc_new_account"  name="novalnetcc_new_account" value="{s namespace='frontend/novalnet/payment' name='novalnetcc_new_account'}Neue Kartendaten eingeben{/s}"/>
    <input type="hidden" id="nn_cc_new_acc_details"  name="nn_cc_new_acc_details" value="{$nn_cc_new_acc_details}"/>
    <input type="hidden" id="nn_cc_paymentid"  name="nn_cc_paymentid" value="{$payment_mean.id}"/>
    <input type="hidden" id="nn_user_fname"  name="nn_user_fname" value="{$sUserData.billingaddress.firstname}"/>
	<input type="hidden" id="nn_user_lname"  name="nn_user_lname" value="{$sUserData.billingaddress.lastname}"/>
	<input type="hidden" id="nn_user_email"  name="nn_user_email" value="{$sUserData.additional.user.email}"/>
	<input type="hidden" id="nn_user_street"  name="nn_user_street" value="{$sUserData.billingaddress.street}"/>
	<input type="hidden" id="nn_user_city"  name="nn_user_city" value="{$sUserData.billingaddress.city}"/>
	<input type="hidden" id="nn_user_zipcode"  name="nn_user_zipcode" value="{$sUserData.billingaddress.zipcode}"/>
	<input type="hidden" id="nn_user_countrycode"  name="nn_user_countrycode" value="{$sUserData.additional.country.countryiso}"/>
	<input type="hidden" id="customer_no" value="{$sUserData.additional.user.customernumber}"/>
	<input type="hidden" id="nn_amount"  name="nn_amount" value="{$nn_amount}"/>
	<input type="hidden" id="nn_currency"  name="nn_currency" value="{$nnCurrency}"/>
	<input type="hidden" id="shop_lang" name="shop_lang"  value="{$shop_lang}"/>
    <input type="hidden" id="client_key" name="client_key"  value="{$nnConfigArray.novalnet_clientkey} "/>
    <input type="hidden" id="nn_cc_test_mode" name="nn_cc_test_mode"  value="{$nnConfigArray.novalnetcc_test_mode}"/>
    <input type="hidden" id="nn_enforce_cc_3d" name="nn_cc_test_mode"  value="{$nnConfigArray.novalnetcc_force_cc3d}"/>
    <input type="hidden" id="CreditcardDefaultLabel" value="{$nnConfigArray.novalnetcc_standard_label}"/>
    <input type="hidden" id="CreditcardDefaultInput" value="{$nnConfigArray.novalnetcc_standard_field}"/>
    <input type="hidden" id="CreditcardDefaultCss"  value="{$nnConfigArray.novalnetcc_standard_text}"/>
    <input type="hidden" id="CreditcardCCErrorLang" value="{s namespace='frontend/novalnet/payment' name='novalnetcc_error'}Your credit card details are invalid{/s}"/>
    <input type="hidden" id="novalnet_cc_hash"       name="novalnet_cc_hash"      value=""/>
    <input type="hidden" id="novalnet_cc_uniqueid"   name="novalnet_cc_uniqueid"  value=""/>
    <input type="hidden" id="novalnet_cc_mask_no"    name="novalnet_cc_mask_no"  value=""/>
    <input type="hidden" id="novalnet_cc_mask_type"  name="novalnet_cc_mask_type"  value=""/>
    <input type="hidden" id="novalnet_cc_mask_holder" name="novalnet_cc_mask_holder"  value=""/>
    <input type="hidden" id="novalnet_cc_mask_month" name="novalnet_cc_mask_month"  value=""/>
    <input type="hidden" id="novalnet_cc_mask_year" name="novalnet_cc_mask_year"  value=""/>
    <input type="hidden" id="novalnet_do_redirect" name="novalnet_do_redirect"  value=""/>
    
<style>
@media only screen and (max-width: 600px) {
  iframe {
    width: 100%;
  }
}
</style>
<script type="text/javascript">

if(typeof(jQuery) == 'undefined') {
    ﻿document.write('<scr'+'ipt src="{link file='frontend/_resources/js/jquery-2.1.4.min.js'}"></sc'+'ript>');
}
</script>
<script src="{link file='frontend/_resources/js/novalnetcc.js'}"></script>
</div>
