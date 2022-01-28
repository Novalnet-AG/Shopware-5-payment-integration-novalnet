<?php
/**
* Novalnet payment plugin
*
* NOTICE OF LICENSE
*
* This source file is subject to Novalnet End User License Agreement
*
* DISCLAIMER
*
* If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
*
* @author Novalnet AG
* @copyright Copyright (c) Novalnet
* @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz
* @link https://www.novalnet.de
*
* This free contribution made by request.
*
* If you have found this script useful a small
* recommendation as well as a comment on merchant
*
*/

class Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper
{
    private $novalnetLang = array();

    /**
     * Creates an instance of the translation helper
     *
     * @param $form
     * @return null
     */
    public function __construct()
    {
        $this->novalnetLang =
            Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper::novalnetGetLanguage(
                Shopware()->Container()->get('locale')
            );
    }

    /**
     * Removes all Snippets created by the plugin installation routine
     *
     * @param null
     * @return null
     */
    public static function dropSnippets()
    {
        Shopware()->DB()->query(
            'DELETE FROM s_core_snippets WHERE name LIKE "%novalnet%" OR namespace LIKE "%novalnet%"'
        );
    }

    /**
     * Get language description based on Shop language
     *
     * @param $lang
     * @return array
     */
    public static function novalnetGetLanguage($lang)
    {
		$filename = dirname(__FILE__) . '/../../lib/locale/'. $lang .'.csv' ;
		
		if(empty($lang) || !in_array($lang,array('en_GB','de_DE')))
		{
			
			$filename = dirname(__FILE__) . '/../../lib/locale/de_DE.csv' ;
		}
		
        $novalnetLang = array();
        if (file_exists($filename)) {
            if ($file = fopen($filename, 'r')) {
                while ($data = fgetcsv($file, 0, ';', '"')) {
                    $novalnetLang[$data[0]] = $data[1];
                }
            }
        }
        return $novalnetLang;
    }

    /*
    * For backend Translation for this Plugin
    *
    * @param $form
    * @param $Config
    * @return null
    */
    public function changePluginTranslation($form, $Config)
    {
        $enNovalLang   =
            Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper::novalnetGetLanguage(
                'en_GB'
            );
        $deNovalLang   =
            Shopware_Plugins_Frontend_NovalPayment_lib_classes_TranslationHelper::novalnetGetLanguage(
                'de_DE'
            );
        $available_lang = array('de_DE','en_GB');
        foreach ($available_lang as $key) {
            $novaLang = ($key == 'en_GB') ? $enNovalLang : $deNovalLang;
            foreach ($Config as $getElement => $getElementVal) {
                if (!empty($novaLang['config_'.$getElement])) {
                    $translations[$key][$getElement]['label'] = $novaLang['config_'.$getElement];
                }
                if (!empty($novaLang['config_description_'.$getElement])) {
                    $translations[$key][$getElement]['description'] = $novaLang['config_description_'.$getElement];
                }
            }
        }
        foreach ($translations as $localeCode => $snippets) {
            $locale = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale')->findOneBy(
                array(
                    'locale' => $localeCode
                )
            );
            if (empty($locale)) {
                continue;
            }
            foreach ($snippets as $elementName => $snippet) {
                $isUpdate = false;
                $element = $form->getElement($elementName);
                if ($element === null) {
                    continue;
                }
                foreach ($element->getTranslations() as $existingTranslation) {
                    // Check if translation for this locale already exists
                    if ($existingTranslation->getLocale()->getLocale() != $localeCode) {
                        continue;
                    }
                    if (array_key_exists('label', $snippet)) {
                        $existingTranslation->setLabel($snippet['label']);
                    }
                    if (array_key_exists('description', $snippet)) {
                        $existingTranslation->setDescription($snippet['description']);
                    }
                    $isUpdate = true;
                    break;
                }
                if (!$isUpdate) {
                    $elementTranslation = new \Shopware\Models\Config\ElementTranslation();
                    if (array_key_exists('label', $snippet)) {
                        $elementTranslation->setLabel($snippet['label']);
                    }
                    if (array_key_exists('description', $snippet)) {
                        $elementTranslation->setDescription($snippet['description']);
                    }

                    $elementTranslation->setLocale($locale);
                    $element->addTranslation($elementTranslation);
                }
            }
        }
    }
}
