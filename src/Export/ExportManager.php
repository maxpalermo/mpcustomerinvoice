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

namespace MpSoft\MpCustomerInvoice\Export;

abstract class ExportManager
{
    /**
     * @var int
     */
    protected $idOrder;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var \Order
     */
    protected $order;

    protected $subjects = [
        'PRIVATO' => 'F',
        'PARTITA_IVA' => 'G',
        'ENTE' => 'E'
    ];

    /**
     * ExportManager constructor.
     *
     * @param int $idOrder
     * @param string $type
     */
    public function __construct($idOrder, $type)
    {
        $this->idOrder = (int) $idOrder;
        $this->type = $type;
    }

    /**
     * Get the Order object.
     *
     * @return \Order
     * @throws \Exception
     */
    public function getOrder()
    {
        if ($this->order === null) {
            $this->order = new \Order($this->idOrder);
            if (!\Validate::isLoadedObject($this->order)) {
                throw new \Exception("Order #{$this->idOrder} not found");
            }
        }
        return $this->order;
    }

    /**
     * Instantiate the correct exporter, build the data, convert to XML and download.
     *
     * @param int $idOrder
     * @param string $type
     * @return void
     */
    public static function run($idOrder, $type)
    {
        switch ($type) {
            case 'invoice':
                $exporter = new ExportInvoice($idOrder, $type);
                break;
            case 'delivery':
            case 'sales_note':
                $exporter = new ExportDelivery($idOrder, $type);
                break;
            case 'order':
            default:
                $exporter = new ExportOrder($idOrder, $type);
                break;
        }

        $data = $exporter->getData();
        $filename = $exporter->getFilename();

        JsonToXml::convertAndDownload($data, $filename);
    }

    /**
     * Merge common and specific data and return the structured array.
     *
     * @return array
     */
    public function getData()
    {
        $common = $this->getCommonData();
        $specific = $this->getSpecificData();

        $invoiceData = [
            'document_type' => (string) ($specific['document_type'] ?? ''),
            'order_id' => (string) ($common['order_id'] ?? ''),
            'order_date' => (string) ($common['order_date'] ?? ''),
            'order_reference' => (string) ($common['order_reference'] ?? ''),
            'current_status' => (string) ($common['current_status'] ?? ''),
            'invoice_id' => (string) ($specific['invoice_id'] ?? ''),
            'invoice_number' => (string) ($specific['invoice_number'] ?? ''),
            'invoice_date' => (string) ($specific['invoice_date'] ?? ''),
            'products_tax_excl' => (string) ($common['products_tax_excl'] ?? ''),
            'discounts_tax_excl' => (string) ($common['discounts_tax_excl'] ?? ''),
            'shipping_tax_excl' => (string) ($common['shipping_tax_excl'] ?? ''),
            'wrapping_tax_excl' => (string) ($common['wrapping_tax_excl'] ?? ''),
            'products_tax_incl' => (string) ($common['products_tax_incl'] ?? ''),
            'discounts_tax_incl' => (string) ($common['discounts_tax_incl'] ?? ''),
            'shipping_tax_incl' => (string) ($common['shipping_tax_incl'] ?? ''),
            'wrapping_tax_incl' => (string) ($common['wrapping_tax_incl'] ?? ''),
            'total_tax_excl' => (string) ($common['total_tax_excl'] ?? ''),
            'total_taxes' => (string) ($common['total_taxes'] ?? ''),
            'total_tax_incl' => (string) ($common['total_tax_incl'] ?? ''),
            'total_paid' => (string) ($common['total_paid'] ?? ''),
            'vat_code' => (string) ($common['vat_code'] ?? ''),
            'rounds' => (string) ($common['rounds'] ?? ''),
            'nc' => (string) ($common['nc'] ?? ''),
            'payment' => (string) ($common['payment'] ?? ''),
            'carrier' => (string) ($common['carrier'] ?? ''),
            'shop_address' => (string) ($common['shop_address'] ?? ''),
            'foreign' => (string) ($common['foreign'] ?? ''),
            'discount_note' => (string) ($common['discount_note'] ?? ''),
            'customer' => $common['customer'] ?? [],
            'rows' => $common['rows'] ?? [],
            'fees' => $common['fees'] ?? []
        ];

        return [
            'invoices' => [
                'invoice' => $invoiceData
            ]
        ];
    }

