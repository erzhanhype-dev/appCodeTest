<?php

namespace App\Controllers;

use AgentBasement;
use App\Resources\CarRowResource;
use App\Resources\GoodsRowResource;
use App\Services\Car\CarService;
use Bank;
use Car;
use CarHistories;
use ContactDetail;
use ControllerBase;
use CorrectionLogs;
use File;
use Goods;
use OrderChats;
use OrderWatchers;
use PersonDetail;
use Profile;
use ProfileLogs;
use RefInitiator;
use Sf;
use Transaction;
use User;
use ZdBank;

class OperatorOrderController extends ControllerBase
{
    //Сохранение сообщения чата заявки
    private CarService $carService;

    public function onConstruct(): void
    {
        $this->carService = $this->di->getShared('carService');
        $this->goodsService = $this->di->getShared('goodsService');
    }

    public function clearFilterAction()
    {
        $ses = $this->session;
        $currentYear = (int)date('Y');

        // если в indexAction вы уже перешли с JSON на массивы
        $ses->set('mv_filter_year', [$currentYear]);
        $ses->set('mv_filter_status', ['REVIEW']);
        $ses->set('mv_filter_idnum', '');
        $ses->set('mv_filter_type', ['CAR', 'GOODS']);
        $ses->set('mv_filter_user_types', 'ALL');
        $ses->set('mv_filter_car_st_type', 'ALL');
        $ses->set('mv_filter_amount_from', 0);
        $ses->set('mv_filter_amount_end', 99999999999);
        $ses->set('mv_filter_pid', '');

        return $this->response->redirect('/operator_order/index');
    }

