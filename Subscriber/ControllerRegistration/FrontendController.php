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

class FrontendController implements SubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
                'Enlight_Controller_Dispatcher_ControllerPath_Frontend_NovalPayment' => 'registerFrontendController',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function registerFrontendController(\Enlight_Event_EventArgs $args)
    {
        return $this->Path() . '/Controllers/Frontend/NovalPayment.php';
    }
    
    public function Path() {
        return $this->getPlugin()->Path();
    }

    public function getPlugin() {
        return Shopware()->Plugins()->Frontend()->NovalPayment();
    }
}
