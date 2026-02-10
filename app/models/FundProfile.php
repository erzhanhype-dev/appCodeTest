<?php

use App\Services\Mail\MailService;

class FundProfile extends \Phalcon\Mvc\Model
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
    public $number;

    /**
     *
     * @var string
     */
    public $type;

    /**
     *
     * @var integer
     */
    public $created;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var integer
     */
    public $ref_country_id;

    /**
     *
     * @var integer
     */
    public $period_start;

    /**
     *
     * @var integer
     */
    public $period_end;

    /**
     *
     * @var double
     */
    public $w_a;

    /**
     *
     * @var double
     */
    public $w_b;

    /**
     *
     * @var double
     */
    public $w_c;

    /**
     *
     * @var double
     */
    public $w_d;

    /**
     *
     * @var double
     */
    public $e_a;

    /**
     *
     * @var double
     */
    public $r_a;

    /**
     *
     * @var double
     */
    public $r_b;

    /**
     *
     * @var double
     */
    public $r_c;

    /**
     *
     * @var double
     */
    public $tc_a;

    /**
     *
     * @var double
     */
    public $tc_b;

    /**
     *
     * @var double
     */
    public $tc_c;

    /**
     *
     * @var double
     */
    public $tt_a;

    /**
     *
     * @var double
     */
    public $tt_b;

    /**
     *
     * @var double
     */
    public $tt_c;

    /**
     *
     * @var double
     */
    public $amount;

    /**
     *
     * @var double
     */
    public $old_amount;

    /**
     *
     * @var integer
     */
    public $md_dt_sent;

    /**
     *
     * @var integer
     */
    public $dt_approve;

    /**
     *
     * @var string
     */
    public $approve;

    /**
     *
     * @var integer
     */
    public $blocked;

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
    public $signed_by;

    /**
     *
     * @var string
     */
    public $sign_acc;

    /**
     *
     * @var string
     */
    public $sign_hod;

    /**
     *
     * @var string
     */
    public $sign_fad;

    /**
     *
     * @var string
     */
    public $sign_hop;

    /**
     *
     * @var string
     */
    public $sign_hof;

    /**
     *
     * @var string
     */
    public $reference;

    /**
     *
     * @var double
     */
    public $sum_before;

    /**
     *
     * @var string
     */
    public $ref_fund_key;

    /**
     * @var string
     */
    public $entity_type;

    /**
     *
     * @var integer
     */
    public $paid_dt;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_profile");
        $this->belongsTo('ref_country_id', 'RefCountry', 'id', ['alias' => 'RefCountry']);
    }


    /**
     * @throws Exception
     */
    public static function sendNotification($email, $fund_id, $fund_number, $last_signed_at_dt): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

            // 2. Регулярное выражение специально для домена recycle.kz
            // i — регистронезависимость
            $pattern = '/^.+@recycle\.kz$/i';

            if (preg_match($pattern, $email)) {
                $mail_log_txt = APP_PATH . '/storage/logs/system/mail_logs.txt';

                if (!file_exists($mail_log_txt)) {
                    touch($mail_log_txt);
                }

                $last_signed_at_dt = date('Y-m-d H:i:s', $last_signed_at_dt);
                $subject = "На подписании $fund_number";
                $url = HTTP_ADDRESS . '/moderator_fund/view/' . $fund_id;

                $body = <<<HTML
                <br>
                <p>
                    На подпись $last_signed_at_dt поступило заявление о предоставлении финансирования 
                    <a href="$url"> $fund_number </a>.
                </p>
                <p>Данное письмо направлено автоматически.</p>
                <br>
                <br>
                <p>С уважением, <br />
                команда recycle.kz</p>
            HTML;

                $send_mail = (new MailService())->sendMail($email, $subject, $body);

                if ($send_mail) {
                    $status = 'SUCCEES';
                } else {
                    $status = 'FAILED';
                }


                $log_content = date('Y-m-d H:i:s') . " || FUND_PROFILE || FundId: $fund_id || Reciever: $email || Status: $status";
                file_put_contents($mail_log_txt, $log_content . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        }
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }
}
