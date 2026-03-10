<?php

use MpSoft\MpCustomerInvoice\Helpers\GetTwigEnvironment;
use MpSoft\MpCustomerInvoice\Helpers\ImportFromV16;
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
        $this->page_header_toolbar_btn['customers'] = [
            'href' => $this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showCustomersPage']),
            'desc' => $this->module->l('Clienti'),
            'icon' => 'icon-user',
            'class' => 'btn-customers',
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
            case 'showCustomersPage':
                $this->content = $this->renderCustomersPage();
                break;
            case 'showJobsPage':
                $this->content = $this->renderJobsPage();
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
        ]);
    }

    public function ajaxProcessRenderCustomersData()
    {
        $offset = Tools::getValue('offset', 0);
        $limit = Tools::getValue('limit', 20);
        $search = Tools::getValue('search', '');
        $sort = Tools::getValue('sort', 'c.id_customer');
        $order = Tools::getValue('order', 'DESC');
        $filter = json_decode(Tools::getValue('filter'), true);

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
