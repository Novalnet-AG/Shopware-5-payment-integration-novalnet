{namespace name='frontend/novalnet/payment'}
{include file='frontend/plugins/payment/novalnetlogo.tpl'}
<div class="space"></div>
<div class="debit" >
   <p class="none">
	  <input type="hidden" name="novalnetinvoiceShopVersion" id = "novalnetinvoiceShopVersion" value="{$shopVersion}"/>
      <input type="hidden" id="nn_invoice_paymentid"  name="nn_invoice_paymentid" value="{$payment_mean.id}"/>
      <input type="hidden" id="nn_invoice_date_error"  name="nn_invoice_date_error" value="{$payment_mean.id}"/>
   </p>
   {if $nnConfigArray.novalnetinvoice_guarantee_payment && $date_birth_field && $companyValue eq ''}
	   <input type="hidden" id="date_birth_field"  name="date_birth_field" value="{$date_birth_field}"/>
	   <label style="width:50%;">{s namespace='frontend/novalnet/payment' name='frontend_date_of_birth'}Ihr Geburtsdatum:{/s}<sup style='color:red;'>*</sup>:</label>
	   <div class="register--birthday field--select">
		  <div class="select-field">
			 <select class="invoiceDateOfBirthDay" id="invoiceDateOfBirthDay" name="invoiceDateOfBirthDay">
				<option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_date'}Tag{/s}</option>
				{for $day = 1 to 31}
					<option value="{$day}" {if ! empty ($birthdate_val.day) && ($day == $birthdate_val.day) }selected{/if}>{$day}</option>
				{/for}
			 </select>
		  </div>
		  <div class="select-field">
			 <select class="invoiceDateOfBirthMonth" name="invoiceDateOfBirthMonth" id="invoiceDateOfBirthMonth">
				<option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_month'}Monat{/s}</option>
				{for $month = 1 to 12}
                    <option value="{$month}" {if ! empty ($birthdate_val.month) && ($month == $birthdate_val.month) }selected{/if}>{$month}</option>
				{/for}
			 </select>
		  </div>
		  <div class="select-field">
			 <select class="invoiceDateOfBirthYear" name="invoiceDateOfBirthYear" id="invoiceDateOfBirthYear">
				<option value="" >{s namespace='frontend/novalnet/payment' name='novalnet_guarantee_payment_year'}Jahr{/s}</option>
				{for $year = date("Y") to date("Y")-120 step=-1}
					<option value="{$year}" {if ! empty ($birthdate_val.year) && ($year == $birthdate_val.year) }selected{/if}>{$year}</option>
				{/for}
			 </select>
		  </div>
	   </div>
   {/if}
</div>
