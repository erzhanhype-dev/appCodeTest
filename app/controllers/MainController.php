<?php

namespace App\Controllers;
/*******************************************************************************
 * Модуль для основных функций.
 *******************************************************************************/

use App\Services\Kap\KapService;
use Car;
use ControllerBase;
use Goods;
use KapLogs;
use KapRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Profile;
use RefCarType;
use RefModel;
use SimpleXMLElement;
use Transaction;
use User;

// DECLINED:0 AJAX-подгрузка справочников автомашин
// DONE:60 Замена справочника машин → справочник типов (M1, N1)
// TODO:10 Ревизия по переводам от 30 марта 2016 года

class MainController extends ControllerBase
{
    /**
     * Выдаем к просмотру сертификат.
     * @param integer $t_id номер транзакции (платежа)
     * @param integer $c_id номер автомобиля (в платеже)
     * @return void
     */
    public function certificateAction($t_id, $c_id)
    {
        // отключаем рендеринг шаблонов
        // выдачу делаем только в виде файлов
        $auth = User::getUserBySession();

        $t = Transaction::findFirstByProfileId($t_id);
        $p = Profile::findFirstById($t->profile_id);
        $is_jd_user = User::isEmployee();

        if ($is_jd_user || ($p->user_id == $auth->id)) {
            // в каком формате выдаем?
            if ($c_id == 'zip') {
                $func = "__genDPPNEW";
                if ($t->dt_approve < START_ZHASYL_DAMU) {
                    $func = "__genDPP";
                }
                $func($t_id, $c_id, true, true);
            } else {
                if ($t->dt_approve < START_ZHASYL_DAMU) {
                    if ($p->type == 'CAR') {
                        __genDPP($t_id, $c_id);
                    } elseif ($p->type == 'GOODS') {
                        __genDPP($t_id, $c_id);
                    } elseif ($p->type == 'KPP') {
                        __genDPP($t_id, $c_id);
                    }
                } else {
                    if ($p->type == 'CAR') {
                        __genDPPNEW($t_id, $c_id);
                    } elseif ($p->type == 'GOODS') {
                        __genDPPNEW($t_id, $c_id);
                    } elseif ($p->type == 'KPP') {
                        __genDPPNEW($t_id, $c_id);
                    }
                }
            }

            $this->logAction('Скачивание сертификата', 'access');
        }
    }

    public function certificateKzAction($t_id, $c_id)
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        $p = Profile::findFirstById($t_id);
        $is_jd_user = User::isEmployee();

