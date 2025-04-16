<?php

namespace MpSoft\MpCustomerInvoice;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MpCustomerInvoiceServiceProvider
{
    public function register(ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(_PS_MODULE_DIR_ . '/mpcustomerinvoice/config')
        );
        $loader->load('services.yml');
    }
}
