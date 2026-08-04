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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Invoices\Dalavoro\Traits;

trait Common
{
    protected function getColors(): array
    {
        return [
            'black' => [0, 0, 0],
            'dark' => [40, 40, 40],
            'gray' => [120, 120, 120],
            'light-gray' => [230, 230, 230],
            'table-header' => [240, 240, 240],
            'alt-row' => [250, 250, 250],
            'white' => [255, 255, 255],
        ];
    }

    protected function formatPrice(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    protected function formatPercent(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' %';
    }

    protected function getShopLogo(): string
    {
        $logo = \Configuration::get('PS_LOGO');
        if (!$logo) {
            return '';
        }

        return '/img/' . $logo;
    }
}
