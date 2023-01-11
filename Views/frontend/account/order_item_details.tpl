{extends file="parent:frontend/account/order_item_details.tpl"}

{block name="frontend_account_order_item_user_comment_content"}
    <div class="panel--body is--wide">
		<blockquote>{$offerPosition.customercomment|nl2br}</blockquote>
    </div>
    {if $nnInstalmentInfo ne '' }
		{foreach from=$nnInstalmentInfo key=key item=value}
			{foreach from=$value key=orderNo item=orderInfo}
				{if $orderNo == $offerPosition['ordernumber'] }
					<div class="panel--title">
						{s namespace='frontend/novalnet/payment' name='instalmentInformation'}Informationen zur Ratenzahlung{/s}
					</div>
					<div class="panel--body is--wide">
						<table style="width: 100%;text-align: center;">
							<thead style="font-weight: bold;">
								<tr>
									<td>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_serial_no'}S.Nr.{/s}</td>
									<td>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_date'}Datum{/s}</td>
									<td>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_transaction_id'}Novalnet-Transaktions-ID{/s}</td>
									<td>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_amount'}Betrag{/s}</td>
								<tr>
							</thead>
							<tbody>
								{foreach from=$orderInfo key=k item=info}
									{assign var="instalmentAmount" value= {math equation="amount / 100" amount=$info.amount format="%.2f"}}
									<tr>
										<td>{$k}</td>
										<td>{if $info.cycleDate ne ''} {$info.cycleDate|date:"DATE_MEDIUM"} {else} - {/if}</td>
										<td>{if $info.reference ne ''} {$info.reference} {else} - {/if}</td>
										<td>{if $instalmentAmount ne ''} {$instalmentAmount|currency} {else} - {/if}</td>
									<tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				{/if}
			{/foreach}
		{/foreach}
    {/if}
{/block}
