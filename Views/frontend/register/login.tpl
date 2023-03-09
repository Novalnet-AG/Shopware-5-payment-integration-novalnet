{extends file='parent:frontend/register/login.tpl'}

{block name='frontend_register_login_form'}
    {$smarty.block.parent}
    {foreach from=$nnWalletPayments key=k item=nnPaymentName}
		{if is_array($nnConfig["{$nnPaymentName}_button_display_fields"]) && 'register'|in_array:$nnConfig["{$nnPaymentName}_button_display_fields"]}
			{include file='frontend/noval_payment/button.tpl' pageType = 'login'}
		{/if}
	{/foreach}
{/block}
