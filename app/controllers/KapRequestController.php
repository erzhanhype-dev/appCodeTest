<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\Kap\KapService;
use App\Services\Pdf\PdfService;
use ControllerBase;
use Exception;
use KapLogs;
use KapRequest;
use PersonDetail;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use PHPQRCode\QRcode;
use SimpleXMLElement;
use User;
use XMLReader;

class KapRequestController extends ControllerBase
{

    /**
     * @throws AppException
     */
    public function downloadAction($kap_log_id)
    {
        $this->view->disable();
        $kap_log = KapLogs::findFirstById($kap_log_id);

        $this->logAction('Генерация справки КАП');
        $this->generateDoc($kap_log);
    }

    /**
     * @throws AppException
     */
    public function downloadOldAction($id)
    {
        $r_id = $id;
        $lang = 'ru';
        $download = true;
        $a = $this->session->get('auth');
        $this->view->disable();
        $request = KapRequest::findFirstById($r_id);
        $xml = new SimpleXMLElement($request->xml_response);
        $xml = $xml->script->dataset->records;
        $user = User::findFirstById($request->user_id);

        $to_download = '';
        $path = APP_PATH . '/private/kap_requests/';
        $hash = '';
        $__qr_line = '';

        $spravka = $path . 'kap_request_' . $request->id . '.pdf';
        if (file_exists($spravka)) unlink($spravka);

        $reader_signDocs = new XMLReader();
        $reader_signDocs->XML($request->xml_response);

        if ($reader_signDocs->isValid()) {
            echo "XML is invalid #0004.";
        } else {

            while ($reader_signDocs->read() && $reader_signDocs->name != 'ds:X509Data') ;
            while ($reader_signDocs->name == 'ds:X509Data') {
                $hash = simplexml_load_string($reader_signDocs->readInnerXml());
                $reader_signDocs->next('ds:X509Data');
            }
        }

        $reader_signDocs->close();

        $x509Data =
            "-----BEGIN CERTIFICATE-----\n"
            . $hash
            . "\n-----END CERTIFICATE-----";

        $sign = openssl_x509_parse($x509Data, true);

        // гененрируем сертификат
        $certificate_template = APP_PATH . '/app/templates/html/kap_request/kap_request.html';

        $content_qr = 'ФИО: ' . $sign['subject']['CN'] . ' ' . $sign['subject']['GN'] .
            ':: ИИН: ' . str_replace('IIN', '', $sign['subject']['serialNumber']) .
            ':: БИН: ' . str_replace('BIN', '', $sign['subject']['OU']) .
            ':: Наименование : ' . $sign['subject']['O'];
        $content_sign = generateQrHash($content_qr);
        QRcode::png($content_qr . '::' . $content_sign, APP_PATH . '/storage/temp/kap_request_' . $request->id . '_x509.png', 'H', 3, 0);
        $__qr_line .= '<img src="' . APP_PATH . '/storage/temp/kap_request_' . $request->id . '_x509.png" width="200" height="200"> &nbsp &nbsp &nbsp &nbsp &nbsp &nbsp';

        $content_qr = 'id_' . $request->id . '_status_' . $request->state . '_date_' . date('d.m.Y', $request->created_at) . '_user_' . $user->idnum;
        $content_sign = generateQrHash($content_qr);
        QRcode::png($content_qr . ':' . $content_sign, APP_PATH . '/storage/temp/kap_request_' . $request->id . '.png', 'H', 3, 0);

        $__qr_line .= '<img src="' . APP_PATH . '/storage/temp/kap_request_' . $request->id . '.png" width="200" height="200"> &nbsp';

        $cert = join('', file($certificate_template));

        $certificate_tmp = APP_PATH . '/storage/temp/kap_request_' . $request->id . '.html';

        $header = "Запрос в КАП #" . $request->id;
        $cert = str_replace('[HEADER]', $header, $cert);

        $desc = $request->base_on;
        $cert = str_replace('[DESCRIPTION]', $desc, $cert);

        $usr = "Автор запроса (ИИН): " . $user->idnum;
        $cert = str_replace('[AUTHOR]', $usr, $cert);

        $date = 'Дата:' . date('d.m.Y', $request->created_at);
        $time = 'Время:' . date('H:i', $request->created_at);
        $cert = str_replace('[DATE]', ' ' . $date . '<br>' . $time . ' ', $cert);

        $cert = str_replace('[STATUS]', "Статус : <b>$request->state</b>", $cert);

        $status = $request->k_status;

        $cert = str_replace('[RESPONSE]', $status, $cert);

        $cert = str_replace('[Z_QR]', $__qr_line, $cert);

        $safeId = (int)$request->id;
        if ($safeId <= 0) {
            throw new \Exception('Некорректный ID запроса');
        }

        $tempDir = APP_PATH . '/storage/temp/';
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            throw new AppException('Папка для временных файлов недоступна для записи');
        }

