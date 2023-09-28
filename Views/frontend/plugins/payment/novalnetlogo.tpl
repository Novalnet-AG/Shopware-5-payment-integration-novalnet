{namespace name='frontend/plugins/payment/novalnetdescription'}

<div class="debit">
    {if $nnConfig["{$payment_mean.name}_test_mode"]}
        <div id="nn-test-drive" class="nn-test-drive">
            <span>{s namespace='frontend/novalnet/payment' name='testModeMessage'}Testmodus{/s}</span>
        </div>
        <br />
    {/if}
    
</div>

<div class="novalnet-info-box">
	<strong>{include file="string:{$payment_mean.additionaldescription}"}</strong>
	{if $nnConfig["{$payment_mean.name}_payment_notification_to_buyer"] }
		<br />
		<div id="notification_for_buyer" class="notification_for_buyer">
			<strong>{$nnConfig["{$payment_mean.name}_payment_notification_to_buyer"]|strip_tags:true}</strong>
		</div>
	{/if}
</div>
