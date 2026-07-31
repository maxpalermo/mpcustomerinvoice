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

namespace MpSoft\MpCustomerInvoice\PrintPdf\Templates;

class PrintTemplateFactory
{
    /**
     * Scans the filesystem under src/PrintPdf/Templates/{DocType}/ and returns available template folder names.
     *
     * @param string $docType E.g. 'Orders', 'Invoices', 'Deliveries', 'Addresses'
     * @return array Array of template names (e.g. ['Default' => 'Default', 'Dalavoro' => 'Dalavoro'])
     */
    public static function getAvailableTemplates(string $docType): array
    {
        $docTypeDir = ucfirst(trim($docType));
        $baseDir = _PS_MODULE_DIR_ . 'mpcustomerinvoice/src/PrintPdf/Templates/' . $docTypeDir;

        $templates = [];

        if (is_dir($baseDir)) {
            $items = scandir($baseDir);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $fullPath = $baseDir . '/' . $item;
                    if (is_dir($fullPath)) {
                        $templates[$item] = $item;
                    }
                }
            }
        }

        if (empty($templates)) {
            $templates['Default'] = 'Default';
        }

        ksort($templates);

        return $templates;
    }

    /**
     * Instantiates a template object implementing PrintTemplateInterface.
     *
     * @param string $docType E.g. 'Orders', 'Invoices', 'Deliveries', 'Addresses'
     * @param string $templateName E.g. 'Default', 'Dalavoro'
     * @param int $idOrder
     * @return PrintTemplateInterface|null
     */
    public static function createTemplate(string $docType, string $templateName, int $idOrder): ?PrintTemplateInterface
    {
        $docTypeDir = ucfirst(trim($docType));
        $tplName = ucfirst(trim($templateName));

        if (empty($tplName)) {
            $tplName = 'Default';
        }

        $singularType = rtrim($docTypeDir, 's'); // Orders -> Order

        // Candidate class names to check:
        // 1. MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\DefaultOrderTemplate
        // 2. MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\OrderTemplate
        // 3. MpSoft\MpCustomerInvoice\PrintPdf\Templates\Orders\Default\Template
        $candidates = [
            "MpSoft\\MpCustomerInvoice\\PrintPdf\\Templates\\{$docTypeDir}\\{$tplName}\\{$tplName}{$singularType}Template",
            "MpSoft\\MpCustomerInvoice\\PrintPdf\\Templates\\{$docTypeDir}\\{$tplName}\\{$singularType}Template",
            "MpSoft\\MpCustomerInvoice\\PrintPdf\\Templates\\{$docTypeDir}\\{$tplName}\\Template",
        ];

        foreach ($candidates as $className) {
            if (class_exists($className)) {
                $instance = new $className($idOrder);
                if ($instance instanceof PrintTemplateInterface) {
                    return $instance;
                }
            }
        }

        // Fallback to Default if specified template class was not found
        if ($tplName !== 'Default') {
            return self::createTemplate($docTypeDir, 'Default', $idOrder);
        }

        return null;
    }
}
