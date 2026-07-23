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

namespace MpSoft\MpCustomerInvoice\Export;

class JsonToXml
{
    /**
     * Converts an array to XML string and triggers the download in the browser.
     *
     * @param array $data The document data as array
     * @param string $filename The name of the downloaded file
     * @return void
     */
    public static function convertAndDownload(array $data, $filename)
    {
        $xmlString = self::toXml($data);
        self::downloadXml($xmlString, $filename);
    }

    /**
     * Converts an associative array to XML string.
     *
     * @param array $data
     * @param string $rootElement
     * @return string
     */
    public static function toXml(array $data, $rootElement = 'invoices')
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootElement . '></' . $rootElement . '>');

        if (isset($data[$rootElement])) {
            $data = $data[$rootElement];
        }

        self::arrayToXml($data, $xml);

        // Format XML to be pretty-printed
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Helper recursive function to convert array to XML.
     *
     * @param array $data
     * @param \SimpleXMLElement $xmlData
     * @return void
     */
    private static function arrayToXml(array $data, &$xmlData)
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'row';
            }
            if (is_array($value)) {
                if (self::isAssoc($value)) {
                    $subnode = $xmlData->addChild($key);
                    self::arrayToXml($value, $subnode);
                } else {
                    foreach ($value as $v) {
                        $subnode = $xmlData->addChild($key);
                        if (is_array($v)) {
                            self::arrayToXml($v, $subnode);
                        } else {
                            $subnode->addChild($key, htmlspecialchars((string)$v));
                        }
                    }
                }
            } else {
                $xmlData->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    /**
     * Check if array is associative.
     *
     * @param array $arr
     * @return bool
     */
    private static function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Force the browser download of the XML string.
     *
     * @param string $xmlString
     * @param string $filename
     * @return void
     */
    private static function downloadXml($xmlString, $filename)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xmlString));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $xmlString;
        exit;
    }

    /**
     * Converts XML string to JSON string.
     *
     * @param string $xmlString
     * @return string
     */
    public static function xmlToJson($xmlString)
    {
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            return json_encode(['error' => 'Invalid XML']);
        }

        return json_encode($xml, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}