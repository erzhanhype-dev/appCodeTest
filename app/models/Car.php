<?php

use App\Services\Cms\CmsService;
use App\Services\Pdf\PdfService;
use PHPQRCode\QRcode;

class Car extends \Phalcon\Mvc\Model
{

    public const string VEHICLE_TYPE_PASSENGER = 'PASSENGER';
    public const string VEHICLE_TYPE_CARGO = 'CARGO';
    public const string VEHICLE_TYPE_AGRO = 'AGRO';

    public ?int $id = null;
    public string $volume = '0';
    public string $vin;
    public ?string $year;
    public int $profile_id = 0;
    public ?int $ref_car_cat = 0;
    public int $ref_car_type_id = 0;
    public int $ref_country = 0;
    public int $ref_country_import = 0;
    public int $ref_st_type = 0;

    public int $date_import = 0;
    public string $cost = '0';
    public ?int $electric_car = 0;
    public ?int $calculate_method = 0;
    public ?string $status = null;
    public int $check_reg_status = 0;
    public int $mask_id = 0;
    public int $created = 0;
    public int $updated = 0;
    public int $first_reg_date = 0;
    public ?int $kap_request_id = 0;
    public ?int $epts_request_id = 0;
    public ?int $kap_log_id = 0;
    public string $vehicle_type = self::VEHICLE_TYPE_PASSENGER;
    public ?string $id_code = null;
    public ?string $body_code = null;

    public function initialize(): void
    {
        $this->setSchema('recycle');
        $this->setSource('car');

        // для корректной работы hasChanged()
        $this->keepSnapshots(true);
        $this->useDynamicUpdate(true);

        $this->belongsTo(
            'ref_country',
            RefCountry::class,
            'id',
            [
                'alias' => 'country',
                'reusable' => true,
            ]
        );

        $this->belongsTo(
            'ref_country_import',
            RefCountry::class,
            'id',
            [
                'alias' => 'country_import',
                'reusable' => true,
            ]
        );

        $this->belongsTo(
            'ref_car_cat',
            RefCarCat::class,
            'id',
            [
                'alias' => 'ref_category',
                'reusable' => true,
            ]
        );
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

    public static function genSvupKz($t_id = 0, $c_id = 0)
    {
        $langFile = APP_PATH . '/resources/lang/kk.php';
        $messages = [];
        if (file_exists($langFile)) {
            require $langFile;
        }
        if (!isset($messages) || !is_array($messages)) {
            $messages = [];
        }
        $interpolator = new \Phalcon\Translate\InterpolatorFactory();
        $lc = new \Phalcon\Translate\Adapter\NativeArray($interpolator, [
            'content' => $messages
        ]);

        $certificate_template = APP_PATH.'/app/templates/html/dpp_zd/certificate_car_kz.html';
        $t = Transaction::findFirstById($t_id);
        $p = Profile::findFirstById($t->profile_id);
        $path = APP_PATH.'/private/certificates_zd/';
        $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 550px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">CANCELED</div>';
        $cmsService = new CmsService();

        if ($t->approve != 'GLOBAL') {
            return "Сертификат берілмеген!";
        }

        if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
            return "Өтінім мақұлданбаған!";
        }

        $car = self::findFirstById($c_id);
        $to_download = $path.$car->vin.'_kz.pdf';
        $certificate_tmp = APP_PATH.'/storage/temp/certificate_'.$car->id.'_kz.html';

        $page_num = 0;
        $is_electric_car = '';

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

        $cancel_car = Car::findFirst([
            "conditions" => "id = :cid: and status = :status:",
            "bind" => [
                "cid" => $car->id,
                "status" => "CANCELLED"
            ]
        ]);

        $edited_car = CorrectionLogs::find([
            "conditions" => "object_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $car->id,
                "type" => "CAR"
            ]
        ]);

