<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/mpcustomerinvoice/vendor/autoload.php';

use MpSoft\MpCustomerInvoice\Helpers\GetTwigEnvironment;
use MpSoft\MpCustomerInvoice\Helpers\HookManager;
use MpSoft\MpCustomerInvoice\Helpers\InstallMenu;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceBrtAddresses;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobArea;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobLink;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobPosition;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class MpCustomerInvoice extends Module implements WidgetInterface
{
    protected $id_lang;
    protected $serviceProvider;
    protected $hookManager;

    public function __construct()
    {
        $this->name = 'mpcustomerinvoice';
        $this->tab = 'administration';
        $this->version = '1.3.2';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99',
        ];

        parent::__construct();

        $this->displayName = $this->trans('MP Customer Invoice', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->description = $this->trans('Gestisce i codici della fatturazione elettronica.', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->id_lang = (int) $this->context->language->id;
        $this->hookManager = new HookManager($this);
    }

    public function install()
    {
        $installMenu = new InstallMenu($this);

        return parent::install() &&
            $this->registerHook(
                [
                    'actionAdminControllerSetMedia',
                    'actionAdminCustomersControllerFormModifier',
                    'actionAdminCustomersControllerSaveAfter',
                    'actionAdminCustomersFormSubmit',
                    'actionFrontControllerSetMedia',
                    'additionalCustomerFormFields',
                    'actionCustomerAccountAdd',
                    'actionCustomerAccountUpdate',
                    'actionBeforeSubmitAccount',
                    'actionObjectCustomerDeleteAfter',
                    'actionCustomerGridDefinitionModifier',
                    'actionCustomerGridQueryBuilderModifier',
                    'actionCustomerGridDataModifier',
                    'actionCustomerFormDataProviderData',
                    'actionOrderGridDefinitionModifier',
                    'actionOrderGridQueryBuilderModifier',
                    'actionOrderGridDataModifier',
                    'actionAfterCreateCustomerFormHandler',
                    'actionAfterUpdateCustomerFormHandler',
                    'actionCustomerFormBuilderModifier',
                    'additionalCustomerAddressFields',
                    'validateCustomerFormFields',
                    'displayAdminCustomersForm',
                    'displayBeforeBodyClosingTag',
                ]
            ) &&
            $installMenu->installMenu(
                'AdminMpCustomerInvoice',
                'MP Gestione Clienti',
                'AdminParentCustomer',
                'account_circle'
            ) &&
            ModelCustomerInvoice::install() &&
            ModelCustomerInvoiceBrtAddresses::install() &&
            ModelCustomerInvoiceJobArea::install() &&
            ModelCustomerInvoiceJobPosition::install() &&
            ModelCustomerInvoiceJobLink::install();
    }

    public function uninstall()
    {
        $installMenu = new InstallMenu($this);

        return parent::uninstall() &&
            $installMenu->uninstallMenu('AdminMpCustomerInvoice');
    }

    public function renderTwig($path, $params = [])
    {
        $twig = new GetTwigEnvironment($this->name);
        $twig->load($path);

        return $twig->render($params);
    }

    public function renderWidget($hookName, array $configuration)
    {
        switch ($hookName) {
            case 'displayAdminCustomers':
                return $this->hookManager->hookDisplayAdminCustomers($configuration);
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            case 'displayAdminEndContent':
                return $this->hookManager->hookDisplayAdminEndContent($configuration);
            case 'displayBeforeBodyClosingTag':
                return $this->hookManager->hookDisplayBeforeBodyClosingTag($configuration);
            default:
                return '';
        }

        return '';
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $vars = [];
        switch ($hookName) {
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            default:
                return [];
        }

        return $vars;
    }

    public function getContent()
    {
        $message = [];
        if (Tools::isSubmit('submitConfiguration')) {
            $this->saveConfiguration();
            $message = [
                'type' => 'success',
                'content' => $this->l('Configuration updated'),
            ];
        }
        $tpl = $this->getLocalPath() . 'views/templates/admin/configuration.tpl';
        $template = $this->context->smarty->createTemplate($tpl, $this->context->smarty);
        $params = [
            'orderStates' => OrderState::getOrderStates($this->id_lang),
            'message' => $message,
            'PAYMENT_MODULES' => PaymentModule::getPaymentModules(),
            'CUSTOMER_PREFIX' => $this->getConfig('CUSTOMER_PREFIX', ''),
            'TYPE_ORDER' => $this->getConfig('TYPE_ORDER', 0),
            'TYPE_INVOICE' => $this->getConfig('TYPE_INVOICE', 0),
            'TYPE_RETURN' => $this->getConfig('TYPE_RETURN', 0),
            'TYPE_SLIP' => $this->getConfig('TYPE_SLIP', 0),
            'TYPE_DELIVERY' => $this->getConfig('TYPE_DELIVERY', 0),
            'PAYMENT_SELECTED' => $this->getConfig('PAYMENT_SELECTED', []),
            'EXPORT_FILE_NAME' => $this->getConfig('EXPORT_FILE_NAME', 'export'),
        ];
        $template->assign($params);

        return $template->fetch();
    }

    private function saveConfiguration()
    {
        $this->setConfig('PAYMENT_SELECTED', Tools::getValue('PAYMENT_SELECTED'), []);
        $this->setConfig('CUSTOMER_PREFIX', Tools::getValue('CUSTOMER_PREFIX'), '');
        $this->setConfig('TYPE_ORDER', Tools::getValue('TYPE_ORDER'), 0);
        $this->setConfig('TYPE_DELIVERY', Tools::getValue('TYPE_DELIVERY'), 0);
        $this->setConfig('TYPE_INVOICE', Tools::getValue('TYPE_INVOICE'), 0);
        $this->setConfig('TYPE_RETURN', Tools::getValue('TYPE_RETURN'), 0);
        $this->setConfig('TYPE_SLIP', Tools::getValue('TYPE_SLIP'), 0);
        $this->setConfig('PAYMENT_SELECTED', Tools::getValue('PAYMENT_SELECTED'), []);
        $this->setConfig('EXPORT_FILE_NAME', Tools::getValue('EXPORT_FILE_NAME'), 'export');
    }

    public function getConfig($key, $default = null)
    {
        $value = Configuration::get($key);

        try {
            $return = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $th) {
            $return = $value;
        }

        if (!$return && $default !== null) {
            return $default;
        }

        return $return;
    }

    private function setConfig($key, $value, $default = null)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        if (!$value && $default !== null) {
            $value = $default;
        }

        return Configuration::updateValue($key, $value);
    }

    /**
     * Hook per aggiungere i campi personali al form di registrazione
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        return $this->hookManager->hookAdditionalCustomerFormFields($params);
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));
        $baseJs = $this->getLocalPath() . 'views/assets/js/';

        if ($controller == 'admincustomers' && Tools::getValue('id_customer')) {
            $this->context->controller->addJS("{$baseJs}admin/jobLinkManager.js");
        }
    }

    /**
     * Hook per impostare CSS e JS nel frontend
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));

        if ($controller == 'registration' || $controller == 'order') {
            // 1. Carica jQuery (necessario per Chosen)
            $this->context->controller->addJquery();

            // 2. Carica i file di Chosen dal core (i percorsi rimangono questi anche in PS 8)
            $this->context->controller->registerJavascript(
                'remote-chosen-js',
                'js/jquery/plugins/jquery.chosen.js',
                ['position' => 'bottom', 'priority' => 100]
            );

            $this->context->controller->registerStylesheet(
                'remote-chosen-css',
                'js/jquery/plugins/pages/jquery.chosen.css',
                ['media' => 'all', 'priority' => 100]
            );

            // 3. Carica il TUO script di inizializzazione
            $this->context->controller->registerJavascript(
                'module-my-chosen-init',
                'modules/' . $this->name . '/views/js/init_chosen.js',
                ['position' => 'bottom', 'priority' => 150]
            );

            /*
             * $this->context->controller->registerJavascript(
             *     'mpcustomerinvoice-admin',
             *     'modules/mpcustomerinvoice/views/assets/js/registration/registrationPage.js',
             *     [
             *         'priority' => 100,
             *     ]
             * );
             */
        }
    }

    public function hookAdditionalCustomerAddressFields($params)
    {
        return $this->hookAdditionalCustomerFormFields($params);
    }

    /**
     * Hook per modificare il form di registrazione
     */
    public function hookActionAdminCustomersControllerFormModifier($params)
    {
        return $this->hookManager->hookActionAdminCustomersControllerFormModifier($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionCustomerAccountAdd($params)
    {
        return $this->hookManager->hookActionCustomerAccountAdd($params);
    }

    /**
     * Hook che scatta quando si aggiorna il cliente dal form di registrazione
     */
    public function hookActionCustomerAccountUpdate($params)
    {
        return $this->hookManager->hookActionCustomerAccountUpdate($params);
    }

    /**
     * Hook che inserisce i campi custom nel form di registrazione
     */
    public function hookActionCustomerFormBuilderModifier($params)
    {
        return $this->hookManager->hookActionCustomerFormBuilderModifier($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionAfterCreateCustomerFormHandler($params)
    {
        return $this->hookManager->hookActionAfterCreateCustomerFormHandler($params);
    }

    /**
     * Hook che scatta quando si aggiorna il cliente dal form di registrazione
     */
    public function hookActionAfterUpdateCustomerFormHandler($params)
    {
        return $this->hookManager->hookActionAfterUpdateCustomerFormHandler($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionCustomerFormDataProviderData($params)
    {
        return $this->hookManager->hookActionCustomerFormDataProviderData($params);
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        return $this->hookManager->hookActionObjectCustomerDeleteAfter($params);
    }

    public function hookActionCustomerGridDefinitionModifier($params)
    {
        return $this->hookManager->hookActionCustomerGridDefinitionModifier($params);
    }

    public function hookActionCustomerGridQueryBuilderModifier($params)
    {
        return $this->hookManager->hookActionCustomerGridQueryBuilderModifier($params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        return $this->hookManager->hookActionOrderGridDefinitionModifier($params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        return $this->hookManager->hookActionOrderGridQueryBuilderModifier($params);
    }

    public function hookActionOrderGridDataModifier($params)
    {
        return $this->hookManager->hookActionOrderGridDataModifier($params);
    }
}
