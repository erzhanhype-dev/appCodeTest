<?php

namespace App\services\Fund;

use Phalcon\Di\Injectable;
use FundCar;
use FundGoods;
use FundGoodsHistories;
use FundProfile;
use Goods;
use Profile;
use RefCarCat;
use RefFund;
use RefModel;
use RefTnCode;
use Transaction;
use User;

class FundService extends Injectable
{
    public function getDeletedFundGoodsByFundId(FundProfile $f): array
    {
        $fund_goods = FundGoodsHistories::find([
            'conditions' => 'fund_id = :fund_id:',
            'bind' => [
                'fund_id' => $f->id
            ],
            'orderBy' => 'id DESC',
        ]);

        // Проверяем, если нет данных
        if (!$fund_goods) {
            return [];
        }

        // Преобразуем результат в массив
        $goodsArray = [];
        foreach ($fund_goods as $item) {
            $item->ref_tn_code = $item->ref_tn_code ? $item->ref_tn_code->toArray() : null;
            $item->date_produce = date('d.m.Y', $item->date_produce);
            $goodsArray[] = [
                'id' => $item->id,
                'weight' => $item->weight,
                'amount' => __money($item->cost),
                'date_produce' => $item->date_produce,
                'ref_tn_code' => $item->ref_tn_code,
                'status' => $item->status,
            ];
        }

        return $goodsArray;
    }

    public function getDeletedFundCarsbyFundId(FundProfile $f): array
    {
        $sql = <<<SQL
                SELECT
                  c.car_id AS c_id,
                  c.volume AS c_volume,
                  c.vin AS c_vin,
                  c.cost AS c_cost,
                  cc.name AS c_cat,
                  c.date_produce AS c_date_produce,
                  c.status AS c_status
                FROM FundCarHistories c
                  JOIN RefCarCat cc
                  JOIN RefCarType t
                WHERE
                  c.fund_id = :pid: AND
                  cc.id = c.ref_car_cat
                GROUP BY c.id
                ORDER BY c.id DESC
              SQL;

        $deleted_cars = $this->modelsManager->createQuery($sql);

        $cancelled_cars = $deleted_cars->execute(array(
            "pid" => $f->id
        ));
        $cancelled_cars_list = [];
        if (count($cancelled_cars) > 0) {
            foreach ($cancelled_cars as $c) {
                $cancelled_cars_list[] = [
                    "id" => $c->c_id,
                    "volume" => $c->c_volume,
                    "vin" => $c->c_vin,
                    "cost" => $c->c_cost,
                    "ref_car_cat" => $c->c_cat,
                    "date_produce" => date('d.m.Y', convertTimeZone($c->c_date_produce)),
                    "status" => $c->c_status,
                ];
            }
        }

        return $cancelled_cars_list;
    }

    public function getFundGoodsByFundId(FundProfile $fund): array
    {
        $fund_goods = FundGoods::find([
            'conditions' => 'fund_id = :fund_id:',
            'bind' => [
                'fund_id' => $fund->id
            ],
            'orderBy' => 'id DESC',
        ]);

        // Проверяем, если нет данных
        if (!$fund_goods) {
            return [];
        }

        // Преобразуем результат в массив
        $goodsArray = [];
        foreach ($fund_goods as $item) {
            $ref_tn_code = $item->ref_tn_code ? $item->ref_tn_code->toArray() : [];
            $date_produce = date('d.m.Y', $item->date_produce);
            $goodsArray[] = [
                'id' => $item->id,
                'profile_id' => $item->profile_id ? $item->profile_id : '-',
                'weight' => $item->weight,
                'amount' => __money($item->cost),
                'date_produce' => $date_produce,
                'ref_tn_code' => $ref_tn_code,
            ];
        }

        return $goodsArray;
    }

