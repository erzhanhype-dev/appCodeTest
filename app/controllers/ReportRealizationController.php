<?php
namespace App\Controllers;
use Car;
use CompanyDetail;
use ControllerBase;
use CorrectionLogs;
use Goods;
use PersonDetail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RefCarCat;
use RefCarType;
use RefCountry;
use RefInitiator;
use RefTnCode;
use RefFund;
use User;

set_time_limit(0);

class ReportRealizationController extends ControllerBase
{

    public function indexAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $report_approve_date = $this->request->getPost("report_approve_date");
            $report_agents = $this->request->getPost("report_agents");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($report_approve_date == 1) {
                if ($start) {
                    if ($end) {
                        $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                                strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                    } else {
                        $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                                strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                                strtotime($start)));
                    }
                }
            } else {
                if ($start) {
                    if ($end) {
                        $date_limit = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                                strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                    } else {
                        $date_limit = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                                strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                    }
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            if ($report_agents == 1) {
                $profiles = "SELECT p.initiator_id as initiator_id, p.id as pid, p.created as created, t.amount as amount, p.type as type, t.approve as approve, t.status as status, t.dt_approve as dt_approve, p.agent_status as agent_status FROM profile p JOIN transaction t JOIN user u WHERE t.profile_id = p.id AND u.id = p.user_id AND t.approve = 'GLOBAL' AND u.role_id = 4 $date_limit ORDER BY p.created DESC";
            } else {
                $profiles = "SELECT p.initiator_id as initiator_id, p.id as pid, p.created as created, t.amount as amount, p.type as type, t.approve as approve, t.status as status, t.dt_approve as dt_approve, p.agent_status as agent_status FROM profile p JOIN transaction t WHERE t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.created DESC";
            }

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
            $today = date('d-m-Y');

            $filename = "repreal_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Номер заявки (уникальный)")
                ->setCellValue("B1", "Дата создания")
                ->setCellValue("C1", "Сумма по заявке")
                ->setCellValue("D1", "ФИО / Наименование")
                ->setCellValue("E1", "ИИН / БИН")
                ->setCellValue("F1", "ФЛ / ЮЛ")
                ->setCellValue("G1", "Субконто доходов")
                ->setCellValue("H1", "Инициатор создания заявки")
                ->setCellValue("I1", "Дата выдачи")
                ->setCellValue("J1", "Количество в заявке")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;
            $corr_cnt = 2;
            $is_corrected = false;
            $corrected_objects = [];

            $sqls = array($profiles);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_array($result)) {
                    $initiator_name = '';

                    $cnt++;
                    // если профиль в норме
                    if ($row[1]) {
                        $num = $counter;
                        $counter++;
                        $client = __getClientTitle($row['pid']);

                        if ($client['is_agent'] != false) {
                            $sh->setCellValue("K1", "ФИО агента")
                                ->getStyle('K1')
                                ->applyFromArray($yellow_style);
                        }

                        if (!empty($row['initiator_id'])) {
                            $initiator = RefInitiator::findFirstById($row['initiator_id']);
                            $initiator_name = $initiator ? $initiator->name : '';
                        }
                        // определяем ФЮ / ЮЛ по ИИН
                        $fsign = $client['idnum'][4];
                        $fstatus = 'ФЛ';
                        if ($fsign > 3) {
                            $fstatus = 'ЮЛ';
                        }

                        // определяем субконто
                        $subconto = 'Импорт ТС';
                        if ($row['type'] == 'GOODS') {
                            $subconto = 'Импорт ТБО';
                        }

                        if ($row['type'] == 'KPP') {
                            $subconto = 'Импорт КПП';
                        }

                        if ($row['agent_status'] == 'VENDOR') {
                            $subconto = 'Производитель';

                           if (
                                (($row['type'] ?? null) === 'GOODS') &&
                                is_array($client) &&
                                !empty($client['idnum'])
                            ) {
                                $refFund = RefFund::findFirst([
                                    'conditions' => 'idnum = :idnum: AND entity_type = :entity_type:',
                                    'bind' => [
                                        'idnum' => $client['idnum'],
                                        'entity_type' => 'GOODS',
                                    ],
                                ]);

                                if ($refFund) {
                                    $subconto = 'Производитель автокомпонентов';
                                }
                            }
                        }

                        $car_count = '';
                        $html = '';

                        if ($row['type'] == 'CAR') {
                            $agro_car_count = 0;

                            $cars = Car::findByProfileId($row['pid']);

                            foreach ($cars as $car) {
                                $car_count++;

                                // если car_type tractor(4) или combain(5)
                                if ($car->ref_car_type_id == 4 || $car->ref_car_type_id == 5) {
                                    $agro_car_count++;
                                }

                                $edited_car = CorrectionLogs::find([
                                    "conditions" => "object_id = :cid: and type = :type:",
                                    "bind" => [
                                        "cid" => $car->id,
                                        "type" => "CAR"
                                    ]
                                ]);

                                if ($edited_car) {
                                    foreach ($edited_car as $log) {
                                        $is_corrected = true;
                                        $corrected_objects[] = $log->id;
                                        $html .= ' - Заявка на изменение № ' . $log->id . ' от ' . date(" Y-m-d H:i",
                                                convertTimeZone($log->dt)) . ' года.(' . $this->translator->_($log->action) . ');' . PHP_EOL;
                                    }
                                }
                            }

                            if ($car_count == $agro_car_count) {
                                if ($row['agent_status'] == 'VENDOR') {
                                    $subconto = 'Производитель сельхозтехники';
                                } elseif ($row['agent_status'] == 'IMPORTER') {
                                    $subconto = 'Импорт сельхозтехники';
                                }
                            }
                        } elseif ($row['type'] == 'GOODS') {
                            $goods = Goods::findByProfileId($row['pid']);

                            foreach ($goods as $good) {
                                $edited_good = CorrectionLogs::find([
                                    "conditions" => "object_id = :cid: and type = :type:",
                                    "bind" => [
                                        "cid" => $good->id,
                                        "type" => "GOODS"
                                    ]
                                ]);

                                if ($edited_good) {
                                    foreach ($edited_good as $log) {
                                        $is_corrected = true;
                                        $html .= ' - Заявка на изменение № ' . $log->id . ' от ' . date(" Y-m-d H:i ",
                                                convertTimeZone($log->dt)) . ' года.(' . $this->translator->_($log->action) . ');' . PHP_EOL;
                                        $corrected_objects[] = $log->id;
                                    }
                                }
                            }
                        }

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                            ->setCellValue("B$num", date('d.m.Y', convertTimeZone($row['created'])))
                            ->setCellValue("C$num", $row['amount'])
                            ->setCellValue("D$num", $client['title'])
                            ->setCellValue("E$num", $client['idnum'])
                            ->setCellValue("F$num", $fstatus)
                            ->setCellValue("G$num", $subconto)
                            ->setCellValue("H$num", $initiator_name)
                            ->setCellValue("I$num", date('d.m.Y', convertTimeZone($row['dt_approve'])))
                            ->setCellValue("J$num", $car_count);

                        if ($is_corrected != false) {
                            $sh->setCellValue("J1", "Корректировка / Аннулирование")
                                ->getStyle('J1')
                                ->applyFromArray($yellow_style);
                            $sh->setCellValue("J$num", $html);
                        }

                        if ($client['is_agent'] != false) {
                            $sh->setCellValue("K$num", $client['agent_title']);
                            $sh->getColumnDimension('K')->setWidth(30);
                        }

                        // нулевой платеж
                        if ($row['amount'] == 0) {
                            $sh->setCellValue("C$num", '-');
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_realization/");
            }

            if ($is_corrected != false) {
                $objEx->createSheet();

                // заполняем заявки по ТБО
                $sh = $objEx->setActiveSheetIndex(1);
                $sh->setTitle('История изменения');

                $sh->setCellValue("A1", "ID")
                    ->setCellValue("B1", "Тип / Обьект ID")
                    ->setCellValue("C1", "Пользователь")
                    ->setCellValue("D1", "Действия")
                    ->setCellValue("E1", "Время")
                    ->setCellValue("F1", "Данные до")
                    ->setCellValue("G1", "Данные после")
                    ->setCellValue("H1", "Комментарий")
                    ->getStyle('A1:H1')
                    ->applyFromArray($yellow_style);

                foreach ($corrected_objects as $object) {
                    $nm = $corr_cnt;
                    $corr_cnt++;
                    $before = '';
                    $after = '';

                    $c_log = CorrectionLogs::findFirstById(intval($object));
                    $c_user = PersonDetail::findFirstByUserId($c_log->user_id);

                    $c_type = $this->translator->_($c_log->type);
                    $user_fio = $c_user->last_name . ' ' . $c_user->first_name . ' ' . $c_user->parent_name;

                    if ($c_log->type == 'CAR') {
                        $meta = json_decode($c_log->meta_before, true, 512, JSON_THROW_ON_ERROR);

                        if (!is_array($meta)) {
                            $meta = [];
                        }
                        $car_cat_before = null;

                        foreach ($meta as $l_before) {
                            $country_before = RefCountry::findFirstById($l_before->ref_country);
                            $car_type_before = RefCarType::findFirstById($l_before->ref_car_type_id);
                            $car_cat_before = RefCarCat::findFirstById($l_before->ref_car_cat);
                            if ($l_before->ref_st_type == 0) {
                                $st_type_before = 'НЕТ';
                            } else {
                                $st_type_before = 'ДА';
                            }

                            $before .= 'Год производства: ' . $l_before->year . ';' . PHP_EOL .
                                'Тип: ' . $car_type_before->name . ';' . PHP_EOL .
                                'Категория ТС: ' . $this->translator->_($car_cat_before->name) . ';' . PHP_EOL .
                                'Седельный тягач?: ' . $st_type_before . ';' . PHP_EOL .
                                'Объем / вес: ' . $l_before->volume . ';' . PHP_EOL .
                                'VIN-код / номер: ' . $l_before->vin . ';' . PHP_EOL .
                                'Страна производства: ' . $country_before->name . ';' . PHP_EOL .
                                'Сумма, тенге: ' . $l_before->cost . ' тг ;' . PHP_EOL .
                                'Дата импорта: ' . date("d-m-Y", convertTimeZone($l_before->date_import)) . ';' . PHP_EOL .
                                'Номер заявки: ' . $l_before->profile_id . ';';
                        }

                        foreach (json_decode($c_log->meta_after) as $l_after) {
                            $country_after = RefCountry::findFirstById($l_after->ref_country);
                            $car_type_after = RefCarType::findFirstById($l_after->ref_car_type_id);
                            $car_cat_after = RefCarCat::findFirstById($l_after->ref_car_cat);
                            if ($l_after->ref_st_type == 0) {
                                $st_type_after = 'НЕТ';
                            } else {
                                $st_type_after = 'ДА';
                            }

                            $after .= 'Год производства: ' . $l_after->year . ';' . PHP_EOL .
                                'Тип: ' . $car_type_after->name . ';' . PHP_EOL .
                                'Категория ТС: ' . ($car_cat_before ? $this->translator->_($car_cat_before->name) : '') . ';' . PHP_EOL .
                                'Седельный тягач?: ' . $st_type_after . ';' . PHP_EOL .
                                'Объем / вес: ' . $l_after->volume . ';' . PHP_EOL .
                                'VIN-код / номер: ' . $l_after->vin . ';' . PHP_EOL .
                                'Страна производства: ' . $country_after->name . ';' . PHP_EOL .
                                'Сумма, тенге: ' . $l_after->cost . ' тг ;' . PHP_EOL .
                                'Дата импорта: ' . date("d-m-Y", convertTimeZone($l_after->date_import)) . ';' . PHP_EOL .
                                'Номер заявки: ' . $l_after->profile_id . ';';
                        }
                    } elseif ($c_log->type == 'GOODS') {
                        $meta = json_decode($c_log->meta_before, true, 512, JSON_THROW_ON_ERROR);

                        if (!is_array($meta)) {
                            $meta = [];
                        }
                        foreach ($meta as $l_before) {
                            $g_country_before = RefCountry::findFirstById($l_before->ref_country);
                            $g_tn_before = RefTnCode::findFirstById($l_before->ref_tn);

                            // товар в упаковке
                            $before_tn_add = '';

                            if ($l_before->ref_tn_add != 0) {
                                $tn_add_before = RefTnCode::findFirstById($l_before->ref_tn_add);
                                if ($tn_add_before) {
                                    $before_tn_add = ' (упаковано ' . $tn_add_before->code . ')';
                                }
                            }

                            $before .= 'Страна: ' . $g_country_before->name . ';' . PHP_EOL .
                                'Код ТНВЭД: ' . $g_tn_before->code . '' . $before_tn_add . ';' . PHP_EOL .
                                'Масса товара или упаковки (кг): ' . number_format($l_before->weight, 3) . ';' . PHP_EOL .
                                'Сумма, тенге: ' . $l_before->amount . ' тг ;' . PHP_EOL .
                                'Номер счет-фактуры или ГТД: ' . $l_before->basis . ';' . PHP_EOL .
                                'Дата импорта или реализации: ' . date("d-m-Y",
                                    convertTimeZone($l_before->date_import)) . ';' . PHP_EOL .
                                'Номер заявки: ' . $l_before->profile_id . ';';
                        }

                        foreach (json_decode($c_log->meta_after) as $l_after) {
                            $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                            $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                            // товар в упаковке
                            $before_tn_add = '';

                            if ($l_after->ref_tn_add != 0) {
                                $tn_add_after = RefTnCode::findFirstById($l_after->ref_tn_add);
                                if ($tn_add_before) {
                                    $after_tn_add = ' (упаковано ' . $tn_add_after->code . ')';
                                }
                            }

                            $after .= 'Страна: ' . $g_country_after->name . ';' . PHP_EOL .
                                'Код ТНВЭД: ' . $g_tn_after->code . '' . $after_tn_add . ';' . PHP_EOL .
                                'Масса товара или упаковки (кг): ' . number_format($l_after->weight, 3) . ';' . PHP_EOL .
                                'Сумма, тенге: ' . $l_after->amount . ' тг ;' . PHP_EOL .
                                'Номер счет-фактуры или ГТД: ' . $l_after->basis . ';' . PHP_EOL .
                                'Дата импорта или реализации: ' . date("d-m-Y",
                                    convertTimeZone($l_after->date_import)) . ';' . PHP_EOL .
                                'Номер заявки: ' . $l_after->profile_id . ';';
                        }
                    }

                    $sh->setCellValue("A$nm", $c_log->id)
                        ->setCellValue("B$nm", "$c_type($c_log->object_id)")
                        ->setCellValue("C$nm", "$user_fio($c_log->iin)")
                        ->setCellValue("D$nm", $this->translator->_($c_log->action))
                        ->setCellValue("E$nm", date('d-m-Y H:i', convertTimeZone($c_log->dt)))
                        ->setCellValue("F$nm", $before)
                        ->setCellValue("G$nm", $after)
                        ->setCellValue("H$nm", $c_log->comment);
                }
            }

            // оформление

            $cc = $counter - 1;
            $ccc = $corr_cnt - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:I$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setWidth(50);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setWidth(50);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("D1:D$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("D1:D$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("J1:J$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("J1:J$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("A2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // агентов дооформить
            if ($report_agents == 1) {
                $sh->getStyle("A1:J$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('J')->setAutoSize(true);
                $sh->getStyle("J2:J$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }

            if ($is_corrected != false) {
                $sh = $objEx->setActiveSheetIndex(1);
                $sh->getStyle("A1:H$ccc")->applyFromArray($border_all);
                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setAutoSize(true);
                $sh->getColumnDimension('C')->setWidth(40);
                $sh->getColumnDimension('D')->setWidth(30);
                $sh->getColumnDimension('E')->setAutoSize(true);
                $sh->getColumnDimension('F')->setWidth(45);
                $sh->getColumnDimension('G')->setWidth(45);
                $sh->getColumnDimension('H')->setWidth(50);
                $sh->getStyle("B2:B$ccc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
                $sh->getStyle("E2:E$ccc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:E$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("F2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("C1:C$ccc")->getActiveSheet()->getDefaultRowDimension($ccc)->setRowHeight(-1);
                $sh->getStyle("C1:C$ccc")->getAlignment()->setWrapText(true);
                $sh->getStyle("F1:F$ccc")->getActiveSheet()->getDefaultRowDimension($ccc)->setRowHeight(-1);
                $sh->getStyle("F1:F$ccc")->getAlignment()->setWrapText(true);
                $sh->getStyle("G1:G$ccc")->getActiveSheet()->getDefaultRowDimension($ccc)->setRowHeight(-1);
                $sh->getStyle("G1:G$ccc")->getAlignment()->setWrapText(true);
                $sh->getStyle("H1:H$ccc")->getActiveSheet()->getDefaultRowDimension($ccc)->setRowHeight(-1);
                $sh->getStyle("H1:H$ccc")->getAlignment()->setWrapText(true);
            }

            // ==================================

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . date('d.m.Y', $when) . '.xlsx"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($outEx));
                // читаем файл и отправляем его пользователю
                readfile($outEx);
                // не взумайте вставить сюда exit
            }

            mysqli_close($mc);
        }
    }

    public function detailedAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $report_approve_date = $this->request->getPost("report_approve_date");
            $report_agents = $this->request->getPost("report_agents");

            if (!$start) {
                $start = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $_dur = strtotime($end) - strtotime($start);
                    if ($_dur > (5 * 24 * 3600)) {
                        $end = date('d.m.Y', strtotime($start) + (5 * 24 * 3600));
                    }
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $cars = "SELECT p.id, c.vin, c.cost, c.volume, p.agent_status, ct.name, country.name FROM profile p JOIN car c JOIN ref_car_cat ct JOIN ref_country country JOIN transaction t WHERE c.profile_id = p.id AND c.ref_car_cat = ct.id AND c.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $goods = "SELECT p.id, tn.code, tn.name, g.weight, g.price, g.amount, country.name FROM profile p JOIN goods g JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE g.profile_id = p.id AND g.ref_tn = tn.id AND g.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $kpps = "SELECT p.id, tn.code, tn.name, k.weight, k.amount, country.name FROM profile p JOIN kpp k JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE k.profile_id = p.id AND k.ref_tn = tn.id AND k.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";

            $profiles = "SELECT p.id as pid, t.dt_approve AS dt_approve, p.type as type, t.amount as amount, t.approve as approve, t.status as status, p.agent_type as agent_type, (SELECT CONCAT(`account_num`, '_', `paid`) FROM bank WHERE LOCATE(p.id, `transactions`) LIMIT 1) as ref_num FROM profile p JOIN transaction t WHERE t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";

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
            $today = date('d-m-Y');

            $filename = "repreal_imp_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по ТБО
            $sh = $objEx->setActiveSheetIndex(1);
            $sh->setTitle('ТБО');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по КПП
            $sh = $objEx->setActiveSheetIndex(2);
            $sh->setTitle('КПП');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем VIN
            $sh = $objEx->setActiveSheetIndex(3);
            $sh->setTitle('VIN-коды за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "VIN-код")
                ->setCellValue("C1", "Стоимость, по заявке")
                ->setCellValue("D1", "Объем / вес")
                ->setCellValue("E1", "Статус агента")
                ->setCellValue("F1", "Категория")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $sh->setTitle('ТБО за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Цена за кг")
                ->setCellValue("F1", "Итого, по позиции")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем КПП
            $sh = $objEx->setActiveSheetIndex(5);
            $sh->setTitle('КПП за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Итого, по позиции")
                ->setCellValue("F1", "Страна")
                ->getStyle('A1:F1')
                ->applyFromArray($yellow_style);

            $cars_counter = 2;
            $goods_counter = 2;
            $kpps_counter = 2;
            $cc_counter = 2;
            $gc_counter = 2;
            $kpp_counter = 2;
            $cnt = 0;

            $sqls = array($profiles);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_array($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['pid']) {
                        $client = __getClientTitle($row['pid']);

                        // определяем ФЮ / ЮЛ по ИИН
                        $fsign = $row[4][4];
                        $fsign = $client['idnum'][4];
                        $fstatus = 1;
                        if ($fsign > 3) {
                            $fstatus = 2;
                        }

                        // выбираем активный лист
                        if ($row['type'] == 'CAR') {
                            $sh = $objEx->setActiveSheetIndex(0);
                            $num = $cars_counter;
                            $cars_counter++;
                        } elseif ($row['type'] == 'KPP') {
                            $sh = $objEx->setActiveSheetIndex(2);
                            $num = $kpps_counter;
                            $kpps_counter++;
                        } else {
                            $sh = $objEx->setActiveSheetIndex(1);
                            $num = $goods_counter;
                            $goods_counter++;
                        }

                        // ищем референс или номер платежки
                        $ref_num = '—';
                        $ref_dt = '—';
                        if ($row['ref_num']) {
                            $__rf = explode('_', $row['ref_num']);
                            $ref_num = $__rf[0];
                            $ref_dt = $__rf[1];
                        }
                        // $ref_result = mysqli_query($mc, "SELECT account_num FROM bank WHERE LOCATE ('".$row[0]."', `transactions`) LIMIT 1");
                        // if(mysqli_num_rows($ref_result) > 0) {
                        //   $ref_row = mysqli_fetch_assoc($ref_result);
                        //   $ref_num = $ref_row['account_num'];
                        // }

                        $dt_approve = ($row['dt_approve'] > 0) ? convertTimeZone($row['dt_approve']) : '_';

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", $row['pid'])
                            ->setCellValue("B$num", $client['idnum'])
                            ->setCellValue("C$num", $client['title'])
                            ->setCellValue("D$num", $dt_approve)
                            ->setCellValue("E$num", $row['amount'])
                            ->setCellValue("F$num", $row['approve'])
                            ->setCellValue("G$num", $row['status'])
                            ->setCellValue("H$num", $fstatus)
                            ->setCellValue("I$num", $ref_num)
                            ->setCellValue("J$num", date('d.m.Y', convertTimeZone($ref_dt)));

                        // нулевой платеж
                        if ($row['amount'] == 0) {
                            $sh->setCellValue("E$num", '-');
                        }

                        // пустой статус
                        if ($row['status'] == '') {
                            $sh->setCellValue("F$num", '-');
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_realization/");
            }

            // лист машин
            $sh = $objEx->setActiveSheetIndex(3);
            $result = mysqli_query($mc, $cars);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $cc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", mb_strtoupper(str_replace('cat-', '', $row[5])))
                    ->setCellValue("G$num", $row[6]);
                $cc_counter++;
            }

            // лист ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $result = mysqli_query($mc, $goods);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $gc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5])
                    ->setCellValue("G$num", $row[6]);
                $gc_counter++;
            }

            // лист КПП
            $sh = $objEx->setActiveSheetIndex(5);
            $result = mysqli_query($mc, $kpps);

            while ($row = mysqli_fetch_array($result)) {
                $num = $kpp_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5]);
                $kpp_counter++;
            }

            // оформление

            $cc = $cars_counter - 1;
            $gc = $goods_counter - 1;
            $kppc = $kpps_counter - 1;
            $ccc = $cc_counter - 1;
            $gcc = $gc_counter - 1;
            $kppcc = $kpp_counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:J$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(1);
            $sh->getStyle("A1:J$gc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$gc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$gc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$gc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$gc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(2);
            $sh->getStyle("A1:J$kppc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$kppc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$kppc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$kppc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$kppc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(3);
            $sh->getStyle("A1:G$ccc")->applyFromArray($border_all);
            $sh->getStyle("C2:C$ccc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("B2:B$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(4);
            $sh->getStyle("A1:G$gcc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("F2:F$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(5);
            $sh->getStyle("A1:F$kppcc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$kppcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$kppcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getStyle("F2:F$kppcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$kppcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(0);

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $start . '.xlsx"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($outEx));
                // читаем файл и отправляем его пользователю
                readfile($outEx);
                // не взумайте вставить сюда exit
            }

            mysqli_close($mc);
        }
    }

    public function jdDetailedAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $report_approve_date = $this->request->getPost("report_approve_date");
            $report_agents = $this->request->getPost("report_agents");

            if (!$start) {
                $start = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $_dur = strtotime($end) - strtotime($start);
                    if ($_dur > (5 * 24 * 3600)) {
                        $end = date('d.m.Y', strtotime($start) + (5 * 24 * 3600));
                    }
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $cars = "SELECT p.id, c.vin, c.cost, c.volume, p.agent_status, ct.name, country.name FROM profile p JOIN car c JOIN ref_car_cat ct JOIN ref_country country JOIN transaction t WHERE c.profile_id = p.id AND c.ref_car_cat = ct.id AND c.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $goods = "SELECT p.id, tn.code, tn.name, g.weight, g.price, g.amount, country.name FROM profile p JOIN goods g JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE g.profile_id = p.id AND g.ref_tn = tn.id AND g.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $kpps = "SELECT p.id, tn.code, tn.name, k.weight, k.amount, country.name FROM profile p JOIN kpp k JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE k.profile_id = p.id AND k.ref_tn = tn.id AND k.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";

            $profiles = "SELECT p.id as pid, t.dt_approve AS dt_approve, p.type as type, t.amount as amount, t.approve as approve, t.status as status, p.agent_type as agent_type, (SELECT CONCAT(`account_num`, '_', `paid`) FROM zd_bank WHERE LOCATE(p.id, `transactions`) LIMIT 1) as ref_num FROM profile p JOIN transaction t WHERE t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";

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
            $today = date('d-m-Y');

            $filename = "repreal_imp_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников АО «Жасыл даму»")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("АО «Жасыл даму»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по ТБО
            $sh = $objEx->setActiveSheetIndex(1);
            $sh->setTitle('ТБО');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по КПП
            $sh = $objEx->setActiveSheetIndex(2);
            $sh->setTitle('КПП');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->setCellValue("I1", "Референс оплаты")
                ->setCellValue("J1", "Дата оплаты")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем VIN
            $sh = $objEx->setActiveSheetIndex(3);
            $sh->setTitle('VIN-коды за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "VIN-код")
                ->setCellValue("C1", "Стоимость, по заявке")
                ->setCellValue("D1", "Объем / вес")
                ->setCellValue("E1", "Статус агента")
                ->setCellValue("F1", "Категория")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $sh->setTitle('ТБО за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Цена за кг")
                ->setCellValue("F1", "Итого, по позиции")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем КПП
            $sh = $objEx->setActiveSheetIndex(5);
            $sh->setTitle('КПП за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Итого, по позиции")
                ->setCellValue("F1", "Страна")
                ->getStyle('A1:F1')
                ->applyFromArray($yellow_style);

            $cars_counter = 2;
            $goods_counter = 2;
            $kpps_counter = 2;
            $cc_counter = 2;
            $gc_counter = 2;
            $kpp_counter = 2;
            $cnt = 0;

            $sqls = array($profiles);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_array($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['pid']) {
                        $client = __getClientTitle($row['pid']);

                        // определяем ФЮ / ЮЛ по ИИН
                        $fsign = $row[4][4];
                        $fsign = $client['idnum'][4];
                        $fstatus = 1;
                        if ($fsign > 3) {
                            $fstatus = 2;
                        }

                        // выбираем активный лист
                        if ($row['type'] == 'CAR') {
                            $sh = $objEx->setActiveSheetIndex(0);
                            $num = $cars_counter;
                            $cars_counter++;
                        } elseif ($row['type'] == 'KPP') {
                            $sh = $objEx->setActiveSheetIndex(2);
                            $num = $kpps_counter;
                            $kpps_counter++;
                        } else {
                            $sh = $objEx->setActiveSheetIndex(1);
                            $num = $goods_counter;
                            $goods_counter++;
                        }

                        // ищем референс или номер платежки
                        $ref_num = '—';
                        $ref_dt = '—';
                        if ($row['ref_num']) {
                            $__rf = explode('_', $row['ref_num']);
                            $ref_num = $__rf[0];
                            $ref_dt = $__rf[1];
                        }
                        // $ref_result = mysqli_query($mc, "SELECT account_num FROM bank WHERE LOCATE ('".$row[0]."', `transactions`) LIMIT 1");
                        // if(mysqli_num_rows($ref_result) > 0) {
                        //   $ref_row = mysqli_fetch_assoc($ref_result);
                        //   $ref_num = $ref_row['account_num'];
                        // }

                        $dt_approve = ($row['dt_approve'] > 0) ? convertTimeZone($row['dt_approve']) : '_';

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", $row['pid'])
                            ->setCellValue("B$num", $client['idnum'])
                            ->setCellValue("C$num", $client['title'])
                            ->setCellValue("D$num", $dt_approve)
                            ->setCellValue("E$num", $row['amount'])
                            ->setCellValue("F$num", $row['approve'])
                            ->setCellValue("G$num", $row['status'])
                            ->setCellValue("H$num", $fstatus)
                            ->setCellValue("I$num", $ref_num)
                            ->setCellValue("J$num", date('d.m.Y', convertTimeZone($ref_dt)));

                        // нулевой платеж
                        if ($row['amount'] == 0) {
                            $sh->setCellValue("E$num", '-');
                        }

                        // пустой статус
                        if ($row['status'] == '') {
                            $sh->setCellValue("F$num", '-');
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_realization/");
            }

            // лист машин
            $sh = $objEx->setActiveSheetIndex(3);
            $result = mysqli_query($mc, $cars);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $cc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", mb_strtoupper(str_replace('cat-', '', $row[5])))
                    ->setCellValue("G$num", $row[6]);
                $cc_counter++;
            }

            // лист ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $result = mysqli_query($mc, $goods);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $gc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5])
                    ->setCellValue("G$num", $row[6]);
                $gc_counter++;
            }

            // лист КПП
            $sh = $objEx->setActiveSheetIndex(5);
            $result = mysqli_query($mc, $kpps);

            while ($row = mysqli_fetch_array($result)) {
                $num = $kpp_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5]);
                $kpp_counter++;
            }

            // оформление

            $cc = $cars_counter - 1;
            $gc = $goods_counter - 1;
            $kppc = $kpps_counter - 1;
            $ccc = $cc_counter - 1;
            $gcc = $gc_counter - 1;
            $kppcc = $kpp_counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:J$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(1);
            $sh->getStyle("A1:J$gc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$gc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$gc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$gc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$gc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(2);
            $sh->getStyle("A1:J$kppc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$kppc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("E2:E$kppc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$kppc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:E$kppc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(3);
            $sh->getStyle("A1:G$ccc")->applyFromArray($border_all);
            $sh->getStyle("C2:C$ccc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("B2:B$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(4);
            $sh->getStyle("A1:G$gcc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("F2:F$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(5);
            $sh->getStyle("A1:F$kppcc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$kppcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$kppcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getStyle("F2:F$kppcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$kppcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(0);

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $start . '.xlsx"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($outEx));
                // читаем файл и отправляем его пользователю
                readfile($outEx);
                // не взумайте вставить сюда exit
            }

            mysqli_close($mc);
        }
    }

    public function doldAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $report_approve_date = $this->request->getPost("report_approve_date");
            $report_agents = $this->request->getPost("report_agents");

            if (!$start) {
                $start = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $_dur = strtotime($end) - strtotime($start);
                    if ($_dur > (31 * 24 * 3600)) {
                        $end = date('d.m.Y', strtotime($start) + (5 * 24 * 3600));
                    }
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $cars = "SELECT p.id, c.vin, c.cost, c.volume, p.agent_status, ct.name, country.name FROM profile p JOIN car c JOIN ref_car_cat ct JOIN ref_country country JOIN transaction t WHERE c.profile_id = p.id AND c.ref_car_cat = ct.id AND c.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $goods = "SELECT p.id, tn.code, tn.name, g.weight, g.price, g.amount, country.name FROM profile p JOIN goods g JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE g.profile_id = p.id AND g.ref_tn = tn.id AND g.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $kpps = "SELECT p.id, tn.code, tn.name, k.weight, k.amount, country.name FROM profile p JOIN kpp k JOIN ref_tn_code tn JOIN ref_country country JOIN transaction t WHERE k.profile_id = p.id AND k.ref_tn = tn.id AND k.ref_country = country.id AND t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";
            $profiles = "SELECT p.id as pid, t.dt_approve AS dt_approve, p.type as type, t.amount as amount, t.approve as approve, t.status as status, p.agent_type as agent_type FROM profile p JOIN transaction t WHERE t.profile_id = p.id AND t.approve = 'GLOBAL' $date_limit";

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
            $today = date('d-m-Y');

            $filename = "repreal_imp_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->getStyle('A1:H1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по ТБО
            $sh = $objEx->setActiveSheetIndex(1);
            $sh->setTitle('ТБО');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->getStyle('A1:H1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем заявки по КПП
            $sh = $objEx->setActiveSheetIndex(2);
            $sh->setTitle('КПП');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "ИИН / БИН")
                ->setCellValue("C1", "ФИО / Наименование")
                ->setCellValue("D1", "Дата одобрения")
                ->setCellValue("E1", "Сумма по заявке")
                ->setCellValue("F1", "Статус сертификата")
                ->setCellValue("G1", "Статус оплаты")
                ->setCellValue("H1", "ФЛ (1) / ЮЛ (2)")
                ->getStyle('A1:H1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем VIN
            $sh = $objEx->setActiveSheetIndex(3);
            $sh->setTitle('VIN-коды за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "VIN-код")
                ->setCellValue("C1", "Стоимость, по заявке")
                ->setCellValue("D1", "Объем / вес")
                ->setCellValue("E1", "Статус агента")
                ->setCellValue("F1", "Категория")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $sh->setTitle('ТБО за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Цена за кг")
                ->setCellValue("F1", "Итого, по позиции")
                ->setCellValue("G1", "Страна")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $objEx->createSheet();

            // заполняем KPP(Коды ТНВЭД)
            $sh = $objEx->setActiveSheetIndex(5);
            $sh->setTitle('КПП за период');

            $sh->setCellValue("A1", "Номер заявки")
                ->setCellValue("B1", "Код")
                ->setCellValue("C1", "Название")
                ->setCellValue("D1", "Вес")
                ->setCellValue("E1", "Итого, по позиции")
                ->setCellValue("F1", "Страна")
                ->getStyle('A1:F1')
                ->applyFromArray($yellow_style);

            $cars_counter = 2;
            $goods_counter = 2;
            $kpps_counter = 2;
            $cc_counter = 2;
            $gc_counter = 2;
            $kpp_counter = 2;
            $cnt = 0;

            $sqls = array($profiles);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_array($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['pid']) {
                        $client = __getClientTitle($row['pid']);

                        // определяем ФЮ / ЮЛ по ИИН
                        $fsign = $row[4][4];
                        $fsign = $client['idnum'][4];
                        $fstatus = 1;
                        if ($fsign > 3) {
                            $fstatus = 2;
                        }

                        // выбираем активный лист
                        if ($row['type'] == 'CAR') {
                            $sh = $objEx->setActiveSheetIndex(0);
                            $num = $cars_counter;
                            $cars_counter++;
                        } elseif ($row['type'] == 'KPP') {
                            $sh = $objEx->setActiveSheetIndex(2);
                            $num = $kpps_counter;
                            $kpps_counter++;
                        } else {
                            $sh = $objEx->setActiveSheetIndex(1);
                            $num = $goods_counter;
                            $goods_counter++;
                        }

                        $dt_approve = ($row['dt_approve'] > 0) ? convertTimeZone($row['dt_approve']) : '_';

                        // вставляем строку в листd
                        $sh->setCellValue("A$num", $row['pid'])
                            ->setCellValue("B$num", $client['idnum'])
                            ->setCellValue("C$num", $client['title'])
                            ->setCellValue("D$num", $dt_approve)
                            ->setCellValue("E$num", $row['amount'])
                            ->setCellValue("F$num", $row['approve'])
                            ->setCellValue("G$num", $row['status'])
                            ->setCellValue("H$num", $fstatus);

                        // нулевой платеж
                        if ($row['amount'] == 0) {
                            $sh->setCellValue("E$num", '-');
                        }

                        // пустой статус
                        if ($row['approve'] == '') {
                            $sh->setCellValue("F$num", '-');
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_realization/");
            }

            // лист машин
            $sh = $objEx->setActiveSheetIndex(3);
            $result = mysqli_query($mc, $cars);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $cc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", mb_strtoupper(str_replace('cat-', '', $row[5])))
                    ->setCellValue("G$num", $row[6]);
                $cc_counter++;
            }

            // лист ТБО
            $sh = $objEx->setActiveSheetIndex(4);
            $result = mysqli_query($mc, $goods);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $gc_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5])
                    ->setCellValue("G$num", $row[6]);
                $gc_counter++;
            }

            // лист КПП
            $sh = $objEx->setActiveSheetIndex(5);
            $result = mysqli_query($mc, $kpps);
            // поехали!
            while ($row = mysqli_fetch_array($result)) {
                $num = $kpp_counter;
                // вставляем строку в лист
                $sh->setCellValue("A$num", $row[0])
                    ->setCellValue("B$num", $row[1])
                    ->setCellValue("C$num", $row[2])
                    ->setCellValue("D$num", $row[3])
                    ->setCellValue("E$num", $row[4])
                    ->setCellValue("F$num", $row[5])
                    ->setCellValue("G$num", $row[6]);
                $kpp_counter++;
            }

            // оформление

            $cc = $cars_counter - 1;
            $gc = $goods_counter - 1;
            $kpp = $kpps_counter - 1;
            $ccc = $cc_counter - 1;
            $gcc = $gc_counter - 1;
            $kppc = $kpp_counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:H$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("A2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sh = $objEx->setActiveSheetIndex(1);
            $sh->getStyle("A1:H$gc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(36);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getStyle("B2:B$gc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("E2:E$gc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("A2:I$gc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sh = $objEx->setActiveSheetIndex(2);
            $sh->getStyle("A1:H$kpp")->applyFromArray($border_all);
            $sh->getStyle("E2:E$kpp")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(40);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getStyle("B2:B$kpp")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("A2:H$kpp")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sh = $objEx->setActiveSheetIndex(3);
            $sh->getStyle("A1:G$ccc")->applyFromArray($border_all);
            $sh->getStyle("C2:C$ccc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("B2:B$ccc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

            $sh = $objEx->setActiveSheetIndex(4);
            $sh->getStyle("A1:G$gcc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$gcc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("F2:F$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$gcc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sh = $objEx->setActiveSheetIndex(5);
            $sh->getStyle("A1:F$kppc")->applyFromArray($border_all);
            $sh->getStyle("D2:D$kppc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("E2:E$kppc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setWidth(56);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getStyle("A2:F$kppc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sh = $objEx->setActiveSheetIndex(0);

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $start . '.xlsx"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($outEx));
                // читаем файл и отправляем его пользователю
                readfile($outEx);
                // не взумайте вставить сюда exit
            }

            mysqli_close($mc);
        }
    }

    public function jdActSverkiAction()
    {
        $this->view->disable();

        $start = $this->request->getPost("dstart");
        $end = $this->request->getPost("dend");
        $idnum = $this->request->getPost("idnum");
        $client_name = '-';
        $user_id = 0;

        if (!$start) {
            $start = date('d.m.Y');
        }

        if (!$end) {
            $end = date('d.m.Y');
        }

        if ($idnum) {
            $user = User::findFirstByIdnum($idnum);
            if ($user) {
                $user_id = $user->id;
                if ($user->user_type_id == 1) {
                    $pd = PersonDetail::findFirstByUserId($user->id);
                    $client_name = $pd->last_name . " " . $pd->first_name . " " . $pd->parent_name;
                    $idnum = $pd->iin;
                } else {
                    $cd = CompanyDetail::findFirstByUserId($user->id);
                    $client_name = str_replace("&quot;", "\"", $cd->name);
                    $idnum = $cd->bin;
                }
            } else {
                $this->flash->error("Пользователь с таким ИИН / БИН #$idnum не найден!");
                return $this->response->redirect("/report_realization/");
            }
        }

        $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

        mysqli_set_charset($mc, 'utf8');
        mysqli_select_db($mc, $this->config->database->dbname);

        $yellow_style = array(
            'font' => array('bold' => true),
            'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
            )
        );
        $title_style = array(
            'font' => array('bold' => true),
            'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        );
        $gray_style = array(
            'font' => array('bold' => true),
            'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER),
            'fill' => array('type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => array('rgb' => 'D9D9D9'))
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

        $filename = "sverki_" . $when . '.xlsx';
        $outEx = APP_PATH . '/storage/temp/' . $filename;

        $objEx = new Spreadsheet();

        $objEx->getProperties()->setCreator("recycle.kz")
            ->setLastModifiedBy("recycle.kz")
            ->setTitle("Отчет за " . $start)
            ->setSubject("Отчет за " . $start)
            ->setDescription("Отчет для сотрудников АО «Жасыл даму»")
            ->setKeywords("отчет роп 2016")
            ->setCategory("Отчеты")
            ->setCompany("АО «Жасыл даму»");

        // заполняем заявки по ТС
        $sh = $objEx->setActiveSheetIndex(0);
        $sh->setTitle('Акт сверки');

        $sh->setCellValue("A1", "Акт сверки")
            ->getStyle('A1:H1')
            ->applyFromArray($title_style);

        $sh->setCellValue("A2", "за период с $start по $end между Акционерное общество «Жасыл Даму» и $client_name")
            ->getStyle('A2:H2')
            ->applyFromArray($title_style);

        $sh->mergeCells("A1:H1");
        $sh->mergeCells("A2:H2");

        $sh->setCellValue("A4", "По данным Акционерное общество «Жасыл Даму», KZT")
            ->getStyle('A4:D4')
            ->applyFromArray($gray_style);
        $sh->mergeCells("A4:D4");

        $sh->setCellValue("E4", "По данным $client_name, KZT")
            ->getStyle('E4:H4')
            ->applyFromArray($gray_style);
        $sh->mergeCells("E4:H4");

        $sh->setCellValue("A5", "Дата")
            ->setCellValue("B5", "Документ")
            ->setCellValue("C5", "Дебет")
            ->setCellValue("D5", "Кредит")
            ->setCellValue("E5", "Дата")
            ->setCellValue("F5", "Документ")
            ->setCellValue("G5", "Дебет")
            ->setCellValue("H5", "Кредит")
            ->getStyle('A5:H5')
            ->applyFromArray($yellow_style);

        /***********************************************************************/

        $balance_start_dt = START_ZHASYL_DAMU; // 19.01.2022 00:00:00
        $first_day = strtotime($start . " 00:00:00");
        $last_day = strtotime($end . " 23:59:59");

        $psum_before_period = 0;
        $bsum_before_period = 0;
        $psum_after_period = 0;
        $arrears_before_period = 0;
        $arrears_after_period = 0;
        $debet_end_period = 0;
        $credit_end_period = 0;
        $arrears_all_time = 0;
        $bsum = 0;
        $psum = 0;
        $in_favor = ZHASYL_DAMU;

        $profiles_before_period = mysqli_query($mc, "SELECT p.id as 'pid', 
                                                        t.amount as 't_amount'
                                                    FROM user u
                                                        JOIN profile p
                                                        JOIN transaction t
                                                    WHERE
                                                        t.profile_id = p.id AND
                                                        p.user_id = u.id AND
                                                        t.approve = 'GLOBAL' AND
                                                        t.status = 'PAID' AND
                                                        t.dt_approve >= $balance_start_dt AND 
                                                        t.dt_approve <= $first_day AND
                                                        u.idnum = $idnum
                                                    ORDER BY t.dt_approve ASC");

        $p_before_period = mysqli_num_rows($profiles_before_period);

        if ($p_before_period > 0) {
            while ($pidbefore = mysqli_fetch_assoc($profiles_before_period)) {
                $pid = $pidbefore['pid'];
                $t_amount = $pidbefore['t_amount']; // Размер платежа
                $psum_before_period += $t_amount;

                $bank_before = mysqli_query($mc, "SELECT amount,transactions 
                                                      FROM zd_bank 
                                                      WHERE (transactions = $pid OR transactions LIKE '%$pid,%')");

                $bnk = mysqli_num_rows($bank_before);

                if ($bnk > 0) {
                    while ($bnkbefore = mysqli_fetch_assoc($bank_before)) {
                        $b_before_amount = $bnkbefore['amount'];
                        $bsum_before_period += $b_before_amount;
                    }
                }
            }
        }

        $arrears_before_period = abs(round(($bsum_before_period - $psum_before_period), 2));

        $sh->setCellValue("A6", "Сальдо на начало " . date('d.m.Y', $first_day))
            ->setCellValue("C6", "")
            ->setCellValue("D6", __money($arrears_before_period))
            ->getStyle('A6:D6')
            ->applyFromArray($title_style);

        $sh->mergeCells("A6:B6");

        $sh->setCellValue("E6", "Сальдо на начало " . date('d.m.Y', $first_day))
            ->setCellValue("G6", __money($arrears_before_period))
            ->setCellValue("H6", "")
            ->getStyle('E6:H6')
            ->applyFromArray($title_style);

        $sh->mergeCells("E6:F6");

        $counter = 7;

        $transactions = mysqli_query($mc, "SELECT p.id as 'pid', 
                                           t.amount as 't_amount',
                                           t.dt_approve as 'dt_approve'
                                    FROM user u
                                        JOIN profile p
                                        JOIN transaction t
                                    WHERE
                                        t.profile_id = p.id AND
                                        p.user_id = u.id AND
                                        t.approve = 'GLOBAL' AND
                                        t.status = 'PAID' AND
                                        t.dt_approve >= $first_day AND 
                                        t.dt_approve <= $last_day AND
                                        u.idnum = $idnum
                                    ORDER BY t.dt_approve ASC");

        $tr_count = mysqli_num_rows($transactions);

        if ($tr_count > 0) {
            while ($tran = mysqli_fetch_assoc($transactions)) {
                $profile_id = $tran['pid'];
                $svup_dt = date('d.m.Y H:i', convertTimeZone($tran['dt_approve'])); // Дата одобрение
                $svup = "Заявка № $profile_id от $svup_dt"; // СВУП
                $t_amount = $tran['t_amount']; // Размер платежа
                $psum += $t_amount;

                $sh->setCellValue("A$counter", $svup_dt)
                    ->setCellValue("B$counter", $svup)
                    ->setCellValue("C$counter", __money($t_amount))
                    ->setCellValue("D$counter", "")
                    ->setCellValue("E$counter", $svup_dt)
                    ->setCellValue("F$counter", $svup)
                    ->setCellValue("G$counter", "")
                    ->setCellValue("H$counter", __money($t_amount));

                $counter++;

                $b = mysqli_query($mc, "SELECT account_num,
                                              paid,
                                              amount,
                                              transactions 
                                        FROM zd_bank 
                                        WHERE (transactions = $profile_id OR transactions LIKE '%$profile_id,%')");

                $bk = mysqli_num_rows($b);

                if ($bk > 0) {
                    while ($bank = mysqli_fetch_assoc($b)) {
                        $data = date('d.m.Y H:i', convertTimeZone($bank['paid'])); // Дата
                        $document = "Платежное поручение (входящее) " . $bank['account_num'] . " от " .
                            date('d.m.Y', convertTimeZone($bank['paid'])); // Документ(reference)
                        $amount = $bank['amount']; // Сумма
                        $pids = $bank['transactions']; // Сумма
                        $trs = explode(',', $pids);

                        foreach ($trs as $tr) {
                            if ($tr == $profile_id) {
                                $bsum += $amount;

                                $sh->setCellValue("A$counter", $data)
                                    ->setCellValue("B$counter", $document)
                                    ->setCellValue("C$counter", "")
                                    ->setCellValue("D$counter", __money($amount))
                                    ->setCellValue("E$counter", $data)
                                    ->setCellValue("F$counter", $document)
                                    ->setCellValue("G$counter", __money($amount))
                                    ->setCellValue("H$counter", "");

                                $counter++;
                            }
                        }
                    }
                }
            }
        } else {
            $this->flash->error("# Не найден !");
            return $this->response->redirect("/report_realization/");
        }

        $sh->setCellValue("A$counter", "Обороты за период")
            ->setCellValue("C$counter", __money($psum))
            ->setCellValue("D$counter", __money($bsum))
            ->getStyle("A$counter:D$counter")
            ->applyFromArray($yellow_style);

        $sh->mergeCells("A$counter:B$counter");

        $sh->setCellValue("E$counter", "Обороты за период")
            ->setCellValue("G$counter", __money($bsum))
            ->setCellValue("H$counter", __money($psum))
            ->getStyle("E$counter:H$counter")
            ->applyFromArray($yellow_style);

        $sh->mergeCells("E$counter:F$counter");

        $counter = $counter + 1;

        $arrears_after_period = abs(round(($bsum - $psum), 2));
        if ($bsum > $psum) {
            $in_favor = $client_name;
        }
        $arrears_all_time = round(($arrears_after_period + $arrears_before_period), 2);

        $sh->setCellValue("A$counter", "Сальдо на " . date('d.m.Y H:i', $last_day))
            ->setCellValue("C$counter", "")
            ->setCellValue("D$counter", __money($arrears_after_period))
            ->getStyle("A$counter:D$counter")
            ->applyFromArray($title_style);

        $sh->setCellValue("E$counter", "Сальдо на " . date('d.m.Y H:i', $last_day))
            ->setCellValue("G$counter", __money($arrears_after_period))
            ->setCellValue("H$counter", "")
            ->getStyle("E$counter:H$counter")
            ->applyFromArray($title_style);

        $last_line = $counter + 3;

        $sh->setCellValue("A$last_line", "Задолженность за период " . date('d.m.Y H:i', $first_day) . " ~ " .
            date('d.m.Y H:i', $last_day) . " в пользу " . $in_favor . " " . __money($arrears_after_period) . " тг")
            ->getStyle("A$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:H$last_line");

        $last_line += 3;

        $sh->setCellValue("A$last_line", "От: Акционерное общество «Жасыл даму»")
            ->getStyle("A$last_line:D$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:D$last_line");

        $sh->setCellValue("E$last_line", "От: $client_name")
            ->getStyle("E$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("E$last_line:H$last_line");

        $last_line += 1;

        $sh->setCellValue("A$last_line", "ИИН / БИН: " . ZHASYL_DAMU_BIN)
            ->getStyle("A$last_line:D$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:D$last_line");

        $sh->setCellValue("E$last_line", "ИИН / БИН: $idnum")
            ->getStyle("E$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("E$last_line:H$last_line");

        $last_line += 3;

        $sh->setCellValue("A$last_line", "──────────────────")
            ->getStyle("A$last_line:D$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:D$last_line");

        $sh->setCellValue("E$last_line", "──────────────────")
            ->getStyle("E$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("E$last_line:H$last_line");

        $last_line += 3;

        $sh->setCellValue("A$last_line", "────────────────────────────────────(──────────────────)")
            ->getStyle("A$last_line:D$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:D$last_line");

        $sh->setCellValue("E$last_line", "────────────────────────────────────(──────────────────)")
            ->getStyle("E$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("E$last_line:H$last_line");

        $last_line += 1;

        $sh->setCellValue("A$last_line", "М.П.")
            ->getStyle("A$last_line:D$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("A$last_line:D$last_line");

        $sh->setCellValue("E$last_line", "М.П.")
            ->getStyle("E$last_line:H$last_line")
            ->applyFromArray($title_style);

        $sh->mergeCells("E$last_line:H$last_line");

        /***********************************************************************/

        $cc = $counter;

        $sh = $objEx->setActiveSheetIndex(0);
        $sh->getStyle("A4:H$cc")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setWidth(10);
        $sh->getColumnDimension('B')->setWidth(30);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getColumnDimension('D')->setAutoSize(true);
        $sh->getColumnDimension('E')->setWidth(10);
        $sh->getColumnDimension('F')->setWidth(30);
        $sh->getColumnDimension('G')->setAutoSize(true);
        $sh->getColumnDimension('H')->setAutoSize(true);
        $sh->getStyle("A7:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("A7:A$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
        $sh->getStyle("A7:A$cc")->getAlignment()->setWrapText(true);
        $sh->getStyle("B7:B$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
        $sh->getStyle("B7:B$cc")->getAlignment()->setWrapText(true);
        $sh->getStyle("E7:E$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
        $sh->getStyle("E7:E$cc")->getAlignment()->setWrapText(true);
        $sh->getStyle("F7:F$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
        $sh->getStyle("F7:F$cc")->getAlignment()->setWrapText(true);
        $sh->getStyle("A$cc:H$last_line")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // ==================================

        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
        $objWriter->save($outEx);

        if (file_exists($outEx)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header("Accept-Charset: utf-8");
            header('Content-Type: application/octet-stream; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . date('d.m.Y', $when) . '.xlsx"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($outEx));
            readfile($outEx);
        }

        mysqli_close($mc);
    }

    public function correctionsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " AND cl.dt >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND cl.dt <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $c_logs = "SELECT cl.id AS cl_id,
                            cl.initiator_id as initiator_id,
                          cl.type As cc_type,
                          cl.profile_id AS profile_id,
                          cl.dt AS dt,
                          cl.meta_before AS meta_before,
                          cl.meta_after AS meta_after,
                          IF(u.user_type_id = 1, fio, org_name) AS title,
                          u.idnum AS idnum,
                          IF(u.user_type_id = 1, 'ФЛ', 'ЮЛ') AS user_type,
                          IF(cl.type = 'CAR', (SELECT COUNT(id) FROM car c WHERE profile_id = cl.profile_id), '') AS car_count,
                          CASE WHEN cl.type = 'CAR'
                               THEN IF((c.ref_car_cat = 13 OR c.ref_car_cat = 14), 'Производитель сельхозтехники', 'Импорт ТС')
                               ELSE 'Импорт ТБО'
                          END AS subconto_income 
                    FROM correction_logs AS cl
                    JOIN profile AS p ON cl.profile_id = p.id 
                    JOIN user AS u ON p.user_id = u.id
                    LEFT JOIN car AS c ON cl.object_id = c.id AND cl.profile_id = c.profile_id AND cl.type = 'CAR' 
                    WHERE cl.accountant_id > 0 
                   $date_limit";

            $cc_logs = "SELECT cl.id AS cl_id,
                            ccp.initiator_id as initiator_id,
                          cl.type As cc_type,
                          ccp.profile_id AS profile_id,
                          cl.dt AS dt,
                          cl.meta_before AS meta_before,
                          cl.meta_after AS meta_after,
                          IF(u.user_type_id = 1, fio, org_name) AS title,
                          u.idnum AS idnum,
                          IF(u.user_type_id = 1, 'ФЛ', 'ЮЛ') AS user_type,
                          IF(cl.type = 'CAR', (SELECT COUNT(id) FROM car c WHERE profile_id = ccp.profile_id), '') AS car_count,
                          CASE WHEN cl.type = 'CAR'
                               THEN IF((c.ref_car_cat = 13 OR c.ref_car_cat = 14), 'Производитель сельхозтехники', 'Импорт ТС')
                               ELSE 'Импорт ТБО'
                          END AS subconto_income 
                    FROM client_correction_logs AS cl
                    LEFT JOIN client_correction_profile ccp ON cl.ccp_id = ccp.id
                    JOIN profile AS p ON ccp.profile_id = p.id 
                    JOIN user AS u ON p.user_id = u.id
                    LEFT JOIN car AS c ON cl.object_id = c.id AND ccp.profile_id = c.profile_id AND cl.type = 'CAR' 
                    WHERE ccp.accountant_id > 0 $date_limit AND NOT EXISTS (
                  SELECT 1
                  FROM correction_logs cl2
                  WHERE cl2.profile_id = ccp.profile_id
                    AND cl2.accountant_id > 0
              )";       

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
            $today = date('d-m-Y');

            $filename = "correction_logs_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников АО «Жасыл даму»")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("АО «Жасыл даму»");

            $sh = $objEx->setActiveSheetIndex(0);

            $sh->setCellValue("A1", "Номер заявки (уникальный)")
                ->setCellValue("B1", "Дата одобрения")
                ->setCellValue("C1", "Сумма разницы по заявке (+/-)")
                ->setCellValue("D1", "ФИО / Наименование")
                ->setCellValue("E1", "ИИН / БИН")
                ->setCellValue("F1", "ФЛ / ЮЛ")
                ->setCellValue("G1", "Субконто доходов")
                ->setCellValue("H1", "Количество в заявке")
                ->setCellValue("I1", "Инициатор корректировки")
                ->getStyle('A1:I1')
                ->applyFromArray($yellow_style);

            $logs_counter = 2;
            $count = 0;
            $sqls = array($c_logs);
            $cc_sqls = array($cc_logs);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);

                while ($row = mysqli_fetch_array($result)) {
                    $count++;
                    $num = $logs_counter;
                    $diff = 0;
                    $initiator_name = '-';

                    if ($row['initiator_id']) {
                        $initiator = RefInitiator::findFirstById($row['initiator_id']);
                        if($initiator) {
                            $initiator_name = $initiator->name;
                        }
                    }

                    $unique_number = __getProfileNumber($row['profile_id']) . '/1';

                    if ($row['meta_before'] != null && $row['meta_before'] != '-') {
                        $before = json_decode($row['meta_before'], true);
                    }
                    if ($row['meta_after'] != null && $row['meta_after'] != '-') {
                        $after = json_decode($row['meta_after'], true);
                    }

                    if ($row['cc_type'] == 'CAR') {
                        $diff = $after[0]['cost'] - $before[0]['cost'];
                    }else{
                        if($after[0]['amount'] == $before[0]['amount']){
                            $diff = $after[0]['package_cost'] - $before[0]['package_cost'];
                        }else{
                            $diff = $after[0]['amount'] - $before[0]['amount'];
                        }
                    }

                    $diff_value = ($diff > 0) ? __money($diff) : $diff;

                    $sh->setCellValue("A$num", $unique_number)
                        ->setCellValue("B$num", date('d.m.Y H:i', convertTimeZone($row['dt'])))
                        ->setCellValue("C$num", $diff_value)
                        ->setCellValue("D$num", $row['title'])
                        ->setCellValue("E$num", $row['idnum'])
                        ->setCellValue("F$num", $row['user_type'])
                        ->setCellValue("G$num", $row['subconto_income'])
                        ->setCellValue("H$num", $row['car_count'])
                        ->setCellValue("I$num", $initiator_name);

                    $logs_counter++;
                }
            }

            foreach ($cc_sqls as $sql) {
                $result = mysqli_query($mc, $sql);

                while ($row = mysqli_fetch_array($result)) {
                    $count++;
                    $num = $logs_counter;
                    $diff = 0;
                    $initiator_name = '-';

                    if ($row['initiator_id']) {
                        $initiator = RefInitiator::findFirstById($row['initiator_id']);
                        if($initiator) {
                            $initiator_name = $initiator->name;
                        }
                    }

                    $unique_number = __getProfileNumber($row['profile_id']) . '/1';

                    if ($row['meta_before'] != null && $row['meta_before'] != '-') {
                        $before = json_decode($row['meta_before'], true);
                    }
                    if ($row['meta_after'] != null && $row['meta_after'] != '-') {
                        $after = json_decode($row['meta_after'], true);
                    }

                    if ($row['cc_type'] == 'CAR') {
                        $diff = $after[0]['cost'] - $before[0]['cost'];
                    }else{
                        if($after[0]['amount'] == $before[0]['amount']){
                            $diff = $after[0]['package_cost'] - $before[0]['package_cost'];
                        }else{
                            $diff = $after[0]['amount'] - $before[0]['amount'];
                        }
                    }

                    $diff_value = ($diff > 0) ? __money($diff) : $diff;

                    $sh->setCellValue("A$num", $unique_number)
                        ->setCellValue("B$num", date('d.m.Y H:i', convertTimeZone($row['dt'])))
                        ->setCellValue("C$num", $diff_value)
                        ->setCellValue("D$num", $row['title'])
                        ->setCellValue("E$num", $row['idnum'])
                        ->setCellValue("F$num", $row['user_type'])
                        ->setCellValue("G$num", $row['subconto_income'])
                        ->setCellValue("H$num", $row['car_count'])
                        ->setCellValue("I$num", $initiator_name);

                    $logs_counter++;
                }
            }

            if ($count == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_realization/");
            }

            $cc = $logs_counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:H$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setWidth(50);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getStyle("A2:C$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("D2:D$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("E2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $start . '.xlsx"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($outEx));
                readfile($outEx);
            }

            mysqli_close($mc);
        }
    }
}
