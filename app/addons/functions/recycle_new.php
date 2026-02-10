<?php

use App\Exceptions\AppException;
use App\Services\Cms\CmsService;
use App\Services\Epts\EptsService;
use App\Services\Pdf\PdfService;
use Phalcon\Di\Di;
use PHPQRCode\QRcode;

function __genDPPNEW($t_id, $c_id, $download = true, $isZip = false, $lang = null): void
{
    $lang = $lang ?? 'ru';
    $t = Transaction::findFirstByProfileId($t_id);
    $cmsService = new CmsService();

    $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 550px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';
    $cancel_sign_horizontal = '<div style="color: red; font-size: 120px; position: fixed; top: 350px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-35deg) ;">АННУЛИРОВАНО</div>';

    if ($t != false && $t->approve == 'GLOBAL') {
        $p = Profile::findFirstById($t->profile_id);
        if ($p != false) {
            $ac_info = '';
            $ac_to = '';
            if ($t->ac_approve == 'SIGNED') {
                $ac_info = ' и подписан электронно-цифровой подписью, выданной Национальным удостоверяющим центром РК уполномоченному сотруднику АО «Жасыл даму»';

                if ($p->agent_iin != '') {
                    $ac_name = $p->agent_name . ', ИИН / БИН ' . $p->agent_iin;
                } else {
                    $ac_user = User::findFirstById($p->user_id);
                    if ($ac_user != false) {
                        if ($ac_user->user_type_id == 1) {
                            $ac_name = $ac_user->fio . ', БИН/ИИН ' . $ac_user->idnum;
                        } else {
                            $ac_name = $ac_user->org_name . ', БИН/ИИН ' . $ac_user->idnum;
                        }
                    }
                }

                $ac_to = '<br /><br /><strong>Производитель/импортер: ' . $ac_name . '</strong>';
            }

            if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
                echo 'Ошибка #20200614.';
                die();
            }
            if ($p->type == 'CAR') {

                // пути для сохранения и скачивания
                $to_download = '';
                $path = APP_PATH . '/private/certificates_zd/';

                if (!$isZip) {
                    $car = Car::findFirstById($c_id);

                    if ($car) {
                        $to_download = $path . $car->vin . '.pdf';

                        __genPDFForCar($t_id, $c_id, $lang, $ac_info, $ac_to);
                        if (is_file($to_download) && $download != false) {
                            __downloadFile($to_download);
                        }
                    }
                } else {

                    $finalZip = $path . 'svup_' . $t->profile_id . '.zip';
                    $tmpZip = $path . 'svup_' . $t->profile_id . '_in_progress.zip';

                    if (is_file($finalZip)) {
                        @unlink($finalZip);
                    }
                    if (is_file($tmpZip)) {
                        @unlink($tmpZip);
                    }

                    $cars = Car::find([
                        'profile_id = :pid:',
                        'bind' => [
                            'pid' => $p->id,
                        ],
                    ]);

                    $zip = new ZipArchive();
                    $res = $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    if ($res !== true) {
                        throw new \RuntimeException('Не удалось создать ZIP: ' . $tmpZip . ' (code=' . $res . ')');
                    }

                    foreach ($cars as $c) {
                        $pdf = $path . $c->vin . '.pdf';

                        __genPDFForCar($t_id, $c->id, $lang, $ac_info, $ac_to);

                        if (is_file($pdf)) {
                            // внутри архива будет только имя файла, без пути
                            $zip->addFile($pdf, basename($pdf));
                        }
                    }

                    $zip->close();

                    if (!@rename($tmpZip, $finalZip)) {
                        if (!@copy($tmpZip, $finalZip) || !@unlink($tmpZip)) {
                            throw new \RuntimeException("Не удалось переименовать ZIP ($tmpZip -> $finalZip)");
                        }
                    }
                }
            } elseif ($p->type == 'GOODS') {
                $goods = Goods::find([
                    'conditions' => 'profile_id = :pid:',
                    'bind'       => [
                        'pid' => $p->id,
                    ],
                    'order'      => 'id DESC',
                ]);

                $path = APP_PATH . '/private/certificates_zd/';

                $cancel_profile = Goods::findFirst([
                    "conditions" => "profile_id = :pid: and status = :status:",
                    "bind" => [
                        "pid" => $p->id,
                        "status" => "CANCELLED"
                    ]
                ]);

                // гененрируем сертификат
                $certificate_template = APP_PATH . '/app/templates/html/dpp_zd/certificate_goods.html';

                if (is_file($path . 'svup_' . $t->profile_id . '.zip')) {
                    unlink($path . 'svup_' . $t->profile_id . '.zip');
                }

                $archive = new PclZip($path . 'svup_' . $t->profile_id . '.zip');

                $p_weight_first = 0;
                $p_amount_first = 0;
                $g_count = 0;
                $p_id_first = 0;
                $tn = null;
                foreach ($goods as $c_k => $c) {
                    $g_count++;

                    if ($g_count == 1) {
                        $p_id_first = $c->id;
                        $p_weight_first = $c->weight;
                        $p_amount_first = $c->amount;
                        $tn = RefTnCode::findFirstById($c->ref_tn);
                    }
                    break;
                }

                $content_qr = $t->profile_id . '_' . date('d.m.Y', $t->date) . '_' . $p_weight_first . '_' . ($tn ? $tn->code : '') . '_' . $p_amount_first . '_' . $t->amount;
                $content_base64 = base64_encode($content_qr);

                $_sign = $cmsService->signHash($content_base64);
                if(isset($_sign['data'])) {
                    $_s = $_sign['data'];

                    // поехали
                    $__qr = '';

                    if ($_s['sign'] != 'FAILED') {
                        $content_sign = base64_encode($_s['sign']);
                        $__qr = $content_qr . ':' . mb_strtoupper(genAppHash($content_sign));
                    } else {
                        $content_sign = base64_encode($_s['sign']);
                    }

                $__qr_sign_line = '';

                $_qr_c = round((strlen($content_sign) / SIGN_QR_LENGTH) + 0.5);
                for ($i = 0; $i < $_qr_c; $i++) {
                    $l = substr($content_sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                    // echo $l.'<br />';
                    QRcode::png($l, APP_PATH . '/storage/temp/' . $p->id . '_qr_' . $i . '.png', 'H', 5, 0);
                    $__qr_sign_line .= '<img src="' . APP_PATH . '/storage/temp/' . $p->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                }


                QRcode::png($__qr, APP_PATH . '/storage/temp/' . $p_id_first . '.png', 'H', 3, 0);
                }

                $cert = join('', file($certificate_template));

                $certificate_tmp = APP_PATH . '/storage/temp/goods_' . $p_id_first . '.html';

                $cert = str_replace('[Z_TITLE]', 'Сертификат о внесении утилизационного платежа', $cert);
                $cert = str_replace('[Z_NUM]', '№1' . str_pad($p_id_first, 8, 0, STR_PAD_LEFT), $cert);

                // пометка об отмене
                if ($cancel_profile) {
                    $cert = str_replace('[CANCELLED]', $cancel_sign_horizontal, $cert);
                } else {
                    $cert = str_replace('[CANCELLED]', '', $cert);
                }

                // дата выдачи, если есть
                $dt_for_use = $t->date;
                if ($t->dt_approve > 0) {
                    $dt_for_use = $t->dt_approve;
                }

                $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $dt_for_use) . ' г.', $cert);

                $cert = str_replace('[Z_PRE]', 'Настоящий документ, информация в котором представлена в электронно-цифровой форме и удостоверена посредством электронной цифровой подписи, подтверждающий внесение утилизационного платежа в целях исполнения расширенных обязательств производителями (импортерами), а именно: ' . $ac_to, $cert);

                $tr1 = '';
                $tr2 = '';

                $cc = 1;
                $z_count = 0;
                $gid_list = array();
                $tr = '';
                foreach ($goods as $c_k => $c) {
                    $gid_list[] = $c->id;
                    if ($c->status == 'DELETED') continue;
                    if ($c->goods_type == 0) {
                        $tn = RefTnCode::findFirstById($c->ref_tn);

                        // товар в упаковке
                        $good_tn_add = '';
                        $tn_add = false;
                        if ($c->ref_tn_add) {
                            $tn_add = RefTnCode::findFirstById($c->ref_tn_add);
                            if ($tn_add) {
                                $good_tn_add = ' (упаковано ' . $tn_add->code . ')';
                            }
                        }

                        $basis_arr = preg_split('/[\s,]+/', $c->basis);
                        $basis_str = NULL;

                        foreach ($basis_arr as $val) {
                            $basis_str .= $val . '<br>';
                        }

                        $tr = $tr.'<tr>
                                       <td>' . $cc . '</td>
                                       <td>' . $tn->code . $good_tn_add . '</td>
                                       <td>' . date("d.m.Y", $c->date_import) . '</td>
                                       <td style="white-space: normal; overflow-wrap: anywhere; word-break: break-word;">' . $basis_str . '</td>
                                       <td>' . date("d.m.Y", $c->basis_date) . '</td>
                                       <td>' . $c->weight . '</td>
                                       <td>' . __money(round($c->amount - $c->package_cost, 2)) . '</td>
                                       <td>' . ($tn_add ? $tn_add->code : '') . '</td>
                                       <td>' . $c->package_weight . '</td>
                                       <td>' . __money($c->package_cost) . '</td>
                                 </tr>';
                        $cc++;
                    }
                    $z_count++;
                }

                $page_num = '';

                $edited_good = CorrectionLogs::find([
                    "conditions" => "object_id IN ({gid:array}) and type = :type:",
                    "bind" => [
                        "gid" => $gid_list,
                        "type" => "GOODS"
                    ],
                    "order" => "id ASC"
                ]);
                $html = '';
                $log_info = '';
                if (!empty($edited_good)) {
                    $log_gid_list = '';
                    foreach ($edited_good as $key => $log) {
                        $page_num++;

                        $log_gid_list .= $log->id . ', ';
                        $log_info = '<br><b>Внесены изменения на основании заявки(-ок) на изменение № ' . $log_gid_list . ' данные об изменениях указаны в приложении(-ях) к настоящему документу.</b>';
                        $html .= '<div class="page">
                                    <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                                        <h1>Приложение ' . $page_num . '</h1>
                                        <br><br><b>Заявка на изменение № ' . $log->id . ' от ' . date(" H:i d-m-Y ", $log->dt) . ' года.</b><br><br><table>';

                        if ($log->action == "CREATED" || $log->meta_before == "_") {
                            foreach (json_decode($log->meta_after) as $l_after) {

                                $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                                $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                                // товар в упаковке
                                $after_tn_add = '';
                                $calculate_method_after = CALCULATE_METHODS[$l_after->calculate_method];

                                if ($l_after->ref_tn_add != 0) {
                                    $tn_add_after = RefTnCode::findFirstById($l_after->ref_tn_add);
                                    if ($tn_add_after) {
                                        $after_tn_add = ' (упаковано ' . $tn_add_after->code . ')';
                                    }
                                }

                                $html .= '
                                            <tr><td><b>Название поля</b></td><td><b>Старое значение</b></td><td><b>Новое значение</b></td></tr>
                                            <tr><td>Страна</td><td>_</td><td>' . $g_country_after->name . ' </td></tr>
                                            <tr><td>Код ТНВЭД продукции (товара)</td><td>_</td><td>' . $g_tn_after->code . ' ' . $after_tn_add . ' </td></tr>
                                            <tr><td>Дата импорта/производства продукции (товара)</td><td>_</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                            <tr><td>Номер счет-фактуры или ГТД</td><td>_</td><td>' . $l_after->basis . ' </td></tr>
                                            <tr><td>Дата СФ/ГТД</td><td>_</td><td>' . date("d-m-Y", $l_after->basis_date) . ' </td></tr>
                                            <tr><td>Вес продукции (товара), кг</td><td>_</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                            <tr><td>Сумма утилизационного платежа за продукцию (товар), тг.</td><td>_</td><td>' . __money(round($l_after->amount - $l_after->package_cost, 2)) . '</td></tr>
                                            <tr><td>Вес упаковки, кг.</td><td>_</td><td>' . number_format($l_after->package_weight, 3) . '</td></tr>
                                            <tr><td>Утилизационный платеж за упаковку, тг</td><td>_</td><td>' . __money($l_after->package_cost) . '</td></tr>
                                            <tr><td>Итоговая сумма, тг</td><td></td><td>' . __money($l_after->amount) . ' тг</td></tr>
                                            <tr><td>Способ расчета</td><td>_</td><td>' . $calculate_method_after . '</td></tr>';
                            }

                        } else {
                            foreach (json_decode($log->meta_before) as $l_before) {
                                foreach (json_decode($log->meta_after) as $l_after) {
                                    $g_country_before = RefCountry::findFirstById($l_before->ref_country);
                                    $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                                    $g_tn_before = RefTnCode::findFirstById($l_before->ref_tn);
                                    $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                                    // товар в упаковке
                                    $before_tn_add = '';
                                    $after_tn_add = '';
                                    $calculate_method_before = CALCULATE_METHODS[$l_before->calculate_method];
                                    $calculate_method_after = CALCULATE_METHODS[$l_after->calculate_method];

                                    if ($l_before->ref_tn_add != 0) {
                                        $tn_add_before = RefTnCode::findFirstById($l_before->ref_tn_add);
                                        if ($tn_add_before) {
                                            $before_tn_add = ' (упаковано ' . $tn_add_before->code . ')';
                                        }
                                    }
                                    if ($l_after->ref_tn_add != 0) {
                                        $tn_add_after = RefTnCode::findFirstById($l_after->ref_tn_add);
                                        if ($tn_add_after) {
                                            $after_tn_add = ' (упаковано ' . $tn_add_after->code . ')';
                                        }
                                    }

                                    $html .= '
                                                <tr><td><b>Название поля</b></td><td><b>Старое значение</b></td><td><b>Новое значение</b></td></tr>
                                                <tr><td>Страна</td><td>' . $g_country_before->name . ' </td><td>' . $g_country_after->name . ' </td></tr>
                                                <tr><td>Код ТНВЭД продукции (товара)</td><td>' . $g_tn_before->code . ' ' . $before_tn_add . ' </td><td>' . $g_tn_after->code . ' ' . $after_tn_add . '</td></tr>
                                                <tr><td>Дата импорта/производства продукции (товара)</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                                <tr><td>Номер счет-фактуры или ГТД</td><td>' . $l_before->basis . ' </td><td>' . $l_after->basis . ' </td></tr>
                                                <tr><td>Дата СФ/ГТД</td><td>' . date("d-m-Y", $l_before->basis_date) . ' </td><td>' . date("d-m-Y", $l_after->basis_date) . ' </td></tr>
                                                <tr><td>Вес продукции (товара), кг</td><td>' . number_format($l_before->weight, 3) . '</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                                <tr><td>Сумма утилизационного платежа за продукцию (товар), тг.</td><td>' . __money(round($l_before->amount - $l_before->package_cost, 2)) . ' тг</td><td>' . __money(round($l_after->amount - $l_after->package_cost, 2)) . ' тг</td></tr>
                                                <tr><td>Вес упаковки, кг.</td><td>' . number_format($l_before->package_weight, 3) . '</td><td>' . number_format($l_after->package_weight, 3) . '</td></tr>
                                                <tr><td>Утилизационный платеж за упаковку, тг</td><td>' . __money($l_before->package_cost) . ' тг</td><td>' . __money($l_after->package_cost) . ' тг</td></tr>
                                                <tr><td>Итоговая сумма, тг</td><td>' . __money($l_before->amount) . ' тг</td><td>' . __money($l_after->amount) . ' тг</td></tr>
                                                <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>';
                                }
                            }
                        }

                        $_qr_edited_good_user = '';

                        if ($log->sign != '') {
                            $_qr_edited = round((strlen($log->sign) / SIGN_QR_LENGTH) + 0.5);
                            for ($i = 0; $i < $_qr_edited; $i++) {
                                $ls = substr($log->sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                                // echo $l.'<br />';
                                QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                                $_qr_edited_good_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                            }
                        } else {
                            $user_iin_base64 = base64_encode($log->iin);

                            $_qr_edited = round((strlen($user_iin_base64) / SIGN_QR_LENGTH) + 0.5);
                            for ($i = 0; $i < $_qr_edited; $i++) {
                                $ls = substr($user_iin_base64, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                                // echo $l.'<br />';
                                QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                                $_qr_edited_good_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                            }

                        }

                        $html .= '<tr><td colspan="3"><br>' . $_qr_edited_good_user . ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> АКЦИОНЕРНОЕ ОБЩЕСТВО «ЖАСЫЛ ДАМУ» (БИН
                        040340008429)</td></tr>';
                        $html .= '</table></div></div>';
                    }

                } else {
                    $cert = str_replace('[LOGS]', '', $cert);
                    $cert = str_replace('[LOG_INFO]', '', $cert);
                }

                $cert = str_replace('[LOGS]', $html, $cert);
                $cert = str_replace('[LOG_INFO]', $log_info, $cert);

                $cert = str_replace('[Z_TABLE]', $tr, $cert);
                $cert = str_replace('[Z_TABLE1]', $tr1, $cert);
                $cert = str_replace('[Z_TABLE2]', $tr2, $cert);
                $cert = str_replace('[Z_COUNT]', $z_count, $cert);

                $cert = str_replace('[Z_SUM]', 'Общая сумма утилизационного платежа: ' . number_format($t->amount, 2, ',', ' ') . ' тенге', $cert);
                $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесения утилизационного платежа: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

                // формируем красивую подпись
                $_s_text = 'Наименование организации, выдавшей сертификат о внесении утилизационного платежа: ';
                if ($_s['sign'] != 'FAILED') {
                    $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
                }

                $_s_text = '<st>' . $_s_text . '</st>';

                $cert = str_replace('[Z_ROP]', $_s_text, $cert);

                $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
                $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $p_id_first . '.png" width="125">', $cert);
//                $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);

                $cert = str_replace('[Z_S1]', 'Настоящий документ сформирован электронным способом' . $ac_info . '. 
                    Данные, указанные в настоящем документе, включая сумму оплаты, сформированы на основании сведений, которые были 
                    предоставлены плательщиком (производителем, импортером). Ответственность за полноту и достоверность указанных 
                    в настоящем документе сведений, а также за какие-либо возможные последствия и/или ущерб, причиненный плательщику 
                    (производителю, импортеру) и/или третьим лицам, государству, государственным органам и организациям в связи 
                    с недостоверностью и/или не полнотой предоставленных сведений полностью несет плательщик (производитель, импортер). 
                    АО «Жасыл даму» полностью освобождается от такой ответственности. Данные и подлинность настоящего Сертификата 
                    о внесении утилизационного платежа можно проверить на официальном сайте АО «Жасыл даму». ', $cert);

                $cert = str_replace('[Z_S2]', 'Форма Сертификата о внесении утилизационного платежа разработана 
                    и утверждена на основании Правил реализации расширенных обязательств производителей, импортеров, утвержденных 
                    постановлением Правительства Республики Казахстан №763 от «25» октября 2021 года.', $cert);

                file_put_contents($certificate_tmp, $cert);
                (new PdfService())->generate($certificate_tmp, $path . 'goods_' . $p->id . '.pdf', 'landscape');

                // запрашивали сертификат??? готовим ссылку
                $to_download = $path . 'goods_' . $p->id . '.pdf';

                if (is_file($to_download)) {
                    $archive->add($to_download, PCLZIP_OPT_REMOVE_PATH, $path);
                }

                if (is_file($to_download) && $download != false) {
                    if (!$isZip) {
                        __downloadFile($to_download);
                    }
                }
            } elseif ($p->type == 'KPP') {
                $kpps = Kpp::find(array(
                    'profile_id = :pid:',
                    'bind' => array(
                        'pid' => $p->id,
                    ),
                    "order" => "id DESC"
                ));

                $to_download = '';
                $path = APP_PATH . '/private/certificates_zd/';

                $cancel_profile = Kpp::findFirst([
                    "conditions" => "profile_id = :pid: and status = :status:",
                    "bind" => [
                        "pid" => $p->id,
                        "status" => "CANCELLED"
                    ]
                ]);

                $certificate_template = APP_PATH . '/app/templates/html/dpp_zd/certificate_kpp.html';

                if (is_file($path . 'kpp_svup_' . $t->profile_id . '.zip')) {
                    unlink($path . 'kpp_svup_' . $t->profile_id . '.zip');
                }

                $archive = new PclZip($path . 'kpp_svup_' . $t->profile_id . '.zip');

                $kpp_count = 0;
                foreach ($kpps as $c_k => $c) {
                    if ($c->status == 'DELETED') continue;
                    $kpp_count++;
                    // начали цикл
                    if ($kpp_count == 1) {
                        $p_id_first = $c->id;
                        $p_weight_first = $c->weight;
                        $p_amount_first = $c->amount;
                        $tn = RefTnCode::findFirstById($c->ref_tn);
                    }
                    break;
                }

                $content_qr = $t->profile_id . '_' . date('d.m.Y', $t->date) . '_' . $p_weight_first . '_' . $tn->code . '_' . $p_amount_first . '_' . $t->amount;
                $content_base64 = base64_encode($content_qr);

                $_sign = $cmsService->signHash($content_base64);
                $_s_data = $_sign;
                $_s = json_decode(base64_decode($_s_data));

                $__qr = '';
                if ($_s['sign'] != 'FAILED') {
                    $content_sign = base64_encode($_s['sign']);
                    $__qr = $content_qr . ':' . mb_strtoupper(genAppHash($content_sign));
                } else {
                    $content_sign = base64_encode($_s['sign']);
                }

                $__qr_sign_line = '';
                $_qr_c = round((strlen($content_sign) / SIGN_QR_LENGTH) + 0.5);
                for ($i = 0; $i < $_qr_c; $i++) {
                    $l = substr($content_sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                    QRcode::png($l, APP_PATH . '/storage/temp/' . $c->id . '_qr_' . $i . '.png', 'H', 5, 0);
                    $__qr_sign_line .= '<img src="' . APP_PATH . '/storage/temp/' . $c->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                }

                QRcode::png($__qr, APP_PATH . '/storage/temp/' . $p_id_first . '.png', 'H', 3, 0);

                $cert = join('', file($certificate_template));
                $certificate_tmp = APP_PATH . '/storage/temp/kpp_' . $p_id_first . '.html';
                $cert = str_replace('[Z_TITLE]', 'Сертификат о внесении утилизационного платежа', $cert);
                $cert = str_replace('[Z_NUM]', '№1' . str_pad($p_id_first, 8, 0, STR_PAD_LEFT), $cert);

                // пометка об отмене
                if ($cancel_profile) {
                    $cert = str_replace('[CANCELLED]', $cancel_sign_horizontal, $cert);
                } else {
                    $cert = str_replace('[CANCELLED]', '', $cert);
                }

                // дата выдачи, если есть
                $dt_for_use = $t->date;
                if ($t->dt_approve > 0) {
                    $dt_for_use = $t->dt_approve;
                }

                $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $dt_for_use) . ' г.', $cert);
                $cert = str_replace('[Z_PRE]', 'Настоящий документ, информация в котором представлена в электронно-цифровой форме и удостоверена посредством электронной цифровой подписи, подтверждающий внесение утилизационного платежа в целях исполнения расширенных обязательств производителями (импортерами), а именно: ' . $ac_to, $cert);

                $tr1 = '';
                $tr2 = '';

                $_RU_MONTH = ["январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"];

                $tr = '';
                $cc = 1;
                $z_count = 0;
                $kpp_id_list = array();

                foreach ($kpps as $c_k => $v) {
                    $kpp_id_list[] = $v->id;
                    if ($v->status == 'DELETED') continue;
                    $tn = RefTnCode::findFirstById($v->ref_tn);
                    $package_tn = RefTnCode::findFirstById($v->package_tn_code);

                    $tr .= '<tr>';
                    $tr .= '<td>' . $cc . '.</td><td>' . $tn->code . '</td><td>' . date('d.m.Y', $v->date_import) . '</td>';
                    $tr .= '<td>' . $v->basis . '</td><td>' . date('d.m.Y', $v->basis_date) . '</td><td>' . $v->weight . '</td>';
                    $tr .= '<td>' . __money(round($v->amount - $v->package_cost, 2)) . '</td>';
                    $tr .= '<td>' . $package_tn->code . '</td>';
                    $tr .= '<td>' . $v->package_weight . '</td>';
                    $tr .= '<td>' . __money($v->package_cost) . '</td>';
                    $tr .= '<tr>';
                    $cc++;
                    $z_count++;
                }

                $page_num = '';

                $edited_kpp = CorrectionLogs::find([
                    "conditions" => "object_id IN ({kpp_id:array}) and type = :type:",
                    "bind" => [
                        "kpp_id" => $kpp_id_list,
                        "type" => "KPP"
                    ]
                ]);

                if (!empty($edited_kpp)) {
                    $log_kpp_id_list = '';
                    foreach ($edited_kpp as $key => $log) {
                        $page_num++;

                        $log_kpp_id_list .= $log->id . ', ';
                        $log_info = '<br><b>Внесены изменения на основании заявки(-ок) на изменение № ' . $log_kpp_id_list . ' данные об изменениях указаны в приложении(-ях) к настоящему документу.</b>';
                        $html .= '<div class="page">
                                    <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                                        <h1>Приложение ' . $page_num . '</h1>
                                        <br><br><b>Заявка на изменение № ' . $log->id . ' от ' . date(" H:i d-m-Y ", $log->dt) . ' года.</b><br><br><table>';


                        foreach (json_decode($log->meta_before) as $l_before) {
                            foreach (json_decode($log->meta_after) as $l_after) {
                                $kpp_country_before = RefCountry::findFirstById($l_before->ref_country);
                                $kpp_country_after = RefCountry::findFirstById($l_after->ref_country);
                                $kpp_tn_before = RefTnCode::findFirstById($l_before->ref_tn);
                                $kpp_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                                // товар в упаковке
                                $before_tn_add = '';
                                $after_tn_add = '';

                                $invoice_sum_currency_before = '_';
                                $invoice_sum_currency_after = '_';

                                if ($l_before->ref_tn_add != 0) {
                                    $tn_add_before = RefTnCode::findFirstById($l_before->ref_tn_add);
                                    if ($tn_add_before) {
                                        $before_tn_add = ' (упаковано ' . $tn_add_before->code . ')';
                                    }
                                }
                                if ($l_after->ref_tn_add != 0) {
                                    $tn_add_after = RefTnCode::findFirstById($l_after->ref_tn_add);
                                    if ($tn_add_after) {
                                        $after_tn_add = ' (упаковано ' . $tn_add_after->code . ')';
                                    }
                                }

                                if ($l_before->invoice_sum_currency != NULL) {
                                    $invoice_sum_currency_before = "$l_before->invoice_sum_currency($l_before->currency_type)";
                                }

                                if ($l_after->invoice_sum_currency != NULL) {
                                    $invoice_sum_currency_after = "$l_after->invoice_sum_currency($l_after->currency_type)";
                                }


                                $html .= '
                                            <tr><td><b>Название поля</b></td><td><b>Старое значение</b></td><td><b>Новое значение</b></td></tr>
                                            <tr><td>Код ТНВЭД</td><td>' . $kpp_tn_before->code . ' ' . $before_tn_add . ' </td><td>' . $kpp_tn_after->code . ' ' . $after_tn_add . '</td></tr>
                                            <tr><td>Масса КПП (тонна)</td><td>' . number_format($l_before->weight, 3) . '</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                            <tr><td>Сумма в инвойсе (тенге)</td><td>' . $l_before->invoice_sum . ' тг</td><td>' . $l_after->invoice_sum . ' тг</td></tr>
                                            <tr><td>Сумма в инвойсе (валюте) Тип валюты</td><td>' . $invoice_sum_currency_before . ' </td><td>' . $invoice_sum_currency_after . ' </td></tr>
                                            <tr><td>Сумма, тенге</td><td>' . $l_before->amount . ' тг</td><td>' . $l_after->amount . ' тг</td></tr>
                                            <tr><td>Номер счет-фактуры или ГТД</td><td>' . $l_before->basis . ' </td><td>' . $l_after->basis . ' </td></tr>
                                            <tr><td>Дата импорта / производства продукции(товара)</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                            <tr><td>Код ТНВЭД упаковки</td><td>' . $l_before->package_tn_code . ' тг</td><td>' . $l_after->package_tn_code . ' тг</td></tr>
                                            <tr><td>Вес упаковки</td><td>' . $l_before->package_weight . ' тг</td><td>' . $l_after->package_weight . ' тг</td></tr>
                                            <tr><td>Утилизационный платеж за упаковку, тг</td><td>' . $l_before->package_cost . ' тг</td><td>' . $l_after->package_cost . ' тг</td></tr>';
                            }
                        }

                        $_qr_edited_good_user = '';

                        if ($log->sign != '') {
                            $_qr_edited = round((strlen($log->sign) / SIGN_QR_LENGTH) + 0.5);
                            for ($i = 0; $i < $_qr_edited; $i++) {
                                $ls = substr($log->sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                                // echo $l.'<br />';
                                QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                                $_qr_edited_good_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                            }
                        } else {
                            $user_iin_base64 = base64_encode($log->iin);

                            $_qr_edited = round((strlen($user_iin_base64) / SIGN_QR_LENGTH) + 0.5);
                            for ($i = 0; $i < $_qr_edited; $i++) {
                                $ls = substr($user_iin_base64, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                                // echo $l.'<br />';
                                QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                                $_qr_edited_good_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                            }

                        }

                        $html .= '<tr><td colspan="3"><br>' . $_qr_edited_good_user . ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> ' . ZHASYL_DAMU . ' (БИН
                        ' . ZHASYL_DAMU_BIN . ')</td></tr>';

                        $html .= '</table></div></div>';

                    }

                } else {
                    $cert = str_replace('[LOGS]', '', $cert);
                    $cert = str_replace('[LOG_INFO]', '', $cert);
                }

                $cert = str_replace('[LOGS]', $html, $cert);
                $cert = str_replace('[LOG_INFO]', $log_info, $cert);

                $cert = str_replace('[Z_TABLE]', $tr, $cert);
                $cert = str_replace('[Z_TABLE1]', $tr1, $cert);
                $cert = str_replace('[Z_TABLE2]', $tr2, $cert);
                $cert = str_replace('[Z_COUNT]', $z_count, $cert);

                $cert = str_replace('[Z_SUM]', 'Общая сумма утилизационного платежа: ' . number_format($t->amount, 2, ',', ' ') . ' тенге', $cert);
                $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесения утилизационного платежа: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

                // формируем красивую подпись
                $_s_text = 'Наименование организации, выдавшей сертификат о внесении утилизационного платежа: ';
                if ($_s['sign'] != 'FAILED') {
                    $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
                }

                $_s_text = '<st>' . $_s_text . '</st>';
                $cert = str_replace('[Z_ROP]', $_s_text, $cert);
                $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
                $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $p_id_first . '.png" width="125">', $cert);
//                $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);
                $cert = str_replace('[Z_S1]', 'Настоящий документ сформирован электронным способом' . $ac_info . '. 
                    Данные, указанные в настоящем документе, включая сумму оплаты, сформированы на основании сведений, которые 
                    были предоставлены плательщиком (производителем, импортером). Ответственность за полноту и достоверность указанных 
                    в настоящем документе сведений, а также за какие-либо возможные последствия и/или ущерб, причиненный плательщику 
                    (производителю, импортеру) и/или третьим лицам, государству, государственным органам и организациям в связи 
                    с недостоверностью и/или не полнотой предоставленных сведений полностью несет плательщик (производитель, импортер). 
                    АО «Жасыл даму» полностью освобождается от такой ответственности. Данные и подлинность настоящего Сертификата 
                    о внесении утилизационного платежа можно проверить на официальном сайте АО «Жасыл даму». ', $cert);

                $cert = str_replace('[Z_S2]', 'Форма Сертификата о внесении утилизационного платежа разработана и 
                    утверждена на основании Правил реализации расширенных обязательств производителей, импортеров, утвержденных 
                    постановлением Правительства Республики Казахстан №763 от «25» октября 2021 года.', $cert);

                file_put_contents($certificate_tmp, $cert);
                (new PdfService())->generate($certificate_tmp, $path . 'kpp_' . $p->id . '.pdf', 'landscape');

                $archive->add($path . 'kpp_' . $p->id . '.pdf', PCLZIP_OPT_REMOVE_PATH, $path);

                // запрашивали сертификат??? готовим ссылку
                $to_download = $path . 'kpp_' . $p->id . '.pdf';

                if (is_file($to_download) && $download != false) {
                    if (!$isZip) {
                        __downloadFile($to_download);
                    }
                }
            }
        }
    }
}

function __calculateCarByDate($date, $car_volume, $value, $ref_st = 0, $e_car = false, $ref_country_import = 0, $is_new_profile = true): float
{
    $value = json_decode($value);
    $sum = 0;
    $k = 0;

    $date = date('Y-m-d H:i:s', convertTimeZone(strtotime($date)));

    $v = $value->price;
    // если импорт после введения ставок,
    // то считаем по новым коэффициентам
    if (strtotime($date) >= START_NEW_COEFFICIENT_2022) {
        if ($ref_st == 1 && $car_volume >= 12000) {
            $k = 11.00;
        } elseif ($ref_st == 2 && $car_volume >= 12000) {
            $k = 0;
        } else {
            $k = $value->k_2022;
        }
    } elseif ((strtotime($date) >= ROP_NEW_TS_DATE) and (strtotime($date) < START_NEW_COEFFICIENT_2022)) {
        if ($ref_st == 1 && $car_volume >= 20000 && $car_volume < 50000) {
            $k = 11.00;
        } else {
            $k = $value->k;
        }
    } else {
        $k = $value->ko;
    }

    if($is_new_profile) {
        if (in_array($ref_country_import, [135, 18])) {
            $k = 100;
        }
    }

    // если дата импорта в 2016 году
    if ((strtotime($date) >= strtotime(STARTROP)) and (strtotime($date) < strtotime(START_2017))) {
        $sum = round(MRP_2016 * $v * $k, 2);
    }

    // если дата импорта в 2017 году
    if ((strtotime($date) >= strtotime(START_2017)) and (strtotime($date) < strtotime(START_2018))) {
        $sum = round(MRP_2017 * $v * $k, 2);
    }

    // если дата импорта в 2018 году
    if ((strtotime($date) >= strtotime(START_2018)) and (strtotime($date) < strtotime(START_2019))) {
        $sum = round(MRP_2018 * $v * $k, 2);
    }

    // если дата импорта в 2019 году
    if ((strtotime($date) >= strtotime(START_2019)) and (strtotime($date) < strtotime(START_2020))) {
        $sum = round(MRP_2019 * $v * $k, 2);
    }

    // если дата импорта в 2020 году, но до коронавируса
    if ((strtotime($date) >= strtotime(START_2020)) and (strtotime($date) < strtotime(START_CORONA))) {
        $sum = round(MRP_2020 * $v * $k, 2);
    }

    // если дата импорта в 2020 году, но после коронавируса
    // точнее, после смены МРП связанной с коронавирусом
    if ((strtotime($date) >= strtotime(START_CORONA)) and (strtotime($date) < strtotime(START_2021))) {
        $sum = round(MRP * $v * $k, 2);
    }

    // START_2021(с 01.01.2021)
    // MRP_2021 = 2917
    if ((strtotime($date) >= strtotime(START_2021)) and (strtotime($date) < strtotime(START_2022))) {
        $sum = round(MRP_2021 * $v * $k, 2);
    }

    // START_2022(с 01.01.2022)
    // MRP_2022 = 3063
    if ((strtotime($date) >= strtotime(START_2022)) and (strtotime($date) < strtotime(START_2023))) {
        $sum = round(MRP_2022 * $v * $k, 2);
    }

    // START_2023(с 01.01.2023)
    // MRP_2023 = 3450
    if ((strtotime($date) >= strtotime(START_2023)) and (strtotime($date) < strtotime(START_2024))) {
        $sum = round(MRP_2023 * $v * $k, 2);
    }

    // START_2024(с 01.01.2024)
    // MRP_2024 = 3692
    if (strtotime($date) >= strtotime(START_2024) and (strtotime($date) < strtotime(START_2025))) {
        $sum = round(MRP_2024 * $v * $k, 2);
    }

    if (strtotime($date) >= strtotime(START_2025) and (strtotime($date) < strtotime(START_2026))) {
        $sum = round(MRP_2025 * $v * $k, 2);
    }

    if (strtotime($date) >= strtotime(START_2026)) {
        $sum = round(MRP_2026 * $v * $k, 2);
    }

    // ROP_ELECTRIC_CAR_DATE(с 04.06.2021 00:00:00)
    if (strtotime($date) >= ROP_ELECTRIC_CAR_DATE) {
        if ($e_car) {
            $sum = 0;
        }
    }

    return $sum;
}

function __calculateCar($car_volume, $value, $ref_st = 0, $e_car = false, $ref_country_import = 0, $is_new_profile = true): float
{
    $value = json_decode($value);

    $v = $value->price;

    if ($ref_st == 1 && $car_volume >= 12000) {
        $k = 11.00;
    } elseif ($ref_st == 2 && $car_volume >= 12000) {
        $k = 0;
    } else {
        $k = $value->k_2022;
    }

    if($is_new_profile) {
        if (in_array($ref_country_import, [135, 18])) {
            $k = 100;
        }
    }

    // if ELECTRIC_CAR
    if ($e_car) {
        $sum = 0;
    } else {
        $now = time();
        if ($now >= strtotime(START_2026)) {
            $current_mrp = MRP_2026;
        } else {
            $current_mrp = MRP_2025;
        }

        $sum = round($current_mrp * $v * $k, 2);
    }

    return $sum;
}

/**
 * @throws AppException
 */
function __genPDFForCar($t_id, $c_id, $lang = null, $ac_info = null, $ac_to = null)
{
    global $messages;
    $lang = $lang ?? 'ru';
    $path = APP_PATH . '/private/certificates_zd/';
    $cars = Car::findFirstById($c_id);
    $t = Transaction::findFirstById($t_id);
    $cmsService = new CmsService();

    $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 550px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';

    $di = Di::getDefault();
    $lc = $di->has('translator') ? $di->getShared('translator') : null;

    // пути для сохранения и скачивания
    $to_download = '';
    $path = APP_PATH . '/private/certificates_zd/';

    // гененрируем сертификат
    $certificate_template = APP_PATH . '/app/templates/html/dpp_zd/certificate_car.html';

    $page_num = 1;
    $is_electric_car = '';

    $cancel_car = Car::findFirst([
        "conditions" => "id = :cid: and status = :status:",
        "bind" => [
            "cid" => $cars->id,
            "status" => "CANCELLED"
        ]
    ]);

    $edited_car = CorrectionLogs::find([
        "conditions" => "object_id = :cid: and type = :type:",
        "bind" => [
            "cid" => $cars->id,
            "type" => "CAR"
        ]
    ]);

    // начали цикл
    if ($cancel_car || $edited_car || !is_file(APP_PATH . '/private/certificates_zd/' . $cars->vin . '.pdf')) {
        $m = RefCarCat::findFirstById($cars->ref_car_cat);
        $type = RefCarType::findFirstById($cars->ref_car_type_id);
        $vehicle_type = $cars->vehicle_type;
        // если это машина с исправленным ВИН-ом
        $trans_vin = '';
        if (is_file(APP_PATH . '/private/trans/' . $cars->vin . '.txt')) {
            $trans_vin = file_get_contents(APP_PATH . '/private/trans/' . $cars->vin . '.txt');
        }

        // строка QR-кода и его подпись
        $content_qr = $t->profile_id . ':' . date('d.m.Y', $t->date) . ':' . mb_strtoupper(str_replace('cat-', '', $m->name)) . ':' . $cars->year . ':' . $cars->vin . ':' . $cars->volume . ':' . $cars->cost;
        $content_base64 = base64_encode($content_qr);

        $_sign = $cmsService->signHash($content_base64);
        $_s = null;
        $__qr_sign_line = '';

        if (isset($_sign['data'])) {
            $_s = $_sign['data'];

            // поехали
            $__qr = '';

            if ($_s['sign'] != 'FAILED') {
                $content_sign = base64_encode($_s['sign']);
                $__qr = $content_qr . ':' . mb_strtoupper(genAppHash($content_sign));
            } else {
                $content_sign = base64_encode($_s['sign']);
            }



            $_qr_c = round((strlen($content_sign) / SIGN_QR_LENGTH) + 0.5);
            for ($i = 0; $i < $_qr_c; $i++) {
                $l = substr($content_sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                // echo $l.'<br />';
                QRcode::png($l, APP_PATH . '/storage/temp/' . $cars->id . '_qr_' . $i . '.png', 'H', 5, 0);
                $__qr_sign_line .= '<img src="' . APP_PATH . '/storage/temp/' . $cars->id . '_qr_' . $i . '.png" width="116">&nbsp;';
            }

            // die();
            // формируем изображение
            QRcode::png($__qr, APP_PATH . '/storage/temp/' . $cars->id . '.png', 'H', 4, 0);
        }

        $cert = join('', file($certificate_template));

        $certificate_tmp = APP_PATH . '/storage/temp/certificate_' . $cars->id . '.html';

        $cert = str_replace('[Z_TITLE]', 'Сертификат о внесении утилизационного платежа', $cert);
        $cert = str_replace('[Z_NUM]', '№0' . str_pad($cars->id, 8, 0, STR_PAD_LEFT), $cert);

        // пометка об отмене
        if ($cancel_car) {
            $cert = str_replace('[CANCELLED]', $cancel_sign, $cert);
        } else {
            $cert = str_replace('[CANCELLED]', '', $cert);
        }

        // если оба условия не сработали выше, зачищаем
        $cert = str_replace('[CANCELLED]', '', $cert);
        $html = '';
        $log_info = '';

        if ($edited_car) {
            $log_id_list = '';
            foreach ($edited_car as $key => $log) {
                if ($log->object_id != $c_id) continue;
                $page_num++;

                $log_id_list .= $log->id . ', ';
                $log_info = '<br><b style="font-size:9px">Внесены изменения на основании заявки(-ок) на изменение № ' . $log_id_list . ' данные об изменениях указаны в приложении(-ях) к настоящему документу.</b>';
                $html .= '<div class="page">
                            <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                            <h1> Приложение ' . $page_num . '</h1>
                            <br><br><b>Заявка на изменение № ' . $log->id . ' от ' . date(" H:i d-m-Y ", $log->dt) . ' года.</b><br><br><table>';

                foreach (json_decode($log->meta_before) as $l_before) {
                    foreach (json_decode($log->meta_after) as $l_after) {
                        $country_before = RefCountry::findFirstById($l_before->ref_country);
                        $country_after = RefCountry::findFirstById($l_after->ref_country);
                        $car_type_before = RefCarType::findFirstById($l_before->ref_car_type_id);
                        $car_type_after = RefCarType::findFirstById($l_after->ref_car_type_id);
                        $car_cat_before = RefCarCat::findFirstById($l_before->ref_car_cat);
                        $car_cat_after = RefCarCat::findFirstById($l_after->ref_car_cat);
                        $calculate_method_before = CALCULATE_METHODS[$l_before->calculate_method];
                        $calculate_method_after = CALCULATE_METHODS[$l_after->calculate_method];
                        $cost_before = __money($l_before->cost) . ' тг';
                        $cost_after = __money($l_after->cost) . ' тг';
                        $e_car_before = '—';
                        $e_car_after = '—';

                        if ($l_before->electric_car == 0) {
                            $e_car_before = 'НЕТ';
                        } elseif ($l_before->electric_car == 1) {
                            $e_car_before = 'ДА';
                        }

                        if ($l_after->electric_car == 0) {
                            $e_car_after = 'НЕТ';
                        } elseif ($l_after->electric_car == 1) {
                            $e_car_after = 'ДА';
                        }

                        if ($l_before->ref_st_type == 0) {
                            $st_type_before = 'НЕТ';
                        } elseif ($l_before->ref_st_type == 1) {
                            $st_type_before = 'ДА';
                        } elseif ($l_before->ref_st_type == 2) {
                            $st_type_before = 'ДА(Международные перевозки)';
                        }

                        if ($l_after->ref_st_type == 0) {
                            $st_type_after = 'НЕТ';
                        } elseif ($l_after->ref_st_type == 1) {
                            $st_type_after = 'ДА';
                        } elseif ($l_after->ref_st_type == 2) {
                            $st_type_after = 'ДА(Международные перевозки)';
                        }

                        $html .= '
                                <tr><td><b>Название поля</b></td><td><b>Старое значение</b></td><td><b>Новое значение</b></td></tr>
                                <tr><td>Год производства</td><td>' . $l_before->year . '</td><td>' . $l_after->year . '</td></tr>
                                <tr><td>Тип</td><td>' . $car_type_before->name . '</td><td>' . $car_type_after->name . '</td></tr>
                                <tr><td>Категория ТС</td><td>' . $lc->_($car_cat_before->name) . '</td><td>' . $lc->_($car_cat_after->name) . '</td></tr>
                                <tr><td>Седельный тягач?</td><td>' . $st_type_before . '</td><td>' . $st_type_after . '</td></tr>
                                <tr><td>Объем / вес</td><td>' . number_format($l_before->volume, 3) . '</td><td>' . number_format($l_after->volume, 3) . '</td></tr>
                                <tr><td>VIN-код / номер</td><td>' .  preg_replace('/-/', '&', $l_before->vin, 1) . '</td><td>' .  preg_replace('/-/', '&', $l_after->vin, 1). '</td></tr>
                                <tr><td>Страна производства</td><td>' . $country_before->name . '</td><td>' . $country_after->name . '</td></tr>
                                <tr><td>Сумма, тенге</td><td>' . $cost_before . '</td><td>' . $cost_after . '</td></tr>
                                <tr><td>Дата импорта</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>
                                <tr><td>С электродвигателям?</td><td>' . $e_car_before . '</td><td>' . $e_car_after . '</td></tr>';
                    }
                }

                $_qr_edited_user = '';

                if ($log->sign != '') {
                    $_qr_edited = round((strlen($log->sign) / SIGN_QR_LENGTH) + 0.5);
                    for ($i = 0; $i < $_qr_edited; $i++) {
                        $ls = substr($log->sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                        QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                        $_qr_edited_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                    }
                } else {
                    $user_iin_base64 = base64_encode($log->iin);

                    $_qr_edited = round((strlen($user_iin_base64) / SIGN_QR_LENGTH) + 0.5);
                    for ($i = 0; $i < $_qr_edited; $i++) {
                        $ls = substr($user_iin_base64, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                        // echo $l.'<br />';
                        QRcode::png($ls, APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png', 'H', 5, 0);
                        $_qr_edited_user .= '<img src="' . APP_PATH . '/storage/temp/log_user_' . $log->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                    }

                }

                $html .= '<tr><td colspan="3"><br>' . $_qr_edited_user .
                    ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> АКЦИОНЕРНОЕ ОБЩЕСТВО «ЖАСЫЛ ДАМУ» (БИН
                                    040340008429)</td></tr>';
                $html .= '</table></div></div>';
            }

            $cert = str_replace('[LOGS]', $html, $cert);
            $cert = str_replace('[LOG_INFO]', $log_info, $cert);
        } else {
            $cert = str_replace('[LOGS]', '', $cert);
            $cert = str_replace('[LOG_INFO]', '', $cert);
        }
        // дата выдачи, если есть
        $dt_for_use = $t->date;
        if ($t->dt_approve > 0) {
            $dt_for_use = $t->dt_approve;
        }

        $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $dt_for_use) . ' г.', $cert);

        $cert = str_replace('[Z_PRE]', 'Настоящий документ, информация в котором представлена в электронно-цифровой форме и удостоверена посредством электронной цифровой подписи, подтверждающий внесение утилизационного платежа в целях исполнения расширенных обязательств производителями (импортерами), а именно: ' . $ac_to, $cert);

        // если есть дополнения к ВИН-у
        $__printed_vin = $cars->vin;
        if ($trans_vin != '') {
            $__printed_vin = $__printed_vin . " ($trans_vin)";
        }

        $c_cost = number_format($cars->cost, 2, ',', ' ') . ' тенге';

        // если это Грузовой автомобиль
        if ($m->car_type == 2 || $vehicle_type == 'CARGO') {
            $cert = str_replace('[TOP_LEFT]', '№0' . str_pad($cars->id, 8, 0, STR_PAD_LEFT) . ' - транспортные средства', $cert);
            $cert = str_replace('[Z_CAT]', 'Категория автотранспортного средства: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_VIN]', 'Vehicle Identity Number (VIN): ' . $__printed_vin, $cert);
            $cert = str_replace('[Z_VOLUME]', 'Рабочий объем двигателя (V см3)/Технически допустимая максимальная масса (кг):  ' . $cars->volume, $cert);
            // уточнение по седельным тягачам от 21.09.2020
            // дата импорта после 02.05.2019 применять признак седельности, а до не применяит
            if ($cars->date_import >= 1588356000) {
                $sed_t = ' (не седельный тягач)';
                if ($cars->ref_st_type == 1) {
                    $sed_t = ' (седельный тягач)';
                } elseif ($cars->ref_st_type == 2) {
                    $sed_t = ' (седельный тягач(Международные перевозки))';
                }
            } else  $sed_t = '';
            $cert = str_replace('[Z_TYPE]', 'Вид автотранспортного средства: ' . $type->name . $sed_t, $cert);

            // если это ССХТ
        } else if ($m->car_type == 5 || $m->car_type == 4) {
            $__printed_vin = str_replace('I-', '—-', $__printed_vin);
            $__printed_vin = str_replace('-B', '-—', $__printed_vin);
            $__pv = preg_split('/[-&]/', $__printed_vin, 2);
            $cert = str_replace('[TOP_LEFT]', '№0' . str_pad($cars->id, 8, 0, STR_PAD_LEFT) . ' - По самоходной сельскохозяйственной техники', $cert);
            $cert = str_replace('[Z_CAT]', 'Категория самоходной сельскохозяйственной техники: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_TYPE]', 'Вид самоходной сельскохозяйственной техники: ' . $type->name, $cert);
            $cert = str_replace('[Z_VIN]', 'Заводской номер: ' . $__pv[0] . '</p><p>Номер двигателя: ' . $__pv[1], $cert);
            $cert = str_replace('[Z_VOLUME]', 'Мощность двигателя (л.с.): ' . $cars->volume, $cert);

            // если это Легковой автомобиль или автобус
        } else {
            $cert = str_replace('[TOP_LEFT]', '№0' . str_pad($cars->id, 8, 0, STR_PAD_LEFT) . ' - транспортные средства', $cert);
            $cert = str_replace('[Z_CAT]', 'Категория автотранспортного средства: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_TYPE]', 'Тип автотранспортного средства: ' . $type->name, $cert);
            $cert = str_replace('[Z_VIN]', 'Vehicle Identity Number (VIN): ' . $__printed_vin, $cert);
            $cert = str_replace('[Z_VOLUME]', 'Рабочий объем двигателя (V см3)/Технически допустимая максимальная масса (кг):  ' . $cars->volume, $cert);
        }

        if ($cars->electric_car == 1) {
            $is_electric_car = '(Транспортное средство с электродвигателем)';
        }

        $cert = str_replace('[Z_SUM]', 'Сумма утилизационного платежа: ' . $c_cost . $is_electric_car, $cert);
        $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесение утилизационного платежа: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

        // формируем красивую подпись
        $_s_text = 'Наименование организации, выдавшей сертификат о внесении утилизационного платежа: ';
        if($_s) {
            if ($_s['sign'] != 'FAILED') {
                $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
            }
        }

        $_s_text = '<strong>' . $_s_text . '</strong>';

        $cert = str_replace('[Z_ROP]', $_s_text, $cert);

        $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
        $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $cars->id . '.png" width="125">', $cert);
//        $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);

        $cert = str_replace('[Z_S1]', ' Настоящий документ сформирован электронным способом' . $ac_info . '. Данные, указанные в настоящем документе, включая сумму оплаты, 
                        сформированы на основании сведений, которые были предоставлены плательщиком (производителем, импортером). 
                        Ответственность за полноту и достоверность указанных в настоящем документе сведений, а также за какие-либо 
                        возможные последствия и/или ущерб, причиненный плательщику (производителю, импортеру) и/или третьим лицам, 
                        государству, государственным органам и организациям в связи с недостоверностью и/или не полнотой предоставленных 
                        сведений полностью несет плательщик (производитель, импортер). АО «Жасыл даму» полностью освобождается от такой ответственности. 
                        Данные и подлинность настоящего Сертификата о внесении утилизационного платежа можно проверить на официальном сайте АО «Жасыл даму». ', $cert);

        $cert = str_replace('[Z_S2]', 'Форма Сертификата о внесении утилизационного платежа 
                        разработана и утверждена на основании Правил реализации расширенных обязательств производителей, 
                        импортеров, утвержденных постановлением Правительства Республики Казахстан №763 от «25» октября 2021 года.', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/certificates_zd/' . $cars->vin . '.pdf');
    }
}

function __checkSVUPZip($pid)
{
    $success = false;
    $file = '';
    $path = '';
    $cert_dir = '';
    $arr = [];
    $dt = '';
    $size = '';

    $p = Profile::findFirstById($pid);
    $t = Transaction::findFirstByProfileId($p->id);

    if ($p) {
        if ($t->dt_approve < START_ZHASYL_DAMU) {
            $path = APP_PATH . '/private/certificates/';
            $cert_dir = 'certificates';
        } else {
            $path = APP_PATH . '/private/certificates_zd/';
            $cert_dir = 'certificates_zd';
        }

        if ($p->type == "CAR" || $p->type == "GOODS") {
            if (is_file($path . 'svup_' . $t->profile_id . '.zip')) {
                $success = true;
                $file = 'svup_' . $t->profile_id . '.zip';
                $dt = date("d/m/Y H:i:s", filemtime($path . 'svup_' . $t->profile_id . '.zip'));
                $size = filesize($path . 'svup_' . $t->profile_id . '.zip');
            } elseif (is_file($path . 'svup_' . $t->profile_id . '_in_progress.zip')) {
                $success = true;
                $file = 'in_progress';
            }
        } elseif ($p->type == "KPP") {
            if (is_file($path . 'kpp_svup_' . $t->profile_id . '.zip')) {
                $success = true;
                $file = 'kpp_svup_' . $t->profile_id . '.zip';
                $dt = date("d/m/Y H:i:s", filemtime($path . 'kpp_svup_' . $t->profile_id . '.zip'));
                $size = filesize($path . 'kpp_svup_' . $t->profile_id . '.zip');
            }
        }
    }

    $arr = array(
        "success" => $success,
        "cert_dir" => $cert_dir,
        "file" => $file,
        "dt" => $dt,
        "size" => $size . ' kb'
    );
    if ($success) {
        return $arr;
    }
    return false;
}

function __getUpDatesByCarVin(string $vin): array
{
    $data = [];
    $md_dt_sent = 0;
    $date_import = 0;
    $first_reg_date = 0;
    $calculate_method = 0;
    $car_vin = $vin;
    $car = Car::findFirstByVin($car_vin);
    $tr = Transaction::findFirstByProfileId($car->profile_id);

    if ($car && $tr) {
        $md_dt_sent = $tr->md_dt_sent;
        $calculate_method = $car->calculate_method;
        $date_import = $car->date_import;
        $first_reg_date = $car->first_reg_date;
    }

    $data = [
        'MD_DT_SENT' => $md_dt_sent,
        'CALCULATE_METHOD' => $calculate_method,
        'DATE_IMPORT' => $date_import,
        'FIRST_REG_DATE' => $first_reg_date,
    ];

    return $data;
}

function __checkProfileIsEditable(int $profile_id): bool
{
    $success = true;
    $profile = Profile::findFirstById($profile_id);

    if ($profile->type == "CAR") {
        if (Car::findFirstByProfileId($profile->id)) $success = false;
    } elseif ($profile->type == "GOODS") {
        if (Goods::findFirstByProfileId($profile->id)) $success = false;
    } elseif ($profile->type == "KPP") {
        if (Kpp::findFirstByProfileId($profile->id)) $success = false;
    }

    return $success;
}

function __checkSignedAfterDeclined(int $profile_id): bool
{
    $success = true;
    $profile = Profile::findFirstById($profile_id);

    if ($profile) {
        $last_declined = ProfileLogs::findFirst([
            "conditions" => "profile_id = :profile_id: and action = :action:",
            "bind" => [
                "profile_id" => $profile->id,
                "action" => "DECLINED"
            ],
            'order' => 'dt DESC'
        ]);

        if($last_declined) {
            if ($profile->sign_date < $last_declined->dt) $success = false;
        }
    }

    return $success;
}

function __checkSignedIntTranApp(int $profile_id): bool
{
    $check = true;
    $has_int_transport = 0;
    $has_app_int_tr = 0;
    $profile = Profile::findFirstById($profile_id);

    $has_int_transport += Car::count([
        "conditions" => "profile_id = :profile_id: AND ref_st_type = 2",
        "bind" => [
            "profile_id" => $profile->id
        ]
    ]);

    if ($has_int_transport > 0) {

        $has_app_int_tr += File::count([
            "conditions" => "profile_id = :profile_id: AND type = :type: AND visible = 1",
            "bind" => [
                "profile_id" => $profile->id,
                "type" => 'app_international_transport'
            ]
        ]);

        if ($has_app_int_tr == 0 || $profile->international_transporter == 0) {
            $check = false;
        }
    }

    return $check;
}

function __genIntTranApp($pid, $mode = 'view', $j = NULL)
{

    // данные подписанта
    $j_sign = '—';
    if ($j['iin']) {
        $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
    }
    // если это компания
    if ($j['bin']) {
        $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
    }

    $p = Profile::findFirstById($pid);
    $user = User::findFirstById($p->user_id);

    $to_download = APP_PATH . '/private/docs/app_int_transport_' . $p->id . '.pdf';
    $certificate_tmp = APP_PATH . '/storage/temp/app_int_transport_' . $p->id . '.html';
    $client_idnum = '';
    $client_name = '';
    $certificate_template = '';

    if (is_file($to_download)) unlink($to_download);
    if (is_file($certificate_tmp)) unlink($certificate_tmp);

    if ($user->user_type_id == 1) {
        $client_idnum = $user->idnum;
        $client_name = $user->fio;
        $certificate_template = APP_PATH . '/app/templates/html/application/app_st_car_person.html';
        $cert = join('', file($certificate_template));
        $cert = str_replace('[IIN]', $client_idnum, $cert);
        $cert = str_replace('[FIO]', $client_name, $cert);
    } else {
        $client_idnum = $user->idnum;
        $client_name = $user->org_name;
        $certificate_template = APP_PATH . '/app/templates/html/application/app_st_car_company.html';
        $cert = join('', file($certificate_template));
        $cert = str_replace('[BIN]', $client_idnum, $cert);
        $cert = str_replace('[ORG_NAME]', $client_name, $cert);
    }

    if ($mode == 'sign') {
        $_qr_ = '';
        if ($p->int_tr_app_sign != '') {
            $_qr_edited = round((strlen($p->int_tr_app_sign) / SIGN_QR_LENGTH) + 0.5);
            for ($i = 0; $i < $_qr_edited; $i++) {
                $ls = substr($p->int_tr_app_sign, $i * SIGN_QR_LENGTH, SIGN_QR_LENGTH);
                QRcode::png($ls, APP_PATH . '/storage/temp/int_tr_sign_' . $p->id . '_qr_' . $i . '.png', 'H', 5, 0);
                $_qr_ .= '<img src="' . APP_PATH . '/storage/temp/int_tr_sign_' . $p->id . '_qr_' . $i . '.png" width="116">&nbsp;';
            }
        }

        $cert = str_replace('[SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> '
            . mb_strtoupper(genAppHash($p->hash)) . '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> '
            . mb_strtoupper(genAppHash($p->int_tr_app_sign)) . '</p><p><strong>ДАННЫЕ ЭЦП:</strong> ' . mb_strtoupper($j_sign) .
            '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') . '</p>', $cert);
        $cert = str_replace('[QR_LINE]', $_qr_, $cert);

    } else {
        $cert = str_replace('[SIGN]', "Не подписано !", $cert);
        $cert = str_replace('[QR_LINE]', '', $cert);
    }

//    $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);
    $cert = str_replace('[Z_NUM]', $p->id, $cert);

    file_put_contents($certificate_tmp, $cert);
//    exec('wkhtmltopdf -q --page-size A4 --dpi 300 --disable-smart-shrinking -T 10 -B 10 -L 10 -R 10 '.$certificate_tmp.' '.APP_PATH.'/private/docs/app_int_transport_'.$p->id.'.pdf');
    (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/docs/app_int_transport_' . $p->id . '.pdf');

    if ($mode == 'sign') {
        // добавляем файл
        $f = new File();
        $f->profile_id = $p->id;
        $f->type = 'app_international_transport';
        $f->original_name = 'app_int_transport_' . $p->id . '.pdf';
        $f->ext = 'pdf';
        $f->visible = 1;

        if ($f->save()) {
            rename($to_download, APP_PATH . '/private/docs/' . $f->id . '.pdf');
        }
    }
}

function __checkAnnulledCars(int $pid): array
{
    $count = 0;
    $annulled_cars = array();
    $annulled_date = '';
    $data = array();

    $cars = Car::findByProfileId($pid);

    if ($cars) {
        foreach ($cars as $car) {
            $cl = CorrectionLogs::findFirst([
                "conditions" => "action= :action: AND type = :type: AND object_id = :object_id: ",
                "bind" => [
                    "action" => "ANNULMENT",
                    "object_id" => $car->id,
                    "type" => "CAR"
                ],
                "order" => "id DESC"
            ]);

            if ($cl) {
                $count++;
                $annulled_date = date("d.m.Y H:i", convertTimeZone($cl->dt));
                $annulled_cars[] = "$count. $car->vin ($annulled_date)";
            }
        }
    }

    $data[] = [
        'count' => $count,
        'annulled_cars' => $annulled_cars,
    ];

    return $data;
}

function __checkAnnulledGoods(int $pid): array
{
    $count = 0;
    $g_count = 0;
    $annulled_date = '';
    $data = array();
    $annuled_all = true;

    $goods = Goods::findByProfileId($pid);

    if ($goods) {
        foreach ($goods as $good) {
            $g_count++;

            $cl = CorrectionLogs::findFirst([
                "conditions" => "action= :action: AND type = :type: AND object_id = :object_id: ",
                "bind" => [
                    "action" => "ANNULMENT",
                    "object_id" => $good->id,
                    "type" => "GOODS"
                ],
                "order" => "id DESC"
            ]);

            if ($cl) {
                $count++;
                $annulled_date = date("d.m.Y H:i", convertTimeZone($cl->dt));
            }
        }
    }

    $data[] = [
        'count' => $count,
        'g_count' => $g_count,
        'annulled_date' => $annulled_date,
    ];

    return $data;
}

function __checkDeletedGoods(int $pid): array
{
    $count = 0;
    $g_count = 0;
    $deleted_date = '';
    $data = array();
    $deleted_goods = array();

    $goods = Goods::findByProfileId($pid);

    if ($goods) {
        foreach ($goods as $good) {
            $g_count++;

            $cl = CorrectionLogs::findFirst([
                "conditions" => "action= :action: AND type = :type: AND object_id = :object_id: ",
                "bind" => [
                    "action" => "DELETED",
                    "object_id" => $good->id,
                    "type" => "GOODS"
                ],
                "order" => "id DESC"
            ]);

            if ($cl) {
                $tn = RefTnCode::findFirstById($good->ref_tn);
                $count++;
                $deleted_date = date("d.m.Y H:i", convertTimeZone($cl->dt));
                $deleted_goods[] = "Код ТНВЭД: $tn->code ($deleted_date)";
            }
        }
    }

    $data[] = [
        'count' => $count,
        'g_count' => $g_count,
        'deleted_goods' => $deleted_goods,
    ];

    return $data;
}

function __checkAnnulledKpps(int $pid): array
{
    $count = 0;
    $g_count = 0;
    $annulled_date = '';
    $data = array();
    $annuled_all = true;

    $kpps = Kpp::findByProfileId($pid);

    if ($kpps) {
        foreach ($kpps as $kpp) {
            $g_count++;

            $cl = CorrectionLogs::findFirst([
                "conditions" => "action= :action: AND type = :type: AND object_id = :object_id: ",
                "bind" => [
                    "action" => "ANNULMENT",
                    "object_id" => $kpp->id,
                    "type" => "KPP"
                ],
                "order" => "id DESC"
            ]);

            if ($cl) {
                $count++;
                $annulled_date = date("d.m.Y H:i", convertTimeZone($cl->dt));
            }
        }
    }

    $data[] = [
        'count' => $count,
        'g_count' => $g_count,
        'annulled_date' => $annulled_date,
    ];

    return $data;
}

function __checkDeletedKpps(int $pid): array
{
    $count = 0;
    $g_count = 0;
    $deleted_date = '';
    $data = array();
    $deleted_kpps = array();

    $kpps = Kpp::findByProfileId($pid);

    if ($kpps) {
        foreach ($kpps as $kpp) {
            $g_count++;

            $cl = CorrectionLogs::findFirst([
                "conditions" => "action= :action: AND type = :type: AND object_id = :object_id: ",
                "bind" => [
                    "action" => "DELETED",
                    "object_id" => $kpp->id,
                    "type" => "KPP"
                ],
                "order" => "id DESC"
            ]);

            if ($cl) {
                $tn = RefTnCode::findFirstById($kpp->ref_tn);
                $count++;
                $deleted_date = date("d.m.Y H:i", convertTimeZone($cl->dt));
                $deleted_kpps[] = "Код ТНВЭД: $tn->code ($deleted_date)";
            }
        }
    }

    $data[] = [
        'count' => $count,
        'g_count' => $g_count,
        'deleted_kpps' => $deleted_kpps,
    ];

    return $data;
}

function __getDataFromKAPByVin(string $vin, int $user_id = null): array
{
    $result_data = [];
    $data = array();
    $found = false;
    $car_info = '';
    $status = '';
    $state = '';
    $k = 0;
    $kap_request_id = NULL;

    // $request = KapRequest::findFirstByVin($vin); // test
    // if( __isValidXml($request->xml_response )){ // test
    // $xml_data = new SimpleXMLElement($request->xml_response); // test

    exec('cd /var/www/recycle/kap_integration && LD_LIBRARY_PATH="/opt/kalkancrypt:/opt/kalkancrypt/lib/engines" php list.php ' . $vin, $result_data);

    if (__isValidXml($result_data[0])) {
        $xml_data = new SimpleXMLElement($result_data[0]);

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
                            $state = 'На регистрации';
                            $car_info .= "STATUS=Карточка распечатана ($field)";
                            break;
                        case "S":
                            $found = true;
                            $state = 'Карточка снята с учета';
                            $car_info .= "STATUS=Карточка снята с учета ($field)";
                            break;
                        default:
                            $state = $field;
                            $car_info .= "STATUS=$field";
                            break;
                    }
                }
            }

            if ($found != false) {
                foreach ($record[0]->field as $field) {
                    $field_name = $field->attributes();
                    $field_name = $field_name[0];
                    $k++;

                    if (isset(KAP_INTEG_DATA_TYPE[strval($field_name)])) {
                        $car_info .= "$field_name=$field,";
                        $status .= '<span>' . $k . '. ' . KAP_INTEG_DATA_TYPE[strval($field_name)] . ': ';
                        $status .= ' <b>' . $field . '</b></span><br>';
                    }
                }

                $k_request = new KapRequest();
                $k_request->vin = $vin;
                $k_request->base_on = "add&edit_car";
                $k_request->state = $state;
                $k_request->k_status = $status;
                // $k_request->xml_response = $request->xml_response; // test
                $k_request->xml_response = $result_data[0];
                $k_request->user_id = $user_id;
                $k_request->created_at = time();

                if ($k_request->save()) $kap_request_id = $k_request->id;
            }
        }
    }

    $data[] = [
        'FOUND' => $found,
        'CAR_INFO' => $car_info,
        'KAP_REQUEST_ID' => $kap_request_id
    ];

    return $data;
}

function __checkIsPaid(int $pid): bool
{
    $paid = false;
    $paid_amount = 0;

    $tr = Transaction::findFirstByProfileId($pid);

    if ($tr->amount == 0) $paid = true;

    if ($tr->amount > 0) {
        $banks = Bank::find(array(
            'conditions' => "(transactions LIKE :value:) AND iban_to IN ('KZ256017131000029119', 'KZ606017131000029459', 'KZ236017131000028670', 'KZ686017131000029412', 'KZ61926180219T620004', 'KZ34926180219T620005', 'KZ07926180219T620006', 'KZ77926180219T620007', 'KZ196010111000325234', 'KZ896010111000325235', 'KZ466010111000325233', 'KZ736010111000325232', 'KZ496018871000301461')",
            'bind' => array('value' => '%' . $pid . '%'),
            "order" => "id DESC"
        ));

        if ($banks) {
            foreach ($banks as $bank) {
                $pids = $bank->transactions;
                $pid_arr = explode(',', $pids);

                foreach ($pid_arr as $b_pid) {
                    if ($b_pid == $pid) {
                        $paid_amount += $bank->amount;
                    }
                }
            }
        }


        $zd_banks = ZdBank::find(array(
            'conditions' => "(transactions LIKE :value:) AND iban_to IN ('KZ20601A871001726091', 'KZ02601A871001725251', 'KZ07601A871001726131', 'KZ70601A871001726161', 'KZ62601A871001726111', 'KZ41601A871001726101', 'KZ28601A871001726141', 'KZ49601A871001726151', 'KZ83601A871001726121')",
            'bind' => array('value' => '%' . $tr->id . '%'),
            "order" => "id DESC"
        ));

        if ($zd_banks) {
            foreach ($zd_banks as $bank) {
                $pids = $bank->transactions;
                $pid_arr = explode(',', $pids);

                foreach ($pid_arr as $b_pid) {
                    if (intval(trim($b_pid)) == intval($tr->profile_id)) {
                        $paid_amount += $bank->amount;
                    }
                }
            }
        }
    }

    $epsilon = 0.001;
    $abs = abs(strval($paid_amount) - strval($tr->amount));

    if ((($abs < $epsilon || $abs == $epsilon))) $paid = true;
    if (strval($tr->amount) < strval($paid_amount)) $paid = true;

    return $paid;
}

function __checkKeywordsInKap($notes): bool
{
    $check_notes = false;

    $key_words = array("ввременный ввоз", "веменный ввоз", "верменный ввоз", "врееменный ввоз", "вреемнный ввоз", "вреенный ввоз",
        "времеенный ввоз", "временнй ввоз", "временнйы ввоз", "временнный ввоз", "временны ввоз", "временны йввоз", "временный ввоз",
        "временный вввоз", "временный ввз", "временный вво", "временный ввоз", "временный ввозз", "временный ввооз", "временный вовз",
        "временный воз", "временныйв воз", "временныйввоз", "временныйй ввоз", "временныый ввоз", "временый ввоз", "временынй ввоз",
        "времменный ввоз", "времненый ввоз", "времнный ввоз", "врмеенный ввоз", "врменный ввоз", "врременный ввоз", "ВВРЕМЕННЫЙ ВВОЗ",
        "ВЕМЕННЫЙ ВВОЗ", "ВЕРМЕННЫЙ ВВОЗ", "ВРЕЕМЕННЫЙ ВВОЗ", "ВРЕЕМННЫЙ ВВОЗ", "ВРЕЕННЫЙ ВВОЗ", "ВРЕМЕЕННЫЙ ВВОЗ", "ВРЕМЕННЙ ВВОЗ",
        "ВРЕМЕННЙЫ ВВОЗ", "ВРЕМЕНННЫЙ ВВОЗ", "ВРЕМЕННЫ ВВОЗ", "ВРЕМЕННЫ ЙВВОЗ", "ВРЕМЕННЫЙ ВВОЗ", "ВРЕМЕННЫЙ ВВВОЗ", "ВРЕМЕННЫЙ ВВЗ",
        "ВРЕМЕННЫЙ ВВО", "ВРЕМЕННЫЙ ВВОЗ", "ВРЕМЕННЫЙ ВВОЗЗ", "ВРЕМЕННЫЙ ВВООЗ", "ВРЕМЕННЫЙ ВОВЗ", "ВРЕМЕННЫЙ ВОЗ", "ВРЕМЕННЫЙВ ВОЗ",
        "ВРЕМЕННЫЙВВОЗ", "ВРЕМЕННЫЙЙ ВВОЗ", "ВРЕМЕННЫЫЙ ВВОЗ", "ВРЕМЕНЫЙ ВВОЗ", "ВРЕМЕНЫНЙ ВВОЗ", "ВРЕММЕННЫЙ ВВОЗ", "ВРЕМНЕНЫЙ ВВОЗ",
        "ВРЕМННЫЙ ВВОЗ", "ВРМЕЕННЫЙ ВВОЗ", "ВРМЕННЫЙ ВВОЗ", "ВРРЕМЕННЫЙ ВВОЗ", "аренда", "АРЕНДА", "договор аренды", "ДОГОВОР АРЕНДЫ");

    foreach ($key_words as $key_word) {
        if (strstr($notes, $key_word)) $check_notes = true;
    }

    return $check_notes;
}

function __getDataFromEpts(string $uniqueNumber = null, int $operationType = 0, int $user_id = 0, string $base_on = "test", $save = false): array
{
    $result_data = [];

    $statusCode = 0;
    $messageDescription = NULL;
    $epts_request_id = 0;
    $success = false;
    $uniqueCode = NULL;
    $vin = NULL;
    $digitalPassportStatus = NULL;
    $vehicleTechCategoryCodeNsi = NULL;
    $manufactureYear = NULL;
    $engineCapacityMeasure = NULL;
    $engineMaxPowerMeasure = NULL;
    $vehicleFuelKindCodeList = NULL;
    $vehicleMadeInCountry = NULL;
    $vehicleMassMeasure1 = NULL;
    $manufactureDate = NULL;
    $releaseDate = NULL;
    $bin = NULL;
    $pdf_base64 = false;
    $image_base64 = false;

    $green_response_xml_file_name = NULL;
    $response_xml_file_name = NULL;
    $request_xml_file_name = NULL;
    $execution_time = 0;
    $request_time = 0;
    $sessionId = NULL;
    $messageId = NULL;
    $responseDate = NULL;
    $code = NULL;
    $message = NULL;
    $errors = NULL;

    $folder = APP_PATH . "/private/docs/epts_logs/";
    if (!file_exists($folder)) mkdir($folder, 0777, true);

    $eptsService = new EptsService();
    $requestData = [
        'value' => $uniqueNumber,
        'operationType' => $operationType
    ];
    $resultEpts = $eptsService->search($requestData);

    if(isset($resultEpts['status'])) {
        if ($resultEpts['status'] === 'success') {
            $result = $resultEpts['data'];
            $response_xml_file_name = $result['response'];
            $request_xml_file_name = $result['request'];
            $green_response_xml_file_name = $result['green_response'];
            $execution_time = round((float)$result['execution_time'], 3);
            $request_time = $result['request_time'];
            $messageId = $result['message_id'];
            $errors = [];

            $shep_parser = EptsRequest::shepParser($response_xml_file_name);

            $sessionId = $shep_parser['sessionId'] ?? ($shep_parser['session_id'] ?? '');
            $responseDate = $shep_parser['responseDate'] ?? ($shep_parser['response_date'] ?? '');
            $code = isset($shep_parser['code']) ? $shep_parser['code'] : '';
            $message = isset($shep_parser['message']) ? $shep_parser['message'] : '';

            $epts = EptsRequest::greenResponseParser($green_response_xml_file_name);

            if($epts) {
                $success = $epts['success'];
                $statusCode = $epts['statusCode'];
                $messageDescription = $epts['messageDescription'];
                $uniqueCode = $epts['uniqueCode'];
                $vin = $epts['vin'];
                $digitalPassportStatus = $epts['digitalPassportStatus'];
                $vehicleTechCategoryCodeNsi = $epts['vehicleTechCategoryCodeNsi'];
                $manufactureYear = $epts['manufactureYear'];
                $engineCapacityMeasure = $epts['engineCapacityMeasure'];
                $engineMaxPowerMeasure = $epts['engineMaxPowerMeasure'];
                $vehicleFuelKindCodeList = $epts['vehicleFuelKindCodeList'];
                $vehicleMadeInCountry = $epts['vehicleMadeInCountry'];
                $vehicleMassMeasure1 = $epts['vehicleMassMeasure1'];
                $manufactureDate = $epts['manufactureDate'];
                $releaseDate = $epts['releaseDate'];
                $pdf_base64 = ($epts['pdf_base64'] != NULL) ? true : false;
                $image_base64 = ($epts['image_base64'] != NULL) ? true : false;
                $bin = $epts['bin'];
            }
        }
    }

    $epts_request = new EptsRequest();
    $epts_request->request_num = $uniqueNumber;
    $epts_request->request_time = $request_time;
    $epts_request->operation_type = $operationType;
    $epts_request->base_on = $base_on;
    $epts_request->request = $request_xml_file_name;
    $epts_request->message_id = $messageId;
    $epts_request->execution_time = $execution_time;
    $epts_request->response_date = $responseDate;
    $epts_request->shep_status_code = $code;
    $epts_request->shep_status_message = $message;
    $epts_request->session_id = $sessionId;
    $epts_request->status_code = $statusCode;
    $epts_request->description = $messageDescription;
    $epts_request->unique_code = $uniqueCode;
    $epts_request->digital_passport_status = $digitalPassportStatus;
    $epts_request->vin = $vin;
    $epts_request->response = $response_xml_file_name;
    $epts_request->green_response = $green_response_xml_file_name;
    $epts_request->user_id = $user_id;
    $epts_request->created_at = time();

    if ($epts_request->save()) $epts_request_id = $epts_request->id;

    return [
        'SUCCESS' => $success,
        'STATUS_CODE' => $statusCode,
        'EPTS_REQUEST_ID' => $epts_request_id,
        'EPTS_VIN' => $vin,
        'EPTS_UNIQUE_CODE' => $uniqueCode,
        'EPTS_COUNTRY' => $vehicleMadeInCountry,
        'EPTS_YEAR' => $manufactureYear,
        'EPTS_CATEGORY' => $vehicleTechCategoryCodeNsi,
        'EPTS_CapacityMeasure' => $engineCapacityMeasure,
        'EPTS_MaxPowerMeasure' => $engineMaxPowerMeasure,
        'EPTS_MassMeasure1' => $vehicleMassMeasure1,
        'EPTS_FUEL_TYPE' => $vehicleFuelKindCodeList,
        'EPTS_ManufactureDate' => $manufactureDate,
        'EPTS_ReleaseDate' => $releaseDate,
        'EPTS_BIN' => $bin,
        'EPTS_IMAGE_BASE64' => $image_base64,
        'EPTS_PDF_BASE64' => $pdf_base64,
        'ERRORS' => $errors,
    ];
}

function __uploadEptsPdfToProfile(int $epts_request_id, int $pid, int $car_id): void
{
    $p_folder = APP_PATH . "/private/docs/epts_pdf/$pid";

    if (!file_exists($p_folder)) {
        @mkdir($p_folder, 0777, true);
    }

    $epts = EptsRequest::findFirstById($epts_request_id);

    if ($epts->status_code == 200) {
        $parsed_epts = EptsRequest::greenResponseParser($epts->green_response);
        $base64string = $parsed_epts['pdf_base64'];
        $car_vin = $parsed_epts['vin'];

        $spravka_epts = APP_PATH . '/storage/temp/epts_request_' . $epts_request_id . '.pdf';

        EptsRequest::genDoc($epts, false);

        if (file_exists($spravka_epts)) {
            copy($spravka_epts, $p_folder . '/spravka_' . $car_vin . '.pdf');

            if (!empty($base64string)) {
                if (strpos($base64string, ',') !== false) {
                    @list($encode, $base64string) = explode(',', $base64string);
                }

                $base64data = base64_decode($base64string, true);

                if (strpos($base64data, '%PDF') !== 0) {
                    echo 'Missing the PDF file signature';
                } else {

                    file_put_contents($p_folder . '/epts_' . $car_vin . '.pdf', $base64data);

                    $digitalpass = File::findFirst(array(
                        "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                        "bind" => array(
                            "pid" => $pid,
                            "type" => "digitalpass"
                        )
                    ));

                    if ($digitalpass) {
                        $doc_path = APP_PATH . '/private/docs/' . $digitalpass->id . '.pdf';
                        exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $doc_path . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $pid . '/epts_*.pdf');
                    } else {
                        // добавляем файл
                        $f = new File();
                        $f->profile_id = $pid;
                        $f->type = 'digitalpass';
                        $f->original_name = 'ЭлектронныйПаспорт_' . $car_vin . '.pdf';
                        $f->ext = 'pdf';
                        $f->good_id = 0;
                        $f->car_id = $car_id;
                        $f->visible = 1;

                        if ($f->save()) {
                            $doc_path = APP_PATH . '/private/docs/' . $f->id . '.pdf';
                            exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $doc_path . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $pid . '/epts_*.pdf');
                        }
                    }

                    $spravka = File::findFirst(array(
                        "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                        "bind" => array(
                            "pid" => $pid,
                            "type" => "spravka_epts"
                        )
                    ));

                    if ($spravka) {
                        $doc_path = APP_PATH . '/private/docs/' . $spravka->id . '.pdf';
                        exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $doc_path . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $pid . '/spravka_*.pdf');
                    } else {
                        // добавляем файл
                        $f = new File();
                        $f->profile_id = $pid;
                        $f->type = 'spravka_epts';
                        $f->original_name = 'СправкаЭПТС_' . $car_vin . '.pdf';
                        $f->ext = 'pdf';
                        $f->good_id = 0;
                        $f->car_id = $car_id;
                        $f->visible = 1;

                        if ($f->save()) {
                            $doc_path = APP_PATH . '/private/docs/' . $f->id . '.pdf';
                            exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $doc_path . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $pid . '/spravka_*.pdf');
                        }
                    }
                }
            }
        }
    }
}

