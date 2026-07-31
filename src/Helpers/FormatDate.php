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

use Context;
use DateTime;
use Tools;

class FormatDate
{
    private string $date;
    private string $locale_iso_code;
    private array $localFormats = [
        'en' => 'Y-m-d H:i:s',
        'it' => 'd/m/Y H:i:s',
    ];

    public function __construct(string $date)
    {
        $this->date = $date;
        $context = Context::getContext();
        $this->locale_iso_code = (string) ($context->language->iso_code ?? 'it');
    }

    public function toLocalDate(string $iso_code = ''): string
    {
        if (!$iso_code) {
            $iso_code = Tools::strtolower($this->locale_iso_code);
        }
        $format = $this->localFormats[$iso_code] ?? 'd/m/Y H:i:s';

        try {
            $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $this->date);
            if ($dateObj) {
                return $dateObj->format($format);
            }
        } catch (\Exception $e) {
            // fallback
        }

        return $this->date;
    }
}
