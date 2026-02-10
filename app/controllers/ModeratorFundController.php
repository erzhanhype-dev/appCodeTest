<?php

namespace App\Controllers;

use App\Services\Cms\CmsService;
use App\Services\Fund\FundGoodsDocumentService;
use App\Services\Fund\FundService;
use App\Services\Pdf\PdfService;
use CompanyDetail;
use ContactDetail;
use ControllerBase;
use FundCar;
use FundCarHistories;
use FundCorrectionLogs;
use FundFile;
use FundGoods;
use FundGoodsHistories;
use FundLogs;
use FundProfile;
use PersonDetail;
use Profile;
use RefCountry;
use RefModel;
use RefTnCode;
use Transaction;
use User;
use ZipArchive;
use ZipFundDownloadLogs;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;

class ModeratorFundController extends ControllerBase
{
    public function declineAction($pid)
    {
        // просим сессию аутентификации
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($pid);
        $f->blocked = 0;
        $f->sign = null;
        $f->sign_acc = null;
        $f->approve = 'FUND_DECLINED';

        $msg = $this->request->getPost("msg");

        if ($f->save()) {

            if ($f->entity_type == 'CAR') {
                $fund_cars = FundCar::findByFundId($pid);
                foreach ($fund_cars as $car) {
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
                    $f_car_history->status = 'FUND_DECLINED';
                    $f_car_history->dt = time();
                    $f_car_history->user_id = $auth->id;

                    if ($f_car_history->save()) {
                        $car->delete();
                    }
                }
            } else if ($f->entity_type == 'GOODS') {
                $fund_goods = FundGoods::findByFundId($pid);
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
                    }
                }
            }

            __fundRecalc($f->id);

            // логгирование
            $l = new FundLogs();
            $l->login = $auth->idnum;
            $l->action = 'FUND_MSG';
            $l->fund_id = $pid;
            $l->dt = time();
            $l->meta_before = '—';
            $l->meta_after = $msg;
            $l->save();

            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $this->logAction($logString);

