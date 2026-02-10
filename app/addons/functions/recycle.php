<?php
ini_set('max_execution_time', 320);
set_time_limit(320);

use App\Services\Cms\CmsService;
use App\Services\Pdf\PdfService;
use Phalcon\Di\Di;
use PHPQRCode\QRcode;

/*******************************************************************************
 * Назначение: сервисные функции
 *******************************************************************************/

/**
 * GET data from https://www.nationalbank.kz/ru/exchangerates/ezhednevnye-oficialnye-rynochnye-kursy-valyut
 * FOR EXACTLY NOW
 * @return array countains
 * HTML as table tag and records |||
 * ARRAY with many rows each as ['title','shortTitle','from','to']
 */

/**
 * Глобальная функция для хэширования данных
 *
 * @param string $data Данные для хэширования
 * @return string Сгенерированный хэш
 */

function genAppHash(?string $data): string
{
    $data = $data ?? '';
    return hash('sha256', $data . hash('sha256', getenv('NEW_SALT')));
}

function getActualCurrencies(): array
{
    $url = getenv('EXCHANGE_RATES_URL');

    // Инициализация cURL
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); // Тайм-аут запроса
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true); // Проверка SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // Проверка хоста SSL

    $output = curl_exec($curl);

    // Проверка ошибок cURL
    if (curl_errno($curl)) {
        curl_close($curl);
        return [
            'html' => '',
            'array' => [],
            'error' => 'Ошибка cURL: ' . curl_error($curl),
        ];
    }

    curl_close($curl);

    // Проверка пустого ответа
    if (empty($output)) {
        return [
            'html' => '',
            'array' => [],
            'error' => 'Пустой ответ от сервера.',
        ];
    }

    // Инициализация DOMDocument
    $DOM = new DOMDocument;
    libxml_use_internal_errors(true);

    if (!$DOM->loadHTML($output, LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        libxml_clear_errors();
        return [
            'html' => '',
            'array' => [],
            'error' => 'Ошибка загрузки HTML.',
        ];
    }

    $items = $DOM->getElementsByTagName('tr');
    $resultArray = [];
    $table = "<table>";

    foreach ($items as $item) {
        $td = $item->getElementsByTagName("td");

        // Валидация количества ячеек
        if ($td->length > 4) {
            // Очистка данных
            $titleStr = explode(' ', htmlspecialchars(trim($td[1]->nodeValue ?? ''), ENT_QUOTES), 2);
            $shortTitleStr = explode('/', htmlspecialchars(trim($td[2]->nodeValue ?? ''), ENT_QUOTES), 2);

            $resultArray[] = [
                'title' => $titleStr[1] ?? '',
                'shortTitle' => $shortTitleStr[0] ?? '',
                'from' => $titleStr[0] ?? '',
                'to' => htmlspecialchars(trim($td[3]->nodeValue ?? ''), ENT_QUOTES),
            ];

            $table .= "<tr>";
            $table .= "<td>" . htmlspecialchars($td[1]->nodeValue ?? '', ENT_QUOTES) . "</td>";
            $table .= "<td>" . htmlspecialchars($td[2]->nodeValue ?? '', ENT_QUOTES) . "</td>";
            $table .= "<td>" . htmlspecialchars($td[3]->nodeValue ?? '', ENT_QUOTES) . "</td>";
            $table .= "</tr>";
        }
    }
    $table .= "</table>";

    return [
        'html' => $table,
        'array' => $resultArray,
        'error' => null, // Нет ошибок
    ];
}


function currencyToTenge($currencyType, $currencyCount, $date = null)
{
    $ok = true;

    $curr = getActualCurrencyByType($currencyType, $date);

    $sum = ((float)$curr['to'] / (float)$curr['from']) * $currencyCount;
    return [
        'sum' => $sum,
        'from' => $curr['from'],
        'to' => $curr['to'],
        'dateActuality' => $curr['dateActuality'],
    ];
}

function actualizeCurrencies()
{

    $requestCur = CurrencyRequest::findFirst(['order' => 'id DESC']);
    $actualCurrencies = getActualCurrencies();
    $html = $actualCurrencies['html'];
    $currencyAr = $actualCurrencies['array'];

    if ($requestCur && $requestCur->html_result == $html) {
        return true;
    }

    if ($actualCurrencies && isset($currencyAr)) {
        $reqCur = new CurrencyRequest();
        $reqCur->html_result = $html;
        $reqCur->created_at = time();
        if ($reqCur->save()) {
            echo 'saved Request | ';
            foreach ($currencyAr as $cur) {
                $eachCur = new CurrencyEach();
                $eachCur->title = $cur['shortTitle'];
                $eachCur->from = $cur['from'];
                $eachCur->to = $cur['to'];
                $eachCur->request_id = $reqCur->id;

                if ($eachCur->save()) {
                    echo "saved $eachCur->title | ";
                } else {
                    echo "unable save " . $cur['shortTitle'] . " | ";
                    $allEach = CurrencyEach::find(["condition" => " request_id = $reqCur->id"]);
                    foreach ($allEach as $a) {
                        $a->delete();
                    }
                }
            }
        }
    }

    unset($requestCur);
    unset($actualCurrencies);
    unset($html);
    unset($currencyAr);
    unset($reqCur);
    unset($eachCur);

}

function getActualCurrencyByType($currencyType, $date = null): array
{
    $request = false;
    if ($date) {
        $start = strtotime($date . ' 00:00');
        $end = strtotime($date . ' 23:59');
        $request = CurrencyRequest::findFirst([
            "conditions" => "created_at > ?1 AND created_at < ?2",
            "bind" => [
                1 => $start,
                2 => $end,
            ]
        ]);
        $currency = CurrencyEach::findFirst([
            "conditions" => "title = ?1 AND request_id = ?2",
            "order" => "id DESC",
            "bind" => [
                1 => $currencyType,
                2 => $request->id
            ]
        ]);
    } else {
        $currency = CurrencyEach::findFirst([
            "conditions" => "title = ?1",
            "order" => "id DESC",
            "bind" => [
                1 => $currencyType
            ]
        ]);
        if ($currency) {
            $request = CurrencyRequest::findFirst($currency->request_id);
        }
    }

    if ($request) {
        return [
            'from' => $currency->from,
            'to' => $currency->to,
            'dateActuality' => date('d.m.Y H:i', $request->created_at)
        ];
    }

    return [
        'from' => '',
        'to' => '',
        'dateActuality' => ''
    ];
}

/**
 * Функция выкачивания файла из приватной зоны.
 * @param string $file путь к файлу
 * @return void
 */
function __downloadFile($file, $rname = null, $mode = 'download')
{
    // --- 1. ВВЕДЕНИЕ МЕР БЕЗОПАСНОСТИ ПРОТИВ LFI/PATH TRAVERSAL ---

    // Определяем базовый безопасный каталог. Замените его на ваш реальный путь.
    $safe_base_dir = APP_PATH;

    // Нормализуем путь к файлу
    $normalized_file = realpath($file);

    // Если реальный путь не может быть определен или файл не существует
    if ($normalized_file === false) {
        http_response_code(404);
        echo 'File not found (Normalization error)';
        return;
    }

    // Проверка: гарантируем, что файл находится внутри безопасного каталога
    if (strpos($normalized_file, $safe_base_dir) !== 0) {
        http_response_code(403); // Forbidden
        echo 'Access denied: Path Traversal detected.';
        return;
    }

    // Используем проверенный и нормализованный путь
    $file = $normalized_file;

    // --- КОНЕЦ МЕР БЕЗОПАСНОСТИ ---

    // Старая проверка (теперь избыточна, но оставим для надежности)
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        echo 'File not found';
        return;
    }

    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!$rname) {
        $rname = basename($file);
    }

    $ext = strtolower(pathinfo($rname, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8', 'json' => 'application/json; charset=utf-8',
        'zip' => 'application/zip'
    ];
    if (isset($map[$ext])) $mime = $map[$ext];

    $disp = ($mode === 'view') ? 'inline' : 'attachment';
    $safe = str_replace('"', "'", $rname);
    $dispValue = $disp . '; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($rname);

    clearstatcache(true, $file);
    $size = @filesize($file);
    @ini_set('zlib.output_compression', '0');

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: none');
    header('Expires: 0');
    header('Cache-Control: private, must-revalidate');
    header('Pragma: public');
    header('Content-Disposition: ' . $dispValue);
    if (!ini_get('zlib.output_compression') && is_numeric($size)) {
        header('Content-Length: ' . $size);
    }

    $fp = @fopen($file, 'rb');
    if ($fp === false) {
        http_response_code(500);
        echo 'Cannot open file';
        return;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $chunk = 8192;
    while (!feof($fp)) {
        $buf = fread($fp, $chunk);
        if ($buf === false) break;
        echo $buf;
        flush();
    }
    fclose($fp);
    exit;
}

/**
 * Сгенерировать сертификаты, выдать запрашиваемый.
 * @param integer $t_id номер транзакции (заявки)
 * @param integer $c_id номер транспортного средства
 * @return void
 */
function __genDPP($t_id, $c_id, $download = true, $isZip = false, $lang = null): void
{
    $cmsService = new CmsService;

    global $messages;
    $lang = $lang ?? 'ru';
    $t = Transaction::findFirstByProfileId($t_id);

    $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 550px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';

    if ($t != false && $t->approve == 'GLOBAL') {
        $p = Profile::findFirstById($t->profile_id);
        if ($p != false) {
            $ac_info = '';
            $ac_to = '';
            if ($t->ac_approve == 'SIGNED') {
                $ac_info = ' и подписан электронно-цифровой подписью, выданной Национальным удостоверяющим центром РК представителю ТОО «Оператор РОП»';

                // достаем инфо про заказчика
                // если это агентская заявка
                if ($p->agent_iin != '') {
                    $ac_name = $p->agent_name . ', ИИН / БИН ' . $p->agent_iin;
                } else {
                    $ac_user = User::findFirstById($p->user_id);
                    if ($ac_user != false) {
                        if ($ac_user->user_type_id == 1) {
                            $ac_name = $ac_user->fio . ', ИИН ' . $ac_user->idnum;
                        } else {
                            $ac_name = $ac_user->org_name . ', БИН ' . $ac_user->idnum;
                        }
                    }
                }

                $ac_to = '<br /><br /><strong>Заказчик: ' . $ac_name . '</strong>';
            }

            if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
                echo 'Ошибка #20200614.';
                die();
            }

            if ($p->type == 'CAR') {

                // пути для сохранения и скачивания
                $to_download = '';
                $path = APP_PATH . '/private/certificates/';

                if (!$isZip) {
                    $car = Car::findFirstById($c_id);
                    $to_download = $path . $car->vin . '.pdf';
                    __genPDFForCarROP($t_id, $c_id, $lang, $ac_info, $ac_to);

                    if (is_file($to_download) && $download) {
                        __downloadFile($to_download);
                    }
                } else {
                    $finalZip = $path . 'svup_' . $t->profile_id . '.zip';
                    $tmpZip = $path . 'svup_' . $t->profile_id . '_in_progress.zip';
                    if (is_file($finalZip)) @unlink($finalZip);
                    if (is_file($tmpZip)) @unlink($tmpZip);

                    $cars = Car::find([
                        'profile_id = :pid:',
                        'bind' => ['pid' => $p->id],
                    ]);

                    $zip = new ZipArchive();
                    $res = $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    if ($res !== true) {
                        throw new \RuntimeException("Не удалось открыть ZIP: $tmpZip (code=$res)");
                    }

                    foreach ($cars as $c) {
                        $pdf = $path . $c->vin . '.pdf';

                        __genPDFForCarROP($t_id, $c->id, $lang, $ac_info, $ac_to);

                        if (is_file($pdf)) {
                            // без пути внутри архива (аналог REMOVE_ALL_PATH)
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
                $goods = Goods::find(array(
                    'profile_id = :pid:',
                    'bind' => array(
                        'pid' => $p->id,
                    ),
                    "order" => "id DESC"
                ));

                $to_download = '';
                $path = APP_PATH . '/private/certificates/';

                $cancel_profile = Goods::findFirst([
                    "conditions" => "profile_id = :pid: and status = :status:",
                    "bind" => [
                        "pid" => $p->id,
                        "status" => "CANCELLED"
                    ]
                ]);


                // гененрируем сертификат
                $certificate_template = APP_PATH . '/app/templates/html/dpp/certificate_goods.html';

                if (is_file($path . 'svup_' . $t->profile_id . '.zip')) {
                    @unlink($path . 'svup_' . $t->profile_id . '.zip');
                }
                $p_weight_first = '';
                $p_amount_first = '';
                $archive = new PclZip($path . 'svup_' . $t->profile_id . '.zip');
                $p_id_first = null;
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

                $content_qr = $t->profile_id . '_' . date('d.m.Y', $t->date) . '_' . $p_weight_first . '_' . ($tn ? $tn->code : '') . '_' . $p_amount_first . '_' . $t->amount;
                $content_base64 = base64_encode($content_qr);

                $_sign = $cmsService->signHash($content_base64);

                $_s = $_sign['data'];

                // поехали
                $__qr = '';

                if ($_s) {
                    if ($_s['sign'] != 'FAILED') {
                        $content_sign = base64_encode($_s['sign']);
                        $__qr = $content_qr . ':' . mb_strtoupper(genAppHash($content_sign));
                    } else {
                        $content_sign = base64_encode($_s['sign']);
                    }

                    $__qr_sign_line = getQrImagesHtml($content_sign);
                }


                QRcode::png($__qr, APP_PATH . '/storage/temp/' . $p_id_first . '.png', 'H', 3, 0);

                $cert = join('', file($certificate_template));

                $certificate_tmp = APP_PATH . '/storage/temp/goods_' . $p_id_first . '.html';

                $cert = str_replace('[Z_TITLE]', 'Документ о полноте платы', $cert);
                $cert = str_replace('[Z_SUBTITLE]', 'за организацию сбора, транспортировки, переработки, обезвреживания,<br />использования и (или) утилизации отходов, образующихся после утраты потребительских свойств продукции (товаров), на которые распространяются расширенные обязательства производителей (импортеров), и их упаковки', $cert);
                $cert = str_replace('[Z_NUM]', '№1' . str_pad($p_id_first, 8, 0, STR_PAD_LEFT), $cert);

                // пометка об отмене
                if ($cancel_profile) {
                    $cert = str_replace('[CANCELLED]', $cancel_sign, $cert);
                } else {
                    $cert = str_replace('[CANCELLED]', '', $cert);
                }

                // если оба условия не сработали выше, зачищаем
                $cert = str_replace('[CANCELLED]', '', $cert);

                // дата выдачи, если есть
                $dt_for_use = $t->date;
                if ($t->dt_approve > 0) {
                    $dt_for_use = $t->dt_approve;
                }

                $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $dt_for_use) . ' г.', $cert);

                $cert = str_replace('[Z_PRE]', 'Настоящий документ подтверждает оплату платы в полном объеме, в целях исполнения расширенных обязательств производителями (импортерами), а именно:' . $ac_to, $cert);

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

                        
                            $tr .= '<tr><td>' . $cc . '</td><td>' . $tn->code . $good_tn_add . '</td><td>' . date("d.m.Y", $c->date_import) . '</td><td>' . $c->weight . '</td><td>' . $basis_str . '</td><td>' . $c->amount . '</td></tr>';
                        
                        $cc++;
                    }

                    $z_count++;
                }

                $page_num = 0;

                $edited_good = CorrectionLogs::find([
                    "conditions" => "object_id IN ({gid:array}) and type = :type:",
                    "bind" => [
                        "gid" => $gid_list,
                        "type" => "GOODS"
                    ]
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
                                    <div style="page-break-before: always; padding: 30px 0px 0px 0px;font-size: 12px;" >
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
                                                <tr><td>Код ТНВЭД</td><td>_</td><td>' . ($g_tn_after ? $g_tn_after->code : '') . ' ' . $after_tn_add . ' </td></tr>
                                                <tr><td>Масса товара или упаковки (кг)</td><td>_</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                                <tr><td>Сумма, тенге</td><td>_</td><td>' . $l_after->amount . ' тг</td></tr>
                                                <tr><td>Номер счет-фактуры или ГТД</td><td>_</td><td>' . $l_after->basis . ' </td></tr>
                                                <tr><td>Дата импорта или реализации</td><td>_</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
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
                                                    <tr><td>Код ТНВЭД</td><td>' . ($g_tn_before ? $g_tn_before->code : '') . ' ' . $before_tn_add . ' </td><td>' . ($g_tn_after ? $g_tn_after->code : '') . ' ' . $after_tn_add . '</td></tr>
                                                    <tr><td>Масса товара или упаковки (кг)</td><td>' . number_format($l_before->weight, 3) . '</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                                    <tr><td>Сумма, тенге</td><td>' . $l_before->amount . ' тг</td><td>' . $l_after->amount . ' тг</td></tr>
                                                    <tr><td>Номер счет-фактуры или ГТД</td><td>' . $l_before->basis . ' </td><td>' . $l_after->basis . ' </td></tr>
                                                    <tr><td>Дата импорта или реализации</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                                    <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>';
                                }
                            }
                        }

                        $_qr_edited_good_user = '';

                        $log_sign = $log->sign;
                        if ($log_sign != '') {
                            $_qr_edited_good_user = getQrImagesHtml($log_sign);
                        } else {
                            $user_iin_base64 = base64_encode($log->iin);
                            $_qr_edited_good_user = getQrImagesHtml($user_iin_base64);
                        }

                        $html .= '<tr><td colspan="3"><br>' . $_qr_edited_good_user . ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> ТОВАРИЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ "ОПЕРАТОР РОП" (БИН
                        151140025060)</td></tr>';

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

                $cert = str_replace('[Z_SUM]', 'Общая сумма платы: ' . number_format($t->amount, 2, ',', ' ') . ' тенге', $cert);
                $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесение платы: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

                // формируем красивую подпись
                $_s_text = 'Наименование организации, выдавшей документ о полноте платы: ';
                if ($_s['sign'] != 'FAILED') {
                    $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
                }
                // $_s_text = 'Наименование организации, выдавшей документ о полноте платы и данные электронно-цифровой подписи: ';
                // if($_s->sign != 'FAILED') {
                //     $_s_text .= $_s->company.' ('.$_s->bin.'), руководитель отдела регулирования приема утилизационных платежей '.$_s->fio.' ('.$_s->iin.'), период действия: '.$_s->dt;
                // }

                $_s_text = '<st>' . $_s_text . '</st>';

                $cert = str_replace('[Z_ROP]', $_s_text, $cert);

                $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
                $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $p_id_first . '.png" width="125">', $cert);
//                $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/old_logo2x_black.png" width="150">', $cert);

                $cert = str_replace('[Z_S1]', 'Настоящий документ сформирован электронным способом' . $ac_info . '. Данные указанные в настоящем документе, включая сумму оплаты, сформированы на основании сведений, которые были предоставлены плательщиком (производителем, импортером). Ответственность за полноту и достоверность указанных в настоящем документе сведений, а также за какие-либо возможные последствия и/или ущерб, причиненный плательщику (производителю, импортеру) и/или третьим лицам, государству, государственным органам и организациям в связи с недостоверностью и/или не полнотой предоставленных сведений полностью несет плательщик (производитель, импортер). ТОО «Оператор РОП» полностью освобождается от такой ответственности. Данные и подлинность настоящего документа о полноте оплаты можно проверить на официальном сайте ТОО «Оператор РОП».', $cert);
                $cert = str_replace('[Z_S2]', 'Форма Документа о полноте оплаты разработана и утверждена на основании Правил реализации расширенных обязательств производителей, импортеров, утвержденных постановлением Правительства Республики Казахстан №28 от «27» января 2016 года.', $cert);

                file_put_contents($certificate_tmp, $cert);
//                exec('wkhtmltopdf -q --page-size A4 --dpi 300 --disable-smart-shrinking -T 10 -B 10 -L 10 -R 10 '.$certificate_tmp.' '.$path.'goods_'.$t->profile_id.'.pdf');
                (new PdfService())->generate($certificate_tmp, $path . 'goods_' . $t->profile_id . '.pdf');

                $archive->add($path . 'goods_' . $t->profile_id . '.pdf', PCLZIP_OPT_REMOVE_PATH, $path);

                // запрашивали сертификат??? готовим ссылку
                $to_download = $path . 'goods_' . $t->profile_id . '.pdf';

                if ($download) {
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
                $path = APP_PATH . '/private/certificates/';

                $cancel_profile = Kpp::findFirst([
                    "conditions" => "profile_id = :pid: and status = :status:",
                    "bind" => [
                        "pid" => $p->id,
                        "status" => "CANCELLED"
                    ]
                ]);

                $certificate_template = APP_PATH . '/app/templates/html/dpp/certificate_kpp.html';

                if (is_file($path . 'kpp_svup_' . $t->profile_id . '.zip')) {
                    unlink($path . 'kpp_svup_' . $t->profile_id . '.zip');
                }

                $archive = new PclZip($path . 'kpp_svup_' . $t->profile_id . '.zip');
                $p_weight_first = '';
                $p_amount_first = '';
                $p_id_first = '';
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

                $content_qr = $t->profile_id . '_' . date('d.m.Y', $t->date) . '_' . $p_weight_first . '_' . ($tn ? $tn->code : '') . '_' . $p_amount_first . '_' . $t->amount;
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

                $__qr_sign_line = getQrImagesHtml($content_sign);

                QRcode::png($__qr, APP_PATH . '/storage/temp/' . $p_id_first . '.png', 'H', 3, 0);

                $cert = join('', file($certificate_template));
                $certificate_tmp = APP_PATH . '/storage/temp/kpp_' . $p_id_first . '.html';
                $cert = str_replace('[Z_TITLE]', 'Документ о полноте платы', $cert);
                $cert = str_replace('[Z_SUBTITLE]', 'за организацию сбора, транспортировки, переработки, обезвреживания,<br />использования и (или) утилизации отходов, образующихся после утраты потребительских свойств продукции (товаров), на которые распространяются расширенные обязательства производителей (импортеров), и их упаковки', $cert);
                $cert = str_replace('[Z_NUM]', '№1' . str_pad($p_id_first, 8, 0, STR_PAD_LEFT), $cert);

                // пометка об отмене
                if ($cancel_profile) {
                    $cert = str_replace('[CANCELLED]', $cancel_sign, $cert);
                } else {
                    $cert = str_replace('[CANCELLED]', '', $cert);
                }

                // дата выдачи, если есть
                $dt_for_use = $t->date;
                if ($t->dt_approve > 0) {
                    $dt_for_use = $t->dt_approve;
                }

                $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $dt_for_use) . ' г.', $cert);
                $cert = str_replace('[Z_PRE]', 'Настоящий документ подтверждает оплату платы в полном объеме, в целях исполнения расширенных обязательств производителями (импортерами), а именно:' . $ac_to, $cert);

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

                    $tr .= '<tr>';
                    $tr .= '<td>' . $cc . '.</td><td>' . $tn->code . '</td><td>' . $v->weight . '</td>';
                    $currency_sum = $v->currency_type != 'KZT' ? ($v->invoice_sum_currency . ' ' . $v->currency_type) : ('- / ' . $v->currency_type);
                    $tr .= '<td>' . $v->basis . '</td><td>' . $v->invoice_sum . '</td><td>' . $currency_sum . '</td>';
                    $tr .= '<td>' . $v->amount . '</td>';
                    $tr .= '<td>' . date('d.m.Y', $v->date_import) . '</td>';
                    $tr .= '<tr>';
                    $cc++;
                    $z_count++;
                }

                $page_num = 0;

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
                                    <div style="page-break-before: always; padding: 30px 0px 0px 0px;font-size: 12px;" >
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
                        $log_sign = $log->sign;
                        if ($log_sign != '') {
                            $__qr_sign_line = getQrImagesHtml($log_sign);
                        } else {
                            $user_iin_base64 = base64_encode($log->iin);
                            $_qr_edited_good_user = getQrImagesHtml($user_iin_base64);
                        }

                        $html .= '<tr><td colspan="3"><br>' . $_qr_edited_good_user . ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> ' . ROP . ' (БИН
                        ' . ROP_BIN . ')</td></tr>';

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

                $cert = str_replace('[Z_SUM]', 'Общая сумма платы: ' . number_format($t->amount, 2, ',', ' ') . ' тенге', $cert);
                $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесение платы: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

                // формируем красивую подпись
                $_s_text = 'Наименование организации, выдавшей документ о полноте платы: ';
                if ($_s['sign'] != 'FAILED') {
                    $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
                }

                $_s_text = '<st>' . $_s_text . '</st>';
                $cert = str_replace('[Z_ROP]', $_s_text, $cert);
                $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
                $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $p_id_first . '.png" width="125">', $cert);
