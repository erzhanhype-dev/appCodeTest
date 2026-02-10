<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use UniqueContract;

class RefContractController extends ControllerBase
{

    /**
     * Index action
     */
    public function indexAction(): void
    {
        $page = $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(['u' => UniqueContract::class])
            ->where('u.id <> 0')
            ->orderBy('u.id DESC');

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => $page,
        ]);

        $this->view->setVar('page', $paginator->paginate());
    }

    public function newAction()
    {
    }

    public function editAction(int $id)
    {
        $contract = UniqueContract::findFirstById($id);
        if (!$contract) {
            $this->flash->error('Договор не найден.');
            return $this->response->redirect('/ref_contract/index/');
        }

        if (!$this->request->isPost()) {
            $this->view->setVars([
                'id' => (int)$contract->id,
                'bin' => (string)$contract->bin,
                'contract' => (string)$contract->contract,
            ]);
        }
    }


    public function createAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_contract/index/");
        }

        $binInput = trim((string)$this->request->getPost('bin', 'string', ''));
        $contract = trim((string)$this->request->getPost('contract', 'string', ''));

        if (!preg_match('/^\d{12}$/', $binInput)) {
            $this->flash->error('БИН должен содержать ровно 12 цифр.');
            return $this->response->redirect('/ref_contract/new');
        }

        $ref_contract = new UniqueContract();

        $ref_contract->bin = $binInput;
        $ref_contract->contract = $contract;

        if (!$ref_contract->save()) {
            foreach ($ref_contract->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_contract/new/");
        }

        $this->flash->success("Договор успешно создан.");
        return $this->response->redirect("/ref_contract/index/");
    }

    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_contract/index/");
        }

        $id = $this->request->getPost("id");

        $ref_contract = UniqueContract::findFirstByid($id);
        if (!$ref_contract) {
            $this->flash->error("Этот договор не существует: #" . $id);
            return $this->response->redirect("/ref_contract/index/");
        }

        $ref_contract->bin = $this->request->getPost("bin");
        $ref_contract->contract = $this->request->getPost("contract");

        if (!$ref_contract->save()) {
            foreach ($ref_contract->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_contract/edit/$ref_contract->id");
        }

        $this->flash->success("Правки в данные договора внесены.");
        return $this->response->redirect("/ref_contract/index/");
    }

    public function deleteAction($id)
    {

        $ref_contract = UniqueContract::findFirstByid($id);
        if (!$ref_contract) {
            $this->flash->error("Договор не найден.");
            return $this->response->redirect("/ref_contract/index/");
        }

        if (!$ref_contract->delete()) {
            foreach ($ref_contract->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_contract/index/");
        }

        $this->flash->success("Договор удален успешно.");
        return $this->response->redirect("/ref_contract/index/");
    }
}
