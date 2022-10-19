{extends file="parent:frontend/detail/buy.tpl"}


{block name="frontend_detail_buy_button_container"}

{assign var="invoiceMinimumAmount" value= {math equation="amount / 100" amount=$nnConfig['novalnetinvoiceinstalment_minimum_amount'] format="%.2f"}}
{assign var="sepaMinimumAmount" value= {math equation="amount / 100" amount=$nnConfig['novalnetsepainstalment_minimum_amount'] format="%.2f"}}

{if $sArticle.price_numeric > $invoiceMinimumAmount && !empty($nnConfig.novalnetinvoiceinstalment_product_page_info) && !empty($invoiceInstalmentCycle) && !empty($isInvoiceInstalmentActive)}
	<div class="novalnet_invoice_instalment_product_notification" style="border-style: solid;border-width: 1px;border-radius: 2px;padding: 16px;margin: 2px 0px 6px 0px;">
		<span class="is--block is--bold">
			{s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_invoice_desc'}Sie können die Ware in Raten per Rechnung bezahlen!{/s}
		</span>
		<a href="javascript:void(0);" id="invoice_link" name="invoice_link" class="invoice_link" style="border-bottom: solid 1px;">
		 {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_possible_rates'}Verfügbare Ratenzyklen{/s}
		</a>
		
		<div class="panel--body is--flat has--border invoice_details" style="display: none;">
			<span class="is--block is--bold" style="padding-left:8px;">
			 {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_loan_amount'}Gesamtbetrag{/s}: {$sArticle.price_numeric|currency}
			</span>
			<div class="panel--body is--flat">
				{foreach $invoiceInstalmentCycle as $key => $value}
				{assign var="instalmentInvoiceAmount" value= {math equation="amount / total_period" amount=$sArticle.price_numeric total_period=$value format="%.2f"}}
				 {if $instalmentInvoiceAmount gte 9.99 }
				 <div class="panel--body is--flat has--border">
				  {$value} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s} / {$instalmentInvoiceAmount|currency} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_month'}pro Monat{/s}
				</div>
				{/if}
				{/foreach}
			</div>
			<span class="is--block" style="padding-left:8px;font-size: 13px;font-family: open-sans;">
			 * {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_condition1'}Verfügbar in Deutschland, Österreich und der Schweiz{/s}<br/>
			 * {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_condition2'}Die Lieferadresse muss mit der Rechnungsadresse übereinstimmen{/s}
			</span>
		</div>
	</div>
{/if}

{if $sArticle.price_numeric > $sepaMinimumAmount && !empty($nnConfig.novalnetsepainstalment_product_page_info) && !empty($sepaInstalmentCycle) && !empty($isSepaInstalmentActive)}
	<div class="novalnet_sepa_instalment_product_notification" style="border-style: solid;border-width: 1px;border-radius: 2px;padding: 16px;margin: 2px 0px 6px 0px;">
		<span class="is--block is--bold">
			{s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_sepa_desc'}Sie können die Ware in Raten per Lastschrift SEPA bezahlen!{/s}
		</span>
		<a href="javascript:void(0);" id="sepa_link" name="sepa_link" class="sepa_link" style="border-bottom: solid 1px;">
		 {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_possible_rates'}Verfügbare Ratenzyklen{/s}
		</a>
		
		<div class="panel--body is--flat has--border sepa_details" style="display: none;">
			<span class="is--block is--bold" style="padding-left:8px;">
			 {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_loan_amount'}Gesamtbetrag{/s}: {$sArticle.price_numeric|currency}
			</span>
			<div class="panel--body is--flat">
				{foreach $sepaInstalmentCycle as $key => $value}
				{assign var="instalmentSepaAmount" value= {math equation="amount / total_period" amount=$sArticle.price_numeric total_period=$value format="%.2f"}}
				 {if $instalmentSepaAmount gte 9.99 }
				 <div class="panel--body is--flat has--border">
				  {$value} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s} / {$instalmentSepaAmount|currency} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_month'}pro Monat{/s}
				</div>
				{/if}
				{/foreach}
			</div>
			<span class="is--block" style="padding-left:8px;font-size: 13px;font-family: open-sans;">
			 * {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_condition1'}Verfügbar in Deutschland, Österreich und der Schweiz{/s}<br/>
			 * {s namespace='frontend/novalnet/payment' name='frontend_novalnetinstalment_condition2'}Die Lieferadresse muss mit der Rechnungsadresse übereinstimmen{/s}
			</span>
		</div>
	</div>
{/if}

{$smarty.block.parent}

{if (!isset($sArticle.active) || $sArticle.active)}
	{if $sArticle.isAvailable && $isApplePayValid}
		<div class="table--actions actions--bottom" style="margin-top: .5rem;margin-bottom: 3.5rem;">
			{assign var="amount" value= {$sArticle.price|replace:',':'.'} * 100 }
				<input type="hidden" id="nnArticlePrice" class="nnArticlePrice" value="{$amount}">
				{include file='frontend/noval_payment/button.tpl' pageType='productDetail'}
		</div>
	{/if}
{/if}

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
$(document).ready( function(){
		$("#invoice_link").click(function () {
            $(".invoice_details").toggle();
			});
        $("#sepa_link").click(function () {
            $(".sepa_details").toggle();
        });
});
</script>
{/block}
