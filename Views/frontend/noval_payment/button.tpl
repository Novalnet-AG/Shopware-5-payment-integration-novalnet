{block name='frontend_novalnet_apple_pay_button'}
	<input type="hidden" id="nn_applepay_token" name="novalnetapplepayFormData[walletToken]" value="">
	<div class="btn nn-apple-pay nn-apple-pay-cart {if $pageType == 'ajaxCart'} button--open-basket {elseif $pageType == 'productDetail'} nn-apple-pay-product-page {elseif $pageType == 'login'} nn-apple-pay-login-page {else} btn--checkout-proceed {/if} is--hidden"
			data-pageType="{$pageType}"
			data-addBasketUrl="{url controller=NovalPayment action=addArticle forceSecure}"
			data-shippingUrl="{url controller=NovalPayment action=getAvailableShipping forceSecure}"
			data-successUrl="{url controller=NovalPayment action=createApplePayOrder forceSecure}"
			data-applePayParams="{$applePayParams|htmlentities}">
	</div>
{/block}

{block name='frontend_novalnet_apple_pay_button_script_cart'}
    {if $pageType == 'ajaxCart'}
        <script>
            {* Shopware 5.3 may load the javaScript asynchronously, therefore
               we have to use the asyncReady function *}
            var asyncConf = ~~("{$theme.asyncJavascriptLoading}");
            if (typeof document.asyncReady === 'function' && asyncConf) {
                document.asyncReady(function() {
                    window.StateManager.addPlugin('.nn-apple-pay', 'novalnetApplePay');
                });
            } else {
                window.StateManager.addPlugin('.nn-apple-pay', 'novalnetApplePay');
            }
        </script>
    {/if}
{/block}
