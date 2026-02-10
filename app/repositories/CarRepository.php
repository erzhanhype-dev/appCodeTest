<?php

namespace App\Repositories;

use Car;
use CarHistories;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\Model\Query\BuilderInterface;
use Profile;
use RefCarCat;
use RefCarType;
use RefCountry;
use Transaction;

final class CarRepository extends Injectable
{
    /**
     * Поиск по ID
     */
    public function findById(int $id): ?Car
    {
        return Car::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => $id]
        ]);
    }

    /**
     * Все машины конкретного профиля
     */
    public function findByProfileId(int $profileId): ResultsetInterface
    {
        return $this->modelsManager->createBuilder()
            ->from(Car::class)
            ->where('profile_id = :pid:', ['pid' => $profileId])
            ->orderBy('id DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Поиск машин для супермодератора (проверка связи через профиль)
     */
    public function findBySuperModerator(int $profileId, int $moderatorId): ResultsetInterface
    {
        return $this->modelsManager->createBuilder()
            ->from(['c' => Car::class])
            ->innerJoin(Profile::class, 'p.id = c.profile_id', 'p')
            ->where('c.profile_id = :pid:', ['pid' => $profileId])
            ->andWhere('p.moderator_id = :mid:', ['mid' => $moderatorId])
            ->orderBy('c.id DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * История машин профиля (переписано с PHQL на Builder для единообразия)
     */
    public function findHistoryByProfileId(int $profileId): ResultsetInterface
    {
        return $this->modelsManager->createBuilder()
            ->columns([
                'c.car_id AS c_id', 'c.volume AS c_volume', 'c.vin AS c_vin',
                'c.year AS c_year', 'c.action AS c_action', 'c.date_import AS c_date_import',
                'c.vehicle_type AS c_vehicle_type', 't.id AS c_type', 'cc.name AS c_car_cat',
                'country.name AS c_country', 'countryImport.name AS c_country_import'
            ])
            ->from(['c' => CarHistories::class])
            ->innerJoin(Profile::class, 'p.id = c.profile_id', 'p')
            ->innerJoin(RefCarCat::class, 'cc.id = c.ref_car_cat', 'cc')
            ->innerJoin(RefCountry::class, 'country.id = c.ref_country', 'country')
            ->innerJoin(RefCountry::class, 'countryImport.id = c.ref_country_import', 'countryImport')
            ->innerJoin(RefCarType::class, 't.id = c.ref_car_type_id', 't')
            ->where('p.id = :pid:', ['pid' => $profileId])
            ->orderBy('c.id DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Проверка существования VIN (с исключением текущего ID)
     */
    public function existsByVin(string $vin, ?int $excludeId = null): bool
    {
        $builder = $this->modelsManager->createBuilder()
            ->from(Car::class)
            ->where('vin = :vin:', ['vin' => $vin]);

        if ($excludeId) {
            $builder->andWhere('id != :id:', ['id' => $excludeId]);
        }

        return $builder->getQuery()->execute()->count() > 0;
    }

    /**
     * Количество машин профиля
     */
    public function countByProfileId(int $profileId): int
    {
        $result = $this->modelsManager->createBuilder()
            ->columns('COUNT(*) as count')
            ->from(Car::class)
            ->where('profile_id = :pid:', ['pid' => $profileId])
            ->getQuery()
            ->getSingleResult();

        return (int) $result['count'];
    }

    /**
     * Основной строитель запроса для списков (GridView/Paginator)
     */
    public function getCarBuilder(int $userId): BuilderInterface
    {
        return $this->modelsManager->createBuilder()
            ->columns([
                'c.id AS car_id', 'c.volume', 'c.vin', 'c.year', 'c.cost',
                'cc.name AS category', 'c.date_import',
                'country.name AS country', 'countryImport.name AS country_import',
                't.id AS type_id', 'p.id AS profile_id',
                'tr.id AS transaction_id', 'tr.status AS transaction_status',
                'tr.approve AS transaction_approve', 'p.blocked AS profile_blocked'
            ])
            ->from(['c' => Car::class])
            ->innerJoin(Profile::class, 'p.id = c.profile_id', 'p')
            ->innerJoin(RefCountry::class, 'country.id = c.ref_country', 'country')
            ->innerJoin(RefCountry::class, 'countryImport.id = c.ref_country_import', 'countryImport')
            ->innerJoin(RefCarType::class, 't.id = c.ref_car_type_id', 't')
            ->innerJoin(Transaction::class, 'tr.profile_id = p.id', 'tr')
            ->innerJoin(RefCarCat::class, 'cc.id = c.ref_car_cat', 'cc')
            ->where('p.user_id = :userId:', ['userId' => $userId])
            ->groupBy('c.id')
            ->orderBy('c.id DESC');
    }
}