//                $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/old_logo2x_black.png" width="150">', $cert);
                $cert = str_replace('[Z_S1]', 'Настоящий документ сформирован электронным способом и подписан электронно-цифровой подписью, выданной Национальным удостоверяющим центром РК представителю ТОО «Оператор РОП», с правом подписи финансовых документов. Данные, указанные в настоящем документе, включая сумму оплаты, сформированы на основании сведений, которые были предоставлены плательщиком (производителем, импортером). Ответственность за полноту и достоверность указанных в настоящем документе сведений, а также за какие-либо возможные последствия и/или ущерб, причиненный плательщику (производителю, импортеру) и/или третьим лицам, государству, государственным органам и организациям в связи с недостоверностью и/или не полнотой предоставленных сведений полностью несет плательщик (производитель, импортер). ТОО «Оператор РОП» полностью освобождается от такой ответственности. Данные и подлинность настоящего Сертификата о внесении утилизационного платежа можно проверить на официальном сайте ТОО «Оператор РОП». ', $cert);
                $cert = str_replace('[Z_S2]', 'Форма Сертификата о внесении утилизационного платежа разработана и утверждена на основании Правил реализации расширенных обязательств производителей, импортеров, утвержденных постановлением Правительства Республики Казахстан №763 от «25» октября 2021 года.', $cert);

                file_put_contents($certificate_tmp, $cert);
//                exec('wkhtmltopdf -q --page-size A4 --dpi 300 --disable-smart-shrinking -T 10 -B 10 -L 10 -R 10 '.$certificate_tmp.' '.$path.'kpp_'.$p->id.'.pdf');
                (new PdfService())->generate($certificate_tmp, $path . 'kpp_' . $p->id . '.pdf');

                $archive->add($path . 'kpp_' . $p->id . '.pdf', PCLZIP_OPT_REMOVE_PATH, $path);

                // запрашивали сертификат??? готовим ссылку
                $to_download = $path . 'kpp_' . $p->id . '.pdf';

                if ($download) {
                    if (!$isZip) {
                        __downloadFile($to_download);
                    }
                }
            }
        }
    }
}

function __genPDFForCarROP($t_id, $c_id, $lang = null, $ac_info = null, $ac_to = null)
{
    $cmsService = new CmsService();
    $cars = Car::findFirstById($c_id);
    $t = Transaction::findFirstById($t_id);

    $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 550px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';

    $di = Di::getDefault();
    $lc = $di->has('translator') ? $di->getShared('translator') : null;

    // пути для сохранения и скачивания
    $to_download = '';
    $path = APP_PATH . '/private/certificates/';

    // гененрируем сертификат
    $certificate_template = APP_PATH . '/app/templates/html/dpp/certificate_car.html';

    $page_num = 0;
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
    if ($cancel_car || $edited_car || !is_file(APP_PATH . '/private/certificates/' . $cars->vin . '.pdf')) {
        $m = RefCarCat::findFirstById($cars->ref_car_cat);
        $vehicle_type = $cars->vehicle_type;
        $type = RefCarType::findFirstById($cars->ref_car_type_id);

        // если это машина с исправленным ВИН-ом
        $trans_vin = '';
        if (is_file(APP_PATH . '/private/trans/' . $cars->vin . '.txt')) {
            $trans_vin = file_get_contents(APP_PATH . '/private/trans/' . $cars->vin . '.txt');
        }

        // строка QR-кода и его подпись
        $content_qr = $t->profile_id . ':' . date('d.m.Y', $t->date) . ':' . mb_strtoupper(str_replace('cat-', '', $m->name)) . ':' . $cars->year . ':' . $cars->vin . ':' . $cars->volume . ':' . $cars->cost;
        $content_base64 = base64_encode($content_qr);

        $_sign = $cmsService->signHash($content_base64);
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
        $__qr_sign_line = getQrImagesHtml($content_sign);
        // формируем изображение
        QRcode::png($__qr, APP_PATH . '/storage/temp/' . $cars->id . '.png', 'H', 4, 0);

        $cert = join('', file($certificate_template));

        $certificate_tmp = APP_PATH . '/storage/temp/certificate_' . $cars->id . '.html';

        $cert = str_replace('[Z_TITLE]', 'Документ о полноте платы', $cert);
        $cert = str_replace('[Z_SUBTITLE]', 'за организацию сбора, транспортировки, переработки, обезвреживания,<br />использования и (или) утилизации отходов, образующихся после утраты потребительских свойств продукции (товаров), на которые распространяются расширенные обязательства производителей (импортеров), и их упаковки', $cert);
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
                            <div style="page-break-before: always; padding: 30px 0px 0px 0px;font-size: 12px;" >
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
                            <tr><td>VIN-код / номер</td><td>' . $l_before->vin . '</td><td>' . $l_after->vin . '</td></tr>
                            <tr><td>Страна производства</td><td>' . $country_before->name . '</td><td>' . $country_after->name . '</td></tr>
                            <tr><td>Сумма, тенге</td><td>' . __money($l_before->cost) . ' тг</td><td>' . __money($l_after->cost) . ' тг</td></tr>
                            <tr><td>Дата импорта</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                            <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>
                            <tr><td>С электродвигателям?</td><td>' . $e_car_before . '</td><td>' . $e_car_after . '</td></tr>';
                    }
                }

                $_qr_edited_user = '';
                $log_sign = $log->sign;
                if ($log_sign != '') {
                    $_qr_edited_user = getQrImagesHtml($log_sign);
                } else {
                    $user_iin_base64 = base64_encode($log->iin);
                    $_qr_edited_user = getQrImagesHtml($user_iin_base64);
                }

                $html .= '<tr><td colspan="3"><br>' . $_qr_edited_user . ' <br><br><b>РУКОВОДИТЕЛЬ ДРПУП:</b> ТОВАРИЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ "ОПЕРАТОР РОП" (БИН
                                    151140025060)</td></tr>';
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

        $cert = str_replace('[Z_PRE]', 'Настоящий документ подтверждает оплату платы в полном объеме, в целях исполнения расширенных обязательств производителями (импортерами), а именно:' . $ac_to, $cert);

        // если есть дополнения к ВИН-у
        $__printed_vin = $cars->vin;
        if ($trans_vin != '') {
            $__printed_vin = $__printed_vin . " ($trans_vin)";
        }

        // если это Грузовой автомобиль
        if ($m->car_type == 2 || $vehicle_type == 'CARGO') {
            $cert = str_replace('[Z_CAT]', 'Категория автотранспортного средства: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_VIN]', 'Vehicle Identity Number (VIN): ' . $__printed_vin, $cert);
            $cert = str_replace('[Z_VOLUME]', 'Технически допустимая максимальная масса ТС (кг): ' . $cars->volume, $cert);
            // уточнение по седельным тягачам от 21.09.2020
            // дата импорта после 24.05.2019 применять признак седельности, а до не применяит
            if ($cars->date_import >= 1558634400) {
                $sed_t = ' (не седельный тягач)';
                if ($cars->ref_st_type == 1) $sed_t = ' (седельный тягач)';
            } else  $sed_t = '';
            $cert = str_replace('[Z_TYPE]', 'Вид автотранспортного средства: ' . $type->name . $sed_t, $cert);

            // если это ССХТ
        } else if ($m->car_type == 5 || $m->car_type == 4) {
            $__printed_vin = str_replace('I-', '—-', $__printed_vin);
            $__printed_vin = str_replace('-B', '-—', $__printed_vin);
            $__pv = preg_split('/[-&]/', $__printed_vin, 2);
            $cert = str_replace('[Z_CAT]', 'Категория самоходной сельскохозяйственной техники: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_TYPE]', 'Вид самоходной сельскохозяйственной техники: ' . $type->name, $cert);
            $cert = str_replace('[Z_VIN]', 'Заводской номер: ' . $__pv[0] . '</p><p>Номер двигателя: ' . $__pv[1], $cert);
            $cert = str_replace('[Z_VOLUME]', 'Мощность двигателя (л.с.): ' . $cars->volume, $cert);

            // если это Легковой автомобиль или автобус
        } else {
            $cert = str_replace('[Z_CAT]', 'Категория автотранспортного средства: ' . $lc->_($m->name), $cert);
            $cert = str_replace('[Z_YEAR]', 'Год производства: ' . $cars->year, $cert);
            $cert = str_replace('[DT_MANUFACTURE]', 'Дата производства / Дата импорта: ' . date("d-m-Y", $cars->date_import), $cert);
            $cert = str_replace('[Z_TYPE]', 'Тип автотранспортного средства: ' . $type->name, $cert);
            $cert = str_replace('[Z_VIN]', 'Vehicle Identity Number (VIN): ' . $__printed_vin, $cert);
            $cert = str_replace('[Z_VOLUME]', 'Рабочий объем двигателя (V/см3): ' . $cars->volume, $cert);
        }
        if ($cars->electric_car == 1) {
            $is_electric_car = '(Транспортное средство с электродвигателем)';
        }

        $cert = str_replace('[Z_SUM]', 'Сумма платы: ' . number_format($cars->cost, 2, ',', ' ') . ' тенге' . $is_electric_car, $cert);
        $cert = str_replace('[Z_DOC]', 'Дата и номер заявки на внесение платы: №' . $t->profile_id . ' от ' . date('d.m.Y', $t->date) . ' г.', $cert);

        // формируем красивую подпись
        $_s_text = 'Наименование организации, выдавшей документ о полноте платы: ';
        if ($_s['sign'] != 'FAILED') {
            $_s_text .= $_s['company'] . ', БИН/ИИН  ' . $_s['bin'] . '.';
        }

        $_s_text = '<strong>' . $_s_text . '</strong>';

        $cert = str_replace('[Z_ROP]', $_s_text, $cert);

        $cert = str_replace('[QR_LINE]', $__qr_sign_line, $cert);
        $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/' . $cars->id . '.png" width="125">', $cert);
