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

use Context;
use Db;
use DbQuery;
use ObjectModel;
use Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModelCustomerInvoiceJobArea extends ObjectModel
{
    public $id_job_area;
    public $name;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'customer_invoice_job_area',
        'primary' => 'id_customer_invoice_job_area',
        'multilang' => true,
        'fields' => [
            'name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 64,
                'required' => true,
                'lang' => true,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateOrNull',
                'required' => true,
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateOrNull',
                'required' => false,
            ],
        ],
    ];

    public static function install()
    {
        $pfx = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $QUERY = "
            CREATE TABLE IF NOT EXISTS `{$pfx}customer_invoice_job_area` (
                `id_customer_invoice_job_area` int(11) NOT NULL AUTO_INCREMENT,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_customer_invoice_job_area`)
            ) ENGINE={$engine}
        ";

        $install_table = Db::getInstance()->execute($QUERY);

        $QUERY = "
            CREATE TABLE IF NOT EXISTS `{$pfx}customer_invoice_job_area_lang` (
                `id_customer_invoice_job_area` int(11) NOT NULL,
                `id_lang` int(11) NOT NULL,
                `name` varchar(64) NOT NULL,
                PRIMARY KEY (`id_customer_invoice_job_area`,`id_lang`)
            ) ENGINE={$engine}
        ";

        $install_table_lang = Db::getInstance()->execute($QUERY);

        return $install_table && $install_table_lang;
    }

    public static function getTableContent()
    {
        $db = Db::getInstance();
        $sql = new DbQuery();
        $sql
            ->select(self::$definition['primary'] . ' as id')
            ->select('name')
            ->from(self::$definition['table'] . '_lang')
            ->where('id_lang = ' . (int) Context::getContext()->language->id)
            ->orderBy('name');

        $result = $db->executeS($sql);

        return $result ?: [];
    }

    public static function getList()
    {
        $result = self::getTableContent();
        if ($result) {
            $list = [];
            foreach ($result as $row) {
                $list[$row['id']] = Tools::strtoupper($row['name']);
            }

            return $list;
        }

        return [];
    }

    public static function getListJson()
    {
        $result = self::getTableContent();

        return $result ?: [];
    }
}
