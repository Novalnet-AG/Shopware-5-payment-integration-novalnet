{extends file="parent:frontend/checkout/change_payment.tpl"}

{block name='frontend_checkout_payment_fieldset_input_radio'}
	{if $payment_mean.name eq 'novalnetpay'}
		<div class="method--input">
			<input type="radio" name="payment" class="radio auto_submit" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $sFormData.payment or (!$sFormData && !$smarty.foreach.register_payment_mean.index)} checked="checked"{/if} style = "display:none" />
		</div>
		{include file='frontend/noval_payment/load_payment_form.tpl' } 
	{else}
		{$smarty.block.parent}
    {/if}
{/block}

{block name='frontend_checkout_payment_fieldset_input_label'}
	{if $payment_mean.name eq 'novalnetpay'}
	{else}
		{$smarty.block.parent}
    {/if}
{/block}


{block name='frontend_checkout_payment_fieldset_description'}
	{if $payment_mean.name eq 'novalnetpay'}
	{else}
		{$smarty.block.parent}
    {/if}
{/block}
