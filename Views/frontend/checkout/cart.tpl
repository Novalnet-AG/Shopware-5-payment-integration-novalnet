{extends file='parent:frontend/checkout/cart.tpl'}

{* Apple Pay Express Checkout integration *}
{block name='frontend_checkout_cart_table_actions'}
    {$smarty.block.parent}
    {if $isApplePayValid}
		<div class="table--actions" style="margin-top:-0.75rem;">
			<div class="main--actions">
				{include file='frontend/noval_payment/button.tpl' pageType='cart'}
			</div>
		</div>
	{/if}
{/block}

{block name='frontend_checkout_actions_confirm_bottom'}
    {$smarty.block.parent}
    {if $isApplePayValid}
		{include file='frontend/noval_payment/button.tpl' pageType='cart'}
	{/if}
{/block}
