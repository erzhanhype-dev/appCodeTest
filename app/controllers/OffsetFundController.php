<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\OffsetFund\Dto\CreateOffsetFundDto;
use App\Services\OffsetFund\OffsetFundService;
use ControllerBase;
use OffsetFund;
use OffsetFundFile;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use User;

class OffsetFundController extends ControllerBase
{
    protected OffsetFundService $offset_fund_service;

    public function onConstruct(): void
    {
        $this->offset_fund_service = $this->getDI()->getShared(OffsetFundService::class);
    }

    public function indexAction(): void
    {
        $limit = $this->request->getQuery("limit", "int", 10);
        $page = $this->request->getQuery("page", "int", 1);

        $filters = [
            'id' => $this->request->getQuery("id", "int"),
            'status' => $this->request->getQuery("status", "string"),
            'search' => $this->request->getQuery("search", "string"),
            'year' => $this->request->getQuery("year", "string")
        ];

        $paginator = $this->offset_fund_service->getPaginator($filters, $limit, $page);

        $this->view->setVars([
            'limit' => $limit,
            'page' => $paginator,
            'statuses' => OffsetFund::getStatusList()
        ]);
    }

    public function newAction()
    {
        $auth = User::getUserBySession();

        try {
            $view_data = $this->offset_fund_service->prepareCreateForm($auth, $this->request->getQuery());

            if (!empty($view_data['warning_msg'])) {
                $this->flash->warning($view_data['warning_msg']);
            }
            if (!empty($view_data['error_msg'])) {
                $this->flash->error($view_data['error_msg']);
            }

            $this->view->setVars($view_data);

        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            return $this->response->redirect("/offset_fund/new");
        } catch (\Exception $e) {
        }
    }

