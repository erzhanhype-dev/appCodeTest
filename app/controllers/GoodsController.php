<?php

namespace App\Controllers;

use App\Services\Cms\CmsService;
use ClientCorrectionFile;
use ClientCorrectionGoods;
use ClientCorrectionLogs;
use ClientCorrectionProfile;
use ControllerBase;
use CorrectionLogs;
use File;
use Goods;
use PersonDetail;
use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use Profile;
use RefCountry;
use RefTnCode;
use Transaction;
use User;

class GoodsController extends ControllerBase
{
    public function newAction($pid = 0)
    {
        $profile = Profile::findFirstById($pid);
        $auth = User::getUserBySession();

        if ($auth->id != $profile->user_id) {
            $message = "У вас нет прав на это действие!";
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        $profile = Profile::findFirstById($pid);

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $message = "Нет доступа";
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if (!$profile || $auth->id != $profile->user_id) {
            $message = "У вас нет прав на это действие!";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/order/index");
        }

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile->id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->flash->warning('Уважаемый пользователь, вы не можете добавить, отредактировать или удалить ТБО, вы уже подписали электронное Заявление 
                              (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                              Если вы хотите отредактировать данные ТБО, Вам необходимо удалить Подписанное Заявление. 
                              После внесения изменений в данные ТБО подпишите Заявление повторно.');

            return $this->response->redirect("/order/view/$profile->id");
        }

        $tn_codes = RefTnCode::find([
            'conditions' => "code IS NOT NULL AND is_active = 1 AND is_correct = 1",
            'order' => 'name'
        ]);

        $tn_codes_package = RefTnCode::find([
            'conditions' => "code IS NOT NULL AND is_active = 1 AND is_correct = 1 AND type = 'PACKAGE'",
            'order' => 'name'
        ]);

        $country = RefCountry::find(array('id NOT IN (1, 201)'));

        $this->view->setVars(array(
            "tn_codes" => $tn_codes,
            "tn_codes_package" => $tn_codes_package,
            "pid" => $pid,
            "country" => $country
        ));
    }

    public function addAction()
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $message = "Нет доступа";
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("profile");

            $profile = Profile::findFirstById($pid);

            if ($auth->id != $profile->user_id || $profile->blocked) {
                $message = "Вы не имеете права создавать новый товар.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/view/$profile->id");
            } else {
                $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
                $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
                $good_date = $this->request->getPost("good_date");
                $basis_date = $this->request->getPost("basis_date");
                $tn_code = $this->request->getPost("tn_code");
                $tn_code_add = $this->request->getPost("tn_code_add");
                $country = $this->request->getPost("good_country");
                $good_basis = $this->request->getPost("good_basis");

                $up_type = 0;
                $date_real = 0;
                $date_report = 0;
                $t_type_i = 0;
                $package_cost = 0;
                $good_amount = 0;

                $t_type = $this->request->getPost("t_type");
                if ($t_type && $t_type == 'up') {
                    $up_type = $this->request->getPost("up_type");
                    $date_report = $this->request->getPost("date_report");
                    $t_type_i = 1;
                }
                if ($t_type && $t_type == 'goods') {
                    $up_type = $this->request->getPost("up_type");
                    $date_report = $this->request->getPost("date_report");
                    $t_type_i = 2;
                }

                if ($good_weight < 0) {
                    $message = "Вес товара некорректный, товар не добавлен";
                    $this->logAction($message);
                    $this->flash->error($message);
                    return $this->response->redirect("/order/view/$profile->id");
                }

                // если дата импорта меньше, чем дата введения постановления
                // в действие - то перекидываем пользователя на соответствующее
                // сообщение
                if (strtotime($good_date) < strtotime(STARTROP)) {
                    $this->flash->notice("За товары, ввезенные / произведенные на территорию Республики Казахстан до 27 января 2016 года включительно, не оплачивается утилизационный платеж.");
                    return $this->response->redirect("/order/view/$profile->id");
                }

                $tn = RefTnCode::findFirstById($tn_code);

                // NOTE: Расчет платежа (добавление товара)
                if ($tn != false) {

                    // расчет суммы платежа
                    $calc_good_res = Goods::calculateAmount($good_weight, json_encode($tn));

                    $sum = $calc_good_res['sum'];

                    $c = new Goods();
                    $c->weight = $good_weight;
                    $c->basis = $good_basis;
                    $c->date_import = strtotime($good_date);
                    $c->basis_date = strtotime($basis_date);
                    $c->profile_id = $profile->id;
                    $c->ref_tn = $tn->id;
                    $c->price = $calc_good_res['price'];
                    $c->created = time();
                    $c->calculate_method = 1;

                    $tn_add = RefTnCode::findFirstById($tn_code_add);
                    if ($tn_add) {
                        $c->ref_tn_add = $tn_add->id;
                        $c->package_weight = $package_weight;
                        $package_cost_calc = Goods::calculateAmount($package_weight, json_encode($tn_add));
                        $package_cost = $package_cost_calc['sum'];
                        $c->package_cost = $package_cost;
                    }

                    $good_amount = round($sum + $package_cost, 2);
                    $c->goods_cost = $sum;
                    $c->amount = $good_amount;
                    $c->ref_country = $country;

                    if ($t_type_i > 0) {
                        $c->goods_type = $t_type_i;
                        $c->up_type = $up_type;
                        $c->date_report = strtotime($date_report);

                        if ($t_type_i == 1) {
                            $v = 0;
                            switch ($up_type) {
                                case 1:
                                    $v = 1.76;
                                    break;
                                case 2:
                                    $v = 1.14;
                                    break;
                                case 3:
                                    $v = 1.40;
                                    break;
                                case 4:
                                    $v = 0.20;
                                    break;
                                case 5:
                                    $v = 0.91;
                                    break;
                            }

                            $sum = round($good_weight * $v, 2);
                            $c->amount = $sum + $package_cost;
                            $c->goods_cost = $sum;
                            $c->price = $v;
                        }
                    }

                    $tr = Transaction::findFirstByProfileId($profile->id);

                    if ($good_amount > 999999999999.99) {
                        $message = "Сумма платежа слишком велика и выглядит некорректной.";
                        $this->logAction($message);
                        $this->flash->error($message);
                        return $this->response->redirect("/order/view/$profile->id");
                    }

                    if ($tr) {
                        $tr->amount = $tr->amount + $good_amount;
                        $tr->save();
                    }

                    if ($c->save()) {
                        $this->logAction("Товар добавлен");
                        $this->flash->success("Новая позиция добавлена");
                    } else {
                        $this->logAction("Невозможно сохранить новую позицию", 'action', 'WARNING');
                        $this->flash->warning("Невозможно сохранить новую позицию");
                    }

                    return $this->response->redirect("/order/view/$profile->id");
                }

            }
        }
    }

    public function editAction($gid = 0)
    {
        $auth = User::getUserBySession();

        if ($gid == 0) {
            return $this->response->redirect("/order/index");
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $message = "Нет доступа";
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);

            if ($auth->id != $profile->user_id) {
                $message = "У вас нет прав на это действие!";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/index");
            }

            if ($auth->isSuperModerator() && !$this->isSuperModeratorProfile($profile->moderator_id, $tr->approve)) {
                $message = "У вас нет прав на это действие!";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/create_order/view/" . $profile->id);
            }

            if ($profile->blocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message);
                $this->flash->error($message);
                if ($auth->isSuperModerator() && !$this->isSuperModeratorProfile($profile->moderator_id, $tr->approve)) {
                    return $this->response->redirect("/create_order/view/" . $profile->id);
                }
                return $this->response->redirect("/order/index/");
            }

            $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $good_date = $this->request->getPost("good_date");
            $basis_date = $this->request->getPost("basis_date");
            $good_basis = $this->request->getPost("good_basis");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");

            $up_type = 0;
            $date_real = 0;
            $date_report = 0;
            $t_type_i = 0;
            $package_cost = 0;
            $good_amount = 0;

            $t_type = $this->request->getPost("t_type");
            if ($t_type && $t_type == 'up') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 1;
            }
            if ($t_type && $t_type == 'goods') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 2;
            }

            if ($good_weight < 0) {
                $message = "Вес товара некорректный, товар не добавлен";
                $this->flash->error($message);
                $this->logAction($message);

                return $this->response->redirect("/order/view/$profile->id");
            }

            $tn = RefTnCode::findFirstById($tn_code);

            // NOTE: Расчет платежа (правка товара)
            if ($tn != false) {

                $calc_good_amount = Goods::calculateAmount($good_weight, json_encode($tn));
                $sum = $calc_good_amount['sum'];

                $tr->amount = $tr->amount - $good->amount;

                $good->weight = $good_weight;
                $good->basis = $good_basis;
                $good->ref_tn = $tn->id;
                $good->date_import = strtotime($good_date);
                $good->basis_date = strtotime($basis_date);
                $good->profile_id = $profile->id;
                $good->price = $calc_good_amount['price'];

                $tn_add = RefTnCode::findFirstById($tn_code_add);
                if ($tn_add) {
                    $good->ref_tn_add = $tn_add->id;
                    $good->package_weight = $package_weight;
                    $package_cost_calc = Goods::calculateAmount($package_weight, json_encode($tn_add));
                    $package_cost = $package_cost_calc['sum'];
                    $good->package_cost = $package_cost;

                    $good_amount = round($sum + $package_cost, 2);
                    $good->amount = $good_amount;
                } else {
                    $good->ref_tn_add = 0;
                    $good->package_weight = 0;
                    $good->package_cost = 0;

                    $good_amount = $sum;
                    $good->amount = $sum;
                }

                $good->goods_cost = $sum;

                $good->ref_country = $country;
                $good->updated = time();

                if ($t_type_i > 0) {
                    $good->goods_type = $t_type_i;
                    $good->up_type = $up_type;
                    $good->date_report = strtotime($date_report);

                    if ($t_type_i == 1) {
                        $v = 0;
                        switch ($up_type) {
                            case 1:
                                $v = 1.76;
                                break;
                            case 2:
                                $v = 1.14;
                                break;
                            case 3:
                                $v = 1.40;
                                break;
                            case 4:
                                $v = 0.20;
                                break;
                            case 5:
                                $v = 0.91;
                                break;
                        }

                        $sum = round($good_weight * $v, 2);
                        $good->amount = $sum;
                        $good->price = $v;
                    }
                }

                if ($good_amount > 999999999999.99) {
                    $message = "Сумма платежа слишком велика и выглядит некорректной.";
                    $this->logAction($message);
                    $this->flash->error($message);
                    return $this->response->redirect("/order/view/$profile->id");
                }

                $tr->amount = $tr->amount + $good_amount;
                $tr->save();

                if ($good->save()) {
                    $this->logAction("Товар отредактировано.");
                    $this->flash->success("Позиция отредактирована.");
                } else {
                    $this->logAction("Невозможно отредактировать эту позицию.", 'action', 'WARNING');
                    $this->flash->warning("Невозможно отредактировать эту позицию.");
                }

                if ($auth->isEmployee()) {
                    return $this->response->redirect("/create_order/view/$profile->id");
                } else {
                    return $this->response->redirect("/order/view/$profile->id");
                }
            }
        } else {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);

            $files = File::count(array(
                "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
                "bind" => array(
                    "pid" => $profile->id,
                    "type" => "application"
                )
            ));

            if ($files > 0) {
                $this->flash->warning('Уважаемый пользователь, вы не можете добавить, отредактировать или удалить ТБО, вы уже подписали электронное Заявление 
                                (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                                Если вы хотите отредактировать данные ТБО, Вам необходимо удалить Подписанное Заявление. 
                                После внесения изменений в данные ТБО подпишите Заявление повторно.');

                return $this->response->redirect("/order/view/$profile->id");
            }

            $filter = "code IS NOT NULL AND is_correct = 1";
            $filter_add = "code IS NOT NULL AND is_correct = 1 AND type = 'PACKAGE'";

            if ($profile->type == 'R20') {
                if ($good->goods_type == 0) {
                    $filter = "(id > 0 AND id < 102) OR id > 850 AND price > 0";
                };
                if ($good->goods_type == 1) {
                    $filter = "id > 107 AND id < 144 AND price > 0";
                };
                if ($good->goods_type == 2) {
                    $filter = "id > 143 AND id < 851 AND price > 0";
                };
            }

            $tn_codes = RefTnCode::find([
                $filter,
                'order' => 'code'
            ]);

            $tn_codes_package = RefTnCode::find([
                $filter_add,
                'order' => 'name'
            ]);

            $country = RefCountry::find(array('id NOT IN (1, 201)'));

            if ($auth->id != $profile->user_id || $profile->blocked) {
                $this->logAction("Вы не имеете права редактировать этот объект.", 'security', 'ALERT');
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                return $this->response->redirect("/order/index/");
            }

            $this->view->setVars(array(
                "good" => $good,
                "tn_codes" => $tn_codes,
                "tn_codes_package" => $tn_codes_package,
                "country" => $country
            ));
        }
    }

    public function recalcAction()
    {
        $this->view->disable();
        $list = array();

        foreach ($list as $n => $_l) {
            //echo 'Start: '.$_l.'<br />';
            $good = Goods::findFirstById($_l);
            $profile = Profile::findFirstById($good->profile_id);

            $good_weight = $good->weight;
            $good_date = $good->date_import;

            $tn = RefTnCode::findFirstById($good->ref_tn);

            // NOTE: Расчет платежа (правка товара)
            if ($tn != false) {

                $calc_good_res = Goods::calculateAmount($good_weight, json_encode($tn));
                $sum = $calc_good_res['sum'];

                $tr = Transaction::findFirstByProfileId($profile->id);

                if ($tr->approve == 'REVIEW' || $tr->approve == 'DECLINED' || $tr->approve == 'NEUTRAL') {
                    $tr->amount = $tr->amount - $good->amount;
                    $good->amount = $sum;
                    $good->goods_cost = $sum;
                    $good->v = $calc_good_res['price'];
                    $good->updated = time();

                    $tr->amount = $tr->amount + $sum;
                    $tr->save();

                    $good->save();
                    echo $_l . ', ';
                }
            }
        }
    }

    public function deleteAction($gid = 0)
    {
        $auth = User::getUserBySession();

        if ($gid == 0) {
            return $this->response->redirect("/order/index");
        }

        $good = Goods::findFirstById($gid);
        $profile = Profile::findFirstById($good->profile_id);

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile->id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->flash->warning('Уважаемый пользователь, вы не можете добавить, отредактировать или удалить ТБО, вы уже подписали электронное Заявление 
                              (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                              Если вы хотите отредактировать данные ТБО, Вам необходимо удалить Подписанное Заявление. 
                              После внесения изменений в данные ТБО подпишите Заявление повторно.');

            return $this->response->redirect("/order/view/$profile->id");
        }

        $t_type = $good->goods_type;
        $_SESSION['goods_show'] = $t_type;

        if (($auth->id != $profile->user_id && $auth->id != $profile->moderator_id) || $profile->blocked) {
            $this->logAction("Вы не имеете права удалять этот объект.", 'security', 'ALERT');
            $this->flash->error("Вы не имеете права удалять этот объект.");
            return $this->response->redirect("/order/index/");
        } else {
            $tr = Transaction::findFirstByProfileId($profile->id);
            $tr->amount = $tr->amount - $good->amount;
            $tr->save();

            if ($good->delete()) {
                $this->logAction("Товар удален.");
                $this->flash->success("Удаление произошло успешно.");

                if ($auth->isEmployee()) {
                    return $this->response->redirect("/create_order/view/$profile->id");
                } else {
                    return $this->response->redirect("/order/view/$profile->id");
                }
            }
        }
    }

    /**
     * Импорт товар.
     * @param int $pid
     * @return void
     */
    public function importAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        $profile = Profile::findFirstById($pid);

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Нет доступа!", 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        $isOwner = $auth->id === $profile->user_id;
        $isModerator = $auth->id === $profile->moderator_id;
        $isNotBlocked = $profile->blocked === 0;

        if (!$isModerator && !$isOwner && !$isNotBlocked) {
            $message = "Вы не имеете права редактировать этот объект.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/order/index/");
        }

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile->id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->flash->warning('Уважаемый пользователь, вы не можете добавить, отредактировать или удалить ТБО, вы уже подписали электронное Заявление 
                              (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                              Если вы хотите отредактировать данные ТБО, Вам необходимо удалить Подписанное Заявление. 
                              После внесения изменений в данные ТБО подпишите Заявление повторно.');

            return $this->response->redirect("/order/view/$profile->id");
        }

        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    public function uploadAction(): ?ResponseInterface
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Нет доступа!", 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        $order = $this->request->getPost("order_id");
        $profile = Profile::findFirstById($order);

        if ($this->request->isPost()) {
            $isOwner = $auth->id === $profile->user_id;
            $isModerator = $auth->id === $profile->moderator_id;
            $isNotBlocked = $profile->blocked === 0;

            if (!$isModerator && !$isOwner && !$isNotBlocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/index/");
            }

            $filename = $order . '_' . time();

            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo(APP_PATH . "/storage/temp/" . $filename . ".csv");
                }
            }

            $import = file(APP_PATH . "/storage/temp/" . $filename . ".csv");
            $success = false;

            foreach ($import as $key => $value) {
                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);

                    $good_weight = (float)str_replace(',', '.', trim($val[0]));
                    $good_date = trim($val[2]);
                    $tn_code = trim($val[3]);
                    $tn_code_add = trim($val[4]);
                    $country = trim($val[5]);
                    $good_basis = trim($val[1]);
                    $basis_date = trim($val[6]);
                    $package_weight = (float)str_replace(',', '.', trim($val[7]));
                    $package_cost = 0;
                    // если дата импорта меньше, чем дата введения постановления
                    // в действие - то перекидываем пользователя на соответствующее
                    // сообщение
                    if (strtotime($good_date) < strtotime(STARTROP)) {
                        $this->flash->notice("За товары, ввезенные / произведенные на территорию Республики Казахстан до 27 января 2016 года включительно, не оплачивается утилизационный платеж.");
                        continue;
                    } else {
                        if ($good_weight < 0) {
                            $this->flash->error("Вес товара в позиции $key некорректный, товар не добавлен");
                            continue;
                        }

                        $tn = RefTnCode::findFirstByCode($tn_code);

                        // NOTE: Расчет платежа (добавление товара)
                        if ($tn != false) {
                            $good_calc_res = Goods::calculateAmount($good_weight, json_encode($tn));
                            $sum = $good_calc_res['sum'];

                            $g = new Goods();
                            $g->weight = $good_weight;
                            $g->basis = $good_basis;
                            $g->date_import = strtotime($good_date);
                            $g->profile_id = $profile->id;
                            $g->ref_tn = $tn->id;
                            $g->price = $good_calc_res['price'];
                            $g->created = time();

                            if ($tn->pay_pack == 1) {
                                if ($tn_code_add > 0 && $tn_code_add != 0) {
                                    $tn_add = RefTnCode::findFirstByCode($tn_code_add);
                                    if ($tn_add && $tn_add->is_correct == 1) {
                                        $g->ref_tn_add = $tn_add->id;
                                        $g->package_weight = $package_weight;
                                        $package_cost_calc = Goods::calculateAmount($package_weight, json_encode($tn_add));
                                        $package_cost = $package_cost_calc['sum'];
                                        $g->package_cost = $package_cost;
                                    }
                                } else {
                                    $g->ref_tn_add = 0;
                                }
                            }

                            $good_amount = round($sum + $package_cost, 2);
                            $g->amount = $good_amount;
                            $g->goods_cost = $sum;
                            $g->ref_country = $country;
                            $g->basis_date = strtotime($basis_date);

                            $g->goods_type = 0;
                            $g->up_type = 0;
                            $g->up_tn = 0;
                            $g->date_report = 0;
                            $g->save();
                            $tr = Transaction::findFirstByProfileId($profile->id);
                            $tr->amount = $tr->amount + $good_amount;
                            $tr->save();
                            $success = true;
                            $this->logAction("Товар добавлен");
                        }
                    }
                }
            }
        }

        if ($auth->isSuperModerator() || $auth->isAdminSoft()) {
            return $this->response->redirect("/create_order/view/$order");
        }
        return $this->response->redirect("/order/view/$profile->id");
    }

    public function correctionAction($gid = 0)
    {
        $auth = User::getUserBySession();

        if ($gid == 0) {
            return $this->response->redirect("/order/index");
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);
            $_before = json_encode(array($good));

            if ($auth->id != $profile->user_id) {
                $this->logAction("У вас нет прав на это действие!", 'security', 'ALERT');
                $this->flash->error("У вас нет прав на это действие!");
                return $this->response->redirect("/order/index");
            }

            $good_weight_raw = trim((string)$this->request->getPost('good_weight'));
            $good_weight = str_replace(',', '.', $good_weight_raw);
            $good_weight = str_replace(' ', '', $good_weight);

            $package_weight_raw = trim((string)$this->request->getPost('package_weight'));
            $package_weight = str_replace(',', '.', $package_weight_raw);
            $package_weight = str_replace(' ', '', $package_weight);

            $good_date = $this->request->getPost("good_date");
            $basis_date = $this->request->getPost("basis_date");
            $good_basis = $this->request->getPost("good_basis");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");
            $good_comment = $this->request->getPost("good_comment");
            $good_file = $this->request->getPost("good_file");
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");
            $calculate_method = 1;

            if ($good_comment == '' || strlen($good_comment) < 4) {
                $this->flash->error("Поле «Комментарий» обязательно для заполнения.");
                return $this->response->redirect("/goods/correction/" . $gid);
            }

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                if ($result['success'] === true) {

                    $up_type = 0;
                    $date_real = 0;
                    $date_report = 0;
                    $t_type_i = 0;
                    $package_cost = 0;
                    $good_amount = 0;

                    $t_type = $this->request->getPost("t_type");
                    if ($t_type && $t_type == 'up') {
                        $up_type = $this->request->getPost("up_type");
                        $date_report = $this->request->getPost("date_report");
                        $t_type_i = 1;
                    }
                    if ($t_type && $t_type == 'goods') {
                        $up_type = $this->request->getPost("up_type");
                        $date_report = $this->request->getPost("date_report");
                        $t_type_i = 2;
                    }

                    if ($auth->id != $profile->user_id && $auth->id != $profile->moderator_id) {
                        $this->logAction("Вы не имеете права редактировать этот объект.", 'security', 'ALERT');
                        $this->flash->error("Вы не имеете права редактировать этот объект.");
                        return $this->response->redirect("/order/");
                    }

                    if (ClientCorrectionProfile::checkCurrentCorrection($good->id, 'GOODS')) {
                        $message = "Заявка с таким ID: $good->id обнаружен в базе заявок на корректировку";
                        $this->flash->error($message);
                        $this->logAction($message);
                        return $this->response->redirect("/order/");
                    }

                    $tn = RefTnCode::findFirstById($tn_code);

                    // NOTE: Расчет платежа (правка товара)
                    if ($tn != false) {
                        $goodeditfilename = '';
                        $good_calc_res = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $good_weight, json_encode($tn));
                        $sum = $good_calc_res['sum'];

                        $g = new ClientCorrectionGoods();
                        $g->good_id = $good->id;
                        $g->weight = $good_weight;
                        $g->basis = $good_basis;
                        $g->basis_date = strtotime($basis_date);
                        $g->ref_tn = $tn->id;
                        $g->date_import = strtotime($good_date);
                        $g->profile_id = $profile->id;
                        $g->price = $good_calc_res['price'];

                        $tn_add = RefTnCode::findFirstById($tn_code_add);
                        if ($tn_add) {
                            $g->ref_tn_add = $tn_add->id;
                            $g->package_weight = $package_weight;
                            $package_cost_calc = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $package_weight, json_encode($tn_add));
                            $package_cost = $package_cost_calc['sum'];
                            $g->package_cost = $package_cost;
                        } else {
                            $g->ref_tn_add = 0;
                        }

                        $good_amount = round($sum + $package_cost, 2);
                        $g->calculate_method = $calculate_method;
                        $g->amount = $good_amount;
                        $g->goods_cost = $sum;
                        $g->ref_country = $country;

                        if ($t_type_i > 0) {
                            $g->goods_type = $t_type_i;
                            $g->up_type = $up_type;
                            $g->date_report = strtotime($date_report);

                            if ($t_type_i == 1) {
                                $v = 0;
                                switch ($up_type) {
                                    case 1:
                                        $v = 1.76;
                                        break;
                                    case 2:
                                        $v = 1.14;
                                        break;
                                    case 3:
                                        $v = 1.40;
                                        break;
                                    case 4:
                                        $v = 0.20;
                                        break;
                                    case 5:
                                        $v = 0.91;
                                        break;
                                }

                                $sum = round($good_weight * $v, 2);
                                $good_amount = round($sum + $package_cost, 2);
                                $g->amount = $good_amount;
                                $g->price = $v;
                            }
                        }

                        // add to correction_client_profile
                        $cc_p = new ClientCorrectionProfile();
                        $cc_p->created = time();
                        $cc_p->user_id = $auth->id;
                        $cc_p->profile_id = $profile->id;
                        $cc_p->object_id = $good->id;
                        $cc_p->type = 'GOODS';
                        $cc_p->status = "SEND_TO_MODERATOR";
                        $cc_p->action = "CORRECTION";

                        if ($cc_p->save()) {

                            $g->ccp_id = $cc_p->id;

                            if ($this->request->hasFiles()) {
                                foreach ($this->request->getUploadedFiles() as $file) {
                                    if ($file->getSize() > 0) {
                                        $goodeditfilename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                        $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                                        $file->moveTo(APP_PATH . "/private/client_correction_docs/" . $goodeditfilename);

                                        // добавляем файл
                                        $f = new ClientCorrectionFile();
                                        $f->profile_id = $cc_p->profile_id;
                                        $f->type = 'other';
                                        $f->original_name = $goodeditfilename;
                                        $f->ext = $ext;
                                        $f->ccp_id = $cc_p->id;
                                        $f->visible = 1;
                                        $f->user_id = $auth->id;
                                        $f->save();

                                        if ($f->save()) {
                                            copy(APP_PATH . "/private/client_correction_docs/" . $goodeditfilename, APP_PATH . "/private/client_corrections/" . $goodeditfilename);
                                        }
                                    }
                                }
                            }

                            // логгирование
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = 'GOODS';
                            $l->user_id = $auth->id;
                            $l->action = 'SEND_TO_MODERATOR';
                            $l->object_id = $good->id;
                            $l->ccp_id = $cc_p->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = json_encode(array($g));
                            $l->comment = $good_comment;
                            $l->file = $goodeditfilename;
                            $l->sign = $sign;
                            $l->hash = $hash;
                            $l->save();

                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);

                            if ($l->save()) {
                                // Генерация заявка на корректировку.
                                __genAppCorrection($cc_p->id);
                            }
                        }

                        if ($g->save()) {
                            $this->logAction("Корректировка отправлена на согласование.");
                            $this->flash->success("Корректировка отправлена на согласование.");
                            return $this->response->redirect("/correction_request/");
                        } else {
                            $this->logAction("Невозможно отредактировать эту позицию.");
                            $this->flash->warning("Невозможно отредактировать эту позицию.");
                            return $this->response->redirect("/order/");
                        }
                        return $this->response->redirect("/order/");
                    }

                } else {
                    $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/order/");
                }
            } else {
                $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'ALERT');
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                return $this->response->redirect("/order/");
            }
            return $this->response->redirect("/order/");

        } else {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);


            $correction_data = [
                'goods_weight' => $good->goods_weight,
                'tn_code' => $good->ref_tn,
            ];

            if ($good && $profile) {
                $tr = Transaction::findFirstByProfileId($profile->id);
                $signData = __signData($good->profile_id, $this);
                $numberPage = (int)$this->request->getQuery('page', 'int', 1);
                if ($numberPage < 1) {
                    $numberPage = 1;
                }

                $builder = $this->modelsManager->createBuilder()
                    ->columns([
                        'id' => 'c.id',
                        'user_id' => 'c.user_id',
                        'iin' => 'c.iin',
                        'action' => 'c.action',
                        'dt' => 'c.dt',
                        'ccp_id' => 'c.ccp_id',
                    ])
                    ->from(['c' => ClientCorrectionLogs::class])
                    ->where('c.type = :type: AND c.object_id = :gid:', [
                        'type' => 'GOODS',
                        'gid' => $gid,
                    ])
                    ->orderBy('c.id DESC');
                $paginator = new QueryBuilderPaginator([
                    'builder' => $builder,
                    'limit' => 100,
                    'page' => $numberPage,
                ]);

                $filter = "code IS NOT NULL AND is_active = 1";
                $filter_add = "code IS NOT NULL AND is_active = 1 AND type = 'PACKAGE'";

                if ($profile->type == 'R20') {
                    if ($good->goods_type == 0) {
                        $filter = "(id > 0 AND id < 102) OR id > 850 AND price > 0";
                    };
                    if ($good->goods_type == 1) {
                        $filter = "id > 107 AND id < 144 AND price > 0";
                    };
                    if ($good->goods_type == 2) {
                        $filter = "id > 143 AND id < 851 AND price > 0";
                    };
                }

                $tn_codes = RefTnCode::find([
                    $filter,
                    'order' => 'code'
                ]);

                $tn_codes_package = RefTnCode::find([
                    $filter_add,
                    'order' => 'name'
                ]);

                $country = RefCountry::find(array('id NOT IN (1, 201)'));

                if ($auth->id != $profile->user_id) {
                    $this->logAction("Вы не имеете права редактировать этот объект.", 'security');
                    $this->flash->error("Вы не имеете права редактировать этот объект.");
                    return $this->response->redirect("/order/");
                }

                $this->view->page = $paginator->paginate();
                $this->view->setVars(array(
                    "good" => $good,
                    "tn_codes" => $tn_codes,
                    "tn_codes_package" => $tn_codes_package,
                    "country" => $country,
                    "sign_data" => $signData,
                    "md_dt_sent" => $tr->md_dt_sent,
                    "correction_data" => base64_encode(json_encode($correction_data))
                ));

            } else {
                $this->flash->error("Объект не найден.");
                return $this->response->redirect("/order/");
            }
        }
    }

    public function annulmentAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Нет доступа!", 'security', 'ALERT');
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $profile = Profile::findFirstById($pid);

            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");
            $good_comment = $this->request->getPost("good_comment");

            if ($auth->id != $profile->user_id) {
                $this->logAction("У вас нет прав на это действие!", 'security', 'ALERT');
                $this->flash->error("У вас нет прав на это действие!");
                return $this->response->redirect("/order/index");
            }

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];

            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {

                    if ($auth->id != $profile->user_id) {
                        $this->logAction("Вы не имеете права редактировать этот объект.", 'security');
                        $this->flash->error("Вы не имеете права редактировать этот объект.");

                        return $this->response->redirect("/order/");
                    }

                    if (ClientCorrectionProfile::checkCurrentCorrectionByProfileId($pid, 'GOODS')) {
                        $message = "Заявка с таким ID: $pid обнаружен в базе заявок на корректировку";
                        $this->logAction($message);
                        $this->flash->error($message);
                        return $this->response->redirect("/order/");
                    }

                    // add to correction_client_profile
                    $cc_p = new ClientCorrectionProfile();
                    $cc_p->created = time();
                    $cc_p->user_id = $auth->id;
                    $cc_p->profile_id = $profile->id;
                    $cc_p->object_id = $profile->id;
                    $cc_p->type = 'GOODS';
                    $cc_p->status = "SEND_TO_MODERATOR";
                    $cc_p->action = "ANNULMENT";

                    if ($cc_p->save()) {

                        if ($this->request->hasFiles()) {
                            foreach ($this->request->getUploadedFiles() as $file) {
                                if ($file->getSize() > 0) {
                                    $goodeditfilename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                    $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                                    $file->moveTo(APP_PATH . "/private/client_correction_docs/" . $goodeditfilename);

                                    // добавляем файл
                                    $f = new ClientCorrectionFile();
                                    $f->profile_id = $cc_p->profile_id;
                                    $f->type = 'app_annulment';
                                    $f->original_name = $goodeditfilename;
                                    $f->ext = $ext;
                                    $f->ccp_id = $cc_p->id;
                                    $f->visible = 1;
                                    $f->save();

                                    if ($f->save()) {
                                        copy(APP_PATH . "/private/client_correction_docs/" . $goodeditfilename, APP_PATH . "/private/client_corrections/" . $goodeditfilename);
                                    }
                                }
                            }
                        }

                        $goods = Goods::find(array(
                            'profile_id = :pid:',
                            'bind' => array(
                                'pid' => $pid,
                            ),
                            "order" => "id ASC"
                        ));

                        $id_list = array();
                        foreach ($goods as $key => $g) {
                            if ($g->status == "DELETED") continue;

                            $id_list[] = $g->id;
                        }

                        // логгирование
                        $l = new ClientCorrectionLogs();
                        $l->iin = $auth->idnum;
                        $l->type = 'GOODS';
                        $l->user_id = $auth->id;
                        $l->action = 'SEND_TO_MODERATOR';
                        $l->object_id = $pid;
                        $l->ccp_id = $cc_p->id;
                        $l->dt = time();
                        $l->meta_before = json_encode($id_list);
                        $l->meta_after = "Запрос на аннулирование!";
                        $l->comment = $good_comment;
                        $l->file = $goodeditfilename;
                        $l->sign = $sign;

                        if ($l->save()) {
                            // Генерация заявка на корректировку.
                            __genAppAnnulment($cc_p->id);
                        }

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        $this->logAction("Аннулирование отправлено на согласование.");
                        $this->flash->success("Аннулирование отправлено на согласование.");
                        return $this->response->redirect("/correction_request/");
                    } else {
                        $this->logAction("Невозможно отредактировать это транспортное средство.");
                        $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                        return $this->response->redirect("/order/");
                    }
                } else {
                    $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/order/");
                }
            } else {
                $this->logAction("Вы используете несоответствующую профилю подпись.", 'security');
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                return $this->response->redirect("/order/");
            }
        } else {
            $goods = Goods::findByProfileId($pid);
            $profile = Profile::findFirstById($pid);

            if ($goods && $profile) {
                if ($auth->id != $profile->user_id) {
                    $this->logAction("У вас нет прав на это действие.", 'security');
                    $this->flash->error("У вас нет прав на это действие.");
                    return $this->response->redirect("/order/");
                }

                $signData = __signData($pid, $this);

                $html = '';

                foreach ($goods as $value) {
                    $edited_good = CorrectionLogs::find([
                        "conditions" => "object_id = :gid: and type = :type:",
                        "bind" => [
                            "gid" => $value->id,
                            "type" => "GOODS"
                        ]
                    ]);

                    if (!empty($edited_good)) {
                        foreach ($edited_good as $key => $log) {

                            $edited_good_user = PersonDetail::findFirst([
                                "conditions" => "iin = :uiin:",
                                "bind" => [
                                    "uiin" => $log->iin
                                ]
                            ]);

                            if ($log->action == "CORRECTION") {
                                $action = "Корректирование";
                            } elseif ($log->action == "DELETED") {
                                $action = "Удаление";
                            } elseif ($log->action == "CREATED") {
                                $action = "Добавление";
                            } elseif ($log->action == "ANNULMENT") {
                                $action = "Аннулирование";
                            } elseif ($log->action == "CORRECTION_APPROVED") {
                                $action = "КОРРЕКТИРОВАНИЕ ПРОИЗВЕДЕНО";
                            }

                            $html .= '<tr>';

                            $html .= '<td>' . $log->object_id . '</td>
                                  <td>' . $edited_good_user->last_name . ' ' . $edited_good_user->first_name . ' ' . '<br>(' . $log->iin . ')</td>
                                  <td>' . $action . '</td>
                                  <td>' . date("d.m.Y H:i:s", $log->dt) . '</td>';
                            $html .= '</tr>';

                        }
                    } else {
                        $html = '';
                    }
                }

                $count = count($goods);

                $this->view->setVars(array(
                    "goods" => $goods,
                    "count" => $count,
                    "sign_data" => $signData,
                    "pid" => $pid,
                    "logs" => $html
                ));
            } else {
                $this->flash->error("Объект не найден.");
                return $this->response->redirect("/order/");
            }
        }
    }

    protected function isSuperModeratorProfile($moderator_id, $approve): bool
    {
        $auth = User::getUserBySession();
        if ($moderator_id && $approve) {
            if ($auth->id === $moderator_id && $auth->isSuperModerator() && in_array($approve, ['NEUTRAL', 'DECLINED'])) {
                return true;
            }
        }

        return false;
    }

    public function calcCostAction($gid)
    {
        $goods = Goods::findFirstById($gid);
        $sum = $goods->goods_cost;
        $resp = $this->response->setContentType('application/json', 'UTF-8');

        if ($this->request->isPost()) {
            $weigth = $this->request->getPost("good_weight");
            $tn_code = $this->request->getPost("tn_code");
            $tn = RefTnCode::findFirstById($tn_code);
            $profile = Profile::findFirstById($goods->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);
            if ($tn) {
                $res = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $weigth, json_encode($tn));
                $sum = $res['sum'];
            } else {
                return $resp->setJsonContent(['sum' => $sum, 'note' => 'range_not_found']);
            }
        }

        return $resp->setJsonContent(['sum' => (float)$sum]);
    }
}
