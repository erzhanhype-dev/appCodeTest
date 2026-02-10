<?php

namespace App\Repositories;

use OffsetFund;
use OffsetFundCar;
use RefFund;
use RefFundKeys;
use User;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Paginator\Adapter\QueryBuilder;

class OffsetFundRepository
{
    protected ModelsManager $modelsManager;

    public function __construct(ModelsManager $modelsManager)
    {
        $this->modelsManager = $modelsManager;
    }

    /**
     * Поиск лимита по ключу и ИИН пользователя
     */
    public function findLimit(int $refFundKeyId, string $idnum): ?RefFund
    {
        $refFundKey = RefFundKeys::findFirstById($refFundKeyId);
        if (!$refFundKey) {
            return null;
        }

        return RefFund::findFirst([
            "conditions" => "idnum = :idnum: AND year = :year: AND key = :search:",
            "bind" => [
                "idnum" => $idnum,
                "year" => date('Y'),
                "search" => $refFundKey->name
            ]
        ]);
    }

    /**
     * Сумма total_value по ключу фонда и пользователю.
     */
    public function getUsedLimitValue(int $refFundKeyId, int $userId): float
    {
        $result = OffsetFund::sum([
            'column' => 'total_value',
            'conditions' => 'ref_fund_key_id = :k: AND user_id = :u:',
            'bind' => ['k' => $refFundKeyId, 'u' => $userId],
        ]);

        return (float) $result;
    }


    /**
     * Сумма объема ТС по заявке взаимозачета.
     */
    public function getUsedCarVolume(int $offsetFundId, ?int $excludeCarId = null): float
    {
        $params = [
            'column' => 'volume',
            'conditions' => 'offset_fund_id = :offset_fund_id:',
            'bind' => [
                'offset_fund_id' => $offsetFundId,
            ],
        ];

        if ($excludeCarId !== null) {
            $params['conditions'] .= ' AND id != :car_id:';
            $params['bind']['car_id'] = $excludeCarId;
        }

        $result = \OffsetFundCar::sum($params);

        return (float) $result;
    }

    /**
     * Поиск списка заявок с фильтрацией (для indexAction)
     */
    public function search(array $filters, int $limit, int $page)
    {
        $builder = $this->modelsManager->createBuilder()
            ->from(['o' => OffsetFund::class])
            ->leftJoin(User::class, 'u.id = o.user_id', 'u')
            ->leftJoin(RefFundKeys::class, 'k.id = o.ref_fund_key_id', 'k')
            ->columns([
                'o.id as id',
                'o.created_at as created_at',
                'o.sent_at as sent_at',
                'o.type as type',
                'o.status as status',
                'o.entity_type as entity_type',
                'o.total_value as total_value',
                'o.amount as amount',
                'o.created_at as created_at',
                'u.idnum as user_idnum',
                'u.fio as user_fio',
                'k.description as key_name'
            ])
            ->orderBy('o.sent_at DESC, o.created_at DESC');

        // Применение фильтров
        if (!empty($filters['id'])) {
            $builder->andWhere("o.id = :id:", ["id" => $filters['id']]);
        }

        if (!empty($filters['status'])) {
            $builder->andWhere("o.status = :status:", ["status" => $filters['status']]);
        }

        if (!empty($filters['search'])) {
            $builder->andWhere("u.bin LIKE :s: OR u.fio LIKE :s:", ["s" => "%{$filters['search']}%"]);
        }

        if (!empty($filters['year'])) {
            $builder->andWhere("YEAR(FROM_UNIXTIME(o.created_at)) = :year:", ["year" => $filters['year']]);
        }

        return new QueryBuilder([
            "builder" => $builder,
            "limit" => $limit,
            "page" => $page,
        ]);
    }
}
