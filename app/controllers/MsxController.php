<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\Msx\MsxService;
use App\Services\Pdf\PdfService;
use ControllerBase;
use DOMDocument;
use DOMXPath;
use MsxRequest;
use Phalcon\Mvc\View;
use PHPQRCode\QRcode;
use User;

class MsxController extends ControllerBase
{
    public function indexAction(): View
    {
        if ($this->session->has('data')) {
            $msxResult = $this->session->get('data');

            $this->view->setVar('msxResult', $msxResult);

            $rowsList = $this->buildRowsList($msxResult);
            $matrix = $this->buildMsxMatrix($rowsList);

            $this->view->setVar('msxMatrix', $matrix);
        }

        return $this->view->pick('msx/index');
    }

    public function sendAction()
    {
        $msxService = new MsxService();
        $value = $this->request->getPost('uniqueNumber');
        $comment = $this->request->getPost('comment');

        try {
            $data = $msxService->search([
                'requestTypeCode' => 'VIN',
                'value' => $value,
            ], $comment);

            $this->session->set('data', $data);
        } catch (AppException $e) {
            $this->flash->error($e->getMessage());
            return $this->response->redirect('/msx/index');
        }

        return $this->response->redirect('/msx/index');
    }

    public function downloadAction()
    {
        if (!$this->session->has('data')) {
            $this->flash->error('Нет данных для скачивания');
            return $this->response->redirect('/msx/index');
        }

        $msxResult = $this->session->get('data');

        $msx_request = MsxRequest::findFirst($msxResult['msx_request_id']);

        $this->generateDoc($msx_request);
    }

    public function downloadFileAction($filename){
        $path = APP_PATH . '/storage/logs/msx_logs/';
        $to_download = $path . $filename;
        if (file_exists($to_download)) {
            __downloadFile($to_download);
        } else {
            $this->flash->error('Файл не найден');
            return $this->response->redirect('/msx/index');
        }
    }

    private function generateDoc($msx_request)
    {
        $id = $msx_request->id;
        $comment = $msx_request->comment;
        $created = $msx_request->created;
        $user = User::findFirst($msx_request->user_id);
        $idnum = $user->idnum;
        $path = APP_PATH . '/storage/temp/';

        // гененрируем сертификат
        $certificate_template = APP_PATH . '/app/templates/html/msx_request/msx_request.html';

        $certificate_tmp = APP_PATH . '/storage/temp/msx_request_' . $id . '.html';
        $cert = join('', file($certificate_template));
        $payload_status_code = '';

        $responseFile = APP_PATH . '/storage/logs/msx_logs/'.$msx_request->response;

        if(file_exists($responseFile)) {
            $items = $this->transportResponseFirstTransportFlat($responseFile);

            $sign = $this->getCertificate($responseFile);

            if ($items) {
                $table = '<table style="border:none">';
                $table .= '<tbody>';
                foreach ($items as $key => $column) {
                    $table .= '<tr>';
                    $table .= "<td style='border:none'>" . $key . "</td>" . "<td style='border:none'>" . (in_array($key, ['isPrimary', 'Снят с регистрации', 'Арестована']) ? ($column == 'false' ? 'Нет' : 'Да') : $column) . "</td>";
                    $table .= '</tr>';
                }
                $table .= '</tbody>';
                $table .= '</table>';
            } else {
                $table = 'Сведения в КАП отсутствует';
            }

            $header = "Запрос в МСХ #" . $id;
            $cert = str_replace('[HEADER]', $header, $cert);

            $cert = str_replace('[DESCRIPTION]', $comment, $cert);

            $usr = "<strong>Автор запроса (ИИН): </strong>" . $idnum;
            $cert = str_replace('[AUTHOR]', $usr, $cert);
            $cert = str_replace('[STATUS]', "Статус : <b>$msx_request->status</b>", $cert);

            $date = '<strong>Время запроса: </strong>' . date('d.m.Y H:i:s', $created);
            $cert = str_replace('[DATE]', $date, $cert);

            $cert = str_replace('[TABLE]', $table, $cert);

            $content_qr = 'id_' . $id . ':: status_' . $payload_status_code . ' :: date_' . date('d.m.Y', $created) . ' :: user_' . $idnum;
            $content_sign = generateQrHash($content_qr);
            QRcode::png($content_qr . ':' . $content_sign, APP_PATH . '/storage/temp/msx_request_' . $id . '.png', 'H', 3, 0);
            $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/msx_request_' . $id . '.png" width="150" height="150">', $cert);

            if ($sign != NULL) {
                $content_qr = $sign;
                $content_sign = generateQrHash($content_qr);
                QRcode::png($content_qr . '::' . $content_sign, APP_PATH . '/storage/temp/msx_request_' . $id . '_x509.png', 'H', 3, 0);
                $cert = str_replace('[Z_QR_X509]', '<img src="' . APP_PATH . '/storage/temp/msx_request_' . $id . '_x509.png" width="150" height="150">', $cert);
            } else {
                $msg = "<h3 color='red'>ЭЦП отсутствует !</h3>";
                $cert = str_replace('[Z_QR_X509]', $msg, $cert);
                $cert = str_replace('[ORG_NAME]', $msg, $cert);
            }

            file_put_contents($certificate_tmp, $cert);
            $to_download = $path . 'msx_request_' . $id . '.pdf';

            (new PdfService())->generate($certificate_tmp, $to_download);

            if (file_exists($to_download)) {
                __downloadFile($to_download);
            } else {
                echo('Нет файла');
            }
        }

        $this->flash->error('Файл не найден');
        return $this->response->redirect('/msx/index');
    }

