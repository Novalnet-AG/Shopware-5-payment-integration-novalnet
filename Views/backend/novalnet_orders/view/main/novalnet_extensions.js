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
        me.showRefundTab = me.paymentOrderDetails[0];          
        me.amountToBePaid = me.paymentOrderDetails[1];        
        me.items = [
            me.createPartialRefundFieldSet(),
            me.createCaptureFieldSet()
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
        var result = false;
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
                result = [decodedResponse.success];                
                amountToBePaid = [decodedResponse.amount];
                details = [result, amountToBePaid];
            }
        });
        return details;
    },
			
	createPartialRefundFieldSet: function () {
			var me = this;
			if (me.showRefundTab[0]) {
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
						value:me.amountToBePaid
						
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
						text: '{s name=backend_novalnet_transaction_update_button} Uppdate {/s}',
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
						text: '{s name=backend_novalnet_transaction_update_button} Uppdate {/s}',
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

	executeRefund: function () {
		var me = this;
		var refundConfirmAlertText = '{s name=novalnet_amount_refund_update}Are you sure you want to refund the amount?{/s}' ;
		var refundAmount = me.getComponent('nnRefundBlock').getComponent('txtRefundAmount').getValue();
		
		if (refundAmount === null || refundAmount === 0 || refundAmount > me.amountToBePaid) {
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
	}
});
//{/block}
