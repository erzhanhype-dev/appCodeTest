<?php

namespace App\Controllers;

use Bank;
use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use User;

class OperatorBankController extends ControllerBase
{

    public function resetPageAction()
    {
        $this->view->disable();

        unset($_SESSION['operator_bank_status'], $_SESSION['operator_bank_idnum'],
            $_SESSION['operator_bank_reference'], $_SESSION['operator_bank_year']);

        return $this->response->redirect("/operator_bank/bank");
    }

    public function bankAction(): void
    {
        $auth = User::getUserBySession();

        // ---- входные параметры ----
        $page = max(1, (int)$this->request->getQuery('page', 'int', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 'int', 10)));

        // Очистка фильтров
        if ($this->request->getQuery('clear_filters', 'int', 0)) {
            $this->session->remove('bank_filters');
            $this->response->redirect('/operator_bank/bank');
            return;
        }

        // ---- получение фильтров из GET/сессии ----
        $idnum = $this->request->getQuery('idnum');
        $ref = $this->request->getQuery('ref');
        $status = $this->request->getQuery('status');
        $yearReq = $this->request->getQuery('year');

        $hasGetFilters = $this->request->hasQuery('idnum')
            || $this->request->hasQuery('ref')
            || $this->request->hasQuery('status')
            || $this->request->hasQuery('year')
            || $this->request->hasQuery('iban_to');

        if ($hasGetFilters) {
            $this->session->set('bank_filters', [
                'idnum' => $idnum,
                'ref' => $ref,
                'status' => $status,
                'year' => $yearReq,
            ]);
        } else {
            $filters = $this->session->get('bank_filters', []);
            $idnum = $idnum ?? ($filters['idnum'] ?? '');
            $ref = $ref ?? ($filters['ref'] ?? '');
            $status = $status ?? ($filters['status'] ?? '');
            $yearReq = $yearReq ?? ($filters['year'] ?? '2016');
        }

        // Нормализация года: одно число и для запроса, и для UI
        $year = ($yearReq !== null && $yearReq !== '') ? (int)$yearReq : (int)date('Y');
        $currentYear = $year;

        // ---- сортировка ----
        $ORDER_COLUMNS = [
            0 => 'name_sender', 1 => 'iban_from', 2 => 'amount', 3 => 'iban_to',
            4 => 'paid', 5 => 'comment', 6 => 'id', 7 => 'transactions',
        ];
        $colIdx = (int)$this->request->getQuery('order_col', 'int', 4);
        $dir = strtolower((string)$this->request->getQuery('order_dir', null, 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $ORDER_COLUMNS[$colIdx] ?? 'paid';

        // ---- константы ----
        $IBAN_ALLOW = [
            'KZ256017131000029119', 'KZ606017131000029459', 'KZ236017131000028670', 'KZ686017131000029412',
            'KZ61926180219T620004', 'KZ34926180219T620005', 'KZ07926180219T620006', 'KZ77926180219T620007',
            'KZ196010111000325234', 'KZ896010111000325235', 'KZ466010111000325233', 'KZ736010111000325232',
            'KZ496018871000301461',
        ];

        // ---- диапазон дат (локальная TZ приложения) ----
        $tzApp = new \DateTimeZone('Asia/Almaty');
        $begin = (new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $currentYear), $tzApp))->getTimestamp();
        $end = (new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $currentYear), $tzApp))->getTimestamp();

        // ---- билдер ----
        $b = $this->modelsManager->createBuilder()
            ->from(Bank::class)
            ->columns([
                'id', 'rnn_sender', 'name_sender', 'iban_from', 'amount', 'iban_to', 'paid', 'comment', 'transactions', 'account_num',
            ])
            ->betweenWhere('paid', $begin, $end)
            ->inWhere('iban_to', $IBAN_ALLOW)
            // регистронезависимая фильтрация по словам
            ->andWhere('LOWER(comment) NOT LIKE :dep: AND LOWER(comment) NOT LIKE :perc: AND LOWER(comment) NOT LIKE :reward:', [
                'dep' => '%депозит%', 'perc' => '%процент%', 'reward' => '%выплата вознагражд%',
            ]);

        if ($idnum !== '') {
            $b->andWhere('rnn_sender = :idnum:', ['idnum' => $idnum]);
        }

        if ($ref !== '') {
            // экранирование LIKE-масок и \, с ESCAPE '\'
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

        // ---- пагинация ----
        $paginator = new QueryBuilderPaginator([
            'builder' => $b,
            'limit' => $limit,
            'page' => $page,
        ]);
        $repo = $paginator->paginate();

        // ---- подготовка строк ----
        $rows = [];
        foreach ($repo->getItems() as $bank) {
            /** @var Bank $bank */

            // ссылки на транзакции без хвостящей запятой
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

            // форматирование даты оплаты в TZ приложения
            $paidFmt = '';
            if (!empty($bank->paid)) {
                $paidFmt = (new \DateTimeImmutable('@' . (int)$bank->paid))
                    ->setTimezone($tzApp)
                    ->format('d.m.Y H:i');
            }

            $rows[] = (object)[
                'id' => (int)$bank->id,
                'name_sender' => ($bank->name_sender && $bank->rnn_sender)
                    ? sprintf('%s(%s)', $bank->name_sender, $bank->rnn_sender)
                    : '',
                'iban_from' => (string)$bank->iban_from,
                'amount_fmt' => number_format((float)$bank->amount, 2, ',', ' '),
                'iban_to' => (string)$bank->iban_to,
                'paid_fmt' => $paidFmt,
                'comment' => (string)$bank->comment,
                'transactions' => $links,
            ];
        }

        // ---- годы для селекта ----
        $years = range(2016, 2022);

        // ---- в шаблон ----
        $this->view->setVars([
            'items' => $rows,
            'auth' => $auth,
            'page' => $repo,
            'orderCol' => $colIdx,
            'orderDir' => $dir,
            'years' => $years,
            'filters' => (object)[
                'status' => $status ?? '',
                'idnum' => $idnum ?? '',
                'reference' => $ref ?? '',
                'year' => $currentYear,
                'iban_to' => $iban_to ?? '',
            ],
        ]);
    }

}
