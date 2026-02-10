<?php

namespace App\Controllers;

use Bank;
use BankLogs;
use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use ProfileLogs;
use Transaction;
use User;

class AdminBankController extends ControllerBase
{

    public function bankAction(): void
    {
        $auth = null;
        if (class_exists('User')) {
            $auth = User::getUserBySession();
        }

        // ---- 1. Сброс фильтров ----
        if ($this->request->getQuery('clear_filters', 'int', 0)) {
            $this->session->remove('bank_filters');
            $this->response->redirect('/admin_bank/bank');
            return;
        }

        // ---- 2. Получение и сохранение фильтров (Sticky Filters) ----
        $filters = $this->session->get('bank_filters', []);
        
        // Список полей для фильтрации (в этом методе используется 'ref', а не 'reference')
        $filterKeys = ['idnum', 'status', 'year', 'iban_to', 'ref'];
        $hasAnyNewFilter = false;

        foreach ($filterKeys as $key) {
            if ($this->request->hasQuery($key)) {
                $val = $this->request->getQuery($key);
                $filters[$key] = $val;
                $hasAnyNewFilter = true;
            }
        }

        // Дефолтный год для этого метода — 2016 или текущий, если в сессии пусто
        if (empty($filters['year'])) {
            $filters['year'] = date('Y');
        }

        $this->session->set('bank_filters', $filters);

        // Распределяем переменные
        $idnum   = $filters['idnum'] ?? '';
        $status  = $filters['status'] ?? '';
        $year    = (int)$filters['year'];
        $iban_to = $filters['iban_to'] ?? '';
        $ref     = (string)($filters['ref'] ?? '');

        // ---- 3. Логика пагинации ----
        $page = (int)$this->request->getQuery('page', 'int', 1);

        // Сброс в 1 только при новом поиске (есть фильтры, но нет 'page' в URL)
        if ($hasAnyNewFilter && !$this->request->hasQuery('page')) {
            $page = 1;
        }
        
        $page = max(1, $page);
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 'int', 100)));

        // Сортировка
        $ORDER_COLUMNS = [
            0 => 'name_sender', 1 => 'iban_from', 2 => 'amount', 3 => 'iban_to',
            4 => 'paid', 5 => 'comment', 6 => 'id', 7 => 'transactions',
        ];
        $colIdx = (int)$this->request->getQuery('order_col', 'int', 4);
        $dir = strtolower((string)$this->request->getQuery('order_dir', null, 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $ORDER_COLUMNS[$colIdx] ?? 'paid';

        // ---- 4. Константы IBAN (специфичные для этого метода) ----
        $IBAN_ALLOW = [
            'KZ256017131000029119', 'KZ606017131000029459', 'KZ236017131000028670', 'KZ686017131000029412',
            'KZ61926180219T620004', 'KZ34926180219T620005', 'KZ07926180219T620006', 'KZ77926180219T620007',
            'KZ196010111000325234', 'KZ896010111000325235', 'KZ466010111000325233', 'KZ736010111000325232',
            'KZ496018871000301461',
        ];

        $finalIbanList = [];
        if (!empty($iban_to)) {
            $finalIbanList = is_array($iban_to) ? $iban_to : [$iban_to];
            $finalIbanList = array_values(array_filter($finalIbanList, static fn($v) => $v !== '' && $v !== null));
        }

        if (empty($finalIbanList)) {
            $finalIbanList = $IBAN_ALLOW;
        }

        // ---- 5. Диапазон дат ----
        $tzApp = new \DateTimeZone('Asia/Almaty');
        $begin = (new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year), $tzApp))->getTimestamp();
        $end = (new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $year), $tzApp))->getTimestamp();

        // ---- 6. Построение запроса ----
        $modelClass = Bank::class;
        $b = $this->modelsManager->createBuilder()
            ->from($modelClass)
            ->columns([
                'id', 'rnn_sender', 'name_sender', 'iban_from', 'amount', 'iban_to', 'paid', 'comment', 'transactions', 'account_num',
            ])
            ->betweenWhere('paid', $begin, $end)
            ->inWhere('iban_to', $finalIbanList)
            ->andWhere('LOWER(comment) NOT LIKE :dep: AND LOWER(comment) NOT LIKE :perc: AND LOWER(comment) NOT LIKE :reward:', [
                'dep' => '%депозит%', 'perc' => '%процент%', 'reward' => '%выплата вознагражд%',
            ]);

        if ($idnum !== '') {
            $b->andWhere('rnn_sender = :idnum:', ['idnum' => $idnum]);
        }

        if ($ref !== '') {
            $refLike = '%' . strtr($ref, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $b->andWhere('(account_num = :ref: OR comment LIKE :refLike: ESCAPE \'\\\')', [
                'ref' => $ref,
                'refLike' => $refLike,
            ]);
        }

        if ($status === 'SET') {
            $b->andWhere('(transactions IS NOT NULL AND transactions <> \'\')');
        } elseif ($status === 'NOT_SET') {
            $b->andWhere('(transactions IS NULL OR transactions = \'\')');
        }

        $b->orderBy("$orderBy $dir");

        // ---- 7. Пагинация ----
        $paginator = new QueryBuilderPaginator([
            'builder' => $b,
            'limit'   => $limit,
            'page'    => $page,
        ]);
        
        $repo = $paginator->paginate();

        // ---- 8. Подготовка строк ----
        $rows = [];
        foreach ($repo->getItems() as $bank) {
            $links = '';
            if (!empty($bank->transactions)) {
                $arr = array_filter(array_map('trim', explode(',', (string)$bank->transactions)));
                if ($arr) {
                    $links = implode(', ', array_map(function ($tr) {
                        $safe = htmlspecialchars($tr, ENT_QUOTES);
                        return '<a href="/moderator_order/view/' . $safe . '" target="_blank">' . $safe . '</a>';
                    }, $arr));
                }
            }

            $paidFmt = '';
            if (!empty($bank->paid)) {
                try {
                    $paidFmt = (new \DateTimeImmutable('@' . (int)$bank->paid))
                        ->setTimezone($tzApp)
                        ->format('d.m.Y H:i');
                } catch (\Exception $e) {
                    $paidFmt = '---';
                }
            }

            $rows[] = (object)[
                'id' => (int)$bank->id,
                'name_sender' => ($bank->name_sender && $bank->rnn_sender)
                    ? sprintf('%s (%s)', $bank->name_sender, $bank->rnn_sender)
                    : $bank->name_sender,
                'iban_from' => (string)$bank->iban_from,
                'amount_fmt' => number_format((float)$bank->amount, 2, ',', ' '),
                'iban_to' => (string)$bank->iban_to,
                'paid_fmt' => $paidFmt,
                'comment' => (string)$bank->comment,
                'transactions' => $links,
            ];
        }

        // ---- 9. В шаблон ----
        $yearsRange = range(2016, (int)date('Y'));

        $this->view->setVars([
            'items'    => $rows,
            'auth'     => $auth,
            'page'     => $repo,
            'orderCol' => $colIdx,
            'orderDir' => $dir,
            'years'    => $yearsRange,
            'filters'  => (object)[
                'status'    => $status,
                'idnum'     => $idnum,
                'reference' => $ref, // Для совместимости с шаблоном используем 'reference'
                'year'      => $year,
                'iban_to'   => is_array($iban_to) ? $iban_to : (empty($iban_to) ? [] : [$iban_to]),
            ],
        ]);
    }

    public function setAction($id)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $p_back = $this->request->getPost("profile_back");
            $bank_id = $this->request->getPost("bank_id");
            $tr_id = $this->request->getPost("tr_id");

            $bank = Bank::findFirstById($bank_id);

            $tr_id = str_replace(' ', '', $tr_id);

            $old_trs = explode(', ', $bank->transactions);
            $trs = explode(',', $tr_id);

            $trs_to_create = array_diff($trs, $old_trs);
            $trs_to_remove = array_diff($old_trs, $trs);

            $allowed_bins = ['151140025060', '040340008429'];
            if (!in_array($auth->bin, $allowed_bins)) {
                $this->flash->error("У вас нет прав на это действие!");
                return $this->response->redirect("/admin_bank/bank/");
            }

            // добавляем платеж
            foreach ($trs_to_create as $n => $t) {
                if ($t) {
                    $tr = Transaction::findFirstByProfileId($t);
                    if ($tr) {
                        $tr->source = INVOICE;
                        $tr->status = 'PAID';
                        $tr->save();
                    } else {
                        $can = false;
                        $this->flash->error("Заявка $t не найдена !");
                        return $this->response->redirect("/admin_bank/bank/");
                    }
                }
            }

            // убираем платеж
            foreach ($trs_to_remove as $n => $t) {
                if ($t) {
                    $tr = Transaction::findFirstByProfileId($t);
                    if ($tr) {
                        $tr->status = 'NOT_PAID';
                        $tr->approve = 'NEUTRAL';
                        $tr->ac_approve = 'NOT_SIGNED';
                        $tr->save();

                        // логгирование сброса
                        $l = new ProfileLogs();
                        $l->login = $auth->idnum;
                        $l->action = 'NEUTRAL';
                        $l->profile_id = $t;
                        $l->dt = time();
                        $l->meta_before = json_encode(array('reason' => 'bank', 'transaction' => $bank_id));
                        $l->meta_after = json_encode(array('reason' => 'bank', 'transaction' => $bank_id));
                        $l->save();
                    }
                }
            }

            if ($tr_id != '') {
                $bank->transactions = join(', ', $trs);
            } else {
                $bank->transactions = '';
            }

            // логируем всякое
            if ($bank->save()) {
                $l = new BankLogs();
                $l->login = $auth->idnum;
                $l->action = 'change_payment';
                $l->payment_id = $bank_id;
                $l->profile_id = join(', ', $trs);
                $l->dt = time();
                $l->meta = json_encode($_SESSION['auth']);
                $l->save();
            }

            if ($p_back > 0) {
                return $this->response->redirect("/moderator_order/view/$p_back");
            } else {
                return $this->response->redirect("/admin_bank/bank/");
            }
        } else {
            $bank = Bank::findFirstById($id);
            $this->view->setVars(array(
                "bank_tr" => $bank->transactions,
                "bank_id" => $id
            ));
        }
    }

    public function viewAction($id)
    {
        $tr = Transaction::findFirstById($id);
        return $this->response->redirect("/moderator_order/view/$tr->profile_id");
    }

}
