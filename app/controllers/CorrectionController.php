<?php
namespace App\Controllers;

use App\Services\Cms\CmsService;
use App\Services\Kap\KapService;
use Car;
use ClientCorrectionCars;
use ClientCorrectionGoods;
use ClientCorrectionLogs;
use ClientCorrectionProfile;
use ClientCorrectionFile;
use ControllerBase;
use CorrectionLogs;
use CurrencyEach;
use CurrencyRequest;
use FundCar;
use FundCorrectionLogs;
use Goods;
use Kpp;
use PersonDetail;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorBuilder;
use Profile;
use RefCarCat;
use RefCarType;
use RefCarValue;
use RefCountry;
use RefInitiator;
use RefTnCode;
use TExport;
use TInner;
use Transaction;
use User;

class CorrectionController extends ControllerBase
{
    //corr
    public function indexAction(): void
    {
        $auth = User::getUserBySession();

        $can_annul = $auth->hasPermission('correction', '*')
            || $auth->hasPermission('correction', 'goods_view')
            || $auth->hasPermission('correction', 'kpps_view');

        $can_add_goods = $auth->hasPermission('correction', '*')
            || $auth->hasPermission('correction', 'new');

        // фильтр профиля из POST
        if ($this->request->isPost()) {
            $pid   = $this->request->getPost('num', ['int', 'absint']);
            $clear = $this->request->getPost('clear', 'string');

            if ($clear === 'clear') {
                $this->session->remove('filter_correction');
            } elseif (!empty($pid)) {
                $this->session->set('filter_correction', (int) $pid);
            }
        }

        // текущий pid из сессии
        $pid = $this->session->has('filter_correction')
            ? (int) $this->session->get('filter_correction')
            : null;

        $pr = $pid ? Profile::findFirstById($pid) : null;
        if (!$pr) {
            $this->view->setVars([
                'pid'           => null,
                'pr'            => null,
                'can_annul'     => $can_annul,
                'can_add_goods' => $can_add_goods,
            ]);
            return;
        }

        $numberPage = $this->request->getQuery('page', 'int', 1);

        switch ($pr->type) {
            case 'CAR':
                $builder = $this->modelsManager->createBuilder()
                    ->columns([
                        'c.id            AS c_id',
                        'c.volume        AS c_volume',
                        'c.vin           AS c_vin',
                        'c.year          AS c_year',
                        'c.status        AS c_status',
                        'c.date_import   AS c_date_import',
                        'cc.name         AS c_car_cat',
                        'country.name    AS c_country',
                        't.name          AS c_type',
                        'p.id            AS c_profile',
                        'tr.id           AS c_tr',
                        'tr.approve      AS tr_approve',
                        'tr.status       AS tr_status',
                        'tr.ac_approve   AS tr_ac_approve',
                    ])
                    ->from(['c' => Car::class])
                    ->join(Profile::class, 'p.id = c.profile_id', 'p')
                    ->join(RefCarCat::class, 'cc.id = c.ref_car_cat', 'cc')
                    ->join(RefCountry::class, 'country.id = c.ref_country', 'country')
                    ->join(RefCarType::class, 't.id = c.ref_car_type_id', 't')
                    ->join(Transaction::class, 'tr.profile_id = p.id', 'tr')
                    ->where('p.id = :pid:', ['pid' => $pid])
                    ->orderBy('c.id DESC');

                $paginator = new PaginatorBuilder([
                    'builder' => $builder,
                    'limit'   => 10,
                    'page'    => $numberPage,
                ]);

                $this->view->page = $paginator->paginate();
                $this->view->setVars([
                    'pid'           => $pid,
                    'cars'          => $this->view->page->items,
                    'pr'            => $pr,
                    'can_annul'     => $can_annul,
                    'can_add_goods' => $can_add_goods,
                ]);
                break;

            case 'GOODS':
                $builder = $this->modelsManager->createBuilder()
                    ->columns([
                        'g.id           AS g_id',
                        'g.date_import  AS g_date',
                        'g.weight       AS g_weight',
                        'g.price        AS g_price',
                        'g.amount       AS g_amount',
                        'tn.code        AS tn_code',
                        'tn.name        AS tn_name',
                        'tr.id          AS c_tr',
                        'tr.approve     AS tr_approve',
                        'tr.status      AS tr_status',
                        'tr.ac_approve  AS tr_ac_approve',
                        'g.basis        AS g_basis',
                        'g.ref_tn_add   AS tn_add',
                        'g.status       AS g_status',
                        'g.profile_id   AS g_pid',
                    ])
                    ->from(['g' => Goods::class])
                    ->join(RefTnCode::class, 'tn.id = g.ref_tn', 'tn')
                    ->join(Transaction::class, 'tr.profile_id = g.profile_id', 'tr')
                    ->where('g.profile_id = :pid:', ['pid' => $pid])
                    ->orderBy('g.id DESC');

                $paginator = new PaginatorBuilder([
                    'builder' => $builder,
                    'limit'   => 10,
                    'page'    => $numberPage,
                ]);

                $this->view->page = $paginator->paginate();
                $this->view->setVars([
                    'pid'           => $pid,
                    'goods'         => $this->view->page->items,
                    'pr'            => $pr,
                    'can_annul'     => $can_annul,
                    'can_add_goods' => $can_add_goods,
                ]);
                break;

            case 'KPP':
                $builder = $this->modelsManager->createBuilder()
                    ->columns([
                        'k.id                 AS k_id',
                        'k.date_import        AS k_date',
                        'k.weight             AS k_weight',
                        'k.status             AS k_status',
                        'k.invoice_sum        AS k_invoice_sum',
                        'k.invoice_sum_currency AS k_invoice_sum_currency',
                        'k.currency_type      AS k_currency_type',
                        'k.amount             AS k_amount',
                        'tn.code              AS tn_code',
                        'tn.name              AS tn_name',
                        'tr.profile_id        AS tr_profile_id',
                        'tr.approve           AS tr_approve',
                        'tr.status            AS tr_status',
                        'tr.ac_approve        AS tr_ac_approve',
                        'k.basis              AS k_basis',
                        'k.basis_date         AS b_date',
                        'k.package_weight     AS p_weight',
                        'k.package_cost       AS p_cost',
                        'k.package_tn_code    AS p_tn_code',
                    ])
                    ->from(['k' => Kpp::class])
                    ->join(RefTnCode::class, 'tn.id = k.ref_tn', 'tn')
                    ->join(Transaction::class, 'tr.profile_id = k.profile_id', 'tr')
                    ->where('k.profile_id = :pid:', ['pid' => $pid])
                    ->orderBy('k.id DESC');

                $paginator = new PaginatorBuilder([
                    'builder' => $builder,
                    'limit'   => 10,
                    'page'    => $numberPage,
                ]);

                $this->view->page = $paginator->paginate();
                $this->view->setVars([
                    'pid'           => $pid,
                    'kpps'          => $this->view->page->items,
                    'pr'            => $pr,
                    'can_annul'     => $can_annul,
                    'can_add_goods' => $can_add_goods,
                ]);
                break;

            default:
                $this->view->setVars([
                    'pid'           => $pid,
                    'pr'            => $pr,
                    'can_annul'     => $can_annul,
                    'can_add_goods' => $can_add_goods,
                ]);
        }
    }

    public function editCarAction($cid)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $car = Car::findFirstById($cid);
            $old_vin = $car->vin;
            $_before = json_encode(array($car));
            $vin_before = "car:$car->vin, car_id:$car->id, car_profile_id:$car->profile_id";
            $vin_after = null;
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");


            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            $ext = '';
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if ($this->request->hasFiles()) {
                        foreach ($this->request->getUploadedFiles() as $file) {
                            if ($file->getSize() > 0) {
                                $careditfilename = $car->vin . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                $file->moveTo(APP_PATH . "/private/correction/" . $careditfilename);
                                  $originalName = $file->getName();
                                $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
                                copy(APP_PATH . "/private/correction/" . $careditfilename, APP_PATH . "/private/client_correction_docs/" . $careditfilename);
                            }
                        }
                    }

