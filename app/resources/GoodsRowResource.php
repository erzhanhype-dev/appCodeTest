<?php

namespace App\Resources;

use Goods;
use Phalcon\Di\Injectable;

class GoodsRowResource extends Injectable
{
    public static function collection(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::fromRow($row);
        }
        return $out;
    }

    public static function fromRow(object|array $row): array
    {
//        $di = Di::getDefault();
//        $translator = $di->has('translator') ? $di->getShared('translator') : null;

        $goods = Goods::findFirstById($row->id);

        return [
            'id' => $goods->id,
            'tn_code' => $goods->ref_tn_code->code,
            'basis' => $goods->basis,
            'basis_date' => $goods->basis_date ? date('d.m.Y', $goods->basis_date) : '',
            'weight' => $goods->weight,
            'package_weight' => $goods->package_weight,
            'goods_cost' => self::fmtMoney((float)($goods->goods_cost ?? 0)),
            'package_cost' => self::fmtMoney((float)($goods->package_cost ?? 0)),
            'amount' => self::fmtMoney($goods->amount),
            'date_import' => !empty($goods->date_import) ? date('d.m.Y', $goods->date_import) : null,
            'ref_country' => $goods->country ? $goods->country->name : '-',
        ];
    }

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
    }
}