function __getEptsFromDB(int $id): array
{
    $data = array();

    $epts = EptsRequest::findFirstById($id);

    if ($epts->status_code == 200) {
        $parsed_epts = EptsRequest::greenResponseParser($epts->green_response);

        $data = [
            'STATUS_CODE' => $parsed_epts['statusCode'],
            'EPTS_REQUEST_ID' => $id,
            'EPTS_VIN' => $parsed_epts['vin'],
            'EPTS_UNIQUE_CODE' => $parsed_epts['uniqueCode'],
            'EPTS_COUNTRY' => $parsed_epts['vehicleMadeInCountry'],
            'EPTS_YEAR' => $parsed_epts['manufactureYear'],
            'EPTS_CATEGORY' => $parsed_epts['vehicleTechCategoryCodeNsi'],
            'EPTS_MassMeasure1' => $parsed_epts['vehicleMassMeasure1'],
            'EPTS_CapacityMeasure' => $parsed_epts['engineCapacityMeasure'],
            'EPTS_MaxPowerMeasure' => $parsed_epts['engineMaxPowerMeasure'],
            'EPTS_FUEL_TYPE' => $$parsed_epts['vehicleFuelKindCodeList'],
            'EPTS_ManufactureDate' => $parsed_epts['manufactureDate'],
            'EPTS_ReleaseDate' => $parsed_epts['releaseDate'],
            'EPTS_BIN' => $parsed_epts['bin'],
        ];
    }

    return $data;
}