        // начали цикл
        if ($cancel_car || $edited_car || !is_file(APP_PATH . '/private/certificates_zd/' . $car->vin . '.pdf')) {
            $m = RefCarCat::findFirstById($car->ref_car_cat);
            $type = RefCarType::findFirstById($car->ref_car_type_id);
            $vehicle_type = $car->vehicle_type;
            // если это машина с исправленным ВИН-ом
            $trans_vin = '';
            if (is_file(APP_PATH . '/private/trans/' . $car->vin . '.txt')) {
                $trans_vin = file_get_contents(APP_PATH . '/private/trans/' . $car->vin . '.txt');
            }

            // строка QR-кода и его подпись
            $content_qr = $t->profile_id . ':' . date('d.m.Y', $t->date) . ':' . mb_strtoupper(str_replace('cat-', '', $m->name)) . ':' . $car->year . ':' . $car->vin . ':' . $car->volume . ':' . $car->cost;
            $content_base64 = base64_encode($content_qr);

            $_sign = $cmsService->signHash($content_base64);
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
                    QRcode::png($l, APP_PATH . '/storage/temp/' . $car->id . '_qr_' . $i . '.png', 'H', 5, 0);
                    $__qr_sign_line .= '<img src="' . APP_PATH . '/storage/temp/' . $car->id . '_qr_' . $i . '.png" width="116">&nbsp;';
                }