    public function getGoodsByFundProfile(FundProfile $fund, int $user_id): array
    {
        $agent_status = 'VENDOR';
        $profiles = Profile::find([
            'conditions' => 'user_id = :user_id: AND type = "GOODS" AND agent_status = :agent_status:',
            'bind' => [
                'user_id' => $fund->user_id,
                'agent_status' => $agent_status
            ]
        ]);

        if (!$profiles) {
            return [];
        }

        $profileIds = array_column($profiles->toArray(), 'id');

        if (count($profileIds) > 0) {
            $fundGoods = FundGoods::find([
                'conditions' => 'profile_id IN ({profile_ids:array})',
                'bind' => ['profile_ids' => $profileIds]
            ]);
        } else {
            return [];
        }

        if (!$fundGoods) {
            return [];
        }

        $goodsIds = array_column($fundGoods->toArray(), 'goods_id');
        $goods = null;
        $fund_goods_tires = implode('_', FUND_GOODS_TIRES);
        $goods_key = $fund_goods_tires . (($fund->type == 'EXP') ? '_EXP' : '');
        if ($fund->ref_fund_key == $goods_key) {
            $refTnCodes = RefTnCode::find([
                'conditions' => 'code IN ({ref_tn_codes:array})',
                'bind' => [
                    'ref_tn_codes' => FUND_GOODS_TIRES
                ]
            ]);
            $refTnIds = [];
            foreach ($refTnCodes as $refTnCode) {
                $refTnIds[] = $refTnCode->id;
            }

            $conditions = [];
            $bind = [];

            $conditions[] = "profile_id IN ({profile_ids:array})";
            $bind['profile_ids'] = !empty($profileIds) ? $profileIds : [0]; // защита от пустого IN

            $conditions[] = "ref_tn IN ({ids:array})";
            $bind['ids'] = !empty($refTnIds) ? $refTnIds : [-1]; // защита от пустого IN

            if (!empty($goodsIds)) {
                $conditions[] = "id NOT IN ({goods_ids:array})";
                $bind['goods_ids'] = $goodsIds;
            }

            $goods = Goods::find([
                'conditions' => implode(' AND ', $conditions),
                'bind' => $bind,
                'order' => 'id DESC', // <-- не orderBy
            ]);
        }

        if (!$goods) {
            return [];
        }

        $result = [];

        foreach ($goods as $item) {
            $transaction = Transaction::findFirstByProfileId($item->profile_id);
            if ($transaction->status == 'PAID' && $transaction->approve == 'GLOBAL') {
                $result[] = [
                    'id' => $item->id,
                    'profile_id' => $item->profile_id ? $item->profile_id : '-',
                    'weight' => $item->weight,
                    'amount' => __money($item->goods_cost),
                    'ref_tn_code' => $item->ref_tn_code ? $item->ref_tn_code->name : null,
                    'date_import' => $item->date_import ? date('d.m.Y', $item->date_import) : '',
                    'basis_date' => $item->basis_date ? date('d.m.Y', $item->basis_date) : '',
                    'date_approve' => $transaction->dt_approve ? date('d.m.Y', $transaction->dt_approve) : '',
                ];
            }
        }

        return $result;
    }

    public function getFundCarsByFundId(FundProfile $f): array
    {
        $fund_cars = FundCar::find([
            'conditions' => 'fund_id = :fund_id:',
            'bind' => [
                'fund_id' => $f->id
            ],
            'orderBy' => 'id DESC',
        ]);

        // Проверяем, если нет данных
        if (!$fund_cars) {
            return [];
        }

        // Преобразуем результат в массив
        $carsArray = [];
        foreach ($fund_cars as $item) {
            $ref_car_cat = RefCarCat::findFirstById($item->ref_car_cat);
            $ref_model = RefModel::findFirstById($item->model_id);

            $item->date_produce = date('d.m.Y', $item->date_produce);
            $item->ref_car_cat = $ref_car_cat ? $ref_car_cat->tech_category : '';
            $item->ref_model = $ref_model ? $ref_model->brand . ' - ' . $ref_model->model : '-';

            $carsArray[] = [
                'id' => $item->id,
                'volume' => $item->volume,
                'amount' => __money($item->cost),
                'vin' => $item->vin,
                'date_produce' => $item->date_produce,
                'ref_car_cat' => $item->ref_car_cat,
                'ref_model' => $item->ref_model,
            ];
        }

        return $carsArray;
    }

