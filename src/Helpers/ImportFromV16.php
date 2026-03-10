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

use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;

class ImportFromV16
{
    private $module;
    private $url;
    private $token;
    private $flash_message;
    private $flash_type;
    private $id_employee;
    private $errors;

    const TYPE_ENTE = 1;
    const TYPE_PARTITA_IVA = 2;
    const TYPE_PRIVATO = 3;

    public function __construct($module)
    {
        $this->module = $module;
        $this->url = \Configuration::get('MP_REQUEST_API_URL');
        $this->token = \Configuration::get('MP_REQUEST_API_TOKEN');
        $this->id_employee = (int) \Context::getContext()->employee->id;
        $this->errors = [];
    }

    public function getCustomerData($limit = 1000, $offset = 0)
    {
        $pfx = \Configuration::get('MP_REQUEST_API_DB_PREFIX');
        if (!$pfx) {
            $pfx = _DB_PREFIX_;
        }

        $query = "
            SELECT
                a.id_customer, '' as cuu, a.uid as sdi, a.pec, a.cig, a.cup, a.id_eur as `id_eurosolution`, a.id_job_area, a.id_job_name as id_job_position,
                addr.id_address as id_address_invoice, addr.vat_number, addr.dni as dni, addr.subject as type
            FROM
                `{$pfx}customer` a
            LEFT JOIN
                `{$pfx}address` addr
                ON
                a.id_customer=addr.id_customer and addr.type='invoice'
            ORDER BY
                id_customer
            LIMIT 
                {$limit}
            OFFSET 
                {$offset}
            ";

        $data = $this->setQuery($query);

        return $data;
    }

    public function setQuery($query)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'token=' . $this->token . '&query=' . $query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            $this->flash_message = $this->module->l('Errore nella richiesta');
            $this->flash_type = 'danger';
            return false;
        }

        $this->flash_message = $this->module->l('Richiesta effettuata con successo');
        $this->flash_type = 'success';

        $data = json_decode($response, true);
        if (!$data['success']) {
            $this->flash_message = $this->module->l('Errore nella richiesta');
            $this->flash_type = 'danger';

            return false;
        }

        // Decodifico i dati base64
        $base64 = base64_decode($data['data']);
        // Decodifico i dati json
        $data = json_decode($base64, true);

        return $data;
    }

    public function doImport($dataJson)
    {
        $table = ModelCustomerInvoice::$definition['table'];
        $db = \Db::getInstance();
        // Controllo se il dato è di tipo json o array

        if (!is_array($dataJson)) {
            $data = json_decode($dataJson, true);
        } else {
            $data = $dataJson;
        }

        if (!$data) {
            $this->flash_message = $this->module->l('Nessun dato da importare');
            $this->flash_type = 'warning';
            return true;
        }

        foreach ($data as &$row) {
            $type = $row['type'];
            switch ($type) {
                case self::TYPE_ENTE:
                    $row['type'] = 'ENTE';
                    break;
                case self::TYPE_PARTITA_IVA:
                    $row['type'] = 'PARTITA_IVA';
                    break;
                case self::TYPE_PRIVATO:
                    $row['type'] = 'PRIVATO';
                    break;
                default:
                    continue;
            }

            $row['is_foreign'] = 0;
            $row['date_add'] = date('Y-m-d H:i:s');
            $row['date_upd'] = null;

            try {
                $db->insert($table, $row, true);
            } catch (\Throwable $th) {
                $this->errors[] = "Errore durante l'inserimento del record: {$row['id_customer']}: {$th->getMessage()};\n";
            }
        }

        return $this->errors;
    }
}
