<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\OffsetFund\Dto\CreateOffsetFundDto;
use App\Services\OffsetFund\OffsetFundService;
use ControllerBase;
use OffsetFund;
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
            $this->view->setVars([
                'offset_fund' => $offset_fund,
                'offset_fund_cars' => $offset_fund_cars,
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

    public function generateApplicationAction($id): ResponseInterface
    {
        $offset_fund = OffsetFund::findFirst($id);
        return $this->response->redirect("offset_fund/view/$offset_fund->id");
    }
}