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

        $pdf->SetY($startY + 55);
    }

    protected function renderProducts($pdf): void
    {
        $invoice = $this->orderData['invoice'] ?? $this->orderData['invoices']['invoice'] ?? $this->orderData;
        $rowsData = $invoice['rows']['row'] ?? [];
        if (!empty($rowsData) && isset($rowsData['reference'])) {
            $rowsData = [$rowsData];
        }

        if (empty($rowsData)) {
            return;
        }

        $pdf->SetDrawColor(200, 200, 200);

        // Definizione larghezze colonne (totale 180mm)
        $wImg = 16;
        $wRef = 52;
        $wProd = 55;
        $wQty = 14;
        $wMag = 14;
        $wArr = 14;
        $wPrice = 15;

        // Intestazione tabella
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(255, 255, 255);

        $pdf->Cell($wImg, 7, 'IMG', 'B', 0, 'C');
        $pdf->Cell($wRef, 7, 'RIFERIMENTO', 'B', 0, 'L');
        $pdf->Cell($wProd, 7, 'PRODOTTO', 'B', 0, 'L');
        $pdf->Cell($wQty, 7, 'QTA', 'B', 0, 'C');
        $pdf->Cell($wMag, 7, 'MAG', 'B', 0, 'C');
        $pdf->Cell($wArr, 7, 'ARR', 'B', 0, 'C');
        $pdf->Cell($wPrice, 7, 'PREZZO', 'B', 1, 'R');

        $pdf->Ln(2);

        foreach ($rowsData as $product) {
            $rowHeight = 22;
            if ($pdf->GetY() + $rowHeight > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell($wImg, 7, 'IMG', 'B', 0, 'C');
                $pdf->Cell($wRef, 7, 'RIFERIMENTO', 'B', 0, 'L');
                $pdf->Cell($wProd, 7, 'PRODOTTO', 'B', 0, 'L');
                $pdf->Cell($wQty, 7, 'QTA', 'B', 0, 'C');
                $pdf->Cell($wMag, 7, 'MAG', 'B', 0, 'C');
                $pdf->Cell($wArr, 7, 'ARR', 'B', 0, 'C');
                $pdf->Cell($wPrice, 7, 'PREZZO', 'B', 1, 'R');
                $pdf->Ln(2);
            }

            $currentY = $pdf->GetY();
            $currentX = 15;

            // 1. IMG
            $imageUrl = $product['image_url'] ?? '';
            $imgPath = '';
            if ($imageUrl && file_exists($imageUrl)) {
                $imgPath = $imageUrl;
            }

            if ($imgPath) {
                $pdf->Image($imgPath, $currentX + 1, $currentY + 1, 14, 18, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            $currentX += $wImg;

            // 2. RIFERIMENTO (Riferimento in nero, Posizione in rosso, Attributi in blu)
            $ref = $product['reference'] ?? '';

            $posStr = $product['location'] ?? $product['product_position'] ?? '';
            if (is_array($posStr)) {
                $posParts = array_filter([
                    $posStr['warehouse'] ?? '',
                    $posStr['shelf'] ?? '',
                    $posStr['col'] ?? '',
                    $posStr['level'] ?? ''
                ]);
                $posStr = implode('#', $posParts);
            } else if (is_string($posStr)) {
                $posStr = trim($posStr);
            }

            if (empty($posStr) && !empty($product['product_id'])) {
                $idProd = (int) $product['product_id'];
                $idAttr = (int) ($product['product_attribute_id'] ?? 0);
                if (class_exists('\StockAvailable')) {
                    $posStr = \StockAvailable::getLocation($idProd, $idAttr);
                    if (empty($posStr) && $idAttr > 0) {
                        $posStr = \StockAvailable::getLocation($idProd, 0);
                    }
                }
                if (empty($posStr)) {
                    $posStr = (string) \Db::getInstance()->getValue('SELECT location FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . $idProd);
                }
            }

            $attrStr = '';
            $size = $product['size'] ?? '';
            $color = $product['color'] ?? '';
            if ($size || $color) {
                $attrParts = array_filter([$size, $color, 'Monocolore']);
                $attrStr = implode(' - ', $attrParts);
            } else if (!empty($product['attributes']) && is_array($product['attributes'])) {
                $attrs = [];
                foreach ($product['attributes'] as $at) {
                    if (isset($at['name'])) {
                        $attrs[] = $at['name'];
                    }
                }
                $attrStr = implode(' - ', $attrs);
            }

            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($wRef, 4, $ref, 0, 1, 'L');

            // 1. COMBINAZIONE / ATTRIBUTI (In BLU sotto il riferimento)
            if ($attrStr) {
                $pdf->SetX($currentX);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(0, 85, 184);
                $pdf->Cell($wRef, 4, $attrStr, 0, 1, 'L');
            }

            // 2. LOCATION / POSIZIONE MAGAZZINO (In ROSSO sotto la combinazione)
            if ($posStr) {
                $pdf->SetX($currentX);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(180, 40, 40);
                $pdf->Cell($wRef, 4, $posStr, 0, 1, 'L');
            }
            $pdf->SetTextColor(0, 0, 0);
            $currentX += $wRef;

            // 3. PRODOTTO (Nome in MAIUSCOLO)
            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', '', 7.5);
            $prodName = strtoupper($product['product_name'] ?? '');
            $pdf->MultiCell($wProd, 4, $prodName, 0, 'L');
            $currentX += $wProd;

            // 4. QTA (Blu grassetto)
            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 85, 184);
            $pdf->Cell($wQty, 5, (string) ($product['qty'] ?? '1'), 0, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $currentX += $wQty;

            // 5. MAG (Rosso grassetto)
            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(180, 40, 40);
            $pdf->Cell($wMag, 5, (string) ($product['product_in_stock'] ?? '0'), 0, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $currentX += $wMag;

            // 6. ARR
            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', '', 8);
            $checkInfo = $product['product_check_qty'] ?? [];
            $pdf->Cell($wArr, 5, '--', 0, 1, 'C');

            if (!empty($checkInfo['is_checked']) && $checkInfo['is_checked'] == '1') {
                $pdf->SetX($currentX - 10);
                $pdf->SetFont('helvetica', 'I', 5.5);
                $pdf->SetTextColor(100, 100, 100);
                $dateChecked = $checkInfo['date_checked'] ?? '';
                $pdf->Cell($wArr + 10, 3, 'Verificato il ' . $dateChecked, 0, 0, 'C');
                $pdf->SetTextColor(0, 0, 0);
            }
            $currentX += $wArr;

            // 7. PREZZO
            $pdf->SetXY($currentX, $currentY + 1);
            $pdf->SetFont('helvetica', 'B', 8.5);
            $price = $product['unit_price_tax_incl'] ?? $product['unit_price_tax_excl'] ?? '0';
            $pdf->Cell($wPrice, 5, '€ ' . number_format((float) $price, 2, ',', '.'), 0, 0, 'R');

            // Riga divisoria sottile
            $pdf->SetY($currentY + $rowHeight - 1);
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->Cell(180, 0, '', 'T', 1);
        }
    }
}
