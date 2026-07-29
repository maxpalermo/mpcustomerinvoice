<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Helper Class for Rendering Customer Invoice Admin Card.
 */

namespace MpSoft\MpCustomerInvoice\Helpers;

use Context;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobArea;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobLink;
use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoiceJobPosition;
use Customer;
use Validate;

class CustomerInvoiceCardRenderer
{
    /**
     * Get complete Customer Invoice data array for a given customer ID.
     */
    public static function getCustomerInvoiceData(int $idCustomer): array
    {
        $context = Context::getContext();
        $idLang = (int) $context->language->id;

        $customer = new Customer($idCustomer);
        $customerExists = Validate::isLoadedObject($customer);

        $model = new ModelCustomerInvoice($idCustomer);
        if (!Validate::isLoadedObject($model)) {
            $model->id = $idCustomer;
            $model->type = ModelCustomerInvoice::TYPE_CUSTOMER_PRIVATO;
        }

        $jobAreas = ModelCustomerInvoiceJobArea::getList();
        
        $jobPositions = [];
        if ($model->id_customer_invoice_job_area) {
            $jobLinks = ModelCustomerInvoiceJobLink::getJobs((int) $model->id_customer_invoice_job_area);
            foreach ($jobLinks as $link) {
                $jobPositions[$link['id']] = $link['name'];
            }
        }
        if (empty($jobPositions)) {
            $jobPositions = ModelCustomerInvoiceJobPosition::getList();
        }

        $jobAreaName = '--';
        if ($model->id_customer_invoice_job_area && isset($jobAreas[$model->id_customer_invoice_job_area])) {
            $jobAreaName = $jobAreas[$model->id_customer_invoice_job_area];
        }

        $jobPositionName = '--';
        if ($model->id_customer_invoice_job_position && isset($jobPositions[$model->id_customer_invoice_job_position])) {
            $jobPositionName = $jobPositions[$model->id_customer_invoice_job_position];
        }

        // Fetch customer addresses for select dropdown and view mode display
        $addressesSql = '
            SELECT a.id_address, a.alias, a.firstname, a.lastname, a.company, a.address1, a.postcode, a.city, c.name as country_name
            FROM `' . _DB_PREFIX_ . 'address` a
            LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` c ON (a.id_country = c.id_country AND c.id_lang = ' . (int) $idLang . ')
            WHERE a.id_customer = ' . (int) $idCustomer . ' AND a.deleted = 0
            ORDER BY a.id_address ASC
        ';
        $addressesList = \Db::getInstance()->executeS($addressesSql) ?: [];

        $customerAddresses = [];
        $invoiceAddressText = '';

        foreach ($addressesList as $addr) {
            $idAddr = (int) $addr['id_address'];
            $label = '#' . $idAddr . ' - ' . ($addr['alias'] ? $addr['alias'] . ': ' : '') . $addr['firstname'] . ' ' . $addr['lastname'] . ' - ' . $addr['address1'] . ', ' . $addr['postcode'] . ' ' . $addr['city'] . ' (' . $addr['country_name'] . ')';
            $customerAddresses[$idAddr] = $label;

            if ((int) $model->id_address_invoice === $idAddr) {
                $invoiceAddressText = $label;
            }
        }

        return [
            'id_customer' => $idCustomer,
            'customer_firstname' => $customerExists ? $customer->firstname : '',
            'customer_lastname' => $customerExists ? $customer->lastname : '',
            'customer_email' => $customerExists ? $customer->email : '',
            'model' => [
                'id_customer' => (int) $model->id,
                'id_eurosolution' => $model->id_eurosolution ?: '',
                'type' => $model->type ?: ModelCustomerInvoice::TYPE_CUSTOMER_PRIVATO,
                'company' => $model->company ?: '',
                'vat_number' => $model->vat_number ?: '',
                'dni' => $model->dni ?: '',
                'cuu' => $model->cuu ?: '',
                'sdi' => $model->sdi ?: '',
                'pec' => $model->pec ?: '',
                'cig' => $model->cig ?: '',
                'cup' => $model->cup ?: '',
                'id_address_invoice' => (int) $model->id_address_invoice,
                'is_foreign' => (bool) $model->is_foreign,
                'invoice_requested' => (int) $model->invoice_requested,
                'id_customer_invoice_job_area' => (int) $model->id_customer_invoice_job_area,
                'id_customer_invoice_job_position' => (int) $model->id_customer_invoice_job_position,
                'date_add' => $model->date_add ?: '',
                'date_upd' => $model->date_upd ?: '',
            ],
            'job_area_name' => $jobAreaName,
            'job_position_name' => $jobPositionName,
            'customer_addresses' => $customerAddresses,
            'invoice_address_text' => $invoiceAddressText,
            'types' => [
                ModelCustomerInvoice::TYPE_CUSTOMER_PRIVATO => 'Privato',
                ModelCustomerInvoice::TYPE_CUSTOMER_PARTITA_IVA => 'Partita IVA',
                ModelCustomerInvoice::TYPE_CUSTOMER_ENTE => 'Ente Pubblico / P.A.',
            ],
            'job_areas' => $jobAreas,
            'job_positions' => $jobPositions,
            'admin_controller_url' => $context->link->getAdminLink('AdminMpCustomerInvoice'),
        ];
    }

    /**
     * Render the Customer Invoice card HTML template for Admin Customer page.
     */
    public static function renderCustomerCard(int $idCustomer): string
    {
        if ($idCustomer <= 0) {
            return '';
        }

        $data = self::getCustomerInvoiceData($idCustomer);
        $twig = new GetTwigEnvironment('mpcustomerinvoice');
        $template = $twig->load('@ModuleTwig/admin/customerInvoiceCard.html.twig');

        return $template->render($data);
    }
}
