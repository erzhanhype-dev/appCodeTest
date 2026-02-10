<?php

trait DataHelperTrait
{
    // ...
    /** 'Y-m-d' или '' при невалидной дате. */
    protected function ymd($maybeDate): string
    {
        if (!$maybeDate) {
            return '';
        }
        $ts = strtotime((string)$maybeDate);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    /** true/'true'/1/'1' → true. */
    protected function asBoolean($v): bool
    {
        return $v === true || $v === 'true' || $v === 1 || $v === '1';
    }
}