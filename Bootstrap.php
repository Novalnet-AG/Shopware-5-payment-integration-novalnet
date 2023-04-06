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

require_once __DIR__ . '/Components/CSRFWhitelistAware.php';

use Shopware\Plugins\NovalPayment\Setup\PluginInstaller;
use Shopware\Plugins\NovalPayment\Setup\PluginForm;
use Shopware\Plugins\NovalPayment\Subscriber\ControllerRegistration\BackendController;
use Shopware\Plugins\NovalPayment\Subscriber\ControllerRegistration\FrontendController;
use Shopware\Plugins\NovalPayment\Subscriber\Frontend\Frontend;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetValidator;
use Shopware\Models\Config\Element;
use Shopware\Components\Theme\LessDefinition;
use Doctrine\Common\Collections\ArrayCollection;

class Shopware_Plugins_Frontend_NovalPayment_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var null|PluginInstaller
     */
    private $pluginInstaller;

    /**
     * @var null|PluginForm
     */
    private $pluginForm;

    /**
     * @var \Shopware\Components\DependencyInjection\Container
     */
    private $container;

    /**
     * Returns the novalnet version
     *
     * @param null
     * @return mixed
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    /**
     * composer autoloading
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function afterInit()
    {
        $this->registerCustomModels();
        $this->registerPluginNamespace();
    }

    /**
     * registers plugin namespace
     *
     * @return void
     */
    public function registerPluginNamespace()
    {
        $this->get('loader')->registerNamespace('Shopware\\Plugins\\NovalPayment', $this->Path());
        $this->get('loader')->registerNamespace('NovalPayment', $this->Path() . 'Components/Classes/');
        $this->get('snippets')->addConfigDir($this->Path() . 'Snippets/');
    }

    /**
     * Update function of the plugin
     *
     * @param string $version
     * @return array
     */
    public function update($version)
    {
        $this->install();
        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template','frontend', 'theme')
        );
    }

    /**
     * Returns the informations of plugin as array.
     *
     * @param null
     * @return array
     */
    public function getInfo()
    {
        $lang = (string) Shopware()->Container()->get('locale');

        if (strpos($lang, 'en') !== false) {
            $description = file_get_contents(__DIR__ . '/descriptionEN.html');
        } else {
            $description = file_get_contents(__DIR__ . '/descriptionDE.html');
        }
        return array(
            'version' => $this->getVersion(),
            'author' => 'Novalnet AG',
            'label' => 'Novalnet Payment',
            'link' => 'https://www.novalnet.de',
            'copyright' => 'Copyright © ' . date('Y') . ', Novalnet AG',
            'support' => 'https://www.novalnet.de',
            'description' => $description
        );
    }

    public function getCapabilties()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true,
            'delete' => true
        );
    }

    /**
     * Plugin Install method
     *
     * @param $version
     *
     * @return void
     */
    public function install($version = '12.3.4')
    {
        $lang = (string) Shopware()->Container()->get('locale');
        $this->getPluginInstaller()->setPluginName($this->getName());
        $this->getPluginInstaller()->install();
        $this->createPaymentForm();
        $this->createSubscriberEvents();
        $returnArray = array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template','frontend', 'theme')
        );

        if (version_compare($version, '12.2.0', '>=')) {
            $returnArray = array_merge($returnArray, array(
                'message' => $this->getInfoMessage($lang)
            ));
        }

        return $returnArray;
    }

    /**
     * Create the payment form.
     *
     * @return void
     */
    private function createPaymentForm()
    {
        if ($this->pluginForm == null) {
            $this->pluginForm = new PluginForm();
        }

        foreach (array('novalnet_order_status', 'novalnet_onhold_order_complete', 'novalnet_onhold_order_cancelled', 'novalnet_vendor', 'novalnet_auth_code', 'novalnet_product', 'novalnet_callback_mail_send', 'novalnet_callback_notification_url', 'novalnetcc_force_cc3d', 'novalnetcc_amex_enabled', 'novalnetcc_maestro_enabled', 'novalnetcc_cciframe', 'novalnetcc_standard_configuration', 'novalnetsepa_guarantee_configuration', 'novalnetsepa_guarantee_payment', 'novalnetsepa_guarantee_before_paymenstatus', 'novalnetsepa_guaruntee_minimum', 'novalnetsepa_force_guarantee_payment', 'novalnetinvoice_guarantee_configuration', 'novalnetinvoice_guarantee_payment', 'novalnetinvoice_guarantee_before_paymenstatus', 'novalnetinvoice_guaruntee_minimum', 'novalnetinvoice_force_guarantee_payment') as $rmval) {
            Shopware()->Db()->query('DELETE FROM s_core_config_elements WHERE name = "' . $rmval . '"');
        }

        $form = $this->Form();
        $Config = $this->pluginForm->getPaymentForm();

        foreach ($Config as $key => $value) {
            $form->setElement($value['type'], $key, $value['options']);
        }

        // assign translation for the elements
        $this->pluginForm->getPaymentFormTranslations($form);
    }

    /**
     * Create the Subscribe Events.
     */
    private function createSubscriberEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Front_DispatchLoopStartup',
            'onDispatchLoopStartup'
        );

        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Less',
            'addLessFiles'
        );

        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Javascript',
            'addJsFiles'
        );
    }

    /**
     * Return the update information to end customer
     *
     * @param $lang
     *
     * @return string
     */
    private function getInfoMessage($lang)
    {
        if (strpos($lang, 'en') !== false) {
            return 'Thank you for installing/upgrading Novalnet payment plugin. For setup and handling of the Novalnet-Payment plugin you can find the installation guide <a href="https://www.novalnet.com/docs/plugins/installation-guides/shopware-5-installation-guide.pdf" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Here</a>.';
        } else {
            return 'Vielen Dank für die Installation/Aktualisierung des Novalnet-Zahlungsplugins. Für die Einrichtung und Verwendung des Plugins finden Sie die Installationsanleitung <a href="https://www.novalnet.com/docs/plugins/installation-guides/shopware-5-installation-guide.pdf" target="_blank" style="text-decoration: underline; font-weight: bold; color:#0080c9;">Here</a>';
        }
    }

    /**
     * Register the subscriber for autoload
     */
    public function onDispatchLoopStartup(\Enlight_Event_EventArgs $args)
    {
        $helper         = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $validator      = new NovalnetValidator($helper);
        $dataHandler    = new DataHandler(Shopware()->Models());
        $requestHandler = new ManageRequest($helper);

        $this->get('events')->addSubscriber(new BackendController($this));
        $this->get('events')->addSubscriber(new FrontendController());
        $this->get('events')->addSubscriber(new Frontend($helper, $validator, $requestHandler, $dataHandler));
    }

    /**
     * Make directory for css
     *
     * @return ArrayCollection
     */
    public function addLessFiles()
    {
        $less = new LessDefinition(
            // configuration
            [],
            // less files to compile
            [$this->Path() . '/Views/frontend/_public/src/less/all.less'],
            //import directory
            $this->Path() . '/Views'
        );
        return new ArrayCollection([$less]);
    }

    /**
     * Make directory for js
     *
     * @return ArrayCollection
     */
    public function addJsFiles()
    {
        return new ArrayCollection([
            $this->Path() . '/Views/frontend/_public/src/js/jquery.novalnet-wallet.js',
            $this->Path() . '/Views/frontend/_public/src/js/jquery.novalnet-payment.js',
        ]);
    }

    /**
     * Create the object for plugin installer.
     *
     * @return PluginInstaller
     */
    private function getPluginInstaller()
    {
        if ($this->pluginInstaller !== null) {
            return $this->pluginInstaller;
        }

        $this->container = Shopware()->Container();

        /** @var ModelManager $modelManager */
        $modelManager = $this->container->get('models');
        /** @var Plugin\PaymentInstaller $pluginPaymentInstaller */
        $pluginPaymentInstaller = $this->container->get('shopware.plugin_payment_installer');
        /** @var Connection $dbalConnection */
        $dbalConnection = $this->container->get('dbal_connection');
        /** @var DatabaseHandler $databaseHandler */
        $databaseHandler = $this->container->get('shopware.snippet_database_handler');
        /** @var NovalnetHelper $helper */
        $helper = new NovalnetHelper($this->container, $this->container->get('snippets'));

        $translationWriter = $this->container->has('translation') ? $this->container->get('translation') : (version_compare(Shopware()->Config()->version, '5.6.0', '>=') ? new \Shopware_Components_Translation($dbalConnection, Shopware()->Container()) : new \Shopware_Components_Translation());

        $this->pluginInstaller = new PluginInstaller(
            $modelManager,
            $databaseHandler,
            $pluginPaymentInstaller,
            $translationWriter,
            $helper
        );

        return $this->pluginInstaller;
    }

    /**
     * @return boolean
     */
    public function enable()
    {
        return true;
    }

    /**
     * To disable all novalnet payments method is always called when the plugin is deactivated.
     * Here, you should immediately disable active payment methods.
     */
    public function disable()
    {
        $this->getPluginInstaller()->deactivatePayments();
        return true;
    }

    /**
     * To uninstall the installed Novalnet payment modules
     *
     * @return array
     */
    public function uninstall()
    {
        $this->getPluginInstaller()->uninstall();
        $this->secureUninstall();
        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template','frontend', 'theme')
        );
    }

    /**
     * Register Template directory
     *
     * @return void
     */
    public function registerTemplateDir($viewDir = 'Views')
    {
        $template = $this->get('template');
        $template->addTemplateDir($this->Path() . $viewDir.'/');
    }

    /**
     * @return bool
     */
    public function secureUninstall()
    {
        return true;
    }
}
