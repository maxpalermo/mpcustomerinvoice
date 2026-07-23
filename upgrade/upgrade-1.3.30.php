<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_30($module)
{
    return $module->registerHook('actionObjectCartUpdateBefore');
}
