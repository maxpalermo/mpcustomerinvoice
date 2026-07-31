<?php

namespace MpSoft\MpCustomerInvoice\PrintPdf;

use Configuration;

class PrintPdfAddress extends PrintManager
{
    protected float $labelWidth = 100.0;
    protected float $labelHeight = 80.0;

    public function __construct(int $idOrder, int $copies = 1, bool $print = false, string $outputMode = 'I')
    {
        $width = (float) Configuration::get('MPCUSTOMERINVOICE_LABEL_WIDTH');
        $height = (float) Configuration::get('MPCUSTOMERINVOICE_LABEL_HEIGHT');

        $this->labelWidth = $width > 0 ? $width : 100.0;
        $this->labelHeight = $height > 0 ? $height : 80.0;

        $orientation = $this->labelWidth >= $this->labelHeight ? 'L' : 'P';

        if ($orientation === 'L') {
            $pageFormat = [$this->labelHeight, $this->labelWidth];
        } else {
            $pageFormat = [$this->labelWidth, $this->labelHeight];
        }

        $this->margin_left = 3;
        $this->margin_top = 3;
        $this->margin_right = 3;
        $this->margin_foot = 3;

        parent::__construct($idOrder, $copies, $print, $outputMode, $orientation, $pageFormat);
    }

    protected function initComponents(): void
    {
        $this->headerComponent = null;
        $this->footerComponent = null;
    }

    public function renderPdf(): string
    {
        // Disabilitazione totale dell'AutoPageBreak per garantire 1 sola pagina per copia
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 0);

        $invoice = $this->orderData['invoice'] ?? ($this->orderData['invoices']['invoice'] ?? []);
        $customer = $invoice['customer'] ?? [];
        $delivery = $customer['address_delivery'] ?? [];

        $totalPaid = $invoice['total_paid'] ?? $invoice['total_tax_incl'] ?? '0.00';
        if ($this->order && (!isset($invoice['total_paid']) || (float) $totalPaid <= 0)) {
            $totalPaid = $this->order->total_paid_tax_incl;
        }

        $usableW = $this->labelWidth - $this->margin_left - $this->margin_right;
        $usableH = $this->labelHeight - $this->margin_top - $this->margin_foot;

        $logoPath = $this->getLogoPath();
        $isCOD = $this->isCashOnDelivery();