function __checkIsProdServer(): bool
{
    if (in_array(SERVER_IP, PROD_SERVER_IP)) {
        return true;
    }

    return false;
}

function __checkQueue(int $pid, string $vin = ''): array
{
    $pid_found_in_queue = false;
    $vin_in_queue = NULL;
//    $di = Di::getDefault();
//    $config = $di->get('config');
//
//    $params = ['count' => 1, 'requeue' => true, 'encoding' => 'auto', 'truncate' => 50000, "ackmode" => "ack_requeue_true"];
//
//    $url = $config->rabbit->apiUrl . '/api/queues/%2f/' . $config->rabbit->name . '/get/';
//
//    $ch = curl_init($url);
//    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
//    curl_setopt($ch, CURLOPT_HEADER, 0);
//    curl_setopt($ch, CURLOPT_USERPWD, $config->rabbit->username . ":" . $config->rabbit->password);
//    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
//    curl_setopt($ch, CURLOPT_POST, 1);
//    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//    $return = curl_exec($ch);
//    curl_close($ch);
//
//    $result = json_decode($return, true);
//    $queue_msg_count = 0;
//    if (!empty($result)) {
//
//        if (isset($result[0])) {
//            if (isset($result[0]['message_count'])) {
//                $queue_msg_count = $result[0]['message_count'];
//            }
//        }
//
//        for ($i = 0; $i <= $queue_msg_count + 1; $i++) {
//            $params = ['count' => 1, 'requeue' => true, 'encoding' => 'auto', 'truncate' => 50000, "ackmode" => "ack_requeue_true"];
//
//            $url = $config->rabbit->apiUrl . '/api/queues/%2f/' . $config->rabbit->name . '/get/';
//            $ch = curl_init($url);
//            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
//            curl_setopt($ch, CURLOPT_HEADER, 0);
//            curl_setopt($ch, CURLOPT_USERPWD, $config->rabbit->username . ":" . $config->rabbit->password);
//            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//            $return = curl_exec($ch);
//            curl_close($ch);
//
//            $result = json_decode($return, true);
//
//            if ($result) {
//                if (isset($result[0])) {
//                    if (isset($result[0]['payload'])) {
//                        $payload = $result[0]['payload'];
//                        $job = unserialize($payload);
//
//                        if ($job['profile_id'] == $pid && ($job['task_name'] == 'ADD_CAR')) {
//                            $pid_found_in_queue = true;
//                            $vin_in_queue = $job['vin'];
//                            break;
//                        }
//                    }
//                }
//            }
//        }
//    }

    return ["FOUND" => $pid_found_in_queue, "VIN" => $vin_in_queue];
}

