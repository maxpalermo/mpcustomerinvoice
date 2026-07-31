<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\Components;

use Configuration;

class PdfOrderHeader
{
    protected array $orderData;
    protected array $config;
    protected string $logoPath;
    protected PdfHeaderRight $headerRight;

    public function __construct(array $orderData, array $config = [])
    {
        $this->orderData = $orderData;
        $this->config = array_merge([
            'logo_x' => 20,
            'logo_y' => 10,
            'logo_width' => 80,
            'logo_height' => 0,
            'table_width' => 100,
            'table_right_margin' => 10,
            'table_top_margin' => 10
        ], $config);

        $this->logoPath = $this->getLogoPath();
        $this->headerRight = new PdfHeaderRight($this->orderData);
    }

    public function render($pdf): void
    {
        $this->renderLogo($pdf);
        $this->renderTable($pdf);
    }

    protected function renderLogo($pdf): void
    {
        $x = $this->config['logo_x'];
        $y = $this->config['logo_y'];
        $width = $this->config['logo_width'];
        $height = $this->config['logo_height'];

        if (file_exists($this->logoPath)) {
            $pdf->Image(
                $this->logoPath,
                $x,
                $y,
                $width,
                $height,
                'JPG',
                '',
                'T',
                false,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        } else {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($x, $y + 10);
            $pdf->Cell($width, 10, 'LOGO', 0, 0, 'C');
        }
    }

    protected function renderTable($pdf): void
    {
        $tableWidth = $this->config['table_width'];
        $rightMargin = $this->config['table_right_margin'];
        $topMargin = $this->config['table_top_margin'];

        $this->headerRight->renderAlignedRight($pdf, $topMargin, $tableWidth, $rightMargin);
    }

    protected function getLogoPath(): string
    {
        $logo = Configuration::get('PS_LOGO');
        $path = _PS_ROOT_DIR_ . '/img/' . $logo;
        if (file_exists($path)) {
            return $path;
        }
        $altPath = _PS_IMG_DIR_ . $logo;
        if (file_exists($altPath)) {
            return $altPath;
        }
        return _PS_IMG_DIR_ . 'logo.jpg';
    }
}
