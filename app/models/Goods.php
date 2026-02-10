<?php

use App\Services\Cms\CmsService;
use App\Services\Pdf\PdfService;
use Phalcon\Mvc\Model;
use PHPQRCode\QRcode;

class Goods extends Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $profile_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ref_tn;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ref_country;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $date_import;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $weight;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $goods_weight;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $price;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $amount;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $goods_type;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $up_type;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $up_tn;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $date_report;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $basis;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $basis_date;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ref_tn_add;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $package_weight;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $package_cost;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $calculate_method;

    /**
     *
     * @var string
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $created;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $updated;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $goods_cost;


    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("goods");

        $this->belongsTo("ref_tn", "RefTnCode", "id", [
            'alias' => 'ref_tn_code'
        ]);

        $this->belongsTo(
            'ref_country',
            RefCountry::class,
            'id',
            [
                'alias' => 'country',
                'reusable' => true,
            ]
        );
    }

    public static function calculateAmount($good_weight, $tn): array
    {
        $tn = json_decode($tn);
        $v = ($tn->price7 != null) ? $tn->price7 : 0;
        $now = time();

        if ($now >= strtotime(START_2026)) {
            $current_mrp = MRP_2026;
        } else {
            $current_mrp = MRP_2025;
        }

        if($good_weight <= 0){
            return ["sum" => 0, "price" => $v, "mrp" => $current_mrp];
        }
        $sum = round(($good_weight * $current_mrp * $v) / 1000, 2);
        return ["sum" => $sum, "price" => $v, "mrp" => $current_mrp];
    }

    public static function calculateAmountByDate($good_date, $good_weight, $tn): array
    {
        $tn = json_decode($tn);
        //получить мрп по дате
        $mrp = getMrpByDate($good_date);
        $sum = 0;
        $price = 0;
        $msg = '';
        //получить ключ коэффициента по дате
        $tn_price_key = getTnPriceKeyByDate($good_date);

        // если нужно считать по старым ставкам
        if (strtotime($good_date) < ROP_NEW_GD_DATE) {
            $price = (float)$tn->price1;
            $mrp = 0;
            $sum = round((float)$good_weight * $price, 2);
            return ["sum" => $sum, "price" => $price, "mrp" => $mrp, "msg" => $msg];
        }

        // расчет только по запросу от ДРПУП
//        if(strtotime($good_date) >= 1453826400 && strtotime($good_date) <= 1485103200){
//            $price = $tn->price1_old;
//            $mrp = 0;
//            $sum = round($good_weight * $price, 2);
//            return ["sum" => $sum, "price" => $price, "mrp" => $mrp, "msg" => $msg];
//        }
//
//        if(strtotime($good_date) >= 1485103200 && strtotime($good_date) <= 1549838400){
//            $price = $tn->price1_old;
//            $mrp = 0;
//            $sum = round($good_weight * $price, 2);
//            return ["sum" => $sum, "price" => $price, "mrp" => $mrp, "msg" => $msg];
//        }

        if (!$tn_price_key) {
            $msg = 'Ошибка: за указанную дату не установлен коэффициент!';
        } else {
            $prices = [
                'price1' => $tn->price1,
                'price2' => $tn->price2,
                'price3' => $tn->price3,
                'price4' => $tn->price4,
                'price5' => $tn->price5,
                'price6' => $tn->price6,
                'price7' => $tn->price7,
            ];

            $price = (float)$prices[$tn_price_key] ?? null;

            //формула расчета
            $sum = round(((float)$good_weight * $mrp * $price) / 1000, 2);
        }

        return ["sum" => $sum, "price" => $price, "mrp" => $mrp, "msg" => $msg];
    }

    public static function genSvupKz($t_id = 0): string
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

        $path = APP_PATH . '/private/certificates_zd/';
        $to_download = $path . 'goods_' . $t_id . '_kz.pdf';
        $certificate_template = APP_PATH . '/app/templates/html/dpp_zd/certificate_goods_kz.html';
        $cancel_sign_horizontal = '<div style="color: red; font-size: 120px; position: fixed; top: 350px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-35deg) ;">CANCELED</div>';
        $t = Transaction::findFirstByProfileId($t_id);
        $cmsService = new CmsService();

        if ($t->approve != 'GLOBAL') {
            return "Не выдан сертификат !";
        }

        if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
            return "Заявка не одобрена !";
        }

        $p = Profile::findFirstById($t_id);

        if ($p->agent_iin != '') {
            $ac_name = $p->agent_name . ', БСН/ЖСН ' . $p->agent_iin;
        } else {
            $ac_user = User::findFirstById($p->user_id);
            if ($ac_user != false) {
                if ($ac_user->user_type_id == 1) {
                    $ac_name = $ac_user->fio . ', БСН/ЖСН ' . $ac_user->idnum;
                } else {
                    $ac_name = $ac_user->org_name . ', БСН/ЖСН ' . $ac_user->idnum;
                }
            }
        }

        $ac_to = '<br /><br /><strong>Өндіруші / импорттаушы: ' . $ac_name . '</strong>';

        $goods = self::find(array(
            'profile_id = :pid:',
            'bind' => array(
                'pid' => $t_id,
            ),
            "order" => "id DESC"
        ));

        $cancel_profile = self::count([
            "conditions" => "profile_id = :pid: and status = :status:",
            "bind" => [
                "pid" => $t_id,
                "status" => "CANCELLED"
            ]
        ]);

        $edited = CorrectionLogs::count([
            "conditions" => "profile_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $t_id,
                "type" => "GOODS",
            ]
        ]);

        if ($cancel_profile < 1 && $edited < 1 && is_file($to_download)) {
            return $to_download;
        } else {

            $g_count = 0;
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

            $content_qr = $t_id . '_' . date('d.m.Y', $t->date) . '_' . $p_weight_first . '_' . $tn->code . '_' . $p_amount_first . '_' . $t->amount;
            $content_base64 = base64_encode($content_qr);

            $_sign = $cmsService->signHash($content_base64);
            $_s = $_sign['data'];

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
                QRcode::png($l, APP_PATH . '/storage/temp/' . $t_id . '_qr_' . $i . '.png', 'H', 5, 0);
                $__qr_sign_line .= '<img src="' . APP_PATH . '/storage/temp/' . $t_id . '_qr_' . $i . '.png" width="116">&nbsp;';
            }

            QRcode::png($__qr, APP_PATH . '/storage/temp/' . $p_id_first . '.png', 'H', 3, 0);

            $cert = join('', file($certificate_template));

            $certificate_tmp = APP_PATH . '/storage/temp/goods_' . $p_id_first . '_kz.html';

            $cert = str_replace('[Z_TITLE]', 'Кәдеге жарату төлемін енгізу туралы сертификат', $cert);
            $cert = str_replace('[Z_NUM]', '№1' . str_pad($p_id_first, 8, 0, STR_PAD_LEFT), $cert);

            if ($cancel_profile) {
                $cert = str_replace('[CANCELLED]', $cancel_sign_horizontal, $cert);
            } else {
                $cert = str_replace('[CANCELLED]', '', $cert);
            }

            $dt_for_use = $t->date;
            if ($t->dt_approve > 0) {
                $dt_for_use = $t->dt_approve;
            }

            $cert = str_replace('[Z_DATE]', 'берілген күні ' . date('d.m.Y', $dt_for_use) . ' жыл.', $cert);
            $cert = str_replace('[Z_PRE]', 'Өндірушілердің (импорттаушылардың) кеңейтілген міндеттемелерін орындау 
                    мақсатында кәдеге жарату төлемінің енгізілгенін растайтын құжат, атап айтқанда: ' . $ac_to, $cert);

            $cc = 1;
            $z_count = 0;
            $tr = '';
            $gid_list = [];

            foreach ($goods as $c_k => $c) {
                $gid_list[] = $c->id;

                if ($c->status == 'DELETED') continue;
                if ($c->goods_type == 0) {
                    $tn = RefTnCode::findFirstById($c->ref_tn);

                    $good_tn_add = '';
                    $tn_add = false;
                    if ($c->ref_tn_add) {
                        $tn_add = RefTnCode::findFirstById($c->ref_tn_add);
                        if ($tn_add) {
                            $good_tn_add = ' (қорапталған ' . $tn_add->code . ')';
                        }
                    }

                    $basis_arr = preg_split('/[\s,]+/', $c->basis);
                    $basis_str = NULL;

                    foreach ($basis_arr as $val) {
                        $basis_str .= $val . '<br>';
                    }

                    $tr .= '<tr>
                               <td>' . $cc . '</td>
                               <td>' . $tn->code . $good_tn_add . '</td>
                               <td>' . date("d.m.Y", $c->date_import) . '</td>
                               <td style="overflow-wrap: anywhere; word-break: break-word; white-space: pre-wrap;">' . $basis_str . '</td>
                               <td>' . date("d.m.Y", $c->basis_date) . '</td>
                               <td>' . $c->weight . '</td>
                               <td>' . __money(round($c->amount - $c->package_cost, 2)) . '</td>
                               <td>' . $tn_add->code . '</td>
                               <td>' . $c->package_weight . '</td>
                               <td>' . __money($c->package_cost) . '</td>
                         </tr>';
                    $cc++;
                }
                $z_count++;
            }

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
            $page_num = 0;

            if (!empty($edited_good)) {
                $log_gid_list = '';
                foreach ($edited_good as $key => $log) {
                    $page_num++;

                    $log_gid_list .= $log->id . ', ';
                    $html .= '<div class="page">
                                    <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                                        <h1>Қосымша ' . $page_num . '</h1>
                                        <br><br><b>Өзгерістер енгізу жөніндегі өтінім № ' . $log->id . ' күні/айы/жылы, уақыты ' . date(" H:i d-m-Y ", $log->dt) . '.</b><br><br><table>';

                    if ($log->action == "CREATED" || $log->meta_before == "_") {
                        foreach (json_decode($log->meta_after) as $l_after) {

                            $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                            $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                            // товар в упаковке
                            $after_tn_add = '';
                            $calculate_method_after = $l_after->calculate_method ? $lc->_('calculate-method-'.$l_after->calculate_method) : '';

                            if ($l_after->ref_tn_add != 0) {
                                $tn_add_after = RefTnCode::findFirstById($l_after->ref_tn_add);
                                if ($tn_add_after) {
                                    $after_tn_add = ' (Қапталған ' . $tn_add_after->code . ')';
                                }
                            }

                            $html .= '
                                            <tr><td><b>Өріс атауы</b></td><td><b>Ескі мәні</b></td><td><b>Жаңа мәні</b></td></tr>
                                            <tr><td>Ел</td><td>_</td><td>' . $g_country_after->name . ' </td></tr>
                                            <tr><td>Өнімнің СЭҚ ТН коды</td><td>_</td><td>' . $g_tn_after->code . ' ' . $after_tn_add . ' </td></tr>
                                            <tr><td>Импорт/өндірілген күні</td><td>_</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                            <tr><td>Шот-фактура/ДТ нөмірі</td><td>_</td><td>' . $l_after->basis . ' </td></tr>
                                            <tr><td>СФ/ДТ күні</td><td>_</td><td>' . date("d-m-Y", $l_after->basis_date) . ' </td></tr>
                                            <tr><td>Өнім салмағы, кг.</td><td>_</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                            <tr><td>Кәдеге жарату төлемі, тг.</td><td>_</td><td>' . __money(round($l_after->amount - $l_after->package_cost, 2)) . '</td></tr>
                                            <tr><td>Қаптама салмағы, кг.</td><td>_</td><td>' . number_format($l_after->package_weight, 3) . '</td></tr>
                                            <tr><td>Қаптама төлемі, тг.</td><td>_</td><td>' . __money($l_after->package_cost) . '</td></tr>
                                            <tr><td>Қорытынды сома, тг.</td><td></td><td>' . __money($l_after->amount) . ' тг</td></tr>
                                            <tr><td>Есептеу тәсілі</td><td>_</td><td>' . $calculate_method_after . '</td></tr>';
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
                                $calculate_method_before = $l_before->calculate_method ? $lc->_('calculate-method-'.$l_before->calculate_method) : '';
                                $calculate_method_after = $l_after->calculate_method ? $lc->_('calculate-method-'.$l_after->calculate_method) : '';

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
                                                <tr><td><b>Өріс атауы</b></td><td><b>Ескі мәні</b></td><td><b>Жаңа мәні</b></td></tr>
                                                <tr><td>Ел</td><td>' . $g_country_before->name . ' </td><td>' . $g_country_after->name . ' </td></tr>
                                                <tr><td>Өнімнің СЭҚ ТН коды</td><td>' . $g_tn_before->code . ' ' . $before_tn_add . ' </td><td>' . $g_tn_after->code . ' ' . $after_tn_add . '</td></tr>
                                                <tr><td>Импорт/өндірілген күні</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                                <tr><td >Шот-фактура/ДТ нөмірі</td><td>' . $l_before->basis . ' </td><td>' . $l_after->basis . ' </td></tr>
                                                <tr><td>СФ/ДТ күні</td><td>' . date("d-m-Y", $l_before->basis_date) . ' </td><td>' . date("d-m-Y", $l_after->basis_date) . ' </td></tr>
                                                <tr><td>Өнім салмағы, кг.</td><td>' . number_format($l_before->weight, 3) . '</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                                <tr><td>Кәдеге жарату төлемі, тг.</td><td>' . __money(round($l_before->amount - $l_before->package_cost, 2)) . ' тг</td><td>' . __money(round($l_after->amount - $l_after->package_cost, 2)) . ' тг</td></tr>
                                                <tr><td>Қаптама салмағы, кг.</td><td>' . number_format($l_before->package_weight, 3) . '</td><td>' . number_format($l_after->package_weight, 3) . '</td></tr>
                                                <tr><td>Қаптама төлемі, тг.</td><td>' . __money($l_before->package_cost) . ' тг</td><td>' . __money($l_after->package_cost) . ' тг</td></tr>
                                                <tr><td>Қорытынды сома, тг.</td><td>' . __money($l_before->amount) . ' тг</td><td>' . __money($l_after->amount) . ' тг</td></tr>
                                                <tr><td>Есептеу тәсілі</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>';
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

                    $html .= '<tr><td colspan="3"><br>' . $_qr_edited_good_user . ' <br><br>
                    <b>ДРПУП БАСШЫСЫ:</b> «ЖАСЫЛ ДАМУ» АКЦИОНЕРЛІК ҚОҒАМЫ (БИН 040340008429)</td></tr>';
                    $html .= '</table></div></div>';
                }

            } else {
                $cert = str_replace('[LOGS]', '', $cert);
                $cert = str_replace('[LOG_INFO]', '', $cert);
            }

            $cert = str_replace('[LOGS]', $html, $cert);
            $cert = str_replace('[LOG_INFO]', '', $cert);

            $cert = str_replace('[Z_TABLE]', $tr, $cert);
            $cert = str_replace('[Z_COUNT]', $z_count, $cert);

            $cert = str_replace('[Z_SUM]', 'Кәдеге жарату төлемінің жалпы сомасы: ' . number_format($t->amount, 2, ',', ' ') . ' теңге', $cert);
            $cert = str_replace('[Z_DOC]', 'Кәдеге жарату төлемін енгізу туралы өтінімнің күні мен нөмірі: №' . $t->profile_id . ' берілген күні ' . date('d.m.Y', $t->date) . ' жыл.', $cert);

            $_s_text = 'Кәдеге жарату төлемін енгізу туралы сертификатты берген ұйымның атауы: ';
            if ($_s['sign'] != 'FAILED') {
                $_s_text .= '"Жасыл даму" акционерлік қоғамы,  БИН/ИИН  ' . $_s['bin'] . '.';
            }

            $_s_text = '<st>' . $_s_text . '</st>';

            $cert = str_replace('[Z_ROP]', $_s_text, $cert);

            $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
            $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $p_id_first . '.png" width="125">', $cert);

            file_put_contents($certificate_tmp, $cert);
            (new PdfService())->generate($certificate_tmp, $to_download, 'landscape');

            return $to_download;
        }
    }


    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }
}
