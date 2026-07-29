<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Components;

class PdfOrderInfo
{
    protected array $orderData;
    protected array $styles;

    public function __construct(array $orderData)
    {
        $invoice = $orderData['invoice'] ?? [];
        $customer = $invoice['customer'] ?? [];

        $this->orderData = [
            'cod_cliente' => $customer['id'] ?? '--',
            'id_customer' => $customer['id'] ?? '--',
            'new_customer' => $customer['new'] ?? true,
            'data_ordine' => $this->formatDate($invoice['order_date'] ?? date('Y-m-d H:i:s')),
            'totale_ordine' => $this->formatPrice($invoice['total_tax_incl'] ?? '0.00'),
        ];

        $this->styles = [
            'header_font_size' => 10,
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
            return date('d/m/Y');
        }
        return date('d/m/Y', strtotime($date));
    }

    protected function formatPrice($price): string
    {
        return '€ ' . number_format((float) $price, 2, ',', '.');
    }

    public function setStyles(array $styles): void
    {
        $this->styles = array_merge($this->styles, $styles);
    }

    public function renderAlignedLeft($pdf, float $marginTop, float $width, float $leftMargin = 10): float
    {
        $x = $leftMargin;
        $y = $marginTop;

        return $this->render($pdf, $x, $y, $width);
    }

    public function render($pdf, float $x, float $y, float $width): float
    {
        $styles = $this->styles;
        $data = $this->orderData;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor($styles['border_color'][0], $styles['border_color'][1], $styles['border_color'][2]);

        $currentY = $y;
        $rowHeight = 12;

        $codiceCliente = $data['cod_cliente'];
        $isNewCustomer = $data['new_customer'];
        $this->renderSingleCell($pdf, $x, $currentY, $width, 'COD. CLIENTE', $codiceCliente, $styles);
        if (!$isNewCustomer) {
            $pdf->SetTextColor(255, 0, 0);
            $pdf->SetFont($styles['font_family'], 'B', 14);
            $pdf->SetXY($x + $width - 15, $currentY + 8);
            $pdf->Cell(10, 6, 'V', 0, 0, 'C', 0, '', 1, false, 'C', 'M');
            $pdf->SetTextColor(0, 0, 0);
        }
        $currentY += $rowHeight;

        $this->renderSingleCell($pdf, $x, $currentY, $width, 'DATA ORDINE', $data['data_ordine'], $styles);
        $currentY += $rowHeight;

        $this->renderSingleCell($pdf, $x, $currentY, $width, 'TOTALE ORDINE', $data['totale_ordine'], $styles, true);
        $currentY += $rowHeight;

        return $currentY - $y;
    }

    protected function renderSingleCell($pdf, float $x, float $y, float $width, string $label, string $value, array $styles, bool $boldValue = false): void
    {
        $rowHeight = 12;
        $labelHeight = 4;
        $valueHeight = 6;
        $offsetY = 2;

        $pdf->Rect($x, $y, $width, $rowHeight);

        $pdf->SetXY($x, $y + $offsetY);
        $pdf->SetFont($styles['font_family'], 'B', 8);
        $pdf->Cell($width, $labelHeight, $label, 0, 2, 'C', 0, '', 1, false, 'C', 'M');

        $fontStyle = $boldValue ? 'B' : '';
        $pdf->SetFont($styles['font_family'], $fontStyle, 14);
        $pdf->SetXY($x, $y + 8);

        $pdf->Cell($width, $valueHeight, $value, 0, 0, 'C', 0, '', 1, false, 'C', 'M');
    }
}
