<?php

namespace App\Services\Msx;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Services\Integration\IntegrationService;
use MsxRequest;
use User;

class MsxService extends IntegrationService
{
    use LogTrait;

    /**
     * @throws AppException
     */
    public function search(array $payload, $comment): array
    {
        $result = $this->post('/msx', $payload);

        if (isset($result['message'])) {
            if (isset($result['message'][0]) && $result['message'][0] == 'error') {
                $this->writeLog('MSX request error: ' . ($result['message'][1] ?? ''));
            } else {
                $this->writeLog('MSX request success: ' . ($result['message'][1] ?? ''));
            }
        }

        if (isset($result['data'])) {
            $data = $result['data'];

            $msx_request = $this->store($data, $payload, $comment);
            $result['msx_request_id'] = $msx_request->id;
            return $result;
        }
        return [];
    }

    public function store($data, $payload, $comment)
    {
        try {
            $auth = User::getUserBySession();
            if (!$auth) {
                $this->writeLog("User not authenticated");
            }

            $msx_request = new MsxRequest();
            $msx_request->req_value = $payload['value'];
            $msx_request->req_type = $payload['requestTypeCode'];
            $msx_request->req_time = $data['request_time'];
            $msx_request->execution_time = $data['execution_time'];
            $msx_request->request = $data['request'];
            $msx_request->response = $data['response'];
            $msx_request->message_id = $data['message_id'];
            $msx_request->session_id = $data['session_id'];
            $msx_request->response_date = $data['response_date'];
            $msx_request->code = $data['code'];
            $msx_request->message = $data['message'] ?? '';
            $msx_request->response_success = '';
            $msx_request->response_type = '';
            $msx_request->user_id = $auth->id;
            $msx_request->created = time();
            $msx_request->comment = $comment;
            $msx_request->save();

            if ($msx_request->save() === false) {
                $messages = $msx_request->getMessages();
                foreach ($messages as $message) {
                    $this->writeLog("Phalcon Validation Error: " . $message);
                }
                return false;
            }

            return $msx_request;
        } catch (\PDOException $e) {
            $this->writeLog("Database Error: " . $e->getMessage(), 'action', 'ERROR');
            return false;
        } catch (\Exception $e) {
            $this->writeLog("General Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