//        $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);

        $cert = str_replace('[Z_S1]', 'Настоящий документ сформирован электронным способом' . $ac_info . '. Данные указанные в настоящем документе, включая сумму оплаты, сформированы на основании сведений, которые были предоставлены плательщиком (производителем, импортером). Ответственность за полноту и достоверность указанных в настоящем документе сведений, а также за какие-либо возможные последствия и/или ущерб, причиненный плательщику (производителю, импортеру) и/или третьим лицам, государству, государственным органам и организациям в связи с недостоверностью и/или не полнотой предоставленных сведений полностью несет плательщик (производитель, импортер). ТОО «Оператор РОП» полностью освобождается от такой ответственности. Данные и подлинность настоящего документа о полноте оплаты можно проверить на официальном сайте ТОО «Оператор РОП».', $cert);
        $cert = str_replace('[Z_S2]', 'Форма Документа о полноте оплаты разработана и утверждена на основании Правил реализации расширенных обязательств производителей, импортеров, утвержденных постановлением Правительства Республики Казахстан №28 от «27» января 2016 года.', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/certificates/' . $cars->vin . '.pdf');

    }
}

/**
 * Сгенерировать заявка на корректировку, выдать запрашиваемый.
 * @param gfsgfsg integer  $ccp_id  client_correction_profile->id
 * @return void
 */
function __genAppCorrection($ccp_id, $lang = null, $download = true)
{
    global $messages;
    $lang = $lang ?? 'ru';
    $di = Di::getDefault();
    $lc = $di->has('translator') ? $di->getShared('translator') : null;

    $cc_profile = ClientCorrectionProfile::findFirstById($ccp_id);
    $t = Transaction::findFirstByProfileId($cc_profile->profile_id);
    $p = Profile::findFirstById($t->profile_id);
    $auth = User::getUserBySession();

    // пути для сохранения и скачивания
    $to_download = '';
    $path = APP_PATH . '/private/client_corrections/';

    // гененрируем сертификат
    $certificate_template = APP_PATH . '/app/templates/html/application/app_correction.html';

    if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
        echo 'Ошибка #20200614.';
        die();
    }
    $html = '';
    $st_type_before = '';
    $st_type_after = '';
    $_qr_edited_user = '';
    if ($p->type == 'CAR') {

        $car = Car::findFirstById($cc_profile->object_id);

        $page_num = 0;
        $client_idnum = '';
        $client_name = '';

        $edited_car = ClientCorrectionLogs::find([
            "conditions" => "ccp_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $cc_profile->id,
                "type" => "CAR"
            ]
        ]);

        $cert = join('', file($certificate_template));
        $certificate_tmp = APP_PATH . '/storage/temp/cc_car_' . $car->id . '.html';

        $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $cc_profile->created) . ' г.', $cert);
        $cert = str_replace('[ID]', '№ ' . $cc_profile->profile_id, $cert);
        $cert = str_replace('[Z_NUM]', '№ 000' . $cc_profile->id, $cert);

        $cert = str_replace('[DATE]', date('d.m.Y H:i', $cc_profile->created), $cert);

        if ($edited_car) {
            $log_id_list = '';
            foreach ($edited_car as $key => $log) {
                $page_num++;

                $log_id_list .= $log->id . ', ';
                $log_info = '<br><b style="font-size:9px">Внесены изменения на основании заявки(-ок) на изменение № ' . $log_id_list . ' данные об изменениях указаны в приложении(-ях) к настоящему документу.</b>';
                $html .= '<div class="page">
                            <div style="page-break-before: always; padding: 30px 0px 0px 0px;" >
                                <h1> Приложение ' . $page_num . '</h1>
                                <br><br><b>Заявка на изменение № ' . $cc_profile->id . ' от ' . date(" H:i d-m-Y ", $log->dt) . ' года.</b><br><br><table style="max-width: 50%;  max-width: 100%;border-collapse: collapse; table-layout: fixed;">';

                foreach (json_decode($log->meta_before) as $l_before) {
                    foreach (json_decode($log->meta_after) as $l_after) {
                        $country_before = RefCountry::findFirstById($l_before->ref_country);
                        $country_import_before = RefCountry::findFirstById($l_before->ref_country_import);
                        $country_after = RefCountry::findFirstById($l_after->ref_country);
                        $country_import_after = RefCountry::findFirstById($l_after->ref_country_import);
                        $car_type_before = RefCarType::findFirstById($l_before->ref_car_type_id);
                        $car_type_after = RefCarType::findFirstById($l_after->ref_car_type_id);
                        $car_cat_before = RefCarCat::findFirstById($l_before->ref_car_cat);
                        $car_cat_after = RefCarCat::findFirstById($l_after->ref_car_cat);
                        $calculate_method_before = null;
                        $calculate_method_after = null;

                        if ($l_before->calculate_method) {
                            $calculate_method_before = CALCULATE_METHODS[$l_before->calculate_method];
                        }
                        if ($l_after->calculate_method) {
                            $calculate_method_after = CALCULATE_METHODS[$l_after->calculate_method];
                        }

                        $cost_before = __money((float)($l_before->cost ?? 0)) . ' тг';
                        $cost_after = __money((float)($l_after->cost ?? 0)) . ' тг';
                        $e_car_before = '_';
                        $e_car_after = '_';

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
                            <tr><td>Объем / вес</td>
    <td>' . number_format((float)($l_before->volume ?? 0), 3) . '</td>
    <td>' . number_format((float)($l_after->volume ?? 0), 3) . '</td>
</tr>
                                <tr><td>VIN-код / номер</td><td>' . $l_before->vin . '</td><td>' . $l_after->vin . '</td></tr>
                                <tr><td>Страна производства</td><td>' . $country_before->name . '</td><td>' . $country_after->name . '</td></tr>
                                <tr><td>Страна импорта</td><td>' . $country_import_before->name . '</td><td>' . $country_import_after->name . '</td></tr>
                                <tr><td>Сумма, тенге</td><td>' . $cost_before . '</td><td>' . $cost_after . '</td></tr>
                               <tr><td>Дата импорта</td>
    <td>' . (!empty($l_before->date_import) ? date("d-m-Y", (int)$l_before->date_import) : '') . '</td>
    <td>' . (!empty($l_after->date_import) ? date("d-m-Y", (int)$l_after->date_import) : '') . '</td>
</tr>
                                <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>
                                <tr><td>С электродвигателям?</td><td>' . $e_car_before . '</td><td>' . $e_car_after . '</td></tr>';
                    }
                }

                // достаем инфо про заказчика
                // если это агентская заявка
                if ($p->agent_iin != '') {
                    $client_name = $p->agent_name;
                    $client_idnum = $p->agent_iin;
                } else {
                    $user = User::findFirstById($cc_profile->user_id);
                    if ($user != false) {
                        $client_idnum = $user->idnum;

                        if ($user->user_type_id == 1) {
                            // если нет, и это физик
                            $client_name = $user->fio;
                        } else {
                            // если нет, и это юрик
                            $client_name = $user->org_name;
                        }
                    }
                }

                // $cert = str_replace('[FIO]', $client->last_name.' '.$client->first_name.' '.$client->parent_name , $cert);
                // $cert = str_replace('[IIN]', $client->iin, $cert);
                $cert = str_replace('[FIO]', $client_name, $cert);
                $cert = str_replace('[IIN]', $client_idnum, $cert);

                $_qr_edited_user = '';
                $sign = (string)($log->sign ?? '');

                if ($sign != '') {
                    $_qr_edited_user = getQrImagesHtml($sign);
                } else {
                    $user_iin_base64 = base64_encode($log->iin);
                    $_qr_edited_user = getQrImagesHtml($user_iin_base64);
                }

                $html .= '<tr><td colspan="3"><br>' . $_qr_edited_user . ' <br><br><b>Подпись: </b>' . $client_name . '(' . $client_idnum . ')</td></tr>';

                $html .= '</table></div></div>';

                $cert = str_replace('[COMMENT]', $log->comment, $cert);
            }

            $cert = str_replace('[LOGS]', $html, $cert);
        } else {
            $cert = str_replace('[LOGS]', '', $cert);
            $cert = str_replace('[COMMENT]', '', $cert);
        }

        $cert = str_replace('[QR_LINE]', $_qr_edited_user . '<br> <b>Заявитель: </b>' . $client_name . '(' . $client_idnum . ')', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/client_corrections/car_' . $cc_profile->id . '.pdf');

        // добавляем файл
        $f = new ClientCorrectionFile();
        $f->profile_id = $cc_profile->profile_id;
        $f->type = 'app_correction';
        $f->original_name = 'car_' . $cc_profile->id . '.pdf';
        $f->ext = 'pdf';
        $f->ccp_id = $cc_profile->id;
        $f->visible = 1;
        $f->user_id = $auth->id;

        if ($f->save()) {
            copy(APP_PATH . '/private/client_corrections/car_' . $cc_profile->id . '.pdf', APP_PATH . '/private/client_correction_docs/car_' . $cc_profile->id . '.pdf');
        }

    } else {

        $good = Goods::findFirstById($cc_profile->object_id);

        $page_num = 0;
        $client_idnum = '';
        $client_name = '';

        $edited_good = ClientCorrectionLogs::find([
            "conditions" => "ccp_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $cc_profile->id,
                "type" => "GOODS"
            ]
        ]);

        $cert = join('', file($certificate_template));
        $certificate_tmp = APP_PATH . '/storage/temp/cc_good_' . $good->id . '.html';

//        $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);
        $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $cc_profile->created) . ' г.', $cert);
        $cert = str_replace('[ID]', '№ ' . $cc_profile->profile_id, $cert);
        $cert = str_replace('[Z_NUM]', '№ 000' . $cc_profile->id, $cert);

        $cert = str_replace('[DATE]', date('d.m.Y H:i', $cc_profile->created), $cert);

        if ($edited_good) {
            $log_id_list = '';
            foreach ($edited_good as $key => $log) {
                $page_num++;
                $log_id_list .= $log->id . ', ';
                $log_info = '<br><b style="font-size:9px">Внесены изменения на основании заявки(-ок) на изменение № ' . $log_id_list . ' данные об изменениях указаны в приложении(-ях) к настоящему документу.</b>';
                $html .= '<div class="page">
                            <div style="page-break-before: always; padding: 30px 0px 0px 0px;font-size: 12px;" >
                                <h1> Приложение ' . $page_num . '</h1>
                                <br><br><b>Заявка на изменение № ' . $cc_profile->id . ' от ' . date(" H:i d-m-Y ", $log->dt) . ' года.</b><br><br><table>';

                foreach (json_decode($log->meta_before) as $l_before) {
                    foreach (json_decode($log->meta_after) as $l_after) {
                        $g_country_before = RefCountry::findFirstById($l_before->ref_country);
                        $g_country_after = RefCountry::findFirstById($l_after->ref_country);
                        $g_tn_before = RefTnCode::findFirstById($l_before->ref_tn);
                        $g_tn_after = RefTnCode::findFirstById($l_after->ref_tn);

                        // товар в упаковке
                        $before_tn_add = '';
                        $after_tn_add = '';
                        $calculate_method_before = null;
                        $calculate_method_after = null;
                        if ($l_before->calculate_method) {
                            $calculate_method_before = CALCULATE_METHODS[$l_before->calculate_method];
                        }

                        if ($l_after->calculate_method) {
                            $calculate_method_after = CALCULATE_METHODS[$l_after->calculate_method];
                        }

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
                                    <tr><td>Код ТНВЭД продукции (товара)</td><td>' . $g_tn_before->code . ' ' . $before_tn_add . '</td><td>' . $g_tn_after->code . ' ' . $after_tn_add . ' </td></tr>
                                    <tr><td>Дата импорта/производства продукции (товара)</td><td>' . date("d-m-Y", $l_before->date_import) . '</td><td>' . date("d-m-Y", $l_after->date_import) . '</td></tr>
                                    <tr><td>Номер счет-фактуры или ГТД</td><td>' . $l_before->basis . ' </td><td>' . $l_after->basis . ' </td></tr>
                                    <tr><td>Дата СФ/ГТД</td><td>' . date("d-m-Y", $l_before->basis_date) . ' </td><td>' . date("d-m-Y", $l_after->basis_date) . ' </td></tr>
                                    <tr><td>Вес продукции (товара), кг</td><td>' . number_format($l_before->weight, 3) . '</td><td>' . number_format($l_after->weight, 3) . '</td></tr>
                                    <tr><td>Сумма утилизационного платежа за продукцию (товар), тг.</td><td>' . __money(round($l_before->amount - $l_before->package_cost, 2)) . ' тг</td><td>' . __money(round($l_after->amount - $l_after->package_cost, 2)) . ' тг</td></tr>
                                    <tr><td>Вес упаковки, кг.</td><td>' . number_format((float)($l_before->package_weight ?? 0), 3) . '</td><td>' . number_format((float)($l_after->package_weight ?? 0), 3) . '</td></tr>
                                                    <tr><td>Утилизационный платеж за упаковку, тг</td>
                                        <td>' . __money((float)($l_before->package_cost ?? 0)) . ' тг</td>
                                        <td>' . __money((float)($l_after->package_cost ?? 0)) . ' тг</td>
                                    </tr>
                                    <tr><td>Итоговая сумма, тг</td>
                                        <td>' . __money((float)($l_before->amount ?? 0)) . ' тг</td>
                                        <td>' . __money((float)($l_after->amount ?? 0)) . ' тг</td>
                                    </tr>
                                    <tr><td>Способ расчета</td><td>' . $calculate_method_before . '</td><td>' . $calculate_method_after . '</td></tr>';
                    }
                }

                // достаем инфо про заказчика
                // если это агентская заявка
                if ($p->agent_iin != '') {
                    $client_name = $p->agent_name;
                    $client_idnum = $p->agent_iin;
                } else {
                    $user = User::findFirstById($cc_profile->user_id);
                    if ($user != false) {
                        $client_idnum = $user->idnum;

                        if ($user->user_type_id == 1) {
                            // если нет, и это физик
                            $client_name = $user->fio;
                        } else {
                            // если нет, и это юрик
                            $client_name = $user->org_name;
                        }
                    }
                }

                // $cert = str_replace('[FIO]', $client->last_name.' '.$client->first_name.' '.$client->parent_name , $cert);
                // $cert = str_replace('[IIN]', $client->iin, $cert);
                $cert = str_replace('[FIO]', $client_name, $cert);
                $cert = str_replace('[IIN]', $client_idnum, $cert);

                $_qr_edited_user = '';

                $sign = (string)($log->sign ?? '');

                if ($sign !== '') {
                    $_qr_edited_user = getQrImagesHtml($sign);
                } else {
                    $sign = base64_encode((string)($log->iin ?? ''));
                    $_qr_edited_user = getQrImagesHtml($sign);
                }

                $html .= '<tr><td colspan="3"><br>' . $_qr_edited_user . ' <br><br><b>Подпись: </b>' . $client_name . '(' . $client_idnum . ')</td></tr>';
                $html .= '</table></div></div>';

                $cert = str_replace('[COMMENT]', $log->comment, $cert);
            }

            $cert = str_replace('[LOGS]', $html, $cert);
        } else {
            $cert = str_replace('[LOGS]', '', $cert);
            $cert = str_replace('[COMMENT]', '', $cert);
        }

        $cert = str_replace('[QR_LINE]', $_qr_edited_user . '<br> <b>Заявитель: </b>' . $client_name . '(' . $client_idnum . ')', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/client_corrections/good_' . $cc_profile->id . '.pdf');

        // добавляем файл
        $f = new ClientCorrectionFile();
        $f->profile_id = $cc_profile->profile_id;
        $f->type = 'app_correction';
        $f->original_name = 'good_' . $cc_profile->id . '.pdf';
        $f->ext = 'pdf';
        $f->ccp_id = $cc_profile->id;
        $f->visible = 1;
        $f->user_id = $auth->id;

        if ($f->save()) {
            copy(APP_PATH . '/private/client_corrections/good_' . $cc_profile->id . '.pdf', APP_PATH . '/private/client_correction_docs/good_' . $cc_profile->id . '.pdf');
        }
    }
}


/**
 * Сгенерировать заявка на корректировку, выдать запрашиваемый.
 * @param gfsgfsg integer  $ccp_id  client_correction_profile->id
 * @return void
 */