    public function indexAction(): void
    {
        $auth = User::getUserBySession();
        $req  = $this->request;
        $ses  = $this->session;

        $currentYear = (int)date('Y');

        // -------------------------
        // 1) Сохранение фильтров
        // -------------------------
        if ($req->isPost()) {
            $pid       = trim((string)$req->getPost('q', 'string', ''));
            $idnum     = trim((string)$req->getPost('idnum', 'string', ''));
            $userTypes = (string)$req->getPost('user_types', 'string', 'ALL');
            $stType    = (string)$req->getPost('st_type', 'string', 'ALL');
            $aFrom     = (int)$req->getPost('amount_from', 'int', 0);
            $aTo       = (int)$req->getPost('amount_end', 'int', 99999999999);

            $statuses = (array)($req->getPost('p_status') ?: ['REVIEW']);
            $types    = (array)($req->getPost('p_type') ?: ['CAR', 'GOODS']);
            $yearsIn  = $req->getPost('year');

            if ($aTo < $aFrom) {
                $this->flash->error('Укажите правильный интервал суммы УП');
                $this->response->redirect('/operator_order/index');
                return;
            }

            $ses->set('mv_filter_pid', $pid);
            $ses->set('mv_filter_idnum', $idnum);
            $ses->set('mv_filter_user_types', $userTypes);
            $ses->set('mv_filter_car_st_type', $stType);
            $ses->set('mv_filter_amount_from', $aFrom);
            $ses->set('mv_filter_amount_end', $aTo);
            $ses->set('mv_filter_status', $statuses);
            $ses->set('mv_filter_type', $types);

            if ($yearsIn) {
                $ses->set('mv_filter_year', (array)$yearsIn);
            }
        }

        // -------------------------
        // 2) Чтение фильтров
        // -------------------------
        $viewMode  = (int)($auth->view_mode ?? 1);
        $pid       = (string)($ses->get('mv_filter_pid') ?? '');
        $idnum     = (string)($ses->get('mv_filter_idnum') ?? '');
        $userTypes = (string)($ses->get('mv_filter_user_types') ?? 'ALL');
        $stType    = (string)($ses->get('mv_filter_car_st_type') ?? 'ALL');
        $aFrom     = (int)($ses->get('mv_filter_amount_from') ?? 0);
        $aTo       = (int)($ses->get('mv_filter_amount_end') ?? 99999999999);

        $statuses = (array)($ses->get('mv_filter_status') ?? ['REVIEW']);
        $types    = (array)($ses->get('mv_filter_type') ?? ['CAR', 'GOODS']);

        $yearsRaw = (array)($ses->get('mv_filter_year') ?? [$currentYear]);
        $years    = array_values(array_unique(array_map('intval', $yearsRaw)));
        if (!$years) {
            $years = [$currentYear];
        }

        // view_mode влияет на types
        if ($viewMode === 2) {
            $types = ['CAR'];
        } elseif ($viewMode === 3) {
            $types = ['GOODS'];
        }

        $ymin = min($years);
        $ymax = max($years);

        $dtBegin = strtotime(sprintf('%d-01-01 00:00:00', $ymin));
        $dtEnd   = strtotime(sprintf('%d-12-31 23:59:59', $ymax));
        $ses->set('dt_begin', $dtBegin);
        $ses->set('dt_end', $dtEnd);

        // -------------------------
        // 3) QueryBuilder
        // -------------------------
        $qb = $this->modelsManager->createBuilder()
            ->from(['p' => Profile::class])
            ->join(Transaction::class, 't.profile_id = p.id', 't')
            ->leftJoin(
                Transaction::class,
                't2.profile_id = p.id AND (t2.md_dt_sent > t.md_dt_sent OR (t2.md_dt_sent = t.md_dt_sent AND t2.id > t.id))',
                't2'
            )
            ->andWhere('t2.id IS NULL')
            ->leftJoin(User::class, 'u.id = p.user_id', 'u')
            ->leftJoin(User::class, 'exec.id = p.executor_uid', 'exec')
            ->columns([
                'p_id'          => 'p.id',
                'p_type'        => 'p.type',
                'p_blocked'     => 'p.blocked',
                'agent_name'    => 'p.agent_name',
                'executor_uid'  => 'p.executor_uid',
                'moderator_id'  => 'p.moderator_id',
                'sign_date'     => 'p.sign_date',
                'p_created'     => 'p.created',
                't_amount'      => 't.amount',
                't_status'      => 't.status',
                't_approve'     => 't.approve',
                'dt_sent'       => 't.md_dt_sent',
                'auto_detected' => 't.auto_detected',
                'fio'           => 'IF(u.user_type_id = 1, u.fio, u.org_name)',
                'idnum'         => 'u.idnum',
                'executor_name' => 'exec.fio',
            ])
            ->andWhere('p.created >= :b: AND p.created <= :e:', ['b' => $dtBegin, 'e' => $dtEnd])
            ->inWhere('t.approve', $statuses)
            ->inWhere('p.type', $types)
            ->andWhere('t.amount >= :af: AND t.amount <= :at:', ['af' => $aFrom, 'at' => $aTo])
            ->orderBy('t.md_dt_sent DESC, p.id DESC');

        if ($pid !== '') {
            $qb->andWhere('p.id = :pid:', ['pid' => (int)$pid]);
        }
        if ($idnum !== '') {
            $qb->andWhere('u.idnum = :iin:', ['iin' => $idnum]);
        }

        switch ($userTypes) {
            case 'AGENT':
                $qb->andWhere('p.agent_iin IS NOT NULL AND CHAR_LENGTH(p.agent_iin) > 0');
                break;
            case 'MODERATOR':
                $qb->andWhere('p.moderator_id IS NOT NULL AND p.moderator_id > 0');
                break;
            case 'CLIENT':
                $qb->andWhere('p.moderator_id IS NULL AND (p.agent_iin IS NULL OR CHAR_LENGTH(p.agent_iin) = 0)');
                break;
        }

        if ($stType !== 'ALL') {
            $qb->join(Car::class, 'c.profile_id = p.id', 'c');

            // ref_st_type: 0/1/2
            if ($stType === 'YES') {
                $qb->andWhere('c.ref_st_type = 1');
            } elseif ($stType === 'INT_TR') {
                $qb->andWhere('c.ref_st_type = 2');
            } else { // NOT
                $qb->andWhere('c.ref_st_type = 0');
            }

            // при фильтре седельности — только CAR
            $qb->inWhere('p.type', ['CAR']);
        }

        // -------------------------
        // 4) Пагинация
        // -------------------------
        $pageNum = max(1, (int)$req->getQuery('page', 'int', 1));
        $paginator = new \Phalcon\Paginator\Adapter\QueryBuilder([
            'builder' => $qb,
            'limit'   => 10,
            'page'    => $pageNum,
        ]);
        $page = $paginator->paginate();

        // -------------------------
        // 5) Форматирование данных
        // -------------------------
        $fmtTs = static function (?int $ts): string {
            return ($ts && $ts > 0) ? date('d.m.Y H:i', convertTimeZone($ts)) : '—';
        };
        $fmtMoney = static function ($v): string {
            return number_format((float)$v, 2, ',', ' ');
        };

        $approveMap = [
            'REVIEW'          => 'approve-review',
            'NEUTRAL'         => 'approve-neutral',
            'DECLINED'        => 'approve-declined',
            'APPROVE'         => 'approve-approve',
            'CERT_FORMATION'  => 'approve-cert-formation',
            'GLOBAL'          => 'approve-global',
        ];

        $items = [];
        foreach ($page->items as $row) {
            $pId = (int)$row->p_id;

            $hasMsg = file_exists(APP_PATH . '/storage/temp/msg_' . $pId . '.txt');
            $isDeclined = ((string)$row->t_approve === 'DECLINED');

            $rowClassParts = [];
            if ((string)$row->p_type === 'CAR') {
                $rowClassParts[] = 'table-warning';
            }
            if ($hasMsg || $isDeclined) {
                $rowClassParts[] = 'table-danger';
            }

            $items[] = (object)[
                'p_id'          => $pId,
                'row_class'     => implode(' ', $rowClassParts),
                'name_line'     => $row->agent_name ?: ($row->fio . '(' . $row->idnum . ')'),
                'p_created_str' => $fmtTs((int)$row->p_created),
                'sign_date_str' => $fmtTs((int)$row->sign_date),
                'dt_sent_str'   => $fmtTs((int)$row->dt_sent),
                'amount_str'    => $fmtMoney($row->t_amount),
                'paid_label'    => ((string)$row->t_status === 'PAID') ? 'paid-true' : 'paid-false',
                'approve_label' => $approveMap[(string)$row->t_approve] ?? 'approve-not-set',
                'auto_detected' => ((int)$row->auto_detected === 1) ? '1' : '0',
                'executor_name' => $row->executor_name,
            ];
        }

        // -------------------------
        // 6) Опции фильтров
        // -------------------------
        $makeOptions = static function (array $values, callable $labelFn, array $selectedValues): array {
            $selectedSet = array_flip($selectedValues);
            $out = [];
            foreach ($values as $v) {
                $out[] = (object)[
                    'value'    => $v,
                    'label'    => $labelFn($v),
                    'selected' => isset($selectedSet[$v]) ? ' selected' : '',
                ];
            }
            return $out;
        };

        $statusCodes = ['REVIEW', 'GLOBAL', 'DECLINED', 'NEUTRAL', 'APPROVE', 'CERT_FORMATION'];
        $statusOptions = $makeOptions(
            $statusCodes,
            fn($c) => $this->translator->_($c),
            $statuses
        );

        $yearList = range(2016, $currentYear);
        $yearOptions = $makeOptions(
            $yearList,
            fn($y) => (string)$y,
            $years
        );

        $typeMap = [
            'CAR'   => $this->translator->_('ТС'),
            'GOODS' => $this->translator->_('Товар'),
            'KPP'   => $this->translator->_('КПП'),
        ];
        $typeOptions = [];
        $selectedTypes = array_flip($types);
        foreach ($typeMap as $k => $label) {
            $typeOptions[] = (object)[
                'value'    => $k,
                'label'    => $label,
                'selected' => isset($selectedTypes[$k]) ? ' selected' : '',
            ];
        }

        $userTypeOptions = [
            (object)['value' => 'ALL',       'label' => 'Показать все',         'selected' => $userTypes === 'ALL' ? ' selected' : ''],
            (object)['value' => 'CLIENT',    'label' => 'Клиентские заявки',     'selected' => $userTypes === 'CLIENT' ? ' selected' : ''],
            (object)['value' => 'AGENT',     'label' => 'Агентские заявки',      'selected' => $userTypes === 'AGENT' ? ' selected' : ''],
            (object)['value' => 'MODERATOR', 'label' => 'Модераторские заявки',  'selected' => $userTypes === 'MODERATOR' ? ' selected' : ''],
        ];

        $stTypeOptions = [
            (object)['value' => 'ALL',    'label' => 'Все',                                           'selected' => $stType === 'ALL' ? ' selected' : ''],
            (object)['value' => 'NOT',    'label' => 'Не седельный тягач',                            'selected' => $stType === 'NOT' ? ' selected' : ''],
            (object)['value' => 'YES',    'label' => 'Седельный тягач',                               'selected' => $stType === 'YES' ? ' selected' : ''],
            (object)['value' => 'INT_TR', 'label' => 'Седельный тягач(Международные перевозки)',      'selected' => $stType === 'INT_TR' ? ' selected' : ''],
        ];

        // -------------------------
        // 7) Передача в шаблон
        // -------------------------
        $this->view->setVars([
            'auth' => $auth,
            'filters' => (object)[
                'q'           => $pid,
                'idnum'       => $idnum,
                'amount_from' => $aFrom,
                'amount_end'  => $aTo,
                'view_mode'   => $viewMode,
            ],
            'statusOptions'   => $statusOptions,
            'yearOptions'     => $yearOptions,
            'typeOptions'     => $typeOptions,
            'userTypeOptions' => $userTypeOptions,
            'stTypeOptions'   => $stTypeOptions,
            'items'           => $items,
            'page'            => $page,
        ]);
    }

