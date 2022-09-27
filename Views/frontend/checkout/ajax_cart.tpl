{extends file='parent:frontend/checkout/ajax_cart.tpl'}

{* Apple Pay Express Checkout integration *}
{block name='frontend_checkout_ajax_cart_button_container_inner'}
    {$smarty.block.parent}
    
    {if $isApplePayValid}
		{include file='frontend/noval_payment/button.tpl' pageType = 'ajaxCart'}
	{/if}
{/block}
