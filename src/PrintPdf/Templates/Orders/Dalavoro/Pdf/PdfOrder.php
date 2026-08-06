<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA.
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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Pdf;

use MpSoft\MpCustomerInvoice\Helpers\FormatDate;
use MpSoft\MpCustomerInvoice\Helpers\IsNewCustomer;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Partials\OrderCustomization;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Partials\OrderMessages;
use MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Dalavoro\Partials\OrderRow;

class PdfOrder
{
    private $document;
    private $stream;
    private $orderId;
    private $context;
    private $id_lang;
    private $id_order;
    private $locale;
    private $currencyIsoCode;
    private $customizedDatas;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
        $this->context = \Context::getContext();
        $this->id_lang = $this->context->language->id;
        $this->locale = \Tools::getContextLocale($this->context);
        $this->currencyIsoCode = $this->context->currency->iso_code;
    }

    public function create()
    {
        $order = new \Order($this->orderId, $this->id_lang);
        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        $customer = new \Customer($order->id_customer);
        if (!\Validate::isLoadedObject($customer)) {
            $customer = false;
        }

        $deliveryAddress = new \Address($order->id_address_delivery);
        if (!\Validate::isLoadedObject($deliveryAddress)) {
            $deliveryAddress = false;
        }

        $invoiceAddress = new \Address($order->id_address_invoice);
        if (!\Validate::isLoadedObject($invoiceAddress)) {
            $invoiceAddress = false;
        }

        $deliveryCountry = $deliveryAddress ? new \Country((int) $deliveryAddress->id_country) : false;
        if (!\Validate::isLoadedObject($deliveryCountry)) {
            $deliveryCountry = false;
        }

        $invoiceCountry = $invoiceAddress ? new \Country((int) $invoiceAddress->id_country) : false;
        if (!\Validate::isLoadedObject($invoiceCountry)) {
            $invoiceCountry = false;
        }

        $deliveryState = ($deliveryAddress && !empty($deliveryAddress->id_state)) ? new \State((int) $deliveryAddress->id_state) : false;
        if (!\Validate::isLoadedObject($deliveryState)) {
            $deliveryState = false;
        }

        $invoiceState = ($invoiceAddress && !empty($invoiceAddress->id_state)) ? new \State((int) $invoiceAddress->id_state) : false;
        if (!\Validate::isLoadedObject($invoiceState)) {
            $invoiceState = false;
        }

        $this->document = [
            'shop_logo' => $this->getShopLogo(),
            'order' => $this->getOrderFields($order),
            'customer' => $customer ? $customer->getFields() : [],
            'delivery_address' => $deliveryAddress ? $deliveryAddress->getFields() : [],
            'invoice_address' => $invoiceAddress ? $invoiceAddress->getFields() : [],
            'delivery_country' => $deliveryCountry ? $deliveryCountry->getFields() : [],
            'invoice_country' => $invoiceCountry ? $invoiceCountry->getFields() : [],
            'delivery_state' => $deliveryState ? $deliveryState->getFields() : [],
            'invoice_state' => $invoiceState ? $invoiceState->getFields() : [],
            'products' => $this->getProducts($order),
            'customized_products' => $this->getProductCustomizations($order->id_cart),
            'current_state' => $this->getCurrentState($order),
            'is_new_customer' => IsNewCustomer::check($order->id_customer),
            'messages' => [],
        ];

        return $this;
    }

    public function renderToPdf(\TCPDF $pdf): void
    {
        if (empty($this->document)) {
            $this->create();
        }
        $document = $this->document;
        if (!$document) {
            return;
        }

        $id_order = (int) $document['order']['id_order'];

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);

        // HEADER
        $this->writeHeader($pdf, $document);
        $pdf->Ln(2);

        // PRODUCTS
        $this->writeProducts($pdf, $document['products']);
        $pdf->Ln(10);

        // PERSONALIZZAZIONI
        $this->writeCustomizations($pdf, $document['customized_products']);
        $pdf->Ln(10);

        // MESSAGGI
        $this->writeMessages($pdf, $id_order);
    }

    public function render()
    {
        $faTtfPath = _PS_MODULE_DIR_ . '/mpdocumentprint/views/tcpdf/fonts/Font Awesome 7 Free-Solid-900.otf';

        // 3) Registra il font in TCPDF (genera i file nella cartella tcpdf/fonts)
        //    NB: tcpdf/fonts deve essere scrivibile almeno la prima volta.
        $fontName = \TCPDF_FONTS::addTTFfont($faTtfPath);

        $iconUser = html_entity_decode('&#xf007;', ENT_NOQUOTES, 'UTF-8');
        $iconPhone = html_entity_decode('&#xf095;', ENT_NOQUOTES, 'UTF-8');
        $iconMail = html_entity_decode('&#xf0e0;', ENT_NOQUOTES, 'UTF-8');
        $iconAddress = html_entity_decode('&#xf3c5;', ENT_NOQUOTES, 'UTF-8');
        $iconCalendar = html_entity_decode('&#xf133;', ENT_NOQUOTES, 'UTF-8');
        $iconCart = html_entity_decode('&#xf07a;', ENT_NOQUOTES, 'UTF-8');
        $iconPrint = html_entity_decode('&#xf02f;', ENT_NOQUOTES, 'UTF-8');
        $iconTrash = html_entity_decode('&#xf1f8;', ENT_NOQUOTES, 'UTF-8');
        $iconInfo = html_entity_decode('&#xf129;', ENT_NOQUOTES, 'UTF-8');
        $iconCheck = html_entity_decode('&#xf00c;', ENT_NOQUOTES, 'UTF-8');
        $iconClose = html_entity_decode('&#xf00d;', ENT_NOQUOTES, 'UTF-8');

        $document = $this->document;
        $id_order = (int) $document['order']['id_order'];

        // Usa una classe anonima per TCPDF con footer personalizzato
        $pdf = new class extends \TCPDF {
            public function Footer()
            {
                $this->SetY(-15);
                $this->SetFont('helvetica', '', 9);
                $this->SetTextColor(120, 120, 120);
                $footerText = '- ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' -';
                $this->Cell(0, 8, $footerText, 0, 0, 'C');
            }

            public function getTextColor()
            {
                return $this->fgcolor;
            }
        };
        // $pdf->SetFont($fontName, '', 96);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Massimiliano Palermo');
        $pdf->SetTitle('Ordine n.' . $id_order);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // HEADER
        $this->writeHeader($pdf, $document);
        $pdf->Ln(2);

        // PRODUCTS
        $this->writeProducts($pdf, $document['products']);
        $pdf->Ln(10);

        // PERSONALIZZAZIONI
        $this->writeCustomizations($pdf, $document['customized_products']);
        $pdf->Ln(10);

        // MESSAGGI
        $this->writeMessages($pdf, $id_order);

        // Output finale in una stringa
        $this->stream = $pdf->Output("order_{$id_order}.pdf", 'S');

        return $this;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function getStream()
    {
        return $this->stream;
    }

    protected function getOrderFields($order)
    {
        $fields = $order->getFields();
        $fields['total_order_currency'] = $this->locale->formatPrice($fields['total_paid_tax_incl'], $this->currencyIsoCode);

        return $fields;
    }

    protected function isStockService($id_product, $id_product_attribute)
    {
        $db = \Db::getInstance();
        $sql = new \DbQuery();
        // Controllo se è stock service
        $sql
            ->select('is_stock_service')
            ->from('product_stock_service')
            ->where('id_product = ' . (int) $id_product);
        $is_stock_service = (int) $db->getValue($sql);
        if (!$is_stock_service) {
            return [
                'is_stock_service' => false,
                'quantity' => 0,
            ];
        }

        $sql = new \DbQuery();
        $sql
            ->select('quantity')
            ->from('product_stock_service_row')
            ->where('id_product = ' . (int) $id_product)
            ->where('id_product_attribute = ' . (int) $id_product_attribute);
        $result = $db->getRow($sql);
        if ($result) {
            return [
                'is_stock_service' => true,
                'quantity' => $result['quantity'],
            ];
        }

        return [
            'is_stock_service' => true,
            'quantity' => 0,
        ];
    }

    protected function getCheckDate($id_product)
    {
        $db = \Db::getInstance();
        $sql = new \DbQuery();
        $sql
            ->select('date_upd')
            ->from('product_stock_service')
            ->where('id_product = ' . (int) $id_product);
        $result = $db->getRow($sql);
        if ($result) {
            return $result['date_upd'];
        }

        return null;
    }

    protected function getShopLogo()
    {
        $logo = \Configuration::get('PS_LOGO');
        if (!$logo) {
            return '';
        }

        return '/img/' . $logo;
    }

    protected function getProducts($order)
    {
        $this->customizedDatas = [];
        $products = $order->getProducts();
        // Ordino i prodotti per REFERENCE
        usort($products, function ($a, $b) {
            return strcmp($a['reference'], $b['reference']);
        });

        foreach ($products as $key => &$product_item) {
            $product = new \Product($product_item['product_id'], false, $this->id_lang);
            $product_item['product_name_full'] = $product_item['product_name'];
            $product_item['product_name'] = $product->name;
            $product_item['combination'] = $this->getCombination($product, $product_item['product_attribute_id']);
            $product_item['image_url'] = $this->getImage($product_item['product_id']);
            $product_item['price_currency'] = $this->locale->formatPrice($product_item['unit_price_tax_incl'], $this->currencyIsoCode);
            $product_item['stock_service'] = $this->isStockService($product_item['product_id'], $product_item['product_attribute_id']);
            $product_item['check_date'] = $this->getCheckDate($product_item['product_id']);

            if (0 != $product_item['customizable']) {
                $this->customizedDatas[] = $product_item;
                unset($products[$key]);
                continue;
            }
        }

        return $products;
    }

    /**
     * Ottiene tutti i prodotti personalizzati di un ordine.
     *
     * @return array Array strutturato con i prodotti personalizzati
     */
    protected function getProductCustomizations($id_cart)
    {
        $customized_products = $this->customizedDatas;

        foreach ($customized_products as &$product) {
            $id_product = $product['product_id'];
            $id_product_attribute = $product['product_attribute_id'];
            // $customizedData = \Product::getAllCustomizedDatas($id_cart, $this->id_lang);
            $customizedData = $product['customizedDatas'] ?? [];
            if (!$customizedData) {
                continue;
            }

            foreach ($customizedData as $customization_item) {
                $customization_data = reset($customization_item);
                $datas = $customization_data['datas'];
                $productCustomizationData = [];

                foreach ($datas as $data) {
                    if (!is_array($data)) {
                        continue;
                    }
                    foreach ($data as $customizedData) {
                        $hasFile = 0 == $customizedData['type'];
                        $hasText = 1 == $customizedData['type'];
                        $label = $customizedData['name'];
                        $value = $customizedData['value'];
                        $productCustomizationData[] = [
                            'label' => $label,
                            'value' => $value,
                            'hasFile' => $hasFile,
                            'hasText' => $hasText,
                        ];
                    }
                }

                $product['customizations'][] = [
                    'id_product' => $id_product,
                    'id_product_attribute' => $id_product_attribute,
                    'quantity' => $customization_data['quantity'],
                    'data' => $productCustomizationData,
                ];
            }
        }

        return $customized_products;
    }

    protected function getImage($idProduct)
    {
        /** @var array $cover */
        $cover = \Image::getCover($idProduct);
        if (!isset($cover['id_image']) || !$cover['id_image']) {
            return $this->context->shop->getBaseURL() . 'img/404.gif';
        }

        /** @var \Image $image */
        $image = new \Image($cover['id_image']);
        if (!\Validate::isLoadedObject($image)) {
            return $this->context->shop->getBaseURL() . 'img/404.gif';
        }
        /** @var string $imageType */
        $imageType = ".{$image->image_format}";
        /** @var string $imagePath */
        $imageFolders = \Image::getImgFolderStatic((int) $cover['id_image']);
        /** @var string $imagePath */
        $imagePath = "/img/p/{$imageFolders}{$cover['id_image']}-medium{$imageType}";
        /** @var string $root */
        $root = $this->context->shop->getBaseURL();

        return $imagePath;
    }

    protected function getCombination($product, $idProductAttribute)
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

    protected function getCurrentState($order)
    {
        $db = \Db::getInstance();
        $sql = new \DbQuery();
        $sql
            ->select('id_order_state')
            ->select('date_add')
            ->from('order_history')
            ->where('id_order = ' . (int) $order->id)
            ->orderBy('id_order_history DESC');
        $lastOrderHistory = $db->getRow($sql);
        if (!$lastOrderHistory) {
            return '!ERRORE!';
        }
        $orderState = new \OrderState($lastOrderHistory['id_order_state'], $this->id_lang);
        if (!\Validate::isLoadedObject($orderState)) {
            return '!ERRORE!';
        }
        $orderStateDateIta = (new FormatDate($lastOrderHistory['date_add']))->toLocalDate();

        return [
            'date' => $lastOrderHistory['date_add'],
            'date_ita' => $orderStateDateIta,
            'name' => $orderState->name,
        ];
    }

    protected function writeHeader(\TCPDF $pdf, $data)
    {
        $startY = 10;
        $logoX = 10;
        $logoW = 75;
        $logoH = 22;
        $rightX = 90;
        $rightY = 10;
        $rightW = 100;

        // 1. Logo in alto a sinistra
        if (!empty($data['shop_logo']) && @file_exists(_PS_ROOT_DIR_ . $data['shop_logo'])) {
            $pdf->Image(_PS_ROOT_DIR_ . $data['shop_logo'], $logoX, $startY, $logoW, $logoH, '', '', '', true, 300, '', false, false, 0, true, false, false);
        } else {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($logoX, $startY + 5);
            $pdf->Cell($logoW, 10, 'DALAVORO', 0, 0, 'L');
        }

        // 2. Dati ordine in alto a destra
        $orderId = isset($data['order']['id_order']) ? $data['order']['id_order'] : '';
        $this->id_order = $orderId;
        $orderDate = isset($data['order']['date_add']) ? date('d/m/Y', strtotime($data['order']['date_add'])) : '';
        $stateName = isset($data['current_state']['name']) ? $data['current_state']['name'] : '';
        $printedDate = date('d/m/Y H:i:s');
        $payment = isset($data['order']['payment']) ? $data['order']['payment'] : '';

        // Titolo Ordine in grassetto grande in alto a destra
        $pdf->SetXY($rightX, $rightY);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell($rightW, 7, "Ordine: {$orderId}", 0, 1, 'L', 0);

        // Data ordine
        $pdf->SetX($rightX);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($rightW, 4.5, "del {$orderDate}", 0, 1, 'L', 0);

        // Dettagli ordine
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetTextColor(40, 40, 40);

        $pdf->SetX($rightX);
        $pdf->Cell($rightW, 4.5, "Stato corrente: {$stateName}", 0, 1, 'L', 0);

        $pdf->SetX($rightX);
        $pdf->Cell($rightW, 4.5, "Data di stampa: {$printedDate}", 0, 1, 'L', 0);

        $pdf->SetX($rightX);
        $pdf->Cell($rightW, 4.5, "Tipo di Pagamento: {$payment}", 0, 1, 'L', 0);

        $lineY = max($startY + $logoH + 2, $pdf->GetY() + 2);
        $pdf->SetDrawColor(136, 136, 136);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(10, $lineY, 200, $lineY);
        $pdf->SetY($lineY + 3);

        // --- SECONDA RIGA: INDIRIZZI E CLIENTE ---
        $boxH = 28;
        $boxFont = 9;
        $colW = [60, 60, 60];
        $colX = [0, 60, 125];
        $startY = $pdf->GetY();
        $x = $pdf->GetX();

        // Indirizzo di spedizione
        $pdf->SetXY($x + $colX[0], $startY);
        $pdf->SetFont('helvetica', 'B', $boxFont);
        $pdf->SetFillColor(247, 247, 247);
        $pdf->SetDrawColor(136, 136, 136);
        $pdf->Cell($colW[0], 6, 'Indirizzo di spedizione', 1, 2, 'L', 1);
        $pdf->SetFont('helvetica', '', $boxFont);
        $pdf->MultiCell($colW[0], 5, $this->formatAddress($data['delivery_address'], $data['delivery_state'], $data['delivery_country']), 1, 'L', 1, 0);

        // Indirizzo di fatturazione
        $pdf->SetXY($x + $colX[1], $startY);
        $pdf->SetFont('helvetica', 'B', $boxFont);
        $pdf->Cell($colW[1], 6, 'Indirizzo di fatturazione', 1, 2, 'L', 1);
        $pdf->SetFont('helvetica', '', $boxFont);
        $pdf->MultiCell($colW[1], 5, $this->formatAddress($data['invoice_address'], $data['invoice_state'], $data['invoice_country']), 1, 'L', 1, 0);

        // Dati ordine
        $pdf->SetXY($x + $colX[2], $startY);
        $pdf->SetFont('helvetica', 'B', $boxFont);
        $pdf->Cell($colW[2], 6, 'Dati ordine', 1, 2, 'L', 1);
        $pdf->SetFont('helvetica', '', $boxFont);

        $customerId = isset($data['customer']['id_customer']) ? $data['customer']['id_customer'] : '';
        $isOld = !empty($data['is_new_customer']) ? false : true;
        $orderDateFull = isset($data['order']['date_add']) ? date('d/m/Y H:i', strtotime($data['order']['date_add'])) : '';
        $total = isset($data['order']['total_order_currency']) ? $data['order']['total_order_currency'] : '';

        $pdf->Cell(30, 10, 'CODICE CLIENTE:', 0, 0, 'L', 0);
        if ($isOld) {
            $pdf->setTextColor(200, 30, 30);
            $pdf->setFont('', 'B', $boxFont);
            $pdf->Cell(5, 10, 'V', 0, 0, 'L');
        }

        $pdf->setFont('', 'B', $boxFont + 1);
        $pdf->setTextColor(40, 40, 40);
        $pdf->Cell(20, 10, "DL{$customerId}", 0, 10, 'L', 0);

        $pdf->setFont('', '', $boxFont);

        // Data ordine e totale ordine subito sotto Codice Cliente
        $pdf->SetFont('helvetica', '', $boxFont);
        $pdf->SetX($x + $colX[2]);
        $pdf->Cell($colW[2], 0, "Data Ordine: $orderDateFull", 0, 10, 'L');
        $pdf->SetX($x + $colX[2]);
        $pdf->Cell($colW[2], 0, "Totale ordine: $total", 0, 10, 'L');

        $pdf->Ln(10);
    }

    /**
     * Formatta l'indirizzo come stringa multilinea per TCPDF.
     */
    protected function formatAddress($address, $state, $country)
    {
        $out = '';
        $company = \Tools::strtoupper($address['company'] ?? '');
        $name = \Tools::strtoupper($address['firstname'] . ' ' . $address['lastname']);
        if ($company == $name) {
            $out .= $company . "\n";
        } else {
            $out .= $company . "\n";
            $out .= $name . "\n";
        }
        $out .= ($address['address1'] ?? '') . "\n";
        $out .= ($address['address2'] ?? '') . "\n";
        $out .= ($address['postcode'] ?? '') . ' ';
        $out .= ($address['city'] ?? '') . ' ';
        $out .= (isset($state['iso_code']) ? '(' . $state['iso_code'] . ')' : '') . ' ';
        $out .= (isset($country['iso_code']) ? $country['iso_code'] : '');

        return trim(stripslashes($out));
    }

    protected function writeProducts(\TCPDF &$pdf, $products)
    {
        (new OrderRow($this->id_order, $pdf))->renderHeader();
        foreach ($products as $i => $product) {
            $orderRow = new OrderRow($this->id_order, $pdf, $product, $i);
            $orderRow->renderRow();
        }
    }

    protected function writeCustomizations(\TCPDF $pdf, $customizedProducts)
    {
        if (!$customizedProducts) {
            return;
        }

        foreach ($customizedProducts as $i => $product) {
            $orderCustomization = new OrderCustomization($this->id_order, $pdf, $product, $i);
            $orderCustomization->renderRow();
        }
    }

    protected function writeMessages($pdf, $id_order)
    {
        $messages = OrderMessages::getMessages($id_order);

        if (!$messages) {
            return;
        }

        foreach ($messages as $i => $product) {
            $orderMessages = new OrderMessages($this->id_order, $pdf, $product, $i);
            $orderMessages->renderRow();
        }
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
}
