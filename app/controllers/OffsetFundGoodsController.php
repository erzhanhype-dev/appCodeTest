<?php

namespace App\Controllers;

use App\Services\OffsetFund\Dto\CreateOffsetFundGoodsDto;
use App\Services\OffsetFund\OffsetFundGoodsService;
use App\Exceptions\AppException;
use ControllerBase;
use Phalcon\Http\ResponseInterface;
use RefCountry;
use User;

class OffsetFundGoodsController extends ControllerBase
{
    protected OffsetFundGoodsService $offset_fund_goods_service;

    public function onConstruct(): void
    {
        $this->offset_fund_goods_service = $this->getDI()->getShared(OffsetFundGoodsService::class);
    }

    public function newAction(int $fund_id)
    {
        try {
            $offset_fund = $this->offset_fund_goods_service->getFundOrFail($fund_id);
            $this->view->setVars([
                'offset_fund'  => $offset_fund,
                'countries'    =>  RefCountry::find(['id NOT IN (1, 201)']),
            ]);

            $this->view->pick('offset_fund_goods/new');

        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            return $this->response->redirect("/offset_fund_goods/check/{$fund_id}");
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
            $dto = CreateOffsetFundGoodsDto::fromRequest($this->request, $offset_fund_id);
            $this->offset_fund_goods_service->addGoods($dto);

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
            $goods = $this->offset_fund_goods_service->getgoodsOrFail($id);
            $view_data = $this->offset_fund_goods_service->prepareFormData($goods->offset_fund_id, [], (int)$goods->id);

            $this->view->setVars($view_data['data']);
            $this->view->setVars([
                'id'         => $id,
                'offset_fund'=> $view_data['offset_fund'],
                'countries'  => $view_data['countries'],
                'ref_goods_cat'=> $view_data['ref_goods_cat'],
                'ref_goods_type'=> $view_data['ref_goods_type'],
            ]);

            $this->view->pick('offset_fund_goods/edit');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function updateAction(int $offset_fund_goods_id): ?ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/index");
        }

        try {
            $goods = $this->offset_fund_goods_service->getGoodsOrFail($offset_fund_goods_id);

            $dto = CreateOffsetFundGoodsDto::fromRequest($this->request, (int)$goods->offset_fund_id, (int)$goods->id);
            $this->offset_fund_goods_service->updateGoods((int)$goods->id, $dto);

            $this->flash->success('Транспортное средство обновлено');
            return $this->response->redirect("/offset_fund/view/{$goods->offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_goods/edit/{$offset_fund_goods_id}");
        } catch (\Throwable $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_goods/edit/{$offset_fund_goods_id}");
        }
    }

    public function deleteAction(int $id): ResponseInterface
    {
        try {
            $fund_id = $this->offset_fund_goods_service->deleteGoods($id);

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