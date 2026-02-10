<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefCarCat;
use RefModel;
use User;

class RefCarModelController extends ControllerBase
{

    public function indexAction()
    {
        $auth = User::getUserBySession();

        $this->view->setVar('categories', RefCarCat::find());

        if ($this->request->isPost()) {
            $brand = trim((string)$this->request->getPost('brand', 'string', ''));
            $model = trim((string)$this->request->getPost('model', 'string', ''));
            $catId = (string)$this->request->getPost('ref_car_cat_id', 'string', '');
            $clear = (string)$this->request->getPost('clear', 'string', '');

            if ($clear === 'clear') {
                $_SESSION['car_filter_brand'] = '';
                $_SESSION['car_filter_model'] = '';
                $_SESSION['car_filter_id'] = '';
            } else {
                if ($brand !== '') $_SESSION['car_filter_brand'] = $brand;
                if ($model !== '') $_SESSION['car_filter_model'] = $model;
                if ($catId !== '') $_SESSION['car_filter_id'] = $catId;
            }
        }

        $brand = (string)($_SESSION['car_filter_brand'] ?? '');
        $model = (string)($_SESSION['car_filter_model'] ?? '');
        $catId = (string)($_SESSION['car_filter_id'] ?? '');

        $like = static function (string $s): string {
            return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s) . '%';
        };

        $page = (int)$this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'id' => 'rf.id',
                'brand' => 'rf.brand',
                'model' => 'rf.model',
                'category' => 'rcc.name',
            ])
            ->from(['rf' => RefModel::class])
            ->join(RefCarCat::class, 'rf.ref_car_cat_id = rcc.id', 'rcc')
            ->orderBy('rf.id DESC');

        if ($brand !== '') {
            $builder->andWhere('rf.brand LIKE :brand:', ['brand' => $like($brand)]);
        }
        if ($model !== '') {
            $builder->andWhere('rf.model LIKE :model:', ['model' => $like($model)]);
        }
        if ($catId !== '' && $catId !== 'all') {
            $builder->andWhere('rf.ref_car_cat_id = :catId:', ['catId' => (int)$catId]);
        }

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => $page,
        ]);

        $this->view->setVars([
            'f_brand' => $brand,
            'f_model' => $model,
            's_cat_id' => $catId ?: 'all',
            'page' => $paginator->paginate(),
            'auth' => $auth,
        ]);
    }


    /**
     * Displays the creation form
     */
    public function newAction()
    {
        $categories = RefCarCat::find();

        $this->view->setVars(array(
            "categories" => $categories
        ));
    }

    public function editAction(int $id = 0)
    {
        // категории для списка
        $this->view->setVar('categories', RefCarCat::find());

        // проверяем корректность id
        if ($id <= 0) {
            $this->flash->error('Неверный идентификатор.');
            return $this->response->redirect('ref_car_model/index');
        }

        // ищем запись
        $refCarModel = RefModel::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => $id],
        ]);

        if (!$refCarModel) {
            $this->flash->error('Модель машины не найдена.');
            return $this->response->redirect('ref_car_model/index');
        }

        // только GET: передаём значения во view
        if (!$this->request->isPost()) {
            $this->view->setVars([
                'id'             => $refCarModel->id,
                'brand'          => $refCarModel->brand,
                'model'          => $refCarModel->model,
                'ref_car_cat_id' => $refCarModel->ref_car_cat_id,
            ]);
        }
    }

    /**
     * Creates a new ref_car_model
     */
    public function createAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_car_model/index/");
        }

        $ref_car_model = new RefModel();
        $ref_car_model->brand = $this->request->getPost("brand");
        $ref_car_model->model = $this->request->getPost("model");
        $ref_car_model->ref_car_cat_id = $this->request->getPost("car_cat");

        if (!$ref_car_model->save()) {
            foreach ($ref_car_model->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_car_model/new/");
        }

        $this->flash->success("Модель машины создан успешно.");
        return $this->response->redirect("/ref_car_model/index/");
    }

    /**
     * Saves a ref_car_model edited
     *
     */
    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_car_model/index/");
        }

        $id = $this->request->getPost("id");

        $ref_car_model = RefModel::findFirstByid($id);
        if (!$ref_car_model) {
            $this->flash->error("Модель машины не существует: " . $id);
            return $this->response->redirect("/ref_car_model/index/");
        }

        $ref_car_model->id = $this->request->getPost("id");
        $ref_car_model->brand = $this->request->getPost("brand");
        $ref_car_model->model = $this->request->getPost("model");
        $ref_car_model->ref_car_cat_id = $this->request->getPost("ref_car_cat_id");

        if (!$ref_car_model->save()) {
            foreach ($ref_car_model->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_car_model/edit/$ref_car_model->id");
        }

        $this->flash->success("Модель машины изменен успешно.");
        return $this->response->redirect("/ref_car_model/index/");
    }

    /**
     * Deletes a ref_car_model
     *
     * @param string $id
     */
    public function deleteAction($id)
    {

        $ref_car_model = RefModel::findFirstByid($id);
        if (!$ref_car_model) {
            $this->flash->error("Модель не найден.");
            return $this->response->redirect("/ref_car_model/index/");
        }

        if (!$ref_car_model->delete()) {
            foreach ($ref_car_model->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_car_model/index/");
        }
        $this->flash->success("Модель ТС удален успешно.");
        return $this->response->redirect("/ref_car_model/index/");
    }

}
