<?php

namespace App\Controllers;

use App\Services\Halyk\ApiLimit;
use Car;
use ControllerBase;
use HalykPayment;
use Profile;
use RefFund;
use Transaction;
use User;

class ApiController extends ControllerBase
{
    public function indexAction()
    {
        $this->view->disable();
        $r = array(
            'info' => 'INTEGRATION_API',
            'version' => '0.5'
        );
        echo json_encode($r);
    }

    /**
     * Сюда приходит информация из банка о платеже.
     */
    public function paymentsPushAction()
    {
        $this->view->disable();
        $this->setHeaders();

        $clientIP = $this->request->getClientAddress();
        $request = $this->request->getPost("request");
        $this->logRequest($request, $clientIP, 'push');

        $obj = json_decode($request, true);
        $payments64 = $obj['payments'];
        $signature64 = $obj['signature'];
        $payments_json = base64_decode($payments64);
        $signature = base64_decode($signature64);
        $payments = json_decode($payments_json, true);
        $idnum_sender = $payments['idnum-sender'];
        $idnum_owner = $payments['idnum-owner'];
        $is_third_party_payer = $payments['is-third-party-payer'] == 1;
        $reference = $payments['statement-reference'];
        $order_number = $payments['order-number'];
        $amount_sender = $payments['amount-sender'];

        if (!$this->isIpAllowed($clientIP)) {
            return $this->setPushResponse('001', $clientIP);
        }

        $limit = new ApiLimit($clientIP, 8);
        if (!$limit->checkLimit()) {
            header("HTTP/1.1 429 Too Many Requests");
            header(sprintf("Retry-After: %d", 5));
            return $this->setPushResponse('002', $clientIP);
        }

        if (!$this->request->isPost()) {
            return $this->setPushResponse('003', $clientIP);
        }

        if (!$request) {
            return $this->setPushResponse('004', $clientIP);
        }

        $required_fields = ['payments', 'signature'];
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $obj)) {
                return $this->setPushResponse('005', $clientIP, null, $field);
            }
        }

        $required_fields = [
            'id', 'document-number', 'idnum-invoice', 'statement-reference', 'amount-sender',
            'name-sender', 'is-third-party-payer', 'name-recipient', 'idnum-sender', 'idnum-owner', 'idnum-recipient',
            'account-recipient', 'knp-code', 'date-sender', 'payment-purpose', 'mfo-sender',
            'mfo-recipient', 'order-number'
        ];

        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $payments)) {
                $missing_fields[] = $field;
            }
        }

        if (count($missing_fields) > 0) {
            $comma_separated_missing_fields = implode(", ", $missing_fields);
            return $this->setPushResponse('005', $clientIP, null, $comma_separated_missing_fields);
        }

        if (!$this->verifyCMS($signature, $payments64)) {
            return $this->setPushResponse('006', $clientIP);
        }

        $tr = Transaction::findFirstByProfileId($order_number);
        if (!$tr) {
            return $this->setPushResponse('007', $clientIP, null, $order_number);
        }

        $profile = Profile::findFirstById((int)$order_number);
        $user = User::findFirstById($profile->user_id);
        if ($user->idnum != $idnum_owner) {
            return $this->setPushResponse('008', $clientIP, null, $order_number);
        }

        if (!$is_third_party_payer) {
            if ($user->idnum != $idnum_sender) {
                return $this->setPushResponse('009', $clientIP);
            }
        } else {
            if ($idnum_owner == $idnum_sender) {
                return $this->setPushResponse('009', $clientIP);
            }
        }

        if ($tr->approve != 'APPROVE') {
            return $this->setPushResponse('011', $clientIP);
        }

        $epsilon = 0.01;
        $abs = abs((float)$tr->amount - (float)$amount_sender);
        if ($abs > $epsilon || strval($tr->amount) != strval($amount_sender)) {
            return $this->setPushResponse('012', $clientIP);
        }

        $halyk_res = HalykPayment::findFirst(["statement_reference = $reference"]);
        if ($halyk_res) {
            return $this->setPushResponse('013', $clientIP, $halyk_res->id, $halyk_res->statement_reference);
        }

        if (!$this->isPaymentWithinInterval($order_number)) {
            return $this->setPushResponse('014', $clientIP, null, $order_number);
        }

        $halykPayment = $this->halykPaymentService->store($payments);
        if ($halykPayment) {
            return $this->setPushResponse('200', $clientIP, $halykPayment->id);
        }

        return $this->setPushResponse('unknown', $clientIP);
    }

    /**
     * Отсюда банк информацию о платеже запрашивает.
     */
    public function paymentsPullAction()
    {
        $this->view->disable();
        $this->setHeaders();

        $clientIP = $this->request->getClientAddress();
        $request = $this->request->getPost("request");
        $this->logRequest($request, $clientIP, 'pull');

        $obj = json_decode($request, true);
        $signature64 = $obj['signature'];
        $idnum_owner64 = $obj['idnum-owner'];
        $idnum_owner = base64_decode($obj['idnum-owner']);
        $order_num64 = $obj['order-number'];
        $order_num = base64_decode($order_num64);
        $signature = base64_decode($signature64);

        if (!$this->isIpAllowed($clientIP)) {
            return $this->setPullResponse('001', $clientIP, null, $clientIP);
        }

        $limit = new ApiLimit($clientIP, 8);
        if (!$limit->checkLimit()) {
            header("HTTP/1.1 429 Too Many Requests");
            header(sprintf("Retry-After: %d", 5));
            return $this->setPullResponse('002', $clientIP);
        }

        if (!$this->request->isPost()) {
            return $this->setPullResponse('003', $clientIP);
        }

        if (!$request) {
            return $this->setPullResponse('004', $clientIP);
        }

        $required_fields = ['order-number', 'idnum-owner', 'signature'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $obj)) {
                $missing_fields[] = "`" . $field . "`";
            }
        }
        if (count($missing_fields) > 0) {
            $comma_separated_missing_fields = implode(", ", $missing_fields);
            return $this->setPullResponse('005', $clientIP, null, $comma_separated_missing_fields);
        }

        unset($obj["signature"]);
        $inData = base64_encode(json_encode($obj));
        if (!$this->verifyCMS($signature, $inData)) {
            return $this->setPullResponse('006', $clientIP);
        }

        $tr = Transaction::findFirstByProfileId((int)$order_num);
        if (!$tr) {
            return $this->setPullResponse('007', $clientIP, null, $order_num);
        }

        $profile = Profile::findFirstById((int)$order_num);
        $user = User::findFirstById($profile->user_id);
        if ($user->idnum != $idnum_owner) {
            return $this->setPullResponse('008', $clientIP, null, $order_num);
        }

        if ($tr->approve != 'APPROVE') {
            return $this->setPullResponse('010', $clientIP);
        }

        $paymentData = $this->getPaymentData($profile, $tr);
        if ($paymentData) {
            $data = base64_encode(json_encode($paymentData));
            http_response_code(200);
            $res = [
                'response' => 'success',
                'status' => '200',
                'data' => $data
            ];

            $this->logResponse(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $clientIP, 'pull');
            return json_encode($res);
        }

        return $this->setPullResponse('unknown', $clientIP);
    }

    private function setPushResponse(string $code, string $clientIP, string $unique_id = null, string $text = null)
    {
        $responseData = $this->getResponseData($code, $text);
        $data = ["status" => $responseData['statusCode'], "message" => $responseData['message']];
        if ($code == '200') {
            $data = [
                'response' => 'success',
                'status' => $responseData['code'],
                'info' => $responseData['message'],
                'unique_id' => $unique_id
            ];

        }

        $this->logAction('Создание платежа: halyk_mobile');

        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->logResponse($jsonData, $clientIP, 'push');
        return $this->jsonResponse(
            ($code == '200') ? 'success' : 'error',
            $responseData['code'],
            $responseData['message'],
            $responseData['statusCode'],
            $unique_id
        );
    }

    private function setPullResponse(string $code, string $clientIP, string $unique_id = null, string $text = null)
    {
        $responseData = $this->getResponseData($code, $text);
        $data = ["status" => $responseData['statusCode'], "message" => $responseData['message']];
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->logResponse($jsonData, $clientIP, 'pull');

        $this->logAction('Получение данных заявки: halyk_mobile');

        return $this->jsonResponse(
            ($code == '200') ? 'success' : 'error',
            $responseData['code'],
            $responseData['message'],
            $responseData['statusCode'],
            $unique_id
        );
    }

    private function getResponseData($code, string $text = null)
    {
        $data = [
            '001' => ["001", "Проверка по IP адресу не пройдена(IP: %s - запрещен! #001)", 403],
            '002' => ["002", "Ограничение по количеству запросов в секунду! #002", 429],
            '003' => ["003", "Неразрешенный метод(должен быть POST)! #003", 405],
            '004' => ["004", "Поле `request` не может быть пустым! #004", 400],
            '005' => ["005", "Отсутствует ключ %s в JSON-объект(request)! #005", 400],
            '006' => ["006", "Подпись не прошла проверку! #006", 403],
            '007' => ["007", "Номер заявки %s не найден! #007", 423],
            '008' => ["008", "Заявка %s не принадлежит пользователю! #008", 423],
            '009' => ["009", "Нет сведений по оплате от третьего лица! #009", 423],
            '010' => ["009", "Неверный статус заявки! #009", 423],
            '011' => ["010", "Неверный статус заявки! #010", 423],
            '012' => ["011", "Сумма не эквивалентна! #011", 423],
            '013' => ["012", "Сведения по референсу %s уже существует! #012", 423],
            '014' => ["013", "Платеж по заявке № %s уже был отправлен. Пожалуйста, повторите попытку позже! #013", 423],
            '200' => ["200", "Успешное получение информации о платеже! #200", 200],
            'unknown' => ["unknown", "Неизвестная ошибка", 423]
        ];

        if (isset($data[$code])) {
            list($code, $messageTemplate, $statusCode) = $data[$code];
            $message = $text !== null ? sprintf($messageTemplate, $text) : $messageTemplate;
            return ['code' => $code, 'message' => $message, 'statusCode' => $statusCode];
        }
        return ['message' => 'Неизвестный код сообщения', 'statusCode' => 423];
    }

    private function getPaymentData($p, $t)
    {
        $u = User::findFirstById($p->user_id);
        $idnum = $u->idnum;

        $purpose = "Плата за организацию сбора, транспортировки, переработки, обезвреживания, использования и утилизации отходов, согласно заявки #" . $p->id . " от " . date("d.m.Y", $t->md_dt_sent);
        $iban = IBAN_IMPORT;

        if ($p->type == 'CAR') {
            $check_agro = Car::findFirst(array(
                "ref_car_cat IN (13, 14) AND profile_id = :profile_id:",
                "bind" => array(
                    "profile_id" => $p->id
                )
            ));

            if ($check_agro) {
                if ($p->agent_status == 'NOT_SET' || $p->agent_status == 'IMPORTER') {
                    $iban = IBAN_AGRO_IMPORTER;
                } else if ($p->agent_status == 'VENDOR') {
                    $iban = IBAN_AGRO_VENDOR;
                }
            } else {
                if ($p->agent_status == 'NOT_SET' || $p->agent_status == 'IMPORTER') {
                    $iban = IBAN_PERSON;
                } elseif ($p->agent_status == 'VENDOR') {
                    $car = Car::findFirstByProfileId($p->id);
                    $firstCarYear = date('Y', $car->date_import);
                    $ref_fund = RefFund::find([
                        "idnum = :idnum: AND key = :key: AND year = :year:",
                        "bind" => array(
                            "idnum" => $idnum,
                            "key" => 'START',
                            "year" => $firstCarYear
                        )
                    ]);

                    if (isset($ref_fund) && count($ref_fund) > 0) {
                        $iban = IBAN_VENDOR_HAS_FUND;
                    } else {
                        $iban = IBAN_VENDOR_HAS_NOT_FUND;
                    }
                }
            }
        }

        if ($p->type == 'GOODS') {
            if ($p->agent_status == 'NOT_SET' || $p->agent_status == 'IMPORTER') {
                $iban = IBAN_GOODS_IMPORTER;
            } else if ($p->agent_status == 'VENDOR') {
                $iban = IBAN_GOODS_VENDOR;
            }
        }

        if ($p->type == 'KPP') {
            if ($p->agent_status == 'IMPORTER') {
                $iban = IBAN_KPP_IMPORTER;
            } else if ($p->agent_status == 'VENDOR') {
                $iban = IBAN_KPP_VENDOR;
            }
        }

        return [
            'amount' => $t->amount,
            'name-recipient' => ZHASYL_DAMU,
            'idnum-recipient' => ZHASYL_DAMU_BIN,
            'account-recipient' => $iban,
            'payment-purpose' => $purpose,
            'mfo-recipient' => BIK,
            'kbe-recipient' => ZHASYL_DAMU_KBE,
            'knp-recipient' => ZHASYL_DAMU_KNP,
            'order-number' => $p->id
        ];
    }

    private function setHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
        header("Access-Control-Allow-Methods: POST");
    }


    private function isPaymentWithinInterval($order_number): bool
    {
        $currentTimestamp = time();
        $intervalTimestamp = $currentTimestamp - (30 * 60);

        $query = $this->modelsManager->createQuery("
            SELECT COUNT(*) AS count 
            FROM HalykPayment
            WHERE order_number = :order_number: 
            AND created_dt > :intervalTimestamp:
        ");

        $result = $query->execute([
            'order_number' => $order_number,
            'intervalTimestamp' => $intervalTimestamp
        ]);

        $row = $result->getFirst();

        return $row->count == 0;
    }

    private function isIpAllowed($clientIP)
    {
        $ip_list = array_map('trim', explode(',', getenv('HALYK_API_WHITE_LIST')));
        return in_array($clientIP, $ip_list);
    }

    private function jsonResponse($response, $status, $info, $httpStatusCode, $unique_id = null)
    {
        http_response_code($httpStatusCode);
        $r = [
            'response' => $response,
            'status' => $status,
            'info' => $info
        ];
        if ($unique_id) {
            $r['unique_id'] = $unique_id;
        }
        return json_encode($r);
    }

    private function logRequest($request, $clientIP, $prefix)
    {
        $logDir = APP_PATH . "/storage/logs/halyk";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents("{$logDir}/{$prefix}_request_" . date('d.m.Y_H.i.s') . "_{$clientIP}.txt", $request);
    }

    private function logResponse($request, $clientIP, $prefix)
    {
        $logDir = APP_PATH . "/storage/logs/halyk";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents("{$logDir}/{$prefix}_response_" . date('d.m.Y_H.i.s') . "_{$clientIP}.txt", $request);
    }


    private function verifyCMS($cms, $data): bool
    {
        $check = $this->cmsService->checkAttached($data, $cms);
        if($check && isset($check['success'])) {
            if ($check['success']) {
                return true;
            }
        }
        return false;
    }
}
