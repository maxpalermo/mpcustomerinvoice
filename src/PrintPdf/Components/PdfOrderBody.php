<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Components;

use Context;
use Order;

class PdfOrderBody
{
    protected array $orderData;
    protected ?Order $order;
    protected int $idLang = 1;
    protected int $idShop = 1;

    public function __construct(array $orderData, ?Order $order = null)
    {
        $this->orderData = $orderData;
        $this->order = $order;
        $context = Context::getContext();
        $this->idLang = (int) ($context->language->id ?? 1);
        $this->idShop = (int) ($context->shop->id ?? 1);
    }

    public function render($pdf): void
    {
        $pdf->SetFont('helvetica', '', 12);
        $this->renderAddresses($pdf);

        $pdf->SetFont('helvetica', '', 8);
        $this->renderProducts($pdf);
    }

    protected function renderAddresses($pdf): void
    {
        $startY = $pdf->GetY();
        $pageWidth = $pdf->getPageWidth() - 30;

        $addresses = new PdfOrderAddresses($this->orderData);
        $addresses->render($pdf, 15, $startY, $pageWidth * 2 / 3);

        $col3X = 15 + $pageWidth * 2 / 3;
        $col3W = $pageWidth * 1 / 3;

        $orderInfo = new PdfOrderInfo($this->orderData);
        $orderInfo->render($pdf, $col3X, $startY, $col3W);

        $pdf->SetY($startY + 60);
    }

    protected function renderProducts($pdf): void
    {
        // Layout prodotti standard
    }
}
