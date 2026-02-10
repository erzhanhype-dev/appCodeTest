<?php

namespace App\Services\Fund;

use App\Exceptions\AppException;
use App\Services\Cms\CmsService;
use App\Services\Pdf\PdfService;
use ContactDetail;
use FundCar;
use FundFile;
use FundGoods;
use FundProfile;
use Goods;
use Phalcon\Di\Injectable;
use PHPQRCode\QRcode;
use RefBank;
use RefCountry;
use RefKbe;
use RefModel;
use RefTnCode;

class FundGoodsDocumentService extends Injectable
{
    /**
     * @throws AppException
     */
    public function generateStatement(FundProfile $fundProfile): string
    {
        $cmsService = new CmsService();

        $hash = $fundProfile->hash;
        $sign = $fundProfile->sign;
        $sign_acc = $fundProfile->sign_acc;
        $check = null;
        if ($sign) {
            $check = $cmsService->check($hash, $sign);
        }
        $car_list_title = '';
        $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 500px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';

        $j_sign = '';
        $j_acc = '';

        $hash = $fundProfile->hash;
        if ($fundProfile->sign_acc) {
            $check_acc = $cmsService->check($hash, $sign_acc);
            if ($check_acc['success']) {
                $j_acc = $check_acc['data'];
            }
        }

        $j = $check ? $check['data'] : null;
        if ($j) {
            if ($j['iin']) $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
            if ($j['bin']) $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
        }


        // получаем имена и БИН-ы
        $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password);
        mysqli_set_charset($mc, "utf8");
        mysqli_select_db($mc, $this->config->database->dbname);
        $id = $fundProfile->user_id;
        $rs = mysqli_query($mc, "SELECT `name` as title, bin as idnum, `ref_bank_id` as bank, `ref_kbe_id` as kbe, oked, reg_num FROM company_detail WHERE user_id = $id LIMIT 1");
        if (mysqli_num_rows($rs) == 0) {
            $rs = mysqli_query($mc, "SELECT CONCAT(last_name, ' ', first_name, ' ', parent_name) as title, iin as idnum, CONCAT('0') as bank, CONCAT('19') as kbe, CONCAT('') as oked, CONCAT('') as reg_num FROM person_detail WHERE user_id = $id LIMIT 1");
        }
        $rw = mysqli_fetch_assoc($rs);

        $_c_title = $rw['title'];
        $_c_idnum = $rw['idnum'];

        $contact_detail = ContactDetail::findFirstByUserId($id);
        $bik = RefBank::findFirstById($rw['bank']);
        $kbe = RefKbe::findFirstById($rw['kbe']);

        $_c_address = $contact_detail->reg_address;
        $_c_kbe = '';
        if ($kbe) {
            $_c_kbe = $kbe->kbe;
        }
        $_c_bik = '';
        if ($bik) {
            $_c_bik = $bik->bik ? $bik->bik : 'не указан';
        }
        $_c_oked = $rw['oked'] ? $rw['oked'] : 'не указано';
        $_c_reg_num = $rw['reg_num'] ? $rw['reg_num'] : 'не указано';

        $dst = APP_PATH . '/storage/temp/fund_application_' . $fundProfile->id . '.html';

        if ($fundProfile->type == 'EXP') {
            $src = APP_PATH . '/app/templates/html/fund/goods/fund-goods-statement-export.html';
        } else {
            $src = APP_PATH . '/app/templates/html/fund/goods/fund-goods-statement-import.html';
        }

        $_total_weight = 0;

        // заявление
        $content = file_get_contents($src);
        $fund_goods = FundGoods::findByFundId($fundProfile->id);

        $content = str_replace('[FUND_FROM]', $_c_title, $content);
        $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
        $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
        $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
        $content = str_replace('[FUND_BIK]', $_c_bik, $content);
        $content = str_replace('[FUND_OKED]', $_c_oked, $content);
        $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
        $content = str_replace('[FUND_TO]', ROP, $content);
        $content = str_replace('[FUND_NUM]', $fundProfile->number, $content);

        // пометка об отмене
        if ($fundProfile->approve == 'FUND_ANNULMENT') {
            $content = str_replace('[CANCELLED]', $cancel_sign, $content);
        } else {
            $content = str_replace('[CANCELLED]', '', $content);
        }

