<?php

namespace App\Controllers;

use App\Services\OffsetFund\Dto\CreateOffsetFundGoodsDto;
use App\Services\OffsetFund\OffsetFundGoodsService;
use App\Exceptions\AppException;
use App\Services\OffsetFund\OffsetFundService;
use ControllerBase;
use OffsetFundGoods;
use Phalcon\Http\ResponseInterface;
use RefCountry;
use RefTnCode;
use User;

class OffsetFundGoodsController extends ControllerBase
{
    protected OffsetFundGoodsService $offset_fund_goods_service;
    protected OffsetFundService $offset_fund_service;

    public function onConstruct(): void
    {
        $this->offset_fund_service = $this->getDI()->getShared(OffsetFundService::class);
        $this->offset_fund_goods_service = $this->getDI()->getShared(OffsetFundGoodsService::class);
    }

    public function newAction(int $fund_id)
    {
        try {
            $offset_fund = $this->offset_fund_service->getFundOrFail($fund_id);
            $this->view->setVars([
                'ref_tn_code_id'=> null,
                'ref_country_id'=> null,
                'weight'=> null,
                'basis'=> null,
                'basis_at'=> null,
                'offset_fund'  => $offset_fund,
                'countries'    =>  RefCountry::find(['id NOT IN (1, 201)']),
                'ref_tn_code' => RefTnCode::find([
                    'conditions' => 'code IN ({codes:array})',
                    'bind'       => [
                        'codes' => FUND_GOODS_TIRES
                    ]
                ])
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

            $this->flash->success('Товар успешно добавлен');
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
            $goods = $this->offset_fund_goods_service->getGoodsOrFail($id);
            $offset_fund = $this->offset_fund_service->getFundOrFail($goods->offset_fund_id);

            $this->view->setVars([
                'id'         => $id,
                'ref_tn_code_id'=> $goods->ref_tn_code_id,
                'ref_country_id'=> $goods->ref_country_id,
                'weight'=> $goods->weight,
                'basis'=> $goods->basis,
                'basis_at'=> $goods->basis_at,
                'offset_fund'  => $offset_fund,
                'countries'    =>  RefCountry::find(['id NOT IN (1, 201)']),
                'ref_tn_code' => RefTnCode::find([
                    'conditions' => 'code IN ({codes:array})',
                    'bind'       => [
                        'codes' => FUND_GOODS_TIRES
                    ]
                ])
            ]);

            $this->view->pick('offset_fund_goods/edit');

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/offset_fund/index');
        }
    }

    public function updateAction(int $id): ?ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/offset_fund/index");
        }

        try {
            $goods = $this->offset_fund_goods_service->getGoodsOrFail($id);

            $dto = CreateOffsetFundGoodsDto::fromRequest($this->request, $goods->offset_fund_id, $goods->id);
            $this->offset_fund_goods_service->updateGoods($goods->id, $dto);

            $this->flash->success('Товар обновлен');
            return $this->response->redirect("/offset_fund/view/{$goods->offset_fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_goods/edit/{$id}");
        } catch (\Throwable $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund_goods/edit/{$id}");
        }
    }

    public function deleteAction(int $id): ResponseInterface
    {
        try {
            $fund_id = $this->offset_fund_goods_service->deleteGoods($id);

            $this->flash->success('Товар успешно удален!');
            return $this->response->redirect("/offset_fund/view/{$fund_id}");

        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/offset_fund/index");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка: ' . $e->getMessage());
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

            $this->view->pick('offset_fund_goods/import');

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

            $result = $this->offset_fund_goods_service->importGoodsFromCsv(
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
            return $this->response->redirect("/offset_fund_goods/import/{$offset_fund_id}");
        } catch (\Throwable $e) {
            $this->flash->error('Ошибка импорта: ' . $e->getMessage());
            return $this->response->redirect("/offset_fund_goods/import/{$offset_fund_id}");
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
            $offset_fund_goods = OffsetFundGoods::find(['offset_fund_id' => $offset_fund_id]);
            foreach ($offset_fund_goods as $item) {
                $this->offset_fund_goods_service->deleteGoods($item->id);
            }

            $this->flash->success('Товар успешно удален!');
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