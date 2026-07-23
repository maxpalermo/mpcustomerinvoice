<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_16($module)
{
    return $module->registerHook('actionGenerateDocumentReference');
}