/**
 * @throws DateMalformedStringException
 */
function convertTimeZone($current_tz = 0): ?int
{
    // Если пришла строка — пробуем привести к int
    if (is_string($current_tz)) {
        if (ctype_digit($current_tz)) {
            $current_tz = (int)$current_tz;
        } else {
            return 0;
        }
    }

    $unix_time = $current_tz;

    if ($current_tz > 0 && $current_tz <= TIMEZONE_CHANGE_DT) {
        $dt_import_str = date('Y-m-d H:i:s', $current_tz);
        $date = new DateTime($dt_import_str);
        $date->setTimezone(new DateTimeZone('Asia/Dhaka'));
        $unix_time = strtotime($date->format('Y-m-d H:i:s'));
    }

    return $unix_time;
}

function convertEptsTimeZone(string $dt = null)
{
    $date = $dt;
    if ($dt != NULL && strstr($dt, '+06')) {
        $new_date = new DateTime($dt);
        $new_date->setTimezone(new DateTimeZone('Asia/Dhaka'));
        $date = $new_date->format('d.m.Y');
    }

    return $date;
}

function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return 'Неизвестный IP';
}

//function withOldTimezone(int $timestamp): string {
//    $timezoneChangeDate = strtotime('2024-03-01 00:00:00');
//
//    // Создаём объект DateTime из UTC timestamp
//    $dt = new DateTime("@$timestamp");
//
//    if ($timestamp < $timezoneChangeDate) {
//        // До смены — использовать старое смещение вручную
//        $dt->setTimezone(new DateTimeZone('+06:00'));
//    } else {
//        // После — использовать текущую зону Almaty
//        $dt->setTimezone(new DateTimeZone('Asia/Almaty'));
//    }
//
//    return $dt->format('Y-m-d H:i:s');
//}

function getMrpByDate($date_str)
{
    $mrp_array = MRP_PERIODS;
    $date = strtotime($date_str);

    foreach ($mrp_array as $period) {
        $start = strtotime($period['start']);
        $end = strtotime($period['end']);

        if ($date >= $start && $date <= $end) {
            return $period['value'];
        }
    }

    return 0;
}

function getTnPriceKeyByDate($date)
{
    $timestamp = strtotime($date);
    $periods = TN_PERIODS;

    foreach ($periods as $key => $range) {
        $start = isset($range['start']) ? strtotime($range['start']) : null;
        $end = isset($range['end']) ? strtotime($range['end']) : null;

        // Если указана только дата начала
        if ($start && !$end && $timestamp >= $start) {
            return $key;
        }

        // Если указаны обе даты
        if ($start && $end && $timestamp >= $start && $timestamp <= $end) {
            return $key;
        }
    }

    return null; // если дата не попадает ни в один период
}

function generateQrHash($content_qr): string
{
    return md5($content_qr . md5(getenv('NEW_SALT')));
}

?>
