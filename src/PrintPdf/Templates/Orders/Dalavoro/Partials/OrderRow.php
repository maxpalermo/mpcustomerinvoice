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

class OrderRow
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
        $product = $this->product;
        $pdf = $this->pdf;
        $i = $this->index;

        if (!$retry) {
            $pdf->startTransaction();
            $startPage = $pdf->getPage();
        }

        $product['location'] = $product['location'] ?: $this->getProductLocation($product['id_product']);
        $w = $this->getWidths();
        $colors = $this->getColors();
        $h = $this->getHeight();
        $fontSize = $this->getFontSize();
        $lineHeight = $this->getLineHeight();

        $fill = (0 == $i % 2) ? [0xFF, 0xFF, 0xFF] : [0xF6, 0xF6, 0xF6];
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);

        $blockHeight = $h * 3;

        // Thumbnail
        $imgDrawn = false;
        $coverPath = $this->getCover($product['id_product']);
        $pdf->Cell($w['thumb'], $blockHeight, '', 1, 0, 'C', 1);
        $x = $pdf->GetX() - $w['thumb'];
        $y = $pdf->GetY();
        $pdf->Image($coverPath, $x + 2, $y + 2, $w['thumb'] - 4, $blockHeight - 4, '', '', '', false, 300, '', false, false, 0, false, false, false);
        $imgDrawn = true;

        // Riga 1: dati principali
        $reference = $product['reference'] ?? '';
        $name = $this->truncateString($product['product_name'] ?? '', 40);
        $qty = $product['product_quantity'] ?? '';
        $price = $product['price_currency'] ?? '';
        $pdf->setFont('', 'B', 12);
        $pdf->Cell($w['reference'], $h, $reference, 'LTR', 0, 'L', 1);
        $pdf->setFont('', '', 10);
        $pdf->Cell($w['name'], $h, $name, 'LTR', 0, 'L', 1);

        // QUANTITÀ ORDINATA
        $pdf->setFontSize($fontSize + 5);
        $pdf->setFont('', 'B');
        $pdf->setTextColor(0x30, 0x30, 0x30);
        $pdf->setFillColor(0xFC, 0xFC, 0xFC);
        $pdf->Cell($w['qty'], $h, $qty, 'LTR', 0, 'C', 1);
        $pdf->setFont('', '');

        // PREZZO
        $pdf->setFontSize($fontSize + 2);
        $pdf->setFillColor(0xFF, 0xFF, 0xFF);
        $pdf->setTextColor(0x64, 0x64, 0x64);
        $pdf->Cell($w['price'], $h, $price, 'LTR', 1, 'R', 1);

        // Riga 2: combinazione, stock, sconto
        $pdf->Cell($w['thumb'], $h, '', 0, 0, '', 0);
        $pdf->SetFont('', '', $fontSize - 1);
        $pdf->SetTextColor(0x64, 0x64, 0x64);
        $pdf->Cell($w['reference'], $h, '', 'LR', 0, 'L', 1);

        // Visualizzo la combinazione
        $c = $colors['blue'];
        $pdf->SetTextColor($c[0], $c[1], $c[2]);
        $pdf->setFontSize($fontSize + 4);
        $pdf->setFont('', 'B');
        $pdf->Cell($w['name'], $h, isset($product['combination']) ? $product['combination'] : '', 'LR', 0, 'L', 1, '', 1);
        $pdf->setFontSize($fontSize + 2);
        $pdf->setFont('', '');
        $pdf->SetTextColor(0x80, 0x30, 0xB4);

        $pdf->setFontSize($fontSize + 4);
        $pdf->setFont('', 'B');

        // Visualizzo la quantità in magazzino in diversi colori
        $stock = (int) ($product['product_quantity_in_stock'] ?? 0);
        if ($stock <= 0) {
            $c = $colors['red'];
        } else {
            $c = $colors['green'];
        }
        $pdf->setFillColor(0xD5, 0xD5, 0xD5);
        $pdf->SetTextColor($c[0], $c[1], $c[2]);
        $pdf->Cell($w['qty'] / 2, $h, $stock, 'LR', 0, 'C', 1);
        $pdf->setFillColor(0xFF, 0xFF, 0xFF);

        // Visualizzo lo stock Service
        if ($product['stock_service']['is_stock_service']) {
            $c = $colors['blue'];
            $pdf->SetTextColor($c[0], $c[1], $c[2]);
            $stockService = (int) $product['stock_service']['quantity'];
        } else {
            $c = $colors['dark-gray'];
            $pdf->SetTextColor($c[0], $c[1], $c[2]);
            $stockService = '--';
        }
        $pdf->setFillColor(0xD5, 0xD5, 0xD5);
        $pdf->Cell($w['qty'] / 2, $h, $stockService, 'LR', 0, 'C', 1);
        $pdf->setFillColor(0xFF, 0xFF, 0xFF);

        // Visualizzo lo sconto
        $pdf->setFont('', '');
        $pdf->setFontSize($fontSize);
        $c = $colors['light-red'];
        $pdf->SetTextColor($c[0], $c[1], $c[2]);
        $discount = (!empty($product['reduction_percent']) ? "({$product['reduction_percent']} %)" : '');
        $pdf->Cell($w['price'], $h, $discount, 'LR', 1, 'R', 1);

        // Riga 3: locazione e data verifica
        $pdf->Cell($w['thumb'], $h, '', 0, 0, '', 0);
        $pdf->SetTextColor(0x30, 0x30, 0x30);
        $pdf->setFontSize($fontSize + 2);
        $pdf->setTextColor(0xA0, 0x30, 0x30);
        $pdf->setFont('', 'B');
        $pdf->Cell($w['location'], $h, isset($product['location']) ? $product['location'] : '', 'B', 0, 'L', 1);
        $pdf->setFont('', '');
        $pdf->setTextColor(0x30, 0x30, 0x30);

        $checkDate = !empty($product['check_date']) ? $product['check_date'] : '';
        $pdf->SetTextColor(0x30, 0x30, 0x30);
        $pdf->setFontSize($fontSize - 2);
        $pdf->setFont('', '');
        $pdf->Cell($w['stock_Service'], $h, $checkDate, 'LRB', 1, 'R', 1);
        $pdf->setFont('', '');
        // Reset font
        $pdf->SetFont('', '', $fontSize);
        $pdf->SetTextColor(0x28, 0x28, 0x28);

        if (!$retry) {
            if ($pdf->getPage() !== $startPage) {
                $pdf->rollbackTransaction(true);
                $pdf->AddPage();
                $this->writeHeaderOrderNum($pdf, $this->id_order);
                $this->renderHeader();
                $this->renderRow(true);

                return;
            }

            $pdf->commitTransaction();
        }
    }

    public function renderHeader()
    {
        $h = $this->getHeight();
        $w = $this->getWidths();
        $pdf = $this->pdf;

        $pdf->SetFont('helvetica', 'B', $this->fontSize + 1);
        $pdf->SetFillColor(136, 136, 136);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($w['thumb'], $h, 'IMM', 1, 0, 'C', 1);
        $pdf->Cell($w['reference'], $h, 'Riferimento', 1, 0, 'C', 1);
        $pdf->Cell($w['name'], $h, 'Prodotto', 1, 0, 'C', 1);
        $pdf->Cell($w['qty'], $h, 'Quantità', 1, 0, 'C', 1);
        $pdf->Cell($w['price'], $h, 'Prezzo', 1, 1, 'C', 1);
        // Ripristina font e colori per la riga successiva (riga dati)
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', '', $this->fontSize);
        $pdf->SetFillColor(255, 255, 255);
    }

    protected function getProductLocation($idProduct)
    {
        $iconClose = html_entity_decode('&#xf00d;', ENT_NOQUOTES, 'UTF-8');

        $db = \Db::getInstance();
        $pfx = _DB_PREFIX_;
        $query = "
            SELECT 
                location
            FROM {$pfx}product_location
            WHERE 
                id_product = {$idProduct}
        ";
        $result = $db->getValue($query);
        return $result ?: '--';
    }

    protected function getCover($idProduct)
    {
        $imageCover = \Product::getCover($idProduct);
        $imagePath = _PS_IMG_DIR_ . 'p/404.gif';
        if ($imageCover) {
            $image = new \Image($imageCover['id_image']);
            if (\Validate::isLoadedObject($image)) {
                $tmpPath = _PS_IMG_DIR_ . 'p/' . $image->getImgPath() . '.jpg';
                if (file_exists($tmpPath)) {
                    $imagePath = $tmpPath;
                }
            }
        }

        return $imagePath;
    }
}
