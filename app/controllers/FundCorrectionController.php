<?php

namespace App\Controllers;

use App\Services\Cms\CmsService;
use ControllerBase;
use FundCar;
use FundCarHistories;
use FundCorrectionLogs;
use FundFile;
use FundGoods;
use FundGoodsHistories;
use FundProfile;
use RefCarCat;
use RefCarType;
use RefModel;
use User;

class FundCorrectionController extends ControllerBase
{

    public function indexAction()
    {
    }

    public function carAnnulmentListAction($fund_id)
    {
        $fund = FundProfile::findFirstById($fund_id);

        if ($fund) {
            $signData = __signFund($fund->id, $this);
            $numberPage = 1; // по умолочанию
            $numberPage = $this->request->getQuery("page", "int");

            $query = $this->modelsManager->createQuery("
                SELECT
                  c.id AS c_id,
                  c.volume AS c_volume,
                  c.vin AS c_vin,
                  c.cost AS c_cost,
                  cc.name AS c_cat,
                  c.date_produce AS c_date_produce,
                  c.ref_st_type AS ref_st_type   
                FROM FundCar c
                  JOIN RefCarCat cc
                  JOIN RefCarType t
                WHERE
                  c.fund_id = :pid: AND
                  cc.id = c.ref_car_cat
                GROUP BY c.id
                ORDER BY c.id ASC");

            $cars = $query->execute(array(
                "pid" => $fund_id
            ));

//            $paginator  = new PaginatorArray([
//                "data" => $cars,
//                "limit" => 20,
//                "page" => $numberPage
//            ));

            $this->view->setVars(array(
                "fund" => $fund,
                "sign_data" => $signData,
                "count" => count($cars)
            ));

        } else {
            $this->flash->error("Объект не найден.");
            return $this->response->redirect("/moderator_fund/view/$fund_id");
        }
    }

    public function annulAllCarsAction()
    {
        $auth = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect("/fund_correction/");
        }

        $fund_id = $this->request->getPost("fund_id");
        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $__settings = $this->session->get("__settings");
        $car_comment = $this->request->getPost("car_comment");

        $f = FundProfile::findFirstById($fund_id);

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {

                $f->approve = 'FUND_ANNULMENT';

                $annulment_fund_cars = FundCarHistories::count([
                    "conditions" => "fund_id = :fund_id: and status = :status:",
                    "bind" => [
                        "fund_id" => $fund_id,
                        "status" => "FUND_ANNULMENT"
                    ]
                ]);

                if (!$annulment_fund_cars) {
                    $f->old_amount = $f->amount;
                }

                $f->blocked = 0;

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $filename = time() . "_" . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $file->moveTo(APP_PATH . "/private/fund_correction/" . $filename);
                        }
                    }
                }

                if ($f->entity_type == 'CAR') {
                    $cars = FundCar::findByFundId($f->id);
                    foreach ($cars as $i => $car) {
                        $_before = json_encode(array($car));
                        $f_car_history = new FundCarHistories();
                        $f_car_history->car_id = $car->id;
                        $f_car_history->volume = $car->volume;
                        $f_car_history->vin = $car->vin;
                        $f_car_history->date_produce = $car->date_produce;
                        $f_car_history->fund_id = $car->fund_id;
                        $f_car_history->ref_car_cat = $car->ref_car_cat;
                        $f_car_history->ref_car_type_id = $car->ref_car_type_id;
                        $f_car_history->ref_st_type = $car->ref_st_type;
                        $f_car_history->cost = $car->cost;
                        $f_car_history->model_id = $car->model_id;
                        $f_car_history->status = 'FUND_ANNULMENT';
                        $f_car_history->dt = time();
                        $f_car_history->user_id = $auth->id;

                        if ($f_car_history->save()) {
                            $car->delete();

                            // логгирование
                            $l = new FundCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = 'FundCAR';
                            $l->fund_id = $f->id;
                            $l->user_id = $auth->id;
                            $l->action = 'ANNULMENT';
                            $l->object_id = $car->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = json_encode(array($f_car_history));
                            $l->comment = $car_comment;
                            $l->file = $filename;
                            $l->sign = $sign;
                            $l->save();

                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);
                        }
                    }

                    $fund_car_cost_sum = FundCar::sum([
                        'column' => 'cost',
                        'conditions' => 'fund_id = ' . $f->id
                    ]);

                    $f->amount = ($fund_car_cost_sum > 0) ? $fund_car_cost_sum : 0;
                } else if ($f->entity_type == 'GOODS') {
                    $fund_goods = FundGoods::findByFundId($f->id);
                    $_before = json_encode(array($fund_goods));

                    foreach ($fund_goods as $item) {
                        $f_goods_history = new FundGoodsHistories();
                        $f_goods_history->goods_id = $item->goods_id;
                        $f_goods_history->weight = $item->weight;
                        $f_goods_history->fund_id = $item->fund_id;
                        $f_goods_history->ref_tn_id = $item->ref_tn;
                        $f_goods_history->date_produce = $item->date_produce;
                        $f_goods_history->cost = $item->amount;
                        $f_goods_history->status = 'FUND_DECLINED';
                        $f_goods_history->dt = time();
                        $f_goods_history->user_id = $auth->id;

                        if ($f_goods_history->save()) {
                            $item->delete();

                            $l = new FundCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = 'FundGoods';
                            $l->fund_id = $f->id;
                            $l->user_id = $auth->id;
                            $l->action = 'ANNULMENT';
                            $l->object_id = $item->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = json_encode(array($f_goods_history));
                            $l->comment = $car_comment;
                            $l->file = $filename;
                            $l->sign = $sign;
                            $l->save();

                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);
                        }
                    }
                }
                $f->save();

                $fund_files = FundFile::find(array(
                    "visible = 1 AND fund_id = :pid:",
                    "bind" => array(
                        "pid" => $f->id
                    )
                ));

                foreach ($fund_files as $f_f) {
                    $file = FundFile::findFirstById($f_f->id);
                    $file->visible = 0;
                    if ($file->save()) {
                        if (file_exists(APP_PATH . '/private/fund/' . $file->id . '.pdf')) {
                            exec('mkdir "' . APP_PATH . '"/private/fund_correction/"' . $f->id . '"');
                            exec('mv "' . APP_PATH . '"/private/fund/"' . $file->id . '".pdf "' . APP_PATH . '"/private/fund_correction/"' . $f->id . '"/"' . $file->id . '".pdf ');
                        }
                    }
                }

                $types = array('fund', 'app3', 'app4', 'app5', 'app6', 'app7', 'app8', 'app9', 'app10', 'app11', 'app12', 'app13', 'app14');
                foreach ($types as $tp) {
                    __genFund($f->id, $tp, $j);
                }

                $this->logAction("Заявка успешна аннулирована.");
                $this->flash->success("Заявка успешна аннулирована.");
                $data = array('success' => 1, 'fund_id' => $f->id);
                return json_encode($data);
            } else {
                $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                $this->flash->error("Подпись не прошла проверку!");
                $data = array('success' => 0, 'fund_id' => $f->id);
                return json_encode($data);
            }
        } else {
            $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'ALERT');
            $this->flash->error("Вы используете несоответствующую профилю подпись.");
            $data = array('success' => 0, 'fund_id' => $f->id);
            return json_encode($data);
        }
    }

    public function viewCarAction($cid)
    {

        $car = FundCar::findFirstById($cid);
        if ($car) {
            $signData = __signFund($car->fund_id, $this);
            $car_types = RefCarType::find(array('id <= 3'));
            $models = RefModel::find();
            $car_cats = RefCarCat::find();
            $m = 'CAR';
            if ($car->ref_car_type_id > 3) {
                $car_types = RefCarType::find(array('id > 3'));
                $m = 'TRAC';
            }

            $logs = FundCorrectionLogs::find([
                "conditions" => "object_id = :object_id: AND fund_id = :fund_id: AND type = :type:",
                "bind" => [
                    "object_id" => $car->id,
                    "fund_id" => $car->fund_id,
                    "type" => "FundCar"
                ]
            ]);

            $this->view->setVars(array(
                "car" => $car,
                "car_types" => $car_types,
                "models" => $models,
                "car_cats" => $car_cats,
                "m" => $m,
                "sign_data" => $signData,
                "logs" => $logs
            ));
        } else {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/fund_correction/");
        }
    }

    public function annulCarAction()
    {
        $auth = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect("/fund_correction/");
        }

        $car_id = $this->request->getPost("car_id");
        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $__settings = $this->session->get("__settings");
        $car_comment = $this->request->getPost("car_comment");

        $car = FundCar::findFirstById(intval($car_id));
        $fund_id = $car->fund_id;
        $_before = json_encode(array($car));

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        $fund = FundProfile::findFirstById($fund_id);

        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {

                $annulment_fund_cars = FundCarHistories::count([
                    "conditions" => "fund_id = :fund_id: and status = :status:",
                    "bind" => [
                        "fund_id" => $fund_id,
                        "status" => "FUND_ANNULMENT"
                    ]
                ]);

                if (!$annulment_fund_cars) {
                    $f = FundProfile::findFirstById($fund_id);
                    $f->old_amount = $f->amount;
                    $f->save();
                }
                $fundType = 'fundCar';

                if ($fund->entity_type == 'CAR') {
                    $f_car_history = new FundCarHistories();
                    $f_car_history->car_id = $car->id;
                    $f_car_history->volume = $car->volume;
                    $f_car_history->vin = $car->vin;
                    $f_car_history->date_produce = $car->date_produce;
                    $f_car_history->fund_id = $fund_id;
                    $f_car_history->ref_car_cat = $car->ref_car_cat;
                    $f_car_history->ref_car_type_id = $car->ref_car_type_id;
                    $f_car_history->ref_st_type = $car->ref_st_type;
                    $f_car_history->cost = $car->cost;
                    $f_car_history->model_id = $car->model_id;
                    $f_car_history->status = 'FUND_ANNULMENT';
                    $f_car_history->dt = time();
                    $f_car_history->user_id = $auth->id;

                    if ($f_car_history->save()) {
                        $car->delete();

                        $fund_car_cost_sum = FundCar::sum([
                            'column' => 'cost',
                            'conditions' => 'fund_id = ' . $fund_id
                        ]);

                        $f = FundProfile::findFirstById($fund_id);
                        $f->amount = ($fund_car_cost_sum > 0) ? $fund_car_cost_sum : 0;
                        $f->save();

                        if ($this->request->hasFiles()) {
                            foreach ($this->request->getUploadedFiles() as $file) {
                                if ($file->getSize() > 0) {
                                    $filename = time() . "_" . pathinfo($file->getName(), PATHINFO_BASENAME);
                                    $file->moveTo(APP_PATH . "/private/fund_correction/" . $filename);
                                }
                            }
                        }
                    }
                    $f_history = $f_car_history;
                }else{
                    $fundType = 'fundGOODS';
                }

                // логгирование
                $l = new FundCorrectionLogs();
                $l->iin = $auth->idnum;
                $l->type = $fundType;
                $l->fund_id = $fund_id;
                $l->user_id = $auth->id;
                $l->action = 'ANNULMENT';
                $l->object_id = $car->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($f_history));
                $l->comment = $car_comment;
                $l->file = $filename;
                $l->sign = $sign;
                $l->save();

                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);
                $this->flash->success("Транспортное средство $car->vin успешно аннулировано.");
                return $this->response->redirect("/moderator_fund/view/$fund_id");
            } else {
                $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                $this->flash->error("Подпись не прошла проверку!");
                return $this->response->redirect("/fund_correction/view_car/$car_id");
            }
        } else {
            $this->logAction("Вы используете несоответствующую профилю подпись.");
            $this->flash->error("Вы используете несоответствующую профилю подпись.");
            return $this->response->redirect("/fund_correction/view_car/$car_id");
        }
    }

    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund_correction/";
        $auth = User::getUserBySession();

        $cl = FundCorrectionLogs::findFirstById($id);

        if ($cl->user_id == $auth->id || $auth->isEmployee()) {
            if (file_exists($path . $cl->file)) {
                __downloadFile($path . $cl->file, $cl->file);
            }
        }
    }
}
