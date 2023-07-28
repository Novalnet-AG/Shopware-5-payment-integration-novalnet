{extends file='parent:frontend/checkout/confirm.tpl'}

{block name="frontend_checkout_confirm_left_payment_method"}
	{if $sUserData.additional.payment.name eq 'novalnetpay' && $maskedDetails.result.status eq 'SUCCESS' }
		<p class="payment--method-info">
			<strong class="payment--title">{s name="ConfirmInfoPaymentMethod" namespace="frontend/checkout/confirm"}{/s}</strong>
			<span class="payment--description">{$maskedDetails.payment_details.name}</span>
		</p>

		{if !$sUserData.additional.payment.esdactive && {config name="showEsd"}}
			<p class="payment--confirm-esd">{s name="ConfirmInfoInstantDownload" namespace="frontend/checkout/confirm"}{/s}</p>
		{/if}
	    {if $maskedDetails.payment_details.type eq 'DIRECT_DEBIT_SEPA' || $maskedDetails.payment_details.type eq 'GUARANTEED_DIRECT_DEBIT_SEPA' || $maskedDetails.payment_details.type eq 'INSTALMENT_DIRECT_DEBIT_SEPA'}
	        <div id="novalnetsepa_ref_details" >
	            {if $maskedDetails.booking_details.iban neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_iban'}IBAN{/s}: </strong>{$maskedDetails.booking_details.iban|upper}</label>
					</p>
	            {/if}
	            {if $maskedDetails.booking_details.bic neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_bic'}BIC{/s}: </strong>{$maskedDetails.booking_details.bic|upper}</label>
					</p>
				{/if}
	            {if $maskedDetails.booking_details.birth_date neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}: </strong>{$maskedDetails.booking_details.birth_date|date:'dd.MM.y'}</label>
					</p>
				{/if}
				{if $maskedDetails.booking_details.cycle neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s}: </strong>{$maskedDetails.booking_details.cycle}</label>
					</p>
				{/if}
	        </div>
	    {/if}
	    {if $maskedDetails.payment_details.type eq 'INSTALMENT_INVOICE' || $maskedDetails.payment_details.type eq 'GUARANTEED_INVOICE' || $maskedDetails.payment_details.type eq 'INVOICE' }
			<div id="novalnetinvoice_ref_details" >
				{if $maskedDetails.booking_details.birth_date|date:'dd.MM.y' neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}: </strong>{$maskedDetails.booking_details.birth_date|date:'dd.MM.y'}</label>
					</p>
				{/if}
				{if $maskedDetails.booking_details.cycle neq ''}
					<p class="none">
						<label style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s}: </strong>{$maskedDetails.booking_details.cycle}</label>
					</p>
				{/if}

			</div>
	    {/if}
	    {if $maskedDetails.card_details neq '' && $maskedDetails.payment_details.type eq 'CREDITCARD' }
	        <div id="novalnetcc_ref_details" >
	            <p class="none">
	                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_holder'}Name des Karteninhabers{/s}: </strong>{$maskedDetails.card_details.card_holder}</label>
	            </p>
	            <p class="none">
	                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_number'}Kreditkartennummer{/s}: </strong>{$maskedDetails.card_details.card_number}</label>
	            </p>
	            <p class="none">
	                <label  style="width:50%;"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_card_date'}Ablaufdatum{/s}: </strong>{$maskedDetails.card_details.card_exp_month}/{$maskedDetails.card_details.card_exp_year}</label>
	            </p>
	        </div>
	    {/if}
    {else}
		{$smarty.block.parent}
    {/if}
    
{/block}
	

