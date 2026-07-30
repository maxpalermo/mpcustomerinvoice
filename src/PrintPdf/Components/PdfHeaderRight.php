<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Components;

use TCPDF;

class PdfHeaderRight
{
    protected array $orderData;
    protected array $styles;

    public function __construct(array $orderData)
    {
        $invoice = $orderData['invoice'] ?? $orderData['invoices']['invoice'] ?? $orderData;

        $this->orderData = [
            'id_order' => $invoice['order_id'] ?? $invoice['invoice_id'] ?? '--',
            'metodo_pagamento' => !empty($invoice['payment']) ? strtoupper($invoice['payment']) : '--',
            'data_stampa' => date('d/m/Y H:i:s'),
            'corriere' => $invoice['carrier'] ?? '--',
            'stato' => $invoice['current_status'] ?? '--',
            'current_status' => $invoice['current_status'] ?? '',
        ];

        $this->styles = [
            'header_font_size' => 13,
            'header_font_style' => 'B',
            'cell_font_size' => 9,
            'cell_font_style' => '',
            'border_color' => [200, 200, 200],
            'header_bg_color' => [248, 248, 248],
            'cell_bg_color' => [255, 255, 255],
            'font_family' => 'helvetica'
        ];
    }

    protected function formatDate(?string $date): string
    {
        if (empty($date)) {
            return date('d/m/Y H:i:s');
        }
        return date('d/m/Y H:i:s', strtotime($date));
    }

    public function setStyles(array $styles): void
    {
        $this->styles = array_merge($this->styles, $styles);
    }

    public function renderAlignedRight($pdf, float $marginTop, float $width, float $rightMargin = 10): float
    {
        $pageWidth = $pdf->getPageWidth();
        $x = $pageWidth - $width - $rightMargin;
        $y = $marginTop;

        return $this->render($pdf, $x, $y, $width);
    }

    public function render($pdf, float $x, float $y, float $width): float
    {
        $styles = $this->styles;
        $data = $this->orderData;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor($styles['border_color'][0], $styles['border_color'][1], $styles['border_color'][2]);

        $col1Width = $width * 0.5;
        $col2Width = $width * 0.5;

        $currentY = $y;

        $headerRowHeight = 12;
        $pdf->SetFont($styles['font_family'], 'B', $styles['header_font_size']);
        $pdf->SetFillColor($styles['header_bg_color'][0], $styles['header_bg_color'][1], $styles['header_bg_color'][2]);
        $pdf->SetXY($x, $currentY);
        $pdf->Cell($width, $headerRowHeight, 'ORDINE N. ' . $data['id_order'], 1, 1, 'C', 1);
        $currentY += $headerRowHeight;

        $h1 = $this->renderCell($pdf, $x, $currentY, $col1Width, 'METODO DI PAGAMENTO', $data['metodo_pagamento'], $styles);
        $h2 = $this->renderCell($pdf, $x + $col1Width, $currentY, $col2Width, 'DATA DI STAMPA', $data['data_stampa'], $styles, true);
        $currentY += max($h1, $h2);

        $h1 = $this->renderCell($pdf, $x, $currentY, $col1Width, 'CORRIERE', $data['corriere'], $styles);
        $h2 = $this->renderCell($pdf, $x + $col1Width, $currentY, $col2Width, 'STATO CORRENTE', $data['stato'], $styles);
        $currentY += max($h1, $h2);

        return $currentY - $y;
    }

    protected function renderCell($pdf, float $x, float $y, float $width, string $label, string $value, array $styles, bool $bold = false): float
    {
        $cellHeight = 7.5;
        $labelHeight = 3.5;
        $valueHeight = 4;
        $offsetY = 1.3;

        $pdf->Rect($x, $y, $width, $cellHeight);

        $pdf->SetXY($x, $y + $offsetY);
        $pdf->SetFont($styles['font_family'], 'B', 7);
        $pdf->Cell($width, $labelHeight, $label, 0, 0, 'C', 0, '', 1, false, 'C', 'M');

        $pdf->SetXY($x, $y + $offsetY + $labelHeight);
        $fontStyle = $bold ? 'B' : '';
        $pdf->SetFont($styles['font_family'], $fontStyle, 8);
        $pdf->Cell($width, $valueHeight, $value, 0, 0, 'C', 0, '', 1, false, 'C', 'M');

        return $cellHeight;
    }
}
