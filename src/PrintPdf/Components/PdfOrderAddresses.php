<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Components;

class PdfOrderAddresses
{
    protected array $orderData;
    protected array $styles;

    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
        $this->styles = [
            'title_font_size' => 8,
            'title_font_style' => 'B',
            'address_font_size' => 7,
            'address_font_style' => '',
            'max_address_height' => 45,
            'border_color' => [200, 200, 200],
            'title_color' => [0, 0, 144],
            'text_color' => [0, 0, 0],
            'font_family' => 'dejavusans'
        ];
    }

    public function setStyles(array $styles): void
    {
        $this->styles = array_merge($this->styles, $styles);
    }

    public function render($pdf, float $x, float $y, float $totalWidth): float
    {
        $styles = $this->styles;
        $invoice = $this->orderData['invoice'] ?? $this->orderData['invoices']['invoice'] ?? $this->orderData;
        $customer = $invoice['customer'] ?? [];

        $addressDelivery = $customer['address_delivery'] ?? [];
        $addressInvoice = $customer['address_invoice'] ?? [];

        $colWidth = round($totalWidth / 2, 1);
        $col1Width = $colWidth;
        $col2Width = $colWidth;

        $pdf->SetDrawColor($styles['border_color'][0], $styles['border_color'][1], $styles['border_color'][2]);

        $height1 = $this->renderAddressColumn($pdf, $x, $y, $col1Width, 'INDIRIZZO DI CONSEGNA', $addressDelivery);
        $height2 = $this->renderAddressColumn($pdf, $x + $col1Width, $y, $col2Width, 'INDIRIZZO DI FATTURAZIONE', $addressInvoice);

        return max($height1, $height2);
    }

    public function renderAddressColumn($pdf, float $x, float $y, float $width, string $title, array $address): float
    {
        $styles = $this->styles;
        $currentY = $y;

        $pdf->SetFont($styles['font_family'], $styles['title_font_style'], $styles['title_font_size']);
        $pdf->SetTextColor($styles['title_color'][0], $styles['title_color'][1], $styles['title_color'][2]);
        $pdf->SetXY($x, $currentY);
        $pdf->Cell($width, 5, $title, 0, 1, 'L');
        $currentY += 5;

        $pdf->SetFont($styles['font_family'], $styles['address_font_style'], $styles['address_font_size']);
        $pdf->SetTextColor($styles['text_color'][0], $styles['text_color'][1], $styles['text_color'][2]);

        $maxHeight = $styles['max_address_height'];
        $addressHeight = $this->renderAddressLines($pdf, $x, $currentY, $width, $address, $maxHeight);
        $currentY += min($addressHeight, $maxHeight);

        return $currentY - $y;
    }

    protected function renderAddressLines($pdf, float $x, float $y, float $width, array $address, float $maxHeight = 0): float
    {
        if (empty($address)) {
            return 0;
        }

        $currentY = $y;
        $fontSize = $this->styles['address_font_size'];
        $lineHeight = $fontSize * 0.5;

        foreach ($address as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if ($key == 'subject' || $key == 'state' || $key == 'country') {
                continue;
            }

            if (($key == 'firstname' || $key == 'lastname') && !empty($address['company'])) {
                continue;
            }

            if ($key == 'firstname' && empty($address['company'])) {
                $value = ($address['firstname'] ?? '') . ' ' . ($address['lastname'] ?? '');
            }

            if ($key == 'company' && !empty($value)) {
                $value = '<b>' . $value . '</b>';
            } elseif ($key == 'address2' && empty($value)) {
                continue;
            } elseif (($key == 'header' || $key == 'state_name' || $key == 'country_name') && !empty($value)) {
                $value = '<b>' . $value . '</b>';
            } elseif ($key == 'phone' && !empty($value)) {
                $value = 'Telefono: <b>' . $value . '</b>';
            } elseif ($key == 'phone_mobile' && !empty($value)) {
                $value = 'Cellulare: <b>' . $value . '</b>';
            }

            if ($maxHeight > 0 && ($currentY - $y + $lineHeight) > $maxHeight) {
                $pdf->SetXY($x, $currentY);
                $pdf->writeHTMLCell($width, $lineHeight, $x, $currentY, '...', 0, 1, 0, true, 'L', true);
                $currentY = $pdf->GetY();
                break;
            }

            $pdf->SetXY($x, $currentY);
            $pdf->writeHTMLCell($width, $lineHeight, $x, $currentY, $value, 0, 1, 0, true, 'L', true);
            $currentY = $pdf->GetY();
        }

        return $currentY - $y;
    }
}
