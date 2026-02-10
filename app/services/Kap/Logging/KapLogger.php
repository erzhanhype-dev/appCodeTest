<?php

namespace App\Services\Kap\Logging;

use App\Helpers\LogTrait;
use KapLogs;
use Phalcon\Di\Injectable;

class KapLogger extends Injectable
{
    use LogTrait;
    public function store(string $req_value, int $req_type, int $user_id, string $comment, array $response): KapLogs
    {
        // 1. Извлечение статуса
        $status = $this->getStatusFromResponse($response);
        $payload_status = $status['Code'];
        $payload_message_ru = $status['MessageRu'];
        $payload_message_kz = $status['MessageKz'];

        // 2. Извлечение полей из секции 'data' ответа
        $data = $response['data'] ?? [];

        $messageId = $data['message_id'] ?? null;
        $sessionId = $data['session_id'] ?? null;
        $code = $data['code'] ?? null;
        $message = $data['message'] ?? null;
        $responseDate = $data['response_date'] ?? null;
        $request_time = $data['request_time'] ?? null;
        $execution_time = $data['execution_time'] ?? null;
        $request_xml_file_name = $data['request'] ?? null;
        $response_xml_file_name = $data['response'] ?? null;

        // 3. Определение типа запроса
        $req_type_label = match ($req_type) {
            0 => 'VIN',
            1 => 'GRNZ',
            2 => 'IINORBIN',
            default => 'UNKNOWN',
        };

        // 4. Заполнение и сохранение модели KapLogs
        $kap_log = new KapLogs();

        $kap_log->req_value = $req_value;
        $kap_log->req_type = $req_type_label;

        // --- Все поля, которые были упущены ранее, теперь включены ---
        $kap_log->req_time = $request_time;
        $kap_log->execution_time = $execution_time;
        $kap_log->request = $request_xml_file_name;
        $kap_log->response = $response_xml_file_name;
        $kap_log->message_id = $messageId;
        $kap_log->session_id = $sessionId;
        $kap_log->response_date = $responseDate;
        $kap_log->code = $code;
        $kap_log->message = $message;

        $kap_log->response_success = ''; // Как было в оригинале
        $kap_log->response_type = '';    // Как было в оригинале

        $kap_log->payload_status = $payload_status;
        $kap_log->payload_message_ru = $payload_message_ru;
        $kap_log->payload_message_kz = $payload_message_kz;
        // -------------------------------------------------------------

        $kap_log->user_id = $user_id;
        $kap_log->created = time();
        $kap_log->comment = $comment;

        $kap_log->save();

        $logString = json_encode($kap_log->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->writeLog($logString);

        return $kap_log;
    }

    private function getStatusFromResponse(array $response): array
    {
        return $response['data']['list']['Status'] ?? [
            'Code' => 'N/A',
            'MessageRu' => 'Статус не найден',
            'MessageKz' => 'Статус не найден',
        ];
    }
}