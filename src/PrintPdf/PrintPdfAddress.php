<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use Configuration;

class PrintPdfAddress extends PrintManager
{
    protected float $labelWidth = 100.0;
    protected float $labelHeight = 50.0;

    public function __construct(int $idOrder, int $copies = 1, bool $print = false, string $outputMode = 'I')
    {
        $width = (float) Configuration::get('MPCUSTOMERINVOICE_LABEL_WIDTH');
        $height = (float) Configuration::get('MPCUSTOMERINVOICE_LABEL_HEIGHT');

        $this->labelWidth = $width > 0 ? $width : 100.0;
        $this->labelHeight = $height > 0 ? $height : 50.0;

        // L'orientamento dipende dalle dimensioni reali dell'etichetta
        $orientation = $this->labelWidth >= $this->labelHeight ? 'L' : 'P';

        // Per le etichette usiamo il formato custom [W, H] in mm (dimensione fisica del foglio)
        // TCPDF si aspetta [larghezza, altezza] in formato PORTRAIT (la dimensione più corta come W)
        if ($orientation === 'L') {
            $pageFormat = [$this->labelHeight, $this->labelWidth];
        } else {
            $pageFormat = [$this->labelWidth, $this->labelHeight];
        }

        // Margini ridotti per massimizzare lo spazio sull'etichetta
        $this->margin_left = 3;
        $this->margin_top = 3;
        $this->margin_right = 3;
        $this->margin_foot = 3;

        parent::__construct($idOrder, $copies, $print, $outputMode, $orientation, $pageFormat);
    }

    protected function initComponents(): void
    {
        // L'etichetta non usa header/footer standard di PrintManager
        $this->headerComponent = null;
        $this->footerComponent = null;
    }

    public function renderPdf(): string
    {
        // Disabilita header/footer automatici di TCPDF
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 0);

        // Dati ordine
        $invoice = $this->orderData['invoice'] ?? ($this->orderData['invoices']['invoice'] ?? []);
        $orderDate = $invoice['order_date'] ?? '';
        $customer = $invoice['customer'] ?? [];
        $deliveryAddress = $customer['address_delivery'] ?? [];

        // Fallback alla data dell'oggetto Order
        if (empty($orderDate) && $this->order) {
            $orderDate = $this->order->date_add;
        }

        $formattedDate = !empty($orderDate) ? date('d/m/Y', strtotime($orderDate)) : date('d/m/Y');

        // Area utile dell'etichetta
        $usableW = $this->labelWidth - $this->margin_left - $this->margin_right;
        $usableH = $this->labelHeight - $this->margin_top - $this->margin_foot;

        for ($i = 1; $i <= $this->copies; $i++) {
            $this->AddPage();

            $x = $this->margin_left;
            $y = $this->margin_top;

            $orderLabel = $this->idOrder . '-' . $i;

            // --- Banda ordine (sfondo scuro, testo bianco) ---
            $headerH = min(8, $usableH * 0.18);
            $this->SetFillColor(50, 50, 50);
            $this->SetTextColor(255, 255, 255);
            $this->RoundedRect($x, $y, $usableW, $headerH, 1.5, '0011', 'DF', [], [50, 50, 50]);

            // Numero ordine a sinistra
            $fontSize = min(9, max(6, $headerH * 0.85));
            $this->SetFont('dejavusans', 'B', $fontSize);
            $this->SetXY($x + 2, $y + ($headerH - $fontSize * 0.35) / 2 - 0.5);
            $this->Cell($usableW * 0.6, $headerH - 2, 'Ordine: ' . $orderLabel, 0, 0, 'L');

            // Data a destra
            $this->SetFont('dejavusans', '', max(5, $fontSize - 1.5));
            $this->SetXY($x + $usableW * 0.6, $y + ($headerH - $fontSize * 0.35) / 2 - 0.5);
            $this->Cell($usableW * 0.4 - 2, $headerH - 2, $formattedDate, 0, 0, 'R');

            // --- Box indirizzo (bordo grigio chiaro, sfondo bianco) ---
            $addressY = $y + $headerH + 1;
            $addressH = $usableH - $headerH - 1;

            $this->SetDrawColor(180, 180, 180);
            $this->SetFillColor(255, 255, 255);
            $this->RoundedRect($x, $addressY, $usableW, $addressH, 1.5, '1100', 'DF', ['all' => ['width' => 0.3, 'color' => [180, 180, 180]]], [255, 255, 255]);

            // Titoletto "SPEDIRE A:"
            $titleH = min(5, $addressH * 0.15);
            $labelFontSize = max(4.5, min(6, $titleH * 0.9));
            $this->SetTextColor(100, 100, 100);
            $this->SetFont('dejavusans', 'B', $labelFontSize);
            $this->SetXY($x + 2, $addressY + 1);
            $this->Cell($usableW - 4, $titleH, 'SPEDIRE A:', 0, 1, 'L');

            // Righe indirizzo
            $contentY = $addressY + $titleH + 1.5;
            $contentH = $addressH - $titleH - 3;
            $this->SetTextColor(0, 0, 0);

            $this->renderAddressBlock($x + 2, $contentY, $usableW - 4, $contentH, $deliveryAddress);
        }

