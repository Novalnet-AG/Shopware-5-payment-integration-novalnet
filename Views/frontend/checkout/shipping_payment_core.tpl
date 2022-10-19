{extends file="parent:frontend/checkout/shipping_payment_core.tpl"}


{block name='frontend_account_payment_error_messages'}
	{$smarty.block.parent}
	<div class="alert is--error is--rounded {if !$smarty.get.sNNError}is--hidden{/if}" id="nn_error_block">
		<div class="alert--icon">
			<i class="icon--element icon--cross"></i>
		</div>
		<div class="alert--content" id="nn_error">{$smarty.get.sNNError}</div>
	</div>
	
	<div class="alert is--info is--rounded {if !$smarty.get.sNNInfo}is--hidden{/if}" id="nn_info_block">
		<div class="alert--icon">
			<div class="icon--element icon--info"></div>
		</div>
		<div class="alert--content">{$smarty.get.sNNInfo}</div>
	</div>
{/block}
