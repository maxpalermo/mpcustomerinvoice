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

class MpcustomerinvoiceAddressModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        // Verifica che il cliente sia loggato
        if (!$this->context->customer->isLogged()) {
            Tools::redirect($this->context->link->getPageLink('authentication'));
        }

        // Recupera i parametri
        $id_address = (int) Tools::getValue('id_address');
        $action = $this->getAction();

        // Gestisci l'azione
        switch ($action) {
            case 'add':
                $this->handleAddAddress();
                break;
            case 'edit':
                if ($id_address) {
                    $this->handleEditAddress($id_address);
                } else {
                    $this->handleAddAddress();
                }
                break;
            case 'view':
                if ($id_address) {
                    $this->handleEditAddress($id_address);
                } else {
                    $this->showAddressList();
                }
                break;
            case 'delete':
                if ($id_address) {
                    $this->handleDeleteAddress($id_address);
                } else {
                    $this->showAddressList();
                }
                break;
            default:
                $this->showAddressList();
                break;
        }
    }

    private function getAction()
    {
        if (Tools::getValue('add')) {
            return 'add';
        }
        if (Tools::getValue('edit')) {
            return 'edit';
        }
        if (Tools::getValue('view')) {
            return 'view';
        }
        if (Tools::getValue('delete')) {
            return 'delete';
        }
        return 'list';
    }

    private function handleAddAddress()
    {
        $invoice = $this->getInvoiceData();
        $address = new Address();
        $address->id_country = $this->getCurrentCountryId();
        $formContext = $this->getFormContext();
        $this->context->smarty->assign([
            'address' => $address,
            'countries' => Country::getCountries($this->context->language->id),
            'states' => $this->getActiveStatesByCountry((int) $address->id_country),
            'country_has_states' => $this->countryContainsStates((int) $address->id_country),
            'invoice' => $invoice,
            'can_request_invoice' => !$invoice['has_invoice'],
            'is_invoice_address' => false,
            'action' => 'add',
            'form_action' => $formContext['form_action'],
            'back_url' => $formContext['back_url'],
        ]);
        $this->setTemplate('module:mpcustomerinvoice/views/templates/front/address-form.tpl');
    }

    private function handleEditAddress($id_address)
    {
        $address = new Address($id_address);

        // Verifica che l'indirizzo appartenga al cliente
        if (
            !Validate::isLoadedObject($address) ||
            $address->id_customer != $this->context->customer->id
        ) {
            Tools::redirect($this->context->link->getModuleLink('mpcustomerinvoice', 'address'));
        }

        $states = $this->getActiveStatesByCountry((int) $address->id_country);
        $formContext = $this->getFormContext($id_address);

        $invoice = $this->getInvoiceData();
        $this->context->smarty->assign([
            'address' => $address,
            'countries' => Country::getCountries($this->context->language->id),
            'states' => $states,
            'country_has_states' => $this->countryContainsStates((int) $address->id_country),
            'invoice' => $invoice,
            'can_request_invoice' => false,
            'is_invoice_address' => $invoice['has_invoice'] && (int) $invoice['id_address_invoice'] === (int) $id_address,
            'action' => $invoice['has_invoice'] && (int) $invoice['id_address_invoice'] === (int) $id_address ? 'view' : 'edit',
            'form_action' => $formContext['form_action'],
            'back_url' => $formContext['back_url'],
        ]);
        $this->setTemplate('module:mpcustomerinvoice/views/templates/front/address-form.tpl');
    }

    private function getCurrentCountryId()
    {
        $countryId = (int) ($this->context->country->id ?? 0);

        return $countryId ?: (int) Configuration::get('PS_COUNTRY_DEFAULT');
    }

    private function countryContainsStates($idCountry)
    {
        $country = new Country((int) $idCountry, (int) $this->context->language->id);

        return Validate::isLoadedObject($country) && (bool) $country->contains_states;
    }

    private function getActiveStatesByCountry($idCountry)
    {
        if ($idCountry <= 0) {
            return [];
        }

        $query = new DbQuery();
        $query
            ->select('`id_state`, `id_zone`, `name`, `iso_code`')
            ->from('state')
            ->where('`id_country` = ' . (int) $idCountry)
            ->where('`active` = 1')
            ->orderBy('`name` ASC');

        return Db::getInstance()->executeS($query) ?: [];
    }

    private function getFormContext($idAddress = 0)
    {
        $fromCheckout = (int) Tools::getValue('from_checkout', 0);
        $params = ['save' => 1];
        if ($idAddress > 0) {
            $params['id_address'] = (int) $idAddress;
        }
        if ($fromCheckout) {
            $params['from_checkout'] = 1;
            $useSameAddress = Tools::getValue('use_same_address');
            if ($useSameAddress !== null) {
                $params['use_same_address'] = (int) $useSameAddress;
            }
        }

        return [
            'form_action' => $this->context->link->getModuleLink('mpcustomerinvoice', 'address', $params),
            'back_url' => $fromCheckout
                ? $this->context->link->getPageLink('order')
                : $this->context->link->getModuleLink('mpcustomerinvoice', 'address'),
        ];
    }

    private function handleDeleteAddress($id_address)
    {
        $address = new Address($id_address);

        $invoice = $this->getInvoiceData();
        if (
            Validate::isLoadedObject($address) &&
            $address->id_customer == $this->context->customer->id &&
            (int) $invoice['id_address_invoice'] !== (int) $id_address
        ) {
            $address->delete();
        }

        Tools::redirect($this->context->link->getModuleLink('mpcustomerinvoice', 'address'));
    }

    private function showAddressList()
    {
        $addresses = $this->context->customer->getAddresses($this->context->language->id);

        $invoiceNotice = (bool) $this->context->cookie->mpcustomerinvoice_invoice_address_notice;
        if ($invoiceNotice) {
            $this->context->cookie->__unset('mpcustomerinvoice_invoice_address_notice');
            $this->context->cookie->write();
        }

        $this->context->smarty->assign([
            'addresses' => $addresses,
            'invoice_notice' => $invoiceNotice,
            'invoice_address_id' => $this->getInvoiceData()['id_address_invoice'],
        ]);

        $this->setTemplate('module:mpcustomerinvoice/views/templates/front/address-list.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('ajax') && Tools::getValue('action') === 'validateInvoiceData') {
            $errors = $this->getInvoiceValidationErrors();
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'success' => empty($errors),
                'errors' => $errors,
            ]));
        }

        if (Tools::isSubmit('save')) {
            $this->saveAddress();
        }
    }


    private function saveAddress()
    {
        $id_address = (int) Tools::getValue('id_address');
        $fromCheckout = (int) Tools::getValue('from_checkout', 0);
        $useSameAddress = Tools::getValue('use_same_address');
        $address = new Address($id_address);

        // Verifica che l'indirizzo appartenga al cliente
        if (
            $id_address &&
            (!Validate::isLoadedObject($address) ||
                $address->id_customer != $this->context->customer->id)
        ) {
            $this->errors[] = $this->trans('Indirizzo non valido', [], 'Modules.MpCustomerInvoice.Shop');
            return;
        }

        // Imposta il cliente se è un nuovo indirizzo
        if (!$id_address) {
            $address->id_customer = $this->context->customer->id;
        }

        // Recupera i dati dal form
        $address->alias = Tools::getValue('alias');
        $address->firstname = Tools::getValue('firstname');
        $address->lastname = Tools::getValue('lastname');
        $address->address1 = Tools::getValue('address1');
        $address->address2 = Tools::getValue('address2');
        $address->postcode = Tools::getValue('postcode');
        $address->city = Tools::getValue('city');
        $address->id_country = (int) Tools::getValue('id_country');
        $address->id_state = (int) Tools::getValue('id_state');
        $address->phone = Tools::getValue('phone');
        $address->phone_mobile = Tools::getValue('phone_mobile');

        $wantsInvoice = (int) Tools::getValue('want_invoice') === 1;
        $invoice = $this->getInvoiceData();
        if ($id_address && (int) $invoice['id_address_invoice'] === $id_address) {
            $this->errors[] = $this->trans('L’indirizzo di fatturazione non è modificabile.', [], 'Modules.Mpcustomerinvoice.Shop');
            return;
        }
        if ($wantsInvoice && $invoice['has_invoice']) {
            $this->errors[] = $this->trans('Hai già un indirizzo di fatturazione.', [], 'Modules.Mpcustomerinvoice.Shop');
            return;
        }

        if ($wantsInvoice) {
            $address->company = Tools::getValue('company');
            $address->vat_number = Tools::getValue('vat_number');
            $address->dni = Tools::getValue('dni');
            $invoiceErrors = $this->getInvoiceValidationErrors();
            if (!empty($invoiceErrors)) {
                $this->errors = array_merge($this->errors, $invoiceErrors);
                return;
            }
        }

        // Validazione
        $validationError = $address->validateFields(false, true);
        if ($validationError !== true) {
            $this->errors[] = $validationError;
            return;
        }

        if (!$address->save()) {
            $this->errors[] = $this->trans('Errore nel salvataggio dell\'indirizzo', [], 'Modules.Mpcustomerinvoice.Shop');
            return;
        }

        if ($wantsInvoice && !$this->saveInvoiceData((int) $address->id)) {
            $this->errors[] = $this->trans('Errore nel salvataggio dei dati di fatturazione', [], 'Modules.Mpcustomerinvoice.Shop');
            return;
        }

        if ($wantsInvoice) {
            $this->context->cookie->mpcustomerinvoice_invoice_address_notice = 1;
            $this->context->cookie->write();
        }

        // GESTIONE REDIRECT: Se veniamo dal checkout, aggiorna il carrello e torna al checkout
        if ($fromCheckout) {
            $cart = $this->getCartFromContext();
            if ($cart && Validate::isLoadedObject($cart)) {
                // Se use_same_address=0, imposta solo l'indirizzo di fatturazione
                if ($useSameAddress !== null && $useSameAddress == 0) {
                    $cart->id_address_invoice = (int) $address->id;
                } else {
                    // Imposta entrambi gli indirizzi (consegna e fatturazione)
                    $cart->id_address_delivery = (int) $address->id;
                    $cart->id_address_invoice = (int) $address->id;
                }
                $cart->update();
                $this->context->cart = $cart;
            }

            // Torna al checkout
            Tools::redirect($this->context->link->getPageLink('order'));
        }

        // Redirect standard alla lista degli indirizzi
        Tools::redirect($this->context->link->getModuleLink('mpcustomerinvoice', 'address'));
    }

    /**
     * Ottiene il carrello dal contesto o dal cookie
     */
    private function getCartFromContext()
    {
        $cart = $this->context->cart;
        if ($cart && Validate::isLoadedObject($cart)) {
            return $cart;
        }

        $cartId = (int) $this->context->cookie->id_cart;
        if ($cartId > 0) {
            $cart = new Cart($cartId);
            if (Validate::isLoadedObject($cart)) {
                $this->context->cart = $cart;
                return $cart;
            }
        }

        return null;
    }

    private function _saveAddress()
    {
        $id_address = (int) Tools::getValue('id_address');
        $address = new Address($id_address);

        // Verifica che l'indirizzo appartenga al cliente
        if (
            $id_address &&
            (!Validate::isLoadedObject($address) ||
                $address->id_customer != $this->context->customer->id)
        ) {
            $this->errors[] = $this->trans('Indirizzo non valido', [], 'Modules.MpCustomerInvoice.Shop');
            return;
        }

        // Imposta il cliente se è un nuovo indirizzo
        if (!$id_address) {
            $address->id_customer = $this->context->customer->id;
        }

        // Recupera i dati dal form
        $address->alias = Tools::getValue('alias');
        $address->firstname = Tools::getValue('firstname');
        $address->lastname = Tools::getValue('lastname');
        $address->address1 = Tools::getValue('address1');
        $address->address2 = Tools::getValue('address2');
        $address->postcode = Tools::getValue('postcode');
        $address->city = Tools::getValue('city');
        $address->id_country = (int) Tools::getValue('id_country');
        $address->id_state = (int) Tools::getValue('id_state');
        $address->phone = Tools::getValue('phone');
        $address->phone_mobile = Tools::getValue('phone_mobile');

        $wantsInvoice = (int) Tools::getValue('want_invoice') === 1;
        $invoice = $this->getInvoiceData();
        if ($id_address && (int) $invoice['id_address_invoice'] === $id_address) {
            $this->errors[] = $this->trans('L’indirizzo di fatturazione non è modificabile.', [], 'Modules.Mpcustomerinvoice.Shop');

            return;
        }
        if ($wantsInvoice && $invoice['has_invoice']) {
            $this->errors[] = $this->trans('Hai già un indirizzo di fatturazione.', [], 'Modules.Mpcustomerinvoice.Shop');

            return;
        }

        if ($wantsInvoice) {
            $address->company = Tools::getValue('company');
            $address->vat_number = Tools::getValue('vat_number');
            $address->dni = Tools::getValue('dni');
            $invoiceErrors = $this->getInvoiceValidationErrors();
            if (!empty($invoiceErrors)) {
                $this->errors = array_merge($this->errors, $invoiceErrors);

                return;
            }
        }

        // Validazione
        $validationError = $address->validateFields(false, true);
        if ($validationError !== true) {
            $this->errors[] = $validationError;

            return;
        }

        if (!$address->save()) {
            $this->errors[] = $this->trans('Errore nel salvataggio dell\'indirizzo', [], 'Modules.Mpcustomerinvoice.Shop');

            return;
        }

        if ($wantsInvoice && !$this->saveInvoiceData((int) $address->id)) {
            $this->errors[] = $this->trans('Errore nel salvataggio dei dati di fatturazione', [], 'Modules.Mpcustomerinvoice.Shop');

            return;
        }

        if ($wantsInvoice) {
            $this->context->cookie->mpcustomerinvoice_invoice_address_notice = 1;
            $this->context->cookie->write();
        }

        Tools::redirect($this->context->link->getModuleLink('mpcustomerinvoice', 'address'));
    }

    private function getInvoiceData(): array
    {
        $data = Db::getInstance()->getRow(
            'SELECT `id_address_invoice`, `type`, `company`, `vat_number`, `dni`, `cuu`, `sdi`, `pec`, `cig`, `cup`'
            . ' FROM `' . _DB_PREFIX_ . 'customer_invoice`'
            . ' WHERE `id_customer` = ' . (int) $this->context->customer->id
        );

        $invoice = array_merge([
            'id_address_invoice' => 0,
            'type' => '',
            'company' => '',
            'vat_number' => '',
            'dni' => '',
            'cuu' => '',
            'sdi' => '',
            'pec' => '',
            'cig' => '',
            'cup' => '',
        ], is_array($data) ? $data : []);
        $invoice['has_invoice'] = (int) $invoice['id_address_invoice'] > 0;

        return $invoice;
    }

    private function getInvoiceValidationErrors(): array
    {
        $type = (string) Tools::getValue('invoice_type');
        $company = trim((string) Tools::getValue('company'));
        $vatNumber = trim((string) Tools::getValue('vat_number'));
        $dni = trim((string) Tools::getValue('dni'));
        $cuu = trim((string) Tools::getValue('cuu'));
        $sdi = trim((string) Tools::getValue('sdi'));
        $pec = trim((string) Tools::getValue('pec'));
        $cig = trim((string) Tools::getValue('cig'));
        $cup = trim((string) Tools::getValue('cup'));
        $errors = [];

        if (!in_array($type, ['PRIVATO', 'PARTITA_IVA', 'ENTE'], true)) {
            $errors[] = $this->trans('Seleziona il tipo di intestatario.', [], 'Modules.Mpcustomerinvoice.Shop');

            return $errors;
        }

        if ($company === '') {
            $errors[] = $this->trans('L’intestazione fattura è obbligatoria.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if (Tools::strlen($company) > 255) {
            $errors[] = $this->trans('L’intestazione fattura supera la lunghezza consentita.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if ($type === 'PRIVATO' && $dni === '') {
            $errors[] = $this->trans('Il codice fiscale è obbligatorio.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if ($type === 'PARTITA_IVA' && $vatNumber === '') {
            $errors[] = $this->trans('La partita IVA è obbligatoria.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if ($type === 'PARTITA_IVA' && $sdi === '' && $pec === '') {
            $errors[] = $this->trans('Inserisci il codice SDI o la PEC.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if ($type === 'ENTE' && ($dni === '' || $cuu === '' || $cig === '' || $cup === '')) {
            $errors[] = $this->trans('Per un ente sono obbligatori codice fiscale, CUU, CIG e CUP.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if ($pec !== '' && !Validate::isEmail($pec)) {
            $errors[] = $this->trans('Inserisci un indirizzo PEC valido.', [], 'Modules.Mpcustomerinvoice.Shop');
        }
        if (Tools::strlen($vatNumber) > 16 || Tools::strlen($dni) > 16 || Tools::strlen($cuu) > 6 || Tools::strlen($sdi) > 7 || Tools::strlen($cig) > 10 || Tools::strlen($cup) > 15) {
            $errors[] = $this->trans('Uno o più campi di fatturazione superano la lunghezza consentita.', [], 'Modules.Mpcustomerinvoice.Shop');
        }

        return $errors;
    }

    private function saveInvoiceData(int $idAddress): bool
    {
        $wantsInvoice = (int) Tools::getValue('want_invoice', 0) === 1 ? 1 : 0;
        $data = [
            'type' => pSQL((string) Tools::getValue('invoice_type')),
            'company' => pSQL((string) Tools::getValue('company')),
            'vat_number' => pSQL((string) Tools::getValue('vat_number')),
            'dni' => pSQL((string) Tools::getValue('dni')),
            'cuu' => pSQL((string) Tools::getValue('cuu')),
            'sdi' => pSQL((string) Tools::getValue('sdi')),
            'pec' => pSQL((string) Tools::getValue('pec')),
            'cig' => pSQL((string) Tools::getValue('cig')),
            'cup' => pSQL((string) Tools::getValue('cup')),
            'id_address_invoice' => $idAddress,
            'invoice_requested' => $wantsInvoice,
            'date_upd' => date('Y-m-d H:i:s'),
        ];
        $db = Db::getInstance();
        $where = 'id_customer = ' . (int) $this->context->customer->id;

        if ($db->getValue('SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer_invoice` WHERE ' . $where)) {
            return $db->update('customer_invoice', $data, $where);
        }

        return $db->insert('customer_invoice', array_merge($data, [
            'id_customer' => (int) $this->context->customer->id,
            'date_add' => date('Y-m-d H:i:s'),
        ]));
    }
}