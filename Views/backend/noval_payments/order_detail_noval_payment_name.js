/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */


/**
 * Overrides the backend order detail overview to display novalnet paymnets
 */
//{block name="backend/order/view/detail/overview"}
    //{$smarty.block.parent}

Ext.define('Shopware.apps.NovalPayment.Order.view.detail.Overview.NovalPaymentName', {

    override: 'Shopware.apps.Order.view.detail.Overview',
    
    novalPaymentMethodNames: '{$novalPaymentMethodNames}',
    
    /**
     * Adds the order's transaction ID to the render data of the payment container.
     *
     * @return The newly created payment container.
     */
    createPaymentContainer: function () {
        var container = this.callParent(arguments);
        var me = this;
        
        if (container) {
            // Append the transaction ID to the render data
            var template = container.items.first();
            template.renderData['novalPaymentName'] = (me.getNovalnetPaymentName() == null) ? template.renderData['description'] : me.getNovalnetPaymentName();
        }

        return container;
    },

    /**
     * Replaces the default template for Stripe payments, to add a button that
     * directly links to the resepctive charge in the Stripe dashboard.
     *
     * @return The created template.
     */
    createPaymentTemplate: function () {
        // Check for stripe payment
        var paymentMethod = this.record.getPayment().first();
        if (paymentMethod && (paymentMethod.raw.action === "NovalPayment" )) {
            // Use the custom template
            return new Ext.XTemplate(
                '{literal}<tpl for=".">',
                '<div class="customer-info-pnl">',
                '<div class="base-info">',
                '<p>',
                '<span>{novalPaymentName}</span>',
                '</p>',
                '</div>',
                '</div>',
                '</tpl>{/literal}'
            );
        }

        // Use the default template
        return this.callParent(arguments);
    },
    
    getNovalnetPaymentName : function () {
        var result = false;
        Ext.Ajax.request({
            url:     '{url controller=NovalPayment action=getNovalnetPaymentName}',
            method:  'POST',
            async:   false,
            params:  {
                number : this.record.get('number'),
                id : this.record.get('id'),
                transactionId : this.record.get('transactionId'),
            },
            success: function (response) {
                var decodedResponse = Ext.decode(response.responseText);
                result = decodedResponse.novalnetPaymentName;
            }
        });
        return result;
    },


});
//{/block}
