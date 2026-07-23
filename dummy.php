<?php
class dummy
{
    /**
     * Hook per aggiungere i campi personali al form di registrazione
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        return $this->hookManager->hookAdditionalCustomerFormFields($params);
    }





    /**
     * Hook per impostare CSS e JS nel frontend
     */


    public function hookAdditionalCustomerAddressFields($params)
    {
        return $this->hookAdditionalCustomerFormFields($params);
    }

    /**
     * Hook per modificare il form di registrazione
     */
    public function hookActionAdminCustomersControllerFormModifier(array &$params)
    {
        return $this->hookManager->hookActionAdminCustomersControllerFormModifier($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionCustomerAccountAdd($params)
    {
        return $this->hookManager->hookActionCustomerAccountAdd($params);
    }

    /**
     * Hook che scatta quando si aggiorna il cliente dal form di registrazione
     */
    public function hookActionCustomerAccountUpdate($params)
    {
        return $this->hookManager->hookActionCustomerAccountUpdate($params);
    }

    /**
     * Hook che inserisce i campi custom nel form di registrazione
     */
    public function hookActionCustomerFormBuilderModifier(array &$params)
    {
        return $this->hookManager->hookActionCustomerFormBuilderModifier($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionAfterCreateCustomerFormHandler($params)
    {
        return $this->hookManager->hookActionAfterCreateCustomerFormHandler($params);
    }

    /**
     * Hook che scatta quando si aggiorna il cliente dal form di registrazione
     */
    public function hookActionAfterUpdateCustomerFormHandler($params)
    {
        return $this->hookManager->hookActionAfterUpdateCustomerFormHandler($params);
    }

    /**
     * Hook che scatta quando si salva il cliente dal form di registrazione
     */
    public function hookActionCustomerFormDataProviderData($params)
    {
        return $this->hookManager->hookActionCustomerFormDataProviderData($params);
    }

    public function hookActionObjectAddressAddAfter($params)
    {
        // $params['object'] contiene l'oggetto Address appena creato
        $address = $params['object'];

        $deliveryAddressId = (int) $address->id;
        $customerId = (int) ($address->id_customer ?: $this->context->customer->id);

        if ($deliveryAddressId && $customerId) {
            $customer = new ModelCustomerInvoice($customerId);

            if (!$customer->vat_number && !$customer->dni) {
                return;
            }

            $c_cart = $this->context->cart;
            if (!$c_cart || !isset($c_cart->id) || !(int) $c_cart->id) {
                $cookieCartId = (int) ($this->context->cookie->id_cart ?? 0);
                if ($cookieCartId) {
                    $c_cart = new Cart($cookieCartId);
                }
            }

            if (!$c_cart || !Validate::isLoadedObject($c_cart)) {
                return;
            }

            $invoiceAddressId = (int) ($customer->id_address_invoice ?: 0);

            Db::getInstance()->update(
                'cart',
                [
                    'id_address_delivery' => $deliveryAddressId,
                    'id_address_invoice' => $invoiceAddressId,
                ],
                'id_cart=' . (int) $c_cart->id
            );

            $this->context->cart->id_address_delivery = $deliveryAddressId;
            $this->context->cart->id_address_invoice = $invoiceAddressId;
            $this->context->cart->save();
            $this->context->cookie->__set('mpcustomerinvoice_id_address_delivery', $deliveryAddressId);
            $this->context->cookie->__set('mpcustomerinvoice_id_address_invoice', $invoiceAddressId);
            $this->context->cookie->write();
        }
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        return $this->hookManager->hookActionObjectCustomerDeleteAfter($params);
    }


    public function hookActionDispatcherBefore($params)
    {
        $controller = Tools::getValue('controller');
        if ($controller == 'order') {
            $deliverAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_delivery');
            $invoiceAddressId = (int) $this->context->cookie->__get('mpcustomerinvoice_id_address_invoice');
            if ($deliverAddressId && $invoiceAddressId) {
                $cart = $this->context->cart;
                if (!$cart || !Validate::isLoadedObject($cart)) {
                    $cookieCartId = (int) ($this->context->cookie->id_cart ?? 0);
                    if ($cookieCartId) {
                        $cart = new Cart($cookieCartId);
                    }
                }

                if ($cart && Validate::isLoadedObject($cart)) {
                    $cart->id_address_delivery = $deliverAddressId;
                    $cart->id_address_invoice = $invoiceAddressId;
                    $cart->update();
                    $this->context->cart = $cart;

                    $this->context->cookie->__unset('mpcustomerinvoice_id_address_delivery');
                    $this->context->cookie->__unset('mpcustomerinvoice_id_address_invoice');
                    $this->context->cookie->write();
                }
            }

            if (!$this->context->customer->isLogged()) {
                $registrationUrl = $this->context->link->getPageLink('registration');
                // Reindirizza alla pagina di registrazione
                Tools::redirect($registrationUrl);
            }
        }
    }

}