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
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpCustomerInvoice\Helpers;

use Db;
use DbQuery;

class IsNewCustomer
{
    public static function check($id_customer): bool
    {
        $db = Db::getInstance();
        $sql = new DbQuery();
        $sql->select('count(*)')
            ->from('orders')
            ->where('id_customer = ' . (int) $id_customer);

        return 1 === (int) $db->getValue($sql);
    }
}
