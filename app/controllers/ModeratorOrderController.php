<?php

namespace App\Controllers;

use App\Resources\CarRowResource;
use App\Resources\GoodsRowResource;
use App\Services\Car\CarService;
use App\Services\Goods\GoodsService;
use ApproveExpense;
use Bank;
use Car;
use CarHistories;
use CompanyDetail;
use ContactDetail;
use ControllerBase;
use CorrectionLogs;
use File;
use FundCar;
use FundProfile;
use Goods;
use OrderChats;
use OrderWatchers;
use PersonDetail;
use Phalcon\Http\ResponseInterface;
use Profile;
use ProfileExpense;
use ProfileLogs;
use RefCarCat;
use RefCarValue;
use RefCountry;
use RefInitiator;
use Transaction;
use User;
use ZdBank;
use ZipDownloadLogs;

class ModeratorOrderController extends ControllerBase
{
    private CarService $carService;
    private GoodsService $goodsService;
    private const int ORDER_STATUS_OPENED = 0;
    private const int ORDER_STATUS_IN_PROGRESS = 1;
    private const int ORDER_STATUS_COMPLETED = 2;
    private const int MAX_FILE_SIZE = 15 * 1024 * 1024; // 15 MB

    public function onConstruct(): void
    {
        $this->carService = $this->di->getShared('carService');
        $this->goodsService = $this->di->getShared('goodsService');
    }

    //Сохранение сообщения чата заявки
    public function chatAction()
    {
        $auth = User::getUserBySession();
        $message = $this->request->getPost("message");
        $pid = $this->request->getPost("pid");
        $user_id = $auth->id;
        $person_detail = PersonDetail::findFirstByUserId($user_id);
        $orderChats = new OrderChats();
        $orderChats->message = $message;
        $orderChats->user_id = $user_id;
        $orderChats->p_id = $pid;
        $orderChats->username = $person_detail->last_name . ' ' . $person_detail->first_name;
        $orderChats->dt = time();
        $orderChats->save();

        $this->logAction('Сообщение чата в заявке');

        return json_encode($orderChats);
    }

// //Изменение и обработка статуса заявки
    public function statusAction($pid)
    {
        $auth = User::getUserBySession();
        $user_id = $auth->id;
        $profile = Profile::findFirstById($pid);
        $status = $this->request->getPost("status");

        if ($profile->status == self::ORDER_STATUS_IN_PROGRESS && $profile->executor_uid != NULL && $profile->executor_uid != $user_id) {
            $this->flash->warning("Заявка уже исполняется другим пользователем.");
            $this->logAction("Заявка уже исполняется другим пользователем.");
            return $this->response->redirect("/moderator_order/view/$pid");
        } else {
            if ($status === 1 || $status === '1') {
                $profile->executor_uid = $user_id;
                $this->logAction('Заявка на исполнении');
            } else {
                $this->logAction('Исполнение заявки прекращена');
                $profile->executor_uid = NULL;
            }

            $profile->status = intval($status);

            if ($profile->save() === false) {
                foreach ($profile->getMessages() as $m) {
                    $this->logAction((string)$m);
                    $this->flash->error((string)$m);
                }
                return $this->response->redirect("/moderator_order/view/$pid");
            }


            return $this->response->redirect("/moderator_order/view/$pid");
        }
    }

// //Привязка пользователя к заявке как наблюдатель
    public function attachAction()
    {
        $socket_id = $this->request->getPost("socket_id");
        $username = $this->request->get('data')['username'];
        $pid = $this->request->get('data')['pid'];

        $order_watchers = new OrderWatchers();
        $order_watchers->p_id = $pid;
        $order_watchers->username = $username;
        $order_watchers->socket_id = $socket_id;
        $order_watchers->save();
    }

// //Удаление пользователя в заявке как наблюдателя
    public function detachAction()
    {
        $socket_id = $this->request->getPost("socket_id");
        $watcher = OrderWatchers::findFirstBySocketId($socket_id);

        if ($watcher != NULL) {
            $watcher->delete();
        }
    }

