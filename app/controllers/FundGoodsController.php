<?php
namespace App\Controllers;

use App\Services\Fund\FundService;
use ControllerBase;
use FundGoods;
use FundProfile;
use Goods;
use RefCountry;
use RefFund;
use RefFundKeys;
use RefTnCode;
use User;

class FundGoodsController extends ControllerBase
{

    public function addAction()
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!");
            return $this->response->redirect("/fund/");
        }

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("fund");
            $goods_id = $this->request->getPost("goods_id");
            $tn_code = $this->request->getPost("tn_code");
            $good_date = $this->request->getPost("good_date");
            $weight = $this->request->getPost("good_weight");
            $good_basis = $this->request->getPost("good_basis");
            $basis_date = $this->request->getPost("basis_date");

            $ref_tn = RefTnCode::findFirst($tn_code);
            if ($ref_tn) {
                $ref_tn_code = $ref_tn->id;
            }
            $f = FundProfile::findFirstById($pid);
            $goods_check = FundGoods::findFirstByGoodsId($goods_id);

            if ($goods_check) {
                $message = "товар с № $goods_id уже был представлен в заявке № " . __getFundNumber($goods_check->fund_id);
                $this->flash->warning($message);
            }
            $year = date('Y', $f->created);

            $fund_goods_weight = (float)(FundGoods::sum([
                'column' => 'weight',
                'conditions' => 'fund_id = :fund_id:',
                'bind' => ['fund_id' => $f->id],
            ]) ?: 0);

            $refFund = RefFund::findFirst([
                'conditions' => 'key = :key:
                         AND idnum = :idnum:
                         AND year = :year:
                         AND prod_start <= :prod_start:
                         AND prod_end >= :prod_end:',
                'bind' => [
                    'key'   => $f->ref_fund_key,
                    'idnum' => $auth->idnum,
                    'year'  => $year,
                    'prod_start'  =>  strtotime($good_date),
                    'prod_end'  =>  strtotime($good_date),
                ],
            ]);

            $all_weight = $fund_goods_weight + $weight;

            if ($all_weight > $refFund->value) {
                $this->flash->warning("Превышение лимитов по товарам № $goods_id, объект не был добавлен.");
                return $this->response->redirect("/fund/view/$f->id");
            }

            $calc_good_amount = Goods::calculateAmount($weight, json_encode($ref_tn));
            $sum = $calc_good_amount['sum'];

            $fund_goods = new FundGoods();
            $fund_goods->fund_id = $f->id;
            $fund_goods->ref_tn = $ref_tn_code ?? NULL;
            $fund_goods->date_produce = strtotime($good_date);
            $fund_goods->weight = $weight;
            $fund_goods->cost = $sum;
            $fund_goods->basis = $good_basis;
            $fund_goods->basis_date = strtotime($basis_date);
            $fund_goods->save();

            return $this->response->redirect("/fund/view/$f->id");
        }
    }

    public function newAction($pid)
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            return $this->response->redirect("/fund/");
        }

        $fund = FundProfile::findFirst($pid);

        $country = RefCountry::find(array('id NOT IN (1, 201)'));
        $tn_codes = [];

        if ($fund->entity_type == 'GOODS') {
            $tn_codes = RefTnCode::find([
                'conditions' => 'code IN (401110000,4011800000,4011900000,401120)',
            ]);
        }

        $this->view->setVars(array(
            'country' => $country,
            "tn_codes" => $tn_codes,
            "pid" => $pid,
        ));
    }

    public function deleteAction($goods_id)
    {
        $auth = User::getUserBySession();
        $fundService = new FundService();

        $fundGoods = FundGoods::findFirstById($goods_id);
        $f = FundProfile::findFirstById($fundGoods->fund_id);

        if ($auth->id != $f->user_id || $f->blocked) {
            $this->logAction("Вы не имеете права удалять этот объект.");
            $this->flash->error("Вы не имеете права удалять этот объект.");

            $this->dispatcher->forward(array(
                "controller" => "fund",
                "action" => "index"
            ));
        } else {
            if ($fundGoods->delete()) {
                $fundService->calculationFundAmount($f);
                $this->logAction("Удаление произошло успешно.");
                $this->flash->success("Удаление произошло успешно.");
                return $this->response->redirect("/fund/view/$f->id");
            }
        }
    }
}