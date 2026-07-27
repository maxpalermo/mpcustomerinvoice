<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_63($module)
{
    return $module->registerHook('actionOrderStatusUpdate')
        && $module->registerHook('actionOrderStatusPostUpdate');
}
