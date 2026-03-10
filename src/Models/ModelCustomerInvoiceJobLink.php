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

use ObjectModel;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModelCustomerInvoiceJobLink extends ObjectModel
{
    public $id_customer_invoice_job_area;

    public static $definition = [
        'table' => 'customer_invoice_job_link',
        'primary' => 'id_customer_invoice_job_position',
        'fields' => [
            'id_customer_invoice_job_area' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
        ],
    ];

    public static function install()
    {
        $pfx = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $QUERY = "
            CREATE TABLE IF NOT EXISTS {$pfx}customer_invoice_job_link (
                `id_customer_invoice_job_position` int(11) NOT NULL AUTO_INCREMENT,
                `id_customer_invoice_job_area` int(11) DEFAULT NULL,
                PRIMARY KEY (`id_customer_invoice_job_position`)
            ) ENGINE={$engine}
        ";

        return \Db::getInstance()->execute($QUERY);
    }

    public static function getJobs($idArea)
    {
        $id_lang = (int) \Context::getContext()->language->id;
        $db = \Db::getInstance();
        $sql = new \DbQuery();

        $sql
            ->select('a.id_customer_invoice_job_position as `id`')
            ->select('b.name as `name`')
            ->from('customer_invoice_job_position', 'a')
            ->innerJoin('customer_invoice_job_position_lang', 'b', 'a.id_customer_invoice_job_position=b.id_customer_invoice_job_position and b.id_lang=' . (int) $id_lang)
            ->innerJoin('customer_invoice_job_link', 'c', 'a.id_customer_invoice_job_position=c.id_customer_invoice_job_position and c.id_customer_invoice_job_area=' . (int) $idArea)
            ->orderBy('b.name');

        $sql = $sql->build();
        $rows = $db->executeS($sql);

        return $rows;
    }
}
