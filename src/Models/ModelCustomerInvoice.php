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

class ModelCustomerInvoice extends ObjectModel
{
    public $id_eurosolution;
    public $type;
    public $vat_number;
    public $dni;
    public $cuu;  // Codice Univoco dal portale IPA
    public $sdi;  // Codice destinatario
    public $pec;  // Indirizzo email pec
    public $cig;  // Codice di identificazione del gestore
    public $cup;  // Codice univoco di pagamento
    public $id_address_invoice;
    public $is_foreign;  // Indica se il cliente è un cliente straniero
    public $id_customer_invoice_job_area;
    public $id_customer_invoice_job_position;
    public $date_add;
    public $date_upd;
    protected static $model_name = 'ModelCustomerInvoice';

    public static $definition = [
        'table' => 'customer_invoice',
        'primary' => 'id_customer',
        'multilang' => false,
        'fields' => [
            'id_eurosolution' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
            ],
            'type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 24,
                'required' => false,
            ],
            'vat_number' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 16,
                'required' => false,
            ],
            'dni' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 16,
                'required' => false,
            ],
            'cuu' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 6,
                'required' => false,
            ],
            'sdi' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 7,
                'required' => false,
            ],
            'pec' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isEmail',
                'size' => 255,
                'required' => false,
            ],
            'cig' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 10,
                'required' => false,
            ],
            'cup' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 15,
                'required' => false,
            ],
            'id_address_invoice' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
            ],
            'is_foreign' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
                'required' => false,
            ],
            'id_customer_invoice_job_area' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
            ],
            'id_customer_invoice_job_position' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateOrNull',
                'required' => false,
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
            CREATE TABLE IF NOT EXISTS {$pfx}customer_invoice (
                `id_customer` int(11) NOT NULL AUTO_INCREMENT,
                `id_eurosolution` int(11) DEFAULT NULL,
                `type` varchar(24) DEFAULT NULL,
                `vat_number` varchar(16) DEFAULT NULL,
                `dni` varchar(16) DEFAULT NULL,
                `cuu` varchar(6) DEFAULT NULL,
                `sdi` varchar(7) DEFAULT NULL,
                `pec` varchar(255) DEFAULT NULL,
                `cig` varchar(10) DEFAULT NULL,
                `cup` varchar(15) DEFAULT NULL,
                `id_address_invoice` int(11) DEFAULT NULL,
                `is_foreign` tinyint(1) DEFAULT NULL,
                `id_customer_invoice_job_area` int(11) DEFAULT NULL,
                `id_customer_invoice_job_position` int(11) DEFAULT NULL,
                `date_add` datetime NOT NULL,
                `date_upd` datetime DEFAULT NULL,
                PRIMARY KEY (`id_customer`)
            ) ENGINE={$engine}
        ";

        return \Db::getInstance()->execute($QUERY);
    }
}
