<?php

namespace App\Controllers;

set_time_limit(0);
ini_set('memory_limit', '1024M');


use CompanyDetail;
use ControllerBase;
use FundCarHistories;
use FundGoodsHistories;
use PersonDetail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Profile;
use RefCarCat;
use RefCountry;
use RefTnCode;
use User;

class ReportImporterController extends ControllerBase
{
    public function indexAction()
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " AND t.dt_approve >= " . strtotime($start . ' 00:00:00') . " AND t.dt_approve <= " . strtotime($end . ' 23:59:59');
                } else {
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            $start)) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", $start));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);
            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $c_car = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, CONCAT('-') as tnved, CONCAT('1') as volume, car.date_import as date_import, rc.name as country, car.cost as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN car JOIN ref_country rc WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND car.profile_id = p.id AND car.ref_country = rc.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $a_car = "SELECT p.id as pid, p.agent_name AS title, p.agent_status as pstatus, p.agent_iin as bin, p.type as ptype, CONCAT('-') as tnved, CONCAT('1') as volume, car.date_import as date_import, rc.name as country, car.cost as cost FROM user u JOIN profile p JOIN transaction t JOIN ref_country rc JOIN car WHERE u.id = p.user_id AND t.profile_id = p.id AND car.profile_id = p.id AND car.ref_country = rc.id AND p.agent_type = 2 AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $c_goods = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, tncode.code as tnved, goods.weight as volume, goods.date_import as date_import, rc.name as country, goods.amount as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN goods JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND goods.profile_id = p.id AND goods.ref_country = rc.id AND goods.ref_tn = tncode.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $a_goods = "SELECT p.id as pid, p.agent_name AS title, p.agent_status as pstatus, p.agent_iin as bin, p.type as ptype, tncode.code as tnved, goods.weight as volume, goods.date_import as date_import, rc.name as country, goods.amount as cost FROM user u JOIN profile p JOIN transaction t JOIN goods JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND t.profile_id = p.id AND goods.profile_id = p.id AND goods.ref_country = rc.id AND goods.ref_tn = tncode.id AND p.agent_type = 2 AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $kpps = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, tncode.code as tnved, k.weight as volume, k.date_import as date_import, rc.name as country, k.amount as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN kpp k JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND k.profile_id = p.id AND k.ref_country = rc.id AND k.ref_tn = tncode.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $p_car = [];
            $p_goods = [];
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
                ->setCellValue("B1", "Наименование компании / ФИО")
                ->setCellValue("C1", "Импортер / производитель")
                ->setCellValue("D1", "БИН / ИИН")
                ->setCellValue("E1", "Товар / ТС")
                ->setCellValue("F1", "Код ТН ВЭД")
                ->setCellValue("G1", "Объем")
                ->setCellValue("H1", "Платеж")
                ->setCellValue("I1", "Дата импорта / реализации")
                ->setCellValue("J1", "Страна")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($c_car, $a_car, $c_goods, $a_goods, $kpps);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['pid']) {
                        // если указано ФИО или название
                        if ($row['title']) {
                            $num = $counter;
                            $counter++;

                            // определяем ФЮ / ЮЛ по ИИН
                            // $fsign = $row['bin'][4];
                            // $fstatus = 'ФЛ';
                            // if($fsign > 3) {
                            //   $fstatus = 'ЮЛ';
                            // }

                            // определяем субконто
                            $subconto1 = 'ТС';
                            if ($row['ptype'] == 'GOODS') {
                                $subconto1 = 'ТБО';
                            }

                            if ($row['ptype'] == 'KPP') {
                                $subconto1 = 'КПП';
                            }

                            $subconto2 = 'Импортер';
                            if ($row['pstatus'] == 'VENDOR') {
                                $subconto2 = 'Производитель';
                            }

                            $price = '—';
                            if ($row['cost'] > 0) {
                                $price = $row['cost'];
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $row['title'])
                                ->setCellValue("C$num", $subconto1)
                                ->setCellValue("D$num", $row['bin'])
                                ->setCellValue("E$num", $subconto2)
                                ->setCellValue("F$num", $row['tnved'])
                                ->setCellValue("G$num", $row['volume'])
                                ->setCellValue("H$num", $price)
                                ->setCellValue("I$num", date('d.m.Y', convertTimeZone($row['date_import'])))
                                ->setCellValue("J$num", $row['country']);
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:J$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(50);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("G2:G$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("H2:H$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("F2:F$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("D2:D$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("I2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("H2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("J2:J$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sh->getStyle("C2:C$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
        } else {
            // если это простой просмотр форм
            $phql = "
    SELECT f.user_id AS user_id,
           MAX(f.id) AS id,
           cd.bin AS bin,
           u.idnum AS idnum,
           cd.name AS name
    FROM FundProfile f
    JOIN User u ON f.user_id = u.id
    JOIN CompanyDetail cd ON f.user_id = cd.user_id
    GROUP BY f.user_id, cd.bin, u.idnum, cd.name
    ORDER BY id DESC";

            $cache = $this->di->get('cache');
            $countriesKey = 'ref_countries_list';

            $ref_country = $cache->get($countriesKey);

            if ($ref_country === null) {
                $ref_country = RefCountry::find(['id <> 1']);
                $cache->set($countriesKey, $ref_country, 86400); // Сохраняем на 24 часа
            }

            $this->view->setVar('ref_country', $ref_country);
            $tnCodesKey = 'ref_tn_codes_filtered';
            $tn_codes = $cache->get($tnCodesKey);
            if ($tn_codes === null) {
                $tn_codes = RefTnCode::find(["id NOT IN (873, 874)"]);
                $cache->set($tnCodesKey, $tn_codes, 86400); // на 24 часа
            }

            $kppKey = 'ref_tn_code_8544';
            $kpp_tn_codes = $cache->get($kppKey);
            if ($kpp_tn_codes === null) {
                $kpp_tn_codes = RefTnCode::find(["code = '8544'"]);
                $cache->set($kppKey, $kpp_tn_codes, 86400);
            }

            $catsKey = 'ref_car_categories';
            $cats = $cache->get($catsKey);
            if ($cats === null) {
                $cats = RefCarCat::find();
                $cache->set($catsKey, $cats, 86400);
            }

            $fundCompaniesKey = 'fund_companies_list_v1';
            $fund_companies = $cache->get($fundCompaniesKey);

            if ($fund_companies === null) {
                $fund_companies = $this->modelsManager->executeQuery($phql);
                $cache->set($fundCompaniesKey, $fund_companies, 3600);
            }

            $this->view->setVars(array(
                "tn_codes" => $tn_codes,
                "ref_country" => $ref_country,
                "kpp_tn_codes" => $kpp_tn_codes,
                "fund_companies" => $fund_companies,
                "cats" => $cats,
                "auth" => $auth
            ));
        }
    }

    public function zdMainAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " AND t.dt_approve >= " . strtotime($start . ' 00:00:00') . " AND t.dt_approve <= " . strtotime($end . ' 23:59:59');
                } else {
                    $date_limit = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            $start)) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", $start));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);
            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $c_car = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, CONCAT('-') as tnved, CONCAT('1') as volume, car.date_import as date_import, rc.name as country, car.cost as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN car JOIN ref_country rc WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND car.profile_id = p.id AND car.ref_country = rc.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $a_car = "SELECT p.id as pid, p.agent_name AS title, p.agent_status as pstatus, p.agent_iin as bin, p.type as ptype, CONCAT('-') as tnved, CONCAT('1') as volume, car.date_import as date_import, rc.name as country, car.cost as cost FROM user u JOIN profile p JOIN transaction t JOIN ref_country rc JOIN car WHERE u.id = p.user_id AND t.profile_id = p.id AND car.profile_id = p.id AND car.ref_country = rc.id AND p.agent_type = 2 AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $c_goods = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, tncode.code as tnved, goods.weight as volume, goods.date_import as date_import, rc.name as country, goods.amount as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN goods JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND goods.profile_id = p.id AND goods.ref_country = rc.id AND goods.ref_tn = tncode.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $a_goods = "SELECT p.id as pid, p.agent_name AS title, p.agent_status as pstatus, p.agent_iin as bin, p.type as ptype, tncode.code as tnved, goods.weight as volume, goods.date_import as date_import, rc.name as country, goods.amount as cost FROM user u JOIN profile p JOIN transaction t JOIN goods JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND t.profile_id = p.id AND goods.profile_id = p.id AND goods.ref_country = rc.id AND goods.ref_tn = tncode.id AND p.agent_type = 2 AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $kpps = "SELECT p.id as pid, cd.name AS title, p.agent_status as pstatus, cd.bin as bin, p.type as ptype, tncode.code as tnved, k.weight as volume, k.date_import as date_import, rc.name as country, k.amount as cost FROM user u JOIN company_detail cd JOIN profile p JOIN transaction t JOIN kpp k JOIN ref_country rc JOIN ref_tn_code tncode WHERE u.id = p.user_id AND cd.user_id = u.id AND t.profile_id = p.id AND k.profile_id = p.id AND k.ref_country = rc.id AND k.ref_tn = tncode.id AND t.approve = 'GLOBAL' $date_limit ORDER BY p.id DESC";
            $p_car = [];
            $p_goods = [];
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

            $filename = "repreal_zd_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;
            $orgName = ZHASYL_DAMU;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников $orgName")
                ->setKeywords("отчет роп 2016")
                ->setCategory("Отчеты")
                ->setCompany("$orgName");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Номер заявки (уникальный)")
                ->setCellValue("B1", "Наименование компании / ФИО")
                ->setCellValue("C1", "Импортер / производитель")
                ->setCellValue("D1", "БИН / ИИН")
                ->setCellValue("E1", "Товар / ТС")
                ->setCellValue("F1", "Код ТН ВЭД")
                ->setCellValue("G1", "Объем")
                ->setCellValue("H1", "Платеж")
                ->setCellValue("I1", "Дата импорта / реализации")
                ->setCellValue("J1", "Страна")
                ->getStyle('A1:J1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($c_car, $a_car, $c_goods, $a_goods, $kpps);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['pid']) {
                        // если указано ФИО или название
                        if ($row['title']) {
                            $num = $counter;
                            $counter++;

                            // определяем субконто
                            $subconto1 = 'ТС';
                            if ($row['ptype'] == 'GOODS') {
                                $subconto1 = 'ТБО';
                            }

                            if ($row['ptype'] == 'KPP') {
                                $subconto1 = 'КПП';
                            }

                            $subconto2 = 'Импортер';
                            if ($row['pstatus'] == 'VENDOR') {
                                $subconto2 = 'Производитель';
                            }

                            $price = '—';
                            if ($row['cost'] > 0) {
                                $price = $row['cost'];
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $row['title'])
                                ->setCellValue("C$num", $subconto1)
                                ->setCellValue("D$num", $row['bin'])
                                ->setCellValue("E$num", $subconto2)
                                ->setCellValue("F$num", $row['tnved'])
                                ->setCellValue("G$num", $row['volume'])
                                ->setCellValue("H$num", $price)
                                ->setCellValue("I$num", date('d.m.Y', convertTimeZone($row['date_import'])))
                                ->setCellValue("J$num", $row['country']);
                        }
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:J$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(50);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getColumnDimension('H')->setAutoSize(true);
            $sh->getColumnDimension('I')->setAutoSize(true);
            $sh->getColumnDimension('J')->setAutoSize(true);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("G2:G$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("H2:H$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("F2:F$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("D2:D$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("I2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("H2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("J2:J$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sh->getStyle("C2:C$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
                header('Content-Disposition: attachment; filename="zd_' . date('d.m.Y', $when) . '.xlsx"');
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
        } else {
            // если это простой просмотр форм
            $ref_country = RefCountry::find(array('id <> 1'));

            $tn_codes = RefTnCode::find(array("id NOT IN (873, 874)"));

            $kpp_tn_codes = RefTnCode::find([
                'conditions' => 'code = :code:',
                'bind' => [
                    'code' => 8544, // Кабельно-проводниковая продукция
                ]
            ]);

            $this->view->setVars(array(
                "tn_codes" => $tn_codes,
                "ref_country" => $ref_country,
                "kpp_tn_codes" => $kpp_tn_codes
            ));
        }
    }

    public function tnvedAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " AND t1.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t1.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND t1.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t1.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);
            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $req = "SELECT
      tn.code AS tn_code,
      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS weight_eaes,
      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS weight_neaes,
      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit GROUP BY g1.ref_tn) AS weight_sum,
      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS amount_eaes,
      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS amount_neaes,
      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit GROUP BY g1.ref_tn) AS amount_sum
      FROM ref_tn_code tn
      WHERE tn.code <> '8544'
      GROUP BY tn.code ";

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

            $filename = "reptnved_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Код ТН ВЭД")
                ->setCellValue("B1", "Вес, кг (ЕЭАС)")
                ->setCellValue("C1", "Вес, кг (другие)")
                ->setCellValue("D1", "Вес, кг (общий)")
                ->setCellValue("E1", "Сумма, тг (ЕАЭС)")
                ->setCellValue("F1", "Сумма, тг (другие)")
                ->setCellValue("G1", "Сумма, тг (общий)")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['tn_code']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", str_pad($row['tn_code'], 4, 0, STR_PAD_LEFT))
                            ->setCellValue("B$num", $row['weight_eaes'] ? $row['weight_eaes'] : 0)
                            ->setCellValue("C$num", $row['weight_neaes'] ? $row['weight_neaes'] : 0)
                            ->setCellValue("D$num", $row['weight_sum'] ? $row['weight_sum'] : 0)
                            ->setCellValue("E$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("F$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("G$num", $row['amount_sum'] ? $row['amount_sum'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(15);
            $sh->getColumnDimension('C')->setWidth(15);
            $sh->getColumnDimension('D')->setWidth(15);
            $sh->getColumnDimension('F')->setWidth(15);
            $sh->getColumnDimension('E')->setWidth(15);
            $sh->getColumnDimension('G')->setWidth(15);
            $sh->getStyle("A2:A$cc")->getNumberFormat()->setFormatCode('####');
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
                header('Content-Disposition: attachment; filename="ТНВЭД_срез_' . date('d.m.Y.00:00',
                        strtotime($start)) . '~' . date('d.m.Y.23:59', strtotime($end)) . '.xlsx"');
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

    public function zdTnvedAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " AND t1.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t1.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND t1.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND t1.dt_approve <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $req = "SELECT
                      tn.code AS tn_code,
                      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS weight_eaes,
                      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS weight_neaes,
                      (SELECT sum(g1.weight) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit GROUP BY g1.ref_tn) AS weight_sum,
                      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS amount_eaes,
                      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit AND g1.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY g1.ref_tn) AS amount_neaes,
                      (SELECT sum(g1.amount) FROM goods g1 JOIN transaction t1 WHERE g1.profile_id = t1.profile_id AND t1.approve = 'GLOBAL' AND g1.ref_tn = tn.id $date_limit GROUP BY g1.ref_tn) AS amount_sum
                      FROM ref_tn_code tn
                      WHERE tn.code <> '8544'
                      GROUP BY tn.code ";

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

            $filename = "reptnved_zd_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;
            $orgName = ZHASYL_DAMU;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников $orgName")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("$orgName");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Код ТН ВЭД")
                ->setCellValue("B1", "Вес, кг (ЕЭАС)")
                ->setCellValue("C1", "Вес, кг (другие)")
                ->setCellValue("D1", "Вес, кг (общий)")
                ->setCellValue("E1", "Сумма, тг (ЕАЭС)")
                ->setCellValue("F1", "Сумма, тг (другие)")
                ->setCellValue("G1", "Сумма, тг (общий)")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['tn_code']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", str_pad($row['tn_code'], 4, 0, STR_PAD_LEFT))
                            ->setCellValue("B$num", $row['weight_eaes'] ? $row['weight_eaes'] : 0)
                            ->setCellValue("C$num", $row['weight_neaes'] ? $row['weight_neaes'] : 0)
                            ->setCellValue("D$num", $row['weight_sum'] ? $row['weight_sum'] : 0)
                            ->setCellValue("E$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("F$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("G$num", $row['amount_sum'] ? $row['amount_sum'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(15);
            $sh->getColumnDimension('C')->setWidth(15);
            $sh->getColumnDimension('D')->setWidth(15);
            $sh->getColumnDimension('F')->setWidth(15);
            $sh->getColumnDimension('E')->setWidth(15);
            $sh->getColumnDimension('G')->setWidth(15);
            $sh->getStyle("A2:A$cc")->getNumberFormat()->setFormatCode('####');
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
                header('Content-Disposition: attachment; filename="АО_ТНВЭД_срез_' . date('d.m.Y.00:00',
                        strtotime($start)) . '~' . date('d.m.Y.23:59', strtotime($end)) . '.xlsx"');
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

    public function tnvedKppAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
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

            $req = "SELECT
              tn.code AS tn_code,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS weight_eaes,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS weight_neaes,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit GROUP BY k.ref_tn) AS weight_sum,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS amount_eaes,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS amount_neaes,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit GROUP BY k.ref_tn) AS amount_sum
              FROM ref_tn_code tn
              WHERE tn.code = '8544'
              GROUP BY tn.code ";

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

            $filename = "reptnved_kpp_" . $when . '.xlsx';

            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Код ТН ВЭД")
                ->setCellValue("B1", "Вес, кг (ЕЭАС)")
                ->setCellValue("C1", "Вес, кг (другие)")
                ->setCellValue("D1", "Вес, кг (общий)")
                ->setCellValue("E1", "Сумма, тг (ЕАЭС)")
                ->setCellValue("F1", "Сумма, тг (другие)")
                ->setCellValue("G1", "Сумма, тг (общий)")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['tn_code']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", str_pad($row['tn_code'], 4, 0, STR_PAD_LEFT))
                            ->setCellValue("B$num", $row['weight_eaes'] ? $row['weight_eaes'] : 0)
                            ->setCellValue("C$num", $row['weight_neaes'] ? $row['weight_neaes'] : 0)
                            ->setCellValue("D$num", $row['weight_sum'] ? $row['weight_sum'] : 0)
                            ->setCellValue("E$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("F$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("G$num", $row['amount_sum'] ? $row['amount_sum'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(15);
            $sh->getColumnDimension('C')->setWidth(15);
            $sh->getColumnDimension('D')->setWidth(15);
            $sh->getColumnDimension('F')->setWidth(15);
            $sh->getColumnDimension('E')->setWidth(15);
            $sh->getColumnDimension('G')->setWidth(15);
            $sh->getStyle("A2:A$cc")->getNumberFormat()->setFormatCode('####');
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
                header('Content-Disposition: attachment; filename="КПП_ТНВЭД_срез_' . date('d.m.Y.00:00',
                        strtotime($start)) . '~' . date('d.m.Y.23:59', strtotime($end)) . '.xlsx"');
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

    public function zdTnvedKppAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
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

            $req = "SELECT
              tn.code AS tn_code,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS weight_eaes,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS weight_neaes,
              (SELECT sum(k.weight) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit GROUP BY k.ref_tn) AS weight_sum,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS amount_eaes,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit AND k.ref_country NOT IN (11, 18, 71, 78, 135) GROUP BY k.ref_tn) AS amount_neaes,
              (SELECT sum(k.amount) FROM kpp k JOIN transaction t WHERE k.profile_id = t.profile_id AND t.approve = 'GLOBAL' AND k.ref_tn = tn.id $date_limit GROUP BY k.ref_tn) AS amount_sum
              FROM ref_tn_code tn
              WHERE tn.code = '8544'
              GROUP BY tn.code ";

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

            $filename = "zd_reptnved_kpp_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;
            $orgName = ZHASYL_DAMU;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников $orgName")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("$orgName");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Код ТН ВЭД")
                ->setCellValue("B1", "Вес, кг (ЕЭАС)")
                ->setCellValue("C1", "Вес, кг (другие)")
                ->setCellValue("D1", "Вес, кг (общий)")
                ->setCellValue("E1", "Сумма, тг (ЕАЭС)")
                ->setCellValue("F1", "Сумма, тг (другие)")
                ->setCellValue("G1", "Сумма, тг (общий)")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['tn_code']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", str_pad($row['tn_code'], 4, 0, STR_PAD_LEFT))
                            ->setCellValue("B$num", $row['weight_eaes'] ? $row['weight_eaes'] : 0)
                            ->setCellValue("C$num", $row['weight_neaes'] ? $row['weight_neaes'] : 0)
                            ->setCellValue("D$num", $row['weight_sum'] ? $row['weight_sum'] : 0)
                            ->setCellValue("E$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("F$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("G$num", $row['amount_sum'] ? $row['amount_sum'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setWidth(15);
            $sh->getColumnDimension('C')->setWidth(15);
            $sh->getColumnDimension('D')->setWidth(15);
            $sh->getColumnDimension('F')->setWidth(15);
            $sh->getColumnDimension('E')->setWidth(15);
            $sh->getColumnDimension('G')->setWidth(15);
            $sh->getStyle("A2:A$cc")->getNumberFormat()->setFormatCode('####');
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B2:G$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("B2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

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
                header('Content-Disposition: attachment; filename="АО_КПП_ТНВЭД_срез_' . date('d.m.Y.00:00',
                        strtotime($start)) . '~' . date('d.m.Y.23:59', strtotime($end)) . '.xlsx"');
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

    public function carcatAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $year = (int)$this->request->getPost("year");
            if ($year < 2018) {
                $year = 2018;
            }

            if ($year == 2022) {
                $date_limit = " and t1.dt_approve > " . strtotime("01.01.$year 00:00:00") . " and t1.dt_approve < " . strtotime("18.01.$year 23:59:59");
            } else {
                $date_limit = " and t1.dt_approve > " . strtotime("01.01.$year 00:00:00") . " and t1.dt_approve < " . strtotime("31.12.$year 23:59:59");
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $req = "select 
      ct.name as car_cat, 
      (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country in (11, 18, 71, 78, 135) group by c.ref_car_cat) as amount_eaes,
      (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country in (11, 18, 71, 78, 135) group by c.ref_car_cat) as count_eaes,
      (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country not in (11, 18, 71, 78, 135) group by c.ref_car_cat) as amount_neaes,
      (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country not in (11, 18, 71, 78, 135) group by c.ref_car_cat) as count_neaes,
      (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit group by c.ref_car_cat) as amount_sum,
      (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit group by c.ref_car_cat) as count_all
      from ref_car_cat ct 
      group by ct.name";

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

            $filename = "repcarcat_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Категория ТС")
                ->setCellValue("B1", "ЕАЭС")
                ->setCellValue("B2", "Сумма, тг")
                ->setCellValue("C2", "Количество ТС")
                ->setCellValue("D1", "Другие")
                ->setCellValue("D2", "Сумма, тг")
                ->setCellValue("E2", "Количество ТС")
                ->setCellValue("F1", "Общий")
                ->setCellValue("F2", "Сумма, тг")
                ->setCellValue("G2", "Количество ТС")
                ->getStyle('A1:G2')
                ->applyFromArray($yellow_style);

            $counter = 3;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['car_cat']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", mb_strtoupper(str_replace('cat-', '', $row['car_cat'])))
                            ->setCellValue("B$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("C$num", $row['count_eaes'] ? $row['count_eaes'] : 0)
                            ->setCellValue("D$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("E$num", $row['count_neaes'] ? $row['count_neaes'] : 0)
                            ->setCellValue("F$num", $row['amount_sum'] ? $row['amount_sum'] : 0)
                            ->setCellValue("G$num", $row['count_all'] ? $row['count_all'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh = $objEx->getActiveSheet()->mergeCells('A1:A2');
            $sh = $objEx->getActiveSheet()->mergeCells("B1:C1");
            $sh = $objEx->getActiveSheet()->mergeCells("D1:E1");
            $sh = $objEx->getActiveSheet()->mergeCells("F1:G1");
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setWidth(20);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("B3:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B3:B$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("D3:D$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("D3:D$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("F3:F$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("F3:F$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A1:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
                header('Content-Disposition: attachment; filename="Срез_по_категориям_ТС_' . $year . '.xlsx"');
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

    public function zdCarcatAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $year = (int)$this->request->getPost("year");
            if ($year == 2022) {
                $date_limit = " and t1.dt_approve > " . strtotime("19.01.$year 00:00:00") . " and t1.dt_approve < " . strtotime("31.12.$year 23:59:59");
            } else {
                $date_limit = " and t1.dt_approve > " . strtotime("01.01.$year 00:00:00") . " and t1.dt_approve < " . strtotime("31.12.$year 23:59:59");
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $req = "select 
          ct.name as car_cat, 
          (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country in (11, 18, 71, 78, 135) group by c.ref_car_cat) as amount_eaes,
          (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country in (11, 18, 71, 78, 135) group by c.ref_car_cat) as count_eaes,
          (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country not in (11, 18, 71, 78, 135) group by c.ref_car_cat) as amount_neaes,
          (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit and c.ref_country not in (11, 18, 71, 78, 135) group by c.ref_car_cat) as count_neaes,
          (select sum(c.cost) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit group by c.ref_car_cat) as amount_sum,
          (select count(c.id) from car c join transaction t1 where c.profile_id = t1.profile_id and t1.approve = 'GLOBAL' and c.ref_car_cat = ct.id $date_limit group by c.ref_car_cat) as count_all
          from ref_car_cat ct 
          group by ct.name";

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

            $filename = "repcarcat_zd_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;
            $orgName = ZHASYL_DAMU;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет в разрезе кодов")
                ->setSubject("Отчет в разрезе кодов")
                ->setDescription("Отчет для сотрудников $orgName")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("$orgName");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "Категория ТС")
                ->setCellValue("B1", "ЕАЭС")
                ->setCellValue("B2", "Сумма, тг")
                ->setCellValue("C2", "Количество ТС")
                ->setCellValue("D1", "Другие")
                ->setCellValue("D2", "Сумма, тг")
                ->setCellValue("E2", "Количество ТС")
                ->setCellValue("F1", "Общий")
                ->setCellValue("F2", "Сумма, тг")
                ->setCellValue("G2", "Количество ТС")
                ->getStyle('A1:G2')
                ->applyFromArray($yellow_style);

            $counter = 3;
            $cnt = 0;

            $sqls = array($req);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    if ($row['car_cat']) {
                        $num = $counter;
                        $counter++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", mb_strtoupper(str_replace('cat-', '', $row['car_cat'])))
                            ->setCellValue("B$num", $row['amount_eaes'] ? $row['amount_eaes'] : 0)
                            ->setCellValue("C$num", $row['count_eaes'] ? $row['count_eaes'] : 0)
                            ->setCellValue("D$num", $row['amount_neaes'] ? $row['amount_neaes'] : 0)
                            ->setCellValue("E$num", $row['count_neaes'] ? $row['count_neaes'] : 0)
                            ->setCellValue("F$num", $row['amount_sum'] ? $row['amount_sum'] : 0)
                            ->setCellValue("G$num", $row['count_all'] ? $row['count_all'] : 0);
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh = $objEx->getActiveSheet()->mergeCells('A1:A2');
            $sh = $objEx->getActiveSheet()->mergeCells("B1:C1");
            $sh = $objEx->getActiveSheet()->mergeCells("D1:E1");
            $sh = $objEx->getActiveSheet()->mergeCells("F1:G1");
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setWidth(20);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setAutoSize(true);
            $sh->getStyle("B3:B$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("B3:B$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("D3:D$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("D3:D$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("F3:F$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("F3:F$cc")->getNumberFormat()->setFormatCode('### ### ### ##0.00_-');
            $sh->getStyle("A1:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
                header('Content-Disposition: attachment; filename="АО_Срез_по_категориям_ТС_' . $year . '.xlsx"');
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

    public function carAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // режим
            $mode = $this->request->get("mode");

            // ***
            // лимиты и фильтры

            $limits = '';

            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $start_import = $this->request->getPost("dstart_import");
            $end_import = $this->request->getPost("dend_import");
            $start_md_dt_sent = $this->request->getPost("dstart_md_dt_sent");
            $end_md_dt_sent = $this->request->getPost("dend_md_dt_sent");
            $start_approve = $this->request->getPost("dstart_global");
            $end_approve = $this->request->getPost("dend_global");
            $status = $this->request->getPost("status");
            $st_type = $this->request->getPost("st_type");

            if (!$start && !$start_import && !$start_approve && !$start_md_dt_sent) {
                $start = date('d.m.Y');
                $end = date('d.m.Y');
            }

            //Подключение таблиц по условию даты(роп, жд)
            $report_org = 'zd';
            if (
                (strtotime($start) >= 1453917600 && strtotime($start) <= 1642528800) ||
                (strtotime($end) >= 1453917600 && strtotime($end) <= 1642528800) ||
                (strtotime($start_import) >= 1453917600 && strtotime($start_import) <= 1642528800) ||
                (strtotime($end_import) >= 1453917600 && strtotime($end_import) <= 1642528800) ||
                (strtotime($start_md_dt_sent) >= 1453917600 && strtotime($start_md_dt_sent) <= 1642528800) ||
                (strtotime($end_md_dt_sent) >= 1453917600 && strtotime($end_md_dt_sent) <= 1642528800) ||
                (strtotime($start_approve) >= 1453917600 && strtotime($start_approve) <= 1642528800) ||
                (strtotime($end_approve) >= 1453917600 && strtotime($end_approve) <= 1642528800)
            ) {
                $report_org = 'rop';
            }

            $bank_relation = "";
            $bank_relation_select = "";
            if ($report_org === 'rop') {
                $bank_relation = " LEFT JOIN (
                SELECT 
                    bt.profile_id,
                    GROUP_CONCAT(DISTINCT b.account_num SEPARATOR ', ') AS bank_account_num,
                    GROUP_CONCAT(DISTINCT b.paid SEPARATOR ', ') AS bank_paid
                FROM bank_transaction bt
                JOIN bank b ON b.id = bt.bank_id
                GROUP BY bt.profile_id
            ) AS bank_data ON bank_data.profile_id = p.id";

                $bank_relation_select = " bank_data.bank_account_num, bank_data.bank_paid";
            } else {
                if ($report_org = 'zd') {
                    $bank_relation = " LEFT JOIN (
                SELECT 
                    zbt.profile_id,
                    GROUP_CONCAT(DISTINCT zb.account_num SEPARATOR ', ') AS zd_bank_account_num,
                    GROUP_CONCAT(DISTINCT zb.paid SEPARATOR ', ') AS zd_bank_paid
                FROM zd_bank_transaction zbt
                JOIN zd_bank zb ON zb.id = zbt.zd_bank_id
                GROUP BY zbt.profile_id
            ) AS zd_bank_data ON zd_bank_data.profile_id = p.id";
                    $bank_relation_select = " zd_bank_data.zd_bank_account_num, zd_bank_data.zd_bank_paid";
                }
            }

            // по Седельность
            $_q_st_type = '';

            if ($st_type == 'NO') {
                $_q_st_type = "AND car.ref_st_type = 0";
            } elseif ($st_type == 'YES') {
                $_q_st_type = "AND car.ref_st_type = 1";
            } elseif ($st_type == 'INT_TR') {
                $_q_st_type = "AND car.ref_st_type = 2";
            }
            $_q_status = '';

            // по статус заявки
            if ($status) {
                $status_encoded = json_encode($status);
                $status_arr = json_decode($status_encoded);
                foreach ($status_arr as $v) {
                    $_q_status .= "'$v', ";
                }
                $_q_status = rtrim($_q_status, ", ");
                $_q_status = "AND t.approve IN ($_q_status)";
            }

            // по дате заявки
            $_l = '';
            if ($start) {
                if ($end) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end) - strtotime($start);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end = date('d.m.Y', strtotime($start) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }
            $limits .= $_l;

            // по дате импорта
            $_l = '';
            if ($start_import) {
                if ($end_import) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_import) - strtotime($start_import);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_import = date('d.m.Y', strtotime($start_import) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND car.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND car.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_import)));
                } else {
                    $_l = " AND car.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND car.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_import)));
                }
            }
            $limits .= $_l;

            // по дате дате отправки модератору
            $_l = '';
            if ($start_md_dt_sent) {
                if ($end_md_dt_sent) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_md_dt_sent) - strtotime($start_md_dt_sent);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_md_dt_sent = date('d.m.Y', strtotime($start_md_dt_sent) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_md_dt_sent)));
                } else {
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_md_dt_sent)));
                }
            }
            $limits .= $_l;

            // по дате выдачи
            $_l = '';
            if ($start_approve) {
                if ($end_approve) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_approve) - strtotime($start_approve);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_approve = date('d.m.Y', strtotime($start_approve) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_approve)));
                } else {
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_approve)));
                }
            }
            $limits .= $_l;

            $l_bin = $this->request->getPost("bin");
            $l_vin = $this->request->getPost("vin");
            $l_cat = $this->request->getPost("cat");
            $l_city = $this->request->getPost("icity");

            // TODO — добавить фильтрацию по региону

            // VIN
            $_l = '';
            if ($l_vin) {
                $_l = " AND car.vin LIKE '%$l_vin%'";
            }
            $limits .= $_l;

            // CAT
            $_l = '';
            if ($l_cat) {
                $_l = " AND car.ref_car_cat = '$l_cat'";
            }
            $limits .= $_l;

            $search_by_user = '';

            // ИИН, БИН
            if ($l_bin) {
                $search_by_user = " AND u.idnum = $l_bin ";
            }

            $_l = '';
            $l_country = $this->request->getPost("country");
            if ($l_country) {
                $_l = " AND car.ref_country = $l_country";
            }
            $limits .= $_l;

            // VOLUME LIMITS
            $_l = '';
            $volume_min = $this->request->getPost("volume_min");
            $volume_max = $this->request->getPost("volume_max");
            if ($volume_max) {
                $_l .= " AND car.volume < $volume_max";
            }
            if ($volume_min) {
                $_l .= " AND car.volume > $volume_min";
            }
            $limits .= $_l;

            // TOTAL LIMITS
            $_l = '';
            $total_min = $this->request->getPost("total_min");
            $total_max = $this->request->getPost("total_max");
            if ($total_max) {
                $_l .= " AND car.cost < $total_max";
            }
            if ($total_min) {
                $_l .= " AND car.cost > $total_min";
            }
            $limits .= $_l;

            // конец лимитов
            // ***

            $count = $this->request->getPost("count");

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            if ($mode != 'json') {
                $car = "SELECT 
               p.id AS pid,
               CASE 
                   WHEN u.user_type_id = 2 THEN u.org_name
                   WHEN u.user_type_id = 1 THEN u.fio
               END AS title,
               u.idnum AS idnum,
               car.vin AS vin,
               rcc.name AS cat,
               car.date_import AS date_import,
               p.created AS date_created,
               t.dt_approve AS date_approve,
               t.md_dt_sent AS md_dt_sent,
               rc.name AS country,
               car.cost AS cost,
               CONCAT('—') AS city,
               car.volume AS volume,
               car.ref_st_type AS st_type,
               t.approve AS approve,
               t.id AS tid,
               $bank_relation_select
           FROM profile p
           JOIN transaction t ON t.profile_id = p.id
           JOIN car ON car.profile_id = p.id
           JOIN ref_country rc ON car.ref_country = rc.id
           JOIN ref_car_cat rcc ON car.ref_car_cat = rcc.id
           JOIN user u ON p.user_id = u.id
           $bank_relation
           WHERE 1 = 1
               $_q_status
               $_q_st_type
               $search_by_user
               $limits
           ORDER BY p.id DESC;";

                $yellow_style = array(
                    'font' => array('bold' => true),
                    'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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

                $sh->setCellValue("A1", "Номер заявки")
                    ->setCellValue("B1", "Наименование компании")
                    ->setCellValue("C1", "БИН")
                    ->setCellValue("D1", "ВИН")
                    ->setCellValue("E1", "Категория ТС")
                    ->setCellValue("F1", "Дата импорта / пр-ва")
                    ->setCellValue("G1", "Дата заявки")
                    ->setCellValue("H1", "Дата выдачи")
                    ->setCellValue("I1", "Страна")
                    ->setCellValue("J1", "Область / город")
                    ->setCellValue("K1", "Объем или масса")
                    ->setCellValue("L1", "Размер платежа")
                    ->setCellValue("M1", "Статус заявки")
                    ->setCellValue("N1", "Седельный тягач?")
                    ->setCellValue("O1", "Дата отправки на модератору")
                    ->setCellValue("P1", "Номер референса")
                    ->setCellValue("Q1", "Дата оплаты")
                    ->getStyle('A1:Q1')
                    ->applyFromArray($yellow_style);

                $counter = 2;
                $cnt = 0;

                $sqls = array($car);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $cnt++;
                        // если профиль в норме
                        if ($row['pid']) {
                            $num = $counter;
                            $counter++;

                            $price = '—';
                            if ($row['cost'] > 0) {
                                $price = $row['cost'];
                            }

                            $vin = '—';
                            if ($row['vin']) {
                                $vin = $row['vin'];
                            }

                            $dt_approve = '—';
                            if ($row['date_approve'] > 0) {
                                $dt_approve = date('d.m.Y', convertTimeZone($row['date_approve']));
                            }

                            $md_dt_sent = '—';
                            if ($row['md_dt_sent'] > 0) {
                                $md_dt_sent = date('d.m.Y', convertTimeZone($row['md_dt_sent']));
                            }

                            $st_type_text = '—';
                            if ($row['st_type'] == 0) {
                                $st_type_text = 'ref-st-not';
                            } elseif ($row['st_type'] == 1) {
                                $st_type_text = 'ref-st-yes';
                            } elseif ($row['st_type'] == 2) {
                                $st_type_text = 'ref-st-international-transport';
                            }

                            $bank_paid = $row['zd_bank_paid'];
                            $bank_account_num = $row['zd_bank_account_num'];
                            if ($report_org === 'rop') {
                                $bank_paid = $row['bank_paid'];
                                $bank_account_num = $row['bank_account_num'];
                            }

                            $paid = '—';
                            $formattedDates = [];
                            if ($bank_paid) {
                                $b_paid = explode("\n", $bank_paid);
                                foreach ($b_paid as $b_date) {
                                    $b_date = trim($b_date);
                                    if ($b_date) {
                                        $formattedDates[] = date('d.m.Y', convertTimeZone($b_date));
                                    } else {
                                        $formattedDates[] = $b_date;
                                    }
                                }
                                $paid = implode("\n", $formattedDates);
                            }

                            $account_num = '—';
                            if ($bank_account_num) {
                                $account_num = $bank_account_num;
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $row['title'])
                                ->setCellValue("C$num", $row['idnum'])
                                ->setCellValue("D$num", $vin)
                                ->setCellValue("E$num", mb_strtoupper(str_replace(array('cat-', 'tractor', 'combain'),
                                    array('', 'Трактор', 'Комбайн'), $row['cat'])))
                                ->setCellValue("F$num", date('d.m.Y', convertTimeZone($row['date_import'])))
                                ->setCellValue("G$num", date('d.m.Y', convertTimeZone($row['date_created'])))
                                ->setCellValue("H$num", $dt_approve)
                                ->setCellValue("I$num", $row['country'])
                                ->setCellValue("J$num", $row['city'])
                                ->setCellValue("K$num", $row['volume'])
                                ->setCellValue("L$num", $price)
                                ->setCellValue("M$num", $this->translator->_($row['approve']))
                                ->setCellValue("N$num", $this->translator->_($st_type_text))
                                ->setCellValue("O$num", $md_dt_sent)
                                ->setCellValue("P$num", $account_num)
                                ->setCellValue("Q$num", $paid);
                        }
                    }
                }

                if ($cnt == 0) {
                    $this->flash->error("# Не найден !");
                    return $this->response->redirect("/report_importer/");
                }

                // оформление

                $cc = $counter - 1;

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->getStyle("A1:O$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setWidth(50);
                $sh->getColumnDimension('C')->setAutoSize(true);
                $sh->getColumnDimension('D')->setAutoSize(true);
                $sh->getColumnDimension('E')->setAutoSize(true);
                $sh->getColumnDimension('F')->setAutoSize(true);
                $sh->getColumnDimension('G')->setAutoSize(true);
                $sh->getColumnDimension('H')->setAutoSize(true);
                $sh->getColumnDimension('I')->setAutoSize(true);
                $sh->getColumnDimension('J')->setAutoSize(true);
                $sh->getColumnDimension('K')->setAutoSize(true);
                $sh->getColumnDimension('L')->setAutoSize(true);
                $sh->getColumnDimension('M')->setAutoSize(true);
                $sh->getColumnDimension('N')->setAutoSize(true);
                $sh->getColumnDimension('O')->setAutoSize(true);
                $sh->getColumnDimension('P')->setAutoSize(true);
                $sh->getColumnDimension('Q')->setAutoSize(true);
                $sh->getStyle("K2:K$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
                $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('############');
                $sh->getStyle("K2:K$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("L2:L$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:O$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("B1:B$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
                $sh->getStyle("B1:B$cc")->getAlignment()->setWrapText(true);

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
            } else {
                $item_count = 0;
                $item_sum = 0;
                // сводная форма

                $car = "SELECT count(p.id) as item_count, SUM(car.cost) as item_sum FROM profile p JOIN transaction t JOIN ref_country rc JOIN car JOIN ref_car_cat rcc JOIN user u WHERE t.profile_id = p.id AND car.profile_id = p.id AND p.user_id = u.id AND car.ref_country = rc.id AND car.ref_car_cat = rcc.id $_q_status $_q_st_type $search_by_user $limits ORDER BY p.id DESC";

                $sqls = array($car);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $item_count += $row['item_count'];
                        $item_sum += $row['item_sum'];
                    }
                }

                echo json_encode(array(
                    "item_count" => $item_count, "item_sum" => number_format($item_sum, 2, ",", "&nbsp;")
                ));
                // конец сводной формы
            }

            mysqli_close($mc);
        }
    }

    public function carsAllTimeAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // режим
            $mode = $this->request->get("mode");

            // ***
            // лимиты и фильтры

            $limits = '';

            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $start_import = $this->request->getPost("dstart_import");
            $end_import = $this->request->getPost("dend_import");
            $start_md_dt_sent = $this->request->getPost("dstart_md_dt_sent");
            $end_md_dt_sent = $this->request->getPost("dend_md_dt_sent");
            $start_approve = $this->request->getPost("dstart_global");
            $end_approve = $this->request->getPost("dend_global");
            $status = $this->request->getPost("status");

            if (!$start && !$start_import && !$start_approve && !$start_md_dt_sent) {
                $start = date('d.m.Y');
                $end = date('d.m.Y');
            }
            $_q_status = '';
            // по статус заявки
            if ($status) {
                $status_encoded = json_encode($status);
                $status_arr = json_decode($status_encoded);
                foreach ($status_arr as $v) {
                    $_q_status .= "'$v', ";
                }
                $_q_status = rtrim($_q_status, ", ");
                $_q_status = "AND t.approve IN ($_q_status)";
            }

            $report_org = 'zd';
            if (
                (strtotime($start) >= 1453917600 && strtotime($start) <= 1642528800) ||
                (strtotime($end) >= 1453917600 && strtotime($end) <= 1642528800) ||
                (strtotime($start_import) >= 1453917600 && strtotime($start_import) <= 1642528800) ||
                (strtotime($end_import) >= 1453917600 && strtotime($end_import) <= 1642528800) ||
                (strtotime($start_md_dt_sent) >= 1453917600 && strtotime($start_md_dt_sent) <= 1642528800) ||
                (strtotime($end_md_dt_sent) >= 1453917600 && strtotime($end_md_dt_sent) <= 1642528800) ||
                (strtotime($start_approve) >= 1453917600 && strtotime($start_approve) <= 1642528800) ||
                (strtotime($end_approve) >= 1453917600 && strtotime($end_approve) <= 1642528800)
            ) {
                $report_org = 'rop';
            }

            $bank_relation = "";
            $bank_relation_select = "";
            if ($report_org === 'rop') {
                $bank_relation = " LEFT JOIN (
                    SELECT 
                        bt.profile_id,
                        GROUP_CONCAT(DISTINCT b.account_num SEPARATOR ', ') AS bank_account_num,
                        GROUP_CONCAT(DISTINCT b.paid SEPARATOR ', ') AS bank_paid
                    FROM bank_transaction bt
                    JOIN bank b ON b.id = bt.bank_id
                    GROUP BY bt.profile_id
                ) AS bank_data ON bank_data.profile_id = p.id";

                $bank_relation_select = " bank_data.bank_account_num, bank_data.bank_paid";
            } else {
                if ($report_org = 'zd') {
                    $bank_relation = " LEFT JOIN (
                    SELECT 
                        zbt.profile_id,
                        GROUP_CONCAT(DISTINCT zb.account_num SEPARATOR ', ') AS zd_bank_account_num,
                        GROUP_CONCAT(DISTINCT zb.paid SEPARATOR ', ') AS zd_bank_paid
                    FROM zd_bank_transaction zbt
                    JOIN zd_bank zb ON zb.id = zbt.zd_bank_id
                    GROUP BY zbt.profile_id
                ) AS zd_bank_data ON zd_bank_data.profile_id = p.id";
                    $bank_relation_select = " zd_bank_data.zd_bank_account_num, zd_bank_data.zd_bank_paid";
                }
            }

            // по дате заявки
            $_l = '';
            if ($start) {
                if ($end) {
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }
            $limits .= $_l;

            // по дате импорта
            $_l = '';
            if ($start_import) {
                if ($end_import) {
                    $_l = " AND car.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND car.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_import)));
                } else {
                    $_l = " AND car.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND car.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_import)));
                }
            }
            $limits .= $_l;

            // по дате дате отправки модератору
            $_l = '';
            if ($start_md_dt_sent) {
                if ($end_md_dt_sent) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_md_dt_sent) - strtotime($start_md_dt_sent);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_md_dt_sent = date('d.m.Y', strtotime($start_md_dt_sent) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_md_dt_sent)));
                } else {
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_md_dt_sent)));
                }
            }
            $limits .= $_l;

            // по дате выдачи
            $_l = '';
            if ($start_approve) {
                if ($end_approve) {
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_approve)));
                } else {
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_approve)));
                }
            }
            $limits .= $_l;

            $l_title = $this->request->getPost("title");
            $l_bin = $this->request->getPost("bin");
            $l_vin = $this->request->getPost("vin");
            $l_cat = strtolower($this->request->getPost("cat"));
            $l_city = $this->request->getPost("icity");

            // TODO — добавить фильтрацию по региону

            // VIN
            $_l = '';
            if ($l_vin) {
                $_l = " AND car.vin LIKE '%$l_vin%'";
            }
            $limits .= $_l;

            // CAT
            $_l = '';
            if ($l_cat) {
                if ($l_cat == 'tractor' || $l_cat == 'combain') {
                    $_l = " AND rcc.name = '$l_cat'";
                } else {
                    $_l = " AND rcc.name = 'cat-$l_cat'";
                }
            }
            $limits .= $_l;

            // TITLE
            $getUsersProfile = '';

            if ($l_title) {
                $cd = CompanyDetail::findFirst(
                    array(
                        "name LIKE :name:",
                        "bind" => array(
                            "name" => "%$l_title%"
                        )
                    ));

                if ($cd) {
                    $getUsersProfile = " AND p.user_id = $cd->user_id ";
                } else {
                    $user_id = [];
                    $pd = PersonDetail::find(
                        array(
                            "(last_name LIKE :name: OR first_name LIKE :name: OR parent_name LIKE :name:) OR CONCAT(last_name, ' ', first_name, ' ',parent_name) LIKE :name:",
                            "bind" => array(
                                "name" => "%$l_title%"
                            )
                        ));
                    if ($pd) {
                        foreach ($pd as $person) {
                            $user_id[] = $person->user_id;
                        }
                        $users = join(",", $user_id);
                    }
                    $getUsersProfile = " AND p.user_id IN($users)";
                }
            }

            // ИИН, БИН
            if ($l_bin) {
                $user = User::findFirstByIdnum($l_bin);
                if ($user) {
                    $getUsersProfile = " AND p.user_id = $user->id ";
                } else {
                    $agent_p = Profile::findFirstByAgentIin($l_bin);

                    if ($agent_p) {
                        $getUsersProfile = " AND p.agent_iin = $l_bin";
                    } else {
                        $this->flash->error("Пользователь с таким ИИН / БИН не найден");
                        return $this->response->redirect("/report_importer");
                    }
                }
            }

            $_l = '';
            $l_country = $this->request->getPost("country");
            if ($l_country) {
                $_l = " AND car.ref_country = $l_country";
            }
            $limits .= $_l;

            // VOLUME LIMITS
            $_l = '';
            $volume_min = $this->request->getPost("volume_min");
            $volume_max = $this->request->getPost("volume_max");
            if ($volume_max) {
                $_l .= " AND car.volume < $volume_max";
            }
            if ($volume_min) {
                $_l .= " AND car.volume > $volume_min";
            }
            $limits .= $_l;

            // TOTAL LIMITS
            $_l = '';
            $total_min = $this->request->getPost("total_min");
            $total_max = $this->request->getPost("total_max");
            if ($total_max) {
                $_l .= " AND car.cost < $total_max";
            }
            if ($total_min) {
                $_l .= " AND car.cost > $total_min";
            }
            $limits .= $_l;

            // конец лимитов
            // ***

            $count = $this->request->getPost("count");

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            if ($mode != 'json') {
                $car = "SELECT p.id as pid, 
                       p.created as date_created, 
                       t.approve as approve, 
                       t.dt_approve as date_approve,
                       t.md_dt_sent as md_dt_sent,
                       t.amount as cost,
                       IF(u.user_type_id = 1, u.fio, u.org_name) as title,
                       u.idnum as idnum,
                        $bank_relation_select
                FROM profile p 
                    JOIN transaction t
                    JOIN user u ON p.user_id = u.id
                    JOIN car ON car.profile_id = p.id
                    JOIN ref_car_cat rcc ON rcc.id = car.ref_car_cat
                   $bank_relation
                WHERE  
                      t.profile_id = p.id AND
                      p.type = 'CAR'
                      $_q_status 
                      $getUsersProfile 
                      $limits
                ORDER BY p.id DESC";

                $yellow_style = array(
                    'font' => array('bold' => true),
                    'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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

                $sh->setCellValue("A1", "Номер заявки")
                    ->setCellValue("B1", "Наименование компании")
                    ->setCellValue("C1", "БИН")
                    ->setCellValue("D1", "Дата заявки")
                    ->setCellValue("E1", "Дата выдачи")
                    ->setCellValue("F1", "Размер платежа")
                    ->setCellValue("G1", "Статус заявки")
                    ->setCellValue("H1", "Дата отправки на модератору")
                    ->setCellValue("I1", "Номер референса")
                    ->setCellValue("J1", "Дата оплаты")
                    ->getStyle('A1:J1')
                    ->applyFromArray($yellow_style);

                $counter = 2;
                $cnt = 0;

                $sqls = array($car);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $cnt++;
                        // если профиль в норме
                        if ($row['pid']) {
                            $num = $counter;
                            $counter++;

                            $price = ($row['cost'] > 0) ? $row['cost'] : '—';
                            $dt_approve = ($row['date_approve'] > 0) ? date('d.m.Y',
                                convertTimeZone($row['date_approve'])) : '—';
                            $md_dt_sent = ($row['md_dt_sent'] > 0) ? date('d.m.Y', convertTimeZone($row['md_dt_sent'])) : '—';

                            $bank_paid = $row['zd_bank_paid'];
                            $bank_account_num = $row['zd_bank_account_num'];
                            if ($report_org === 'rop') {
                                $bank_paid = $row['bank_paid'];
                                $bank_account_num = $row['bank_account_num'];
                            }

                            $paid = '—';
                            $formattedDates = [];
                            if ($bank_paid) {
                                $b_paid = explode("\n", $bank_paid);
                                foreach ($b_paid as $b_date) {
                                    $b_date = trim($b_date);
                                    if ($b_date) {
                                        $formattedDates[] = date('d.m.Y', $b_date);
                                    } else {
                                        $formattedDates[] = $b_date;
                                    }
                                }
                                $paid = implode("\n", $formattedDates);
                            }

                            $account_num = '—';
                            if ($bank_account_num) {
                                $account_num = $bank_account_num;
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $row['title'])
                                ->setCellValue("C$num", $row['idnum'])
                                ->setCellValue("D$num", date('d.m.Y', convertTimeZone($row['date_created'])))
                                ->setCellValue("E$num", $dt_approve)
                                ->setCellValue("F$num", $price)
                                ->setCellValue("G$num", $this->translator->_($row['approve']))
                                ->setCellValue("H$num", $md_dt_sent)
                                ->setCellValue("I$num", $account_num)
                                ->setCellValue("J$num", $paid);
                        }
                    }
                }

                if ($cnt == 0) {
                    $this->flash->error("# Не найден !");
                    return $this->response->redirect("/report_importer/");
                }

                // оформление

                $cc = $counter - 1;

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->getStyle("A1:H$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setWidth(50);
                $sh->getColumnDimension('C')->setAutoSize(true);
                $sh->getColumnDimension('D')->setAutoSize(true);
                $sh->getColumnDimension('E')->setAutoSize(true);
                $sh->getColumnDimension('F')->setAutoSize(true);
                $sh->getColumnDimension('G')->setAutoSize(true);
                $sh->getColumnDimension('H')->setAutoSize(true);
                $sh->getColumnDimension('I')->setAutoSize(true);
                $sh->getColumnDimension('J')->setAutoSize(true);
                $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
                $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('############');
                $sh->getStyle("F2:F$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("B1:B$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
                $sh->getStyle("B1:B$cc")->getAlignment()->setWrapText(true);

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
            } else {
                $item_count = 0;
                $item_sum = 0;
                // сводная форма

                $car = "SELECT count(p.id) as item_count, SUM(car.cost) as item_sum FROM profile p JOIN transaction t JOIN ref_country rc JOIN car JOIN ref_car_cat rcc WHERE t.profile_id = p.id AND car.profile_id = p.id AND car.ref_country = rc.id AND car.ref_car_cat = rcc.id $_q_status $getUsersProfile $limits ORDER BY p.id DESC";

                $sqls = array($car);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $item_count += $row['item_count'];
                        $item_sum += $row['item_sum'];
                    }
                }

                echo json_encode(array(
                    "item_count" => $item_count, "item_sum" => number_format($item_sum, 2, ",", "&nbsp;")
                ));
                // конец сводной формы
            }

            mysqli_close($mc);
        }
    }

    public function goodsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // режим
            $mode = $this->request->get("mode");

            // ***
            // лимиты и фильтры

            $limits = '';

            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $start_import = $this->request->getPost("dstart_import");
            $end_import = $this->request->getPost("dend_import");
            $start_md_dt_sent = $this->request->getPost("dstart_md_dt_sent");
            $end_md_dt_sent = $this->request->getPost("dend_md_dt_sent");
            $start_approve = $this->request->getPost("dstart_global");
            $end_approve = $this->request->getPost("dend_global");
            $start_real = $this->request->getPost("dstart_real");
            $end_real = $this->request->getPost("dend_real");

            if (!$start && !$start_import && !$start_approve && !$start_md_dt_sent) {
                $start = date('d.m.Y');
                $end = date('d.m.Y');
            }

            // по дате заявки
            $_l = '';
            if ($start) {
                if ($end) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end) - strtotime($start);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end = date('d.m.Y', strtotime($start) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }
            $limits .= $_l;

            // по дате импорта
            $_l = '';
            if ($start_import) {
                if ($end_import) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_import) - strtotime($start_import);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_import = date('d.m.Y', strtotime($start_import) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND goods.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND goods.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_import)));
                } else {
                    $_l = " AND goods.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND goods.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_import)));
                }
            }
            $limits .= $_l;

            // по дате отправки на модератору
            $_l = '';
            if ($start_md_dt_sent) {
                if ($end_md_dt_sent) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_md_dt_sent) - strtotime($start_md_dt_sent);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_md_dt_sent = date('d.m.Y', strtotime($start_md_dt_sent) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_md_dt_sent)));
                } else {
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_md_dt_sent)));
                }
            }
            $limits .= $_l;

            // по дате выдачи
            $_l = '';
            if ($start_approve) {
                if ($end_approve) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_approve) - strtotime($start_approve);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_approve = date('d.m.Y', strtotime($start_approve) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_approve)));
                } else {
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_approve)));
                }
            }
            $limits .= $_l;

            // TODO — реализовать фильтр по дате реализации для товаров

            $l_bin = $this->request->getPost("bin");
            $l_good_tncode = $this->request->getPost("good_tncode");
            $l_good_name = strtolower($this->request->getPost("good_name"));
            $l_city = $this->request->getPost("icity");
            $l_up_name = $this->request->getPost("up_name");

            // TODO — добавить фильтрацию по региону

            // название
            $_l = '';
            if ($l_good_name) {
                $_l = " AND tnc.name LIKE '%$l_good_name%'";
            }
            $limits .= $_l;

            // название
            $_l = '';
            if ($l_up_name) {
                $_l = " AND tnc.name LIKE '%$l_up_name%'";
            }
            $limits .= $_l;

            // CAT
            $_l = '';
            if ($l_good_tncode) {
                $_list_tnved = '';
                foreach ($l_good_tncode as $val) {
                    $_list_tnved .= "'" . $val . "',";
                }
                $_l = " AND tnc.code IN ($_list_tnved)";
                $_l = str_replace(',)', ')', $_l);
            }
            $limits .= $_l;

            // TITLE
            $search_by_user_idnum = '';

            // ИИН, БИН
            if ($l_bin) {
                $search_by_user_idnum = " AND u.idnum = $l_bin ";
            }

            $_l = '';
            $l_country = $this->request->getPost("country");
            if ($l_country) {
                $_l = " AND goods.ref_country = $l_country";
            }
            $limits .= $_l;

            // UP LIMITS
            $_l = '';
            $up_min = $this->request->getPost("up_min");
            $up_max = $this->request->getPost("up_max");
            if ($up_max) {
                $_l .= " AND goods.weight < $up_max";
            }
            if ($up_min) {
                $_l .= " AND goods.weight > $up_min";
            }
            $limits .= $_l;

            // VOLUME LIMITS
            $_l = '';
            $volume_min = $this->request->getPost("volume_min");
            $volume_max = $this->request->getPost("volume_max");
            if ($volume_max) {
                $_l .= " AND goods.weight < $volume_max";
            }
            if ($volume_min) {
                $_l .= " AND goods.weight > $volume_min";
            }
            $limits .= $_l;

            // TOTAL LIMITS
            $_l = '';
            $total_min = $this->request->getPost("total_min");
            $total_max = $this->request->getPost("total_max");
            if ($total_max) {
                $_l .= " AND goods.amount < $total_max";
            }
            if ($total_min) {
                $_l .= " AND goods.amount > $total_min";
            }
            $limits .= $_l;

            // конец лимитов
            // ***

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            if ($mode != 'json') {
                $goods = "SELECT p.id as pid,
                         CASE 
                            WHEN u.user_type_id = 2 THEN COALESCE(cd.name, u.org_name) 
                            WHEN u.user_type_id = 1 THEN u.fio
                         END as title,
                         u.idnum as idnum,  
                         tnc.code as tn_code, 
                         tnc.name as tn_name, 
                         goods.date_import as date_import, 
                         p.created as date_created, 
                         t.dt_approve as date_approve, 
                         t.md_dt_sent as md_dt_sent, 
                         rc.name as country, 
                         goods.amount as cost, 
                         CONCAT('—') as city, 
                         goods.weight as wg, 
                         goods.package_weight as package_wg, 
                         goods.package_cost as package_cost,
                         (SELECT code FROM ref_tn_code where id = goods.ref_tn_add) as package_tnvd
                  FROM profile p 
                    JOIN transaction t 
                    JOIN ref_country rc 
                    JOIN goods 
                    JOIN ref_tn_code tnc
                    JOIN user u 
                    LEFT JOIN (
                        SELECT cd1.user_id, cd1.name
                        FROM company_detail cd1
                        JOIN (
                            SELECT user_id, MAX(id) AS max_id
                            FROM company_detail
                            GROUP BY user_id
                        ) x ON x.user_id = cd1.user_id AND x.max_id = cd1.id
                        ) cd ON cd.user_id = u.id
                    WHERE t.profile_id = p.id AND 
                        goods.profile_id = p.id AND 
                        goods.ref_country = rc.id AND 
                        p.user_id = u.id AND 
                        goods.ref_tn = tnc.id AND 
                        t.approve = 'GLOBAL' 
                        $search_by_user_idnum
                        $limits 
                  ORDER BY p.id DESC";

                $yellow_style = array(
                    'font' => array('bold' => true),
                    'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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

                $sh->setCellValue("A1", "Номер заявки")
                    ->setCellValue("B1", "Наименование компании")
                    ->setCellValue("C1", "БИН")
                    ->setCellValue("D1", "Код ТН ВЭД")
                    ->setCellValue("E1", "Наименование товара")
                    ->setCellValue("F1", "Дата импорта / пр-ва")
                    ->setCellValue("G1", "Дата заявки")
                    ->setCellValue("H1", "Дата выдачи")
                    ->setCellValue("I1", "Страна")
                    ->setCellValue("J1", "Область / город")
                    ->setCellValue("K1", "Масса, кг")
                    ->setCellValue("L1", "Размер платежа")
                    ->setCellValue("M1", "Дата отправки на модератору")
                    ->setCellValue("N1", "Вес упаковки, кг.")
                    ->setCellValue("O1", "Утилизационный платеж за упаковку, тг")
                    ->setCellValue("P1", "Код ТН ВЭД упаковки")
                    ->getStyle('A1:P1')
                    ->applyFromArray($yellow_style);

                $counter = 2;
                $cnt = 0;

                $sqls = array($goods);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $cnt++;
                        // если профиль в норме
                        if ($row['pid']) {
                            $num = $counter;
                            $counter++;

                            $price = '—';
                            if ($row['cost'] > 0) {
                                $price = $row['cost'];
                            }

                            $dt_approve = '—';
                            if ($row['date_approve'] > 0) {
                                $dt_approve = date('d.m.Y', convertTimeZone($row['date_approve']));
                            }

                            $md_dt_sent = '—';
                            if ($row['md_dt_sent'] > 0) {
                                $md_dt_sent = date('d.m.Y', convertTimeZone($row['md_dt_sent']));
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $row['title'])
                                ->setCellValue("C$num", $row['idnum'])
                                ->setCellValue("D$num", $row['tn_code'])
                                ->setCellValue("E$num", mb_strtoupper($row['tn_name']))
                                ->setCellValue("F$num", date('d.m.Y', convertTimeZone($row['date_import'])))
                                ->setCellValue("G$num", date('d.m.Y', convertTimeZone($row['date_created'])))
                                ->setCellValue("H$num", $dt_approve)
                                ->setCellValue("I$num", $row['country'])
                                ->setCellValue("J$num", $row['city'])
                                ->setCellValue("K$num", $row['wg'])
                                ->setCellValue("L$num", $price)
                                ->setCellValue("M$num", $md_dt_sent)
                                ->setCellValue("N$num", $row['package_wg'])
                                ->setCellValue("O$num", $row['package_cost'])
                                ->setCellValue("P$num", $row['package_tnvd']);
                        }
                    }
                }

                if ($cnt == 0) {
                    $this->flash->error("# Не найден !");
                    return $this->response->redirect("/report_importer/");
                }

                // оформление

                $cc = $counter - 1;

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->getStyle("A1:P$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setWidth(50);
                $sh->getColumnDimension('C')->setAutoSize(true);
                $sh->getColumnDimension('D')->setAutoSize(true);
                $sh->getColumnDimension('E')->setWidth(60);
                $sh->getColumnDimension('F')->setAutoSize(true);
                $sh->getColumnDimension('G')->setAutoSize(true);
                $sh->getColumnDimension('H')->setAutoSize(true);
                $sh->getColumnDimension('I')->setAutoSize(true);
                $sh->getColumnDimension('J')->setAutoSize(true);
                $sh->getColumnDimension('K')->setAutoSize(true);
                $sh->getColumnDimension('L')->setAutoSize(true);
                $sh->getColumnDimension('M')->setAutoSize(true);
                $sh->getColumnDimension('N')->setAutoSize(true);
                $sh->getColumnDimension('O')->setAutoSize(true);
                $sh->getColumnDimension('P')->setAutoSize(true);
                $sh->getStyle("K2:K$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
                $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('############');
                $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('##################');
                $sh->getStyle("K2:K$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("L2:L$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("N2:N$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("O2:O$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:A$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("B2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("C2:C$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sh->getStyle("D2:D$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("E2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("F2:F$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("I2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("J2:J$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("G2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("H2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("K2:K$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sh->getStyle("L2:L$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sh->getStyle("M2:M$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("N2:N$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("O2:O$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("P2:P$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
            } else {
                $item_count = 0;
                $item_weight = 0;
                $item_sum = 0;
                // сводная форма
                $goods = "SELECT count(p.id) as item_count, 
                        SUM(goods.amount) as item_sum, 
                        SUM(goods.weight) as item_weight 
                  FROM profile p 
                    JOIN transaction t 
                    JOIN ref_country rc 
                    JOIN goods 
                    JOIN ref_tn_code tnc
                    JOIN user u 
                  WHERE t.profile_id = p.id AND 
                        goods.profile_id = p.id AND 
                        goods.ref_country = rc.id AND 
                        goods.ref_tn = tnc.id AND
                        p.user_id = u.id AND  
                        t.approve = 'GLOBAL' 
                        $search_by_user_idnum 
                        $limits 
                  ORDER BY p.id DESC";

                $sqls = array($goods);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $item_count += $row['item_count'];
                        $item_sum += $row['item_sum'];
                        $item_weight += $row['item_weight'];
                    }
                }
                echo json_encode(array(
                    "item_count" => $item_count, "item_weight" => number_format($item_weight, 2, ",", "&nbsp;"),
                    "item_sum" => number_format($item_sum, 2, ",", "&nbsp;")
                ));
                // конец сводной формы
            }

            mysqli_close($mc);
        }
    }

    // для Опреатор РОП
    public function bankAction()
    {
        // дайте переводов!
        $today = date("d.m.Y");
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = "AND paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = "AND paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $banks = "SELECT * FROM bank WHERE comment NOT LIKE '%депозит%' AND comment NOT LIKE '%процент%' AND comment NOT LIKE '%Выплата вознагражд%' AND iban_to IN ('KZ256017131000029119', 'KZ606017131000029459', 'KZ236017131000028670', 'KZ686017131000029412', 'KZ61926180219T620004', 'KZ34926180219T620005', 'KZ07926180219T620006', 'KZ77926180219T620007', 'KZ196010111000325234', 'KZ896010111000325235', 'KZ466010111000325233', 'KZ736010111000325232', 'KZ496018871000301461') $date_limit";

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

            $filename = "banks_" . $when . '.xlsx';
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

            $sh->setCellValue("A1", "ФИО, Название / БИН, ИИН отправителя")
                ->setCellValue("B1", "IBAN отправителя")
                ->setCellValue("C1", "Размер платежа")
                ->setCellValue("D1", "IBAN получателя")
                ->setCellValue("E1", "Дата и время")
                ->setCellValue("F1", "Назначение")
                ->setCellValue("G1", "Привязки")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($banks);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    $num = $counter;
                    $counter++;
                    if ($row['name_sender'] != '' || $row['rnn_sender'] != '') {
                        $client_name = $row['name_sender'] . '(' . $row['rnn_sender'] . ')';
                    } else {
                        $client_name = " — ";
                    }
                    // вставляем строку в лист
                    $sh->setCellValue("A$num", $client_name)
                        ->setCellValue("B$num", $row['iban_from'])
                        ->setCellValue("C$num", $row['amount'])
                        ->setCellValue("D$num", $row['iban_to'])
                        ->setCellValue("E$num", date("d.m.Y H:i", convertTimeZone($row['paid'])))
                        ->setCellValue("F$num", $row['comment'])
                        ->setCellValue("G$num", $row['transactions']);
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setWidth(30);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setWidth(80);
            $sh->getColumnDimension('G')->setWidth(40);
            $sh->getStyle("A1:A$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("A1:A$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("F2:F$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("G2:G$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("G2:G$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("A2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("G2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
                header('Content-Disposition: attachment; filename="' . $filename);
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

    // для Жасыл даму
    public function bankZdAction()
    {
        $today = date("d.m.Y");
        if ($this->request->isPost()) {
            $this->view->disable();
            // выдаем отчет
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = "AND paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = "AND paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $banks = "SELECT * FROM zd_bank WHERE comment NOT LIKE '%депозит%' AND comment NOT LIKE '%процент%' AND comment NOT LIKE '%Выплата вознагражд%' AND iban_to IN ('KZ20601A871001726091', 'KZ02601A871001725251', 'KZ07601A871001726131', 'KZ70601A871001726161',  'KZ62601A871001726111', 'KZ41601A871001726101', 'KZ28601A871001726141', 'KZ49601A871001726151', 'KZ83601A871001726121') $date_limit";

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

            $filename = "banks_zhasyl_damu_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников АО «Жасыл Даму»")
                ->setKeywords("отчет АО «Жасыл Даму» 2022")
                ->setCategory("Отчеты")
                ->setCompany("АО «Жасыл Даму»");

            // заполняем заявки по ТС
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Выгрузка');

            $sh->setCellValue("A1", "ФИО, Название / БИН, ИИН отправителя")
                ->setCellValue("B1", "IBAN отправителя")
                ->setCellValue("C1", "Размер платежа")
                ->setCellValue("D1", "IBAN получателя")
                ->setCellValue("E1", "Дата и время")
                ->setCellValue("F1", "Назначение")
                ->setCellValue("G1", "Привязки")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;

            $sqls = array($banks);

            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                // поехали!
                while ($row = mysqli_fetch_assoc($result)) {
                    $cnt++;
                    // если профиль в норме
                    $num = $counter;
                    $counter++;
                    if ($row['name_sender'] != '' || $row['rnn_sender'] != '') {
                        $client_name = $row['name_sender'] . '(' . $row['rnn_sender'] . ')';
                    } else {
                        $client_name = " — ";
                    }
                    // вставляем строку в лист
                    $sh->setCellValue("A$num", $client_name)
                        ->setCellValue("B$num", $row['iban_from'])
                        ->setCellValue("C$num", $row['amount'])
                        ->setCellValue("D$num", $row['iban_to'])
                        ->setCellValue("E$num", date("d.m.Y H:i", convertTimeZone($row['paid'])))
                        ->setCellValue("F$num", $row['comment'])
                        ->setCellValue("G$num", $row['transactions']);
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }
            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setWidth(30);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setWidth(80);
            $sh->getColumnDimension('G')->setWidth(40);
            $sh->getStyle("A1:A$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("A1:A$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("F2:F$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("G2:G$cc")->getActiveSheet()->getDefaultRowDimension($cc)->setRowHeight(-1);
            $sh->getStyle("G2:G$cc")->getAlignment()->setWrapText(true);
            $sh->getStyle("A2:E$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("G2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
                header('Content-Disposition: attachment; filename="' . $filename);
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

    public function fundAction()
    {
        $a = $this->session->get("auth");
        $today = date("d.m.Y");

        if ($this->request->isPost()) {
            $this->view->disable();

            // --- Параметры ---
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $dt_type = $this->request->getPost("dt_type");
            $status = $this->request->getPost("status");
            $fund_uid = $this->request->getPost("fund_uid");
            $withAnnullments = $this->request->getPost("withAnnullments");

            $search_by_user = '';
            if ($fund_uid && $fund_uid != 'all') {
                $search_by_user = " AND f.user_id = $fund_uid";
            }

            if (!$start) $start = date('d.m.Y');
            if (!$end) $end = date('d.m.Y');

            $date_limit = '';
            if ($dt_type == 2) {
                if ($start && $end) {
                    $date_limit = " AND f.md_dt_sent >= " . strtotime(date("$start 00:00:00")) . " AND f.md_dt_sent <= " . strtotime(date("$end 23:59:59"));
                }
            } elseif ($dt_type == 1) {
                if ($start && $end) {
                    $date_limit = " AND f.paid_dt >= " . strtotime(date("$start 00:00:00")) . " AND f.paid_dt <= " . strtotime(date("$end 23:59:59"));
                }
            } else {
                if ($start && $end) {
                    $date_limit = " AND f.created >= " . strtotime(date("$start 00:00:00")) . " AND f.created <= " . strtotime(date("$end 23:59:59"));
                }
            }

            $status_search = "AND f.approve = 'FUND_DONE'";
            if ($status == 1) {
                $status_search = "AND f.approve = 'FUND_PREAPPROVED' AND f.signed_by = 4";
            }

            // --- БД ---
            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);
            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            // --- Списки аннулирования ---
            $annulment_fund_list_sql = "SELECT fund_id FROM fund_car_histories WHERE status = 'FUND_ANNULMENT' GROUP BY fund_id";
            $annulment_fund_res = mysqli_query($mc, $annulment_fund_list_sql);
            $annulment_fund_list = [];
            while ($annulment_fund = mysqli_fetch_assoc($annulment_fund_res)) {
                $annulment_fund_list[] = $annulment_fund['fund_id'];
            }

            $annulment_goods_list_sql = "SELECT fund_id FROM fund_goods_histories WHERE status = 'FUND_ANNULMENT' GROUP BY fund_id";
            $annulment_goods_res = mysqli_query($mc, $annulment_goods_list_sql);
            $annulment_goods_list = [];
            while ($annulment_good = mysqli_fetch_assoc($annulment_goods_res)) {
                $annulment_goods_list[] = $annulment_good['fund_id'];
            }

            // --- Excel Стили ---
            $yellow_style = array(
                'font' => array('bold' => true),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER),
                'fill' => array(
                    'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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
            $filename = "funds_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()->setCreator("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setCompany("ТОО «Оператор РОП»");

            $total_cnt = 0;

            // ==========================================================
            // ЛИСТ 1: ТРАНСПОРТНЫЕ СРЕДСТВА (без изменений логики)
            // ==========================================================

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС|ССХТ');

            $sh->setCellValue("A1", " № плат поручения")
                ->setCellValue("B1", " № заявления")
                ->setCellValue("C1", " Тип финансирования")
                ->setCellValue("D1", "Сумма платежа")
                ->setCellValue("E1", "ФИО / Наименование")
                ->setCellValue("F1", "ИИН / БИН")
                ->setCellValue("G1", "Количество")
                ->setCellValue("H1", "Дата оплаты")
                ->getStyle('A1:H1')->applyFromArray($yellow_style);

            $sql_cars = "SELECT f.id as f_id, 
                        f.number as f_number,  
                       f.type as type, 
                       f.amount as amount,
                       f.old_amount as old_amount,
                       f.approve as approve,
                       u.idnum as idnum,
                       f.paid_dt as dt_done,
                       IF(u.user_type_id = 2, u.org_name, u.fio) as title,
                       (select count(id) from fund_car c where f.id = c.fund_id ) as item_count,
                       (select ref_car_type_id from fund_car c where f.id = c.fund_id LIMIT 1) as ref_car_type_id
                    FROM fund_profile f
                        JOIN user u ON f.user_id = u.id
                    WHERE 1=1 
                          $search_by_user 
                          $status_search 
                          $date_limit
                    GROUP BY f.id
                    HAVING item_count > 0
                    ORDER BY f.id ASC";

            $counter = 2;
            $result = mysqli_query($mc, $sql_cars);

            while ($row = mysqli_fetch_assoc($result)) {
                $total_cnt++;
                $num = $counter;
                $counter++;

                if ($row['ref_car_type_id'] == 4 || $row['ref_car_type_id'] == 5) {
                    $car_type = ($row['type'] == 'EXP') ? 'ЭКСП СХ' : 'СХ';
                } else {
                    $car_type = ($row['type'] == 'EXP') ? 'ЭКСП ТС' : 'ТС';
                }

                $dt_done = ($row['dt_done'] > 0) ? date('d.m.Y', convertTimeZone($row['dt_done'])) : '_';
                $amount = $withAnnullments ? $row['amount'] : $row['old_amount'];
                $item_count = $row['item_count'];
                $annulment_logs_html = null;

                if ($withAnnullments) {
                    if (in_array($row['f_id'], $annulment_fund_list)) {
                        $annulment_fund_cars = FundCarHistories::find([
                            "conditions" => "fund_id = :fund_id: and status = :status:",
                            "bind" => ["fund_id" => $row['f_id'], "status" => "FUND_ANNULMENT"]
                        ]);
                        $annulled_cnt = 0;
                        if ($annulment_fund_cars) {
                            foreach ($annulment_fund_cars as $afc) {
                                $annulled_cnt++;
                                $annulment_logs_html .= ' - VIN: ' . $afc->vin . ' от ' . date("d.m.Y H:i", convertTimeZone($afc->dt)) . ' (-' . $afc->cost . ' тг);' . PHP_EOL;
                            }
                        }
                        $item_count += $annulled_cnt;
                    }

                    $sh->setCellValue("I1", "Аннулирование")->getStyle('I1')->applyFromArray($yellow_style);
                    $sh->setCellValue("I$num", $annulment_logs_html);
                }

                $sh->setCellValue("A$num", '')
                    ->setCellValue("B$num", $row['f_number'])
                    ->setCellValue("C$num", $car_type)
                    ->setCellValue("D$num", $amount)
                    ->setCellValue("E$num", $row['title'])
                    ->setCellValue("F$num", $row['idnum'])
                    ->setCellValue("G$num", $item_count)
                    ->setCellValue("H$num", $dt_done);
            }

            $cc = $counter - 1;
            $sh->getStyle("A1:H$cc")->applyFromArray($border_all);
            foreach (range('A', 'H') as $col) $sh->getColumnDimension($col)->setAutoSize(true);
            $sh->getColumnDimension('E')->setWidth(50);
            $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("F2:F$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("E2:E$cc")->getAlignment()->setWrapText(true);
            if ($withAnnullments) {
                $sh->getStyle("A1:I$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('I')->setAutoSize(true);
                $sh->getStyle("I1:I$cc")->getAlignment()->setWrapText(true);
            }


            // ==========================================================
            // ЛИСТ 2: ТОВАРЫ (изменена логика на SUM(weight))
            // ==========================================================

            $objEx->createSheet();
            $sh2 = $objEx->setActiveSheetIndex(1);
            $sh2->setTitle('Автокомпоненты');

            $sh2->setCellValue("A1", " № плат поручения")
                ->setCellValue("B1", " № заявления")
                ->setCellValue("C1", " Тип финансирования")
                ->setCellValue("D1", "Сумма платежа")
                ->setCellValue("E1", "ФИО / Наименование")
                ->setCellValue("F1", "ИИН / БИН")
                ->setCellValue("G1", "Общий вес (кг)") // Заголовок изменен
                ->setCellValue("H1", "Дата оплаты")
                ->getStyle('A1:H1')->applyFromArray($yellow_style);

            // SQL: Заменили count(id) на sum(weight)
            // COALESCE(..., 0) используется, чтобы не получить NULL
            $sql_goods = "SELECT f.id as f_id, 
                        f.number as f_number,  
                       f.type as type, 
                       f.amount as amount,
                       f.old_amount as old_amount,
                       f.approve as approve,
                       u.idnum as idnum,
                       f.paid_dt as dt_done,
                       IF(u.user_type_id = 2, u.org_name, u.fio) as title,
                       (select COALESCE(SUM(g.weight), 0) from fund_goods g where f.id = g.fund_id ) as total_weight
                    FROM fund_profile f
                        JOIN user u ON f.user_id = u.id
                    WHERE 1=1 
                          $search_by_user 
                          $status_search 
                          $date_limit
                    GROUP BY f.id
                    HAVING total_weight > 0
                    ORDER BY f.id ASC";

            $counter = 2;
            $result_goods = mysqli_query($mc, $sql_goods);

            while ($row = mysqli_fetch_assoc($result_goods)) {
                $total_cnt++;
                $num = $counter;
                $counter++;

                $item_type = 'Автокомпоненты';
                if ($row['type'] == 'EXP') $item_type = 'ЭКСП ТОВАРЫ';

                $dt_done = ($row['dt_done'] > 0) ? date('d.m.Y', convertTimeZone($row['dt_done'])) : '_';
                $amount = $withAnnullments ? $row['amount'] : $row['old_amount'];

                // Берем вес из базы
                $current_weight = $row['total_weight'];

                $annulment_logs_html = null;

                if ($withAnnullments) {
                    if (in_array($row['f_id'], $annulment_goods_list)) {
                        $annulment_fund_goods = FundGoodsHistories::find([
                            "conditions" => "fund_id = :fund_id: and status = :status:",
                            "bind" => ["fund_id" => $row['f_id'], "status" => "FUND_ANNULMENT"]
                        ]);

                        $annulled_weight_sum = 0;
                        if ($annulment_fund_goods) {
                            foreach ($annulment_fund_goods as $afg) {
                                // Суммируем вес аннулированных позиций
                                $w = isset($afg->weight) ? $afg->weight : 0;
                                $annulled_weight_sum += $w;

                                $good_name = isset($afg->name) ? $afg->name : 'Товар';
                                $annulment_logs_html .= ' - ' . $good_name . ' (' . $w . ' кг) от ' . date("d.m.Y H:i", convertTimeZone($afg->dt)) . ' (-' . $afg->cost . ' тг);' . PHP_EOL;
                            }
                        }
                        // Добавляем аннулированный вес к текущему для отображения "как было"
                        $current_weight += $annulled_weight_sum;
                    }

                    $sh2->setCellValue("I1", "Аннулирование")->getStyle('I1')->applyFromArray($yellow_style);
                    $sh2->setCellValue("I$num", $annulment_logs_html);
                }

                $sh2->setCellValue("A$num", '')
                    ->setCellValue("B$num", $row['f_number'])
                    ->setCellValue("C$num", $item_type)
                    ->setCellValue("D$num", $amount)
                    ->setCellValue("E$num", $row['title'])
                    ->setCellValue("F$num", $row['idnum'])
                    ->setCellValue("G$num", $current_weight) // Выводим вес
                    ->setCellValue("H$num", $dt_done);
            }

            // Оформление Листа 2
            $cc = $counter - 1;
            $sh2->getStyle("A1:H$cc")->applyFromArray($border_all);
            foreach (range('A', 'H') as $col) $sh2->getColumnDimension($col)->setAutoSize(true);
            $sh2->getColumnDimension('E')->setWidth(50);
            $sh2->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh2->getStyle("G2:G$cc")->getNumberFormat()->setFormatCode('### ### ##0.000_-'); // Формат для веса (3 знака)
            $sh2->getStyle("F2:F$cc")->getNumberFormat()->setFormatCode('############');
            $sh2->getStyle("E2:E$cc")->getAlignment()->setWrapText(true);
            $sh2->getStyle("A2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            if ($withAnnullments) {
                $sh2->getStyle("A1:I$cc")->applyFromArray($border_all);
                $sh2->getColumnDimension('I')->setAutoSize(true);
                $sh2->getStyle("I1:I$cc")->getAlignment()->setWrapText(true);
            }

            $objEx->setActiveSheetIndex(0);

            if ($total_cnt == 0) {
                $this->flash->error("# Не найдено заявок ни по ТС, ни по Товарам!");
                return $this->response->redirect("/report_importer/");
            }

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) ob_end_clean();
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename);
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

    public function fundSupermoderatorAction()
    {
        $today = date("d.m.Y");
        if ($this->request->isPost()) {
            $this->view->disable();

            // --- Параметры фильтрации ---
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) $start = date('d.m.Y');
            if (!$end) $end = date('d.m.Y');

            $date_limit = '';
            if ($start) {
                if ($end) {
                    $date_limit = " AND f.paid_dt >= " . strtotime(date("d.m.Y 00:00:00", strtotime($start))) .
                        " AND f.paid_dt <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " AND f.paid_dt >= " . strtotime(date("d.m.Y 00:00:00", strtotime($start))) .
                        " AND f.paid_dt <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            // --- БД ---
            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);
            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            // --- Стили ---
            $yellow_style = array(
                'font' => array('bold' => true),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER),
                'fill' => array(
                    'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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
            $filename = "fund_supermod_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();
            $objEx->getProperties()->setCreator("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setCompany("АО «Жасыл даму»");

            $total_cnt = 0; // Общий счетчик строк

            // ==========================================================
            // ЛИСТ 1: ТРАНСПОРТНЫЕ СРЕДСТВА
            // ==========================================================

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС|ССХТ');

            $sh->setCellValue("A1", "Производитель")
                ->setCellValue("B1", "ID")
                ->setCellValue("C1", "Объем")
                ->setCellValue("D1", "Сумма финансирования, тенге")
                ->setCellValue("E1", "VIN")
                ->setCellValue("F1", "Дата производства")
                ->setCellValue("G1", "Категория")
                ->setCellValue("H1", "Дата оплаты")
                ->getStyle('A1:H1')->applyFromArray($yellow_style);

            $sql_cars = "SELECT f.number as f_number,  
                   c.vin as vin, 
                   c.volume as volume, 
                   c.cost as cost, 
                   f.paid_dt as dt_done, 
                   u.idnum as idnum,
                   c.date_produce as date_produce,
                    CASE WHEN u.user_type_id = 2 THEN u.org_name
                         WHEN u.user_type_id = 1 THEN u.fio
                    END as title,
                    rcc.name as category
                FROM fund_car c
                    JOIN ref_car_cat rcc ON c.ref_car_cat = rcc.id
                    JOIN fund_profile f ON f.id = c.fund_id
                    JOIN user u ON f.user_id = u.id
                WHERE f.approve = 'FUND_DONE' 
                      $date_limit
                GROUP BY c.id       
                ORDER BY f.paid_dt ASC";

            $counter = 2;
            $result = mysqli_query($mc, $sql_cars);

            while ($row = mysqli_fetch_assoc($result)) {
                $total_cnt++;
                $num = $counter;
                $counter++;
                $dt_prod = date("d.m.Y", convertTimeZone($row['date_produce']));

                $sh->setCellValue("A$num", $row['title'] . ' (' . $row['idnum'] . ')')
                    ->setCellValue("B$num", $row['f_number'])
                    ->setCellValue("C$num", $row['volume'])
                    ->setCellValue("D$num", __money($row['cost']))
                    ->setCellValue("E$num", $row['vin'])
                    ->setCellValue("F$num", $dt_prod)
                    ->setCellValue("G$num", $this->translator->_($row['category']))
                    ->setCellValue("H$num", date('Y-m-d H:i', convertTimeZone($row['dt_done'])));
            }

            // Оформление Листа 1
            $cc = $counter - 1;
            $sh->getStyle("A1:H$cc")->applyFromArray($border_all);
            foreach (range('A', 'H') as $col) $sh->getColumnDimension($col)->setAutoSize(true);
            $sh->getStyle("B2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


            // ==========================================================
            // ЛИСТ 2: ТОВАРЫ / УПАКОВКА
            // ==========================================================

            $objEx->createSheet();
            $sh2 = $objEx->setActiveSheetIndex(1);
            $sh2->setTitle('Автокомпоненты');

            // Исправлена нумерация колонок (E, F, G, H, I)
            $sh2->setCellValue("A1", "Производитель")
                ->setCellValue("B1", "ID")
                ->setCellValue("C1", "Вес (кг)")
                ->setCellValue("D1", "Сумма финансирования, тенге")
                ->setCellValue("E1", "Номер счет-фактуры")
                ->setCellValue("F1", "Дата счет-фактуры")
                ->setCellValue("G1", "Дата производства")
                ->setCellValue("H1", "код ТНВЭД")
                ->setCellValue("I1", "Дата оплаты")
                ->getStyle('A1:I1')->applyFromArray($yellow_style);

            // SQL для товаров: добавлены basis, basis_date, tn_code
            $sql_goods = "SELECT f.number as f_number,  
                   g.basis as basis, 
                   g.basis_date as basis_date, 
                   rtc.code as tn_code, 
                   g.weight as weight, 
                   g.cost as cost, 
                   f.paid_dt as dt_done, 
                   u.idnum as idnum,
                   g.date_produce as date_produce,
                    CASE WHEN u.user_type_id = 2 THEN u.org_name
                         WHEN u.user_type_id = 1 THEN u.fio
                    END as title
                FROM fund_goods g
                    JOIN fund_profile f ON f.id = g.fund_id
                    JOIN user u ON f.user_id = u.id
                LEFT JOIN ref_tn_code rtc ON rtc.id = g.ref_tn
                WHERE f.approve = 'FUND_DONE' 
                      $date_limit
                GROUP BY g.id       
                ORDER BY f.paid_dt ASC";

            $counter = 2;
            $result_goods = mysqli_query($mc, $sql_goods);

            while ($row = mysqli_fetch_assoc($result_goods)) {
                $total_cnt++;
                $num = $counter;
                $counter++;

                $dt_prod = $row['date_produce'];
                $tn_code = isset($row['tn_code']) ? $row['tn_code'] : '-';

                // Форматирование даты счета-фактуры
                $basis_date = ($row['basis_date'] > 0) ? date('Y-m-d', $row['basis_date']) : '';

                $sh2->setCellValue("A$num", $row['title'] . ' (' . $row['idnum'] . ')')
                    ->setCellValue("B$num", $row['f_number'])
                    ->setCellValue("C$num", $row['weight'])
                    ->setCellValue("D$num", __money($row['cost']))
                    ->setCellValue("E$num", $row['basis'])
                    ->setCellValue("F$num", $basis_date)
                    ->setCellValue("G$num", $dt_prod)
                    ->setCellValue("H$num", $tn_code)
                    ->setCellValue("I$num", date('Y-m-d H:i', convertTimeZone($row['dt_done'])));
            }

            // Оформление Листа 2 (Теперь диапазон A-I)
            $cc = $counter - 1;
            $sh2->getStyle("A1:I$cc")->applyFromArray($border_all);
            foreach (range('A', 'I') as $col) $sh2->getColumnDimension($col)->setAutoSize(true);
            $sh2->getStyle("B2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh2->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.000_-');

            // Возвращаемся на первый лист
            $objEx->setActiveSheetIndex(0);

            // ==========================================================

            if ($total_cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename);
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

    public function kppsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            // режим
            $mode = $this->request->get("mode");

            // лимиты и фильтры
            $limits = '';
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");
            $start_import = $this->request->getPost("dstart_import");
            $end_import = $this->request->getPost("dend_import");
            $start_md_dt_sent = $this->request->getPost("dstart_md_dt_sent");
            $end_md_dt_sent = $this->request->getPost("dend_md_dt_sent");
            $start_approve = $this->request->getPost("dstart_global");
            $end_approve = $this->request->getPost("dend_global");

            if (!$start && !$start_import && !$start_approve && !$start_md_dt_sent) {
                $start = date('d.m.Y');
                $end = date('d.m.Y');
            }

            // по дате заявки
            $_l = '';
            if ($start) {
                if ($end) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end) - strtotime($start);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end = date('d.m.Y', strtotime($start) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $_l = " AND p.created >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND p.created <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }
            $limits .= $_l;

            // по дате импорта
            $_l = '';
            if ($start_import) {
                if ($end_import) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_import) - strtotime($start_import);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_import = date('d.m.Y', strtotime($start_import) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND kpp.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND kpp.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_import)));
                } else {
                    $_l = " AND kpp.date_import >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_import))) . " AND kpp.date_import <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_import)));
                }
            }
            $limits .= $_l;

            // по дате отправки на модератору
            $_l = '';
            if ($start_md_dt_sent) {
                if ($end_md_dt_sent) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_md_dt_sent) - strtotime($start_md_dt_sent);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_md_dt_sent = date('d.m.Y', strtotime($start_md_dt_sent) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_md_dt_sent)));
                } else {
                    $_l = " AND t.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_md_dt_sent))) . " AND t.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_md_dt_sent)));
                }
            }
            $limits .= $_l;

            // по дате выдачи
            $_l = '';
            if ($start_approve) {
                if ($end_approve) {
                    if ($mode != 'json') {
                        $_dur = strtotime($end_approve) - strtotime($start_approve);
                        if ($_dur > (31 * 24 * 3600)) {
                            $end_approve = date('d.m.Y', strtotime($start_approve) + (31 * 24 * 3600));
                        }
                    }
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($end_approve)));
                } else {
                    $_l = " AND t.dt_approve >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start_approve))) . " AND t.dt_approve <= " . strtotime(date("d.m.Y 23:59:59",
                            strtotime($start_approve)));
                }
            }
            $limits .= $_l;

            // TODO — реализовать фильтр по дате реализации для товаров

            $l_title = $this->request->getPost("title");
            $l_bin = $this->request->getPost("bin");

            // TITLE
            $getUsersProfile = '';

            if ($l_title) {
                $cd = CompanyDetail::findFirst(
                    array(
                        "name LIKE :name:",
                        "bind" => array(
                            "name" => "%$l_title%"
                        )
                    ));

                if ($cd) {
                    $getUsersProfile = " AND p.user_id = $cd->user_id ";
                } else {
                    $user_id = [];
                    $pd = PersonDetail::find(
                        array(
                            "(last_name LIKE :name: OR first_name LIKE :name: OR parent_name LIKE :name:) OR CONCAT(last_name, ' ', first_name, ' ',parent_name) LIKE :name:",
                            "bind" => array(
                                "name" => "%$l_title%"
                            )
                        ));
                    if ($pd) {
                        foreach ($pd as $person) {
                            $user_id[] = $person->user_id;
                        }
                        $users = join(",", $user_id);
                    }
                    $getUsersProfile = " AND p.user_id IN($users)";
                }
            }

            // ИИН, БИН
            if ($l_bin) {
                $user = User::findFirstByIdnum($l_bin);
                if ($user) {
                    $getUsersProfile = " AND p.user_id = $user->id ";
                }
            }

            $_l = '';
            $l_country = $this->request->getPost("country");
            if ($l_country) {
                $_l = " AND kpp.ref_country = $l_country";
            }
            $limits .= $_l;

            // VOLUME LIMITS
            $_l = '';
            $volume_min = $this->request->getPost("volume_min");
            $volume_max = $this->request->getPost("volume_max");
            if ($volume_max) {
                $_l .= " AND kpp.weight < $volume_max";
            }
            if ($volume_min) {
                $_l .= " AND kpp.weight > $volume_min";
            }
            $limits .= $_l;

            // TOTAL LIMITS
            $_l = '';
            $total_min = $this->request->getPost("total_min");
            $total_max = $this->request->getPost("total_max");
            if ($total_max) {
                $_l .= " AND kpp.amount < $total_max";
            }
            if ($total_min) {
                $_l .= " AND kpp.amount > $total_min";
            }
            $limits .= $_l;

            // конец лимитов
            // ***

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            if ($mode != 'json') {
                $kpp = "SELECT p.id as pid,
                                tnc.code as tn_code,
                                kpp.date_import as date_import, 
                                rc.name as country, 
                                kpp.weight as wg, 
                                kpp.invoice_sum as invoice_sum, 
                                kpp.currency as currency, 
                                kpp.invoice_sum_currency as sum_cr, 
                                kpp.basis as basis, 
                                kpp.amount as amount,
                                kpp.id as id,
                                t.dt_approve as dt_approve,
                                t.md_dt_sent as md_dt_sent
                          FROM profile p 
                            JOIN ref_country rc 
                            JOIN kpp kpp
                            JOIN ref_tn_code tnc 
                            JOIN transaction t
                          WHERE t.profile_id = p.id 
                                AND kpp.profile_id = p.id 
                                AND kpp.ref_country = rc.id 
                                AND kpp.ref_tn = tnc.id
                                AND t.approve = 'GLOBAL'
                                 $getUsersProfile
                                 $limits 
                                ORDER BY p.id DESC";

                $yellow_style = array(
                    'font' => array('bold' => true),
                    'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER), 'fill' => array(
                        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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

                $sh->setCellValue("A1", "Номер заявки")
                    ->setCellValue("B1", "Наименование компании")
                    ->setCellValue("C1", "БИН")
                    ->setCellValue("D1", "Код ТН ВЭД")
                    ->setCellValue("E1", "Номер счет-фактуры")
                    ->setCellValue("F1", "Номер позиции")
                    ->setCellValue("G1", "Сумма в валюте")
                    ->setCellValue("H1", "Курс валюты")
                    ->setCellValue("I1", "Сумма в тенге")
                    ->setCellValue("J1", "Дата импорта/производства")
                    ->setCellValue("K1", "Страна импорта/производства")
                    ->setCellValue("L1", "Вес, кг")
                    ->setCellValue("M1", "Дата выдачи ДПП")
                    ->setCellValue("N1", "Сумма УП")
                    ->setCellValue("O1", "Дата отправки на модератору")
                    ->getStyle('A1:O1')
                    ->applyFromArray($yellow_style);

                $counter = 2;
                $cnt = 0;

                $sqls = array($kpp);
                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $cnt++;
                        // если профиль в норме
                        if ($row['pid']) {
                            // если указано ФИО или название
                            $client = __getClientTitle($row['pid']);

                            $num = $counter;
                            $counter++;

                            $dt_approve = '—';
                            if ($row['dt_approve'] > 0) {
                                $dt_approve = date('d.m.Y', convertTimeZone($row['dt_approve']));
                            }

                            $md_dt_sent = '—';
                            if ($row['md_dt_sent'] > 0) {
                                $md_dt_sent = date('d.m.Y', convertTimeZone($row['md_dt_sent']));
                            }

                            // вставляем строку в лист
                            $sh->setCellValue("A$num", str_pad($row['pid'], 11, 0, STR_PAD_LEFT))
                                ->setCellValue("B$num", $client['title'])
                                ->setCellValue("C$num", $client['idnum'])
                                ->setCellValue("D$num", $row['tn_code'])
                                ->setCellValue("E$num", $row['basis'])
                                ->setCellValue("F$num", $row['id'])
                                ->setCellValue("G$num", $row['sum_cr'])
                                ->setCellValue("H$num", $row['currency'])
                                ->setCellValue("I$num", $row['invoice_sum'])
                                ->setCellValue("J$num", date('d.m.Y', convertTimeZone($row['date_import'])))
                                ->setCellValue("K$num", $row['country'])
                                ->setCellValue("L$num", $row['wg'])
                                ->setCellValue("M$num", $dt_approve)
                                ->setCellValue("N$num", $row['amount'])
                                ->setCellValue("O$num", $md_dt_sent);
                        }
                    }
                }

                if ($cnt == 0) {
                    $this->flash->error("# Не найден !");
                    return $this->response->redirect("/report_importer/");
                }

                // оформление

                $cc = $counter - 1;

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->getStyle("A1:O$cc")->applyFromArray($border_all);
                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setWidth(50);
                $sh->getColumnDimension('C')->setAutoSize(true);
                $sh->getColumnDimension('D')->setAutoSize(true);
                $sh->getColumnDimension('E')->setAutoSize(true);
                $sh->getColumnDimension('F')->setAutoSize(true);
                $sh->getColumnDimension('G')->setAutoSize(true);
                $sh->getColumnDimension('H')->setAutoSize(true);
                $sh->getColumnDimension('I')->setAutoSize(true);
                $sh->getColumnDimension('J')->setAutoSize(true);
                $sh->getColumnDimension('K')->setAutoSize(true);
                $sh->getColumnDimension('L')->setAutoSize(true);
                $sh->getColumnDimension('M')->setAutoSize(true);
                $sh->getColumnDimension('N')->setAutoSize(true);
                $sh->getColumnDimension('O')->setAutoSize(true);
                $sh->getStyle("K2:K$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
                $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('############');
                $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode('##################');
                $sh->getStyle("G2:G$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("I2:I$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("L2:L$cc")->getNumberFormat()->setFormatCode('### ### ##0.000_-');
                $sh->getStyle("N2:N$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:O$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
            } else {
                $item_count = 0;
                $item_weight = 0;
                $item_sum = 0;
                // сводная форма
                $kpp = "SELECT count(p.id) as item_count,
                            SUM(kpp.amount) as item_sum, 
                            SUM(kpp.weight) as item_weight
                          FROM profile p 
                            JOIN ref_country rc 
                            JOIN kpp kpp
                            JOIN ref_tn_code tnc 
                            JOIN transaction t
                          WHERE t.profile_id = p.id 
                                AND kpp.profile_id = p.id 
                                AND kpp.ref_country = rc.id 
                                AND kpp.ref_tn = tnc.id
                                AND t.approve = 'GLOBAL'
                                $getUsersProfile
                                $limits 
                                ORDER BY p.id DESC";
                $sqls = array($kpp);

                foreach ($sqls as $sql) {
                    $result = mysqli_query($mc, $sql);
                    // поехали!
                    while ($row = mysqli_fetch_assoc($result)) {
                        $item_count += $row['item_count'];
                        $item_sum += $row['item_sum'];
                        $item_weight += $row['item_weight'];
                    }
                }
                echo json_encode(array(
                    "item_count" => $item_count, "item_weight" => number_format($item_weight, 2, ",", "&nbsp;"),
                    "item_sum" => number_format($item_sum, 2, ",", "&nbsp;")
                ));
                // конец сводной формы
            }

            mysqli_close($mc);
        }
    }

    // Выгрузка учет неидентифицированных платежей в Excel(Оператор РОП)

    public function unidentifiedPaymentsAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $_dur = strtotime($end) - strtotime($start);
                    if ($_dur > (31 * 24 * 3600)) {
                        $end = date('d.m.Y', strtotime($start) + (5 * 24 * 3600));
                    }
                    $date_limit = "  b.paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND b.paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " b.paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND b.paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $payments = "SELECT b.iban_from as iban_from,
                              b.amount as amount,
                              b.paid as paid, 
                              i.name_sender as name_sender,
                              i.rnn_sender as rnn_sender 
                          FROM bank AS b
                              INNER JOIN bank_income AS i ON b.account_num = i.statement_reference  
                          WHERE $date_limit
                              AND b.comment NOT LIKE '%депозит%' 
                              AND b.comment NOT LIKE '%процент%' 
                              AND b.comment NOT LIKE '%вознагражд%'
                              AND i.rnn_sender NOT LIKE '" . ROP_BIN . "' 
                              AND (b.transactions = '' OR b.transactions IS NULL) ORDER BY b.id DESC";

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

            $filename = "Учет_неидентифицированных_платежей_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет по внутренним пользователям")
                ->setSubject("Отчет по внутренним пользователям")
                ->setDescription("Отчет для сотрудников ТОО «Оператор РОП»")
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany("ТОО «Оператор РОП»");

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Банковские транзакции');

            $sh->setCellValue("A1", "№")
                ->setCellValue("B1", "IBAN-отправителя")
                ->setCellValue("C1", "Размер платежа")
                ->setCellValue("D1", "БИН/ИИН ")
                ->setCellValue("E1", "Наименование/ФИО")
                ->setCellValue("F1", "Дата и время")
                ->setCellValue("G1", "Причина")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;
            $i = 0;

            $sqls = array($payments);
            // Profile ID list
            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                while ($row = mysqli_fetch_array($result)) {
                    $cnt++;
                    // Send to reviews

                    if ($row[1]) {
                        $num = $counter;
                        $counter++;
                        $i++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", $i)
                            ->setCellValue("B$num", $row['iban_from'])
                            ->setCellValue("C$num", $row['amount'])
                            ->setCellValue("D$num", $row['rnn_sender'])
                            ->setCellValue("E$num", $row['name_sender'])
                            ->setCellValue("F$num", date('d.m.Y H:i', convertTimeZone($row['paid'])));
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setWidth(40);
            // $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("C2:C$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
            $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("A2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            //==================================

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="Учет_неидентифицированных_платежей_' . date('d.m.Y',
                        $when) . '.xlsx"');
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

// Выгрузка учет неидентифицированных платежей в Excel(АО Жасыл даму)

    public function unidentifiedPaymentsZdAction()
    {
        if ($this->request->isPost()) {
            $this->view->disable();

            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $_dur = strtotime($end) - strtotime($start);
                    //if($_dur > (31*24*3600)) { $end = date('d.m.Y', strtotime($start)+(5*24*3600)); }
                    $date_limit = " paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " paid >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND paid <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            $payments = "SELECT iban_from as iban_from,
                          amount as amount,
                          paid as paid, 
                          name_sender as name_sender,
                          rnn_sender as rnn_sender 
                      FROM zd_bank
                      WHERE $date_limit
                          AND comment NOT LIKE '%депозит%' 
                          AND comment NOT LIKE '%процент%' 
                          AND comment NOT LIKE '%вознагражд%'
                          AND rnn_sender NOT LIKE '" . ZHASYL_DAMU_BIN . "' 
                          AND (transactions = '' OR transactions IS NULL) ORDER BY id DESC";

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

            $filename = "Учет_неидентифицированных_платежей_Жасыл_даму" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new Spreadsheet();

            $objEx->getProperties()->setCreator("recycle.kz")
                ->setLastModifiedBy("recycle.kz")
                ->setTitle("Отчет по внутренним пользователям")
                ->setSubject("Отчет по внутренним пользователям")
                ->setDescription("Отчет для сотрудников" . ZHASYL_DAMU)
                ->setKeywords("отчет роп 2020")
                ->setCategory("Отчеты")
                ->setCompany(ZHASYL_DAMU);

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('Банковские транзакции');

            $sh->setCellValue("A1", "№")
                ->setCellValue("B1", "IBAN-отправителя")
                ->setCellValue("C1", "Размер платежа")
                ->setCellValue("D1", "БИН/ИИН ")
                ->setCellValue("E1", "Наименование/ФИО")
                ->setCellValue("F1", "Дата и время")
                ->setCellValue("G1", "Причина")
                ->getStyle('A1:G1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cnt = 0;
            $i = 0;

            $sqls = array($payments);
            // Profile ID list
            foreach ($sqls as $sql) {
                $result = mysqli_query($mc, $sql);
                while ($row = mysqli_fetch_array($result)) {
                    $cnt++;
                    // Send to reviews

                    if ($row[1]) {
                        $num = $counter;
                        $counter++;
                        $i++;

                        // вставляем строку в лист
                        $sh->setCellValue("A$num", $i)
                            ->setCellValue("B$num", $row['iban_from'])
                            ->setCellValue("C$num", __money($row['amount']))
                            ->setCellValue("D$num", $row['rnn_sender'])
                            ->setCellValue("E$num", $row['name_sender'])
                            ->setCellValue("F$num", date('d.m.Y H:i', convertTimeZone($row['paid'])));
                    }
                }
            }

            if ($cnt == 0) {
                $this->flash->error("# Не найден !");
                return $this->response->redirect("/report_importer/");
            }

            // оформление

            $cc = $counter - 1;

            $sh = $objEx->setActiveSheetIndex(0);
            $sh->getStyle("A1:G$cc")->applyFromArray($border_all);
            $sh->getColumnDimension('A')->setAutoSize(true);
            $sh->getColumnDimension('B')->setAutoSize(true);
            $sh->getColumnDimension('C')->setAutoSize(true);
            $sh->getColumnDimension('D')->setAutoSize(true);
            $sh->getColumnDimension('E')->setAutoSize(true);
            $sh->getColumnDimension('F')->setAutoSize(true);
            $sh->getColumnDimension('G')->setWidth(40);
            // $sh->getStyle("E2:E$cc")->getNumberFormat()->setFormatCode('############');
            $sh->getStyle("D2:D$cc")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            $sh->getStyle("A2:G$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            //==================================

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="Учет_неидентифицированных_платежей_Жасыл_даму_' . date('d.m.Y',
                        $when) . '.xlsx"');
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

    public function fundDetailedAction()
    {
        $this->view->disable();
        $today = date("d.m.Y");

        if ($this->request->isPost()) {
            $start = $this->request->getPost("dstart");
            $end = $this->request->getPost("dend");

            if (!$start) {
                $start = date('d.m.Y');
            }
            if (!$end) {
                $end = date('d.m.Y');
            }

            $date_limit = '';

            if ($start) {
                if ($end) {
                    $date_limit = " f.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND f.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59", strtotime($end)));
                } else {
                    $date_limit = " f.md_dt_sent >= " . strtotime(date("d.m.Y 00:00:00",
                            strtotime($start))) . " AND f.md_dt_sent <= " . strtotime(date("d.m.Y 23:59:59", strtotime($start)));
                }
            }

            $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password, $this->config->database->dbname);

            mysqli_set_charset($mc, 'utf8');
            mysqli_select_db($mc, $this->config->database->dbname);

            // --- ЗАПРОС 1: ТРАНСПОРТ (CARS) ---
            $sqlCar = "SELECT f.number as f_number,
                f.type as f_type,  
                c.vin as vin, 
                c.fund_id as fund_id,
                c.volume as volume, 
                c.cost as cost,
                f.paid_dt as dt_done,
                c.ref_car_type_id as ref_car_type_id, 
                u.idnum as idnum,
                IF(u.user_type_id = 2, u.org_name, u.fio) as title,
               c.profile_id as profile_id,
               t.dt_approve as dt_approve            
        FROM fund_car c
        JOIN fund_profile f
        JOIN user u
        LEFT JOIN transaction t ON c.profile_id = t.profile_id
        WHERE 
            f.id = c.fund_id AND
            f.user_id = u.id AND
            f.approve = 'FUND_DONE' AND
            $date_limit 
        GROUP BY c.id";

            // --- ЗАПРОС 2: ТОВАРЫ (GOODS) ---
            // Оптимизация: JOIN с таблицей кодов ТНВЭД сразу
            $sqlGoods = "SELECT f.number as f_number,
                f.type as f_type,  
                c.ref_tn as ref_tn, 
                rtc.code as tn_code,
                c.fund_id as fund_id,
                c.weight as weight, 
                c.basis as basis, 
                c.basis_date as basis_date, 
                c.cost as cost,
                f.paid_dt as dt_done,
                u.idnum as idnum,
                IF(u.user_type_id = 2, u.org_name, u.fio) as title,
               c.profile_id as profile_id,
               t.dt_approve as dt_approve            
        FROM fund_goods c
        JOIN fund_profile f
        JOIN user u
        LEFT JOIN transaction t ON c.profile_id = t.profile_id
        LEFT JOIN ref_tn_code rtc ON c.ref_tn = rtc.id
        WHERE 
            f.id = c.fund_id AND
            f.user_id = u.id AND
            f.approve = 'FUND_DONE' AND
            $date_limit 
        GROUP BY c.id";

            // Стили
            $yellow_style = array(
                'font' => array('bold' => true),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER),
                'fill' => array(
                    'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => array('argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW)
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
            $filename = "fund_combined_" . $when . '.xlsx';
            $outEx = APP_PATH . '/storage/temp/' . $filename;

            $objEx = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $objEx->getProperties()->setCreator("recycle.kz")
                ->setTitle("Отчет за " . $today)
                ->setSubject("Отчет за " . $today)
                ->setDescription("Отчет для сотрудников АО «Жасыл даму»")
                ->setKeywords("отчет роп")
                ->setCategory("Отчеты")
                ->setCompany("АО «Жасыл даму»");

            // ==========================================
            // ЛИСТ 1: ТРАНСПОРТ (CARS)
            // ==========================================
            $sh = $objEx->setActiveSheetIndex(0);
            $sh->setTitle('ТС|ССХТ');

            $sh->setCellValue("A1", "№ заявления")
                ->setCellValue("B1", "№ заявки (СВУП)")
                ->setCellValue("C1", "Дата выдачи СВУП")
                ->setCellValue("D1", "Тип финансирования")
                ->setCellValue("E1", "Сумма платежа")
                ->setCellValue("F1", "ФИО / Наименование")
                ->setCellValue("G1", "ИИН / БИН")
                ->setCellValue("H1", "VIN код")
                ->setCellValue("I1", "Дата оплаты")
                ->getStyle('A1:I1')
                ->applyFromArray($yellow_style);

            $counter = 2;
            $cntTotal = 0;

            $resultCar = mysqli_query($mc, $sqlCar);
            if ($resultCar) {
                while ($row = mysqli_fetch_assoc($resultCar)) {
                    $cntTotal++;
                    $num = $counter;
                    $counter++;

                    if ($row['ref_car_type_id'] == 4 || $row['ref_car_type_id'] == 5) {
                        if ($row['f_type'] == 'EXP') {
                            $car_type = 'ЭКСП СХ';
                        } else {
                            $car_type = 'СХ';
                        }
                    } else {
                        if ($row['f_type'] == 'EXP') {
                            $car_type = 'ЭКСП ТС';
                        } else {
                            $car_type = 'ТС';
                        }
                    }

                    $dt_approve = ($row['dt_approve'] > 0) ? convertTimeZone($row['dt_approve']) : '_';

                    $sh->setCellValue("A$num", $row['f_number'])
                        ->setCellValue("B$num", $row['profile_id'])
                        ->setCellValue("C$num", ($dt_approve != '_') ? date('d.m.Y', $dt_approve) : '_')
                        ->setCellValue("D$num", $car_type)
                        ->setCellValue("E$num", __money($row['cost']))
                        ->setCellValue("F$num", $row['title'])
                        ->setCellValue("G$num", $row['idnum'])
                        ->setCellValue("H$num", $row['vin'])
                        ->setCellValue("I$num", date('Y-m-d H:i', convertTimeZone($row['dt_done'])));
                }
            }

            // Оформление листа 1 (до I)
            $cc = max($counter - 1, 1);
            $sh->getStyle("A1:I$cc")->applyFromArray($border_all);
            foreach (range('A', 'I') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
            $sh->getStyle("B2:I$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


            // ==========================================
            // ЛИСТ 2: ТОВАРЫ (GOODS)
            // ==========================================
            $objEx->createSheet();
            $sh = $objEx->setActiveSheetIndex(1);
            $sh->setTitle('Автокомпоненты');

            // Заголовки (до K)
            $sh->setCellValue("A1", "№ заявления")
                ->setCellValue("B1", "№ заявки (СВУП)")
                ->setCellValue("C1", "Дата выдачи СВУП")
                ->setCellValue("D1", "Тип финансирования")
                ->setCellValue("E1", "Сумма платежа")
                ->setCellValue("F1", "ФИО / Наименование")
                ->setCellValue("G1", "ИИН / БИН")
                ->setCellValue("H1", "Код ТНВЭД")
                ->setCellValue("I1", "№ счет-фактуры")
                ->setCellValue("J1", "Дата счет-фактуры")
                ->setCellValue("K1", "Дата оплаты")
                ->getStyle('A1:K1')
                ->applyFromArray($yellow_style);

            $counter = 2; // Сброс счетчика

            $resultGoods = mysqli_query($mc, $sqlGoods);
            if ($resultGoods) {
                while ($row = mysqli_fetch_assoc($resultGoods)) {
                    $cntTotal++;
                    $num = $counter;
                    $counter++;

                    $goods_type = $row['f_type'];
                    $dt_approve = ($row['dt_approve'] > 0) ? convertTimeZone($row['dt_approve']) : '_';

                    // Берем код ТНВЭД из JOIN (оптимизация)
                    $tn_code = isset($row['tn_code']) ? $row['tn_code'] : '';
                    $item_type = 'Автокомпоненты';
                    if ($row['type'] == 'EXP') $item_type = 'ЭКСП. Автокомпоненты';

                    $sh->setCellValue("A$num", $row['f_number'])
                        ->setCellValue("B$num", $row['profile_id'])
                        ->setCellValue("C$num", ($dt_approve != '_') ? date('d.m.Y', $dt_approve) : '_')
                        ->setCellValue("D$num", $item_type)
                        ->setCellValue("E$num", __money($row['cost']))
                        ->setCellValue("F$num", $row['title'])
                        ->setCellValue("G$num", $row['idnum'])
                        ->setCellValue("H$num", $tn_code)
                        ->setCellValue("I$num", $row['basis'])
                        ->setCellValue("J$num", (($row['basis_date'] && $row['basis_date'] > 0) ? date('Y-m-d', $row['basis_date']) : ''))
                        ->setCellValue("K$num", date('Y-m-d H:i', convertTimeZone($row['dt_done'])));
                }
            }

            // Оформление листа 2 (до K)
            $cc = max($counter - 1, 1);
            $sh->getStyle("A1:K$cc")->applyFromArray($border_all);

            // Автоширина A-K
            foreach (range('A', 'K') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
            $sh->getStyle("B2:K$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


            // ==========================================
            // ФИНАЛИЗАЦИЯ
            // ==========================================

            if ($cntTotal == 0) {
                $this->flash->error("# Не найдены данные ни по ТС, ни по Товарам!");
                return $this->response->redirect("/report_importer/");
            }

            // Возвращаемся на первый лист
            $objEx->setActiveSheetIndex(0);

            $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
            $objWriter->save($outEx);

            if (file_exists($outEx)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header("Accept-Charset: utf-8");
                header('Content-Type: application/octet-stream; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
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
