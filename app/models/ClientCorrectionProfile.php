<?php

class ClientCorrectionProfile extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $created;

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     *
     * @var integer
     */
    public $object_id;

   /**
    *
    * @var integer
    */
   public $initiator_id;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var string
     */
    public $type;

    /**
     *
     * @var string
     */
    public $action;

    /**
     *
     * @var integer
     */
    public $action_dt;

    /**
     *
     * @var string
     */
    public $status;

    /**
     *
     * @var string
     */
    public $hash;

    /**
     *
     * @var string
     */
    public $sign;

    /**
     *
     * @var integer
     */
    public $moderator_id;

    /**
     *
     * @var integer
     */
    public $accountant_id;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("client_correction_profile");
    }

    public static function checkCurrentCorrection($object_id, $type) {

        $correction = self::findFirst(array(
            "object_id = :object_id: AND type = :type: AND status IN ('SEND_TO_MODERATOR', 'SENT_TO_ACCOUNTANT')",
            "bind" => array(
                "object_id" => $object_id,
                "type" => $type
            )));

        return ($correction) ?: 0;
    }

    public static function checkCurrentCorrectionByProfileId($profile_id, $type) {

        $correction = self::findFirst(array(
            "profile_id = :profile_id: AND type = :type: AND status IN ('SEND_TO_MODERATOR', 'SENT_TO_ACCOUNTANT')",
            "bind" => array(
                "profile_id" => $profile_id,
                "type" => $type
            )));

        return ($correction) ?: 0;
    }
}