    public function addAction(): ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('offset_fund/index');
        }

        $auth = User::getUserBySession();

        try {
            $dto = CreateOffsetFundDto::fromRequest($this->request, $auth->id);

            $offset_fund = $this->offset_fund_service->createFund($dto, $auth);

            $this->flash->success("Заявка успешно создана");
            return $this->response->redirect("offset_fund/view/{$offset_fund->id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());

            $redirect_params = [
                'object' => $this->request->getPost('entity_type'),
                'type' => $this->request->getPost('type'),
                'total_value' => $this->request->getPost('total_value'),
                'period_start_at' => $this->request->getPost('period_start_at'),
                'period_end_at' => $this->request->getPost('period_end_at'),
                'ref_fund_key_id' => $this->request->getPost('ref_fund_key_id')
            ];

            return $this->response->redirect('offset_fund/new?' . http_build_query($redirect_params));
        }
    }

    public function viewAction($id): void
    {
        try {
            $offset_fund = OffsetFund::findFirst($id);
            $offset_fund_cars = $this->offset_fund_service->getOffsetFundCars($id);
            $offset_fund_goods = $this->offset_fund_service->getOffsetFundGoods($id);
            $offset_fund_files = $offset_fund->files;

            $uploadBaseDir = APP_PATH . '/private/offset_fund/' . (int)$offset_fund->id;

            $preparedFiles = [];

            foreach ($offset_fund_files as $file) {
                $id = (int)$file->id;
                $ext = strtolower(trim((string)$file->ext));
                $ext = preg_replace('/[^a-z0-9]+/i', '', $ext);

                $isExists = false;

                if ($ext !== '') {
                    $filename = $id . '.' . $ext;
                    $fullPath = $uploadBaseDir . '/' . $filename;

                    if (is_file($fullPath)) {
                        $isExists = true;
                    }
                }

                if (!$isExists) {
                    $candidates = glob($uploadBaseDir . '/' . $id . '.*') ?: [];
                    foreach ($candidates as $candidate) {
                        if (is_file($candidate)) {
                            $isExists = true;
                            break;
                        }
                    }
                }

                $preparedFiles[] = (object)[
                    'id' => $id,
                    'type' => (string)$file->type,
                    'original_name' => (string)($file->original_name ?? ''),
                    'ext' => (string)($file->ext ?? ''),
                    'date' => date('d.m.Y H:i:s', $file->created_at),
                    'is_exists' => $isExists,
                ];
            }

            $this->view->setVars([
                'offset_fund' => $offset_fund,
                'offset_fund_cars' => $offset_fund_cars,
                'offset_fund_goods' => $offset_fund_goods,
                'offset_fund_files' => $preparedFiles
            ]);
        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            $this->response->redirect('offset_fund/index');
        }
    }

    public function deleteAction($id): ResponseInterface
    {
        $auth = User::getUserBySession();

        try {
            $this->offset_fund_service->delete((int)$id, $auth);
            $this->flash->success("Заявка и все связанные записи успешно удалены.");
        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
        } catch (\Exception $e) {
            $this->flash->error("Произошла системная ошибка при удалении.");
        }

        return $this->response->redirect("offset_fund/index");
    }

    public function uploadFileAction(int $offset_fund_id): Response
    {
        $response = new Response();
        $response->setContentType('application/json', 'UTF-8');

        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $files = $this->request->getUploadedFiles(false);
        $doc_type = $this->request->getPost('doc_type');

        if (empty($files)) {
            $error = $_FILES['file']['error'] ?? null;

            $message = 'Файл не загружен';
            if ($error !== null) {
                $errors = [
                    UPLOAD_ERR_INI_SIZE => 'Файл превышает upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE формы',
                    UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
                    UPLOAD_ERR_NO_FILE => 'Файл не выбран',
                    UPLOAD_ERR_NO_TMP_DIR => 'Не найдена временная папка',
                    UPLOAD_ERR_CANT_WRITE => 'Ошибка записи файла на диск',
                    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP',
                ];
                $message = $errors[$error] ?? ('Ошибка загрузки файла: ' . $error);
            }

            $this->flash->error($message);
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $uploaded = $files[0];

        if (!$uploaded->getSize()) {
            $this->flash->error('Не удалось загрузить файл');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $max_size = 50 * 1024 * 1024;
        if ($uploaded->getSize() > $max_size) {
            $this->flash->error('Файл большой');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $original_name = $uploaded->getName() ?: 'UNKNOWN';
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $offset_fund_file = new OffsetFundFile();
        $offset_fund_file->offset_fund_id = $offset_fund_id;
        $offset_fund_file->original_name = $original_name;
        $offset_fund_file->ext = $extension ?: 'bin';
        $offset_fund_file->type = $doc_type;
        $offset_fund_file->visible = 1;
        $offset_fund_file->created_at = time();
        $offset_fund_file->created_by = (int)($this->session->get('auth')['id'] ?? 0);

        if (!$offset_fund_file->save()) {
            $errors = [];
            foreach ($offset_fund_file->getMessages() as $msg) {
                $errors[] = (string)$msg;
            }

            $this->flash->error('Ошибка сохранения' . implode(', ', $errors));
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $base_dir = APP_PATH . '/private/offset_fund/' . $offset_fund_id;
        if (!is_dir($base_dir) && !mkdir($base_dir, 0775, true) && !is_dir($base_dir)) {
            $offset_fund_file->delete();
            $this->flash->error('Ошибка сохранения');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        $safe_ext = preg_replace('/[^a-z0-9]+/i', '', $offset_fund_file->ext) ?: 'bin';
        $final_path = $base_dir . '/' . $offset_fund_file->id . '.' . $safe_ext;

        if (!$uploaded->moveTo($final_path)) {
            $offset_fund_file->delete();
            $this->flash->error('Ошибка сохранения');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }

        return $this->response->redirect("/offset_fund/view/$offset_fund_id");
    }

    public function deleteFileAction(int $offset_fund_id, int $offset_fund_file_id): Response
    {
        $response = new Response();
        $response->setContentType('application/json', 'UTF-8');
        $auth = User::getUserBySession();

        try {
            $offset_fund_file = OffsetFundFile::findFirst([
                'conditions' => 'id = :id: AND offset_fund_id = :offset_fund_id:',
                'bind' => [
                    'id' => $offset_fund_file_id,
                    'offset_fund_id' => $offset_fund_id,
                ],
            ]);

            if (!$offset_fund_file) {
                $this->flash->warning('Файл не найден');
                return $this->response->redirect("/offset_fund/view/$offset_fund_id");
            }

            if($offset_fund_file->created_by !== $auth->id && !$auth->isEmployee()){
                $this->flash->error('Файл не доступен');
                return $this->response->redirect("/offset_fund/view/$offset_fund_id");
            }


            $base_dir = APP_PATH . '/private/offset_fund/' . $offset_fund_id;
            $safe_ext = preg_replace('/[^a-z0-9]+/i', '', (string)$offset_fund_file->ext) ?: 'bin';
            $path = $base_dir . '/' . $offset_fund_file->id . '.' . $safe_ext;

            if (is_file($path) && !unlink($path)) {
            }

            foreach (glob($base_dir . '/' . $offset_fund_file->id . '.*') ?: [] as $p) {
                if (is_file($p)) {
                    @unlink($p);
                }
            }

            if (!$offset_fund_file->delete()) {
                $errors = [];
                foreach ($offset_fund_file->getMessages() as $msg) {
                    $errors[] = (string)$msg;
                }

                $this->flash->error(implode(', ', $errors));
                return $this->response->redirect("/offset_fund/view/$offset_fund_id");
            }

            $this->flash->success('Файл удален');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        } catch (\Throwable $e) {
            $this->flash->error('Не удалось удалить файл');
            return $this->response->redirect("/offset_fund/view/$offset_fund_id");
        }
    }

    public function viewFileAction($offset_fund_file_id)
    {
        $offset_fund_file = OffsetFundFile::findFirst($offset_fund_file_id);
        $offset_fund = OffsetFund::findFirst($offset_fund_file->offset_fund_id);
        $file_path = APP_PATH . '/private/offset_fund/' . $offset_fund->id . '/' . $offset_fund_file_id . '.' . $offset_fund_file->ext;

        $this->view->disable();
        $auth = User::getUserBySession();

        if (($offset_fund_file->created_by == $auth->id) || ($auth->isEmployee())) {
            __downloadFile($file_path, $offset_fund_file->original_name, 'view');
        } else {
            $this->flash->warning('Файл не доступен');
            return $this->response->redirect($this->request->getHTTPReferer());
        }
    }

    public function generateApplicationAction($id): ResponseInterface
    {
        $offset_fund = OffsetFund::findFirst($id);

        return $this->response->redirect("offset_fund/view/$offset_fund->id");
    }
}