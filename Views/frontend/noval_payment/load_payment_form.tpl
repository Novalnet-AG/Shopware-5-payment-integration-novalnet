{block name='frontend_noval_payment_load_payment_form'}
	<iframe id="novalnetPayForm" class= "novalnet-payments" src="{$nnPaymentFromUrl}" width="100%" frameborder="0" scrolling="no" allow="payment" ></iframe>
	<input type="hidden" id="novalnet_payment_data" name="novalnet_payment_data" value=""/>
	<input type="hidden" value="{$payment_mean.id}" name="{$payment_mean.name}Id" id="{$payment_mean.name}Id">
	<input type="hidden" value="{$form_data.payment}" name="formDataId" id="formDataId">
	<input type="hidden" value="{$walletPaymentParams|htmlentities}" name="wallet_payment_params" id="wallet_payment_params">
	<input type="hidden" value="{url controller=NovalPayment action=createWalletPaymentOrder forceSecure}" name="wallet_successUrl" id="wallet_successUrl">
{/block}

{block name='frontend_novalnet_apple_pay_button_script_cart'}
        <script>
            {* Shopware 5.3 may load the javaScript asynchronously, therefore
               we have to use the asyncReady function *}
            var asyncConf = ~~("{$theme.asyncJavascriptLoading}");
            if (typeof document.asyncReady === 'function' && asyncConf ) {
                document.asyncReady(function() {
                    window.StateManager.addPlugin('.novalnet-payments', 'novalnetPayments');
                });
            } else if (window.StateManager != undefined ) {
                window.StateManager.addPlugin('.novalnet-payments', 'novalnetPayments');
            }
        </script>
{/block}