    private function transportResponseFirstTransportFlat(string $xmlOrPath): array
    {
        if (is_file($xmlOrPath)) {
            $xmlOrPath = (string)file_get_contents($xmlOrPath);
        }

        if (trim($xmlOrPath) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlOrPath, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [];
        }

        $xp = new \DOMXPath($dom);

        // 2) Namespace-нейтральный поиск TransportResponse/Transport
        $nodes = $xp->query('//*[local-name()="TransportResponse"]/*[local-name()="Transport"][1]');
        if (!$nodes || $nodes->length === 0) {
            return [];
        }

        $transport = $nodes->item(0);
        if (!($transport instanceof \DOMElement)) {
            return [];
        }

        $put = function (array &$map, string $key, mixed $value): void {
            if (!array_key_exists($key, $map)) {
                $map[$key] = $value;
                return;
            }
            if (!is_array($map[$key])) {
                $map[$key] = [$map[$key]];
            }
            $map[$key][] = $value;
        };

        $flatten = function (\DOMNode $node, string $prefix = '') use (&$flatten, $put): array {
            $result = [];

            foreach ($node->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $name = $child->localName ?? $child->nodeName;
                $path = ($prefix === '') ? $this->translator->_($name) : ($this->translator->_($prefix) . '.' . $this->translator->_($name));

                $hasElementChildren = false;
                foreach ($child->childNodes as $cc) {
                    if ($cc->nodeType === XML_ELEMENT_NODE) {
                        $hasElementChildren = true;
                        break;
                    }
                }

                if ($hasElementChildren) {
                    $nested = $flatten($child, $path);
                    foreach ($nested as $k => $v) {
                        $put($result, $k, $v);
                    }
                } else {
                    $put($result, $path, trim((string)$child->textContent));
                }
            }

            return $result;
        };

        return $flatten($transport);
    }

    /**
     * @throws AppException
     */
    private function getCertificate($responseFile): ?string
    {
        $signature = $this->getFirstSignatureValue($responseFile);
        return $signature;
    }

    public static function getAllSignatureValues(string $xmlPath): array
    {
        if (!is_file($xmlPath)) {
            throw new AppException("File not found: " . $xmlPath);
        }

        $xml = file_get_contents($xmlPath);
        if ($xml === false) {
            throw new AppException("Failed to read: " . $xmlPath);
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            $err = libxml_get_last_error();
            libxml_clear_errors();
            throw new AppException("Invalid XML: " . ($err ? trim($err->message) : "unknown error"));
        }

        $xp = new DOMXPath($dom);

        // Все SignatureValue в документе
        $list = $xp->query("//*[local-name()='SignatureValue']");
        $out = [];

        foreach ($list as $node) {
            $b64 = preg_replace('/\s+/', '', trim($node->textContent)) ?? trim($node->textContent);
            if ($b64 !== '') {
                $out[] = $b64;
            }
        }

        return $out;
    }