            // логгирование
            $l = new FundLogs();
            $l->login = $auth->idnum;
            $l->action = 'FUND_DECLINE';
            $l->fund_id = $pid;
            $l->dt = time();
            $l->meta_before = '—';
            $l->meta_after = json_encode(array($f));
            $l->save();

            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $this->logAction($logString);
        }

        return $this->response->redirect("/moderator_fund/view/$pid");
    }

    public function stageDeclineAction($pid)
    {
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($pid);
        $f->blocked = 1;
        $f->sign_hod = null;
        $f->sign_fad = null;
        $f->sign_hop = null;
        $f->sign_hof = null;
        $f->approve = 'FUND_REVIEW';
        $f->save();

        // логгирование
        $l = new FundLogs();
        $l->login = $auth->idnum;
        $l->action = 'FUND_REVIEW';
        $l->fund_id = $pid;
        $l->dt = time();
        $l->meta_before = '—';
        $l->meta_after = json_encode(array($f));
        $l->save();

        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString);

        return $this->response->redirect("/moderator_fund/view/$pid");
    }

    public function referenceAction()
    {
        $auth = User::getUserBySession();

        $pid = $this->request->getPost("orderId");
        $reference = $this->request->getPost("orderReference");

        $f = FundProfile::findFirstById($pid);
        $f->approve = 'FUND_DONE';
        $f->reference = $reference;
        $f->paid_dt = time();
        $f->save();

        // логгирование
        $l = new FundLogs();
        $l->login = $auth->idnum;
        $l->action = 'FUND_DONE';
        $l->fund_id = $pid;
        $l->dt = time();
        $l->meta_before = '—';
        $l->meta_after = json_encode(array($f));
        $l->save();

        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString);

        $message = "Референс привязан и оплата одобрена.";
        $this->flash->success($message);

        return $this->response->redirect("/moderator_fund/view/$pid");
    }

    public function approveAction($pid)
    {
        $auth = User::getUserBySession();

        if (!($auth->isSuperModerator() || $auth->isModerator())) {
            $message = "У вас нет прав на это действия!";
            $this->logAction($message, 'security');
            $this->flash->error($message);
            return $this->response->redirect("/moderator_fund/view/$pid");
        }

        $f = FundProfile::findFirstById($pid);
        $f->blocked = 1;
        $f->approve = 'FUND_PREAPPROVED';
        $f->signed_by = 0;
        $f->dt_approve = time();
        $f->save();

        // логгирование
        $l = new FundLogs();
        $l->login = $auth->idnum;
        $l->action = 'FUND_PREAPPROVE';
        $l->fund_id = $pid;
        $l->dt = time();
        $l->meta_before = '—';
        $l->meta_after = json_encode(array($f));
        $l->save();

        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString);

        return $this->response->redirect("/moderator_fund/view/$pid");
    }

    /**
     * Список всех заявок пользователя.
     * @return void
     */
    public function indexAction()
    {
        $auth = User::getUserBySession();
        $this->session->remove("fund");

        // входные значения
        $num = '';
        $s_uid = '';
        $p_status = [];
        $p_type = [];
        $p_state = 'ALL';
        $s_number = '';
        $fund_stage_user = false;

        if ($this->request->isPost()) {
            $num = $this->request->getPost("num");
            $fund_number = trim((string)$this->request->getPost("fund_number"));
            $status = (array)$this->request->getPost("status");
            $reset = $this->request->getPost("reset");
            $year = (array)$this->request->getPost("year");
            $type = (array)$this->request->getPost("type");
            $state = (string)$this->request->getPost("state");

            if ($num !== null && $num !== '') {
                $_SESSION['fund_filter_uid'] = json_encode($num);
            }
            if ($fund_number !== '') {
                $_SESSION['fund_filter_number'] = $fund_number;
            }
            if (!empty($status)) {
                $_SESSION['fund_filter_status'] = json_encode(array_values($status));
            }
            if (!empty($year)) {
                $_SESSION['fund_filter_year'] = json_encode(array_values($year));
            }
            if (!empty($type)) {
                $_SESSION['filter_fund_type'] = json_encode(array_values($type));
            }
            if ($state !== '') {
                $_SESSION['fund_filter_state'] = $state;
            }

            if ($reset === 'all') {
                $num = '';
                $s_uid = '';
                $_SESSION['fund_filter_uid'] = json_encode("all");
                $_SESSION['fund_filter_year'] = json_encode([date("Y")]);

                if ($auth->isAccountant()) {
                    $_SESSION['fund_filter_status'] = json_encode(['FUND_PREAPPROVED']);
                } else {
                    $_SESSION['fund_filter_status'] = json_encode(['FUND_REVIEW', 'FUND_PREAPPROVED']);
                }

                $_SESSION['filter_fund_type'] = json_encode(['INS', 'EXP']);
                unset($_SESSION['fund_filter_number']);
                $_SESSION['fund_filter_state'] = 'ALL';
            } else {
                try {
                    $this->cache->delete('fund_companies_formatted_list');
                } catch (\Throwable $e) {
                }
            }
        }

        // номер
        if (isset($_SESSION['fund_filter_number'])) {
            $s_number = (string)$_SESSION['fund_filter_number'];
        }

        // годы
        if (!isset($_SESSION['fund_filter_year'])) {
            $_SESSION['fund_filter_year'] = json_encode([date("Y")]);
        }
        $s_years = (array)json_decode($_SESSION['fund_filter_year'], true);
        if (empty($s_years)) {
            $s_years = [date("Y")];
        }
        $minYear = (int)min($s_years);
        $maxYear = (int)max($s_years);
        $dt_begin = strtotime(sprintf('%d-01-01 00:00:00', $minYear));
        $dt_end = strtotime(sprintf('%d-12-31 23:59:59', $maxYear));

        // статусы
        if (!isset($_SESSION['fund_filter_status'])) {
            if ($auth->isAccountant()) {
                $_SESSION['fund_filter_status'] = json_encode(['FUND_PREAPPROVED']);
            } else {
                $_SESSION['fund_filter_status'] = json_encode(['FUND_REVIEW', 'FUND_PREAPPROVED']);
            }
        }
        $p_status = (array)json_decode($_SESSION['fund_filter_status'], true);

        if (!isset($_SESSION['fund_filter_state'])) {
            $_SESSION['fund_filter_state'] = 'ALL';
        }
        $p_state = (string)$_SESSION['fund_filter_state'];

        // типы
        if (!isset($_SESSION['filter_fund_type'])) {
            $_SESSION['filter_fund_type'] = json_encode(['INS', 'EXP']);
        }
        $p_type = (array)json_decode($_SESSION['filter_fund_type'], true);

        // user_id
        if (!isset($_SESSION['fund_filter_uid'])) {
            $_SESSION['fund_filter_uid'] = json_encode("all");
            $s_uid = 'all';
        } else {
            $s_uid = json_decode($_SESSION['fund_filter_uid'], true);
            if ($s_uid === null) {
                $s_uid = 'all';
            }
        }

        $fund_stage_cond = null;
        if ($auth->isAccountant() && $auth->fund_stage !== 'STAGE_NOT_SET') {
            $fund_stage_user = true;
            switch ($auth->fund_stage) {
                case 'HOD':
                    $fund_stage_cond = ['approve' => 'FUND_PREAPPROVED', 'signed_by' => 0];
                    break;
                case 'FAD':
                    $fund_stage_cond = ['approve' => 'FUND_PREAPPROVED', 'signed_by' => 1];
                    break;
                case 'HOP':
                    $fund_stage_cond = ['approve' => 'FUND_PREAPPROVED', 'signed_by' => 2];
                    break;
                case 'HOF':
                    $fund_stage_cond = ['approve' => 'FUND_PREAPPROVED', 'signed_by' => 3];
                    break;
            }
        }

        $bind = [];
        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'f.id AS id',
                'f.number AS number',
                'f.approve AS approve',
                'f.md_dt_sent AS md_dt_sent',
                'f.amount AS amount',
                'f.type AS type',
                'f.signed_by AS signed_by',
                'IF(u.user_type_id = 1, fio, org_name) AS fio',
                'u.idnum AS idnum',
                'f.entity_type AS entity_type',
            ])
            ->from(['f' => FundProfile::class])
            ->join(User::class, 'f.user_id = u.id', 'u')
            ->orderBy('f.md_dt_sent DESC');

        if ($s_uid !== '' && $s_uid !== 'all') {
            $builder->andWhere('f.user_id = :s_uid:');
            $bind['s_uid'] = $s_uid;
        }

        if ($fund_stage_cond) {
            $builder->andWhere('f.approve = :stageApprove: AND f.signed_by = :stageSigned:');
            $bind['stageApprove'] = $fund_stage_cond['approve'];
            $bind['stageSigned'] = $fund_stage_cond['signed_by'];
        } else {
            if (!empty($p_status)) {
                $builder->andWhere('f.approve IN ({statuses:array})');
                $bind['statuses'] = $p_status;
            }

            $stateMap = [
                'AT_HOD' => 0,
                'AT_FAD' => 1,
                'AT_HOP' => 2,
                'AT_HOF' => 3,
            ];
            if (in_array('FUND_PREAPPROVED', $p_status, true) && isset($stateMap[$p_state])) {
                $builder->andWhere('f.signed_by = :signed_by:');
                $bind['signed_by'] = $stateMap[$p_state];
            }
        }

        if (!empty($p_type)) {
            $builder->andWhere('f.type IN ({types:array})');
            $bind['types'] = $p_type;
        }

        if ($s_number !== '') {
            $builder->andWhere('f.number LIKE :num:');
            $bind['num'] = '%' . $s_number . '%';
        }

        if (!empty($dt_begin) && !empty($dt_end)) {
            $builder->andWhere('f.created BETWEEN :dt_begin: AND :dt_end:');
            $bind['dt_begin'] = (int)$dt_begin;
            $bind['dt_end'] = (int)$dt_end;
        }

        $builder->setBindParams($bind);

        $numberPage = (int)($this->request->getQuery("page", "int") ?: 1);
        $paginator = new QueryBuilderPaginator([
            'builder' => $builder,
            'limit' => 10,
            'page' => $numberPage,
        ]);
        $this->view->page = $paginator->paginate();

        $sqlCompanies = "
          SELECT
            cd.user_id AS user_id,
            last.last_id AS id,
            cd.bin AS bin,
            u.idnum AS idnum,
            cd.name AS name
          FROM (
            SELECT f2.user_id AS user_id, MAX(f2.id) AS last_id
            FROM fund_profile f2
            GROUP BY f2.user_id
          ) AS last
          JOIN user u ON u.id = last.user_id
          JOIN (
            SELECT cd2.*
            FROM company_detail cd2
            JOIN (
              SELECT user_id, MAX(id) AS last_cd_id
              FROM company_detail
              WHERE bin IS NOT NULL AND bin <> ''
              GROUP BY user_id
            ) cd_last ON cd_last.user_id = cd2.user_id AND cd_last.last_cd_id = cd2.id
          ) cd ON cd.user_id = last.user_id
          ORDER BY last.last_id DESC
        ";

        $cache = $this->di->get('cache');
        $companiesCacheKey = 'fund_companies_formatted_list';

        $safeCacheGet = static function ($cache, string $key) {
            try {
                return $cache->get($key);
            } catch (\Throwable $e) {
                return null;
            }
        };
        $safeCacheSet = static function ($cache, string $key, $value, int $ttl) {
            try {
                $cache->set($key, $value, $ttl);
            } catch (\Throwable $e) {
            }
        };

        $companies_view = $safeCacheGet($cache, $companiesCacheKey);

        if ($companies_view === null) {
            $companies = $this->db->fetchAll($sqlCompanies, \Phalcon\Db\Enum::FETCH_OBJ);

            $companies_view = [];
            foreach ($companies as $c) {
                $binText = $c->bin ?: $c->idnum;

                $name = (string)$c->name;
                $prefix = 'ТОВАРИЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ ';
                if (mb_substr($name, 0, mb_strlen($prefix)) === $prefix) {
                    $name = 'ТОО ' . mb_substr($name, mb_strlen($prefix));
                }

                $companies_view[] = (object)[
                    'user_id' => $c->user_id,
                    'label' => "БИН: {$binText} ({$name})",
                ];
            }

            $safeCacheSet($cache, $companiesCacheKey, $companies_view, 43200);
        }

        $this->view->setVars([
            'companies_view' => $companies_view,
            'fund_stage_user' => $fund_stage_user,
            'selected_uid' => $s_uid,
            'selected_status' => $p_status,
            'selected_years' => $s_years,
            'years_list' => range(2020, (int)date('Y')),
            'selected_types' => $p_type,
            'selected_state' => $p_state,
            'fund_filter_number' => $s_number,
        ]);
    }

    public function signAction()
    {
        $__settings = $this->session->get("__settings");
        $auth = User::getUserBySession();

        $pid = $this->request->getPost("orderId");
        $sign = $this->request->getPost("fundSign");
        $type = $this->request->getPost("orderType");

        $f = FundProfile::findFirstById($pid);

        $hash = $f->hash;

        if ($f) {
            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            if ($result && isset($result['data'])) {
                $j = $result['data'];
                $sign = $j['sign'];
                if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                    if ($result['success'] === true) {

                        $type = strtolower($type);
                        $user = User::findFirst([
                            'conditions' => 'LOWER(fund_stage) = :fund_stage:',
                            'bind' => [
                                'fund_stage' => $type,
                            ],
                        ]);

                        $email = $user ? $user->email : null;

                        if ($type == 'hod') {
                            $f->sign_hod = $sign;
                            $f->signed_by = 1;
                        }

                        if ($type == 'fad') {
                            $f->sign_fad = $sign;
                            $f->signed_by = 2;
                        }

                        if ($type == 'hop') {
                            $f->sign_hop = $sign;
                            $f->signed_by = 3;
                        }

                        if ($type == 'hof') {
                            $f->sign_hof = $sign;
                            $f->signed_by = 4;
                        }

                        $f->save();

                        // логгирование
                        $l = new FundLogs();
                        $l->login = $auth->idnum;
                        $l->action = 'FUND_SIGN_' . strtoupper($type);
                        $l->fund_id = $pid;
                        $l->dt = time();
                        $l->meta_before = '—';
                        $l->meta_after = json_encode(array($f));
                        $l->save();

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        if ($email != '') {
                            FundProfile::sendNotification($email, $pid, $f->number, $l->dt);
                        }

                        $this->flash->success("Заявка подписана.");
                        return $this->response->redirect("/moderator_fund/index/");
                    } else {
                        $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                        $this->flash->error("Подпись не прошла проверку!");
                        return $this->response->redirect("/moderator_fund/view/$pid");
                    }
                } else {
                    $this->logAction("Вы используете несоответствующую профилю подпись.", 'security');
                    $this->flash->error("Вы используете несоответствующую профилю подпись.");
                    return $this->response->redirect("/moderator_fund/view/$pid");
                }
            }
        }
    }

    public function signMassAction()
    {
        $__settings = $this->session->get("__settings");
        $auth = User::getUserBySession();

        $signs = $this->request->getPost("fundSigns");
        $payload = $signs !== '' ? json_decode($signs, true) : null;

        if(!empty($payload)) {
            foreach ($payload as $value) {
                $f = FundProfile::findFirstByHash($value['hash']);
                $hash = $f->hash;
                $sign = $value['sign'];

                if ($f) {
                    $cmsService = new CmsService();
                    $result = $cmsService->check($hash, $sign);
                    if ($result && isset($result['data'])) {
                        $j = $result['data'];

                        if($j) {
                            $sign = $j['sign'];
                            if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                                if ($result['success'] == true) {

                                    $type = strtolower($auth->fund_stage);
                                    $user = User::findFirst([
                                        'conditions' => 'LOWER(fund_stage) = :fund_stage:',
                                        'bind' => [
                                            'fund_stage' => $type,
                                        ],
                                    ]);

                                    $email = $user ? $user->email : null;

                                    if ($type == 'hod') {
                                        $f->sign_hod = $sign;
                                        $f->signed_by = 1;
                                    }

                                    if ($type == 'fad') {
                                        $f->sign_fad = $sign;
                                        $f->signed_by = 2;
                                    }

                                    if ($type == 'hop') {
                                        $f->sign_hop = $sign;
                                        $f->signed_by = 3;
                                    }

                                    if ($type == 'hof') {
                                        $f->sign_hof = $sign;
                                        $f->signed_by = 4;
                                    }

                                    $f->save();

                                    // логгирование
                                    $l = new FundLogs();
                                    $l->login = $auth->idnum;
                                    $l->action = 'FUND_SIGN_' . strtoupper($type);
                                    $l->fund_id = $f->id;
                                    $l->dt = time();
                                    $l->meta_before = '—';
                                    $l->meta_after = json_encode(array($f));
                                    $l->save();

                                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                    $this->logAction($logString);

                                    if ($email != '') {
                                        FundProfile::sendNotification($email, $f->id, $f->number, $l->dt);
                                    }

                                    $this->flash->success("Заявка подписана.");
                                } else {
                                    $this->logAction("Подпись не прошла проверку!",'security', 'NOTICE');
                                    $this->flash->error("Подпись не прошла проверку!");
                                    return $this->response->redirect("/moderator_fund/index");
                                }
                            } else {
                                $this->logAction("Вы используете несоответствующую профилю подпись.",'security');
                                $this->flash->error("Вы используете несоответствующую профилю подпись.");
                                return $this->response->redirect("/moderator_fund/index");
                            }
                        }else{
                            $this->logAction("Не удалось проверить подпись!");
                            $this->flash->error("Не удалось проверить подпись!");
                            return $this->response->redirect("/moderator_fund/index");
                        }
                    }
                }
            }
        }

        return $this->response->redirect("/moderator_fund/index/");
    }

    /**
     * Скачивание заявления для заполнения.
     * @param int $pid
     * @param string $type car|goods
     * @return void
     */
    public function applicationAction($pid, $type)
    {
        $this->view->disable();

        if ($type == 'car') {
            if (true) {
                $src = APP_PATH . '/app/templates/html/application/application.html';
                $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                $content = file_get_contents($src);

                $profile = Profile::findFirstById($pid);
                $tr = Transaction::findFirstByProfileId($profile->id);

                $user = User::findFirstById($profile->user_id);

                if ($user->role->name === 'agent' || $user->role->name === 'admin_soft') {
                    if ($profile->agent_sign) {
                        $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                        $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                        $fio_bottom = $profile->agent_name;
                        $fio_line = '______________________________<br />М.П.';
                    } else {
                        $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                        $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                        $fio_bottom = $profile->agent_name;
                        $fio_line = '______________________________<br />';
                    }
                    $city = $profile->agent_city;
                    $address = $profile->agent_address;
                    $phone = $profile->agent_phone;
                } else {
                    if ($user->user_type_id == PERSON) {
                        $pd = PersonDetail::findFirstByUserId($user->id);
                        $cd = ContactDetail::findFirstByUserId($user->id);

                        $iin = '<strong>ИИН: </strong>' . $pd->iin;
                        $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                        $name = '<strong>Импортер: </strong>' . $fio_bottom;
                        $fio_line = '______________________________<br />';
                        $city = $cd->city;
                        $address = $cd->address;
                        $phone = $cd->phone;
                    } else {
                        // а это для ЮЛ
                        $pd = CompanyDetail::findFirstByUserId($user->id);
                        $cd = ContactDetail::findFirstByUserId($user->id);

                        $iin = '<strong>БИН: </strong>' . $pd->bin;
                        $fio_bottom = $pd->name;
                        $name = '<strong>Импортер: </strong>' . $fio_bottom;
                        $fio_line = '______________________________<br />М.П.';
                        $city = $cd->city;
                        $address = $cd->address;
                        $phone = $cd->phone;
                    }
                }

                $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                $content = str_replace('[Z_DATE]', date("d.m.Y", convertTimeZone($tr->date)), $content);
                $content = str_replace('[ZA_CITY]', '<strong>Город постановки на учет: </strong>' . $city, $content);
                $content = str_replace('[Z_CITY]', $city, $content);
                $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                $content = str_replace('[ZA_NAME]', $name, $content);
                $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                $content = str_replace('[Z_SIGN]', '', $content);
                $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                $query = $this->modelsManager->createQuery("
          SELECT
            c.volume AS volume,
            c.vin AS vin,
            c.year AS year,
            c.cost AS cost,
            cc.name AS cat,
            c.date_import AS date_import,
            country.name AS country
          FROM
            Car c
            JOIN Profile p
            JOIN RefCountry country
            JOIN RefCarCat cc
          WHERE
            p.id = :pid: AND
            country.id = c.ref_country AND
            cc.id = c.ref_car_cat AND
            c.profile_id = p.id
          GROUP BY c.id
          ORDER BY c.id DESC");

                $cars = $query->execute(array(
                    "pid" => $profile->id
                ));

                $c = 1;
                $car_content = '';
                foreach ($cars as $key => $v) {
                    $car_content = $car_content . '<tr><td>' . $c . '.</td><td>' . $v->volume . '</td><td>' . $v->vin . '</td><td>' . $v->year . '</td><td>' . $v->country . '</td><td>' . date("d.m.Y", convertTimeZone($v->date_import)) . '</td><td>' . $this->translator->_($v->cat) . '</td><td>' . number_format($v->cost, 2, ",",
                            "&nbsp;") . '</td></tr>';
                    $c++;
                }

                $content = str_replace('[Z_CONTENT]', $car_content, $content);
                file_put_contents($dst, $content);
                (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

                __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
            } else {
                $this->logAction("Вы должны быть агентом.");
                $this->flash->error("Вы должны быть агентом.");

                return $this->response->redirect("/order/view/$pid");
            }
        } else {
            if ($type == 'goods') {
                if (true) {
                    $src = APP_PATH . '/app/templates/html/application_goods/application.html';
                    $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                    $content = file_get_contents($src);

                    $profile = Profile::findFirstById($pid);
                    $tr = Transaction::findFirstByProfileId($profile->id);

                    $user = User::findFirstById($profile->user_id);

                    if ($user->role->name == 'agent' || $user->role->name == 'admin_soft') {
                        if ($profile->agent_sign) {
                            $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                            $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                            $fio_bottom = $profile->agent_name;
                            $fio_line = '______________________________<br />М.П.';
                        } else {
                            $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                            $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                            $fio_bottom = $profile->agent_name;
                            $fio_line = '______________________________<br />';
                        }
                        $city = $profile->agent_city;
                        $address = $profile->agent_address;
                        $phone = $profile->agent_phone;
                    } else {
                        if ($user->user_type_id == PERSON) {
                            $pd = PersonDetail::findFirstByUserId($user->id);
                            $cd = ContactDetail::findFirstByUserId($user->id);

                            $iin = '<strong>ИИН: </strong>' . $pd->iin;
                            $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                            $name = '<strong>Импортер: </strong>' . $fio_bottom;
                            $fio_line = '______________________________<br />';
                            $city = $cd->city;
                            $address = $cd->address;
                            $phone = $cd->phone;
                        } else {
                            // а это для ЮЛ
                            $pd = CompanyDetail::findFirstByUserId($user->id);
                            $cd = ContactDetail::findFirstByUserId($user->id);

                            $iin = '<strong>БИН: </strong>' . $pd->bin;
                            $fio_bottom = $pd->name;
                            $name = '<strong>Импортер: </strong>' . $fio_bottom;
                            $fio_line = '______________________________<br />М.П.';
                            $city = $cd->city;
                            $address = $cd->address;
                            $phone = $cd->phone;
                        }
                    }

                    $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                    $content = str_replace('[Z_DATE]', date("d.m.Y", convertTimeZone($tr->date)), $content);
                    $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
                    $content = str_replace('[Z_CITY]', $city, $content);
                    $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                    $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                    $content = str_replace('[ZA_NAME]', $name, $content);
                    $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                    $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                    $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                    $content = str_replace('[Z_SIGN]', '', $content);
                    $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                    $query = $this->modelsManager->createQuery("
          SELECT
            g.weight AS g_weight,
            g.date_import AS g_date,
            g.basis AS g_basis,
            g.amount AS g_amount,
            tn.code AS tn_code,
            g.ref_tn_add AS tn_add
          FROM
            Goods g
            JOIN Profile p
            JOIN RefTnCode tn
          WHERE
            p.id = :pid: AND
            tn.id = g.ref_tn AND
            g.profile_id = p.id
          GROUP BY g.id
          ORDER BY g.id DESC");

                    $goods = $query->execute(array(
                        "pid" => $profile->id
                    ));

                    $c = 1;
                    $goods_content = '';
                    foreach ($goods as $key => $v) {
                        $good_tn_add = '';
                        $tn_add = false;
                        if ($v->tn_add) {
                            $tn_add = RefTnCode::findFirstById($v->tn_add);
                            if ($tn_add) {
                                $good_tn_add = ' (упаковано ' . $tn_add->code . ')';
                            }
                        }
                        $goods_content = $goods_content . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . $good_tn_add . '</td><td>' . $v->g_weight . '</td><td>' . date("d.m.Y", $v->g_date) . '</td><td>' . $v->g_basis . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td></tr>';
                        $c++;
                    }

                    $content = str_replace('[Z_CONTENT]', $goods_content, $content);
                    file_put_contents($dst, $content);
                    (new PdfService())->generate($dst . ' ' . APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
                    __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
                } else {
                    $this->logAction("Вы должны быть агентом.");
                    $this->flash->error("Вы должны быть агентом.");

                    return $this->response->redirect("/order/view/$pid");
                }
            } else {
                if ($type == 'r20') {
                    if (true) {
                        $src = APP_PATH . '/app/templates/html/application_goods/application_r20.html';
                        $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                        $content = file_get_contents($src);

                        $profile = Profile::findFirstById($pid);
                        $tr = Transaction::findFirstByProfileId($profile->id);

                        $user = User::findFirstById($profile->user_id);

                        if ($user->role->name == 'agent' || $user->role->name == 'admin_soft') {
                            if ($profile->agent_sign) {
                                $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                                $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                                $fio_bottom = $profile->agent_name;
                                $fio_line = '______________________________<br />М.П.';
                            } else {
                                $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                                $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                                $fio_bottom = $profile->agent_name;
                                $fio_line = '______________________________<br />';
                            }
                            $city = $profile->agent_city;
                            $address = $profile->agent_address;
                            $phone = $profile->agent_phone;
                        } else {
                            if ($user->user_type_id == PERSON) {
                                $pd = PersonDetail::findFirstByUserId($user->id);
                                $cd = ContactDetail::findFirstByUserId($user->id);

                                $iin = '<strong>ИИН: </strong>' . $pd->iin;
                                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                                $fio_line = '______________________________<br />';
                                $city = $cd->city;
                                $address = $cd->address;
                                $phone = $cd->phone;
                            } else {
                                // а это для ЮЛ
                                $pd = CompanyDetail::findFirstByUserId($user->id);
                                $cd = ContactDetail::findFirstByUserId($user->id);

                                $iin = '<strong>БИН: </strong>' . $pd->bin;
                                $fio_bottom = $pd->name;
                                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                                $fio_line = '______________________________<br />М.П.';
                                $city = $cd->city;
                                $address = $cd->address;
                                $phone = $cd->phone;
                            }
                        }

                        $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                        $content = str_replace('[Z_DATE]', date("d.m.Y", $tr->date), $content);
                        $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
                        $content = str_replace('[Z_CITY]', $city, $content);
                        $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                        $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                        $content = str_replace('[ZA_NAME]', $name, $content);
                        $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                        $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                        $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                        $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                        $query = $this->modelsManager->createQuery("
                          SELECT
                            g.weight AS g_weight,
                            g.date_import AS g_date,
                            g.price AS g_price,
                            g.amount AS g_amount,
                            tn.code AS tn_code,
                            tn.name AS tn_name,
                            g.goods_type AS goods_type,
                            g.up_type AS up_type,
                            g.date_report AS g_report
                          FROM
                            Goods g
                            JOIN Profile p
                            JOIN RefTnCode tn
                          WHERE
                            p.id = :pid: AND
                            tn.id = g.ref_tn AND
                            g.profile_id = p.id
                          GROUP BY g.id
                          ORDER BY g.id DESC");

                        $goods = $query->execute(array(
                            "pid" => $profile->id
                        ));

                        $_RU_MONTH = array(
                            "январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"
                        );

                        $c = 1;
                        $goods_content = '';
                        $goods_content1 = '';
                        $goods_content2 = '';
                        foreach ($goods as $key => $v) {
                            if ($v->goods_type == 2) {
                                $up_type = '';
                                switch ($v->up_type) {
                                    case 1:
                                        $up_type = 'Бумажная и картонная упаковки, изделия из бумаги и картона';
                                        break;
                                    case 2:
                                        $up_type = 'Стеклянная упаковка';
                                        break;
                                    case 3:
                                        $up_type = 'Полимерная упаковка, изделия из пластмасс';
                                        break;
                                    case 4:
                                        $up_type = 'Металлическая упаковка';
                                        break;
                                    case 5:
                                        $up_type = 'Упаковка из комбинированных материалов';
                                        break;
                                }
                                $goods_content2 = $goods_content2 . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->tn_name . '</td><td>' . $up_type . '</td><td>' . number_format($v->g_weight, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td><td>' . $_RU_MONTH[date('n',
                                        $v->g_report) - 1] . ' ' . date('Y', $v->g_report) . '</td></tr>';
                            }
                            if ($v->goods_type == 1) {
                                $goods_content1 = $goods_content1 . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->tn_name . '</td><td>' . number_format($v->g_weight, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td><td>' . $_RU_MONTH[date('n',
                                        $v->g_report) - 1] . ' ' . date('Y', $v->g_report) . '</td></tr>';
                            }
                            if ($v->goods_type == 0) {
                                $goods_content = $goods_content . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->g_weight . '</td><td>' . date("d.m.Y", $v->g_date) . '</td><td>' . number_format($v->g_price, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td></tr>';
                            }
                            $c++;
                        }

                        $content = str_replace('[Z_CONTENT]', $goods_content, $content);
                        $content = str_replace('[Z_CONTENT1]', $goods_content1, $content);
                        $content = str_replace('[Z_CONTENT2]', $goods_content2, $content);
                        file_put_contents($dst, $content);
                        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

                        __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
                    } else {
                        $this->logAction("Вы должны быть агентом.");
                        $this->flash->error("Вы должны быть агентом.");

                        return $this->response->redirect("/order/view/$pid");
                    }
                }
            }
        }
    }

    public function getLogsAction()
    {
        $auth = User::getUserBySession();
        $pid = $this->request->getPost("pid");
        $this->view->disable();

        if ($auth->isEmployee()) {
            $logs = FundLogs::findByFundId($pid);
            $correction_logs = FundCorrectionLogs::findByFundId($pid);
            $html = '';
            $p_logs = '';
            if ($logs) {
                foreach ($logs as $log) {
                    $log_details = '';
                    $ui = User::findFirstByIdnum($log->login);
                    if ($ui) {
                        if ($ui->user_type_id == 1) {
                            $dt = PersonDetail::findFirstByUserId($ui->id);
                            if ($dt) {
                                $log_details = $dt->first_name . " " . $dt->last_name . " (ИИН " . $dt->iin . ")";
                            }
                        } else {
                            $log_details = $ui->fio . " (ИИН " . $ui->idnum . ")";
                        }
                    } else {
                        $log_details = $log->login . ' (БЕЗ ДЕТАЛИЗАЦИИ)';
                    }

                    $html .= '<tr>';

                    $html .= '<td>' . $log_details . '</td>
                    <td>' . $this->translator->_($log->action) . '</td>
                    <td>' . date("d.m.Y H:i", convertTimeZone($log->dt)) . '</td>';

                    $html .= '</tr>';
                }

                if ($correction_logs) {
                    foreach ($correction_logs as $c_log) {
                        $c_log_details = '';
                        $ui = User::findFirstById($c_log->user_id);
                        if (!$ui) {
                            $ui = User::findFirstById($c_log->user_id);
                        }
                        if ($ui) {
                            if ($ui->user_type_id == 1) {
                                $dt = PersonDetail::findFirstByUserId($ui->id);
                                if ($dt) {
                                    $c_log_details = $dt->first_name . " " . $dt->last_name . " (ИИН " . $dt->iin . ")";
                                }
                            } else {
                                $c_log_details = $ui->fio . " (ИИН " . $ui->idnum . ")";
                            }
                        } else {
                            $c_log_details = $c_log->iin . ' (БЕЗ ДЕТАЛИЗАЦИИ)';
                        }

                        $html .= '<tr>';

                        $c_vin = '';
                        $arr = json_decode($c_log->meta_after, true);
                        if (is_array($arr)) {
                            foreach ($arr as $a) {
                                foreach ($a as $key => $value) {
                                    if ($key == 'vin') {
                                        $c_vin = $value;
                                    }
                                }
                            }
                        }
                        $html .= '<td>' . $c_log_details . '</td>
                                   <td>' . $this->translator->_($c_log->action) . '(' . $c_vin . ')</td>
                                   <td>' . date("d.m.Y H:i", convertTimeZone($c_log->dt)) . '</td>';
                        $html .= '</tr>';
                    }
                }
                return $html;
            } else {
                return '<tr><td></td><td> <span class="badge badge-danger" style="font-size: 16px;">Не найдены!</span></td><td></td></tr>';
            }
        } else {
            return '<tr><td></td><td><span class="badge badge-danger" style="font-size: 16px;">У вас нет прав на это действие !</span></td><td></td></tr>';
        }
    }

    /**
     * Загрузка необходимых документов.
     * @return void
     */
    public function docAction()
    {
        $order = $this->request->getPost("order_id");
        $doc_type = $this->request->getPost("doc_type");
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($order);

        if ($f->user_id == $auth->id || $auth->isEmployee()) {
            if ($this->request->hasFiles() && $doc_type != '') {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getSize() > 0) {
                        $nf = new FundFile();
                        $nf->fund_id = $f->id;
                        $nf->type = $doc_type;
                        $nf->original_name = $file->getName();
                        $nf->ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                        $nf->save();
                        $file->moveTo(APP_PATH . "/private/fund/" . $nf->id . "." . pathinfo($file->getName(), PATHINFO_EXTENSION));
                        $this->flash->success("Файл добавлен.");
                        $this->logAction("Файл добавлен.");
                    }
                }
            } else {
                $this->flash->warning("Укажите тип документа.");
            }
        } else {
            $message = "У вас нет прав на это действие.";
            $this->logAction($message, 'security');
            $this->flash->warning($message);
        }

        if ($auth->isEmployee()) {
            return $this->response->redirect("/fund/view/$order");
        } else {
            return $this->response->redirect("/fund/view/$order");
        }
    }

    /**
     * Скачать документ.
     * @param int $id
     * @return void
     */
    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund/";
        $auth = User::getUserBySession();

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);

        if ($f->user_id == $auth->id || ($auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET' || $auth->isAccountant())) {
            if ($f->entity_type == 'GOODS') {
                if ($pf->type == 'application' || $pf->type == 'calculation_cost') {
                    if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                } else {
                    if (file_exists($path . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                }
            } else {
                if ($f->created > 1744012800) {
                    if ($pf->type == 'calculation_cost' || $pf->type == 'other') {
                        if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                        }
                    } else {
                        if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                        }
                    }
                } else {
                    if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                    }
                }
            }
        }
    }

    /**
     * Просмотреть документ.
     * @param int $id
     * @return void
     */
    public function viewdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund/";

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);
        $auth = User::getUserBySession();

        if ($f->user_id == $auth->id || ($auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET' || $auth->isAccountant())) {
            if ($f->entity_type == 'GOODS') {
                if ($pf->type == 'application' || $pf->type == 'calculation_cost') {
                    if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                } else {
                    if (file_exists($path . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                }
            } else {
                if ($f->created > 1744012800) {
                    if ($pf->type == 'calculation_cost' || $pf->type == 'other') {
                        if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                        }
                    } else {
                        if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                        }
                    }
                } else {
                    if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                    }
                }
            }

            $this->logAction('Просмотр файла', 'access');
        }
    }

    /**
     * Просмотреть документ.
     * @param int $id
     * @return void
     */
    public function viewtmpAction($id, $type)
    {
        $this->view->disable();

        $auth = User::getUserBySession();
        if (!$auth) {
            http_response_code(401);
            echo "Неавторизован";
            return;
        }

        $fund = FundProfile::findFirstById($id);
        if (!$fund) {
            http_response_code(404);
            $this->flash->error("Финансирование не найден");
            return $this->response->redirect("/moderator_fund/view/$fund->id");
        }

        // Проверка доступа
        if (!($fund->user_id == $auth->id || $auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET' || $auth->isAccountant())) {
            http_response_code(403);

            $this->logAction('Доступ запрещен', 'security');
            $this->flash->error("Доступ запрещен");
            return $this->response->redirect("/moderator_fund/view/$fund->id");
        }

        // Генерация файла
        $filePath = '';
        if ($fund->entity_type == 'GOODS') {
            $fundGoodsDocumentService = new FundGoodsDocumentService();
            if ($type == 'fund') {
                $filePath = $fundGoodsDocumentService->generateStatement($fund);
            } else {
                $filePath = $fundGoodsDocumentService->generateApp($fund, $type);
            }
        } else {
            __genFund($id, $type, null);
            $filePath = APP_PATH . "/storage/temp/" . $type . '_' . $id . '.pdf';
        }

        // Проверка существования файла
        if (!file_exists($filePath)) {
            http_response_code(404);
            $this->flash->error("Файл не найден");
            return $this->response->redirect("/moderator_fund/view/$fund->id");
        }

        $this->logAction('Просмотр файла', 'access');

        // Вывод PDF
        header('Content-Type: application/pdf');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        if (ob_get_length()) {
            ob_end_clean();
        }
        ob_clean();
        flush();
        readfile($filePath);
    }


    /**
     * Удаление документа к заявке.
     * @param int $id
     * @return void
     */
    public function rmdocAction($id)
    {
        $auth = User::getUserBySession();
        $path = APP_PATH . "/private/fund/";

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);

        if ($auth->isAdminSoft() || (!$f->blocked && $f->user_id == $auth->id)) {
            //unlink($path.$pf->id.'.'.$pf->ext);
            $pf->visible = 0;
            $pid = $pf->fund_id;
            $pf->save();
            $this->logAction("Файл удален");
        } else {
            $this->logAction("Вы не можете удалить этот файл.", 'security');
            $this->flash->error("Вы не можете удалить этот файл.");
            return $this->response->redirect("/fund/index/");
        }

        if ($auth->isAdminSoft()) {
            return $this->response->redirect("/fund/view/$pid");
        } else {
            return $this->response->redirect("/fund/view/$pid");
        }
    }

    /**
     * Восстановление документа к заявке.
     * @param int $id
     * @return void
     */
    public function restoreAction($id)
    {
        $auth = User::getUserBySession();
        $pf = FundFile::findFirstById($id);

        if ($auth->isAdminSoft()) {
            $pf->visible = 1;
            $pid = $pf->fund_id;
            $pf->save();

            $this->logAction("Файл восстановлен");
        }

        $this->response->redirect("/fund/view/$pid");
    }

    /**
     * Просмотр заявки.
     * @param integer $pid
     * @return void
     */
    public function viewAction($pid)
    {
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($pid);

        if ($f->number == null) {
            $f->number = __getFundNumber($f->id);
            $f->save();
        }

        if ($auth && $f) {
            $files = FundFile::find(array(
                "visible = 1 AND fund_id = :pid:",
                "bind" => array(
                    "pid" => $f->id
                )
            ));

            if (file_exists(APP_PATH . '/storage/temp/fund_msg_' . $pid . '.txt') && $f->approve == 'FUND_DECLINED') {
                $msg_modal = file_get_contents(APP_PATH . '/storage/temp/fund_msg_' . $pid . '.txt');
                $this->flash->error('<strong>Сообщение менеджера:</strong> ' . $msg_modal);
            }

            $app_form = false;
            $app_form_query = FundFile::count(array(
                "type = 'application' AND fund_id = :pid: AND visible = 1",
                "bind" => array(
                    "pid" => $f->id
                )
            ));

            if ($app_form_query > 0) {
                $app_form = true;
            }

            $deleted_car = [];
            $deleted_goods = [];
            $fund_cars = [];
            $fund_goods = [];

            $fundService = new FundService();

            if ($f->entity_type == 'CAR') {
                $fund_cars = $fundService->getFundCarsByFundId($f);
                $deleted_car = $fundService->getDeletedFundCarsByFundId($f);
            } else if ($f->entity_type == 'GOODS') {
                $fund_goods = $fundService->getFundGoodsByFundId($f);
                $deleted_goods = $fundService->getDeletedFundGoodsByFundId($f);
            }

            $this->logAction("Просмотр заявки", 'access');

            $this->view->setVars(array(
                "pid" => $pid,
                "fund" => $f,
                "files" => $files,
                "app_form" => $app_form,
                "deleted_car" => $deleted_car,
                "deleted_goods" => $deleted_goods,
                "auth" => $auth,
                "fund_cars" => $fund_cars,
                "fund_goods" => $fund_goods,
            ));
        } else {
            return $this->response->redirect("/moderator_fund/index/");
        }
    }

    public function cancelledListAction(int $id = 0)
    {
        $this->view->disable();
        $data = array();

        $sql = <<<SQL
        SELECT
          c.car_id AS c_id,
          c.volume AS c_volume,
          c.vin AS c_vin,
          c.cost AS c_cost,
          cc.name AS c_cat,
          c.date_produce AS c_date_produce,
          c.status AS c_status
        FROM FundCarHistories c
          JOIN RefCarCat cc
          JOIN RefCarType t
        WHERE
          c.fund_id = :pid: AND
          cc.id = c.ref_car_cat
        GROUP BY c.id
        ORDER BY c.id DESC
      SQL;

        $deleted_cars = $this->modelsManager->createQuery($sql);

        $cancelled_cars = $deleted_cars->execute(array(
            "pid" => $id
        ));

        if (count($cancelled_cars) > 0) {
            foreach ($cancelled_cars as $c) {
                $data[] = [
                    "c_id" => $c->c_id,
                    "c_volume" => $c->c_volume,
                    "c_vin" => $c->c_vin,
                    "c_cost" => $c->c_cost,
                    "c_cat" => $this->translator->_($c->c_cat),
                    "c_date_produce" => date('d.m.Y', convertTimeZone($c->c_date_produce)),
                    "c_status" => $this->translator->_($c->c_status),
                ];
            }
        }

        if (is_array($data) && count($data) > 0) {
            $json_data = array(
                "draw" => 1,
                "recordsTotal" => intval(count($data)),
                "recordsFiltered" => intval(count($data)),
                "data" => $data,
            );
            http_response_code(200);
            return json_encode($json_data);
        } else {
            $json_data = array(
                "draw" => 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
            );
            http_response_code(200);
            return json_encode($json_data);
        }
    }

    public function getMsgAction(int $id = 0): string
    {
        $this->view->disable();
        $message = 'NOT FOUND';

        $msg = FundLogs::findFirst([
            "conditions" => "fund_id = :fund_id: AND action = 'FUND_MSG'",
            "bind" => [
                "fund_id" => $id
            ],
            "order" => "id DESC"
        ]);

        if ($msg) {
            http_response_code(200);
            $message = "<strong>Сообщение менеджера:</strong> $msg->meta_after";
        }

        return $message;
    }

    /**
     * Правка заявки.
     * @param int $pid
     * @return void
     */
    public function editAction($pid)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $f = FundProfile::findFirstById($pid);

            if ($auth->id != $f->user_id || $f->blocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security');
                $this->flash->error($message);
                return $this->response->redirect("/fund/index/");
            }

            $type = $this->request->getPost("order_type");
            $w_a = $this->request->getPost("w_a");
            $w_b = $this->request->getPost("w_b");
            $w_c = $this->request->getPost("w_c");
            $w_d = $this->request->getPost("w_d");
            $e_a = $this->request->getPost("e_a");
            $r_a = $this->request->getPost("r_a");
            $r_b = $this->request->getPost("r_b");
            $r_c = $this->request->getPost("r_c");
            $tc_a = $this->request->getPost("tc_a");
            $tc_b = $this->request->getPost("tc_b");
            $tc_c = $this->request->getPost("tc_c");
            $tt_a = $this->request->getPost("tt_a");
            $tt_b = $this->request->getPost("tt_b");
            $tt_c = $this->request->getPost("tt_c");
            $period_start = $this->request->getPost("period_start");
            $period_end = $this->request->getPost("period_end");
            $country = $this->request->getPost("car_country");

            $f->type = $type;
            $f->period_start = strtotime($period_start . ' 00:00:00');
            $f->period_end = strtotime($period_end . ' 00:00:00');
            $f->ref_country_id = $country;
            $f->w_a = $w_a;
            $f->w_b = $w_b;
            $f->w_c = $w_c;
            $f->w_d = $w_d;
            $f->e_a = $e_a;
            $f->r_a = $r_a;
            $f->r_b = $r_b;
            $f->r_c = $r_c;
            $f->tc_a = $tc_a;
            $f->tc_b = $tc_b;
            $f->tc_c = $tc_c;
            $f->tt_a = $tt_a;
            $f->tt_b = $tt_b;
            $f->tt_c = $tt_c;

            if ($f->save()) {
                $message = "Изменения сохранены.";
                $this->logAction($message);
                $this->flash->success($message);
                __fundRecalc($pid);
                return $this->response->redirect("/fund/index/");
            } else {
                $message = "Нет возможности сохранить ваши изменения.";
                $this->logAction($message);
                $this->flash->error($message);
                return $this->response->redirect("/fund/index/");
            }
        } else {
            $f = FundProfile::findFirstById($pid);
            $countries = RefCountry::find(array('id NOT IN (1, 201)'));
            $model = RefModel::find();

            if ($auth->id != $f->user_id || $f->blocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message,'security');
                $this->flash->error($message);
                return $this->response->redirect("/fund/index/");
            }

            $this->view->setVars(array(
                "fund" => $f,
                "countries" => $countries,
                "model" => $model
            ));
        }
    }

    /**
     * Форма новой заявки.
     * @return void
     */
    public function newAction()
    {
        $countries = RefCountry::find(array('id NOT IN (1, 201)'));

        $this->view->setVars(array(
            "countries" => $countries,
        ));
    }

    /**
     * Добавить новую заявку (в базу).
     */
    public function addAction()
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $type = $this->request->getPost("order_type");
            $w_a = $this->request->getPost("w_a");
            $w_b = $this->request->getPost("w_b");
            $w_c = $this->request->getPost("w_c");
            $w_d = $this->request->getPost("w_d");
            $e_a = $this->request->getPost("e_a");
            $r_a = $this->request->getPost("r_a");
            $r_b = $this->request->getPost("r_b");
            $r_c = $this->request->getPost("r_c");
            $tc_a = $this->request->getPost("tc_a");
            $tc_b = $this->request->getPost("tc_b");
            $tc_c = $this->request->getPost("tc_c");
            $tt_a = $this->request->getPost("tt_a");
            $tt_b = $this->request->getPost("tt_b");
            $tt_c = $this->request->getPost("tt_c");
            $period_start = $this->request->getPost("period_start");
            $period_end = $this->request->getPost("period_end");
            $country = $this->request->getPost("car_country");

            $f = new FundProfile();
            $f->created = time();
            $f->user_id = $auth->id;
            $f->type = $type;
            $f->period_start = strtotime($period_start . ' 00:00:00');
            $f->period_end = strtotime($period_end . ' 00:00:00');
            $f->ref_country_id = $country;
            $f->w_a = $w_a;
            $f->w_b = $w_b;
            $f->w_c = $w_c;
            $f->w_d = $w_d;
            $f->e_a = $e_a;
            $f->r_a = $r_a;
            $f->r_b = $r_b;
            $f->r_c = $r_c;
            $f->tc_a = $tc_a;
            $f->tc_b = $tc_b;
            $f->tc_c = $tc_c;
            $f->tt_a = $tt_a;
            $f->tt_b = $tt_b;
            $f->tt_c = $tt_c;

            if ($f->save()) {
                __fundRecalc($f->id);
                return $this->response->redirect("/fund/index/");
            }
        }
    }

    public function downloadZipAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund/";
        $auth = User::getUserBySession();

        // Проверяем существование объекта фонда
        $fund = FundProfile::findFirstById($id);
        if (!$fund) {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/moderator_fund");
        }

        // Загружаем список видимых файлов
        $files = FundFile::find([
            "conditions" => "fund_id = :fid: AND visible = 1",
            "bind" => ["fid" => $fund->id]
        ]);

        // Генерируем уникальное имя ZIP-файла
        $zipName = 'fund_download_' . $fund->id . '_' . time() . '.zip';
        $zipPath = $path . $zipName;

        // Создаём архив
        $zip = new ZipArchive();
        $res = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            $this->flash->error("Ошибка создания ZIP архива. Код: " . $res);
            return $this->response->redirect("/moderator_fund");
        }

        $fund_id_list = [];
        $fund_file_count = 0;

        // Добавляем файлы в ZIP
        foreach ($files as $f) {
            if ($f->type == 'calculation_cost' || $f->type == 'other' || $f->type == 'application') {
                $filePath = $path . 'fund_' . $f->type . '_' . $f->id . '.' . $f->ext;
                $filename = 'fund_' . $f->type . '_' . $f->id . '.' . $f->ext;
            } else {
                $filePath = $path . $f->type . '_' . $f->id . '.' . $f->ext;
                $filename = $f->type . '_' . $f->id . '.' . $f->ext;
            }

            if (file_exists($filePath)) {
                // В ZIP файл будет называться по исходному имени
                $zip->addFile($filePath, $filename);

                $fund_file_count++;
                $fund_id_list[] = $f->id;

            } else {
                // Файл отсутствует — логируем это
                $fund_id_list[] = [
                    'id' => $f->id,
                    'missing' => true
                ];
            }
        }

        // Закрываем ZIP
        $zip->close();

        // Логируем скачивание
        $log = new ZipFundDownloadLogs();
        $log->fund_profile_id = $fund->id;
        $log->user_id = $auth->id;
        $log->files = json_encode($fund_id_list);
        $log->file_count = $fund_file_count;
        $log->dt = time();
        $log->save();

        // Проверяем, создан ли файл
        if (file_exists($zipPath)) {
            $this->logAction('Скачивание архива', 'access');
            __downloadFile($zipPath);
            return;
        }

        // Если по какой-то причине ZIP не создан
        $this->logAction("Файлы не найдены.");
        $this->flash->warning("Файлы не найдены.");
        return $this->response->redirect("/moderator_fund");
    }

    public function getFundHashesAction(){
        $auth = User::getUserBySession();
        $fund_ids = $this->request->getPost('fund_ids');
        if($auth->isSuperModerator() || $auth->isAccountant()){
            $fund_profile_hashes = FundProfile::find([
                "conditions" => "id in (" . implode(',', $fund_ids) . ")",
                "select" => "hash",
            ]);
            return $this->response->setJsonContent([
                'succces' => true,
                'fund_ids' => $fund_ids,
                'hashes' => $fund_profile_hashes
            ]);
        }

        return $this->response->setJsonContent([
            'succces' => false,
            'fund_ids' => $fund_ids,
        ]);
    }

}

