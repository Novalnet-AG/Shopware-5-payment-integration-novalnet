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

use Shopware\Models\Payment\Payment;
use Shopware\Plugins\NovalPayment\Setup\PluginForm;
use Shopware\CustomModels\Transaction\Transaction;
use Shopware\CustomModels\Api\Api;
use Shopware\CustomModels\ChangeSubscriptionPayment\ChangeSubscriptionPayment;
use Shopware\Plugins\NovalPayment\Setup\Mailer;
use Shopware\Models\Config\Element;
use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Plugins\NovalPayment\Subscriber\ControllerRegistration\BackendController;
use Shopware\Plugins\NovalPayment\Subscriber\ControllerRegistration\FrontendController;
use Shopware\Plugins\NovalPayment\Subscriber\Frontend\Frontend;
use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Components\Classes\DataHandler;
use Shopware\Plugins\NovalPayment\Components\Classes\ManageRequest;

define("NOVALNET_PAYMENT_NAME", "novalnetpay");

class Shopware_Plugins_Frontend_NovalPayment_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var null|PluginForm
     */
    private $pluginForm;

    
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
     * registers the custom plugin models and plugin namespaces
     *
     * @return void
     */
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
            $label = 'Novalnet Payment';
        } else {
            $description = file_get_contents(__DIR__ . '/descriptionDE.html');
            $label = 'Novalnet-Zahlung';
        }
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
                
        return array(
            'version' => $this->getVersion(),
            'author' => $info['author'],
            'label' => $label,
            'link' => $info['link'],
            'copyright' => 'Copyright © ' . date('Y') . ', Novalnet AG',
            'support' => $info['link'],
            'description' => $description
        );
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
        $this->deactivatePayments();
        return true;
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
     * composer autoloading
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function afterInit()
    {
        $this->registerCustomModels();
        $this->get('loader')->registerNamespace('Shopware\\Plugins\\NovalPayment', $this->Path());
        $this->get('loader')->registerNamespace('NovalPayment', $this->Path() . 'Components/Classes/');
        $this->get('snippets')->addConfigDir($this->Path() . 'Snippets/');

        if (version_compare(self::getShopwareVersion(), '5.2.0', '<')) {
            return;
        }
    }

    public static function getShopwareVersion()
    {
        $currentVersion = '';

        if (defined('\Shopware::VERSION')) {
            $currentVersion = \Shopware::VERSION;
        }

        //get old composer versions
        if ($currentVersion === '___VERSION___' && class_exists('ShopwareVersion') && class_exists('PackageVersions\Versions')) {
            $currentVersion = \ShopwareVersion::parseVersion(
                \PackageVersions\Versions::getVersion('shopware/shopware')
            )['version'];
        }

        if (!$currentVersion || $currentVersion === '___VERSION___') {
            $currentVersion = Shopware()->Container()->getParameter('shopware.release.version');
        }

        return $currentVersion;
    }
    
    /**
     * perform all neccessary install tasks
     *
     * @return array
     */
    public function install()
    {
        $lang = (string) Shopware()->Container()->get('locale');
        /** @var DatabaseHandler $databaseHandler */
        $databaseHandler = Shopware()->Container()->get('shopware.snippet_database_handler');
        $databaseHandler->loadToDatabase($this->Path() . 'Snippets/');
        $service = Shopware()->Container()->get('shopware_attribute.crud_service');
        $service->update('s_order_attributes', 'novalnet_payment_name', 'string');
        $this->registerEvents();
        $this->createPayments();
        $this->createPaymentForm();
        $this->createNovalnetTables();
        $this->removeMailTemplate();
        $this->insertMailTemplate();

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
     * To uninstall the installed Novalnet payment modules
     *
     * @return array
     */
    public function uninstall()
    {
        $service = Shopware()->Container()->get('shopware_attribute.crud_service');
        $tableMapping = Shopware()->Container()->get('shopware_attribute.table_mapping');
        $service->update('s_order_attributes', 'novalnet_payment_name', 'string');
        if ($tableMapping->isTableColumn('s_order_attributes', 'novalnet_payment_name') === true) {
            $service->delete('s_order_attributes', 'novalnet_payment_name');
        }
        $this->deactivatePayments();
        $this->removeMailTemplate();
        Shopware()->Container()->get('shopware.snippet_database_handler')->removeFromDatabase($this->Path() . 'Snippets/');
        $this->removeNovalnetApiDetails();
        $this->secureUninstall();
        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template','frontend', 'theme')
        );
    }

     /**
     * register for several events to extend shop functions
     */
    protected function registerEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Front_DispatchLoopStartup',
            'onDispatchLoopStartup'
        );

        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Javascript',
            'addJsFiles'
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
     * create payment methods
     */
    protected function createPayments()
    {
        /** @var ModelManager $modelManager */
        $modelManager = Shopware()->Container()->get('models');
        /** @var \Enlight_Components_Snippet_Namespace $namespace */

        $existingPayment = $modelManager->getRepository(Payment::class)->findOneBy(['name' => NOVALNET_PAYMENT_NAME ]);
        # create the payment method if not exists
        
        $lang = (string) Shopware()->Container()->get('locale');
        $novalPaymentDesc = (strpos($lang, 'en') !== false) ? 'Novalnet Payment' : 'Novalnet-Zahlung' ;

        if (!$existingPayment) {
            $paymentMethodData = [
                'name'                  => NOVALNET_PAYMENT_NAME,
                'description'           => $novalPaymentDesc,
                'action'                => 'NovalPayment',
                'active'                => 0,
                'position'              => '1',
                'additionalDescription' => '',
                'template'              => '',
            ];
            $this->createPayment($paymentMethodData);
        }
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
        
        $formElementOrder = ['novalnet_api', 'novalnet_secret_key', 'novalnet_password', 'novalnet_clientkey','NClass', 'novalnet_tariff', 'novalnet_callback', 'novalnet_callback_url', 'novalnetcallback', 'novalnetcallback_test_mode', 'novalnet_callback_mail_send_to', 'novalnet_after_payment_status' ];

        $formElementName = Shopware()->Db()->fetchAll('SELECT name FROM s_core_config_elements WHERE name LIKE "%novalnet%"');

        foreach ($formElementName as $rmvalElement) {
            if (! in_array($rmvalElement['name'], $formElementOrder)) {
                Shopware()->Db()->query('DELETE FROM s_core_config_elements WHERE name = "' . $rmvalElement['name'] . '"');
            }
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
     * Create Transaction and API tables
     *
     * @return void
     */
    private function createNovalnetTables()
    {
        $em = $this->Application()->Models();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schema = [
            $em->getClassMetadata(Transaction::class),
            $em->getClassMetadata(Api::class),
            $em->getClassMetadata(ChangeSubscriptionPayment::class)
        ];
        $schemaTool->updateSchema($schema, true);
    }
    
    /**
     * Insert novalet mail template into database
     *
     */
    private function insertMailTemplate()
    {
        $mailer = new Mailer(Shopware()->Container()->get('models'));
        $mailer->createMailTemplate();
    }

    /**
     * Remove novalet mail template from database
     *
     */
    private function removeMailTemplate()
    {
        $mailer = new Mailer(Shopware()->Container()->get('models'));
        $mailer->remove();
    }
    
    /**
     * Register the subscriber for autoload
     */
    public function onDispatchLoopStartup(\Enlight_Event_EventArgs $args)
    {
        $helper         = new NovalnetHelper(Shopware()->Container(), Shopware()->Container()->get('snippets'));
        $dataHandler    = new DataHandler(Shopware()->Models());
        $requestHandler = new ManageRequest($helper);
        
        $this->get('events')->addSubscriber(new BackendController($this));
        $this->get('events')->addSubscriber(new FrontendController());
        $this->get('events')->addSubscriber(new Frontend($helper, $requestHandler, $dataHandler));
    }
    
    /**
     * Make directory for js
     *
     * @return ArrayCollection
     */
    public function addJsFiles()
    {
        return new ArrayCollection([
            $this->Path() . '/Views/frontend/_public/src/js/jquery.novalnet-payment.js',
        ]);
    }
    
    /**
     * deactivate the payments
     *
     * @return void
     */
    public function deactivatePayments()
    {
        $paymentType = Shopware()->Container()->get('models')->getRepository(Payment::class)->findOneBy([
            'name' => NOVALNET_PAYMENT_NAME,
        ]);
        if ($paymentType) {
            $paymentType->setActive(false);
            Shopware()->Container()->get('models')->persist($paymentType);
            Shopware()->Container()->get('models')->flush($paymentType);
        }
    }
    
    /**
     * Remove the API data from the table.
     *
     * @return void
     */
    public function removeNovalnetApiDetails()
    {
        $count = Shopware()->Db()->fetchAll('SHOW TABLES LIKE "s_novalnet_api"');
        
        if (!empty($count)) {
            /** @var Repository $apiRepository */
            $apiRepository = Shopware()->Container()->get('models')->getRepository(Api::class);

            /** @var API[] $apiData */
            $apiData = $apiRepository->findAll();
            
            foreach ($apiData as $data) {
                Shopware()->Container()->get('models')->transactional(static function ($em) use ($data) {
                    if (!empty($data)) {
                        /** @var ModelManager $em */
                        $em->remove($data);
                        $em->flush();
                    }
                });
            }
        }
    }

    /**
     * @return bool
     */
    public function secureUninstall()
    {
        return true;
    }
}
