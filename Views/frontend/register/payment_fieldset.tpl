{extends file="parent:frontend/register/payment_fieldset.tpl"}

{block name="frontend_register_payment_headline"}
    {$smarty.block.parent}
    <input type="hidden" value="{url controller=NovalPayment action=deleteCard forceSecure}" name="nnDeleteUrl" id="nnDeleteUrl">
    <input type="hidden" value="{$sUserData.additional.user.customernumber}" name="nnCustomerId" id="nnCustomerId">
{/block}
        
{block name="frontend_register_payment_fieldset_description"}
	{if $payment_mean.name|strstr:"novalnet"}
		<div class="payment--description panel">
			{$name = "frontend_payment_name_"|cat:$payment_mean.name}
			{$paymenName = ''|snippet:$name:'frontend/novalnet/payment'}
			{if $nnConfig['novalnet_payment_logo_display'] }
				<img class="nn-image" src={link file="frontend/_public/src/img/{$payment_mean.name}.png"} alt="{$paymenName}" title="{$paymenName}" />
			{/if}
		</div>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}
