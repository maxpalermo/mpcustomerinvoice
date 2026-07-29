<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Helper Class for Processing and Saving Customer Invoice Form Data (CRUD).
 */

namespace MpSoft\MpCustomerInvoice\Helpers;

use MpSoft\MpCustomerInvoice\Models\ModelCustomerInvoice;
use Validate;
use Db;

class CustomerInvoiceFormHandler
{
    /**
     * Process and save customer invoice data from POST array.
     */
    public static function saveFromPost(int $idCustomer, array $data): array
    {
        if ($idCustomer <= 0) {
            return [
                'success' => false,
                'error' => 'ID cliente non valido.',
            ];
        }

        $model = new ModelCustomerInvoice($idCustomer);
        $isNew = !Validate::isLoadedObject($model);

        if ($isNew) {
            $model->force_id = true;
            $model->id = $idCustomer;
            $model->date_add = date('Y-m-d H:i:s');
        }

        // Hydrate & sanitize input fields
        if (isset($data['type'])) {
            $type = trim((string) $data['type']);
            $allowedTypes = [
                ModelCustomerInvoice::TYPE_CUSTOMER_PRIVATO,
                ModelCustomerInvoice::TYPE_CUSTOMER_PARTITA_IVA,
                ModelCustomerInvoice::TYPE_CUSTOMER_ENTE,
            ];
            $model->type = in_array($type, $allowedTypes, true) ? $type : ModelCustomerInvoice::TYPE_CUSTOMER_PRIVATO;
        }

        if (isset($data['company'])) {
            $model->company = mb_substr(trim((string) $data['company']), 0, 255);
        }

        if (isset($data['vat_number'])) {
            $model->vat_number = mb_substr(mb_strtoupper(trim((string) $data['vat_number'])), 0, 16);
        }

        if (isset($data['dni'])) {
            $model->dni = mb_substr(mb_strtoupper(trim((string) $data['dni'])), 0, 16);
        }

        if (isset($data['cuu'])) {
            $model->cuu = mb_substr(mb_strtoupper(trim((string) $data['cuu'])), 0, 6);
        }

        if (isset($data['sdi'])) {
            $model->sdi = mb_substr(mb_strtoupper(trim((string) $data['sdi'])), 0, 7);
        }

        if (isset($data['pec'])) {
            $pec = trim((string) $data['pec']);
            if ($pec === '' || Validate::isEmail($pec)) {
                $model->pec = mb_substr($pec, 0, 255);
            }
        }

        if (isset($data['cig'])) {
            $model->cig = mb_substr(mb_strtoupper(trim((string) $data['cig'])), 0, 10);
        }

        if (isset($data['cup'])) {
            $model->cup = mb_substr(mb_strtoupper(trim((string) $data['cup'])), 0, 15);
        }

        if (isset($data['id_eurosolution'])) {
            $model->id_eurosolution = (int) $data['id_eurosolution'];
        }

        if (isset($data['is_foreign'])) {
            $model->is_foreign = (bool) $data['is_foreign'];
        }

        if (isset($data['id_customer_invoice_job_area'])) {
            $model->id_customer_invoice_job_area = (int) $data['id_customer_invoice_job_area'];
        }

        if (isset($data['id_customer_invoice_job_position'])) {
            $model->id_customer_invoice_job_position = (int) $data['id_customer_invoice_job_position'];
        }

        if (isset($data['id_address_invoice'])) {
            $model->id_address_invoice = (int) $data['id_address_invoice'];
        }

        if (isset($data['invoice_requested'])) {
            $model->invoice_requested = (int) $data['invoice_requested'];
        } elseif (isset($data['want_invoice'])) {
            $model->invoice_requested = (int) $data['want_invoice'];
        }

        $model->date_upd = date('Y-m-d H:i:s');

        try {
            if ($isNew) {
                $model->force_id = true;
                $model->id = $idCustomer;
                $saved = $model->add(true, true);
            } else {
                $saved = $model->update(true);
            }

            if (!$saved) {
                return [
                    'success' => false,
                    'error' => 'Errore durante il salvataggio dei dati: ' . Db::getInstance()->getMsgError(),
                ];
            }

            // Sync vat_number, dni, and company with customer Address objects
            self::syncCustomerAddresses($idCustomer, $model);

            return [
                'success' => true,
                'message' => 'Dati fatturazione elettronica salvati con successo!',
                'data' => CustomerInvoiceCardRenderer::getCustomerInvoiceData($idCustomer),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Eccezione durante il salvataggio: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize vat_number, dni, and company with PrestaShop Address objects for this customer.
     */
    protected static function syncCustomerAddresses(int $idCustomer, ModelCustomerInvoice $model): void
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_address` FROM `' . _DB_PREFIX_ . 'address`
             WHERE `id_customer` = ' . (int) $idCustomer . '
             AND `deleted` = 0'
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $addrRow) {
            $idAddress = (int) ($addrRow['id_address'] ?? 0);
            if ($idAddress <= 0) {
                continue;
            }

            $addressObj = new \Address($idAddress);
            if (!Validate::isLoadedObject($addressObj)) {
                continue;
            }

            $updated = false;

            if (isset($model->vat_number) && $addressObj->vat_number !== $model->vat_number) {
                $addressObj->vat_number = $model->vat_number;
                $updated = true;
            }

            if (isset($model->dni) && $addressObj->dni !== $model->dni) {
                $addressObj->dni = $model->dni;
                $updated = true;
            }

            if (isset($model->company) && !empty($model->company) && $addressObj->company !== $model->company) {
                $addressObj->company = $model->company;
                $updated = true;
            }

            if ($updated) {
                $addressObj->save();
            }
        }
    }
}
