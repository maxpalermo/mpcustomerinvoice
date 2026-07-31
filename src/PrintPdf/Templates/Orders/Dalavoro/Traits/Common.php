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
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Traits;

use \TCPDF;

trait Common
{
    protected static $TCPDF_NO_BORDER = 0;
    protected static $TCPDF_BORDER = 1;
    protected static $TCPDF_NO_FILL = 0;
    protected static $TCPDF_FILL = 1;
    protected static $TCPDF_NO_LN = 0;
    protected static $TCPDF_LN = 1;
    protected static $TCPDF_NO_RESET_HEIGHT = 0;
    protected static $TCPDF_RESET_HEIGHT = 1;
    protected static $TCPDF_NO_STRETCH = 0;
    protected static $TCPDF_STRETCH = 1;
    protected static $TCPDF_IS_NOT_HTML = 0;
    protected static $TCPDF_IS_HTML = 1;
    protected static $TCPDS_NO_AUTOPADDING = 0;
    protected static $TCPDF_AUTOPADDING = 1;
    protected static $TCPDF_NO_FITCELL = 0;
    protected static $TCPDF_FITCELL = 1;

    protected $w = [
        'thumb' => 20,
        'reference' => 60,
        'name' => 50,
        'qty' => 30,
        'price' => 30,
        'location' => 110,
        'stock_Service' => 60,
    ];

    protected $height = 7;
    protected $fontSize = 9;
    protected $lineHeight = 4.5;

    protected function getWidths()
    {
        return [
            'thumb' => 20,
            'reference' => 50,
            'name' => 60,
            'qty' => 30,
            'price' => 30,
            'location' => 110,
            'stock_Service' => 60,
            'label' => 60,
            'value' => 100,
        ];
    }

    protected function getColors()
    {
        return [
            'red' => [0xC8, 0x1E, 0x1E],
            'light-red' => [0xB0, 0x64, 0x64],
            'green' => [0x64, 0xB0, 0x64],
            'blue' => [0x1E, 0x1E, 0xC8],
            'yellow' => [0xFF, 0xFF, 0x0],
            'dark-yellow' => [0xC0, 0xC0, 0x0],
            'orange' => [0xFF, 0xA5, 0x0],
            'purple' => [0x80, 0x0, 0x80],
            'pink' => [0xFF, 0xC0, 0xCB],
            'brown' => [0xA5, 0x2A, 0x2A],
            'gray' => [0x80, 0x80, 0x80],
            'dark-gray' => [0x40, 0x40, 0x40],
        ];
    }

    protected function truncateString($string, $length, $html = false)
    {
        if (strlen($string) > $length) {
            if ($html) {
                // Grabs the original and escapes any quotes
                $original = str_replace('"', '"', $string);
            }

            // Truncates the string
            $string = substr($string, 0, $length);

            // Appends ellipses and optionally wraps in a hoverable span
            if ($html) {
                $string = '<span title="' . $original . '">' . $string . '…</span>';
            } else {
                $string .= '...';
            }
        }

        return $string;
    }

    protected function writeHeaderOrderNum(TCPDF $pdf, $id_order)
    {
        $currentX = $pdf->getX();
        $currentY = $pdf->getY();
        $orderId = (int) $id_order;

        // Riga 0: TITOLO ORDINE
        $pdf->SetX(0);
        $pdf->SetY(2);
        $pdf->setFont('helvetica', 'B', 20);
        $pdf->Cell($pdf->getPageWidth() - 20, 5.5, "Ordine: $orderId", 0, 0, 'R', 0);

        $pdf->SetX($currentX);
        $pdf->SetY($currentY);
    }

    protected function writeHeaderCustomization(TCPDF $pdf)
    {
        $pdf->setFontSize(16);
        $pdf->setFont('', 'B');
        $pdf->Cell(0, 8, 'Personalizzazioni', 0, 0, 'L');
        $pdf->Ln();
        $pdf->setFontSize($this->getFontSize());
        $pdf->setFont('', '');
    }

    protected function writeHeaderMessages(TCPDF $pdf)
    {
        $pdf->setFontSize(16);
        $pdf->setFont('', 'B');
        $pdf->Cell(0, 8, 'Messaggi', 0, 0, 'L');
        $pdf->Ln();
        $pdf->setFontSize($this->getFontSize());
        $pdf->setFont('', '');
    }

    protected function writeHeaderMessagesTable(TCPDF $pdf)
    {
        // Intestazione tabella
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('', 'B');
        $pdf->Cell(50, 8, 'IMPIEGATO', 1, 0, 'C', 1);
        $pdf->Cell(35, 8, 'DATA', 1, 0, 'C', 1);
        $pdf->Cell(85, 8, 'MESSAGGIO', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'CHAT', 1, 1, 'C', 1);
        $pdf->SetFont('', '');
        $pdf->SetFillColor(255, 255, 230);
    }

    protected function getHeight()
    {
        return $this->height;
    }

    protected function getFontSize()
    {
        return $this->fontSize;
    }

    protected function getLineHeight()
    {
        return $this->lineHeight;
    }

    public function getPdfX()
    {
        return $this->pdf->getX();
    }

    public function getPdfY()
    {
        return $this->pdf->getY();
    }
}
