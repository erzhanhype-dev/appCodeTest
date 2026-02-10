<?php
namespace App\Controllers;

use ControllerBase;
use Goods;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefTnCode;

class RefTnCodeController extends ControllerBase
{

    public function indexAction()
    {
        // обработка фильтров из POST
        if ($this->request->isPost()) {
            $id     = trim((string)$this->request->getPost('num', 'string', ''));
            $code   = trim((string)$this->request->getPost('code', 'string', ''));
            $name   = trim((string)$this->request->getPost('name', 'string', ''));
            $status = trim((string)$this->request->getPost('status', 'string', 'ALL')); // ALL|YES|NO
            $type   = trim((string)$this->request->getPost('type', 'string', 'ALL'));
            $clear  = trim((string)$this->request->getPost('clear', 'string', ''));

            if ($clear === 'clear') {
                $this->session->set('ref_tn_code_id', '');
                $this->session->set('ref_tn_code_tnvd', '');
                $this->session->set('ref_tn_code_name', '');
                $this->session->set('ref_tn_code_status', 'ALL');
                $this->session->set('ref_tn_code_type', 'ALL');
                return $this->response->redirect('/ref_tn_code/');
            }

            $this->session->set('ref_tn_code_id', $id);
            $this->session->set('ref_tn_code_tnvd', $code);
            $this->session->set('ref_tn_code_name', $name);
            $this->session->set('ref_tn_code_status', $status ?: 'ALL');
            $this->session->set('ref_tn_code_type', $type ?: 'ALL');
        }

        // значения по умолчанию
        $id     = (string)($this->session->get('ref_tn_code_id')     ?? '');
        $code   = (string)($this->session->get('ref_tn_code_tnvd')   ?? '');
        $name   = (string)($this->session->get('ref_tn_code_name')   ?? '');
        $status = (string)($this->session->get('ref_tn_code_status') ?? 'ALL'); // ALL|YES|NO
        $type   = (string)($this->session->get('ref_tn_code_type')   ?? 'ALL');

        $page = $this->request->getQuery('page', 'int', 1);

        // билдер с биндингами
        $builder = $this->modelsManager->createBuilder()
            ->from(['t' => RefTnCode::class])
            ->where('t.code IS NOT NULL')
            ->orderBy('t.id DESC');

        // bind-параметры
        if ($id !== '' && ctype_digit($id)) {
            $builder->andWhere('t.id = :id:', ['id' => (int)$id]);
        }
        if ($code !== '') {
            $builder->andWhere('t.code LIKE :code:', ['code' => '%' . $code . '%']);
        }
        if ($name !== '') {
            $builder->andWhere('t.name LIKE :name:', ['name' => '%' . $name . '%']);
        }
        if ($status === 'YES') {
            $builder->andWhere('t.is_active = 1');
        } elseif ($status === 'NO') {
            $builder->andWhere('t.is_active = 0');
        }
        if ($type !== '' && $type !== 'ALL') {
            $builder->andWhere('t.type = :type:', ['type' => $type]);
        }

        // пагинация
        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => 5,
            'page'    => $page,
        ]);

        $this->view->setVars([
            'page'   => $paginator->paginate(),
            // для формы фильтров во view
            'filter' => [
                'id'     => $id,
                'code'   => $code,
                'name'   => $name,
                'status' => $status,
                'type'   => $type,
            ],
        ]);
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
    }

    /**
     * Edits a ref_tn_code
     *
     * @param string $id
     */
    public function editAction($id)
    {
        if (!$this->request->isPost()) {
            $ref_tn_code = RefTnCode::findFirstByid($id);
            if (!$ref_tn_code) {
                $this->flash->error("ref_tn_code was not found");
                return $this->response->redirect("/ref_tn_code/index/");
            }

            $this->view->id = $ref_tn_code->id;

            $this->tag->setDefault("id", $ref_tn_code->id);
            $this->tag->setDefault("code", $ref_tn_code->code);
            $this->tag->setDefault("name", $ref_tn_code->name);
            $this->tag->setDefault("group", $ref_tn_code->group);
            $this->tag->setDefault("price1", $ref_tn_code->price1);
            $this->tag->setDefault("price2", $ref_tn_code->price2);
            $this->tag->setDefault("price3", $ref_tn_code->price3);
            $this->tag->setDefault("price4", $ref_tn_code->price4);
            $this->tag->setDefault("price5", $ref_tn_code->price5);
            $this->tag->setDefault("price6", $ref_tn_code->price6);
            $this->tag->setDefault("price7", $ref_tn_code->price7);
            $this->tag->setDefault("is_active", $ref_tn_code->is_active);
            $this->tag->setDefault("is_correct", $ref_tn_code->is_correct);
            $this->tag->setDefault("type", $ref_tn_code->type);
        }
    }

    /**
     * Creates a new ref_tn_code
     */
    public function createAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_tn_code/index/");
        }

        $ref_tn_code = new RefTnCode();

        $ref_tn_code->code = $this->request->getPost("code");
        $ref_tn_code->name = $this->request->getPost("name");
        $ref_tn_code->group = $this->request->getPost("group");
        $ref_tn_code->price1 = $this->request->getPost("price1");
        $ref_tn_code->price2 = $this->request->getPost("price2");
        $ref_tn_code->price3 = $this->request->getPost("price3");
        $ref_tn_code->price4 = $this->request->getPost("price4");
        $ref_tn_code->price5 = $this->request->getPost("price5");
        $ref_tn_code->price6 = $this->request->getPost("price6");
        $ref_tn_code->price7 = $this->request->getPost("price7");

        $ref_tn_code->pay_pack = $this->request->getPost("pay_pack");
        $ref_tn_code->is_active = $this->request->getPost("is_active");
        $ref_tn_code->is_correct = $this->request->getPost("is_correct");
        $ref_tn_code->type = $this->request->getPost("type");

        if (!$ref_tn_code->save()) {
            foreach ($ref_tn_code->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_tn_code/new/");
        }

        $this->flash->success("ref_tn_code was created successfully");
        return $this->response->redirect("/ref_tn_code/index/");
    }

    /**
     * Saves a ref_tn_code edited
     *
     */
    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_tn_code/index/");
        }

        $id = $this->request->getPost("id");

        $ref_tn_code = RefTnCode::findFirstByid($id);
        if (!$ref_tn_code) {
            $this->flash->error("ref_tn_code does not exist " . $id);
            return $this->response->redirect("/ref_tn_code/index/");
        }

        $ref_tn_code->code = $this->request->getPost("code");
        $ref_tn_code->name = $this->request->getPost("name");
        $ref_tn_code->group = $this->request->getPost("group");
        $ref_tn_code->price1 = $this->request->getPost("price1");
        $ref_tn_code->price2 = $this->request->getPost("price2");
        $ref_tn_code->price3 = $this->request->getPost("price3");
        $ref_tn_code->price4 = $this->request->getPost("price4");
        $ref_tn_code->price5 = $this->request->getPost("price5");
        $ref_tn_code->price6 = $this->request->getPost("price6");
        $ref_tn_code->price7 = $this->request->getPost("price7");

        $ref_tn_code->pay_pack = $this->request->getPost("pay_pack");
        $ref_tn_code->is_active = $this->request->getPost("is_active");
        $ref_tn_code->is_correct = $this->request->getPost("is_correct");
        $ref_tn_code->type = $this->request->getPost("type");

        if (!$ref_tn_code->save()) {
            foreach ($ref_tn_code->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_tn_code/edit/$ref_tn_code->id");
        }

        $this->flash->success("ref_tn_code was updated successfully");
        return $this->response->redirect("/ref_tn_code/index/");
    }

    /**
     * Deletes a ref_tn_code
     *
     * @param string $id
     */

    public function deleteAction($id)
    {

        $ref_tn_code = RefTnCode::findFirstByid($id);
        if (!$ref_tn_code) {
            $this->flash->error("ref_tn_code was not found");
            return $this->response->redirect("/ref_tn_code/index/");
        }

        if (!$ref_tn_code->delete()) {
            foreach ($ref_tn_code->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_tn_code/search/");
        }

        $this->flash->success("ref_tn_code was deleted successfully");
        return $this->response->redirect("/ref_tn_code/index/");
    }

    public function calculatorAction()
    {
        $this->view->disable();
        $msg = null;
        $success = true;

        if ($this->request->isPost()) {
            $code = strtoupper($this->request->getPost('code'));
            $good_weight = strtoupper($this->request->getPost('good_weight'));
            $good_date = strtoupper($this->request->getPost('good_date'));

            $tn_code = RefTnCode::findFirstByCode($code);

            if (!$tn_code) {
                $success = false;

                $msg = <<<HTML
                    <span class="badge badge-danger" style="font-size:0.9rem;">
                        Код ТН ВЭД: <i>$code</i> не найден !
                    </span>
                HTML;
            } else {
                $good_calc_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn_code));
                $sum = __money($good_calc_res['sum']);
                $price = __money($good_calc_res['price']);
                $mrp = __money($good_calc_res['mrp']);
                $calc_error = $good_calc_res['msg'];

                $msg = <<<HTML
                    <p style="font-size:0.9rem;">
                        Сумма УП: <b> $sum тг </b><br>
                        МРП: <b> $mrp тг </b><br>
                        Коэффициент: <b> $price тг </b>
                    </p>
                HTML;

                if (strtotime($good_date) < ROP_NEW_GD_DATE) {
                    $msg .= <<<HTML
                        <b>Формула: </b>
                        <span class="badge badge-warning mt-1" style="font-size:0.9rem;">
                            $good_weight &times; $price = $sum тг  
                        </span>
                    HTML;
                } else {
                    $msg .= <<<HTML
                        <b>Формула: </b>
                        <span class="badge badge-warning mt-1" style="font-size:0.9rem;">
                           ( $mrp &times; $good_weight &times; $price ) / 1000 = $sum тг  
                        </span>
                    HTML;
                }

                if ($calc_error != null) {
                    $msg .= <<<HTML
                        <span class="badge badge-danger mt-2" style="font-size:0.9rem;">
                           <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                           $calc_error
                        </span>
                    HTML;
                }
            }
        }

        $json_data = array(
            "success" => $success,
            "msg" => $msg
        );

        http_response_code(200);
        return json_encode($json_data);
    }

}
