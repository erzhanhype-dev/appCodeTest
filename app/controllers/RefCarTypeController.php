<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefCarType;
use User;

class RefCarTypeController extends ControllerBase
{
    /**
     * Index action
     */
    public function indexAction()
    {
        $auth = User::getUserBySession();

        $page = (int)$this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(RefCarType::class)
            ->orderBy('id DESC');

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => $page,
        ]);

        $this->view->page = $paginator->paginate();
        $this->view->auth = $auth;
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
    }

    public function editAction(int $id = 0)
    {
        $refCarType = RefCarType::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id],
        ]);

        if (!$refCarType) {
            $this->flash->error('Тип машины не найден.');
            return $this->response->redirect('ref_car_type/index');
        }

        $this->view->setVars([
            'id' => $refCarType->id,
            'name' => $refCarType->name,
        ]);
    }

    /**
     * Creates a new ref_car_type
     */
    public function createAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_car_type/index/");
        }

        $ref_car_type = new RefCarType();

        $ref_car_type->name = $this->request->getPost("name");

        if (!$ref_car_type->save()) {
            foreach ($ref_car_type->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_car_type/new/");
        }

        $this->flash->success("Тип машины создан успешно.");
        return $this->response->redirect("/ref_car_type/index/");
    }

    /**
     * Saves a ref_car_type edited
     *
     */
    public function saveAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_car_type/index/");
        }

        $id = $this->request->getPost("id");
        $ref_car_type = RefCarType::findFirstById($id);

        if (!$ref_car_type) {
            $this->flash->error("Тип машины не существует: " . $id);
            return $this->response->redirect("/ref_car_type/index/");
        }

        $ref_car_type->name = $this->request->getPost("name");

        if (!$ref_car_type->save()) {
            foreach ($ref_car_type->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_car_type/edit/$ref_car_type->id");
        }

        $this->flash->success("Тип машины изменен успешно.");
        return $this->response->redirect("/ref_car_type/index/");
    }

    /**
     * Deletes a ref_car_type
     *
     * @param string $id
     */
    public function deleteAction($id)
    {

        $ref_car_type = RefCarType::findFirstByid($id);
        if (!$ref_car_type) {
            $this->flash->error("Тип машины не найден.");
            return $this->response->redirect("/ref_car_type/index/");
        }

        if (!$ref_car_type->delete()) {
            foreach ($ref_car_type->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_car_type/index/");
        }

        $this->flash->success("Тип машины удален успешно.");
        return $this->response->redirect("/ref_car_type/index/");
    }

}