    public function getCarsByFundProfile(FundProfile $f, int $user_id): array
    {
        $data = [];
        $cars = [];
        $max = 100000; // максимум отображаемых после всех фильтров

        if ($f->ref_fund_key != null) {

            $exploted = explode("_", $f->ref_fund_key);
            $category_id = '';
            $volume_start = $exploted[1];
            $volume_end = $exploted[2];
            $st_type = 'c.ref_st_type = 0';

            if ($exploted[0] == "TRACTOR") {
                $category_id = '(13)';
            } else if ($exploted[0] == "COMBAIN") {
                $category_id = '(14)';
            } else if ($exploted[0] == "M1") {
                $category_id = '(1, 2)';
            } else if ($exploted[0] == "M2M3") {
                $category_id = '(9, 10, 11, 12)';
            } else if ($exploted[0] == "N") {
                $category_id = '(3, 4, 5, 6, 7, 8)';

                if(isset($exploted[3])) {
                    if ($exploted[3] == "ST") {
                        $st_type = 'c.ref_st_type > 0';
                    }
                }
            }

            if ($volume_end && $volume_end > 0) {
                $sql = <<<SQL
              SELECT
                c.id AS c_id,
                c.volume AS c_volume,
                c.vin AS c_vin,
                c.cost AS c_cost,
                rcc.name AS c_cat,
                FROM_UNIXTIME(c.date_import, "%d.%m.%Y") AS c_date_import,
                FROM_UNIXTIME(t.dt_approve) AS dt_approve,
                c.status AS c_status,
                (SELECT fc.id FROM FundCar fc WHERE fc.vin = c.vin) AS fund_car_id
              FROM Profile p
                JOIN Transaction t
                JOIN User u
                JOIN Car c
                JOIN RefCarCat rcc
              WHERE
                p.id = t.profile_id AND
                p.type = 'CAR' AND
                c.profile_id = p.id AND
                c.ref_car_cat = rcc.id AND
                p.user_id = u.id AND
                t.approve = 'GLOBAL' AND
                t.ac_approve = 'SIGNED' AND
                c.ref_car_cat IN $category_id AND
                c.volume >= $volume_start AND 
                c.volume <= $volume_end AND 
                $st_type AND
                p.user_id = $user_id
              ORDER BY c.id ASC
            SQL;
            }

            if ($volume_start == 0 && !$volume_end) {
                $sql = <<<SQL
            SELECT
              c.id AS c_id,
              c.volume AS c_volume,
              c.vin AS c_vin,
              c.cost AS c_cost,
              rcc.name AS c_cat,
              FROM_UNIXTIME(c.date_import, "%d.%m.%Y") AS c_date_import,
              FROM_UNIXTIME(t.dt_approve) AS dt_approve,
              c.status AS c_status,
              (SELECT fc.id FROM FundCar fc WHERE fc.vin = c.vin) AS fund_car_id
            FROM Profile p
              JOIN Transaction t
              JOIN User u
              JOIN Car c
              JOIN RefCarCat rcc
            WHERE
              p.id = t.profile_id AND
              p.type = 'CAR' AND
              c.profile_id = p.id AND
              c.ref_car_cat = rcc.id AND
              p.user_id = u.id AND
              t.approve = 'GLOBAL' AND
              t.ac_approve = 'SIGNED' AND
              c.ref_car_cat IN $category_id AND
              c.volume = $volume_start AND 
              $st_type AND
              p.user_id = $user_id
            ORDER BY c.id ASC
          SQL;
            }

            if (!empty($sql)) {
                $query = $this->modelsManager->createQuery($sql);
                $cars = $query->execute();
            }
        }

        if (count($cars) > 0) {
            $count = 0;
            foreach ($cars as $c) {
                if ($c->c_status == 'CANCELLED') continue;
                if ($c->fund_car_id > 0) continue;

                $data[] = [
                    'id' => $c->c_id,
                    'volume' => $c->c_volume,
                    'vin' => $c->c_vin,
                    'ref_car_cat' => $c->c_cat,
                    'amount' => __money($c->c_cost),
                    'date_import' => $c->c_date_import,
                    'date_approve' => $c->dt_approve,
                    'fund_car_id' => $c->fund_car_id,
                    'status' => $c->c_status
                ];

                $count++;
                if ($count >= $max) {
                    break; // достигли 1000 подходящих записей
                }
            }
        }

        return $data;
    }

