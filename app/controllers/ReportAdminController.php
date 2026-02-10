<?php

namespace App\Controllers;

use CompanyDetail;
use ControllerBase;
use PersonDetail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Role;
use User;
use PhpOffice\PhpSpreadsheet\IOFactory;           // или use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportAdminController extends ControllerBase
{

    public function indexAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            $users = User::find();
            if (count($users) === 0) {
                $this->flash->error("# Пользователи не найдены!");
                return $this->response->redirect("/report_admin/");
            }

            // стили PhpSpreadsheet
            $yellow_style = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'type' => Fill::FILL_SOLID,
                    'color' => ['argb' => Color::COLOR_YELLOW],
                ],
            ];
            $border_all = [
                'borders' => [
                    'allBorders' => [ // в PhpSpreadsheet ключ с большой B
                        'style' => Border::BORDER_THIN,
                        'color' => ['argb' => Color::COLOR_BLACK],
                    ],
                ],
            ];

            $filename = "users_" . time() . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()
                ->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет по внутренним пользователям")
                ->setSubject("Отчет по внутренним пользователям")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Пользователи');

            $sh->setCellValue("A1", "ID")
                ->setCellValue("B1", "Почта")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "ИИН / БИН")
                ->setCellValue("E1", "Дата последнего входа")
                ->setCellValue("F1", "Последний IP")
                ->setCellValue("G1", "Роль")
                ->setCellValue("H1", "Активирован");
            $sh->getStyle('A1:H1')->applyFromArray($yellow_style);

            $row = 2;
            foreach ($users as $user) {
                $role = Role::findFirstById($user->role_id);

                $sh->setCellValue("A{$row}", str_pad($user->id, 5, '0', STR_PAD_LEFT))
                    ->setCellValue("B{$row}", $user->email)
                    ->setCellValue("C{$row}", $user->org_name ?: $user->fio)
                    ->setCellValue("D{$row}", $user->idnum)
                    ->setCellValue("E{$row}", $user->last_login ? date('d.m.Y H:i', $user->last_login) : '—')
                    ->setCellValue("F{$row}", $user->lastip)
                    ->setCellValue("G{$row}", $role ? $role->description : '—')
                    ->setCellValue("H{$row}", $user->active ? 'Активен' : '—');
                $row++;
            }

            $last = $row - 1;
            $sh->getStyle("A1:H{$last}")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(40);
            $sh->getColumnDimension('D')->setWidth(40);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);

            // сохранение через PhpSpreadsheet
            $writer = IOFactory::createWriter($objEx, 'Xlsx'); // или new Xlsx($objEx);
            $writer->save($outEx);

            if (file_exists($outEx)) {
                $this->response
                    ->setHeader('Content-Description', 'File Transfer')
                    ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->setHeader('Content-Disposition', 'attachment; filename="ListUsers_' . date('d.m.Y') . '.xlsx"')
                    ->setHeader('Content-Transfer-Encoding', 'binary')
                    ->setHeader('Content-Length', filesize($outEx))
                    ->sendHeaders();

                readfile($outEx);
                unlink($outEx);
            }
        }
    }

    public function profile_logsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // Получение данных из POST
            $start = $this->request->getPost("dstart", "string");
            $action = $this->request->getPost("action");

            // Убедимся, что action — это массив
            if (!is_array($action)) {
                $action = ['DECLINED', 'APPROVE'];
            }

            // Формирование временных диапазонов
            $startDate = $start ? strtotime($start . ' 00:00:00') : strtotime(date('d.m.Y 00:00:00'));
            $endDate = $start ? strtotime($start . ' 23:59:59') : strtotime(date('d.m.Y 23:59:59'));

            // Запрос данных
            $phql = "
            SELECT pl.id, pl.login, pl.action, pl.profile_id, pl.dt, pl.meta_after, 
                   u.user_type_id, u.fio, u.org_name
            FROM ProfileLogs pl
            JOIN User u ON pl.login = u.idnum
            WHERE pl.login <> 'SYSTEM'
              AND pl.dt BETWEEN :startDate: AND :endDate:
              AND pl.action IN ({actions:array})
            ORDER BY pl.dt ASC
        ";
            $logs = $this->modelsManager->executeQuery($phql, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'actions' => $action, // Передаем массив действий напрямую
            ]);

            // Проверка наличия записей
            if (count($logs) === 0) {
                $this->flash->error("# Записи не найдены!");
                return $this->response->redirect("/report_admin/");
            }

            // Создание Excel-файла
            $filename = "prologs_" . time() . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()
                ->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Действия с заявками")
                ->setSubject("Действия с заявками")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Номер записи")
                ->setCellValue("B1", "ФИО")
                ->setCellValue("C1", "Действие")
                ->setCellValue("D1", "Заявка")
                ->setCellValue("E1", "Дата действия")
                ->setCellValue("F1", "Сообщение / другая информация");

            $yellowStyle = [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => Color::COLOR_YELLOW],
                ],
            ];

            $sh->getStyle("A1:F1")->applyFromArray($yellowStyle);

            $counter = 2;

            foreach ($logs as $log) {
                // Определяем значение u_name на уровне PHP
                $uName = $log->user_type_id == 1 ? $log->fio : $log->org_name;

                $sh->setCellValue("A$counter", str_pad($log->id, 6, '0', STR_PAD_LEFT))
                    ->setCellValue("B$counter", html_entity_decode($uName, ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("C$counter", html_entity_decode($this->translator->_($log->action), ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("D$counter", $log->profile_id)
                    ->setCellValue("E$counter", date('d.m.Y H:i', $log->dt))
                    ->setCellValue("F$counter", $log->action === 'MSG' ? html_entity_decode($log->meta_after, ENT_QUOTES, 'UTF-8') : '—');
                $counter++;
            }

            $borderAll = [
                'borders' => [
                    'allBorders' => [
                        'style' => Border::BORDER_THIN,
                        'color' => ['argb' => Color::COLOR_BLACK],
                    ],
                ],
            ];
            $sh->getStyle("A1:F" . ($counter - 1))->applyFromArray($borderAll);

            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setWidth(50);

            // Сохранение файла
            $objWriter = IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            // Отправка файла пользователю
            if (file_exists($outEx)) {
                $this->response->setHeader('Content-Description', 'File Transfer')
                    ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->setHeader('Content-Disposition', "attachment; filename=\"LogActions_" . date('d.m.Y', $startDate) . ".xlsx\"")
                    ->setHeader('Content-Transfer-Encoding', 'binary')
                    ->setHeader('Cache-Control', 'must-revalidate')
                    ->setHeader('Pragma', 'public')
                    ->setHeader('Content-Length', filesize($outEx))
                    ->sendHeaders();

                readfile($outEx);
                unlink($outEx); // Удаление временного файла
            }
        }
    }

    public function user_logsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            $start = $this->request->getPost("dstart", 'string');
            $end = $this->request->getPost("dend", 'string');

            $startDate = $start ? strtotime($start . ' 00:00:00') : strtotime(date('d.m.Y 00:00:00'));
            $endDate = $end ? strtotime($end . ' 23:59:59') : strtotime(date('d.m.Y 23:59:59'));

            // Запрос данных из базы
            $phql = "
            SELECT ul.id, CONCAT(pd.last_name, ' ', pd.first_name, ' ', pd.parent_name) AS full_name, ul.action, 
                   ul.affected_user_id, ul.dt, ul.info 
            FROM UserLogs ul
            JOIN PersonDetail pd ON pd.user_id = ul.user_id
            JOIN User u ON u.id = ul.user_id
            WHERE ul.dt BETWEEN :startDate: AND :endDate:
            ORDER BY ul.dt ASC
        ";
            $logs = $this->modelsManager->executeQuery($phql, [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            if (count($logs) === 0) {
                $this->flash->error("# Записи не найдены!");
                return $this->response->redirect("/report_admin/");
            }

            // Создание Excel файла
            $filename = "userlogs_" . time() . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()
                ->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Действия пользователей")
                ->setSubject("Действия пользователей")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет действия")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Логи');

            $sh->setCellValue("A1", "ID")
                ->setCellValue("B1", "ФИО")
                ->setCellValue("C1", "Действие")
                ->setCellValue("D1", "ID пользователя")
                ->setCellValue("E1", "Дата действия")
                ->setCellValue("F1", "Информация");

            $yellowStyle = [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => Color::COLOR_YELLOW],
                ],
            ];
            $sh->getStyle("A1:F1")->applyFromArray($yellowStyle);

            $counter = 2;

            foreach ($logs as $log) {
                $sh->setCellValue("A$counter", str_pad($log->id, 6, '0', STR_PAD_LEFT))
                    ->setCellValue("B$counter", html_entity_decode($log->full_name, ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("C$counter", html_entity_decode($log->action, ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("D$counter", $log->affected_user_id)
                    ->setCellValue("E$counter", date('d.m.Y H:i', $log->dt))
                    ->setCellValue("F$counter", html_entity_decode($log->info ?: '—', ENT_QUOTES, 'UTF-8'));
                $counter++;
            }

            $borderAll = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => Color::COLOR_BLACK],
                    ],
                ],
            ];
            $sh->getStyle("A1:F" . ($counter - 1))->applyFromArray($borderAll);

            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setWidth(50);

            // Сохранение файла
            $objWriter = IOFactory::createWriter($objEx, 'Xlsx');

            $objWriter->save($outEx);

            // Отправка файла пользователю
            if (file_exists($outEx)) {
                $this->response->setHeader('Content-Description', 'File Transfer')
                    ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->setHeader('Content-Disposition', "attachment; filename=\"LogsWithUsers_" . date('d.m.Y', $startDate) . '-' . date('d.m.Y', $endDate) . ".xlsx\"")
                    ->setHeader('Content-Transfer-Encoding', 'binary')
                    ->setHeader('Cache-Control', 'must-revalidate')
                    ->setHeader('Pragma', 'public')
                    ->setHeader('Content-Length', filesize($outEx))
                    ->sendHeaders();

                readfile($outEx);
                unlink($outEx); // Удаление временного файла
            }
        }
    }

    public function detection_logsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // Получение диапазона дат
            $start = $this->request->getPost("dstart", "string") ?: date('d.m.Y');
            $end = $this->request->getPost("dend", "string") ?: date('d.m.Y');

            $startDate = strtotime($start . ' 00:00:00');
            $endDate = strtotime($end . ' 23:59:59');

            // Запрос данных через ORM
            $phql = "
            SELECT bl.id, bl.login, bl.action, bl.profile_id, bl.dt, bl.payment_id
            FROM BankLogs bl
            WHERE bl.login <> 'SYSTEM'
              AND bl.dt BETWEEN :startDate: AND :endDate:
            ORDER BY bl.dt ASC
        ";
            $logs = $this->modelsManager->executeQuery($phql, [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            // Проверка на наличие данных
            if (count($logs) === 0) {
                $this->flash->error("# Данные не найдены!");
                return $this->response->redirect("/report_admin/");
            }

            // Создание Excel-файла
            $filename = "bank_logs_" . time() . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()
                ->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Действия с заявками")
                ->setSubject("Действия с заявками")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Id")
                ->setCellValue("B1", "Модератор")
                ->setCellValue("C1", "Действие")
                ->setCellValue("D1", "Номер заявки")
                ->setCellValue("E1", "Дата действия")
                ->setCellValue("F1", "Номер платежа");

            $yellowStyle = [
                'font' => ['bold' => true],
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => Color::COLOR_YELLOW],
                ],
            ];
            $sh->getStyle("A1:F1")->applyFromArray($yellowStyle);

            $counter = 2;

            foreach ($logs as $log) {
                // Определяем имя пользователя
                $person = PersonDetail::findFirstByIin($log->login);
                if ($person) {
                    $name = $person->last_name . ' ' . $person->first_name . ' ' . $person->last_name . " ({$log->login})";
                } else {
                    $company = CompanyDetail::findFirstByBin($log->login);
                    $name = $company ? $company->name . " ({$log->login})" : "Неизвестно ({$log->login})";
                }

                // Заполнение строки в Excel
                $sh->setCellValue("A$counter", $log->id)
                    ->setCellValue("B$counter", html_entity_decode($name, ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("C$counter", html_entity_decode($this->translator->_($log->action), ENT_QUOTES, 'UTF-8'))
                    ->setCellValue("D$counter", $log->profile_id)
                    ->setCellValue("E$counter", date('d.m.Y H:i', $log->dt))
                    ->setCellValue("F$counter", $log->payment_id);
                $counter++;
            }

            $borderAll = [
                'borders' => [
                    'allborders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => Color::COLOR_BLACK],
                    ],
                ],
            ];
            $sh->getStyle("A1:F" . ($counter - 1))->applyFromArray($borderAll);

            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setWidth(50);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setWidth(50);

            // Сохранение файла
            $objWriter = IOFactory::createWriter($objEx, 'Xlsx');

            $objWriter->save($outEx);

            // Отправка файла пользователю
            if (file_exists($outEx)) {
                $this->response->setHeader('Content-Description', 'File Transfer')
                    ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->setHeader('Content-Disposition', "attachment; filename=\"BankLogActions_" . date('d.m.Y', $startDate) . "-" . date('d.m.Y', $endDate) . ".xlsx\"")
                    ->setHeader('Content-Transfer-Encoding', 'binary')
                    ->setHeader('Cache-Control', 'must-revalidate')
                    ->setHeader('Pragma', 'public')
                    ->setHeader('Content-Length', filesize($outEx))
                    ->sendHeaders();

                readfile($outEx);
                unlink($outEx); // Удаление временного файла
            }
        }
    }
}


