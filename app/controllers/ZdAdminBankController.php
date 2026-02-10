<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ProfileLogs;
use Transaction;
use User;
use ZdBank;
use ZdBankLogs;

class ZdAdminBankController extends ControllerBase
{
    public function bankAction(): void
    {
        // Проверка авторизации
        $auth = null;
        if (class_exists('User')) {
            $auth = User::getUserBySession();
        }

        // ---- 1. Сброс фильтров ----
        if ($this->request->getQuery('clear_filters', 'int', 0)) {
            $this->session->remove('zd_bank_filters');
            $this->response->redirect('/zd_admin_bank/bank');
            return;
        }

        // ---- 2. Получение и сохранение фильтров (Sticky Filters) ----
        $filters = $this->session->get('zd_bank_filters', []);
        
        // Список полей, которые мы фильтруем
        $filterKeys = ['idnum', 'status', 'year', 'iban_to', 'reference'];
        $hasAnyNewFilter = false;

        foreach ($filterKeys as $key) {
            if ($this->request->hasQuery($key)) {
                $val = $this->request->getQuery($key);
                $filters[$key] = $val;
                $hasAnyNewFilter = true;
            }
        }

        // Если в сессии всё еще нет года, ставим текущий
        if (empty($filters['year'])) {
            $filters['year'] = date('Y');
        }

        $this->session->set('zd_bank_filters', $filters);

        // Распределяем переменные для удобства
        $idnum     = $filters['idnum'] ?? '';
        $status    = $filters['status'] ?? '';
        $year      = (int)$filters['year'];
        $iban_to   = $filters['iban_to'] ?? '';
        $ref       = (string)($filters['reference'] ?? '');

        // ---- 3. Логика пагинации ----
        $page = (int)$this->request->getQuery('page', 'int', 1);

        // Если пользователь отправил форму (есть фильтры в GET, но нет номера страницы) — сбрасываем на 1
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

        // ---- 4. Константы IBAN ----
        $IBAN_ALLOW = [
            'KZ02601A871001725251', 'KZ07601A871001726131', 'KZ20601A871001726091',
            'KZ28601A871001726141', 'KZ41601A871001726101', 'KZ62601A871001726111',
            'KZ70601A871001726161', 'KZ49601A871001726151', 'KZ83601A871001726121',
        ];

        // Финальный список IBAN для SQL
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
        $modelClass = 'ZdBank';
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
            $b->andWhere('(account_num = :ref: OR comment LIKE :refLike:)', [
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

        // ---- 7. Инициализация пагинатора ----
        $paginator = new QueryBuilderPaginator([
            'builder' => $b,
            'limit'   => $limit,
            'page'    => $page,
        ]);
        
        $repo = $paginator->paginate();

        // ---- 8. Подготовка данных для View ----
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

        // Годы и IBAN для селектов
        $yearsRange = range(2022, (int)date('Y'));
        $constantNames = [
            'IBAN_VENDOR_HAS_FUND', 'IBAN_VENDOR_HAS_NOT_FUND', 'IBAN_IMPORT',
            'IBAN_AGRO_VENDOR', 'IBAN_AGRO_IMPORTER', 'IBAN_GOODS_VENDOR',
            'IBAN_GOODS_IMPORTER', 'IBAN_KPP_IMPORTER', 'IBAN_KPP_VENDOR'
        ];

        $ibansForFilter = [];
        foreach ($constantNames as $name) {
            if (defined($name)) {
                $ibansForFilter[] = constant($name);
            }
        }

        $this->view->setVars([
            'items'    => $rows,
            'auth'     => $auth,
            'page'     => $repo,
            'orderCol' => $colIdx,
            'orderDir' => $dir,
            'years'    => $yearsRange,
            'ibans'    => $ibansForFilter,
            'filters'  => (object)[
                'status'    => $status,
                'idnum'     => $idnum,
                'reference' => $ref,
                'year'      => $year,
                'iban_to'   => is_array($iban_to) ? $iban_to : (empty($iban_to) ? [] : [$iban_to]),
            ],
        ]);
    }

    /**
     * @throws AppException
     */
    public function setAction($id)
    {
        set_time_limit(0);
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $p_back = $this->request->getPost("profile_back");
            $bank_id = $this->request->getPost("bank_id");
            $tr_id = $this->request->getPost("tr_id");

            $bank = ZdBank::findFirstById($bank_id);

            $tr_id = str_replace(' ', '', $tr_id);
            $oldStr = (string)($bank->transactions ?? '');
            $newStr = (string)($tr_id ?? '');

            $old_trs = array_values(array_filter(explode(',', str_replace(' ', '', $oldStr)), 'strlen'));
            $trs     = array_values(array_filter(explode(',', str_replace(' ', '', $newStr)), 'strlen'));

            $trs_to_create = array_diff($trs, $old_trs);
            $trs_to_remove = array_diff($old_trs, $trs);

            if ($bank_id >= 450230) {
                foreach ($trs as $tr) {
                    $transaction = Transaction::findFirstByProfileId($tr);
                    if ($transaction->status == 'NOT_PAID') {
                        $trs_to_create[] = $transaction->profile_id;
                    }
                }
            }

            $allowed_bins = ['040340008429'];

            if (!in_array($auth->bin, $allowed_bins)) {
                $message = "У вас нет прав на это действие!";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/zd_admin_bank/bank/");
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
                        $this->flash->error("Заявка $t не найдена !");
                        return $this->response->redirect("/zd_admin_bank/bank/");
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
                $l = new ZdBankLogs();
                $l->login = $auth->idnum;
                $l->action = 'change_payment';
                $l->payment_id = $bank_id;
                $l->profile_id = join(', ', $trs);
                $l->dt = time();
                $l->meta = json_encode($_SESSION['auth']);
                $l->save();
            }

            $this->logAction('Привязка оплаты');

            if ($p_back > 0) {
                return $this->response->redirect("/moderator_order/view/$p_back");
            } else {
                return $this->response->redirect("/zd_admin_bank/bank/");
            }
        } else {
            $bank = ZdBank::findFirstById($id);
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

    public function downloadAction()
    {
        $this->view->disable();

        $idnum = $this->request->getPost('idnum');
        $reference = $this->request->getPost('reference');
        $status = $this->request->getPost('status');
        $year = $this->request->getPost('year');
        $iban_to = $this->request->getPost('iban_to');

        foreach ($iban_to as &$val) {
            $val = "'$val'";
        }

        $comma_separated_iban_list = implode(",", $iban_to);

        $search_by_iban = 'AND iban_to IN ("KZ02601A871001725251", "KZ07601A871001726131", "KZ20601A871001726091", 
                "KZ28601A871001726141", "KZ41601A871001726101", "KZ62601A871001726111", "KZ70601A871001726161",
                "KZ49601A871001726151", "KZ83601A871001726121")';

        $searchQuery = '';
        $dt_begin = strtotime(date("$year-01-01 00:00:00"));
        $dt_end = strtotime(date("$year-12-31 23:59:59"));
        $searchQuery .= " AND paid >= $dt_begin AND paid <= $dt_end ";

        if ($idnum != '') {
            $searchQuery .= " AND rnn_sender = '$idnum' ";
        }
        if ($reference != '') {
            $searchQuery .= " AND account_num = $reference ";
        }
        if (count($iban_to) > 0) {
            $search_by_iban = " AND iban_to IN ($comma_separated_iban_list) ";
        }
        if ($status != '') {
            if ($status == 'SET') {
                $searchQuery .= " AND (transactions <> '' OR transactions IS NOT NULL)";
            }
            if ($status == 'NOT_SET') {
                $searchQuery .= " AND (transactions = '' OR transactions IS NULL)";
            }
        }

        $searchQuery .= " AND comment NOT LIKE '%депозит%' AND comment NOT LIKE '%процент%' AND comment NOT LIKE '%Выплата вознагражд%' $search_by_iban";

        $query = $this->modelsManager->createQuery("SELECT COUNT(*) as allcount FROM ZdBank WHERE 1 $searchQuery");
        $records = $query->execute();
        $totalRecordwithFilter = $records[0]->allcount;

        if ($totalRecordwithFilter > 40000) {
            http_response_code(403);
            die('Forbidden');
        }

        $sql = <<<SQL
          SELECT 
            id, rnn_sender, name_sender, iban_from, amount, iban_to, paid, comment 
          FROM ZdBank 
          WHERE 1
           {$searchQuery}
          ORDER BY paid DESC
        SQL;
        $query = $this->modelsManager->createQuery($sql);
        $banks = $query->execute();

        $yellow_style = array(
            'font' => array('bold' => true),
            'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
            )
        );
        $border_all = array(
            'borders' => array(
                'allborders' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK)
                )
            )
        );

