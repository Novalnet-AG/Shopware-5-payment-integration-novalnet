Ext.define('Shopware.apps.Order.store.InstalmentInfo', {

    /**
     * Define that this component is an extension of the Ext.data.Store
     */
    extend: 'Ext.data.Store',

    /**
     * Auto load the store after the component
     * is initialized
     * @boolean
     */
    autoLoad: false,

    remoteSort: false,

    remoteFilter: false,
    pageSize: 25,

    /**
     * Define the used model for this store
     * @string
     */
    model: 'Shopware.apps.Order.model.InstalmentInfo'
});