    /**
     * Get specific data for the document type.
     *
     * @return array
     */
    abstract protected function getSpecificData();

    /**
     * Get target filename for the download.
     *
     * @return string
     */
    abstract protected function getFilename();

    /**
     * Get real common data shared among all document exports.
     *
     * @return array
     */
    protected function getCommonData()
    {
        $order = $this->getOrder();
        $customer = new \Customer($order->id_customer);
        $customerInvoice = new \MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice($customer->id);

        $addressDelivery = new \Address($order->id_address_delivery);
        $addressInvoice = new \Address($order->id_address_invoice);

        // Current status
        $orderState = new \OrderState($order->getCurrentState(), \Context::getContext()->language->id);
        $currentStatus = $orderState->name ?: '';

        // Shop Address
        $shopName = \Configuration::get('PS_SHOP_NAME');
        $shopAddr1 = \Configuration::get('PS_SHOP_ADDR1');
        $shopAddr2 = \Configuration::get('PS_SHOP_ADDR2');
        $shopCode = \Configuration::get('PS_SHOP_CODE');
        $shopCity = \Configuration::get('PS_SHOP_CITY');
        $shopCountryId = (int) \Configuration::get('PS_SHOP_COUNTRY_ID');
        $shopCountry = $shopCountryId ? \Country::getNameById($shopCountryId, \Context::getContext()->language->id) : '';
        $shopStateId = (int) \Configuration::get('PS_SHOP_STATE_ID');
        $shopState = $shopStateId ? \State::getNameById($shopStateId) : '';
        $shopDetails = \Configuration::get('PS_SHOP_DETAILS');

        $shopAddressParts = [];
        if ($shopName)
            $shopAddressParts[] = $shopName;
        if ($shopDetails)
            $shopAddressParts[] = $shopDetails;
        $addr = [];
        if ($shopAddr1)
            $addr[] = $shopAddr1;
        if ($shopAddr2)
            $addr[] = $shopAddr2;
        if ($shopCode || $shopCity)
            $addr[] = trim($shopCode . ' ' . $shopCity);
        if ($shopState)
            $addr[] = $shopState;
        if ($shopCountry)
            $addr[] = $shopCountry;
        if (!empty($addr)) {
            $shopAddressParts[] = implode(', ', $addr);
        }
        $shopAddress = implode(' - ', $shopAddressParts);

        // Discounts/Cart rules
        $cartRules = $order->getCartRules();
        $discountNotes = [];
        foreach ($cartRules as $rule) {
            $discountNotes[] = $rule['name'];
        }
        $discountNote = implode(', ', $discountNotes);

        // Carrier
        $carrier = new \Carrier($order->id_carrier);
        $carrierName = $carrier->name ?: '';

        // Customer subject (F/G/E)
        $type = $customerInvoice->type;
        $subject = isset($this->subjects[$type]) ? $this->subjects[$type] : '--';

        // Customer ID
        $customerId = 'DL' . $customer->id;

        // Rows
        $products = $order->getProducts();
        $rows = [];

        // Customized datas
        $customizedDatas = \Product::getAllCustomizedDatas($order->id_cart);

        foreach ($products as $product) {
            // Attributes (size/color)
            $attributes = [];
            $size = '';
            $color = '';
            if (!empty($product['product_attribute_id'])) {
                $sql = 'SELECT g.id_attribute_group, gl.name as group_name, al.name as attribute_name
                        FROM ' . _DB_PREFIX_ . 'product_attribute_combination pac
                        JOIN ' . _DB_PREFIX_ . 'attribute a ON a.id_attribute = pac.id_attribute
                        JOIN ' . _DB_PREFIX_ . 'attribute_group g ON g.id_attribute_group = a.id_attribute_group
                        JOIN ' . _DB_PREFIX_ . 'attribute_group_lang gl ON (gl.id_attribute_group = g.id_attribute_group AND gl.id_lang = ' . (int) \Context::getContext()->language->id . ')
                        JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (al.id_attribute = a.id_attribute AND al.id_lang = ' . (int) \Context::getContext()->language->id . ')
                        WHERE pac.id_product_attribute = ' . (int) $product['product_attribute_id'];
                $dbAttributes = \Db::getInstance()->executeS($sql);

                if ($dbAttributes) {
                    $idx = 0;
                    foreach ($dbAttributes as $attr) {
                        $groupNameLower = strtolower($attr['group_name']);
                        if (strpos($groupNameLower, 'tagli') !== false || strpos($groupNameLower, 'size') !== false) {
                            $size = strtoupper($attr['attribute_name']);
                        } elseif (strpos($groupNameLower, 'color') !== false) {
                            $color = strtoupper($attr['attribute_name']);
                        }

                        $attributes['lev_' . $idx] = [
                            'id_attribute_group' => (string) $attr['id_attribute_group'],
                            'group' => $attr['group_name'],
                            'name' => $attr['attribute_name']
                        ];
                        $idx++;
                    }
                }
            }

            // Customization
            $customizationData = '';
            if (isset($customizedDatas[$product['product_id']][$product['product_attribute_id']])) {
                $customizationData = [];
                $custIdx = 0;
                foreach ($customizedDatas[$product['product_id']][$product['product_attribute_id']] as $deliveryAddressId => $customizationsList) {
                    foreach ($customizationsList as $idCustomization => $customizationItems) {
                        $fields = [];
                        if (isset($customizationItems['datas'][\Product::CUSTOMIZE_TEXTFIELD])) {
                            $subIdx = 0;
                            foreach ($customizationItems['datas'][\Product::CUSTOMIZE_TEXTFIELD] as $textField) {
                                $fields['lev_' . $subIdx] = [
                                    'id_customization' => (string) $idCustomization,
                                    'type' => '1',
                                    'index' => (string) $textField['id_customization_field'],
                                    'title' => $textField['name'],
                                    'value' => $textField['value']
                                ];
                                $subIdx++;
                            }
                        }
                        if (!empty($fields)) {
                            $customizationData['lev_' . $custIdx] = $fields;
                            $custIdx++;
                        }
                    }
                }
            }

            // Product image path
            $id_image = 0;
            if (!empty($product['product_attribute_id'])) {
                $combinationImages = \Product::getCombinationImageById((int) $product['product_attribute_id'], (int) \Context::getContext()->language->id);
                if (!empty($combinationImages)) {
                    $id_image = (int) $combinationImages[0]['id_image'];
                }
            }
            if (!$id_image) {
                $cover = \Product::getCover((int) $product['product_id']);
                if (isset($cover['id_image'])) {
                    $id_image = (int) $cover['id_image'];
                }
            }

            $imageUrl = '';
            if ($id_image) {
                $imgFolder = \Image::getImgFolderStatic($id_image);
                $imageUrl = _PS_PROD_IMG_DIR_ . $imgFolder . $id_image . '-small_default.jpg';
                if (!file_exists($imageUrl)) {
                    $imageUrl = _PS_PROD_IMG_DIR_ . $imgFolder . $id_image . '.jpg';
                }
            }

            // Stock
            $stockQty = \StockAvailable::getQuantityAvailableByProduct($product['product_id'], $product['product_attribute_id']);

            $originalPriceTaxExcl = isset($product['original_product_price']) ? $product['original_product_price'] : $product['unit_price_tax_excl'];
            $originalPriceTaxIncl = isset($product['original_product_price_wt']) ? $product['original_product_price_wt'] : $product['unit_price_tax_incl'];

            $table = _DB_PREFIX_ . 'product_lang';
            $idLang = (int) \Context::getContext()->language->id;
            $productId = (int) $product['product_id'];

            $productNameQuery = "SELECT COALESCE(`name`, '--') FROM {$table} WHERE id_product = {$productId} AND id_lang = {$idLang}";
            $productName = \Db::getInstance()->getValue($productNameQuery);

            // Location / Ubicazione prodotto
            $location = '';
            $idProd = (int) $product['product_id'];
            $idAttr = (int) $product['product_attribute_id'];

            if (class_exists('\StockAvailable')) {
                $location = \StockAvailable::getLocation($idProd, $idAttr);
                if (empty($location) && $idAttr > 0) {
                    $location = \StockAvailable::getLocation($idProd, 0);
                }
            }

            if (empty($location) && !empty($product['location'])) {
                $location = $product['location'];
            }

            if (empty($location)) {
                $sqlLoc = 'SELECT location FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . $idProd . ' AND id_product_attribute = ' . $idAttr;
                $location = (string) \Db::getInstance()->getValue($sqlLoc);
                if (empty($location) && $idAttr > 0) {
                    $sqlLoc = 'SELECT location FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . $idProd . ' AND id_product_attribute = 0';
                    $location = (string) \Db::getInstance()->getValue($sqlLoc);
                }
            }

            if (empty($location)) {
                $sqlLocProd = 'SELECT location FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . $idProd;
                $location = (string) \Db::getInstance()->getValue($sqlLocProd);
            }

            $rows[] = [
                'ean13' => (string) $product['ean13'],
                'reference' => (string) $product['reference'],
                'original_price_tax_excl' => number_format((float) $originalPriceTaxExcl, 6, '.', ''),
                'product_price_tax_excl' => number_format((float) $product['unit_price_tax_excl'], 6, '.', ''),
                'original_price_tax_incl' => number_format((float) $originalPriceTaxIncl, 6, '.', ''),
                'discount_percent' => number_format((float) $product['reduction_percent'], 2, '.', ''),
                'reduction_amount' => number_format((float) $product['reduction_amount_tax_excl'], 6, '.', ''),
                'price_tax_excl' => number_format((float) $product['unit_price_tax_excl'], 6, '.', ''),
                'unit_price_tax_excl' => number_format((float) $product['unit_price_tax_excl'], 6, '.', ''),
                'unit_price_tax_incl' => number_format((float) $product['unit_price_tax_incl'], 6, '.', ''),
                'qty' => (string) $product['product_quantity'],
                'total_tax_excl' => number_format((float) $product['total_price_tax_excl'], 6, '.', ''),
                'total_price_tax_excl' => number_format((float) $product['total_price_tax_excl'], 6, '.', ''),
                'total_price_tax_incl' => number_format((float) $product['total_price_tax_incl'], 6, '.', ''),
                'total_tax_incl' => number_format((float) $product['total_price_tax_incl'], 6, '.', ''),
                'tax_rate' => number_format((float) $product['tax_rate'], 0, '.', ''),
                'product_id' => (string) $product['product_id'],
                'product_attribute_id' => (string) $product['product_attribute_id'],
                'product_check_qty' => [
                    'employee' => ' ',
                    'date_checked' => '',
                    'is_checked' => '0'
                ],
                'product_name' => $productName,
                'size' => $size,
                'color' => $color,
                'stock_service' => '0',
                'product_in_stock' => (string) $stockQty,
                'product_refunded' => (string) ($product['product_quantity_refunded'] ?? 0),
                'product_returned' => (string) ($product['product_quantity_return'] ?? 0),
                'product_reinjected' => (string) ($product['product_quantity_reinjected'] ?? 0),
                'image_url' => $imageUrl,
                'customization' => $customizationData,
                'attributes' => !empty($attributes) ? $attributes : '',
                'location' => (string) $location,
                'product_position' => (string) $location
            ];
        }

        // Addresses formats
        $addrDeliveryData = $this->formatAddress($addressDelivery, $subject);
        $addrInvoiceData = $this->formatAddress($addressInvoice, $subject);

        // Prioritize PEC/SDI/Vat/Dni from ModelCustomerInvoice
        if ($customerInvoice->vat_number) {
            $addrInvoiceData['vat_number'] = $customerInvoice->vat_number;
        }
        if ($customerInvoice->dni) {
            $addrInvoiceData['dni'] = $customerInvoice->dni;
        }

        $fees = $this->calcFees();
        $fee_excl = isset($fees['fee_tax_excl']) ? (float) $fees['fee_tax_excl'] : 0.0;
        $fee_incl = isset($fees['fee_tax_incl']) ? (float) $fees['fee_tax_incl'] : 0.0;

        $totalPaidTaxIncl = (float) $order->total_paid_tax_incl;
        $totalPaidTaxExcl = (float) $order->total_paid_tax_excl;
        $totalPaidReal = (float) $order->total_paid_real;

        $baseProductsShippingIncl = (float) $order->total_products_wt
            - (float) $order->total_discounts_tax_incl
            + (float) $order->total_shipping_tax_incl
            + (float) $order->total_wrapping_tax_incl;

        if ($fee_incl > 0 && abs($totalPaidTaxIncl - $baseProductsShippingIncl) < ($fee_incl / 2)) {
            $totalPaidTaxIncl += $fee_incl;
            $totalPaidTaxExcl += $fee_excl;
            $totalPaidReal += $fee_incl;
        }

        $rounds = $this->calcRound();

        return [
            'order_id' => (string) $order->id,
            'order_date' => $order->date_add,
            'order_reference' => $order->reference,
            'current_status' => $currentStatus,
            'products_tax_excl' => number_format((float) $order->total_products, 2, '.', ''),
            'discounts_tax_excl' => number_format((float) $order->total_discounts_tax_excl, 2, '.', ''),
            'shipping_tax_excl' => number_format((float) $order->total_shipping_tax_excl, 2, '.', ''),
            'wrapping_tax_excl' => number_format((float) $order->total_wrapping_tax_excl, 2, '.', ''),
            'products_tax_incl' => number_format((float) $order->total_products_wt, 6, '.', ''),
            'discounts_tax_incl' => number_format((float) $order->total_discounts_tax_incl, 6, '.', ''),
            'shipping_tax_incl' => number_format((float) $order->total_shipping_tax_incl, 6, '.', ''),
            'wrapping_tax_incl' => number_format((float) $order->total_wrapping_tax_incl, 6, '.', ''),
            'total_tax_excl' => number_format((float) $totalPaidTaxExcl, 6, '.', ''),
            'total_taxes' => number_format((float) ($totalPaidTaxIncl - $totalPaidTaxExcl), 2, '.', ''),
            'total_tax_incl' => number_format((float) $totalPaidTaxIncl, 6, '.', ''),
            'total_paid' => number_format((float) $totalPaidReal, 6, '.', ''),
            'vat_code' => '',
            'rounds' => number_format($rounds, 2, '.', ''),
            'nc' => '0',
            'payment' => $order->payment,
            'carrier' => $carrierName,
            'shop_address' => $shopAddress,
            'foreign' => (string) ($customerInvoice->is_foreign ?: 0),
            'discount_note' => $discountNote,
            'customer' => [
                'id' => $customerId,
                'id_customer' => (string) $customer->id,
                'gender' => $customer->id_gender == 1 ? 'M' : ($customer->id_gender == 2 ? 'F' : ''),
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'birthday' => $customer->birthday,
                'pec' => (string) ($customerInvoice->pec ?: ''),
                'uid' => (string) ($customerInvoice->sdi ?: ''),
                'email' => $customer->email,
                'new' => \Order::getCustomerNbOrders($customer->id) <= 1 ? '1' : '0',
                'address_delivery' => $addrDeliveryData,
                'address_invoice' => $addrInvoiceData
            ],
            'rows' => [
                'row' => $rows
            ],
            'fees' => $this->calcFees()
        ];
    }