    public function viewAction($pid)
    {
        $decline_reasons = DECLINE_REASONS;
        $auth = User::getUserBySession();

        $profile = Profile::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $pid],
        ]);

        if (!$profile) {
            return $this->response->redirect("/operator_order");
        }

        $transaction = $profile->tr;

        $profile_user = User::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->user_id],
        ]);

        $profile_moderator = User::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->moderator_id],
        ]);

        $profile_initiator = RefInitiator::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->initiator_id],
        ]);

        $contact_detail = ContactDetail::findFirst([
            'conditions' => 'user_id = :user_id:',
            'bind' => ['user_id' => $profile->user_id],
        ]);

        $watchers = OrderWatchers::find([
            'conditions' => 'p_id = :pid:',
            'bind' => ['pid' => $pid],
        ]);

        $chats = OrderChats::find([
            'conditions' => 'p_id = :pid:',
            'bind' => ['pid' => $pid],
        ]);

        $files = File::find([
            'conditions' => 'profile_id = :pid:',
            'bind' => ['pid' => $pid],
        ]);

        $cars = $this->carService->itemsByProfile($profile->id);
        $cancelled_cars = $this->carService->itemsCancelledByProfile($profile->id);

        $goods = $this->goodsService->itemsByProfile($profile->id);

        $title = $profile_user->user_type_id === 1 ? $profile_user->fio : $profile_user->org_name;

        $userArr = [
            'id' => $profile_user->id,
            'title' => $title ? $title : $profile_user->fio,
            'idnum' => $profile_user->idnum,
            'user_type_id' => $profile_user->user_type_id,
            'email' => $profile_user->email,
            'phone' => $contact_detail ? $contact_detail->phone : ''
        ];

        $executor_uid = ($profile->executor_uid ?? 0);
        $executorName = '';
        if ($executor_uid > 0) {
            $exUser = User::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $executor_uid],
            ]);
            $nm = $exUser ? (string)$exUser->fio : 'Не назначен';
            $executorName = $nm !== '' ? $nm : (string)$executor_uid;
        }

        $watcherList = [];
        foreach ($watchers as $w) {
            $watcherList[] = [
                'username' => (string)($w->username ?? $w->user_name ?? ''),
                'socket_id' => (string)($w->socket_id ?? ('watcher-' . (string)($w->user_id ?? ''))),
                'since' => $this->formatTs((string)($w->created ?? '')),
            ];
        }

        $chatsArr = [];

        $initiators = RefInitiator::find();

        foreach ($chats as $chat) {
            $chat_user = User::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $chat->user_id],
            ]);
            $chatsArr[] = [
                'id' => $chat->id,
                'username' => $chat_user ? $chat_user->fio : '',
                'message' => $chat->message,
                'datetime' => $this->formatTs($chat->dt),
                'user_id' => $chat->user_id,
            ];
        }

        $filesArr = [];
        foreach ($files as $file) {
            $modifier = null;
            $created = null;
            if ($file->modified_by) {
                $modifier = User::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $file->modified_by],
                ]);
            }
            if ($file->created_by) {
                $created = User::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $file->created_by],
                ]);
            }
            $filesArr[] = [
                'id' => $file->id,
                'type' => $file->type,
                'original_name' => $file->original_name,
                'ext' => $file->ext,
                'visible' => $file->visible,
                'modified_at' => $file->modified_at,
                'modifier' => $modifier ? $modifier->toArray() : null,
                'created_by' => $created ? $created->toArray() : null,
            ];
        }

        $executor = [
            'id' => $executor_uid,
            'name' => $executor_uid === 0 ? null : $executorName,
            'status_code' => $profile->status,
            'status_text' => $this->statusText($profile->status)
        ];

        $isModerator = ($auth->isAdmin() || $auth->isModerator() || $auth->isSuperModerator());
        $can_approve = false;

        if ($profile->type === 'CAR') {
            $builder = $this->modelsManager->createBuilder()
                ->columns([
                    'profile_id' => 'p.id',
                    'first_sent' => 'MIN(t.md_dt_sent)',
                ])
                ->from(['p' => Profile::class])
                ->join(Transaction::class, 't.profile_id = p.id', 't')
                ->where('t.approve = :status: AND p.type = :ptype:')
                ->groupBy('p.id')
                ->orderBy('first_sent ASC')
                ->limit(ORDER_COUNT_MAX);

            $topOrders = $builder->getQuery()->execute([
                'status' => 'REVIEW',
                'ptype' => 'CAR',
            ]);

            foreach ($topOrders as $row) {
                if ((int)$profile->id === (int)$row->profile_id) {
                    $can_approve = true;
                    break;
                }
            }
        } elseif ($profile->type === 'GOODS') {
            $can_approve = true;
        }

        $permissions = [
            'start_execute_order' => ($executor_uid === 0) && $isModerator && ($transaction->approve === 'REVIEW' || $transaction->approve === 'APPROVE'),
            'stop_execute_order' => ($executor_uid === $auth->id) && ((int)$profile->status === 1) && $isModerator && ($transaction->approve === 'REVIEW' || $transaction->approve === 'APPROVE'),
            'can_approve' => $can_approve,
        ];

        $calculate_method = 0;

        if ($transaction->approve == 'DECLINED') {
            $profileLogsMsg = ProfileLogs::findFirst([
                'conditions' => "profile_id = :pid: AND action = 'MSG'",
                'bind' => ['pid' => (int)$profile->id],
                'order' => 'id DESC',
            ]);
            if (file_exists(APP_PATH . '/storage/temp/msg_' . $pid . '.txt')) {
                $msg_modal = file_get_contents(APP_PATH . '/storage/temp/msg_' . $pid . '.txt');
            } else {
                $msg_modal = $profileLogsMsg->meta_after;

            }
            $this->flash->error('<strong>Сообщение модератора:</strong> ' . $msg_modal);
        }

        $data = [
            'id' => $profile->id,
            'name' => $profile->name,
            'created_dt' => $this->formatTs($profile->created),
            'sent_dt' => $this->formatTs($transaction->md_dt_sent),
            'approve_dt' => $this->formatTs($transaction->dt_approve),
            'amount' => self::fmtMoney((float)($transaction->amount ?? 0)),
            'agent_name' => ($profile->agent_name ?? ''),
            'agent_iin' => ($profile->agent_iin ?? ''),
            'agent_city' => ($profile->agent_city ?? ''),
            'agent_phone' => ($profile->agent_phone ?? ''),
            'blocked' => $profile->blocked,
            'status' => $transaction->status,
            'approve' => $transaction->approve,
            'ac_approve' => $transaction->ac_approve,
            'type' => $profile->type,
            'executor' => $executor,
            'calculate_method' => $calculate_method,
            'initiator' => $profile_initiator ? $profile_initiator->toArray() : null,
            'user' => $userArr,
            'moderator' => $profile_moderator ? $profile_moderator->toArray() : null,

            'files' => $filesArr,
            'initiators' => $initiators->toArray(),
            'chats' => $chatsArr,
            'watchers' => $watcherList,
            'permissions' => $permissions,
            'decline_reasons' => $decline_reasons,
            'cancelled_cars' => $cancelled_cars,
            'cars' => CarRowResource::collection($cars),
            'goods' => GoodsRowResource::collection($goods)
        ];

        $this->view->setVar('data', $this->toObj($data));
    }

    private function toObj(mixed $v): mixed
    {
        if (is_array($v)) {
            // сначала конвертируем все вложенные элементы
            $v = array_map(fn($x) => $this->toObj($x), $v);
            // затем отдаём объект с доступом через свойства
            return new \ArrayObject($v, \ArrayObject::ARRAY_AS_PROPS);
        }
        return $v;
    }

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
    }

    private function formatTs(string $ts): string
    {
        if ($ts === '' || $ts === '0') return '';
        if (ctype_digit($ts)) return date('d.m.Y H:i', (int)$ts);
        $t = strtotime($ts);
        return $t === false ? '' : date('d.m.Y H:i', $t);
    }

    private function statusText(string $code): string
    {
        return match ($code) {
            'new' => 'Новая',
            'in_progress' => 'В работе',
            'done' => 'Завершена',
            'declined' => 'Отклонена',
            default => $code,
        };
    }

    public function viewdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);

        if (($auth->isOperator())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
            } else {
                $this->flash->warning("Файл не найден");
                return $this->response->redirect($this->request->getHTTPReferer());
            }
        }
    }

    function getMsgListAction(int $id = 0)
    {
        $data = [];

        $sql = <<<SQL
            SELECT dt as dt, meta_after as message
            FROM ProfileLogs pl
            WHERE profile_id = :pid: AND action = "MSG"
            ORDER BY pl.id DESC
        SQL;

        $query = $this->modelsManager->createQuery($sql);

        $msg = $query->execute(array(
            "pid" => $id
        ));

        if (count($msg) > 0) {
            foreach ($msg as $m) {
                $data[] = [
                    "date" => date('Y-m-d H:i:s', convertTimeZone($m->dt)),
                    "message" => $m->message,
                ];
            }

            http_response_code(200);
            return json_encode($data);
        }

        return false;
    }

    function getVinListAction(int $id = 0)
    {

        $sql = <<<SQL
            SELECT c.vin as vin
            FROM Car c
            WHERE c.profile_id = :pid: AND mask_id > 0
            ORDER BY c.id DESC
        SQL;

        $query = $this->modelsManager->createQuery($sql);
        $vin = $query->execute(array(
            "pid" => $id
        ));

        if (count($vin) > 0) {
            $vin_arr = [];
            foreach ($vin as $v) {
                $vin_arr[] = $v->vin;
            }
            $vin_list = implode(', ', $vin_arr);

            $message = <<<TEXT
                Данная заявка содержит VIN сходящий в перечень заключений об оценке типа ТС РФ, которые 
                не подлежат эксплуатации на территории РК согласно данным 
                <a href='https://www.rst.gov.ru/portal/gost/home/activity/compliance/evaluationcompliance/assessmentconclusion/typeassessment' target='_blank'>
                    перейдите по ссылке
                </a>

                <a href="#" type="button" class="btn btn-link" data-toggle='collapse' data-target='#vinListThatMatchesWithMask' aria-expanded='false'
                    aria-controls='vinListThatMatchesWithMask'>
                    <i class='fa fa-exclamation-circle' aria-hidden='true' style='color:orange; font-size: 14px;'> </i>
                     Показать список VIN кодов
                </a>
                <div class='collapse' id='vinListThatMatchesWithMask'>
                  <div class='card card-body'>
                    <div class='alert alert-warning' role='alert'>
                      <p style='text-align: justify;'>$vin_list</p>
                    </div>
                  </div>
                </div>
            TEXT;

            http_response_code(200);
            return $message;
        }

        return false;
    }

    function getAnnulledOrDeletedGoodsAction(int $id = 0)
    {
        $data = [];

        $check_annullment = __checkAnnulledGoods((int)$id);
        $annulled_goods_count = $check_annullment[0]['count'];
        $all_goods_count = $check_annullment[0]['g_count'];

        if ($annulled_goods_count > 0) {
            $annulled_date = $check_annullment[0]['annulled_date'];
            $data[] = [
                "message" => "Сертификат о внесении утилизационного платежа $id аннулирован в $annulled_date (Были аннулированы $annulled_goods_count позиции из $all_goods_count)",
            ];

            http_response_code(200);
            return json_encode($data);
        }

        $check_deleted = __checkDeletedGoods((int)$id);
        $deleted_goods_count = $check_deleted[0]['count'];
        $all_goods_count = $check_deleted[0]['g_count'];

        if ($deleted_goods_count > 0) {
            $deleted_goods = implode(", ", $check_deleted[0]['deleted_goods']);
            $data[] = [
                "message" => "Удаленные позиции: <br> $deleted_goods <br>(Были удалены $deleted_goods_count позиции из $all_goods_count)",
            ];

            http_response_code(200);
            return json_encode($data);

            $this->flash->error();
        }

        return false;
    }

    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);

        if (($auth->isOperator())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name);
            } else {
                $this->flash->warning("Файл не найден");
                return $this->response->redirect($this->request->getHTTPReferer());
            }
        }
    }

    public function getPaymentsJdAction(int $id = 0)
    {
        $this->view->disable();
        $data = [];

        if ($id > 50000) {
            $ibans = [
                'KZ20601A871001726091', 'KZ02601A871001725251', 'KZ07601A871001726131',
                'KZ70601A871001726161', 'KZ62601A871001726111', 'KZ41601A871001726101',
                'KZ28601A871001726141', 'KZ49601A871001726151', 'KZ83601A871001726121'
            ];

            $zd_banks = ZdBank::find([
                'conditions' => '(comment LIKE :v1: OR transactions LIKE :v2:) AND iban_to IN ({ib:array})',
                'bind' => [
                    'v1' => "%{$id}%",
                    'v2' => "%{$id}%",
                    'ib' => $ibans,
                ],
                // опционально, если нужны типы
                // 'bindTypes'  => ['v1' => PDO::PARAM_STR, 'v2' => PDO::PARAM_STR],
                'order' => 'id DESC',
            ]);

            foreach ($zd_banks as $bank) {
                $amount = number_format($bank->amount, 2, ",", " ");
                $comment = str_replace(',', ', ', str_replace((string)$id, "<strong>{$id}</strong>", $bank->comment));

                $data[] = [
                    'iban_to' => $bank->iban_to,
                    'date' => ($bank->paid > 0) ? date('d.m.Y H:i', convertTimeZone($bank->paid)) : '',
                    'comment' => $comment,
                    'amount' => $amount,
                    'transactions' => $bank->transactions,
                    'id' => $bank->id,
                ];
            }
        }

        $json_data = [
            'draw' => 1,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
            'data' => $data,
        ];

        http_response_code(200);
        return json_encode($json_data);
    }

    function getPaymentsRopAction(int $id = 0)
    {
        $this->view->disable();
        $data = [];

        if ($id > 50000) {
            $ibans = [
                'KZ256017131000029119', 'KZ606017131000029459', 'KZ236017131000028670',
                'KZ686017131000029412', 'KZ61926180219T620004', 'KZ34926180219T620005',
                'KZ07926180219T620006', 'KZ77926180219T620007', 'KZ196010111000325234',
                'KZ896010111000325235', 'KZ466010111000325233', 'KZ736010111000325232',
                'KZ496018871000301461',
            ];

            $banks = Bank::find([
                'conditions' => '(comment LIKE :v1: OR transactions LIKE :v2:) AND iban_to IN ({ib:array})',
                'bind' => [
                    'v1' => "%{$id}%",
                    'v2' => "%{$id}%",
                    'ib' => $ibans,
                ],
                'order' => 'id DESC',
            ]);

            foreach ($banks as $bank) {
                $amount = number_format($bank->amount, 2, ",", " ");
                $comment = str_replace(',', ', ', str_replace((string)$id, "<strong>{$id}</strong>", (string)$bank->comment));

                $data[] = [
                    'iban_to' => $bank->iban_to,
                    'date' => ($bank->paid > 0) ? date('d.m.Y H:i', convertTimeZone($bank->paid)) : '',
                    'comment' => $comment,
                    'amount' => $amount,
                    'transactions' => $bank->transactions,
                    'id' => $bank->id,
                ];
            }
        }

        $json = [
            'draw' => 1,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
            'data' => $data,
        ];

        http_response_code(200);
        return json_encode($json);
    }

    public function getLogsAction()
    {
        $auth = User::getUserBySession();
        $html = NULL;

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("pid");

            if ($auth->isEmployee() || $auth->isOperator()) {
                $logs = ProfileLogs::find([
                    'conditions' => 'profile_id = :pid:',
                    'bind' => ['pid' => $pid],
                    'order' => 'dt DESC' // сортировка по убыванию даты
                ]);
                $p = Profile::findFirstById($pid);

                if (sizeof($logs) != 0) {
                    foreach ($logs as $log) {
                        $log_details = $log->login . ' (БЕЗ ДЕТАЛИЗАЦИИ)';

                        if ($log->login != 'SYSTEM') {
                            $user = User::findFirst(["login = '" . $log->login . "'"]);
                            if (!$user) $user = User::findFirst(["idnum = '" . $log->login . "'"]);

                            if ($user) {
                                if ($user->fio) {
                                    $log_details = $user->fio . " (ИИН " . $user->idnum . ")";
                                } else {
                                    $log_details = $user->org_name . " (ИИН " . $user->idnum . ")";
                                }
                            }
                        }

                        $html .= '<tr>';

                        $html .= '<td>' . $log_details . '</td>
                              <td>' . $this->translator->_($log->action) . '</td>
                              <td>' . date("d.m.Y H:i", convertTimeZone($log->dt)) . '</td>';

                        $html .= '</tr>';
                    }

                    if ($p->type == "CAR") {

                        $car = Car::findByProfileId($p->id);

                        foreach ($car as $c) {
                            $edited_car = CorrectionLogs::find([
                                "conditions" => "object_id = :cid: and type = :type:",
                                "bind" => [
                                    "cid" => $c->id,
                                    "type" => "CAR"
                                ]
                            ]);

                            if ($edited_car) {
                                foreach ($edited_car as $log) {
                                    $log_details = $log->iin . ' (БЕЗ ДЕТАЛИЗАЦИИ)';

                                    $user = User::findFirstById($log->user_id);

                                    if ($user->fio) {
                                        $log_details = $user->fio . " (ИИН " . $user->idnum . ")";
                                    } else {
                                        $log_details = $user->org_name . " (ИИН " . $user->idnum . ")";
                                    }

                                    $html .= '<tr>';

                                    $html .= '<td>' . $log_details . '</td>
                                        <td>' . $this->translator->_($log->action) . '</td>
                                        <td>' . date("d.m.Y H:i", convertTimeZone($log->dt)) . '</td>';

                                    $html .= '</tr>';
                                }
                            }
                        }
                    } else {
                        $goods = Goods::findByProfileId($p->id);

                        foreach ($goods as $g) {
                            $edited_good = CorrectionLogs::find([
                                "conditions" => "object_id = :gid: and type = :type:",
                                "bind" => [
                                    "gid" => $g->id,
                                    "type" => "GOODS"
                                ]
                            ]);

                            if ($edited_good) {
                                foreach ($edited_good as $log) {
                                    $log_details = $log->iin . ' (БЕЗ ДЕТАЛИЗАЦИИ)';

                                    $user = User::findFirstById($log->user_id);

                                    if($user) {
                                        if ($user->user_type_id == 1) {
                                            $log_details = $user->fio . " (ИИН " . $user->idnum . ")";
                                        } else {
                                            $log_details = $user->org_name . " (ИИН " . $user->idnum . ")";
                                        }
                                    }

                                    $html .= '<tr>';

                                    $html .= '<td>' . $log_details . '</td>
                                          <td>' . $this->translator->_($log->action) . '</td>
                                          <td>' . date("d.m.Y H:i", convertTimeZone($log->dt)) . '</td>';

                                    $html .= '</tr>';
                                }
                            }
                        }
                    }
                    return $html;
                } else {
                    return '<tr><td></td><td> <span class="badge badge-danger" style="font-size: 16px;">Не найдены !</span></td><td></td></tr>';
                }
            }
        } else {
            return "Error";
        }
    }
}
