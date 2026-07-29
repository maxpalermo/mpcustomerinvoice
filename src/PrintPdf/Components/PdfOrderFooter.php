<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Components;

class PdfOrderFooter
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'show_separator' => true,
            'font_size' => 8,
            'font_family' => 'helvetica',
            'bottom_margin' => 15,
            'separator_color' => [0, 0, 0]
        ], $config);
    }

    public function render($pdf): void
    {
        $pdf->SetY(-$this->config['bottom_margin']);

        if ($this->config['show_separator']) {
            $pdf->writeHTML("<hr>");
        }

        $pdf->SetFont(
            $this->config['font_family'],
            '',
            $this->config['font_size']
        );

        $pdf->SetY(-10);
        $pdf->Cell(
            0,
            10,
            'Pagina ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(),
            0,
            false,
            'C',
            0,
            '',
            0,
            false,
            'T',
            'M'
        );
    }
}
