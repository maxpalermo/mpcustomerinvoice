<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderHeader;
use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderBody;
use MpSoft\MpCustomerInvoice\PrintPdf\Components\PdfOrderFooter;

class PrintPdfDelivery extends PrintManager
{
    protected function initComponents(): void
    {
        $header = new PdfOrderHeader($this->orderData);
        $body = new PdfOrderBody($this->orderData, $this->order);
        $footer = new PdfOrderFooter();

        $this->setComponents($header, $body, $footer);
    }
}
