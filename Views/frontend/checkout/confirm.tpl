{extends file='parent:frontend/checkout/confirm.tpl'}

{block name="frontend_checkout_confirm_error_messages"}
    {$smarty.block.parent}
    
    {if $sPayment.name == 'novalnetapplepay'}
		<div id="nn_error_block" class="alert is--error is--rounded" style="display: none;">
			<div class="alert--icon">
				<i class="icon--element icon--cross"></i>
			</div>
			<div class="alert--content error-content">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_apple_pay_error'}Apple Pay steht auf diesem Gerät bzw. in diesem Browser nicht zur Verfügung. Bitte wählen Sie eine andere Zahlungsart.{/s}</div>
		</div>
    {/if}
{/block}

{block name="frontend_checkout_confirm_left_payment_method"}
	{$smarty.block.parent}
    {if ($sUserData.additional.payment.name eq 'novalnetsepainstalment' || $sUserData.additional.payment.name eq 'novalnetsepaGuarantee' || $sUserData.additional.payment.name eq 'novalnetsepa') }
        <div id="novalnetsepa_ref_details" >
            {if $maskedDetails.Iban neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_iban'}IBAN{/s}: </strong>{$maskedDetails.Iban|upper}</label>
				</p>
            {/if}
            {if $maskedDetails.Bic neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_bic'}BIC{/s}: </strong>{$maskedDetails.Bic|upper}</label>
				</p>
			{/if}
            {if $maskedDetails.dob neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}: </strong>{$maskedDetails.dob|date:'dd.MM.y'}</label>
				</p>
			{/if}
			{if $maskedDetails.duration neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s}: </strong>{$maskedDetails.duration}</label>
				</p>
			{/if}
        </div>
    {/if}
    {if ($sUserData.additional.payment.name eq 'novalnetinvoiceinstalment' || $sUserData.additional.payment.name eq 'novalnetinvoiceGuarantee' || $sUserData.additional.payment.name eq 'novalnetinvoice') }
		<div id="novalnetinvoice_ref_details" >
			{if $maskedDetails.dob neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}: </strong>{$maskedDetails.dob|date:'dd.MM.y'}</label>
				</p>
			{/if}
			{if $maskedDetails.duration neq ''}
				<p class="none">
					<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s}: </strong>{$maskedDetails.duration}</label>
				</p>
			{/if}

		</div>
    {/if}
    {if $maskedDetails.cardData neq '' && $sUserData.additional.payment.name eq 'novalnetcc'}
        <div id="novalnetcc_ref_details" >
            <p class="none">
                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_holder'}Name des Karteninhabers{/s}: </strong>{$maskedDetails.cardHolder}</label>
            </p>
            <p class="none">
                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_number'}Kreditkartennummer{/s}: </strong>{$maskedDetails.cardData}</label>
            </p>
            <p class="none">
                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_date'}Ablaufdatum{/s}: </strong>{$maskedDetails.expiryDate}</label>
            </p>
        </div>
    {/if}
{/block}

{block name='frontend_checkout_confirm_tos_panel'}
	{$smarty.block.parent}
	<input type="hidden" id="nn_applepay_token" name="novalnetapplepayFormData[walletToken]" value="">
	<input type="hidden" id="nn_googlepay_token" name="novalnetgooglepayFormData[walletToken]" value="">
	<input type="hidden" id="nn_googlepay_do_redirect" name="novalnetgooglepayFormData[doRedirect]" value="">
{/block}

{block name='frontend_checkout_confirm_confirm_table_actions'}
	{if $sPayment.name == 'novalnetapplepay' || $sPayment.name == 'novalnetgooglepay' }
		<div class="table--actions actions--bottom">
			<div class="main--actions">
				{include file='frontend/noval_payment/button.tpl' pageType = 'checkout' nnPaymentName = $sPayment.name}
			</div>
		</div>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}
