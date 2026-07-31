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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Partials;

use \TCPDF;

class OrderCustomization
{
    protected $id_order;
    protected $pdf;
    protected $product;
    protected $index;

    use \MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Traits\Common;

    public function __construct($id_order, TCPDF &$pdf, array $product = null, int $index = null)
    {
        $this->id_order = $id_order;
        $this->pdf = $pdf;
        $this->product = $product;
        $this->index = $index;
    }

    public function renderRow($retry = false)
    {
        // Definizione larghezze colonne (mm)
        // Larghezze colonna per coprire tutta la pagina (A4, margini 10mm -> 190mm)
        $w = $this->getWidths();
        $colors = $this->getColors();
        $h = $this->getHeight();
        $fontSize = $this->getFontSize();
        $pdf = $this->pdf;
        $product = $this->product;

        if (!$retry) {
            $pdf->startTransaction();
            $startPage = $pdf->getPage();
        }

        if ($this->index == 0 && !$retry) {
            $this->writeHeaderCustomization($this->pdf);
        }

        // Controllo che sia un allegato con immagine
        $imgPath = '';

        if (!isset($product['customizations']) || empty($product['customizations'])) {
            if (!$retry) {
                $pdf->commitTransaction();
            }
            return;
        }

        foreach ($product['customizations'] as $customizations) {
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();

            // Tipo di personalizzazione
            $pdf->SetX($startX);
            $pdf->SetY($pdf->GetY());
            $c = $colors['blue'];
            $pdf->setTextColor($c[0], $c[1], $c[2]);
            $pdf->Cell($w['label'], $h, 'Tipo di Personalizzazione', '', 0, 'L', 0, '', 1);
            $pdf->setFont('', 'B');
            $pdf->MultiCell($w['value'], $h, $product['combination'], self::$TCPDF_NO_BORDER, 'L', self::$TCPDF_NO_FILL, self::$TCPDF_LN, $pdf->GetX(), $pdf->GetY() + 1, self::$TCPDF_RESET_HEIGHT, self::$TCPDF_NO_STRETCH, self::$TCPDF_IS_NOT_HTML, self::$TCPDF_AUTOPADDING, 0, 'T', self::$TCPDF_NO_FITCELL);
            $pdf->setFont('', '');

            // Quantità
            $pdf->SetX($startX);
            $pdf->SetY($pdf->GetY() - 2);
            $c = $colors['blue'];
            $pdf->setTextColor($c[0], $c[1], $c[2]);
            $pdf->Cell($w['label'], $h, 'Quantità', '', 0, 'L', 0, '', 1);
            $pdf->setFont('', 'B');
            $pdf->MultiCell($w['value'], $h, $product['product_quantity'], self::$TCPDF_NO_BORDER, 'L', self::$TCPDF_NO_FILL, self::$TCPDF_LN, $pdf->GetX(), $pdf->GetY() + 1, self::$TCPDF_RESET_HEIGHT, self::$TCPDF_NO_STRETCH, self::$TCPDF_IS_NOT_HTML, self::$TCPDF_AUTOPADDING, 0, 'T', self::$TCPDF_NO_FITCELL);
            $pdf->setFont('', '');

            foreach ($customizations['data'] as $customization) {
                $pdf->SetFont('', '', $fontSize - 1);
                $c = $colors['dark-gray'];
                $pdf->SetTextColor($c[0], $c[1], $c[2]);

                $pdf->SetX($startX + 35);
                $pdf->SetY($pdf->GetY() - 2);
                $pdf->Cell($w['label'], $h, $customization['label'], self::$TCPDF_NO_BORDER, self::$TCPDF_NO_LN, 'L', self::$TCPDF_NO_FILL, '', self::$TCPDF_STRETCH);
                $pdf->setFont('', 'B');
                $pdf->MultiCell($w['value'], $h, $customization['value'], self::$TCPDF_NO_BORDER, 'L', self::$TCPDF_NO_FILL, self::$TCPDF_LN, $pdf->GetX(), $pdf->GetY() + 1, self::$TCPDF_RESET_HEIGHT, self::$TCPDF_NO_STRETCH, self::$TCPDF_IS_NOT_HTML, self::$TCPDF_AUTOPADDING, 0, 'T', self::$TCPDF_NO_FITCELL);
                $pdf->setFont('', '');
            }

            foreach ($customizations['data'] as $customization) {
                if ($customization['hasFile']) {
                    $imgPath = _PS_UPLOAD_DIR_ . $customization['value'];
                    if (!empty($imgPath)) {
                        if (@file_exists($imgPath)) {
                            $pdf->Image($imgPath, $startX + 160, $startY + 2, 30, 30, '', '', '', true, 300, '', false, false, 1, 'CM', false, true);
                        }
                    }
                }
            }

            $c = $colors['dark-gray'];
            $pdf->setTextColor($c[0], $c[1], $c[2]);
        }

        // linea di divisione
        $pdf->Line($startX, $pdf->GetY() + 2, $startX + 190, $pdf->GetY() + 2);
        $pdf->setY($pdf->getY() + 2);

        if (!$retry) {
            if ($pdf->getPage() !== $startPage) {
                $pdf->rollbackTransaction(true);
                $pdf->AddPage();
                $this->writeHeaderOrderNum($pdf, $this->id_order);
                $this->writeHeaderCustomization($pdf);
                $this->renderRow(true);

                return;
            }

            $pdf->commitTransaction();
        }
    }
}
