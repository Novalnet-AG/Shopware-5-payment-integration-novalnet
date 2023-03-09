{extends file='parent:frontend/checkout/ajax_cart.tpl'}

{* Apple Pay Express Checkout integration *}
{block name='frontend_checkout_ajax_cart_button_container_inner'}
    {$smarty.block.parent}
    {foreach from=$nnWalletPayments key=k item=nnPaymentName}
		{if is_array($nnConfig["{$nnPaymentName}_button_display_fields"]) && 'ajaxCart'|in_array:$nnConfig["{$nnPaymentName}_button_display_fields"] && $sBasket.content}
			{include file='frontend/noval_payment/button.tpl' pageType='ajaxCart'}
		{/if}
	{/foreach}
{/block}
