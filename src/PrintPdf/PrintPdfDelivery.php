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

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use Configuration;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\PrintTemplateFactory;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\DefaultOrderTemplate;

class PrintPdfDelivery extends PrintManager
{
    protected function initComponents(): void
    {
        $templateName = (string) Configuration::get('MPCUSTOMERINVOICE_TEMPLATE_DELIVERIES');
        if (empty($templateName)) {
            $templateName = 'Default';
        }

        $template = PrintTemplateFactory::createTemplate('Deliveries', $templateName, $this->idOrder);

        if (!$template) {
            $template = PrintTemplateFactory::createTemplate('Orders', $templateName, $this->idOrder);
        }

        if ($template) {
            $template->render($this);
        } else {
            $default = new DefaultOrderTemplate($this->idOrder);
            $default->render($this);
        }
    }
}
