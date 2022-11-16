{block name='frontend_novalnet_apple_pay_button'}
	<div class="nn-wallet-pay-{$pageType}"
			data-pageType="{$pageType}"
			data-wallet-payments="true"
			data-paymentName="{$nnPaymentName}"
			data-addBasketUrl="{url controller=NovalPayment action=addArticle forceSecure}"
			data-shippingUrl="{url controller=NovalPayment action=getAvailableShipping forceSecure}"
			data-successUrl="{url controller=NovalPayment action=createApplePayOrder forceSecure}"
			data-walletPayParams="{if $nnPaymentName == 'novalnetapplepay'}{$applePayParams|htmlentities}{else}{$googlePayParams|htmlentities}{/if}">
	</div>
{/block}

{block name='frontend_novalnet_apple_pay_button_script_cart'}
        <script>
            {* Shopware 5.3 may load the javaScript asynchronously, therefore
               we have to use the asyncReady function *}
            var asyncConf = ~~("{$theme.asyncJavascriptLoading}");
            if (typeof document.asyncReady === 'function' && asyncConf) {
                document.asyncReady(function() {
                    window.StateManager.addPlugin('*[data-wallet-payments="true"]', 'novalnetWalletPay');
                });
            } else {
                window.StateManager.addPlugin('*[data-wallet-payments="true"]', 'novalnetWalletPay');
            }
        </script>
{/block}
