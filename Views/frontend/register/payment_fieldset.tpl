{extends file="parent:frontend/register/payment_fieldset.tpl"}

{block name="frontend_register_payment_fieldset_input"}
    {if $payment_mean.name eq 'novalnetpay'}
        <div class="payment--selection-input">
             <input type="radio" name="register[payment]" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $form_data.payment or (!$form_data && !$payment_mean@index)} checked="checked"{/if}/>
        </div>
            {include file='frontend/noval_payment/load_payment_form.tpl' } 
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name="frontend_register_payment_fieldset_input_label"}
    {if $payment_mean.name eq 'novalnetpay'}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name="frontend_register_payment_fieldset_description"}
    {if $payment_mean.name eq 'novalnetpay'}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}



