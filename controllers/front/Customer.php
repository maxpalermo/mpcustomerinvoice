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
class MpCustomerInvoiceCustomerModuleFrontController extends ModuleFrontController
{
    protected $id_lang;
    protected $id_shop;

    public function __construct()
    {
        parent::__construct();
        $this->id_lang = (int) $this->context->language->id;
        $this->id_shop = (int) $this->context->shop->id;

        if (Tools::isSubmit('ajax') && Tools::isSubmit('action')) {
            $action = Tools::getValue('action') . 'Action';
            if (method_exists($this, $action)) {
                $status = 200;
                exit($this->response($this->$action($status), $status));
            }
        }
    }

    protected function response($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        exit(json_encode($data));
    }

    public function initContent()
    {
        $this->response('METODO NON PERMESSO', 404);
    }

    public function getJobPositionsAction(&$status)
    {
        $idJobArea = (int) Tools::getValue('idJobArea');
        $db = Db::getInstance();
        $query = new DbQuery();
        $query
            ->select('l.id_customer_invoice_job_position as id')
            ->select('jpl.name')
            ->from('customer_invoice_job_link', 'l')
            ->innerJoin(
                'customer_invoice_job_position_lang',
                'jpl',
                "l.id_customer_invoice_job_position = jpl.id_customer_invoice_job_position AND jpl.id_lang = {$this->id_lang}"
            )
            ->where("l.id_customer_invoice_job_area = {$idJobArea}")
            ->orderBy('jpl.name ASC');

        // Leggo i dati
        $jobPositions = $db->executeS($query);

        $status = 200;

        return [
            'success' => true,
            'jobPositions' => $jobPositions,
        ];
    }
}
