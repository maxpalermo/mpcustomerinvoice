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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/mpcustomerinvoice/vendor/autoload.php';

use MpSoft\MpCustomerInvoice\Helpers\GetTwigEnvironment;
use MpSoft\MpCustomerInvoice\Helpers\HookManager;
use MpSoft\MpCustomerInvoice\Helpers\InstallMenu;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobArea;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobLink;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobPosition;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class MpCustomerInvoice extends Module implements WidgetInterface
{
    protected $id_lang;
    protected $serviceProvider;
    protected $hookManager;

    public function __construct()
    {
        $this->name = 'mpcustomerinvoice';
        $this->tab = 'administration';
        $this->version = '1.3.61';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99',
        ];

        parent::__construct();

        $this->displayName = $this->trans('MP Gestione Fattura Elettronica', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->description = $this->trans('Gestisce i codici della fatturazione elettronica.', [], 'Modules.Mpcustomerinvoice.Admin');
        $this->id_lang = (int) $this->context->language->id;
        $this->hookManager = new HookManager($this);
    }

    public function install()
    {
        $installMenu = new InstallMenu($this);

        return parent::install() &&
            $this->registerHook(
                [
                    'actionGenerateDocumentReference',
                    'actionDispatcherBefore',
                    'actionDispatcherAfter',
                    'actionObjectCartUpdateBefore',
                    'actionFrontControllerSetMedia',
                    'actionAdminControllerSetMedia',
                    'displayBeforeBodyClosingTag',
                    'displayAdminOrderMain',
                    'displayCustomerAccount',
                    //'actionAdminCustomersControllerFormModifier',
                    //'actionAdminCustomersControllerSaveAfter',
                    //'actionAdminCustomersFormSubmit',
                    'additionalCustomerFormFields',
                    'actionCustomerAccountAdd',
                    //'actionCustomerAccountUpdate',
                    //'actionBeforeSubmitAccount',
                    //'actionObjectCustomerDeleteAfter',
                    //'actionCustomerFormDataProviderData',
                    //'actionObjectAddressAddAfter',
                    //'actionAfterCreateCustomerFormHandler',
                    //'actionAfterUpdateCustomerFormHandler',
                    //'actionCustomerFormBuilderModifier',
                    //'additionalCustomerAddressFields',
                    //'validateCustomerFormFields',
                    //'displayAdminCustomersForm',
                ]
            ) &&
            $installMenu->installMenu(
                'AdminMpCustomerInvoice',
                'MP Fattura Elettronica',
                'SELL',
                'receipt'
            ) &&
            ModelCustomerInvoice::install() &&
            ModelCustomerInvoiceJobArea::install() &&
            ModelCustomerInvoiceJobPosition::install() &&
            ModelCustomerInvoiceJobLink::install();
    }

    public function uninstall()
    {
        $installMenu = new InstallMenu($this);

        return parent::uninstall() &&
            $installMenu->uninstallMenu('AdminMpCustomerInvoice');
    }

    public function renderTwig($path, $params = [])
    {
        $twig = new GetTwigEnvironment($this->name);
        $twig->load($path);

        return $twig->render($params);
    }

    public function renderWidget($hookName, array $configuration)
    {
        switch ($hookName) {
            case 'displayAdminCustomers':
                return $this->hookManager->hookDisplayAdminCustomers($configuration);
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            case 'displayAdminEndContent':
                return $this->hookManager->hookDisplayAdminEndContent($configuration);
            case 'displayBeforeBodyClosingTag':
                return $this->hookManager->hookDisplayBeforeBodyClosingTag($configuration);
            default:
                return '';
        }

        return '';
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $vars = [];
        switch ($hookName) {
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            default:
                return [];
        }

        return $vars;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMpCustomerInvoice', true, [], ['action' => 'showSetupPage']));

        return '';
    }


    /***********************************************
     *  HOOKS
     ***********************************************/
    public function hookAdditionalCustomerFormFields($params)
    {
        return $this->hookManager->hookAdditionalCustomerFormFields($params);
    }

    public function hookActionCustomerAccountAdd($params)
    {
        return $this->hookManager->hookActionCustomerAccountAdd($params);
    }

    public function hookActionGenerateDocumentReference(array $params)
    {
        if (($params['type'] ?? '') !== 'order') {
            return '';
        }

        $pattern = (string) Configuration::get('REFERENCE_RENUMBER');
        if ($pattern === '') {
            return '';
        }

        $tableStatus = Db::getInstance()->executeS(
            "SHOW TABLE STATUS LIKE '" . pSQL(_DB_PREFIX_ . "orders") . "'"
        );
        $idOrder = (int) ($tableStatus[0]['Auto_increment'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }

        return $this->formatOrderReference($pattern, $idOrder);
    }


    public function hookActionDispatcherAfter($params)
    {
        $controller = Tools::getValue('controller');
        if ($controller !== 'order') {
            return;
        }
    }

    public function hookActionDispatcherBefore($params)
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#orders/(\d+)/generate-(invoice|delivery-slip|order)-pdf#', $requestUri, $matches)) {
            $idOrder = (int) $matches[1];
            $documentType = $matches[2];

            $url = $this->context->link->getAdminLink(
                'AdminMpCustomerInvoice',
                true,
                [],
                [
                    'action' => 'showCustomPdfPage',
                    'id_order' => $idOrder,
                    'document_type' => $documentType,
                ]
            );
            Tools::redirectAdmin($url);
        }

        // Verifica che siamo nel front-office
        if (!isset($params['controller_type']) || $params['controller_type'] != 1) {
            return;
        }

        $controller = Tools::getValue('controller');
        $useSameAddress = Tools::getValue('use_same_address');
        $newAddress = Tools::getValue('newAddress');

        if (
            $this->context->customer->isLogged()
            && Validate::isLoadedObject($this->context->cart)
            && $this->enforceCartInvoiceAddress($this->context->cart)
        ) {
            $this->context->cart->update();
        }

        // Intercetta il controller "address" originale (sezione account)
        if ($controller == 'address' || $controller == 'addresses') {
            if (Tools::getValue('module') == 'mpcustomerinvoice') {
                return;
            }
            $this->redirectToCustomAddressPage('account');
            return;
        }

        // Intercetta il checkout
        if ($controller == 'order' || $controller == 'ordine') {
            if (!$this->context->customer->isLogged() && !Tools::isSubmit('ajax') && !Tools::getValue('ajax')) {
                $this->redirectCheckoutToRegistration();
                return;
            }

            if ($newAddress === 'invoice') {
                $this->saveCheckoutContext();
                $this->redirectToCustomAddressPage('checkout', ['add' => 1]);
                return;
            }

            // Se esiste almeno un indirizzo non fa il redirect
            $customer = $this->context->customer;
            $addresses = $customer->getAddresses($this->context->language->id);
            if (count($addresses) > 0) {
                return;
            }

            // NUOVO CONTROLLO: Intercetta use_same_address=0
            if ($useSameAddress !== null && $useSameAddress == 0) {
                if (Tools::getValue('module') == 'mpcustomerinvoice') {
                    return;
                }

                // Salva il contesto del checkout
                $this->saveCheckoutContext();

                // Reindirizza alla pagina personalizzata con il parametro
                $this->redirectToCustomAddressPage('checkout', ['use_same_address' => 0, 'add' => 1]);
                return;
            }

            // Verifica se siamo nella sezione indirizzi
            if ($this->isCheckoutAddressStep()) {
                if (Tools::getValue('module') == 'mpcustomerinvoice') {
                    return;
                }

                // Salva il carrello corrente per il redirect
                $this->saveCheckoutContext();

                // Reindirizza alla pagina personalizzata
                $this->redirectToCustomAddressPage('checkout');
                return;
            }
        }

        // Gestione indirizzi dal cookie
        $this->handleAddressFromCookie();

        // Controllo login
        //$this->redirectToRegistrationIfNotLogged();
    }

    /**
     * Verifica se siamo nel passo degli indirizzi del checkout
     */
    private function redirectCheckoutToRegistration()
    {
        $back = $this->context->link->getPageLink('order', true);
        Tools::redirect($this->context->link->getPageLink('registration', true, null, ['back' => $back]));
    }

    private function isCheckoutAddressStep()
    {
        // 1. Ottieni il carrello manualmente se è null
        $cart = $this->getCartFromContext();

        if (!$cart || !Validate::isLoadedObject($cart)) {
            // Se non c'è carrello, potrebbe essere la prima volta
            // Verifica se siamo nel checkout
            $controller = Tools::getValue('controller');
            if ($controller == 'order') {
                // Se siamo nel checkout e non c'è carrello, probabilmente
                // siamo nella sezione indirizzi (primo passo)
                return true;
            }
            return false;
        }

        // 2. Se il carrello non ha indirizzo, siamo nella sezione indirizzi
        if ($cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            return true;
        }

        // 3. Verifica tramite l'URL
        $requestUri = $_SERVER['REQUEST_URI'];

        // Cerca pattern specifici del checkout indirizzi
        $patterns = [
            'controller=order',
            'address=1',
            'delivery=0',
            'step=address'
        ];

        foreach ($patterns as $pattern) {
            if (strpos($requestUri, $pattern) !== false) {
                return true;
            }
        }

        // 4. Verifica tramite il controller
        if ($this->context->controller instanceof \OrderController) {
            $orderController = $this->context->controller;
            // Verifica il carrello dal controller
            $cartCheck = $orderController->getCart();
            if ($cartCheck && Validate::isLoadedObject($cartCheck)) {
                if ($cartCheck->id_address_delivery == 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Ottiene il carrello dal contesto o dal cookie
     */
    private function getCartFromContext()
    {
        // Prova a ottenere il carrello dal contesto
        $cart = $this->context->cart;

        if ($cart && Validate::isLoadedObject($cart)) {
            return $cart;
        }

        // Se il carrello è null, prova a caricarlo dal cookie
        $cartId = (int) $this->context->cookie->id_cart;
        if ($cartId > 0) {
            $cart = new Cart($cartId);
            if (Validate::isLoadedObject($cart)) {
                // Aggiorna il contesto
                $this->context->cart = $cart;
                return $cart;
            }
        }

        // Se ancora null, prova a creare un nuovo carrello
        if ($this->context->customer->isLogged()) {
            $cart = new Cart();
            $cart->id_customer = $this->context->customer->id;
            $cart->id_currency = $this->context->currency->id;
            $cart->id_lang = $this->context->language->id;
            $cart->id_shop = $this->context->shop->id;
            $cart->add();

            $this->context->cart = $cart;
            $this->context->cookie->id_cart = $cart->id;
            $this->context->cookie->write();

            return $cart;
        }

        return null;
    }

    /**
     * Salva il contesto del checkout per il redirect
     */
    private function saveCheckoutContext()
    {
        $cart = $this->getCartFromContext();
        if ($cart && Validate::isLoadedObject($cart)) {
            $this->context->cookie->__set('mpcustomerinvoice_checkout_cart', $cart->id);
            $this->context->cookie->__set('mpcustomerinvoice_checkout_mode', true);
            $this->context->cookie->write();
        }
    }

    /**
     * Gestisce gli indirizzi dal cookie
     */
    private function handleAddressFromCookie()
    {
        $deliveryAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_delivery');
        $invoiceAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_invoice');

        if (!$deliveryAddressId && !$invoiceAddressId) {
            return;
        }

        $cart = $this->getCartFromContext();
        if (!$cart || !Validate::isLoadedObject($cart)) {
            return;
        }

        $customerInvoiceAddressId = $this->getCustomerInvoiceAddressId((int) $cart->id_customer);
        if ($customerInvoiceAddressId > 0) {
            $invoiceAddressId = $customerInvoiceAddressId;
        }

        $updated = false;

        if ($deliveryAddressId > 0 && $cart->id_address_delivery != $deliveryAddressId) {
            $cart->id_address_delivery = $deliveryAddressId;
            $updated = true;
        }

        if ($invoiceAddressId > 0 && $cart->id_address_invoice != $invoiceAddressId) {
            $cart->id_address_invoice = $invoiceAddressId;
            $updated = true;
        }

        if ($updated) {
            $cart->update();
            $this->context->cart = $cart;

            $this->context->cookie->__unset('mpcustomerinvoice_id_address_delivery');
            $this->context->cookie->__unset('mpcustomerinvoice_id_address_invoice');
            $this->context->cookie->write();
        }
    }

    public function hookActionObjectCartUpdateBefore(array $params)
    {
        $cart = $params['object'] ?? null;
        if ($cart instanceof Cart) {
            $this->enforceCartInvoiceAddress($cart);
        }
    }

    private function enforceCartInvoiceAddress($cart)
    {
        if (!$cart instanceof Cart || !(int) $cart->id_customer) {
            return false;
        }

        $invoiceAddressId = $this->getCustomerInvoiceAddressId((int) $cart->id_customer);
        if ($invoiceAddressId > 0 && (int) $cart->id_address_invoice !== $invoiceAddressId) {
            $cart->id_address_invoice = $invoiceAddressId;

            return true;
        }

        return false;
    }

    private function getCustomerInvoiceAddressId($idCustomer)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT ci.`id_address_invoice`'
            . ' FROM `' . _DB_PREFIX_ . 'customer_invoice` ci'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'address` a ON a.`id_address` = ci.`id_address_invoice`'
            . ' WHERE ci.`id_customer` = ' . (int) $idCustomer
            . ' AND a.`id_customer` = ci.`id_customer`'
            . ' AND a.`deleted` = 0'
        );
    }

    /**
     * Reindirizza alla pagina personalizzata degli indirizzi
     */
    private function redirectToCustomAddressPage($source = 'account', $extraParams = [])
    {
        $params = [];
        $params['source'] = $source;

        if ($source == 'checkout') {
            $params['from_checkout'] = 1;
            $params['back_to_checkout'] = 1;
        }

        // Aggiungi parametri extra (es. use_same_address)
        if (!empty($extraParams)) {
            $params = array_merge($params, $extraParams);
        }

        // Mantieni i parametri originali
        $id_address = (int) Tools::getValue('id_address');
        if ($id_address) {
            $params['id_address'] = $id_address;
        }

        if (Tools::getValue('delete')) {
            $params['delete'] = 1;
        }
        if (Tools::getValue('add')) {
            $params['add'] = 1;
        }
        if (Tools::getValue('edit')) {
            $params['edit'] = 1;
        }

        $redirectUrl = $this->context->link->getModuleLink(
            'mpcustomerinvoice',
            'address',
            $params,
            true
        );

        Tools::redirect($redirectUrl);
    }

    /**
     * Controllo login con redirect
     */
    private function redirectToRegistrationIfNotLogged()
    {
        $controller = Tools::getValue('controller');

        $authControllers = ['authentication', 'registration', 'login', 'password'];
        if (in_array($controller, $authControllers)) {
            return;
        }

        if (Tools::isSubmit('ajax') || Tools::getValue('ajax')) {
            return;
        }

        if (!$this->context->customer->isLogged()) {
            $back = urlencode($this->context->link->getPageLink($controller));
            Tools::redirect($this->context->link->getPageLink('registration', true, null, ['back' => $back]));
        }
    }

    /**
     * Aggiunge un link personalizzato nella pagina dell'account cliente
     */
    public function hookDisplayCustomerAccount($params)
    {
        // Puoi aggiungere un link personalizzato o modificare il link esistente
        return $this->display(__FILE__, 'customer-account-link.tpl');
    }

    /**
     * Gestisce il recupero e l'impostazione degli indirizzi dal cookie
     */
    private function _handleAddressFromCookie()
    {
        $deliveryAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_delivery');
        $invoiceAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_invoice');

        // Se non ci sono indirizzi nel cookie, esci
        if (!$deliveryAddressId && !$invoiceAddressId) {
            return;
        }

        // Ottieni il carrello
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        // Aggiorna gli indirizzi del carrello
        $updated = false;

        if ($deliveryAddressId > 0) {
            $cart->id_address_delivery = $deliveryAddressId;
            $updated = true;
        }

        if ($invoiceAddressId > 0) {
            $cart->id_address_invoice = $invoiceAddressId;
            $updated = true;
        }

        if ($updated) {
            $cart->update();
            $this->context->cart = $cart;

            // Pulisci il cookie DOPO aver aggiornato il carrello
            $this->context->cookie->__unset('mpcustomerinvoice_id_address_delivery');
            $this->context->cookie->__unset('mpcustomerinvoice_id_address_invoice');
            $this->context->cookie->write();

            // Log per debug
            PrestaShopLogger::addLog(
                sprintf(
                    'Indirizzi aggiornati dal cookie: delivery=%d, invoice=%d',
                    $deliveryAddressId,
                    $invoiceAddressId
                ),
                1,
                null,
                'Cart',
                $cart->id
            );
        }
    }

    /**
     * Recupera il carrello corrente o dal cookie
     */
    private function getCart()
    {
        $cart = $this->context->cart;

        if ($cart && Validate::isLoadedObject($cart)) {
            return $cart;
        }

        // Prova a recuperare il carrello dal cookie
        $cookieCartId = (int) ($this->context->cookie->id_cart ?? 0);
        if ($cookieCartId > 0) {
            $cart = new Cart($cookieCartId);
            if (Validate::isLoadedObject($cart)) {
                return $cart;
            }
        }

        return null;
    }

    /**
     * Reindirizza alla registrazione se il cliente non è loggato
     */
    private function _redirectToRegistrationIfNotLogged()
    {
        $controller = Tools::getValue('controller');

        // Non reindirizzare su pagine di autenticazione
        $authControllers = ['authentication', 'registration', 'login', 'password'];
        if (in_array($controller, $authControllers)) {
            return;
        }

        // Non reindirizzare su richieste AJAX
        if (Tools::isSubmit('ajax') || Tools::getValue('ajax')) {
            return;
        }

        if (!$this->context->customer->isLogged()) {
            $back = urlencode($this->context->link->getPageLink($controller));
            Tools::redirect($this->context->link->getPageLink('registration', true, null, ['back' => $back]));
        }
    }

    /**
     * Verifica se un indirizzo appartiene al cliente corrente
     */
    private function validateAddressOwnership($addressId)
    {
        if ($addressId <= 0) {
            return false;
        }

        $address = new Address($addressId);
        if (!Validate::isLoadedObject($address)) {
            return false;
        }

        return $address->id_customer == $this->context->customer->id;
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));

        if (
            $controller == 'registration'
            || $controller == 'order'
            || Tools::getValue('module') == $this->name
        ) {
            // 1. Carica jQuery (necessario per Chosen)
            $this->context->controller->addJquery();

            // 2. Carica i file di Chosen dal core (i percorsi rimangono questi anche in PS 8)
            $this->context->controller->registerJavascript(
                'remote-chosen-js',
                'js/jquery/plugins/jquery.chosen.js',
                ['position' => 'bottom', 'priority' => 100]
            );

            $this->context->controller->registerStylesheet(
                'remote-chosen-css',
                'js/jquery/plugins/chosen/jquery.chosen.css',
                ['media' => 'all', 'priority' => 100]
            );

            // 3. Carica il TUO script di inizializzazione
            $this->context->controller->registerJavascript(
                'module-my-chosen-init',
                'modules/' . $this->name . '/views/js/init_chosen.js',
                ['position' => 'bottom', 'priority' => 150]
            );

            /*
             * $this->context->controller->registerJavascript(
             *     'mpcustomerinvoice-admin',
             *     'modules/mpcustomerinvoice/views/assets/js/registration/registrationPage.js',
             *     [
             *         'priority' => 100,
             *     ]
             * );
             */
        }
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));
        $baseJs = $this->getLocalPath() . 'views/assets/js/';
        $baseCss = $this->getLocalPath() . 'views/assets/css/';

        $this->context->controller->addCSS("{$baseCss}/theme-override.css", 'all', 100);

        if ($controller == 'admincustomers' && Tools::getValue('id_customer')) {
            $this->context->controller->addJS("{$baseJs}admin/jobLinkManager.js");
        }
    }

    private function formatOrderReference(string $pattern, int $idOrder): string
    {
        $reference = str_replace('{$year}', date('Y'), $pattern);
        $reference = preg_replace_callback(
            '/\\[\\{\\$id_order\\}\\|(\\d+)\\]/',
            static function (array $matches) use ($idOrder): string {
                return str_pad((string) $idOrder, (int) $matches[1], '0', STR_PAD_LEFT);
            },
            $reference
        );

        return str_replace('{$id_order}', (string) $idOrder, $reference);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        $id_order = (int) ($params['id_order'] ?? 0);
        if ($id_order) {
            $order = new Order($id_order);
            if (!Validate::isLoadedObject($order)) {
                return;
            }
            $id_customer = (int) $order->id_customer;

            $customerInvoice = new ModelCustomerInvoice($id_customer);
            if (!Validate::isLoadedObject($customerInvoice)) {
                return;
            }
            $path = $this->getLocalPath() . 'views/twig/admin/id_eurosolution.html.twig';
            $data = [
                'admin_endpoint' => $this->context->link->getAdminLink('AdminMPCustomerInvoice'),
                'id_eurosolution' => $customerInvoice->id_eurosolution,
                'id_customer' => $id_customer,
            ];

            return $this->renderTwig($path, $data);
        }

        return;
    }
}
