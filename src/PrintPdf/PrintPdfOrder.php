<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderHeader;
use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderBody;
use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderFooter;

class PrintPdfOrder extends PrintManager
{
    protected function initComponents(): void
    {
        $header = new PdfOrderHeader(
            $this->orderData,
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

        $body = new PdfOrderBody($this->orderData, $this->order);

        $footer = new PdfOrderFooter([
            'show_separator' => true,
            'font_size' => 8,
            'bottom_margin' => 15
        ]);

        $this->setComponents($header, $body, $footer);
    }
}
