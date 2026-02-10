<?php

namespace App\Controllers;

use ControllerBase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TestController extends ControllerBase
{

    public function indexAction()
    {
    }

    public function excelImportAction()
    {
        $this->view->disable();
        $count = 0;

        if ($this->request->isPost()) {
            $dir = APP_PATH . '/storage/temp/excel_car_list';
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            if ($this->request->hasFiles(true) == true) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getSize() > 0) {
                        $filename = time() . "_" . pathinfo($file->getName(), PATHINFO_BASENAME);
                        $file->moveTo($dir . "/" . $filename . ".csv");
                    }
                }

                $import = file($dir . "/" . $filename . ".csv");
                foreach ($import as $key => $value) {
                    if ($key > 0) {
                        $count++;
                    }
                }
                $this->flash->success('Файл успешно загружен!<br>(Количество записей:' . $count . ')');
            } else {
                $this->flash->error(".CSV файл не загружен!");
            }

            $this->response->redirect("/test/");
        } else {
            $this->response->redirect("/test/");
        }
    }

    public function checkExcelAction()
    {
        $this->view->disable();
        if ($this->request->isPost()) {
            $count = 0;
            $wrong_vin = 0;
            $found = 0;
            $not_found = 0;

            $file = $this->request->getPost("file_name");

            $csv = APP_PATH . '/storage/temp/excel_car_list/' . htmlspecialchars($file);
            if (file_exists($csv)) {
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

                $filename = $csv . '_byVIN.xlsx';
                $outEx = $filename;
                $objEx = new Spreadsheet();
                $objEx->getProperties()->setCreator("recycle.kz")
                    ->setLastModifiedBy("recycle.kz")
                    ->setTitle("Отчет по проверке")
                    ->setSubject("Отчет по проверке")
                    ->setDescription("Проверка")
                    ->setKeywords("отчет роп 2022")
                    ->setCategory("Отчеты")
                    ->setCompany("РОП");

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->setTitle('result');
                $sh->setCellValue("A1", "ID")
                    ->setCellValue("B1", "VIN")
                    //result
                    ->setCellValue("C1", "_RESULT")
                    //result by car
                    ->setCellValue("D1", "_CAR_ID")
                    ->setCellValue("E1", "_CAR_VOLUME")
                    ->setCellValue("F1", "_CAR_COST")
                    ->setCellValue("G1", "_CAR_YEAR")
                    ->setCellValue("H1", "_CAR_CATEGORY")
                    ->setCellValue("I1", "_CAR_TYPE")
                    ->setCellValue("J1", "_CAR_ST_TYPE")
                    ->setCellValue("K1", "_CAR_COUNTRY")
                    //result by transaction
                    ->setCellValue("L1", "_TR_PROFILE_ID")
                    ->setCellValue("M1", "_TR_AMOUNT")
                    ->setCellValue("N1", "_TR_STATUS")
                    ->setCellValue("O1", "_TR_APPROVE")
                    ->setCellValue("P1", "_TR_DT_APPROVE")
                    ->setCellValue("Q1", "_TR_AC_APPROVE")
                    ->setCellValue("R1", "_TR_AC_DT_APPROVE")
                    ->getStyle('A1:R1')
                    ->applyFromArray($yellow_style);

                $counter = 2;

                foreach (file($csv) as $key => $value) {
                    if ($key > 0) {
                        $count++;
                        $_result = 'Не найдено';
                        //result by car
                        $_car_id = '';
                        $_car_volume = '';
                        $_car_cost = '';
                        $_car_year = '';
                        $_car_category = '';
                        $_car_type = '';
                        $_car_st_type = '';
                        $_car_country = '';
                        //result by transaction
                        $_tr_profile_id = '';
                        $_tr_amount = '';
                        $_tr_status = '';
                        $_tr_approve = '';
                        $_tr_dt_approve = '';
                        $_tr_ac_approve = '';
                        $_tr_ac_dt_approve = '';

                        $num = $counter;
                        $counter++;

                        $val = __multiExplode(array(";", ","), $value);
                        $car_vin = $val[1];
                        $car_vin = trim($car_vin);

                        if ($car_vin == '' || $car_vin == '-' || strlen($car_vin) < 4) {
                            $wrong_vin++;
                        } else {
                            //check VIN
                            $check_dpp = __checkDPPForExcel($car_vin);
                            if ($check_dpp) {
                                $found++;
                                $_result = 'Найдено';
                                //result by car
                                $_car_id = $check_dpp['car_id'];
                                $_car_volume = $check_dpp['volume'];
                                $_car_cost = $check_dpp['cost'];
                                $_car_year = $check_dpp['year'];
                                $_car_category = $check_dpp['car_cat'];
                                $_car_type = $check_dpp['car_type'];
                                $_car_st_type = $check_dpp['st_type'];
                                $_car_country = $check_dpp['country'];
                                //result by transaction
                                $_tr_profile_id = $check_dpp['profile_id'];
                                $_tr_amount = $check_dpp['amount'];
                                $_tr_status = $check_dpp['status'];
                                $_tr_approve = $check_dpp['approve'];
                                $_tr_dt_approve = date('d-m-Y H:i', $check_dpp['dt_approve']);
                                $_tr_ac_approve = $check_dpp['ac_approve'];
                                $_tr_ac_dt_approve = date('d-m-Y H:i', $check_dpp['ac_dt_approve']);
                            } else {
                                $not_found++;
                            }
                        }

                        $sh->setCellValue("A$num", $val[0])
                            ->setCellValue("B$num", $car_vin)
                            ->setCellValue("C$num", $_result)
                            //result by car
                            ->setCellValue("D$num", $_car_id)
                            ->setCellValue("E$num", $_car_volume)
                            ->setCellValue("F$num", $_car_cost)
                            ->setCellValue("G$num", $_car_year)
                            ->setCellValue("H$num", $_car_category)
                            ->setCellValue("I$num", $_car_type)
                            ->setCellValue("J$num", $_car_st_type)
                            ->setCellValue("K$num", $_car_country)
                            //result by transaction
                            ->setCellValue("L$num", $_tr_profile_id)
                            ->setCellValue("M$num", $_tr_amount)
                            ->setCellValue("N$num", $_tr_status)
                            ->setCellValue("O$num", $_tr_approve)
                            ->setCellValue("P$num", $_tr_dt_approve)
                            ->setCellValue("Q$num", $_tr_ac_approve)
                            ->setCellValue("R$num", $_tr_ac_dt_approve);
                    }
                }

                $cc = $counter - 1;

                $sh = $objEx->setActiveSheetIndex(0);
                $sh->getStyle("A1:R$cc")->applyFromArray($border_all);

                $sh->getColumnDimension('A')->setAutoSize(true);
                $sh->getColumnDimension('B')->setAutoSize(true);
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
                $sh->getColumnDimension('R')->setAutoSize(true);

                $sh->getStyle("B2:R$cc")->getNumberFormat()->setFormatCode('############');
                $sh->getStyle("E2:F$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("M2:M$cc")->getNumberFormat()->setFormatCode('### ### ##0.00_-');
                $sh->getStyle("A2:R$cc")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objEx, 'Xlsx');
                $objWriter->save($outEx);

                $this->flash->success('Было проверено - ' . $count . ' ТС.<br>Из них неправильный VIN - ' . $wrong_vin . '.<br>Найдено - '
                    . $found . '.<br>Не найдено - ' . $not_found . '<br>');
            } else {
                $this->flash->error("Файл не найден");
            }
        }
        $this->response->redirect("/test/");
    }

    public function deleteExcelAction($file)
    {
        $this->view->disable();

        $csv = APP_PATH . '/storage/temp/excel_car_list/' . htmlspecialchars($file);
        if (file_exists($csv)) {
            unlink($csv);
            $this->flash->success("Файл $file успешно удален!");
        } else {
            $this->flash->error("Файл не найден");
        }

        $this->response->redirect("/test/");
    }

    public function downloadExcelAction($file)
    {
        $this->view->disable();

        $csv = APP_PATH . '/storage/temp/excel_car_list/' . htmlspecialchars($file);
        if (file_exists($csv)) {
            __downloadFile($csv);
        }
    }

}
