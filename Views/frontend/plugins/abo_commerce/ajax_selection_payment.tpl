{extends file="SwagAboCommerce:frontend/abo_commerce/orders/ajax_selection_payment.tpl"}

{block name="frontend_abonnement_payment_fieldset_input"}
	{if $payment_mean.name eq 'novalnetpay'}
		<div class="payment--selection-input">
			<input type="radio" name="payment" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $form_data.selectedPaymentId or (!$form_data.selectedPaymentId && !$payment_mean@index)} checked="checked"{/if} />
		</div>
		{include file='frontend/noval_payment/load_payment_form.tpl' } 
	{else}
		{$smarty.block.parent}
	{/if}
{/block}



