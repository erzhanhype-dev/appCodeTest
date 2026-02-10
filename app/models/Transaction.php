<?php

use Phalcon\Mvc\Model;

class Transaction extends Model
{

    // Статусы транзакции
    public const string STATUS_NOT_PAID = 'NOT_PAID';
    public const string STATUS_PAID = 'PAID';

    // Статусы одобрения (approve)
    public const string APPROVE_NEUTRAL = 'NEUTRAL';
    public const string APPROVE_GLOBAL = 'GLOBAL';
    public const string APPROVE_APPROVED = 'APPROVE';
    public const string APPROVE_DECLINED = 'DECLINED';

    // Статусы подписи бухгалтера (ac_approve)
    public const string AC_APPROVE_NOT_SIGNED = 'NOT_SIGNED';
    public const string AC_APPROVE_SIGNED = 'SIGNED';

    // Источники
    public const int SOURCE_INVOICE = -1;
    public const int SOURCE_VISA = -2;

    // Поля модели
    public ?int $id = null;
    public ?float $amount = null;
    public string $status = self::STATUS_NOT_PAID;
    public ?int $source = self::SOURCE_INVOICE;
    public ?int $profile_id = null;
    public string $approve = self::APPROVE_NEUTRAL;
    public int $dt_approve = 0;
    public string $ac_approve = self::AC_APPROVE_NOT_SIGNED;
    public int $ac_dt_approve = 0;
    public int $md_dt_sent = 0;
    public int $auto_detected = 0;


    public function initialize()
    {
        $this->setSource('transaction');
    }

    public static function findFirstByProfileId($profileId): ?self
    {
        return self::findFirst([
            'conditions' => 'profile_id = :profile_id:',
            'bind'       => [
                'profile_id' => $profileId,
            ],
        ]);
    }

    public static function findFirstById($id): ?self
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => [
                'id' => $id,
            ],
        ]);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }


}
