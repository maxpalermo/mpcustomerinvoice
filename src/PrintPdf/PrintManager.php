<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use TCPDF;
use Order;
use OrderState;
use Validate;
use Context;
use Currency;
use Configuration;
use MpSoft\MpCustomerInvoice\Export\ExportOrder;

abstract class PrintManager extends TCPDF
{
    protected int $idOrder = 0;
    protected int $copies = 1;
    protected ?Order $order = null;
    protected array $orderData = [];
    protected $context = null;
    protected int $idShop = 1;
    protected int $idLang = 1;
    protected ?OrderState $currentOrderStatus = null;
    protected bool $print = false;
    protected string $outputMode = 'I';

    // Margini di default
    protected float $margin_left = 15;
    protected float $margin_top = 40;
    protected float $margin_right = 15;
    protected float $margin_foot = 20;

    // Componenti del documento (Header, Body, Footer)
    protected $headerComponent = null;
    protected $bodyComponent = null;
    protected $footerComponent = null;

    public function __construct(
        int $idOrder,
        int $copies = 1,
        bool $print = false,
        string $outputMode = 'I',
        string $orientation = 'P',
        $pageFormat = 'A4'
    ) {
        $this->idOrder = $idOrder;
        $this->copies = max(1, $copies);
        $this->print = $print;
        $this->outputMode = $outputMode;

        $this->context = Context::getContext();
        if (!$this->context->currency) {
            $this->context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        $this->idShop = (int) ($this->context->shop->id ?? 1);
        $this->idLang = (int) ($this->context->language->id ?? 1);

        $this->loadOrderData();

        parent::__construct($orientation, 'mm', $pageFormat, true, 'UTF-8', false);

        $this->setupDocument();
        $this->initComponents();
    }

    public function getIdOrder(): int
    {
        return $this->idOrder;
    }

    public function getCopies(): int
    {
        return $this->copies;
    }

    public function getOrderData(): array
    {
        return $this->orderData;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    protected function loadOrderData(): void
    {
        if ($this->idOrder > 0) {
            $order = new Order($this->idOrder);
            if (Validate::isLoadedObject($order)) {
                $this->order = $order;
                $this->currentOrderStatus = new OrderState((int) $order->current_state, $this->idLang);
            }
            if (class_exists('\MpSoft\MpCustomerInvoice\Export\ExportOrder')) {
                $exporter = new ExportOrder($this->idOrder, 'order');
                $raw = $exporter->getData();
                $invoiceData = $raw['invoices']['invoice'] ?? $raw['invoice'] ?? $raw;
                $this->orderData = array_merge($raw, [
                    'invoice' => $invoiceData,
                    'invoices' => ['invoice' => $invoiceData]
                ]);
            }
        }
    }

    protected function setupDocument(): void
    {
        $this->SetCreator(defined('PDF_CREATOR') ? PDF_CREATOR : 'TCPDF');
        $this->SetAuthor('Massimiliano Palermo');
        $this->SetTitle('Document ' . $this->idOrder);
        $this->SetSubject('PrestaShop Document');
        $this->SetKeywords('PrestaShop, order, print, pdf, ' . $this->idOrder);

        $this->SetMargins($this->margin_left, $this->margin_top, $this->margin_right);
        $this->SetHeaderMargin($this->margin_top);
        $this->SetFooterMargin($this->margin_foot);

        $this->SetAutoPageBreak(true, $this->margin_foot - 10);
        $this->setImageScale(defined('PDF_IMAGE_SCALE_RATIO') ? PDF_IMAGE_SCALE_RATIO : 1.25);
        $this->setFontSubsetting(true);
        $this->SetFont('dejavusans', '', 10, '', true);
    }

    abstract protected function initComponents(): void;

    public function setComponents($header, $body, $footer): void
    {
        $this->headerComponent = $header;
        $this->bodyComponent = $body;
        $this->footerComponent = $footer;
    }

    public function Header()
    {
        if ($this->headerComponent && method_exists($this->headerComponent, 'render')) {
            $this->headerComponent->render($this);
        }
    }

    public function Footer()
    {
        if ($this->footerComponent && method_exists($this->footerComponent, 'render')) {
            $this->footerComponent->render($this);
        }
    }

    public function renderPdf(): string
    {
        $this->AddPage('', '', true);

        if ($this->bodyComponent && method_exists($this->bodyComponent, 'render')) {
            $this->bodyComponent->render($this);
        }

        $id = $this->idOrder;
        $copies = $this->copies;

        if ($this->print) {
            return chunk_split(base64_encode($this->Output('Order_' . $id . '.pdf', 'S')));
        } else {
            if ($this->outputMode === 'I') {
                $this->Output('Order_' . $id . '.pdf', 'I');
                return "Stampa PDF Ordine #{$id} (Copie: {$copies}) inviata all'output del browser.";
            } else {
                return $this->Output('Order_' . $id . '.pdf', 'S');
            }
        }
    }
}
