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
        $table = _DB_PREFIX_ . 'customer_invoice';
        $idCustomer = (int) $idCustomer;

        $sql = "
            SELECT
                `company`, 
                `type`, 
                `invoice_requested`, 
                `vat_number`, 
                `dni`,
                `id_address_invoice`
            FROM 
                {$table} 
            WHERE 
                `id_customer` = {$idCustomer}
        ";

        $row = Db::getInstance()->getRow($sql);

        if (!$row) {
            return false;
        }

        if ($row['type'] === 'PRIVATO' && $row['invoice_requested']) {
            $hasCompany = !empty($row['company']) && trim((string) $row['company']) !== '';
            $hasDni = !empty($row['dni']) && trim((string) $row['dni']) !== '';
            $hasIdAddressInvoice = (int) $row['id_address_invoice'];
            return $hasCompany && $hasDni && $hasIdAddressInvoice;
        }

        if ($row['type'] === 'PARTITA_IVA' && $row['invoice_requested']) {
            $hasCompany = !empty($row['company']) && trim((string) $row['company']) !== '';
            $hasVat = !empty($row['vat_number']) && trim((string) $row['vat_number']) !== '';
            $hasIdAddressInvoice = (int) $row['id_address_invoice'];
            return $hasCompany && $hasVat && $hasIdAddressInvoice;
        }

        if ($row['type'] === 'ENTE' && $row['invoice_requested']) {
            $hasCompany = !empty($row['company']) && trim((string) $row['company']) !== '';
            $hasDni = !empty($row['dni']) && trim((string) $row['dni']) !== '';
            $hasIdAddressInvoice = (int) $row['id_address_invoice'];
            return $hasCompany && $hasDni && $hasIdAddressInvoice;
        }

        return false;
    }

    public static function handleAutomaticDocumentGeneration(array $params)
    {
        $createBoth = (int) Configuration::get('MPCUSTOMERINVOICE_CREATE_BOTH') === 1;
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
            return;
        }

        if (!in_array($idOrderState, $triggers, true) || $idOrder <= 0) {
            PrestaShopLogger::addLog("hookActionOrderStatusPostUpdate: trigger or id_order non valido", 3, 0, 'Mpcustomerinvoice');
            return;
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog("hookActionOrderStatusPostUpdate: Ordine {$idOrder} non validato", 3, 0, 'Mpcustomerinvoice');
            return;
        }
        $isInvoiceRequired = self::isInvoiceRequired($order->id_customer);

        if (!$order->hasInvoice()) {
            $order->setInvoice(true);
            PrestaShopLogger::addLog("Fattura per l'ordine {$idOrder} creata.");
        }
        if (empty($order->delivery_number)) {
            $order->setDeliverySlip();
            PrestaShopLogger::addLog("DDT per l'ordine {$idOrder} creato.");
        }

        if (!$createBoth) {
            if ($isInvoiceRequired) {
                PrestaShopLogger::addLog("Bisogna generare solo la fattura per l'ordine {$idOrder}. Semplice correzione del documento.");
            } else {
                PrestaShopLogger::addLog("Bisogna generare solo il DDT per l'ordine {$idOrder}. Semplice correzione del documento.");
            }

            $orderTable = Db::getFullTableName('orders');
            $orderInvoiceTable = Db::getFullTableName('order_invoice');
            if (!$createBoth && $isInvoiceRequired) {
                PrestaShopLogger::addLog("Rimozione DDT per l'ordine {$idOrder}.");

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
                    PrestaShopLogger::addLog("Errore rimozione DDT per l'ordine {$idOrder}. Errore: {$th->getMessage()}");
                    PrestaShopLogger::addLog("QUERY: {$query}");
                    $result = false;
                }
                if ($result) {
                    PrestaShopLogger::addLog("DDT per l'ordine {$idOrder} rimosso.");
                }
            }

            if (!$createBoth && !$isInvoiceRequired) {
                PrestaShopLogger::addLog("Rimozione fattura per l'ordine {$idOrder}.");

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
                    PrestaShopLogger::addLog("Errore rimozione fattura per l'ordine {$idOrder}. Errore: {$th->getMessage()}");
                    PrestaShopLogger::addLog("QUERY: {$query}");
                    $result = false;
                }
                if ($result) {
                    PrestaShopLogger::addLog("Fattura per l'ordine {$idOrder} rimossa.");
                }
            }
        }
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