function __genAppAnnulment($ccp_id, $lang = null, $download = true)
{
    $di = Di::getDefault();
    $lc = $di->has('translator') ? $di->getShared('translator') : null;

    $cc_profile = ClientCorrectionProfile::findFirstById($ccp_id);
    $t = Transaction::findFirstByProfileId($cc_profile->profile_id);
    $p = Profile::findFirstById($t->profile_id);

    // пути для сохранения и скачивания
    $to_download = '';
    $path = APP_PATH . '/private/client_corrections/';

    // гененрируем сертификат
    $certificate_template = APP_PATH . '/app/templates/html/application/app_annulment.html';

    if ($t->dt_approve > ROP_ESIGN_DATE && $t->ac_approve != 'SIGNED') {
        echo 'Ошибка #20200614.';
        die();
    }

    if ($p->type == 'CAR') {

        $car = Car::findFirstById($cc_profile->object_id);

        $page_num = 0;
        $client_idnum = '';
        $client_name = '';

        $cert = join('', file($certificate_template));
        $certificate_tmp = APP_PATH . '/storage/temp/cc_car_' . $car->id . '.html';

//        $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);
        $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $cc_profile->created) . ' г.', $cert);
        $cert = str_replace('[ID]', '№ ' . $cc_profile->profile_id, $cert);
        $cert = str_replace('[Z_NUM]', '№ 000' . $cc_profile->id, $cert);

        $cert = str_replace('[DATE]', date('d.m.Y H:i', $cc_profile->created), $cert);

        // достаем инфо про заказчика
        // если это агентская заявка
        if ($p->agent_iin != '') {
            $client_name = $p->agent_name;
            $client_idnum = $p->agent_iin;
        } else {
            $user = User::findFirstById($cc_profile->user_id);
            if ($user != false) {
                $client_idnum = $user->idnum;

                if ($user->user_type_id == 1) {
                    // если нет, и это физик
                    $client_name = $user->fio;
                } else {
                    // если нет, и это юрик
                    $client_name = $user->org_name;
                }
            }
        }

        $edited = ClientCorrectionLogs::find([
            "conditions" => "ccp_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $cc_profile->id,
                "type" => "CAR"
            ]
        ]);

        if ($edited) {
            foreach ($edited as $key => $log) {
                $cert = str_replace('[COMMENT]', $log->comment, $cert);
            }
        } else {
            $cert = str_replace('[COMMENT]', '', $cert);
        }

        $cert = str_replace('[FIO]', $client_name, $cert);
        $cert = str_replace('[IIN]', $client_idnum, $cert);

        $_qr_edited_user = '';
        $log_sign = $log->sign ?? '';
        if ($log_sign) {
            $_qr_edited_user = getQrImagesHtml($log_sign);
        } else {
            $log_iin = $log->iin ?? '';
            if ($log_iin) {
                $user_iin_base64 = base64_encode($log_iin);
                $_qr_edited_user = getQrImagesHtml($user_iin_base64);
            }
        }

        $cert = str_replace('[QR_LINE]', $_qr_edited_user . '<br> <b>Заявитель: </b>' . $client_name . '(' . $client_idnum . ')', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/client_corrections/annul_car_' . $cc_profile->id . '.pdf');

        // добавляем файл
        $f = new ClientCorrectionFile();
        $f->profile_id = $cc_profile->profile_id;
        $f->type = 'app_annulment';
        $f->original_name = 'annul_car_' . $cc_profile->id . '.pdf';
        $f->ext = 'pdf';
        $f->ccp_id = $cc_profile->id;
        $f->visible = 1;

        if ($f->save()) {
            copy(APP_PATH . '/private/client_corrections/annul_car_' . $cc_profile->id . '.pdf', APP_PATH . '/private/client_correction_docs/annul_car_' . $cc_profile->id . '.pdf');
        }

    } else {

        $good = Goods::findFirstById($cc_profile->object_id);

        $page_num = 0;
        $client_idnum = '';
        $client_name = '';

        $edited_good = ClientCorrectionLogs::find([
            "conditions" => "ccp_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $cc_profile->id,
                "type" => "GOODS"
            ]
        ]);

        $cert = join('', file($certificate_template));
        $certificate_tmp = APP_PATH . '/storage/temp/cc_good_' . $good->id . '.html';

//        $cert = str_replace('[Z_LOGO]', '<img src="' . APP_PATH . '/public/assets/img/logo2x_black.png" width="150">', $cert);
        $cert = str_replace('[Z_DATE]', 'от ' . date('d.m.Y', $cc_profile->created) . ' г.', $cert);
        $cert = str_replace('[ID]', '№ ' . $cc_profile->profile_id, $cert);
        $cert = str_replace('[Z_NUM]', '№ 000' . $cc_profile->id, $cert);

        $cert = str_replace('[DATE]', date('d.m.Y H:i', $cc_profile->created), $cert);


        // достаем инфо про заказчика
        // если это агентская заявка
        if ($p->agent_iin != '') {
            $client_name = $p->agent_name;
            $client_idnum = $p->agent_iin;
        } else {
            $user = User::findFirstById($cc_profile->user_id);
            if ($user != false) {
                $client_idnum = $user->idnum;

                if ($user->user_type_id == 1) {
                    // если нет, и это физик
                    $client_name = $user->fio;
                } else {
                    // если нет, и это юрик
                    $client_name = $user->org_name;
                }
            }
        }

        $edited = ClientCorrectionLogs::find([
            "conditions" => "ccp_id = :cid: and type = :type:",
            "bind" => [
                "cid" => $cc_profile->id,
                "type" => "GOODS"
            ]
        ]);

        if ($edited) {
            foreach ($edited as $key => $log) {
                $cert = str_replace('[COMMENT]', $log->comment, $cert);
            }
        } else {
            $cert = str_replace('[COMMENT]', '', $cert);
        }

        // $cert = str_replace('[FIO]', $client->last_name.' '.$client->first_name.' '.$client->parent_name , $cert);
        // $cert = str_replace('[IIN]', $client->iin, $cert);
        $cert = str_replace('[FIO]', $client_name, $cert);
        $cert = str_replace('[IIN]', $client_idnum, $cert);

        $_qr_edited_user = '';
        $log_sign = $log->sign ?? '';
        if ($log_sign) {
            $_qr_edited_user = getQrImagesHtml($log_sign);
        } else {
            $log_iin = $log->iin ?? '';
            if ($log_iin) {
                $user_iin_base64 = base64_encode($log_iin);
                $_qr_edited_user = getQrImagesHtml($user_iin_base64);
            }
        }

        $cert = str_replace('[QR_LINE]', $_qr_edited_user . '<br> <b>Заявитель: </b>' . $client_name . '(' . $client_idnum . ')', $cert);

        file_put_contents($certificate_tmp, $cert);
        (new PdfService())->generate($certificate_tmp, APP_PATH . '/private/client_corrections/annul_good_' . $cc_profile->id . '.pdf');

        // добавляем файл
        $f = new ClientCorrectionFile();
        $f->profile_id = $cc_profile->profile_id;
        $f->type = 'app_annulment';
        $f->original_name = 'annul_good_' . $cc_profile->id . '.pdf';
        $f->ext = 'pdf';
        $f->ccp_id = $cc_profile->id;
        $f->visible = 1;

        if ($f->save()) {
            copy(APP_PATH . '/private/client_corrections/annul_good_' . $cc_profile->id . '.pdf', APP_PATH . '/private/client_correction_docs/annul_good_' . $cc_profile->id . '.pdf');
        }
    }
}

function __signFund($id, $context)
{
    $f = FundProfile::findFirstById($id);
    $context_settings = $context->session->get("__settings");
    $__settings = json_decode(json_encode($context_settings), true);

    $s = '';

    $idnum = '';
    $title = '';

    if ($__settings) {
        $idnum = $__settings['iin'];
        $title = $__settings['fio'];
        if ($__settings['bin']) {
            $idnum = $__settings['bin'];
            $title = $__settings['company'];
        }

        $s .= "$idnum:$title";
    }

    // номер заявки и общая сумма
    $s .= ":" . $f->id . ":" . $f->amount . ":" . date('d.m.Y', $f->period_start) . '(' . $f->period_start . ')' . ":" . date('d.m.Y', $f->period_end) . '(' . $f->period_end . ')' . ":" . $f->ref_country_id . ":" . $f->w_a . ":" . $f->w_b . ":" . $f->w_c . ":" . $f->w_d . ":" . $f->e_a . ":" . $f->r_a . ":" . $f->r_b . ":" . $f->r_c . ":" . $f->tc_a . ":" . $f->tc_b . ":" . $f->tc_c . ":" . $f->tt_a . ":" . $f->tt_b . ":" . $f->tt_c;

    // дата создания
    $s .= ":" . date('d.m.Y', $f->created) . "(" . $f->created . ")";

    // заполнение заявки
    $list = FundCar::find(array('fund_id = :pid:',
        'bind' => array(
            'pid' => $id,
        )
    ));

    foreach ($list as $n => $l) {
        $s .= ':CAR';
        $s .= ':' . $l->id . ':' . $l->volume . ':' . $l->vin . ':' . $l->ref_car_cat . ':' . $l->ref_car_type_id . ':' . date('d.m.Y', $l->date_produce) . '(' . $l->date_produce . '):' . $l->cost . ':' . $l->ref_st_type;
    }

    $z = gzencode($s);
    $z = base64_encode($z);

    $f->hash = $z;
    $f->save();

    return $z;
}

function __signData($id, $context)
{
    $t = Transaction::findFirstByProfileId($id);
    $p = Profile::findFirstById($id);
    $__settings = $context->session->get("__settings");

    $idnum = '';
    $title = '';
    $s = '';
    if ($__settings) {
        $idnum = $__settings['iin'];
        $title = $__settings['fio'];
        if ($__settings['bin']) {
            $idnum = $__settings['bin'];
            $title = $__settings['company'];
        }

        $s .= "$idnum:$title";
    }

    // номер заявки и общая сумма
    $s .= ":" . $p->id . ":" . $t->amount;

    // дата создания
    $s .= ":" . date('d.m.Y', $p->created) . "(" . $p->created . ")";

    // заполнение заявки
    if ($p->type == 'CAR') {
        $list = Car::find(array('profile_id = :pid:',
            'bind' => array(
                'pid' => $id,
            )
        ));
        foreach ($list as $n => $l) {
            $s .= ':CAR';
            $s .= ':' . $l->id . ':' . $l->volume . ':' . $l->vin . ':' . $l->year . ':' . $l->ref_car_cat . ':' . $l->ref_car_type_id . ':' . $l->ref_country . ':' . date('d.m.Y', $l->date_import) . '(' . $l->date_import . '):' . $l->cost . ':' . $l->ref_st_type;
        }
    } else {
        $list = Goods::find(array('profile_id = :pid:',
            'bind' => array(
                'pid' => $id,
            )
        ));
        foreach ($list as $n => $l) {
            $s .= ':GOOD';
            $s .= ':' . $l->id . ':' . $l->ref_tn . '(' . $l->ref_tn_add . '):' . $l->ref_country . ':' . date('d.m.Y', $l->date_import) . '(' . $l->date_import . '):' . $l->weight . ':' . $l->price . ':' . $l->amount . ':' . $l->basis;
        }
    }

    $z = gzencode($s);
    $z = base64_encode($z);

    $p->hash = $z;
    $p->save();

    return $z;
}

/**
 * @throws \App\Exceptions\AppException
 */
