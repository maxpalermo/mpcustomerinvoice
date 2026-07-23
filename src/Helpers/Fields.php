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

class Fields
{
    const TYPE_ENTE = 'ENTE';
    const TYPE_PARTITA_IVA = 'PARTITA_IVA';
    const TYPE_PRIVATO = 'PRIVATO';

    protected static $FIELDS = [
        'ENTE' => [
            'dni' => ['mandatory' => true],
            'cig' => ['mandatory' => true],
            'cup' => ['mandatory' => true],
        ],
        'PARTITA_IVA' => [
            'vat_number' => ['mandatory' => true],
            'sdi' => ['mandatory' => true],
            'pec' => ['mandatory' => false],
        ],
        'PRIVATO' => [
            'dni' => ['mandatory' => true],
        ]
    ];

    public static function getFields($type)
    {
        if (isset(self::$FIELDS[$type])) {
            return self::$FIELDS[$type];
        }

        return false;
    }
}