        if ($is_jd_user || ($p->user_id == $auth->id)) {
            if ($p->type == 'CAR') {
                $generated_file_path = Car::genSvupKz($t_id, $c_id);

                if (is_file($generated_file_path)) {
                    __downloadFile($generated_file_path);
                } else {
                    $this->flash->error($generated_file_path);
                    return $this->response->redirect($this->request->getHTTPReferer());
                }
            } elseif ($p->type == 'GOODS') {
                $generated_file_path = Goods::genSvupKz($t_id);

                if (is_file($generated_file_path)) {
                    __downloadFile($generated_file_path);
                } else {
                    $this->flash->error($generated_file_path);
                    return $this->response->redirect($this->request->getHTTPReferer());
                }
            }
            $this->logAction('Скачивание сертификата', 'access');
        }
    }

    /**
     * Выдаем к просмотру сертификат.
     * @param integer $t_id номер транзакции (платежа)
     * @param integer $c_id номер автомобиля (в платеже)
     * @return void
     */
    public function dppAction($t_id, $c_id)
    {
        // отключаем рендеринг шаблонов
        // выдачу делаем только в виде файлов
        $this->view->disable();
    }

    public function checkSVUPZipAction($pid)
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        if ($auth) {
            if ($cf = __checkSVUPZip($pid)) {
                http_response_code(200);
                return json_encode($cf);
            }
        } else {
            return false;
        }
    }

    public function downloadSVUPZipAction($pid)
    {
        $this->view->disable();
        $auth = User::getUserBySession();
        $p = Profile::findFirstById($pid);
        $is_jd_user = User::isEmployee();

        if ($is_jd_user || ($p->user_id == $auth->id)) {
            if ($cf = __checkSVUPZip($pid)) {
                $path = APP_PATH . '/private/' . $cf['cert_dir'] . '/' . $cf['file'];

                if (file_exists($path)) {

                    $this->logAction('Скачаивания архива', 'access');
                    __downloadFile($path);
                }
            }
        } else {
            return false;
        }
    }

    public function genStatementAction($pid)
    {
        $this->view->disable();
        $auth = User::getUserBySession();
        $p = Profile::findFirstById($pid);

        if ($auth->isEmployee() || ($p->user_id == $auth->id)) {
            $path = HTTP_ADDRESS . '/order/viewfile/' . $p->id;
        }

        return $path;
    }

    public function checkCarInQueueAction(int $pid, string $vin = '')
    {
//        $this->view->disable();
//        $auth = User::getUserBySession();
//
//        if ($auth) {
//            try {
//                $channel = $this->getDi()->getShared('queue')->channel();
//                $check_queue = __checkQueue($pid, $vin);
//
//                if ($check_queue['FOUND']) {
//                    http_response_code(200);
//                    return json_encode($check_queue['VIN']);
//                }
//            } catch (Exception $e) {
//                return false;
//            }
//
//            if ($vin != null) {
//                $car = Car::findFirstByVin($vin);
//                if (!$car) {
//                    http_response_code(200);
//                    return json_encode($vin);
//                }
//            }
//        } else {
//            return false;
//        }
    }

    public function generateFundCarINSImportExampleAction()
    {
        $models = RefModel::find();
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

        $outEx = APP_PATH . '/storage/temp/fundCarINSImportExample.xlsx';
        $objEx = new Spreadsheet();
        $objEx->getProperties()->setCreator("recycle.kz")
            ->setLastModifiedBy("recycle.kz")
            ->setTitle("fundCarINSImportExample")
            ->setSubject("fundCarINSImportExample")
            ->setDescription("fundCarINSImportExample")
            ->setKeywords("Жасыл даму 2023")
            ->setCategory("Импорт с excel")
            ->setCompany("Жасыл даму");

        $sh = $objEx->setActiveSheetIndex(0);
        $sh->setTitle('ПримерТаблицыИмпорта');
        $sh->setCellValue("A1", "VIN-код")
            ->setCellValue("B1", "Марка, модель")
            ->getStyle('A1:B1')
            ->applyFromArray($yellow_style);

        $objEx->createSheet();
        $sh = $objEx->setActiveSheetIndex(1);
        $sh->setTitle('Модели');

        $sh->setCellValue("A1", "Номер")
            ->setCellValue("B1", "Марка")
            ->setCellValue("C1", "Модель")
            ->getStyle('A1:C1')
            ->applyFromArray($yellow_style);

        $sh = $objEx->setActiveSheetIndex(0);

        $sh->setCellValue("A2", 'JTSTA18P219060011')
            ->setCellValue("B2", '80')
            ->setCellValue("A3", 'JTSTA18P219060012')
            ->setCellValue("B3", '80')
            ->setCellValue("A4", 'JTSTA18P219060013')
            ->setCellValue("B4", '80')
            ->setCellValue("A5", 'JTSTA18P219060015')
            ->setCellValue("B5", '80')
            ->setCellValue("A6", 'JTSTA18P219060016')
            ->setCellValue("B6", '80')
            ->setCellValue("A7", 'XXXXX18P219060CAR')
            ->setCellValue("B7", '80')
            ->setCellValue("A8", 'XXXXX18P2190XXCAR')
            ->setCellValue("B8", '80');

        $cc = 8;

        $sh->getStyle("A1:B$cc")->applyFromArray($border_all);

        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);

        $sh->getStyle("A2:B$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sh = $objEx->setActiveSheetIndex(1);

        $model_counter = 1;

        foreach ($models as $model) {
            $model_counter++;
            $num = $model_counter;
            $sh->setCellValue("A$num", $model->id)
                ->setCellValue("B$num", $model->brand)
                ->setCellValue("C$num", $model->model);
        }

        $sh = $objEx->setActiveSheetIndex(1);
        $sh->getStyle("A1:C$model_counter")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getStyle("A2:C$model_counter")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
        $objWriter->save($outEx);

        if (file_exists($outEx)) {
            __downloadFile($outEx);
        }
    }

    public function generateFundCarEXPImportExampleAction()
    {
        $models = RefModel::find();
        $ref_car_types = RefCarType::find();
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

        $outEx = APP_PATH . '/storage/temp/fundCarEXPImportExample.xlsx';
        $objEx = new Spreadsheet();
        $objEx->getProperties()->setCreator("recycle.kz")
            ->setLastModifiedBy("recycle.kz")
            ->setTitle("fundCarEXPImportExample")
            ->setSubject("fundCarEXPImportExample")
            ->setDescription("fundCarEXPImportExample")
            ->setKeywords("Жасыл даму 2023")
            ->setCategory("Импорт с excel")
            ->setCompany("Жасыл даму");

        $sh = $objEx->setActiveSheetIndex(0);
        $sh->setTitle('ПримерТаблицыИмпорта');
        $sh->setCellValue("A1", "Тип автомобиля")
            ->setCellValue("B1", "Объем (см3) или масса (кг)")
            ->setCellValue("C1", "VIN-код")
            ->setCellValue("D1", "Дата производства")
            ->setCellValue("E1", "Категория ТС")
            ->setCellValue("F1", "Марка, модель")
            ->setCellValue("G1", "Седельный тягач?(Да=1, Нет=0)")
            ->setCellValue("H1", "Способ расчета")
            ->getStyle('A1:H1')
            ->applyFromArray($yellow_style);

        $objEx->createSheet();
        $sh = $objEx->setActiveSheetIndex(1);
        $sh->setTitle('Модели');

        $sh->setCellValue("A1", "Номер")
            ->setCellValue("B1", "Марка")
            ->setCellValue("C1", "Модель")
            ->getStyle('A1:C1')
            ->applyFromArray($yellow_style);

        $objEx->createSheet();
        $sh = $objEx->setActiveSheetIndex(2);
        $sh->setTitle('ТипыАвтомашин');

        $sh->setCellValue("A1", "Номер")
            ->setCellValue("B1", "Тип автомашины")
            ->getStyle('A1:B1')
            ->applyFromArray($yellow_style);

        $objEx->createSheet();
        $sh = $objEx->setActiveSheetIndex(3);
        $sh->setTitle('КатегорииТС');

        $sh->setCellValue("A1", "Номер")
            ->setCellValue("B1", "Тип ТС")
            ->setCellValue("C1", "Категория ТС")
            ->getStyle('A1:C1')
            ->applyFromArray($yellow_style);

        $objEx->createSheet();
        $sh = $objEx->setActiveSheetIndex(4);
        $sh->setTitle('Способ расчета');

        $sh->setCellValue("A1", "Номер")
            ->setCellValue("B1", "Способ расчета")
            ->getStyle('A1:B1')
            ->applyFromArray($yellow_style);

        $sh = $objEx->setActiveSheetIndex(0);

        $sh->setCellValue("A2", "1")
            ->setCellValue("B2", "1400")
            ->setCellValue("C2", "JTSTA18P219060011")
            ->setCellValue("D2", "29.01.2016")
            ->setCellValue("E2", "1")
            ->setCellValue("F2", "80")
            ->setCellValue("G2", "0")
            ->setCellValue("H2", "0")
            ->setCellValue("A3", "1")
            ->setCellValue("B3", "2000")
            ->setCellValue("C3", "JTSTA18P219060012")
            ->setCellValue("D3", "01.01.2020")
            ->setCellValue("E3", "1")
            ->setCellValue("F3", "80")
            ->setCellValue("G3", "0")
            ->setCellValue("H3", "1")
            ->setCellValue("A4", "1")
            ->setCellValue("B4", "2200")
            ->setCellValue("C4", "JTSTA18P219060013")
            ->setCellValue("D4", "28.01.2016")
            ->setCellValue("E4", "1")
            ->setCellValue("F4", "80")
            ->setCellValue("G4", "0")
            ->setCellValue("H4", "0")
            ->setCellValue("A5", "1")
            ->setCellValue("B5", "2600")
            ->setCellValue("C5", "JTSTA18P219060014")
            ->setCellValue("D5", "28.01.2018")
            ->setCellValue("E5", "1")
            ->setCellValue("F5", "80")
            ->setCellValue("G5", "0")
            ->setCellValue("H5", "0")
            ->setCellValue("A6", "2")
            ->setCellValue("B6", "12000")
            ->setCellValue("C6", "JTSTA18P219060015")
            ->setCellValue("D6", "28.01.2020")
            ->setCellValue("E6", "3")
            ->setCellValue("F6", "80")
            ->setCellValue("G6", "1")
            ->setCellValue("H6", "0")
            ->setCellValue("A7", "2")
            ->setCellValue("B7", "11999")
            ->setCellValue("C7", "JTSTA18P219060016")
            ->setCellValue("D7", "14.05.2022")
            ->setCellValue("E7", "3")
            ->setCellValue("F7", "80")
            ->setCellValue("G7", "1")
            ->setCellValue("H7", "2");

        $cc = 7;

        $sh->getStyle("A1:H$cc")->applyFromArray($border_all);

        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getColumnDimension('D')->setAutoSize(true);
        $sh->getColumnDimension('E')->setAutoSize(true);
        $sh->getColumnDimension('F')->setAutoSize(true);
        $sh->getColumnDimension('G')->setAutoSize(true);
        $sh->getColumnDimension('H')->setAutoSize(true);

        $sh->getStyle("A2:H$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sh = $objEx->setActiveSheetIndex(1);

        $model_counter = 1;

        foreach ($models as $model) {
            $model_counter++;
            $num = $model_counter;
            $sh->setCellValue("A$num", $model->id)
                ->setCellValue("B$num", $model->brand)
                ->setCellValue("C$num", $model->model);
        }

        $sh = $objEx->setActiveSheetIndex(1);
        $sh->getStyle("A1:C$model_counter")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getStyle("A2:C$model_counter")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sh = $objEx->setActiveSheetIndex(2);

        $type_counter = 1;

        foreach ($ref_car_types as $type) {
            $type_counter++;
            $num = $type_counter;
            $sh->setCellValue("A$num", $type->id)
                ->setCellValue("B$num", $type->name);
        }

        $sh = $objEx->setActiveSheetIndex(2);
        $sh->getStyle("A1:B$type_counter")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getStyle("A2:B$type_counter")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        $sh = $objEx->setActiveSheetIndex(3);

        $category_counter = 15;

        $sh->setCellValue("A2", "1")
            ->setCellValue("B2", "Легковой автомобиль")
            ->setCellValue("C2", "Категория M1")
            ->setCellValue("A3", "2")
            ->setCellValue("B3", "Легковой автомобиль")
            ->setCellValue("C3", "Категория M1 (повышенной проходимости)")
            ->setCellValue("A4", "3")
            ->setCellValue("B4", "Грузовой автомобиль")
            ->setCellValue("C4", "Категория N1")
            ->setCellValue("A5", "4")
            ->setCellValue("B5", "Грузовой автомобиль")
            ->setCellValue("C5", "Категория N2")
            ->setCellValue("A6", "5")
            ->setCellValue("B6", "Грузовой автомобиль")
            ->setCellValue("C6", "Категория N3")
            ->setCellValue("A7", "6")
            ->setCellValue("B7", "Грузовой автомобиль")
            ->setCellValue("C7", "Категория N1 (повышенной проходимости)")
            ->setCellValue("A8", "7")
            ->setCellValue("B8", "Грузовой автомобиль")
            ->setCellValue("C8", "Категория N2 (повышенной проходимости)")
            ->setCellValue("A9", "8")
            ->setCellValue("B9", "Грузовой автомобиль")
            ->setCellValue("C9", "Категория N3 (повышенной проходимости)")
            ->setCellValue("A10", "9")
            ->setCellValue("B10", "Автобус")
            ->setCellValue("C10", "Категория M2")
            ->setCellValue("A11", "10")
            ->setCellValue("B11", "Автобус")
            ->setCellValue("C11", "Категория M3")
            ->setCellValue("A12", "11")
            ->setCellValue("B12", "Автобус")
            ->setCellValue("C12", "Категория M2 (повышенной проходимости)")
            ->setCellValue("A13", "12")
            ->setCellValue("B13", "Автобус")
            ->setCellValue("C13", "Категория M3 (повышенной проходимости)")
            ->setCellValue("A14", "13")
            ->setCellValue("B14", "Трактор")
            ->setCellValue("C14", "Трактор")
            ->setCellValue("A15", "14")
            ->setCellValue("B15", "Комбайн")
            ->setCellValue("C15", "Трактор");

        $sh = $objEx->setActiveSheetIndex(3);
        $sh->getStyle("A1:C$category_counter")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getStyle("A2:C$category_counter")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sh = $objEx->setActiveSheetIndex(4);

        $calc_type_counter = 4;

        $sh->setCellValue("A2", "0")
            ->setCellValue("B2", "По дате импорта")
            ->setCellValue("A3", "1")
            ->setCellValue("B3", "По дате подачи заявки")
            ->setCellValue("A4", "2")
            ->setCellValue("B4", "По дате УП");

        $sh = $objEx->setActiveSheetIndex(4);
        $sh->getStyle("A1:B$calc_type_counter")->applyFromArray($border_all);
        $sh->getColumnDimension('A')->setAutoSize(true);
        $sh->getColumnDimension('B')->setAutoSize(true);
        $sh->getStyle("A2:B$calc_type_counter")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
        $objWriter->save($outEx);

        if (file_exists($outEx)) {
            __downloadFile($outEx);
        }
    }

    public function getKAPInfoAction($id): string
    {
        $t = $this->translator;
        $this->view->disable();
        $car = Car::findFirstById($id);
        $service = new KapService();
        $auth = User::getUserBySession();
        $table = 'Данные не найдены!';

        if($auth->isEmployee() || $auth->isOperator()) {
            $find = '';
            if ($car->kap_request_id && $car->kap_request_id > 0) {
                $kap_request = KapRequest::findFirstById($car->kap_request_id);
                if ($kap_request && $kap_request->response != null) {
                    $table = $this->__returnKAPInfoAsTable($kap_request->response);
                }
            }

            if ($car->kap_log_id && $car->kap_log_id > 0) {
                $kap_log = KapLogs::findFirstById($car->kap_log_id);
                if ($kap_log && $kap_log->payload_status && $kap_log->payload_status == '200') {
                    $items = $service->getItemsFromFileName($kap_log->response);

                    if ($items) {
                        $list = $service->parseDataForDoc(json_encode($items), $t);

                        if ($list && count($list) > 0) {
                            $firstTwo = array_slice($list, 0, 2);
                            $maxRows = max(array_map('count', $firstTwo));
                            $table = '<table>';
                            $table .= '<tbody>';

                            for ($i = 0; $i < $maxRows; $i++) {
                                $table .= '<tr>';
                                foreach ($firstTwo as $key => $column) {
                                    $table .= "<td style='border:1px solid #e6e6e6;padding:2px'>" . htmlspecialchars($column[$i]) . "</td>";
                                }
                                $table .= '</tr>';
                            }

                            $table .= '</tbody>';
                            $table .= '</table>';
                        }
                    }
                }
            }
        }

        return $table;
    }

    /**
     * @throws Exception
     */
    public function __returnKAPInfoAsTable(string $response_file_name = NULL): string
    {
        $table_tr = '';
        $found = false;
        $folder = APP_PATH . "/private/docs/kap_requests/";
        $file_path = $folder . $response_file_name;

        if (file_exists($file_path)) {
            $kap_info = file_get_contents($file_path);
            if (__isValidXml($kap_info)) {
                $xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $kap_info);
                $xml_data = new SimpleXMLElement($xml);
                if (count($xml_data->script->dataset->records->record) > 0) {
                    $xml = $xml_data->script->dataset->records;
                    $record = $xml->record;

                    foreach ($record[0]->field as $field) {
                        $field_name = $field->attributes();
                        $field_name = $field_name[0];

                        if ($field_name == "STATUS") {
                            switch ($field) {
                                case "P":
                                    $found = true;
                                    $table_tr .= "<tr><td>Статус</td><td>На регистрации ($field)</td></tr>";
                                    break;
                                case "S":
                                    $found = true;
                                    $table_tr .= "<tr><td>Статус</td><td>Карточка снята с учета ($field)</td></tr>";
                                    break;
                                default:
                                    $found = true;
                                    $table_tr .= "<tr><td>Статус</td><td>$field</td></tr>";
                                    break;
                            }
                        }
                    }

                    if ($found != false) {
                        foreach ($record[0]->field as $field) {
                            $field_name = $field->attributes();
                            $field_name = $field_name[0];

                            if (isset(KAP_INTEG_DATA_TYPE[strval($field_name)])) {
                                $car_info .= "$field_name=$field,";
                                $translated_name = KAP_INTEG_DATA_TYPE[strval($field_name)];
                                $table_tr .= "<tr><td>$translated_name</td><td>$field</td></tr>";
                            }
                        }
                    }
                }
            } else {
                $table_tr = '<span class="badge badge-danger mt-2" style="font-size: 12px;">
                            Ошибка преобразования данных XML-файла, <br> 
                            неверный формат документа !
                        </span>';
            }
        } else {
            $table_tr = '<span class="badge badge-danger mt-2" style="font-size: 14px;">Файл не найден</span>';
        }

        return $table_tr;
    }


}
