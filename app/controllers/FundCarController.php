<?php

namespace App\Controllers;

use App\Services\Fund\FundService;
use ControllerBase;
use FundCar;
use FundProfile;
use RefCarCat;
use RefCarType;
use RefCarValue;
use RefFund;
use RefModel;
use User;

// DONE:10 Редактирование автомобиля (если профиль открыт)
// TODO:30 Форматирование цифровых значений

class FundCarController extends ControllerBase
{
    public function newAction($pid)
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            return $this->response->redirect("/fund/");
        }

        $car_types = RefCarType::find(array('id <= 3'));
        $m = 'CAR';
        if ($_GET['m'] == 'trac') {
            $car_types = RefCarType::find(array('id > 3'));
            $m = 'TRAC';
        }

        $cats = RefCarCat::find();

        $check_agro = FundCar::findFirst(array(
            "ref_car_cat IN (13, 14) AND fund_id = :fund_id:",
            "bind" => array(
                "fund_id" => $pid
            )
        ));

        $check_car = FundCar::findFirst(array(
            "ref_car_cat NOT IN (13, 14) AND fund_id = :fund_id:",
            "bind" => array(
                "fund_id" => $pid
            )
        ));

        if ($m == 'CAR' && $check_agro) {
            $this->flash->error("Нельзя добавлять автомобиль к заявке, где уже есть сельхозтехника.");
            $this->response->redirect("/fund/view/$pid");
        }

        if ($m == 'TRAC' && $check_car) {
            $this->flash->error("Нельзя добавлять сельхозтехнику к заявке, где уже есть автомобили.");
            $this->response->redirect("/fund/view/$pid");
        }

        if ($m == 'CAR') {
            $model = RefModel::find([
                'conditions' => 'ref_car_cat_id NOT IN (13, 14) AND is_visible = 1'
            ]);
        } else {
            $model = RefModel::find([
                'conditions' => 'ref_car_cat_id IN (13, 14) AND is_visible = 1'
            ]);
        }

        $this->view->setVars(array(
            "cats" => $cats,
            "car_types" => $car_types,
            "model" => $model,
            "pid" => $pid,
            "m" => $m
        ));
    }

    public function addAction()
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!");
            return $this->response->redirect("/fund/");
        }

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("fund");

            $f = FundProfile::findFirstById($pid);
            $fundService = new FundService();

            if ($auth->id != $f->user_id || $f->blocked) {
                $this->logAction("Вы не имеете права создавать новый автомобиль.");
                $this->flash->error("Вы не имеете права создавать новый автомобиль.");
                return $this->response->redirect("/fund/index/");
            } else {
                $car_year = $this->request->getPost("car_year");
                $car_date = $this->request->getPost("car_date");
                $car_cat = $this->request->getPost("car_cat");
                $car_volume = str_replace(',', '.', $this->request->getPost("car_volume"));
                $ref_st = $this->request->getPost("ref_st");
                $model = $this->request->getPost("model");

                $car_vin = $this->request->getPost("car_vin") ? mb_strtoupper($this->request->getPost("car_vin")) : '';
                $car_id_code = $this->request->getPost("car_id_code") ? mb_strtoupper($this->request->getPost("car_id_code")) : '';
                $car_body_code = $this->request->getPost("car_body_code") ? mb_strtoupper($this->request->getPost("car_body_code")) : '';
                $calculate_method = $this->request->getPost("calculate_method");

                $can_add = true;
                // проверка
                $added = FundCar::findFirstByFundId($f->id);
                if ($added) {
                    if ($added->ref_car_cat != $car_cat) {
                        $can_add = false;
                    }
                }

                // проверка ref_fund_key
                if ($f->ref_fund_key != null) {
                    // если электрокар
                    if ($f->ref_fund_key == 'M1_0' || $f->ref_fund_key == 'M2M3_0' || $f->ref_fund_key == 'M1_0_EXP') {
                        $exploted = explode("_", $f->ref_fund_key);
                        $cat_name = [];

                        if ($exploted[0] == "M1") {
                            $cat_name = array(1, 2);
                        } else {
                            if ($exploted[0] == "M2M3") {
                                $cat_name = array(9, 10, 11, 12);
                            }
                        }

                        if ($car_volume == $exploted[1] && in_array($car_cat, $cat_name)) {
                            $can_add = true;
                        } else {
                            $can_add = false;
                            $this->flash->error("Ошибка: Разрешенная категория ТС: $exploted[0], С электродвигателем");
                            return $this->response->redirect("/fund/view/$f->id");
                        }
                    } else {
                        $exploted = explode("_", $f->ref_fund_key);
                        $cat_name = [];

                        if ($exploted[0] == "TRACTOR") {
                            $cat_name = array(13);
                        } else {
                            if ($exploted[0] == "COMBAIN") {
                                $cat_name = array(14);
                            } else {
                                if ($exploted[0] == "M1") {
                                    $cat_name = array(1, 2);
                                } else {
                                    if ($exploted[0] == "M2M3") {
                                        $cat_name = array(9, 10, 11, 12);
                                    } else {
                                        if ($exploted[0] == "N") {
                                            $cat_name = array(3, 4, 5, 6, 7, 8);
                                            if ($exploted[3] != null) {
                                                if ($exploted[3] == 'ST') {
                                                    if ($ref_st != 1) {
                                                        $can_add = false;
                                                        $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Седельный тягач)");
                                                        return $this->response->redirect("/fund/view/$f->id");
                                                    }
                                                } else {
                                                    if ($ref_st != 0) {
                                                        $can_add = false;
                                                        $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Не седельный тягач)");
                                                        return $this->response->redirect("/fund/view/$f->id");
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ($car_volume <= $exploted[2] && $car_volume >= $exploted[1] && in_array($car_cat, $cat_name)) {
                        } else {
                            $can_add = false;
                            $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем должен быть: От $exploted[1] до $exploted[2]");
                            return $this->response->redirect("/fund/view/$f->id");
                        }
                    }
                }

                if (!$car_vin) {
                    if (!$car_id_code) {
                        $car_id_code = 'I';
                    }
                    if (!$car_body_code) {
                        $car_body_code = 'B';
                    }
                }

                if ($car_vin) {
                    $car_vin = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_vin);
                    $car_vin = preg_replace('/(\W)/', '', $car_vin);
                    $vin_length = mb_strlen($car_vin);

                    if ($vin_length != 17) {
                        $this->flash->error("Вы ввели VIN-код длиной менее 17 символов. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые в VIN-коде запрещены к использованию.");
                        $this->response->redirect("/fund/view/$f->id");
                    }
                } else {
                    $car_id_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_id_code);
                    $car_body_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_body_code);
                    $car_vin = preg_replace('/(\W)/', '', $car_id_code) . '-' . preg_replace('/(\W)/', '', $car_body_code);
                    $vin_length = mb_strlen($car_vin);

                    if ($vin_length <= 3) {
                        $this->flash->error("Вы не ввели обязательный идентификатор или номер кузова. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые запрещены к использованию.");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                }

                // проверки при добавлении
                $_car_check_cons = true;

                // проверяем старые заявки
                $car_check = FundCar::findFirstByVin($car_vin);

                if ($car_check) {
                    $_car_check_cons = false;
                    $this->flash->error("VIN $car_vin уже был представлен в заявке №" . __getFundNumber($car_check->fund_id) . ".");
                    return $this->response->redirect("/fund/view/$f->id");
                }

                if ($f->type == 'EXP') {
                    // проверяем утильплатежи для экспортных машин
                    if ($pr = __checkPayment($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе утилизационных платежей в заявке №" . __getProfileNumber($pr) . ".");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                    // проверяем старый экспорт, до октября 2020
                    if ($pr = __checkExport($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                } else {
                    // проверка ДПП
                    if ($pr = __checkDPP($car_vin, $car_volume)) {
                        $_car_check_cons = false;
                        $this->flash->error("По VIN $car_vin отсутствует оплата утилизационного платежа или не соответствуют характеристики");
                        return $this->response->redirect("/fund/view/$f->id");
                    }

                    // проверяем старый экспорт, до октября 2020
                    if ($pr = __checkInner($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                }

                $_lim_can_add = true;
                $u = User::findFirstById($f->user_id);
                $prod = strtotime($car_date . " 00:00:00");
                $year = date('Y', $f->created);
                $carCategory = RefCarCat::findFirstById($car_cat);
                $limitCount = $fundService->getCarLimitCount($f);

                if ($car_volume == 0) {
                    $value = RefCarValue::findFirst(array(
                        "conditions" => "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                        "bind" => array(
                            "car_type" => $carCategory->car_type,
                            "volume_start" => $car_volume,
                            "volume_end" => $car_volume
                        )
                    ));
                } else {
                    $value = RefCarValue::findFirst(array(
                        "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                        "bind" => array(
                            "car_type" => $carCategory->car_type,
                            "volume_end" => $car_volume,
                            "volume_start" => $car_volume,
                        )
                    ));
                }
                $rf = RefFund::findFirst([
                    "conditions" => "key = :key: AND idnum = :idnum: AND prod_start <= :prod_start: AND prod_end >= :prod_end: AND year = :year:",
                    "bind" => [
                        "key" => $f->ref_fund_key,
                        "idnum" => $u->idnum,
                        "prod_start" => $prod,
                        "prod_end" => $prod,
                        "year" => $year,
                    ],
                    'order' => 'id DESC'
                ]);

                $fundProfiles = FundProfile::find([
                    "conditions" => "created < :end: AND created > :start: AND user_id = :user_id: AND type = :type:",
                    "bind" => [
                        "end" => strtotime("31.12.$year 23:59:59"),
                        "start" => strtotime("01.01.$year 00:00:00"),
                        "user_id" => $f->user_id,
                        "type" => "EXP"
                    ]
                ]);
                $fundCarCount = 0;
                if ($rf) {
                    foreach ($fundProfiles as $t_f) {
                        $fundCarCount += FundCar::count([
                            "conditions" => "fund_id = :fund_id: AND date_produce < :end: AND date_produce > :start: AND ref_car_type_id = :cat: AND volume >= :vs: AND volume <= :ve: AND ref_st_type = :opt:",
                            "bind" => [
                                "fund_id" => $t_f->id,
                                "end" => $rf->prod_end,
                                "start" => $rf->prod_start,
                                "cat" => $carCategory->car_type,
                                "vs" => $value->volume_start,
                                "ve" => $value->volume_end,
                                "opt" => $ref_st
                            ]
                        ]);
                    }
                }

                $fundCarCount = $fundCarCount + 1;
                if (($fundCarCount > $limitCount) || !$rf) {
                    $_lim_can_add = false;
                    $this->flash->error("Превышение лимитов по ТС $car_vin, объект не был добавлен.");
                    return $this->response->redirect("/fund/view/$f->id");
                }

                if ($f->created < START_ZHASYL_DAMU_FUND_MAY) {
                    $_lim_can_add = false;
                    $this->flash->error("Невозможно добавить ТС к данной заявке, создайте новую заявку!");
                    return $this->response->redirect("/fund");
                }

                $car_cats = RefCarCat::findFirstById($car_cat);
                $car_type = $car_cats->car_type;

                if ($ref_st == 0) {
                    //для финансирования по электромобилям ставки не такие как в УП, поэтому отдельная проверка и ставка в таблице ref_car_value
                    if ($car_volume == 0) {
                        $value = RefCarValue::findFirst(array(
                            "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_end" => $car_volume,
                                "volume_start" => $car_volume,
                            )
                        ));
                    } else {
                        $value = RefCarValue::findFirst(array(
                            "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_end" => $car_volume,
                                "volume_start" => $car_volume,
                            )
                        ));
                    }
                } else {
                    $value = RefCarValue::findFirst(array(
                        "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                        "bind" => array(
                            "car_type" => $car_type,
                            "volume_end" => $car_volume,
                            "volume_start" => $car_volume,
                        )
                    ));
                }

                // NOTE: Расчет платежа (добавление машины)
                if ($value != false) {
                    $up_date = __getUpDatesByCarVin($car_vin);

                    if ($calculate_method == 1 && $up_date['MD_DT_SENT'] > 0) {
                        $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                    } elseif ($calculate_method == 2) {
                        if ($up_date['CALCULATE_METHOD'] == 0) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['DATE_IMPORT']), $car_volume, json_encode($value), $ref_st);
                        }
                        if ($up_date['CALCULATE_METHOD'] == 1) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                        }
                        if ($up_date['CALCULATE_METHOD'] == 2) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['FIRST_REG_DATE']), $car_volume, json_encode($value), $ref_st);
                        }
                    } else {
                        $sum = __calculateCarByDate($car_date, $car_volume, json_encode($value), $ref_st);
                    }

                    $c = new FundCar();
                    $c->volume = $car_volume;
                    $c->vin = $car_vin;
                    $c->date_produce = strtotime($car_date);
                    $c->fund_id = $f->id;
                    $c->ref_car_cat = $car_cat;
                    $c->ref_car_type_id = $car_type;
                    $c->ref_st_type = $ref_st;
                    $c->calculate_method = $calculate_method;
                    $c->cost = $sum;
                    $c->model_id = $model;

                    if ($_car_check_cons == true && $_lim_can_add) {
                        $next = false;
                        if ($car_type <= 3) {
                            if ($vin_length == 17) {
                                $next = true;
                            }
                        } else {
                            if ($vin_length >= 7) {
                                $next = true;
                            }
                        }

                        // если длина VIN в норме
                        if ($next) {
                            if ($can_add) {
                                if ($c->save()) {
                                    __fundRecalc($f->id);

                                    $this->logAction("Новая машина добавлена.");
                                    $this->flash->success("Новая машина добавлена.");
                                } else {
                                    $this->logAction("Невозможно сохранить новую машину.");
                                    $this->flash->warning("Невозможно сохранить новую машину.");
                                }
                            } else {
                                $this->flash->warning("В одном заявлении могут присутствовать только ТС одного модельного ряда и категории.");
                            }
                        }
                    }

                    $this->response->redirect("/fund/view/$f->id");
                }
            }
        }
    }

    public function editAction($cid)
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            return $this->response->redirect("/fund/");
        }

        if ($this->request->isPost()) {
            $car = FundCar::findFirstById($cid);
            $f = FundProfile::findFirstById($car->fund_id);

            $car_date = $this->request->getPost("car_date");
            $car_year = $this->request->getPost("car_year");
            $car_volume = str_replace(',', '.', (string)($this->request->getPost('car_volume') ?? ''));
            $ref_st = $this->request->getPost("ref_st");
            $car_cat = $this->request->getPost("car_cat");
            $model = $this->request->getPost("model");

            $car_vin = $this->request->getPost("car_vin");
            $car_id_code = $this->request->getPost("car_id_code") ? mb_strtoupper($this->request->getPost("car_id_code")) : '';
            $car_body_code = $this->request->getPost("car_body_code") ? mb_strtoupper($this->request->getPost("car_body_code")) : '';
            $calculate_method = $this->request->getPost("calculate_method");

            if ($f->type == "INS") {
                $car->model_id = $model;
                if ($car->save()) {
                    __fundRecalc($f->id);
                    $this->logAction("Транспортное средство изменено успешно.");
                    $this->flash->success("Транспортное средство изменено успешно.");
                    return $this->response->redirect("/fund/view/$f->id");
                }
            } else {
                $editable = true;
                // проверка
                $added = FundCar::findFirstByFundId($f->id);

                if ($added && $added->id != $car->id) {
                    if ($added->ref_car_cat != $car_cat) {
                        $editable = false;
                    }
                } else {
                    $car_cnt = FundCar::countByFundId($f->id);
                    if ($car_cnt > 1) {
                        $editable = false;
                    }
                }

                if (!$car_vin) {
                    if (!$car_id_code) {
                        $car_id_code = 'I';
                    }
                    if (!$car_body_code) {
                        $car_body_code = 'B';
                    }
                }

                // проверка ref_fund_key
                if ($f->ref_fund_key != null) {
                    // если электрокар
                    if ($f->ref_fund_key == 'M1_0' || $f->ref_fund_key == 'M2M3_0' || $f->ref_fund_key == 'M1_0_EXP') {
                        $exploted = explode("_", $f->ref_fund_key);
                        $cat_name = [];

                        if ($exploted[0] == "M1") {
                            $cat_name = array(1, 2);
                        } else {
                            if ($exploted[0] == "M2M3") {
                                $cat_name = array(9, 10, 11, 12);
                            }
                        }

                        if ($car_volume == $exploted[1] && in_array($car_cat, $cat_name)) {
                        } else {
                            $can_add = false;
                            $this->flash->error("Ошибка: Разрешенная категория ТС: $exploted[0], С электродвигателем");
                            return $this->response->redirect("/fund/view/$f->id");
                        }
                    } else {
                        $exploted = explode("_", $f->ref_fund_key);
                        $cat_name = [];

                        if ($exploted[0] == "TRACTOR") {
                            $cat_name = array(13);
                        } else {
                            if ($exploted[0] == "COMBAIN") {
                                $cat_name = array(14);
                            } else {
                                if ($exploted[0] == "M1") {
                                    $cat_name = array(1, 2);
                                } else {
                                    if ($exploted[0] == "M2M3") {
                                        $cat_name = array(9, 10, 11, 12);
                                    } else {
                                        if ($exploted[0] == "N") {
                                            $cat_name = array(3, 4, 5, 6, 7, 8);
                                            if ($exploted[3] != null) {
                                                if ($exploted[3] == 'ST') {
                                                    if ($ref_st != 1) {
                                                        $can_add = false;
                                                        $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Седельный тягач)");
                                                        return $this->response->redirect("/fund/view/$f->id");
                                                    }
                                                } else {
                                                    if ($ref_st != 0) {
                                                        $can_add = false;
                                                        $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Не седельный тягач)");
                                                        return $this->response->redirect("/fund/view/$f->id");
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ($car_volume <= $exploted[2] && $car_volume >= $exploted[1] && in_array($car_cat, $cat_name)) {
                        } else {
                            $can_add = false;
                            $this->flash->error("Ошибка: Разрешенная категория ТС $exploted[0], Обьем должен быть: От $exploted[1] до $exploted[2]");
                            return $this->response->redirect("/fund/view/$f->id");
                        }
                    }
                }

                if ($car_vin) {
                    $car_vin = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_vin);
                    $car_vin = preg_replace('/(\W)/', '', $car_vin);
                    $vin_length = mb_strlen($car_vin);
                    if ($vin_length != 17) {
                        $this->flash->error("Вы ввели VIN-код длиной менее 17 символов. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые в VIN-коде запрещены к использованию.");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                } else {
                    $car_id_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_id_code);
                    $car_body_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_body_code);
                    $car_vin = preg_replace('/(\W)/', '', $car_id_code) . '-' . preg_replace('/(\W)/', '', $car_body_code);
                    $vin_length = mb_strlen($car_vin);

                    if ($vin_length <= 3) {
                        $this->flash->error("Вы не ввели обязательный идентификатор или номер кузова. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые запрещены к использованию.");
                        return $this->response->redirect("/fund/view/$f->id");
                    }
                }

                if ($auth->id != $f->user_id || $f->blocked) {
                    $this->logAction("Вы не имеете права редактировать этот объект.");
                    $this->flash->error("Вы не имеете права редактировать этот объект.");
                    return $this->response->redirect("/fund/index/");
                }

                // проверки при добавлении
                $_car_check_cons = true;

                if ($f->type == 'EXP') {
                    // проверяем утильплатежи для экспортных машин
                    if ($pr = __checkPayment($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе утилизационных платежей в заявке №" . __getProfileNumber($pr) . ".");
                    }
                    // проверяем старый экспорт, до октября 2020
                    if ($pr = __checkExport($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.");
                    }
                } else {
                    // проверка ДПП
                    if ($pr = __checkDPP($car_vin, $car_volume)) {
                        $_car_check_cons = false;
                        $this->flash->error("По VIN $car_vin отсутствует оплата утилизационного платежа или не соответствуют характеристики");
                    }
                    // проверяем старый экспорт, до октября 2020
                    if ($pr = __checkInner($car_vin)) {
                        $_car_check_cons = false;
                        $this->flash->error("VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.");
                    }
                }

                $_lim_can_add = true;
                $__lim = __checkLimits($f->id, $car_cat, $car_volume, $ref_st, strtotime($car_date . " 00:00:00"), date('Y', $f->created));
                if ($__lim < 0) {
                    $_lim_can_add = false;
                    $this->flash->error("Превышение лимитов по ТС $car_vin, объект не был добавлен.");
                }

                if ($f->created < START_ZHASYL_DAMU_FUND_MAY) {
                    $_lim_can_add = false;
                    $this->flash->error("Невозможно добавить ТС к данному заявку, создайте новую заявку !");
                    return $this->response->redirect("/fund");
                }

                $car_cats = RefCarCat::findFirstById($car_cat);
                $car_type = $car_cats->car_type;

                if ($ref_st == 0) {
                    //для финансирования по электромобилям ставки не такие как в УП, поэтому отдельная проверка и ставка в таблице ref_car_value
                    if ($car_volume == 0) {
                        $value = RefCarValue::findFirst(array(
                            "conditions" => "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_start" => $car_volume,
                                "volume_end" => $car_volume
                            )
                        ));
                    } else {
                        $value = RefCarValue::findFirst(array(
                            "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_end" => $car_volume,
                                "volume_start" => $car_volume,
                            )
                        ));
                    }
                } else {
                    $value = RefCarValue::findFirst(array(
                        "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                        "bind" => array(
                            "car_type" => $car_type,
                            "volume_end" => $car_volume,
                            "volume_start" => $car_volume,
                        )
                    ));
                }

                // NOTE: Расчет платежа (правка машины)
                if ($value != false) {
                    $up_date = __getUpDatesByCarVin($car_vin);

                    if ($calculate_method == 1 && $up_date['MD_DT_SENT'] > 0) {
                        $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                    } elseif ($calculate_method == 2) {
                        if ($up_date['CALCULATE_METHOD'] == 0) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['DATE_IMPORT']), $car_volume, json_encode($value), $ref_st);
                        }
                        if ($up_date['CALCULATE_METHOD'] == 1) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                        }
                        if ($up_date['CALCULATE_METHOD'] == 2) {
                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['FIRST_REG_DATE']), $car_volume, json_encode($value), $ref_st);
                        }
                    } else {
                        $sum = __calculateCarByDate($car_date, $car_volume, json_encode($value), $ref_st);
                    }

                    $car->volume = $car_volume;
                    $car->vin = $car_vin;
                    $car->date_produce = strtotime($car_date);
                    $car->fund_id = $f->id;
                    $car->ref_car_cat = $car_cat;
                    $car->ref_car_type_id = $car_type;
                    $car->model_id = $model;
                    $car->ref_st_type = $ref_st;
                    $car->calculate_method = $calculate_method;
                    $car->cost = $sum;

                    if ($_car_check_cons == true && $_lim_can_add) {
                        $next = false;
                        if ($car_type <= 3) {
                            if ($vin_length == 17) {
                                $next = true;
                            }
                        } else {
                            if ($vin_length >= 7) {
                                $next = true;
                            }
                        }

                        // если с длиной VIN все в порядке
                        if ($next) {
                            if ($editable) {
                                if ($car->save()) {
                                    __fundRecalc($f->id);

                                    $this->logAction("Транспортное средство изменено успешно.");
                                    $this->flash->success("Транспортное средство изменено успешно.");
                                } else {
                                    $this->logAction("Невозможно отредактировать это транспортное средство.");
                                    $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                                }
                            } else {
                                $this->flash->warning("В одном заявлении могут присутствовать только ТС одного модельного ряда и категории.");
                            }
                        }
                    }
                }
            }

            return $this->response->redirect("/fund/view/$f->id");
        } else {
            $car = FundCar::findFirstById($cid);
            $f = FundProfile::findFirstById($car->fund_id);

            $car_types = RefCarType::find(array('id <= 3'));
//            $model = RefModel::find();

            $model = RefModel::find([
                'conditions' => 'is_visible = 1'
            ]);

            $m = 'CAR';
            if ($car->ref_car_type_id > 3) {
                $car_types = RefCarType::find(array('id > 3'));
                $m = 'TRAC';
            }

            $car_cats = RefCarCat::find();

            if (($auth->id != $f->user_id) || $f->blocked) {
                $this->logAction("Вы не имеете права редактировать этот объект.");
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                return $this->response->redirect("/fund/index/");
            }

            $this->view->setVars(array(
                "car" => $car,
                "car_types" => $car_types,
                "model" => $model,
                "car_cats" => $car_cats,
                "m" => $m,
                "type" => $f->type,
            ));
        }
    }

    public function deleteAction($cid)
    {
        $auth = User::getUserBySession();

        $car = FundCar::findFirstById($cid);
        $f = FundProfile::findFirstById($car->fund_id);

        if ($auth->id != $f->user_id || $f->blocked) {
            $this->logAction("Вы не имеете права удалять этот объект.");
            $this->flash->error("Вы не имеете права удалять этот объект.");

            $this->dispatcher->forward(array(
                "controller" => "fund",
                "action" => "index"
            ));
        } else {
            if ($car->delete()) {
                __fundRecalc($f->id);
                $this->logAction("Удаление произошло успешно.");
                $this->flash->success("Удаление произошло успешно.");
                return $this->response->redirect("/fund/view/$f->id");
            }
        }
    }

}
