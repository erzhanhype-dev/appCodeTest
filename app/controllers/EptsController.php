<?php

namespace App\Controllers;

use Car;
use ControllerBase;
use EptsRequest;
use User;

class EptsController extends ControllerBase
{

    public function indexAction()
    {
        $auth = User::getUserBySession();

        $req_id = $_SESSION['epts_request_id'] ?? null;
        $uniqueNumber = $_SESSION['epts_uniqueNumber'] ?? null;
        $base_on = $_SESSION['epts_base_on'] ?? null;
        $type = $_SESSION['epts_operationType'] ?? null;

        $current_request = $req_id ? EptsRequest::findFirstById($req_id) : null;

        $this->view->setVars([
            'unique_number' => $uniqueNumber,
            'base_on' => $base_on,
            'current_request' => $current_request,
            'type' => $type,
            'auth' => $auth
        ]);
    }

    public function sendRequestAction()
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        $_SESSION['epts_uniqueNumber'] = NULL;
        $_SESSION['epts_operationType'] = NULL;
        $_SESSION['epts_base_on'] = NULL;
        $_SESSION['epts_request_id'] = NULL;

        if ($this->request->isPost()) {

            $uniqueNumber = trim($this->request->getPost("uniqueNumber"));
            $operationType = $this->request->getPost("type");
            $base_on = $this->request->getPost("base_on");

            $_SESSION['epts_uniqueNumber'] = $uniqueNumber;
            $_SESSION['epts_operationType'] = $operationType;
            $_SESSION['epts_base_on'] = $base_on;

            $epts = __getDataFromEpts($uniqueNumber, $operationType, $auth->id, $base_on, 1);
            $_SESSION['epts_request_id'] = $epts['EPTS_REQUEST_ID'];
            $_SESSION['epts_pdf_base64'] = $epts['EPTS_PDF_BASE64'];
            $_SESSION['epts_image_base64'] = $epts['EPTS_IMAGE_BASE64'];

            $this->logAction("Запрос в ЭПТС: {$uniqueNumber}");

            if ($epts['ERRORS'] != NULL) {
                $this->flash->error($epts['ERRORS']);
            }

        }
    }

    public function resetPageAction()
    {
        $this->view->disable();
        unset($_SESSION['epts_uniqueNumber'], $_SESSION['epts_operationType'],
            $_SESSION['epts_base_on'], $_SESSION['epts_request_id']);
        return $this->response->redirect("/epts/index");
    }

    public function downloadAction($id)
    {
        $this->view->disable();
        $request = EptsRequest::findFirstById($id);

        $this->logAction("Генерация справки ЭПТС");
        if ($request) EptsRequest::genDoc($request);
    }

    public function viewXmlAction(string $file_name)
    {
        $this->view->disable();
        $file_path = APP_PATH . "/storage/logs/epts_logs/$file_name";
        $auth = User::getUserBySession();

        if (!file_exists($file_path)) {
            echo "<h2>Файл не найден! </h2>";
            die();
        }

        if (!$auth->isAdminSoft()) {
            $this->logAction('У вас нет прав на это действие!', 'security', 'ALERT');
            echo "<h2>У вас нет прав на это действие!</h2>";
            die();
        }

        __downloadFile($file_path, '.xml', 'view');
    }


    public function getXmlContentAction(string $response_xml_file_name)
    {
        $this->view->disable();
        $folder = APP_PATH . "/storage/logs/epts_logs/";
        $response_file_path = $folder . $response_xml_file_name;

        if (file_exists($response_file_path)) {
            $xml_response = file_get_contents($response_file_path);
            if (__isValidXml($xml_response)) {
                return $xml_response;
            }
        }

        return false;
    }

    public function downloadXmlAction(string $file_name)
    {
        $this->view->disable();
        $file_path = APP_PATH . "/storage/logs/epts_logs/$file_name";
        $auth = User::getUserBySession();

        if (!file_exists($file_path)) {
            echo "<h2>Файл не найден! </h2>";
            die();
        }

        if (!$auth->isAdmin()) {
            $this->logAction('У вас нет прав на это действие!', 'security', 'ALERT');
            echo "<h2>У вас нет прав на это действие!</h2>";
            die();
        }

        __downloadFile($file_path);
    }

    public function getListAction(string $uniqueNumber = NULL)
    {

        $this->view->disable();
        $data = array();

        if ($uniqueNumber != NULL) {
            $sql = <<<SQL
            SELECT id, request_num, request_time, operation_type, status_code, green_response, created_at
            FROM EptsRequest
            WHERE
              '$uniqueNumber' IN (request_num, vin, unique_code)
            ORDER BY id DESC
          SQL;

            $query = $this->modelsManager->createQuery($sql);
            $epts = $query->execute();

            if (count($epts) > 0) {
                foreach ($epts as $val) {

                    $status_code = ($val->status_code == 200) ?
                        '<span class="badge badge-success mb-2" style="font-size: 12px;">Найден</span>' :
                        '<span class="badge badge-danger mb-2" style="font-size: 12px;">Не найден</span>';

                    $data[] = [
                        "id" => $val->id,
                        "request_time" => ($val->request_time > 0) ? date("Y-m-d H:i", $val->request_time) : date("Y-m-d H:i", $val->created_at),
                        "request_num" => $val->request_num,
                        "operation_type" => ($val->operation_type == 1) ? "По уникальному номеру" : "По VIN-коду",
                        "status_code" => $status_code,
                        "green_response" => $val->green_response,
                        "code" => $val->status_code,
                    ];
                }
            }
        }

        if (is_array($data) && count($data) > 0) {
            $json_data = array(
                "draw" => 1,
                "recordsTotal" => intval(count($data)),
                "recordsFiltered" => intval(count($data)),
                "data" => $data,
            );
            http_response_code(200);
            return json_encode($json_data);
        } else {
            $json_data = array(
                "draw" => 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
            );
            http_response_code(200);
            return json_encode($json_data);
        }
    }

    public function viewEptsPdfAction(string $xml_file_name = NULL)
    {
        $this->view->disable();
        $pdf = NULL;
        header('Content-Type: application/pdf');

        if ($xml_file_name != NULL) {
            $epts = EptsRequest::greenResponseParser($xml_file_name);

            if (isset($epts['pdf_base64']) && $epts['pdf_base64'] != NULL) {
                $pdf = '<iframe src="data:application/pdf;base64,' . $epts['pdf_base64'] . '" style="width:100%; height: 750px; border: none"></iframe>';
            } else {
                $pdf = '<span class="badge badge-danger mb-2" style="font-size: 14px;">Файл не найден</span>';
            }
        }

        return $pdf;
    }

    public function viewEptsImageAction(string $xml_file_name = NULL)
    {
        $this->view->disable();
        $image = NULL;

        if ($xml_file_name != NULL) {
            $epts = EptsRequest::greenResponseParser($xml_file_name);

            if (isset($epts['image_base64']) && $epts['image_base64'] != NULL) {
                $image = '<iframe src="' . $epts['image_base64'] . '" style="width:100%; height: 750px; border: none"></iframe>';
            } else {
                $image = '<span class="badge badge-danger mb-2" style="font-size: 14px;">Файл не найден</span>';
            }
        }

        return $image;
    }

    public function getEPTSInfoAction(string $req = NULL)
    {
        $this->view->disable();
        $epts_info = '';
        $file_name = $req;
        if ($req != NULL) {
            if (ctype_digit($req)) {
                $car_id = (int)$req;
                $car = Car::findFirst($car_id);
                $epts_request = EptsRequest::findFirst($car->epts_request_id);
                $epts_info = isset($epts_request->green_response) ? EptsRequest::returnEPTSInfoAsTable($epts_request->green_response) : '';

            } else {
                $epts_info = EptsRequest::returnEPTSInfoAsTable($file_name);
            }
        }

        return $epts_info;
    }
}