                // die();
                // формируем изображение
                QRcode::png($__qr, APP_PATH . '/storage/temp/' . $car->id . '.png', 'H', 4, 0);
            }

            $cert = join('', file($certificate_template));

            $certificate_tmp = APP_PATH . '/storage/temp/certificate_' . $car->id . '.html';

            $cert = str_replace('[Z_TITLE]', 'Сертификат о внесении утилизационного платежа', $cert);
            $cert = str_replace('[Z_NUM]', '№0' . str_pad($car->id, 8, 0, STR_PAD_LEFT), $cert);

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

                    $html .= '<div class="page">
                            <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                            <h1> Қосымша ' . $page_num . '</h1>
                            <br><br><b>Өзгерістер енгізу жөніндегі өтінім № ' . $log->id . ' күні/айы/жылы, уақыты ' . date(" H:i d-m-Y ", $log->dt) . '.</b><br><br><table>';

                    foreach (json_decode($log->meta_before) as $l_before) {
                        foreach (json_decode($log->meta_after) as $l_after) {
                            $country_before = RefCountry::findFirstById($l_before->ref_country);
                            $country_after = RefCountry::findFirstById($l_after->ref_country);
                            $car_type_before = RefCarType::findFirstById($l_before->ref_car_type_id);
                            $car_type_after = RefCarType::findFirstById($l_after->ref_car_type_id);
                            $car_cat_before = RefCarCat::findFirstById($l_before->ref_car_cat);
                            $car_cat_after = RefCarCat::findFirstById($l_after->ref_car_cat);
                            $calculate_method_before = $l_before->calculate_method ? $lc->_('calculate-method-'.$l_before->calculate_method) : '';
                            $calculate_method_after = $l_after->calculate_method ? $lc->_('calculate-method-'.$l_after->calculate_method) : '';
                            $cost_before = __money($l_before->cost) . ' тг';
                            $cost_after = __money($l_after->cost) . ' тг';
                            $e_car_before = '—';
                            $e_car_after = '—';

                            $no_text = mb_strtoupper($lc->_('no'));
                            $yes_text = $lc->_('yes');
                            $yes_text_int = mb_strtoupper($lc->_('yes') . '('. $lc->_('int-tr') . ')');

                            if ($l_before->electric_car == 0) {
                                $e_car_before = $no_text;
                            } elseif ($l_before->electric_car == 1) {
                                $e_car_before = $yes_text;
                            }

                            if ($l_after->electric_car == 0) {
                                $e_car_after = $no_text;
                            } elseif ($l_after->electric_car == 1) {
                                $e_car_after = $yes_text;
                            }

                            if ($l_before->ref_st_type == 0) {
                                $st_type_before = $no_text;
                            } elseif ($l_before->ref_st_type == 1) {
                                $st_type_before = $yes_text;
                            } elseif ($l_before->ref_st_type == 2) {
                                $st_type_before = $yes_text_int;
                            }

                            if ($l_after->ref_st_type == 0) {
                                $st_type_after = $no_text;
                            } elseif ($l_after->ref_st_type == 1) {
                                $st_type_after = $yes_text;
                            } elseif ($l_after->ref_st_type == 2) {
                                $st_type_after = $yes_text_int;
                            }

                            $html .= '
                                <tr><td><b>Өріс атауы</b></td><td><b>Ескі мәні</b></td><td><b>Жаңа мәні</b></td></tr>
                                <tr><td>Өндірілген жылы</td><td>' . $l_before->year . '</td><td>' . $l_after->year . '</td></tr>
                                <tr><td>Түрі</td><td>' . $car_type_before->name . '</td><td>' . $car_type_after->name . '</td></tr>
                                <tr><td>Көлік құралдарының және өздігінен жүретін ауыл шаруашылығы техникасының түрлері және санаттары</td><td>' . $lc->_($car_cat_before->name) . '</td><td>' . $lc->_($car_cat_after->name) . '</td></tr>
                                <tr><td>Седельный тягач па?</td><td>' . $st_type_before . '</td><td>' . $st_type_after . '</td></tr>
                                <tr><td>Көлемі / салмағы</td><td>' . number_format($l_before->volume, 3) . '</td><td>' . number_format($l_after->volume, 3) . '</td></tr>
                                <tr><td>VIN-код / нөмір</td><td>' .  preg_replace('/-/', '&', $l_before->vin, 1) . '</td><td>' . preg_replace('/-/', '&', $l_after->vin, 1) . '</td></tr>
                                <tr><td>Өндірілген елі</td><td>' . $country_before->name . '</td><td>' . $country_after->name . '</td></tr>
                                <tr><td>Сомасы, теңге</td><td>' . $cost_before . '</td><td>' . $cost_after . '</td></tr>
                                <tr><td>Импорт күні</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                <tr><td>Есептеу тәсілі</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>
                                <tr><td>Электрқозғалтқышы бар ма?</td><td>' . $e_car_before . '</td><td>' . $e_car_after . '</td></tr>';
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
                        ' <br><br><b>ДРПУП БАСШЫСЫ:</b> «ЖАСЫЛ ДАМУ» АКЦИОНЕРЛІК ҚОҒАМЫ (БИН
                                    040340008429)</td></tr>';
                    $html .= '</table></div></div>';
                }

                $cert = str_replace('[LOGS]', $html, $cert);
                $cert = str_replace('[LOG_INFO]', $log_info, $cert);
            } else {
                $cert = str_replace('[LOGS]', '', $cert);
                $cert = str_replace('[LOG_INFO]', '', $cert);
            }

            $dt_for_use = $t->date;
            if($t->dt_approve > 0) {
                $dt_for_use = $t->dt_approve;
            }

            $cert = str_replace('[Z_DATE]', 'берілген күні '.date('d.m.Y', $dt_for_use).' жыл.', $cert);
            $cert = str_replace('[Z_PRE]', 'Өндірушілердің (импорттаушылардың) кеңейтілген міндеттемелерін 
                       орындау мақсатында кәдеге жарату төлемінің енгізілгенін растайтын құжат, атап айтқанда: '.$ac_to, $cert);

            $__printed_vin = $car->vin;
            if($trans_vin != '') {
                $__printed_vin = $__printed_vin." ($trans_vin)";
            }

            $c_cost = number_format($car->cost, 2, ',', ' ').' теңге';

            // если это Грузовой автомобиль
            if ($m->car_type == 2) {
                $cert = str_replace('[TOP_LEFT]', '№0'.str_pad($car->id, 8, 0, STR_PAD_LEFT).' - автокөлік құралдары', $cert);
                $cert = str_replace('[Z_CAT]', 'Автокөлік құралының санаты: '.$lc->_($m->name), $cert);
                $cert = str_replace('[Z_YEAR]', 'Шығарылған жылы: '.$car->year, $cert);
                $cert = str_replace('[DT_MANUFACTURE]', 'Өндіріс күні / импорттау күні: '.date("d-m-Y", $car->date_import), $cert);
                $cert = str_replace('[Z_VIN]', 'Көлік құралының сәйкестендіру нөмірі (VIN):  '.$__printed_vin, $cert);
                $cert = str_replace('[Z_VOLUME]', 'Қозғалтқыштың жұмыс көлемі (V см3)/Көлік құралының техникалық қолжетімді максималды массасы (кг):   '.$car->volume, $cert);
                // уточнение по седельным тягачам от 21.09.2020
                // дата импорта после 02.05.2019 применять признак седельности, а до не применяит
                if ($car->date_import >= 1588356000) {
                    $sed_t = ' (жоқ, бұл ершіткі тартқыштар емес)';
                    if($car->ref_st_type == 1) {
                        $sed_t = ' (ия, бұл ершіткі тартқыштар)';
                    }elseif($car->ref_st_type == 2){
                        $sed_t = ' (ия, бұл ершіткі тартқыштар (халықаралық тасымалдау))';
                    }
                }
                else  $sed_t = '';
                $cert = str_replace('[Z_TYPE]', 'Автокөліктің типі: '.$type->name_kz.$sed_t, $cert);

                // если это ССХТ
            } else if($m->car_type == 5 || $m->car_type == 4) {
                $__printed_vin = str_replace('I-', '—-', $__printed_vin);
                $__printed_vin = str_replace('-B', '-—', $__printed_vin);
                $__pv = preg_split('/[-&]/', $__printed_vin, 2);
                $cert = str_replace('[TOP_LEFT]', '№0'.str_pad($car->id, 8, 0, STR_PAD_LEFT).' - Өздігінен жүретін ауыл шаруашылығы техникасы бойынша', $cert);
                $cert = str_replace('[Z_CAT]', 'Өздігінен жүретін ауыл шаруашылығы техникасының санаты: '.$lc->_($m->name), $cert);
                $cert = str_replace('[Z_YEAR]', 'Шығарылған жылы: '.$car->year, $cert);
                $cert = str_replace('[DT_MANUFACTURE]', 'Өндіріс күні / импорттау күні: '.date("d-m-Y", $car->date_import), $cert);
                $cert = str_replace('[Z_TYPE]', 'Өздігінен жүретін ауыл шаруашылығы техникасының түрі: '.$type->name_kz, $cert);
                $cert = str_replace('[Z_VIN]', 'Зауыттық нөмірі: '.$__pv[0].'</p><p>Қозғалтқыш нөмірі: '.$__pv[1], $cert);
                $cert = str_replace('[Z_VOLUME]','Қозғалтқыш қуаты (а. к.): '.$car->volume, $cert);

                // если это Легковой автомобиль или автобус
            } else {
                $cert = str_replace('[TOP_LEFT]', '№0'.str_pad($car->id, 8, 0, STR_PAD_LEFT).' - автокөлік құралдары', $cert);
                $cert = str_replace('[Z_CAT]', 'Автокөлік құралының санаты: '.$lc->_($m->name), $cert);
                $cert = str_replace('[Z_YEAR]', 'Шығарылған жылы: '.$car->year, $cert);
                $cert = str_replace('[DT_MANUFACTURE]', 'Өндіріс күні / импорттау күні: '.date("d-m-Y", $car->date_import), $cert);
                $cert = str_replace('[Z_TYPE]', 'Автокөліктің типі: '.$type->name_kz, $cert);
                $cert = str_replace('[Z_VIN]', 'Көлік құралының сәйкестендіру нөмірі (VIN):  '.$__printed_vin, $cert);
                $cert = str_replace('[Z_VOLUME]','Қозғалтқыштың жұмыс көлемі (V см3)/Көлік құралының техникалық қолжетімді максималды массасы (кг): '.$car->volume, $cert);
            }

            if($car->electric_car == 1){ $is_electric_car = '(Электр қозғалтқышы бар көлік құралы)'; }

            $cert = str_replace('[Z_SUM]', 'Кәдеге жарату төлемінің сомасы: '.$c_cost.$is_electric_car, $cert);
            $cert = str_replace('[Z_DOC]', 'Кәдеге жарату төлемін енгізу туралы өтінімнің күні мен нөмірі:  №'.$t->profile_id.' берілген күні '.date('d.m.Y', $t->date).' жыл.', $cert);

            $_s_text = 'Кәдеге жарату төлемін енгізу туралы сертификатты берген ұйымның атауы: ';
            if($_s['sign'] != 'FAILED') {
                $_s_text .= '"Жасыл даму" акционерлік қоғамы , БИН/ИИН  '.$_s['bin'].'.';
            }

            $_s_text = '<strong>'.$_s_text.'</strong>';

            $cert = str_replace('[Z_ROP]', $_s_text, $cert);
            $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
            $cert = str_replace('[Z_QR]', '<img src="'.APP_PATH.'/storage/temp/'.$car->id.'.png" width="125">', $cert);
            $cert = str_replace('[Z_LOGO]', '<img src="'.APP_PATH.'/public/v2/assets/img/logo2x_black.png" width="150">', $cert);

            file_put_contents($certificate_tmp, $cert);
            (new PdfService())->generate($certificate_tmp, $to_download);

            return $to_download;
        }
    }
}
