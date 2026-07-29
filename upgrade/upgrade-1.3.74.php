<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_74($module)
{
    $pfx = _DB_PREFIX_;
    $columns = Db::getInstance()->executeS("SHOW COLUMNS FROM {$pfx}customer_invoice LIKE 'invoice_requested'");
    if (empty($columns)) {
        Db::getInstance()->execute("ALTER TABLE {$pfx}customer_invoice ADD COLUMN `invoice_requested` tinyint(1) UNSIGNED DEFAULT 0 AFTER `is_foreign`");
    }

    return $module->registerHook('actionOrderStatusUpdate')
        && $module->registerHook('actionOrderStatusPostUpdate')
        && $module->registerHook('actionGetAdminToolbarButtons');
}
