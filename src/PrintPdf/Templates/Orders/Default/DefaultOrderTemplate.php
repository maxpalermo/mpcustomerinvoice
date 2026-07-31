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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default;

use MpSoft\MpCustomerInvoice\PrintPdf\PrintManager;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\PrintTemplateInterface;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\Components\PdfOrderHeader;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\Components\PdfOrderBody;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\Components\PdfOrderFooter;

class DefaultOrderTemplate implements PrintTemplateInterface
{
    protected int $idOrder;

    public function __construct(int $idOrder = 0)
    {
        $this->idOrder = $idOrder;
    }

    public function render(PrintManager $pdf): void
    {
        $orderData = $pdf->getOrderData();
        $orderObj = $pdf->getOrder();

        $header = new PdfOrderHeader(
            $orderData,
            [
                'logo_x' => 20,
                'logo_y' => 10,
                'logo_width' => 80,
                'logo_height' => 0,
                'table_width' => 100,
                'table_right_margin' => 10,
                'table_top_margin' => 10
            ]
        );

        $body = new PdfOrderBody($orderData, $orderObj);

        $footer = new PdfOrderFooter([
            'show_separator' => true,
            'font_size' => 8,
            'bottom_margin' => 15
        ]);

        $pdf->setComponents($header, $body, $footer);
    }
}