        for ($i = 1; $i <= $this->copies; $i++) {
            $this->AddPage();

            $x = $this->margin_left;
            $y = $this->margin_top;

            // 1. LOGO IN ALTO AL CENTRO
            $logoH = 8.0;
            if (file_exists($logoPath)) {
                $logoWidth = min(48, $usableW * 0.52);
                $logoX = $x + ($usableW - $logoWidth) / 2;

                $imgSize = @getimagesize($logoPath);
                if ($imgSize && $imgSize[0] > 0) {
                    $logoH = ($logoWidth / $imgSize[0]) * $imgSize[1];
                    if ($logoH > 11) {
                        $logoH = 11;
                    }
                }

                $this->Image($logoPath, $logoX, $y, $logoWidth, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $y += $logoH + 3.0;
            } else {
                $this->SetFont('helvetica', 'B', 12);
                $this->SetXY($x, $y);
                $this->Cell($usableW, 7, 'DALAVORO', 0, 1, 'C');
                $y += 9;
            }

            // ABBASSAMENTO DI 1 CM (10 MM) DEI BOX E DEI DATI DELL'INDIRIZZO
            $y += 10.0;

            // 2. BOX ID ORDINE & NUMERO COPIA (SPOSTATI PIÙ IN BASSO DI 1 CM)
            $boxH = 8.5;
            $boxOrderW = 36;
            $boxCopyW = 15;

            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.3);

            // Box Ordine
            $this->Rect($x, $y, $boxOrderW, $boxH);
            $this->SetFont('helvetica', 'B', 14.5);
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($x, $y + 0.3);
            $this->Cell($boxOrderW, $boxH - 0.6, (string) $this->idOrder, 0, 0, 'C');

            // Box Copie
            $copyX = $x + $usableW - $boxCopyW;
            $this->Rect($copyX, $y, $boxCopyW, $boxH);
            $this->SetFont('helvetica', 'B', 14.5);
            $this->SetXY($copyX, $y + 0.3);
            $this->Cell($boxCopyW, $boxH - 0.6, (string) $i, 0, 0, 'C');

            $y += $boxH + 3.0;

            // 3. FOOTER POSIZIONATO IN BASSO RIGIDAMENTE (NON VIENE SPOSTATO)
            $footerH = 11.5;
            $footerY = $this->labelHeight - $this->margin_foot - $footerH;

            // Dati Telefono per il Box sopra il Barcode
            $phone = trim($delivery['phone_mobile'] ?? $delivery['phone'] ?? '');
            $phoneBoxH = !empty($phone) ? 8.0 : 0.0;

            // 4. CALCOLO DINAMICO DELLO SPAZIO DISPONIBILE PER INTESTATARIO + INDIRIZZO CENTRATI
            $maxCenterH = $footerY - $phoneBoxH - $y - 1.5;

            // Dati Destinatario
            $company = trim(strtoupper($delivery['company'] ?? ''));
            $firstname = trim(strtoupper($delivery['firstname'] ?? ''));
            $lastname = trim(strtoupper($delivery['lastname'] ?? ''));
            $fullName = trim($firstname . ' ' . $lastname);

            $recipientLines = [];
            if (!empty($company) && !empty($fullName) && $company !== $fullName) {
                $recipientLines[] = $company;
                $recipientLines[] = $fullName;
            } else if (!empty($company)) {
                $recipientLines[] = $company;
            } else if (!empty($fullName)) {
                $recipientLines[] = $fullName;
            } else {
                $recipientLines[] = 'DESTINATARIO NON SPECIFICATO';
            }

            // Dati Indirizzo
            $addr1 = trim(strtoupper($delivery['address1'] ?? ''));
            $addr2 = trim(strtoupper($delivery['address2'] ?? ''));
            $fullAddr = array_filter([$addr1, $addr2]);
            $addrText = implode(' , ', $fullAddr);

            $postcode = trim($delivery['postcode'] ?? '');
            $city = trim(strtoupper($delivery['city'] ?? ''));
            $state = trim(strtoupper($delivery['state'] ?? $delivery['state_name'] ?? ''));
            $cityText = array_filter([$postcode, $city, $state]);
            $cityLine = implode(' - ', $cityText);

            $country = trim(strtoupper($delivery['country_name'] ?? $delivery['country'] ?? 'ITALIA'));

            // Righe al centro (Intestatario + Indirizzo + Nazione)
            $totalLines = count($recipientLines) + ($addrText ? 1 : 0) + ($cityLine ? 1 : 0) + ($country ? 1 : 0);

            $lineH = min(5.5, max(3.8, ($maxCenterH - 1) / $totalLines));
            $recipientFontSize = min(14.0, max(10.5, $lineH * 2.4));
            $addressFontSize = min(12.5, max(9.0, $lineH * 2.2));

            // Stampa Intestatario (Centrato 'C', Grassetto)
            $this->SetFont('helvetica', 'B', $recipientFontSize);
            foreach ($recipientLines as $recLine) {
                $this->SetXY($x, $y);
                $this->Cell($usableW, $lineH, $recLine, 0, 1, 'C');
                $y += $lineH;
            }
            $y += 1.0;

            // Stampa Indirizzo (TUTTI CENTRATI 'C', Maiuscolo)
            $this->SetFont('helvetica', '', $addressFontSize);
            if ($addrText) {
                $this->SetXY($x, $y);
                $this->Cell($usableW, $lineH, $addrText, 0, 1, 'C');
                $y += $lineH;
            }

            if ($cityLine) {
                $this->SetXY($x, $y);
                $this->Cell($usableW, $lineH, $cityLine, 0, 1, 'C');
                $y += $lineH;
            }

            if ($country) {
                $this->SetXY($x, $y);
                $this->Cell($usableW, $lineH, $country, 0, 1, 'C');
                $y += $lineH;
            }

            // 5. RENDERING BOX TELEFONO APPENA SOPRA IL CODICE A BARRE (POSIZIONE FISSA)
            $barcodeW = 40;
            $barcodeH = $footerH;
            $barcodeX = $x + $usableW - $barcodeW;

            if (!empty($phone)) {
                $phoneBoxW = $barcodeW;
                $phoneBoxH = 7.0;
                $phoneBoxY = $footerY - $phoneBoxH - 1.5;

                $this->SetDrawColor(0, 0, 0);
                $this->SetLineWidth(0.3);
                $this->Rect($barcodeX, $phoneBoxY, $phoneBoxW, $phoneBoxH);

                $this->SetFont('helvetica', 'B', 11);
                $this->SetTextColor(0, 0, 0);
                $this->SetXY($barcodeX, $phoneBoxY + 0.5);
                $this->Cell($phoneBoxW, $phoneBoxH - 1, $phone, 0, 0, 'C');
            }

            // 6. RENDERING FOOTER RIGIDO IN BASSO ($footerY) (POSIZIONE FISSA)
            if ($isCOD) {
                // Box Totale da Pagare per Contrassegno
                $boxTotalW = 38;
                $boxTotalH = $footerH;
                $this->Rect($x, $footerY, $boxTotalW, $boxTotalH);

                $this->SetFont('helvetica', 'B', 7);
                $this->SetXY($x, $footerY + 0.8);
                $this->Cell($boxTotalW, 3.2, 'TOTALE DA PAGARE', 0, 1, 'C');

                $this->SetFont('helvetica', 'B', 10.5);
                $this->SetXY($x, $footerY + 4.5);
                $this->Cell($boxTotalW, 5, number_format((float) $totalPaid, 2, ',', '.') . ' €', 0, 0, 'C');
            }

            // Barcode a destra (Code128)
            $barcodeCode = $this->idOrder . '-' . $i;

            $styleBarcode = [
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfstyle' => '',
                'border' => false,
                'hpadding' => 'auto',
                'vpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => true,
                'font' => 'helvetica',
                'fontsize' => 7,
                'stretchtext' => 4
            ];

            $this->write1DBarcode($barcodeCode, 'C128', $barcodeX, $footerY, $barcodeW, $barcodeH, 0.4, $styleBarcode, 'N');
        }

