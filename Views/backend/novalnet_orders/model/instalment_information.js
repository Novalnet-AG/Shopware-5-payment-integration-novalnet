Ext.define('Shopware.apps.Order.model.InstalmentInfo', {

    /**
     * Extends the standard Ext Model
     * @string
     */
    extend: 'Ext.data.Model',

    /**
     * The fields used for this model
     * @array
     */
    fields: [
		{ name: 'processedInstalment', type: 'int' },
		{ name: 'date', type: 'datetime' },
        { name: 'amount', type: 'string' },
        { name: 'refundedAmount', type: 'string' },
        { name: 'reference', type: 'string' },
        { name: 'status', type: 'string' }
    ],

    /**
     * Configure the data communication
     * @object
     */
    proxy: {
        type: 'ajax',

        /**
         * Configure the url mapping for the different
         * store operations based on
         * @object
         */
        api: {
            read: '{url controller="NovalPayment" action="displayPaymentInstalmentDetails"}'
        },

        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});
