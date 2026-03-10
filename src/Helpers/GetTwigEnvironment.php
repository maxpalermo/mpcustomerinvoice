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

namespace MpSoft\MpCustomerInvoice\Helpers;

use Twig\Environment;

class GetTwigEnvironment
{
    /** @var string */
    protected $module_name;
    /** @var \Context */
    protected $context;
    /** @var string */
    protected $path = null;
    /** @var Environment */
    protected $twig;

    public function __construct($module_name)
    {
        $this->module_name = $module_name;
        $this->context = \Context::getContext();
    }

    public function load($path)
    {
        // Base URL
        $baseUrl = $this->context->link->getBaseLink();
        // Base Path
        $basePath = _PS_MODULE_DIR_;
        // Modulo corrente
        $moduleName = "{$this->module_name}";
        // Percorso completo modulo
        $modulePath = "{$basePath}{$moduleName}/";
        // Percorso della cartella views
        $moduleViewsPath = "{$basePath}{$moduleName}/views/";
        // Percorso della cartella dei template dei moduli
        $moduleTwigPath = "{$basePath}{$moduleName}/views/twig/";
        // Percorso della cartella assets
        $moduleAssetsPath = "{$basePath}{$moduleName}/views/assets/";
        // Nome file
        if (!preg_match('/\.html\.twig$/i', $path)) {
            $path .= '.html.twig';
        }

        // Inizializza il FilesystemLoader e aggiungi un percorso
        // Il primo argomento è il percorso fisico
        // Il secondo è il nome del namespace
        $loader = new \Twig\Loader\FilesystemLoader($moduleTwigPath);
        $loader->addPath($basePath, 'Modules');
        $loader->addPath($modulePath, 'Module');
        $loader->addPath($moduleViewsPath, 'ModuleViews');

        // Inizializza l'Environment Twig
        $this->twig = new Environment($loader);
        $this->twig->addGlobal('baseUrl', $this->context->link->getBaseLink());
        $this->twig->addGlobal('modulePathUrl', "{$baseUrl}modules/{$moduleName}/");
        $this->twig->addGlobal('moduleViewsUrl', "{$baseUrl}modules/{$moduleName}/views/");
        $this->twig->addGlobal('moduleViewsPath', $moduleViewsPath);

        if (file_exists($moduleAssetsPath)) {
            $loader->addPath($moduleAssetsPath, 'ModuleAssets');
            $this->twig->addGlobal('moduleAssetsPath', $moduleAssetsPath);
            $this->twig->addGlobal('moduleAssetsUrl', "{$baseUrl}modules/{$moduleName}/views/assets/");
            // Aggiungi la funzione asset direttamente
            $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) use ($baseUrl, $moduleName) {
                $path = ltrim($path, '/');
                return "{$baseUrl}modules/{$moduleName}/views/assets/" . $path;
            }));
        } else {
            $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) use ($baseUrl, $moduleName) {
                $path = ltrim($path, '/');
                return "{$baseUrl}modules/{$moduleName}/views/" . $path;
            }));
        }

        if (file_exists($moduleTwigPath)) {
            $this->twig->addGlobal('moduleTwigPath', $moduleTwigPath);
            $this->twig->addGlobal('moduleTwigUrl', "{$baseUrl}modules/{$moduleName}/views/twig/");
            $loader->addPath("{$basePath}{$moduleName}/views/twig/", 'ModuleTwig');
        }

        $this->path = $path;

        return $this;
    }

    public function render($params = [])
    {
        if (!$this->twig) {
            throw new \Exception('Twig environment not initialized');
        }
        if (!$this->path) {
            throw new \Exception('Template path not set');
        }

        // Convert absolute path to relative if needed
        $templatePath = $this->path;
        $moduleTwigPath = _PS_MODULE_DIR_ . $this->module_name . '/views/twig/';

        if (strpos($templatePath, $moduleTwigPath) === 0) {
            // Remove the base path to get relative path
            $templatePath = substr($templatePath, strlen($moduleTwigPath));
        }

        return $this->twig->render($templatePath, $params);
    }
}
