<?php
/**
 * Novalnet payment plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 * that is bundled with this package in the file freeware_license_agreement.txt
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
 *
 * @category Novalnet
 * @package NovalPayment
 * @copyright Copyright (c) Novalnet
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

namespace Shopware\Plugins\NovalPayment\Subscriber\ControllerRegistration;

use Enlight\Event\SubscriberInterface;
use Enlight_Template_Manager;

class BackendController implements SubscriberInterface
{
    /**
     * @var \Shopware_Plugins_Frontend_NovalPayment_Bootstrap
     */
    private $bootstrap;

    /**
     * Backend Controller constructor.
     *
     * @param \Shopware_Plugins_Frontend_NovalPayment_Bootstrap $bootstrap
     */
    public function __construct(\Shopware_Plugins_Frontend_NovalPayment_Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_NovalPayment' => 'registerBackendController',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onOrderPostDispatch'
        ];
    }

    public function onPreDispatch()
    {
        $this->bootstrap->registerTemplateDir();
    }

    public function registerBackendController(\Enlight_Event_EventArgs $args)
    {
        return $this->Path() . '/Controllers/Backend/NovalPayment.php';
    }

    /**
     * Call when open the backend order
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onOrderPostDispatch(\Enlight_Event_EventArgs $args)
    {
        $view    = $args->getSubject()->View();
        $request = $args->getSubject()->Request();
        $this->bootstrap->registerTemplateDir();
        if ($request->getActionName() == 'index') {
            $view->extendsTemplate('backend/novalnet_orders/app.js');
        } elseif ($request->getActionName() == 'load') {
            $view->extendsTemplate('backend/novalnet_orders/view/main/window.js');
        }
    }

    public function Path()
    {
        return $this->getPlugin()->Path();
    }

    public function getPlugin()
    {
        return Shopware()->Plugins()->Frontend()->NovalPayment();
    }
}
