<?php

use Phalcon\Mvc\Model;

/**
 * Модель заявок на финансирование методом взаимозачета
 * @property User $Sender
 * @property User $Approver
 * @property RefFundKeys $FundKey  <-- Добавить эту строку
 */

class OffsetFund extends Model
{
    // Статусы заявки
    public const string STATUS_NEW = 'NEW';
    public const string STATUS_NEUTRAL = 'NEUTRAL';
    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_CERT_FORMATION = 'CERT_FORMATION';
    public const string STATUS_CERT_RECEIVED = 'CERT_RECEIVED';
    public const string STATUS_DECLINED = 'DECLINED';
    public const string STATUS_CANCELLED = 'CANCELLED';
    public const string TYPE_INTERNAL = 'INTERNAL'; // Внутренний
    public const string TYPE_EXPORT   = 'EXPORT';   // Экспорт

    // Разрешенные коды ТН ВЭД для товаров (Автокомпоненты)
    public const array GOODS_TN_CODES = ['401110000', '4011800000', '4011900000', '401120'];

    public ?int $id = 0;
    public int $user_id;             // Id пользователя
    public ?int $approved_by_id = 0;     // Id пользователя
    public string $amount = '0.00';           // DECIMAL(12,2) сумма
    public string $total_value = '0.000';     // DECIMAL(12,2) количество/вес
    public string $type;             // Тип заявки
    public string $entity_type;      // Объект финансирования
    public string $status;
    public int $is_funded;           // Флаг: был на финансировании
    public int $ref_fund_key_id;     // Справочник
    public int $period_start_at;     // Начало периода финансирования
    public int $period_end_at;       // Конец периода финансирования
    public int $sent_at = 0;             // Unix timestamp
    public int $created_at;          // Unix timestamp

    public function initialize(): void
    {
        $this->setSource("offset_funds");

        $this->belongsTo(
            'user_id',
            User::class,
            'id',
            [
                'alias'    => 'user',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'approved_by_id',
            User::class,
            'id',
            [
                'alias'    => 'moderator',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'ref_fund_key_id',
            RefFundKeys::class,
            'id',
            [
                'alias'    => 'ref_fund_key',
            ]
        );

        $this->hasMany(
            'id',
            OffsetFundCar::class,
            'offset_fund_id',
            [
                'alias'    => 'cars',
            ]
        );

        $this->hasMany(
            'id',
            OffsetFundFile::class,
            'offset_fund_id',
            [
                'alias'    => 'files',
            ]
        );

        $this->setup(['notNullValidations' => false]);
    }

    public function beforeCreate(): void
    {
        if (empty($this->created_at)) {
            $this->created_at = time();
        }
    }

    public function beforeSave(): void
    {
        if (!empty($this->period_start_at)) {
            $this->period_start_at = (int) strtotime(date('Y-m-d 00:00:00', $this->period_start_at));
        }
        if (!empty($this->period_end_at)) {
            $this->period_end_at = (int) strtotime(date('Y-m-d 00:00:00', $this->period_end_at));
        }
    }

    public function columnMap(): array
    {
        return [
            'id' => 'id',
            'user_id' => 'user_id',
            'approved_by_id' => 'approved_by_id',
            'amount' => 'amount',
            'total_value' => 'total_value',
            'type' => 'type',
            'entity_type' => 'entity_type',
            'status' => 'status',
            'is_funded' => 'is_funded',
            'ref_fund_key_id' => 'ref_fund_key_id',
            'period_start_at' => 'period_start_at',
            'period_end_at' => 'period_end_at',
            'sent_at' => 'sent_at',
            'created_at' => 'created_at'
        ];
    }

    /**
     * Возвращает массив для фильтра в интерфейсе
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NEW => 'Новая заявка',
            self::STATUS_NEUTRAL => 'Нейтральная',
            self::STATUS_PENDING => 'На рассмотрении',
            self::STATUS_CERT_FORMATION => 'Формирование сертификата',
            self::STATUS_CERT_RECEIVED => 'Сертификат получен',
            self::STATUS_DECLINED => 'Отклонено',
            self::STATUS_CANCELLED => 'Аннулировано',
        ];
    }

    public function getStatusName(): string
    {
        return self::getStatusList()[$this->status] ?? $this->status;
    }
}