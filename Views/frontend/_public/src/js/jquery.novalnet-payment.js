/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

(function ($) {
    "use strict";
    $.plugin('novalnetPayments', {
        defaults: {

        },
        init: function () {
            let me = this;
            let shopRadio = "";
            $('#payment_mean'+document.getElementById('novalnetpayId').value).addClass('is--hidden');
            
            if ($('.sizing--content') == 'undefined' || $('.sizing--content').length) {
                $('.sizing--content').css('max-height', '70vh');
            }
            const url = 'https://cdn.novalnet.de/js/pv13/checkout.js?'+ new Date().getTime();
            
            if ($("#novalnetCheckoutJsScript") && $("#novalnetCheckoutJsScript").length) {
                me.loadPaymentForm();
            } else {
                me.includeNovalnetCheckoutJs(url).then((response) => {
                    me.loadPaymentForm();
                }).catch((error) => {
                    $.getScript(url, me.loadPaymentForm());
                });
            }
        },
        
        includeNovalnetCheckoutJs : function (scriptUrl) {
            const script = document.createElement('script');
            script.src = scriptUrl;
            script.id = "novalnetCheckoutJsScript";
            document.body.appendChild(script);
          
            return new Promise((resolve, reject) => {
                script.onload = function () {
                    resolve();
                }
                script.onerror = function () {
                    reject();
                }
            });
        },

        loadPaymentForm : function () {
            let self = this;
            var me                  = this,
                paymentFormInstance = new NovalnetPaymentForm(),
                walletPaymentParams = $('#wallet_payment_params').val(),
                orderInfoData       = (walletPaymentParams == '' || walletPaymentParams == null || walletPaymentParams.length <= 0 ) ? {} : JSON.parse(walletPaymentParams),
                radioSelector       = ( $('input[name="payment"]').length ) ? $('input[name="payment"]') : null ,
                selectedPaymentId   = ( $('input[name="payment"]:checked').length ) ? $('input[name="payment"]:checked') : null,
                novalnetId          = $('#novalnetpayId'),
                
                checkPaymentType    = null,
                uncheckPayments     = true;
                
            if (selectedPaymentId != null && selectedPaymentId != undefined && ( $('input[name="payment"]:checked').length ) ) {
                var shopRadio       = selectedPaymentId.val();
            }
                
            if ( selectedPaymentId != undefined && novalnetId != undefined && novalnetId.val() == shopRadio) {
                uncheckPayments = false;
                checkPaymentType = checkPaymentType ? checkPaymentType : self.getCookie('novalnet_payment_type');
            }

            let paymentFormRequestObj = {
                iframe : '#novalnetPayForm',
                initForm: {
                    orderInformation : {
                        lineItems : orderInfoData,
                    },
                    uncheckPayments: uncheckPayments,
                    setWalletPending: true,
                    showButton: false
                }
            };

            if (checkPaymentType) {
                paymentFormRequestObj.checkPayment = checkPaymentType;
            }

            paymentFormInstance.initiate(paymentFormRequestObj);

            paymentFormInstance.validationResponse((data) => {
                 paymentFormInstance.initiate(paymentFormRequestObj);
                if ($('.abo-payment-selection-button').length) {
                    const isDisabled = $('.abo-payment-selection-button'). prop('disabled');
                    if (isDisabled) {
                        $('.abo-payment-selection-button').prop('disabled', false);
                    }
                }
            });
                        
            paymentFormInstance.selectedPayment((data) => {
                self.eraseCookie('novalnet_payment_type');
                self.setCookie('novalnet_payment_type',data.payment_details.type,'1');
                
                if ($('#shippingPaymentForm').length) {
                    if ( ['GOOGLEPAY', 'APPLEPAY'].includes(data.payment_details.type)) {
                        $(':input[type=submit]').prop('disabled', true);
                    } else {
                        $(':input[type=submit]').prop('disabled', false);
                    }
                }
                
                if ($('#shippingPaymentForm').length && $('input[name="payment"]:checked').val() != $('#novalnetpayId').val()) {
                    $('#payment_mean' + novalnetId.val()).prop('checked', true);
                    $('*[data-ajax-shipping-payment="true"]').data('plugin_swShippingPayment').onInputChanged();
                }
                
                if ($('.abo-commerce-payment--selection-form').length && $('input[name="payment"]:checked').val() != $('#novalnetpayId').val()) {
                    $('#payment_mean' + novalnetId.val()).trigger('click');
                }


            });

            radioSelector.click(function () {
                shopRadio = $(this).val();
                if (novalnetId != undefined && novalnetId.val() != $(this).val()) {
                    $('#payment_mean' + novalnetId.val()).removeAttr('checked');
                    paymentFormInstance.uncheckPayment();
                }
            });

            paymentFormInstance.walletResponse({
                onProcessCompletion: (response) => {
                    if (response) {
                        const responseData = JSON.stringify(response);

                        if (response.result.status == "SUCCESS") {
                            if ($('#novalnet_payment_data').val() == '' || $('#novalnet_payment_data').val() == null || $('#novalnet_payment_data').val() == undefined) {
                                $('#novalnet_payment_data').val(responseData);
                                
                                $.ajax({
                                    url: $('#wallet_successUrl').val(),
                                    data: {
                                        serverResponse : JSON.stringify(response),
                                        novalPaymentId : $('#novalnetpayId').val()
                                    },
                                    method: 'POST',
                                    success: function (data) {
                                        if (data.success != undefined && data.success == true) {
                                            window.location.replace(data.url);
                                            return {status: 'SUCCESS', statusText: response.result.message};
                                        } else {
                                            return {status: 'FAILURE', statusText: 'failure'};
                                        }
                                    },
                                    error: function (data) {
                                        $('#novalnet_payment_data').val('');
                                        self.showErrorMessage(response.result.message);
                                        return {status: 'FAILURE', statusText: response.result.message};
                                    }
                                });
                            }
                            return {status: 'SUCCESS', statusText: response.result.message};
                        } else {
                            $('#novalnet_payment_data').val('');
                            self.showErrorMessage(response.result.message);
                            return {status: 'FAILURE', statusText: response.result.message};
                        }
                    }
                }
            });

            $('#novalnetPayForm').closest('form').submit(function (event) {
                if (novalnetId.val() == shopRadio ) {
                    let paymentResponse = $('#novalnet_payment_data').val();
                    if (paymentResponse == '' || paymentResponse == null || paymentResponse == undefined) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        paymentFormInstance.getPayment((data) => {
                            if (data) {
                                var responseData = JSON.stringify(data);
                                if (data.result.status == "SUCCESS") {
                                    if (data.payment_details.key != undefined && data.payment_details.key != null) {
                                        $('#novalnet_payment_data').val(responseData);
                                        $('#novalnetPayForm').closest('form').submit();
                                    } else {
                                        $('#novalnet_payment_data').val('');
                                        self.showErrorMessage('Please select any payment method');
                                    }
                                    return {status: 'SUCCESS', statusText: data.result.message};
                                } else {
                                    $('#novalnet_payment_data').val('');
                                    self.showErrorMessage(data.result.message);
                                    return {status: 'FAILURE', statusText: data.result.message};
                                }
                            }
                        })
                    }
                }
            });
        },

        showErrorMessage : (message) => {
            if ($("#nn_error_block").length) {
                let targetAlert = $("#nn_error_block");
                targetAlert.find('.alert--content').html('');
                targetAlert.find('.alert--content').html(message);
                targetAlert.removeClass('is--hidden');
                $(window).scrollTop($("#nn_error_block"));

                return false;
            } else {
                alert(message);
                if ($('.abo-payment-selection-button').length) {
                    const isDisabled = $('.abo-payment-selection-button'). prop('disabled');
                    if (isDisabled) {
                        $('.abo-payment-selection-button').prop('disabled', false);
                    }
                }
            }
        },

        setCookie : (key, value, expiry) => {
            let expires = new Date();
            expires.setTime(expires.getTime() + (expiry * 24 * 60 * 60 * 1000));
            document.cookie = key + '=' + value + ';expires=' + expires.toUTCString();
        },

        getCookie : (key) => {
            var keyValue = document.cookie.match('(^|;) ?' + key + '=([^;]*)(;|$)');
            return (keyValue != null && keyValue.length) ? keyValue[2] : null;
        },

        eraseCookie : function (key) {
            var keyValue = this.getCookie(key);
            this.setCookie(key, keyValue, '-1');
        }
    });

    if (typeof jQuery != 'undefined') {
        window.StateManager.addPlugin('.novalnet-payments', 'novalnetPayments');
    }

})(jQuery);
