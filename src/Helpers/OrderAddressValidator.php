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

use Address;
use Context;
use Country;
use Db;
use Order;
use Validate;

class OrderAddressValidator
{
    /**
     * Checks if both delivery and invoice addresses of an order are valid.
     *
     * @param int $idOrder
     * @return array ['valid' => bool, ...]
     */
    public static function verifyOrderAddresses(int $idOrder): array
    {
        if ($idOrder <= 0) {
            return ['valid' => true];
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return ['valid' => true];
        }

        $idLang = (int) $order->id_lang;
        if ($idLang <= 0) {
            $idLang = (int) Context::getContext()->language->id;
        }

        $deliveryValid = self::isOrderAddressValid((int) $order->id_address_delivery, $idLang);
        $invoiceValid = self::isOrderAddressValid((int) $order->id_address_invoice, $idLang);

        if (!$deliveryValid || !$invoiceValid) {
            return [
                'valid' => false,
                'id_order' => (int) $order->id,
                'delivery_valid' => $deliveryValid,
                'invoice_valid' => $invoiceValid,
                'id_address_delivery' => (int) $order->id_address_delivery,
                'id_address_invoice' => (int) $order->id_address_invoice,
            ];
        }

        return ['valid' => true];
    }

    /**
     * Checks if a specific address ID is valid and has a valid country with language name.
     *
     * @param int $idAddress
     * @param int $idLang
     * @return bool
     */
    public static function isOrderAddressValid(int $idAddress, int $idLang = 0): bool
    {
        if ($idAddress <= 0) {
            return false;
        }

        $address = new Address($idAddress);
        if (!Validate::isLoadedObject($address) || (int) $address->deleted === 1) {
            return false;
        }

        if ((int) $address->id_country <= 0) {
            return false;
        }

        if ($idLang <= 0) {
            $idLang = (int) Context::getContext()->language->id ?: (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        $country = new Country((int) $address->id_country, $idLang);
        if (!Validate::isLoadedObject($country)) {
            return false;
        }

        // Verify country name exists for this language or any language to prevent Symfony GetOrderForViewingHandler crash
        if (is_array($country->name)) {
            if (empty($country->name[$idLang]) && empty(reset($country->name))) {
                return false;
            }
        } elseif (empty($country->name)) {
            return false;
        }

        // Extra check: confirm row exists in ps_address table directly
        $addressExists = (bool) Db::getInstance()->getValue(
            'SELECT `id_address` FROM `' . _DB_PREFIX_ . 'address` WHERE `id_address` = ' . (int) $idAddress
        );

        return $addressExists;
    }

    /**
     * Returns formatted details for an address ID.
     *
     * @param int $idAddress
     * @param int $idLang
     * @return array
     */
    public static function getAddressDisplayInfo(int $idAddress, int $idLang = 0): array
    {
        $isValid = self::isOrderAddressValid($idAddress, $idLang);
        if (!$isValid) {
            return [
                'id' => $idAddress,
                'is_valid' => false,
                'formatted' => $idAddress > 0 ? "ID #{$idAddress} - [NON TROVATO O NON VALIDO NEL DATABASE]" : "[NESSUN INDIRIZZO ASSEGNATO]",
            ];
        }

        $address = new Address($idAddress);
        $country = new Country((int) $address->id_country, $idLang);
        $cName = is_array($country->name) ? ($country->name[$idLang] ?? reset($country->name)) : $country->name;

        $parts = array_filter([
            $address->company,
            trim($address->firstname . ' ' . $address->lastname),
            $address->address1,
            $address->address2,
            $address->postcode . ' ' . $address->city,
            $cName,
        ]);

        return [
            'id' => $idAddress,
            'is_valid' => true,
            'formatted' => "ID #{$idAddress} - " . implode(', ', $parts),
        ];
    }

    /**
     * Gets all valid addresses for a customer.
     *
     * @param int $idCustomer
     * @param int $idLang
     * @return array
     */
    public static function getCustomerValidAddresses(int $idCustomer, int $idLang = 0): array
    {
        if ($idCustomer <= 0) {
            return [];
        }

        $rawAddresses = Db::getInstance()->executeS(
            'SELECT `id_address`, `alias` FROM `' . _DB_PREFIX_ . 'address` WHERE `id_customer` = ' . (int) $idCustomer . ' AND `deleted` = 0'
        ) ?: [];
        $validAddresses = [];

        foreach ($rawAddresses as $addrData) {
            $idAddr = (int) $addrData['id_address'];
            if (self::isOrderAddressValid($idAddr, $idLang)) {
                $info = self::getAddressDisplayInfo($idAddr, $idLang);
                $alias = !empty($addrData['alias']) ? " ({$addrData['alias']})" : '';
                $validAddresses[] = [
                    'id_address' => $idAddr,
                    'alias' => $addrData['alias'] ?? '',
                    'formatted' => $info['formatted'] . $alias,
                ];
            }
        }

        return $validAddresses;
    }
}
