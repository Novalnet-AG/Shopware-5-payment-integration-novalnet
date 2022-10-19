/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

(function($) {
    "use strict";
    $.plugin('novalnetPayments', {
        defaults: {
			forceGuarantee: null,
			company: null,
			allowB2b: null,
			paymentName: null,
			ccData: null,
			ccErrorText: '',
			emptyDobError: '',
			invalidDobError: '',
			invalidSepaIban: '',
			deleteConfirmMsg: '',
			instalmentPaymentTypes: ['novalnetsepainstalment', 'novalnetinvoiceinstalment'],
			invoicePaymentTypes: ['novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment'],
			sepaPaymentTypes: ['novalnetsepa', 'novalnetsepaGuarantee', 'novalnetsepainstalment'],
        },

        init: function () {
			var me = this;
			me.insertScript();
            me.applyDataAttributes();
            me.paymentForm();
            me.registerEvents();
        },
        
        /**
         * Hide/Show payment form based on the radio button checked.
         */
        paymentForm: function () {
			var me = this;
			var radioInputChecked = $('.' + me.opts.paymentName + '-SavedPaymentMethods-tokenInput:checked');
			
			if( radioInputChecked != undefined && radioInputChecked != null && radioInputChecked.length > 0 )
			{
				me.showComponents( radioInputChecked );
			}
			
		},
         
        /**
         * Registers all necessary event listener.
         */
        registerEvents: function () {
            var me = this;
            var radioInputs = $('.' + me.opts.paymentName + '-SavedPaymentMethods-tokenInput');
            var removeCardData = $('.remove_card_details');
            
            // triggers event when radio button is clicked
            $.each(radioInputs, function( index, radioInput ) {
				me._on($(radioInput), 'click', $.proxy(me.showComponents, me));
			});
			
			// triggers event when remove card data button is clicked
            $.each(removeCardData, function( index, paymentData ) {
				me._on($(paymentData), 'click', $.proxy(me.removePaymentCardData, me));
			});
			
            me._on($('#shippingPaymentForm'), 'submit', $.proxy(me.SubmitPaymentForm, me));
            
            me._on($('form[name="frmRegister"]'), 'submit', $.proxy(me.accountPaymentPage, me));
			
			if (me.opts.paymentName == 'novalnetcc')
			{
				if(me.opts.ccData != undefined)
				{
					$.getScript('https://cdn.novalnet.de/js/v2/NovalnetUtility.js', function(event) {
						// Call iframe function defined in script
						me.loadIframe();
					});
				}
			}
			
			if (me.opts.instalmentPaymentTypes.includes(me.opts.paymentName)) 
			{
				var radioInputs = $('.' + me.opts.paymentName + '-SavedPaymentMethods-tokenInput');
				me._on($('#' + me.opts.paymentName + 'Info'), 'click', $.proxy(me.hideInstalmentSummary, me));
				me._on($('#' + me.opts.paymentName + 'Duration'), 'change', $.proxy(me.ChangeInstalmentTable, me));
			}
			
			if (me.opts.invoicePaymentTypes.includes(me.opts.paymentName) || me.opts.sepaPaymentTypes.includes(me.opts.paymentName))
			{
				if ($('#' + me.opts.paymentName + 'Iban') != undefined && $('#' + me.opts.paymentName + 'Iban') != null)
				{
					['click','paste','keydown','keyup'].forEach((evt) => {
						me._on($('#' + me.opts.paymentName + 'Iban'), evt, $.proxy(me.validateIban, me));
					});
				}
				me._on($('#' + me.opts.paymentName + 'Iban'), 'click', $.proxy(me.removeError, me));
				me._on($('#' + me.opts.paymentName + 'Bic'), 'click', $.proxy(me.removeError, me));
				me._on($('#' + me.opts.paymentName + 'Dob'), 'click', $.proxy(me.removeError, me));
			}

            $.publish('plugin/novalnetPayments/onRegisterEvents', [me]);
        },
        
        loadIframe: function() {
			var me = this;
			var opts = me.opts.ccData;
			NovalnetUtility.setClientKey(opts.clientKey);
			var configurationObject = {
				callback: {
					on_success: function (data) {
						$('#novalnetcc_panhash').val(data ['hash']);
						$('#novalnetcc_uniqueid').val(data ['unique_id']);
						$('#novalnetcc_do_redirect').val(data ['do_redirect']);
						if(data ['card_exp_month'] != undefined && data ['card_exp_year'] != undefined) {
							$('#novalnetcc_expiry_date').val(data ['card_exp_month'] + '/' + data ['card_exp_year']);
						}
						$('#novalnetcc_card_holder').val(data ['card_holder']);
						$('#novalnetcc_card_no').val(data ['card_number']);
						$('#novalnetcc_card_type').val(data ['card_type']);
						$('#nnIframe').closest('form').submit();
						return true;
					},
					on_error:  function (data) {
						if ( undefined != data['error_message'] ) {
							alert(data['error_message']);
							return false;
						}
					},
					on_show_overlay:  function (data) {
						document.getElementById('nnIframe').classList.add("novalnet-challenge-window-overlay");
					},
					on_hide_overlay:  function (data) {
						document.getElementById("nnIframe").classList.remove("novalnet-challenge-window-overlay");
					}
				},
				iframe: opts.iframe,
				customer: opts.customer,
				transaction: opts.transaction,
				custom: opts.custom
			}
			NovalnetUtility.createCreditCardForm(configurationObject);
		},
        
        removeError: function(element) {
			if($(element.target) != undefined && $(element.target).hasClass('has--error'))
			{
				$(element.target).removeClass('has--error');
			}
		},
		
		showComponents: function (field) {
			var me = this;
			var paymentName = me.opts.paymentName;
			field = (field.target != undefined && field.target != null) ? $(field.target) : field;
			if ( field.val() == 'new'  && field.is(":checked")) {
				$('#' + paymentName + 'PaymentForm').removeClass("is--hidden");
			} else {
				$('#' + paymentName + 'PaymentForm').addClass("is--hidden");
			}
		},
		
		removePaymentCardData: function(element) {
			var me = this;
			var selectedPaymentId = $('input[name=payment]:checked', '#shippingPaymentForm').val();
			var nnPaymentId = $('#'+ me.opts.paymentName + 'Id').val();
            
            if(selectedPaymentId == undefined)
            {
				var selectedPaymentId = $('input[name="register[payment]"]:checked', 'form[name="frmRegister"]').val();
			}
            
			/** return if no payment method is selected */
            if(selectedPaymentId == undefined)
            {
				return false;
			}
			
			if (me.opts.paymentName == 'novalnetcc' && nnPaymentId != undefined && nnPaymentId == selectedPaymentId)
			{
				var radioInputChecked = $('.' + me.opts.paymentName + '-SavedPaymentMethods-tokenInput:checked');
				if( radioInputChecked != undefined && radioInputChecked != '' && radioInputChecked.val() != 'new')
				{
					me.tokenDeleteCall(radioInputChecked.val(), me.opts.deleteConfirmMsg);
				}
			} else if (me.opts.sepaPaymentTypes.includes(me.opts.paymentName) && nnPaymentId != undefined && nnPaymentId == selectedPaymentId) {
				var radioInputChecked = $('.' + me.opts.paymentName + '-SavedPaymentMethods-tokenInput:checked');
				if( radioInputChecked != undefined && radioInputChecked != '' && radioInputChecked.val() != 'new')
				{
					me.tokenDeleteCall(radioInputChecked.val(), me.opts.deleteConfirmMsg);
				}
			}
		},
		
		tokenDeleteCall: function(token, message) {
			if($('#nnDeleteUrl') != undefined && $('#nnCustomerId'))
			{
				message = message ? message : 'You want to delete ?';
				if(window.confirm(message))
				{
					$.ajax({
						type: 'POST',
						url: $('#nnDeleteUrl').val(),
						data: { token: token, customer_no: $('#nnCustomerId').val()},
						cache: false,
						success: function(result) {
							setTimeout(() => window.location.reload(), 1000);
						}
					});
				}
			}
		},

		hideInstalmentSummary: function() {
			var me = this;
			if($('#' + me.opts.paymentName + 'Summary').hasClass('is--hidden'))
			{
				$('#' + me.opts.paymentName + 'Summary').removeClass('is--hidden');
			} else {
				$('#' + me.opts.paymentName + 'Summary').addClass('is--hidden');
			}
		},
		
		ChangeInstalmentTable: function(element) {
			var me = this;
			$('.' + me.opts.paymentName + 'Detail').each(function() {
				var currentElement = $(this);
				if (currentElement.attr('data-duration') == $(element.target).val())
				{
					currentElement.removeAttr('hidden');
				} else {
					currentElement.attr('hidden', true);
				}
			});
		},
        
        insertScript: function() {
            // insert the novalnet utility script in head.
            const url = 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js';
			const script = document.createElement('script');
			script.type = 'text/javascript';
			script.src = url;
			script.addEventListener('load', this.callback.bind(this), false);
			document.head.appendChild(script);
        },
        
        callback: function () {
			var me = this;
			return me;
		},
		
		accountPaymentPage: function(event) {
			var me = this;
			var nnPaymentName = me.opts.paymentName;
			var nnPaymentId = $('#'+ nnPaymentName + 'Id').val();
			var selectedPaymentId = $('input[name="register[payment]"]:checked', 'form[name="frmRegister"]').val();
			var radioInputChecked = $('.' + nnPaymentName + '-SavedPaymentMethods-tokenInput:checked');
			
			if (nnPaymentName == 'novalnetcc' && $('#novalnetcc_panhash').val() == '' && $('#novalnetcc_uniqueid').val() == '' && (radioInputChecked == undefined || radioInputChecked == null || radioInputChecked.length == 0 || radioInputChecked.val() == 'new'))
			{
				if((nnPaymentId != undefined && nnPaymentId == selectedPaymentId)) {
					event.preventDefault();
					event.stopImmediatePropagation();
					$('html, body').animate({ scrollTop: $("#payment_mean" + nnPaymentId).offset().top}, 500);
					NovalnetUtility.getPanHash();
				}
			}
		},
		
		/**
         * Handles submit event of next button on address/wallet widget page.
         *
         * @param event
         *
         * @return {boolean}
         */
        SubmitPaymentForm: function(event) {
            var me = this,
                $target = $(event.target),
                elements = $('.novalnet-payment-name'),
                nnPaymentName = me.opts.paymentName,
                nnPaymentId = $('#'+ nnPaymentName + 'Id').val(),
                dob = $('#'+ nnPaymentName + 'Dob'),
                selectedPaymentId = $('input[name=payment]:checked', '#shippingPaymentForm').val(),
                radioInputChecked = $('.' + nnPaymentName + '-SavedPaymentMethods-tokenInput:checked');
            
            /** return if no payment method is selected */
            if(selectedPaymentId == undefined)
            {
				return false;
			}
			
			if (nnPaymentName == 'novalnetcc' && $('#novalnetcc_panhash').val() == '' && $('#novalnetcc_uniqueid').val() == '' && (radioInputChecked == undefined || radioInputChecked == null || radioInputChecked.length == 0 || radioInputChecked.val() == 'new'))
			{
				if((nnPaymentId != undefined && nnPaymentId == selectedPaymentId)) {
					event.preventDefault();
					event.stopImmediatePropagation();
					$('html, body').animate({ scrollTop: $("#payment_mean" + nnPaymentId).offset().top}, 500);
					NovalnetUtility.getPanHash();
				}
			} else if (me.opts.invoicePaymentTypes.includes(nnPaymentName)) 
			{
				if ((nnPaymentId != undefined && nnPaymentId == selectedPaymentId) && (me.opts.company == '' || !NovalnetUtility.isValidCompanyName(me.opts.company) || me.opts.allowB2b == '' || me.opts.allowB2b == undefined)) 
				{
					if (dob == undefined || dob.val() == '') {
						if (nnPaymentName == 'novalnetinvoiceGuarantee' && me.opts.forceGuarantee == 1) {
							$('#doForceInvoicePayment').val(1);
							return true;
						} else {
							me.preventForm(dob, event, me.opts.emptyDobError);
						}
					} else if (dob != undefined && dob.val() != '') {
						var age = me.validateAge(dob.val());
						if ((age < 18 || isNaN(age)) && nnPaymentName == 'novalnetinvoiceGuarantee' && me.opts.forceGuarantee == 1) {
							$('#doForceInvoicePayment').val(1);
							return true;
						} else if (age < 18 || isNaN(age)) {
							me.preventForm(dob, event, me.opts.invalidDobError);
						}
					}
				}
			} else if (me.opts.sepaPaymentTypes.includes(nnPaymentName))
			{
				if ((nnPaymentId != undefined && nnPaymentId == selectedPaymentId))
				{
					var iban = $('#'+ nnPaymentName + 'Iban');
					var bic = $('#'+ nnPaymentName + 'Bic');
					
					if (iban == undefined || iban.val() == '' && (radioInputChecked == undefined || radioInputChecked == null || radioInputChecked.length == 0 || radioInputChecked.val() == 'new')) {
						me.preventForm(iban, event, me.opts.invalidSepaIban);
					} else if ((bic == undefined || bic.val() == '') && $('.nn-bic-field').hasClass('is--hidden') == false && (radioInputChecked == undefined || radioInputChecked == null || radioInputChecked.length == 0 || radioInputChecked.val() == 'new')) {
						me.preventForm(bic, event, me.opts.invalidSepaIban);
					} else if (nnPaymentName != 'novalnetsepa' && (me.opts.company == '' || !NovalnetUtility.isValidCompanyName(me.opts.company) || me.opts.allowB2b == '' || me.opts.allowB2b == undefined))
					{
						if (dob == undefined || dob.val() == '') {
							if (nnPaymentName == 'novalnetsepaGuarantee' && me.opts.forceGuarantee == 1) {
								$('#doForceSepaPayment').val(1);
								return true;
							} else {
								me.preventForm(dob, event, me.opts.emptyDobError);
							}
						} else if (dob != undefined && dob.val() != '') {
							var age = me.validateAge(dob.val());
							if ((age < 18 || isNaN(age)) && nnPaymentName == 'novalnetsepaGuarantee' && me.opts.forceGuarantee == 1) {
								$('#doForceSepaPayment').val(1);
								return true;
							} else if (age < 18 || isNaN(age)) {
								me.preventForm(dob, event, me.opts.invalidDobError);
							}
						}
					}
				} 
			}
        },
        
        validateIban: function (element) {
			var result = $(element.target).val();
			if (result != undefined && result != '')
			{
				result = result.toUpperCase();
                if (result.match(/(?:CH|MC|SM|GB)/)) {
					$('.nn-bic-field').removeClass("is--hidden");
                } else {
					$('.nn-bic-field').addClass("is--hidden");
				}
			}
		},
        
        validateAge: function (dob) {
			if(dob == undefined || dob == '')
			{
				return NaN;
			}
			var today = new Date();
			var birthDate = dob.split(".");
			var age = today.getFullYear() - birthDate[2];
			var m = today.getMonth() - birthDate[1];
			m = m + 1
			if (m < 0 || (m == '0' && today.getDate() < birthDate[0])) {
				age--;
			}
			return age;
		},
        
        preventForm: function (field, event, message) {
			var targetAlert = $("#nn_error_block");
			targetAlert.find('.alert--content').html('');
			targetAlert.find('.alert--content').html(message);
			field.addClass('has--error');
			targetAlert.removeClass('is--hidden');
			$(window).scrollTop( field );
			event.preventDefault();
			event.stopImmediatePropagation();
			return false;
		},

        destroy: function () {
            this._destroy();
        }
    });
    
	if (typeof jQuery != 'undefined') {
		window.StateManager.addPlugin('.novalnet-payments', 'novalnetPayments');
		
		$.subscribe('plugin/swShippingPayment/onInputChanged', function() {
			window.StateManager.addPlugin('.novalnet-payments', 'novalnetPayments');
		});
	}
})(jQuery);
