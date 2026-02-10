<?php

/*******************************************************************************
 * Модуль управления списком документов.
 *******************************************************************************/

use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

class DocsController extends ControllerBase
{
    /**
     * Индексный файл.
     */
    public function indexAction(): void
    {
        // Номер страницы из GET-параметра, по умолчанию 1
        $numberPage = $this->request->getQuery('page', 'int', 1);

        // Настройка пагинатора
        $paginator = new PaginatorModel([
            'model' => Docs::class,   // указываем имя модели
            'parameters' => [],       // можно добавить условия find()
            'limit' => 10,
            'page'  => $numberPage,
        ]);

        // В Phalcon 5.9 метод называется paginate()
        $page = $paginator->paginate();

        if ($page->getTotalItems() === 0) {
            $this->flash->notice("Ничего не найдено.");
        }

        // Передаём результат во view
        $this->view->page = $page;
    }

    /**
     * Создание документа.
     */
    public function newAction()
    {
    }

    /**
     * Правка документа.
     *
     * @param string $id
     */
    public function editAction($id)
    {
        if (!$this->request->isPost()) {
            $doc = Docs::findFirstByid($id);
            if (!$doc) {
                $this->flash->error("Документ не найден.");

                $this->dispatcher->forward(array(
                    'controller' => "docs",
                    'action' => 'index'
                ));

                return;
            }

            $this->view->id = $doc->id;

            $this->tag->setDefault("id", $doc->id);
            $this->tag->setDefault("title", $doc->title);
            $this->tag->setDefault("title_kk", $doc->title_kk);
            $this->tag->setDefault("link", $doc->link);
        }
    }

    /**
     * Новый документ.
     */
    public function createAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/docs/index/");
        }

        $doc = new Docs();
        $doc->title = $this->request->getPost("title");
        $doc->title_kk = $this->request->getPost("title_kk");
        $doc->link = $this->request->getPost("link");

        if (!$doc->save()) {
            foreach ($doc->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/docs/new/");
        }

        if ($this->request->hasFiles()) {
            foreach ($this->request->getUploadedFiles() as $key => $file) {
                $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                if ($file->getKey() == 'preview') {
                    $file->moveTo(APP_PATH . "/public/docs-preview/" . $doc->id . "." . $ext);
                } else {
                    $file->moveTo(APP_PATH . "/public/docs-files/" . $doc->id . "." . $ext);
                }
            }
        }

        $this->flash->success("Документ создан.");
        return $this->response->redirect("/docs/index/");
    }

    /**
     * Сохранение отредактированного документа.
     */
    public function saveAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/docs/index/");
        }

        $id = $this->request->getPost("id");
        $doc = Docs::findFirstByid($id);

        if (!$doc) {
            $this->flash->error("Документ " . $id . " не существует.");
            return $this->response->redirect("/docs/index/");
        }

        $doc->title = $this->request->getPost("title");
        $doc->title_kk = $this->request->getPost("title_kk");
        $doc->link = $this->request->getPost("link");

        if (!$doc->save()) {
            foreach ($doc->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/docs/edit/$doc->id");
        }

        if ($this->request->hasFiles()) {
            foreach ($this->request->getUploadedFiles() as $key => $file) {
                $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                if ($file->getKey() == 'preview') {
                    $file->moveTo(APP_PATH . "/public/docs-preview/" . $doc->id . "." . $ext);
                } else {
                    $file->moveTo(APP_PATH . "/public/docs-files/" . $doc->id . "." . $ext);
                }
            }
        }

        $this->flash->success("Документ сохранен.");
        return $this->response->redirect("/docs/index/");
    }

    /**
     * Удаление документа.
     *
     * @param string $id
     */
    public function deleteAction($id)
    {
        $doc = Docs::findFirstByid($id);
        if (!$doc) {
            $this->flash->error("Документ не найден.");

            $this->dispatcher->forward(array(
                'controller' => "docs",
                'action' => 'index'
            ));

            return;
        }

        if (!$doc->delete()) {
            foreach ($doc->getMessages() as $message) {
                $this->flash->error($message);
            }

            $this->dispatcher->forward(array(
                'controller' => "docs",
                'action' => 'search'
            ));

            return;
        }

        $v = array('files', 'preview');

        foreach ($v as $value) {
            $d = opendir(APP_PATH . '/public/docs-' . $value);
            while ($file = readdir($d)) {
                if ($file != '.' && $file != '..') {
                    if ($id == substr($file, 0, -4)) {
                        @unlink(APP_PATH . '/public/docs-' . $value . '/' . $file);
                    }
                }
            }
        }

        $this->flash->success("Документ удален.");
        return $this->response->redirect("/docs/index/");
    }

    /**
     * Очистка загруженных к документу файлов.
     * @param int $doc_id
     * @return \Phalcon\Http\ResponseInterface
     */
    public function purgeAction(int $doc_id): ResponseInterface
    {
        $v = array('files', 'preview');

        foreach ($v as $value) {
            $d = opendir(APP_PATH . '/public/docs-' . $value);
            while ($file = readdir($d)) {
                if ($file != '.' && $file != '..') {
                    if ($doc_id == substr($file, 0, -4)) {
                        @unlink(APP_PATH . '/public/docs-' . $value . '/' . $file);
                    }
                }
            }
        }

        $this->flash->success("Документ очищен от файлов.");
        return $this->response->redirect("/docs/index/");
    }

}