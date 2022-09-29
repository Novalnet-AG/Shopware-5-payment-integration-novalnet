{namespace name='frontend/plugins/payment/novalnetpaypal'}

<div class="novalnet-payments"
     data-paymentName="{$payment_mean.name}"
     data-deleteConfirmMsg="{s namespace='frontend/novalnet/payment' name='frontend_sepa_card_delete_message'}Sind Sie sicher, dass Sie diese Kontodaten entfernen möchten?{/s}">
    
    {if !empty($cardBundle) && $nnConfig['novalnetpaypal_shopping_type'] eq '1' }
		{assign var="isAlreadyExists" value = ""}
		<div class="{$payment_mean.name}SavedTokens nnclass">
			<ul class="{$payment_mean.name}-SavedPaymentMethods novalnet-SavedPaymentMethods" data-count="{$cardBundle|count}" style="list-style: none;">
				{foreach from=$cardBundle key=k item=cardData}
					{if $isAlreadyExists neq $cardData.paypal_account && $cardData.paypal_account ne '' }
						{assign var="isAlreadyExists" value = $cardData.paypal_account}
						<li class="{$payment_mean.name}-SavedPaymentMethods-token novalnet-SavedPaymentMethods-token">
							<label for="novalnetpaypal-payment-token-{$cardData.token}">
								<input id="novalnetpaypal-payment-token-{$cardData.token}" type="radio" name="novalnetpaypalFormData[paymentToken]" value="{$cardData.token}" class="novalnetpaypal-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput" {if $k == 0} checked{/if}/>&nbsp; &nbsp;
								<span>
									{s namespace='frontend/novalnet/payment' name='frontend_novalnetpaypal_account'} PayPal-Account: {/s} {$cardData.paypal_account}&nbsp; &nbsp; <a href="javascript:void(0);"  class="remove_card_details" style="color: #5f7285;" data-value="{$cardData.token}" ><i class="icon--trash"></i></a>
								</span>
							</label>
						</li>
					{/if}
				{/foreach}
				{if $isAlreadyExists ne ''}
					<li class="novalnetpaypal-SavedPaymentMethods-new novalnet-SavedPaymentMethods-new">
						<label for="novalnetpaypal-payment-new"><input id="novalnetpaypal-payment-new" type="radio" name="novalnetpaypalFormData[paymentToken]" value="new" style="width:auto;" class="novalnetpaypal-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput"/>&nbsp; &nbsp;
							{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_new_payment'} Verwende eine neue Zahlungsmethode {/s}
						</label>
					</li>
				{/if}
			</ul>
		</div>
	{/if}
	 
    <div id="{$payment_mean.name}PaymentForm" class="{$payment_mean.name}PaymentForm">
		{if $nnConfig['novalnetpaypal_shopping_type'] eq '1' && $sUserData.additional.user.accountmode eq '0' }
			<div class="savePaypalDataBox">
				<input name="{$payment_mean.name}FormData[saveCard]" type="checkbox" id="savePaypalData" value="true" checked="checked" class="checkbox">
				<label for="savePaypalData">{s namespace='frontend/novalnet/payment' name='frontend_novalnetsepa_save_card'} Speichern für zukünftige Einkäufe {/s}</label>
			</div>
		{/if}
	</div>
	<input type="hidden" value="{$payment_mean.name}" class="novalnet-payment-name">
	<input type="hidden" value="{$payment_mean.id}" name="{$payment_mean.name}Id" id="{$payment_mean.name}Id">
	{include file='frontend/plugins/payment/novalnetlogo.tpl'}
</div>