    /**
     * Formats address to export array schema.
     *
     * @param \Address $address
     * @param string $subject
     * @return array
     */
    protected function formatAddress(\Address $address, $subject)
    {
        $state = new \State($address->id_state);
        $country = new \Country($address->id_country, \Context::getContext()->language->id);

        return [
            'subject' => $subject,
            'company' => (string) $address->company,
            'firstname' => $address->firstname,
            'lastname' => $address->lastname,
            'address1' => $address->address1,
            'address2' => (string) $address->address2,
            'postcode' => $address->postcode,
            'city' => $address->city,
            'state_name' => strtoupper($state->name),
            'country_name' => strtoupper($country->name),
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'vat_number' => (string) $address->vat_number,
            'dni' => (string) $address->dni,
            'state' => (string) $state->iso_code,
            'country' => (string) \Country::getIsoById($address->id_country)
        ];
    }

    /**
     * Calculate round value based on VAT configuration and order totals.
     *
     * @return float
     */
    protected function calcRound()
    {
        $order = $this->getOrder();

        $fees = $this->calcFees();
        $fee_excl = isset($fees['fee_tax_excl']) ? (float) $fees['fee_tax_excl'] : 0.0;
        $fee_incl = isset($fees['fee_tax_incl']) ? (float) $fees['fee_tax_incl'] : 0.0;

        $reference_price = (float) $order->total_paid_tax_incl;

        $base_products_shipping_incl = (float) $order->total_products_wt
            - (float) $order->total_discounts_tax_incl
            + (float) $order->total_shipping_tax_incl
            + (float) $order->total_wrapping_tax_incl;

        if ($fee_incl > 0 && abs($reference_price - $base_products_shipping_incl) < ($fee_incl / 2)) {
            $reference_price += $fee_incl;
        }

        $total_excl = (float) $order->total_products
            - (float) $order->total_discounts_tax_excl
            + (float) $order->total_shipping_tax_excl
            + (float) $order->total_wrapping_tax_excl
            + $fee_excl;

        $vat_rate = (float) \Configuration::get('MPCUSTOMERINVOICE_VAT_RATE');
        if ($vat_rate <= 0) {
            $vat_rate = 22.0; // Default fallback
        }

        $calculated_incl = $total_excl * (1 + $vat_rate / 100);

        return round($reference_price - $calculated_incl, 2);
    }

