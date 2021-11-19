/**
* Novalnet payment plugin
* 
* NOTICE OF LICENSE
* 
* This source file is subject to Novalnet End User License Agreement
* 
* @author Novalnet AG
* @copyright Copyright (c) Novalnet
* @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz
* @link https://www.novalnet.de
*/

    var iframe = jQuery('#nnIframe')[0];
    var iframeContent = iframe.contentWindow ? iframe.contentWindow : iframe.contentDocument.defaultView;
    
    jQuery(document).ready(function () {
        var nn_cc_paymentid = jQuery("#nn_cc_paymentid").val();
  
        if (jQuery("#nn_cc_new_acc_details").length && jQuery("#nn_cc_new_acc_details").val() == 1) {
            document.getElementById('nn_cc_new_acc_details').value='1';
            jQuery('#novalnetcc_new_acc').css({"display":"none"});
        }
        var nn_checked = jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]:checked').val();
        
        if (nn_checked != undefined &&  nn_cc_paymentid == nn_checked && $('input[id="payment_mean'+nn_cc_paymentid+'"]').is(':checked') && jQuery("#nn_cc_new_acc_details").val() == 1) {
			loadIframe();
		}
                
        jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]').click(function (e) {
            loadIframe();
        });
        
        if (jQuery("#basketButton").val() == undefined) {
            var formID = jQuery('#nnIframe').closest('form').attr('id');
            
            jQuery('#'+formID).submit(function (e) {
                var nn_checked = jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]:checked').val();
                if (nn_checked != undefined &&  nn_cc_paymentid == nn_checked && jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]').is(':checked')) {
                    if ($('#novalnet_cc_hash').val() == '' && $('#novalnet_cc_uniqueid').val() == '' && jQuery("#nn_cc_new_acc_details").val() == 1) {
						e.preventDefault();
						e.stopImmediatePropagation();
						$('html, body').animate({ scrollTop: $("#payment_mean" + nn_cc_paymentid).offset().top}, 500);
						NovalnetUtility.getPanHash();
					} else {
						return true;
					}
				}
            });
            jQuery('input[type="submit"]').click(function (e) {
                var nn_checked = jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]:checked').val();
                if (nn_checked != undefined &&  nn_cc_paymentid == nn_checked && jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]').is(':checked')) {
                    if ($('#novalnet_cc_hash').val() == '' && $('#novalnet_cc_uniqueid').val() == '' && jQuery("#nn_cc_new_acc_details").val() == 1) {
                        e.preventDefault();
						e.stopImmediatePropagation();
						$('html, body').animate({ scrollTop: $("#payment_mean" + nn_cc_paymentid).offset().top}, 500);
						NovalnetUtility.getPanHash();
                    }	else {
						return true;
					}
                }
            });
        } else {
            jQuery('input[id="basketButton"][type="submit"]').click(function (e) {
                if ( nn_checked != undefined &&  nn_cc_paymentid == nn_checked && jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]').is(':checked')) {
                    if ($('#novalnet_cc_hash').val() == '' && $('#novalnet_cc_uniqueid').val() == '' && jQuery("#nn_cc_new_acc_details").val() == 1) {
						e.preventDefault();
						e.stopImmediatePropagation();
						$('html, body').animate({ scrollTop: $("#payment_mean" + nn_cc_paymentid).offset().top}, 500);
						NovalnetUtility.getPanHash();
					} else {
						return true;
					}
				}
            });
        }
        
        jQuery(".abo-commerce-payment--selection-form").submit(function(e){
            var nn_cc_paymentid = jQuery("#nn_cc_paymentid").val();
            var nn_checked = jQuery('input[id="payment_mean'+nn_cc_paymentid+'"]:checked').val();
			if($('#novalnet_cc_hash').val() == '' && nn_checked != undefined &&  nn_cc_paymentid == nn_checked ){
               e.preventDefault();
			}
         });

    });

    jQuery('#novalnetcc_new_acc').click(function () {
        var nn_cc_paymentid = jQuery("#nn_cc_paymentid").val();
        if (jQuery('#nn_cc_new_acc_details').val() == '0') {
            loadIframe();
            document.getElementById('novalnetcc_ref_details').style.display  ='none';
            document.getElementById('nnIframe').style.display  ='block';
            document.getElementById('nn_cc_confirm_save_check').style.display  ='block';
            jQuery('#nn_cc_new_acc_details').val('1');
            jQuery('#novalnetcc_new_acc').html('<b><u> ' + jQuery('#novalnetcc_given_account').val() + '</u></b>');
        } else {
            document.getElementById('novalnetcc_ref_details').style.display  ='block';
            document.getElementById('nnIframe').style.display  ='none';
            document.getElementById('nn_cc_confirm_save_check').style.display  ='none';
            jQuery('#nn_cc_confirm_save_check').find('input').prop('checked',false);
            jQuery('#nn_cc_new_acc_details').val('0');
            jQuery('#novalnetcc_new_acc').html('<b><u> ' + jQuery('#novalnetcc_new_account').val() + '</u></b>');
        }
    });

    function show_cc_error(nnErrorMessage)
    {
        if (jQuery("div[class='alert is--error is--rounded']").length) {
            jQuery("div[class='alert is--error is--rounded']").css('display','block');
            jQuery("div[class='alert--content']").html(nnErrorMessage);
            jQuery("div[class='alert is--info is--rounded']").css('display','none');
            jQuery(window).scrollTop(jQuery("div[class='alert is--error is--rounded']").offset().top);
        } else if (jQuery("div[class='error'][id='nn_error']").length) {
            jQuery("div[class='error'][id='nn_error']").html(nnErrorMessage).css('display','block');
            jQuery(window).scrollTop(jQuery("div[class='error'][id='nn_error']").offset().top);
        } else if (jQuery("div[class='error agb_confirm']").length ) {
            jQuery("div[class='error agb_confirm']").html(nnErrorMessage).css('display','block');
            jQuery(window).scrollTop(jQuery("div[class='error agb_confirm']").offset().top);
        } else if(jQuery("div[class='abo-payment-selection-error']").length ) {
            var span = jQuery('<div />').attr('class', 'alert--content').html(nnErrorMessage);
            jQuery('<div>', { class: 'alert is--error is--rounded'}).appendTo("div[class='abo-payment-selection-error']");
            jQuery('<div>', { class: 'alert--icon'}).append( jQuery('<i>', { class: 'icon--element icon--cross'})).appendTo("div[class='alert is--error is--rounded']");
            span.appendTo("div[class='alert is--error is--rounded']");
            jQuery(window).scrollTop(jQuery("div[class='abo-payment-selection-error']").offset().top);
         }
        return false;
    }
    
    function loadIframe() {
	
		var client_key = document.getElementById('client_key').value;
		var shop_lang	= document.getElementById('shop_lang').value;
	 
		// Set your Client key
        NovalnetUtility.setClientKey(client_key);
        
        var configurationObject = {
        
            callback: {
            
                // Called once the pan_hash (temp. token) created successfully.
                on_success: function (data) {
                    document.getElementById('novalnet_cc_hash').value = data ['hash'];
                    document.getElementById('novalnet_cc_uniqueid').value = data ['unique_id'];
                    document.getElementById('novalnet_cc_mask_no').value = data ['card_number'];
                    document.getElementById('novalnet_cc_mask_type').value = data ['card_type'];
                    document.getElementById('novalnet_cc_mask_holder').value = data ['card_holder'];
                    document.getElementById('novalnet_cc_mask_month').value = data ['card_exp_month'];
                    document.getElementById('novalnet_cc_mask_year').value = data ['card_exp_year'];
                    document.getElementById('novalnet_do_redirect').value = data ['do_redirect'];
                    var form = $("#nnIframe").closest("form");
					form.submit();
                    return true;
                },
                
                // Called in case of an invalid payment data or incomplete input. 
                on_error:  function (data) {
                    if ( undefined !== data['error_message'] ) {                        
                        show_cc_error(data['error_message']);
                        return false;
                    }
                },
                
                // Called in case the challenge window Overlay (for 3ds2.0) displays 
                on_show_overlay:  function (data) {
                    $('#nnIframe').addClass("novalnet-challenge-window-overlay");
                    $('#nn_cc_confirm_save_check').css('display','none');
                },
                
                // Called in case the Challenge window Overlay (for 3ds2.0) hided
                on_hide_overlay:  function (data) {
                    $('#nnIframe').removeClass("novalnet-challenge-window-overlay");
                    $('#nn_cc_confirm_save_check').css('display','block');
                }
            },
            
            iframe: {
				
				id: 'nnIframe',
				inline: 0,
				skip_auth: 1,
				style: {
                    
                    container: document.getElementById('CreditcardDefaultCss').value,
                    
                    input: document.getElementById('CreditcardDefaultInput').value,
                    
                    label: document.getElementById('CreditcardDefaultLabel').value
                }
            },
            
            customer: {
            
                first_name: document.getElementById('nn_user_fname').value,
                
                last_name: document.getElementById('nn_user_lname').value,
                
                email: document.getElementById('nn_user_email').value,
                
                billing: {
                
                    street: document.getElementById('nn_user_street').value,
                    
                    city: document.getElementById('nn_user_city').value,
                    
                    zip: document.getElementById('nn_user_zipcode').value,
                    
                    country_code: document.getElementById('nn_user_countrycode').value
                },
            },
            
            transaction: {
            
                amount: document.getElementById('nn_amount').value * 100,
                
                currency: document.getElementById('nn_currency').value,
                
                test_mode: document.getElementById('nn_cc_test_mode').value,
                
                enforce_3d: document.getElementById('nn_enforce_cc_3d').value,
            },
            custom: {
				lang : shop_lang
			}
        }
        
        NovalnetUtility.createCreditCardForm(configurationObject);
	}
