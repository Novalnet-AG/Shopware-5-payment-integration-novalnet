{extends file='parent:frontend/checkout/finish.tpl'}

{block name="frontend_checkout_finish_teaser_title"}
	{$smarty.block.parent}
    <p class="panel--body is--align-center">
        {$sComment|nl2br}
        {if $nncheckoutToken}
			<button class="bz-checkout-btn nn-btn" id="novalnet_button" >
				{s namespace='frontend/novalnet/payment' name='frontend_novalnetcashpayment_button'}Bezahlen mit Barzahlen{/s}						
			</button>
			<style>
			  .nn-btn {
				display:inline-block !important;
				float:none !important;
				background-color: rgb(99, 169, 36);
				background-image: none;
				border-color: rgb(82, 145, 25);
				border-radius: 2px;
				border-style: solid;
				border-width: 1px;
				box-sizing: border-box;
				color: rgb(255, 255, 255);
				cursor: pointer;
				font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
				font-size: 14px;
				font-stretch: normal;
				font-style: normal;
				font-variant: normal;
				font-weight: normal;
				letter-spacing: normal;
				line-height: 20px;
				overflow: visible;
				padding: 6px 12px;
				position: relative;
				text-align: center;
				text-indent: 0px;
				text-rendering: auto;
				text-shadow: none;
				text-transform: none;
				touch-action: manipulation;
				vertical-align: middle;
				visibility: visible;
				white-space: nowrap;
				text-decoration: none;
				margin: 10px;
			  }
			</style>
		{/if}
    </p>
    {if $nncheckoutToken}
		<script src="{$nncheckoutJs}" class="bz-checkout" data-token="{$nncheckoutToken}" data-auto-display="true"></script>
		<style type="text/css">
			iframe#bz-checkout-modal {
			position: fixed !important; }
		</style>
	{/if}
{/block}

    

