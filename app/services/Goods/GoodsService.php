<?php

namespace App\Services\Goods;

use Goods;
use Phalcon\Di\Injectable;

class GoodsService extends Injectable
{
    public function itemsByProfile($profileId)
    {
        return Goods::find([
            'conditions' => 'profile_id = :pid:',
            'bind' => ['pid' => $profileId],
            'order' => 'id DESC',
        ]);
    }

    public function getTotalCostByIds(array $goods_ids): float
    {
        $sum = 0;

        if (count($goods_ids) > 0) {
            foreach ($goods_ids as $g_id) {
                $goods = Goods::findFirstById($g_id);
                $sum += $goods->goods_cost;
            }
        }

        return $sum;
    }

}