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
Ext.define('Shopware.apps.NovalnetOrders.view.main.NovalnetExtensions', {

    extend:'Ext.form.Panel',
    anchor: '100%',
    border: false,
    bodyPadding: 10,

    style: {
        background: '#EBEDEF'
    },
    autoScroll:true,

    stateful:true,

    initComponent:function () {
        var me = this;
        me.paymentOrderDetails = me.displayAmountRefundTab();
        me.instalmentCancel    = me.displayInstalmentCancelTab();
        
        me.items = [
            me.createPartialRefundFieldSet(),
            me.createCaptureFieldSet(),
            me.createInstalmentCancelFieldSet(),
            me.createAmountBookingFieldSet(),
        ];
        me.callParent(arguments);
    },
     
    displayOnholdOrdersTab:  function () {
        var result = false;
        Ext.Ajax.request({
            url:     '{url controller=NovalPayment action=displayOnholdTab}',
            method:  'POST',
            async:   false,
            params:  {
                number : this.record.get('number')
            },
            success: function (response) {
            
                var decodedResponse = Ext.decode(response.responseText);
                result = decodedResponse.success;
            }
        });
        return result;
    },
    
    displayAmountRefundTab : function () {
        let details = {
            'result'         : false,
            'amountToBePaid' : 0
        };

        Ext.Ajax.request({
            url:     '{url controller=NovalPayment action=displayRefundTab}',
            method:  'POST',
            async:   false,
            params:  {
                number : this.record.get('number'),
                id : this.record.get('id')
            },
            success: function (response) {
                var decodedResponse = Ext.decode(response.responseText);
                details.result = decodedResponse.success;
                details.amountToBePaid = decodedResponse.amount;
            }
        });
        
        return details;
    },
        
    displayInstalmentCancelTab : function () {
        let details = {
            'result'                    : false,
            'cancelAllInstalment'       : null,
            'cancelRemainingInstalment' : null
        };

        Ext.Ajax.request({
            url:     '{url controller=NovalPayment action=displayInstalmentCancel}',
            method:  'POST',
            async:   false,
            params:  {
                number : this.record.get('number'),
                id : this.record.get('id')
            },
            success: function (response) {
                var decodedResponse = Ext.decode(response.responseText);
                details.result                    = decodedResponse.success;
                details.cancelAllInstalment       = decodedResponse.displayAllInstalment;
                details.cancelRemainingInstalment = decodedResponse.displayRemainingInstalment;
            }
        });

        return details;
    },

    displayZeroAmountBookingTab : function () {
        var result = false;
        Ext.Ajax.request({
            url:     '{url controller=NovalPayment action=displayZeroAmountBookingTab}',
            method:  'POST',
            async:   false,
            params:  {
                number : this.record.get('number'),
                id : this.record.get('id')
            },
            success: function (response) {
            
                var decodedResponse = Ext.decode(response.responseText);
                result = decodedResponse.success;
            }
        });
        return result;
    },

    createPartialRefundFieldSet: function () {
            var me = this;
        if (me.paymentOrderDetails.result) {
            return Ext.create('Ext.form.FieldSet', {
                itemId: 'nnRefundBlock',
                title: '{s name=backend_novalnet_order_transamountrefund_title}Refund{/s}',
                defaults: {
                    labelWidth: 215,
                    labelStyle: 'font-weight: 700;'
                },
                layout: 'anchor',
                minWidth:250,
                items: [
                     {
                            xtype: 'numberfield',
                            name: 'txtRefundAmount',
                            itemId: 'txtRefundAmount',
                            decimalSeparator : false,
                            style: 'width : 120px',
                            fieldLabel: '{s name=backend_novalnet_order_operations_partialrefund_field_title}Please enter the refund amount{/s}',
                            anchor: '60%',
                            cols: 1,
                            allowBlank: false,
                            grow: true,
                            maxLength: 70,
                            hideTrigger: true,
                            keyNavEnabled: false,
                            mouseWheelEnabled: false,
                            value:me.paymentOrderDetails.amountToBePaid
                        
                },
                     {
                            xtype: 'textfield',
                            name: 'txtRefundReason',
                            itemId: 'txtRefundReason',
                            decimalSeparator : false,
                            style: 'width : 120px',
                            fieldLabel: '{s name=backend_novalnet_order_operations_refund_ref_field_title} Refund reference {/s}',
                            anchor: '60%',
                            cols: 1,
                            allowBlank: false,
                            grow: true,
                            maxLength: 70,
                            hideTrigger: true,
                            keyNavEnabled: false,
                            mouseWheelEnabled: false
                },
                     {
                            xtype: 'button',
                            cls: 'small primary',
                            name: 'btn_refund',
                            itemId: 'btn_refund',
                            text: '{s name=backend_novalnet_transaction_update_button} Update {/s}',
                            handler: function () {
                                me.executeRefund();
                            }
                }
                ]
            });
        }
    },
    
    createCaptureFieldSet: function () {
            var me = this;
        if (me.displayOnholdOrdersTab()) {
            var subscription_stop_types = Ext.create('Ext.data.Store', {
                fields: ['abbr', 'name'],
                data: [{
                    "abbr": "100",
                    "name": "{s name=backend_novalnet_order_debit_field}Confirm{/s}"
                }, {
                    "abbr": "103",
                    "name": "{s name=backend_novalnet_order_debit_cancel}Cancel{/s}"
                }]
                });
                  
            return Ext.create('Ext.form.FieldSet', {
                itemId: 'nnCaptureBlock',
                title: '{s name=backend_novalnet_order_transaction_confirmation_title}Manage Transaction{/s}',
                defaults: {
                    labelWidth: 215,
                    labelStyle: 'font-weight: 700;'
                },
                layout: 'anchor',
                minWidth:250,
                items: [
                 {
                        xtype: 'combobox',
                        labelWidth: 215,
                        labelStyle: 'font-weight: 700;',
                        name: 'novalnet_onhold_trans',
                        itemId: 'novalnet_onhold_trans',
                        store: subscription_stop_types,
                        enableKeyEvents: true,
                        forceSelection: true,
                        fieldLabel:'{s name=novalnet_text_please_select_status}Please select status{/s}',
                        queryMode: 'local',
                        displayField: 'name',
                        anchor: '60%',
                        cols: 1,
                        allowBlank: true,
                        grow: true,
                        valueField: 'abbr',
                        emptyText: "{s name=novalnet_select_type}Select{/s}",
                        listeners: {
                            select: function ( combo, record, index) {
                            }
                        }
                },
                 {
                        xtype: 'button',
                        cls: 'small primary',
                        text: '{s name=backend_novalnet_transaction_update_button} Update {/s}',
                        name: 'btn_capture',
                        itemId: 'btn_capture',
                        handler: function () {
                            me.executeCapture();
                        }
                }
                ]
            });
        }
    },
    
    createInstalmentCancelFieldSet: function () {
        var me = this;
        var instalmentCancelOptions = [];
        var cancelAllInstalment       = {};
        var cancelRemainingInstalment = {};

        if (me.instalmentCancel.cancelAllInstalment == 'CANCEL_ALL_CYCLES' ) {
            cancelAllInstalment = {
                "abbr": "CANCEL_ALL_CYCLES",
                "name": "{s name=backend_novalnet_order_instalment_cancel_all_cycle}Cancel All Instalments{/s}",
            };
        }
        
        if (me.instalmentCancel.cancelRemainingInstalment == 'CANCEL_REMAINING_CYCLES' ) {
            cancelRemainingInstalment = {
                "abbr": "CANCEL_REMAINING_CYCLES",
                "name": "{s name=backend_novalnet_order_instalment_cancel_remaining_cycle}Cancel All Remaining Instalments{/s}"
            };
        }

        var optionValues = [cancelAllInstalment, cancelRemainingInstalment];
        optionValues.forEach(function (item,index) {
            if (Object.keys(item).length !== 0 ) {
                instalmentCancelOptions.push(item);
            }
        });
       
        if (me.instalmentCancel.result) {
             var instalment_cancel_types = Ext.create('Ext.data.Store', {
                    fields: ['abbr', 'name'],
                    data: instalmentCancelOptions
                });

            return Ext.create('Ext.form.FieldSet', {
                itemId: 'nnInstalmentCancelBlock',
                title: '{s name=backend_novalnet_order_trans_instalment_cancel_title}Instalment Cancel{/s}',
                defaults: {
                    labelWidth: 215,
                    labelStyle: 'font-weight: 700;'
                },
                layout: 'anchor',
                minWidth:250,
                items: [
                     {
                            xtype: 'combobox',
                            labelWidth: 215,
                            labelStyle: 'font-weight: 700;',
                            name: 'novalnet_instalment_canceltypes',
                            itemId: 'novalnet_instalment_canceltypes',
                            store: instalment_cancel_types,
                            enableKeyEvents: true,
                            forceSelection: true,
                            fieldLabel:'{s name=novalnet_text_please_select_instalment_cancel_type}Please select Instalment Cancel Type{/s}',
                            queryMode: 'local',
                            displayField: 'name',
                            anchor: '60%',
                            cols: 1,
                            allowBlank: true,
                            grow: true,
                            valueField: 'abbr',
                            emptyText: "{s name=novalnet_select_type}Select{/s}",
                            listeners: {
                                select: function ( combo, record, index) {
                                }
                            }
                },
                     {
                            xtype: 'button',
                            cls: 'small primary',
                            text: '{s name=backend_novalnet_transaction_update_button} Update {/s}',
                            name: 'btn_instalmentcancel',
                            itemId: 'btn_instalmentcancel',
                            handler: function () {
                                me.executeInstalmentCancel();
                            }
                }
                ]
            });
        }
    },
    
    createAmountBookingFieldSet: function () {
        var me = this;
        if (me.displayZeroAmountBookingTab()) {
            return Ext.create('Ext.form.FieldSet', {
                itemId: 'nnAmountBookingBlock',
                title: '{s name=backend_novalnet_order_zero_amount_sub_title}Book transaction{/s}',
                defaults: {
                    labelWidth: 215,
                    labelStyle: 'font-weight: 700;'
                },
                layout: 'anchor',
                minWidth:250,
                items: [
                     {
                            xtype: 'numberfield',
                            name: 'txtBookingAmount',
                            itemId: 'txtBookingAmount',
                            decimalSeparator : false,
                            style: 'width : 120px',
                            fieldLabel: '{s name=backend_novalnet_order_zero_amount_value}Please enter amount{/s} {s name=novalnet_in_cents}(in minimum unit of currency. E.g. enter 100 which is equal to 1.00){/s}',
                            anchor: '60%',
                            cols: 1,
                            allowBlank: false,
                            grow: true,
                            minValue: 0,
                            maxLength: 70,
                            hideTrigger: true,
                            keyNavEnabled: false,
                            mouseWheelEnabled: false,
                            value: me.record.get('invoiceAmount') * 100,
                },
                     {
                            xtype: 'button',
                            cls: 'small primary',
                            name: 'btn_booking_amount',
                            itemId: 'btn_booking_amount',
                            text: '{s name=backend_novalnet_amount_book_button} Book {/s}',
                            handler: function () {
                                me.executeBookingAmount();
                            }
                }
                ]
            });
        }
    },
    
    executeBookingAmount: function () {
        var me = this;
        var BookingConfirmAlertText = '{s name=novalnet_booking_amount_update}Are you sure you want to book the order amount?{/s}' ;
        var bookingAmount = me.getComponent('nnAmountBookingBlock').getComponent('txtBookingAmount').getValue();
        if (bookingAmount == null || bookingAmount == 0) {
            Shopware.Notification.createStickyGrowlMessage({
                title: "{s name=window_title}{/s}",
                text: '{s name=novalnet_amount_invalid}The amount is invalid{/s}',
                width: 440,
                log: false
              });
             return;
        }

        Ext.Msg.confirm(
            "",
            BookingConfirmAlertText ,
            function (btn) {
                if (btn === 'yes') {
                    Ext.Ajax.request({
                        url: '{url controller=NovalPayment action=processBookingAmount}',
                        method:'POST',
                        async:false,
                        params: {
                            id : me.record.get('id'),
                            number : me.record.get('number'),
                            languageIso : me.record.get('languageIso'),
                            currency :  me.record.get('currency'),
                            transactionId : me.record.get('transactionId'),
                            bookingAmount : bookingAmount,
                        },

                        success: function (response) {
                            var messageText = "";
                            var decodedResponse = Ext.decode(response.responseText);
                            messageText =(typeof decodedResponse.message == "object") ? decodedResponse.message['0'] : decodedResponse.message;
                            if (decodedResponse.success) {
                                Ext.ComponentQuery.query('#btn_booking_amount')[0].disable();
                            }
                            Shopware.Msg.createGrowlMessage('',messageText, '{s name=window_title}{/s}');
                            me.record.store.load();
                        }
                    });
                }
            }
        );
    },
    
    executeRefund: function () {
        var me = this;
        var refundConfirmAlertText = '{s name=novalnet_amount_refund_update}Are you sure you want to refund the amount?{/s}' ;
        var refundAmount = me.getComponent('nnRefundBlock').getComponent('txtRefundAmount').getValue();
        
        if (refundAmount === null || refundAmount === 0 || refundAmount > me.paymentOrderDetails.amountToBePaid) {
            Shopware.Notification.createStickyGrowlMessage({
                title: "{s name=window_title}{/s}",
                text: '{s name=novalnet_amount_invalid}The amount is invalid{/s}',
                width: 440,
                log: false
              });
             return;
        }
        
        Ext.Msg.confirm(
            "",
            refundConfirmAlertText ,
            function (btn) {
                if (btn === 'yes') {
                    Ext.Ajax.request({
                        url: '{url controller=NovalPayment action=processRefund}',
                        method:'POST',
                        async:false,
                        params: {
                            id : me.record.get('id'),
                            number : me.record.get('number'),
                            cleared : me.record.get('cleared'),
                            languageIso : me.record.get('languageIso'),
                            currency :  me.record.get('currency'),
                            refund_amount : refundAmount,
                            refund_reason : me.getComponent('nnRefundBlock').getComponent('txtRefundReason').getValue()
                        },

                        success: function (response) {
                            var messageText = "";
                            var decodedResponse = Ext.decode(response.responseText);
                            messageText =(typeof decodedResponse.message == "object") ? decodedResponse.message['0'] : decodedResponse.message;
                            if (decodedResponse.success) {
                                Ext.ComponentQuery.query('#btn_refund')[0].disable();
                            }
                            Shopware.Msg.createGrowlMessage('',messageText, '{s name=window_title}{/s}');
                            me.record.store.load();
                        }
                    });
                }
            }
        );
    },
    
    executeCapture : function () {
        var me = this;
        var statusValue = me.getComponent('nnCaptureBlock').getComponent('novalnet_onhold_trans').getValue();
        var order_confirm_info = ((statusValue == 100) ? '{s name=novalnet_amount_confirm}Are you sure you want to capture the payment?{/s}' : '{s name=novalnet_amount_cancel}Are you sure you want to cancel the payment?{/s}' );
        
        if (statusValue === null) {
            Shopware.Notification.createStickyGrowlMessage({
                title: "{s name=window_title}{/s}",
                text: '{s name=novalnet_text_please_select_status}Please select status{/s}',
                width: 440,
                log: false
                });
            return;
        }
        
        Ext.Msg.confirm(
            "",
            order_confirm_info ,
            function (btn) {
                if (btn === 'yes') {
                    Ext.Ajax.request({
                        url: '{url controller=NovalPayment action=processCapture}',
                        method:'POST',
                        async:false,
                        params: {
                            id          : me.record.get('id'),
                            languageIso : me.record.get('languageIso'),
                            number : me.record.get('number'),
                            status : me.getComponent('nnCaptureBlock').getComponent('novalnet_onhold_trans').getValue()
                        },
                        success: function (response) {
                            var messageText = "";
                            var decodedResponse = Ext.decode(response.responseText);
                            messageText =(typeof decodedResponse.message == "object") ? decodedResponse.message['0'] : decodedResponse.message;
                            if (decodedResponse.success) {
                                Ext.ComponentQuery.query('#btn_capture')[0].disable();
                            }
                            Shopware.Msg.createGrowlMessage('', messageText, '{s name=window_title}{/s}');
                            me.record.store.load();
                        }
                    });
                }
            }
        );
    },
    
    executeInstalmentCancel : function () {
        var me = this;
        var statusValue = me.getComponent('nnInstalmentCancelBlock').getComponent('novalnet_instalment_canceltypes').getValue();
        var instalment_cancel_confirm_info = ((statusValue == 'CANCEL_ALL_CYCLES') ? '{s name=novalnet_instalment_cancel_all_cycles}Are you sure you want to Cancel All Instalments?{/s}' : '{s name=novalnet_instalment_cancel_remaining_cycles}Are you sure you want to cancel Cancel All Remaining Instalments?{/s}' );

        if (statusValue === null) {
            Shopware.Notification.createStickyGrowlMessage({
                title: "{s name=window_title}{/s}",
                text: '{s name=novalnet_text_please_select_status}Please select status{/s}',
                width: 440,
                log: false
                });
            return;
        }

        Ext.Msg.confirm(
            "",
            instalment_cancel_confirm_info ,
            function (btn) {
                if (btn === 'yes') {
                    Ext.Ajax.request({
                        url: '{url controller=NovalPayment action=processInstalmentCancel}',
                        method:'POST',
                        async:false,
                        params: {
                            id          : me.record.get('id'),
                            languageIso : me.record.get('languageIso'),
                            number : me.record.get('number'),
                            currency :  me.record.get('currency'),
                            transactionId : me.record.get('transactionId'),
                            cancelType : me.getComponent('nnInstalmentCancelBlock').getComponent('novalnet_instalment_canceltypes').getValue()
                        },
                        success: function (response) {
                            var messageText = "";
                            var decodedResponse = Ext.decode(response.responseText);
                            messageText =(typeof decodedResponse.message == "object") ? decodedResponse.message['0'] : decodedResponse.message;
                            if (decodedResponse.success) {
                                Ext.ComponentQuery.query('#btn_instalmentcancel')[0].disable();
                            }
                            Shopware.Msg.createGrowlMessage('', messageText, '{s name=window_title}{/s}');
                            me.record.store.load();
                        }
                    });
                }
            }
        );
    },
});
//{/block}