    public function blockAction($pid)
    {
        $auth = User::getUserBySession();
        $pr = Profile::findFirstById($pid);
        $_before = json_encode(array($pr));
        if ($pr && $auth) {
            $pr->blocked = 1;
            if ($pr->save()) {
                // логгирование
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = 'BLOCK';
                $l->profile_id = $pr->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($pr));
                $l->save();
                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);
            }
            return $this->response->redirect("/moderator_order/view/$pid");
        } else {
            $this->flash->error("Заявка не найдена!");
            return $this->response->redirect("/moderator_order/");
        }
    }

    public function unblockAction($pid)
    {
        $auth = User::getUserBySession();
        $pr = Profile::findFirstById($pid);
        $tr = Transaction::findFirstByProfileId($pid);
        $_before = json_encode(array($pr, $tr));
        if ($pr && $auth) {
            $tr->approve = 'REVIEW';
            $tr->save();
            $pr->blocked = 0;
            if ($pr->save()) {
                // логгирование
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = 'UNBLOCK';
                $l->profile_id = $pr->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($pr, $tr));
                $l->save();
                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);
            }
            return $this->response->redirect("/moderator_order/view/$pid");
        } else {
            $this->flash->error("Заявка не найдена!");
            return $this->response->redirect("/moderator_order/");
        }
    }

    public function acceptAction($pid, $val): ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!($auth->isSuperModerator() || $auth->isModerator())) {
            $message = "У вас нет прав на это действия!";
            $this->logAction($message);
            $this->flash->error($message);
            return $this->response->redirect("/moderator_order/view/$pid");
        }
        $dt_approve = 0;
        $block = 1;
        if ($val == 'approved') {
            $approve = 'APPROVE';
        } else if ($val == 'declined') {
            $approve = 'DECLINED';
            $block = 0;
        } else if ($val == 'neutral') {
            $approve = 'NEUTRAL';
            $block = 0;
        } else if ($val == 'global') {
            $approve = 'GLOBAL';
            $dt_approve = time();
        } else if ($val == 'cert_formation') {
            $approve = 'CERT_FORMATION';
            $dt_approve = time();
        } else {
            $approve = NULL;
        }

        // если не NULL, то меняем
        if ($approve) {
            $tr = Transaction::findFirstByProfileId($pid);
            $p = Profile::findFirstById($pid);

            //история действий
            $lastLog = ProfileLogs::findFirst([
                'conditions' => 'profile_id = :pid:',
                'bind' => [
                    'pid' => $pid,
                ],
                'order' => 'dt DESC',
            ]);

            //Проверка на повторное действие
            if ($approve === 'APPROVE') {
                if ($lastLog->action === 'APPROVE') {
                    $this->flash->error("Счет уже выдан!");
                    $this->logAction("Счет уже выдан, {$this->translator->_($approve)}");
                    return $this->response->redirect("/moderator_order/view/$pid");
                }
                if (in_array($lastLog->action, ['NEUTRAL', 'DECLINED', 'CERT_FORMATION', 'GLOBAL'])) {
                    $this->logAction("Действие запрещено, {$this->translator->_($approve)}");
                    $this->flash->error("Действие запрещено!");
                    return $this->response->redirect("/moderator_order/view/$pid");
                }
            }

            if ($approve === 'CERT_FORMATION') {
                if ($lastLog->action === 'CERT_FORMATION') {
                    $this->flash->error("ДПП уже выдан!");
                    $this->logAction("ДПП уже выдан, {$this->translator->_($approve)}");

                    return $this->response->redirect("/moderator_order/view/$pid");
                }
                if (in_array($lastLog->action, ['REVIEW', 'NEUTRAL', 'DECLINED', 'GLOBAL'])) {
                    $this->flash->error("Действие запрещено!");
                    return $this->response->redirect("/moderator_order/view/$pid");
                }
            }

            // не разрешаем Выдать ДПП или Формирование сертификата статус не оплачена
            if ($approve == 'GLOBAL' || $approve == 'CERT_FORMATION') {
                $check_payment = __checkIsPaid($pid);
                if (!$check_payment) {
                    $this->flash->error("Платеж отсутствует или сумма платежа меньше необходимой!");
                    $this->logAction("Платеж отсутствует или сумма платежа меньше необходимой, {$this->translator->_($approve)}");
                    return $this->response->redirect("/moderator_order/view/$pid");
                }
            }

            // ищем БИН в заявке
            $rnn = '';
            if ($p->agent_iin) {
                $rnn = preg_replace('/(\D)/', '', $p->agent_iin);
            } else {
                $u = User::findFirstById($p->user_id);
                if ($u->user_type_id == 1) {
                    $pd = PersonDetail::findFirstByUserId($p->user_id);
                    if ($pd->iin) {
                        $rnn = preg_replace('/(\D)/', '', $pd->iin);
                    }
                } else {
                    $cd = CompanyDetail::findFirstByUserId($p->user_id);
                    if ($cd->bin) {
                        $rnn = preg_replace('/(\D)/', '', $cd->bin);
                    }
                }
            }

            // создаем списание
            $pe = ProfileExpense::findFirstByProfileId($pid);
            $ae = ApproveExpense::findFirstByProfileId($pid);
            if (!$pe) {
                $pe = new ProfileExpense();
            }
            if (!$ae) {
                $ae = new ApproveExpense();
            }
            // списание по одобрение
            $ae->profile_id = $p->id;
            $ae->rnn_recipient = $rnn;
            $ae->date_modified = time();
            if ($approve == 'APPROVE' || $approve == 'GLOBAL' || $approve == 'CERT_FORMATION') {
                $ae->amount = $tr->amount;
            } else {
                $ae->amount = 0.00;
            }
            // списание по завке
            $pe->profile_id = $p->id;
            $pe->rnn_recipient = $rnn;
            $pe->date_modified = time();
            if ($approve == 'APPROVE') {
                $ae->amount = $tr->amount;
                $pe->amount = 0.00;
            } elseif ($approve == 'CERT_FORMATION') {
                $ae->amount = $tr->amount;
                $pe->amount = $tr->amount;
            } elseif ($approve == 'GLOBAL') {
                $ae->amount = $tr->amount;
                $pe->amount = $tr->amount;
            } else {
                $pe->amount = 0.00;
            }
            $ae->save();
            $pe->save();
            // конец учета по БИН

            $_before = json_encode(array($p, $tr));
            if ($tr) {
                if ($val == 'declined') {
                    $tr->ac_approve = 'NOT_SIGNED';
                }
                $tr->approve = $approve;
                if ($tr->dt_approve == 0) {
                    $tr->dt_approve = $dt_approve;
                }
                $p->blocked = $block;
                $tr->save();
                $p->status = 2;
                $p->executor_uid = NULL;
                if ($p->save()) {
                    // логгирование
                    $l = new ProfileLogs();
                    $l->login = $auth->idnum;
                    $l->action = $approve;
                    $l->profile_id = $p->id;
                    $l->dt = time();
                    $l->meta_before = $_before;
                    $l->meta_after = json_encode(array($p, $tr));
                    $l->save();
                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);
                }
            } else {
                $this->logAction("Не удалось обновить: {$this->translator->_($approve)}}");
            }
        } else {
            $this->logAction("Вы совершаете попытку сброса / обнуления заявки №" . $pid);
            $this->flash->warning("Вы совершаете попытку сброса / обнуления заявки №" . $pid);
        }

        return $this->response->redirect("/moderator_order/view/$pid");
    }

    // Отклонени заявки && очищение все ТС
    public function clearCarsAction()
    {
        $auth = User::getUserBySession();
        $pid = $this->request->getPost("order_id");
        $msg = $this->request->getPost("msg");

        if (!$auth->isEmployee()) {
            $this->logAction("У вас нет прав на это действия!");
            $this->flash->error("У вас нет прав на это действия!");
        } else {

            // если не NULL, то меняем
            if ($pid && $auth) {
                $tr = Transaction::findFirstByProfileId($pid);
                $p = Profile::findFirstById($pid);

                $_before = json_encode(array($p, $tr));

                if ($tr) {
                    $tr->ac_approve = 'NOT_SIGNED';
                    $tr->approve = 'DECLINED';
                    // $tr->md_dt_sent = 0;
                    // $tr->dt_approve = 0;
                    // $tr->ac_dt_approve = 0;
                    if ($tr->dt_approve == 0) {
                        $tr->dt_approve = time();
                    }
                    $p->blocked = 1;
                    $p->status = 2;
                    $p->executor_uid = NULL;
                    if ($p->save()) {
                        // логгирование
                        $l = new ProfileLogs();
                        $l->login = $auth->idnum;
                        $l->action = 'DECLINED';
                        $l->profile_id = $p->id;
                        $l->dt = time();
                        $l->meta_before = $_before;
                        $l->meta_after = json_encode(array($p, $tr));
                        $l->save();
                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        $tr->save();

                        if ($auth->isEmployee()) {
                            file_put_contents(APP_PATH . '/storage/temp/msg_' . $p->id . '.txt', $msg);

                            // логгирование
                            $l = new ProfileLogs();
                            $l->login = $auth->idnum;
                            $l->action = 'MSG';
                            $l->profile_id = $p->id;
                            $l->dt = time();
                            $l->meta_before = '—';
                            $l->meta_after = $msg;
                            $l->save();
                            $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            $this->logAction($logString);
                        }

                        if ($p->type == 'CAR') {

                            $cars = Car::findByProfileId($p->id);

                            foreach ($cars as $car) {

                                $car_history = new CarHistories();
                                $car_history->car_id = $car->id;
                                $car_history->volume = $car->volume;
                                $car_history->vin = $car->vin;
                                $car_history->year = $car->year;
                                $car_history->date_import = $car->date_import;
                                $car_history->profile_id = $car->profile_id;
                                $car_history->ref_car_cat = $car->ref_car_cat;
                                $car_history->ref_car_type_id = $car->ref_car_type_id;
                                $car_history->ref_country = $car->ref_country;
                                $car_history->ref_st_type = $car->ref_st_type;
                                $car_history->cost = $car->cost;
                                $car_history->status = $car->status;
                                $car_history->check_reg_status = $car->check_reg_status;
                                $car_history->action = 'DECLINED';
                                $car_history->dt = time();
                                $car_history->user_id = $auth->id;
                                $car_history->vehicle_type = $car->vehicle_type;

                                if ($car_history->save()) {
                                    $car->delete();

                                    $digitalpass = File::findFirst(array(
                                        "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                                        "bind" => array(
                                            "pid" => $car->profile_id,
                                            "type" => "digitalpass"
                                        )
                                    ));

                                    if ($digitalpass) {
                                        $filename = APP_PATH . '/private/docs/' . $digitalpass->id . '.' . $digitalpass->ext;
                                        @unlink(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/epts_' . $car->vin . '.pdf');

                                        $epts_pdf_num = count(glob(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . "/epts_*.pdf"));
                                        if ($epts_pdf_num > 0) {
                                            exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $filename . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/epts_*.pdf');
                                        } else {
                                            if (file_exists($filename)) {
                                                @unlink($filename);
                                            }

                                            $digitalpass->delete();
                                        }
                                    }

                                    $spravka_epts = File::findFirst(array(
                                        "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                                        "bind" => array(
                                            "pid" => $car->profile_id,
                                            "type" => "spravka_epts"
                                        )
                                    ));

                                    if ($spravka_epts) {
                                        $filename = APP_PATH . '/private/docs/' . $spravka_epts->id . '.' . $spravka_epts->ext;
                                        @unlink(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/spravka_' . $car->vin . '.pdf');

                                        $epts_pdf_num = count(glob(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . "/spravka_*.pdf"));
                                        if ($epts_pdf_num > 0) {
                                            exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $filename . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/spravka_*.pdf');
                                        } else {
                                            if (file_exists($filename)) {
                                                @unlink($filename);
                                            }

                                            $spravka_epts->delete();
                                        }
                                    }
                                }
                            }
                            __carRecalc($p->id);
                        }
                    }
                }
            } else {
                $this->logAction("Вы совершаете попытку сброса / обнуления заявки №" . $pid);
                $this->flash->warning("Вы совершаете попытку сброса / обнуления заявки №" . $pid . ". Попытка была заблокирована и сведения о ней переданы службе безопасности.");
            }
        }

        return $this->response->redirect("/moderator_order/view/$pid");
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

        return $this->response->redirect('/moderator_order/index');
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
                $this->response->redirect('/moderator_order/index');
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
            $name = $row->executor_name ? $row->executor_name : '';
            $parts = explode(' ', $name);
            $executor_name = 'Не назначен';
            if (count($parts) > 1) {
                array_pop($parts);
                $executor_name = implode(' ', $parts);
            }
            $items[] = (object)[
                'p_id'          => $pId,
                'row_class'     => implode(' ', $rowClassParts),
                'name_line'     => $row->agent_name ?: ($row->fio ? $row->fio . '(' . $row->idnum . ')' : ''),
                'p_created_str' => $fmtTs((int)$row->p_created),
                'sign_date_str' => $fmtTs((int)$row->sign_date),
                'dt_sent_str'   => $fmtTs((int)$row->dt_sent),
                'amount_str'    => $fmtMoney($row->t_amount),
                'paid_label'    => ((string)$row->t_status === 'PAID') ? 'paid-true' : 'paid-false',
                'approve_label' => $approveMap[(string)$row->t_approve] ?? 'approve-not-set',
                'auto_detected' => ((int)$row->auto_detected === 1) ? '1' : '0',
                'executor_name' => $executor_name,
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

        $this->logAction("Просмотр списка заявок", 'access');
    }

    /** Вернуть ФИО исполнителя по user_id или 'Не назначен' */
    private function resolveExecutorName(?int $uid): string
    {
        if (!$uid) {
            return 'Не назначен';
        }
        $pd = $this->modelsManager->createBuilder()
            ->from(['pd' => PersonDetail::class])
            ->columns(['full' => "CONCAT(pd.last_name,' ',pd.first_name) AS full"])
            ->andWhere('pd.user_id = :u:', ['u' => $uid])
            ->getQuery()->execute()->getFirst();
        return $pd ? (string)$pd->full : 'Не назначен';
    }


    public function getLogsAction()
    {
        $auth = User::getUserBySession();
        $html = NULL;

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("pid");

            if ($auth->isEmployee()) {
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
                                if ($user->bin === ZHASYL_DAMU_BIN) {
                                    $log_details = $user->org_name . '/' . $user->fio . " (ИИН " . $user->idnum . ")";
                                }else {
                                    if ($user->fio) {
                                        $log_details = $user->fio . " (ИИН " . $user->idnum . ")";
                                    } else {
                                        $log_details = $user->org_name . " (ИИН " . $user->idnum . ")";
                                    }
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

                                    if($user) {
                                        if ($user->bin === ZHASYL_DAMU_BIN) {
                                            $log_details = $user->org_name . '/' . $user->fio . " (ИИН " . $user->idnum . ")";
                                        }else {
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
                                            if($user->bin === ZHASYL_DAMU_BIN){
                                                $log_details = $user->org_name.'/'.$user->fio . " (ИИН " . $user->idnum . ")";
                                            }else{
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

    public function viewAction($pid)
    {
        $decline_reasons = DECLINE_REASONS;
        $auth = User::getUserBySession();

        $profile = Profile::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $pid],
        ]);

        if (!$profile) {
            return $this->response->redirect("/moderator_order");
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
                ->columns(['t.profile_id AS profile_id'])
                ->from(['p' => Profile::class])
                ->join(Transaction::class, 't.profile_id = p.id', 't')
                ->where('t.approve = :approve:', ['approve' => 'REVIEW'])
                ->andWhere('p.type = :type:', ['type' => 'CAR'])
                ->groupBy('p.id')
                ->orderBy('t.md_dt_sent ASC')
                ->limit(ORDER_COUNT_MAX);

            $top_orders = $builder->getQuery()->execute();

            if ($top_orders && count($top_orders) > 0) {
                foreach ($top_orders as $row) {
                    if ((int)$pid === (int)$row->profile_id) {
                        $can_approve = true;
                        break;
                    }
                }
            }
        } elseif ($profile->type === 'GOODS') {
            $can_approve = true;
        }

        $permissions = [
            'start_execute_order' => ($executor_uid === 0) && $isModerator && ($transaction->approve === 'REVIEW' || $transaction->approve === 'APPROVE' || $transaction->approve === 'DECLINED'),
            'stop_execute_order' => ($executor_uid === $auth->id) && ((int)$profile->status === 1) && $isModerator && ($transaction->approve === 'REVIEW' || $transaction->approve === 'APPROVE' || $transaction->approve === 'DECLINED'),
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

        $this->logAction("Просмотр заявки", 'access');
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

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
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

    public function msgAction()
    {
        $auth = User::getUserBySession();
        $order = $this->request->getPost("order_id");
        $msg = $this->request->getPost("msg");

        if (!($auth->isSuperModerator() || $auth->isModerator())) {
            $message = 'У вас нет прав на это действие.';
            $this->logAction($message, 'security');
            $this->flash->warning($message);
            $this->response->redirect("/moderator_order/accept/$order/declined")->send();
        }

        file_put_contents(APP_PATH . '/storage/temp/msg_' . $order . '.txt', $msg);

        // логгирование
        $l = new ProfileLogs();
        $l->login = $auth->idnum;
        $l->action = 'MSG';
        $l->profile_id = $order;
        $l->dt = time();
        $l->meta_before = '—';
        $l->meta_after = $msg;
        $l->save();
        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString);

        $this->logAction("Заявка отклонена");
        $this->flash->success("Заявка отклонена!");
        $this->response->redirect("/moderator_order/accept/$order/declined")->send();
    }

    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || $auth->isEmployee()) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name);
            }
        }
    }

    public function downloadZipAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $profile = Profile::findFirstById($id);
        $file_id_list = [];
        $files_count = 0;

        if ($profile) {
            $files = File::find([
                "conditions" => "profile_id = :pid: and visible = :visible:",
                "bind" => [
                    "pid" => $profile->id,
                    "visible" => 1
                ]
            ]);

            if ($files && count($files) > 0) {
                if (file_exists($path . 'download.zip')) {
                    @unlink($path . 'download.zip');
                }

                $zipPath = $path . 'download.zip';
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Не удалось создать ZIP: ' . $zipPath);
                }

                foreach ($files as $f) {
                    $fullPath = $path . $f->id . '.' . $f->ext;

                    if (file_exists($fullPath)) {
                        $files_count++;
                        $file_id_list[] = (int)$f->id;

                        // Аналог PCLZIP_OPT_REMOVE_PATH, $path:
                        // внутри архива будет "123.pdf", без префикса $path
                        $localName = basename($fullPath);

                        if (!$zip->addFile($fullPath, $localName)) {
                            // если нужно — фиксируем, что файл не удалось добавить
                            $file_id_list[] = $f->id . '_ADD_FAILED';
                        }
                    } else {
                        $file_id_list[] = $f->id . '_NOT_FOUND';
                    }
                }

                $zip->close();

                $download_logs = new ZipDownloadLogs();
                $download_logs->profile_id = $profile->id;
                $download_logs->user_id = $auth->id;
                $download_logs->files = json_encode($file_id_list);
                $download_logs->file_count = $files_count;
                $download_logs->dt = time();

                if ($download_logs->save()) {
                    if (file_exists($path . 'download.zip')) {
                        $this->logAction('Скачаивания архива', 'access');
                        __downloadFile($path . 'download.zip');
                    } else {
                        $message = "Файлы не найдены.";
                        $this->flash->warning($message);
                        return $this->response->redirect("/moderator_order");
                    }
                }
            } else {
                $message = "Файлы не найдены.";
                $this->logAction($message);
                $this->flash->warning($message);
                return $this->response->redirect("/moderator_order");
            }
        } else {
            $this->flash->error("Объект не найден!");
            return $this->response->redirect("/moderator_order");
        }
    }

    public function checkOrderAction()
    {

        $checkIn = 'NO';
        $checkExp = 'NO';

        if ($this->request->isPost() && $this->request->getPost("vin") != '') {
            $vin = $this->request->getPost("vin");
            $car = Car::findFirstByVin($vin);
            $f_car = FundCar::findFirstByVin($vin);

            if (__checkInner($vin) == true) {
                $checkIn = 'YES';
            }

            if (__checkExport($vin) == true) {
                $checkExp = 'YES';
            }

            $car_country = null;
            $f_car_cat = null;
            $f_car_profile = null;
            if ($car) {
                $car_country = RefCountry::findFirst($car->ref_country);
            }

            if ($f_car) {
                $f_car_cat = RefCarCat::findFirst($f_car->ref_car_cat);
                $f_car_profile = FundProfile::findFirst($f_car->fund_id);
            }

            $this->logAction('Проверка заявки по VIN', 'access');

            $this->view->setVars(array(
                "f_car" => $f_car,
                "car" => $car,
                "car_country" => $car_country,
                "f_car_cat" => $f_car_cat,
                "f_car_profile" => $f_car_profile,
                "checkExp" => $checkExp,
                "checkIn" => $checkIn,
                "vin" => $vin
            ));
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

    function changeStatusAction()
    {
        $auth = User::getUserBySession();
        $this->view->disable();
        $json_data = [];
        $messages = [];
        $success = true;
        $json_data = [];

        if ($this->request->isPost()) {
            $profile_id = $this->request->getPost('id');
            $status = $this->request->getPost('status');
            $approve = $this->request->getPost('approve');
            $ac_approve = $this->request->getPost('ac_approve');
            $comment = $this->request->getPost('comment');

            $tr = Transaction::findFirstByProfileId($profile_id);
            $p = Profile::findFirstById($profile_id);
            $_before = json_encode(array($p, $tr));

            if ($approve == 'GLOBAL' || $approve == 'CERT_FORMATION' || $ac_approve == 'SIGNED' || $status == 'PAID') {
                $check_payment = __checkIsPaid($profile_id);
                if (!$check_payment) {
                    $success = false;
                    $messages[] = "Платеж отсутствует или сумма платежа меньше необходимой!";
                    $this->logAction("Платеж отсутствует или сумма платежа меньше необходимой!, action: $approve, profile_id: $p->id");
                }
            }

            if ($success != false) {
                $tr->approve = $approve;
                $tr->ac_approve = $ac_approve;
                $tr->status = $status;
                $tr->save();

                $p->blocked = 1;
                if ($approve == 'NEUTRAL' || $approve == 'DECLINED') {
                    $p->blocked = 0;
                }
                $p->save();

                $approve = ($tr->amount == 0) ? $this->translator->_("no_payment_required") : $this->translator->_($approve);

                // логгирование
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = $approve;
                $l->profile_id = $p->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($comment, $p, $tr));
                $l->save();
                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);

                $messages[] = "Заявка успешно отредактирована!";
            }

            $json_data = array(
                "success" => $success,
                "lang" => $messages,
                "status" => $this->translator->_($status),
                "approve" => $this->translator->_($approve)
            );
        }

        http_response_code(200);
        return json_encode($json_data);
    }

    function changeCalcAction()
    {
        $auth = User::getUserBySession();
        $this->view->disable();
        if ($this->request->isPost()) {
            $pid = $this->request->getPost('id');

            if ($auth->isAdminSoft()) {
                $calculate_method = $this->request->getPost('calculate_method');

                if ($pid) {
                    $profile = Profile::findFirstById($pid);

                    $cars = Car::findByProfileId($pid);
                    $tr = Transaction::findFirstByProfileId($profile->id);
                    $total = 0;

                    if ($tr->approve == 'GLOBAL') {
                        $this->logAction("Изменение способа расчета в этом статусе запрещено");
                        $this->flash->error("Изменение способа расчета в этом статусе запрещено");
                    }

                    foreach ($cars as $car) {
                        $car_volume = $car->volume;
                        $car_cats = RefCarCat::findFirstById($car->ref_car_cat);
                        $car_type = $car_cats->car_type;
                        $ref_st = $car->ref_st_type;
                        $e_car = $car->electric_car ? true : false;

                        $value = RefCarValue::findFirst(array(
                            "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_start" => (int)$car_volume,
                                "volume_end" => (int)$car_volume
                            )
                        ));

                        if ($calculate_method == 1) {
                            $sum = __calculateCar((float)$car_volume, json_encode($value), $ref_st, $e_car);

                            $car->cost = $sum;
                            $car->calculate_method = 1;
                            $car->save();

                            $total += $sum;
                        }
                    }
                    $tr->amount = $total;
                    $tr->save();

                    $calcName = CALCULATE_METHODS[$calculate_method];
                    $this->logAction("Способ расчета изменен на '$calcName'");
                    $this->flash->success("Способ расчета изменен на '$calcName'");
                }
            }
            return $this->response->redirect("/moderator_order/view/$pid");
        }

        return $this->response->redirect("/moderator_order/index");
    }

    public function setInitiatorAction($pid): ResponseInterface
    {
        $auth = User::getUserBySession();
        if ($auth->isAdminSoft() || $auth->isSuperModerator()) {
            if ($this->request->isPost()) {
                $initiator = $this->request->getPost('initiator');
                $profile = Profile::findFirst($pid);
                $_before = json_encode(array($profile));

                $profile->initiator_id = $initiator;
                $profile->save();

                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = 'CHANGE_INITIATOR';
                $l->profile_id = $profile->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($profile));
                $l->save();
                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);
            }
        }
        return $this->response->redirect("/moderator_order/view/$pid");
    }

    public function docAction(): ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/moderator_order/view/' . (int)$this->request->get('profile_id'));
        }

        $auth = User::getUserBySession();
        $profileId = (int)$this->request->getPost('profile_id');
        $docType = trim((string)$this->request->getPost('doc_type'));
        $profile = Profile::findFirstById($profileId);

        if (!$profile) {
            $this->flash->error('Профиль не найден.');
            return $this->response->redirect("/moderator_order/view/{$profileId}");
        }
        if ($profile->user_id !== $auth->id && !$auth->isEmployee()) {
            $this->logAction('У вас нет прав на это действие.', 'security');
            $this->flash->warning('У вас нет прав на это действие.');
            return $this->response->redirect("/moderator_order/view/{$profileId}");
        }
        if ($docType === '') {
            $this->flash->error('Укажите тип документа.');
            return $this->response->redirect("/moderator_order/view/{$profileId}");
        }

        $cl = (int)($this->request->getServer('CONTENT_LENGTH') ?? 0);
        $pms = $this->bytesFromIni((string)ini_get('post_max_size'));
        if ($cl > 0 && $cl > $pms) {
            $this->logAction('Запрос больше post_max_size (' . ini_get('post_max_size') . ').');
            $this->flash->error('Запрос больше post_max_size (' . ini_get('post_max_size') . ').');
            return $this->response->redirect("/moderator_order/view/{$profileId}");
        }

        $files = $this->request->getUploadedFiles(false); // включаем файлы с ошибками
        if (empty($files)) {
            $this->flash->error('Файл не передан. Проверьте enctype="multipart/form-data".');
            return $this->response->redirect("/moderator_order/view/{$profileId}");
        }

        $saved = false;
        foreach ($files as $file) {
            $err = $file->getError();
            if ($err !== UPLOAD_ERR_OK) {
                $this->flash->error(match ($err) {
                    UPLOAD_ERR_INI_SIZE => 'Размер файла нельзя превышать 50 МБ',
                    UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE в форме.',
                    UPLOAD_ERR_PARTIAL => 'Файл загружен частично.',
                    UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории.',
                    UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP.',
                    default => 'Ошибка загрузки файла.'
                });
                continue;
            }

            $size = $file->getSize();
            if ($size <= 0) {
                $this->flash->error('Пустой файл.');
                continue;
            }
            if ($size > self::MAX_FILE_SIZE) {
                $this->flash->error('Размер файла нельзя превышать 50 МБ.');
                $this->logAction('Размер файла нельзя превышать 50 МБ.');

                continue;
            }

            $original = $file->getName();
            $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));

            $nf = new File();
            $nf->profile_id = $profile->id;
            $nf->type = $docType;
            $nf->original_name = $original;
            $nf->ext = $ext;
            $nf->created_by = $auth->id;

            if (!$nf->save()) {
                $this->flash->error('Не удалось сохранить метаданные файла.');
                $this->logAction('Не удалось сохранить метаданные файла.');
                continue;
            }

            $target = APP_PATH . "/private/docs/{$nf->id}.{$ext}";
            if (!$file->moveTo($target)) {
                $nf->delete();
                $this->flash->error('Не удалось сохранить файл на диск.');
                $this->logAction('Не удалось сохранить файл на диск.');
                continue;
            }

            $this->flash->success('Файл добавлен.');
            $this->logAction('Загружен файл');

            $saved = true;
        }

        if (!$saved) {
            $this->flash->warning('Файлы не были добавлены.');
        }

        return $this->response->redirect("/moderator_order/view/{$profileId}");
    }

    private function bytesFromIni(string $v): int
    {
        $v = trim($v);
        $n = (int)$v;
        $s = strtolower(substr($v, -1));
        return $s === 'g' ? $n << 30 : ($s === 'm' ? $n << 20 : ($s === 'k' ? $n << 10 : (int)$v));
    }
}
