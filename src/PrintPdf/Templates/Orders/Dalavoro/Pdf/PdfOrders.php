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

use setasign\Fpdi\Fpdi;

class PdfOrders
{
    public static function mergePdfStreams(array $streams, $orientation = 'P', $unit = 'mm', $format = 'A4')
    {
        // Specifica unità 'mm' direttamente
        $pdf = new Fpdi($orientation, $unit, $format);

        foreach ($streams as $stream) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pdf');
            file_put_contents($tmpFile, $stream);

            $pageCount = $pdf->setSourceFile($tmpFile);
            for ($pageNo = 1; $pageNo <= $pageCount; ++$pageNo) {
                $tplIdx = $pdf->importPage($pageNo);
                $pdf->AddPage($orientation, $format);
                $pdf->useTemplate($tplIdx);
            }

            unlink($tmpFile);
        }

        return $pdf->Output('S');
    }

    /**
     * Genera i PDF per tutti gli id_order e li unisce in un unico stream.
     *
     * @return string PDF stream
     */
    public static function render(array $id_orders)
    {
        $streams = [];
        foreach ($id_orders as $id_order) {
            $pdfOrder = new PdfOrder($id_order);
            $pdfOrder->create()->render();
            $stream = $pdfOrder->getStream();
            $streams[] = $stream;
        }

        return self::mergePdfStreams($streams);
    }
}
