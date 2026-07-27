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
 * @copyright 2026 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpCustomerInvoice\Helpers;

use \Order;
use \Validate;
use \Db;
use \OrderInvoice;
use \PrestaShopLogger;

class GenerateDocumentRestrictions
{
    /**
     * Intercept order status update BEFORE PrestaShop generates documents.
     * Overrides $params['newOrderStatus'] flags to ensure only Invoice OR Delivery is generated, never both.
     *
     * @param array $params
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if (empty($params['id_order']) || empty($params['newOrderStatus'])) {
            return;
        }

        $idOrder = (int) $params['id_order'];
        $newOrderStatus = $params['newOrderStatus'];

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        // If status triggers neither invoice nor delivery, return
        if (!$newOrderStatus->invoice && !$newOrderStatus->delivery) {
            return;
        }

        $isInvoiceRequired = $this->isInvoiceRequired($order->id_customer);

        if ($isInvoiceRequired) {
            $newOrderStatus->invoice = 1;
            $newOrderStatus->delivery = 0;
        } else {
            $newOrderStatus->invoice = 0;
            $newOrderStatus->delivery = 1;
        }
    }

    /**
     * Intercept order status update AFTER status is changed.
     * Guarantees strict mutual exclusion on order_invoice records for the order.
     *
     * @param array $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if (empty($params['id_order']) || empty($params['newOrderStatus'])) {
            return;
        }

        $idOrder = (int) $params['id_order'];
        $newOrderStatus = $params['newOrderStatus'];

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        if (!$newOrderStatus->invoice && !$newOrderStatus->delivery && !$order->hasInvoice() && !$order->hasDelivery()) {
            return;
        }

        $isInvoiceRequired = $this->isInvoiceRequired($order->id_customer);
        $invoices = $order->getInvoicesCollection();

        if ($isInvoiceRequired) {
            // Invoice required: number > 0, delivery_number = 0
            if ($invoices->count() == 0) {
                $this->createInvoiceOnly($order);
            } else {
                foreach ($invoices as $orderInvoice) {
                    $changed = false;
                    if ((int) $orderInvoice->number <= 0) {
                        $orderInvoice->number = $orderInvoice->getNextInvoiceNumber();
                        if (empty($orderInvoice->invoice_date) || $orderInvoice->invoice_date === '0000-00-00 00:00:00') {
                            $orderInvoice->invoice_date = date('Y-m-d H:i:s');
                        }
                        $changed = true;
                    }
                    if ((int) $orderInvoice->delivery_number > 0) {
                        $orderInvoice->delivery_number = 0;
                        $orderInvoice->delivery_date = '0000-00-00 00:00:00';
                        $changed = true;
                    }
                    if ($changed) {
                        $orderInvoice->save();
                    }
                }
            }
        } else {
            // Delivery required: delivery_number > 0, number = 0
            if ($invoices->count() == 0) {
                $this->createDeliveryOnly($order);
            } else {
                foreach ($invoices as $orderInvoice) {
                    $changed = false;
                    if ((int) $orderInvoice->delivery_number <= 0) {
                        $orderInvoice->delivery_number = $orderInvoice->getNextDeliveryNumber();
                        if (empty($orderInvoice->delivery_date) || $orderInvoice->delivery_date === '0000-00-00 00:00:00') {
                            $orderInvoice->delivery_date = date('Y-m-d H:i:s');
                        }
                        $changed = true;
                    }
                    if ((int) $orderInvoice->number > 0) {
                        $orderInvoice->number = 0;
                        $orderInvoice->invoice_date = '0000-00-00 00:00:00';
                        $changed = true;
                    }
                    if ($changed) {
                        $orderInvoice->save();
                    }
                }
            }
        }
    }

    /**
     * Check if customer exists in customer_invoice and has non-empty vat_number OR dni.
     *
     * @param int $idCustomer
     * @return bool
     */
    public function isInvoiceRequired($idCustomer)
    {
        if (empty($idCustomer)) {
            return false;
        }

        $sql = 'SELECT `vat_number`, `dni` FROM `' . _DB_PREFIX_ . 'customer_invoice` WHERE `id_customer` = ' . (int) $idCustomer;
        $row = Db::getInstance()->getRow($sql);

        if (!$row) {
            return false;
        }

        $hasVat = !empty($row['vat_number']) && trim((string) $row['vat_number']) !== '';
        $hasDni = !empty($row['dni']) && trim((string) $row['dni']) !== '';

        return $hasVat || $hasDni;
    }

