<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_25($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'customer_invoice';
    $column = $db->executeS('SHOW COLUMNS FROM `' . bqSQL($table) . '` LIKE "company"');

    if (!empty($column)) {
        return true;
    }

    return $db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD `company` VARCHAR(255) DEFAULT NULL AFTER `type`');
}
