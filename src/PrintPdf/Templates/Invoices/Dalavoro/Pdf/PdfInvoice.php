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
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Invoices\Dalavoro\Pdf;

use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Invoices\Dalavoro\Traits\Common;

class PdfInvoice
{
    use Common;

    private int $orderId;
    private $context;
    private int $id_lang;
    private array $document = [];
    private string $stream = '';

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
        $this->context = \Context::getContext();
        $this->id_lang = (int) ($this->context->language->id ?? 1);
    }

    public function create(): self
    {
        $order = new \Order($this->orderId);
        if (!\Validate::isLoadedObject($order)) {
            return $this;
        }

        $customer = new \Customer($order->id_customer);
        $deliveryAddress = new \Address($order->id_address_delivery);
        $invoiceAddress = new \Address($order->id_address_invoice);

        $deliveryCountry = \Validate::isLoadedObject($deliveryAddress) ? new \Country((int) $deliveryAddress->id_country, $this->id_lang) : null;
        $invoiceCountry = \Validate::isLoadedObject($invoiceAddress) ? new \Country((int) $invoiceAddress->id_country, $this->id_lang) : null;

        $deliveryState = ($deliveryAddress && !empty($deliveryAddress->id_state)) ? new \State((int) $deliveryAddress->id_state) : null;
        $invoiceState = ($invoiceAddress && !empty($invoiceAddress->id_state)) ? new \State((int) $invoiceAddress->id_state) : null;

        $customerInvoice = new \MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice($customer->id);

        $invoiceCountryIso = $invoiceCountry ? strtoupper($invoiceCountry->iso_code) : 'IT';
        $isForeign = ($customerInvoice->is_foreign == 1) || ($invoiceCountryIso !== 'IT');

        $vatRate = (float) \Configuration::get('MPCUSTOMERINVOICE_VAT_RATE');
        if ($vatRate <= 0) {
            $vatRate = 22.0;
        }

        // Invoice Number & Date
        $invoiceNumber = $order->invoice_number > 0 ? (string) $order->invoice_number : (string) $order->id;
        $invoiceDate = !empty($order->invoice_date) && $order->invoice_date !== '0000-00-00 00:00:00'
            ? date('d/m/Y', strtotime($order->invoice_date))
            : date('d/m/Y', strtotime($order->date_add));

        $orderDate = date('d/m/Y', strtotime($order->date_add));

        // Fiscal code / VAT
        $vatCode = $customerInvoice->vat_number ?: ($customerInvoice->dni ?: ($invoiceAddress->vat_number ?: $invoiceAddress->dni));
        if (empty($vatCode)) {
            $vatCode = '--';
        }

        // Products
        $rawProducts = $order->getProducts();
        $products = [];
        foreach ($rawProducts as $p) {
            $prodObj = new \Product((int) $p['product_id'], false, $this->id_lang);
            $combination = $this->getCombination($prodObj, (int) $p['product_attribute_id']);
            
            $unitPriceExcl = (float) $p['unit_price_tax_excl'];
            $discountPct = (float) ($p['reduction_percent'] ?? 0);
            $qty = (int) $p['product_quantity'];
            $totalExcl = (float) $p['total_price_tax_excl'];

            $products[] = [
                'reference' => (string) $p['reference'],
                'name' => (string) $prodObj->name,
                'combination' => $combination,
                'vat_label' => $isForeign ? 'NI7' : number_format((float) ($p['tax_rate'] ?? $vatRate), 2, ',', '.') . ' %',
                'unit_price_excl' => $unitPriceExcl,
                'discount_percent' => $discountPct,
                'qty' => $qty,
                'total_excl' => $totalExcl,
            ];
        }

        // Carrier & Payment
        $carrier = new \Carrier((int) $order->id_carrier);
        $carrierName = \Validate::isLoadedObject($carrier) ? $carrier->name : '';

        // Payment fee calculation & PAYMENT_SELECTED check
        $feeData = $this->getPaymentFeeData($order, $vatRate);
        $feeExcl = $feeData['fee_excl'];

        // Calculation of totals according to Dalavoro business rules
        $productsTotalExcl = (float) $order->total_products;
        $discountsTotalExcl = (float) $order->total_discounts_tax_excl;
        $shippingTotalExcl = (float) $order->total_shipping_tax_excl + (float) $order->total_wrapping_tax_excl;

        $imponibile = $productsTotalExcl - $discountsTotalExcl + $shippingTotalExcl + $feeExcl;
        if ($imponibile < 0) {
            $imponibile = 0.0;
        }

        if ($isForeign) {
            $vatTotal = 0.0;
            $calculatedTotalIncl = $imponibile;
        } else {
            $vatTotal = round($imponibile * ($vatRate / 100), 2);
            $calculatedTotalIncl = round($imponibile + $vatTotal, 2);
        }

        $customerPaidTotal = (float) $order->total_paid_tax_incl;
        $roundingDelta = round($customerPaidTotal - $calculatedTotalIncl, 2);

        $this->document = [
            'shop_logo' => $this->getShopLogo(),
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'order_reference' => $order->reference ?: (string) $order->id,
            'order_date' => $orderDate,
            'vat_code' => $vatCode,
            'is_foreign' => $isForeign,
            'vat_rate' => $vatRate,
            'delivery_address' => $this->formatAddressData($deliveryAddress, $deliveryCountry, $deliveryState),
            'invoice_address' => $this->formatAddressData($invoiceAddress, $invoiceCountry, $invoiceState),
            'products' => $products,
            'payment_method' => (string) $order->payment,
            'carrier_name' => (string) $carrierName,
            'products_total_excl' => $productsTotalExcl,
            'shipping_total_excl' => $shippingTotalExcl,
            'fee_total_excl' => $feeExcl,
            'imponibile' => $imponibile,
            'vat_total' => $vatTotal,
            'calculated_total_incl' => $calculatedTotalIncl,
            'rounding_delta' => $roundingDelta,
            'customer_paid_total' => $customerPaidTotal,
        ];

        return $this;
    }

    protected function getPaymentFeeData(\Order $order, float $vatRate): array
    {
        $feeExcl = 0.0;
        $feeIncl = 0.0;

        $rawPaymentSelected = \Configuration::get('PAYMENT_SELECTED');
        if (empty($rawPaymentSelected)) {
            $rawPaymentSelected = \Configuration::get('MPCUSTOMERINVOICE_PAYMENT_SELECTED');
        }

        $paymentSelectedList = [];
        if (!empty($rawPaymentSelected)) {
            $decoded = json_decode($rawPaymentSelected, true);
            if (is_array($decoded)) {
                $paymentSelectedList = array_map('strval', $decoded);
            } else {
                $paymentSelectedList = [(string) $rawPaymentSelected];
            }
        }

        $orderModuleStr = (string) $order->module;
        $orderModuleIdStr = isset($order->id_module) ? (string) $order->id_module : '';

        if (empty($orderModuleIdStr) && !empty($orderModuleStr)) {
            $orderModuleIdStr = (string) \Db::getInstance()->getValue('SELECT id_module FROM ' . _DB_PREFIX_ . 'module WHERE name = "' . pSQL($orderModuleStr) . '"');
        }

        $isMatch = empty($paymentSelectedList)
            || in_array($orderModuleStr, $paymentSelectedList, true)
            || in_array($orderModuleIdStr, $paymentSelectedList, true);

        // 1. Check mp_payment_fee_order table for this order ID
        $feeRow = \Db::getInstance()->getRow('SELECT fee_amount, tax_included FROM ' . _DB_PREFIX_ . 'mp_payment_fee_order WHERE id_order = ' . (int) $order->id);
        if ($feeRow && !empty($feeRow['fee_amount'])) {
            $feeAmount = (float) $feeRow['fee_amount'];
            $taxIncluded = (int) $feeRow['tax_included'];
            if ($taxIncluded == 1) {
                // Scorporo IVA dalla commissione
                $feeExcl = round($feeAmount / (1 + $vatRate / 100), 6);
                $feeIncl = $feeAmount;
            } else {
                $feeExcl = $feeAmount;
                $feeIncl = round($feeAmount * (1 + $vatRate / 100), 2);
            }
        } elseif ($isMatch && \Module::isEnabled('mppaymentswithfees') && class_exists('\MpSoft\MpPaymentsWithFees\Helpers\Fees') && class_exists('\MpSoft\MpPaymentsWithFees\Model\PaymentFeeModule')) {
            try {
                $feeModule = \MpSoft\MpPaymentsWithFees\Model\PaymentFeeModule::getByModuleName($order->module);
                if ($feeModule && (int) $feeModule->active) {
                    $orderTotal = (float) $order->total_products_wt + (float) $order->total_shipping_tax_incl + (float) $order->total_wrapping_tax_incl - (float) $order->total_discounts_tax_incl;
                    $feeRes = \MpSoft\MpPaymentsWithFees\Helpers\Fees::calculate($orderTotal, (int) $feeModule->id, $vatRate);
                    if (isset($feeRes['has_fee']) && $feeRes['has_fee']) {
                        $feeExcl = (float) ($feeRes['fee_tax_excl'] ?? 0);
                        $feeIncl = (float) ($feeRes['fee_with_tax'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                $feeExcl = 0.0;
                $feeIncl = 0.0;
            }
        }

        return [
            'fee_excl' => $feeExcl,
            'fee_incl' => $feeIncl,
        ];
    }

    public function renderToPdf(\TCPDF $pdf): void
    {
        if (empty($this->document)) {
            $this->create();
        }
        if (empty($this->document)) {
            return;
        }

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);

        $this->drawDocument($pdf);
    }

    protected function drawDocument(\TCPDF $pdf): void
    {
        $doc = $this->document;

        if ($pdf->getNumPages() === 0) {
            $pdf->AddPage();
        }

        $pageWidth = $pdf->getPageWidth(); // 210
        $leftMargin = 10;
        $rightMargin = 10;
        $printableWidth = $pageWidth - $leftMargin - $rightMargin; // 190

        // Render Header & Info
        $startY = $this->renderHeader($pdf, $doc, $leftMargin, $printableWidth);

        // Render Products Table
        $summaryHeight = 42; // Height required for bottom summary boxes
        $bottomYLimit = $pdf->getPageHeight() - 15 - $summaryHeight; // ~240mm

        $currentY = $this->renderProductsTable($pdf, $doc['products'], $startY, $leftMargin, $printableWidth, $bottomYLimit, $doc);

        // Render Summary Footer (VAT + Totals)
        $this->renderSummaryFooter($pdf, $doc, $leftMargin, $printableWidth);

        // Render Page Footer text on all pages
        $numPages = $pdf->getNumPages();
        for ($p = 1; $p <= $numPages; $p++) {
            $pdf->setPage($p);
            $pdf->SetY(-15);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(0, 8, '- Pagina ' . $p . '/' . $pdf->getAliasNbPages() . ' -', 0, 0, 'C');
        }
    }

    protected function renderHeader(\TCPDF $pdf, array $doc, float $leftMargin, float $printableWidth): float
    {
        $pdf->SetY(10);
        $pdf->SetX($leftMargin);

        // Shop Logo
        $logoW = 75;
        $logoH = 22;
        if (!empty($doc['shop_logo']) && @file_exists(_PS_ROOT_DIR_ . $doc['shop_logo'])) {
            $pdf->Image(_PS_ROOT_DIR_ . $doc['shop_logo'], $leftMargin, 10, $logoW, $logoH, '', '', '', true, 300, '', false, false, 0, true, false, false);
        }

        // Title: FATTURA WEB/D
        $pdf->SetXY($leftMargin + $printableWidth - 80, 10);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(80, 8, 'FATTURA WEB/D', 0, 1, 'R');

        // Shop address line under logo
        $shopAddr = 'www.dalavoro.com - site by: Soc. IMPRENDO s.r.l.s. - Via Mafalda di Savoia 28,30 - P.iva: IT03412990784 - 87013 Fagnano Castello (Cs) - Cosenza - Italia';
        $pdf->SetXY($leftMargin, 31);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($printableWidth, 4, $shopAddr, 0, 1, 'L');

        // Horizontal Line
        $pdf->SetDrawColor(80, 80, 80);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($leftMargin, 36, $leftMargin + $printableWidth, 36);

        // 2-Column Addresses (Delivery & Invoice)
        $colW = ($printableWidth - 10) / 2; // 90mm each
        $addrY = 38;

        // Delivery Address
        $pdf->SetXY($leftMargin, $addrY);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($colW, 4.5, 'Indirizzo di consegna', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8.5);
        $this->renderAddressBlock($pdf, $doc['delivery_address'], $leftMargin, $colW);

        // Invoice Address
        $pdf->SetXY($leftMargin + $colW + 10, $addrY);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($colW, 4.5, 'Indirizzo di fatturazione', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8.5);
        $this->renderAddressBlock($pdf, $doc['invoice_address'], $leftMargin + $colW + 10, $colW);

        // Document Info Box (Bordered Header Table)
        $infoY = 70;
        $pdf->SetXY($leftMargin, $infoY);

        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);

        $colWidths = [35, 35, 40, 35, 45]; // Total 190mm

        // Table Header
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($colWidths[0], 5, 'NUMERO', 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 5, 'DATA', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 5, 'RIF. ORDINE', 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 5, 'DATA ORDINE', 1, 0, 'C', true);
        $pdf->Cell($colWidths[4], 5, 'P.IVA / COD. FISC.', 1, 1, 'C', true);

        // Table Values
        $pdf->SetX($leftMargin);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($colWidths[0], 5.5, $doc['invoice_number'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 5.5, $doc['invoice_date'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 5.5, $doc['order_reference'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 5.5, $doc['order_date'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[4], 5.5, $doc['vat_code'], 1, 1, 'C', true);

        return $infoY + 12; // ~82mm
    }

    protected function renderAddressBlock(\TCPDF $pdf, array $addr, float $x, float $w): void
    {
        $startY = $pdf->GetY();
        $pdf->SetX($x);

        $lines = [];
        $nameComp = [];
        if (!empty($addr['company'])) {
            $nameComp[] = $addr['company'];
        }
        if (!empty($addr['name'])) {
            $nameComp[] = $addr['name'];
        }
        if (!empty($nameComp)) {
            $lines[] = ['text' => implode(' - ', array_unique($nameComp)), 'bold' => true];
        }

        if (!empty($addr['address1'])) {
            $lines[] = ['text' => $addr['address1'], 'bold' => false];
        }
        if (!empty($addr['address2'])) {
            $lines[] = ['text' => $addr['address2'], 'bold' => false];
        }

        $cityLine = trim(($addr['postcode'] ?? '') . ' ' . ($addr['city'] ?? ''));
        if (!empty($cityLine)) {
            $lines[] = ['text' => $cityLine, 'bold' => false];
        }

        if (!empty($addr['state'])) {
            $lines[] = ['text' => strtoupper($addr['state']), 'bold' => true];
        }
        if (!empty($addr['country'])) {
            $lines[] = ['text' => strtoupper($addr['country']), 'bold' => true];
        }

        if (!empty($addr['phone'])) {
            $lines[] = ['text' => 'Telefono: ' . $addr['phone'], 'bold' => false];
        }
        if (!empty($addr['mobile'])) {
            $lines[] = ['text' => 'Cellulare: ' . $addr['mobile'], 'bold' => false];
        }

        foreach ($lines as $line) {
            $pdf->SetX($x);
            $pdf->SetFont('helvetica', $line['bold'] ? 'B' : '', 8.5);
            $pdf->Cell($w, 3.8, $line['text'], 0, 1, 'L');
        }
    }

    protected function renderProductsTable(\TCPDF $pdf, array $products, float $startY, float $leftMargin, float $printableWidth, float $bottomYLimit, array $doc): float
    {
        // Columns: RIFERIMENTO (28), PRODOTTO (65), IVA (16), PREZZO (20), SCONTO (18), QTA (12), TOTALE (24) = 183 + 7 = 190
        $colW = [28, 65, 16, 22, 20, 14, 25]; // Total 190mm

        $currentY = $startY;

        $drawTableHeader = function ($y) use ($pdf, $leftMargin, $colW) {
            $pdf->SetXY($leftMargin, $y);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFont('helvetica', 'B', 8);

            $pdf->Cell($colW[0], 5, 'RIFERIMENTO', 1, 0, 'L', true);
            $pdf->Cell($colW[1], 5, 'PRODOTTO', 1, 0, 'L', true);
            $pdf->Cell($colW[2], 5, 'IVA', 1, 0, 'C', true);
            $pdf->Cell($colW[3], 5, 'PREZZO', 1, 0, 'R', true);
            $pdf->Cell($colW[4], 5, 'SCONTO', 1, 0, 'R', true);
            $pdf->Cell($colW[5], 5, 'QTA', 1, 0, 'C', true);
            $pdf->Cell($colW[6], 5, 'TOTALE', 1, 1, 'R', true);

            return $y + 5;
        };

        $currentY = $drawTableHeader($currentY);

        $alt = false;
        foreach ($products as $p) {
            // Check if page break is needed
            if ($currentY + 8 > $bottomYLimit) {
                $pdf->AddPage();
                $currentY = $this->renderHeader($pdf, $doc, $leftMargin, $printableWidth);
                $currentY = $drawTableHeader($currentY);
            }

            $pdf->SetXY($leftMargin, $currentY);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor($alt ? 250 : 255, $alt ? 250 : 255, $alt ? 250 : 255);
            $pdf->SetDrawColor(220, 220, 220);

            $prodName = $p['name'];
            if (!empty($p['combination'])) {
                $prodName .= ' - ' . $p['combination'];
            }

            $discountText = $p['discount_percent'] > 0 ? number_format($p['discount_percent'], 2, ',', '.') . ' %' : '--';

            $pdf->Cell($colW[0], 5.5, $p['reference'], 'B', 0, 'L', true);
            $pdf->Cell($colW[1], 5.5, $this->truncateString($prodName, 42), 'B', 0, 'L', true);
            $pdf->Cell($colW[2], 5.5, $p['vat_label'], 'B', 0, 'C', true);
            $pdf->Cell($colW[3], 5.5, $this->formatPrice($p['unit_price_excl']), 'B', 0, 'R', true);
            $pdf->Cell($colW[4], 5.5, $discountText, 'B', 0, 'R', true);
            $pdf->Cell($colW[5], 5.5, (string) $p['qty'], 'B', 0, 'C', true);
            $pdf->Cell($colW[6], 5.5, $this->formatPrice($p['total_excl']), 'B', 1, 'R', true);

            $currentY += 5.5;
            $alt = !$alt;
        }

        return $currentY;
    }

    protected function renderSummaryFooter(\TCPDF $pdf, array $doc, float $leftMargin, float $printableWidth): void
    {
        $footerY = $pdf->getPageHeight() - 55; // Positioned near bottom of page

        $pdf->SetXY($leftMargin, $footerY);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);

        // Left Box: VAT table + Payment/Carrier (Width: 100mm)
        $leftW = 100;
        
        // VAT Breakdown Table
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 8);

        if ($doc['is_foreign']) {
            // Foreign / NI7 Exempt VAT Table
            $pdf->Cell(25, 5, '% IVA', 1, 0, 'C', true);
            $pdf->Cell(37.5, 5, 'Imponibile', 1, 0, 'C', true);
            $pdf->Cell(37.5, 5, 'Imposta', 1, 1, 'C', true);

            $pdf->SetX($leftMargin);
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell(25, 5.5, 'NI7', 1, 0, 'C', true);
            $pdf->Cell(75, 5.5, 'Cessioni CEE art.41 DL.331/93', 1, 1, 'C', true);
        } else {
            // Domestic VAT Table
            $pdf->Cell(25, 5, '% IVA', 1, 0, 'C', true);
            $pdf->Cell(37.5, 5, 'Imponibile', 1, 0, 'C', true);
            $pdf->Cell(37.5, 5, 'Imposta', 1, 1, 'C', true);

            $pdf->SetX($leftMargin);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell(25, 5.5, number_format($doc['vat_rate'], 2, ',', '.') . ' %', 1, 0, 'C', true);
            $pdf->Cell(37.5, 5.5, $this->formatPrice($doc['imponibile']), 1, 0, 'R', true);
            $pdf->Cell(37.5, 5.5, $this->formatPrice($doc['vat_total']), 1, 1, 'R', true);
        }

        // Payment Method & Carrier box below VAT Table
        $pdf->Ln(1.5);
        $pdf->SetX($leftMargin);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);

        // Row 1: Payment Method
        $pdf->Cell(40, 5, 'METODO DI PAGAMENTO', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(35, 5, $doc['payment_method'], 1, 0, 'L', false);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->Cell(25, 5, $this->formatPrice($doc['customer_paid_total']), 1, 1, 'R', false);

        // Row 2: Carrier
        $pdf->SetX($leftMargin);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(40, 5, 'CORRIERE', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(60, 5, $doc['carrier_name'], 1, 1, 'L', false);


        // Right Box: Totals Table (Width: 80mm, positioned right)
        $rightX = $leftMargin + $printableWidth - 80;
        $pdf->SetXY($rightX, $footerY);

        $rightW1 = 45;
        $rightW2 = 35;

        // Totale Prodotti
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($rightW1, 5, 'TOTALE PRODOTTI', 1, 0, 'R', true);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($rightW2, 5, $this->formatPrice($doc['products_total_excl']), 1, 1, 'R', false);

        // Totale Spedizioni
        $pdf->SetX($rightX);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($rightW1, 5, 'TOTALE SPEDIZIONI', 1, 0, 'R', true);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($rightW2, 5, $this->formatPrice($doc['shipping_total_excl']), 1, 1, 'R', false);

        // Commissioni (if > 0)
        if (!empty($doc['fee_total_excl']) && (float) $doc['fee_total_excl'] > 0) {
            $pdf->SetX($rightX);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($rightW1, 5, 'COMMISSIONI', 1, 0, 'R', true);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->Cell($rightW2, 5, $this->formatPrice($doc['fee_total_excl']), 1, 1, 'R', false);
        }

        // Totale IVA
        $pdf->SetX($rightX);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($rightW1, 5, 'TOTALE IVA', 1, 0, 'R', true);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($rightW2, 5, $this->formatPrice($doc['vat_total']), 1, 1, 'R', false);

        // Optional Arrotondamento Row
        if (abs($doc['rounding_delta']) >= 0.005) {
            $pdf->SetX($rightX);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($rightW1, 5, 'ARROTONDAMENTO', 1, 0, 'R', true);
            $pdf->SetFont('helvetica', '', 8.5);
            $sign = $doc['rounding_delta'] > 0 ? '+' : '';
            $pdf->Cell($rightW2, 5, $sign . $this->formatPrice($doc['rounding_delta']), 1, 1, 'R', false);
        }

        // Totale Documento
        $pdf->SetX($rightX);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->Cell($rightW1, 5.5, 'TOTALE DOCUMENTO', 1, 0, 'R', true);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($rightW2, 5.5, $this->formatPrice($doc['customer_paid_total']), 1, 1, 'R', false);
    }

    protected function formatAddressData($addrObj, $countryObj, $stateObj): array
    {
        if (!\Validate::isLoadedObject($addrObj)) {
            return [];
        }

        $company = (string) $addrObj->company;
        $name = trim($addrObj->firstname . ' ' . $addrObj->lastname);

        return [
            'company' => $company,
            'name' => $name,
            'address1' => (string) $addrObj->address1,
            'address2' => (string) $addrObj->address2,
            'postcode' => (string) $addrObj->postcode,
            'city' => (string) $addrObj->city,
            'state' => \Validate::isLoadedObject($stateObj) ? $stateObj->name : '',
            'country' => \Validate::isLoadedObject($countryObj) ? $countryObj->name : '',
            'phone' => (string) $addrObj->phone,
            'mobile' => (string) $addrObj->phone_mobile,
        ];
    }

    protected function getCombination($product, int $idProductAttribute): string
    {
        $combination = $product->getAttributesGroups($this->id_lang, $idProductAttribute);
        if ($combination) {
            $out = [];
            foreach ($combination as $group) {
                $out[] = $group['attribute_name'];
            }
            return implode(' - ', $out);
        }
        return '';
    }

    protected function truncateString(string $str, int $length): string
    {
        if (mb_strlen($str) > $length) {
            return mb_substr($str, 0, $length - 1) . '…';
        }
        return $str;
    }
}
