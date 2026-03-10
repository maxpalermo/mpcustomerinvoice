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

namespace MpSoft\MpCustomerInvoice\Models;

use \Db;
use \DbQuery;

class ModelCustomerInvoiceBrtAddresses extends \ObjectModel
{
    public $id_customer_invoice_brt_addresses;
    public $cap;
    public $localita;
    public $prov;
    public $zone;
    public $tempi;
    public $alias;
    public $carrier_allowed;
    public $active;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'customer_invoice_brt_addresses',
        'primary' => 'id_customer_invoice_brt_addresses',
        'fields' => [
            'cap' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'localita' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'prov' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'zone' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'tempi' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'alias' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true, 'size' => 65535],
            'carrier_allowed' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true, 'size' => 65535],
            'active' => ['type' => self::TYPE_INT, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    public function __construct($id = null, $id_lang = null)
    {
        parent::__construct($id, $id_lang);
        if ($this->alias) {
            $this->alias = json_decode($this->alias, true);
        }
    }

    public static function install()
    {
        $pfx = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $QUERY = "
            CREATE TABLE IF NOT EXISTS `{$pfx}customer_invoice_brt_addresses` (
                `id_customer_invoice_brt_addresses` int(11) NOT NULL AUTO_INCREMENT,
                `postcode` char(5) NOT NULL,
                `city` varchar(64) NOT NULL,
                `state` char(2) NOT NULL,
                `zone` char(1) NOT NULL,
                `delivery_times` varchar(10) NOT NULL,
                `alias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`alias`)),
                `carrier_allowed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`carrier_allowed`)),
                `active` tinyint(1) NOT NULL DEFAULT 0,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id_customer_invoice_brt_addresses`),
                UNIQUE KEY `cap_2` (`postcode`,`city`,`state`,`zone`,`delivery_times`),
                KEY `postcode` (`postcode`),
                KEY `city` (`city`),
                KEY `state` (`state`)
            ) ENGINE={$engine}
        ";

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($QUERY);
    }

    public function add($auto_date = true, $null_values = false)
    {
        if (is_array($this->alias)) {
            $this->alias = json_encode($this->alias);
        }

        return parent::add($auto_date, $null_values);
    }

    public function update($null_values = false)
    {
        if (is_array($this->alias)) {
            $this->alias = json_encode($this->alias);
        }

        return parent::update($null_values);
    }

    public static function importXlsx($filename = '', &$errors = [])
    {
        if (!$filename) {
            $filename = _PS_MODULE_DIR_ . 'mpcustomerinvoice/views/brt/addresses.xlsx';
        }

        if (!file_exists($filename)) {
            $errors[] = "Il file {$filename} non esiste";
            return false;
        }

        $errors = [];
        $xlsx = file_get_contents($filename);
        $data = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);

        Db::getInstance(_PS_USE_SQL_SLAVE_)->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'customer_invoice_brt_addresses');

        foreach ($data->getSheet(0)->toArray() as $item) {
            $model = new self();
            $model->postcode = $item['CAP'];
            $model->city = $item['LOCALITA'];
            $model->state = $item['PROV'];
            $model->zone = $item['ZONE'];
            $model->delivery_times = $item['TEMPI'];
            $model->alias = [];
            $model->active = 1;
            $model->date_add = date('Y-m-d H:i:s');
            $model->date_upd = null;
            try {
                $model->add();
            } catch (\Throwable $th) {
                $errors[] = "{RIGA: {$item['CAP']}} {$item['LOCALITA']} {$item['PROV']} {$item['ZONE']} {$item['TEMPI']}: {$th->getMessage()}";
                continue;
            }
        }

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $result = (int) $db->getValue('SELECT count(*) FROM ' . _DB_PREFIX_ . 'customer_invoice_brt_addresses');

        return (bool) $result;
    }

    public static function importAlias($filename, $separator = ',', &$errors = [])
    {
        $db = Db::getInstance();
        $errors = [];
        $xlsx = file_get_contents($filename);
        $data = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);
        $affectedRows = 0;

        foreach ($data->getSheet(0)->toArray() as $item) {
            $aliases = $item['ALIAS'];
            $arr = explode($separator, $aliases);

            if ($arr) {
                $arr = array_map(
                    function ($alias) {
                        $alias = trim($alias);
                        $alias = str_replace([' ', '.', "'", '-'], '', $alias);
                        if ($alias) {
                            return $alias;
                        }
                    },
                    $arr
                );

                $arr = array_unique($arr);
                asort($arr);

                $postcode = pSQL($item['POSTCODE']);
                $city = pSQL($item['CITY']);
                $state = pSQL($item['STATE']);

                $db->update(
                    self::$definition['table'],
                    [
                        'alias' => json_encode($arr),
                        'date_upd' => date('Y-m-d H:i:s')
                    ],
                    "`city` = '{$city}' AND `postcode` = '{$postcode}' AND `state` = '{$state}'"
                );

                $affectedRows += $db->Affected_Rows();
            }
        }

        return (int) $affectedRows;
    }

    public static function getCity()
    {
        $query = new DbQuery();
        $query
            ->select('*')
            ->from('customer_invoice_brt_addresses')
            ->orderBy('city ASC');

        try {
            $list = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        } catch (\Throwable $th) {
            return $th;
        }

        return $list;
    }

    public static function getPostcodes()
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $result = $db->executeS('SELECT DISTINCT postcode FROM ' . _DB_PREFIX_ . 'customer_invoice_brt_addresses ORDER BY postcode ASC');

        $postcodes = array_column($result, 'postcode');

        return $postcodes;
    }

    public static function getStates()
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $result = $db->executeS('SELECT DISTINCT state FROM ' . _DB_PREFIX_ . 'customer_invoice_brt_addresses ORDER BY state ASC');

        $states = array_column($result, 'state');

        return $states;
    }

    public static function getCitiesFromPostcode($postcode)
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $table = _DB_PREFIX_ . 'customer_invoice_brt_addresses';
        $postcode = pSQL($postcode);

        $result = $db->executeS(
            "SELECT
                id_customer_invoice_brt_addresses as id,
                city
            FROM
                {$table}
            WHERE
                postcode = {$postcode}
            ORDER BY
                city ASC"
        );

        return $result;
    }
}
