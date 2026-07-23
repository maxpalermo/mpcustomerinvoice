<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_35($module)
{
    return $module->registerHook('additionalCustomerFormFields')
        && $module->registerHook('actionCustomerAccountAdd');
}
