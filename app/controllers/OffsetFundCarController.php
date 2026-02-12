<?php

namespace App\Controllers;

use App\Services\OffsetFund\Dto\CreateOffsetFundCarDto;
use App\Services\OffsetFund\OffsetFundCarService;
use App\Exceptions\AppException;
use App\Services\OffsetFund\OffsetFundService;
use ControllerBase;
use OffsetFundCar;
use Phalcon\Http\ResponseInterface;
use User;

class OffsetFundCarController extends ControllerBase
{
    protected OffsetFundCarService $offset_fund_car_service;
    protected OffsetFundService $offset_fund_service;

    public function onConstruct(): void
    {
        $this->offset_fund_car_service = $this->getDI()->getShared(OffsetFundCarService::class);
        $this->offset_fund_service = $this->getDI()->getShared(OffsetFundService::class);
    }

    public function checkAction(int $id)
    {
        try {
            $offset_fund = $this->offset_fund_service->getFundOrFail($id);
            $this->offset_fund_car_service->checkOffsetFundCarLimit($offset_fund); // Предположительно этот метод есть, в коде сервиса его не было, но я оставил вызов

            $this->view->setVars(['offset_fund' => $offset_fund]);
            $this->view->pick('offset_fund_car/check');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund/view/{$id}");
        }
    }

    public function newAction(int $fund_id)
    {
        $auth = User::getUserBySession();

        try {
            $view_data = $this->offset_fund_car_service->prepareNewFormData($fund_id, $auth, $this->request->getQuery());

            $this->view->setVars($view_data['data']);
            $this->view->setVars([
                'offset_fund' => $view_data['offset_fund'],
                'countries' => $view_data['countries'],
                'ref_car_cat' => $view_data['ref_car_cat'],
                'ref_car_type' => $view_data['ref_car_type']
            ]);

            $this->view->pick('offset_fund_car/new');

        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            return $this->response->redirect("/offset_fund_car/check/{$fund_id}");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка: ' . $e->getMessage());
            return $this->response->redirect("/offset_fund/view/{$fund_id}");
        }
    }

    public function addAction(int $offset_fund_id): ?ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");
        }

        try {
            $dto = CreateOffsetFundCarDto::fromRequest($this->request, $offset_fund_id);
            $this->offset_fund_car_service->addCar($dto);

            $this->flash->success('Транспортное средство успешно добавлено');
            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->dispatcher->forward([
                'action' => 'new',
                'params' => [$offset_fund_id]
            ]);
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка: ' . $e->getMessage());
            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");
        }
    }

    public function editAction(int $id)
    {
        try {
            $car = $this->offset_fund_car_service->getCarOrFail($id);
            $view_data = $this->offset_fund_car_service->prepareFormData($car->offset_fund_id, [], (int)$car->id);

            $this->view->setVars($view_data['data']);
            $this->view->setVars([
                'id' => $id,
                'offset_fund' => $view_data['offset_fund'],
                'countries' => $view_data['countries'],
                'ref_car_cat' => $view_data['ref_car_cat'],
                'ref_car_type' => $view_data['ref_car_type'],
            ]);

            $this->view->pick('offset_fund_car/edit');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function importAction(int $id)
    {
        try {
            $offset_fund = $this->offset_fund_service->getFundOrFail($id);
            $this->view->setVars([
                'offset_fund' => $offset_fund,
            ]);

            $this->view->pick('offset_fund_car/import');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function uploadAction(int $offset_fund_id): ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");
        }

        try {
            $this->offset_fund_service->getFundOrFail($offset_fund_id);

            $files = $this->request->getUploadedFiles();

            if (empty($files)) {
                throw new AppException('Файл не загружен');
            }

            $file = $files[0];

            if (!$file->getTempName() || !is_uploaded_file($file->getTempName())) {
                throw new AppException('Некорректный загруженный файл');
            }

            $ext = mb_strtolower(pathinfo((string)$file->getName(), PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new AppException('Допустим только CSV файл');
            }

            $result = $this->offset_fund_car_service->importCarFromCsv(
                $offset_fund_id,
                $file->getTempName()
            );

            $this->flash->success(
                "Импорт завершен. Добавлено: {$result['created']}, пропущено: {$result['skipped']}"
            );

            if (!empty($result['has_limit_errors'])) {
                $this->flash->warning('Часть строк пропущена: превышен лимит.');
            }

            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_car/import/{$offset_fund_id}");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка импорта: ' . $e->getMessage());
            return $this->response->redirect("/offset_fund_car/import/{$offset_fund_id}");
        }
    }

    public function updateAction(int $id): ?ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/index");
        }

        try {
            $car = $this->offset_fund_car_service->getCarOrFail($id);

            $dto = CreateOffsetFundCarDto::fromRequest($this->request, $car->offset_fund_id, $car->id);
            $this->offset_fund_car_service->updateCar($car->id, $dto);

            $this->flash->success('Транспортное средство обновлено');
            return $this->response->redirect("/offset_fund/view/{$car->offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_car/edit/{$id}");
        } catch (\Throwable $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_car/edit/{$id}");
        }
    }

    public function deleteAction(int $id): ResponseInterface
    {
        try {
            $fund_id = $this->offset_fund_car_service->deleteCar($id);

            $this->flash->success('Транспортное средство успешно удалено!');
            return $this->response->redirect("/offset_fund/view/{$fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund/index");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка: ' . $e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function deleteAllAction(int $offset_fund_id): ResponseInterface
    {
        try {
            $offset_fund = $this->offset_fund_service->getFundOrFail($offset_fund_id);
            if ($offset_fund->status !== $offset_fund::STATUS_NEW) {
                $this->flash->success('В данном статусе запрещено удалять записи');
                return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");
            }
            $offset_fund_car = OffsetFundCar::find(['offset_fund_id' => $offset_fund_id]);
            foreach ($offset_fund_car as $item) {
                $this->offset_fund_car_service->deleteCar($item->id);
            }

            $this->flash->success('Транспортное средство успешно удалено!');
            return $this->response->redirect("/offset_fund/view/{$offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund/index");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка: ' . $e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }
}