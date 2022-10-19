{namespace name='frontend/plugins/payment/novalnetinvoice'}

{if $sUserData.billingaddress.company ne '' && $nnConfig["{$payment_mean.name}_allow_b2b"] eq '1' }
	{assign var="display" value = "none"}
{else}
	{assign var="display" value = "block"}
{/if}

{if $sAmountWithTax && $sUserData.additional.charge_vat }
	{assign var="totalAmount" value=$sAmountWithTax}
{else}
	{assign var="totalAmount" value=$sAmount}
{/if}

{if $payment_mean.name eq 'novalnetinvoiceGuarantee' || $payment_mean.name eq 'novalnetinvoiceinstalment' }
	<div class="novalnet-payments"
	     data-paymentName="{$payment_mean.name}"
	     data-forceGuarantee="{$nnConfig['novalnetinvoiceGuarantee_force_payment']}"
		 data-company="{$sUserData.billingaddress.company}"
		 data-allowB2b="{$nnConfig["{$payment_mean.name}_allow_b2b"]}"
		 data-emptyDobError="{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth_error'}Geben Sie bitte Ihr Geburtsdatum ein{/s}"
		 data-invalidDobError="{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth_invalid_error'}Sie müssen mindestens 18 Jahre alt sein{/s}">
		<div id="{$payment_mean.name}Form" class="{$payment_mean.name}Form" style="display:{$display}">
			<label class="form-label" for="{$payment_mean.name}Dob">{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}<sup style='color:red;'>*</sup></label><br/>
			<input type="text" name="{$payment_mean.name}FormData[dob]" id="{$payment_mean.name}Dob" onkeydown="return NovalnetUtility.isNumericBirthdate( this, event )" placeholder="DD.MM.YYYY" value="{$sUserData.additional.user.birthday|date:'dd.MM.y'}" autocomplete="off">
		</div>
		{if $payment_mean.name eq 'novalnetinvoiceinstalment' }
			<div class="panel">
				<div class="panel--header primary">
					{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_banner'}Wählen Sie Ihren Ratenplan (Netto-Kreditbetrag: {/s}{$totalAmount|currency}) 
				</div>
			</div>
			<div class="panel--body">
				<p class="none">
					{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_banner_info'}Wählen Sie die Finanzierungsoption, die Ihren Bedürfnissen am besten entspricht. Die Raten werden Ihnen entsprechend dem gewählten Ratenplan berechnet.{/s}
				</p>
				<div class="panel--filter-select field--select select-field">
					<p class="none">
						<select name="{$payment_mean.name}FormData[duration]" id="{$payment_mean.name}Duration" size="1">
							{foreach from=$nnInvoiceInstalmentCycles key=k item=v}
								{assign var="instalmentAmount" value= {math equation="amount / period" amount=$totalAmount period=$v format="%.2f"}}
								{if $instalmentAmount gte 9.99 }
									<option value="{$v}">{$v} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_cycle'}Raten{/s} / {$instalmentAmount|currency} {s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_month'}pro Monat{/s}</option>
								{/if}
							{/foreach}
						</select>
					</p>
				</div>
				
				<div class="novalnetInstalmentInfo" id="{$payment_mean.name}Info">
					<a href="javascript:void(0)">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_instalment_sumary'}Zusammenfassung der Ratenzahlung{/s}</a>
                </div>  
				<div id="{$payment_mean.name}Summary" class="{$payment_mean.name}InfoSummary">
					{foreach from=$nnInvoiceInstalmentCycles key=k item=v}
						{assign var="cycleAmount" value=($totalAmount / $v)|round:2}
						<div class="{$payment_mean.name}Detail" data-duration="{$v}" {if $k != 0} hidden="hidden" {/if}>
							<table class="{$payment_mean.name}SummaryTable" id="{$payment_mean.name}SummaryTable">
								<thead>
									<tr>
										<th scope="col">{s namespace='frontend/novalnet/payment' name='frontend_instalment_cycle_heading'}Anzahl der Raten{/s}</th>
										<th scope="col">{s namespace='frontend/novalnet/payment' name='frontend_instalment_amount_heading'}Anzahl der Raten{/s}</th>
									</tr>
								</thead>
								<tbody>
									{for $cycle=1 to $v}
										<tr>
											{if $cycle != $v}
												<td>{$cycle}</td>
												<td>{$cycleAmount|currency}</td>
											{else}
												{assign var="cycleAmount" value=($totalAmount - ($cycleAmount * ($cycle-1)))}
												<td>{$cycle}</td>
												<td>{$cycleAmount|currency}</td>
											{/if}
										</tr>
									{/for}
								</tbody>
							</table>
						</div>
					{/foreach}
				</div>
				
			</div>
		{/if}
		<input type="hidden" value="{$payment_mean.name}" class="novalnet-payment-name">
		<input type="hidden" value="{$payment_mean.id}" name="{$payment_mean.name}Id" id="{$payment_mean.name}Id">
		{if $payment_mean.name eq 'novalnetinvoiceGuarantee' }
			<input type="hidden" id="doForceInvoicePayment"  name="{$payment_mean.name}FormData[doForceInvoicePayment]" />
		{/if}
		{include file='frontend/plugins/payment/novalnetlogo.tpl'}
	</div>
{else}
	{include file='frontend/plugins/payment/novalnetlogo.tpl'}
{/if}
