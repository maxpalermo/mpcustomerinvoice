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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro;

use MpSoft\MpCustomerInvoice\PrintPdf\PrintManager;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\PrintTemplateInterface;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Pdf\PdfOrder;

class DalavoroOrderTemplate implements PrintTemplateInterface
{
    protected int $idOrder;

    public function __construct(int $idOrder = 0)
    {
        $this->idOrder = $idOrder;
    }

    public function render(PrintManager $pdf): void
    {
        $idOrder = $this->idOrder > 0 ? $this->idOrder : $pdf->getIdOrder();
        if ($idOrder <= 0) {
            return;
        }

        $dalavoroBody = new class($idOrder) {
            private int $idOrder;

            public function __construct(int $idOrder)
            {
                $this->idOrder = $idOrder;
            }

            public function render($pdf): void
            {
                $pdfOrder = new PdfOrder($this->idOrder);
                $pdfOrder->create();

                if (method_exists($pdfOrder, 'renderToPdf')) {
                    $pdfOrder->renderToPdf($pdf);
                } else {
                    $pdfOrder->render();
                }
            }
        };

        // Set Dalavoro body component and clear default header/footer
        $pdf->setComponents(null, $dalavoroBody, null);
    }
}
