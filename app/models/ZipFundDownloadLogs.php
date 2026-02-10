<?php

class ZipFundDownloadLogs extends \Phalcon\Mvc\Model
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
    public $fund_profile_id;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var string
     */
    public $files;

    /**
     *
     * @var integer
     */
    public $file_count;

    /**
     *
     * @var integer
     */
    public $dt;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("zip_fund_download_logs");
        $this->belongsTo('fund_profile_id', 'FundProfile', 'id', ['alias' => 'FundProfile']);
        $this->belongsTo('user_id', 'User', 'id', ['alias' => 'User']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ZipFundDownloadLogs[]|ZipFundDownloadLogs|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ZipFundDownloadLogs|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
