<?php

use MpSoft\MpCustomerInvoice\Helpers\GetTwigEnvironment;
use MpSoft\MpCustomerInvoice\Helpers\ImportFromV16;
use MpSoft\MpCustomerInvoice\Helpers\MpConnector;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobArea;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobPosition;

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
class AdminMpCustomerInvoiceController extends ModuleAdminController
{
    protected $id_lang;
    protected $id_shop;
    protected $id_employee;
    protected $ajaxController;
    protected $flash_message;
    protected $flash_type;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->className = '';
        parent::__construct();
        $this->id_lang = (int) $this->context->language->id;
        $this->id_shop = (int) $this->context->shop->id;
        $this->id_employee = (int) $this->context->employee->id;
        $this->ajaxController = Context::getContext()->link->getAdminLink('AdminMpCustomerInvoice');

        if (Tools::isSubmit('ajax') && Tools::isSubmit('action')) {
            $action = 'ajaxProcess' . ucfirst(Tools::getValue('action'));
            if (method_exists($this, $action)) {
                $this->response($this->$action());
            }
        }
    }

    protected function response($params, $code = 200)
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        exit(json_encode($params));
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->module->l('Customer Invoices');
        $this->page_header_toolbar_btn['setup'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showSetupPage']),
            'desc' => $this->module->l('Configurazione'),
            'icon' => 'icon-cogs',
            'class' => 'btn-setup',
        ];
        $this->page_header_toolbar_btn['customers'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showCustomersPage']),
            'desc' => $this->module->l('Clienti'),
            'icon' => 'icon-user',
            'class' => 'btn-customers',
        ];
        $this->page_header_toolbar_btn['orders'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showOrdersPage']),
            'desc' => $this->module->l('Ordini'),
            'icon' => 'icon-shopping-cart',
            'class' => 'btn-orders',
        ];
        $this->page_header_toolbar_btn['jobs'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showJobsPage']),
            'desc' => $this->module->l('Professioni'),
            'icon' => 'icon-list',
            'class' => 'btn-jobs',
        ];
        $this->page_header_toolbar_btn['import'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showImportPage']),
            'desc' => $this->module->l('Importa'),
            'icon' => 'icon-download',
            'class' => 'btn-import',
        ];
    }

    public function initContent()
    {
        switch (Tools::getValue('action')) {
            case 'showSetupPage':
                $this->content = $this->renderSetupPage();
                break;
            case 'showCustomersPage':
                $this->content = $this->renderCustomersPage();
                break;
            case 'showOrdersPage':
                $this->content = $this->renderOrdersPage();
                break;
            case 'showJobsPage':
                $this->content = $this->renderJobsPage();
                break;
            case 'showCustomPdfPage':
                $this->content = $this->renderCustomPdfPage();
                break;
            case 'showCustomExportPage':
                $idOrder = (int) Tools::getValue('id_order');
                $documentType = Tools::getValue('document_type');
                try {
                    \MpSoft\MpCustomerInvoice\Export\ExportManager::run($idOrder, $documentType);
                    exit;
                } catch (\Exception $e) {
                    $this->errors[] = $e->getMessage();
                }
                break;
            case 'showImportPage':
                $this->content = $this->renderImportPage();
                break;
            default:
                $this->content = $this->renderCustomersPage();
                break;
        }
        parent::initContent();
    }

    public function renderCustomPdfPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/custom_pdf_test.html.twig');

        return $template->render([
            'adminControllerUrl' => $this->ajaxController,
            'id_order' => (int) Tools::getValue('id_order'),
            'document_type' => Tools::getValue('document_type'),
        ]);
    }

    public function renderCustomExportPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/custom_export_test.html.twig');

        return $template->render([
            'adminControllerUrl' => $this->ajaxController,
            'id_order' => (int) Tools::getValue('id_order'),
            'document_type' => Tools::getValue('document_type'),
        ]);
    }

    public function renderSetupPage()
    {
        if (Tools::isSubmit('submitConfiguration')) {
            $this->saveSetupConfiguration();
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], [
                'action' => 'showSetupPage',
                'configured' => 1,
            ]));
        }

        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/configuration.html.twig');

        return $template->render([
            'message' => Tools::getValue('configured') ? [
                'type' => 'success',
                'content' => $this->module->l('Configuration updated'),
            ] : [],
            'PAYMENT_MODULES' => PaymentModule::getPaymentModules(),
            'CUSTOMER_PREFIX' => $this->getSetupConfig('CUSTOMER_PREFIX', ''),
            'TYPE_ORDER' => $this->getSetupConfig('TYPE_ORDER', 0),
            'TYPE_INVOICE' => $this->getSetupConfig('TYPE_INVOICE', 0),
            'TYPE_RETURN' => $this->getSetupConfig('TYPE_RETURN', 0),
            'TYPE_SLIP' => $this->getSetupConfig('TYPE_SLIP', 0),
            'TYPE_DELIVERY' => $this->getSetupConfig('TYPE_DELIVERY', 0),
            'PAYMENT_SELECTED' => $this->getSetupConfig('PAYMENT_SELECTED', []),
            'EXPORT_FILE_NAME' => $this->getSetupConfig('EXPORT_FILE_NAME', 'export'),
            'REFERENCE_RENUMBER' => $this->getSetupConfig('REFERENCE_RENUMBER', '{$year}[{$id_order}|6]'),
            'MPCUSTOMERINVOICE_VAT_RATE' => $this->getSetupConfig('MPCUSTOMERINVOICE_VAT_RATE', 22.0),
            'adminControllerUrl' => $this->ajaxController,
            'orderReferenceLengths' => $this->getOrderReferenceLengths(),
        ]);
    }

    public function ajaxProcessUpdateOrderReferenceLengths()
    {
        $db = Db::getInstance();
        $columns = [
            'orders' => 'reference',
            'order_payment' => 'order_reference',
        ];

        foreach ($columns as $table => $column) {
            $sql = sprintf(
                'ALTER TABLE `%s` MODIFY `%s` VARCHAR(32)',
                _DB_PREFIX_ . $table,
                $column
            );
            if (!$db->execute($sql)) {
                return [
                    'success' => false,
                    'error' => $db->getMsgError(),
                    'lengths' => $this->getOrderReferenceLengths(),
                ];
            }
        }

        return [
            'success' => true,
            'lengths' => $this->getOrderReferenceLengths(),
        ];
    }

    public function ajaxProcessUpdateIdEurosolution()
    {
        $id_customer = (int) Tools::getValue('id_customer');
        $id_eurosolution = (string) Tools::getValue('id_eurosolution');

        $table = _DB_PREFIX_ . ModelCustomerInvoice::$definition['table'];
        $query = "
            UPDATE
                {$table}
            SET 
                id_eurosolution = {$id_eurosolution}
            WHERE
                id_customer = {$id_customer}
        ";

        try {
            $result = Db::getInstance()->execute($query);
            $this->response(['success' => $result]);
        } catch (Exception $e) {
            $this->response(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajaxProcessPrintOrder()
    {
        $id_order = (int) Tools::getValue('id_order', 0);
        $document = (string) Tools::getValue('document', 'order');

        $this->response([
            'success' => true,
            'id_order' => $id_order,
            'document' => $document,
            'message' => 'Stampa simulata: ' . $document . ' per ordine #' . $id_order,
        ]);
    }

    public function ajaxProcessExportOrder()
    {
        $id_order = (int) Tools::getValue('id_order', 0);
        $document = (string) Tools::getValue('document', 'order');

        $this->response([
            'success' => true,
            'id_order' => $id_order,
            'document' => $document,
            'message' => 'Esportazione simulata: ' . $document . ' per ordine #' . $id_order,
        ]);
    }

    public function ajaxProcessPrintLabel()
    {
        $id_order = (int) Tools::getValue('id_order', 0);
        $labelType = (string) Tools::getValue('labelType', 'address');
        $copies = (int) Tools::getValue('copies', 1);

        $this->response([
            'success' => true,
            'id_order' => $id_order,
            'labelType' => $labelType,
            'copies' => $copies,
            'message' => 'Etichetta simulata: ' . $labelType . ' x' . $copies . ' per ordine #' . $id_order,
        ]);
    }

    private function getOrderReferenceLengths(): array
    {
        return [
            'orders' => $this->getVarcharColumnLength('orders', 'reference'),
            'order_payment' => $this->getVarcharColumnLength('order_payment', 'order_reference'),
        ];
    }

    private function getVarcharColumnLength(string $table, string $column): ?int
    {
        $result = Db::getInstance()->executeS(sprintf(
            "SHOW COLUMNS FROM `%s` LIKE '%s'",
            _DB_PREFIX_ . $table,
            pSQL($column)
        ));
        $type = $result[0]['Type'] ?? '';

        return preg_match('/^varchar\\((\\d+)\\)$/i', $type, $matches) ? (int) $matches[1] : null;
    }

    private function saveSetupConfiguration(): void
    {
        $values = [
            'PAYMENT_SELECTED' => Tools::getValue('PAYMENT_SELECTED'),
            'CUSTOMER_PREFIX' => Tools::getValue('CUSTOMER_PREFIX'),
            'TYPE_ORDER' => Tools::getValue('TYPE_ORDER'),
            'TYPE_DELIVERY' => Tools::getValue('TYPE_DELIVERY'),
            'TYPE_INVOICE' => Tools::getValue('TYPE_INVOICE'),
            'TYPE_RETURN' => Tools::getValue('TYPE_RETURN'),
            'TYPE_SLIP' => Tools::getValue('TYPE_SLIP'),
            'EXPORT_FILE_NAME' => Tools::getValue('EXPORT_FILE_NAME'),
            'REFERENCE_RENUMBER' => Tools::getValue('REFERENCE_RENUMBER'),
            'MPCUSTOMERINVOICE_VAT_RATE' => Tools::getValue('MPCUSTOMERINVOICE_VAT_RATE'),
        ];
        $defaults = [
            'PAYMENT_SELECTED' => [],
            'CUSTOMER_PREFIX' => '',
            'TYPE_ORDER' => 0,
            'TYPE_DELIVERY' => 0,
            'TYPE_INVOICE' => 0,
            'TYPE_RETURN' => 0,
            'TYPE_SLIP' => 0,
            'EXPORT_FILE_NAME' => 'export',
            'REFERENCE_RENUMBER' => '{$year}[{$id_order}|6]',
            'MPCUSTOMERINVOICE_VAT_RATE' => 22.0,
        ];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            Configuration::updateValue($key, $value ?: $defaults[$key]);
        }
    }

    private function getSetupConfig(string $key, $default = null)
    {
        $value = Configuration::get($key);
        try {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
        }

        return $value ?: $default;
    }

    public function renderCustomersPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/customers.html.twig');
        return $template->render([
            'flash_message' => $this->flash_message,
            'flash_type' => $this->flash_type,
            'adminControllerUrl' => $this->ajaxController,
            'jobAreas' => json_encode(ModelCustomerInvoiceJobArea::getList()),
            'jobPositions' => json_encode(ModelCustomerInvoiceJobPosition::getList()),
            'customerPageLink' => $this->context->link->getAdminLink(
                'AdminCustomers',
                true,
                [],
                ['id_customer' => '999999999', 'viewcustomer' => true],
            ),
        ]);
    }

    public function renderOrdersPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/orders.html.twig');
        $orderStates = [];
        $cancelledStateIds = [];

        foreach (OrderState::getOrderStates($this->id_lang) as $orderState) {
            $idOrderState = (int) $orderState['id_order_state'];
            $orderStates[$idOrderState] = $orderState['name'];

            if ($idOrderState === 6 || preg_match('/annull|cancel/i', Tools::strtolower($orderState['name']))) {
                $cancelledStateIds[] = $idOrderState;
            }
        }

        $orderCountries = [];
        foreach (Country::getCountries($this->id_lang, false) as $country) {
            $orderCountries[(int) $country['id_country']] = $country['name'];
        }


        return $template->render([
            'adminControllerUrl' => $this->ajaxController,
            'orderStates' => $orderStates,
            'orderCountries' => $orderCountries,
            'cancelledStateIds' => array_values(array_unique($cancelledStateIds)),
            'orderFlagItems' => $this->getOrderFlagItems(),
            'orderPageLink' => $this->context->link->getAdminLink(
                'AdminOrders',
                true,
                [],
                ['id_order' => '999999999', 'vieworder' => true]
            ),
            'invoicePdfLink' => $this->context->link->getAdminLink(
                'AdminPdf',
                true,
                ['route' => 'admin_orders_generate_invoice_pdf', 'orderId' => '999999999']
            ),
            'deliveryPdfLink' => $this->context->link->getAdminLink(
                'AdminPdf',
                true,
                ['route' => 'admin_orders_generate_delivery_slip_pdf', 'orderId' => '999999999']
            ),
            'labelPrintEndpoint' => $this->context->link->getModuleLink('mplabelprint', 'Fetch', ['ajax' => 1]),
        ]);
    }

    public function renderJobsPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/jobs.html.twig');
        return $template->render([
            'flash_message' => $this->flash_message,
            'flash_type' => $this->flash_type,
            'adminControllerUrl' => $this->ajaxController,
        ]);
    }

    public function renderImportPage()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $template = $twig->load('@ModuleTwig/admin/import.html.twig');
        return $template->render([
            'flash_message' => $this->flash_message,
            'flash_type' => $this->flash_type,
            'adminControllerUrl' => $this->ajaxController,
            'connectorUrl' => Configuration::get(MpConnector::CONFIG_URL),
            'connectorToken' => Configuration::get(MpConnector::CONFIG_TOKEN),
        ]);
    }

    public function ajaxProcessSaveConnectorConfig()
    {
        $url = trim(Tools::getValue('connector_url', ''));
        $token = trim(Tools::getValue('connector_token', ''));

        if (!$url || !$token) {
            return ['success' => false, 'error' => 'URL e token sono obbligatori.'];
        }

        $saved = MpConnector::saveConfig($url, $token);

        return ['success' => $saved, 'error' => $saved ? '' : 'Errore nel salvataggio.'];
    }

    public function ajaxProcessTestConnector()
    {
        $connector = MpConnector::fromConfig();
        $result = $connector->test();

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? '',
        ];
    }

    public function ajaxProcessDescribeTable()
    {
        $table = Tools::getValue('table', 'ps_customer');
        $connector = MpConnector::fromConfig();
        $result = $connector->query('SHOW COLUMNS FROM `' . pSQL($table) . '`');

        return $result;
    }

    public function ajaxProcessImportCustomerDataInit()
    {
        $connector = MpConnector::fromConfig();

        // Query 1: dati principali da ps_customer
        $sqlCustomers = 'SELECT id_customer, uid, pec, cig, cup, id_eur AS id_eurosolution,'
            . ' id_job_area AS id_customer_invoice_job_area, id_job_name AS id_customer_invoice_job_position'
            . ' FROM ps_customer ORDER BY id_customer';
        $resCustomers = $connector->query($sqlCustomers);
        if (!$resCustomers['success']) {
            return ['success' => false, 'error' => 'Errore ps_customer: ' . $resCustomers['error']];
        }

        // Query 2: dati fiscali da ps_address (type=invoice)
        $typeMap = [1 => 'ENTE', 2 => 'PARTITA_IVA', 3 => 'PRIVATO'];
        $sqlAddresses = 'SELECT id_customer, subject AS type_id, vat_number, dni'
            . ' FROM ps_address WHERE type = \'invoice\' ORDER BY id_customer';
        $resAddresses = $connector->query($sqlAddresses);
        if (!$resAddresses['success']) {
            return ['success' => false, 'error' => 'Errore ps_address: ' . $resAddresses['error']];
        }

        // Indicizza gli indirizzi per id_customer e mappa il tipo
        $addressIndex = [];
        foreach ($resAddresses['data'] as $addr) {
            $typeId = (int) $addr['type_id'];
            $addr['type'] = $typeMap[$typeId] ?? 'PRIVATO';
            unset($addr['type_id']);
            $addressIndex[(int) $addr['id_customer']] = $addr;
        }

        // Merge: per ogni cliente unisci i dati address
        $merged = [];
        foreach ($resCustomers['data'] as $row) {
            $idCustomer = (int) $row['id_customer'];
            $addr = $addressIndex[$idCustomer] ?? [];
            $merged[] = array_merge($row, [
                'type' => $addr['type'] ?? '',
                'vat_number' => $addr['vat_number'] ?? '',
                'dni' => $addr['dni'] ?? '',
            ]);
        }

        $hash = md5(uniqid('mpcust_', true));
        $tmpFile = sys_get_temp_dir() . '/mpcust_import_' . $hash . '.json';
        file_put_contents($tmpFile, json_encode(['rows' => $merged, 'done' => 0, 'total' => count($merged)]));

        return ['success' => true, 'total' => count($merged), 'done' => 0, 'hash' => $hash];
    }

    public function ajaxProcessImportCustomerDataChunk()
    {
        $chunkSize = 200;
        $hash = Tools::getValue('hash');

        if (!$hash || !preg_match('/^[a-f0-9]{32}$/', $hash)) {
            return ['success' => false, 'error' => 'Hash non valido. Riavvia l\'importazione.'];
        }
        $tmpFile = sys_get_temp_dir() . '/mpcust_import_' . $hash . '.json';
        if (!file_exists($tmpFile)) {
            return ['success' => false, 'error' => 'Sessione scaduta. Riavvia l\'importazione.'];
        }

        $payload = json_decode(file_get_contents($tmpFile), true);
        $rows = $payload['rows'];
        $done = (int) $payload['done'];
        $total = (int) $payload['total'];

        $chunk = array_slice($rows, $done, $chunkSize);
        $now = date('Y-m-d H:i:s');
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'customer_invoice';
        $errors = [];

        if (!empty($chunk)) {
            $parts = [];
            foreach ($chunk as $row) {
                $idCustomer = (int) $row['id_customer'];
                $idEuro = (int) ($row['id_eurosolution'] ?? 0);
                $type = pSQL($row['type'] ?? '');
                $vatNumber = pSQL($row['vat_number'] ?? '');
                $dni = pSQL($row['dni'] ?? '');
                $pec = pSQL($row['pec'] ?? '');
                $cig = pSQL($row['cig'] ?? '');
                $cup = pSQL($row['cup'] ?? '');
                $uid = pSQL($row['uid'] ?? '');
                $idJobArea = (int) ($row['id_customer_invoice_job_area'] ?? 0);
                $idJobPos = (int) ($row['id_customer_invoice_job_position'] ?? 0);

                $parts[] = '(' . $idCustomer . ', ' . $idEuro . ', \'' . $type . '\','
                    . ' \'' . $vatNumber . '\', \'' . $dni . '\', \'' . $pec . '\','
                    . ' \'' . $cig . '\', \'' . $cup . '\','
                    . ' ' . $idJobArea . ', ' . $idJobPos . ','
                    . ' \'' . $now . '\', \'' . $now . '\')';
            }

            $sql = 'INSERT INTO `' . $table . '`'
                . ' (`id_customer`, `id_eurosolution`, `type`,'
                . ' `vat_number`, `dni`, `pec`,'
                . ' `cig`, `cup`,'
                . ' `id_customer_invoice_job_area`, `id_customer_invoice_job_position`,'
                . ' `date_add`, `date_upd`)'
                . ' VALUES ' . implode(', ', $parts)
                . ' ON DUPLICATE KEY UPDATE'
                . ' `id_eurosolution` = VALUES(`id_eurosolution`),'
                . ' `type` = VALUES(`type`),'
                . ' `vat_number` = VALUES(`vat_number`),'
                . ' `dni` = VALUES(`dni`),'
                . ' `pec` = VALUES(`pec`),'
                . ' `cig` = VALUES(`cig`),'
                . ' `cup` = VALUES(`cup`),'
                . ' `id_customer_invoice_job_area` = VALUES(`id_customer_invoice_job_area`),'
                . ' `id_customer_invoice_job_position` = VALUES(`id_customer_invoice_job_position`),'
                . ' `date_upd` = VALUES(`date_upd`)';

            if (!$db->execute($sql)) {
                $errors[] = $db->getMsgError();
            }
        }

        $done += count($chunk);
        $finished = ($done >= $total);

        if ($finished) {
            @unlink($tmpFile);
        } else {
            $payload['done'] = $done;
            file_put_contents($tmpFile, json_encode($payload));
        }

        return [
            'success' => empty($errors),
            'done' => $done,
            'total' => $total,
            'finished' => $finished,
            'errors' => $errors,
        ];
    }

    public function ajaxProcessImportProfessionsInit()
    {
        $connector = MpConnector::fromConfig();

        // ps_job_area → customer_invoice_job_area + _lang
        $resArea = $connector->query('SELECT id_job_area, id_lang, name FROM ps_job_area ORDER BY id_job_area');
        if (!$resArea['success']) {
            return ['success' => false, 'error' => 'Errore ps_job_area: ' . $resArea['error']];
        }

        // ps_job_name → customer_invoice_job_position + _lang
        $resName = $connector->query('SELECT id_job_name, id_lang, name FROM ps_job_name ORDER BY id_job_name');
        if (!$resName['success']) {
            return ['success' => false, 'error' => 'Errore ps_job_name: ' . $resName['error']];
        }

        // ps_job_link → customer_invoice_job_link
        $resLink = $connector->query('SELECT id_job_area, id_job_name FROM ps_job_link ORDER BY id_job_area, id_job_name');
        if (!$resLink['success']) {
            return ['success' => false, 'error' => 'Errore ps_job_link: ' . $resLink['error']];
        }

        $total = count($resArea['data']) + count($resName['data']) + count($resLink['data']);

        $hash = md5(uniqid('mpcprof_', true));
        $tmpFile = sys_get_temp_dir() . '/mpcprof_import_' . $hash . '.json';
        file_put_contents($tmpFile, json_encode([
            'area' => $resArea['data'],
            'name' => $resName['data'],
            'link' => $resLink['data'],
            'phase' => 'area',
            'done' => 0,
            'total' => $total,
        ]));

        return ['success' => true, 'total' => $total, 'done' => 0, 'hash' => $hash];
    }

    public function ajaxProcessImportProfessionsChunk()
    {
        $chunkSize = 200;
        $hash = Tools::getValue('hash');

        if (!$hash || !preg_match('/^[a-f0-9]{32}$/', $hash)) {
            return ['success' => false, 'error' => 'Hash non valido. Riavvia l\'importazione.'];
        }
        $tmpFile = sys_get_temp_dir() . '/mpcprof_import_' . $hash . '.json';
        if (!file_exists($tmpFile)) {
            return ['success' => false, 'error' => 'Sessione scaduta. Riavvia l\'importazione.'];
        }

        $payload = json_decode(file_get_contents($tmpFile), true);
        $phase = $payload['phase'];
        $done = (int) $payload['done'];
        $total = (int) $payload['total'];
        $now = date('Y-m-d H:i:s');
        $db = Db::getInstance();
        $errors = [];

        if ($phase === 'area') {
            $rows = $payload['area'];
            $chunk = array_slice($rows, 0, $chunkSize);
            $payload['area'] = array_slice($rows, count($chunk)); // consume processed

            // Collect unique ids for main table
            $uniqueIds = array_unique(array_column($chunk, 'id_job_area'));
            if (!empty($uniqueIds)) {
                $mainParts = [];
                foreach ($uniqueIds as $id) {
                    $mainParts[] = '(' . (int) $id . ', \'' . $now . '\', \'' . $now . '\')';
                }
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customer_invoice_job_area`'
                    . ' (`id_customer_invoice_job_area`, `date_add`, `date_upd`)'
                    . ' VALUES ' . implode(', ', $mainParts)
                    . ' ON DUPLICATE KEY UPDATE `date_upd` = VALUES(`date_upd`)'
                ) ?: $errors[] = $db->getMsgError();
            }

            // Lang table
            if (!empty($chunk)) {
                $langParts = [];
                foreach ($chunk as $row) {
                    $langParts[] = '(' . (int) $row['id_job_area'] . ', ' . (int) $row['id_lang']
                        . ', \'' . pSQL($row['name']) . '\')';
                }
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customer_invoice_job_area_lang`'
                    . ' (`id_customer_invoice_job_area`, `id_lang`, `name`)'
                    . ' VALUES ' . implode(', ', $langParts)
                    . ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
                ) ?: $errors[] = $db->getMsgError();
            }

            $done += count($chunk);
            if (empty($payload['area'])) {
                $payload['phase'] = 'name';
            }
        } elseif ($phase === 'name') {
            $rows = $payload['name'];
            $chunk = array_slice($rows, 0, $chunkSize);
            $payload['name'] = array_slice($rows, count($chunk));

            $uniqueIds = array_unique(array_column($chunk, 'id_job_name'));
            if (!empty($uniqueIds)) {
                $mainParts = [];
                foreach ($uniqueIds as $id) {
                    $mainParts[] = '(' . (int) $id . ', \'' . $now . '\', \'' . $now . '\')';
                }
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customer_invoice_job_position`'
                    . ' (`id_customer_invoice_job_position`, `date_add`, `date_upd`)'
                    . ' VALUES ' . implode(', ', $mainParts)
                    . ' ON DUPLICATE KEY UPDATE `date_upd` = VALUES(`date_upd`)'
                ) ?: $errors[] = $db->getMsgError();
            }

            if (!empty($chunk)) {
                $langParts = [];
                foreach ($chunk as $row) {
                    $langParts[] = '(' . (int) $row['id_job_name'] . ', ' . (int) $row['id_lang']
                        . ', \'' . pSQL($row['name']) . '\')';
                }
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customer_invoice_job_position_lang`'
                    . ' (`id_customer_invoice_job_position`, `id_lang`, `name`)'
                    . ' VALUES ' . implode(', ', $langParts)
                    . ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
                ) ?: $errors[] = $db->getMsgError();
            }

            $done += count($chunk);
            if (empty($payload['name'])) {
                $payload['phase'] = 'link';
            }
        } elseif ($phase === 'link') {
            $rows = $payload['link'];
            $chunk = array_slice($rows, 0, $chunkSize);
            $payload['link'] = array_slice($rows, count($chunk));

            if (!empty($chunk)) {
                $parts = [];
                foreach ($chunk as $row) {
                    $parts[] = '(' . (int) $row['id_job_name'] . ', ' . (int) $row['id_job_area'] . ')';
                }
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customer_invoice_job_link`'
                    . ' (`id_customer_invoice_job_position`, `id_customer_invoice_job_area`)'
                    . ' VALUES ' . implode(', ', $parts)
                    . ' ON DUPLICATE KEY UPDATE `id_customer_invoice_job_area` = VALUES(`id_customer_invoice_job_area`)'
                ) ?: $errors[] = $db->getMsgError();
            }

            $done += count($chunk);
        }

        $finished = ($done >= $total);
        $payload['done'] = $done;

        if ($finished) {
            @unlink($tmpFile);
        } else {
            file_put_contents($tmpFile, json_encode($payload));
        }

        return [
            'success' => empty($errors),
            'done' => $done,
            'total' => $total,
            'phase' => $payload['phase'],
            'finished' => $finished,
            'errors' => $errors,
        ];
    }

    public function ajaxProcessTruncateProfessions()
    {
        $db = Db::getInstance();
        $tables = [
            'customer_invoice_job_area',
            'customer_invoice_job_area_lang',
            'customer_invoice_job_position',
            'customer_invoice_job_position_lang',
            'customer_invoice_job_link',
        ];
        $errors = [];
        foreach ($tables as $t) {
            if (!$db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . $t . '`')) {
                $errors[] = $db->getMsgError();
            }
        }

        return ['success' => empty($errors), 'errors' => $errors];
    }

    public function ajaxProcessTruncateCustomerInvoice()
    {
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'customer_invoice';
        $ok = $db->execute('TRUNCATE TABLE `' . $table . '`');

        return ['success' => $ok, 'error' => $ok ? '' : $db->getMsgError()];
    }

    public function ajaxProcessRenderCustomersData()
    {
        $offset = max(0, (int) Tools::getValue('offset', 0));
        $limit = min(100, max(10, (int) Tools::getValue('limit', 25)));
        $search = Tools::getValue('search', '');
        $sort = Tools::getValue('sort', 'id_customer');
        $order = Tools::strtoupper(Tools::getValue('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = json_decode(Tools::getValue('filter'), true) ?: [];
        $sortableFields = [
            'id_customer' => 'c.id_customer',
            'id_eurosolution' => 'ci.id_eurosolution',
            'firstname' => 'c.firstname',
            'lastname' => 'c.lastname',
            'email' => 'c.email',
            'type' => 'ci.type',
            'dni' => 'ci.dni',
            'vat_number' => 'ci.vat_number',
            'cuu' => 'ci.cuu',
            'sdi' => 'ci.sdi',
            'pec' => 'ci.pec',
            'cig' => 'ci.cig',
            'cup' => 'ci.cup',
            'date_add' => 'ci.date_add',
        ];
        $sort = $sortableFields[$sort] ?? 'c.id_customer';

        $db = Db::getInstance();
        $query = new DbQuery();

        $query
            ->select('c.id_customer')
            ->select('id_eurosolution')
            ->select('c.firstname')
            ->select('c.lastname')
            ->select('c.email')
            ->select('ci.type')
            ->select('ci.dni')
            ->select('ci.vat_number')
            ->select('ci.cuu')
            ->select('ci.sdi')
            ->select('ci.pec')
            ->select('ci.cig')
            ->select('ci.cup')
            ->select('ci.id_customer_invoice_job_area')
            ->select('ci.id_customer_invoice_job_position')
            ->select('ja.name as job_area')
            ->select('jp.name as job_position')
            ->select('ci.date_add')
            ->select('ci.date_upd')
            ->from('customer_invoice', 'ci')
            ->innerJoin('customer', 'c', 'c.id_customer = ci.id_customer')
            ->leftJoin('customer_invoice_job_area_lang', 'ja', "ja.id_customer_invoice_job_area = ci.id_customer_invoice_job_area and ja.id_lang = {$this->id_lang}")
            ->leftJoin('customer_invoice_job_position_lang', 'jp', "jp.id_customer_invoice_job_position = ci.id_customer_invoice_job_position and jp.id_lang = {$this->id_lang}")
            ->orderBy("{$sort} {$order}")
            ->limit($limit, $offset);

        $queryCount = new DbQuery();
        $queryCount
            ->select('count(ci.id_customer) as total')
            ->from('customer_invoice', 'ci')
            ->innerJoin('customer', 'c', 'c.id_customer = ci.id_customer');

        if ($filter && count($filter)) {
            if (isset($filter['id_eurosolution']) && $filter['id_eurosolution']) {
                $filter['id_eurosolution'] = (int) $filter['id_eurosolution'];
                $query->where("ci.id_eurosolution = {$filter['id_eurosolution']}");
                $queryCount->where("ci.id_eurosolution = {$filter['id_eurosolution']}");
            }

            if (isset($filter['firstname']) && $filter['firstname']) {
                $filter['firstname'] = pSQL($filter['firstname']);
                $query->where("c.firstname LIKE '%{$filter['firstname']}%'");
                $queryCount->where("c.firstname LIKE '%{$filter['firstname']}%'");
            }

            if (isset($filter['lastname']) && $filter['lastname']) {
                $filter['lastname'] = pSQL($filter['lastname']);
                $query->where("c.lastname LIKE '%{$filter['lastname']}%'");
                $queryCount->where("c.lastname LIKE '%{$filter['lastname']}%'");
            }

            if (isset($filter['email']) && $filter['email']) {
                $filter['email'] = pSQL($filter['email']);
                $query->where("c.email LIKE '%{$filter['email']}%'");
                $queryCount->where("c.email LIKE '%{$filter['email']}%'");
            }

            if (isset($filter['dni']) && $filter['dni']) {
                $filter['dni'] = pSQL($filter['dni']);
                $query->where("ci.dni LIKE '%{$filter['dni']}%'");
                $queryCount->where("ci.dni LIKE '%{$filter['dni']}%'");
            }

            if (isset($filter['vat_number']) && $filter['vat_number']) {
                $filter['vat_number'] = pSQL($filter['vat_number']);
                $query->where("ci.vat_number LIKE '%{$filter['vat_number']}%'");
                $queryCount->where("ci.vat_number LIKE '%{$filter['vat_number']}%'");
            }

            if (isset($filter['cuu']) && $filter['cuu']) {
                $filter['cuu'] = pSQL($filter['cuu']);
                $query->where("ci.cuu LIKE '%{$filter['cuu']}%'");
                $queryCount->where("ci.cuu LIKE '%{$filter['cuu']}%'");
            }

            if (isset($filter['sdi']) && $filter['sdi']) {
                $filter['sdi'] = pSQL($filter['sdi']);
                $query->where("ci.sdi LIKE '%{$filter['sdi']}%'");
                $queryCount->where("ci.sdi LIKE '%{$filter['sdi']}%'");
            }

            if (isset($filter['pec']) && $filter['pec']) {
                $filter['pec'] = pSQL($filter['pec']);
                $query->where("ci.pec LIKE '%{$filter['pec']}%'");
                $queryCount->where("ci.pec LIKE '%{$filter['pec']}%'");
            }

            if (isset($filter['cig']) && $filter['cig']) {
                $filter['cig'] = pSQL($filter['cig']);
                $query->where("ci.cig LIKE '%{$filter['cig']}%'");
                $queryCount->where("ci.cig LIKE '%{$filter['cig']}%'");
            }

            if (isset($filter['cup']) && $filter['cup']) {
                $filter['cup'] = pSQL($filter['cup']);
                $query->where("ci.cup LIKE '%{$filter['cup']}%'");
                $queryCount->where("ci.cup LIKE '%{$filter['cup']}%'");
            }

            if (isset($filter['type']) && $filter['type']) {
                $filter['type'] = pSQL($filter['type']);
                $query->where("ci.type LIKE '%{$filter['type']}%'");
                $queryCount->where("ci.type LIKE '%{$filter['type']}%'");
            }

            if (isset($filter['job_area']) && $filter['job_area']) {
                $filter['job_area'] = (int) $filter['job_area'];
                $query->where("ci.id_customer_invoice_job_area = {$filter['job_area']}");
                $queryCount->where("ci.id_customer_invoice_job_area = {$filter['job_area']}");
                $queryCount
                    ->leftJoin('customer_invoice_job_area', 'ja', 'ja.id_customer_invoice_job_area = ci.id_customer_invoice_job_area');
            }

            if (isset($filter['job_position']) && $filter['job_position']) {
                $filter['job_position'] = (int) $filter['job_position'];
                $query->where("ci.id_customer_invoice_job_position = {$filter['job_position']}");
                $queryCount->where("ci.id_customer_invoice_job_position = {$filter['job_position']}");
                $queryCount
                    ->leftJoin('customer_invoice_job_position', 'jp', 'jp.id_customer_invoice_job_position = ci.id_customer_invoice_job_position');
            }
        }

        $queryCount = $queryCount->build();
        $query = $query->build();

        $total = $db->getValue($queryCount);
        $result = $db->executeS($query);

        if (!$result) {
            $result = [];
        }

        return [
            'rows' => $result,
            'total' => $total,
            'totalNotFiltered' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    public function ajaxProcessRenderOrdersData()
    {
        $offset = max(0, (int) Tools::getValue('offset', 0));
        $limit = min(100, max(10, (int) Tools::getValue('limit', 25)));
        $filter = json_decode(Tools::getValue('filter', '{}'), true) ?: [];
        $excludedStates = array_values(array_unique(array_filter(array_map('intval', (array) json_decode(Tools::getValue('excludedStates', '[]'), true)))));
        $sort = Tools::getValue('sort', 'o.id_order');
        $order = Tools::strtoupper(Tools::getValue('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sortableFields = [
            'id_order' => 'o.id_order',
            'reference' => 'o.reference',
            'email' => 'c.email',
            'customer' => 'customer',
            'id_eurosolution' => 'ci.id_eurosolution',
            'total_paid_tax_incl' => 'o.total_paid_tax_incl',
            'payment' => 'o.payment',
            'status' => 'status',
            'date_add' => 'o.date_add',
        ];
        $sort = $sortableFields[$sort] ?? 'o.id_order';

        $hasOrderFlag = Module::getInstanceByName('mporderflag');
        $hasNotes = Module::getInstanceByName('mpnotes');
        $query = new DbQuery();
        $query
            ->select('o.id_order, o.reference, o.total_paid_tax_incl, o.payment, o.date_add')
            ->select('c.email')
            ->select("CONCAT(c.firstname, ' ', c.lastname) AS customer")
            ->select('ci.id_eurosolution, osl.name AS status')
            ->select('COALESCE(CONCAT("", ad.id_country), "") AS delivery_country')
            ->from('orders', 'o')
            ->leftJoin('customer', 'c', 'c.id_customer = o.id_customer')
            ->leftJoin('customer_invoice', 'ci', 'ci.id_customer = o.id_customer')
            ->leftJoin('address', 'ad', 'ad.id_address = o.id_address_delivery')
            ->leftJoin('order_state_lang', 'osl', 'osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->id_lang);

        $hasFeesModule = Module::isInstalled('mppaymentswithfees') && Module::isEnabled('mppaymentswithfees');
        if ($hasFeesModule) {
            $query
                ->select('COALESCE(pfo.fee_amount, 0) AS payment_fee_amount')
                ->leftJoin('mp_payment_fee_order', 'pfo', 'pfo.id_order = o.id_order');
        }

        if ($hasOrderFlag && $hasOrderFlag->active) {
            $query
                ->select('COALESCE(of.id_order_flag_item, 0) AS order_flag_item')
                ->leftJoin('order_flag', 'of', 'of.id_order = o.id_order');
        }

        if ($hasNotes && $hasNotes->active) {
            $query
                ->select("(SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "mpnote` mn WHERE mn.id_customer = o.id_customer AND mn.type = 'customer' AND mn.deleted = 0) AS notes_customer")
                ->select("(SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "mpnote` mn WHERE mn.id_order = o.id_order AND mn.type = 'order' AND mn.deleted = 0) AS notes_order")
                ->select("(SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "mpnote` mn WHERE mn.id_customer = o.id_customer AND mn.type = 'embroidery' AND mn.deleted = 0) AS notes_embroidery");
        }

        $query
            ->orderBy($sort . ' ' . $order)
            ->limit($limit, $offset);

        $queryCount = new DbQuery();
        $queryCount
            ->select('COUNT(o.id_order)')
            ->from('orders', 'o')
            ->leftJoin('customer', 'c', 'c.id_customer = o.id_customer')
            ->leftJoin('customer_invoice', 'ci', 'ci.id_customer = o.id_customer')
            ->leftJoin('address', 'ad', 'ad.id_address = o.id_address_delivery')
            ->leftJoin('order_state_lang', 'osl', 'osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->id_lang);

        if ($hasOrderFlag && $hasOrderFlag->active) {
            $queryCount->leftJoin('order_flag', 'of', 'of.id_order = o.id_order');
        }

        $filterFields = [
            'id_order' => 'o.id_order',
            'order_flag_item' => 'of.id_order_flag_item',
            'delivery_country' => 'ad.id_country',
            'reference' => 'o.reference',
            'email' => 'c.email',
            'customer' => "CONCAT(c.firstname, ' ', c.lastname)",
            'id_eurosolution' => 'ci.id_eurosolution',
            'total_paid_tax_incl' => 'o.total_paid_tax_incl',
            'payment' => 'o.payment',
            'status' => 'o.current_state',
            'date_add' => 'o.date_add',
        ];
        $filterConditions = [];
        foreach ($filter as $field => $value) {
            if (!isset($filterFields[$field]) || $value === '') {
                continue;
            }

            switch ($field) {
                case 'status':
                    $condition = 'o.current_state = ' . (int) $value;
                    break;
                case 'order_flag_item':
                    if (!$hasOrderFlag) {
                        continue 2;
                    }
                    $condition = 'of.id_order_flag_item = ' . (int) $value;
                    break;
                case 'delivery_country':
                    $condition = 'ad.id_country = ' . (int) $value;
                    break;
                case 'id_eurosolution':
                    $condition = 'ci.id_eurosolution = ' . (int) $value;
                    break;
                case 'total_paid_tax_incl':
                    $condition = $this->getOrderTotalFilterCondition($value);
                    if ($condition === null) {
                        continue 2;
                    }
                    break;
                case 'date_add':
                    $condition = $this->getOrderDateFilterCondition($value);
                    break;
                default:
                    $condition = $filterFields[$field] . " LIKE '%" . pSQL($value) . "%'";


            }

            $filterConditions[] = $condition;
            $query->where($condition);
            $queryCount->where($condition);
        }

        $statistics = $this->getOrderStatistics($filterConditions, $excludedStates);
        $db = Db::getInstance();
        $total = (int) $db->getValue($queryCount);
        $rows = $db->executeS($query) ?: [];

        foreach ($rows as &$row) {
            $feeAmount = (float) ($row['payment_fee_amount'] ?? 0);
            $totalPaid = (float) $row['total_paid_tax_incl'];

            if ($feeAmount > 0) {
                $row['order_base_total'] = $totalPaid;
                $row['payment_fee_amount'] = $feeAmount;
                $row['real_total'] = $totalPaid + $feeAmount;
            } else {
                $row['order_base_total'] = $totalPaid;
                $row['payment_fee_amount'] = 0;
                $row['real_total'] = $totalPaid;
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'totalNotFiltered' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'statistics' => $statistics,
        ];
    }

    private function getOrderFlagItems(): array
    {
        $path = _PS_MODULE_DIR_ . 'mporderflag/src/json/orderFagItems.json';
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && is_array($data['data'] ?? null) ? $data['data'] : [];
    }

    private function getOrderStatistics(array $filterConditions, array $excludedStates)
    {
        return [
            'archive' => $this->getOrderStatisticsValues([], $excludedStates),
            'filtered' => $this->getOrderStatisticsValues($filterConditions, $excludedStates),
        ];
    }

    private function getOrderStatisticsValues(array $filterConditions, array $excludedStates)
    {
        $query = new DbQuery();
        $query
            ->select('COUNT(o.id_order) AS order_count, COALESCE(SUM(o.total_paid_tax_incl), 0) AS total_paid_tax_incl')
            ->from('orders', 'o')
            ->leftJoin('customer', 'c', 'c.id_customer = o.id_customer')
            ->leftJoin('customer_invoice', 'ci', 'ci.id_customer = o.id_customer')
            ->leftJoin('address', 'ad', 'ad.id_address = o.id_address_delivery')
            ->leftJoin('order_state_lang', 'osl', 'osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->id_lang);

        if (Module::isInstalled('mporderflag')) {
            $query->leftJoin('order_flag', 'of', 'of.id_order = o.id_order');
        }

        if ($excludedStates) {
            $query->where('o.current_state NOT IN (' . implode(', ', $excludedStates) . ')');
        }

        foreach ($filterConditions as $condition) {
            $query->where($condition);
        }

        $statistics = Db::getInstance()->getRow($query) ?: [];

        return [
            'count' => (int) ($statistics['order_count'] ?? 0),
            'total_paid_tax_incl' => (float) ($statistics['total_paid_tax_incl'] ?? 0),
        ];
    }

    private function getOrderDateFilterCondition($value)
    {
        $value = trim((string) $value);
        $dates = preg_split('/\s+(?:-|a|al)\s+/iu', $value);

        if (count($dates) === 2) {
            $from = $this->normalizeOrderFilterDate($dates[0]);
            $to = $this->normalizeOrderFilterDate($dates[1]);

            if ($from !== null && $to !== null) {
                return "o.date_add >= '{$from} 00:00:00' AND o.date_add < '{$to} 23:59:59'";
            }
        }

        $date = $this->normalizeOrderFilterDate($value);
        if ($date !== null) {
            return "o.date_add >= '{$date} 00:00:00' AND o.date_add < '{$date} 23:59:59'";
        }

        return "o.date_add LIKE '%" . pSQL($value) . "%'";
    }

    private function normalizeOrderFilterDate($value)
    {
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, trim((string) $value));
            $errors = \DateTime::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function getOrderTotalFilterCondition($value)
    {
        $amount = '(?:\d{1,3}(?:[.\s]\d{3})+|\d+)(?:[,.]\d+)?';
        $value = trim((string) $value);
        $conditions = [];

        if (
            preg_match_all('/(>=|<=|>|<)\s*(' . $amount . ')/', $value, $matches, PREG_SET_ORDER)
            && trim(preg_replace('/(>=|<=|>|<)\s*' . $amount . '/', '', $value)) === ''
        ) {
            foreach ($matches as $match) {
                $conditions[] = 'o.total_paid_tax_incl ' . $match[1] . ' ' . $this->normalizeOrderTotal($match[2]);
            }

            return implode(' AND ', $conditions);
        }

        if (preg_match('/^' . $amount . '$/', $value)) {
            return 'o.total_paid_tax_incl = ' . $this->normalizeOrderTotal($value);
        }

        return null;
    }

    private function normalizeOrderTotal($value)
    {
        $value = str_replace(' ', '', $value);

        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return number_format((float) $value, 6, '.', '');
    }

    public function ajaxProcessGetCustomerAddresses()
    {
        $twig = new GetTwigEnvironment($this->module->name);
        $id_customer = (int) Tools::getValue('id_customer');
        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('a.*')
            ->select('state.name as state')
            ->select('country.iso_code as country')
            ->from('address', 'a')
            ->leftJoin('state', 'state', 'state.id_state = a.id_state')
            ->leftJoin('country', 'country', 'country.id_country = a.id_country')
            ->where("a.id_customer = {$id_customer}");

        $addresses = $db->executeS($query);
        if (!$addresses) {
            $addresses = [];
        }

        foreach ($addresses as &$address) {
            $address['addressPageUrl'] = $this->context->link->getAdminLink('AdminAddresses', true, [], [
                'id_address' => $address['id_address'],
                'updateaddress' => true,
            ]);
        }

        $queryIdAddressInvoice = new DbQuery();
        $queryIdAddressInvoice
            ->select('id_address_invoice')
            ->from('customer_invoice')
            ->where("id_customer = {$id_customer}");

        $id_address_invoice = (int) $db->getValue($queryIdAddressInvoice);

        $twig->load('@ModuleTwig/admin/customerAddresses.html.twig');
        $html = $twig->render([
            'addresses' => $addresses,
            'idAddressInvoice' => $id_address_invoice,
        ]);

        $this->response([
            'success' => true,
            'addresses' => $addresses,
            'table' => $html,
        ]);
    }

    public function ajaxProcessTruncateTable()
    {
        $db = Db::getInstance();
        $pfx = _DB_PREFIX_;
        $table = ModelCustomerInvoice::$definition['table'];
        $db->execute("TRUNCATE TABLE `{$pfx}{$table}`");

        $this->response([
            'success' => true,
        ]);
    }

    public function ajaxProcessGetCustomersApiData()
    {
        $import = new ImportFromV16($this->module);
        $limit = (int) Tools::getValue('limit', 500);
        $offset = (int) Tools::getValue('offset', 0);

        $data = $import->getCustomerData($limit, $offset);

        $this->response([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function ajaxProcessImportV16()
    {
        $import = new ImportFromV16($this->module);
        $data = json_decode(Tools::getValue('data'), true);

        if (!is_array($data)) {
            $this->response([
                'success' => false,
                'errors' => ['Dati non validi'],
            ]);
        }

        $errors = $import->doImport($data);

        $this->response([
            'success' => true,
            'errors' => $errors,
        ]);
    }

    public function ajaxProcessRenderJobsLink()
    {
        $offset = Tools::getValue('offset', 0);
        $limit = Tools::getValue('limit', 20);
        $search = Tools::getValue('search', '');
        $sort = Tools::getValue('sort', 'jal.name');
        $order = Tools::getValue('order', 'ASC');

        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('ja.id_customer_invoice_job_area')
            ->select('jal.name as job_area')
            ->select('count(jl.id_customer_invoice_job_position) as job_positions_count')
            ->from('customer_invoice_job_area', 'ja')
            ->innerJoin('customer_invoice_job_area_lang', 'jal', "jal.id_customer_invoice_job_area = ja.id_customer_invoice_job_area and jal.id_lang = {$this->id_lang}")
            ->innerJoin('customer_invoice_job_link', 'jl', 'jl.id_customer_invoice_job_area = ja.id_customer_invoice_job_area')
            ->groupBy('ja.id_customer_invoice_job_area')
            ->orderBy("{$sort} {$order}")
            ->limit($limit, $offset);

        $queryCount = new DbQuery();
        $queryCount
            ->select('count(ja.id_customer_invoice_job_area) as total')
            ->from('customer_invoice_job_area', 'ja')
            ->innerJoin('customer_invoice_job_area_lang', 'jal', "jal.id_customer_invoice_job_area = ja.id_customer_invoice_job_area and jal.id_lang = {$this->id_lang}")
            ->innerJoin('customer_invoice_job_link', 'jl', 'jl.id_customer_invoice_job_area = ja.id_customer_invoice_job_area')
            ->groupBy('ja.id_customer_invoice_job_area');

        if ($search) {
            $search = pSQL($search);
            $searchWhere = "ja.name LIKE '%{$search}%'";

            $query->where($searchWhere);
            $queryCount->where($searchWhere);
        }

        $total = $db->getValue($queryCount);
        $result = $db->executeS($query);

        if (!$result) {
            $result = [];
        }

        return [
            'rows' => $result,
            'total' => $total,
            'totalNotFiltered' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    public function ajaxProcessRenderJobsArea()
    {
        $offset = Tools::getValue('offset', 0);
        $limit = Tools::getValue('limit', 20);
        $search = Tools::getValue('search', '');
        $sort = Tools::getValue('sort', 'jal.name');
        $order = Tools::getValue('order', 'ASC');

        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('ja.id_customer_invoice_job_area')
            ->select('jal.name as job_area')
            ->select('ja.date_add')
            ->select('ja.date_upd')
            ->from('customer_invoice_job_area', 'ja')
            ->innerJoin('customer_invoice_job_area_lang', 'jal', "jal.id_customer_invoice_job_area = ja.id_customer_invoice_job_area and jal.id_lang = {$this->id_lang}")
            ->orderBy("{$sort} {$order}")
            ->limit($limit, $offset);

        $queryCount = new DbQuery();
        $queryCount
            ->select('count(ja.id_customer_invoice_job_area) as total')
            ->from('customer_invoice_job_area', 'ja')
            ->innerJoin('customer_invoice_job_area_lang', 'jal', "jal.id_customer_invoice_job_area = ja.id_customer_invoice_job_area and jal.id_lang = {$this->id_lang}");

        if ($search) {
            $search = pSQL($search);
            $searchWhere = "ja.name LIKE '%{$search}%'";

            $query->where($searchWhere);
            $queryCount->where($searchWhere);
        }

        $total = $db->getValue($queryCount);
        $result = $db->executeS($query);

        if (!$result) {
            $result = [];
        }

        return [
            'rows' => $result,
            'total' => $total,
            'totalNotFiltered' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    public function ajaxProcessRenderJobsPosition()
    {
        $offset = Tools::getValue('offset', 0);
        $limit = Tools::getValue('limit', 20);
        $search = Tools::getValue('search', '');
        $sort = Tools::getValue('sort', 'jal.name');
        $order = Tools::getValue('order', 'ASC');

        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('jp.id_customer_invoice_job_position')
            ->select('jpl.name as job_position')
            ->select('jp.date_add')
            ->select('jp.date_upd')
            ->from('customer_invoice_job_position', 'jp')
            ->innerJoin('customer_invoice_job_position_lang', 'jpl', "jpl.id_customer_invoice_job_position = jp.id_customer_invoice_job_position and jpl.id_lang = {$this->id_lang}")
            ->orderBy("{$sort} {$order}")
            ->limit($limit, $offset);

        $queryCount = new DbQuery();
        $queryCount
            ->select('count(jp.id_customer_invoice_job_position) as total')
            ->from('customer_invoice_job_position', 'jp')
            ->innerJoin('customer_invoice_job_position_lang', 'jpl', "jpl.id_customer_invoice_job_position = jp.id_customer_invoice_job_position and jpl.id_lang = {$this->id_lang}");

        if ($search) {
            $search = pSQL($search);
            $searchWhere = "ja.name LIKE '%{$search}%'";

            $query->where($searchWhere);
            $queryCount->where($searchWhere);
        }

        $total = $db->getValue($queryCount);
        $result = $db->executeS($query);

        if (!$result) {
            $result = [];
        }

        return [
            'rows' => $result,
            'total' => $total,
            'totalNotFiltered' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    public function ajaxProcessRenderJobsPositionsByJobArea()
    {
        $idJobArea = (int) Tools::getValue('idJobArea');
        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('jp.id_customer_invoice_job_position as id')
            ->select('jpl.name as job_position')
            ->from('customer_invoice_job_position', 'jp')
            ->innerJoin('customer_invoice_job_position_lang', 'jpl', "jpl.id_customer_invoice_job_position = jp.id_customer_invoice_job_position and jpl.id_lang = {$this->id_lang}")
            ->innerJoin('customer_invoice_job_link', 'jl', 'jl.id_customer_invoice_job_position = jp.id_customer_invoice_job_position')
            ->where("jl.id_customer_invoice_job_area = {$idJobArea}")
            ->orderBy('jpl.name ASC');

        $jobPositions = $db->executeS($query);

        if (!$jobPositions) {
            $jobPositions = [];
        }

        $twig = new GetTwigEnvironment($this->module->name);
        $twig->load('@ModuleTwig/admin/expandRowJobPositions.html.twig');
        $table = $twig->render([
            'jobPositions' => $jobPositions,
        ]);

        $this->response([
            'success' => true,
            'table' => $table,
        ]);
    }

    public function ajaxProcessGetJobPositions()
    {
        $idJobArea = (int) Tools::getValue('idJobArea');
        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('a.id_customer_invoice_job_position as id')
            ->select('a.name')
            ->from('customer_invoice_job_position_lang', 'a')
            ->innerJoin('customer_invoice_job_link', 'l', 'l.id_customer_invoice_job_position = a.id_customer_invoice_job_position')
            ->where("l.id_customer_invoice_job_area = {$idJobArea}")
            ->where("a.id_lang = {$this->id_lang}")
            ->orderBy('a.name ASC');

        $jobPositions = $db->executeS($query);

        $this->response([
            'success' => true,
            'jobPositions' => $jobPositions,
        ]);
    }
}