        $when = time();

        $filename = "jd_bank_list_" . $when . '.xlsx';
        $outEx = APP_PATH . '/storage/temp/' . $filename;

        $this->logAction('Формирование отчета','access');
        $objEx = new Spreadsheet();

        $objEx->getProperties()->setCreator("recycle.kz")
            ->setLastModifiedBy("recycle.kz")
            ->setTitle("Отчет по внутренним пользователям")
            ->setSubject("Отчет по внутренним пользователям")
            ->setDescription("Отчет для сотрудников АО «Жасыл даму»")
            ->setKeywords("отчет АО «Жасыл даму» 2023")
            ->setCategory("Отчеты")
            ->setCompany("АО «Жасыл даму»");

        $sh = $objEx->setActiveSheetIndex(0);
        $sh->setTitle('Банковские транзакции');

        $sh->setCellValue("A1", "ФИО, Название / БИН, ИИН отправителя")
            ->setCellValue("B1", "IBAN отправителя")
            ->setCellValue("C1", "Размер платежа")
            ->setCellValue("D1", "IBAN получателя")
            ->setCellValue("E1", "Дата и время")
            ->setCellValue("F1", "Назначение")
            ->getStyle('A1:F1')
            ->applyFromArray($yellow_style);

        $counter = 2;

        if (count($banks) > 0) {
            foreach ($banks as $bank) {
                $num = $counter;
                $counter++;

                $amount = __money($bank->amount);

                $sh->setCellValue("A$num", "$bank->name_sender($bank->rnn_sender)")
                    ->setCellValue("B$num", $bank->iban_from)
                    ->setCellValue("C$num", $amount)
                    ->setCellValue("D$num", $bank->iban_to)
                    ->setCellValue("E$num", date("d.m.Y H:i", convertTimeZone($bank->paid)))
                    ->setCellValue("F$num", $bank->comment);
            }
        }

        $cc = $counter - 1;

        $sh = $objEx->setActiveSheetIndex(0);
        $sh->getStyle("A1:F$cc")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setWidth(50);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getColumnDimension('D')->setAutoSize(true);
        $sh->getColumnDimension('E')->setAutoSize(true);
        $sh->getColumnDimension('F')->setWidth(100);
        $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sh->getStyle("B2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("F2:F$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sh->getStyle("F1:F$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
        $sh->getStyle("F1:F$cc")->getAlignment()->setWrapText(true);

        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
        $objWriter->save($outEx);

        if (file_exists($outEx)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header("Accept-Charset: utf-8");
            header('Content-Type: application/octet-stream; charset=utf-8');
            header('Content-Disposition: attachment; filename="JD_BANK_LIST_' . date('d.m.Y', $when) . '.xlsx"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($outEx));
            readfile($outEx);
        }
    }

}
