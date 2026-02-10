<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefBank;
use User;

class RefBankController extends ControllerBase
{

    public function indexAction()
    {
        $auth = User::getUserBySession();
        $_SESSION['ref_bank_bik']   = '';
        $_SESSION['ref_bank_title'] = '';

        if ($this->request->isPost()) {
            $bik   = (string) $this->request->getPost('bik', 'string', '');
            $title = (string) $this->request->getPost('title', 'string', '');
            $reset = (string) $this->request->getPost('reset', 'string', '');

            if ($reset === 'all') {
                $_SESSION['ref_bank_bik']   = '';
                $_SESSION['ref_bank_title'] = '';
            } else {
                if ($bik   !== '') $_SESSION['ref_bank_bik']   = $bik;
                if ($title !== '') $_SESSION['ref_bank_title'] = $title;
            }
        }

        $s_bik  = (string) ($_SESSION['ref_bank_bik']   ?? '');
        $p_name = (string) ($_SESSION['ref_bank_title'] ?? '');

        $numberPage = (int) $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(RefBank::class)
            ->where('id <> 0')
            ->orderBy('id DESC');

        if ($p_name !== '') {
            $builder->andWhere('name LIKE :name:', ['name' => '%' . $p_name . '%']);
        }
        if ($s_bik !== '') {
            $builder->andWhere('bik LIKE :bik:', ['bik' => '%' . $s_bik . '%']);
        }

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => 10,
            'page'    => $numberPage,
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

    /**
     * Edits a ref_bank
     *
     * @param string $id
     */
    public function editAction(int $id = 0): ?ResponseInterface
    {
        if ($id <= 0) {
            $this->flash->error('Неверный идентификатор.');
            return $this->response->redirect('ref_bank/index');
        }

        // ищем запись безопасно (без магии findFirstByid)
        /** @var RefBank|null $refBank */
        $refBank = RefBank::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => $id],
        ]);

        if (!$refBank) {
            $this->response->setStatusCode(404, 'Not Found');
            $this->flash->error('Банк не найден.');
            return $this->response->redirect('ref_bank/index');
        }

        // GET: показать форму
        if (!$this->request->isPost()) {
            $this->view->setVars([
                'id'   => $refBank->id,
                'bik'  => $refBank->bik,
                'name' => $refBank->name,
            ]);
            return null; // рендерим вид
        }

        // POST: сохранить изменения
        if (!$this->security->checkToken()) {
            $this->flash->error('CSRF токен недействителен.');
            return $this->response->redirect('ref_bank/edit/' . $id);
        }

        $bik  = (string) $this->request->getPost('bik',  'string', '');
        $name = (string) $this->request->getPost('name', 'string', '');

        $refBank->bik  = $bik;
        $refBank->name = $name;

        if ($refBank->save() === false) {
            foreach ($refBank->getMessages() as $msg) {
                $this->flash->error($msg->getMessage());
                $this->logAction($msg->getMessage());
            }
            return $this->response->redirect('ref_bank/edit/' . $id);
        }

        $this->flash->success('Банк обновлён.');
        $this->logAction('Банк обновлён.');
        return $this->response->redirect('ref_bank/index');
    }

    /**
     * Creates a new ref_bank
     */
    public function createAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_bank/index/");
        }

        $ref_bank = new RefBank();

        $ref_bank->bik = $this->request->getPost("bik");
        $ref_bank->name = $this->request->getPost("name");

        if (!$ref_bank->save()) {
            foreach ($ref_bank->getMessages() as $message) {
                $this->flash->error($message);
                $this->logAction($message);
            }
            return $this->response->redirect("/ref_bank/new/");
        }

        $this->flash->success("Банк успешно создан.");
        $this->logAction("Банк успешно создан.");
        return $this->response->redirect("/ref_bank/index/");
    }

    /**
     * Saves a ref_bank edited
     *
     */
    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_bank/index/");
        }

        $id = $this->request->getPost("id");

        $ref_bank = RefBank::findFirstByid($id);
        if (!$ref_bank) {
            $this->flash->error("Этот банк не существует: #" . $id);
            return $this->response->redirect("/ref_bank/index/");
        }

        $ref_bank->bik = $this->request->getPost("bik");
        $ref_bank->name = $this->request->getPost("name");

        if (!$ref_bank->save()) {
            foreach ($ref_bank->getMessages() as $message) {
                $this->flash->error($message);
                $this->logAction($message);
            }
            return $this->response->redirect("/ref_bank/edit/$ref_bank->id");
        }

        $this->flash->success("Правки в данные банка внесены.");
        $this->logAction("Правки в данные банка внесены.");

        return $this->response->redirect("/ref_bank/index/");
    }

    /**
     * Deletes a ref_bank
     *
     * @param string $id
     */
    public function deleteAction($id)
    {

        $ref_bank = RefBank::findFirstByid($id);
        if (!$ref_bank) {
            $this->flash->error("Банк не найден.");
            return $this->response->redirect("ref_bank/index");
        }

        if (!$ref_bank->delete()) {
            foreach ($ref_bank->getMessages() as $message) {
                $this->flash->error($message);
                $this->logAction($message);
            }
            return $this->response->redirect("ref_bank/index");
        }

        $this->flash->success("Банк удален успешно.");
        $this->logAction("Банк удален: " . $id);
        return $this->response->redirect("ref_bank/index");
    }
}
