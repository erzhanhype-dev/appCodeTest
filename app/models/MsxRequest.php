<?php

class MsxRequest extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $req_value;

    /**
     *
     * @var string
     */
    public $req_type;

    /**
     *
     * @var integer
     */
    public $req_time;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $execution_time;

    /**
     *
     * @var string
     */
    public $request;

    /**
     *
     * @var string
     */
    public $response;

    /**
     *
     * @var string
     */
    public $message_id;

    /**
     *
     * @var string
     */
    public $session_id;

    /**
     *
     * @var string
     */
    public $response_date;

    /**
     *
     * @var string
     */
    public $code;

    /**
     *
     * @var string
     */
    public $message;

    /**
     *
     * @var string
     */
    public $response_success;

    /**
     *
     * @var string
     */
    public $response_type;

    /**
     *
     * @var string
     */
    public $payload_status;

    /**
     *
     * @var string
     */
    public $payload_message_ru;

    /**
     *
     * @var string
     */
    public $payload_message_kz;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var integer
     */
    public $created;

    /**
     *
     * @var string
     */
    public $comment;

    /**
     *
     * @var string
     */
    public $result;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("msx_request");
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