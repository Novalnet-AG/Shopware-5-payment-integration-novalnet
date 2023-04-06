/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

//{namespace name=backend/order/view/novalnet}
//{block name="backend/order/view/detail/window" append}
Ext.define('Shopware.apps.NovalnetOrders.view.main.Window', {
	
	extend: "Ext.window.Window",
	
	override:'Shopware.apps.Order.view.detail.Window',

    initComponent:function () {
        var me = this;
        me.callParent(arguments);    
    },
    
    createTabPanel: function () {
		var me = this;
		var tabPanel = me.callParent(arguments);        
		if (me.checkNovalPayment()) {
			tabPanel.add(Ext.create('Shopware.apps.NovalnetOrders.view.main.NovalnetExtensions', {
				title:              'Novalnet',
				historyStore:       me.historyStore,
				record:             me.record,
				orderStatusStore:   me.orderStatusStore,
				paymentStatusStore: me.paymentStatusStore                
			 }));
		}
		if (me.displayInstalmentInfoTab()) {
			tabPanel.add(Ext.create('Shopware.apps.NovalnetOrders.view.list.NovalnetInstalment', {
				title:              '{s name=backend_novalnet_order_instalment_info_sub_title}Instalment Information{/s}',
				historyStore:       me.historyStore,
				record:             me.record,
				orderStatusStore:   me.orderStatusStore,
				paymentStatusStore: me.paymentStatusStore                
			 }));				 
		}
		return tabPanel;
    },
        
	checkNovalPayment : function () {
		var result = false;
		Ext.Ajax.request({
			url:     '{url controller=NovalPayment action=showTabForOurPayments}',
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
	
	displayInstalmentInfoTab : function () {
		var result = false;
		Ext.Ajax.request({
			url:     '{url controller=NovalPayment action=displayInstalmentInfoTab}',
			method:  'POST',
			async:   false,
			params:  {
				number : this.record.get('number'),
				id : this.record.get('id'),
				amount : this.record.get('invoiceAmount')
			},
			success: function (response) {
			
				var decodedResponse = Ext.decode(response.responseText);
				result = decodedResponse.success;
			}
		});
		return result;
	},
   
});
//{/block}
