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

namespace Shopware\Plugins\NovalPayment\Setup;

use Shopware\Plugins\NovalPayment\Components\Classes\NovalnetHelper;
use Shopware\Plugins\NovalPayment\Setup\Mailer;
use Shopware\CustomModels\Transaction\Transaction;
use Shopware\CustomModels\Api\Api;
use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Api\Resource\Translation;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\PaymentInstaller;
use Shopware\Components\Snippet\DatabaseHandler;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Shopware_Components_Translation;

class PluginInstaller
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var DatabaseHandler
     */
    private $snippetHandler;

    /**
     * @var PaymentInstaller
     */
    private $paymentInstaller;

    /**
     * @var Shopware_Components_Translation
     */
    private $translation;

    /**
     * @var string
     */
    private $snippetDir;

    /**
     * @var NovalnetHelper
     */
    private $helper;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * Installer constructor.
     *
     * @param ModelManager $modelManager
     * @param DatabaseHandler $snippetHandler
     * @param PaymentInstaller $paymentInstaller
     * @param Shopware_Components_Translation $translation
     * @param NovalnetHelper $helper
     */
    public function __construct(
        ModelManager $modelManager,
        DatabaseHandler $snippetHandler,
        PaymentInstaller $paymentInstaller,
        Shopware_Components_Translation $translation,
        NovalnetHelper $helper
    ) {
        $this->modelManager     = $modelManager;
        $this->snippetHandler   = $snippetHandler;
        $this->paymentInstaller = $paymentInstaller;
        $this->translation      = $translation;
        $this->snippetDir       = __DIR__ . '/../Snippets/';
        $this->helper           = $helper;
    }

    /**
     * Create novalnet tables, novalnet payments and mail templates
     */
    public function install()
    {
        $this->snippetHandler->loadToDatabase($this->snippetDir);
        $this->createNovalnetPayments();
        $this->createNovalnetTables();
        $this->removeMailTemplate();
        $this->insertMailTemplate();
    }

    /**
     * set payment plugin name
     */
    public function setPluginName($pluginName)
    {
        $this->pluginName = $pluginName;
    }

    /**
     * Insert novalet mail template into database
     *
     */
    private function insertMailTemplate()
    {
        $mailer = new Mailer($this->modelManager);
        $mailer->createMailTemplate();
    }

    /**
     * Remove novalet mail template from database
     *
     */
    private function removeMailTemplate()
    {
        $mailer = new Mailer($this->modelManager);
        $mailer->remove();
    }

    /**
     * create Novalnet Transaction Tables
     */
    private function createNovalnetPayments()
    {
        $payments = array_keys($this->helper->getPaymentInfo());
        $setPosition = 1;

        /** @var Repository $shopRepository */
        $shopRepository = $this->modelManager->getRepository(Shop::class);

        /** @var Shop[] $shops */
        $shops = $shopRepository->findAll();

        $defaultShop = $shopRepository->getActiveDefault();

        foreach ($payments as $paymentName) {
            Shopware()->Snippets()->setShop($defaultShop);
            /** @var \Enlight_Components_Snippet_Namespace $namespace */
            $namespace = Shopware()->Snippets()->getNamespace('frontend/novalnet/payment');

            $template = in_array($paymentName, array('novalnetsepa', 'novalnetsepaGuarantee', 'novalnetsepainstalment')) ? 'novalnetsepa.tpl' : (in_array($paymentName, array('novalnetinvoice', 'novalnetinvoiceGuarantee', 'novalnetinvoiceinstalment')) ? 'novalnetinvoice.tpl' : (in_array($paymentName, ['novalnetcc', 'novalnetpaypal']) ? $paymentName . '.tpl' : 'novalnetlogo.tpl'));

            $existingPayment = $this->modelManager->getRepository(Payment::class)->findOneBy(['name' => $paymentName]);

            # create the payment method if not exists
            if (!$existingPayment) {
                $paymentMethodData = [
                    'name'                  => $paymentName,
                    'description'           => $namespace->get('frontend_payment_name_'. $paymentName),
                    'action'                => 'NovalPayment',
                    'active'                => 0,
                    'position'              => $setPosition,
                    'additionalDescription' => $namespace->get('frontend_payment_description_'. $paymentName),
                    'template'              => $template,
                ];

                foreach ($shops as $shop) {
                    Shopware()->Snippets()->setShop($shop);
                    $namespace = Shopware()->Snippets()->getNamespace('frontend/novalnet/payment');
                    $paymentId = $this->paymentInstaller->createOrUpdate($this->pluginName, $paymentMethodData)->getId();

                    $data = [
                        'description'           => $namespace->get('frontend_payment_name_'. $paymentName),
                        'additionalDescription' => $namespace->get('frontend_payment_description_'. $paymentName),
                    ];
                    $this->translation->write($shop->getId(), Translation::TYPE_PAYMENT, $paymentId, $data, true);
                }
            }
            $setPosition++;
        }
    }

    /**
     * Create Transaction and API tables
     *
     * @return void
     */
    private function createNovalnetTables()
    {
        $schemaManager = new SchemaTool($this->modelManager);
        $schema = $this->getClasses();
        $schemaManager->updateSchema($schema, true);
    }

    private function getClasses()
    {
        return [
            $this->modelManager->getClassMetadata(Transaction::class),
            $this->modelManager->getClassMetadata(Api::class)
        ];
    }

    /**
     * Remove the snippets from the database
     *
     * @return void
     */
    public function uninstall()
    {
        $this->deactivatePayments();
        $this->removeMailTemplate();
        $this->snippetHandler->removeFromDatabase($this->snippetDir);
        $this->removeNovalnetApiDetails();
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
            $apiRepository = $this->modelManager->getRepository(Api::class);

            /** @var API[] $apiData */
            $apiData = $apiRepository->findAll();

            foreach ($apiData as $data) {
                $this->modelManager->transactional(static function ($em) use ($data) {
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
     * deactivate the payments
     *
     * @return void
     */
    public function deactivatePayments()
    {
        $payments = array_keys($this->helper->getPaymentInfo());
        foreach ($payments as $paymentName) {
            $paymentType =  $this->modelManager->getRepository(Payment::class)->findOneBy([
                'name' => $paymentName,
            ]);
            if ($paymentType) {
                $paymentType->setActive(false);
                $this->modelManager->persist($paymentType);
                $this->modelManager->flush($paymentType);
            }
        }
    }
    
    public function updateRefundSnippetValue()
    {
        $snippets = Shopware()->Db()->fetchAll(
            '
            SELECT ss.localeID, sl.locale
            FROM s_core_snippets ss
            INNER JOIN s_core_locales sl ON ss.localeID = sl.id
            WHERE ss.name = ?
            ORDER BY ss.localeID ASC',
            array('backend_novalnet_order_operations_refund_ref_field_title')
        );
        
        foreach ($snippets as $snippet) {
            $snippetLanguage = ($snippet['locale'] == 'de_DE') ? 'Grund der Rückerstattung/Stornierung' : ($snippet['locale'] == 'en_GB' ? 'Refund / Cancellation Reason' : null);
            
            if (!empty($snippetLanguage)) {
                Shopware()->Db()->query(
                    '
                    UPDATE s_core_snippets
                    SET value = ?
                    WHERE localeID = ? AND name = ?',
                    array(
                        $snippetLanguage,
                        $snippet['localeID'],
                        'backend_novalnet_order_operations_refund_ref_field_title'
                    )
                );
            }
        }
    }
}
