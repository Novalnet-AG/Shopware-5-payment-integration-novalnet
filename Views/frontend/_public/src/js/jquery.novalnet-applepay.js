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
    $.plugin('novalnetApplePay', {
        defaults: {
			pageType: null,
			shippingUrl: null,
			addBasketUrl: null,
			successUrl: null,
			applePayParams: ''
        },

        init: function () {
			var me = this;
            me.applyDataAttributes();
            
            $.getScript('https://cdn.novalnet.de/js/v2/NovalnetUtility.js', function(event) {
				
				// Call apple pay function defined in script
				me.isApplePayAvailable = NovalnetUtility.isApplePayAllowed();
				
				if (me.isApplePayAvailable != undefined && me.isApplePayAvailable == true) {
					// triggers event when apple pay button is clicked
					$('.nn-apple-pay').each(function(element) {
						var currentElement = $(this);
						currentElement.removeClass("is--hidden");
						currentElement.css({"height": me.opts.applePayParams.settings.buttonHeight + 'px', "borderRadius": me.opts.applePayParams.settings.buttonRadius + 'px'});
						currentElement.addClass(me.opts.applePayParams.settings.buttonType);
						currentElement.addClass(me.opts.applePayParams.settings.buttonTheme);
						me._on(currentElement, 'click', $.proxy(me._loadApplePayForm, me));
					});
				} else {
					$('#nn_error_block').css("display", "block");
				}
			});
        },
        
        /**
         * Registers all necessary event listener.
         */
        _loadApplePayForm: function (event) {
			var me = this;
			
			if (me.opts.pageType == 'productDetail')
			{
				me.opts.applePayParams.transaction.amount = $('#nnArticlePrice').val();
				
				event.preventDefault();
				
				$.ajax({
					url: me.opts.addBasketUrl,
					data: {
						ordernumber : $('input[name="sAdd"]').val(),
						quantity : $('select[name="sQuantity"] option:selected').val()
					},
					method: 'POST',
					success: function(data)
					{
						var decodeData  = JSON.parse(data);
					}
				});
			}
			
			var tos = $('input[id="sAGB"]:checked').val();
			
			if(tos == undefined && me.opts.pageType == 'checkout')
			{
				window.scroll(0, $('input[id="sAGB"]').offset().top - (window.innerHeight/2));
				$('label[for="sAGB"]').addClass('has--error');
				return this;
			}
			
			if (me.opts.pageType != undefined && me.opts.pageType != 'checkout')
			{
				var requiredFields = {shipping : ['postalAddress', 'email', 'name', 'phone'], contact: ['postalAddress']};
			} else {
				var requiredFields = {};
			}
		
			NovalnetUtility.setClientKey(me.opts.applePayParams.clientKey);
			
			// Preparing the Apple request
			var requestData = {
				transaction: me.opts.applePayParams.transaction,
				merchant: {
				  country_code: 'DE'
				},
				custom: me.opts.applePayParams.custom,
				wallet: {
					shop_name: me.opts.applePayParams.wallet.shop_name,
					order_info: me.opts.applePayParams.wallet.order_info,
					required_fields: requiredFields,
					shipping_configuration:
					{
						type: 'shipping',
						calc_final_amount_from_shipping : '0'
					},
				},
				callback: {
					on_completion: function (responseData, processedStatus) 
					{ 
						// Handle response here and setup the processedStatus
						if (responseData.result && responseData.result.status) {
							// Only on success, we proceed further with the booking
							if (responseData.result.status == 'SUCCESS') {
								if (me.opts.pageType == 'checkout') {
									document.getElementById('nn_applepay_token').value = responseData.transaction.token;
									document.getElementById("confirm--form").submit();
								} else {
									var response = {response : responseData};
									$.ajax({
										url: me.opts.successUrl,
										data: {
											serverResponse : JSON.stringify(response)
										},
										method: 'POST',
										success: function(data)
										{
											processedStatus('SUCCESS');
											if (data.error != undefined && data.error)
											{
												alert(data.error);
											} else {
												window.location.replace(data.url);
											}
										},
										error: function (data)
										{
											processedStatus('ERROR');
										}
									});
								}
							} else {
								// Upon failure, displaying the error text
								if (responseData.result.status_text) {
									alert(responseData.result.status_text);
								}
							}
						}                                                                                                             
					},
					on_shippingcontact_change: function (shippingContact, updatedData) {
							var payload = {address : shippingContact};
							
							$.ajax({
								url: me.opts.shippingUrl,
								data: {
									shippingInfo : JSON.stringify(payload),
									shippingAddressChange : '1'
								},
								method: 'POST',
								success: function(data)
								{
									var updatedInfo = {
										amount: data.totalAmount,
										order_info: data.cartItems,
										shipping_methods: data.shipping
									};
									
									updatedData(updatedInfo);
								},
								error: function (data)
								{
									updatedData('ERROR'); 
								}
							});
					},
					on_shippingmethod_change: function (choosenShippingMethod, updatedData) {
						
						var payload = {shippingMethod : choosenShippingMethod};
							
						$.ajax({
							url: me.opts.shippingUrl,
							data: {
								shippingMethod : JSON.stringify(payload), 
								shippingMethodChange : '1'
							},
							method: 'POST',
							success: function(data)
							{
								var updatedInfo = {
									amount: data.totalAmount,
									order_info: data.cartItems
								};
								updatedData(updatedInfo);
							},
							error: function (data)
							{
								updatedData('ERROR'); 
							}
						});
					}
				}
			};
		
			// Setting up the payment request to initiate the Apple Payment sheet
			NovalnetUtility.processApplePay(requestData);
		},
		
        destroy: function () {
            this._destroy();
        }
    });
    
	if (typeof jQuery != 'undefined') {
		window.StateManager.addPlugin('.nn-apple-pay', 'novalnetApplePay');
	}
})(jQuery);
