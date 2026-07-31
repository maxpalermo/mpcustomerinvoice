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

namespace MpSoft\MpCustomerInvoice\Helpers;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;
use setasign\Fpdi\Fpdi as StandardFpdi;

class PdfMerger
{
    /**
     * Merges multiple PDF binary strings or base64 strings into a single merged PDF binary stream.
     *
     * @param array $pdfList List of PDF binary or base64 strings
     * @return string Binary PDF content
     * @throws Exception
     */
    public static function mergePdfs(array $pdfList): string
    {
        if (empty($pdfList)) {
            throw new InvalidArgumentException('Nessun documento PDF fornito per l\'unione.');
        }

        $validBins = [];
        foreach ($pdfList as $item) {
            if (empty($item)) {
                continue;
            }
            $bin = base64_decode($item, true);
            if ($bin === false || strpos($bin, '%PDF') !== 0) {
                if (strpos($item, '%PDF') === 0) {
                    $bin = $item;
                } else {
                    continue;
                }
            }
            $validBins[] = $bin;
        }

        if (empty($validBins)) {
            throw new RuntimeException('Nessun contenuto PDF valido trovato.');
        }

        if (count($validBins) === 1) {
            return $validBins[0];
        }

        self::ensureFpdiLoaded();

        $tmpFiles = [];
        try {
            if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                $pdf = new TcpdfFpdi();
            } elseif (class_exists('setasign\Fpdi\Fpdi')) {
                $pdf = new StandardFpdi();
            } else {
                throw new RuntimeException('Libreria FPDI non disponibile per l\'unione dei PDF.');
            }

            if (method_exists($pdf, 'setPrintHeader')) {
                $pdf->setPrintHeader(false);
            }
            if (method_exists($pdf, 'setPrintFooter')) {
                $pdf->setPrintFooter(false);
            }
            if (method_exists($pdf, 'SetAutoPageBreak')) {
                $pdf->SetAutoPageBreak(false);
            }

            foreach ($validBins as $index => $binary) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'mp_pdf_merge_' . $index . '_') . '.pdf';
                file_put_contents($tmpFile, $binary);
                $tmpFiles[] = $tmpFile;

                $pageCount = $pdf->setSourceFile($tmpFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            return $pdf->Output('merged_documents.pdf', 'S');
        } finally {
            foreach ($tmpFiles as $f) {
                if (file_exists($f)) {
                    @unlink($f);
                }
            }
        }
    }

    private static function ensureFpdiLoaded(): void
    {
        if (!class_exists('TCPDF')) {
            $root = defined('_PS_ROOT_DIR_') ? _PS_ROOT_DIR_ : dirname(__DIR__, 4);
            $tcpdfPath = $root . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            if (file_exists($tcpdfPath)) {
                require_once $tcpdfPath;
            }
        }

        if (class_exists('setasign\Fpdi\Tcpdf\Fpdi') || class_exists('setasign\Fpdi\Fpdi')) {
            return;
        }

        $moduleDir = defined('_PS_MODULE_DIR_') ? _PS_MODULE_DIR_ : dirname(__DIR__, 2) . '/';

        $paths = [
            $moduleDir . 'mpbrtrestapishipments/vendor/autoload.php',
            $moduleDir . 'mbeshipping/vendor/autoload.php',
            $moduleDir . 'mplabelprint/vendor/autoload.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('setasign\Fpdi\Tcpdf\Fpdi') || class_exists('setasign\Fpdi\Fpdi')) {
                    return;
                }
            }
        }
    }
}
