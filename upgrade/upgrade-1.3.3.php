<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_3($module)
{
    return $module->unregisterHook([
        'actionCustomerGridDefinitionModifier',
        'actionCustomerGridQueryBuilderModifier',
        'actionCustomerGridDataModifier',
        'actionAddressGridDefinitionModifier',
        'actionAddressGridQueryBuilderModifier',
        'actionAddressGridDataModifier',
        'actionOrderGridDefinitionModifier',
        'actionOrderGridQueryBuilderModifier',
        'actionOrderGridDataModifier',
    ]);
}