        // Output
        $filename = 'Etichetta_' . $this->idOrder . '.pdf';

        if ($this->print) {
            return chunk_split(base64_encode($this->Output($filename, 'S')));
        }

        if ($this->outputMode === 'I') {
            $this->Output($filename, 'I');
            return "Stampa PDF Etichetta #{$this->idOrder} ({$this->copies} copie, {$this->labelWidth}x{$this->labelHeight}mm) inviata al browser.";
        }

        return $this->Output($filename, 'S');
    }

    /**
     * Renderizza il blocco indirizzo adattando la dimensione del font allo spazio disponibile.
     */
    protected function renderAddressBlock(float $x, float $y, float $maxW, float $maxH, array $address): void
    {
        if (empty($address)) {
            $this->SetFont('dejavusans', 'I', 7);
            $this->SetXY($x, $y);
            $this->Cell($maxW, 5, 'Indirizzo non disponibile', 0, 0, 'L');
            return;
        }

        // Costruiamo le righe dell'indirizzo
        $lines = $this->buildAddressLines($address);
        if (empty($lines)) {
            return;
        }

        // Calcola font size ottimale in base allo spazio disponibile
        $lineCount = count($lines);
        $maxFontSize = 9;
        $minFontSize = 5;
        $lineSpacing = 1.15; // rapporto tra altezza riga e font size (in punti)

        // Partendo dal font più grande, riduciamo fino a che le righe entrano nel box
        $fontSize = $maxFontSize;
        for ($fs = $maxFontSize; $fs >= $minFontSize; $fs -= 0.5) {
            $lineH = $fs * 0.42; // approssimazione altezza riga in mm
            $totalH = $lineCount * $lineH * $lineSpacing;
            if ($totalH <= $maxH) {
                $fontSize = $fs;
                break;
            }
            $fontSize = $fs;
        }

        $lineH = $fontSize * 0.42;
        $currentY = $y;

        foreach ($lines as $idx => $line) {
            if (($currentY - $y + $lineH) > $maxH) {
                break; // non sforiamo dal box
            }

            $style = '';
            // La prima riga (nome/azienda) in grassetto
            if ($idx === 0) {
                $style = 'B';
            }

            $this->SetFont('dejavusans', $style, $fontSize);
            $this->SetXY($x, $currentY);
            $this->Cell($maxW, $lineH * $lineSpacing, $line, 0, 1, 'L');
            $currentY += $lineH * $lineSpacing;
        }
    }

    /**
     * Costruisce un array di righe dell'indirizzo a partire dai dati grezzi.
     */
    protected function buildAddressLines(array $addr): array
    {
        $lines = [];

        // Riga 1: Azienda oppure Nome Cognome
        $company = trim($addr['company'] ?? '');
        $firstname = trim($addr['firstname'] ?? '');
        $lastname = trim($addr['lastname'] ?? '');

        if (!empty($company)) {
            $lines[] = $company;
            // Se c'è anche nome e cognome, aggiungiamo come riga sotto
            $fullName = trim($firstname . ' ' . $lastname);
            if (!empty($fullName)) {
                $lines[] = $fullName;
            }
        } else {
            $fullName = trim($firstname . ' ' . $lastname);
            if (!empty($fullName)) {
                $lines[] = $fullName;
            }
        }

        // Indirizzo
        $address1 = trim($addr['address1'] ?? '');
        if (!empty($address1)) {
            $lines[] = $address1;
        }
        $address2 = trim($addr['address2'] ?? '');
        if (!empty($address2)) {
            $lines[] = $address2;
        }

        // CAP + Città + Provincia
        $postcode = trim($addr['postcode'] ?? '');
        $city = trim($addr['city'] ?? '');
        $state = trim($addr['state_name'] ?? ($addr['state'] ?? ''));
        $cityLine = '';
        if (!empty($postcode)) {
            $cityLine .= $postcode . ' ';
        }
        if (!empty($city)) {
            $cityLine .= $city;
        }
        if (!empty($state)) {
            $cityLine .= ' (' . $state . ')';
        }
        $cityLine = trim($cityLine);
        if (!empty($cityLine)) {
            $lines[] = $cityLine;
        }

        // Paese
        $country = trim($addr['country_name'] ?? ($addr['country'] ?? ''));
        if (!empty($country)) {
            $lines[] = $country;
        }

        // Telefono
        $phone = trim($addr['phone'] ?? '');
        $phoneMobile = trim($addr['phone_mobile'] ?? '');
        if (!empty($phoneMobile)) {
            $lines[] = 'Tel: ' . $phoneMobile;
        } elseif (!empty($phone)) {
            $lines[] = 'Tel: ' . $phone;
        }

        return $lines;
    }
}
