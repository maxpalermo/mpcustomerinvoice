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

use TCPDF;

class OrderMessages
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

    public static function getMessages($order_id)
    {
        $order = new \Order($order_id);
        if (!\Validate::isLoadedObject($order)) {
            return [];
        }

        $db = \Db::getInstance();
        $sql = new \DbQuery();

        $sql
            ->select('id_employee, content, printable, chat, date_add')
            ->from('mpnote')
            ->where("type='order'")
            ->where('id_order=' . (int) $order_id)
            ->where('printable = 1')
            ->orderBy('date_add DESC');

        return $db->executeS($sql);
    }

    public function renderRow($retry = false)
    {
        $pdf = $this->pdf;
        $message = $this->product;
        $startPage = $pdf->getPage();

        if (!$retry) {
            $pdf->startTransaction();
        }

        if (!$retry && (int) $this->index === 0) {
            $this->writeHeaderMessages($pdf);
            $this->writeHeaderMessagesTable($pdf);
            $this->pdf->setY($this->pdf->GetY() + 1);
        }

        $employees = [];
        if (!isset($message['printable']) || !$message['printable']) {
            if (!$retry) {
                $pdf->commitTransaction();
            }
            return;
        }

        $id_employee = $message['id_employee'];
        $employee = new \Employee($id_employee);
        if (!\Validate::isLoadedObject($employee)) {
            $employees[$id_employee] = 'N/D';
        } else {
            $employees[$id_employee] = \Tools::ucwords($employee->firstname . ' ' . $employee->lastname);
        }

        $impiegato = $employees[$id_employee];
        $data = isset($message['date_add']) ? date('d/m/Y H:i', strtotime($message['date_add'])) : '';
        $messaggio = stripslashes((string) ($message['content'] ?? ''));

        if ($retry) {
            $pdf->setY($pdf->GetY() + 1);
        } else {
            $pdf->setY($pdf->GetY());
        }

        $startY = $pdf->GetY();
        $cellW = [50, 35, 85, 20];
        $cellH = 2;

        // Colonna IMPIEGATO
        $pdf->MultiCell($cellW[0], $cellH, $impiegato, self::$TCPDF_NO_BORDER, 'L', self::$TCPDF_NO_FILL, self::$TCPDF_NO_LN);

        // Colonna DATA
        $pdf->MultiCell($cellW[1], $cellH, $data, self::$TCPDF_NO_BORDER, 'C', self::$TCPDF_NO_FILL, self::$TCPDF_NO_LN);

        // Colonna MESSAGGIO
        $pdf->MultiCell($cellW[2], $cellH, $messaggio, self::$TCPDF_NO_BORDER, 'L', self::$TCPDF_NO_FILL, self::$TCPDF_LN);
        $currentY = $pdf->GetY();

        // Colonna CHAT
        $x = $pdf->GetX();
        $pdf->SetY($startY);
        $pdf->MultiCell($cellW[3], $cellH, '', self::$TCPDF_NO_BORDER, 'C', self::$TCPDF_NO_FILL, self::$TCPDF_NO_LN);
        if (isset($message['chat']) && 1 == $message['chat']) {
            // Centra l'immagine nella cella
            $sumWidth = array_sum($cellW);
            $imgX = $x + $sumWidth - ($cellW[3] / 2);
            $imgY = $startY + ($cellH - 5);
            $filename = _PS_MODULE_DIR_ . 'mpcustomerinvoice/views/img/chat.png';
            if (!file_exists($filename)) {
                $filename = _PS_MODULE_DIR_ . 'mpdocumentprint/views/img/chat.png';
            }
            if (file_exists($filename)) {
                $pdf->Image($filename, $imgX, $imgY, 5, 5);
            }
        }

        $pdf->setY($currentY + 0.5);
        // Linea di separazione
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + array_sum($cellW), $pdf->GetY());
        $pdf->SetY($pdf->GetY() + 1.5);

        if (!$retry) {
            // Se getpage != dalla pagina iniziale procedo al salto pagina, altrimenti faccio il commit
            $checkPage = $pdf->getPage();
            if ($checkPage !== $startPage) {
                $pdf->rollbackTransaction(true);
                $pdf->AddPage();
                $this->writeHeaderOrderNum($pdf, $this->id_order);
                $this->writeHeaderMessages($pdf);
                $this->writeHeaderMessagesTable($pdf);
                $this->renderRow(true);

                return;
            }

            $pdf->commitTransaction();
        }
    }
}
