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

namespace MpSoft\MpCustomerInvoice\Export;

class ExportDelivery extends ExportManager
{
    /**
     * Get specific data for Delivery.
     *
     * @return array
     */
    protected function getSpecificData()
    {
        $order = $this->getOrder();

        $sql = 'SELECT `id_order_invoice`, `delivery_number`, `delivery_date` FROM `' . _DB_PREFIX_ . 'order_invoice` WHERE `id_order` = ' . (int) $order->id . ' ORDER BY `id_order_invoice` ASC';
        $row = \Db::getInstance()->getRow($sql);

        if (!$row || empty($row['delivery_number']) || (int) $row['delivery_number'] <= 0) {
            throw new \Exception("Documento Nota di Consegna (DDT) non esistente per l'ordine #{$order->id}");
        }

        $invoiceId = (string) $order->id;
        $invoiceNumber = (string) $row['delivery_number'];
        $invoiceDate = (string) $row['delivery_date'];

        return [
            'document_type' => '78',
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
        ];
    }

    /**
     * Get target filename for Delivery export.
     *
     * @return string
     */
    protected function getFilename()
    {
        return 'delivery_' . $this->idOrder . '.xml';
    }
}
