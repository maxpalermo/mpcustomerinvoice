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

namespace MpSoft\MpCustomerInvoice\Helpers\install;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

/**
 * Summary of InstallMenu
 * Install a new menu in the back office
 * 
 * Parent tab menu list:
    AdminDashboard
    SELL
    AdminParentOrders
        AdminOrders
        AdminInvoices
        AdminSlip
        AdminDeliverySlip
        AdminCarts
    AdminCatalog
        AdminProducts
        AdminCategories
        AdminTracking
        AdminParentAttributesGroups
        AdminParentManufacturers
        AdminAttachments
        AdminParentCartRules
    AdminParentCustomer
        AdminCustomers
        AdminAddresses
        AdminOutstanding
    AdminParentCustomerThreads
        AdminCustomerThreads
        AdminOrderMessage
        AdminReturn
    AdminStats
    AdminStock
        AdminWarehouses
        AdminParentStockManagement
        AdminSupplyOrders
        AdminStockConfiguration
    IMPROVE
    AdminParentModulesSf
        AdminModulesSf
        AdminModules
        AdminAddonsCatalog
    AdminParentThemes
        AdminThemes
        AdminThemesCatalog
        AdminCmsContent
        AdminModulesPositions
        AdminImages
    AdminParentShipping
        AdminCarriers
        AdminShipping
    AdminParentPayment
        AdminPayment
        AdminPaymentPreferences
    AdminInternational
        AdminParentLocalization
        AdminParentCountries
        AdminParentTaxes
        AdminTranslations
    CONFIGURE
    ShopParameters
        AdminParentPreferences
        AdminParentOrderPreferences
        AdminPPreferences
        AdminParentCustomerPreferences
        AdminParentStores
        AdminParentMeta
        AdminParentSearchConf
    AdminAdvancedParameters
        AdminInformation
        AdminPerformance
        AdminAdminPreferences
        AdminEmails
        AdminImport
        AdminParentEmployees
        AdminParentRequestSql
        AdminLogs
        AdminWebservice
        AdminShopGroup
        AdminShopUrl
    DEFAULT
 */
class InstallMenu
{
    protected $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Summary of installMenu
     * Install a new menu in the back office
     *
     * @param string $class_name Controller class name
     * @param string $route_name Route name
     * @param string|array $name Tab name, if multilang, pass an array with id_lang as key
     * @param string $parent_class_name Parent menu if you want to add this menu as a child
     * @param string $icon Material Icon name
     * @param string $wording Wording translation key
     * @param string $wording_domain Wording domain
     * @param bool $active If true, Tab menu will be shown
     * @param bool $enabled If true Tab menu is enabled
     *
     * @return bool
     */
    public function installMenu(
        string $class_name,
        string|array $name,
        string $parent_class_name = 'DEFAULT',
        string $icon = '',
        string $route_name = '',
        string $wording = '',
        string $wording_domain = '',
        int $position = null,
        bool $active = true,
        bool $enabled = true
    ) {
        if (!$name) {
            return false;
        }

        if (!$class_name) {
            return false;
        }

        if (!is_array($name)) {
            $multilang_name = [];
            foreach (\Language::getLanguages() as $lang) {
                $multilang_name[$lang['id_lang']] = $name;
            }
        } else {
            foreach (\Language::getLanguages() as $lang) {
                if (!isset($name[$lang['id_lang']])) {
                    $multilang_name[$lang['id_lang']] = '--';
                } else {
                    $multilang_name[$lang['id_lang']] = $name[$lang['id_lang']];
                }
            }
        }

        $id_tab_parent = (int) SymfonyContainer::getInstance()
            ->get('prestashop.core.admin.tab.repository')
            ->findOneIdByClassName($parent_class_name);

        if ($position === null) {
            $position = (int) \Tab::getNewLastPosition($id_tab_parent);
        }

        $tab = new \Tab();
        $tab->id_parent = $id_tab_parent;
        $tab->position = (int) $position;
        $tab->module = $this->module->name;
        $tab->class_name = $class_name;
        $tab->route_name = $route_name;
        $tab->active = $active;
        $tab->enabled = $enabled;
        $tab->icon = $icon;
        $tab->wording = $wording;
        $tab->wording_domain = $wording_domain;

        // Multilang fields
        $tab->name = [];
        foreach (\Language::getLanguages() as $lang) {
            $id_lang = (int) $lang['id_lang'];
            $tab->name[$id_lang] = $multilang_name[$id_lang];
        }

        return $tab->add();
    }

    /**
     * Summary of uninstallMenu
     * Uninstall a menu from the back office
     *
     * @param string $class_name Controller class name
     *
     * @return bool
     */
    public function uninstallMenu($class_name)
    {
        $id_tab = (int) SymfonyContainer::getInstance()
            ->get('prestashop.core.admin.tab.repository')
            ->findOneIdByClassName($class_name);
        if ($id_tab) {
            $tab = new \Tab((int) $id_tab);

            return $tab->delete();
        }

        return true;
    }
}
