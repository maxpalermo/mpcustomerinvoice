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
use \Configuration;
use \OrderInvoice;
use \PrestaShopLogger;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;

class GenerateDocumentRestrictions
{
    /**
     * Check if customer exists in customer_invoice and has non-empty vat_number OR dni.
     *
     * @param int $idCustomer
     * @return bool
     */
    public static function isInvoiceRequired($idCustomer)
    {
        if (empty($idCustomer)) {
            return false;
        }

        $table = Db::getFullTableName('customer_invoice');
        $idCustomer = (int) $idCustomer;

        $sql = "
            SELECT
                `invoice_requested`
            FROM 
                {$table} 
            WHERE 
                `id_customer` = {$idCustomer}
        ";

        $invoiceRequested = (int) Db::getInstance()->getValue($sql);

        return (bool) $invoiceRequested;
    }

    public static function handleAutomaticDocumentGeneration(array $params)
    {
        $newOrderStatus = $params['newOrderStatus'] ?? null;
        $idOrderState = (int) ($newOrderStatus->id ?? $params['id_order_state'] ?? 0);
        $idOrder = (int) ($params['id_order'] ?? 0);

        PrestaShopLogger::addLog("hookActionOrderStatusPostUpdate: Generazione documenti per l'ordine {$idOrder}.");

        $rawTriggers = Configuration::get('MPCUSTOMERINVOICE_ORDER_STATE_TRIGGER');
        $triggers = [];
        if ($rawTriggers) {
            $decoded = json_decode($rawTriggers, true);
            if (is_array($decoded)) {
                $triggers = array_map('intval', $decoded);
            } elseif (is_numeric($rawTriggers)) {
                $triggers = [(int) $rawTriggers];
            }
        }

        if (empty($triggers)) {
            PrestaShopLogger::addLog("hookActionOrderStatusPostUpdate: Nessun trigger impostato. Controllare Configurazione Modulo.", 3, 0, 'Mpcustomerinvoice');
            return;
        }

        if (!in_array($idOrderState, $triggers, true)) {
            return;
        }

        self::processDocumentGenerationForOrder($idOrder);
    }

    /**
     * Procedure for manual document generation (bypasses order state trigger check).
     */
    public static function handleManualDocumentGeneration(int $idOrder): bool
    {
        PrestaShopLogger::addLog("handleManualDocumentGeneration: Generazione manuale documento per l'ordine {$idOrder}.");
        return self::processDocumentGenerationForOrder($idOrder);
    }

    /**
     * Shared procedure for document generation & restrictions enforcement.
     */
    public static function processDocumentGenerationForOrder(int $idOrder): bool
    {
        if ($idOrder <= 0) {
            PrestaShopLogger::addLog("processDocumentGenerationForOrder: id_order non valido {$idOrder}", 3, 0, 'Mpcustomerinvoice');
            return false;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog("processDocumentGenerationForOrder: Ordine {$idOrder} non validato", 3, 0, 'Mpcustomerinvoice');
            return false;
        }

        $createBoth = (int) Configuration::get('MPCUSTOMERINVOICE_CREATE_BOTH') === 1;
        $isInvoiceRequired = self::isInvoiceRequired($order->id_customer);

        if (!$createBoth && $isInvoiceRequired) {
            $order->setInvoice(true);
            self::removeDeliverySlip($idOrder);
        }

        if (!$createBoth && !$isInvoiceRequired) {
            $order->setInvoice(true);
            self::setDeliverySlip($idOrder);
            self::removeInvoice($idOrder);
        }

        if ($createBoth) {
            $order->setInvoice(true);
            self::setDeliverySlip($idOrder);
        }

        return true;
    }

    public static function removeInvoice($idOrder)
    {
        $orderInvoiceTable = Db::getFullTableName('order_invoice');
        $orderTable = Db::getFullTableName('orders');

        $query = "
            UPDATE 
                {$orderTable} o
                INNER JOIN {$orderInvoiceTable} oi ON o.id_order = oi.id_order
            SET 
                o.invoice_number = 0, 
                o.invoice_date = null,
                oi.number=0
            WHERE 
                o.id_order = {$idOrder}
        ";
        try {
            $result = Db::getInstance()->execute($query);
        } catch (\Throwable $th) {
            PrestaShopLogger::addLog("Errore rimozione fattura per l'ordine {$idOrder}. Errore: {$th->getMessage()}", 1, 0, "order", $idOrder);
            PrestaShopLogger::addLog("QUERY: {$query}", 1, 0, "order", $idOrder);
            return false;
        }
        return $result;
    }

