<?php

use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\InclusionIn;
use Phalcon\Filter\Validation\Validator\StringLength;
use Phalcon\Mvc\Model;

class Profile extends Model
{
    public const string TYPE_CAR = 'CAR';
    public const string TYPE_GOODS = 'GOODS';
    public const string AGENT_STATUS_IMPORTER = 'IMPORTER';
    public const string AGENT_STATUS_VENDOR = 'VENDOR';

    public ?int $id = null;
    public ?int $created = null;
    public string $name;
    public int $user_id;
    public $status;
    public $executor_uid;
    public ?int $reason_id = null;
    public ?int $initiator_id = null;
    public string $type;
    public ?int $agent_type = null;
    public ?string $agent_name = null;
    public ?string $agent_iin = null;
    public ?string $agent_city = null;
    public ?string $agent_address = null;
    public ?string $agent_phone = null;
    public ?string $agent_sign = null;
    public ?string $agent_iban = null;
    public ?int $agent_bank = null;
    public ?string $agent_status = self::AGENT_STATUS_IMPORTER;
    public int $blocked = 0;
    public ?int $agent_size = null;
    public ?string $hash = null;
    public ?string $sign = null;
    public ?int $sign_date = null;
    public ?int $moderator_id = null;
    public ?string $comment = null;
    public ?int $international_transporter = null;
    public ?string $int_tr_app_sign = null;

    public function initialize(): void
    {
        $this->setSchema('recycle');
        $this->setSource('profile');

        // для корректной работы hasChanged()
        $this->keepSnapshots(true);
        $this->useDynamicUpdate(true);

        $this->belongsTo(
            'user_id',
            User::class,
            'id',
            [
                'alias' => 'user',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'id',
            Transaction::class,
            'profile_id',
            [
                'alias' => 'tr',
                'reusable' => true,
            ]
        );

        $this->hasMany(
            'id',
            Car::class,
            'profile_id',
            [
                'alias' => 'cars',
                'reusable' => true,
            ]
        );

        $this->hasMany(
            'id',
            ProfileLogs::class,
            'profile_id',
            [
                'alias' => 'logs',
                'reusable' => true,
            ]
        );
    }

    public function validation(): bool
    {
        $validator = new Validation();

        $validator->add(
            'type',
            new InclusionIn([
                'domain' => [self::TYPE_CAR, self::TYPE_GOODS],
                'message' => 'Type must be CAR, GOODS or KPP',
            ])
        );

        $validator->add(
            'agent_status',
            new InclusionIn([
                'domain' => [
                    self::AGENT_STATUS_IMPORTER,
                    self::AGENT_STATUS_VENDOR,
                ],
                'message' => 'Invalid agent status',
                'allowEmpty' => true,
            ])
        );

        $validator->add(
            'name',
            new StringLength([
                'max' => 255,
                'min' => 1,
                'messageMaximum' => 'Name is too long',
                'messageMinimum' => 'Name is too short',
            ])
        );

        $validator->add(
            'agent_iin',
            new StringLength([
                'max' => 12,
                'min' => 12,
                'message' => 'IIN must be 12 characters',
                'allowEmpty' => true,
            ])
        );

        return $this->validate($validator);
    }

    public static function getAllowedTypes(): array
    {
        return [self::TYPE_CAR, self::TYPE_GOODS];
    }

    public static function getAgentStatuses(): array
    {
        return [
            self::AGENT_STATUS_IMPORTER,
            self::AGENT_STATUS_VENDOR,
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked === 1;
    }

    public function columnMap(): array
    {
        return [
            'id' => 'id',
            'created' => 'created',
            'name' => 'name',
            'user_id' => 'user_id',
            'reason_id' => 'reason_id',
            'initiator_id' => 'initiator_id',
            'type' => 'type',
            'status' => 'status',
            'executor_uid' => 'executor_uid',
            'agent_type' => 'agent_type',
            'agent_name' => 'agent_name',
            'agent_iin' => 'agent_iin',
            'agent_city' => 'agent_city',
            'agent_address' => 'agent_address',
            'agent_phone' => 'agent_phone',
            'agent_sign' => 'agent_sign',
            'agent_iban' => 'agent_iban',
            'agent_bank' => 'agent_bank',
            'agent_status' => 'agent_status',
            'blocked' => 'blocked',
            'agent_size' => 'agent_size',
            'hash' => 'hash',
            'sign' => 'sign',
            'sign_date' => 'sign_date',
            'moderator_id' => 'moderator_id',
            'comment' => 'comment',
            'international_transporter' => 'international_transporter',
            'int_tr_app_sign' => 'int_tr_app_sign',
        ];
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
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
}