    /**
     * @throws AppException
     */
    public static function getFirstSignatureValue(string $xmlPath): ?string
    {
        $all = self::getAllSignatureValues($xmlPath);
        return $all[0] ?? null;
    }

    private function buildMsxMatrix(array $rowsList): array
    {
        $cols = [];
        $colCount = count($rowsList);
        for ($i = 1; $i <= $colCount; $i++) {
            $cols[] = $i;
        }

        // собираем “все строки” (группа+ключ) в порядке появления
        $order = [];          // массив rowId в порядке добавления
        $seenOrder = [];      // rowId => true
        $meta = [];           // rowId => ['type'=>'group'|'field', ...]
        $values = [];         // rowId => [colIndex => value]

        for ($c = 1; $c <= $colCount; $c++) {
            $groups = $rowsList[$c - 1]['groups'] ?? [];
            foreach ($groups as $g) {
                $gName  = (string)($g['name'] ?? '');
                $gTitle = (string)($g['title'] ?? $gName);
                $gData  = (array)($g['data'] ?? []);

                // строка-заголовок группы (один раз в матрице)
                $groupRowId = 'G|' . $gName;
                if (!isset($seenOrder[$groupRowId])) {
                    $seenOrder[$groupRowId] = true;
                    $order[] = $groupRowId;

                    [$headerColor, $bodyColor] = $this->groupColors($gName);

                    $meta[$groupRowId] = [
                        'type'        => 'group',
                        'group_name'  => $gName,
                        'title'       => $gTitle,
                        'headerColor' => $headerColor,
                        'bodyColor'   => $bodyColor,
                    ];
                }

                // строки-поля группы
                foreach ($gData as $key => $val) {
                    $key = (string)$key;
                    $fieldRowId = 'F|' . $gName . '|' . $key;

                    if (!isset($seenOrder[$fieldRowId])) {
                        $seenOrder[$fieldRowId] = true;
                        $order[] = $fieldRowId;

                        [$headerColor, $bodyColor] = $this->groupColors($gName);

                        $meta[$fieldRowId] = [
                            'type'        => 'field',
                            'group_name'  => $gName,
                            'key'         => $key,
                            'headerColor' => $headerColor,
                            'bodyColor'   => $bodyColor,
                        ];
                    }

                    $values[$fieldRowId][$c] = $val;
                }
            }
        }

        $rows = [];
        foreach ($order as $rowId) {
            $row = $meta[$rowId];

            if ($row['type'] === 'field') {
                $row['vals'] = [];
                for ($c = 1; $c <= $colCount; $c++) {
                    $row['vals'][$c] = $values[$rowId][$c] ?? null;
                }
            }

            $rows[] = $row;
        }

        return [
            'cols'     => $cols,
            'colCount' => $colCount,
            'rows'     => $rows,
        ];
    }