        $country = RefCountry::findFirstById($fundProfile->ref_country_id);
        $content = str_replace('[FUND_COUNTRY]', $country->name, $content);
        $content = str_replace('[PERIOD_START]', date('d.m.Y', $fundProfile->period_start), $content);
        $content = str_replace('[PERIOD_END]', date('d.m.Y', $fundProfile->period_end), $content);
        $content = str_replace('[FUND_PERIOD]', date('d.m.Y', $fundProfile->period_start) . " - " . date('d.m.Y', $fundProfile->period_end), $content);
        $content = str_replace('[FUND_FROM]', $_c_title, $content);
        $content = str_replace('[COUNT]', __money(count($fund_goods)), $content);

        $check_goods = FundGoods::findFirst(array(
            "fund_id = :fund_id:",
            "bind" => array(
                "fund_id" => $fundProfile->id
            )
        ));

        $content = str_replace('[FUND_CAMOUNT]', __money($fundProfile->amount), $content);

        $fund_w = $fundProfile->w_a + $fundProfile->w_b + $fundProfile->w_c + $fundProfile->w_d;
        $fund_e = $fundProfile->e_a;
        $fund_r = $fundProfile->r_a + $fundProfile->r_b + $fundProfile->r_c;
        $fund_t = $fundProfile->tc_a + $fundProfile->tc_b + $fundProfile->tc_c + $fundProfile->tt_a + $fundProfile->tt_b + $fundProfile->tt_c;
        $fund_total = $fundProfile->amount - ($fund_w + $fund_e + $fund_r + $fund_t);

        $content = str_replace('[FUND_W]', __money($fund_w), $content);
        $content = str_replace('[FUND_E]', __money($fund_e), $content);
        $content = str_replace('[FUND_R]', __money($fund_r), $content);
        $content = str_replace('[FUND_T]', __money($fund_t), $content);
        $content = str_replace('[FUND_TOTAL]', __money($fund_total), $content);