                    if (file_exists(APP_PATH . '/private/certificates/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates/corrected/"' . $car->vin . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/"' . $car->vin . '"_"' . time() . '".pdf ');
                    }

                    $profile = Profile::findFirstById($car->profile_id);
                    $tr = Transaction::findFirstByProfileId($profile->id);

                    // удаляем СВУП архив(svup_XXXX.zip) если есть
                    if ($cf = __checkSVUPZip($profile->id)) {
                        $svup_path = APP_PATH . '/private';
                        $svup_dir = $cf['cert_dir'];
                        $svup_file = $cf['file'];

                        exec("rm -rf $svup_path/$svup_dir/$svup_file");
                    }

                    $car_date_import = $this->request->getPost("car_date");
                    $car_year = $this->request->getPost("car_year");
                    $car_country = $this->request->getPost("car_country");
                    $car_volume = str_replace(',', '.', $this->request->getPost("car_volume"));
                    $vehicle_type = $this->request->getPost("vehicle_type");

                    $ref_st = $this->request->getPost("ref_st");
                    $car_cat = $this->request->getPost("car_cat");

                    $car_vin = $this->request->getPost("car_vin");

                    $car_check = Car::findFirst([
                        'conditions' => 'vin = :vin:',
                        'bind'       => ['vin' => $car_vin],
                    ]);

                    if ($car_check && $car_check->id != $car->id) {
                        $this->flash->error("VIN код ".$car_vin." уже привязан другой заявке");
                        return $this->response->redirect("/order/index/");
                    }


                    $car_comment = $this->request->getPost("car_comment");
                    $initiator_id = $this->request->getPost("initiator");

                    $car_id_code = mb_strtoupper($this->request->getPost("car_id_code"));
                    $car_body_code = mb_strtoupper($this->request->getPost("car_body_code"));
                    $calculate_method = $this->request->getPost("calculate_method");
                    $e_car = $this->request->getPost("e_car");

                    $first_reg_date = date('Y-m-d', $car->first_reg_date);
                    $kap_info = null;
                    $kap_request_id = $car->kap_request_id;

                    if($car_comment == '' || strlen($car_comment) < 4){
                        $this->flash->error("Поле «Комментарий» обязательно для заполнения.");
                        return $this->response->redirect("/correction/edit_car/" . $cid);
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
                        }
                    } else {
                        $car_id_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_id_code);
                        $car_body_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_body_code);
                        $car_vin = preg_replace('/(\W)/', '', $car_id_code) . '-' . preg_replace('/(\W)/', '', $car_body_code);
                        $vin_length = mb_strlen($car_vin);

                        if ($vin_length <= 3) {
                            $this->flash->error("Вы не ввели обязательный идентификатор или номер кузова. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые запрещены к использованию.");
                        }
                    }

                    $car_cats = RefCarCat::findFirstById($car_cat);
                    $car_type = $car_cats->car_type;

                    if(in_array($car_type, [2])){
                        $vehicle_type = 'CARGO';
                    }  else if(in_array($car_type, [1, 3])){
                        $vehicle_type = 'PASSENGER';
                    }else if(in_array($car_type, [4, 5])){
                        $vehicle_type = 'AGRO';
                    }

                    if($car_type != 6) {
                        if ($e_car && $car_type != 2 && $car_volume > 0) {
                            $this->flash->notice("Объем электромобиля(легковой или автобус) должен быть 0");
                            return $this->response->redirect("/correction/index/");
                        }
                    }

                    if(in_array($car_type, [2])){
                        $vehicle_type = 'CARGO';
                    }  else if(in_array($car_type, [1, 3])){
                        $vehicle_type = 'PASSENGER';
                    }else if(in_array($car_type, [4, 5])){
                        $vehicle_type = 'AGRO';
                    }

                    $value = RefCarValue::findFirst(array(
                        "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                        "bind" => array(
                            "car_type" => $car_type,
                            "volume_start" => $car_volume,
                            "volume_end" => $car_volume
                        )
                    ));

                    // NOTE: Расчет платежа (правка машины)
                    if ($value != false) {
                        if ($calculate_method == 1) {
                            $sum = __calculateCarByDate(date('d.m.Y', $tr->md_dt_sent), $car_volume, json_encode($value), $ref_st, $e_car);
                        } elseif ($calculate_method == 2) {
                            $kapService = new KapService();
                            $kapRegInfo = $kapService->getRegInfo($car_vin);
                            if ($kapRegInfo && $kapRegInfo['regDate']) {
                                $first_reg_date = $kapRegInfo['regDate'];
                            }

                            if (strtotime($first_reg_date) > strtotime(STARTROP)) {
                                $sum = __calculateCarByDate($first_reg_date, $car_volume, json_encode($value), $ref_st, $e_car);
                            } else {
                                $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                                $this->response->redirect("/correction/index/");
                                exit();
                            }
                        } else {
                            $sum = __calculateCarByDate($car_date_import, $car_volume, json_encode($value), $ref_st, $e_car);
                        }

                        // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                        if ($car->cost != $sum) {
                            if (ClientCorrectionProfile::checkCurrentCorrection($car->id, 'CAR')) {
                                $this->flash->error("ТС с VIN $car->vin уже существует в базе корректировок");
                                return $this->response->redirect("/correction/");
                            }

                            $cc_profile = new ClientCorrectionProfile();
                            $cc_profile->created = time();
                            $cc_profile->action = "CORRECTION";
                            $cc_profile->type = 'CAR';
                            $cc_profile->user_id = $profile->user_id;
                            $cc_profile->profile_id = $profile->id;
                            $cc_profile->object_id = $car->id;
                            $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                            $cc_profile->initiator_id = $initiator_id;
                            $cc_profile->moderator_id = $auth->id;
                            $cc_profile->action_dt = time();
                            $cc_profile->sign = $sign;
                            $cc_profile->hash = $hash;

                            if ($cc_profile->save()) {
                                $c = new ClientCorrectionCars();
                                $c->volume = $car_volume;
                                $c->car_id = $car->id;
                                $c->vin = $car_vin;
                                $c->year = $car_year;
                                $c->date_import = strtotime($car_date_import);
                                $c->profile_id = $profile->id;
                                $c->ccp_id = $cc_profile->id;
                                $c->ref_car_cat = $car_cat;
                                $c->ref_car_type_id = $car_type;
                                $c->ref_country = $car_country;
                                $c->ref_st_type = $ref_st;
                                $c->calculate_method = $calculate_method;
                                $c->electric_car = $e_car;
                                $c->cost = $sum;
                                $c->vehicle_type = $car->vehicle_type;
                                $c->save();

                                $l = new ClientCorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'CAR';
                                $l->user_id = $auth->id;
                                $l->action = 'SENT_TO_ACCOUNTANT';
                                $l->object_id = $car ? $car->id : null;
                                $l->ccp_id = $cc_profile ? $cc_profile->id : null;
                                $l->dt = time();
                                $l->meta_before = $_before;
                                $l->meta_after = $c ? json_encode(array($c)) : null;
                                $l->comment = $car_comment;
                                $l->file = $careditfilename;
                                $l->hash = $hash;
                                $l->sign = $sign;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $f = new ClientCorrectionFile();
                                $f->profile_id     = $cc_profile->profile_id;
                                $f->type           = 'other';
                                $f->original_name  = $careditfilename;
                                $f->ext            = $ext;
                                $f->ccp_id         = $cc_profile->id;
                                $f->visible        = 1;
                                $f->user_id        = $auth->id;
                                $f->save();

                                $this->flash->success("Заявка отправлена к бухгалтеру.");
                                return $this->response->redirect("/correction_request/index/");
                            }
                        }

                        $tr = Transaction::findFirstByProfileId($profile->id);
                        $tr->amount = $tr->amount - $car->cost;

                        $car->volume = $car_volume;
                        $car->vin = $car_vin;
                        $car->year = $car_year;
                        $car->date_import = strtotime($car_date_import);
                        $car->profile_id = $profile->id;
                        $car->ref_car_cat = $car_cat;
                        $car->ref_car_type_id = $car_type;
                        $car->ref_country = $car_country;
                        $car->ref_st_type = $ref_st;
                        $car->cost = round($sum, 2);
                        $car->updated = time();
                        $car->calculate_method = $calculate_method;
                        $car->electric_car = $e_car ? 1 : 0;
                        $car->first_reg_date = strtotime($first_reg_date);
                        $car->kap_request_id = $kap_request_id;
                        $car->vehicle_type = $vehicle_type;

                        $tr->amount = $tr->amount + $sum;

                        $car_check = Car::find(array(
                            "vin = :car_vin:",
                            "bind" => array(
                                "car_vin" => $car->vin
                            )
                        ));

                        if ($car_type <= 3) {
                            if ($vin_length == 17) {
                                $next = true;
                            }
                        } else {
                            if ($vin_length >= 7) {
                                $next = true;
                            }
                        }

                        if ($car) {
                            if ($car->save()) {
                                if ($old_vin != $car->vin) {
                                    $vin_after .= "car:$car->vin, car_id:$car->id, car_profile_id:$car->profile_id";

                                    $fund_car = FundCar::findFirstByVin($old_vin);
                                    $t_inner = TInner::findFirstByVin($old_vin);
                                    $t_export = TExport::findFirstByVin($old_vin);

                                    if ($fund_car) {
                                        $f_before = json_encode(array($fund_car));
                                        $vin_before .= ", fund_car:$fund_car->vin, fund_car_id:$fund_car->id, fund_id:$fund_car->fund_id";
                                        $fund_car->vin = $car->vin;
                                        $fund_car->profile_id = $car->profile_id;

                                        if ($fund_car->save()) {
                                            $vin_after .= ", fund_car:$fund_car->vin, fund_car_id:$fund_car->id, fund_id:$fund_car->fund_id";

                                            $f_l = new FundCorrectionLogs();
                                            $f_l->iin = $auth->idnum;
                                            $f_l->type = 'FundCAR';
                                            $f_l->fund_id = $fund_car->fund_id;
                                            $f_l->user_id = $auth->id;
                                            $f_l->action = 'CORRECTION';
                                            $f_l->object_id = $fund_car->id;
                                            $f_l->dt = time();
                                            $f_l->meta_before = $f_before;
                                            $f_l->meta_after = json_encode(array($fund_car));
                                            $f_l->comment = $car_comment;
                                            $f_l->file = $careditfilename;
                                            $f_l->sign = $sign;
                                            $f_l->save();

                                            $logString = json_encode($f_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                            $this->logAction($logString);

                                            $this->flash->warning("В базе финансирование найдено ТС с таким VIN: $old_vin, был изменен на VIN: $fund_car->vin(номер заявки: $fund_car->fund_id)");
                                        }
                                    }

                                    if ($t_inner) {
                                        $vin_before .= ", t_inner:$t_inner->vin";
                                        $t_inner->vin = $car->vin;

                                        if ($t_inner->save()) {
                                            $vin_after .= ", t_inner:$t_inner->vin";
                                        }
                                    }

                                    if ($t_export) {
                                        $vin_before .= ", t_export:$t_export->vin";
                                        $t_export->vin = $car->vin;

                                        if ($t_export->save()) {
                                            $vin_after .= ", t_export:$t_export->vin";
                                        }
                                    }
                                } else {
                                    $vin_before = null;
                                }

                                // логгирование
                                $l = new CorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'CAR';
                                $l->user_id = $auth->id;
                                $l->profile_id = $profile ? $profile->id : null;
                                $l->action = 'CORRECTION';
                                $l->object_id = $car ? $car->id : null;
                                $l->dt = time();
                                $l->meta_before = $_before;
                                $l->meta_after = $car ? json_encode(array($car)) : null;
                                $l->vin_before = $vin_before;
                                $l->vin_after = $vin_after;
                                $l->comment = $car_comment;
                                $l->file = $careditfilename;
                                $l->sign = $sign;
                                $l->initiator_id = $initiator_id;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);
                            }
                        }

                        // если с длиной VIN все в порядке
                        if ($next) {
                            if ($car->save()) {
                                $this->flash->success("Транспортное средство отредактировано.");
                                $tr->save();

                                __carRecalc($profile->id);
                            } else {
                                $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                            }
                        }

                        return $this->response->redirect("/correction/index/$car->id");
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/correction/edit_car/" . $cid);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                return $this->response->redirect("/correction/edit_car/" . $cid);
            }
        } else {
            $car = Car::findFirstById($cid);
            $profile = Profile::findFirstById($car->profile_id);

            if ($car && $profile) {
                $tr = Transaction::findFirstByProfileId($profile->id);
                $signData = __signData($car->profile_id, $this);
                $car_types = RefCarType::find("id IN (1,2,3,6)");
                $m = 'CAR';
                if (in_array($car->ref_car_type_id, [4,5])) {
                    $car_types = RefCarType::find("id IN (4,5)");
                    $m = 'TRAC';
                }

                $car_cats = RefCarCat::find();
                $countries = RefCountry::find(array('id <> 1'));

                $user = User::getUserBySession();
                if($user->isAdmin()){
                    $initiators = RefInitiator::find();
                }else{
                    $initiators = RefInitiator::find([
                        'conditions' => 'id IN (1,2,3)',
                    ]);
                }

                $correction = ClientCorrectionProfile::findFirstByObjectId($car->id);
                $initiator_id = null;
                if ($correction) {
                    $initiator_id = $correction->initiator_id;
                }

                $this->view->setVars(array(
                    "car" => $car,
                    "car_types" => $car_types,
                    "car_cats" => $car_cats,
                    "countries" => $countries,
                    "m" => $m,
                    "sign_data" => $signData,
                    "md_dt_sent" => $tr->md_dt_sent,
                    'initiators' => $initiators,
                    'initiator_id' => $initiator_id,
                    'vehicle_type' => $car->vehicle_type,
                ));
            } else {
                $this->flash->error("Объект не найден!");
                return $this->response->redirect("/correction/");
            }
        }
    }

    public function annulCarAction($cid)
    {
        $auth = User::getUserBySession();

        $car = Car::findFirstById($cid);
        $profile = Profile::findFirstById($car->profile_id);

        if ($car && $profile) {
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");

            $_before = json_encode(array($car));
            $car_comment = $this->request->getPost("car_comment");
            $car_file = $this->request->getPost("car_file");
            $initiator_id = $this->request->getPost("initiator");

            // удаляем СВУП архив(svup_XXXX.zip) если есть
            if ($cf = __checkSVUPZip($profile->id)) {
                $svup_path = APP_PATH . '/private';
                $svup_dir = $cf['cert_dir'];
                $svup_file = $cf['file'];

                exec("rm -rf $svup_path/$svup_dir/$svup_file");
            }

            $car = Car::findFirstById($cid);

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if ($this->request->hasFiles()) {
                        foreach ($this->request->getUploadedFiles() as $file) {
                            if ($file->getSize() > 0) {
                                $carfilename = $car->vin . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_EXTENSION);
                                $file->moveTo(APP_PATH . "/private/correction/" . $carfilename);
                            }
                        }
                    }

                    if (file_exists(APP_PATH . '/private/certificates/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates/cancelled/"' . $car->vin . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/cancelled/"' . $car->vin . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates/svup_' . $car->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/svup_"' . $car->profile_id . '".zip "' . APP_PATH . '"/private/certificates/corrected/svup_"' . $car->profile_id . '"_"' . time() . '".zip ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/svup_' . $car->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/svup_"' . $car->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/svup_"' . $car->profile_id . '"_"' . time() . '".zip ');
                    }

                    $profile = Profile::findFirstById($car->profile_id);

                    // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                    if ($car->cost > 0) {
                        if (ClientCorrectionProfile::checkCurrentCorrection($car->id, 'CAR')) {
                            $this->flash->error("ТС с VIN $car->vin уже существует в базе корректировок");
                            return $this->response->redirect("/correction/");
                        }

                        $cc_profile = new ClientCorrectionProfile();
                        $cc_profile->created = time();
                        $cc_profile->action = "ANNULMENT";
                        $cc_profile->type = 'CAR';
                        $cc_profile->user_id = $profile->user_id;
                        $cc_profile->profile_id = $profile->id;
                        $cc_profile->object_id = $car->id;
                        $cc_profile->initiator_id = $initiator_id;
                        $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                        $cc_profile->moderator_id = $auth->id;
                        $cc_profile->action_dt = time();
                        $cc_profile->sign = $sign;
                        $cc_profile->hash = $hash;

                        if ($cc_profile->save()) {
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = 'CAR';
                            $l->user_id = $auth->id;
                            $l->action = 'SENT_TO_ACCOUNTANT';
                            $l->object_id = $car->id;
                            $l->ccp_id = $cc_profile->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = "ДПП Аннулирован!";
                            $l->comment = $car_comment;
                            $l->file = $carfilename;
                            $l->hash = $hash;
                            $l->sign = $sign;
                            $l->save();

                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);

                            $this->flash->success("Заявка отправлена к бухгалтеру.");
                            return $this->response->redirect("/correction_request/index/");
                        }
                    }

                    // удаляем СВУП архив(svup_XXXX.zip) если есть
                    if ($cf = __checkSVUPZip($profile->id)) {
                        $svup_path = APP_PATH . '/private';
                        $svup_dir = $cf['cert_dir'];
                        $svup_file = $cf['file'];

                        exec("rm -rf $svup_path/$svup_dir/$svup_file");
                    }

                    $car_cats = RefCarCat::findFirstById($car->ref_car_cat);

                    // минусовать
                    $tr = Transaction::findFirstByProfileId($profile->id);
                    $tr->amount = $tr->amount - $car->cost;

                    if ($tr->save()) {
                        $car->cost = 0.00;
                        $car->status = "CANCELLED";
                        $car->vin = $car->vin . "__ANNULMENT";
                    }

                    // логгирование
                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'CAR';
                    $l->user_id = $auth->id;
                    $l->profile_id = $profile ? $profile->id : null;
                    $l->action = 'ANNULMENT';
                    $l->object_id = $car ? $car->id : null;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = $car ? json_encode(array($car)) : null;
                    $l->comment = $car_comment;
                    $l->file = $carfilename;
                    $l->sign = $sign;
                    $l->initiator_id = $initiator_id;

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    // если с длиной VIN все в порядке
                    if ($car->save()) {
                        $this->flash->success("ДПП " . $cid . " аннулирован");
                        $l->save();

                        __carRecalc($profile->id);

                        return $this->response->redirect("/correction/index/$car->id");
                    } else {
                        $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                    return $this->response->redirect("/correction/edit_car/" . $cid);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'ALERT');
                return $this->response->redirect("/correction/edit_car/" . $cid);
            }
        }
    }

    public function viewCarAction($cid)
    {
        $auth = User::getUserBySession();
        $car = Car::findFirstById($cid);
        $profile = Profile::findFirstById($car->profile_id);

        if ($car && $profile) {
            $query = $this->modelsManager->createQuery("
            SELECT
              c.id AS c_id,
              c.volume AS c_volume,
              c.vin AS c_vin,
              c.year AS c_year,
              c.cost AS c_cost,
              cc.name AS c_cat,
              c.date_import AS c_date_import,
              country.name AS c_country,
              t.name AS c_type,
              p.id AS c_profile,
              p.blocked AS p_blocked,
        p.user_id AS p_uid,
              tr.status AS tr_status,
              tr.id AS tr_id
            FROM Car c
            JOIN Profile p
              JOIN RefCountry country
              JOIN RefCarCat cc
              JOIN RefCarType t
              JOIN Transaction tr
            WHERE
              c.id = :cid: AND
              c.profile_id = p.id AND
              c.ref_country = country.id AND
              c.ref_car_cat = cc.id AND
              t.id = c.ref_car_type_id AND
              tr.profile_id = p.id
            GROUP BY c.id");

            $car = $query->execute(array(
                "cid" => $cid
            ));

            if ($car[0]->p_uid != $auth->id) {
                return $this->response->redirect("/index/index/");
            }

            $this->view->setVars(array(
                "cid" => $cid,
                "car" => $car
            ));
        } else {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/correction/");
        }
    }

    public function editGoodsAction($gid)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {

            $good = Goods::findFirstById($gid);
            $_before = json_encode(array($good));
            $profile = Profile::findFirstById($good->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);

            $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $good_date = $this->request->getPost("good_date");
            $good_basis = $this->request->getPost("good_basis");
            $basis_date = $this->request->getPost("basis_date");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");
            $calculate_method = $this->request->getPost("calculate_method");
            $good_comment = $this->request->getPost("good_comment");
            $good_file = $this->request->getPost("good_file");
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");
            $initiator_id = $this->request->getPost("initiator");

            if($good_comment == '' || strlen($good_comment) < 4){
                $this->flash->error("Поле «Комментарий» обязательно для заполнения.");
                return $this->response->redirect("/correction/edit_goods/" . $gid);
            }

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if (file_exists(APP_PATH . '/private/certificates/goods_' . $good->id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $good->id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $good->id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $good->id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $good->id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $good->id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates/svup_' . $good->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/svup_"' . $good->profile_id . '".zip "' . APP_PATH . '"/private/certificates/corrected/svup_"' . $good->profile_id . '"_"' . time() . '".zip ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/svup_' . $good->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/svup_"' . $good->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/svup_"' . $good->profile_id . '"_"' . time() . '".zip ');
                    }

                    $up_type = 0;
                    $date_real = 0;
                    $date_report = 0;
                    $t_type_i = 0;
                    $package_cost = $good->package_cost;
                    $sum = 0;
                    $g_price = 0;

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

                    $tn = RefTnCode::findFirstById($tn_code);
                    $ext = '';
                    // NOTE: Расчет платежа (правка товара)
                    if ($tn != false) {
                        if ($this->request->hasFiles()) {
                            foreach ($this->request->getUploadedFiles() as $file) {
                                if ($file->getSize() > 0) {
                                    $filename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                    $file->moveTo(APP_PATH . "/private/correction/" . $filename);
                                     $originalName = $file->getName();
                                    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
                                    copy(APP_PATH . "/private/correction/" . $filename, APP_PATH . "/private/client_correction_docs/" . $filename);
                                }
                            }
                        }

                        if ($calculate_method == 1) {
                            $good_calc_res = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $good_weight, json_encode($tn));
                            $sum = $good_calc_res['sum'];
                            $g_price = $good_calc_res['price'];
                        } else {
                            $good_calc_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn));
                            $sum = $good_calc_res['sum'];
                            $g_price = $good_calc_res['price'];
                        }

                        $tn_add = RefTnCode::findFirstById($tn_code_add);
                        if ($tn_add) {
                            if ($calculate_method == 1) {
                                $package_cost = Goods::calculateAmountByDate(date('Y-m-d H:i:s', $tr->md_dt_sent), $package_weight, json_encode($tn_add));
                                $package_cost = $package_cost['sum'];
                            } else {
                                $package_cost = Goods::calculateAmountByDate($good_date, $package_weight, json_encode($tn_add));
                                $package_cost = $package_cost['sum'];
                            }
                        }

                        $good->weight = $good_weight;
                        $good->basis = $good_basis;
                        $good->ref_tn = $tn->id;
                        $good->date_import = strtotime($good_date);
                        $good->basis_date = strtotime($basis_date);
                        $good->profile_id = $profile->id;
                        $good->price = $g_price;

                        $good_amount = round($sum + $package_cost, 2);

                        // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                        if ($good_amount != $good->amount) {
                            if (ClientCorrectionProfile::checkCurrentCorrection($good->id, 'GOODS')) {
                                $this->flash->error("Заявка с таким ID: $good->profile_id (goods-id: $good->id) обнаружен в базе заявок на корректировку");
                                return $this->response->redirect("/correction/");
                            }

                            $cc_profile = new ClientCorrectionProfile();
                            $cc_profile->created = time();
                            $cc_profile->action_dt = time();
                            $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                            $cc_profile->moderator_id = $auth->id;
                            $cc_profile->user_id = $auth->id;
                            $cc_profile->profile_id = $profile->id;
                            $cc_profile->object_id = $good->id;
                            $cc_profile->type = 'GOODS';
                            $cc_profile->action = 'CORRECTION';
                            $cc_profile->sign = $sign;
                            $cc_profile->hash = $hash;
                            $cc_profile->initiator_id = $initiator_id;

                            if ($cc_profile->save()) {
                                $cc_g = new ClientCorrectionGoods();
                                $cc_g->good_id = $good->id;
                                $cc_g->ccp_id = $cc_profile->id;
                                $cc_g->weight = $good_weight;
                                $cc_g->basis = $good_basis;
                                $cc_g->basis_date = strtotime($basis_date);
                                $cc_g->ref_tn = $tn->id;
                                $cc_g->date_import = strtotime($good_date);
                                $cc_g->profile_id = $profile->id;
                                $cc_g->price = $g_price;
                                $cc_g->ref_tn_add = $good->ref_tn_add;
                                $cc_g->package_weight = $package_weight;
                                $cc_g->package_cost = $package_cost;
                                $cc_g->calculate_method = $calculate_method;
                                $cc_g->amount = $good_amount;
                                $cc_g->goods_cost = $sum;
                                $cc_g->ref_country = $country;
                                $cc_g->save();

                                $l = new ClientCorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'GOODS';
                                $l->user_id = $auth->id;
                                $l->action = 'SENT_TO_ACCOUNTANT';
                                $l->object_id = $good->id;
                                $l->ccp_id = $cc_profile->id;
                                $l->dt = time();
                                $l->meta_before = $_before;
                                $l->meta_after = json_encode(array($cc_g));
                                $l->comment = $good_comment;
                                $l->file = $filename;
                                $l->hash = $hash;
                                $l->sign = $sign;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $ccf = new ClientCorrectionFile();
                                $ccf->profile_id     = $cc_profile->profile_id;
                                $ccf->type           = 'other';
                                $ccf->original_name  = $filename;
                                $ccf->ext            = $ext;
                                $ccf->ccp_id         = $cc_profile->id;
                                $ccf->visible        = 1;
                                $ccf->user_id        = $auth->id;
                                $ccf->save();

                                $this->flash->success("Заявка отправлена к бухгалтеру.");
                                return $this->response->redirect("/correction_request/index/");
                            }
                        }

                        $good->calculate_method = $calculate_method;
                        $good->amount = $good_amount;
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
                                $good_amount = round($sum + $package_cost, 2);
                                $good->amount = $good_amount;
                                $good->price = $v;
                            }
                        }

                        if ($good) {
                            // $pr->blocked = 1;
                            if ($good->save()) {
                                // логгирование
                                $l = new CorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'GOODS';
                                $l->user_id = $auth->id;
                                $l->profile_id = $profile ? $profile->id : null;
                                $l->action = 'CORRECTION';
                                $l->object_id = $good ? $good->id : null;
                                $l->dt = time();
                                $l->meta_before = $_before;
                                $l->meta_after = $good ? json_encode(array($good)) : null;
                                $l->comment = $good_comment;
                                $l->file = $filename;
                                $l->sign = $sign;
                                $l->initiator_id = $initiator_id;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                __goodRecalc($good->profile_id);

                                $this->flash->success("Позиция отредактирована.");
                            }
                        } else {
                            $this->flash->warning("Невозможно отредактировать эту позицию.");
                        }

                        return $this->response->redirect("/correction/index/$profile->id");
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                    return $this->response->redirect("/correction/edit_goods/" . $gid);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'ALERT');
                return $this->response->redirect("/correction/edit_goods/" . $gid);
            }
        } else {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);
            $user = User::getUserBySession();

            if($user->isAdmin()){
                $initiators = RefInitiator::find();
            }else{
                $initiators = RefInitiator::find([
                    'conditions' => 'id IN (1,2,3)',
                ]);
            }

            if ($good && $profile) {
                $signData = __signData($good->profile_id, $this);

                $filter = "code IS NOT NULL";

                if ($profile->type == 'R20') {
                    if ($good->goods_type == 0) {
                        $filter = "(id > 0 AND id < 102) OR id > 850 AND price > 0";
                    }
                    if ($good->goods_type == 1) {
                        $filter = "id > 107 AND id < 144 AND price > 0";
                    }
                    if ($good->goods_type == 2) {
                        $filter = "id > 143 AND id < 851 AND price > 0";
                    }
                }

                $tn_codes = RefTnCode::find([
                    $filter,
                    'order' => 'code'
                ]);

                $filter_add = " AND type = 'PACKAGE'";

                $tn_codes_add = RefTnCode::find([
                    $filter . $filter_add,
                    'order' => 'code'
                ]);

                $country = RefCountry::find(array('id <> 1'));

                $this->view->setVars(array(
                    "good" => $good,
                    "tn_codes" => $tn_codes,
                    "tn_codes_add" => $tn_codes_add,
                    "country" => $country,
                    "sign_data" => $signData,
                    "md_dt_sent" => $tr->md_dt_sent,
                    "initiators" => $initiators,
                    "initiator_id" => $profile->initiator_id,
                ));
            } else {
                $this->flash->error("Объект не найден!");
                return $this->response->redirect("/correction/index/");
            }
        }
    }

    public function newAction($pid)
    {
        $profile = Profile::findFirstById($pid);

        if ($profile) {
            $tr = Transaction::findFirstByProfileId($profile->id);
            $signData = __signData($pid, $this);
            $tType = $_GET['t_type'] ?? null;

            switch ($tType) {
                case 'comp':
                    $filter = "((id > 0 AND id < 102) OR (id > 850)) AND price > 0";
                    break;

                case 'up':
                    $filter = "id > 107 AND id < 144 AND price > 0";
                    break;

                case 'goods':
                    $filter = "id > 143 AND id < 851 AND price > 0";
                    break;

                default:
                    $filter = "price7 > 0";
                    break;
            }

            $tn_codes = RefTnCode::find([
                $filter,
                'order' => 'name'
            ]);

            $country = RefCountry::find(array('id <> 1'));

            $this->view->setVars(array(
                "tn_codes" => $tn_codes,
                "pid" => $pid,
                "country" => $country,
                "sign_data" => $signData,
                "md_dt_sent" => $tr->md_dt_sent,
            ));
        } else {
            $this->flash->error("Заявка не найдена!");
            return $this->response->redirect("/correction/index/");
        }
    }

    public function goodsViewAction($pid)
    {
        $auth = User::getUserBySession();
        $goods = Goods::findByProfileId($pid);
        $profile = Profile::findFirstById($pid);

        if ($goods && $profile) {
            $tr = Transaction::findFirstByProfileId($profile->id);
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

                        if ($log->action == "CREATED" || $log->meta_before == "_") {
                            $html .= '<td>_</td>';
                        } else {
                            foreach (json_decode($log->meta_before) as $l_before) {
                                $g_country_before = RefCountry::findFirstById($l_before->ref_country);
                                $g_tn_before = RefTnCode::findFirstById($l_before->ref_tn);

                                $html .= '<td>Страна: ' . $g_country_before->name . '<br>
                                                  Код ТНВЭД: ' . $g_tn_before->code . ' <br>
                                                  Масса товара или упаковки (кг): ' . number_format($l_before->weight, 3) . ' кг<br>
                                                  Сумма, тенге: ' . $l_before->amount . ' тг<br>
                                                  ГТД: ' . $l_before->basis . '<br>
                                                  Дата импорта или реализации: ' . date("d-m-Y", $l_before->date_import) . '<br></td>';
                            }
                        }

                        foreach (json_decode($log->meta_after) as $l_after) {
                            $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                            $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                            $html .= '<td>Страна: ' . $g_country_after->name . '<br>
                                                Код ТНВЭД: ' . $g_tn_after->code . ' <br>
                                                Масса товара или упаковки (кг): ' . number_format($l_after->weight, 3) . ' кг<br>
                                                Сумма, тенге: ' . $l_after->amount . ' тг<br>
                                                ГТД: ' . $l_after->basis . '<br>
                                                Дата импорта или реализации: ' . date("d-m-Y", $l_after->date_import) . '<br></td>';
                        }

                        $html .= '<td>' . $log->comment . '</td>';
                        $html .= '<td><a href="/correction/getdoc/' . $log->id . '">' . $log->file . '</a></td>';
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
                "logs" => $html,
                "md_dt_sent" => $tr->md_dt_sent,
            ));
        } else {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/correction/");
        }
    }

    public function addGoodsAction()
    {
        $auth = User::getUserBySession();

        $__settings = $this->session->get("__settings");

        if ($this->request->isPost()) {

            $pid = $this->request->getPost("profile");
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");

            $gc = Goods::findByProfileId($pid);
            $count = count($gc);

            $profile = Profile::findFirstById($pid);
            $tr = Transaction::findFirstByProfileId($profile->id);


            $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $good_date = $this->request->getPost("good_date");
            $basis_date = $this->request->getPost("basis_date");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");
            $good_basis = $this->request->getPost("good_basis");
            $calculate_method = $this->request->getPost("calculate_method");
            $comment = $this->request->getPost("good_comment");
            $good_file = $this->request->getPost("good_file");

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if (file_exists(APP_PATH . '/private/certificates/goods_' . $profile->id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $profile->id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $profile->id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $profile->id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $profile->id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $profile->id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates/svup_' . $profile->id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/svup_"' . $profile->id . '".zip "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $profile->id . '"_"' . time() . '".zip ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/svup_' . $profile->id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/svup_"' . $profile->id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/svup_"' . $profile->id . '"_"' . time() . '".zip ');
                    }

                    $up_type = 0;
                    $date_real = 0;
                    $date_report = 0;
                    $t_type_i = 0;
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

                    // если дата импорта меньше, чем дата введения постановления
                    // в действие - то перекидываем пользователя на соответствующее
                    // сообщение
                    if (strtotime($good_date) < strtotime(STARTROP)) {
                        $this->flash->notice("За товары, ввезенные / произведенные на территорию Республики Казахстан до 27 января 2016 года включительно, не оплачивается утилизационный платеж.");
                        return $this->response->redirect("/correction/new/" . $profile->id);
                    }

                    $tn = RefTnCode::findFirstById($tn_code);

                    // NOTE: Расчет платежа (добавление товара)
                    if ($tn != false) {
                        if ($this->request->hasFiles()) {
                            foreach ($this->request->getUploadedFiles() as $file) {
                                if ($file->getSize() > 0) {
                                    $filename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                    $file->moveTo(APP_PATH . "/private/correction/" . $filename);
                                }
                            }
                        }

                        if ($calculate_method == 1) {
                            $good_calc_res = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $good_weight, json_encode($tn));
                            $sum = $good_calc_res['sum'];
                        } else {
                            $good_calc_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn));
                            $sum = $good_calc_res['sum'];
                        }

                        $c = new Goods();
                        $c->weight = $good_weight;
                        $c->basis = $good_basis;
                        $c->date_import = strtotime($good_date);
                        $c->basis_date = strtotime($basis_date);
                        $c->profile_id = $profile->id;
                        $c->ref_tn = $tn->id;
                        $c->price = $good_calc_res['price'];
                        $c->created = time();
                        $package_cost = 0;
                        $tn_add = RefTnCode::findFirstById($tn_code_add);
                        if ($tn_add) {
                            $c->ref_tn_add = $tn_add->id;
                            $c->package_weight = $package_weight;

                            if ($calculate_method == 1) {
                                $package_calc_res = Goods::calculateAmountByDate(date('Y-m-d', $tr->md_dt_sent), $package_weight, json_encode($tn_add));
                                $package_cost = $package_calc_res['sum'];
                            } else {
                                $package_calc_res = Goods::calculateAmountByDate($good_date, $package_weight, json_encode($tn_add));
                                $package_cost = $package_calc_res['sum'];
                            }

                            $c->package_cost = $package_cost;
                        }

                        $good_amount = round($sum + $package_cost, 2);

                        // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                        if ($good_amount > 0) {
                            $cc_profile = new ClientCorrectionProfile();
                            $cc_profile->created = time();
                            $cc_profile->action_dt = time();
                            $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                            $cc_profile->moderator_id = $auth->id;
                            $cc_profile->user_id = $auth->id;
                            $cc_profile->profile_id = $profile->id;
                            $cc_profile->object_id = null;
                            $cc_profile->type = 'GOODS';
                            $cc_profile->action = 'CREATED';
                            $cc_profile->sign = $sign;
                            $cc_profile->hash = $hash;

                            if ($cc_profile->save()) {
                                $cc_g = new ClientCorrectionGoods();
                                $cc_g->good_id = 0;
                                $cc_g->ccp_id = $cc_profile->id;
                                $cc_g->weight = $good_weight;
                                $cc_g->basis = $good_basis;
                                $cc_g->basis_date = strtotime($basis_date);
                                $cc_g->ref_tn = $tn->id;
                                $cc_g->date_import = strtotime($good_date);
                                $cc_g->profile_id = $profile->id;
                                $cc_g->price = $tn->price7;
                                $cc_g->ref_tn_add = ($tn_add) ? $tn_add->id : 0;
                                $cc_g->package_weight = $package_weight;
                                $cc_g->package_cost = $package_cost;
                                $cc_g->calculate_method = $calculate_method;
                                $cc_g->amount = $good_amount;
                                $cc_g->goods_cost = $sum;
                                $cc_g->ref_country = $country;
                                $cc_g->goods_type = $t_type_i;
                                $cc_g->up_type = $up_type;
                                $cc_g->date_report = strtotime($date_report);
                                $cc_g->save();

                                $l = new ClientCorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'GOODS';
                                $l->user_id = $auth->id;
                                $l->action = 'SENT_TO_ACCOUNTANT';
                                $l->object_id = 0;
                                $l->ccp_id = $cc_profile->id;
                                $l->dt = time();
                                $l->meta_before = '-';
                                $l->meta_after = json_encode(array($cc_g));
                                $l->comment = $comment;
                                $l->file = $filename;
                                $l->hash = $hash;
                                $l->sign = $sign;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $this->flash->success("Заявка отправлена к бухгалтеру.");
                                return $this->response->redirect("/correction_request/index/");
                            }
                        }

                        $c->calculate_method = $calculate_method;
                        $c->amount = $good_amount;
                        $c->goods_cost = $sum;
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
                                $good_amount = round($sum + $package_cost, 2);
                                $c->amount = $good_amount;
                                $c->price = $v;
                            }
                        }

                        $tr = Transaction::findFirstByProfileId($profile->id);
                        $tr->amount = $tr->amount + $good_amount;
                        $tr->save();

                        if ($c->save()) {
                            // логгирование
                            $l = new CorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = 'GOODS';
                            $l->user_id = $auth->id;
                            $l->profile_id = $profile ? $profile->id : null;
                            $l->action = 'CREATED';
                            $l->object_id = $c ? $c->id : null;
                            $l->dt = time();
                            $l->meta_before = "_";
                            $l->meta_after = $c ? json_encode(array($c)) : json_encode(array());
                            $l->comment = $comment;
                            $l->file = $filename;
                            $l->sign = $sign;
                            $l->save();
                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);

                            __goodRecalc($profile->id);

                            $this->flash->success("Новая позиция добавлена");
                            return $this->response->redirect("/correction/index/$profile->id");
                        } else {
                            $this->flash->warning("Невозможно сохранить новую позицию");
                            return $this->response->redirect("/correction/new/" . $pid);
                        }
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/correction/new/" . $pid);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись!");
                return $this->response->redirect("/correction/new/" . $pid);
            }
        }
    }

    public function annulGoodsAction($pid)
    {
        $auth = User::getUserBySession();
        $pid = $this->request->getPost("profile");
        $good_comment = $this->request->getPost("good_comment");

        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $goodfilename = '';

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {
                if (file_exists(APP_PATH . '/private/certificates/goods_' . $pid . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $pid . '".pdf "' . APP_PATH . '"/private/certificates/deleted/goods_"' . $pid . '"_"' . time() . '".pdf ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $pid . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $pid . '".pdf "' . APP_PATH . '"/private/certificates_zd/deleted/goods_"' . $pid . '"_"' . time() . '".pdf ');
                }


                if (file_exists(APP_PATH . '/private/certificates/svup_' . $pid . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/svup_"' . $pid . '".zip "' . APP_PATH . '"/private/certificates/deleted/svup_"' . $pid . '"_"' . time() . '".zip ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/svup_' . $pid . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/svup_"' . $pid . '".zip "' . APP_PATH . '"/private/certificates_zd/deleted/svup_"' . $pid . '"_"' . time() . '".zip ');
                }

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $goodfilename = $pid . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $file->moveTo(APP_PATH . "/private/correction/" . $goodfilename);
                        }
                    }
                }

                $tr_amount = Transaction::sum([
                    'column' => 'amount',
                    'conditions' => 'profile_id = ' . $pid
                ]);

                $goods = Goods::find(array(
                    'profile_id = :pid:',
                    'bind' => array(
                        'pid' => $pid,
                    ),
                    "order" => "id ASC"
                ));

                if ($tr_amount > 0) {
                    if (ClientCorrectionProfile::checkCurrentCorrectionByProfileId($pid, 'GOODS')) {
                        $this->flash->error("Заявка с таким ID: $pid обнаружен в базе заявок на корректировку");
                        return $this->response->redirect("/correction/");
                    }

                    $cc_profile = new ClientCorrectionProfile();
                    $cc_profile->created = time();
                    $cc_profile->action_dt = time();
                    $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                    $cc_profile->moderator_id = $auth->id;
                    $cc_profile->user_id = $auth->id;
                    $cc_profile->profile_id = $pid;
                    $cc_profile->object_id = $pid;
                    $cc_profile->type = 'GOODS';
                    $cc_profile->action = "ANNULMENT";
                    $cc_profile->sign = $sign;
                    $cc_profile->hash = $hash;

                    if ($cc_profile->save()) {
                        foreach ($goods as $key => $g) {
                            if ($g->status == "DELETED") {
                                continue;
                            }

                            $id_list[] = $g->id;
                        }

                        $l = new ClientCorrectionLogs();
                        $l->iin = $auth->idnum;
                        $l->type = 'GOODS';
                        $l->user_id = $auth->id;
                        $l->action = 'SENT_TO_ACCOUNTANT';
                        $l->object_id = $pid;
                        $l->ccp_id = $cc_profile->id;
                        $l->dt = time();
                        $l->meta_before = json_encode($id_list);
                        $l->meta_after = "Запрос на аннулирование!";
                        $l->comment = $good_comment;
                        $l->file = $goodfilename;
                        $l->hash = $hash;
                        $l->sign = $sign;
                        $l->save();

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        $this->flash->success("Заявка отправлена к бухгалтеру.");
                        return $this->response->redirect("/correction_request/index/");
                    }
                }
                foreach ($goods as $key => $g) {
                    if ($g->status == "DELETED") {
                        continue;
                    }

                    $good = Goods::findFirstById($g->id);
                    $_before = json_encode(array($good));

                    $profile = Profile::findFirstById($good->profile_id);

                    $tr = Transaction::findFirstByProfileId($profile->id);
                    $tr->amount = $tr->amount - $good->amount - $good->package_cost;

                    if ($tr->save()) {
                        $good->amount = 0.00;
                        $good->package_cost = 0.00;
                        $good->status = "CANCELLED";
                        $good->save();
                    }

                    // логгирование
                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'GOODS';
                    $l->user_id = $auth->id;
                    $l->profile_id = $good ? $good->profile_id : null;
                    $l->action = 'ANNULMENT';
                    $l->object_id = $good ? $good->id : null;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = $good ? json_encode(array($good)) : null;
                    $l->comment = $good_comment;
                    $l->file = $goodfilename;
                    $l->sign = $sign;

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    if ($good->save()) {
                        $l->save();
                    }
                }

                __goodRecalc($profile->id);

                $this->flash->success("ДПП по заявке " . $pid . " аннулирован");
                return $this->response->redirect("/correction/index/$pid");
            } else {
                $this->flash->error("Подпись не прошла проверку!");
                $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                return $this->response->redirect("/correction/goods_view/$pid");
            }
        } else {
            $this->flash->error("Вы используете несоответствующую профилю подпись.");
            $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'NOTICE');
            return $this->response->redirect("/correction/goods_view/$pid");
        }
    }

    public function deleteGoodsAction($gid)
    {
        $auth = User::getUserBySession();

        $good = Goods::findFirstById($gid);
        $_before = json_encode(array($good));
        $good_comment = $this->request->getPost("good_comment");
        $good_file = $this->request->getPost("good_file");
        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $initiator_id = $this->request->getPost("initiator");
        $__settings = $this->session->get("__settings");

        $good = Goods::findFirstById($gid);

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {
                if (file_exists(APP_PATH . '/private/certificates/goods_' . $good->profile_id . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $good->profile_id . '".pdf "' . APP_PATH . '"/private/certificates/deleted/goods_"' . $good->profile_id . '"_"' . time() . '".pdf ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $good->profile_id . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $good->profile_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/deleted/goods_"' . $good->profile_id . '"_"' . time() . '".pdf ');
                }

                if (file_exists(APP_PATH . '/private/certificates/svup_' . $good->profile_id . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/svup_"' . $good->profile_id . '".zip "' . APP_PATH . '"/private/certificates/deleted/svup_"' . $good->profile_id . '"_"' . time() . '".zip ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/certificates_' . $good->profile_id . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/svup_"' . $good->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/deleted/svup_"' . $good->profile_id . '"_"' . time() . '".zip ');
                }

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $goodfilename = $good->id . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $file->moveTo(APP_PATH . "/private/correction/" . $goodfilename);
                        }
                    }
                }

                $profile = Profile::findFirstById($good->profile_id);

                // минусовать
                $tr = Transaction::findFirstByProfileId($profile->id);
                $tr->amount = $tr->amount - $good->amount;

                // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                if ($good->amount > 0) {
                    if (ClientCorrectionProfile::checkCurrentCorrection($good->id, 'GOODS')) {
                        $this->flash->error("Заявка с таким ID: $good->profile_id (goods-id: $good->id) обнаружен в базе заявок на корректировку");
                        return $this->response->redirect("/correction/");
                    }

                    $cc_profile = new ClientCorrectionProfile();
                    $cc_profile->created = time();
                    $cc_profile->action_dt = time();
                    $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                    $cc_profile->moderator_id = $auth->id;
                    $cc_profile->user_id = $auth->id;
                    $cc_profile->profile_id = $profile->id;
                    $cc_profile->object_id = $good->id;
                    $cc_profile->type = 'GOODS';
                    $cc_profile->action = 'DELETED';
                    $cc_profile->sign = $sign;
                    $cc_profile->hash = $hash;
                    $cc_profile->initiator_id = $initiator_id;
                    $cc_profile->save();

                    $cc_g = new ClientCorrectionGoods();
                    $cc_g->good_id = $good->id;
                    $cc_g->ccp_id = $cc_profile->id;
                    $cc_g->weight = $good->weight;
                    $cc_g->basis = $good->basis;
                    $cc_g->basis_date = $good->basis_date;
                    $cc_g->ref_tn = $good->ref_tn;
                    $cc_g->date_import = $good->date_import;
                    $cc_g->profile_id = $profile->id;
                    $cc_g->price = $good->price;
                    $cc_g->ref_tn_add = $good->ref_tn_add;
                    $cc_g->package_weight = $good->package_weight;
                    $cc_g->package_cost = 0;
                    $cc_g->calculate_method = $good->calculate_method;
                    $cc_g->amount = 0;
                    $cc_g->goods_cost = 0;
                    $cc_g->ref_country = $good->ref_country;
                    $cc_g->status = 'DELETED';
                    $cc_g->goods_type = null;
                    $cc_g->up_type = null;
                    $cc_g->date_report = null;
                    $cc_g->save();

                    $l = new ClientCorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'GOODS';
                    $l->user_id = $auth->id;
                    $l->action = 'SENT_TO_ACCOUNTANT';
                    $l->object_id = $good->id;
                    $l->ccp_id = $cc_profile->id;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = json_encode(array($cc_g));
                    $l->comment = $good_comment;
                    $l->file = $goodfilename;
                    $l->hash = $hash;
                    $l->sign = $sign;
                    $l->save();

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    $this->flash->success("Заявка отправлена к бухгалтеру.");
                    return $this->response->redirect("/correction_request/index/");
                }

                if ($tr->save()) {
                    $good->amount = 0;
                    $good->package_cost = 0;
                    $good->status = "DELETED";
                }

                // логгирование
                $l = new CorrectionLogs();
                $l->iin = $auth->idnum;
                $l->type = 'GOODS';
                $l->user_id = $auth->id;
                $l->profile_id = $good ? $good->profile_id : null;
                $l->action = 'DELETED';
                $l->object_id = $good ? $good->id : null;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = $good ? json_encode(array($good)) : null;
                $l->comment = $good_comment;
                $l->file = $goodfilename;
                $l->sign = $sign;
                $l->initiator_id = $initiator_id;

                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);

                if ($good->save()) {
                    $this->flash->success("Позиция " . $gid . " успешно удалена");
                    $l->save();

                    __goodRecalc($good->profile_id);

                    return $this->response->redirect("/correction/index/$good->id");
                } else {
                    $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                    return $this->response->redirect("/correction/edit_goods/" . $gid);
                }
            } else {
                $this->flash->error("Подпись не прошла проверку!");
                $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                return $this->response->redirect("/correction/edit_goods/" . $gid);
            }
        } else {
            $this->flash->error("Вы используете несоответствующую профилю подпись.");
            $this->logAction("Вы используете несоответствующую профилю подпись.", 'security');
            return $this->response->redirect("/correction/edit_goods/" . $gid);
        }
    }

    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/correction/";
        $auth = User::getUserBySession();

        $cl = CorrectionLogs::findFirstById($id);
        //$profile = Profile::findFirstById($cl->profile_id);

        if ($cl->user_id == $auth->id || $auth->isEmployee()) {
            if (file_exists($path . $cl->file)) {
                __downloadFile($path . $cl->file, $cl->file);
            }
        }
    }

    public function showKppAction(int $id)
    {
        // можно убрать, если не используется
        $auth = User::getUserBySession();

        $kpp = Kpp::findFirstById($id);
        if (!$kpp) {
            $this->flash->error('Объект не найден');
            return $this->response->redirect('/correction/');
        }

        $tr = Transaction::findFirstByProfileId($kpp->profile_id);
        if (!$tr) {
            $this->flash->error('Транзакция не найдена');
            return $this->response->redirect('/correction/');
        }

        // справочники
        $tn_codes = RefTnCode::find([
            'conditions' => 'code = :code: AND is_active = 1',
            'bind'       => ['code' => 8544],
            'order'      => 'name',
        ]);

        $country = RefCountry::find([
            'conditions' => 'id <> 1',
            'order'      => 'name',
        ]);

        $request = CurrencyRequest::findFirst(['order' => 'id DESC']);
        $currencies = $request
            ? CurrencyEach::find([
                'conditions' => 'request_id = :rid:',
                'bind'       => ['rid' => $request->id],
            ])
            : [];

        $package_tn_codes = RefTnCode::find([
            'conditions' => 'code IS NOT NULL AND is_active = 1',
            'order'      => 'code',
        ]);

        $signData   = __signData((int)$kpp->profile_id, $this);
        $numberPage = $this->request->getQuery('page', 'int', 1);

        // логи через QueryBuilder + пагинация на уровне БД
        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'c.id          AS id',
                'c.type        AS type',
                'c.object_id   AS object_id',
                'c.user_id     AS user_id',
                'c.iin         AS iin',
                'c.action      AS action',
                'c.dt          AS dt',
                'c.comment     AS comment',
                'c.file        AS file',
                'c.meta_before AS meta_before',
                'c.meta_after  AS meta_after',
                "CONCAT(pd.last_name, ' ', pd.first_name, ' ', pd.parent_name) AS fio",
            ])
            ->from(['c' => CorrectionLogs::class])
            ->join(PersonDetail::class, 'pd.user_id = c.user_id', 'pd')
            ->where('c.type = :type: AND c.object_id = :oid:', [
                'type' => 'KPP',
                'oid'  => (int)$kpp->id,
            ])
            ->orderBy('c.id DESC');

        $paginator = new PaginatorBuilder([
            'builder' => $builder,
            'limit'   => 5,
            'page'    => $numberPage,
        ]);

        $page = $paginator->paginate();

        $this->view->page = $page;
        $this->view->setVars([
            'kpp'               => $kpp,
            'tn_codes'          => $tn_codes,
            'country'           => $country,
            'sign_data'         => $signData,
            'md_dt_sent'        => $tr->md_dt_sent,
            'currencies'        => $currencies,
            'package_tn_codes'  => $package_tn_codes,
        ]);
    }

    public function editKppAction($id)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $kpp = Kpp::findFirstById($id);
            $_before = json_encode(array($kpp));
            $tr = Transaction::findFirstByProfileId($kpp->profile_id);

            $kpp_comment = $this->request->getPost("kpp_comment");
            $kpp_file = $this->request->getPost("kpp_file");
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");

            $kpp_weight = (float)str_replace(',', '.', $this->request->getPost("kpp_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $kpp_date = $this->request->getPost("kpp_date");
            $basis_date = $this->request->getPost("basis_date");
            $tn_code = $this->request->getPost("tn_code");
            $package_tn_id = $this->request->getPost("package_tn_id");
            $country = $this->request->getPost("kpp_country");
            $kpp_basis = $this->request->getPost("kpp_basis");
            $tn = RefTnCode::findFirstById($tn_code);
            $currency_type = $this->request->getPost("currency_type");
            $invoice_sum = (float)str_replace(',', '.', $this->request->getPost("sum"));
            $invoice_sum = round($invoice_sum, 2);
            $package_cost = $kpp->package_cost;
            $kpp_amount = 0;

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if (file_exists(APP_PATH . '/private/certificates/kpp_' . $kpp->profile_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/kpp_"' . $kpp->profile_id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/kpp_"' . $kpp->profile_id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/kpp_' . $kpp->profile_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/kpp_"' . $kpp->profile_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/kpp_"' . $kpp->profile_id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates/kpp_svup_' . $kpp->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/kpp_svup_"' . $kpp->profile_id . '".zip "' . APP_PATH . '"/private/certificates/corrected/kpp_svup_"' . $kpp->profile_id . '"_"' . time() . '".zip ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/kpp_svup_' . $kpp->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/kpp_svup_"' . $kpp->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/kpp_svup_"' . $kpp->profile_id . '"_"' . time() . '".zip ');
                    }

                    if ($tn != false) {
                        if ($currency_type != 'KZT') {
                            $kpp->invoice_sum_currency = (float)$this->request->getPost("sum");
                            $currAr = currencyToTenge($currency_type, $invoice_sum, $kpp_date);
                            $invoice_sum = $currAr['sum'] > 0 ? $currAr['sum'] : 0;
                            $kpp->currency = $currAr['from'] . " " . $currency_type . " / " . $currAr['to'] . " KZT";
                        }

                        $kpp->ref_tn = $tn->id;
                        $kpp->ref_country = $country;
                        $kpp->date_import = strtotime($kpp_date);
                        $kpp->weight = $kpp_weight;
                        $kpp->basis = $kpp_basis;

                        if ($package_tn_id) {
                            $package_tn = RefTnCode::findFirstById($package_tn_id);
                            if ($package_tn) {
                                $package = Goods::calculateAmountByDate($kpp_date, $package_weight, json_encode($package_tn));
                                $kpp->package_tn_code = $package_tn->code;
                                $kpp->package_weight = $package_weight;
                                $package_cost = $package['sum'];
                                $kpp->package_cost = $package_cost;
                            }
                        }

                        // расчет от суммы
                        $sum = (float)$invoice_sum * 0.05;
                        $sum = round($sum, 2);
                        $old_amount = $kpp->amount;
                        $kpp_amount = round($package_cost + $sum, 2);
                        $kpp->amount = $kpp_amount;
                        $kpp->invoice_sum = $invoice_sum;
                        $kpp->currency_type = $currency_type;

                        $tr = Transaction::findFirstByProfileId($kpp->profile_id);
                        $tr->amount = ((float)$tr->amount - $old_amount) + $kpp_amount;

                        if ($kpp->save()) {
                            $tr->save();
                            $this->flash->success("Объект был изменен.");
                        } else {
                            $this->flash->warning("Невозможно сохранить изменение.");
                        }
                        return $this->response->redirect("order/view/$kpp->profile_id");
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/correction/show_kpp/" . $id);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                return $this->response->redirect("/correction/show_kpp/" . $id);
            }
        }
    }

    public function deleteKppAction($id)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $kpp = Kpp::findFirstById($id);
            $_before = json_encode(array($kpp));
            $tr = Transaction::findFirstByProfileId($kpp->profile_id);

            $kpp_comment = $this->request->getPost("kpp_comment");
            $kpp_file = $this->request->getPost("kpp_file");
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    if (file_exists(APP_PATH . '/private/certificates/kpp_' . $kpp->profile_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/kpp_"' . $kpp->profile_id . '".pdf "' . APP_PATH . '"/private/certificates/deleted/kpp_"' . $kpp->profile_id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/kpp_' . $kpp->profile_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/kpp_"' . $kpp->profile_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/deleted/kpp_"' . $kpp->profile_id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates/certificates_' . $kpp->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/certificates_"' . $kpp->profile_id . '".zip "' . APP_PATH . '"/private/certificates/deleted/kpp_"' . $kpp->profile_id . '"_"' . time() . '".zip ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/certificates_' . $kpp->profile_id . '.zip')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/certificates_"' . $kpp->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/deleted/kpp_"' . $kpp->profile_id . '"_"' . time() . '".zip ');
                    }

                    $profile = Profile::findFirstById($kpp->profile_id);

                    $tr = Transaction::findFirstByProfileId($profile->id);
                    $tr->amount = $tr->amount - $kpp->amount;

                    if ($tr->save()) {
                        $kpp->amount = 0.00;
                        $kpp->status = "DELETED";
                    }

                    if ($this->request->hasFiles()) {
                        foreach ($this->request->getUploadedFiles() as $file) {
                            if ($file->getSize() > 0) {
                                $kppfilename = $kpp->id . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                $file->moveTo(APP_PATH . "/private/correction/" . $kppfilename);
                            }
                        }
                    }
                    // логгирование
                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'KPP';
                    $l->user_id = $auth->id;
                    $l->profile_id = $kpp ? $kpp->profile_id : null;
                    $l->action = 'DELETED';
                    $l->object_id = $kpp ? $kpp->id : null;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = $kpp ? json_encode(array($kpp)) : null;
                    $l->comment = $kpp_comment;
                    $l->file = $kppfilename;
                    $l->sign = $sign;

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    if ($kpp->save()) {
                        $this->flash->success("Позиция " . $kpp->id . " успешно удалена");
                        $l->save();
                        return $this->response->redirect("/correction/");
                    } else {
                        $this->flash->warning("Невозможно отредактировать это транспортное средство.");
                        return $this->response->redirect("/correction/edit_kpp/" . $id);
                    }
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                    return $this->response->redirect("/correction/edit_kpp/" . $id);
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                return $this->response->redirect("/correction/edit_kpp/" . $id);
            }
        }
    }

    public function kppsViewAction($pid)
    {
        $kpps = Kpp::findByProfileId($pid);
        $profile = Profile::findFirstById($pid);

        if ($kpps && $profile) {
            $signData = __signData($pid, $this);

            $count = count($kpps);

            $this->view->setVars(array(
                "kpps" => $kpps,
                "count" => $count,
                "sign_data" => $signData,
                "pid" => $pid
            ));
        } else {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/correction/");
        }
    }

    public function annulKppsAction($pid)
    {
        $auth = User::getUserBySession();
        $pid = $this->request->getPost("profile");
        $kpp_comment = $this->request->getPost("kpp_comment");
        $kpp_file = $this->request->getPost("kpp_file");

        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $__settings = $this->session->get("__settings");

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {
                if (file_exists(APP_PATH . '/private/certificates/kpp_' . $pid . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/kpp_"' . $pid . '".pdf "' . APP_PATH . '"/private/certificates/deleted/kpp_"' . $pid . '"_"' . time() . '".pdf ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/kpp_' . $pid . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/kpp_"' . $pid . '".pdf "' . APP_PATH . '"/private/certificates_zd/deleted/kpp_"' . $pid . '"_"' . time() . '".pdf ');
                }

                if (file_exists(APP_PATH . '/private/certificates/certificates_' . $pid . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/certificates_"' . $pid . '".zip "' . APP_PATH . '"/private/certificates/cancelled/certificates_"' . $pid . '"_"' . time() . '".zip ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/certificates_' . $pid . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/certificates_"' . $pid . '".zip "' . APP_PATH . '"/private/certificates_zd/cancelled/certificates_"' . $pid . '"_"' . time() . '".zip ');
                }

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $kppfilename = $pid . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $file->moveTo(APP_PATH . "/private/correction/" . $kppfilename);
                        }
                    }
                }

                $kpps = Kpp::find(array(
                    'profile_id = :pid:',
                    'bind' => array(
                        'pid' => $pid,
                    ),
                    "order" => "id ASC"
                ));

                foreach ($kpps as $key => $g) {
                    if ($g->status == "DELETED") {
                        continue;
                    }

                    $kpp = Kpp::findFirstById($g->id);
                    $_before = json_encode(array($kpp));

                    $profile = Profile::findFirstById($kpp->profile_id);

                    $tr = Transaction::findFirstByProfileId($profile->id);
                    $tr->amount = $tr->amount - $kpp->amount - $kpp->package_cost;

                    if ($tr->save()) {
                        $kpp->amount = 0.00;
                        $kpp->package_cost = 0.00;
                        $kpp->status = "CANCELLED";
                        $kpp->save();
                    }

                    // логгирование
                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'KPP';
                    $l->user_id = $auth->id;
                    $l->profile_id = $kpp->profile_id;
                    $l->action = 'ANNULMENT';
                    $l->object_id = $kpp->id;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = json_encode(array($kpp));
                    $l->comment = $kpp_comment;
                    $l->file = $kppfilename;
                    $l->sign = $sign;

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    if ($kpp->save()) {
                        $l->save();
                    }
                }

                $this->flash->success("ДПП по заявке " . $pid . " аннулирован");
                return $this->response->redirect("/correction/index/$pid");
            } else {
                $this->flash->error("Подпись не прошла проверку!");
                return $this->response->redirect("/correction/kpps_view/$pid");
            }
        } else {
            $this->flash->error("Вы используете несоответствующую профилю подпись.");
            return $this->response->redirect("/correction/kpps_view/$pid");
        }
    }

    public function restoreAnnulledCarAction()
    {
        $auth = User::getUserBySession();
        $car_id = $this->request->getPost("car_id");
        $message = null;
        $success = false;

        $car = Car::findFirstById($car_id);
        $_before = json_encode(array($car));
        $vin_before = "car:$car->vin, car_id:$car->id, car_profile_id:$car->profile_id";
        $hash = $this->request->getPost("hash");
        $sign = $this->request->getPost("sign");
        $car_comment = $this->request->getPost("car_comment");
        $expected_cost = $this->request->getPost("expected_cost");
        $expected_amount = $this->request->getPost("expected_amount");
        $expected_vin = $this->request->getPost("expected_vin");
        $__settings = $this->session->get("__settings");

        $cmsService = new CmsService();
        $result = $cmsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];
        if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
            if ($result['success'] === true) {
                if (file_exists(APP_PATH . '/private/certificates/' . $car->vin . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/"' . $car->vin . '"*.pdf "' . APP_PATH . '"/private/certificates/corrected/"');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $car->vin . '"*.pdf "' . APP_PATH . '"/private/certificates_zd/corrected/"');
                }

                $profile = Profile::findFirstById($car->profile_id);
                $tr = Transaction::findFirstByProfileId($profile->id);

                // удаляем СВУП архив(svup_XXXX.zip) если есть
                if ($cf = __checkSVUPZip($profile->id)) {
                    $svup_path = APP_PATH . '/private';
                    $svup_dir = $cf['cert_dir'];
                    $svup_file = $cf['file'];

                    exec("rm -rf $svup_path/$svup_dir/$svup_file");
                }

                $tr->amount = round($expected_amount, 2);

                $car->vin = $expected_vin;
                $car->cost = round($expected_cost, 2);
                $car->status = null;
                $car->updated = time();

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $careditfilename = $car->vin . "_" . time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $file->moveTo(APP_PATH . "/private/correction/" . $careditfilename);
                        }
                    }
                }

                if ($car->save()) {
                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'CAR';
                    $l->user_id = $auth->id;
                    $l->profile_id = $profile ? $profile->id : null;
                    $l->action = 'RESTORED';
                    $l->object_id = $car ? $car->id : null;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = $car ? json_encode(array($car)) : null;
                    $l->vin_before = $vin_before;
                    $l->vin_after = $expected_vin;
                    $l->comment = $car_comment;
                    $l->file = $careditfilename;
                    $l->sign = $sign;
                    $l->save();

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    $tr->save();

                    $message = "Транспортное средство отредактировано.";
                    $success = true;
                } else {
                    $message = "Невозможно отредактировать это транспортное средство.";
                }
            } else {
                $message = "Ошибка: Подпись не прошла проверку !";
            }
        } else {
            $message = "Ошибка: Вы используете несоответствующую профилю подпись. !";
        }

        http_response_code(200);
        return json_encode(["success" => $success, "message" => $message]);
    }

    public function getAnnulledCarAction($cid = 0)
    {
        $this->view->disable();
        $auth = User::getUserBySession();
        $html = null;
        $arr = [];

        if ($cid > 0 && $auth->isEmployee()) {
            $car = Car::findFirstById($cid);
            $profile = Profile::findFirstById($car->profile_id);

            if ($car && $profile) {
                $tr = Transaction::findFirstByProfileId($profile->id);
                $signData = __signData($car->profile_id, $this);

                $correction_logs = CorrectionLogs::findFirst(array(
                    'profile_id = :pid: AND object_id = :car_id: AND type = "CAR" AND action = "ANNULMENT"',
                    'bind' => array(
                        'pid' => $car->profile_id,
                        'car_id' => $car->id,
                    ),
                    "order" => "id DESC"
                ));

                if ($correction_logs->meta_before != null && $correction_logs->meta_before != '_') {
                    $before = json_decode($correction_logs->meta_before, true);
                }
                $expected_cost = $before[0]['cost'];
                $expected_amount = $tr->amount + $expected_cost;
                $expected_vin = $before[0]['vin'];
                $car_cost = __money($car->cost);
                $tr_amount = __money($tr->amount);
                $new_cost = __money($expected_cost);
                $new_amount = __money($expected_amount);

                $html .= <<<TABLE_BODY
                  <tr>
                    <td>{$this->translator->_("vin-code")}</td>
                    <td><del style="color:orange">{$car->vin}</del></td>
                    <td><b style="color:green;">{$expected_vin}</b></td>
                  </tr>
                  <tr>
                    <td>{$this->translator->_("amount")}</td>
                    <td><del style="color:orange">{$car_cost}</del> тг </td>
                    <td><b style="color:green;">{$new_cost}</b> тг </td>
                  </tr>
                  <tr>
                    <td>{$this->translator->_("Сумма по заявке")}</td>
                    <td><del style="color:orange">{$tr_amount}</del> тг </td>
                    <td><b style="color:green;">{$new_amount}</b> тг </td>
                  </tr>
                  <tr>
                    <td>{$this->translator->_("Статус")}</td>
                    <td><del style="color:orange">ДПП Аннулировано</del></td>
                    <td><i class="fa fa-check-circle" style="color: green; text-shadow: 1px 1px 1px #ccc; font-size: 1.8em;"></i></td>
                  </tr>
                TABLE_BODY;

                $arr = array(
                    "html" => $html,
                    "expected_vin" => $expected_vin,
                    "expected_amount" => $expected_amount,
                    "expected_cost" => $expected_cost,
                    "signData" => $signData,
                    "profile_id" => $profile->id,
                    "car_id" => $car->id,
                );

                http_response_code(200);
                return json_encode($arr);
            }
        }

        return false;
    }
}