    /**
     * Create ONLY invoice for order.
     *
     * @param Order $order
     */
    private function createInvoiceOnly(Order $order)
    {
        $order_invoice = new OrderInvoice();
        $order_invoice->id_order = $order->id;
        $order_invoice->number = $order_invoice->getNextInvoiceNumber();
        $order_invoice->delivery_number = 0;
        $order_invoice->invoice_date = date('Y-m-d H:i:s');
        $order_invoice->delivery_date = '0000-00-00 00:00:00';
        $order_invoice->total_paid_tax_incl = $order->total_paid_tax_incl;
        $order_invoice->total_paid_tax_excl = $order->total_paid_tax_excl;
        $order_invoice->total_products = $order->total_products;
        $order_invoice->total_products_wt = $order->total_products_wt;
        $order_invoice->total_shipping_tax_incl = $order->total_shipping_tax_incl;
        $order_invoice->total_shipping_tax_excl = $order->total_shipping_tax_excl;
        $order_invoice->total_discount_tax_excl = $order->total_discounts_tax_excl;
        $order_invoice->total_discount_tax_incl = $order->total_discounts_tax_incl;
        $order_invoice->total_wrapping_tax_excl = $order->total_wrapping_tax_excl;
        $order_invoice->total_wrapping_tax_incl = $order->total_wrapping_tax_incl;

        $order_invoice->add();

        $order->invoice_date = $order_invoice->invoice_date;
        $order->save();

        $orderDetails = $order->getProductsDetail();
        foreach ($orderDetails as $detail) {
            $order_invoice->addProduct($detail, $detail['product_quantity']);
        }

        PrestaShopLogger::addLog(
            sprintf('Fattura creata per ordine %d (GenerateDocumentRestrictions)', $order->id),
            1,
            null,
            'Order',
            $order->id
        );
    }

    /**
     * Create ONLY delivery for order.
     *
     * @param Order $order
     */
    private function createDeliveryOnly(Order $order)
    {
        $order_invoice = new OrderInvoice();
        $order_invoice->id_order = $order->id;
        $order_invoice->number = 0;
        $order_invoice->delivery_number = $order_invoice->getNextDeliveryNumber();
        $order_invoice->invoice_date = '0000-00-00 00:00:00';
        $order_invoice->delivery_date = date('Y-m-d H:i:s');
        $order_invoice->total_paid_tax_incl = $order->total_paid_tax_incl;
        $order_invoice->total_paid_tax_excl = $order->total_paid_tax_excl;
        $order_invoice->total_products = $order->total_products;
        $order_invoice->total_products_wt = $order->total_products_wt;
        $order_invoice->total_shipping_tax_incl = $order->total_shipping_tax_incl;
        $order_invoice->total_shipping_tax_excl = $order->total_shipping_tax_excl;
        $order_invoice->total_discount_tax_excl = $order->total_discounts_tax_excl;
        $order_invoice->total_discount_tax_incl = $order->total_discounts_tax_incl;
        $order_invoice->total_wrapping_tax_excl = $order->total_wrapping_tax_excl;
        $order_invoice->total_wrapping_tax_incl = $order->total_wrapping_tax_incl;

        $order_invoice->add();

        $order->delivery_date = $order_invoice->delivery_date;
        $order->save();

        PrestaShopLogger::addLog(
            sprintf('Delivery creato per ordine %d (GenerateDocumentRestrictions)', $order->id),
            1,
            null,
            'Order',
            $order->id
        );
    }
}