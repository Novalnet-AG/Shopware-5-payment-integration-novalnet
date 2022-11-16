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
    $.plugin('novalnetWalletPay', {
        defaults: {
			pageType: null,
			paymentName: null,
			shippingUrl: null,
			addBasketUrl: null,
			successUrl: null,
			walletPayParams: ''
        },

        init: function () {
			var me = this;
            me.applyDataAttributes();
            
            $.getScript('https://cdn.novalnet.de/js/v3/payment.js', function(event) {
				try {
					me._loadWalletPaymentForm();
				} catch (e) {
					// Handling the errors from the payment intent setup
					console.log(e.message);
				}
			});
        },
        
        /**
         * Registers all necessary event listener.
         */
        _loadWalletPaymentForm: function (event) {
			var me = this,
			    novalnetPaymentObj = NovalnetPayment().createPaymentObject(),
			    configuration = me.opts.walletPayParams;
			    
			if (me.opts.pageType == 'productDetail')
			{
				configuration.transaction.amount = $('#nnArticlePrice').val();
			}
			
			// Preparing the Wallet payment request
			var paymentIntent = {
				clientKey: configuration.clientKey,
				paymentIntent: {
					merchant: configuration.merchant,
					transaction: configuration.transaction,
					order: configuration.order,
					custom: configuration.custom,
					button: configuration.button,
					callbacks: {
						onProcessCompletion: function(response, bookingResult) {
							// Handle response here and setup the processedStatus
							if (response.result && response.result.status) {
								// Only on success, we proceed further with the booking
								if (response.result.status == 'SUCCESS') {
									if (me.opts.pageType == 'checkout') {
										if(me.opts.paymentName != null && me.opts.paymentName == 'novalnetapplepay')
										{
											document.getElementById('nn_applepay_token').value = response.transaction.token;
										} else {
											document.getElementById('nn_googlepay_token').value = response.transaction.token;
											document.getElementById('nn_googlepay_do_redirect').value = response.transaction.doRedirect;
										}
										document.getElementById("confirm--form").submit();
									} else {
										$.ajax({
											url: me.opts.successUrl,
											data: {
												serverResponse : JSON.stringify(response),
												paymentName: me.opts.paymentName
											},
											method: 'POST',
											success: function(data)
											{
												if (data.success != undefined && data.success == true)
												{
													bookingResult({status: "SUCCESS", statusText: ""});
													window.location.replace(data.url);
												} else {
													bookingResult({status: "FAILURE", statusText: response.result.status_text});
												}
											},
											error: function (data)
											{
												bookingResult({status: "FAILURE", statusText: response.result.status_text});
											}
										});
									}
								} else {
									bookingResult({status: "FAILURE", statusText: response.result.status_text});
									// Upon failure, displaying the error text 
									if (response.result.status_text) {
										alert(response.result.status_text);
									}
								}
							}
						},
						onShippingContactChange: function(shippingContact, updatedRequestData) {
							$.ajax({
								url: me.opts.shippingUrl,
								data: {
									shippingInfo : JSON.stringify(shippingContact),
									paymentName: me.opts.paymentName
								},
								method: 'POST',
								success: function(data)
								{
									
									if (!data.shipping.length) {
                                    updatedRequestData({methodsNotFound :"No Shipping Contact Available, please enter a valid contact"});
									} else {
										updatedRequestData({
											amount: data.totalAmount,
											lineItems: data.cartItems,
											methods: data.shipping,
											defaultIdentifier: data.shipping[0].identifier
										});
									}
								},
								error: function (data)
								{
									updatedRequestData({status: "FAILURE"});
								}
							});
						},
						onShippingMethodChange: function(shippingMethod, updatedRequestData) {
							$.ajax({
								url: me.opts.shippingUrl,
								data: {
									shippingMethod : JSON.stringify(shippingMethod),
									paymentName: me.opts.paymentName
								},
								method: 'POST',
								success: function(data)
								{
									
									updatedRequestData({
										amount: data.totalAmount,
										lineItems: data.cartItems
									});
								},
								error: function (data)
								{
									updatedRequestData({status: "FAILURE"});
								}
							});
						},
						onPaymentButtonClicked: function(clickResult) {
							var tos = $('input[id="sAGB"]:checked').val();
							if(tos == undefined && me.opts.pageType == 'checkout')
							{
								window.scroll(0, $('input[id="sAGB"]').offset().top - (window.innerHeight/2));
								$('label[for="sAGB"]').addClass('has--error');
								clickResult({status: "FAILURE"});
								return this;
							} else if (me.opts.pageType == 'productDetail') {
								$.ajax({
									url: me.opts.addBasketUrl,
									data: {
										ordernumber : $('input[name="sAdd"]').val(),
										quantity : $('select[name="sQuantity"] option:selected').val()
									},
									method: 'POST',
									success: function(data)
									{
										if (data.success == false)
										{
											window.location.replace(data.url);
											clickResult({status: "FAILURE"});
										} else {
											$.publish('plugin/swAddArticle/onAddArticle', [me, data]);
											clickResult({status: "SUCCESS"});
										}
									}
								});
							} else {
								clickResult({status: "SUCCESS"});
							}
						}
					}
				}
			}
			// Setting up the payment intent in your object 
			novalnetPaymentObj.setPaymentIntent(paymentIntent);

			novalnetPaymentObj.isPaymentMethodAvailable(function(displayPaymentButton) {
				if(me.$el === undefined) {return}

				if (displayPaymentButton) {
					// Initiating the Payment Request for the Wallet Payment
					novalnetPaymentObj.addPaymentButton("." + me.$el.attr('class'));
				}
			});
		},
		
        destroy: function () {
            this._destroy();
        }
    });
    
	if (typeof jQuery != 'undefined') {
		window.StateManager.addPlugin('*[data-wallet-payments="true"]', 'novalnetWalletPay');
	}
})(jQuery);
