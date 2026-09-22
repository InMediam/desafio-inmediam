<?php

namespace App\Support;

class DocumentValidator
{
    public static function isValid(string $document): bool
    {
        $document = preg_replace('/\D/', '', $document);

        return match (strlen($document)) {
            11 => self::isValidCpf($document),
            14 => self::isValidCnpj($document),
            default => false,
        };
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($length = 9; $length <= 10; $length++) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * (($length + 1) - $i);
            }

            $remainder = ($sum * 10) % 11;

            if ($remainder === 10) {
                $remainder = 0;
            }

            if ($remainder !== (int) $cpf[$length]) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ($digit1 !== (int) $cnpj[12]) {
            return false;
        }

        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return $digit2 === (int) $cnpj[13];
    }
}