    public function buildRowsList(array $msxResult): array
    {
        if (
            !isset($msxResult['status']) || $msxResult['status'] !== 'success' ||
            !isset($msxResult['data']['list'])
        ) {
            return [];
        }

        $rawList = $msxResult['data']['list'];

        $rows = (is_array($rawList) && array_key_exists(0, $rawList))
            ? $rawList
            : [$rawList];

        $out = [];
        $i = 0;

        foreach ($rows as $recordGroups) {
            $i++;

            if (!is_array($recordGroups)) {
                $recordGroups = [];
            }

            $seen = [];
            $groupsOut = [];

            $agentOwnerData = null;

            foreach ($recordGroups as $groupName => $groupData) {
                $groupName = (string)$groupName;

                if (isset($seen[$groupName])) {
                    continue;
                }
                $seen[$groupName] = true;

                if ($groupName === 'holderAgent' || $groupName === 'ownerAgent') {
                    if ($agentOwnerData === null && is_array($groupData)) {
                        $agentOwnerData = $groupData;
                    }
                    continue;
                }

                if ($groupName === 'Transport' && is_array($groupData)) {
                    $transportBlocks = $this->splitTransportIntoBlocks($groupData);

                    foreach ($transportBlocks as $blockName => $blockData) {
                        $groupsOut[] = [
                            'name'  => $blockName,
                            'title' => $this->groupTitle($blockName),
                            'data'  => $blockData,
                        ];
                    }

                    continue;
                }

                $groupsOut[] = [
                    'name'  => $groupName,
                    'title' => $this->groupTitle($groupName),
                    'data'  => is_array($groupData) ? $groupData : [],
                ];
            }

            if (is_array($agentOwnerData)) {
                $groupsOut[] = [
                    'name'  => 'AgentOwner',
                    'title' => $this->groupTitle('AgentOwner'),
                    'data'  => $agentOwnerData,
                ];
            }

            $out[] = [
                'index'  => $i,
                'groups' => $groupsOut,
            ];
        }

        return $out;
    }

    private function splitTransportIntoBlocks(array $transport): array
    {
        $map = [
            // Блок 1: Транспортное средство (ССХТ)
            'TransportMain' => [
                'factoryNumber',
                'engineNumber',
                'tpSeries',
                'tpNumber',
                'gnNumber',
                'mark',
                'modelName',
                'graduationYear',
                'powerTs',
                'unitName',
                'typeName',
                'viewName',
                'marks',
            ],

            // Блок 2: Регистрация и учёт
            'TransportReg' => [
                'registerDate',
                'isPrimary',
                'unreg',
                'mUrDate',
                'machineryUrCause',
                'arrest',
                'registrarFullName',
            ],

            // Блок 3: Местонахождение ТС
            'TransportLocation' => [
                'region',
                'district',
                'ruralDistrict',
                'settlement',
                'locationTe',
            ],
        ];

        // Быстро определяем "известные" ключи
        $known = [];
        foreach ($map as $fields) {
            foreach ($fields as $f) {
                $known[$f] = true;
            }
        }

        // Собираем блоки в нужном порядке ключей
        $out = [];
        foreach ($map as $blockName => $fields) {
            $block = [];
            foreach ($fields as $key) {
                if (array_key_exists($key, $transport)) {
                    $block[$key] = $transport[$key];
                }
            }
            if (!empty($block)) {
                $out[$blockName] = $block;
            }
        }

        // Остаток: добавляем в TransportMain, чтобы не плодить 5-й блок
        $other = [];
        foreach ($transport as $k => $v) {
            $k = (string)$k;
            if (!isset($known[$k])) {
                $other[$k] = $v;
            }
        }
        if (!empty($other)) {
            if (!isset($out['TransportMain'])) {
                $out['TransportMain'] = [];
            }
            foreach ($other as $k => $v) {
                $out['TransportMain'][$k] = $v;
            }
        }

        return $out;
    }

    private function groupTitle(string $groupName): string
    {
        switch ($groupName) {
            // Подблоки Transport
            case 'TransportMain':
                return 'Транспортное средство (ССХТ)';
            case 'TransportReg':
                return 'Регистрация и учёт';
            case 'TransportLocation':
                return 'Местонахождение ТС';
            case 'AgentOwner':
                return 'Агент / Владелец';
            default:
                return $groupName;
        }
    }

    private function groupColors(string $groupName): array
    {
        // headerColor, bodyColor
        switch ($groupName) {
            case 'TransportMain':
                return ['#6c8ebf', '#f0f4f8'];
            case 'TransportReg':
                return ['#73a580', '#f2f7f4'];
            case 'TransportLocation':
                return ['#d69e73', '#fbf7f4'];
            case 'AgentOwner':
                return ['#875959', '#f8f8f8'];
            default:
                return ['#6c757d', '#f8f9fa'];
        }
    }
}
