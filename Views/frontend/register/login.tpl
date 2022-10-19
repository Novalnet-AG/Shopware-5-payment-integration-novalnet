{extends file='parent:frontend/register/login.tpl'}

{block name='frontend_register_login_form'}
    {$smarty.block.parent}
    {if $isApplePayValid}
		{include file='frontend/noval_payment/button.tpl' pageType = 'login'}
	{/if}
{/block}
