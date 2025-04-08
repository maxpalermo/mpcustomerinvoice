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
require_once _PS_MODULE_DIR_ . '/mpcustomerinvoice/models/autoload.php';

use Doctrine\ORM\QueryBuilder;
use MpSoft\MpCustomerInvoice\Helpers\install\InstallMenu;
use MpSoft\MpCustomerInvoice\Helpers\install\TableGenerator;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;

class MpCustomerInvoice extends Module
{
    protected $id_lang;
    protected $serviceProvider;

    public function __construct()
    {
        $this->name = 'mpcustomerinvoice';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];

        if (class_exists('MpSoft\MpCustomerInvoice\MpCustomerInvoiceServiceProvider')) {
            $this->serviceProvider = new \MpSoft\MpCustomerInvoice\MpCustomerInvoiceServiceProvider();
        }

        parent::__construct();

        $this->displayName = $this->trans('MP Customer Invoice', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->description = $this->trans('Gestisce i codici della fatturazione elettronica.', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->id_lang = (int) $this->context->language->id;
    }

    public function install()
    {
        $installMenu = new InstallMenu($this);
        $tableGenerator = new TableGenerator($this);

        return parent::install()
            && $this->registerHook(
                [
                    'actionFrontControllerSetMedia',
                    'actionAdminControllerSetMedia',
                    'actionAdminCustomersFormModifier',
                    'actionAdminCustomersControllerSaveAfter',
                    'additionalCustomerFormFields',
                    'actionCustomerAccountAdd',
                    'actionCustomerAccountUpdate',
                    'actionBeforeSubmitAccount',
                    'actionObjectCustomerAddAfter',
                    'actionObjectCustomerUpdateAfter',
                    'actionObjectCustomerDeleteAfter',
                    'actionObjectCustomerUpdateBefore',
                    'actionCustomerGridDefinitionModifier',
                    'actionCustomerGridQueryBuilderModifier',
                    'actionCustomerFormDataProviderData',
                    'actionAfterCreateCustomerFormHandler',
                    'actionAfterUpdateCustomerFormHandler',
                    'actionCustomerFormBuilderModifier',
                    'validateCustomerFormFields',
                    'displayAdminCustomers',
                    'displayBeforeBodyClosingTag',
                ]
            )
            && $installMenu->installMenu('AdminMpCustomerInvoice', 'Codice Clienti F.E.', 'AdminParentCustomer', 'account_circle')
            && $tableGenerator->createTable(ModelMpCustomerInvoice::$definition);
    }

    public function uninstall()
    {
        $installMenu = new InstallMenu($this);

        return parent::uninstall()
            && $installMenu->uninstallMenu('AdminMpCustomerInvoice');
    }

    public function renderWidget($hookName, array $configuration)
    {
        switch ($hookName) {
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
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

    public function hookActionFrontControllerSetMedia()
    {
        $controller = Context::getContext()->controller;
        $controller_name = Tools::strtolower(Tools::getValue('controller'));

        if ($controller_name == 'registration') {
            $jsPath = "/modules/{$this->name}/views/js/";
            $cssPath = "/modules/{$this->name}/views/css/";
            $controller->registerJavascript('mpcustomerinvoiceScript', $jsPath . 'registration/script.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceElement', $jsPath . 'admin/ElementFromHtml.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2', $jsPath . 'swal2/sweetalert2.all.min.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Confirm', $jsPath . 'swal2/request/SwalConfirm.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Error', $jsPath . 'swal2/request/SwalError.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Success', $jsPath . 'swal2/request/SwalSuccess.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Warning', $jsPath . 'swal2/request/SwalWarning.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Info', $jsPath . 'swal2/request/SwalInfo.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Note', $jsPath . 'swal2/request/SwalNote.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceSwal2Loading', $jsPath . 'swal2/request/SwalLoading.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceTippyCore', $jsPath . 'tippy/popper-core2.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceTippy', $jsPath . 'tippy/tippy.js', ['priority' => 100]);

            $controller->registerStylesheet('mpcustomerinvoiceSwal2', $cssPath . 'swal2/sweetalert2.min.css', ['priority' => 100]);
            $controller->registerStylesheet('mpcustomerinvoiceTippy', $cssPath . 'tippy/scale.css', ['priority' => 100]);
        }
    }

    public function hookActionAdminControllerSetMedia()
    {
        $controller_name = Tools::strtolower(Tools::getValue('controller'));
        $id_order = (int) Tools::getValue('id_order');

        if ($controller_name == 'adminorders' && $id_order) {
            $controller = Context::getContext()->controller;
            $cssPath = $this->getLocalPath() . 'views/css/';
            $jsPath = $this->getLocalPath() . 'views/js/';

            $controller->addCSS(
                [
                    $cssPath . 'swal2/sweetalert2.min.css',
                    $cssPath . 'tippy/scale.css',
                ],
                'all',
                100
            );

            $controller->addJS([
                $jsPath . 'swal2/sweetalert2.all.min.js',
                $jsPath . 'swal2/request/SwalConfirm.js',
                $jsPath . 'swal2/request/SwalError.js',
                $jsPath . 'swal2/request/SwalSuccess.js',
                $jsPath . 'swal2/request/SwalWarning.js',
                $jsPath . 'swal2/request/SwalInfo.js',
                $jsPath . 'swal2/request/SwalNote.js',
                $jsPath . 'swal2/request/SwalLoading.js',
                $jsPath . 'tippy/popper-core2.js',
                $jsPath . 'tippy/tippy.js',
                $jsPath . 'admin/script.js',
                $jsPath . 'admin/ElementFromHtml.js',
            ]);
        }

        if ($controller_name == 'admincustomers') {
            $controller = Context::getContext()->controller;
            $cssPath = $this->getLocalPath() . 'views/css/';
            $jsPath = $this->getLocalPath() . 'views/js/';

            $controller->addCSS(
                [
                    $cssPath . 'swal2/sweetalert2.min.css',
                    $cssPath . 'tippy/scale.css',
                ],
                'all',
                100
            );

            $controller->addJS([
                $jsPath . 'swal2/sweetalert2.all.min.js',
                $jsPath . 'swal2/request/SwalConfirm.js',
                $jsPath . 'swal2/request/SwalError.js',
                $jsPath . 'swal2/request/SwalSuccess.js',
                $jsPath . 'swal2/request/SwalWarning.js',
                $jsPath . 'swal2/request/SwalInfo.js',
                $jsPath . 'swal2/request/SwalNote.js',
                $jsPath . 'swal2/request/SwalLoading.js',
                $jsPath . 'tippy/popper-core2.js',
                $jsPath . 'tippy/tippy.js',
                $jsPath . 'admin/ElementFromHtml.js',
                $jsPath . 'admin/script.js',
            ]);
        }
    }

    public function hookDisplayAdminCustomers($params)
    {
        $controller_name = Tools::strtolower($this->context->controller->controller_name);
        if ($controller_name != 'admincustomers') {
            return;
        }

        $this->context->controller->confirmations[] = 'HOOK displayAdminCustomers';
        $controller = $this->context->link->getModuleLink($this->name, 'Export');
        $id_customer = (int) Tools::getValue('id_customer');
        $model = new ModelMpCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return;
        }

        $badgeColor = 'info';
        $fontSize = '1.0rem';
        $subject = $model->type ?: '--';
        $vat_number = $model->vat_number ?: '--';
        $fiscal_code = $model->fiscal_code ?: '--';
        $sdi = $model->sdi ?: '--';
        $pec = $model->pec ?: '--';
        $cig = $model->cig ?: '--';
        $cup = $model->cup ?: '--';

        $script = <<<JS
            <template id="mpcustomerinvoice-personal-info">
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        Soggetto
                    </div>
                    <div class="col-8">
                        <span class="eurosolutionId badge badge-success rounded" style="font-size: {$fontSize}; border-radius: 0;">
                            <i class="material-icons">person</i>
                            {$subject}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        Partita IVA
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$vat_number}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        Codice Fiscale
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$fiscal_code}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        SDI
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$sdi}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        PEC
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$pec}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        CIG
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$cig}
                        </span> 
                    </div>
                </div>
                <div class="row mb-1 eurosolution-container">
                    <div class="col-4 text-right">
                        CUP
                    </div>
                    <div class="col-8">
                        <span style="font-size: {$fontSize};">
                            {$cup}
                        </span> 
                    </div>
                </div>
            </template>

            <script type="text/javascript">
                const adminAjaxController = "{$controller}";
                const MPCUSTOMERINVOICE_customerId = {$id_customer};
                const MPCUSTOMERINVOICE_employeeId = {$this->context->employee->id};

                //creo un nuovo custom event
                const MpCustomerInvoiceReady = new CustomEvent('MpCustomerInvoiceReady', {
                    detail: {
                        MPCUSTOMERINVOICE_employeeId: MPCUSTOMERINVOICE_employeeId??0,
                        MPCUSTOMERINVOICE_customerId: MPCUSTOMERINVOICE_customerId??0,
                    },
                });
                document.dispatchEvent(MpCustomerInvoiceReady);
            </script>
        JS;

        return $script;
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        $controller_name = Tools::strtolower(Tools::getValue('controller'));
        if ($controller_name != 'registration') {
            return;
        }

        $controller = $this->context->link->getModuleLink($this->name, 'Customer');
        $script = <<<JS
            <script type="text/javascript">
                const adminAjaxController = "{$controller}";
            </script>
        JS;

        return $script;
    }

    public function hookActionCustomerAccountAdd($params)
    {
        /**
         * @var Customer
         */
        $customer = $params['newCustomer'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        $model = new ModelMpCustomerInvoice($customer->id);
        if (!Validate::isLoadedObject($model)) {
            $model->force_id = true;
            $model->id = (int) $customer->id;
            $model->date_add = date('Y-m-d H:i:s');
        }
        $model->hydrate($fields);
        $model->date_upd = date('Y-m-d H:i:s');

        return $model->save(true);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        /**
         * @var Customer
         */
        $customer = $params['customer'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        $model = new ModelMpCustomerInvoice($customer->id);
        if (!Validate::isLoadedObject($model)) {
            $model->force_id = true;
            $model->id = (int) $customer->id;
            $model->date_add = date('Y-m-d H:i:s');
        }
        $model->hydrate($fields);
        $model->date_upd = date('Y-m-d H:i:s');

        return $model->save(true);
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        $fields = ModelMpCustomerInvoice::$definition['fields'];

        $formFields = [
            (new FormField)
                ->setName('want_invoice')
                ->setType('checkbox')
                ->setLabel($this->trans('Desidero ricevere la fattura'))
                ->setRequired(false)
                ->setValue(Tools::getValue('want_invoice', 0)),

            (new FormField)
                ->setName('type')
                ->setType('select')
                ->setLabel($this->trans('Tipo'))
                ->setRequired(false)
                ->addConstraint('isString')
                ->setAvailableValues([
                    'ENTE' => 'ENTE',
                    'PARTITA_IVA' => 'PARTITA IVA',
                    'PRIVATO' => 'PRIVATO',
                ])
                ->setValue(Tools::getValue('type', 'ENTE')),

            (new FormField)
                ->setName('vat_number')
                ->setType('text')
                ->setLabel($this->trans('Partita IVA'))
                ->setMaxLength($fields['vat_number']['size'])
                ->setRequired(false)
                ->setValue(Tools::getValue('vat_number', '')),

            (new FormField)
                ->setName('fiscal_code')
                ->setType('text')
                ->setLabel($this->trans('Codice Fiscale'))
                ->setMaxLength($fields['fiscal_code']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('fiscal_code', '')),

            (new FormField)
                ->setName('cuu')
                ->setType('text')
                ->setLabel($this->trans('Codice Unico dal portale IPA'))
                ->setMaxLength($fields['cuu']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('cuu', '')),

            (new FormField)
                ->setName('sdi')
                ->setType('text')
                ->setLabel($this->trans('Codice destinatario (SDI)'))
                ->setMaxLength($fields['sdi']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('sdi', '')),

            (new FormField)
                ->setName('pec')
                ->setType('text')
                ->setLabel($this->trans('PEC'))
                ->setMaxLength($fields['pec']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('pec', '')),

            (new FormField)
                ->setName('cig')
                ->setType('text')
                ->setLabel($this->trans('Codice di identificazione del gestore (CIG)'))
                ->setMaxLength($fields['cig']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('cig', '')),

            (new FormField)
                ->setName('cup')
                ->setType('text')
                ->setLabel($this->trans('Codice univoco di pagamento (CUP)'))
                ->setMaxLength($fields['cup']['size'])
                ->setRequired(false)
                ->addConstraint('isString')
                ->setValue(Tools::getValue('cup', '')),
        ];

        $paramsFormFields = $params['fields'];
        // divido paramsFormFields in due array: optin e formfields
        $optinFields = [];
        $customerFields = [];
        foreach ($paramsFormFields as $key => $field) {
            if ($key === 'optin') {
                $optinFields[$key] = $field;
            } else {
                $customerFields[$key] = $field;
            }
        }
        $outFields = array_merge($customerFields, $formFields, $optinFields);
        $params['fields'] = $outFields;

        return false;
    }

    public function hookValidateCustomerFormFields($params)
    {
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        return true;
    }

    public function hookActionBeforeSubmitAccount($params)
    {
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return true;
        }

        return true;
    }

    protected function getFrontCustomerFields()
    {
        $fields = [
            'type' => Tools::getValue('type', ''),
            'vat_number' => Tools::getValue('vat_number', ''),
            'fiscal_code' => Tools::getValue('fiscal_code', ''),
            'cuu' => Tools::getValue('cuu', ''),
            'sdi' => Tools::getValue('sdi', ''),
            'pec' => Tools::getValue('pec', ''),
            'cig' => Tools::getValue('cig', ''),
            'cup' => Tools::getValue('cup', ''),
        ];

        return $fields;
    }

    public function hookActionObjectCustomerAddAfter($params)
    {
        $customer = $params['object'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return true;
        }

        if (Validate::isLoadedObject($customer)) {
            $model = new ModelMpCustomerInvoice((int) $customer->id);
            if (!Validate::isLoadedObject($model)) {
                $model->force_id = true;
                $model->id = (int) $customer->id;
                $model->date_add = date('Y-m-d H:i:s');
            }
            $model->hydrate($fields);
            $model->date_upd = null;
            if ($model->id) {
                return $model->update(true);
            } else {
                $model->force_id = true;
                $model->id = (int) $customer->id;
                $model->date_add = date('Y-m-d H:i:s');

                return $model->add(true, true);
            }
        }

        return true;
    }

    /**
     * Hook eseguito PRIMA che un oggetto Customer venga aggiornato.
     * Usato qui per salvare dati custom da campi non mappati nel form.
     *
     * @param array $params
     *
     * @return bool Sempre true, altrimenti potrebbe bloccare l'update.
     *              Gestire errori internamente.
     */
    public function hookActionObjectCustomerUpdateBefore(array $params)
    {
        $controller = Tools::getValue('controller');

        if (!preg_match('/^AdminCustomers/i', $controller)) {
            return true;
        }

        // 1. Recupera l'oggetto Customer (prima del salvataggio)
        /** @var Customer $customer */
        $customer = $params['object'];

        if (!Validate::isLoadedObject($customer)) {
            // Dovrebbe essere sempre un oggetto valido qui, ma meglio controllare
            return true; // Non fare nulla se l'oggetto non è valido
        }

        $id_customer = (int) $customer->id;
        if ($id_customer <= 0) {
            // Non dovrebbe succedere in un UpdateBefore, ma per sicurezza...
            return true;
        }

        // 2. Recupera i dati inviati dal form usando Tools::getValue()
        //    USA I NOMI ESATTI DEI CAMPI come definiti nel FormBuilder!
        $customerValues = Tools::getValue('customer');
        $typeValue = $customerValues['type']; // Es: 'PRIVATO', 'PARTITA_IVA', 'ENTE'
        $vatNumberValue = $customerValues['vat_number']; // Es: '12345678901'
        $fiscalCodeValue = $customerValues['fiscal_code']; // Es: 'AAABBB99X99X123X'
        $sdiValue = $customerValues['sdi']; // Es: '1234567'
        $pecValue = $customerValues['pec']; // Es: 'example@pec.it'
        $cigValue = $customerValues['cig']; // Es: '1234567890'
        $cupValue = $customerValues['cup']; // Es: '12345678901'

        // 3. Prepara i dati per il salvataggio (validazione/pulizia minima se necessaria)
        //    La validazione principale dovrebbe essere avvenuta con Symfony Form Constraints.
        //    Assicurati che i valori siano nei formati attesi per il DB.
        $dataToSave = [
            'type' => pSQL($typeValue), // Usa pSQL per sicurezza base, anche se già validato
            'vat_number' => pSQL($vatNumberValue),
            'fiscal_code' => pSQL($fiscalCodeValue),
            'sdi' => pSQL($sdiValue),
            'pec' => pSQL($pecValue),
            'cig' => pSQL($cigValue),
            'cup' => pSQL($cupValue),
            'id_customer' => $id_customer, // Assicurati di avere id_customer nella tabella
        ];

        // 4. Salva i dati nella tua tabella custom (ps_customer_invoice)
        //    Questa logica fa un UPDATE se esiste già una riga per id_customer,
        //    altrimenti fa un INSERT.
        return $this->saveOrUpdateCustomerInvoiceData($id_customer, $dataToSave);
    }

    /**
     * Salva o aggiorna i dati nella tabella customer_invoice.
     *
     * @param int $id_customer
     * @param array $data Dati da salvare (colonna => valore), già "puliti".
     *
     * @return bool True se successo o se non c'erano dati da salvare, false in caso di errore DB grave.
     */
    private function saveOrUpdateCustomerInvoiceData(int $id_customer, array $data): bool
    {
        // Rimuovi l'id_customer dai dati per l'update, ma tienilo per l'insert
        $updateData = $data;
        unset($updateData['id_customer']);

        // Controlla se esiste già una riga per questo cliente
        $existsQuery = new DbQuery();
        $existsQuery->select('id_customer'); // Seleziona la chiave primaria o un campo qualsiasi
        $existsQuery->from('customer_invoice');
        $existsQuery->where('id_customer = ' . $id_customer);

        try {
            $exists = Db::getInstance()->getValue($existsQuery);

            if ($exists) {
                 // Esiste: Aggiorna la riga esistente
                 // La condizione WHERE è fondamentale!
                $result = Db::getInstance()->update('customer_invoice', $updateData, 'id_customer = ' . $id_customer, 1); // '1' limita a 1 riga
            } else {
                 // Non esiste: Inserisci una nuova riga
                 // Assicurati che $data contenga 'id_customer'
                $result = Db::getInstance()->insert('customer_invoice', $data, false, true, DbCore::INSERT_IGNORE); // INSERT_IGNORE può essere utile se c'è una gara (race condition)
            }
            // $result contiene true/false per l'operazione DB o numero righe per update? Controlla documentazione Db::update/insert
            // Qui assumiamo che se non ci sono eccezioni, l'operazione logica è andata a buon fine
            // Potresti voler controllare $result più in dettaglio se necessario.

            return $result; // Operazione tentata
        } catch (PrestaShopDatabaseException $e) {
            PrestaShopLogger::addLog('[mpcustomerinvoice] Errore salvataggio dati fattura cliente ID ' . $id_customer . ': ' . $e->getMessage(), 3, null, 'mpcustomerinvoice');

            return false; // Errore DB
        }
    }

    public function hookActionObjectCustomerUpdateAfter($params)
    {
        $controller = Tools::getValue('controller');

        if (preg_match('/^AdminCustomers/i', $controller)) {
            return true;
        }

        $customer = $params['object'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        if (Validate::isLoadedObject($customer)) {
            $model = new ModelMpCustomerInvoice((int) $customer->id);
            if (!Validate::isLoadedObject($model)) {
                $model->force_id = true;
                $model->id = (int) $customer->id;
                $model->date_add = date('Y-m-d H:i:s');
            }
            $model->hydrate($fields);
            $model->date_upd = date('Y-m-d H:i:s');
            if ($model->id) {
                return $model->update(true);
            } else {
                return $model->add(true, true);
            }
        }

        return true;
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        $id_customer = (int) $params['object']->id;

        $model = new ModelMpCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return true;
        }

        return $model->delete();
    }

    public function hookActionCustomerFormDataProviderData($params)
    {
        // Code for providing data to the customer form data provider
        $id_customer = (int) $params['id'];

        $model = new ModelMpCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return true;
        }
        $fields = $model->getFields();

        $params['data']['vat_number'] = $fields['vat_number'] ?? '';
        $params['data']['fiscal_code'] = $fields['fiscal_code'] ?? '';
        $params['data']['type'] = $fields['type'] ?? '';
    }

    public function hookActionCustomerFormBuilderModifier($params)
    {
        $id_customer = (int) $params['id'];
        $model = new ModelMpCustomerInvoice($id_customer);

        $builder = $params['form_builder'];
        $builder
            ->add(
                'type',
                ChoiceType::class,
                [
                    'label' => $this->l('Soggetto'),
                    'required' => false,
                    'choices' => [
                        'ENTE' => 'ENTE',
                        'PARTITA IVA' => 'PARTITA_IVA',
                        'PRIVATO' => 'PRIVATO',
                    ],
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il tipo di soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->type,
                ],
            )
            ->add(
                'vat_number',
                TextType::class,
                [
                    'label' => $this->l('Partita IVA'),
                    'required' => false,
                    'constraints' => [
                        // Esempio di vincolo: lunghezza massima di 10 caratteri
                        new Length([
                            'max' => 16,
                            'maxMessage' => $this->trans('La partita IVA non può superare i %limit% caratteri.', ['%limit%' => 16], 'Modules.MpCustomerInvoice.Shop'),
                        ]),
                        // Potresti aggiungere altri vincoli, es. Regex per un formato specifico
                        // new Assert\Regex([
                        //     'pattern' => '/^[A-Z0-9]{5,10}$/',
                        //     'message' => $this->trans('Il formato del codice socio non è valido.', [], 'Modules.MpCustomerInvoice.Shop')
                        // ])
                    ],
                    'attr' => [
                        'placeholder' => $this->trans('Es. 12345678901', [], 'Modules.MpCustomerInvoice.Shop'), // Placeholder nel campo
                        'maxlength' => 16, // Attributo HTML per la lunghezza massima
                    ],
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui la partita IVA del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->vat_number,
                ]
            )->add(
                'fiscal_code',
                TextType::class,
                [
                    'label' => $this->l('Codice Fiscale'),
                    'required' => false,
                    'constraints' => [
                        // Esempio di vincolo: lunghezza massima di 10 caratteri
                        new Length([
                            'max' => 16,
                            'maxMessage' => $this->trans('Il codice fiscale non può superare i %limit% caratteri.', ['%limit%' => 16], 'Modules.MpCustomerInvoice.Shop'),
                        ]),
                        // Potresti aggiungere altri vincoli, es. Regex per un formato specifico
                        // new Assert\Regex([
                        //     'pattern' => '/^[A-Z0-9]{5,10}$/',
                        //     'message' => $this->trans('Il formato del codice fiscale non è valido.', [], 'Modules.MpCustomerInvoice.Shop')
                        // ])
                    ],
                    'attr' => [
                        'placeholder' => $this->trans('Es. AAABBB99X99X123K', [], 'Modules.MpCustomerInvoice.Shop'), // Placeholder nel campo
                        'maxlength' => 16, // Attributo HTML per la lunghezza massima
                    ],
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il codice fiscale del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->fiscal_code,
                ]
            )->add(
                'sdi',
                TextType::class,
                [
                    'label' => $this->l('Codice destinatario (SDI)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il codice destinatario (SDI) del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->sdi,
                ]
            )->add(
                'pec',
                TextType::class,
                [
                    'label' => $this->l('PEC'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il PEC del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->pec,
                ]
            )->add(
                'cig',
                TextType::class,
                [
                    'label' => $this->l('Codice di identificazione del gestore (CIG)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il CIG del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->cig,
                ]
            )->add(
                'cup',
                TextType::class,
                [
                    'label' => $this->l('Codice univoco di pagamento (CUP)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->trans('Inserisci qui il CUP del soggetto (opzionale).', [], 'Modules.MpCustomerInvoice.Shop'),
                    'data' => $model->cup,
                ]
            );

        $params['form_builder'] = $builder;
    }

    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        // Visualizzo le nuove colonne
        // Aggiungo la colonna SOGGETTO
        $definition
            ->getColumns()
            ->addAfter(
                'optin',
                (new DataColumn('type'))
                    ->setName($this->trans('Soggetto', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'type',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('type', ChoiceType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('Soggetto', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                    'choices' => [
                        'ENTE' => 'ENTE',
                        'PARTITA_IVA' => 'PARTITA IVA',
                        'PRIVATO' => 'PRIVATO',
                    ],
                ])
                ->setAssociatedColumn('type')
        );

        // Rimuovo la colonna opti
        $definition->getColumns()->remove('option');

        // Aggiungo la colonna PARTITA IVA
        $definition
            ->getColumns()
            ->addAfter(
                'type',
                (new DataColumn('vat_number'))
                    ->setName($this->trans('Partita IVA', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'vat_number',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('vat_number', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('Partita IVA', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('vat_number')
        );

        // Aggiungo la colonna CODICE FISCALE
        $definition
            ->getColumns()
            ->addAfter(
                'vat_number',
                (new DataColumn('fiscal_code'))
                    ->setName($this->trans('Codice Fiscale', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'fiscal_code',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('fiscal_code', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('Codice Fiscale', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('fiscal_code')
        );

        // Aggiungo la colonna SDI
        $definition
            ->getColumns()
            ->addAfter(
                'fiscal_code',
                (new DataColumn('sdi'))
                    ->setName($this->trans('SDI', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'sdi',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('sdi', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('SDI', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('sdi')
        );

        // Aggiungo la colonna PEC
        $definition
            ->getColumns()
            ->addAfter(
                'sdi',
                (new DataColumn('pec'))
                    ->setName($this->trans('PEC', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'pec',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('pec', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('PEC', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('pec')
        );

        // Aggiungo la colonna CIG
        $definition
            ->getColumns()
            ->addAfter(
                'pec',
                (new DataColumn('cig'))
                    ->setName($this->trans('CIG', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'cig',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('cig', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('CIG', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('cig')
        );

        // Aggiungo la colonna CUP
        $definition
            ->getColumns()
            ->addAfter(
                'cig',
                (new DataColumn('cup'))
                    ->setName($this->trans('CUP', [], 'Modules.Mpcustomerinvoice.Admin'))
                    ->setOptions([
                        'field' => 'cup',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('cup', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->trans('CUP', [], 'Modules.Mpcustomerinvoice.Admin'),
                    ],
                ])
                ->setAssociatedColumn('cup')
        );
    }

    public function hookActionCustomerGridQueryBuilderModifier($params)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $params['search_query_builder'] ?? null;
        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'] ?? null;

        if (!$queryBuilder || !$searchCriteria) {
            return;
        }

        // creo la query di selezione
        $queryBuilder
            ->addSelect('cinv.type')
            ->addSelect("COALESCE(cinv.vat_number, '--') as vat_number")
            ->addSelect("COALESCE(cinv.fiscal_code, '--') as fiscal_code")
            ->addSelect("COALESCE(cinv.cuu, '--') as cuu")
            ->addSelect("COALESCE(cinv.sdi, '--') as sdi")
            ->addSelect("COALESCE(cinv.pec, '--') as pec")
            ->addSelect("COALESCE(cinv.cig, '--') as cig")
            ->addSelect("COALESCE(cinv.cup, '--') as cup");
        $queryBuilder
            ->leftJoin('c', _DB_PREFIX_ . 'customer_invoice', 'cinv', 'c.id_customer = cinv.id_customer');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($filterName == 'type') {
                $queryBuilder->andWhere('cinv.type = :type');
                $queryBuilder->setParameter('type', $filterValue);

                break;
            }
            if ($filterName == 'vat_number') {
                $queryBuilder->andWhere('cinv.vat_number = :vat_number');
                $queryBuilder->setParameter('vat_number', $filterValue);

                break;
            }
            if ($filterName == 'fiscal_code') {
                $queryBuilder->andWhere('cinv.fiscal_code = :fiscal_code');
                $queryBuilder->setParameter('fiscal_code', $filterValue);

                break;
            }

            if ($filterName == 'cuu') {
                $queryBuilder->andWhere('cinv.cuu = :cuu');
                $queryBuilder->setParameter('cuu', $filterValue);

                break;
            }
            if ($filterName == 'sdi') {
                $queryBuilder->andWhere('cinv.sdi = :sdi');
                $queryBuilder->setParameter('sdi', $filterValue);

                break;
            }
            if ($filterName == 'pec') {
                $queryBuilder->andWhere('cinv.pec = :pec');
                $queryBuilder->setParameter('pec', $filterValue);

                break;
            }
            if ($filterName == 'cig') {
                $queryBuilder->andWhere('cinv.cig = :cig');
                $queryBuilder->setParameter('cig', $filterValue);

                break;
            }
            if ($filterName == 'cup') {
                $queryBuilder->andWhere('cinv.cup = :cup');
                $queryBuilder->setParameter('cup', $filterValue);

                break;
            }
        }

        return;
    }

    public function hookActionAdminCustomersFormModifier($params)
    {
        $fieldsForm = &$params['fields'];
        $MPCUSTOMERINVOICE_customerId = (int) $params['id_customer'];

        // Recupera i dati salvati
        $customData = Db::getInstance()->getRow(
            '
        SELECT * FROM ' . _DB_PREFIX_ . 'customer_custom_data
        WHERE id_customer = ' . $MPCUSTOMERINVOICE_customerId
        );

        // Aggiungi i campi al form backoffice
        $fieldsForm['form']['input'][] = [
            'type' => 'text',
            'label' => 'Codice Fiscale',
            'name' => 'codice_fiscale',
            'value' => $customData['codice_fiscale'] ?? '',
        ];

        $fieldsForm['form']['input'][] = [
            'type' => 'checkbox',
            'label' => 'Accettazione termini',
            'name' => 'accept_terms',
            'values' => [
                'query' => [['id' => 'on', 'name' => 'Accettato']],
                'id' => 'id',
                'name' => 'name',
            ],
            'is_bool' => true,
            'value' => $customData['accept_terms'] ?? false,
        ];
    }

    public function hookActionAdminCustomersControllerSaveAfter($params)
    {
        // TODO
    }

    public function hookActionAfterCreateCustomerFormHandler(array $params)
    {
        $MPCUSTOMERINVOICE_customerId = $params['id'];
        $fields = $params['form_data'];
        $model = new ModelMpCustomerInvoice($MPCUSTOMERINVOICE_customerId);
        $model->force_id = true;
        $model->id = $MPCUSTOMERINVOICE_customerId;
        $model->hydrate($fields);
        $model->date_add = date('Y-m-d H:i:s');
        $model->add();
    }

    public function hookActionAfterUpdateCustomerFormHandler(array $params)
    {
        $MPCUSTOMERINVOICE_customerId = $params['id'];
        $fields = $params['form_data'];
        $model = new ModelMpCustomerInvoice($MPCUSTOMERINVOICE_customerId);
        $model->force_id = true;
        $model->id = $MPCUSTOMERINVOICE_customerId;
        $model->hydrate($fields);
        $model->date_upd = date('Y-m-d H:i:s');
        if (Validate::isLoadedObject($model)) {
            $model->update();
        } else {
            $model->add();
        }
    }

    private static function getSqlImport()
    {
        $sql = "SELECT `id_customer`, '' as `cuu`, `uid` as `sdi`, `pec`, `cig`, `cup` FROM `ps_customer` ORDER BY id_customer;";

        return $sql;
    }
}