        $certificate_tmp = $tempDir . 'kap_request_' . $safeId . '.html';

        $certificate_tmp = $tempDir . basename($certificate_tmp);

        $bytes = @file_put_contents($certificate_tmp, $cert, LOCK_EX);
        if ($bytes === false) {
            throw new \Exception('Не удалось сохранить временный файл сертификата');
        }

        (new PdfService())->generate($certificate_tmp, $path . 'kap_request_' . $request->id . '.pdf');
        // запрашивали сертификат??? готовим ссылку
        $to_download = $path . 'kap_request_' . $request->id . '.pdf';

        if (file_exists($to_download)) {
            $this->logAction('Генерация справки КАП');
            __downloadFile($to_download);
        } else {
            echo('Нет файла');
        }
    }

    public function indexAction()
    {
        // $this->session->remove("k_request_id");
        // $this->view->disable();
        $k_request = null;
        $xml = null;
        $user = null;
        $date = null;

        if (isset($_SESSION['k_request_id'])) {
            // Экранирование значения из сессии
            $id = htmlspecialchars($_SESSION['k_request_id'], ENT_QUOTES, 'UTF-8');
            $k_request = KapRequest::findFirstById($id);

            // Проверка и очистка xml_response
            if ($k_request) {
                $xml_response = $k_request->xml_response;
                if (__isValidXml($xml_response)) {
                    try {
                        // Создание объекта XML с защитой
                        $xml_data = new SimpleXMLElement($xml_response);

                        if ($xml_data->error) {
                            // Экранирование сообщения об ошибке перед выводом
                            $this->flash->error('Ответ из КАП: ' . htmlspecialchars((string)$xml_data->error->message, ENT_QUOTES, 'UTF-8'));
                        } else {
                            // Работа с данными XML
                            $xml = $xml_data->script->dataset->records;
                            $user = PersonDetail::findFirstByUserId($k_request->user_id);
                            $date = date('d.m.Y г. H:i', $k_request->created_at);
                        }
                    } catch (Exception $e) {
                        // Обработка исключений при парсинге XML
                        $this->flash->error('Ошибка обработки XML: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
                    }
                } elseif ($k_request->xml_response != '') {
                    // Экранирование xml_response в случае ошибки
                    $this->flash->error('Ответ из КАП: ' . htmlspecialchars($k_request->xml_response, ENT_QUOTES, 'UTF-8'));
                }
            } else {
                $this->flash->error('Запрос с указанным ID не найден.');
            }
        }

        $this->view->setVars(array(
            "k_log" => $k_request,
            "k_log_status" => true,
            "xml" => $xml,
            "user" => $user,
            "request_date" => $date
        ));
    }

    /**
     * @throws Exception
     */
    public function searchAction(): ResponseInterface
    {
        $auth = User::getUserBySession();
        $__settings = $this->session->get("__settings");
        $request = $this->request;
        $k_request = null;

        $this->view->disable();

        if (($request->isPost() != true) && ($request->getPost("base_on") == '') || ($request->getPost("vininput") == '') && ($request->getPost("grnzinput") == '')) {
            $this->flash->error("Ошибка, отправьте форму проверки в КАП.");
            return $this->response->redirect("/kap_request/index/");
        }

        $vin = $this->request->getPost("vininput");
        $grnz = $this->request->getPost("grnzinput");
        $type = $this->request->getPost("type");

        $base_on = $this->request->getPost("base_on");
        $status = '';
        $result_data = [];
        $value = '';

        if ($type === 'VIN') {
            $value = $vin;
            $kap_request_car_id = $vin;
            $kap_data = $this->kapOldService->getFromVIN($vin);
            if ($kap_data['status'] === 'success') {
                $result_data[0] = $kap_data['data'];
            }
        } else {
            $value = $grnz;
            $kap_data = $this->kapOldService->getFromGRNZ($grnz);
            if ($kap_data['status'] === 'success') {
                $result_data[0] = $kap_data['data'];
            }
        }

        if (__isValidXml($result_data[0])) {
            $xml_data = new SimpleXMLElement($result_data[0]);
            if ($xml_data->error) {
                $this->flash->error('Ответ из КАП: ' . $xml_data->error->message);
                $status = 'Ошибка запроса:' . $xml_data->error->message;
                $state = 'Ошибка запроса:' . $xml_data->error->message;
            } elseif (count($xml_data->script->dataset->records->record) > 0) {
                // S - Выведен из учета

                $status = '';
                $state = '';
                $state_bool = false;
                $state_bool_without_check_other = false;
                $xml = $xml_data->script->dataset->records;
                $record = $xml->record;
                $first_record_status = $record[0]->field->attributes();
                $first_record_status = $record[0]->field->attributes();

                foreach ($record[0]->field as $field) {
                    $field_name = $field->attributes();
                    $field_name = $field_name[0];

                    if ($field_name == 'STATUS') {
                        if ($field == 'S') {
                            $state_bool = true;
                            $state = 'Снят с учета';
                        } elseif ($field == 'P') {
                            $state_bool_without_check_other = true;
                            $state = 'На регистрации';
                        } else {
                            $state_bool = false;
                            $state = $field;
                        }
                    }
                }

                if ($state_bool) {
                    foreach ($record as $key => $r) {
                        // убираем первый из проверки
                        if ($key === 0) {
                            continue;
                        }

                        foreach ($r->field as $field) {
                            $field_name = $field->attributes();
                            $field_name = $field_name[0];

                            if ($field_name == 'STATUS') {
                                if ($field != 'S') {
                                    $state_bool = false;
                                    $state = 'Требуется проверка вручную';
                                    break;
                                } else {
                                    continue; // типа все пока нормально
                                }
                            }
                        }
                    }
                }

                if ($state_bool || $state_bool_without_check_other) {
                    $status .= '';
                    $k = 0;

                    foreach ($record[0]->field as $field) {
                        $field_name = $field->attributes();
                        $field_name = $field_name[0];
                        $k++;

                        if (isset(KAP_INTEG_DATA_TYPE[strval($field_name)])) {
                            $status .= '<span>' . $k . '. ' . KAP_INTEG_DATA_TYPE[strval($field_name)] . ': ';

                            $status .= ' <b>' . $field . '</b></span><br>';
                        }
                    }

                    $status .= '';
                }

                $this->flash->success('Запрос в КАП был успешен');
            } elseif (count($xml_data->script->dataset->records->record) == 0) {
                $this->flash->error('В КАП записей не найдено');
                $state = 'В АИС КАП записей не найдено!';
            }

        } elseif ($result_data[0] != '') {
            $this->flash->error('Ответ из КАП: ' . $result_data[0]);
            $status = 'Ошибка запроса:' . $result_data[0];
            $this->logAction("{$status}: {$value}");
        }

        $k_request = new KapRequest();
        $k_request->vin = $kap_request_car_id;
        $k_request->base_on = $base_on;
        $k_request->state = $state;
        $k_request->k_status = $status;
        $k_request->xml_response = $result_data[0];
        $k_request->user_id = $auth->id;
        $k_request->created_at = time();
        $k_request->save();

        $_SESSION['k_request_id'] = $k_request->id;

        return $this->response->redirect("/kap_request/index/");
    }

    /**
     * @throws Exception
     */
    public function newAction()
    {
        $auth = User::getUserBySession();
        $current_request = null;
        $lastStatus = '';
        $items = [];

        if ($this->request->isGet()) {
            $log_id = (isset($_SESSION['kap_log_id'])) ? $_SESSION['kap_log_id'] : NULL;
            $current = KapLogs::findFirstById($log_id);

            if ($current) {
                if ($current->response != '') {
                    $current_request = $current;
                    $service = new KapService();
                    $list = $service->getItemsFromFileName($current->response);
                    if ($list && count($list) > 0) {
                        $items = $service->parseDataWithSorted(json_encode($list));
                    }
                }
            }

            if (count($items) > 1) {
                $lastItem = $items[count($items) - 1];
                if ($lastItem) {
                    $lastStatus = $lastItem[22];
                }
            }
        }

        $this->view->setVars(array(
            "auth" => $auth,
            "current_request" => $current_request,
            "items" => $items,
            "lastStatus" => $lastStatus
        ));
    }

    /**
     * @throws AppException
     * @throws Exception
     */

    private function findValueByKey(array $array, $keyToFind)
    {
        foreach ($array as $key => $value) {
            if ($key === $keyToFind) {
                return $value;
            }
            if (is_array($value)) {
                $result = $this->findValueByKey($value, $keyToFind);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null; // Вернётся, если ключ не найден
    }

    /**
     * @throws AppException
     */
    public function searchKey($array, $key)
    {
        return $this->findValueByKey($array, $key);
    }


    /**
     * @throws AppException
     * @throws Exception
     */
    private function generateDoc($kap_log)
    {
        $service = new KapService();
        $id = $kap_log->id;
        $comment = $kap_log->comment;
        $created = $kap_log->created;
        $user = User::findFirst($kap_log->user_id);
        $idnum = $user->idnum;
        $path = APP_PATH . '/storage/temp/';

        // гененрируем сертификат
        $certificate_template = APP_PATH . '/app/templates/html/kap_request/kap_request_v2.html';

        $certificate_tmp = APP_PATH . '/storage/temp/kap_log_' . $id . '.html';
        $cert = join('', file($certificate_template));
        $payload_status_code = '';

        $xmlString = $service->getFile($kap_log->response);
        $hash = $service->getSignFromXmlString($xmlString);

        $x509Data =
            "-----BEGIN CERTIFICATE-----\n"
            . $hash
            . "-----END CERTIFICATE-----";

        $sign = openssl_x509_parse($x509Data, true);
        $items = $service->getItemsFromFileName($kap_log->response);

        if ($items) {
            $list = $service->parseDataForDoc(json_encode($items));
            $firstTwo = array_slice($list, 0, 2);
            $maxRows = max(array_map('count', $firstTwo));
            $table = '<table style="border:none">';
            $table .= '<tbody>';
            for ($i = 0; $i < $maxRows; $i++) {
                $table .= '<tr>';
                foreach ($firstTwo as $key => $column) {
                    $table .= "<td style='border:none'>" . htmlspecialchars($column[$i]) . "</td>";
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>';
            $table .= '</table>';
        } else {
            $table = 'Сведения в КАП отсутствует';
        }

        $header = "Запрос в КАП #" . $id;
        $cert = str_replace('[HEADER]', $header, $cert);

        $cert = str_replace('[DESCRIPTION]', $comment, $cert);

        $usr = "<strong>Автор запроса (ИИН): </strong>" . $idnum;
        $cert = str_replace('[AUTHOR]', $usr, $cert);

        $date = '<strong>Время запроса: </strong>' . date('d.m.Y H:i:s', $created);
        $cert = str_replace('[DATE]', $date, $cert);

        $cert = str_replace('[TABLE]', $table, $cert);

        $content_qr = 'id_' . $id . ':: status_' . $payload_status_code . ' :: date_' . date('d.m.Y', $created) . ' :: user_' . $idnum;
        $content_sign = generateQrHash($content_qr);
        QRcode::png($content_qr . ':' . $content_sign, APP_PATH . '/storage/temp/kap_request_' . $id . '.png', 'H', 3, 0);
        $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/kap_request_' . $id . '.png" width="150" height="150">', $cert);

        if ($sign != NULL) {
            $content_qr = 'ФИО: ' . $sign['subject']['CN'] . ' ' . $sign['subject']['GN'] .
                ':: ИИН: ' . str_replace('IIN', '', $sign['subject']['serialNumber']) .
                ':: БИН: ' . str_replace('BIN', '', $sign['subject']['OU']) .
                ':: Наименование : ' . $sign['subject']['O'];
            $content_sign = generateQrHash($content_qr);
            QRcode::png($content_qr . '::' . $content_sign, APP_PATH . '/storage/temp/kap_request_' . $id . '_x509.png', 'H', 3, 0);

            $cert = str_replace('[Z_QR_X509]', '<img src="' . APP_PATH . '/storage/temp/kap_request_' . $id . '_x509.png" width="150" height="150">', $cert);
            $cert = str_replace('[ORG_NAME]', $sign['subject']['O'] . '(' . str_replace('BIN', '', $sign['subject']['OU']) . ')', $cert);
        } else {
            $msg = "<h3 color='red'>ЭЦП отсутствует !</h3>";
            $cert = str_replace('[Z_QR_X509]', $msg, $cert);
            $cert = str_replace('[ORG_NAME]', $msg, $cert);
        }

        file_put_contents($certificate_tmp, $cert);
        $to_download = $path . 'kap_log_' . $id . '.pdf';

        (new PdfService())->generate($certificate_tmp, $to_download);

        if (file_exists($to_download)) {
            __downloadFile($to_download);
        } else {
            echo('Нет файла');
        }
    }

    public function resetPageAction(): ResponseInterface
    {
        $this->view->disable();
        unset($_SESSION['kap_log_id'], $_SESSION['kap_req_type'], $_SESSION['kap_req_value'], $_SESSION['kap_comment']);
        return $this->response->redirect("/kap_request/new");
    }

    public function sendRequestAction(): ResponseInterface
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        unset($_SESSION['kap_log_id'], $_SESSION['kap_req_type'], $_SESSION['kap_req_value'],
            $_SESSION['kap_comment']);

        if ($this->request->isPost()) {
            $req_type = $this->request->getPost("req_type");
            $req_value = trim($this->request->getPost("req_value"));

            $comment = trim($this->request->getPost("comment"));

            $_SESSION['kap_req_type'] = $req_type;
            $_SESSION['kap_req_value'] = $req_value;
            $_SESSION['kap_comment'] = $comment;
            $_SESSION['kap_log_id'] = NULL;

            $service = new KapService();
            $requestTypeCode = 'VIN';
            if ($req_type == 0) {
                $requestTypeCode = 'VIN';
            } else if ($req_type == 1) {
                $requestTypeCode = 'GRNZ';
            } else if ($req_type == 2) {
                $requestTypeCode = 'IINORBIN';
            }

            $payload = [
                'value' => $req_value,
                'requestTypeCode' => $requestTypeCode,
            ];

            $res = $service->get($payload);

            if ($res && $res['data']) {
                $kap_log = $service->store($req_value, $req_type, $auth->id, $comment, $res);
                if ($kap_log) {
                    $_SESSION['kap_log_id'] = $kap_log->id;
                } else {
                    $this->flash->error("Возникла ошибка!!");
                }
            } else {
                $this->flash->error("Возникла ошибка, сервис недоступен, повторите попытку!!");
                $this->logAction('Сервис КАП ответил с ошибкой: ' . $requestTypeCode . ' - ' . $req_value);
            }
        }

        return $this->response->redirect("/kap_request/new/");
    }

    public function downloadXmlAction(string $file_name = NULL): Response
    {
        $this->view->disable();
        $service = new KapService();
        $xmlContent = $service->getFile($file_name);
        $response = new Response();
        $response->setContentType('application/xml', 'UTF-8');
        $response->setContent($xmlContent);
        return $response;
    }
}