<?php
namespace App\Services\Halyk;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use CompanyDetail;
use Exception;
use HalykPayment;
use PersonDetail;
use Phalcon\Di\Injectable;
use Profile;
use ProfileExpense;
use ProfileLogs;
use SimpleXMLElement;
use SoapClient;
use SoapFault;
use Transaction;
use User;
use ZdBank;
use ZdBankIncome;
use ZdBankLogs;
use ZdBankTransaction;

class HalykOnlinebankService extends Injectable
{
    use LogTrait;
    /**
     * @throws AppException
     */
    public function run()
    {
        $previousDayTimestamp = strtotime("-1 day");
        $dates = [
            [
                'day' => date("d"),
                'month' => date("m"),
                'year' => date("Y")
            ],
            [
                'day' => date("d", $previousDayTimestamp),
                'month' => date("m", $previousDayTimestamp),
                'year' => date("Y", $previousDayTimestamp)
            ],
        ];

        foreach ($dates as $value) {
            $day = $value['day'];
            $month = $value['month'];
            $year = $value['year'];

            // Validate date. Not really necessary here since we directly get it from system time.
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                throw new AppException("Invalid date format");
            }

            $wsdl = getenv('HALYK_ONLINEBANK_URL');
            $passphrase = getenv('HALYK_ONLINEBANK_KEYPASS');
            $cert_path = getenv('HALYK_ONLINEBANK_CERT_PATH');

            if (!$passphrase || !$cert_path) {
                throw new AppException('Missing configuration for HALYK credentials');
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'timeout' => 30
                ]
            ]);

            try {
                $w = new SoapClient($wsdl, [
                    'stream_context' => $context,
                    'trace' => true
                ]);
            } catch (Exception $e) {
                throw new AppException('Ошибка создания SOAP-клиента');
            }

            $requestXml = new SimpleXMLElement("<statementsRequest></statementsRequest>");
            $requestXml->addChild('date-begin', "$year-$month-$day" . 'T00:00:00+06:00');
            $requestXml->addChild('date-end', "$year-$month-$day" . 'T23:59:59+06:00');
            $requestXml->addChild('account-iban', '');

            $request = $requestXml->asXML();

            if (!$request) {
                throw new AppException('Ошибка создания XML-запроса');
            }

            $pkcs12 = file_get_contents($cert_path);
            if ($pkcs12 === false) {
                throw new AppException('Не удалось прочитать файл сертификата: ' . $cert_path);
            }

            $private_key = [];
            if (!openssl_pkcs12_read($pkcs12, $private_key, $passphrase)) {
                throw new AppException('Ошибка чтения PKCS12 сертификата. Проверьте правильность пути и пароль.');
            }

            $cert = $private_key['cert'];
            $pkey = $private_key['pkey'];

            $certificate64 = base64_encode($cert);
            $request64 = base64_encode($request);

            $signature = 'NOT_SIGNED_YET';

            if (!openssl_sign($request, $signature, $pkey)) {
                throw new AppException('Ошибка подписи запроса. Проверьте корректность ключа.');
            }
            $signature64 = base64_encode($signature);

            try {
                $response = $w->getStatementsList([
                    'certificate' => $certificate64,
                    'xmlBody' => $request64,
                    'signature' => $signature64
                ]);

                $list = json_decode(json_encode($response), true); // stdClass → JSON → array
                if (!empty($list['statements-list'])) {
                    $this->store($response->{'statements-list'});
                } else {
                    throw new AppException('Получен пустой список выписок. Проверьте параметры запроса.');
                }
            } catch (SoapFault $e) {
                throw new AppException('Ошибка SOAP');
            }
        }
    }

    private function store($list)
    {
        echo 'get: ' . count($list);
        $added = 0;

        if (count($list) > 0) {
            foreach ($list as $v) {
                $l = (array)$v;

                if (isset($l['id'])) {
                    $accn = htmlspecialchars($l['statement-reference'], ENT_QUOTES, 'UTF-8');
                    $iban_to = htmlspecialchars($l['account-recipient'], ENT_QUOTES, 'UTF-8');
                    $iban_from = htmlspecialchars($l['account-sender'], ENT_QUOTES, 'UTF-8');
                    $amount = (float)$l['amount-recipient'];
                    $paid = strtotime($l['date-prov-recipient']);
                    $comment = htmlspecialchars(str_replace(array("\\", "'"), "", $l['payment-purpose']), ENT_QUOTES, 'UTF-8');
                    $name_sender = htmlspecialchars(str_replace("'", "", $l['name-sender']), ENT_QUOTES, 'UTF-8');
                    $name_recipient = htmlspecialchars(str_replace("'", "", $l['name-recipient']), ENT_QUOTES, 'UTF-8');
                    $rnn_sender = htmlspecialchars($l['rnn-sender'], ENT_QUOTES, 'UTF-8');
                    $rnn_recipient = $l['rnn-recipient'];

                    $zd_bank = ZdBank::findFirst([
                        'conditions' => 'account_num = :account_num:',
                        'bind' => ['account_num' => $accn],
                    ]);

                    $transaction_id = 0;
                    $name_sender = preg_replace('/&quot;/', '"', $name_sender);

                    if (!$zd_bank) {
                        $newRecord = new ZdBank();
                        $newRecord->account_num = $accn;
                        $newRecord->iban_to = $iban_to;
                        $newRecord->iban_from = $iban_from;
                        $newRecord->amount = $amount;
                        $newRecord->paid = $paid;
                        $newRecord->comment = $comment;
                        $newRecord->transaction_id = $transaction_id;
                        $newRecord->name_sender = $name_sender;
                        $newRecord->rnn_sender = $rnn_sender;

                        if ($newRecord->save()) {
                            $added++;
                        }
                    }

                    $order_number = null;
                    $identificator = 0;

                    $zdBankIncome = ZdBankIncome::findFirst([
                        'conditions' => 'statement_reference = :statement_reference:',
                        'bind' => ['statement_reference' => $accn],
                    ]);

                    if (!$zdBankIncome && $rnn_recipient == ZHASYL_DAMU_BIN) {
                        $reference = $this->extractReference($comment, $accn);
                        $halykPayment = HalykPayment::findFirst([
                            'conditions' => 'statement_reference = :reference:',
                            'bind' => ['reference' => $reference],
                        ]);

                        if ($halykPayment) {
                            $order_number = $halykPayment->order_number;
                            $identificator = 1;
                        }

                        $name_sender = preg_replace('/&quot;/', '"', $name_sender);
                        $name_recipient = preg_replace('/&quot;/', '"', $name_recipient);

                        $zdBankIncome = new ZdBankIncome();
                        $zdBankIncome->statement_reference = $accn;
                        $zdBankIncome->amount = $amount;
                        $zdBankIncome->name_sender = $name_sender;
                        $zdBankIncome->name_recipient = $name_recipient;
                        $zdBankIncome->rnn_sender = $rnn_sender;
                        $zdBankIncome->rnn_recipient = $rnn_recipient;
                        $zdBankIncome->account_sender = $iban_from;
                        $zdBankIncome->account_recipient = $iban_to;
                        $zdBankIncome->knp_code = htmlspecialchars($l['knp-code'], ENT_QUOTES, 'UTF-8');
                        $zdBankIncome->date_sender = strtotime($l['date-sender']);
                        $zdBankIncome->date_recipient = strtotime($l['date-recipient']);
                        $zdBankIncome->payment_purpose = $comment;
                        $zdBankIncome->mfo_sender = htmlspecialchars($l['mfo-sender'], ENT_QUOTES, 'UTF-8');
                        $zdBankIncome->mfo_recipient = htmlspecialchars($l['mfo-recipient'], ENT_QUOTES, 'UTF-8');
                        $zdBankIncome->currency = htmlspecialchars($l['currency'], ENT_QUOTES, 'UTF-8');
                        $zdBankIncome->identificator = $identificator;

                        $zdBankIncome->save();

                        if ($identificator == 1) {
                            $this->detectMultiPaymentIdent1($rnn_sender, $amount, $accn, $order_number);
                        } else {
                            $this->detectMultiPaymentIdent2($rnn_sender, $amount, $comment, $accn);
                        }
                    }
                }
            }
        }

        echo ' - add: ' . $added . ';';
    }

    private function extractReference($comment, $default)
    {
        if ($comment) {
            preg_match('/RefNo PGW (\d+)/', $comment, $matches);
            if (isset($matches[1])) {
                return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            }
        }
        return $default;
    }

    private function detectMultiPaymentIdent1($idnum, $amount, $accn, $order_number)
    {
        if ($order_number) {
            $cnt_to_bind = 0;
            $check_amount = 0;

            // Получение транзакции и профиля по номеру заявки

            // Повторная проверка транзакции
            $tr = Transaction::findFirst([
                'conditions' => 'profile_id = :order_number:',
                'bind' => ['order_number' => $order_number]
            ]);

            $profile = Profile::findFirst([
                'conditions' => 'id = :order_number:',
                'bind' => ['order_number' => $order_number]
            ]);

            if ($tr && $profile) {
                if (round($tr->amount, 2) > 0) {
                    $check_amount += $tr->amount;
                }
                $cnt_to_bind++;
            }

            if ($cnt_to_bind == 0 || $check_amount == 0) {
                return;
            }

            $detection = 0;

            if ($tr && $tr->status == 'NOT_PAID') {

                $detection++;
                // размер платежа тоже совпал
                if ($cnt_to_bind == 1 && $amount == $tr->amount) {
                    $detection++;
                }
                // размер платежей до этого (на предварительной проверке) совпал
                if ($cnt_to_bind > 1 && $amount == round($check_amount, 2)) {
                    $detection++;
                }

                if ($detection == 2) {
                    $bank = ZdBank::findFirst([
                        'conditions' => 'account_num = :accn:',
                        'bind' => ['accn' => $accn]
                    ]);

                    if ($bank) {
                        // Обновление статуса транзакции
                        $tr->status = 'PAID';
                        $tr->auto_detected = 2;
                        $tr->save();

                        // Логирование операции
                        $meta = "AUTO DETECTED PAYMENT: $idnum - $amount ($check_amount) - $order_number: to bind $cnt_to_bind (score $detection)";
                        $log = new ZdBankLogs();
                        $log->assign([
                            'login' => 'SYSTEM',
                            'action' => 'set_payment',
                            'payment_id' => $bank->id,
                            'profile_id' => $order_number,
                            'dt' => time(),
                            'meta' => $meta
                        ]);
                        $log->save();

                        // Обновление информации о банке
                        $bank->transactions = $order_number;
                        $bank->save();

                        // Создание записи в zd_bank_transaction
                        $bankTransaction = new ZdBankTransaction();
                        $bankTransaction->assign([
                            'zd_bank_id' => $bank->id,
                            'profile_id' => $order_number,
                            'transaction_id' => $tr->id,
                            'dt' => time(),
                        ]);
                        $bankTransaction->save();

                        // Автовыдача при одобрении заявки
                        if (in_array($tr->approve, ['APPROVE', 'CERT_FORMATION'])) {
                            $tr->approve = 'GLOBAL';
                            $tr->dt_approve = $tr->dt_approve == 0 ? time() : $tr->dt_approve;
                            $tr->ac_approve = 'SIGNED';
                            $tr->ac_dt_approve = time();
                            $tr->save();

                            // Проверка и обновление расходов профиля
                            $expense = ProfileExpense::findFirst([
                                'conditions' => 'profile_id = :profile_id:',
                                'bind' => ['profile_id' => $tr->profile_id]
                            ]);

                            if (!$expense) {
                                $expense = new ProfileExpense();
                                $expense->assign([
                                    'profile_id' => $tr->profile_id,
                                    'rnn_recipient' => $idnum,
                                    'date_modified' => time(),
                                    'amount' => $tr->amount
                                ]);
                                $expense->save();
                            } else {
                                $expense->date_modified = time();
                                $expense->amount = $tr->amount;
                                $expense->save();
                            }

                            // Логирование действий
                            $log1 = new ProfileLogs();
                            $log1->assign([
                                'login' => 'SYSTEM',
                                'action' => 'GLOBAL',
                                'profile_id' => $tr->profile_id,
                                'dt' => time(),
                                'meta_before' => '-',
                                'meta_after' => '-'
                            ]);
                            $log1->save();

                            $logString = json_encode($log1->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->writeLog($logString);

                            $log2 = new ProfileLogs();
                            $log2->assign([
                                'login' => 'SYSTEM', // дублируем необходимые поля
                                'action' => 'SIGNED', // меняем только action
                                'profile_id' => $tr->profile_id,
                                'dt' => time(),
                                'meta_before' => '-',
                                'meta_after' => '-'
                            ]);
                            $log2->save();

                            $logString = json_encode($log2->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->writeLog($logString);
                        }
                    }
                }
            }
        }
    }

    private function detectMultiPaymentIdent2($idnum, $amount, $comment, $accn)
    {
        // Ищем номера заявок в комментарии
        $order_number = $this->extractOrderNumbers($comment);

        if ($order_number) {
            $num_to_bind = [];
            $cnt_to_bind = 0;
            $check_amount = 0;
            $m = $order_number;

            if(count($m) > 0) {
                foreach ($m as $nn) {
                    $tr = Transaction::findFirst([
                        "conditions" => "profile_id = :profile_id:",
                        "bind" => [
                            "profile_id" => $nn
                        ],
                    ]);
                    $profile = Profile::findFirst([
                        'conditions' => 'id = :profile_id:',
                        'bind' => ['profile_id' => $nn],
                    ]);

                    if ($tr && $profile && round($tr->amount, 2) > 0) {
                        $num_to_bind[] = $nn;
                        $check_amount += $tr->amount;
                        $cnt_to_bind++;
                    }
                }
            }

            echo "num_to_bind:" .implode(',',$num_to_bind). "\n";
            echo "check_amount: $check_amount \n";
            echo "amount : $amount \n";
            echo "cnt_to_bind: $cnt_to_bind \n";

            if ($cnt_to_bind == 0 || $check_amount == 0) {
                return;
            }

            foreach ($num_to_bind as $num) {
                $detection = 0;

                $tr = Transaction::findFirst([
                    'conditions' => 'profile_id = :profile_id:',
                    'bind' => ['profile_id' => $num],
                ]);

                $profile = Profile::findFirst([
                    'conditions' => 'id = :profile_id:',
                    'bind' => ['profile_id' => $num],
                ]);

                if ($tr && $tr->status == 'NOT_PAID') {
                    $detection++;
                    echo "Не оплачено: $tr->id \n";
                    // размер платежа тоже совпал
                    if ($cnt_to_bind == 1 && $amount == $tr->amount) {
                        $detection++;
                        echo "Размер платежа совпал: " . ($detection == 1 ?  'да' : 'нет') ."\n";
                    }
                    // размер платежей до этого (на предварительной проверке) совпал
                    if ($cnt_to_bind > 1 && $amount == round($check_amount, 2)) {
                        $detection++;
                        echo "Размер платежей совпал: " . ($detection == 2 ?  'да' : 'нет') ."\n";
                    }

                    $_idnum = '';
                    if ($profile) {
                        if ($profile->agent_iin) {
                            if ($profile->agent_iin == $idnum) {
                                $detection++;
                                $_idnum = $idnum;
                            } elseif (preg_match('/\b\d{12}\b/', htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'), $_agent)) {
                                if (isset($_agent[0]) && $profile->agent_iin == $_agent[0]) {
                                    $detection++;
                                    $_idnum = $_agent[0];
                                }
                            }
                        } else {
                            $user = User::findFirstById($profile->user_id);

                            if ($user->user_type_id == 1) {
                                $person = PersonDetail::findFirstByUserId($profile->user_id);
                                $_idnum = $person->iin;

                            } else {
                                $company = CompanyDetail::findFirstByUserId($profile->user_id);
                                $_idnum = $company->bin;
                            }

                            if ($idnum == $_idnum) {
                                $detection++;
                            }
                        }

                        echo "ИИН/БИН совпадает:  " . $detection == 3 ? 'да' : 'нет'  ."\n";
                    }

                    echo "detection " . $detection ."\n";

                    if ($detection >= 3) {
                        $bank = ZdBank::findFirst([
                            'conditions' => 'account_num = :account_num:',
                            'bind' => ['account_num' => $accn],
                        ]);

                        if ($bank) {
                            $tr->status = 'PAID';
                            $tr->auto_detected = 1;
                            $tr->save();

                            $meta = "AUTO DETECTED PAYMENT: $idnum ($_idnum) - $amount ($check_amount) - $num: to bind $cnt_to_bind (score $detection)";
                            $log = new ZdBankLogs();
                            $log->login = 'SYSTEM';
                            $log->action = 'set_payment';
                            $log->payment_id = $bank->id;
                            $log->profile_id = $num;
                            $log->dt = time();
                            $log->meta = htmlspecialchars($meta, ENT_QUOTES, 'UTF-8');
                            $log->save();

                            $bank->transactions = implode(', ', array_map(function ($val) {
                                return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                            }, $num_to_bind));
                            $bank->save();

                            if (in_array($tr->approve, ['APPROVE', 'CERT_FORMATION'])) {
                                $tr->approve = 'GLOBAL';
                                $tr->dt_approve = $tr->dt_approve ?: time();
                                $tr->ac_approve = 'SIGNED';
                                $tr->ac_dt_approve = time();
                                $tr->save();

                                $expense = ProfileExpense::findFirst([
                                    'conditions' => 'profile_id = :profile_id:',
                                    'bind' => ['profile_id' => $tr->profile_id],
                                ]);

                                if (!$expense) {
                                    $expense = new ProfileExpense();
                                    $expense->profile_id = $tr->profile_id;
                                    $expense->rnn_recipient = $_idnum;
                                    $expense->date_modified = time();
                                    $expense->amount = $tr->amount;
                                    $expense->save();
                                } else {
                                    $expense->date_modified = time();
                                    $expense->amount = $tr->amount;
                                    $expense->save();
                                }

                                $log1 = new ProfileLogs();
                                $log1->login = 'SYSTEM';
                                $log1->action = 'GLOBAL';
                                $log1->profile_id = $tr->profile_id;
                                $log1->dt = time();
                                $log1->meta_before = '-';
                                $log1->meta_after = '-';
                                $log1->save();

                                $log2 = new ProfileLogs();
                                $log2->login = 'SYSTEM';
                                $log2->action = 'SIGNED';
                                $log2->profile_id = $tr->profile_id;
                                $log2->dt = time();
                                $log2->meta_before = '-';
                                $log2->meta_after = '-';
                                $log2->save();
                            }
                        }
                    }
                }
            }
        }
    }

    public function extractOrderNumbers($comment) {
        // Приводим текст к безопасному HTML
        $text = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

        // Паттерн для поиска номеров по ключевым словам
        $pattern_with_context = '/(?:R-?|#|№|заявк[аи])\s*[-#№]?\s*(\d{5,8})/iu';
        // Паттерн для fallback, когда ключевых слов нет
        $pattern_numbers_only = '/\b\d{6,8}\b/';

        // Ищем сначала по паттерну с контекстом
        if (preg_match_all($pattern_with_context, $text, $matches_with_context)) {
            $numbers = $matches_with_context[1];
        } elseif (preg_match_all($pattern_numbers_only, $text, $matches_numbers)) {
            // fallback: ищем просто числа
            $numbers = $matches_numbers[0];
        } else {
            $numbers = [];
        }

        // Фильтрация дубликатов
        return array_unique($numbers);
    }

}
