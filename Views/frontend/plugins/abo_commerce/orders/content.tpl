{extends file="SwagAboCommerce:frontend/abo_commerce/orders/content.tpl'}

{block name="frontend_account_abonnements_overview_message"}
    {if isset($novalAboError)}        
        {if $changeSuccess === true}
            {include file="frontend/_includes/messages.tpl" type="success" content="{s name="SubscriptionChangeSuccess" namespace="frontend/abo_commerce/index"}{/s}"}
        {else}
            {include file="frontend/_includes/messages.tpl" type="error" content="$novalAboError"}
        {/if}
	{else}
		{$smarty.block.parent}
	{/if}
{/block}
