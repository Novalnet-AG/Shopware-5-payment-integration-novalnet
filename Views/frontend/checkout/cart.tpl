{extends file='parent:frontend/checkout/cart.tpl'}

{* Apple Pay Express Checkout integration *}
{block name='frontend_checkout_actions_confirm_bottom'}
    {$smarty.block.parent}
    {foreach from=$nnWalletPayments key=k item=nnPaymentName}
		{if is_array($nnConfig["{$nnPaymentName}_button_display_fields"]) && 'cart'|in_array:$nnConfig["{$nnPaymentName}_button_display_fields"]}
			{include file='frontend/noval_payment/button.tpl' pageType='cart'}
		{/if}
	{/foreach}
{/block}
