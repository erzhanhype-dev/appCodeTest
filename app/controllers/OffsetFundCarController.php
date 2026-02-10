<?php

namespace App\Controllers;

use App\Services\OffsetFund\Dto\CreateOffsetFundCarDto;
use App\Services\OffsetFund\OffsetFundCarService;
use App\Exceptions\AppException;
use ControllerBase;
use Phalcon\Http\ResponseInterface;
use User;

class OffsetFundCarController extends ControllerBase
{
    protected OffsetFundCarService $offset_fund_car_service;

    public function onConstruct(): void
    {
        $this->offset_fund_car_service = $this->getDI()->getShared(OffsetFundCarService::class);
    }

    public function checkAction(int $id)
    {
        try {
            $offset_fund = $this->offset_fund_car_service->getFundOrFail($id);
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
                'offset_fund'  => $view_data['offset_fund'],
                'countries'    => $view_data['countries'],
                'ref_car_cat'  => $view_data['ref_car_cat'],
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
                'id'         => $id,
                'offset_fund'=> $view_data['offset_fund'],
                'countries'  => $view_data['countries'],
                'ref_car_cat'=> $view_data['ref_car_cat'],
                'ref_car_type'=> $view_data['ref_car_type'],
            ]);

            $this->view->pick('offset_fund_car/edit');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function updateAction(int $offset_fund_car_id): ?ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/index");
        }

        try {
            $car = $this->offset_fund_car_service->getCarOrFail($offset_fund_car_id);

            $dto = CreateOffsetFundCarDto::fromRequest($this->request, (int)$car->offset_fund_id, (int)$car->id);
            $this->offset_fund_car_service->updateCar((int)$car->id, $dto);

            $this->flash->success('Транспортное средство обновлено');
            return $this->response->redirect("/offset_fund/view/{$car->offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_car/edit/{$offset_fund_car_id}");
        } catch (\Throwable $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_car/edit/{$offset_fund_car_id}");
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
}