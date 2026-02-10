<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\Cms\CmsService;
use Car;
use ClientCorrectionCars;
use ClientCorrectionFile;
use ClientCorrectionGoods;
use ClientCorrectionLogs;
use ClientCorrectionProfile;
use ContactDetail;
use ControllerBase;
use CorrectionLogs;
use File;
use FundCar;
use FundCorrectionLogs;
use Goods;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Profile;
use RefCarCat;
use RefCarType;
use RefCountry;
use RefInitiator;
use RefTnCode;
use TExport;
use TInner;
use Transaction;
use User;

class CorrectionRequestController extends ControllerBase
{
    public function indexAction(): void
    {
        $auth = User::getUserBySession();

        // Обработка POST
        if ($this->request->isPost()) {
            $num = $this->request->getPost('num', 'trim');
            $pid = $this->request->getPost('pid', 'trim');
            $reset = $this->request->getPost('reset', 'trim');
            $status = $this->request->getPost('status'); // массив или null
            $action = $this->request->getPost('action'); // массив или null

            if ($num) {
                $_SESSION['cr_num_search'] = $num;
                $_SESSION['cr_pid_search'] = '';
            }

            if ($pid) {
                $_SESSION['cr_pid_search'] = $pid;
                $_SESSION['cr_num_search'] = '';
            }

            if ($status) {
                $_SESSION['cr_status_search'] = json_encode(array_values((array)$status));
            }
            if ($action) {
                $_SESSION['cr_action_search'] = json_encode(array_values((array)$action));
            }

            if ($reset === 'all') {
                $cid = null;
                $profile_id = null;
                $_SESSION['cr_num_search'] = '';
                $_SESSION['cr_pid_search'] = '';
                unset($_SESSION['cr_status_search'], $_SESSION['cr_action_search']);

                if ($auth->isAccountant()) {
                    $_SESSION['cr_status_search'] = json_encode(['SENT_TO_ACCOUNTANT']);
                } else {
                    $_SESSION['cr_status_search'] = json_encode([
                        'SEND_TO_MODERATOR',
                        'APPROVED_BY_MODERATOR',
                        'DECLINED',
                        'SENT_TO_ACCOUNTANT',
                    ]);
                }

                $_SESSION['cr_action_search'] = json_encode(['CORRECTION', 'ANNULMENT', 'DELETED', 'CREATED']);
            }
        }

        // Значения по умолчанию для фильтров статусов и действий
        if (!isset($_SESSION['cr_action_search'])) {
            $_SESSION['cr_action_search'] = json_encode(['CORRECTION', 'ANNULMENT', 'DELETED', 'CREATED']);
        }

        if (!isset($_SESSION['cr_status_search'])) {
            if ($auth->isAccountant()) {
                $_SESSION['cr_status_search'] = json_encode(['SENT_TO_ACCOUNTANT']);
            } else {
                $_SESSION['cr_status_search'] = json_encode([
                    'SEND_TO_MODERATOR',
                    'APPROVED_BY_MODERATOR',
                    'DECLINED',
                    'SENT_TO_ACCOUNTANT',
                ]);
            }
        }

        // Извлечение фильтров из сессии
        $cid = $_SESSION['cr_num_search'] ?? null;
        if (!empty($cid)) {
            $cid = (int)$cid;
        }

        $profile_id = $_SESSION['cr_pid_search'] ?? null;
        if ($profile_id !== null && $profile_id !== '') {
            $profile_id = (int)$profile_id;
        } else {
            $profile_id = null;
        }

        $p_action = json_decode($_SESSION['cr_action_search'] ?? '[]', true) ?: [];
        $p_status = json_decode($_SESSION['cr_status_search'] ?? '[]', true) ?: [];

        // Текущая страница
        $numberPage = (int)$this->request->getQuery('page', 'int', 1);

        // Построение запроса
        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'id' => 'c.id',
                'c_status' => 'c.status',
                'c_profile' => 'c.profile_id',
                'ptype' => 'c.type',
                'created' => 'c.created',
                'object_id' => 'c.object_id',
                'action' => 'c.action',
                'user_type_id' => 'u.user_type_id',
                'fio' => 'u.fio',
                'org_name' => 'u.org_name',
                'idnum' => 'u.idnum',
                'vin' => '(SELECT car.vin FROM Car car WHERE car.id = c.object_id)',
            ])
            ->from(['c' => 'ClientCorrectionProfile'])
            ->join('User', 'u.id = c.user_id', 'u')
            ->groupBy('c.id')
            ->orderBy('c.id DESC');

        // Базовые условия + bind
        $conds = [];
        $bind = [];

        if ($auth->isClient()) {
            $conds[] = 'c.user_id = :uid:';
            $bind['uid'] = (int)$auth->id;
        }
        if (!empty($cid)) {
            $conds[] = 'c.id = :cid:';
            $bind['cid'] = (int)$cid;
        }
        if (!empty($profile_id)) {
            $conds[] = 'c.profile_id = :pid:';
            $bind['pid'] = (int)$profile_id;
        }

        if ($conds) {
            $builder->where(implode(' AND ', $conds), $bind);
        }

        // IN-условия через inWhere
        if (!empty($p_status)) {
            $builder->inWhere('c.status', array_values($p_status));
        }
        if (!empty($p_action)) {
            $builder->inWhere('c.action', array_values($p_action));
        }

        $this->logAction('Просмотр списка заявок', 'access');

        // Пагинация
        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 5,
            'page' => $numberPage,
        ]);

        $this->view->page = $paginator->paginate();
        $this->view->auth = $auth;
    }

    public function viewAction(int $pid, int $objId)
    {
        $auth = User::getUserBySession();
        if (!$auth) {
            $this->flash->error('Сессия истекла');
            return $this->response->redirect('/auth/login');
        }

        /** @var Profile|null $pr */
        $pr = Profile::findFirst([
            'conditions' => 'id = :pid:',
            'bind' => ['pid' => $pid],
        ]);

        /** @var ClientCorrectionProfile|null $ccPr */
        $ccPr = ClientCorrectionProfile::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $objId],
        ]);

        if (!$pr || !$ccPr) {
            $this->flash->error('Заявка не найдена.');
            return $this->response->redirect('/correction_request/index/');
        }

        // доступ
        $own = ($pr->user_id === $ccPr->user_id) && ($ccPr->user_id === $auth->id);
        if (!$own && !$auth->isEmployee()) {
            $this->logAction('У вас нет прав на это действие.', 'security', 'ALERT');
            $this->flash->error('У вас нет прав на это действие.');
            return $this->response->redirect('/correction_request/index/');
        }

        // подпись
        $signData = __signData($pid, $this);

        // профиль + транзакция + модератор
        $profileRow = $this->modelsManager
            ->createBuilder()
            ->columns([
                'p_id' => 'p.id',
                'p_name' => 'p.name',
                'p_created' => 'p.created',
                'p_moderator' => 'p.moderator_id',
                'agent_name' => 'p.agent_name',
                'agent_iin' => 'p.agent_iin',
                'agent_city' => 'p.agent_city',
                'agent_phone' => 'p.agent_phone',
                't_amount' => 't.amount',
                't_status' => 't.status',
                't_approve' => 't.approve',
                't_id' => 't.id',
                't_dt_sent' => 't.md_dt_sent',
                'title' => 'IF(u.user_type_id = 1, u.fio, u.org_name)',
            ])
            ->from(['p' => Profile::class])
            ->join(Transaction::class, 't.profile_id = p.id', 't')
            ->leftJoin(User::class, 'u.id = p.moderator_id', 'u')
            ->where('p.id = :pid:', ['pid' => $pid])
            ->limit(1)
            ->getQuery()
            ->execute()
            ->getFirst();

        // файлы и логи
        $files = File::findByProfileId($pid);
        $cApp = ClientCorrectionFile::findByCcpId($objId);
        $ccLogs = ClientCorrectionLogs::findByCcpId($ccPr->id);

        // сообщения по отклонениям
        if ($ccLogs && $ccLogs->count()) {
            foreach ($ccLogs as $log) {
                if ($log->action === 'DECLINED' && !empty($log->comment)) {
                    $this->flash->error(
                        'Отклонено: ' . $log->comment . ', ' . date('d.m.Y H:i', (int)$log->dt)
                    );
                }
            }
        }

        // общие контейнеры
        $before = null;
        $after = null;

        $countryAfter = null;
        $countryBefore = null;

        $countryImportAfter = null;
        $countryImportBefore = null;

        $carTypeAfter = null;
        $carTypeBefore = null;

        $carCatAfter = null;
        $carCatBefore = null;

        $gTnAfter = null;
        $gTnBefore = null;
        $gTnAddAfter = null;
        $gTnAddBefore = null;

        $goods = null;
        $cars = null;

        if ($pr->type === 'CAR') {
            /** @var ClientCorrectionCars|null $after */
            $after = ClientCorrectionCars::findFirst([
                'conditions' => 'ccp_id = :id:',
                'bind' => ['id' => $objId],
            ]);

            /** @var Car|null $before */
            $before = Car::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $ccPr->object_id],
            ]);

            // ограничим выборки до нужной машины в профиле
            $cars = Car::find([
                'conditions' => 'profile_id = :pid: AND id = :carId:',
                'bind' => ['pid' => $pid, 'carId' => $ccPr->object_id],
            ]);

            // группируем справочники
            $countryIds = array_values(array_unique(array_filter([
                $after?->ref_country, $before?->ref_country,
            ])));

            $countryImportIds = array_values(array_unique(array_filter([
                $after?->ref_country_import, $before?->ref_country_import,
            ])));

            if ($countryImportIds) {
                $countries = RefCountry::query()->inWhere('id', $countryIds)->execute()->toArray();
                $countriesById = [];
                foreach ($countries as $c) {
                    $countriesById[$c['id']] = $c;
                }
                $countryImportAfter = $after && isset($countriesById[$after->ref_country_import]) ? (object)$countriesById[$after->ref_country_import] : null;
                $countryImportBefore = $before && isset($countriesById[$before->ref_country_import]) ? (object)$countriesById[$before->ref_country_import] : null;
            }

            if ($countryIds) {
                $countries = RefCountry::query()->inWhere('id', $countryIds)->execute()->toArray();
                $countriesById = [];
                foreach ($countries as $c) {
                    $countriesById[$c['id']] = $c;
                }
                $countryAfter = $after && isset($countriesById[$after->ref_country]) ? (object)$countriesById[$after->ref_country] : null;
                $countryBefore = $before && isset($countriesById[$before->ref_country]) ? (object)$countriesById[$before->ref_country] : null;
            }

            $typeIds = array_values(array_unique(array_filter([
                $after?->ref_car_type_id, $before?->ref_car_type_id,
            ])));
            if ($typeIds) {
                $types = RefCarType::query()->inWhere('id', $typeIds)->execute()->toArray();
                $typesById = [];
                foreach ($types as $t) {
                    $typesById[$t['id']] = $t;
                }
                $carTypeAfter = $after && isset($typesById[$after->ref_car_type_id]) ? (object)$typesById[$after->ref_car_type_id] : null;
                $carTypeBefore = $before && isset($typesById[$before->ref_car_type_id]) ? (object)$typesById[$before->ref_car_type_id] : null;
            }

            $catIds = array_values(array_unique(array_filter([
                $after?->ref_car_cat, $before?->ref_car_cat,
            ])));
            if ($catIds) {
                $cats = RefCarCat::query()->inWhere('id', $catIds)->execute()->toArray();
                $catsById = [];
                foreach ($cats as $c) {
                    $catsById[$c['id']] = $c;
                }
                $carCatAfter = $after && isset($catsById[$after->ref_car_cat]) ? (object)$catsById[$after->ref_car_cat] : null;
                $carCatBefore = $before && isset($catsById[$before->ref_car_cat]) ? (object)$catsById[$before->ref_car_cat] : null;
            }
        } else {
            /** @var ClientCorrectionGoods|null $after */
            $after = ClientCorrectionGoods::findFirst([
                'conditions' => 'ccp_id = :id:',
                'bind' => ['id' => $objId],
            ]);

            /** @var Goods|null $before */
            $before = Goods::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $after?->good_id],
            ]);

            $goods = Goods::find([
                'conditions' => 'profile_id = :pid:',
                'bind' => ['pid' => $pid],
            ]);

            $countryIds = array_values(array_unique(array_filter([
                $after?->ref_country, $before?->ref_country,
            ])));
            if ($countryIds) {
                $countries = RefCountry::query()->inWhere('id', $countryIds)->execute()->toArray();
                $countriesById = [];
                foreach ($countries as $c) {
                    $countriesById[$c['id']] = $c;
                }
                $countryAfter = $after && isset($countriesById[$after->ref_country]) ? (object)$countriesById[$after->ref_country] : null;
                $countryBefore = $before && isset($countriesById[$before->ref_country]) ? (object)$countriesById[$before->ref_country] : null;
            }

            $tnIds = array_values(array_unique(array_filter([
                $after?->ref_tn, $before?->ref_tn, $after?->ref_tn_add, $before?->ref_tn_add,
            ])));
            if ($tnIds) {
                $tns = RefTnCode::query()->inWhere('id', $tnIds)->execute()->toArray();
                $tnById = [];
                foreach ($tns as $t) {
                    $tnById[$t['id']] = $t;
                }
                $gTnAfter = $after && isset($tnById[$after->ref_tn]) ? (object)$tnById[$after->ref_tn] : null;
                $gTnBefore = $before && isset($tnById[$before->ref_tn]) ? (object)$tnById[$before->ref_tn] : null;
                $gTnAddAfter = $after && isset($tnById[$after->ref_tn_add]) ? (object)$tnById[$after->ref_tn_add] : null;
                $gTnAddBefore = $before && isset($tnById[$before->ref_tn_add]) ? (object)$tnById[$before->ref_tn_add] : null;
            }
        }

        // пользователь
        $userRow = $this->modelsManager
            ->createBuilder()
            ->columns([
                'user_id' => 'u.id',
                'login' => 'u.idnum',
                'email' => 'u.email',
                'user_type' => 'u.user_type_id',
                'title' => 'IF(u.user_type_id = 1, u.fio, u.org_name)',
                'phone' => 'cd.phone',
            ])
            ->from(['p' => Profile::class])
            ->join(User::class, 'u.id = p.user_id', 'u')
            ->join(ContactDetail::class, 'cd.user_id = u.id', 'cd')
            ->where('p.id = :pid:', ['pid' => $pid])
            ->limit(1)
            ->getQuery()
            ->execute()
            ->getFirst();

        $initiator = RefInitiator::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $ccPr->initiator_id],
        ]);

        $title = $userRow?->title ?? '';
        $this->view->setVars([
            'initiator' => $initiator,
            'pr' => $pr,
            'profile' => $profileRow,
            'files' => $files,
            'before' => $before,
            'after' => $after,
            'user' => $userRow ?: null,
            'title' => $title,
            'country_after' => $countryAfter,
            'country_before' => $countryBefore,
            'country_import_after' => $countryImportAfter,
            'country_import_before' => $countryImportBefore,
            'car_type_after' => $carTypeAfter,
            'car_type_before' => $carTypeBefore,
            'car_cat_after' => $carCatAfter,
            'car_cat_before' => $carCatBefore,
            'g_tn_after' => $gTnAfter,
            'g_tn_before' => $gTnBefore,
            'g_tn_add_after' => $gTnAddAfter,
            'g_tn_add_before' => $gTnAddBefore,
            'sign_data' => $signData,
            'c_app' => $cApp,
            'cc_pr' => $ccPr,
            'cc_logs' => $ccLogs,
            'goods' => $goods,
            'cars' => $cars,
        ]);

        $this->logAction('Просмотр заявки', 'access');

        return null; // стандартный рендер
    }

    public function getOldNewValuesAction($id)
    {
        $this->view->disable();
        $t = $this->translator;
        $cc_pr = ClientCorrectionProfile::findFirstById($id);
        $c_log = ClientCorrectionLogs::findFirst([
            "conditions" => "ccp_id = :ccp_id: AND type = :type:",
            "bind" => [
                "ccp_id" => $id,
                "type" => $cc_pr->type
            ],
            'order' => 'id DESC'
        ]);

        $html = NULL;
        $before = null;
        $after = null;

        if ($c_log->meta_before != NULL && $c_log->meta_before != '-') $before = json_decode($c_log->meta_before, true);
        if ($c_log->meta_after != NULL && $c_log->meta_after != '-') $after = json_decode($c_log->meta_after, true);

        $num_application = $t->_("num-application");
        $car_calculate_method = $t->_("car-calculate-method");
        $date_of_import = $t->_("date-of-import");
        $country_of_manufacture = $t->_("country");
        $amount = $t->_("amount");
        $goods_cost = $t->_("goods-cost");

        $before_dt_import = '';
        $country_name_before = '';
        $after_dt_import = '';
        $country_name_after = '';

        $calc_method_before = (
            isset($before[0]['calculate_method'])
            && $before[0]['calculate_method'] !== ''
            && array_key_exists($before[0]['calculate_method'], CALCULATE_METHODS)
        )
            ? CALCULATE_METHODS[$before[0]['calculate_method']]
            : '-';

        $calc_method_after = (
            isset($after[0]['calculate_method'])
            && $after[0]['calculate_method'] !== ''
            && array_key_exists($after[0]['calculate_method'], CALCULATE_METHODS)
        )
            ? CALCULATE_METHODS[$after[0]['calculate_method']]
            : '-';

        if ($calc_method_before != $calc_method_after) {
            $calc_method_before = '<del style="color:red;">' . $calc_method_before . '</del>';
            $calc_method_after = '<b style="color:green;">' . $calc_method_after . '</b>';
        }

        if ($before && $after) {
            if ($before[0]['date_import'] != $after[0]['date_import']) {
                $before_dt_import = (array_key_exists("date_import", $before[0]) && $before[0]['date_import'])
                    ? '<del style="color:red;">' . date('d.m.Y', $before[0]['date_import']) . '</del>'
                    : date('d.m.Y', $before[0]['date_import']);

                $after_dt_import = (array_key_exists("date_import", $after[0]) && $after[0]['date_import'])
                    ? '<b style="color:green;">' . date('d.m.Y', $after[0]['date_import']) . '</b>'
                    : date('d.m.Y', $after[0]['date_import']);
            } else {
                $before_dt_import = date('d.m.Y', $before[0]['date_import']);
                $after_dt_import = date('d.m.Y', $after[0]['date_import']);
            }

            if ($before[0]['ref_country'] != $after[0]['ref_country']) {
                $country_before = RefCountry::findFirstById($before[0]['ref_country']);
                $country_after = RefCountry::findFirstById($after[0]['ref_country']);

                $country_name_before = '<del style="color:red;">' . $country_before->name . '</del>';
                $country_name_after = '<b style="color:green;">' . $country_after->name . '</b>';
            } else {
                $country_before = RefCountry::findFirstById($before[0]['ref_country']);
                $country_after = RefCountry::findFirstById($after[0]['ref_country']);

                $country_name_before = $country_before->name;
                $country_name_after = $country_after->name;
            }
        }

        $initiator_name = '-';
        $initiator = RefInitiator::findFirstById($cc_pr->initiator_id);
        if ($initiator) {
            $initiator_name = $initiator->name;
        }

        if ($c_log->type == 'CAR') {

            $vin_code = $t->_("vin-code");
            $volume_cm = $t->_("volume-cm");
            $year_of_manufacture = $t->_("year-of-manufacture");
            $car_category = $t->_("car-category");
            $ref_st = $t->_("ref-st");
            $transport_type = $t->_("transport-type");
            $is_electric_car = $t->_("is_electric_car?");

            $c_type_before = RefCarType::findFirstById($before[0]['ref_car_type_id']);
            $c_type_after = RefCarType::findFirstById($after[0]['ref_car_type_id']);
            $car_type_before = $c_type_before->name;
            $car_type_after = $c_type_after->name;

            if ($before[0]['ref_car_type_id'] != $after[0]['ref_car_type_id']) {
                $car_type_before = '<del style="color:red;">' . $car_type_before . '</del>';
                $car_type_after = '<b style="color:green;">' . $car_type_after . '</b>';
            }

            $c_cat_before = RefCarCat::findFirstById($before[0]['ref_car_cat']);
            $c_cat_after = RefCarCat::findFirstById($after[0]['ref_car_cat']);
            $car_cat_before_name = $t->_($c_cat_before->name);
            $car_cat_after_name = $t->_($c_cat_after->name);

            if ($before[0]['ref_car_cat'] != $after[0]['ref_car_cat']) {
                $car_cat_before_name = '<del style="color:red;">' . $car_cat_before_name . '</del>';
                $car_cat_after_name = '<b style="color:green;">' . $car_cat_after_name . '</b>';
            }

            $is_electric_val_before = (array_key_exists('electric_car', $before[0])) ? $before[0]['electric_car'] : '-';
            $is_electric_val_after = (array_key_exists('electric_car', $after[0])) ? $after[0]['electric_car'] : '-';

            if ($is_electric_val_before != $is_electric_val_after) {
                $is_electric_before = ($is_electric_val_before == '-') ? '-' : '<del style="color:red;">' . $t->_("yesno-$is_electric_val_before") . '</del>';
                $is_electric_after = ($is_electric_val_after == '-') ? '-' : '<b style="color:green;">' . $t->_("yesno-$is_electric_val_after") . '</b>';
            } else {
                $is_electric_before = ($is_electric_val_before == '-') ? '-' : $t->_("yesno-$is_electric_val_before");
                $is_electric_after = ($is_electric_val_after == '-') ? '-' : $t->_("yesno-$is_electric_val_after");
            }

            $st_type_val_before = (array_key_exists('ref_st_type', $before[0])) ? $before[0]['ref_st_type'] : '-';
            $st_type_val_after = (array_key_exists('ref_st_type', $after[0])) ? $after[0]['ref_st_type'] : '-';

            if ($st_type_val_before != $st_type_val_after) {
                $st_type_before = '<del style="color:red;">' . REF_ST_TYPE[$st_type_val_before] . '</del>';
                $st_type_after = '<b style="color:green;">' . REF_ST_TYPE[$st_type_val_after] . '</b>';
            } else {
                $st_type_before = REF_ST_TYPE[$st_type_val_before];
                $st_type_after = REF_ST_TYPE[$st_type_val_after];
            }

            if ($before[0]['vin'] != $after[0]['vin']) {
                $vin_before = '<del style="color:red;">' . $before[0]['vin'] . '</del>';
                $vin_after = '<b style="color:green;">' . $after[0]['vin'] . '</b>';
            } else {
                $vin_before = $before[0]['vin'];
                $vin_after = $after[0]['vin'];
            }

            if ($before[0]['volume'] != $after[0]['volume']) {
                $volume_before = '<del style="color:red;">' . $before[0]['volume'] . '</del>';
                $volume_after = '<b style="color:green;">' . $after[0]['volume'] . '</b>';
            } else {
                $volume_before = $before[0]['volume'];
                $volume_after = $after[0]['volume'];
            }

            if ($before[0]['cost'] != $after[0]['cost']) {
                $amount_before = '<del style="color:red;">' . __money($before[0]['cost']) . '</del>';
                $amount_after = '<b style="color:green;">' . __money($after[0]['cost']) . '</b>';
            } else {
                $amount_before = __money($before[0]['cost']);
                $amount_after = __money($after[0]['cost']);
            }

            if ($before[0]['year'] != $after[0]['year']) {
                $year_before = '<del style="color:red;">' . $before[0]['year'] . '</del>';
                $year_after = '<b style="color:green;">' . $after[0]['year'] . '</b>';
            } else {
                $year_before = $before[0]['year'];
                $year_after = $after[0]['year'];
            }

            $html .= <<<CAR_T_BODY
        <tr>
          <td> $volume_cm </td><td> {$volume_before}  </td><td> {$volume_after}  </td>
        </tr>
        <tr>
          <td> $vin_code </td><td> {$vin_before} </td><td> {$vin_after}  </td>
        </tr>
         <tr>
          <td> $amount </td><td> {$amount_before} </td><td> {$amount_after}  </td>
        </tr>
        <tr>
          <td> $year_of_manufacture </td><td> {$year_before} </td><td> {$year_after} </td>
        </tr>
        <tr>
          <td> $date_of_import </td><td> {$before_dt_import} </td><td> {$after_dt_import} </td>
        </tr>
        <tr>
          <td> $country_of_manufacture </td><td> {$country_name_before} </td><td> {$country_name_after} </td>
        </tr>
        <tr>
          <td> $car_category </td><td> {$car_cat_before_name} </td><td> {$car_cat_after_name} </td>
        </tr>
        <tr>
          <td> $ref_st </td><td> {$st_type_before} </td><td> {$st_type_after} </td>
        </tr>
        <tr>
          <td> $transport_type </td><td> {$car_type_before} </td><td> {$car_type_after} </td>
        </tr>
        <tr>
          <td> $num_application </td><td> {$before[0]['profile_id']} </td><td> {$after[0]['profile_id']} </td>
          /tr>
        <tr>
          <td> $car_calculate_method </td><td> {$calc_method_before} </td><td> {$calc_method_after} </td>
        </tr>
        <tr>
          <td> $is_electric_car </td><td> {$is_electric_before} </td><td> {$is_electric_after} </td>
        </tr>
        <tr>
            <td>Инициатор: {$initiator_name}</td>
        </tr>
      CAR_T_BODY;
        } else {

            $package_weight = $t->_("package-weight");
            $package_cost = $t->_("package-cost");
            $goods_weight = $t->_("goods-weight");
            $basis_date = $t->_("basis-date");

            if ($before && $after) {
                if ($before[0]['ref_tn'] != $after[0]['ref_tn']) {
                    $tn_before = RefTnCode::findFirstById($before[0]['ref_tn']);
                    $tn_after = RefTnCode::findFirstById($after[0]['ref_tn']);

                    $tn_code_before = '<del style="color:red;">' . $tn_before->code . '</del>';
                    $tn_code_after = '<b style="color:green;">' . $tn_after->code . '</b>';
                } else {
                    $tn_before = RefTnCode::findFirstById($before[0]['ref_tn']);

                    $tn_code_before = $tn_before->code;
                    $tn_code_after = $tn_before->code;
                }

                $basis_date_before = (array_key_exists('basis_date', $before[0]) && $before[0]['basis_date'] > 0) ? date('d.m.Y', $before[0]['basis_date']) : '-';
                $basis_date_after = (array_key_exists('basis_date', $after[0]) && $after[0]['basis_date'] > 0) ? date('d.m.Y', $after[0]['basis_date']) : '-';

                if ($basis_date_before != $basis_date_after) {
                    $basis_date_before = '<del style="color:red;">' . $basis_date_before . '</del>';
                    $basis_date_after = '<b style="color:green;">' . $basis_date_after . '</b>';
                }

                $package_weight_before = (array_key_exists('package_weight', $before[0])) ? $before[0]['package_weight'] : '-';
                $package_weight_after = (array_key_exists('package_weight', $after[0])) ? $after[0]['package_weight'] : '-';

                if ($package_weight_before != $package_weight_after) {
                    $package_weight_before = '<del style="color:red;">' . $package_weight_before . '</del>';
                    $package_weight_after = '<b style="color:green;">' . $package_weight_after . '</b>';
                }

                $package_cost_before = (array_key_exists('package_cost', $before[0])) ? $before[0]['package_cost'] : '-';
                $package_cost_after = (array_key_exists('package_cost', $after[0])) ? $after[0]['package_cost'] : '-';

                if ($package_cost_before != $package_cost_after) {
                    $package_cost_before = '<del style="color:red;">' . $package_cost_before . '</del>';
                    $package_cost_after = '<b style="color:green;">' . $package_cost_after . '</b>';
                }

                $amount_before = ($before[0]['amount'] != $after[0]['amount']) ? '<del style="color:red;">' . __money($before[0]['amount']) . '</del>' : __money($before[0]['amount']);
                $amount_after = ($before[0]['amount'] != $after[0]['amount']) ? '<b style="color:green;">' . __money($after[0]['amount']) . '</b>' : __money($after[0]['amount']);

                $before_goods_cost = isset($before[0]['goods_cost']) ? $before[0]['goods_cost'] : null;
                $after_goods_cost = isset($after[0]['goods_cost']) ? $after[0]['goods_cost'] : null;

                if ($before_goods_cost !== null && $after_goods_cost !== null) {
                    $goods_cost_before = ($before_goods_cost != $after_goods_cost)
                        ? '<del style="color:red;">' . __money($before_goods_cost) . '</del>'
                        : __money($before_goods_cost);

                    $goods_cost_after = ($before_goods_cost != $after_goods_cost)
                        ? '<b style="color:green;">' . __money($after_goods_cost) . '</b>'
                        : __money($after_goods_cost);
                } else {
                    $goods_cost_before = isset($before_goods_cost) ? __money($before_goods_cost) : '—';
                    $goods_cost_after = isset($after_goods_cost) ? __money($after_goods_cost) : '—';
                }

                $weight_before = ($before[0]['weight'] != $after[0]['weight']) ? '<del style="color:red;">' . $before[0]['weight'] . '</del>' : $before[0]['weight'];
                $weight_after = ($before[0]['weight'] != $after[0]['weight']) ? '<b style="color:green;">' . $after[0]['weight'] . '</b>' : $after[0]['weight'];

                $basis_before = ($before[0]['basis'] != $after[0]['basis']) ? '<del style="color:red;">' . $before[0]['basis'] . '</del>' : $before[0]['basis'];
                $basis_after = ($before[0]['basis'] != $after[0]['basis']) ? '<b style="color:green;">' . $after[0]['basis'] . '</b>' : $after[0]['basis'];

                if ($c_log->action == 'CREATED') {
                    $html .= <<<T_BODY
          <tr>
            <td> Код ТНВЭД </td><td>_</td><td> {$tn_code_after}  </td>
          </tr>
          <tr>
            <td> $goods_weight </td><td>_</td><td> {$weight_after}  </td>
          </tr>
          <tr>
            <td> $amount </td><td>_</td><td> {$amount_after}  </td>
          </tr>
          <tr>
            <td> Номер счет-фактуры или ГТД </td><td>_</td><td> {$basis_after} </td>
          </tr>
          <tr>
            <td> $basis_date </td><td>_</td><td> {$basis_date_after} </td>
          </tr>
          <tr>
            <td> $date_of_import </td><td>_</td><td> {$after_dt_import} </td>
          </tr>
          <tr>
            <td> $country_of_manufacture </td><td>_</td><td> {$country_name_after} </td>
          </tr>
          <tr>
            <td> $num_application </td><td>_</td><td> {$after[0]['profile_id']} </td>
          </tr>
          <tr>
            <td> $package_weight </td><td>_</td><td> {$package_weight_after} </td>
          </tr>
          <tr>
            <td> $package_cost </td><td>_</td><td> {$package_cost_after} </td>
          </tr>
            <tr>
                <td> $goods_cost </td><td> {$goods_cost_before} </td><td> {$goods_cost_after}  </td>
            </tr>
          <tr>
            <td> $car_calculate_method </td><td>_</td><td> {$calc_method_after} </td>
          </tr>
        <tr>
            <td>Инициатор: {$initiator_name}</td>
        </tr>
        T_BODY;
                } else {
                    $html .= <<<T_BODY
          <tr>
            <td> Код ТНВЭД </td><td> {$tn_code_before}  </td><td> {$tn_code_after}  </td>
          </tr>
          <tr>
            <td> $goods_weight </td><td> {$weight_before} </td><td> {$weight_after}  </td>
          </tr>
          <tr>
            <td> $amount </td><td> {$amount_before} </td><td> {$amount_after}  </td>
          </tr>
          <tr>
            <td> Номер счет-фактуры или ГТД </td><td> {$basis_before} </td><td> {$basis_after} </td>
          </tr>
          <tr>
            <td> $basis_date </td><td> {$basis_date_before} </td><td> {$basis_date_after} </td>
          </tr>
          <tr>
            <td> $date_of_import </td><td> {$before_dt_import} </td><td> {$after_dt_import} </td>
          </tr>
          <tr>
            <td> $country_of_manufacture </td><td> {$country_name_before} </td><td> {$country_name_after} </td>
          </tr>
          <tr>
            <td> $num_application </td><td> {$before[0]['profile_id']} </td><td> {$after[0]['profile_id']} </td>
          </tr>
          <tr>
            <td> $package_weight </td><td> {$package_weight_before} </td><td> {$package_weight_after} </td>
          </tr>
          <tr>
            <td> $package_cost </td><td> {$package_cost_before} </td><td> {$package_cost_after} </td>
          </tr>
                    <tr>
          <td> $goods_cost </td><td> {$goods_cost_before} </td><td> {$goods_cost_after}  </td>
            </tr>
          <tr>
            <td> $car_calculate_method </td><td> {$calc_method_before} </td><td> {$calc_method_after} </td>
          </tr>
        <tr>
            <td>Инициатор: {$initiator_name}</td>
        </tr>
        T_BODY;
                }
            }
        }

        echo $html;
    }

    /**
     * @throws AppException
     */
    public function signAction()
    {
        $auth = User::getUserBySession();
        $__settings = $this->session->get("__settings");
        $filename = '';

        if ($this->request->isPost()) {

            if (!$auth->isSuperModerator()) {
                $this->logAction('Вы не имеете права совершать это действия.');
                $message = "У вас нет прав на это действие!.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/correction_request/index/");
            } else {
                $id = $this->request->getPost("ccp_id");
                $hash = $this->request->getPost("hash");
                $sign = $this->request->getPost("sign");
                $comment = $this->request->getPost("comment");

                $cc_profile = ClientCorrectionProfile::findFirstById($id);

                $cmsService = new CmsService();
                $result = $cmsService->check($hash, $sign);
                $j = $result['data'];
                $sign = $j['sign'];
                if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                    if ($result['success'] === true) {

                        // удаляем СВУП архив(svup_XXXX.zip) если есть
                        if ($cf = __checkSVUPZip($cc_profile->profile_id)) {
                            $svup_path = APP_PATH . '/private';
                            $svup_dir = $cf['cert_dir'];
                            $svup_file = $cf['file'];

                            exec("rm -rf $svup_path/$svup_dir/$svup_file");
                        }

                        $ccf = ClientCorrectionFile::find([
                            'conditions' => 'profile_id = :pid: AND ccp_id = :ccp_id:',
                            'bind' => [
                                'pid' => $cc_profile->profile_id,
                                'ccp_id' => $cc_profile->id
                            ]
                        ]);

                        if (!$this->handleCorrectionFilesUpload($cc_profile, $auth, $id)) {
                            return $this->response->redirect("/correction_request/view/$cc_profile->profile_id/$id");
                        }
                        
                        $uploadedFiles = $this->request->getUploadedFiles();
                        foreach ($uploadedFiles as $file) {
                            $filename = $file->getName();
                        }

                        if ($cc_profile->type == 'CAR') {
                            $cc_car = ClientCorrectionCars::findFirstByCcpId($cc_profile->id);
                            $car = Car::findFirstById($cc_profile->object_id);
                            $old_vin = $car->vin;
                            $vin_before = "car:$car->vin, car_id:$car->id, car_profile_id:$car->profile_id";
                            $vin_after = NULL;
                            $c_before = json_encode(array($car));
                            $c_c_before = json_encode(array($cc_car));

                            // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                            if ($cc_car && $car && $car->cost != $cc_car->cost) {
                                $cc_profile->action_dt = time();
                                $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                                $cc_profile->moderator_id = $auth->id;
                                $cc_profile->sign = $sign;
                                $cc_profile->hash = $hash;

                                if ($cc_profile->save()) {
                                    $l = new ClientCorrectionLogs();
                                    $l->iin = $auth->idnum;
                                    $l->type = 'CAR';
                                    $l->user_id = $auth->id;
                                    $l->action = 'SENT_TO_ACCOUNTANT';
                                    $l->object_id = $cc_car->car_id;
                                    $l->ccp_id = $cc_profile->id;
                                    $l->dt = time();
                                    $l->meta_before = $c_before;
                                    $l->meta_after = $c_c_before;
                                    $l->comment = $comment;
                                    $l->file = $filename;
                                    $l->hash = $hash;
                                    $l->sign = $sign;
                                    $l->save();

                                    $message = "Заявка на корректировку отправлена к бухгалтеру.";
                                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                    $this->logAction($logString);
                                    $this->flash->success($message);
                                    return $this->response->redirect("/correction_request/index/");
                                }
                            }

                            if ($cc_profile->action == 'CORRECTION' || $cc_profile->action == NULL) {
                                $check_vin = Car::findFirstByVin($cc_car->vin);
                                if ($car->vin != $cc_car->vin && $check_vin) {
                                    $this->flash->error("VIN $cc_car->vin уже был представлен в заявке №" . $check_vin->profile_id . ".");
                                    return $this->response->redirect("/correction_request/index/");
                                } else {

                                    $cc_profile->action_dt = time();
                                    $cc_profile->status = 'APPROVED_BY_MODERATOR';
                                    $cc_profile->moderator_id = $auth->id;
                                    $cc_profile->sign = $sign;
                                    $cc_profile->hash = $hash;
                                    $cc_profile->save();

                                    if (file_exists(APP_PATH . '/private/certificates/' . $cc_car->vin . '.pdf')) {
                                        exec('mv "' . APP_PATH . '"/private/certificates/"' . $cc_car->vin . '".pdf "' . APP_PATH . '"/private/certificates/corrected/"' . $cc_car->vin . '"_"' . time() . '".pdf ');
                                    }

                                    if (file_exists(APP_PATH . '/private/certificates_zd/' . $cc_car->vin . '.pdf')) {
                                        exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $cc_car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/"' . $cc_car->vin . '"_"' . time() . '".pdf ');
                                    }

                                    $tr = Transaction::findFirstByProfileId($car->profile_id);
                                    $tr->amount = $tr->amount - $car->cost;

                                    $car->volume = $cc_car->volume;
                                    $car->vin = $cc_car->vin;
                                    $car->year = $cc_car->year;
                                    $car->date_import = $cc_car->date_import;
                                    $car->profile_id = $cc_car->profile_id;
                                    $car->ref_car_cat = $cc_car->ref_car_cat;
                                    $car->ref_car_type_id = $cc_car->ref_car_type_id;
                                    $car->ref_country = $cc_car->ref_country;
                                    $car->ref_st_type = $cc_car->ref_st_type;
                                    $car->calculate_method = $cc_car->calculate_method;
                                    $car->electric_car = $cc_car->electric_car;
                                    $car->cost = $cc_car->cost;
                                    $car->vehicle_type = $cc_car->vehicle_type;
                                    $car->updated = time();

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
                                                    $f_l->comment = $comment;
                                                    $f_l->file = $filename;
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
                                            $vin_before = NULL;
                                        }
                                    }

                                    $tr->amount = $tr->amount + $cc_car->cost;
                                    $tr->save();

                                    $c_l = new CorrectionLogs();
                                    $c_l->iin = $auth->idnum;
                                    $c_l->type = 'CAR';
                                    $c_l->user_id = $auth->id;
                                    $c_l->profile_id = $cc_car ? $cc_car->profile_id : null;
                                    $c_l->action = 'CORRECTION_APPROVED';
                                    $c_l->object_id = $car ? $car->id : null;
                                    $c_l->dt = time();
                                    $c_l->meta_before = $c_before;
                                    $c_l->meta_after = $c_c_before;
                                    $c_l->vin_before = $vin_before;
                                    $c_l->vin_after = $vin_after;
                                    $c_l->comment = $comment;
                                    $c_l->file = $filename;
                                    $c_l->sign = $sign;
                                    $c_l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;
                                    $c_l->save();
                                    $logString = json_encode($c_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                    $this->logAction($logString);

                                    $l = new ClientCorrectionLogs();
                                    $l->iin = $auth->idnum;
                                    $l->type = 'CAR';
                                    $l->user_id = $auth->id;
                                    $l->action = 'APPROVED_BY_MODERATOR';
                                    $l->object_id = $cc_car->car_id;
                                    $l->ccp_id = $cc_profile->id;
                                    $l->dt = time();
                                    $l->meta_before = $c_before;
                                    $l->meta_after = $c_c_before;
                                    $l->comment = $comment;
                                    $l->file = $filename;
                                    $l->hash = $hash;
                                    $l->sign = $sign;
                                    $l->save();
                                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                    $this->logAction($logString);

                                    $profileFiles = File::find([
                                        'conditions' => 'profile_id = :pid: AND type IN ({types:array})',
                                        'bind' => [
                                            'pid' => $cc_profile->profile_id,
                                            'types' => ['app_correction', 'other']
                                        ]
                                    ]);

                                    foreach ($profileFiles as $profileFile) {
                                        $ccf_by_profile = ClientCorrectionFile::findFirst([
                                            'conditions' => 'profile_id = :pid: AND original_name = :original_name:',
                                            'bind' => [
                                                'pid' => $cc_profile->profile_id,
                                                'original_name' => $profileFile->original_name,
                                            ]
                                        ]);
                                        if ($ccf_by_profile && $ccf_by_profile->original_name == $profileFile->original_name) {
                                            $profileFile->delete();
                                        }
                                    }

                                    foreach ($ccf as $f) {
                                        $user = User::findFirstById($f->user_id);
                                        if ($user->role->name == 'client') {
                                            $pfile = new File();
                                            $pfile->profile_id = $cc_profile->profile_id;
                                            $pfile->type = $f->type;
                                            $pfile->original_name = $f->original_name;
                                            $pfile->ext = $f->ext;
                                            $pfile->visible = 1;

                                            $pfile->save();
                                            copy(APP_PATH . "/private/client_correction_docs/" . $f->original_name, APP_PATH . "/private/docs/" . $pfile->id . '.' . $pfile->ext);
                                        }
                                    }

                                    $message = "Заявка на корректирование успешна одобрена.";
                                    $this->logAction($message);
                                    $this->flash->success($message);
                                    return $this->response->redirect("/correction_request/index/");
                                }

                            } elseif ($cc_profile->action == 'ANNULMENT') {

                                $cc_profile->action_dt = time();
                                $cc_profile->status = 'APPROVED_BY_MODERATOR';
                                $cc_profile->moderator_id = $auth->id;
                                $cc_profile->sign = $sign;
                                $cc_profile->hash = $hash;
                                $cc_profile->save();

                                if (file_exists(APP_PATH . '/private/certificates/' . $car->vin . '.pdf')) {
                                    exec('mv "' . APP_PATH . '"/private/certificates/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates/cancelled/"' . $car->vin . '"_"' . time() . '".pdf ');
                                }

                                if (file_exists(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
                                    exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/cancelled/"' . $car->vin . '"_"' . time() . '".pdf ');
                                }

                                $tr = Transaction::findFirstByProfileId($car->profile_id);
                                $tr->amount = $tr->amount - $car->cost;

                                if ($tr->save()) {
                                    $car->cost = 0.00;
                                    $car->status = "CANCELLED";
                                    $car->vin = $car->vin . "__ANNULMENT";
                                    $car->save();
                                }

                                $l = new CorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'CAR';
                                $l->user_id = $auth->id;
                                $l->profile_id = $car ? $car->profile_id : null;
                                $l->action = 'ANNULMENT';
                                $l->object_id = $car ? $car->id : null;
                                $l->dt = time();
                                $l->meta_before = $c_before;
                                $l->meta_after = $car ? json_encode(array($car)) : json_encode(array());
                                $l->comment = $comment;
                                $l->file = $filename;
                                $l->sign = $sign;
                                $l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $ccl = new ClientCorrectionLogs();
                                $ccl->iin = $auth->idnum;
                                $ccl->type = 'CAR';
                                $ccl->user_id = $auth->id;
                                $ccl->action = 'APPROVED_BY_MODERATOR';
                                $ccl->object_id = $car->id;
                                $ccl->ccp_id = $cc_profile->id;
                                $ccl->dt = time();
                                $ccl->meta_before = $c_before;
                                $ccl->meta_after = "ДПП Ауннулирован!";
                                $ccl->comment = $comment;
                                $ccl->file = $filename;
                                $ccl->hash = $hash;
                                $ccl->sign = $sign;
                                $ccl->save();

                                $logString = json_encode($ccl->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $profileFiles = File::find([
                                    'conditions' => 'profile_id = :pid: AND type IN ({types:array})',
                                    'bind' => [
                                        'pid' => $cc_profile->profile_id,
                                        'types' => ['app_correction', 'other']
                                    ]
                                ]);

                                foreach ($profileFiles as $profileFile) {
                                    $ccf_by_profile = ClientCorrectionFile::findFirst([
                                        'conditions' => 'profile_id = :pid: AND original_name = :original_name:',
                                        'bind' => [
                                            'pid' => $cc_profile->profile_id,
                                            'original_name' => $profileFile->original_name,
                                        ]
                                    ]);
                                    if ($ccf_by_profile && $ccf_by_profile->original_name == $profileFile->original_name) {
                                        $profileFile->delete();
                                    }
                                }

                                foreach ($ccf as $f) {
                                    $user = User::findFirstById($f->user_id);
                                    if ($user && $user->role->name == 'client') {
                                        $pfile = new File();
                                        $pfile->profile_id = $cc_profile->profile_id;
                                        $pfile->type = $f->type;
                                        $pfile->original_name = $f->original_name;
                                        $pfile->ext = $f->ext;
                                        $pfile->visible = 1;

                                        $pfile->save();
                                        copy(APP_PATH . "/private/client_correction_docs/" . $f->original_name, APP_PATH . "/private/docs/" . $pfile->id . '.' . $pfile->ext);
                                    }
                                }

                                $message = "Заявка на аннулирование успешна одобрена.";
                                $this->logAction($message);
                                $this->flash->success($message);
                                return $this->response->redirect("/correction_request/index/");

                            }
                        } else {

                            $cc_good = ClientCorrectionGoods::findFirstByCcpId($cc_profile->id);
                            $good = Goods::findFirstById($cc_good->good_id);
                            $c_before = json_encode(array($good));
                            $c_c_before = json_encode(array($cc_good));

                            // если сумма изменилась (поле платеж), то отображать заявку пользователям с ролью бухгалтер.
                            if ($cc_good->amount != $good->amount) {
                                $cc_profile->action_dt = time();
                                $cc_profile->status = 'SENT_TO_ACCOUNTANT';
                                $cc_profile->moderator_id = $auth->id;
                                $cc_profile->sign = $sign;
                                $cc_profile->hash = $hash;

                                if ($cc_profile->save()) {
                                    $l = new ClientCorrectionLogs();
                                    $l->iin = $auth->idnum;
                                    $l->type = 'GOODS';
                                    $l->user_id = $auth->id;
                                    $l->action = 'SENT_TO_ACCOUNTANT';
                                    $l->object_id = $cc_good->good_id;
                                    $l->ccp_id = $cc_profile->id;
                                    $l->dt = time();
                                    $l->meta_before = $c_before;
                                    $l->meta_after = $c_c_before;
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

                            $cc_profile->action_dt = time();
                            $cc_profile->status = 'APPROVED_BY_MODERATOR';
                            $cc_profile->moderator_id = $auth->id;
                            $cc_profile->sign = $sign;
                            $cc_profile->hash = $hash;
                            $cc_profile->save();

                            if (file_exists(APP_PATH . '/private/certificates/certificates_' . $cc_profile->profile_id . '.zip')) {
                                exec('mv "' . APP_PATH . '"/private/certificates/certificates_"' . $cc_profile->profile_id . '".zip "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_profile->profile_id . '"_"' . time() . '".zip ');
                            }

                            if (file_exists(APP_PATH . '/private/certificates_zd/certificates_' . $cc_profile->profile_id . '.zip')) {
                                exec('mv "' . APP_PATH . '"/private/certificates_zd/certificates_"' . $cc_profile->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_profile->profile_id . '"_"' . time() . '".zip ');
                            }

                            if ($cc_profile->action == 'CORRECTION') {

                                if (file_exists(APP_PATH . '/private/certificates/goods_' . $cc_good->good_id . '.pdf')) {
                                    exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                                }

                                if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $cc_good->good_id . '.pdf')) {
                                    exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                                }

                                $tr = Transaction::findFirstByProfileId($good->profile_id);
                                $tr->amount = $tr->amount - $good->amount;

                                $good->weight = $cc_good->weight;
                                $good->basis = $cc_good->basis;
                                $good->ref_tn = $cc_good->ref_tn;
                                $good->date_import = $cc_good->date_import;
                                $good->profile_id = $cc_good->profile_id;
                                $good->price = $cc_good->price;
                                $good->amount = $cc_good->amount;
                                $good->goods_cost = $cc_good->goods_cost;
                                $good->ref_country = $cc_good->ref_country;
                                $good->ref_tn_add = $cc_good->ref_tn_add;
                                $good->goods_type = $cc_good->goods_type;
                                $good->up_type = $cc_good->up_type;
                                $good->date_report = $cc_good->date_report;
                                $good->basis_date = $cc_good->basis_date;
                                $good->package_weight = $cc_good->package_weight;
                                $good->package_cost = $cc_good->package_cost;
                                $good->calculate_method = $cc_good->calculate_method;
                                $good->status = $cc_good->status;
                                $good->updated = time();
                                $good->save();
                                if (!$good->save()) {
                                    $this->logAction("Ошибка сохранения goods: " . json_encode($good->getMessages()));
                                }

                                $tr->amount = $tr->amount + $cc_good->amount;
                                $tr->save();

                                // логгирование
                                $c_l = new CorrectionLogs();
                                $c_l->iin = $auth->idnum;
                                $c_l->type = 'GOODS';
                                $c_l->user_id = $auth->id;
                                $c_l->profile_id = $cc_good ? $cc_good->profile_id : null;
                                $c_l->action = 'CORRECTION_APPROVED';
                                $c_l->object_id = $good->id;
                                $c_l->dt = time();
                                $c_l->meta_before = $c_before;
                                $c_l->meta_after = $c_c_before;
                                $c_l->comment = $comment;
                                $c_l->file = $filename;
                                $c_l->sign = $sign;
                                $c_l->initiator_id = $cc_profile->initiator_id;
                                if (!$c_l->save()) {
                                    $this->logAction("Ошибка сохранения CorrectionLogs: " . json_encode($c_l->getMessages()));
                                }

                                // логгирование
                                $l = new ClientCorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'GOODS';
                                $l->user_id = $auth->id;
                                $l->action = 'APPROVED_BY_MODERATOR';
                                $l->object_id = $cc_good->good_id;
                                $l->ccp_id = $cc_profile->id;
                                $l->dt = time();
                                $l->meta_before = $c_before;
                                $l->meta_after = $c_c_before;
                                $l->comment = $comment;
                                $l->file = $filename;
                                $l->hash = $hash;
                                $l->sign = $sign;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                if (!$l->save()) {
                                    $this->logAction("Ошибка сохранения ClientCorrectionLogs: " . json_encode($l->getMessages()));
                                }

                                $profileFiles = File::find([
                                    'conditions' => 'profile_id = :pid: AND type IN ({types:array})',
                                    'bind' => [
                                        'pid' => $cc_profile->profile_id,
                                        'types' => ['app_correction', 'other']
                                    ]
                                ]);

                                foreach ($profileFiles as $profileFile) {
                                    $ccf_by_profile = ClientCorrectionFile::findFirst([
                                        'conditions' => 'profile_id = :pid: AND original_name = :original_name:',
                                        'bind' => [
                                            'pid' => $cc_profile->profile_id,
                                            'original_name' => $profileFile->original_name,
                                        ]
                                    ]);
                                    if ($ccf_by_profile && $ccf_by_profile->original_name == $profileFile->original_name) {
                                        $profileFile->delete();
                                    }
                                }

                                foreach ($ccf as $f) {
                                    $user = User::findFirstById($f->user_id);
                                    if ($user->role->name == 'client') {
                                        $pfile = new File();
                                        $pfile->profile_id = $cc_profile->profile_id;
                                        $pfile->type = $f->type;
                                        $pfile->original_name = $f->original_name;
                                        $pfile->ext = $f->ext;
                                        $pfile->visible = 1;

                                        $pfile->save();
                                        copy(APP_PATH . "/private/client_correction_docs/" . $f->original_name, APP_PATH . "/private/docs/" . $pfile->id . '.' . $pfile->ext);
                                    }
                                }

                                $message = "Заявка на корректтировку успешна одобрена.";
                                $this->logAction($message);
                                $this->flash->success($message);
                                return $this->response->redirect("/correction_request/index/");

                            } elseif ($cc_profile->action == 'ANNULMENT') {

                                // update table correction_car_by_client
                                $cc_profile->action_dt = time();
                                $cc_profile->status = 'APPROVED_BY_MODERATOR';
                                $cc_profile->moderator_id = $auth->id;
                                $cc_profile->sign = $sign;
                                $cc_profile->hash = $hash;
                                $cc_profile->save();

                                $goods = Goods::find(array(
                                    'profile_id = :pid:',
                                    'bind' => array(
                                        'pid' => $cc_profile->profile_id,
                                    ),
                                    "order" => "id ASC"
                                ));

                                $id_list = array();
                                foreach ($goods as $key => $g) {
                                    if ($g->status == "DELETED") continue;

                                    if (file_exists(APP_PATH . '/private/certificates/goods_' . $cc_good->good_id . '.pdf')) {
                                        exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                                    }

                                    if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $cc_good->good_id . '.pdf')) {
                                        exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                                    }

                                    $id_list[] = $g->id;

                                    $good = Goods::findFirstById($g->id);
                                    $_before = json_encode(array($good));

                                    $profile = Profile::findFirstById($good->profile_id);

                                    $tr = Transaction::findFirstByProfileId($profile->id);
                                    $tr->amount = $tr->amount - $good->amount;

                                    if ($tr->save()) {
                                        $good->amount = 0.00;
                                        $good->status = "CANCELLED";
                                        $good->save();
                                    }

                                    $l = new CorrectionLogs();
                                    $l->iin = $auth->idnum;
                                    $l->type = 'GOODS';
                                    $l->user_id = $auth->id;
                                    $l->profile_id = $good ? $good->profile_id : null;
                                    $l->action = 'ANNULMENT';
                                    $l->object_id = $g ? $g->id : null;
                                    $l->dt = time();
                                    $l->meta_before = $_before;
                                    $l->meta_after = $good ? json_encode(array($good)) : json_encode(array());
                                    $l->comment = $comment;
                                    $l->file = $filename;
                                    $l->sign = $sign;
                                    $l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;

                                    if ($good->save()) $l->save();
                                }

                                // логгирование
                                $c_l = new ClientCorrectionLogs();
                                $c_l->iin = $auth->idnum;
                                $c_l->type = 'GOODS';
                                $c_l->user_id = $auth->id;
                                $c_l->action = 'APPROVED_BY_MODERATOR';
                                $c_l->object_id = $cc_profile->profile_id;
                                $c_l->ccp_id = $cc_profile->id;
                                $c_l->dt = time();
                                $c_l->meta_before = json_encode($id_list);
                                $c_l->meta_after = "ДПП аннулирован!";
                                $c_l->comment = $comment;
                                $c_l->file = $filename;
                                $c_l->hash = $hash;
                                $c_l->sign = $sign;
                                $c_l->save();

                                $logString = json_encode($c_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                $message = "ДПП по заявке " . $cc_good->profile_id . " аннулирован";
                                $this->flash->success($message);
                                return $this->response->redirect("/correction_request/index/");
                            }
                        }

                    } else {
                        $message = "Подпись не прошла проверку!";
                        $this->logAction($message, 'security', 'ALERT');
                        $this->flash->error($message);
                        return $this->response->redirect("/correction_request/index/");
                    }
                } else {
                    $message = "Вы используете несоответствующую профилю подпись.";
                    $this->logAction($message, 'security', 'ALERT');
                    $this->flash->error($message);
                    return $this->response->redirect("/correction_request/index/");
                }
            }
        }
    }

    public function accountantSignAction()
    {
        $auth = User::getUserBySession();

        if (!$auth->isAccountant()) {
            $message = "Вы не имеете права совершать это действия.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            $this->response->redirect("/correction_request/index/");
        }

        if ($this->request->isPost()) {

            $id = $this->request->getPost("ccp_id");
            $comment = $this->request->getPost("comment");

            $cc_profile = ClientCorrectionProfile::findFirstById($id);
            $sign = $cc_profile->sign;
            $hash = $cc_profile->hash;

            // удаляем СВУП архив(svup_XXXX.zip) если есть
            if ($cf = __checkSVUPZip($cc_profile->profile_id)) {
                $svup_path = APP_PATH . '/private';
                $svup_dir = $cf['cert_dir'];
                $svup_file = $cf['file'];

                exec("rm -rf $svup_path/$svup_dir/$svup_file");
            }

            if ($cc_profile->type == 'CAR') {
                $cc_car = ClientCorrectionCars::findFirstByCcpId($cc_profile->id);
                $car = Car::findFirstById($cc_profile->object_id);
                $old_vin = $car->vin;
                $vin_before = "car:$car->vin, car_id:$car->id, car_profile_id:$car->profile_id";
                $vin_after = NULL;
                $c_before = json_encode(array($car));
                $c_c_before = json_encode(array($cc_car));

                if ($cc_profile->action == 'CORRECTION' || $cc_profile->action == NULL) {
                    $check_vin = Car::findFirstByVin($cc_car->vin);
                    if ($car->vin != $cc_car->vin && $check_vin) {
                        $this->flash->error("VIN $cc_car->vin уже был представлен в заявке №" . $check_vin->profile_id . ".");
                        return $this->response->redirect("/correction_request/index/");
                    } else {

                        $cc_profile->action_dt = time();
                        $cc_profile->status = 'APPROVED_BY_MODERATOR';
                        $cc_profile->accountant_id = $auth->id;
                        $cc_profile->sign = $sign;
                        $cc_profile->hash = $hash;
                        $cc_profile->save();

                        if (file_exists(APP_PATH . '/private/certificates/' . $cc_car->vin . '.pdf')) {
                            exec('mv "' . APP_PATH . '"/private/certificates/"' . $cc_car->vin . '".pdf "' . APP_PATH . '"/private/certificates/corrected/"' . $cc_car->vin . '"_"' . time() . '".pdf ');
                        }

                        if (file_exists(APP_PATH . '/private/certificates_zd/' . $cc_car->vin . '.pdf')) {
                            exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $cc_car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/"' . $cc_car->vin . '"_"' . time() . '".pdf ');
                        }

                        $tr = Transaction::findFirstByProfileId($car->profile_id);
                        $tr->amount = $tr->amount - $car->cost;

                        $car->volume = $cc_car->volume;
                        $car->vin = $cc_car->vin;
                        $car->year = $cc_car->year;
                        $car->date_import = $cc_car->date_import;
                        $car->profile_id = $cc_car->profile_id;
                        $car->ref_car_cat = $cc_car->ref_car_cat;
                        $car->ref_car_type_id = $cc_car->ref_car_type_id;
                        $car->ref_country = $cc_car->ref_country;
                        $car->ref_st_type = $cc_car->ref_st_type;
                        $car->calculate_method = $cc_car->calculate_method;
                        $car->electric_car = $cc_car->electric_car;
                        $car->cost = $cc_car->cost;
                        $car->updated = time();

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
                                        $f_l->comment = $comment;
                                        $f_l->file = NULL;
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
                                $vin_before = NULL;
                            }
                        }

                        $tr->amount = $tr->amount + $cc_car->cost;
                        $tr->save();

                        $c_l = new CorrectionLogs();
                        $c_l->iin = $auth->idnum;
                        $c_l->type = 'CAR';
                        $c_l->user_id = $auth->id;
                        $c_l->accountant_id = $auth->id;
                        $c_l->profile_id = $cc_car ? $cc_car->profile_id : null;
                        $c_l->action = 'CORRECTION_APPROVED';
                        $c_l->object_id = $car ? $car->id : null;
                        $c_l->dt = time();
                        $c_l->meta_before = $c_before;
                        $c_l->meta_after = $c_c_before;
                        $c_l->vin_before = $vin_before;
                        $c_l->vin_after = $vin_after;
                        $c_l->comment = $comment;
                        $c_l->file = NULL;
                        $c_l->sign = $sign;
                        $c_l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;
                        if (!$c_l->save()) {
                            $this->logAction("Ошибка сохранения CorrectionLogs: " . json_encode($c_l->getMessages()));
                        }

                        $logString = json_encode($c_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        $l = new ClientCorrectionLogs();
                        $l->iin = $auth->idnum;
                        $l->type = 'CAR';
                        $l->user_id = $auth->id;
                        $l->action = 'APPROVED_BY_MODERATOR';
                        $l->object_id = $cc_car->car_id;
                        $l->ccp_id = $cc_profile->id;
                        $l->dt = time();
                        $l->meta_before = $c_before;
                        $l->meta_after = $c_c_before;
                        $l->comment = $comment;
                        $l->file = NULL;
                        $l->hash = $hash;
                        $l->sign = $sign;
                        if (!$l->save()) {
                            $this->logAction("Ошибка сохранения ClientCorrectionLogs: " . json_encode($l->getMessages()));
                        }

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        $ccf = ClientCorrectionFile::find([
                            'conditions' => 'profile_id = :pid: AND ccp_id = :ccp_id:',
                            'bind' => [
                                'pid' => $cc_profile->profile_id,
                                'ccp_id' => $cc_profile->id
                            ]
                        ]);

                        $profileFiles = File::find([
                            'conditions' => 'profile_id = :pid: AND type IN ({types:array})',
                            'bind' => [
                                'pid' => $cc_profile->profile_id,
                                'types' => ['app_correction', 'other']
                            ]
                        ]);

                        foreach ($profileFiles as $profileFile) {
                            $ccf_by_profile = ClientCorrectionFile::findFirst([
                                'conditions' => 'profile_id = :pid: AND original_name = :original_name:',
                                'bind' => [
                                    'pid' => $cc_profile->profile_id,
                                    'original_name' => $profileFile->original_name,
                                ]
                            ]);
                            if ($ccf_by_profile && $ccf_by_profile->original_name == $profileFile->original_name) {
                                $profileFile->delete();
                            }
                        }

                        foreach ($ccf as $f) {
                            $user = User::findFirstById($f->user_id);
                            if ($user->role->name == 'client') {
                                $pfile = new File();
                                $pfile->profile_id = $cc_profile->profile_id;
                                $pfile->type = $f->type;
                                $pfile->original_name = $f->original_name;
                                $pfile->ext = $f->ext;
                                $pfile->visible = 1;

                                $pfile->save();
                                copy(APP_PATH . "/private/client_correction_docs/" . $f->original_name, APP_PATH . "/private/docs/" . $pfile->id . '.' . $pfile->ext);
                            }
                        }

                        $message = "Заявка на корректирование успешна одобрена.";
                        $this->flash->success($message);
                        return $this->response->redirect("/correction_request/index/");
                    }
                } elseif ($cc_profile->action == 'ANNULMENT') {
                    $cc_profile->action_dt = time();
                    $cc_profile->status = 'APPROVED_BY_MODERATOR';
                    $cc_profile->accountant_id = $auth->id;
                    $cc_profile->sign = $sign;
                    $cc_profile->hash = $hash;
                    $cc_profile->save();

                    if (file_exists(APP_PATH . '/private/certificates/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates/cancelled/"' . $car->vin . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/"' . $car->vin . '".pdf "' . APP_PATH . '"/private/certificates_zd/cancelled/"' . $car->vin . '"_"' . time() . '".pdf ');
                    }

                    $tr = Transaction::findFirstByProfileId($car->profile_id);
                    $tr->amount = $tr->amount - $car->cost;

                    if ($tr->save()) {
                        $car->cost = 0.00;
                        $car->status = "CANCELLED";
                        $car->vin = $car->vin . "__ANNULMENT";
                        if (!$car->save()) {
                            $this->logAction("Ошибка сохранения car: " . json_encode($car->getMessages()));
                        }
                    }

                    $l = new CorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'CAR';
                    $l->user_id = $auth->id;
                    $l->accountant_id = $auth->id;
                    $l->profile_id = $car ? $car->profile_id : null;
                    $l->action = 'ANNULMENT';
                    $l->object_id = $car ? $car->id : null;
                    $l->dt = time();
                    $l->meta_before = $c_before;
                    $l->meta_after = $car ? json_encode(array($car)) : json_encode(array());
                    $l->comment = $comment;
                    $l->file = NULL;
                    $l->sign = $sign;
                    $l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;
                    $l->save();
                    if (!$l->save()) {
                        $this->logAction("Ошибка сохранения CorrectionLogs: " . json_encode($l->getMessages()));
                    }

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    $ccl = new ClientCorrectionLogs();
                    $ccl->iin = $auth->idnum;
                    $ccl->type = 'CAR';
                    $ccl->user_id = $auth->id;
                    $ccl->action = 'APPROVED_BY_MODERATOR';
                    $ccl->object_id = $car->id;
                    $ccl->ccp_id = $cc_profile->id;
                    $ccl->dt = time();
                    $ccl->meta_before = $c_before;
                    $ccl->meta_after = "ДПП Ауннулирован!";
                    $ccl->comment = $comment;
                    $ccl->file = NULL;
                    $ccl->hash = $hash;
                    $ccl->sign = $sign;
                    if (!$ccl->save()) {
                        $this->logAction("Ошибка сохранения ClientCorrectionLogs: " . json_encode($ccl->getMessages()));
                    }

                    $logString = json_encode($ccl->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    __carRecalc($car->profile_id);

                    $this->flash->success("Заявка на аннулирование успешна одобрена.");
                    return $this->response->redirect("/correction_request/index/");
                }
            } else {

                if (file_exists(APP_PATH . '/private/certificates/certificates_' . $cc_profile->profile_id . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates/certificates_"' . $cc_profile->profile_id . '".zip "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_profile->profile_id . '"_"' . time() . '".zip ');
                }

                if (file_exists(APP_PATH . '/private/certificates_zd/certificates_' . $cc_profile->profile_id . '.zip')) {
                    exec('mv "' . APP_PATH . '"/private/certificates_zd/certificates_"' . $cc_profile->profile_id . '".zip "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_profile->profile_id . '"_"' . time() . '".zip ');
                }

                if ($cc_profile->action == 'CORRECTION' || $cc_profile->action == 'DELETED' || $cc_profile->action == 'CREATED') {

                    $cc_good = ClientCorrectionGoods::findFirstByCcpId($cc_profile->id);

                    if ($cc_profile->action == 'CREATED') {
                        $good = new Goods();
                    } else {
                        $good = Goods::findFirstById($cc_good->good_id);
                    }

                    $c_before = json_encode(array($good));
                    $c_c_before = json_encode(array($cc_good));

                    if (file_exists(APP_PATH . '/private/certificates/goods_' . $cc_good->good_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                    }

                    if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $cc_good->good_id . '.pdf')) {
                        exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                    }

                    $cc_profile->action_dt = time();
                    $cc_profile->status = 'APPROVED_BY_MODERATOR';
                    $cc_profile->accountant_id = $auth->id;
                    $cc_profile->sign = $sign;
                    $cc_profile->hash = $hash;
                    $cc_profile->save();

                    $tr = Transaction::findFirstByProfileId($cc_profile->profile_id);

                    if ($cc_profile->action != 'CREATED') {
                        $tr->amount = $tr->amount - $good->amount;
                    }

                    $good->weight = $cc_good->weight;
                    $good->basis = $cc_good->basis;
                    $good->ref_tn = $cc_good->ref_tn;
                    $good->date_import = $cc_good->date_import;
                    $good->profile_id = $cc_good->profile_id;
                    $good->price = $cc_good->price;
                    $good->amount = $cc_good->amount;
                    $good->goods_cost = $cc_good->goods_cost;
                    $good->ref_country = $cc_good->ref_country;
                    $good->ref_tn_add = $cc_good->ref_tn_add;
                    $good->goods_type = $cc_good->goods_type;
                    $good->up_type = $cc_good->up_type;
                    $good->date_report = $cc_good->date_report;
                    $good->basis_date = $cc_good->basis_date;
                    $good->package_weight = $cc_good->package_weight;
                    $good->package_cost = $cc_good->package_cost;
                    $good->calculate_method = $cc_good->calculate_method;
                    $good->status = $cc_good->status;
                    $good->updated = time();
                    $good->save();

                    $tr->amount = round($tr->amount + $cc_good->amount, 2);
                    $tr->save();

                    // логгирование
                    $c_l = new CorrectionLogs();
                    $c_l->iin = $auth->idnum;
                    $c_l->type = 'GOODS';
                    $c_l->user_id = $auth->id;
                    $c_l->accountant_id = $auth->id;
                    $c_l->profile_id = $cc_good ? $cc_good->profile_id : null;
                    $c_l->action = 'CORRECTION_APPROVED';
                    $c_l->object_id = $good ? $good->id : null;
                    $c_l->dt = time();
                    $c_l->meta_before = $c_before;
                    $c_l->meta_after = $c_c_before;
                    $c_l->comment = $comment;
                    $c_l->file = NULL;
                    $c_l->sign = $sign;
                    $c_l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;
                    $c_l->save();

                    $logString = json_encode($c_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);

                    // логгирование
                    $l = new ClientCorrectionLogs();
                    $l->iin = $auth->idnum;
                    $l->type = 'GOODS';
                    $l->user_id = $auth->id;
                    $l->action = 'APPROVED_BY_MODERATOR';
                    $l->object_id = $cc_good->good_id;
                    $l->ccp_id = $cc_profile->id;
                    $l->dt = time();
                    $l->meta_before = $c_before;
                    $l->meta_after = $c_c_before;
                    $l->comment = $comment;
                    $l->file = NULL;
                    $l->hash = $hash;
                    $l->sign = $sign;
                    $l->save();

                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);
                    $this->flash->success("Заявка на корректтировку успешна одобрена.");
                    return $this->response->redirect("/correction_request/index/");

                } elseif ($cc_profile->action == 'ANNULMENT') {

                    $cc_good = ClientCorrectionGoods::findFirstByCcpId($cc_profile->id);

                    $cc_profile->action_dt = time();
                    $cc_profile->status = 'APPROVED_BY_MODERATOR';
                    $cc_profile->accountant_id = $auth->id;
                    $cc_profile->sign = $sign;
                    $cc_profile->hash = $hash;
                    $cc_profile->save();

                    $goods = Goods::find(array(
                        'profile_id = :pid:',
                        'bind' => array(
                            'pid' => $cc_profile->profile_id,
                        ),
                        "order" => "id ASC"
                    ));

                    $id_list = array();
                    foreach ($goods as $key => $g) {
                        if ($g->status == "DELETED") continue;

                        if (file_exists(APP_PATH . '/private/certificates/goods_' . $cc_good->good_id . '.pdf')) {
                            exec('mv "' . APP_PATH . '"/private/certificates/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                        }

                        if (file_exists(APP_PATH . '/private/certificates_zd/goods_' . $cc_good->good_id . '.pdf')) {
                            exec('mv "' . APP_PATH . '"/private/certificates_zd/goods_"' . $cc_good->good_id . '".pdf "' . APP_PATH . '"/private/certificates_zd/corrected/goods_"' . $cc_good->good_id . '"_"' . time() . '".pdf ');
                        }

                        $id_list[] = $g->id;

                        $good = Goods::findFirstById($g->id);
                        $_before = json_encode(array($good));

                        $profile = Profile::findFirstById($good->profile_id);

                        $tr = Transaction::findFirstByProfileId($profile->id);
                        $tr->amount = $tr->amount - $good->amount;

                        if ($tr->save()) {
                            $good->amount = 0.00;
                            $good->goods_cost = 0.00;
                            $good->package_cost = 0.00;
                            $good->status = "CANCELLED";
                            $good->save();
                        }

                        $l = new CorrectionLogs();
                        $l->iin = $auth->idnum;
                        $l->type = 'GOODS';
                        $l->user_id = $auth->id;
                        $l->accountant_id = $auth->id;
                        $l->profile_id = $good ? $good->profile_id : null;
                        $l->action = 'ANNULMENT';
                        $l->object_id = $g ? $g->id : null;
                        $l->dt = time();
                        $l->meta_before = $_before;
                        $l->meta_after = $good ? json_encode(array($good)) : json_encode(array());
                        $l->comment = $comment;
                        $l->file = NULL;
                        $l->sign = $sign;
                        $l->initiator_id = $cc_profile ? $cc_profile->initiator_id : null;

                        if ($good->save()) $l->save();

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);
                    }

                    // логгирование
                    $c_l = new ClientCorrectionLogs();
                    $c_l->iin = $auth->idnum;
                    $c_l->type = 'GOODS';
                    $c_l->user_id = $auth->id;
                    $c_l->action = 'APPROVED_BY_MODERATOR';
                    $c_l->object_id = $cc_profile->profile_id;
                    $c_l->ccp_id = $cc_profile->id;
                    $c_l->dt = time();
                    $c_l->meta_before = json_encode($id_list);
                    $c_l->meta_after = "ДПП аннулирован!";
                    $c_l->comment = $comment;
                    $c_l->file = NULL;
                    $c_l->hash = $hash;
                    $c_l->sign = $sign;
                    $c_l->save();

                    __goodRecalc($cc_profile->profile_id);

                    $logString = json_encode($c_l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);
                    $this->flash->success("ДПП по заявке " . $cc_good->profile_id . " аннулирован");
                    return $this->response->redirect("/correction_request/index/");
                }
            }
        }
    }

    public function declineAction()
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {

            if (!$auth->isSuperModerator()) {
                $message = "Вы не имеете права совершать это действия.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/correction_request/index/");
            } else {
                $id = $this->request->getPost("ccp_id");
                $file = $this->request->getPost("file");
                $comment = $this->request->getPost("comment");
                $can = true;
                $cc_p = ClientCorrectionProfile::findFirstById($id);

                if ($this->request->hasFiles()) {
                    foreach ($this->request->getUploadedFiles() as $file) {
                        if ($file->getSize() > 0) {
                            $filename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                            $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                            $file->moveTo(APP_PATH . "/private/client_correction_docs/" . $filename);

                            // добавляем файл
                            $f = new ClientCorrectionFile();
                            $f->profile_id = $cc_p->profile_id;
                            $f->type = 'other';
                            $f->original_name = $filename;
                            $f->ext = $ext;
                            $f->ccp_id = $cc_p->id;
                            $f->visible = 1;
                            $f->user_id = $auth->id;
                            $f->save();

                            if ($f->save()) {
                                copy(APP_PATH . "/private/client_correction_docs/" . $filename, APP_PATH . "/private/client_corrections/" . $filename);
                            }
                        }
                    }
                } else {
                    $this->flash->warning("Загрузите файл.");
                    return $this->response->redirect("/correction_request/index/");
                    $can = false;
                }

                if ($can) {
                    $cc_p->action_dt = time();
                    $cc_p->status = 'DECLINED';
                    $cc_p->moderator_id = $auth->id;

                    if ($cc_p->save()) {

                        if ($cc_p->type == 'CAR') {
                            $cc_car = ClientCorrectionCars::findFirstByCcpId($id);
                            $car = Car::findFirstById($cc_car->car_id);
                            $_before = json_encode(array($cc_car));
                            $_after = json_encode(array($car));

                            // логгирование
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = $cc_p->type;
                            $l->user_id = $auth->id;
                            $l->action = 'DECLINED';
                            $l->object_id = $cc_p->object_id;
                            $l->ccp_id = $cc_p->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = $_after;
                            $l->comment = $comment;
                            $l->file = $filename;
                            // $l->sign = NULL;
                            $l->save();
                        } else {
                            $cc_good = ClientCorrectionGoods::findFirstByCcpId($id);
                            $good = Goods::findFirstById($cc_good->good_id);
                            $_before = json_encode(array($cc_good));
                            $_after = json_encode(array($good));

                            // логгирование
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = $cc_p->type;
                            $l->user_id = $auth->id;
                            $l->action = 'DECLINED';
                            $l->object_id = $cc_p->object_id;
                            $l->ccp_id = $cc_p->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = $_after;
                            $l->comment = $comment;
                            $l->file = $filename;
                            // $l->sign = NULL;
                            $l->save();
                        }

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);
                        $this->flash->success("Заявка на корректтировку успешна отклонена.");

                        return $this->response->redirect("/correction_request/index/");
                    }
                } else {
                    $this->logAction("Невозможно отклонить.");
                    $this->flash->warning("Невозможно отклонить.");
                    return $this->response->redirect("/correction_request/index/");
                }
            }
        }
    }

    public function accountantDeclineAction()
    {
        $auth = User::getUserBySession();
        $filename = NULL;

        if ($this->request->isPost()) {

            if (!$auth->isAccountant()) {
                $this->logAction("Вы не имеете права совершать это действия.");
                $this->flash->error("Вы не имеете права совершать это действия.", 'security');
                return $this->response->redirect("/correction_request/index/");
            } else {
                $id = $this->request->getPost("ccp_id");
                $file = $this->request->getPost("file");
                $comment = $this->request->getPost("comment");
                $can = true;
                $cc_p = ClientCorrectionProfile::findFirstById($id);

                if ($can) {
                    $cc_p->action_dt = time();
                    $cc_p->status = 'SEND_TO_MODERATOR';
                    $cc_p->moderator_id = $auth->id;

                    if ($cc_p->save()) {

                        if ($cc_p->type == 'CAR') {
                            $cc_car = ClientCorrectionCars::findFirstByCcpId($id);
                            $car = Car::findFirstById($cc_car->car_id);
                            $_before = json_encode(array($cc_car));
                            $_after = json_encode(array($car));

                            // логгирование
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = $cc_p->type;
                            $l->user_id = $auth->id;
                            $l->action = 'DECLINED_BY_ACCOUNTANT';
                            $l->object_id = $cc_p->object_id;
                            $l->ccp_id = $cc_p->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = $_after;
                            $l->comment = $comment;
                            $l->file = $filename;
                            // $l->sign = NULL;
                            $l->save();
                        } else {
                            $cc_good = ClientCorrectionGoods::findFirstByCcpId($id);
                            $good = Goods::findFirstById($cc_good->good_id);
                            $_before = json_encode(array($cc_good));
                            $_after = json_encode(array($good));

                            // логгирование
                            $l = new ClientCorrectionLogs();
                            $l->iin = $auth->idnum;
                            $l->type = $cc_p->type;
                            $l->user_id = $auth->id;
                            $l->action = 'DECLINED_BY_ACCOUNTANT';
                            $l->object_id = $cc_p->object_id;
                            $l->ccp_id = $cc_p->id;
                            $l->dt = time();
                            $l->meta_before = $_before;
                            $l->meta_after = $_after;
                            $l->comment = $comment;
                            $l->file = $filename;
                            // $l->sign = NULL;
                            $l->save();
                        }

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);
                        $this->flash->success("Заявка на корректтировку успешна отклонена.");
                        return $this->response->redirect("/correction_request/index/");
                    }
                } else {
                    $this->logAction("Невозможно отклонить.");
                    $this->flash->warning("Невозможно отклонить.");
                    return $this->response->redirect("/correction_request/index/");
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
        $path = APP_PATH . "/private/client_correction_docs/";
        $auth = User::getUserBySession();

        $pf = ClientCorrectionFile::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || $auth->isEmployee()) {
            if (file_exists($path . $pf->original_name)) {
                __downloadFile($path . $pf->original_name, $pf->original_name, 'view');
            }
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
        $path = APP_PATH . "/private/client_correction_docs/";
        $auth = User::getUserBySession();

        $pf = ClientCorrectionFile::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || $auth->isEmployee()) {
            if (file_exists($path . $pf->original_name)) {
                __downloadFile($path . $pf->original_name);
            }
        }
    }

    private function handleCorrectionFilesUpload($cc_profile, $auth, $id): bool
    {
        $message = '';
        if (!$this->request->hasFiles()) {
            $message = "Загрузите файл.";
            $this->flash->warning($message);
            return false;
        }

        $uploadedFiles = $this->request->getUploadedFiles();
        if (empty($uploadedFiles)) {
            $message = "Загрузите файл.";
            $this->flash->warning($message);
            return false;
        }

        $savedAny = false;

        foreach ($uploadedFiles as $file) {
            // Проверки
            if (!$file || (int)$file->getSize() <= 0) {
                continue;
            }

            $originalName = (string)$file->getName();
            $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

            // пример проверки расширений (подстрой под свои требования)
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'jfif', 'zip'];
            if ($ext === '' || !in_array($ext, $allowedExt, true)) {
                $message = "Недопустимый формат файла: {$originalName}";
                $this->flash->warning($message);
                continue;
            }

            // пример проверки размера (10MB)
            $maxSize = 50 * 1024 * 1024;
            if ((int)$file->getSize() > $maxSize) {
                $message = "Файл слишком большой: {$originalName}";
                $this->flash->warning($message);
                continue;
            }

            $docsDir = APP_PATH . "/private/client_correction_docs/";
            $targetPath = $docsDir . $originalName;

            try {
                $file->moveTo($targetPath);
            } catch (\Throwable $e) {
                $text = implode(' | ', [
                    $e->getMessage(),
                    str_replace(PHP_EOL, ' ', $e->getTraceAsString()),
                ]);
                $message = "Не удалось сохранить файл: {$originalName}, {$text}";
                $this->flash->warning("Не удалось сохранить файл: {$originalName}, попробуйте еще раз");
                continue;
            }

            // Добавляем запись в БД
            $f = new ClientCorrectionFile();
            $f->profile_id     = $cc_profile->profile_id;
            $f->type           = 'other';
            $f->original_name  = $originalName;
            $f->ext            = $ext;
            $f->ccp_id         = $cc_profile->id;
            $f->visible        = 1;
            $f->user_id        = $auth->id;

            try {
                $ok = $f->save();
            } catch (\Throwable $e) {
                $ok = false;
            }

            if (!$ok) {
                @unlink($targetPath);
                $message = "Не удалось сохранить информацию о файле: {$originalName}";
                $this->flash->warning("Не удалось сохранить информацию о файле: {$originalName}");
                continue;
            }

            // Копирование в другие директории (с проверкой результата)
            $copyTargets = [
                APP_PATH . "/private/client_corrections/" . $originalName,
                APP_PATH . "/private/correction/" . $originalName,
            ];

            foreach ($copyTargets as $copyTo) {
                if (!@copy($targetPath, $copyTo)) {
                    $message = "Файл сохранён, но не удалось скопировать в: {$copyTo}";
                    $this->flash->warning("Файл сохранён");
                }
            }

            $savedAny = true;
        }


        new AppException($message);

        if (!$savedAny) {
            $message = "Загрузите корректный файл.";
            $this->flash->warning($message);
            return false;
        }

        return true;
    }



}