        $__qr_sign_line = $this->getQrImagesHtml($fundProfile->sign);
        $__qr_sign_line_acc = $this->getQrImagesHtml($fundProfile->sign_acc);


        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if ($fundProfile->hash && $fundProfile->sign) {
            $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($fundProfile->hash)) .
                '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($fundProfile->sign)) .
                '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                '</p><p>' . $__qr_sign_line_acc . '</p>' .
                '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                '</p><p>' . $__qr_sign_line . '</p>', $content);
        }

        $np = 1;
        $goods_list = [];
        $table_list = '';
        $table_list_ext = '';
        $c_gm = 0;
        $c_gn = 0;
        $c_gt = 0;
        $c_gmc = 0;
        $c_gnc = 0;
        $c_gtc = 0;

        foreach ($fund_goods as $i => $g) {
            $goods = Goods::findFirstById($g->goods_id);
            $ref_tn_code = RefTnCode::findFirstById($g->ref_tn);
            $_total_weight += $g->weight;
            $profileId = $goods->profile_id ?? null;
            $profile_num = ($profileId && $profileId > 0) ? $profileId : '—';
            if ($goods) {
                $goods_list[] = $goods->weight . ' ' . $goods->goods_cost . " (" . $goods->ref_tn . ")";
            }
            if ($check_goods) {
                $table_list .= '<tr><td>' . $np . '</td><td>' . $ref_tn_code->code . '</td><td>' . __weight($g->weight) . ' ' . __money($g->cost) . '</td></tr>';
            }

            if ($check_goods) {
                $table_list_ext .= '<tr><td>' . $np . '</td><td>' . __weight($g->weight) . '</td><td>' . $ref_tn_code->code . '</td><td>' . $g->basis . '</td><td>' . date('d.m.Y', $g->date_produce) . '</td><td>' . $profile_num . '</td></tr>';
            }

            $np++;
        }

        $content = str_replace('[FUND_WEIGHT]', __weight($_total_weight), $content);

        // сложные расчеты )))
        $_count = count($fund_goods);
        if ($_count !== 0) {
            $_minus = ($fundProfile->w_a + $fundProfile->w_b + $fundProfile->w_c + $fundProfile->w_d +
                    $fundProfile->e_a + $fundProfile->r_a + $fundProfile->r_b + $fundProfile->r_c +
                    $fundProfile->tc_a + $fundProfile->tc_b + $fundProfile->tc_c +
                    $fundProfile->tt_a + $fundProfile->tt_b + $fundProfile->tt_c) / $_count;
        } else {
            // Обрабатываем случай деления на ноль
            $_minus = 0; // или выбросьте исключение
        }

        // минусуем с каждой машины общие части
        if ($c_gm > 0) $c_gm = $c_gm - ($_minus * $c_gmc);
        if ($c_gn > 0) $c_gn = $c_gn - ($_minus * $c_gnc);
        if ($c_gt > 0) $c_gt = $c_gt - ($_minus * $c_gtc);

        $content = str_replace('[FUND_LIST]', implode(', ', $goods_list), $content);
        $content = str_replace('[GOODS_LIST]', $table_list, $content);
        $content = str_replace('[GOODS_LIST_EXT]', $table_list_ext, $content);
        $content = str_replace('[GOODS_LIST_TITLE]', $car_list_title, $content);
        $content = str_replace('[FUND_GM]', __money($c_gm), $content);
        $content = str_replace('[FUND_GN]', __money($c_gn), $content);
        $content = str_replace('[FUND_GT]', __money($c_gt), $content);

        $filePath = APP_PATH . '/storage/temp/fund_application_' . $fundProfile->id . '.pdf';

        file_put_contents($dst, $content);
        (new PdfService())->generate($dst, $filePath);

        if ($check && $check['success'] === true) {
            // добавляем файл
            $fundFile = new FundFile();
            $fundFile->fund_id = $fundProfile->id;
            $fundFile->type = 'application';
            $fundFile->original_name = 'ПодписанноеЗаявление_' . $fundProfile->id . '.pdf';
            $fundFile->ext = 'pdf';
            $fundFile->visible = 1;
            $fundFile->save();

            $filePath2 = APP_PATH . '/private/fund/fund_application_' . $fundFile->id . '.pdf';
            copy($filePath, $filePath2);
            unlink($filePath);

            return $filePath2;
        }

        return $filePath;
    }

    public function generateApp($f, $type): string
    {
        $car_list_title = '';
        $pid = $f->id;

        $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 500px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';

        // данные подписанта
        $j_sign = '—';

        $hash = $f->hash;
        $sign = $f->sign;
        $sign_acc = $f->sign_acc;
        $cmsService = new CmsService();
        $check = null;
        $j_acc = null;
        $_total_weight = 0;

        if ($sign_acc) {
            $check_acc = $cmsService->check($hash, $sign_acc);
            $j_acc = $check_acc['data'];
        }

        if ($sign) {
            $check = $cmsService->check($hash, $sign);
        }

        $j = $check ? $check['data'] : null;
        if ($j) {
            if ($j['iin']) $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
            if ($j['bin']) $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
        }

        // получаем имена и БИН-ы
        $mc = mysqli_connect($this->config->database->host, $this->config->database->username, $this->config->database->password);
        mysqli_set_charset($mc, "utf8");
        mysqli_select_db($mc, $this->config->database->dbname);
        $id = $f->user_id;
        $rs = mysqli_query($mc, "SELECT `name` as title, bin as idnum, `ref_bank_id` as bank, `ref_kbe_id` as kbe, oked, reg_num FROM company_detail WHERE user_id = $id LIMIT 1");
        if (mysqli_num_rows($rs) == 0) {
            $rs = mysqli_query($mc, "SELECT CONCAT(last_name, ' ', first_name, ' ', parent_name) as title, iin as idnum, CONCAT('0') as bank, CONCAT('19') as kbe, CONCAT('') as oked, CONCAT('') as reg_num FROM person_detail WHERE user_id = $id LIMIT 1");
        }
        $rw = mysqli_fetch_assoc($rs);

        $_c_title = $rw['title'];
        $_c_idnum = $rw['idnum'];

        $contact_detail = ContactDetail::findFirstByUserId($id);
        $bik = RefBank::findFirstById($rw['bank']);
        $kbe = RefKbe::findFirstById($rw['kbe']);

        $_c_address = $contact_detail->reg_address;
        $_c_kbe = $kbe->kbe;
        $_c_bik = $bik->bik ? $bik->bik : 'не указан';
        $_c_oked = $rw['oked'] ? $rw['oked'] : 'не указано';
        $_c_reg_num = $rw['reg_num'] ? $rw['reg_num'] : 'не указано';

        $fund_goods = FundGoods::findByFundId($f->id);
        foreach ($fund_goods as $i => $g) {
            $_total_weight += $g->weight;
        }

        /******************************************************************************************************************
         * ПРИЛОЖЕНИЕ 3
         ******************************************************************************************************************/
        if ($type == 'app3') {
            $filePath = APP_PATH . '/storage/temp/fund_app3_' . $pid . '.pdf';
            $dst = APP_PATH . '/storage/temp/app3_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_3.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            $f->w_a ? $w_a = $f->w_a : $w_a = 0;
            $f->w_b ? $w_b = $f->w_b : $w_b = 0;
            $f->w_c ? $w_c = $f->w_c : $w_c = 0;
            $f->w_d ? $w_d = $f->w_d : $w_d = 0;
            $w_sum = $w_a + $w_b + $w_c + $w_d;

            $content = str_replace('[W_A]', __money($w_a), $content);
            $content = str_replace('[W_B]', __money($w_b), $content);
            $content = str_replace('[W_C]', __money($w_c), $content);
            $content = str_replace('[W_D]', __money($w_d), $content);
            $content = str_replace('[W_SUM]', __money($w_sum), $content);

            $__qr_sign_line = $this->getQrImagesHtml($f->sign);
            $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);


            // данные подписанта
            $j_sign_acc = '—';
            if ($j_acc) {
                if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
            }

            $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->sign)) .
                '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                '</p><p>' . $__qr_sign_line_acc . '</p>' .
                '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                '</p><p>' . $__qr_sign_line . '</p>', $content);

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, $filePath);

            if ($check && $check['success'] === true) {
                // добавляем файл
                $fundFile = new FundFile();
                $fundFile->fund_id = $pid;
                $fundFile->type = 'fund_app3';
                $fundFile->original_name = 'ПодписанноеПриложение3_' . $pid . '.pdf';
                $fundFile->ext = 'pdf';
                $fundFile->visible = 1;
                $fundFile->save();

                $filePath2 = APP_PATH . '/private/fund/fund_app3_' . $fundFile->id . '.pdf';
                copy($filePath, $filePath2);
                unlink($filePath);

                return $filePath2;
            }

            return $filePath;
        }

        if ($type == 'app4') {

            $dst = APP_PATH . '/storage/temp/app4_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_4.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            $f->e_a ? $e_a = $f->e_a : $e_a = 0;
            $e_sum = $e_a;

            $content = str_replace('[E_A]', __money($e_a), $content);
            $content = str_replace('[E_SUM]', __money($e_sum), $content);

            if ($sign) {
                $__qr_sign_line = $this->getQrImagesHtml($f->sign);
                $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);

                // данные подписанта
                $j_sign_acc = '—';
                if ($j_acc) {
                    if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                    if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
                }

                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                    '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($sign)) .
                    '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line_acc . '</p>' .
                    '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line . '</p>', $content);
            }

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_app4_' . $pid . '.pdf');

            if ($check && $check['success'] === true) {
                // добавляем файл
                $fundFile = new FundFile();
                $fundFile->fund_id = $pid;
                $fundFile->type = 'fund_app4';
                $fundFile->original_name = 'ПодписанноеПриложение4_' . $pid . '.pdf';
                $fundFile->ext = 'pdf';
                $fundFile->visible = 1;
                $fundFile->save();
                copy(APP_PATH . '/storage/temp/fund_app4_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app4_' . $fundFile->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app4_' . $pid . '.pdf');
            } else {
                copy(APP_PATH . '/storage/temp/fund_app4_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app4_' . $pid . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app4_' . $pid . '.pdf');
            }

            return APP_PATH . '/private/fund/fund_app4_' . $pid . '.pdf';
        }

        if ($type == 'app5') {

            $dst = APP_PATH . '/storage/temp/app5_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_5.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            $f->r_a ? $r_a = $f->r_a : $r_a = 0;
            $f->r_b ? $r_b = $f->r_b : $r_b = 0;
            $f->r_c ? $r_c = $f->r_c : $r_c = 0;
            $r_sum = $r_a + $r_b + $r_c;

            $content = str_replace('[R_A]', __money($r_a), $content);
            $content = str_replace('[R_B]', __money($r_b), $content);
            $content = str_replace('[R_C]', __money($r_c), $content);
            $content = str_replace('[R_SUM]', __money($r_sum), $content);

            $__qr_sign_line = '';

            if ($sign) {
                $__qr_sign_line = $this->getQrImagesHtml($f->sign);
                $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);
            }

            // данные подписанта
            $j_sign_acc = '—';
            if ($j_acc) {
                if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
            }

            if ($f->sign) {
                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                    '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->sign)) .
                    '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line_acc . '</p>' .
                    '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line . '</p>', $content);
            }

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_app5_' . $pid . '.pdf');

            if ($check && $check['success'] === true) {
                // добавляем файл
                $fundFile = new FundFile();
                $fundFile->fund_id = $pid;
                $fundFile->type = 'fund_app5';
                $fundFile->original_name = 'ПодписанноеПриложение5_' . $pid . '.pdf';
                $fundFile->ext = 'pdf';
                $fundFile->visible = 1;
                $fundFile->save();
                copy(APP_PATH . '/storage/temp/fund_app5_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app5_' . $fundFile->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app5_' . $pid . '.pdf');
            } else {
                copy(APP_PATH . '/storage/temp/fund_app5_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app5_' . $pid . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app5_' . $pid . '.pdf');
            }

            return APP_PATH . '/private/fund/fund_app5_' . $pid . '.pdf';
        }

        if ($type == 'app6') {

            $dst = APP_PATH . '/storage/temp/app6_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_6.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            $f->tc_a ? $tc_a = $f->tc_a : $tc_a = 0;
            $f->tc_b ? $tc_b = $f->tc_b : $tc_b = 0;
            $f->tc_c ? $tc_c = $f->tc_c : $tc_c = 0;
            $tc_sum = $tc_a + $tc_b + $tc_c;

            $content = str_replace('[TC_A]', __money($tc_a), $content);
            $content = str_replace('[TC_B]', __money($tc_b), $content);
            $content = str_replace('[TC_C]', __money($tc_c), $content);
            $content = str_replace('[TC_SUM]', __money($tc_sum), $content);

            if ($sign) {
                $__qr_sign_line = $this->getQrImagesHtml($f->sign);
                $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);

                // данные подписанта
                $j_sign_acc = '—';
                if ($j_acc) {
                    if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                    if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
                }

                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                    '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->sign)) .
                    '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line_acc . '</p>' .
                    '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line . '</p>', $content);
            }

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_app6_' . $pid . '.pdf');

            if ($check && $check['success'] === true) {
                // добавляем файл
                $fundFile = new FundFile();
                $fundFile->fund_id = $pid;
                $fundFile->type = 'fund_app6';
                $fundFile->original_name = 'ПодписанноеПриложение6_' . $pid . '.pdf';
                $fundFile->ext = 'pdf';
                $fundFile->visible = 1;
                $fundFile->save();
                copy(APP_PATH . '/storage/temp/fund_app6_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app6_' . $fundFile->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app6_' . $pid . '.pdf');
            } else {
                copy(APP_PATH . '/storage/temp/fund_app6_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app6_' . $pid . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app6_' . $pid . '.pdf');
            }

            return APP_PATH . '/private/fund/fund_app6_' . $pid . '.pdf';
        }

        if ($type == 'app11') {

            $dst = APP_PATH . '/storage/temp/app11_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_11.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);
            $content = str_replace('[FUND_TOTAL_WEIGHT]', __weight($_total_weight), $content);

            if ($f->amount) {
                $content = str_replace('[FUND_CAMOUNT]', __money($f->amount), $content);
            } else {
                $content = str_replace('[FUND_CAMOUNT]', '', $content);
            }

            $fund_w = $f->w_a + $f->w_b + $f->w_c + $f->w_d;
            $fund_e = $f->e_a;
            $fund_r = $f->r_a + $f->r_b + $f->r_c;
            $fund_t = $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c;
            $fund_total = $f->amount - ($fund_w + $fund_e + $fund_r + $fund_t);

            if ($fund_total) {
                $content = str_replace('[FUND_TOTAL_AMOUNT]', __money($fund_total), $content);
            } else {
                $content = str_replace('[FUND_TOTAL_AMOUNT]', '0', $content);
            }

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            $__qr_sign_line = $this->getQrImagesHtml($f->sign);
            $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);

            // данные подписанта
            $j_sign_acc = '—';
            if ($j_acc) {
                if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
            }

            if (!$check) {
                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> документ не подписан</p>', $content);
            } else {
                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                    '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->sign)) .
                    '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line_acc . '</p>' .
                    '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line . '</p>', $content);
            }

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_app11_' . $pid . '.pdf');

            if ($check && $check['success'] === true) {
                // добавляем файл
                $f = new FundFile();
                $f->fund_id = $pid;
                $f->type = 'fund_app11';
                $f->original_name = 'ПодписанноеПриложение11_' . $pid . '.pdf';
                $f->ext = 'pdf';
                $f->visible = 1;
                $f->save();
                copy(APP_PATH . '/storage/temp/fund_app11_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app11_' . $f->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app11_' . $pid . '.pdf');
            } else {
                copy(APP_PATH . '/storage/temp/fund_app11_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app11_' . $f->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app11_' . $pid . '.pdf');
            }

            return APP_PATH . '/private/fund/fund_app11_' . $f->id . '.pdf';
        }

        if ($type == 'app14') {

            $dst = APP_PATH . '/storage/temp/app14_' . $pid . '.html';
            $src = APP_PATH . '/app/templates/html/fund/goods/app_14.html';

            // заявление
            $content = file_get_contents($src);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_IDNUM]', $_c_idnum, $content);
            $content = str_replace('[FUND_ADDRESS]', $_c_address, $content);
            $content = str_replace('[FUND_KBE]', $_c_kbe, $content);
            $content = str_replace('[FUND_BIK]', $_c_bik, $content);
            $content = str_replace('[FUND_OKED]', $_c_oked, $content);
            $content = str_replace('[FUND_REG_NUM]', $_c_reg_num, $content);
            $content = str_replace('[FUND_TO]', ROP, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            if ($sign) {
                $__qr_sign_line = $this->getQrImagesHtml($f->sign);
                $__qr_sign_line_acc = $this->getQrImagesHtml($f->sign_acc);

                // данные подписанта
                $j_sign_acc = '—';
                if ($j_acc) {
                    if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                    if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
                }

                $fund_goods = FundGoods::findByFundId($f->id);
                $goods_list = '';
                $goods_total_weight = 0;
                foreach ($fund_goods as $fg) {
                    if ($fg->ref_tn_code) {
                        $goods_list .= '<tr><td>' . $fg->ref_tn_code->name . '</td>' . '<td>' . __weight($fg->weight) . '</td></tr>';
                    }
                    $goods_total_weight += $fg->weight;
                }

                $content = str_replace('[GOODS_LIST]', $goods_list, $content);
                $content = str_replace('[GOODS_TOTAL_WEIGHT]', __weight($goods_total_weight), $content);


                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->hash)) .
                    '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($f->sign)) .
                    '</p><p><strong>ДАННЫЕ ЭЦП БУХГАЛТЕРА:</strong> ' . mb_strtoupper($j_sign_acc) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line_acc . '</p>' .
                    '</p><p><strong>ДАННЫЕ ЭЦП РУКОВОДИТЕЛЯ:</strong> ' . mb_strtoupper($j_sign) .
                    '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') .
                    '</p><p>' . $__qr_sign_line . '</p>', $content);
            } else {
                $content = str_replace('[GOODS_TOTAL_WEIGHT]', '', $content);
            }

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_app14_' . $pid . '.pdf');

            if ($check && $check['success'] === true) {
                // добавляем файл
                $fundFile = new FundFile();
                $fundFile->fund_id = $pid;
                $fundFile->type = 'fund_app14';
                $fundFile->original_name = 'ПодписанноеПриложение14_' . $pid . '.pdf';
                $fundFile->ext = 'pdf';
                $fundFile->visible = 1;
                $fundFile->save();
                copy(APP_PATH . '/storage/temp/fund_app14_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app14_' . $fundFile->id . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app14_' . $pid . '.pdf');
            } else {
                copy(APP_PATH . '/storage/temp/fund_app14_' . $pid . '.pdf', APP_PATH . '/private/fund/fund_app14_' . $pid . '.pdf');
                unlink(APP_PATH . '/storage/temp/fund_app14_' . $pid . '.pdf');
            }

            return APP_PATH . '/private/fund/fund_app14_' . $pid . '.pdf';
        }

        if ($type == 'payment') {
            $dst = APP_PATH . '/storage/temp/payment_' . $pid . '.html';

            if ($f->type == 'EXP') {
                $src = APP_PATH . '/app/templates/html/fund/payment_1.html';
            } else {
                $src = APP_PATH . '/app/templates/html/fund/payment_2.html';
            }

            // заявление
            $content = file_get_contents($src);

            $car = FundCar::findByFundId($pid);

            $content = str_replace('[FUND_FROM]', $_c_title, $content);
            $content = str_replace('[FUND_NUM]', $f->number, $content);

            // пометка об отмене
            if ($f->approve == 'FUND_ANNULMENT') {
                $content = str_replace('[CANCELLED]', $cancel_sign, $content);
            } else {
                $content = str_replace('[CANCELLED]', '', $content);
            }

            // распечатать(на служебку) дату отправки(с 01.09.2022 00:00:00)
            if ($f->created >= 1661968800) {
                $content = str_replace('[FUND_DATE]', date('d.m.Y', $f->md_dt_sent), $content);
            } else {
                $content = str_replace('[FUND_DATE]', date('d.m.Y', $f->created), $content);
            }

            $content = str_replace('[FUND_SUM]', __money($f->amount), $content);
            $content = str_replace('[FUND_SUM_TEXT]', __numToStr($f->amount), $content);

            $hash = $f->hash;
            $sign = $f->sign;
            $sign_acc = $f->sign_acc;
            $sign_hod = $f->sign_hod;
            $sign_fad = $f->sign_fad;
            $sign_hop = $f->sign_hop;
            $sign_hof = $f->sign_hof;

            $check = $sign ? $cmsService->check($hash, $sign) : [];
            $check_acc = $sign_acc ? $cmsService->check($hash, $sign_acc) : [];
            $check_hod = $sign_hod ? $cmsService->check($hash, $sign_hod) : [];
            $check_fad = $sign_fad ? $cmsService->check($hash, $sign_fad) : [];
            $check_hop = $sign_hop ? $cmsService->check($hash, $sign_hop) : [];
            $check_hof = $sign_hof ? $cmsService->check($hash, $sign_hof) : [];

            $j_acc = null;
            $j_hod = null;
            $j_fad = null;
            $j_hop = null;
            $j_hof = null;

            if (!empty($check)) {
                $j = $check['data'];
            }
            if (!empty($check_acc)) {
                $j_acc = $check_acc['data'];
            }
            if (!empty($check_hod)) {
                $j_hod = $check_hod['data'];
            }
            if (!empty($check_fad)) {
                $j_fad = $check_fad['data'];
            }
            if (!empty($check_hop)) {
                $j_hop = $check_hop['data'];
            }
            if (!empty($check_hof)) {
                $j_hof = $check_hof['data'];
            }

            // данные подписанта
            $j_sign = '—';
            if ($j) {
                if ($j['iin']) $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
                if ($j['bin']) $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
            }

            // данные подписанта
            $j_sign_acc = '—';
            if ($j_acc) {
                if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
                if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
            }

            $__qr_sign_line_hod = $this->getQrImagesHtml($f->sign_hod);

            // данные подписанта
            $j_sign_hod = '—';
            if ($j_hod) {
                if ($j_hod['iin']) $j_sign_hod = $j_hod['fio'] . ' (ИИН ' . $j_hod['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hod['dt'];
                if ($j_hod['bin']) $j_sign_hod = $j_hod['company'] . ' (БИН ' . $j_hod['bin'] . ') — СОТРУДНИК ' . $j_sign_hod;
            }

            $__qr_sign_line_fad = $this->getQrImagesHtml($f->sign_fad);

            // данные подписанта
            $j_sign_fad = '—';
            if ($j_fad) {
                if ($j_fad['iin']) $j_sign_fad = $j_fad['fio'] . ' (ИИН ' . $j_fad['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_fad['dt'];
                if ($j_fad['bin']) $j_sign_fad = $j_fad['company'] . ' (БИН ' . $j_fad['bin'] . ') — СОТРУДНИК ' . $j_sign_fad;
            }

            $__qr_sign_line_hop = $this->getQrImagesHtml($f->sign_hop);

            // данные подписанта
            $j_sign_hop = '—';
            if ($j_hop) {
                if ($j_hop['iin']) $j_sign_hop = $j_hop['fio'] . ' (ИИН ' . $j_hop['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hop['dt'];
                if ($j_hop['bin']) $j_sign_hop = $j_hop['company'] . ' (БИН ' . $j_hop['bin'] . ') — СОТРУДНИК ' . $j_sign_hop;
            }

            $__qr_sign_line_hof = $this->getQrImagesHtml($f->sign_hof);

            // данные подписанта
            $j_sign_hof = '—';
            if ($j_hof) {
                if ($j_hof['iin']) $j_sign_hof = $j_hof->fio . ' (ИИН ' . $j_hof['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hof['dt'];
                if ($j_hof['bin']) $j_sign_hof = $j_hof['company'] . ' (БИН ' . $j_hof['bin'] . ') — СОТРУДНИК ' . $j_sign_hof;
            }

            if (!$j_hof) {
                $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> документ не подписан</p>', $content);
            } else {
                $_5_sign = '';
                // $_5_sign = '<p><strong>РУКОВОДИТЕЛЬ :</strong> '.mb_strtoupper($j_sign).'</p><p>'.$__qr_sign_line.'</p>';
                $_5_sign .= '<p><strong>РУКОВОДИТЕЛЬ ДРПУП:</strong> ' . mb_strtoupper($j_sign_hod) . '</p><p>' . $__qr_sign_line_hod . '</p>';
                $_5_sign .= '<p><strong>РУКОВОДИТЕЛЬ ДБП:</strong> ' . mb_strtoupper($j_sign_fad) . '</p><p>' . $__qr_sign_line_fad . '</p>';
                $_5_sign .= '<p><strong>ЗАМЕСТИТЕЛЬ ПРЕДСЕДАТЕЛЯ ПРАВЛЕНИЯ, ЧЛЕН ПРАВЛЕНИЯ:</strong> ' . mb_strtoupper($j_sign_hop) . '</p><p>' . $__qr_sign_line_hop . '</p>';
                $_5_sign .= '<p><strong>ЗАМЕСТИТЕЛЬ ПРЕДСЕДАТЕЛЯ ПРАВЛЕНИЯ, ЧЛЕН ПРАВЛЕНИЯ:</strong> ' . mb_strtoupper($j_sign_hof) . '</p><p>' . $__qr_sign_line_hof . '</p>';
                $content = str_replace('[FUND_SIGN]', $_5_sign, $content);
            }

            $np = 1;
            $car_list = array();
            $table_list = '';
            $table_list_ext = '';

            foreach ($car as $i => $c) {
                $model = RefModel::findFirstById($c->model_id);
                $car_list[] = $model->brand . ' ' . $model->model . " (" . $c->vin . ")";
                $table_list .= '<tr><td>' . $np . '</td><td>' . $c->vin . '</td><td>' . $model->brand . ' ' . $model->model . '</td></tr>';
                $table_list_ext .= '<tr><td>' . $np . '</td><td>' . $model->brand . ' ' . $model->model . '</td><td>' . $c->vin . '</td><td>—</td><td>—</td><td>' . date('d.m.Y', $c->date_produce) . '</td><td>—</td><td>—</td></tr>';
                $np++;
            }
            $content = str_replace('[FUND_LIST]', implode(', ', $car_list), $content);
            $content = str_replace('[CAR_LIST]', $table_list, $content);
            $content = str_replace('[CAR_LIST_EXT]', $table_list_ext, $content);
            $content = str_replace('[CAR_LIST_TITLE]', $car_list_title, $content);

            file_put_contents($dst, $content);
            (new PdfService())->generate($dst, APP_PATH . '/storage/temp/payment_' . $pid . '.pdf');
            __downloadFile(APP_PATH . '/storage/temp/payment_' . $pid . '.pdf');
        }

        return '';
    }

    private function getQrImagesHtml($text): string
    {
        if (!$text) return '';

        $qrHtml = '';
        $chunks = str_split($text, SIGN_QR_LENGTH);

        foreach ($chunks as $chunk) {
            ob_start();
            QRcode::png($chunk, null, 'H', 5, 0);
            $imageData = ob_get_contents();
            ob_end_clean();

            $base64 = base64_encode($imageData);
            $qrHtml .= '<img src="data:image/png;base64,' . $base64 . '" width="116">&nbsp;';
        }
        return $qrHtml;
    }
}