    /**
     * Calculate payment fees if mppaymentswithfees module is active or order has saved fee.
     *
     * @return array
     */
    protected function calcFees()
    {
        $defaultFees = [
            'fee_tax_excl' => '0',
            'fee_tax_rate' => '0',
            'fee_tax_incl' => '0'
        ];

        try {
            $order = $this->getOrder();

            $vat_rate = (float) \Configuration::get('MPCUSTOMERINVOICE_VAT_RATE');
            if ($vat_rate <= 0) {
                $vat_rate = 22.0;
            }

            // 1. Check mp_payment_fee_order table for this order ID first
            $feeRow = \Db::getInstance()->getRow('SELECT fee_amount, tax_included FROM ' . _DB_PREFIX_ . 'mp_payment_fee_order WHERE id_order = ' . (int) $order->id);
            if ($feeRow && !empty($feeRow['fee_amount']) && (float) $feeRow['fee_amount'] > 0) {
                $feeAmount = (float) $feeRow['fee_amount'];
                $taxIncluded = (int) $feeRow['tax_included'];
                if ($taxIncluded == 1) {
                    // fee_amount is tax included: calculate fee_tax_excl by separating VAT
                    $feeIncl = $feeAmount;
                    $feeExcl = $feeAmount / (1 + $vat_rate / 100);
                } else {
                    // fee_amount is tax excluded: calculate fee_tax_incl by adding VAT
                    $feeExcl = $feeAmount;
                    $feeIncl = $feeAmount * (1 + $vat_rate / 100);
                }
                return [
                    'fee_tax_excl' => number_format((float) $feeExcl, 2, '.', ''),
                    'fee_tax_rate' => number_format((float) $vat_rate, 0, '.', ''),
                    'fee_tax_incl' => number_format((float) $feeIncl, 2, '.', '')
                ];
            }

            // 2. Fallback to dynamic calculation via mppaymentswithfees module if enabled
            if (\Module::isEnabled('mppaymentswithfees')
                && class_exists('MpSoft\MpPaymentsWithFees\Helpers\Fees')
                && class_exists('MpSoft\MpPaymentsWithFees\Model\PaymentFeeModule')
            ) {
                $feeModule = \MpSoft\MpPaymentsWithFees\Model\PaymentFeeModule::getByModuleName($order->module);
                if ($feeModule && (int) $feeModule->active) {
                    $orderTotal = (float) $order->total_products_wt
                        + (float) $order->total_shipping_tax_incl
                        + (float) $order->total_wrapping_tax_incl
                        - (float) $order->total_discounts_tax_incl;

                    $result = \MpSoft\MpPaymentsWithFees\Helpers\Fees::calculate(
                        $orderTotal,
                        (int) $feeModule->id,
                        $vat_rate
                    );

                    if (isset($result['has_fee']) && $result['has_fee']) {
                        $feeIncl = (float) ($result['fee_with_tax'] ?? 0);
                        $feeExcl = (float) ($result['fee_tax_excl'] ?? 0);
                        $taxRate = (float) ($result['tax_rate'] ?? $vat_rate);

                        if ($feeIncl > 0 && abs($feeExcl - $feeIncl) < 0.001 && $taxRate > 0) {
                            $feeExcl = $feeIncl / (1 + $taxRate / 100);
                        }

                        return [
                            'fee_tax_excl' => number_format((float) $feeExcl, 2, '.', ''),
                            'fee_tax_rate' => number_format((float) $taxRate, 0, '.', ''),
                            'fee_tax_incl' => number_format((float) $feeIncl, 2, '.', '')
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback in case of any issues loading classes/database
        }

        return $defaultFees;
    }
}