    public function getGoodsTotalWeight(FundProfile $fundProfile, RefFund $refFund): float
    {
        $auth = User::getUserBySession();
        $year = date('Y', $fundProfile->created);

        // если created в БД как UNIX timestamp (INT)
        $startOfYear = strtotime("$year-01-01 00:00:00");
        $endOfYear   = strtotime("$year-12-31 23:59:59");

        $fundProfiles = FundProfile::find([
            'conditions' => 'user_id = :user_id:
                             AND entity_type = :etype:
                             AND ref_fund_key = :ref_fund_key:
                             AND created >= :startOfYear:
                             AND created <= :endOfYear:',
            'bind' => [
                'user_id'      => $auth->id,      // проверь, что в БД реально хранится id, а не idnum
                'ref_fund_key' => $refFund->key,
                'startOfYear'  => $startOfYear,
                'endOfYear'    => $endOfYear,
                'etype'        => 'GOODS',
            ],
            'columns' => 'id',
        ]);

        $rows = $fundProfiles->toArray();
        $ids  = array_column($rows, 'id');

        if (empty($ids)) {
            return 0.0;
        }

        $sumWeight = FundGoods::sum([
            'column'     => 'weight',
            'conditions' => 'fund_id IN ({fund_ids:array}) AND date_produce >= :prod_start: AND date_produce <= :prod_end:',
            'bind'       => [
                'prod_start' => $refFund->prod_start,
                'prod_end' =>  $refFund->prod_end,
                'fund_ids' => $ids,
            ],
        ]);

        return (float)$sumWeight ?? 0;
    }

    public function getCarTotalCount($fundProfile, $car, $refFund)
    {
        $auth = User::getUserBySession();
        $year = date('Y', $fundProfile->created);
        $startOfYear = strtotime("$year-01-01 00:00:00");
        $endOfYear = strtotime("$year-12-31 23:59:59");
        $start = strtotime($year.'-01-01 00:00:00');

        if (!$refFund) {
            return 0; // или выбросить исключение, если refFund обязателен
        }

        $startOfYear = $refFund->prod_start;
        $endOfYear = $refFund->prod_end;

        // Получаем все fundProfiles за один запрос
        $fundProfiles = FundProfile::find([
            'conditions' => 'user_id = :user_id: AND created >= :created: AND entity_type = "CAR" AND ref_fund_key = :ref_fund_key: AND period_start >= :startOfYear: AND period_end <= :endOfYear:',
            'bind' => [
                'user_id' => $auth->id,
                'ref_fund_key' => $refFund->key,
                'startOfYear' => $startOfYear,
                'endOfYear' => $endOfYear,
                'created' => $start
            ],
            'columns' => 'id'
        ]);

        $ids = array_column($fundProfiles->toArray(), 'id');

        $totalCount = !empty($ids) ? FundCar::count([
            'column' => 'id',
            'conditions' => 'fund_id IN ({fund_ids:array})',
            'bind' => [
                'fund_ids' => $ids
            ]
        ]) : 0;

        if ($car) {
            $totalCount += count($car);
        }

        return $totalCount;
    }


    public function getCarLimitCount($fund): float
    {
        $auth = User::getUserBySession();
        $year = date("Y", $fund->created);

        if ($fund->ref_fund_key != NULL) {
            $rf = RefFund::findFirst([
                "conditions" => "key = :key: AND idnum = :idnum: AND year = :year: AND entity_type = 'CAR'",
                "bind" => [
                    "key" => $fund->ref_fund_key,
                    "idnum" => $auth->idnum,
                    "year" => $year,
                ],
                'order' => 'id DESC'
            ]);
            if($rf) {
                return $rf->value;
            }
            return 0;
        }

        return 0;
    }

    public function calculationFundAmount(FundProfile $f)
    {
        $additionals = $f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c;

        if ($f->entity_type == 'CAR') {
            $fund_car = FundCar::sum([
                'column' => 'cost',
                'conditions' => 'fund_id = ' . $f->id
            ]);

            if (!$fund_car) {
                $f->amount = $additionals;
            } else {
                $f->amount = $fund_car;
            }
        } else if ($f->entity_type == 'GOODS') {
            $fundGoods = FundGoods::sum([
                'column' => 'cost',
                'conditions' => 'fund_id = ' . $f->id
            ]);

            if (!$fundGoods) {
                $f->amount = $additionals;
            } else {
                $f->amount = $fundGoods;
            }
        }

        $f->save();
    }
}
