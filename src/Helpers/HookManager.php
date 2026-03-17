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

namespace MpSoft\MpCustomerInvoice\Helpers;

use Doctrine\DBAL\Query\QueryBuilder;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobArea;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobLink;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobPosition;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\HtmlColumn;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use \Context;
use \Db;
use \DbQuery;
use \FormField;
use \PrestaShopDatabaseException;
use \PrestaShopLogger;
use \Tools;
use \Validate;

class HookManager
{
    protected $module;
    protected $context;
    protected $id_lang;
    protected $adminAjaxController;

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
        $this->id_lang = (int) $this->context->language->id;
        $this->adminAjaxController = $this->context->link->getAdminLink('AdminMpCustomerInvoice');
    }

    protected function reverseKeyArray($array)
    {
        $out = [];
        foreach ($array as $key => $value) {
            $out[$value] = $key;
        }

        return $out;
    }

    public function hookDisplayBeforeBodyClosingTag($configuration)
    {
        $controller = Tools::getValue('controller');
        $idLang = (int) Context::getContext()->language->id;

        if ($controller == 'registration') {
            $params = [
                'endpoint' => $this->context->link->getModuleLink($this->module->name, 'Customer'),
                'jobs_b64' => base64_encode(json_encode($this->getJobs())),
                'countriesJson' => json_encode(\Country::getCountries($idLang)),
            ];
            $html = $this->module->renderTwig('front/registration', $params);

            return $html;
        }
    }

    protected function getJobs()
    {
        $out = [];
        $jobs = ModelCustomerInvoiceJobArea::getList();
        foreach ($jobs as $key => $job) {
            $id = (int) $key;
            $name = (string) $job;

            $out[$id] = [
                'id' => $id,
                'name' => $name,
                'jobs' => [],
            ];

            $jobLink = ModelCustomerInvoiceJobLink::getJobs($id);
            foreach ($jobLink as $link) {
                $out[$id]['jobs'][] = [
                    'id' => $link['id'],
                    'name' => $link['name'],
                ];
            }
        }

        return $out;
    }

    public function hookActionFrontControllerSetMedia()
    {
        $controller = Context::getContext()->controller;
        $controller_name = Tools::strtolower(Tools::getValue('controller'));

        if ($controller_name == 'registration') {
            $jsPath = "/modules/{$this->module->name}/views/js/";
            $cssPath = "/modules/{$this->module->name}/views/css/";
            $controller->registerJavascript('mpcustomerinvoiceScript', $jsPath . 'registration/script.js', ['priority' => 100]);
            $controller->registerJavascript('mpcustomerinvoiceElement', $jsPath . 'admin/ElementFromHtml.js', ['priority' => 100]);
        }
    }

    public function hookActionAdminControllerSetMedia()
    {
        $controller_name = Tools::strtolower(Tools::getValue('controller'));
        $id_order = (int) Tools::getValue('id_order');

        if ($controller_name == 'adminorders' && $id_order) {
            $controller = Context::getContext()->controller;
            $cssPath = $this->module->getLocalPath() . 'views/css/';
            $jsPath = $this->module->getLocalPath() . 'views/js/';

            $controller->addJS([
                $jsPath . 'admin/script.js',
                $jsPath . 'admin/ElementFromHtml.js',
            ]);
        }

        if ($controller_name == 'admincustomers') {
            $controller = Context::getContext()->controller;
            $cssPath = $this->module->getLocalPath() . 'views/css/';
            $jsPath = $this->module->getLocalPath() . 'views/js/';

            $controller->addJS([
                $jsPath . 'admin/ElementFromHtml.js',
                $jsPath . 'admin/script.js',
            ]);
        }
    }

    /**
     * Hook per mostrare le informazioni del cliente
     * @param mixed $params
     * @return string
     */
    public function hookDisplayAdminCustomers($params)
    {
        $controller_name = Tools::strtolower($this->context->controller->controller_name);
        if ($controller_name != 'admincustomers') {
            return '';
        }

        $this->context->controller->confirmations[] = 'HOOK displayAdminCustomers';
        $controller = $this->context->link->getModuleLink($this->module->name, 'Export');
        $id_customer = (int) Tools::getValue('id_customer');
        $model = new ModelCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return '';
        }

        $badgeColor = 'info';
        $fontSize = '1.0rem';
        $subject = $model->type ?: '--';
        $vat_number = $model->vat_number ?: '--';
        $dni = $model->dni ?: '--';
        $sdi = $model->sdi ?: '--';
        $pec = $model->pec ?: '--';
        $cig = $model->cig ?: '--';
        $cup = $model->cup ?: '--';
        $id_eurosolution = $model->id_eurosolution ?: 0;
        $id_customer_invoice_job_area = $model->id_customer_invoice_job_area ?: 0;
        $id_customer_invoice_job_position = $model->id_customer_invoice_job_position ?: 0;
        $job_area = '--';
        $job_position = '--';

        if ($id_customer_invoice_job_area) {
            $modelJobArea = new ModelCustomerInvoiceJobArea($id_customer_invoice_job_area, $this->id_lang);
            if (Validate::isLoadedObject($modelJobArea)) {
                $job_area = $modelJobArea->name;
            }
        }

        if ($id_customer_invoice_job_position) {
            $modelJobPosition = new ModelCustomerInvoiceJobPosition($id_customer_invoice_job_position, $this->id_lang);
            if (Validate::isLoadedObject($modelJobPosition)) {
                $job_position = $modelJobPosition->name;
            }
        }

        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/customerInfo.html.twig');
        $html = $template->render([
            'badgeColor' => $badgeColor,
            'fontSize' => $fontSize,
            'subject' => $subject,
            'vat_number' => $vat_number,
            'dni' => $dni,
            'sdi' => $sdi,
            'pec' => $pec,
            'cig' => $cig,
            'cup' => $cup,
            'id_eurosolution' => $id_eurosolution,
            'id_customer_invoice_job_area' => $id_customer_invoice_job_area,
            'id_customer_invoice_job_position' => $id_customer_invoice_job_position,
            'job_area' => $job_area,
            'job_position' => $job_position,
            'id_employee' => $this->context->employee->id,
            'id_customer' => $id_customer,
            'controller' => $this->context->link->getAdminLink('AdminCustomers'),
        ]);

        return $html;
    }

    public function hookDisplayAdminEndContent($params)
    {
        $ajaxAdminControllerUrl = $this->context->link->getAdminLink('AdminMpCustomerInvoice');
        $controller = Tools::strtolower(Tools::getValue('controller'));

        switch ($controller) {
            case 'admincustomers':
                if (Tools::getValue('id_customer')) {
                    $html = "
                        <script type='text/javascript'>
                            const MPCUSTOMERINVOICE_ADMIN_CONTROLLER = '{$ajaxAdminControllerUrl}';
                        </script>
                    ";
                    return $html;
                }
                break;
            default:
                break;
        }

        return '';
    }

    public function hookActionCustomerAccountAdd($params)
    {
        /** @var \Customer */
        $customer = $params['newCustomer'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        $model = new ModelCustomerInvoice($customer->id);
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
        /** @var \Customer */
        $customer = $params['customer'];
        $want_invoice = Tools::getValue('want_invoice', 0);
        $fields = $this->getFrontCustomerFields();

        if ($want_invoice == 0) {
            return;
        }

        $model = new ModelCustomerInvoice($customer->id);
        if (!Validate::isLoadedObject($model)) {
            $model->force_id = true;
            $model->id = (int) $customer->id;
            $model->date_add = date('Y-m-d H:i:s');
        }
        $model->hydrate($fields);
        $model->date_upd = date('Y-m-d H:i:s');

        return $model->save(true);
    }

    /**
     * Hook che aggiunge dei campi custom nel form di registrazione del Cliente.
     *
     * @param array $params
     *
     * @return bool Sempre true, altrimenti potrebbe bloccare l'update.
     *              Gestire errori internamente.
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        $fields = ModelCustomerInvoice::$definition['fields'];
        $frontAjaxController = $this->context->link->getModuleLink($this->module->name, 'Customer');

        $formFields = [
            (new FormField)
                ->setName('want_invoice')
                ->setType('select')
                ->setAvailableValues(['0' => 'NO', '1' => 'SI'])
                ->setLabel($this->module->l('Desidero ricevere la fattura'))
                ->setRequired(false),
            (new FormField)
                ->setName('id_customer_invoice_job_area')
                ->setType('select')
                ->setLabel($this->module->l('Settore'))
                ->setRequired(false)
                ->addConstraint('isInt')
                ->setAvailableValues(ModelCustomerInvoiceJobArea::getList())
                ->setValue(Tools::getValue('id_customer_invoice_job_area', '')),
            (new FormField)
                ->setName('id_customer_invoice_job_position')
                ->setType('select')
                ->setLabel($this->module->l('Professione'))
                ->addConstraint('isInt')
                ->setRequired(false)
                ->setAvailableValues(ModelCustomerInvoiceJobPosition::getList())
                ->setValue(Tools::getValue('id_customer_invoice_job_position', ''))
        ];

        $paramsFormFields = $params['fields'];
        // divido paramsFormFields in due array: optin e formfields
        // così inserisco i nuovi campi in mezzo
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

        return true;
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

    public function hookActionAdminCustomersControllerFormModifier($params)
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

    public function hookActionAfterCreateCustomerFormHandler(array $params)
    {
        $MPCUSTOMERINVOICE_customerId = $params['id'];
        $fields = $params['form_data'];
        $model = new ModelCustomerInvoice($MPCUSTOMERINVOICE_customerId);
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
        $model = new ModelCustomerInvoice($MPCUSTOMERINVOICE_customerId);
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

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        $id_customer = (int) $params['object']->id;

        $model = new ModelCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return true;
        }

        return $model->delete();
    }

    public function hookActionCustomerFormDataProviderData($params)
    {
        // Code for providing data to the customer form data provider
        $id_customer = (int) $params['id'];

        $model = new ModelCustomerInvoice($id_customer);
        if (!Validate::isLoadedObject($model)) {
            return true;
        }
        $fields = $model->getFields();

        $params['data']['id_eurosolution'] = $fields['id_eurosolution'] ?? '';
        $params['data']['type'] = $fields['type'] ?? '';
        $params['data']['vat_number'] = $fields['vat_number'] ?? '';
        $params['data']['dni'] = $fields['dni'] ?? '';
        $params['data']['cuu'] = $fields['cuu'] ?? '';
        $params['data']['sdi'] = $fields['sdi'] ?? '';
        $params['data']['pec'] = $fields['pec'] ?? '';
        $params['data']['cig'] = $fields['cig'] ?? '';
        $params['data']['cup'] = $fields['cup'] ?? '';
        $params['data']['id_address_invoice'] = $fields['id_address_invoice'] ?? '';
        $params['data']['is_foreign'] = $fields['is_foreign'] ?? '';
        $params['data']['id_customer_invoice_job_area'] = $fields['id_customer_invoice_job_area'] ?? '';
        $params['data']['id_customer_invoice_job_position'] = $fields['id_customer_invoice_job_position'] ?? '';
    }

    /**
     * Inserisce i campi custom nella pagina di admin del cliente
     *
     * @param mixed $params
     * @return void
     */
    public function hookActionCustomerFormBuilderModifier($params)
    {
        $id_customer = (int) $params['id'];
        $model = new ModelCustomerInvoice($id_customer);

        $builder = $params['form_builder'];
        $builder
            ->add(
                'id_eurosolution',
                TextType::class,
                [
                    'label' => $this->module->l('Id EuroSolution'),
                    'required' => false,
                    'constraints' => [
                        // Esempio di vincolo: lunghezza massima di 10 caratteri
                        new Length([
                            'max' => 16,
                            'maxMessage' => $this->module->l('La partita IVA non può superare i 16 caratteri.'),
                        ]),
                        // Potresti aggiungere altri vincoli, es. Regex per un formato specifico
                        new Regex([
                            'pattern' => '/^[0-9]+$/',
                            'message' => $this->module->l('Il formato del codice è solo numerico.')
                        ])
                    ],
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il codice Eurosolution (opzionale).'),
                    'data' => $model->id_eurosolution,
                ],
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'label' => $this->module->l('Soggetto'),
                    'required' => false,
                    'choices' => [
                        'ENTE' => 'ENTE',
                        'PARTITA IVA' => 'PARTITA_IVA',
                        'PRIVATO' => 'PRIVATO',
                    ],
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il tipo di soggetto (opzionale).'),
                    'data' => $model->type,
                ],
            )
            ->add(
                'vat_number',
                TextType::class,
                [
                    'label' => $this->module->l('Partita IVA'),
                    'required' => false,
                    'constraints' => [
                        // Esempio di vincolo: lunghezza massima di 10 caratteri
                        new Length([
                            'max' => 16,
                            'maxMessage' => $this->module->l('La partita IVA non può superare i 16 caratteri.'),
                        ]),
                        // Potresti aggiungere altri vincoli, es. Regex per un formato specifico
                        // new Assert\Regex([
                        //     'pattern' => '/^[A-Z0-9]{5,10}$/',
                        //     'message' => $this->module->l('Il formato del codice socio non è valido.', [], 'Modules.MpCustomerInvoice.Admin')
                        // ])
                    ],
                    'attr' => [
                        'placeholder' => $this->module->l('Es. 12345678901'),  // Placeholder nel campo
                        'maxlength' => 16,  // Attributo HTML per la lunghezza massima
                    ],
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui la partita IVA del soggetto (opzionale).'),
                    'data' => $model->vat_number,
                ]
            )
            ->add(
                'dni',
                TextType::class,
                [
                    'label' => $this->module->l('Codice Fiscale'),
                    'required' => false,
                    'constraints' => [
                        // Esempio di vincolo: lunghezza massima di 10 caratteri
                        new Length([
                            'max' => 16,
                            'maxMessage' => $this->module->l('Il codice fiscale non può superare i 16 caratteri.'),
                        ]),
                        // Potresti aggiungere altri vincoli, es. Regex per un formato specifico
                        // new Assert\Regex([
                        //     'pattern' => '/^[A-Z0-9]{5,10}$/',
                        //     'message' => $this->module->l('Il formato del codice fiscale non è valido.', [], 'Modules.MpCustomerInvoice.Admin')
                        // ])
                    ],
                    'attr' => [
                        'placeholder' => $this->module->l('Es. AAABBB99X99X123K'),  // Placeholder nel campo
                        'maxlength' => 16,  // Attributo HTML per la lunghezza massima
                    ],
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il codice fiscale del soggetto (opzionale).'),
                    'data' => $model->dni,
                ]
            )
            ->add(
                'sdi',
                TextType::class,
                [
                    'label' => $this->module->l('Codice destinatario (SDI)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il codice destinatario (SDI) del soggetto (opzionale).'),
                    'data' => $model->sdi,
                ]
            )
            ->add(
                'pec',
                TextType::class,
                [
                    'label' => $this->module->l('PEC'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il PEC del soggetto (opzionale).'),
                    'data' => $model->pec,
                ]
            )
            ->add(
                'cig',
                TextType::class,
                [
                    'label' => $this->module->l('Codice di identificazione del gestore (CIG)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il CIG del soggetto (opzionale).'),
                    'data' => $model->cig,
                ]
            )
            ->add(
                'cup',
                TextType::class,
                [
                    'label' => $this->module->l('Codice univoco di pagamento (CUP)'),
                    'required' => false,
                    'mapped' => true,
                    'help' => $this->module->l('Inserisci qui il CUP del soggetto (opzionale).'),
                    'data' => $model->cup,
                ]
            )
            ->add('id_customer_invoice_job_area',
                ChoiceType::class,
                [
                    'label' => $this->module->l('Settore'),
                    'required' => false,
                    'choices' => $this->reverseKeyArray(ModelCustomerInvoiceJobArea::getList()),
                    'attr' => [
                        'class' => 'mp-job-area-select',
                    ],
                ])
            ->add('id_customer_invoice_job_position', ChoiceType::class, [
                'label' => $this->module->l('Professione'),
                'required' => false,
                'choices' => $this->reverseKeyArray(ModelCustomerInvoiceJobPosition::getList()),
                'attr' => [
                    'class' => 'mp-job-name-select',
                ],
            ]);

        $params['form_builder'] = $builder;
    }

    public function hookActionCustomerGridDataModifier(array $params)
    {
        $data = $params['data'];
        foreach ($data as $key => $value) {
            $data[$key]['id_eurosolution'] = $value['id_eurosolution'];
        }
        $params['data'] = $data;

        return $params;
    }

    /**
     * Modifica la definizione della griglia dei clienti
     *
     * @param array $params
     * @return void
     */
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
                    ->setName($this->module->l('Soggetto'))
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
                        'placeholder' => $this->module->l('Soggetto'),
                    ],
                    'choices' => [
                        'ENTE' => 'ENTE',
                        'PARTITA IVA' => 'PARTITA_IVA',
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
                    ->setName($this->module->l('Partita IVA'))
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
                        'placeholder' => $this->module->l('Partita IVA'),
                    ],
                ])
                ->setAssociatedColumn('vat_number')
        );

        // Aggiungo la colonna CODICE FISCALE
        $definition
            ->getColumns()
            ->addAfter(
                'vat_number',
                (new DataColumn('dni'))
                    ->setName($this->module->l('Codice Fiscale'))
                    ->setOptions([
                        'field' => 'dni',
                        'sortable' => true,
                        'clickable' => false,
                        'alignment' => 'left',
                    ])
            );

        // Aggiungo il filtro
        $definition->getFilters()->add(
            (new Filter('dni', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->module->l('Codice Fiscale'),
                    ],
                ])
                ->setAssociatedColumn('dni')
        );

        // Aggiungo la colonna SDI
        $definition
            ->getColumns()
            ->addAfter(
                'dni',
                (new DataColumn('sdi'))
                    ->setName($this->module->l('SDI'))
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
                        'placeholder' => $this->module->l('SDI'),
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
                    ->setName($this->module->l('PEC'))
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
                        'placeholder' => $this->module->l('PEC'),
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
                    ->setName($this->module->l('CIG'))
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
                        'placeholder' => $this->module->l('CIG'),
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
                    ->setName($this->module->l('CUP'))
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
                        'placeholder' => $this->module->l('CUP'),
                    ],
                ])
                ->setAssociatedColumn('cup')
        );
    }

    /**
     * Applica i filtri e le query per la tabella dei clienti
     * @param mixed $params
     * @return void
     */
    public function hookActionCustomerGridQueryBuilderModifier($params)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $params['search_query_builder'] ?? null;
        /** @var QueryBuilder $countQueryBuilder */
        $countQueryBuilder = $params['count_query_builder'] ?? null;
        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'] ?? null;

        if (!$queryBuilder || !$searchCriteria) {
            return;
        }

        // creo la query di selezione
        $queryBuilder
            ->addSelect('cinv.type')
            ->addSelect("COALESCE(cinv.vat_number, '--') as vat_number")
            ->addSelect("COALESCE(cinv.dni, '--') as dni")
            ->addSelect("COALESCE(cinv.cuu, '--') as cuu")
            ->addSelect("COALESCE(cinv.sdi, '--') as sdi")
            ->addSelect("COALESCE(cinv.pec, '--') as pec")
            ->addSelect("COALESCE(cinv.cig, '--') as cig")
            ->addSelect("COALESCE(cinv.cup, '--') as cup");
        $queryBuilder
            ->leftJoin('c', _DB_PREFIX_ . 'customer_invoice', 'cinv', 'c.id_customer = cinv.id_customer');

        // Modifico anche la query di conteggio per ottenere il totale corretto
        if ($countQueryBuilder) {
            $countQueryBuilder->leftJoin('c', _DB_PREFIX_ . 'customer_invoice', 'cinv', 'c.id_customer = cinv.id_customer');
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($filterName == 'type') {
                $queryBuilder->andWhere('cinv.type = :type');
                $queryBuilder->setParameter('type', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.type = :type');
                    $countQueryBuilder->setParameter('type', $filterValue);
                }
                break;
            }
            if ($filterName == 'vat_number') {
                $queryBuilder->andWhere('cinv.vat_number = :vat_number');
                $queryBuilder->setParameter('vat_number', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.vat_number = :vat_number');
                    $countQueryBuilder->setParameter('vat_number', $filterValue);
                }
                break;
            }
            if ($filterName == 'dni') {
                $queryBuilder->andWhere('cinv.dni = :dni');
                $queryBuilder->setParameter('dni', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.dni = :dni');
                    $countQueryBuilder->setParameter('dni', $filterValue);
                }
                break;
            }

            if ($filterName == 'cuu') {
                $queryBuilder->andWhere('cinv.cuu = :cuu');
                $queryBuilder->setParameter('cuu', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.cuu = :cuu');
                    $countQueryBuilder->setParameter('cuu', $filterValue);
                }
                break;
            }
            if ($filterName == 'sdi') {
                $queryBuilder->andWhere('cinv.sdi = :sdi');
                $queryBuilder->setParameter('sdi', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.sdi = :sdi');
                    $countQueryBuilder->setParameter('sdi', $filterValue);
                }
                break;
            }
            if ($filterName == 'pec') {
                $queryBuilder->andWhere('cinv.pec = :pec');
                $queryBuilder->setParameter('pec', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.pec = :pec');
                    $countQueryBuilder->setParameter('pec', $filterValue);
                }
                break;
            }
            if ($filterName == 'cig') {
                $queryBuilder->andWhere('cinv.cig = :cig');
                $queryBuilder->setParameter('cig', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.cig = :cig');
                    $countQueryBuilder->setParameter('cig', $filterValue);
                }
                break;
            }
            if ($filterName == 'cup') {
                $queryBuilder->andWhere('cinv.cup = :cup');
                $queryBuilder->setParameter('cup', $filterValue);

                if ($countQueryBuilder) {
                    $countQueryBuilder->andWhere('cinv.cup = :cup');
                    $countQueryBuilder->setParameter('cup', $filterValue);
                }
                break;
            }
        }
    }

    protected function getFrontCustomerFields($customer = null)
    {
        if ($customer) {
            $fields = [
                'type' => $customer->type,
                'vat_number' => $customer->vat_number,
                'dni' => $customer->dni,
                'cuu' => $customer->cuu,
                'sdi' => $customer->sdi,
                'pec' => $customer->pec,
                'cig' => $customer->cig,
                'cup' => $customer->cup,
                'id_customer_invoice_job_area' => $customer->id_customer_invoice_job_area,
                'id_customer_invoice_job_position' => $customer->id_customer_invoice_job_position,
            ];
        } else {
            $fields = [
                'type' => Tools::getValue('type', ''),
                'vat_number' => Tools::getValue('vat_number', ''),
                'dni' => Tools::getValue('dni', ''),
                'cuu' => Tools::getValue('cuu', ''),
                'sdi' => Tools::getValue('sdi', ''),
                'pec' => Tools::getValue('pec', ''),
                'cig' => Tools::getValue('cig', ''),
                'cup' => Tools::getValue('cup', ''),
                'id_customer_invoice_job_area' => Tools::getValue('id_customer_invoice_job_area', 0),
                'id_customer_invoice_job_position' => Tools::getValue('id_customer_invoice_job_position', 0),
            ];
        }

        return $fields;
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
        $existsQuery->select('id_customer');  // Seleziona la chiave primaria o un campo qualsiasi
        $existsQuery->from('customer_invoice');
        $existsQuery->where('id_customer = ' . $id_customer);

        try {
            $exists = Db::getInstance()->getValue($existsQuery);

            if ($exists) {
                // Esiste: Aggiorna la riga esistente
                // La condizione WHERE è fondamentale!
                $result = Db::getInstance()->update('customer_invoice', $updateData, 'id_customer = ' . $id_customer, 1);  // '1' limita a 1 riga
            } else {
                // Non esiste: Inserisci una nuova riga
                // Assicurati che $data contenga 'id_customer'
                $result = Db::getInstance()->insert('customer_invoice', $data, false, true, \DbCore::INSERT_IGNORE);  // INSERT_IGNORE può essere utile se c'è una gara (race condition)
            }
            // $result contiene true/false per l'operazione DB o numero righe per update? Controlla documentazione Db::update/insert
            // Qui assumiamo che se non ci sono eccezioni, l'operazione logica è andata a buon fine
            // Potresti voler controllare $result più in dettaglio se necessario.

            return $result;  // Operazione tentata
        } catch (PrestaShopDatabaseException $e) {
            PrestaShopLogger::addLog('[mpcustomerinvoice] Errore salvataggio dati fattura cliente ID ' . $id_customer . ': ' . $e->getMessage(), 3, null, 'mpcustomerinvoice');

            return false;  // Errore DB
        }
    }

    public function hookActionAdminCustomersFormSubmit($params)
    {
        return $this->saveOrUpdateCustomerInvoiceData($params['object']->id, $this->getFrontCustomerFields($params['object']));
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];
        $columns = $definition->getColumns();

        $columnIdEurosolution = new HtmlColumn('id_eurosolution');
        $columnIdEurosolution
            ->setName($this->module->l('Eurosolution'))
            ->setOptions([
                'field' => 'id_eurosolution',
                'sortable' => false,
                'clickable' => false,
                'alignment' => 'center',
                'attr' => [
                    'font_size' => '1.5rem',
                    'class' => 'pointer',
                ],
            ]);

        $columns->addAfter('id_order', $columnIdEurosolution);

        // Add a new text filter
        $definition->getFilters()->add(
            (new Filter('id_eurosolution', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('id_eurosolution')
        );
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $params['search_query_builder'];
        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        // Aggiungi id_eurosolution alla query
        $queryBuilder->addSelect('ci.id_eurosolution');
        $queryBuilder->leftJoin('c', _DB_PREFIX_ . 'customer_invoice', 'ci', 'a.id_customer = ci.id_customer');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($filterName == 'id_eurosolution') {
                $queryBuilder->andWhere('ci.id_eurosolution = :id_eurosolution');
                $queryBuilder->setParameter('id_eurosolution', $filterValue);

                // Modifica anche la query di conteggio
                $countQueryBuilder = $params['count_query_builder'] ?? null;
                // Applica lo stesso filtro alla query di conteggio
                if ($countQueryBuilder !== null) {
                    $countQueryBuilder->leftJoin('c', _DB_PREFIX_ . 'customer_invoice', 'ci', 'a.id_customer = ci.id_customer');
                    $countQueryBuilder->andWhere('ci.id_eurosolution = :id_eurosolution');
                    $countQueryBuilder->setParameter('id_eurosolution', $filterValue);
                }
            }
        }

        // Filtro per id_eurosolution
        if (isset($params['filter']['id_eurosolution'])) {
            $queryBuilder
                ->andWhere('ci.id_eurosolution = :id_eurosolution')
                ->setParameter('id_eurosolution', $params['filter']['id_eurosolution']);

            // Modifica anche la query di conteggio
            $countQueryBuilder = $params['count_query_builder'] ?? null;
            // Applica lo stesso filtro alla query di conteggio
            if ($countQueryBuilder !== null) {
                $countQueryBuilder->andWhere('eur.id_eurosolution = :id_eurosolution');
                $countQueryBuilder->setParameter('id_eurosolution', $params['filter']['id_eurosolution']);
            }
        }
    }

    public function hookActionOrderGridDataModifier($params)
    {
        /** @var GridData $data */
        $data = $params['data'];
        $records = $data->getRecords()->all();

        $modifiedRecords = [];
        foreach ($records as $record) {
            /** @var array $record */
            $id_eurosolution = $record['id_eurosolution'];

            $tpl = '@ModuleTwig/admin/columnIdEurosolution.html.twig';
            $twig = (new GetTwigEnvironment($this->module->name))->load($tpl);
            $output = $twig->render([
                'id_eurosolution' => $id_eurosolution,
            ]);

            $record['id_eurosolution'] = $output;
            $modifiedRecords[] = $record;
        }

        // Ricrea la collection con i dati modificati
        $recordCollection = new RecordCollection($modifiedRecords);
        $data = new GridData(
            $recordCollection,
            $data->getRecordsTotal(),
            $data->getQuery()
        );

        // Assegna i dati modificati al parametro (passato per riferimento)
        $params['data'] = $data;
    }

    private static function render($path, $params = [])
    {
        $twig = new GetTwigEnvironment('mpcustomerinvoice');
        $twig->load($path);

        return $twig->render($params);
    }
}
