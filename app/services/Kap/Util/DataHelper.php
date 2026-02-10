<?php

namespace App\Services\Kap\Util;


class DataHelper
{
    /** 'Y-m-d' или '' при невалидной дате. */
    public function ymd($maybeDate): string
    {
        if (!$maybeDate) {
            return '';
        }
        // $maybeDate может быть '2023' -> strtotime(2023) будет 1970г,
        // поэтому тут нужен более строгий парсинг, но сохраняем логику date/strtotime
        $ts = strtotime((string)$maybeDate);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    /** true/'true'/1/'1' → true. */
    public function asBoolean($v): bool
    {
        return $v === true || $v === 'true' || $v === 1 || $v === '1';
    }
}