    public static function removeDeliverySlip($idOrder)
    {
        $orderInvoiceTable = Db::getFullTableName('order_invoice');
        $orderTable = Db::getFullTableName('orders');

        $query = "
            UPDATE 
                {$orderTable} o
                INNER JOIN {$orderInvoiceTable} oi ON o.id_order = oi.id_order
            SET 
                o.delivery_number = 0, 
                o.delivery_date = null,
                oi.delivery_number=0,
                oi.delivery_date=null
            WHERE 
                o.id_order = {$idOrder}
        ";
        try {
            $result = Db::getInstance()->execute($query);
        } catch (\Throwable $th) {
            PrestaShopLogger::addLog("Errore rimozione DDT per l'ordine {$idOrder}. Errore: {$th->getMessage()}", 1, 0, "order", $idOrder);
            PrestaShopLogger::addLog("QUERY: {$query}", 1, 0, "order", $idOrder);
            return false;
        }
        return $result;
    }

    public static function setDeliverySlip($id_order)
    {
        $order = new Order($id_order);
        $orderTable = Db::getFullTableName('orders');
        $orderInvoiceTable = Db::getFullTableName('order_invoice');
        $lastInvoiceNumberQuery = "
            SELECT
                MAX(oi.`delivery_number`) AS last_invoice_number
            FROM
                {$orderInvoiceTable} oi
            WHERE
                oi.`delivery_number` > 0
        ";
        $lastInvoiceNumber = (int) Db::getInstance()->getValue($lastInvoiceNumberQuery) + 1;
        $setDeliveryQuery = "
            UPDATE 
                {$orderInvoiceTable} oi,
                {$orderTable} o
            SET 
                oi.`delivery_number` = {$lastInvoiceNumber},
                oi.`delivery_date` = CURDATE(),
                o.`delivery_number` = {$lastInvoiceNumber},
                o.`delivery_date` = CURDATE()
            WHERE 
                oi.`id_order` = {$id_order}
                AND o.`id_order` = {$id_order}
        ";
        try {
            $result = (int) Db::getInstance()->execute($setDeliveryQuery);
        } catch (\Throwable $th) {
            PrestaShopLogger::addLog("Errore DDT per l'ordine {$order->id}. Errore: {$th->getMessage()}", 1, 0, "order", $order->id);
            PrestaShopLogger::addLog("QUERY: {$setDeliveryQuery}", 1, 0, "order", $order->id);
            return false;
        }


        return $result;
    }

    public static function checkCustomerInvoiceFields($id_customer)
    {
        $db = Db::getInstance();
        $table = Db::getFullTableName('customer_invoice');
        $id_customer = (int) $id_customer;

        $sql = "
            SELECT 
                *
            FROM 
                {$table} 
            WHERE 
                `id_customer` = {$id_customer}
        ";

        $row = $db->getRow($sql);

        if (!$row) {
            return false;
        }

        if ($row['type'] === 'PRIVATO' && $row['invoice_requested']) {
            return [
                'requested_invoice' => (int) $row['requested_invoice'],
                'company' => strlen(trim($row['company'])) > 0,
                'dni' => strlen(trim($row['dni'])) > 0,
                'address_invoice' => (int) $row['id_address_invoice'],
                'is_foreign' => (int) $row['is_foreign'],
            ];
        }

        if ($row['type'] === 'PARTITA_IVA' && $row['invoice_requested']) {
            return [
                'requested_invoice' => (int) $row['requested_invoice'],
                'company' => strlen(trim($row['company'])) > 0,
                'vat_number' => strlen(trim($row['vat_number'])) > 0,
                'cuu' => strlen(trim($row['cuu'])) > 0,
                'pec' => strlen(trim($row['pec'])) > 0,
                'address_invoice' => (int) $row['id_address_invoice'],
                'is_foreign' => (int) $row['is_foreign'],
            ];
        }

        if ($row['type'] === 'ENTE' && $row['invoice_requested']) {
            return [
                'requested_invoice' => (int) $row['requested_invoice'],
                'company' => strlen(trim($row['company'])) > 0,
                'dni' => strlen(trim($row['vat_number'])) > 0,
                'cig' => strlen(trim($row['cig'])) > 0,
                'cup' => strlen(trim($row['cup'])) > 0,
                'address_invoice' => (int) $row['id_address_invoice'],
                'is_foreign' => (int) $row['is_foreign'],
            ];
        }

        return false;
    }
}