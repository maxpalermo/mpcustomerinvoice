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

namespace MpSoft\MpCustomerInvoice\Validator;

use MpSoft\MpCustomerInvoice\Validator\Constraints\ItalianTaxCode;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ItalianTaxCodeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ItalianTaxCode) {
            throw new \InvalidArgumentException('Constraint non supportata');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]/', '', $value);

        // Determina la modalità di validazione
        $mode = $constraint->mode;
        if ($mode === 'auto') {
            $mode = (is_numeric($value) && strlen($value) === 11) ? 'piva' : 'cf';
        }

        if ($mode === 'piva') {
            if (!$this->isValidPartitaIva($value)) {
                $this->context->buildViolation($constraint->messagePiva)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        } elseif ($mode === 'cf') {
            if (!$this->isValidCodiceFiscale($value)) {
                $this->context->buildViolation($constraint->messageCf)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        }
    }

    private function isValidPartitaIva(string $value): bool
    {
        // Partita IVA italiana: 11 cifre
        if (!preg_match('/^[0-9]{11}$/', $value)) {
            return false;
        }

        // Algoritmo di controllo cifra di verifica
        $sum = 0;
        for ($i = 0; $i < 10; $i += 2) {
            $sum += ord($value[$i]) - ord('0');
        }
        for ($i = 1; $i < 10; $i += 2) {
            $digit = (ord($value[$i]) - ord('0')) * 2;
            $sum += ($digit < 10) ? $digit : $digit - 9;
        }
        $check = (10 - ($sum % 10)) % 10;

        return $check == (ord($value[10]) - ord('0'));
    }

    private function isValidCodiceFiscale(string $value): bool
    {
        // Codice Fiscale italiano: 16 caratteri alfanumerici
        if (strlen($value) !== 16) {
            return false;
        }

        // Formato: 6 lettere, 2 numeri, 1 lettera, 2 numeri, 1 lettera, 3 numeri, 1 lettera
        return (bool) preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $value);
    }
}
