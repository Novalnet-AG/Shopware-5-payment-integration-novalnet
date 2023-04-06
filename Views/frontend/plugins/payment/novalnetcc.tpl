{namespace name='frontend/plugins/payment/novalnetcc'}

<div class="space"></div>
<div class="novalnet-payments"
     data-paymentName="{$payment_mean.name}"
	 data-ccData="{$ccParams|htmlentities}"
	 data-deleteConfirmMsg="{s namespace='frontend/novalnet/payment' name='frontend_credit_card_delete_message'}Sind Sie sicher, dass Sie diese Kreditkartendaten entfernen möchten?{/s}"
	 data-ccErrorText="{s namespace='frontend/novalnet/payment' name='frontend_invalid_iban'}Ihre Kontodaten sind ungültig{/s}">
	
	{if !empty($cardBundle) && $nnConfig['novalnetcc_shopping_type'] eq '1' }
		{assign var="isAlreadyExists" value = ""}
		<div class="{$payment_mean.name}SavedTokens nnclass">
			<ul class="{$payment_mean.name}-SavedPaymentMethods novalnet-SavedPaymentMethods" data-count="{$cardBundle|count}" style="list-style: none;">
				{foreach from=$cardBundle key=k item=cardData}
					{if $isAlreadyExists neq $cardData.accountData && $cardData.accountData neq ''}
						{assign var="isAlreadyExists" value = $cardData.accountData}
						<li class="{$payment_mean.name}-SavedPaymentMethods-token novalnet-SavedPaymentMethods-token">
							<label for="novalnetcc-payment-token-{$cardData.token}">
								<input id="novalnetcc-payment-token-{$cardData.token}" type="radio" name="novalnetccFormData[paymentToken]" value="{$cardData.token}" class="novalnetcc-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput" {if $k == 0} checked{/if}/>&nbsp; &nbsp;
								{if $cardData.cardBrand neq ''}
									<img class="onclick-image" src={link file="frontend/_public/src/img/{$cardData.cardBrand|lower}.png"} alt="NovalnetPayment logo" title="NovalnetPayment logo" />
								{/if}
								<span>
									{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_ending'} mit Endziffern {/s} {$cardData.accountData} ({s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_expires'} gültig bis {/s} {$cardData.expiryDate})&nbsp; &nbsp; <a href="javascript:void(0);"  class="remove_card_details" style="color: #5f7285;" data-value="{$cardData.token}" ><i class="icon--trash"></i></a>
								</span>
							</label>
						</li>
					{/if}
				{/foreach}
				{if $isAlreadyExists ne ''}
					<li class="novalnetcc-SavedPaymentMethods-new novalnet-SavedPaymentMethods-new">
						<label for="novalnetcc-payment-new"><input id="novalnetcc-payment-new" type="radio" name="novalnetccFormData[paymentToken]" value="new" style="width:auto;" class="novalnetcc-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput"/>&nbsp; &nbsp;
							{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_new_payment'} Neue Kreditkarte hinzufügen {/s}
						</label>
					</li>
				{/if}
			</ul>
		</div>
	{/if}

	<div id="{$payment_mean.name}PaymentForm" class="{$payment_mean.name}PaymentForm">
		<iframe id="nnIframe" frameborder="0" scrolling="no" width="50%"></iframe>
		{if $nnConfig['novalnetcc_shopping_type'] eq '1' && $sUserData.additional.user.accountmode eq '0' }
			<div class="saveCreditCheckBox">
				<input name="{$payment_mean.name}FormData[saveCard]" type="checkbox" id="saveCreditCardData" value="true" checked="checked" class="checkbox">
				<label for="saveCreditCardData">{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_save_card'} Ich möchte meine Kartendaten für spätere Einkäufe speichern  {/s}</label>
			</div>
		{/if}
		<input type="hidden" id="novalnetcc_panhash" name="novalnetccFormData[panhash]"/>
		<input type="hidden" id="novalnetcc_uniqueid" name="novalnetccFormData[uniqueid]"  value=""/>
		<input type="hidden" id="novalnetcc_card_holder" name="novalnetccFormData[cardHolder]"  value=""/>
		<input type="hidden" id="novalnetcc_card_no" name="novalnetccFormData[cardData]"  value=""/>
		<input type="hidden" id="novalnetcc_card_type" name="novalnetccFormData[cardType]"  value=""/>
		<input type="hidden" id="novalnetcc_expiry_date" name="novalnetccFormData[expiryDate]"  value=""/>
		<input type="hidden" id="novalnetcc_do_redirect" name="novalnetccFormData[doRedirect]"  value=""/>
		<input type="hidden" value="{$payment_mean.name}" class="novalnet-payment-name">
		<input type="hidden" value="{$payment_mean.id}" name="{$payment_mean.name}Id" id="{$payment_mean.name}Id">
	</div>
	{include file='frontend/plugins/payment/novalnetlogo.tpl'}
</div>