        $filename = 'Etichetta_' . $this->idOrder . '.pdf';

        if ($this->print) {
            return chunk_split(base64_encode($this->Output($filename, 'S')));
        }

        if ($this->outputMode === 'I') {
            $this->Output($filename, 'I');
            return "Stampa PDF Etichetta #{$this->idOrder} ({$this->copies} copie) inviata al browser.";
        }

        return $this->Output($filename, 'S');
    }

    /**
     * Verifica se l'ordine è stato pagato in contrassegno.
     */
    protected function isCashOnDelivery(): bool
    {
        $invoice = $this->orderData['invoice'] ?? ($this->orderData['invoices']['invoice'] ?? $this->orderData);

        $moduleName = strtolower($this->order->module ?? $invoice['module'] ?? '');
        $paymentName = strtolower($this->order->payment ?? $invoice['payment'] ?? $invoice['payment_method'] ?? '');

        // 1. Configurazione specifica del modulo se presente
        $codModulesConfig = Configuration::get('MPCUSTOMERINVOICE_COD_MODULES');
        if (!empty($codModulesConfig)) {
            $codModules = array_map('trim', array_map('strtolower', explode(',', (string) $codModulesConfig)));
            if (in_array($moduleName, $codModules, true)) {
                return true;
            }
            foreach ($codModules as $codMod) {
                if (!empty($codMod) && (strpos($moduleName, $codMod) !== false || strpos($paymentName, $codMod) !== false)) {
                    return true;
                }
            }
        }

        // 2. Spese contrassegno presenti
        if (!empty($invoice['fees']['fee_tax_incl']) && (float) $invoice['fees']['fee_tax_incl'] > 0) {
            return true;
        }

        // 3. Nomi moduli contrassegno noti
        $knownCodModules = ['ps_cashondelivery', 'cashondelivery', 'maofree_cashondelivery', 'cod', 'mppaymentswithfees', 'cashondeliverywithfee'];
        if (in_array($moduleName, $knownCodModules, true)) {
            return true;
        }

        // 4. Controllo testo metodo di pagamento o nome modulo
        $keywords = ['contrassegno', 'cash on delivery', 'cashondelivery', 'cod', 'alla consegna', 'contanti alla consegna'];
        foreach ($keywords as $kw) {
            if (strpos($paymentName, $kw) !== false || strpos($moduleName, $kw) !== false) {
                return true;
            }
        }

        return false;
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
