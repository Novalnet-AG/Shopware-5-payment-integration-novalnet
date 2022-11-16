/**
 * Novalent payment plugin
 *
 * @author       Novalnet
 * @package      NovalPayment
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */
////{block name="backend/order/application" append}
{include file="backend/novalnet_orders/view/main/window.js"}
{include file="backend/novalnet_orders/view/main/novalnet_extensions.js"}
{include file="backend/novalnet_orders/view/list/novalnet_instalment.js"}
{include file="backend/novalnet_orders/model/instalment_information.js"}
{include file="backend/novalnet_orders/store/instalment_information.js"}
//{/block}
