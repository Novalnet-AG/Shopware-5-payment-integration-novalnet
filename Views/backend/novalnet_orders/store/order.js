// {block name="backend/novalnet_orders/store/order"}
Ext.define('Shopware.apps.NovalnetOrders.store.Order', {
    /**
     * extends from the standard ExtJs store class
     * @type { String }
     */
    extend: 'Shopware.store.Listing',

    /**
     * the model which belongs to the store
     * @type { String }
     */
    model: 'Shopware.apps.NovalnetOrders.model.ShopwareOrder',

    /**
     * @return { Object }
     */
    configure: function () {
        return {
            controller: 'NovalnetOrders'
        };
    }
});
// {/block}
