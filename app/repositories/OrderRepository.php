<?php
namespace App\Repositories;

use App\Services\Order\Dto\OrderFilterDTO;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use Profile;
use Transaction;

final class   OrderRepository extends Injectable
{
    public function getFilteredOrders(OrderFilterDTO $filters, int $userId): array
    {
        $builder = $this->createBaseBuilder($userId);
        $this->applyFilters($builder, $filters);

        $paginator = new QueryBuilderPaginator([
            'builder' => $builder,
            'limit' => $filters->limit,
            'page' => $filters->page,
        ]);

        $result = $paginator->paginate();

        return [
            'items' => $result->items,
            'pagination' => $result,
        ];
    }

    private function createBaseBuilder(int $userId): Builder
    {
        return $this->modelsManager->createBuilder()
            ->columns([
                'p_id'         => 'p.id',
                'p_name'       => 'p.name',
                'p_type'       => 'p.type',
                'p_created'    => 'p.created',
                'p_blocked'    => 'p.blocked',
                'p_agent_name' => 'p.agent_name',
                'p_sign_date'  => 'p.sign_date',

                'tr_id'      => 'tr.id',
                'tr_status'  => 'tr.status',
                'tr_amount'  => 'tr.amount',
                'tr_approve' => 'tr.approve',
                'tr_dt_sent' => 'tr.md_dt_sent',
            ])
            ->from(['p' => Profile::class])
            ->leftJoin(
                Transaction::class,
                "tr.profile_id = p.id AND tr.id = (
                SELECT t2.id
                FROM transaction t2
                WHERE t2.profile_id = p.id
                ORDER BY t2.md_dt_sent DESC, t2.id DESC
                LIMIT 1
            )",
                'tr'
            )
            ->where('p.user_id = :uid:', ['uid' => $userId])
            ->orderBy('p.id DESC');
    }

    private function applyFilters(Builder $builder, OrderFilterDTO $filters): void
    {
        if ($filters->profileId) {
            $builder->andWhere('p.id = :pid:', ['pid' => $filters->profileId]);
        }

        if (!empty($filters->types)) {
            $builder->inWhere('p.type', $filters->types);
        }

        if (!empty($filters->statuses)) {
            $builder->inWhere('tr.approve', $filters->statuses);
        }

        if ($filters->fromDate && $filters->toDate) {
            $builder->betweenWhere('p.created', $filters->fromDate, $filters->toDate);
        } elseif ($filters->fromDate) {
            $builder->andWhere('p.created >= :from:', ['from' => $filters->fromDate]);
        } elseif ($filters->toDate) {
            $builder->andWhere('p.created <= :to:', ['to' => $filters->toDate]);
        }
    }
}