function __genFund($pid, $type, $j, $download = true)
{
    $f = FundProfile::findFirstById($pid);
    $car_list_title = '';


    $cancel_sign = '<div style="color: red; font-size: 120px; position: fixed; top: 500px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-60deg) ;">АННУЛИРОВАНО</div>';
    $cancel_sign_horizontal = '<div style="color: red; font-size: 120px; position: fixed; top: 350px; left: 50%; -webkit-transform:translate(-50%, -50%) rotate(-30deg) ;">АННУЛИРОВАНО</div>';

    // данные подписанта
    $j_sign = '—';

    if ($j) {
        if ($j['iin']) {
            $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
        }
        // если это компания
        if ($j['bin']) {
            $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
        }
    }

    $hash = $f->hash;
    $sign_acc = $f->sign_acc;
    $j_acc = null;
    $j_hod = null;
    $j_hof = null;
    $j_hop = null;
    $j_fad = null;

    if ($sign_acc) {
        $check_acc = checkFund($hash, $sign_acc);
        if ($check_acc) {
            if (isset($check_acc['data'])) {
                $j_acc = $check_acc['data'];
            }
        }
    }

    // получаем имена и БИН-ы
    $mc = mysqli_connect(getenv('DB_HOST'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    mysqli_set_charset($mc, "utf8");
    mysqli_select_db($mc, getenv('DB_NAME'));
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
    $_c_kbe = $kbe ? $kbe->kbe : 'не указан';
    $_c_bik = $bik ? $bik->bik : 'не указан';
    $_c_oked = $rw['oked'] ? $rw['oked'] : 'не указано';
    $_c_reg_num = $rw['reg_num'] ? $rw['reg_num'] : 'не указано';
    $car = FundCar::findByFundId($pid);
    $cars_fund = $car;

    /******************************************************************************************************************
     * Заявка
     ******************************************************************************************************************/

    if ($type == 'payment'):
        $dst = APP_PATH . '/storage/temp/payment_' . $pid . '.html';

        if ($f->type == 'EXP') {
            $src = APP_PATH . '/app/templates/html/fund/payment_1.html';
        } else {
            $src = APP_PATH . '/app/templates/html/fund/payment_2.html';
        }

        // заявление
        $content = file_get_contents($src);

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

        $check = checkFund($hash, $sign);
        $check_acc = checkFund($hash, $sign_acc);
        $check_hod = checkFund($hash, $sign_hod);
        $check_fad = checkFund($hash, $sign_fad);
        $check_hop = checkFund($hash, $sign_hop);
        $check_hof = checkFund($hash, $sign_hof);

        if (!empty($check['data'])) {
            $j = $check['data'];
        }
        if (!empty($check_acc['data'])) {
            $j_acc = $check_acc['data'];
        }
        if (!empty($check_hod['data'])) {
            $j_hod = $check_hod['data'];
        }
        if (!empty($check_fad['data'])) {
            $j_fad = $check_fad['data'];
        }
        if (!empty($check_hop['data'])) {
            $j_hop = $check_hop['data'];
        }
        if (!empty($check_hof['data'])) {
            $j_hof = $check_hof['data'];
        }

        // данные подписанта
        $j_sign = '—';
        if ($j) {
            if ($j['iin']) $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
            if ($j['bin']) $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
        }


        // данные подписанта
        $j_sign = '—';
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        $__qr_sign_line_hod = '';
        if ($f->sign_hod) {
            $__qr_sign_line_hod = getQrImagesHtml($f->sign_hod);
        }

        // данные подписанта
        $j_sign_hod = '—';
        if ($j_hod) {
            if ($j_hod['iin']) $j_sign_hod = $j_hod['fio'] . ' (ИИН ' . $j_hod['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hod['dt'];
            if ($j_hod['bin']) $j_sign_hod = $j_hod['company'] . ' (БИН ' . $j_hod['bin'] . ') — СОТРУДНИК ' . $j_sign_hod;
        }

        $__qr_sign_line_fad = '';
        if ($f->sign_fad) {
            $__qr_sign_line_fad = getQrImagesHtml($f->sign_fad);
        }

        // данные подписанта
        $j_sign_fad = '—';
        if ($j_fad) {
            if ($j_fad['iin']) $j_sign_fad = $j_fad['fio'] . ' (ИИН ' . $j_fad['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_fad['dt'];
            if ($j_fad['bin']) $j_sign_fad = $j_fad['company'] . ' (БИН ' . $j_fad['bin'] . ') — СОТРУДНИК ' . $j_sign_fad;
        }

        $__qr_sign_line_hop = '';
        if ($f->sign_hop) {
            $__qr_sign_line_hop = getQrImagesHtml($f->sign_hop);
        }

        // данные подписанта
        $j_sign_hop = '—';
        if ($j_hop) {
            if ($j_hop['iin']) $j_sign_hop = $j_hop['fio'] . ' (ИИН ' . $j_hop['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hop['dt'];
            if ($j_hop['bin']) $j_sign_hop = $j_hop['company'] . ' (БИН ' . $j_hop['bin'] . ') — СОТРУДНИК ' . $j_sign_hop;
        }

        $__qr_sign_line_hof = '';
        if ($f->sign_hof) {
            $__qr_sign_line_hof = getQrImagesHtml($f->sign_hof);
        }

        // данные подписанта
        $j_sign_hof = '—';
        if ($j_hof) {
            if ($j_hof['iin']) $j_sign_hof = $j_hof['fio'] . ' (ИИН ' . $j_hof['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_hof['dt'];
            if ($j_hof['bin']) $j_sign_hof = $j_hof['company'] . ' (БИН ' . $j_hof['bin'] . ') — СОТРУДНИК ' . $j_sign_hof;
        }

        if (!$j_hof) {
            $content = str_replace('[FUND_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> документ не подписан</p>', $content);
        } else {
            $_5_sign = '';
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

        if ($j && $download) {
            __downloadFile(APP_PATH . '/storage/temp/payment_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ЗАЯВЛЕНИЕ
     ******************************************************************************************************************/

    if ($type == 'fund'):
        $dst = APP_PATH . '/storage/temp/fund_' . $pid . '.html';

        if ($f->type == 'EXP') {
            $src = APP_PATH . '/app/templates/html/fund/fund_1.html';
        } else {
            $src = APP_PATH . '/app/templates/html/fund/fund_2.html';
        }

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
        $content = str_replace('[FUND_NUM]', $f->number, $content);

        // пометка об отмене
        if ($f->approve == 'FUND_ANNULMENT') {
            $content = str_replace('[CANCELLED]', $cancel_sign, $content);
        } else {
            $content = str_replace('[CANCELLED]', '', $content);
        }

        $country = RefCountry::findFirstById($f->ref_country_id);
        $content = str_replace('[FUND_COUNTRY]', $country->name, $content);
        $content = str_replace('[PERIOD_START]', date('d.m.Y', $f->period_start), $content);
        $content = str_replace('[PERIOD_END]', date('d.m.Y', $f->period_end), $content);
        $content = str_replace('[FUND_PERIOD]', date('d.m.Y', $f->period_start) . " - " . date('d.m.Y', $f->period_end), $content);
        $content = str_replace('[FUND_FROM]', $_c_title, $content);
        $content = str_replace('[COUNT]', __money(count($car)), $content);

        $check_agro = FundCar::findFirst(array(
            "ref_car_cat IN (13, 14) AND fund_id = :fund_id:",
            "bind" => array(
                "fund_id" => $pid
            )
        ));

        $check_car = FundCar::findFirst(array(
            "ref_car_cat NOT IN (13, 14) AND fund_id = :fund_id:",
            "bind" => array(
                "fund_id" => $pid
            )
        ));

        $fund_car_trac = '';
        $car_list_title = '';
        if ($check_agro) {
            $fund_car_trac = "самоходной сельскохозяйственной техники, соответствующей экологическим требованиям, определенным техническими регламентами";
            $car_list_title = 'Самоходная сельскохозяйственная техника, соответствующая экологическим требованиям, определенным техническими регламентами';
        }
        if ($check_car) {
            $fund_car_trac = "автомобильных транспортных средств (соответствующих экологическому классу 4 и выше; с электродвигателями) их компонентов";
            $car_list_title = 'Экологически чистые автомобильные транспортные средства, соответствующие экологическому классу 4 и выше; с электродвигателями';
        }

        $content = str_replace('[FUND_CAR_TRAC]', $fund_car_trac, $content);
        $content = str_replace('[FUND_CAMOUNT]', __money($f->amount), $content);

        $content = str_replace('[FUND_W]', __money($f->w_a + $f->w_b + $f->w_c + $f->w_d), $content);
        $content = str_replace('[FUND_E]', __money($f->e_a), $content);
        $content = str_replace('[FUND_R]', __money($f->r_a + $f->r_b + $f->r_c), $content);
        $content = str_replace('[FUND_T]', __money($f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c), $content);
        $content = str_replace('[FUND_TC]', __money($f->tc_a + $f->tc_b + $f->tc_c), $content);
        $content = str_replace('[FUND_TT]', __money($f->tt_a + $f->tt_b + $f->tt_c), $content);
        $content = str_replace('[FUND_TOTAL]', __money($f->amount), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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

        $np = 1;
        $car_list = array();
        $table_list = '';
        $table_list_ext = '';
        $c_gm = 0;
        $c_gn = 0;
        $c_gt = 0;
        $c_gmc = 0;
        $c_gnc = 0;
        $c_gtc = 0;

        foreach ($cars_fund as $i => $c) {
            $model = RefModel::findFirstById($c->model_id);
            $cat = RefCarCat::findFirstById($c->ref_car_cat);
            $car = Car::findFirstByVin($c->vin);
            $profile_num = '—';
            if ($car) {
                $profile_num = $car->profile_id > 0 ? $car->profile_id : '—';
            }
            $car_list[] = $model->brand . ' ' . $model->model . " (" . $c->vin . ")";
            if ($check_car) {
                $table_list .= '<tr><td>' . $np . '</td><td>' . $c->vin . '</td><td>' . $model->brand . ' ' . $model->model . '</td></tr>';
            }
            if ($check_agro) {
                $agro_vin = preg_split('/[&-]/', $c->vin, 2);
                $id_num = $agro_vin[0] != 'I' ? $agro_vin[0] : '—';
                $eng_num = $agro_vin[1] != 'B' ? $agro_vin[1] : '—';
                $table_list .= '<tr><td>' . $np . '</td><td>' . $id_num . ' / ' . $eng_num . '</td><td>' . $model->brand . ' ' . $model->model . '</td></tr>';
            }
            if ($check_car) {
                $table_list_ext .= '<tr><td>' . $np . '</td><td>' . $model->brand . ' ' . $model->model . '</td><td>' . $c->vin . '</td><td>—</td><td>' . __getCat($cat->name) . '</td><td>' . date('d.m.Y', $c->date_produce) . '</td><td>' . $profile_num . '</td><td>' . __money($c->volume) . '</td></tr>';
            }
            if ($check_agro) {
                $agro_vin = preg_split('/[&-]/', $c->vin, 2);
                $id_num = $agro_vin[0] != 'I' ? $agro_vin[0] : '—';
                $eng_num = $agro_vin[1] != 'B' ? $agro_vin[1] : '—';
                $table_list_ext .= '<tr><td>' . $np . '</td><td>' . $model->brand . ' ' . $model->model . '</td><td>' . $id_num . '</td><td>' . $eng_num . '</td><td>' . __getCat($cat->name) . '</td><td>' . date('d.m.Y', $c->date_produce) . '</td><td>' . $profile_num . '</td><td>' . __money($c->volume) . '</td></tr>';
            }
            $np++;
            if (strstr($cat->name, 'cat-m')) {
                $c_gm += $c->cost;
                $c_gmc++;
            }
            if (strstr($cat->name, 'cat-n')) {
                $c_gn += $c->cost;
                $c_gnc++;
            }
            if (strstr($cat->name, 'tractor')) {
                $c_gt += $c->cost;
                $c_gtc++;
            }
            if (strstr($cat->name, 'combain')) {
                $c_gt += $c->cost;
                $c_gtc++;
            }
        }

        // сложные расчеты )))
        $_count = $cars_fund ? count($cars_fund->toArray()) : 0;
        $_minus = 0;
        if ($_count > 0) {
            $_minus = ($f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c) / $_count;
        }
        // минусуем с каждой машины общие части
        if ($c_gm > 0) $c_gm = $c_gm - ($_minus * $c_gmc);
        if ($c_gn > 0) $c_gn = $c_gn - ($_minus * $c_gnc);
        if ($c_gt > 0) $c_gt = $c_gt - ($_minus * $c_gtc);

        $content = str_replace('[FUND_LIST]', implode(', ', $car_list), $content);
        $content = str_replace('[CAR_LIST]', $table_list, $content);
        $content = str_replace('[CAR_LIST_EXT]', $table_list_ext, $content);
        $content = str_replace('[CAR_LIST_TITLE]', $car_list_title, $content);
        $content = str_replace('[FUND_GM]', __money($c_gm), $content);
        $content = str_replace('[FUND_GN]', __money($c_gn), $content);
        $content = str_replace('[FUND_GT]', __money($c_gt), $content);

        file_put_contents($dst, $content);
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/fund_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'application';
            $f->original_name = 'ПодписанноеЗаявление_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/fund_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/fund_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 3
     ******************************************************************************************************************/
    if ($type == 'app3'):

        $dst = APP_PATH . '/storage/temp/app3_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_3.html';

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

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }
        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app3_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app3';
            $f->original_name = 'ПодписанноеПриложение3_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app3_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app3_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 4
     ******************************************************************************************************************/
    if ($type == 'app4'):

        $dst = APP_PATH . '/storage/temp/app4_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_4.html';

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

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app4_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app4';
            $f->original_name = 'ПодписанноеПриложение4_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app4_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app4_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 5
     ******************************************************************************************************************/
    if ($type == 'app5'):

        $dst = APP_PATH . '/storage/temp/app5_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_5.html';

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
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app5_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app5';
            $f->original_name = 'ПодписанноеПриложение5_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app5_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app5_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 6
     ******************************************************************************************************************/
    if ($type == 'app6'):

        $dst = APP_PATH . '/storage/temp/app6_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_6.html';

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

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app6_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app6';
            $f->original_name = 'ПодписанноеПриложение6_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app6_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app6_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 7
     ******************************************************************************************************************/
    if ($type == 'app7'):

        $dst = APP_PATH . '/storage/temp/app7_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_7.html';

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

        $f->tt_a ? $tt_a = $f->tt_a : $tt_a = 0;
        $f->tt_b ? $tt_b = $f->tt_b : $tt_b = 0;
        $f->tt_c ? $tt_c = $f->tt_c : $tt_c = 0;
        $tt_sum = $tt_a + $tt_b + $tt_c;

        $content = str_replace('[TT_A]', __money($tt_a), $content);
        $content = str_replace('[TT_B]', __money($tt_b), $content);
        $content = str_replace('[TT_C]', __money($tt_c), $content);
        $content = str_replace('[TT_SUM]', __money($tt_sum), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app7_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app7';
            $f->original_name = 'ПодписанноеПриложение7_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app7_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app7_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 8
     ******************************************************************************************************************/
    if ($type == 'app8'):

        $dst = APP_PATH . '/storage/temp/app8_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_8.html';

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
            $content = str_replace('[CANCELLED]', $cancel_sign_horizontal, $content);
        } else {
            $content = str_replace('[CANCELLED]', '', $content);
        }

        $m1_1 = 0;
        $m1_2 = 0;
        $m1_3 = 0;
        $m1_4 = 0;
        $m1_0 = 0;
        $m2_1 = 0;
        $m2_2 = 0;
        $m2_3 = 0;
        $m2_4 = 0;
        $m2_0 = 0;
        $m1_1c = 0;
        $m1_2c = 0;
        $m1_3c = 0;
        $m1_4c = 0;
        $m1_0c = 0;
        $m2_1c = 0;
        $m2_2c = 0;
        $m2_3c = 0;
        $m2_4c = 0;
        $m2_0c = 0;
        $m_total = 0;

        foreach ($car as $i => $c) {
            if ($c->ref_car_cat == 1 || $c->ref_car_cat == 2) {
                // M1 or M1G
                if ($c->volume <= 1000 && $c->volume != 0) {
                    $m1_1 += $c->cost;
                    $m1_1c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 1000 && $c->volume <= 2000) {
                    $m1_2 += $c->cost;
                    $m1_2c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 2000 && $c->volume <= 3000) {
                    $m1_3 += $c->cost;
                    $m1_3c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 3000) {
                    $m1_4 += $c->cost;
                    $m1_4c++;
                    $m_total += $c->cost;
                }
                if ($c->volume == 0) {
                    $m1_0 += $c->cost;
                    $m1_0c++;
                    $m_total += $c->cost;
                }
            }
            if ($c->ref_car_cat == 9 || $c->ref_car_cat == 10 || $c->ref_car_cat == 11 || $c->ref_car_cat == 12) {
                // M2&M3
                if ($c->volume <= 2500 && $c->volume != 0) {
                    $m2_1 += $c->cost;
                    $m2_1c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 2500 && $c->volume <= 5000) {
                    $m2_2 += $c->cost;
                    $m2_2c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 5000 && $c->volume <= 10000) {
                    $m2_3 += $c->cost;
                    $m2_3c++;
                    $m_total += $c->cost;
                }
                if ($c->volume > 10000) {
                    $m2_4 += $c->cost;
                    $m2_4c++;
                    $m_total += $c->cost;
                }
                if ($c->volume == 0) {
                    $m2_0 += $c->cost;
                    $m2_0c++;
                    $m_total += $c->cost;
                }
            }
        }

        // сложные расчеты )))
        $_count = count($car);
        $_minus = ($f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c) / $_count;

        $m1_1 = $m1_1 > 0 ? $m1_1 - $m1_1c * $_minus : 0;
        $m1_2 = $m1_2 > 0 ? $m1_2 - $m1_2c * $_minus : 0;
        $m1_3 = $m1_3 > 0 ? $m1_3 - $m1_3c * $_minus : 0;
        $m1_4 = $m1_4 > 0 ? $m1_4 - $m1_4c * $_minus : 0;
        $m1_0 = $m1_0 > 0 ? $m1_0 - $m1_0c * $_minus : 0;
        $m2_1 = $m2_1 > 0 ? $m2_1 - $m2_1c * $_minus : 0;
        $m2_2 = $m2_2 > 0 ? $m2_2 - $m2_2c * $_minus : 0;
        $m2_3 = $m2_3 > 0 ? $m2_3 - $m2_3c * $_minus : 0;
        $m2_4 = $m2_4 > 0 ? $m2_4 - $m2_4c * $_minus : 0;
        $m2_0 = $m2_0 > 0 ? $m2_0 - $m2_0c * $_minus : 0;
        $m_total = $m_total > 0 ? $m_total - $_minus * $_count : 0;

        $content = str_replace('[M1_1]', __money($m1_1), $content);
        $content = str_replace('[M1_2]', __money($m1_2), $content);
        $content = str_replace('[M1_3]', __money($m1_3), $content);
        $content = str_replace('[M1_4]', __money($m1_4), $content);
        $content = str_replace('[M1_0]', __money($m1_0), $content);
        $content = str_replace('[M2_1]', __money($m2_1), $content);
        $content = str_replace('[M2_2]', __money($m2_2), $content);
        $content = str_replace('[M2_3]', __money($m2_3), $content);
        $content = str_replace('[M2_4]', __money($m2_4), $content);
        $content = str_replace('[M2_0]', __money($m2_0), $content);
        $content = str_replace('[M1_1C]', __money($m1_1c), $content);
        $content = str_replace('[M1_2C]', __money($m1_2c), $content);
        $content = str_replace('[M1_3C]', __money($m1_3c), $content);
        $content = str_replace('[M1_4C]', __money($m1_4c), $content);
        $content = str_replace('[M1_0C]', __money($m1_0c), $content);
        $content = str_replace('[M2_1C]', __money($m2_1c), $content);
        $content = str_replace('[M2_2C]', __money($m2_2c), $content);
        $content = str_replace('[M2_3C]', __money($m2_3c), $content);
        $content = str_replace('[M2_4C]', __money($m2_4c), $content);
        $content = str_replace('[M2_0C]', __money($m2_0c), $content);
        $content = str_replace('[M_TOTAL]', __money($m_total), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app8_' . $pid . '.pdf', 'landscape');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app8';
            $f->original_name = 'ПодписанноеПриложение8_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app8_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app8_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 9
     ******************************************************************************************************************/
    if ($type == 'app9'):

        $dst = APP_PATH . '/storage/temp/app9_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_9.html';

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
            $content = str_replace('[CANCELLED]', $cancel_sign_horizontal, $content);
        } else {
            $content = str_replace('[CANCELLED]', '', $content);
        }

        $n_1 = 0;
        $n_2 = 0;
        $n_3 = 0;
        $n_4 = 0;
        $n_5 = 0;
        $n_6 = 0;
        $n_7 = 0;
        $n_8 = 0;
        $n_1c = 0;
        $n_2c = 0;
        $n_3c = 0;
        $n_4c = 0;
        $n_5c = 0;
        $n_6c = 0;
        $n_7c = 0;
        $n_8c = 0;
        $n_total = 0;

        foreach ($car as $i => $c) {
            if ($c->ref_car_cat >= 3 && $c->ref_car_cat <= 8) {
                if ($c->volume <= 2500) {
                    $n_1 += $c->cost;
                    $n_1c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 2500 && $c->volume <= 3500) {
                    $n_2 += $c->cost;
                    $n_2c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 3500 && $c->volume <= 5000) {
                    $n_3 += $c->cost;
                    $n_3c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 5000 && $c->volume <= 8000) {
                    $n_4 += $c->cost;
                    $n_4c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 8000 && $c->volume <= 12000) {
                    $n_5 += $c->cost;
                    $n_5c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 12000 && $c->volume <= 20000) {
                    $n_6 += $c->cost;
                    $n_6c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 20000 && $c->volume <= 50000 && $c->ref_st_type == 0) {
                    $n_7 += $c->cost;
                    $n_7c++;
                    $n_total += $c->cost;
                }
                if ($c->volume > 20000 && $c->volume <= 50000 && $c->ref_st_type == 1) {
                    $n_8 += $c->cost;
                    $n_8c++;
                    $n_total += $c->cost;
                }
            }
        }

        // сложные расчеты )))
        $_count = count($car);
        $_minus = 0;
        if ($_count > 0) {
            $_minus = ($f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c) / $_count;
        }
        $n_1 = $n_1 > 0 ? $n_1 - $_minus * $n_1c : 0;
        $n_2 = $n_2 > 0 ? $n_2 - $_minus * $n_2c : 0;
        $n_3 = $n_3 > 0 ? $n_3 - $_minus * $n_3c : 0;
        $n_4 = $n_4 > 0 ? $n_4 - $_minus * $n_4c : 0;
        $n_5 = $n_5 > 0 ? $n_5 - $_minus * $n_5c : 0;
        $n_6 = $n_6 > 0 ? $n_6 - $_minus * $n_6c : 0;
        $n_7 = $n_7 > 0 ? $n_7 - $_minus * $n_7c : 0;
        $n_8 = $n_8 > 0 ? $n_8 - $_minus * $n_8c : 0;
        $n_total = $n_total > 0 ? $n_total - $_minus * $_count : 0;

        $content = str_replace('[N_1]', __money($n_1), $content);
        $content = str_replace('[N_2]', __money($n_2), $content);
        $content = str_replace('[N_3]', __money($n_3), $content);
        $content = str_replace('[N_4]', __money($n_4), $content);
        $content = str_replace('[N_5]', __money($n_5), $content);
        $content = str_replace('[N_6]', __money($n_6), $content);
        $content = str_replace('[N_7]', __money($n_7), $content);
        $content = str_replace('[N_8]', __money($n_8), $content);
        $content = str_replace('[N_1C]', __money($n_1c), $content);
        $content = str_replace('[N_2C]', __money($n_2c), $content);
        $content = str_replace('[N_3C]', __money($n_3c), $content);
        $content = str_replace('[N_4C]', __money($n_4c), $content);
        $content = str_replace('[N_5C]', __money($n_5c), $content);
        $content = str_replace('[N_6C]', __money($n_6c), $content);
        $content = str_replace('[N_7C]', __money($n_7c), $content);
        $content = str_replace('[N_8C]', __money($n_8c), $content);
        $content = str_replace('[N_TOTAL]', __money($n_total), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app9_' . $pid . '.pdf', 'landscape');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app9';
            $f->original_name = 'ПодписанноеПриложение9_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app9_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app9_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 10
     ******************************************************************************************************************/
    if ($type == 'app10'):

        $dst = APP_PATH . '/storage/temp/app10_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_10.html';

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
            $content = str_replace('[CANCELLED]', $cancel_sign_horizontal, $content);
        } else {
            $content = str_replace('[CANCELLED]', '', $content);
        }

        $t_1 = 0;
        $t_2 = 0;
        $t_3 = 0;
        $t_4 = 0;
        $t_5 = 0;
        $t_6 = 0;
        $t_7 = 0;
        $t_8 = 0;
        $t_9 = 0;
        $t_10 = 0;
        $t_11 = 0;
        $t_12 = 0;
        $t_13 = 0;
        $t_14 = 0;
        $t_1c = 0;
        $t_2c = 0;
        $t_3c = 0;
        $t_4c = 0;
        $t_5c = 0;
        $t_6c = 0;
        $t_7c = 0;
        $t_8c = 0;
        $t_9c = 0;
        $t_10c = 0;
        $t_11c = 0;
        $t_12c = 0;
        $t_13c = 0;
        $t_14c = 0;
        $t_total = 0;

        foreach ($car as $i => $c) {

            if ($c->ref_car_cat == 13) {
                if ($c->volume <= 60) {
                    $t_1 += $c->cost;
                    $t_1c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 61 && $c->volume <= 130) {
                    $t_2 += $c->cost;
                    $t_2c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 131 && $c->volume <= 220) {
                    $t_3 += $c->cost;
                    $t_3c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 221 && $c->volume <= 340) {
                    $t_4 += $c->cost;
                    $t_4c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 341 && $c->volume <= 380) {
                    $t_5 += $c->cost;
                    $t_5c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 381 && $c->volume <= 9999) {
                    $t_6 += $c->cost;
                    $t_6c++;
                    $t_total += $c->cost;
                }
            }
            // комбайны
            if ($c->ref_car_cat == 14) {
                if ($c->volume <= 160) {
                    $t_7 += $c->cost;
                    $t_7c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 161 && $c->volume <= 220) {
                    $t_8 += $c->cost;
                    $t_8c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 221 && $c->volume <= 255) {
                    $t_9 += $c->cost;
                    $t_9c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 256 && $c->volume <= 325) {
                    $t_10 += $c->cost;
                    $t_10c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 326 && $c->volume <= 400) {
                    $t_11 += $c->cost;
                    $t_11c++;
                    $t_total += $c->cost;
                }
                if ($c->volume >= 401 && $c->volume <= 9999) {
                    $t_12 += $c->cost;
                    $t_12c++;
                    $t_total += $c->cost;
                }
            }
        }

        // сложные расчеты )))
        $_count = count($car);
        $_minus = ($f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c) / $_count;

        $t_1 = $t_1 > 0 ? $t_1 - $_minus * $t_1c : 0;
        $t_2 = $t_2 > 0 ? $t_2 - $_minus * $t_2c : 0;
        $t_3 = $t_3 > 0 ? $t_3 - $_minus * $t_3c : 0;
        $t_4 = $t_4 > 0 ? $t_4 - $_minus * $t_4c : 0;
        $t_5 = $t_5 > 0 ? $t_5 - $_minus * $t_5c : 0;
        $t_6 = $t_6 > 0 ? $t_6 - $_minus * $t_6c : 0;
        $t_7 = $t_7 > 0 ? $t_7 - $_minus * $t_7c : 0;
        $t_8 = $t_8 > 0 ? $t_8 - $_minus * $t_8c : 0;
        $t_9 = $t_9 > 0 ? $t_9 - $_minus * $t_9c : 0;
        $t_10 = $t_10 > 0 ? $t_10 - $_minus * $t_10c : 0;
        $t_11 = $t_11 > 0 ? $t_11 - $_minus * $t_11c : 0;
        $t_12 = $t_12 > 0 ? $t_12 - $_minus * $t_12c : 0;
        $t_13 = $t_13 > 0 ? $t_13 - $_minus * $t_13c : 0;
        $t_14 = $t_14 > 0 ? $t_14 - $_minus * $t_14c : 0;
        $t_total = $t_total > 0 ? $t_total - $_minus * $_count : 0;

        $content = str_replace('[T_1]', __money($t_1), $content);
        $content = str_replace('[T_2]', __money($t_2), $content);
        $content = str_replace('[T_3]', __money($t_3), $content);
        $content = str_replace('[T_4]', __money($t_4), $content);
        $content = str_replace('[T_5]', __money($t_5), $content);
        $content = str_replace('[T_6]', __money($t_6), $content);
        $content = str_replace('[T_7]', __money($t_7), $content);
        $content = str_replace('[T_8]', __money($t_8), $content);
        $content = str_replace('[T_9]', __money($t_9), $content);
        $content = str_replace('[T_10]', __money($t_10), $content);
        $content = str_replace('[T_11]', __money($t_11), $content);
        $content = str_replace('[T_12]', __money($t_12), $content);
        $content = str_replace('[T_13]', __money($t_13), $content);
        $content = str_replace('[T_14]', __money($t_14), $content);
        $content = str_replace('[T_1C]', __money($t_1c), $content);
        $content = str_replace('[T_2C]', __money($t_2c), $content);
        $content = str_replace('[T_3C]', __money($t_3c), $content);
        $content = str_replace('[T_4C]', __money($t_4c), $content);
        $content = str_replace('[T_5C]', __money($t_5c), $content);
        $content = str_replace('[T_6C]', __money($t_6c), $content);
        $content = str_replace('[T_7C]', __money($t_7c), $content);
        $content = str_replace('[T_8C]', __money($t_8c), $content);
        $content = str_replace('[T_9C]', __money($t_9c), $content);
        $content = str_replace('[T_10C]', __money($t_10c), $content);
        $content = str_replace('[T_11C]', __money($t_11c), $content);
        $content = str_replace('[T_12C]', __money($t_12c), $content);
        $content = str_replace('[T_13C]', __money($t_13c), $content);
        $content = str_replace('[T_14C]', __money($t_14c), $content);
        $content = str_replace('[T_TOTAL]', __money($t_total), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app10_' . $pid . '.pdf', 'landscape');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app10';
            $f->original_name = 'ПодписанноеПриложение10_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app10_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app10_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 11
     ******************************************************************************************************************/
    if ($type == 'app11'):

        $dst = APP_PATH . '/storage/temp/app11_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_11.html';

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

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app11_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app11';
            $f->original_name = 'ПодписанноеПриложение11_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app11_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app11_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 12
     ******************************************************************************************************************/
    if ($type == 'app12'):

        $dst = APP_PATH . '/storage/temp/app12_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_12.html';

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

        $map = [
            1 => 'M1', 2 => 'M1G', 3 => 'N1', 4 => 'N2',
            5 => 'N3', 6 => 'N1G', 7 => 'N2G', 8 => 'N3G',
            9 => 'M2', 10 => 'M3', 11 => 'M2G', 12 => 'M3G',
        ];

        $ec_list = array_fill_keys(array_values($map), 0);
        $ec_total = 0;

        foreach ($car as $c) {
            $cat = (int)$c->ref_car_cat;
            if (isset($map[$cat])) {
                $key = $map[$cat];
                $ec_list[$key]++; // ключ точно существует
                $ec_total++;
            }
        }

        ksort($ec_list);

        $ec_list_html = '';

        foreach ($ec_list as $ec_title => $ec_sum) {
            $ec_list_html .= '<tr><td>' . $ec_title . '</td><td>' . __money($ec_sum) . '</td></tr>';
        }

        $content = str_replace('[EC_LIST]', $ec_list_html, $content);
        $content = str_replace('[EC_TOTAL]', __money($ec_total), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app12_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app12';
            $f->original_name = 'ПодписанноеПриложение12_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app12_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app12_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 13
     ******************************************************************************************************************/
    if ($type == 'app13'):

        $dst = APP_PATH . '/storage/temp/app12_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_13.html';

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

        $tt_list = array();
        $tt_total = 0;

        foreach ($car as $i => $c) {
            if ($c->ref_car_cat == 13) {
                if ($c->volume <= 60) {
                    $tt_total++;
                    $tt_list['Трактор, до 60 л.с.']++;
                }
                if ($c->volume >= 61 && $c->volume <= 130) {
                    $tt_total++;
                    $tt_list['Трактор, от 61 до 130 л.с.']++;
                }
                if ($c->volume >= 131 && $c->volume <= 220) {
                    $tt_total++;
                    $tt_list['Трактор, от 131 до 220 л.с.']++;
                }
                if ($c->volume >= 221 && $c->volume <= 340) {
                    $tt_total++;
                    $tt_list['Трактор, от 221 до 340 л.с.']++;
                }
                if ($c->volume >= 341 && $c->volume <= 380) {
                    $tt_total++;
                    $tt_list['Трактор, от 341 до 380 л.с.']++;
                }
                if ($c->volume >= 381 && $c->volume <= 9999) {
                    $tt_total++;
                    $tt_list['Трактор, свыше 381 л.с.']++;
                }
            }
            // комбайны
            if ($c->ref_car_cat == 14) {
                if ($c->volume <= 160) {
                    $tt_total++;
                    $tt_list['Комбайн, до 160 л.с.']++;
                }
                if ($c->volume >= 161 && $c->volume <= 220) {
                    $tt_total++;
                    $tt_list['Комбайн, от 161 до 220 л.с.']++;
                }
                if ($c->volume >= 221 && $c->volume <= 255) {
                    $tt_total++;
                    $tt_list['Комбайн, от 221 до 255 л.с.']++;
                }
                if ($c->volume >= 256 && $c->volume <= 325) {
                    $tt_total++;
                    $tt_list['Комбайн, от 256 до 325 л.с.']++;
                }
                if ($c->volume >= 326 && $c->volume <= 400) {
                    $tt_total++;
                    $tt_list['Комбайн, от 326 до 400 л.с.']++;
                }
                if ($c->volume >= 401 && $c->volume <= 9999) {
                    $tt_total++;
                    $tt_list['Комбайн, свыше 401 л.с.']++;
                }
            }
        }

        ksort($tt_list);

        $tt_list_html = '';

        foreach ($tt_list as $tt_title => $tt_sum) {
            $tt_list_html .= '<tr><td>' . $tt_title . '</td><td>' . __money($tt_sum) . '</td></tr>';
        }

        $content = str_replace('[TT_LIST]', $tt_list_html, $content);
        $content = str_replace('[TT_TOTAL]', __money($tt_total), $content);

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app13_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app13';
            $f->original_name = 'ПодписанноеПриложение13_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app13_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app13_' . $pid . '.pdf');
        }

    endif;

    /******************************************************************************************************************
     * ПРИЛОЖЕНИЕ 14
     ******************************************************************************************************************/
    if ($type == 'app14'):

        $dst = APP_PATH . '/storage/temp/app14_' . $pid . '.html';
        $src = APP_PATH . '/app/templates/html/fund/app_14.html';

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

        $__qr_sign_line = '';
        $__qr_sign_line_acc = '';

        if ($f->sign) {
            $__qr_sign_line = getQrImagesHtml($f->sign);
        }

        if ($f->sign_acc) {
            $__qr_sign_line_acc = getQrImagesHtml($f->sign_acc);
        }

        // данные подписанта
        $j_sign_acc = '—';
        if ($j_acc) {
            if ($j_acc['iin']) $j_sign_acc = $j_acc['fio'] . ' (ИИН ' . $j_acc['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j_acc['dt'];
            if ($j_acc['bin']) $j_sign_acc = $j_acc['company'] . ' (БИН ' . $j_acc['bin'] . ') — СОТРУДНИК ' . $j_sign_acc;
        }

        if (!$j) {
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
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/app14_' . $pid . '.pdf');

        if ($j) {
            // добавляем файл
            $f = new FundFile();
            $f->fund_id = $pid;
            $f->type = 'fund_app14';
            $f->original_name = 'ПодписанноеПриложение14_' . $pid . '.pdf';
            $f->ext = 'pdf';
            $f->visible = 1;
            $f->save();
            copy(APP_PATH . '/storage/temp/app14_' . $pid . '.pdf', APP_PATH . '/private/fund/' . $f->id . '.pdf');
            unlink(APP_PATH . '/storage/temp/app14_' . $pid . '.pdf');
        }

    endif;
}

function __genApplication($pid, $context, $j)
{
    $profile = Profile::findFirstById($pid);

    // данные подписанта
    $j_sign = '—';
    if ($j['iin']) {
        $j_sign = $j['fio'] . ' (ИИН ' . $j['iin'] . '), ПЕРИОД ДЕЙСТВИЯ ' . $j['dt'];
    }
    // если это компания
    if ($j['bin']) {
        $j_sign = $j['company'] . ' (БИН ' . $j['bin'] . ') — СОТРУДНИК ' . $j_sign;
    }

    if ($profile->type == 'CAR') {
        $src = APP_PATH . '/app/templates/html/application/application.html';
        $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

        $content = file_get_contents($src);

        $tr = Transaction::findFirstByProfileId($profile->id);
        $user = User::findFirstById($profile->user_id);

        if ($user->user_type_id == PERSON) {
            $pd = PersonDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>ИИН: </strong>' . $pd->iin;
            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                $user_fio = '<strong>' . $fio_bottom . '</strong>';
            }
            // $fio_line = '______________________________<br />';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        } else {
            // а это для ЮЛ
            $pd = CompanyDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>БИН: </strong>' . $pd->bin;

            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                $user_fio = '<strong>' . $fio_bottom . '</strong>';
            }
            // $fio_line = '______________________________<br />М.П.';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        }

        $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
        $content = str_replace('[Z_DATE]', date("d.m.Y", $profile->sign_date), $content);
        $content = str_replace('[ZA_CITY]', '<strong>Город постановки на учет: </strong>' . $city, $content);
        $content = str_replace('[Z_CITY]', (string)($city ?? ''), $content);
        $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
        $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
        $content = str_replace('[ZA_NAME]', $name, $content);
        $content = str_replace('[ZA_IIN]', '' . $iin, $content);
        $content = str_replace('[Z_FIO]', '' . $user_fio, $content);
        $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
        $content = str_replace('[Z_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . ($profile->hash ? mb_strtoupper(genAppHash($profile->hash)) : '') . '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . ($profile->sign ? mb_strtoupper(genAppHash($profile->sign)) : '') . '</p><p><strong>ДАННЫЕ ЭЦП:</strong> ' . mb_strtoupper($j_sign) . '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') . '</p>', $content);
        $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

        $query = $context->modelsManager->createQuery("
        SELECT
          c.volume AS volume,
          c.vin AS vin,
          c.year AS year,
          c.cost AS cost,
          cc.name AS cat,
          c.date_import AS date_import,
          country.name AS country,
          countryImport.name AS country_import
        FROM
          Car c
          JOIN Profile p
          JOIN RefCountry country ON country.id = c.ref_country
          JOIN RefCountry countryImport ON countryImport.id = c.ref_country_import
          JOIN RefCarCat cc
        WHERE
          p.id = :pid: AND
          country.id = c.ref_country AND
          cc.id = c.ref_car_cat AND
          c.profile_id = p.id
        GROUP BY c.id
        ORDER BY c.id ASC");

        $cars = $query->execute(array(
            "pid" => $profile->id
        ));

        $c = 1;
        $car_content = '';
        foreach ($cars as $key => $v) {
            $car_content = $car_content . '<tr><td>' . $c . '.</td><td>' . $v->volume . '</td><td>' . $v->vin . '</td><td>' . $v->year . '</td><td>' . $v->country . '</td><td>' . $v->country_import . '</td><td>' . date("d.m.Y", $v->date_import) . '</td><td>' . mb_strtoupper(str_replace('cat-', '', $v->cat)) . '</td><td>' . number_format($v->cost, 2, ",", "&nbsp;") . '</td></tr>';
            $c++;
        }

        $content = str_replace('[Z_CONTENT]', $car_content, $content);
        file_put_contents($dst, $content);
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

    } elseif ($profile->type == 'GOODS') {
        $src = APP_PATH . '/app/templates/html/application_goods/application.html';
        $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

        $content = file_get_contents($src);

        $tr = Transaction::findFirstByProfileId($profile->id);
        $user = User::findFirstById($profile->user_id);

        if ($user->user_type_id == PERSON) {
            $pd = PersonDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>ИИН: </strong>' . $pd->iin;

            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                $user_fio = '<strong>' . $fio_bottom . '</strong> ';
            }

            // $fio_line = '______________________________<br />';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        } else {
            // а это для ЮЛ
            $pd = CompanyDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>БИН: </strong>' . $pd->bin;

            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $user_fio = '<strong>' . $pd->name . '</strong>';
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            }

            // $fio_line = '______________________________<br />М.П.';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        }

        $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
        $content = str_replace('[Z_DATE]', date("d.m.Y", $profile->sign_date), $content);
        $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
        $content = str_replace('[Z_CITY]', $city, $content);
        $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
        $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
        $content = str_replace('[ZA_NAME]', $name, $content);
        $content = str_replace('[ZA_IIN]', '' . $iin, $content);
        $content = str_replace('[Z_FIO]', '' . $user_fio, $content);
        $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
        $content = str_replace('[Z_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($profile->hash)) . '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($profile->sign)) . '</p><p><strong>ДАННЫЕ ЭЦП:</strong> ' . mb_strtoupper($j_sign) . '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') . '</p>', $content);
        $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

        $query = $context->modelsManager->createQuery("
        SELECT
          g.weight AS g_weight,
          g.date_import AS g_date,
          g.basis AS g_basis,
          g.amount AS g_amount,
          g.goods_cost AS g_cost,
          tn.code AS tn_code,
          g.ref_tn_add AS tn_add,
          g.basis_date AS basis_date,
          g.package_weight AS package_weight,
          g.package_cost AS package_cost     
        FROM
          Goods g
          JOIN Profile p
          JOIN RefTnCode tn
        WHERE
          p.id = :pid: AND
          tn.id = g.ref_tn AND
          g.profile_id = p.id
        GROUP BY g.id
        ORDER BY g.id DESC");

        $goods = $query->execute(array(
            "pid" => $profile->id
        ));

        $c = 1;
        $goods_content = '';
        foreach ($goods as $key => $v) {
            $good_tn_add = '';
            $tn_add = false;
            if ($v->tn_add) {
                $tn_add = RefTnCode::findFirstById($v->tn_add);
                if ($tn_add) {
                    $good_tn_add = ' (упаковано ' . ($tn_add ? $tn_add->code : '') . ')';
                }
            }

            $basis_arr = preg_split('/[\s,]+/', $v->g_basis);
            $basis_str = NULL;

            foreach ($basis_arr as $val) {
                $basis_str .= $val . '<br>';
            }

            $goods_content = $goods_content . '<tr><td>' . $c . '</td>
                                       <td>' . $v->tn_code . $good_tn_add . '</td>
                                       <td>' . date("d.m.Y", $v->g_date) . '</td>
                                       <td>' . $basis_str . '</td>
                                       <td>' . date("d.m.Y", $v->basis_date) . '</td>
                                       <td>' . $v->g_weight . '</td>
                                       <td>' . __money($v->g_cost) . '</td>
                                       <td>' . ($tn_add ? $tn_add->code : '') . '</td>
                                       <td>' . $v->package_weight . '</td>
                                       <td>' . __money($v->package_cost) . '</td>
                                       <td>' . __money($v->g_amount) . '</td></tr>';
            $c++;
        }

        $content = str_replace('[Z_CONTENT]', $goods_content, $content);
        file_put_contents($dst, $content);
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf', 'landscape');

    } elseif ($profile->type == 'KPP') {
        $src = APP_PATH . '/app/templates/html/application_kpp/application.html';
        $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

        $content = file_get_contents($src);

        $tr = Transaction::findFirstByProfileId($profile->id);
        $user = User::findFirstById($profile->user_id);

        if ($user->user_type_id == PERSON) {
            $pd = PersonDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>ИИН: </strong>' . $pd->iin;

            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                $user_fio = '<strong>' . $fio_bottom . '</strong> ';
            }

            // $fio_line = '______________________________<br />';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        } else {
            // а это для ЮЛ
            $pd = CompanyDetail::findFirstByUserId($user->id);
            $cd = ContactDetail::findFirstByUserId($user->id);

            $iin = '<strong>БИН: </strong>' . $pd->bin;

            if ($profile->moderator_id != Null || $profile->moderator_id != '') {
                $supermoder = PersonDetail::findFirstByUserId($profile->moderator_id);
                $user_fio = '<strong>ЗАЯВКА СОЗДАНА СОТРУДНИКОМ АО "Жасыл Даму":</strong> ' . $supermoder->last_name . ' ' . $supermoder->first_name . ' ' . $supermoder->parent_name;
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            } else {
                $user_fio = '<strong>' . $pd->name . '</strong>';
                $fio_bottom = $pd->name;
                $name = '<strong>Импортер: </strong>' . $fio_bottom;
            }

            // $fio_line = '______________________________<br />М.П.';
            $fio_line = '';
            $city = $cd->city;
            $address = $cd->address;
            $phone = $cd->phone;
        }

        $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
        $content = str_replace('[Z_DATE]', date("d.m.Y", $profile->sign_date), $content);
        $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
        $content = str_replace('[Z_CITY]', $city, $content);
        $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
        $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
        $content = str_replace('[ZA_NAME]', $name, $content);
        $content = str_replace('[ZA_IIN]', '' . $iin, $content);
        $content = str_replace('[Z_FIO]', '' . $user_fio, $content);
        $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
        $content = str_replace('[Z_SIGN]', '<p class="code"><strong>ДАННЫЕ ДЛЯ ПРОВЕРКИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($profile->hash)) . '</p><p class="code"><strong>ПРОВЕРКА ПОДПИСИ ДОКУМЕНТА:</strong> ' . mb_strtoupper(genAppHash($profile->sign)) . '</p><p><strong>ДАННЫЕ ЭЦП:</strong> ' . mb_strtoupper($j_sign) . '</p><p><strong>ДАТА И ВРЕМЯ ПОДПИСИ:</strong> ' . date('d.m.Y H:i') . '</p>', $content);
        $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

        $query = $context->modelsManager->createQuery("
        SELECT
          g.weight AS g_weight,
          g.date_import AS g_date,
          g.basis AS g_basis,
          g.basis_date AS basis_date,
          g.amount AS g_amount,
          g.invoice_sum AS g_invoice_sum,
          g.invoice_sum_currency AS g_invoice_sum_currency,
          g.currency_type AS g_currency_type,
          tn.code AS tn_code,   
          g.package_weight AS package_weight,   
          g.package_cost AS package_cost,   
          g.package_tn_code AS package_tn_code  
        FROM
          Kpp g
          JOIN Profile p
          JOIN RefTnCode tn
        WHERE
          p.id = :pid: AND
          tn.id = g.ref_tn AND
          g.profile_id = p.id
        GROUP BY g.id
        ORDER BY g.id DESC");

        $kpp = $query->execute(array(
            "pid" => $profile->id
        ));

        $c = 1;
        $kpp_content = '';
        foreach ($kpp as $key => $v) {
            $p_tn_code = '';
            if ($v->package_tn_code > 0) {
                $p_tn_code = $v->package_tn_code;
            }
            $kpp_content .= '<tr>';
            $kpp_content .= '<td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . date("d.m.Y", $v->g_date) . '</td>';
            $kpp_content .= '<td>' . $v->g_basis . '</td><td>' . date("d.m.Y", $v->basis_date) . '</td><td>' . $v->g_weight . '</td>';
            $kpp_content .= '<td>' . __money(round($v->g_amount - $v->package_cost, 2)) . '</td>';
            $kpp_content .= '<td>' . $p_tn_code . '</td><td>' . $v->package_weight . '</td><td>' . __money($v->package_cost) . '</td>';
            $kpp_content .= '<tr>';
            $c++;
        }

        $content = str_replace('[Z_CONTENT]', $kpp_content, $content);
        file_put_contents($dst, $content);
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf', 'landscape');

    }

    $auth = User::getUserBySession();

    // добавляем файл
    $f = new File();
    $f->profile_id = $pid;
    $f->type = 'application';
    $f->original_name = 'ПодписанноеЗаявление_' . $pid . '.pdf';
    $f->ext = 'pdf';
    $f->good_id = 0;
    $f->visible = 1;
    $f->created_by = $auth->id;
    $f->save();
    copy(APP_PATH . '/storage/temp/application_' . $pid . '.pdf', APP_PATH . '/private/docs/' . $f->id . '.pdf');
    unlink(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
}

function __money($a)
{
    return number_format($a, 2, ",", " ");
}

function __weight($a)
{
    return number_format($a, 3, ",", " ");
}

/**
 * Рекурсивное удаление директорий.
 * @param string $dir путь к директории
 * @return void
 */
function __rmDir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                if (filetype($dir . '/' . $object) == 'dir') {
                    __rmDir($dir . '/' . $object);
                } else {
                    unlink($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Рекурсивное копирование директорий.
 * @param string $src откуда
 * @param string $dst куда
 * @return bool
 */
function __cpDir($src, $dst)
{
    $dir = opendir($src);
    $result = ($dir === false ? false : true);

    if ($result !== false) {
        $result = @mkdir($dst);

        if ($result === true) {
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..') && $result) {
                    if (is_dir($src . '/' . $file)) {
                        $result = __cpDir($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        $result = copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }
    }

    return $result;
}

/**
 * Переводим число в строку прописью.
 * @param float $num
 * @return string
 */
function __numToStr($num)
{
    $nul = 'ноль';

    $ten = array(
        array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
        array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
    );

    $a20 = array('десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать');
    $tens = array(2 => 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто');
    $hundred = array('', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот');

    $unit = array(
        array('тиын', 'тиына', 'тиын', 1),
        array('тенге', 'тенге', 'тенге', 0),
        array('тысяча', 'тысячи', 'тысяч', 1),
        array('миллион', 'миллиона', 'миллионов', 0),
        array('миллиард', 'миллиарда', 'миллиардов', 0),
    );

    list($tg, $tn) = explode('.', sprintf("%015.2f", floatval($num)));

    $out = array();
    if (intval($tg) > 0) {
        foreach (str_split($tg, 3) as $uk => $v) {
            if (!intval($v)) {
                continue;
            }
            $uk = sizeof($unit) - $uk - 1;
            $gender = $unit[$uk][3];
            list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
            $out[] = $hundred[$i1];
            if ($i2 > 1) {
                $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];
            } else {
                $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];
            }
            if ($uk > 1) {
                $out[] = __morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
            }
        }
    } else {
        $out[] = $nul;
    }

    $out[] = __morph(intval($tg), $unit[1][0], $unit[1][1], $unit[1][2]);
    $out[] = $tn . ' ' . __morph($tn, $unit[0][0], $unit[0][1], $unit[0][2]);
    return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
}

/**
 * Морфинг элементов числа (5000 → пять тысяч).
 * @param integer $n
 * @param string $f1
 * @param string $f2
 * @param string $f5
 * @return string
 */
function __morph($n, $f1, $f2, $f5)
{
    $n = abs(intval($n)) % 100;
    if ($n > 10 && $n < 20) {
        return $f5;
    }
    $n = $n % 10;
    if ($n > 1 && $n < 5) {
        return $f2;
    }
    if ($n == 1) {
        return $f1;
    }
    return $f5;
}

/**
 * Пишем с первой заглавной.
 * @param string $str
 * @return string
 */
function __ucFirst($str)
{
    $fc = mb_strtoupper(mb_substr($str, 0, 1));
    return $fc . mb_substr($str, 1);
}

/**
 * Универсальный эксплод для CSV-файлов.
 * @param array $delimiters
 * @param string $string
 * @return array
 */
function __multiExplode($delimiters, $string)
{
    $ready = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return $launch;
}

/**
 * Конвертация в латиницу.
 */
function __ruLat($str)
{
    $tr = array(
        "А" => "a", "Б" => "b", "В" => "v", "Г" => "g", "Д" => "d",
        "Е" => "e", "Ё" => "yo", "Ж" => "zh", "З" => "z", "И" => "i",
        "Й" => "j", "К" => "k", "Л" => "l", "М" => "m", "Н" => "n",
        "О" => "o", "П" => "p", "Р" => "r", "С" => "s", "Т" => "t",
        "У" => "u", "Ф" => "f", "Х" => "kh", "Ц" => "ts", "Ч" => "ch",
        "Ш" => "sh", "Щ" => "sch", "Ъ" => "", "Ы" => "y", "Ь" => "",
        "Э" => "e", "Ю" => "yu", "Я" => "ya", "а" => "a", "б" => "b",
        "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ё" => "yo",
        "ж" => "zh", "з" => "z", "и" => "i", "й" => "j", "к" => "k",
        "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p",
        "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f",
        "х" => "kh", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch",
        "ъ" => "", "ы" => "y", "ь" => "", "э" => "e", "ю" => "yu",
        "я" => "ya", " " => "_", ".." => ".", "," => "_", "/" => "_",
        ":" => "", ";" => "", "—" => "", "–" => "-"
    );
    return strtr($str, $tr);
}

function __fundRecalc($id)
{
    $f = FundProfile::findFirstById($id);
    $additionals = $f->w_a + $f->w_b + $f->w_c + $f->w_d + $f->e_a + $f->r_a + $f->r_b + $f->r_c + $f->tc_a + $f->tc_b + $f->tc_c + $f->tt_a + $f->tt_b + $f->tt_c;

    $c = FundCar::sum([
        'column' => 'cost',
        'conditions' => 'fund_id = ' . $id
    ]);

    $cars = $c;

    if (!$cars) {
        $f->amount = $additionals;
    } else {
        $f->amount = $cars;
    }
    $f->save();
}

function __carRecalc($id)
{
    $tr = Transaction::findFirstByProfileId($id);

    $c = Car::sum([
        'column' => 'cost',
        'conditions' => 'profile_id = ' . $id
    ]);

    $cars = $c;

    if (!$cars) {
        $tr->amount = 0;
    } else {
        $tr->amount = $cars;
    }
    $tr->save();
}

function __goodRecalc($id)
{
    $tr = Transaction::findFirstByProfileId($id);

    $c = Goods::sum([
        'column' => 'amount',
        'conditions' => 'profile_id = ' . $id
    ]);

    $goods = $c;

    if (!$goods) {
        $tr->amount = 0;
    } else {
        $tr->amount = $goods;
    }
    $tr->save();
}

function __checkHOC($eku)
{
    $auth = User::getUserBySession();
    if (in_array($auth->idnum, [
        '960213350271',
    ])) {
        if ($auth->accountant) {
            return false;
        } else {
            return true;
        }
    }

    if (strstr($eku, '1.2.398.3.3.4.1.2.1')) {
        // да, это первый руководитель
        return true;
    } else {
        // простой смертный
        return false;
    }
}

function __checkCompany($eku)
{
    if (strstr($eku, '1.2.398.3.3.4.1.2')) {
        // да, это Юр.лицо
        return true;
    } else {
        // нет, это Физ.лицо
        return false;
    }
}

function __getCat($name)
{
    return mb_strtoupper(str_replace(array('cat-', 'tractor', 'combain'), array('', 'ТРАКТОР', 'КОМБАЙН'), $name));
}

function __checkRefFund($idnum, $start, $end, $year, $key)
{
    $ref = RefFund::findFirst(array(
        "idnum = :idnum: AND prod_start = :start: AND prod_end = :end: AND year = :year: AND key = :key:",
        "bind" => array(
            "idnum" => $idnum,
            "start" => $start,
            "end" => $end,
            "year" => $year,
            "key" => $key
        ),
        'order' => 'id DESC'
    ));

    // возвращаем
    if ($ref) {
        return $ref;
    }

    return false;
}

function __getProfileNumber($num)
{
    return str_pad($num, 8, 0, STR_PAD_LEFT);
}

function __getFundNumber($num)
{
    $year = 'ERROR';
    $pfx = 'NONE';

    $f = FundProfile::findFirstById($num);
    $year = date('Y', $f->created);

    $u = User::findFirstById($f->user_id);
    $prefix = RefPrefix::findFirstByBin($u->idnum);
    $pfx = $prefix ? $prefix->prefix : 'NONE';

    return "$pfx-$year/" . str_pad($num, 4, 0, STR_PAD_LEFT);
}

function __getRefFundKeyDescription($key)
{
    $ref_fund_key = RefFundKeys::findFirstByName($key);

    if ($ref_fund_key) {
        return $ref_fund_key->description;
    } else {
        return false;
    }
}

function __getRefFundTnCodeKeyDescription($key)
{
    $ref_fund_key = RefFundKeys::findFirstByName($key);

    if ($ref_fund_key) {
        $ref_tn_code = RefTnCode::findFirstByCode($ref_fund_key->name);
        return $ref_tn_code->code . ', ' . $ref_tn_code->name;
    } else {
        return false;
    }
}


function __checkPayment($vin)
{
    $car = Car::findFirstByVin($vin);

    if ($car) {
        return $car->profile_id;
    }

    return false;
}

// Проверка ДПП
function __checkDPP($vin, $car_volume)
{

    $car = Car::findFirstByVin($vin);
    $tr = Transaction::findFirstByProfileId($car->profile_id);

    if ($car && $tr && $tr->ac_approve == 'SIGNED' && $car->volume == $car_volume && $tr->approve == 'GLOBAL') {
        return false;
    }

    return true;
}

// Проверка ДПП(for excel check)
function __checkDPPForExcel($vin)
{
    $car = Car::findFirstByVin($vin);

    if ($car) {
        $tr = Transaction::findFirstByProfileId($car->profile_id);
        if ($car->status == NULL) {
            $car_cat = RefCarCat::findFirstById($car->ref_car_cat);
            $car_type = RefCarType::findFirstById($car->ref_car_type_id);
            $car_country = RefCountry::findFirstByid($car->ref_country);
            if ($car_cat && $car_type && $car_country) {
                $data = array(
                    //car description
                    'car_id' => $car->id,
                    'volume' => $car->volume,
                    'cost' => $car->cost,
                    'year' => $car->year,
                    'car_cat' => $car_cat->name,
                    'car_type' => $car_type->name,
                    'st_type' => $car->ref_st_type,
                    'country' => $car_country->name,
                    //transaction description
                    'profile_id' => $tr->profile_id,
                    'amount' => $tr->amount,
                    'status' => $tr->status,
                    'approve' => $tr->approve,
                    'dt_approve' => $tr->dt_approve,
                    'ac_approve' => $tr->ac_approve,
                    'ac_dt_approve' => $tr->ac_dt_approve,
                );
                return $data;
            }
        }
    }
    return false;
}

function __checkInner($vin)
{
    $inner = TInner::findFirstByVin($vin);

    if ($inner) {
        return true;
    }

    return false;
}

function __checkExport($vin)
{
    $export = TExport::findFirstByVin($vin);

    if ($export) {
        return true;
    }

    return false;
}

function __isValidXml($content)
{
    $content = trim($content);
    if (empty($content)) {
        return false;
    }
    //html go to hell!
    if (stripos($content, '<!DOCTYPE html>') !== false) {
        return false;
    }

    libxml_use_internal_errors(true);
    simplexml_load_string($content);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    return empty($errors);
}

function __checkLimits($f, $type, $volume, $opt, $prod, $year)
{
    $ref_st = '';
    $f = FundProfile::findFirstById($f);
    $u = User::findFirstById($f->user_id);

    $exp_flag = '';
    if ($f->type == 'EXP') {
        $exp_flag = '_EXP';
    }

    $cat = RefCarCat::findFirstById($type);

    //для финансирования по электромобилям ставки не такие как в УП, поэтому отдельная проверка и ставка в таблице ref_car_value
    if ($volume == 0) {
        $value = RefCarValue::findFirst(array(
            "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
            "bind" => array(
                "car_type" => $cat->car_type,
                "volume_start" => $volume,
                "volume_end" => $volume
            )
        ));
    } else {
        $value = RefCarValue::findFirst(array(
            "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
            "bind" => array(
                "car_type" => $cat->car_type,
                "volume_start" => $volume,
                "volume_end" => $volume
            )
        ));
    }

    $rf = RefFund::findFirst([
        "conditions" => "key = :key: AND idnum = :idnum: AND prod_start <= :prod_start: AND prod_end >= :prod_end: AND year = :year:",
        "bind" => [
            "key" => $f->ref_fund_key,
            "idnum" => $u->idnum,
            "prod_start" => $prod,
            "prod_end" => $prod,
            "year" => $year,
        ],
        'order' => 'id DESC'
    ]);

    if ($f->type == 'EXP') {
        if ($rf) {
            $__created = 0;
            $fc = FundProfile::find([
                "conditions" => "created < :end: AND created > :start: AND user_id = :user_id: AND type = :type:",
                "bind" => [
                    "end" => strtotime("31.12.$year 23:59:59"),
                    "start" => strtotime("01.01.$year 00:00:00"),
                    "user_id" => $f->user_id,
                    "type" => "EXP"
                ]
            ]);

            foreach ($fc as $t_f) {
                $__created += FundCar::count([
                    "conditions" => "fund_id = :fund_id: AND date_produce < :end: AND date_produce > :start: AND ref_car_type_id = :cat: AND volume >= :vs: AND volume <= :ve: AND ref_st_type = :opt:",
                    "bind" => [
                        "fund_id" => $t_f->id,
                        "end" => $rf->prod_end,
                        "start" => $rf->prod_start,
                        "cat" => $cat->car_type,
                        "vs" => $value->volume_start,
                        "ve" => $value->volume_end,
                        "opt" => $opt
                    ]
                ]);
            }

            return $rf->value - $__created;
        }
    } else {
        if ($rf) {
            $__created = 0;
            $fc = FundProfile::find([
                "conditions" => "created < :end: AND created > :start: AND user_id = :user_id: AND type = :type:",
                "bind" => [
                    "end" => strtotime("31.12.$year 23:59:59"),
                    "start" => strtotime("01.01.$year 00:00:00"),
                    "user_id" => $f->user_id,
                    "type" => "INS"
                ]
            ]);

            foreach ($fc as $t_f) {
                $__created += FundCar::count([
                    "conditions" => "fund_id = :fund_id: AND date_produce < :end: AND date_produce > :start: AND ref_car_type_id = :cat: AND volume >= :vs: AND volume <= :ve: AND ref_st_type = :opt:",
                    "bind" => [
                        "fund_id" => $t_f->id,
                        "end" => $rf->prod_end,
                        "start" => $rf->prod_start,
                        "cat" => $cat->car_type,
                        "vs" => $value->volume_start,
                        "ve" => $value->volume_end,
                        "opt" => $opt
                    ]
                ]);
            }

            return $rf->value - $__created;
        }
    }

    return 0;
}

function checkLimitRemaining(
    int $profileId,
    int $carCatId,
    int $volume,
    int $optType,
    int $prodTs,   // timestamp даты производства ТС
    int $year
): int
{
    // 1) Профиль и пользователь
    $profile = FundProfile::findFirst([
        "conditions" => "id = :id:",
        "bind" => ["id" => $profileId],
        "columns" => "id, user_id, type, ref_fund_key",
    ]);
    if (!$profile) {
        return 0;
    }

    $user = User::findFirst([
        "conditions" => "id = :id:",
        "bind" => ["id" => $profile->user_id],
        "columns" => "id, idnum",
    ]);
    if (!$user) {
        return 0;
    }

    // 2) Категория и диапазон объёма
    $cat = RefCarCat::findFirst([
        "conditions" => "id = :id:",
        "bind" => ["id" => $carCatId],
        "columns" => "id, car_type",
    ]);
    if (!$cat) {
        return 0;
    }

    $value = ($volume === 0)
        ? RefCarValue::findFirst([
            "conditions" => "car_type = :ct: AND volume_start = 0 AND volume_end = 0",
            "bind" => ["ct" => $cat->car_type],
            "columns" => "volume_start, volume_end",
        ])
        : RefCarValue::findFirst([
            "conditions" => "car_type = :ct: AND :v: BETWEEN volume_start AND volume_end",
            "bind" => ["ct" => $cat->car_type, "v" => $volume],
            "columns" => "volume_start, volume_end",
        ]);
    if (!$value) {
        return 0;
    }

    // 3) Запись лимита на год и дату производства
    $rf = RefFund::findFirst([
        "conditions" =>
            "key = :key: AND idnum = :idnum: " .
            "AND :prod: BETWEEN prod_start AND prod_end " .
            "AND year = :year:",
        "bind" => [
            "key" => $profile->ref_fund_key,
            "idnum" => $user->idnum,
            "prod" => $prodTs,
            "year" => $year,
        ],
        "order" => "id DESC",
        "columns" => "id, value, prod_start, prod_end",
    ]);
    if (!$rf) {
        return 0;
    }

    // 4) Все профили пользователя за год по типу (EXP/INS)
    $start = strtotime("01.01.$year 00:00:00");
    $end = strtotime("31.12.$year 23:59:59");

    $profiles = FundProfile::find([
        "conditions" => "md_dt_sent BETWEEN :s: AND :e: AND user_id = :uid: AND type = :type:",
        "bind" => [
            "s" => $start,
            "e" => $end,
            "uid" => $profile->user_id,
            "type" => $profile->type, // 'EXP' или 'INS'
        ],
        "columns" => "id",
    ]);
    if (!$profiles || count($profiles) === 0) {
        return (int)$rf->value;
    }

    $fundIds = array_map(static fn($p) => (int)$p->id, iterator_to_array($profiles));
    // Если ваш адаптер не поддерживает массивы в IN, соберите плейсхолдеры вручную.

    // 5) Сколько уже отправлено по параметрам
    $sent = FundCar::count([
        "conditions" =>
            "fund_id IN ({ids:array}) " .
            "AND date_produce BETWEEN :ps: AND :pe: " .
            "AND ref_car_type_id = :carType: " .
            "AND volume BETWEEN :vs: AND :ve: " .
            "AND ref_st_type = :opt:",
        "bind" => [
            "ids" => $fundIds,
            "ps" => (int)$rf->prod_start,
            "pe" => (int)$rf->prod_end,
            "carType" => $cat->car_type,
            "vs" => (int)$value->volume_start,
            "ve" => (int)$value->volume_end,
            "opt" => $optType,
        ],
    ]);

    return max(0, (int)$rf->value - (int)$sent);
}

function __getClientTitle($pid)
{

    $title = '';
    $idnum = '';
    $is_agent = false;
    $agent_title = '';

    $p = Profile::findFirstById($pid);

    if ($p) {
        $user = User::findFirstById($p->user_id);
        if ($user) {
            if ($user->role_id == 4) {
                if ($p->agent_name != NULL && $p->agent_iin != NULL) {
                    $is_agent = true;
                    $agent = PersonDetail::findFirstByUserId($p->user_id);
                    $agent_title = $agent->last_name . " " . $agent->first_name . " " . $agent->parent_name;
                    $title = $p->agent_name . "(Агентская заявка)";
                    $idnum = $p->agent_iin;
                }
            } else {
                if ($user->user_type_id == 1) {
                    $pd = PersonDetail::findFirstByUserId($p->user_id);
                    $title = $pd->last_name . " " . $pd->first_name . " " . $pd->parent_name;
                    $idnum = $pd->iin;

                } else {
                    $cd = CompanyDetail::findFirstByUserId($p->user_id);
                    $title = str_replace("&quot;", "\"", $cd->name);
                    $idnum = $cd->bin;
                }
            }

            $data = array(
                'idnum' => $idnum,
                'title' => $title,
                'is_agent' => $is_agent,
                'agent_title' => $agent_title
            );

            return $data;
        } else {
            return 'Пользователь не найден !';
        }
    } else {
        return 'Заявка не найдена !';
    }
}

function __getClientTitleByUserId($id)
{
    $idnum = '';
    $title = '';

    $user = User::findFirstById($id);
    if($user) {
        if ($user->isEmployee()) {
            $name = $user->fio ? $user->fio : '';
            $parts = explode(' ', $name);
            if (count($parts) > 1) {
                array_pop($parts);
                $title = implode(' ', $parts);
            }

            $idnum = $user->idnum;
            return $title . '(' . $idnum . ')/'.$user->org_name .'(' . $user->bin . ')';
        } else {
            $cd = CompanyDetail::findFirstByUserId($id);

            if ($cd) {
                $idnum = $cd->bin;
                $title = str_replace("&quot;", "\"", $cd->name);
            } else {
                $pd = PersonDetail::findFirstByUserId($id);
                if ($pd) {
                    $idnum = $pd->iin;
                    $title = $pd->last_name . " " . $pd->first_name . " " . $pd->parent_name;
                }
            }
        }
    }

    return $title . '(' . $idnum . ')';
}

function __checkRefFundUser($idnum)
{
    $rf = RefFund::findFirstByIdnum($idnum);
    if ($rf) {
        return true;
    } else {
        return false;
    }
}

function __detect_in_cyrillic($text)
{
    $pattern = '/[а-я][А-Я]/i';

    return preg_replace_callback($pattern, '__callbackDetectInCyrillicReplace', $text, -1);
}

function __callbackDetectInCyrillicReplace($matches)
{
    return "<span style='color: red;font-weight: bold'>" . $matches[0] . "</span>";
}

function __checkLimitsWhenSend($f, $type, $volume, $opt, $prod, $year)
{
    $ref_st = '';
    $f = FundProfile::findFirstById($f);
    $u = User::findFirstById($f->user_id);

    $exp_flag = '';
    if ($f->type == 'EXP') {
        $exp_flag = '_EXP';
    }

    $cat = RefCarCat::findFirstById($type);

    if ($volume == 0) {
        $value = RefCarValue::findFirst(array(
            "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
            "bind" => array(
                "car_type" => $cat->car_type,
                "volume_start" => $volume,
                "volume_end" => $volume
            )
        ));
    } else {
        $value = RefCarValue::findFirst(array(
            "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
            "bind" => array(
                "car_type" => $cat->car_type,
                "volume_start" => $volume,
                "volume_end" => $volume
            )
        ));
    }

    $rf = RefFund::findFirst([
        "conditions" => "key = :key: AND idnum = :idnum: AND prod_start < :prod_start: AND prod_end > :prod_end: AND year = :year:",
        "bind" => [
            "key" => $f->ref_fund_key,
            "idnum" => $u->idnum,
            "prod_start" => $prod,
            "prod_end" => $prod,
            "year" => $year,
        ],
        // чтобы смотреть последнюю запись
        'order' => 'id DESC'
    ]);

    if ($f->type == 'EXP') {
        if ($rf) {
            $__sended = 0;
            $fc = FundProfile::find([
                "conditions" => "md_dt_sent < :end: AND md_dt_sent > :start: AND user_id = :user_id: AND type = :type:",
                "bind" => [
                    "end" => strtotime("31.12.$year 23:59:59"),
                    "start" => strtotime("01.01.$year 00:00:00"),
                    "user_id" => $f->user_id,
                    "type" => "EXP"
                ]
            ]);

            foreach ($fc as $t_f) {
                $__sended += FundCar::count([
                    "conditions" => "fund_id = :fund_id: AND date_produce < :end: AND date_produce > :start: AND ref_car_type_id = :cat: AND volume >= :vs: AND volume <= :ve: AND ref_st_type = :opt:",
                    "bind" => [
                        "fund_id" => $t_f->id,
                        "end" => $rf->prod_end,
                        "start" => $rf->prod_start,
                        "cat" => $cat->car_type,
                        "vs" => $value->volume_start,
                        "ve" => $value->volume_end,
                        "opt" => $opt
                    ]
                ]);
            }

            return $rf->value - $__sended;
        }
    } else {
        if ($rf) {
            $__sended = 0;
            $fc = FundProfile::find([
                "conditions" => "md_dt_sent < :end: AND md_dt_sent > :start: AND user_id = :user_id: AND type = :type:",
                "bind" => [
                    "end" => strtotime("31.12.$year 23:59:59"),
                    "start" => strtotime("01.01.$year 00:00:00"),
                    "user_id" => $f->user_id,
                    "type" => "INS"
                ]
            ]);

            foreach ($fc as $t_f) {
                $__sended += FundCar::count([
                    "conditions" => "fund_id = :fund_id: AND date_produce < :end: AND date_produce > :start: AND ref_car_type_id = :cat: AND volume >= :vs: AND volume <= :ve: AND ref_st_type = :opt:",
                    "bind" => [
                        "fund_id" => $t_f->id,
                        "end" => $rf->prod_end,
                        "start" => $rf->prod_start,
                        "cat" => $cat->car_type,
                        "vs" => $value->volume_start,
                        "ve" => $value->volume_end,
                        "opt" => $opt
                    ]
                ]);
            }

            return $rf->value - $__sended;
        }
    }

    return 0;
}

function getQrImagesHtml($text): string
{
    static $mem = [];

    if (!$text) return '';
    if (isset($mem[$text])) return $mem[$text];

    $dir = APP_PATH . '/storage/temp/qr_cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $out = [];
    foreach (str_split($text, SIGN_QR_LENGTH) as $chunk) {
        $path = $dir . '/' . sha1($chunk) . '_M4.png';

        if (!is_file($path)) {
            QRcode::png($chunk, $path, 'M', 4, 0);
        }

        $out[] = '<img src="file://' . $path . '" width="132" />';
    }

    return $mem[$text] = implode('&nbsp;', $out) . '&nbsp;';
}


function checkFund($hash, $sign)
{
    $cmsService = new CmsService();
    $check = $cmsService->check($hash, $sign);
    return $check;
}
