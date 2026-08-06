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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Deliveries\Dalavoro\Pdf;

use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Deliveries\Dalavoro\Traits\Common;

class PdfDelivery
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

        // Delivery Number & Date
        $deliveryNumber = $order->delivery_number > 0 ? (string) $order->delivery_number : (string) $order->id;
        $deliveryDate = !empty($order->delivery_date) && $order->delivery_date !== '0000-00-00 00:00:00'
            ? date('d/m/Y', strtotime($order->delivery_date))
            : date('d/m/Y', strtotime($order->date_add));

        $orderDate = date('d/m/Y', strtotime($order->date_add));

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
                'unit_price_excl' => $unitPriceExcl,
                'discount_percent' => $discountPct,
                'qty' => $qty,
                'total_excl' => $totalExcl,
            ];
        }

        // Carrier & Payment
        $carrier = new \Carrier((int) $order->id_carrier);
        $carrierName = \Validate::isLoadedObject($carrier) ? $carrier->name : '';

        $vatRate = (float) \Configuration::get('MPCUSTOMERINVOICE_VAT_RATE');
        if ($vatRate <= 0) {
            $vatRate = 22.0;
        }

        // Payment fee calculation & PAYMENT_SELECTED check
        $feeData = $this->getPaymentFeeData($order, $vatRate);
        $feeExcl = $feeData['fee_excl'];

        // Calculation of totals
        $productsTotalExcl = (float) $order->total_products;
        $discountsTotalExcl = (float) $order->total_discounts_tax_excl;
        $shippingTotalExcl = (float) $order->total_shipping_tax_excl + (float) $order->total_wrapping_tax_excl;

        $imponibile = $productsTotalExcl - $discountsTotalExcl + $shippingTotalExcl + $feeExcl;
        if ($imponibile < 0) {
            $imponibile = 0.0;
        }

        $calculatedTotalIncl = round($imponibile * (1 + $vatRate / 100), 2);
        $customerPaidTotal = (float) $order->total_paid_tax_incl;
        $roundingDelta = round($customerPaidTotal - $calculatedTotalIncl, 2);

        $this->document = [
            'shop_logo' => $this->getShopLogo(),
            'delivery_number' => $deliveryNumber,
            'delivery_date' => $deliveryDate,
            'order_reference' => $order->reference ?: (string) $order->id,
            'order_date' => $orderDate,
            'delivery_address' => $this->formatAddressData($deliveryAddress, $deliveryCountry, $deliveryState),
            'invoice_address' => $this->formatAddressData($invoiceAddress, $invoiceCountry, $invoiceState),
            'products' => $products,
            'payment_method' => (string) $order->payment,
            'carrier_name' => (string) $carrierName,
            'products_total_excl' => $productsTotalExcl,
            'shipping_total_excl' => $shippingTotalExcl,
            'fee_total_excl' => $feeExcl,
            'imponibile' => $imponibile,
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
                        if ($feeIncl > 0 && abs($feeExcl - $feeIncl) < 0.001 && $vatRate > 0) {
                            $feeExcl = $feeIncl / (1 + $vatRate / 100);
                        }
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

        $pageWidth = $pdf->getPageWidth();
        $leftMargin = 10;
        $rightMargin = 10;
        $printableWidth = $pageWidth - $leftMargin - $rightMargin; // 190

        // Render Header & Info
        $startY = $this->renderHeader($pdf, $doc, $leftMargin, $printableWidth);

        // Render Products Table
        $summaryHeight = 35;
        $bottomYLimit = $pdf->getPageHeight() - 15 - $summaryHeight;

        $currentY = $this->renderProductsTable($pdf, $doc['products'], $startY, $leftMargin, $printableWidth, $bottomYLimit, $doc);

        // Render Summary Footer
        $this->renderSummaryFooter($pdf, $doc, $leftMargin, $printableWidth);

        // Page Footer text
        $numPages = $pdf->getNumPages();
        for ($p = 1; $p <= $numPages; $p++) {
            $pdf->setPage($p);
            $pdf->SetY(-15);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(0, 8, 'Pagina ' . $p . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
        }
    }

    protected function renderHeader(\TCPDF $pdf, array $doc, float $leftMargin, float $printableWidth): float
    {
        $pdf->SetY(10);
        $pdf->SetX($leftMargin);

        // Logo
        $logoW = 75;
        $logoH = 22;
        if (!empty($doc['shop_logo']) && @file_exists(_PS_ROOT_DIR_ . $doc['shop_logo'])) {
            $pdf->Image(_PS_ROOT_DIR_ . $doc['shop_logo'], $leftMargin, 10, $logoW, $logoH, '', '', '', true, 300, '', false, false, 0, true, false, false);
        }

        // Title & Subtitle: NOTA VENDITA WEB / DOCUMENTO NON VALIDO AI FINI FISCALI
        $pdf->SetXY($leftMargin + $printableWidth - 110, 8);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(110, 7, 'NOTA VENDITA WEB', 0, 1, 'R');

        $pdf->SetXY($leftMargin + $printableWidth - 110, 16);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(110, 5, 'DOCUMENTO NON VALIDO AI FINI FISCALI', 0, 1, 'R');

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

        // 2-Column Addresses
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

        // Document Info Box (Bordered Header Table: NUMERO, DATA, RIF. ORDINE, DATA ORDINE)
        $infoY = 70;
        $pdf->SetXY($leftMargin, $infoY);

        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);

        $colWidths = [47.5, 47.5, 47.5, 47.5]; // Total 190mm

        // Table Header
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($colWidths[0], 5, 'NUMERO', 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 5, 'DATA', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 5, 'RIF. ORDINE', 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 5, 'DATA ORDINE', 1, 1, 'C', true);

        // Table Values
        $pdf->SetX($leftMargin);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($colWidths[0], 5.5, $doc['delivery_number'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 5.5, $doc['delivery_date'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 5.5, $doc['order_reference'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 5.5, $doc['order_date'], 1, 1, 'C', true);

        return $infoY + 12;
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
        // Columns: RIFERIMENTO (32), PRODOTTO (77), PREZZO (22), SCONTO (20), QTA (14), TOTALE (25) = 190mm
        $colW = [32, 77, 22, 20, 14, 25];

        $currentY = $startY;

        $drawTableHeader = function ($y) use ($pdf, $leftMargin, $colW) {
            $pdf->SetXY($leftMargin, $y);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFont('helvetica', 'B', 8);

            $pdf->Cell($colW[0], 5, 'RIFERIMENTO', 1, 0, 'L', true);
            $pdf->Cell($colW[1], 5, 'PRODOTTO', 1, 0, 'L', true);
            $pdf->Cell($colW[2], 5, 'PREZZO', 1, 0, 'R', true);
            $pdf->Cell($colW[3], 5, 'SCONTO', 1, 0, 'R', true);
            $pdf->Cell($colW[4], 5, 'QTA', 1, 0, 'C', true);
            $pdf->Cell($colW[5], 5, 'TOTALE', 1, 1, 'R', true);

            return $y + 5;
        };

        $currentY = $drawTableHeader($currentY);

        $alt = false;
        foreach ($products as $p) {
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
            $pdf->Cell($colW[1], 5.5, $this->truncateString($prodName, 52), 'B', 0, 'L', true);
            $pdf->Cell($colW[2], 5.5, $this->formatPrice($p['unit_price_excl']), 'B', 0, 'R', true);
            $pdf->Cell($colW[3], 5.5, $discountText, 'B', 0, 'R', true);
            $pdf->Cell($colW[4], 5.5, (string) $p['qty'], 'B', 0, 'C', true);
            $pdf->Cell($colW[5], 5.5, $this->formatPrice($p['total_excl']), 'B', 1, 'R', true);

            $currentY += 5.5;
            $alt = !$alt;
        }

        return $currentY;
    }

    protected function renderSummaryFooter(\TCPDF $pdf, array $doc, float $leftMargin, float $printableWidth): void
    {
        $footerY = $pdf->getPageHeight() - 48; // Position near bottom

        $pdf->SetXY($leftMargin, $footerY);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);

        // Left Box: Payment Method & Carrier (No VAT table!)
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);

        // Row 1: Payment Method
        $pdf->Cell(40, 5.5, 'METODO DI PAGAMENTO', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(35, 5.5, $doc['payment_method'], 1, 0, 'L', false);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->Cell(25, 5.5, $this->formatPrice($doc['customer_paid_total']), 1, 1, 'R', false);

        // Row 2: Carrier
        $pdf->SetX($leftMargin);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(40, 5.5, 'CORRIERE', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(60, 5.5, $doc['carrier_name'], 1, 1, 'L', false);


        // Right Box: Totals Table
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

        // Optional Arrotondamento Row
        if (abs($doc['rounding_delta']) >= 0.005) {
            $pdf->SetX($rightX);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($rightW1, 5, 'ARROTONDAMENTO', 1, 0, 'R', true);
            $pdf->SetFont('helvetica', '', 8.5);
            $sign = $doc['rounding_delta'] > 0 ? '+' : '';
            $pdf->Cell($rightW2, 5, $sign . $this->formatPrice($doc['rounding_delta']), 1, 1, 'R', false);
        }

        // Totale Documento (No VAT total row on Nota di Vendita!)
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
