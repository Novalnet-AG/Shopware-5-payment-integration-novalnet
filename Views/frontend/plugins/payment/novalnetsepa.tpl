{namespace name='frontend/plugins/payment/novalnetsepa'}

{if $sAmountWithTax && $sUserData.additional.charge_vat }
	{assign var="totalAmount" value=$sAmountWithTax}
{else}
	{assign var="totalAmount" value=$sAmount}
{/if}

<div class="novalnet-payments"
     data-paymentName="{$payment_mean.name}"
	 data-forceGuarantee="{$nnConfig['novalnetsepaGuarantee_force_payment']}"
	 data-company="{$sUserData.billingaddress.company}"
	 data-allowB2b="{$nnConfig["{$payment_mean.name}_allow_b2b"]}"
	 data-emptyDobError="{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth_error'}Geben Sie bitte Ihr Geburtsdatum ein{/s}"
	 data-invalidDobError="{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth_invalid_error'}Sie müssen mindestens 18 Jahre alt sein{/s}"
	 data-deleteConfirmMsg="{s namespace='frontend/novalnet/payment' name='frontend_sepa_card_delete_message'}Sind Sie sicher, dass Sie diese Kontodaten entfernen möchten?{/s}"
	 data-invalidSepaIban="{s namespace='frontend/novalnet/payment' name='frontend_invalid_iban'}Ihre Kontodaten sind ungültig{/s}">
	
	{if !empty($cardBundle) && $nnConfig["{$payment_mean.name}_shopping_type"] eq '1' }
		{assign var="isAlreadyExists" value = ""}
		<div class="{$payment_mean.name}SavedTokens nnclass">
			<ul class="{$payment_mean.name}-SavedPaymentMethods novalnet-SavedPaymentMethods" data-count="{$cardBundle|count}" style="list-style: none;">
				{foreach from=$cardBundle key=k item=cardData}
					{if $isAlreadyExists neq $cardData.iban && $cardData.iban neq ''}
						{assign var="isAlreadyExists" value = $cardData.iban}
						<li class="{$payment_mean.name}-SavedPaymentMethods-token novalnet-SavedPaymentMethods-token">
							<label for="{$payment_mean.name}-payment-token-{$cardData.token}">
								<input id="{$payment_mean.name}-payment-token-{$cardData.token}" type="radio" name="{$payment_mean.name}FormData[paymentToken]" value="{$cardData.token}" class="{$payment_mean.name}-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput" {if $k == 0} checked{/if}/>&nbsp; &nbsp;
								IBAN
								<span>
									{s namespace='frontend/novalnet/payment' name='frontend_novalnetcc_ending'} mit Endziffern {/s} {$cardData.iban} &nbsp; &nbsp; <a href="javascript:void(0);"  class="remove_card_details" style="color: #5f7285;" data-value="{$cardData.token}" ><i class="icon--trash"></i></a>
								</span>
							</label>
						</li>
					{/if}
				{/foreach}
				{if $isAlreadyExists ne ''}
					<li class="{$payment_mean.name}-SavedPaymentMethods-new novalnet-SavedPaymentMethods-new">
						<label for="{$payment_mean.name}-payment-new"><input id="{$payment_mean.name}-payment-new" type="radio" name="{$payment_mean.name}FormData[paymentToken]" value="new" style="width:auto;" class="{$payment_mean.name}-SavedPaymentMethods-tokenInput novalnet-SavedPaymentMethods-tokenInput"/>&nbsp; &nbsp;
							{s namespace='frontend/novalnet/payment' name='frontend_novalnetsepa_new_payment'} Neue Kontodaten hinzufügen {/s}
						</label>
					</li>
				{/if}
			</ul>
		</div>
	{/if}
	<div id="{$payment_mean.name}PaymentForm" class="{$payment_mean.name}PaymentForm">
		<p class="nnclass">
			<label class="form-label" for="{$payment_mean.name}Iban">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_iban'}IBAN{/s}<sup style='color:red;'>*</sup></label><br/>
			<input type="text" name="{$payment_mean.name}FormData[Iban]" id="{$payment_mean.name}Iban" placeholder="{$sUserData.additional.country.countryiso}00 0000 0000 0000 0000 00" onkeypress="return NovalnetUtility.formatIban(event)" onchange="return NovalnetUtility.formatIban(event)" style="text-transform:uppercase;" autocomplete="off">
		</p>
		
		<p class="nnclass nn-bic-field is--hidden">
			<label class="form-label" for="{$payment_mean.name}Bic">{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_bic'}BIC{/s}<sup style='color:red;'>*</sup></label><br/>
			<input type="text" name="{$payment_mean.name}FormData[Bic]" id="{$payment_mean.name}Bic" placeholder="0000 00 00 000" onkeypress="return NovalnetUtility.formatBic(event)" onchange="return NovalnetUtility.formatBic(event)" style="text-transform:uppercase;" autocomplete="off">
		</p>
		
		{if $nnConfig["{$payment_mean.name}_shopping_type"] eq '1' && $sUserData.additional.user.accountmode eq '0' }
			<div class="saveCreditCardData">
				<input name="{$payment_mean.name}FormData[saveCard]" type="checkbox" id="saveCreditCardData" value="true" checked="checked" class="checkbox">
				<label for="saveSepaCardData">{s namespace='frontend/novalnet/payment' name='frontend_novalnetsepa_save_card'} Ich möchte meine Kontodaten für spätere Einkäufe speichern {/s}</label>
			</div>
		{/if}
	</div>
	
	{if ($payment_mean.name eq 'novalnetsepaGuarantee' && $canShowDobField.novalnetsepaGuarantee == 'true') ||  ($payment_mean.name eq 'novalnetsepainstalment' && $canShowDobField.novalnetsepainstalment == 'true')}
		<div class="{$payment_mean.name}DobField" id="{$payment_mean.name}DobField">
			<p class="nnclass">
				<label class="form-label" for="{$payment_mean.name}Dob">{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum{/s}<sup style='color:red;'>*</sup></label><br/>
				<input type="text" name="{$payment_mean.name}FormData[dob]" id="{$payment_mean.name}Dob" onkeydown="return NovalnetUtility.isNumericBirthdate( this, event )" placeholder="DD.MM.YYYY" value="{$sUserData.additional.user.birthday|date:'dd.MM.y'}" autocomplete="off">
			</p>
		</div>
	{/if}

	{if $payment_mean.name eq 'novalnetsepainstalment' }
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
						{foreach from=$nnSepaInstalmentCycles key=k item=v}
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
				{foreach from=$nnSepaInstalmentCycles key=k item=v}
					{assign var="cycleAmount" value=($totalAmount / $v)|round:2}
					<div class="{$payment_mean.name}Detail" data-duration="{$v}" {if $k != 0} hidden="hidden" {/if}>
						<table class="{$payment_mean.name}SummaryTable" id="{$payment_mean.name}SummaryTable">
							<thead>
								<tr>
									<th scope="col">{s namespace='frontend/novalnet/payment' name='frontend_instalment_cycle_heading'}Anzahl der Raten{/s}</th>
									<th scope="col">{s namespace='frontend/novalnet/payment' name='frontend_instalment_amount_heading'}Ratenbetrag{/s}</th>
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
	<p class="nnclass">
        <a class="novalnetSepaMandate" onclick="jQuery('#{$payment_mean.name}AboutMandate').toggle('slow')"><strong>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_mandate_confirm'}Ich erteile hiermit das SEPA-Lastschriftmandat (elektronische Übermittlung) und bestätige, dass die Bankverbindung korrekt ist.{/s}</strong></a>
        <p class="none">
			<div id="{$payment_mean.name}AboutMandate" class="novalnetSepaAboutMandate">
				<p>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_authorise'}Ich ermächtige den Zahlungsempfänger, Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von dem Zahlungsempfänger auf mein Konto gezogenen Lastschriften einzulösen.{/s}</p>

				<p><b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_mandate_creditor'}Gläubiger-Identifikationsnummer: DE53ZZZ00000004253{/s}</b></p>

				<p><b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_note'}Note:{/s}</b>{s namespace='frontend/novalnet/payment' name='frontend_novalnet_sepa_entitled'} Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.{/s}</p>
			</div>
		</p>
    </p>
	<input type="hidden" value="{$payment_mean.name}" class="novalnet-payment-name">
	<input type="hidden" value="{$payment_mean.id}" name="{$payment_mean.name}Id" id="{$payment_mean.name}Id">
	{if $payment_mean.name eq 'novalnetsepaGuarantee' }
		<input type="hidden" id="doForceSepaPayment"  name="{$payment_mean.name}FormData[doForceSepaPayment]" />
	{/if}
	{include file='frontend/plugins/payment/novalnetlogo.tpl'}
</div>
