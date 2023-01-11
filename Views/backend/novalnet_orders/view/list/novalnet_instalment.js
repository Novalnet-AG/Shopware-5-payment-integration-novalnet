/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

Ext.require([
    'Ext.grid.*',
    'Ext.data.*',
    'Ext.panel.*'
]);
//{namespace name=backend/order/view/novalnet}
//{block name="backend/order/view/detail/Novalnet"}
Ext.define('Shopware.apps.NovalnetOrders.view.list.NovalnetInstalment', {

    extend: 'Ext.grid.Panel',
    
    alias: 'widget.form-main-formgrid',
    
    region: 'center',
    
    autoScroll: true,

    stateful:true,
    
    listeners: {
        activate: function (tab) {
            var me = this;
            var historystore = Ext.create('Shopware.apps.Order.store.InstalmentInfo');
            var id = me.record.get('id');
            var number = me.record.get('number');
            var store = historystore.load({
                params: {
                    'orderId': id,
                    'number': number
                }
            });
       
            me.reconfigure(store);
        }
    },
        
    /**
     * Contains all snippets for this view
     * @object
     */
    snippets: {
        tooltipOpenArticle: '{s name=novalnet_order_operations_open_article}Open Article{/s}',
        tooltipOpenCustomer: '{s name=novalnet_order_operations_open_customer}Open customer{/s}',
        tooltipRefundAmount: '{s name=backend_novalnet_order_transamountrefund_title}Refund{/s}',
        columnProcessed: '{s name=novalnet_order_operations_serialno_column}S.No{/s}',
        columnDate: '{s name=novalnet_order_operations_date_column}Date{/s}',
        columnAmount: '{s name=novalnet_order_operations_amount_column}Amount{/s}',
        columnStatus: '{s name=novalnet_order_operations_status_column}Status{/s}',
        columnReference: '{s name=novalnet_order_operations_tid_column}Novalnet Transaction ID{/s}'
        
    },

    viewConfig: {
        enableTextSelection: true
    },
   
    initComponent:function () {
        var me = this;
        me.registerEvents();
        me.columns = me.createFieldSet();
        var historystore = Ext.create('Shopware.apps.Order.store.InstalmentInfo');
        me.selModel = me.createSelectionModel();
        var id = me.record.get('id');
        var number = me.record.get('number');
        me.bbar = me.createPagingBar();
        me.callParent(arguments);
    },
    

    registerEvents: function() {
        this.addEvents(

            'openArticle',
 
            'deletePosition'
        );
    },
	createFieldSet: function () {
        var me = this, actionColumItems = [];
        
        actionColumItems.push({
            iconCls:'sprite-user--arrow',
            action:'openCustomer',
            tooltip: me.snippets.tooltipOpenCustomer,
            handler:function (view, rowIndex, colIndex, item) {
                Shopware.app.Application.addSubApplication({
                 name: 'Shopware.apps.Customer',
                 action: 'detail',
                 params: {
                    customerId: me.record.get('customerId')
                 }
              });
            }
        });
        
        actionColumItems.push({
            iconCls:'sprite-pencil',
            tooltip: me.snippets.tooltipRefundAmount,
            handler: function (grid, rowIndex, colIndex) {
                var rec = grid.getStore().getAt(rowIndex);
                me.instalmentAmount = rec.data.amount;
                me.instalmentData = rec;
				Ext.create('Ext.window.Window', {
					title: '{s name=backend_novalnet_order_transamountrefund_title}Refund{/s}',
					width: 900,
					height: 520,
					bodyPadding: 20,
					autoScroll: true,
					layout: 'fit',
					items: [ me.createPartialFieldSet() ]
				}).show();
            }
        });
        
        return [{
            header: me.snippets.columnProcessed,
            dataIndex: 'processedInstalment',
            flex: 1,
            sortable: false
        },{
            header: me.snippets.columnDate,
            dataIndex: 'date',
            flex: 1,
            sortable: false
        },{
            header: me.snippets.columnAmount,
            dataIndex: 'amount',
            flex: 1,
            sortable: false
        },{
            header: me.snippets.columnReference,
            dataIndex: 'reference',
            flex: 1,
            sortable: false
        },{
            header: me.snippets.columnStatus,
            dataIndex: 'status',
            flex: 1,
            sortable: false
        },
        {
            /**
             * Special column type which provides
             * clickable icons in each row
             */
            xtype: 'actioncolumn',
            width: actionColumItems.length * 26,
            items: actionColumItems
        }];
	},
	createPartialFieldSet: function () {
        var me = this;

        return Ext.create('Ext.form.FieldSet', {
            defaults: {
                labelWidth: 40,
                labelStyle: 'font-weight: 700;'
            },
            layout: 'anchor',
            minWidth:100,
            items: me.createPartialElements()
        });
    },
    
    createPartialElements: function () {
        var me = this;
        
        me.instalmentAmount = me.instalmentAmount.replace(/[^0-9]/g, "");
        
        me.externalDescriptionContainer = Ext.create('Ext.container.Container', {
            style: 'color: #000000; margin: 0 0 15px 0;',
            html: '{s name=backend_novalnet_order_operations_partialrefund_field_title}Please enter the refund amount{/s} {s name=novalnet_in_cents}(in minimum unit of currency. E.g. enter 100 which is equal to 1.00):{/s}'
        });

        me.externalRefundAmountTextArea = Ext.create('Ext.form.field.Number', {
            name: 'instalment_partial_amount',
            id : 'instalment_partial_amount',
            decimalSeparator : false,
            minValue: 0,
            height: 20,
            anchor: '40%',
            maxValue:me.instalmentAmount,
            value: me.instalmentAmount,
            enableKeyEvents: true,
            // Remove spinner buttons, and arrow key and mouse wheel listeners
            hideTrigger: true,
            keyNavEnabled: false,
            mouseWheelEnabled: false
        });

        me.externalButton = Ext.create('Ext.button.Button', {
            style: 'margin: 8px 0;',
            cls: 'small primary',
            id: 'instalment_partial_refund',
            text: '{s name=novalnet_order_operations_update_button}Update{/s}',
            handler: function () {
                me.nn_instalment_amount_refund();
            }
        });
            return [me.externalDescriptionContainer , me.externalRefundAmountTextArea, me.externalButton];
        
    },
    
    nn_instalment_amount_refund: function () {
        var me = this;
        var item = me.instalmentData;
        
        if (item.get('reference') == '-' || item.get('reference') == '' ) {
			 Ext.getCmp('instalment_partial_refund').disable();
			 return;
		}
        var refund_confirm_info = '{s name=novalnet_amount_refund_update}Are you sure you want to refund the amount?{/s}' ;
        Ext.Msg.confirm(
            "",
            refund_confirm_info ,
            function (btn) {
                if (btn === 'yes') {
                    Ext.Ajax.request({
                        url: '{url controller=NovalPayment action=processRefund}',
                        method:'POST',
                        async:false,
                        params: {
                            id : me.record.get('id'),
                            number : me.record.get('number'),
                            tid : item.get('reference'),
                            languageIso : me.record.get('languageIso'),
                            currency :  me.record.get('currency'),
                            order_amount : me.record.get('invoiceAmount')*100,
                            nn_partial_refund_amount : Ext.getCmp('instalment_partial_amount').getValue(),
                            reference_transaction : 1
                        },

                        success: function (response) {
                            var messageText = "";
                            var decodedResponse = Ext.decode(response.responseText);
                            messageText =(typeof decodedResponse.message == "object") ? decodedResponse.message['0'] : decodedResponse.message;
                            if (decodedResponse.success) {
                                 Ext.getCmp('instalment_partial_refund').disable();
                            }
                            Shopware.Msg.createGrowlMessage('',messageText, '{s name=window_title}{/s}');
                            me.record.store.load();
                        }
                    });
                }
            }
        );
    },
    createSelectionModel: function() {
        var me = this;

        return Ext.create('Ext.selection.CheckboxModel', {
            listeners: {
                selectionchange: function () {
                }
            }
        });
    },
	createPagingBar: function() {
       var me = this;

        return {
            xtype: 'pagingtoolbar',
            displayInfo: true,
            store: me.store
        };
        
    }    
});
//{/block}
