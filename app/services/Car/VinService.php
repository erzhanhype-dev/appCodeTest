<?php

namespace App\Services\Car;

use User;

class VinService
{
    public function transliterate(string $input): string
    {

        $cyrToLat = [
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'М' => 'M',
            'Н' => 'H', 'К' => 'K', 'Р' => 'P', 'С' => 'C',
            'Т' => 'T', 'Х' => 'X', 'О' => 'O'
        ];

        return str_replace(array_keys($cyrToLat), array_values($cyrToLat), $input);
    }

    public function sanitize(string $input): string
    {
        return preg_replace('/(\W)/u', '', $input);
    }

    public function isValidVin(string $vin): bool
    {
        return mb_strlen($vin) === 17;
    }

    public function processVin(?string $vin): ?string
    {
        if (!$vin) {
            return null;
        }

        $vin = $this->transliterate($vin);
        $vin = $this->sanitize($vin);

        return $vin;
    }

    public function buildVinFromParts(string $idCode, string $bodyCode): string
    {
        $id = $this->sanitize($idCode);
        $body = $this->sanitize($bodyCode);

        return $id . '&' . $body;
    }

    //допускать только латиницу
    public function isLatinAndDigitsOnly($string): bool
    {
        return preg_match('/^[A-Za-z0-9]+$/', $string);
    }

    //допускать кирилицу если вся строка не в кирилице
    public function isAllCyrillic($string): bool
    {
        return preg_match('/^[А-Яа-яЁё]+$/u', $string);
    }

}
