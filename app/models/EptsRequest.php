<?php

use App\Services\Pdf\PdfService;
use PHPQRCode\QRcode;

class EptsRequest extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $request_num;

    /**
     *
     * @var integer
     */
    public $request_time;

    /**
     *
     * @var integer
     */
    public $operation_type;

    /**
     *
     * @var string
     */
    public $base_on;

    /**
     *
     * @var string
     */
    public $request;

    /**
     *
     * @var string
     */
    public $message_id;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $execution_time;

    /**
     *
     * @var string
     */
    public $response_date;

    /**
     *
     * @var string
     */
    public $shep_status_code;

    /**
     *
     * @var string
     */
    public $shep_status_message;

    /**
     *
     * @var string
     */
    public $session_id;

    /**
     *
     * @var integer
     */
    public $status_code;

    /**
     *
     * @var string
     */
    public $description;

    /**
     *
     * @var string
     */
    public $unique_code;

    /**
     *
     * @var string
     */
    public $digital_passport_status;

    /**
     *
     * @var string
     */
    public $vin;

    /**
     *
     * @var string
     */
    public $response;

    /**
     *
     * @var string
     */
    public $green_response;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var integer
     */
    public $created_at;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("epts_request");
    }

    public static function shepParser(string $response_xml_file_name = null): array
    {
        $data = [];
        $folder = APP_PATH . "/storage/logs/epts_logs/";
        $response_file_path = $folder . $response_xml_file_name;

        if (file_exists($response_file_path)) {
            $xml_response = file_get_contents($response_file_path);
            if (__isValidXml($xml_response)) {
                $xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml_response);
                $xml_data = new SimpleXMLElement($xml);
                $encoded_xml = json_encode((array)$xml_data);
                $res = json_decode($encoded_xml, TRUE);
                $sessionId = $res['soapBody']['ns2SendMessageResponse']['response']['responseInfo']['sessionId'];
                $messageId = $res['soapBody']['ns2SendMessageResponse']['response']['responseInfo']['messageId'];
                $responseDate = $res['soapBody']['ns2SendMessageResponse']['response']['responseInfo']['responseDate'];
                $code = $res['soapBody']['ns2SendMessageResponse']['response']['responseInfo']['status']['code'];
                $message = $res['soapBody']['ns2SendMessageResponse']['response']['responseInfo']['status']['message'];

                if (isset($res['soapBody']['soapFault'])) {
                    $code = $res['soapBody']['soapFault']['faultcode'];
                    $message = $res['soapBody']['soapFault']['faultstring'];
                }
            }

            $data = [
                'sessionId' => $sessionId,
                'messageId' => $messageId,
                'responseDate' => $responseDate,
                'code' => $code,
                'message' => $message,
            ];
        }

        return $data;
    }

    public static function greenResponseParser(string $green_response_xml_file_name = null): array
    {
        $data = [];
        $folder = APP_PATH . "/storage/logs/epts_logs/";
        $green_response_file_path = $folder . $green_response_xml_file_name;
        $success = false;
        $statusCode = '';
        $messageDescription = '';
        $uniqueCode = '';
        $vin = '';
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
        $pdf_base64 = NULL;
        $image_base64 = NULL;
        $bin = NULL;
        $sign= NULL;
        $signHash= NULL;
        $docNumber= NULL;
        $dicDocType= NULL;
        $vehicleBodyColourCodeNsi= NULL;
        $vehicleBodyIdentityNumberId= NULL;
        $vehicleFrameIdentityNumberId = NULL;
        $vehicleEngineIdentityNumberIds= NULL;
        $vehicleCategoryCodeNsi= NULL;
        $vehicleCommercialName= NULL;
        $vehicleMakeNameNsi= NULL;
        $vehicleIdentityNumberId= NULL;
        $statusNsi= NULL;

        if (file_exists($green_response_file_path)) {
            $greenResponse = file_get_contents($green_response_file_path);

            if (__isValidXml($greenResponse)) {
                $success = true;
                $xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $greenResponse);
                $xml_data = new SimpleXMLElement($xml);
                $display_data = json_encode((array)$xml_data);
                $array = json_decode($display_data, TRUE);
                $result = $array['messageResult'];

                $statusCode = $result['messageCode'];
                $messageDescription = $result['messageDescription'];

                if ($statusCode == 200) {
                    $uniqueCode = ($result['greenDigitalPassport']['UniqueCode'] != null) ? $result['greenDigitalPassport']['UniqueCode'] : NULL;
                    $vin = ($result['greenDigitalPassport']['VehicleIdentityNumberId'] != null) ? $result['greenDigitalPassport']['VehicleIdentityNumberId'] : NULL;
                    $digitalPassportStatus = ($result['greenDigitalPassport']['statusNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['statusNsi']['ns2NameRu'] : NULL;

                    $statusNsi = ($result['greenDigitalPassport']['statusNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['statusNsi']['ns2NameRu'] : NULL;
                    $vehicleIdentityNumberId = ($result['greenDigitalPassport']['VehicleIdentityNumberId'] != null) ? $result['greenDigitalPassport']['VehicleIdentityNumberId'] : NULL;
                    $vehicleMakeNameNsi = ($result['greenDigitalPassport']['VehicleMakeNameNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['VehicleMakeNameNsi']['ns2NameRu'] : NULL;
                    $vehicleCommercialName = ($result['greenDigitalPassport']['VehicleCommercialName'] != null) ? $result['greenDigitalPassport']['VehicleCommercialName'] : "отсутствует";
                    $vehicleCategoryCodeNsi = ($result['greenDigitalPassport']['VehicleCategoryCodeNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['VehicleCategoryCodeNsi']['ns2NameRu'] : NULL;
                    $vehicleTechCategoryCodeNsi = ($result['greenDigitalPassport']['VehicleTechCategoryCodeNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['VehicleTechCategoryCodeNsi']['ns2NameRu'] : NULL;
                    $vehicleFrameIdentityNumberId = ($result['greenDigitalPassport']['VehicleFrameIdentityNumberId'] != null) ? $result['greenDigitalPassport']['VehicleFrameIdentityNumberId'] : NULL;
                    $vehicleBodyIdentityNumberId = ($result['greenDigitalPassport']['VehicleBodyIdentityNumberId'] != null) ? $result['greenDigitalPassport']['VehicleBodyIdentityNumberId'] : NULL;
                    $manufactureYear = ($result['greenDigitalPassport']['ManufactureYear'] != null) ? $result['greenDigitalPassport']['ManufactureYear'] : NULL;
                    $manufactureDate = ($result['greenDigitalPassport']['ManufactureDate'] != null) ? $result['greenDigitalPassport']['ManufactureDate'] : NULL;
                    $vehicleBodyColourCodeNsi = ($result['greenDigitalPassport']['VehicleBodyColourCodeNsi']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['VehicleBodyColourCodeNsi']['ns2NameRu'] : NULL;
                    $vehicleMassMeasure1 = ($result['greenDigitalPassport']['characteristicDetails']['ns3VehicleMassMeasure1'] != null) ? $result['greenDigitalPassport']['characteristicDetails']['ns3VehicleMassMeasure1'] : NULL;

                    $dvsEngine = $result['greenDigitalPassport']['characteristicDetails']['dvsEngineList']['dvsEngine'] ?? null;
                    $engineMaxPowerMeasure = $dvsEngine['ns3EngineMaxPowerMeasure']
                        ?? $dvsEngine[0]['ns3EngineMaxPowerMeasure']
                        ?? null;
                    $engineCapacityMeasure = $dvsEngine['ns3EngineCapacityMeasure']
                        ?? $dvsEngine[0]['ns3EngineCapacityMeasure']
                        ?? null;
                    $vehicleFuel = $result['greenDigitalPassport']['characteristicDetails']['vehicleFuelKindCodeList']['vehicleFuel'] ?? null;
                    $vehicleFuelKindCodeList = $vehicleFuel['ns2NameRu'] ?? null;
                    $vehicleMadeInCountry = ($result['greenDigitalPassport']['VehicleMadeInCountry'] != null) ? $result['greenDigitalPassport']['VehicleMadeInCountry'] : NULL;
                    $dicDocType = ($result['greenDigitalPassport']['complienceDocuments']['ns2dicDocType']['ns2NameRu'] != null) ? $result['greenDigitalPassport']['complienceDocuments']['ns2dicDocType']['ns2NameRu'] : NULL;
                    $docNumber = ($result['greenDigitalPassport']['complienceDocuments']['ns2DocNumber'] != null) ? $result['greenDigitalPassport']['complienceDocuments']['ns2DocNumber'] : NULL;
//                    $releaseDate = ($result['greenDigitalPassport']['kgdDataList']['kgdData']['ns2ReleaseDate'] != null) ? $result['greenDigitalPassport']['kgdDataList']['kgdData']['ns2ReleaseDate'] : NULL;

                    $releaseDate = null;

                    if (
                        isset($result['greenDigitalPassport']['kgdDataList']['kgdData']['ns2ReleaseDate'])
                    ) {
                        $releaseDate = $result['greenDigitalPassport']['kgdDataList']['kgdData']['ns2ReleaseDate'];
                    }

                    if(isset($result['greenDigitalPassport']['vehicleEngineList'])) {
                        $vehicleEngineList = $result['greenDigitalPassport']['vehicleEngineList']['vehicleEngine'];
                        $vehicleEngineIds = [];
                        foreach ($vehicleEngineList as $engine) {
                            $vehicleEngineIds[] = $engine['ns2:VehicleEngineIdentityNumberId'];
                        }
                        $vehicleEngineIdentityNumberIds = implode(',', $vehicleEngineIds);
                    }else{
                        $vehicleEngineIdentityNumberIds = '';
                    }

                    $manufactureDate = convertEptsTimeZone($manufactureDate);
                    $releaseDate = convertEptsTimeZone($releaseDate);

                    $pdf_base64 = $result['greenDigitalPassport']['EptsFile'];
                    $pdf_base64 = str_replace(array("\n", "\r", "\t"), '', $pdf_base64);

                    $signDocs = $result['greenDigitalPassport']['signDocs'];
                    $signDocs = str_replace(array("\n", "\r", "\t"), '', $signDocs);
                    $signDocs = implode($signDocs);

                    $sign_xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $signDocs);

                    if (__isValidXml($sign_xml)) {
                        $sign_xml_data = new SimpleXMLElement($sign_xml);
                        $sign_display_data = json_encode((array)$sign_xml_data);
                        $sign_array = json_decode($sign_display_data, TRUE);
                        if (
                            isset($sign_array['imagesTS']['ImagesTs'][0]['image'])
                        ) {
                            $image_base64 = $sign_array['imagesTS']['ImagesTs'][0]['image'];
                        } else {
                            $image_base64 = null;
                        }

                        $sign = $sign_array['dsSignature']['dsKeyInfo']['dsX509Data']['dsX509Certificate'];

                        $x509Data =
                            "-----BEGIN CERTIFICATE-----\n"
                            . $sign
                            . "\n-----END CERTIFICATE-----";

                        $signHash = openssl_x509_parse($x509Data, true);
                        $bin = str_replace('BIN', '', $signHash['subject']['OU']);
                    }
                }
            }

            $data = [
                'success' => $success,
                'statusCode' => $statusCode,
                'messageDescription' => $messageDescription,
                'uniqueCode' => $uniqueCode,
                'vin' => $vin,
                'digitalPassportStatus' => $digitalPassportStatus,
                'vehicleTechCategoryCodeNsi' => $vehicleTechCategoryCodeNsi,
                'manufactureYear' => $manufactureYear,
                'engineCapacityMeasure' => $engineCapacityMeasure,
                'engineMaxPowerMeasure' => $engineMaxPowerMeasure,
                'vehicleFuelKindCodeList' => $vehicleFuelKindCodeList,
                'vehicleMadeInCountry' => $vehicleMadeInCountry,
                'vehicleMassMeasure1' => $vehicleMassMeasure1,
                'manufactureDate' => $manufactureDate,
                'releaseDate' => $releaseDate,
                'pdf_base64' => $pdf_base64,
                'image_base64' => $image_base64,
                'bin' => $bin,
                'sign' => $sign,
                'signHash' => $signHash,
                'docNumber' => $docNumber,
                'dicDocType' => $dicDocType,
                'vehicleBodyColourCodeNsi' => $vehicleBodyColourCodeNsi,
                'vehicleBodyIdentityNumberId' => $vehicleBodyIdentityNumberId,
                'vehicleFrameIdentityNumberId' => $vehicleFrameIdentityNumberId,
                'vehicleEngineIdentityNumberIds' => $vehicleEngineIdentityNumberIds,
                'vehicleCategoryCodeNsi' => $vehicleCategoryCodeNsi,
                'vehicleCommercialName' => $vehicleCommercialName,
                'vehicleMakeNameNsi' => $vehicleMakeNameNsi,
                'vehicleIdentityNumberId' => $vehicleIdentityNumberId,
                'statusNsi' => $statusNsi,
            ];
        }

        return $data;
    }

    public static function returnEPTSInfoAsTable(string $xml_file_name = NULL): string
    {
        $table_tr = NULL;
        $result = self::greenResponseParser($xml_file_name);

        if ($result) {
            $statusCode = $result['statusCode'];

            if ($statusCode == 200) {

                $manufactureDate = $result['manufactureDate'];
                $vehicleMadeInCountry = $result['vehicleMadeInCountry'];
                $releaseDate = $result['releaseDate'];

                if ($vehicleMadeInCountry == 'KZ') {
                    $dateRow = "<tr><td>Дата производства ТС</td><td><b>$manufactureDate</b></td></tr>";
                } else {
                    $dateRow = "<tr><td>Дата импорта ТС</td><td><b>$releaseDate</b></td></tr>";
                }

                $table_tr .= "
                <tr><td>Уникальный номер</td><td><b>" . $result['uniqueCode'] . "</b></td></tr>
                <tr><td>Идентификационный номер(VIN)</td><td><b>" . $result['vehicleIdentityNumberId'] . "</b></td></tr>
                <tr><td>Статус электронного паспорта</td><td><b>" . $result['statusNsi'] . "</b></td></tr>
                <tr><td>Марка</td><td><b>" . $result['vehicleMakeNameNsi'] . "</b></td></tr>
                <tr><td>Коммерческое наименование</td><td><b>" . $result['vehicleCommercialName'] . "</b></td></tr>
                <tr><td>Категория в соответствии с Конвенцией о дорожномдвижении</td><td><b>" . $result['vehicleCategoryCodeNsi'] . "</b></td></tr>
                <tr><td>Категория в соответствии с ТР ТС 018/2011</td><td><b>" . $result['vehicleTechCategoryCodeNsi'] . "</b></td></tr>
                <tr><td>Номер двигателя</td><td><b>" . $result['vehicleEngineIdentityNumberIds'] . "</b></td></tr>
                <tr><td>Номер шасси (рамы)</td><td><b>" . $result['vehicleFrameIdentityNumberId'] . "</b></td></tr>
                <tr><td>Номер кузова (кабины, прицепа)</td><td><b>" . $result['vehicleBodyIdentityNumberId'] . "</b></td></tr>
                <tr><td>Год изготовления</td><td><b>" . $result['manufactureYear'] . "</b></td></tr>
                <tr><td>Цвет кузова (кабины, прицепа)</td><td><b>" . $result['vehicleBodyColourCodeNsi'] . "</b></td></tr>
                <tr><td> - рабочий объем цилиндров (сМ3)</td><td><b>" . $result['engineCapacityMeasure'] . "</b></td></tr>
                <tr><td> - максимальная мощность (кВт)</td><td><b>" . $result['engineMaxPowerMeasure'] . "</b></td></tr>
                <tr><td> - Технически допустимая максимальная масса ТС (кг)</td><td><b>" . $result['vehicleMassMeasure1'] . "</b></td></tr>
                <tr><td>Вид топлива</td><td><b>" . $result['vehicleFuelKindCodeList'] . "</b></td></tr>
                $dateRow
                <tr><td>Страна производитель</td><td><b>" . $result['vehicleMadeInCountry'] . "</b></td></tr>
                <tr><td>Документ подтверждающий соответсвие оябзат. требованиям безопасности
                (ОТТС, ОТШ и СБКТС)</td><td><b>" . $result['dicDocType'] . "<br> " . $result['docNumber'] . "</b></td></tr>
            ";
            } else {
                $messageDescription = $result['messageDescription'];

                $table_tr .= "
                <tr><td>MessageCode</td><td><b>$statusCode</b></td></tr>
                <tr><td>MessageDescription</td><td><b>$messageDescription</b></td></tr>
            ";
            }

            return $table_tr;
        }

        return '<span class="badge badge-danger mb-2" style="font-size: 14px;">Не найден</span>';;
    }

    public static function genDoc($request, $download = true): void
    {
        if ($request->status_code == 200) {
            $parsed_epts = self::greenResponseParser($request->green_response);
            $sign = $parsed_epts['signHash'];

            $path = APP_PATH . '/storage/temp/';
            $table = self::returnEPTSInfoAsTable($request->green_response);
            $status = '';

            $spravka = $path . 'epts_request_' . $request->id . '.pdf';
            if (file_exists($spravka)) unlink($spravka);

            $user = User::findFirstById($request->user_id);

            $certificate_template = APP_PATH . '/app/templates/html/epts_request/epts_request.html';

            $digital_passport_status = $parsed_epts['digitalPassportStatus']
                ?? $parsed_epts['digital_passport_status']
                ?? null;

            $unique_code = $parsed_epts['uniqueCode']
                ?? $parsed_epts['unique_code']
                ?? null;

            $content_qr = 'ЗапросID: #' . $request->id . '::СтатусЭлектронногоПаспорта:' . $digital_passport_status .
                '::VIN-код: ' . $parsed_epts['vin'] . '::УникальныйНомерЭлектронногоПаспорта: ' . $unique_code .
                '::ДатаЗапроса:' . date('d.m.Y H:i:s', $request->created_at) . '::ИИН/БИН:' . $user->idnum;
            $content_sign = genAppHash($content_qr . getenv('NEW_SALT'));
            QRcode::png($content_qr . '::' . $content_sign, APP_PATH . '/storage/temp/epts_request_' . $request->id . '.png', 'H', 3, 0);

            $content_qr = 'ФИО: ' . (isset($sign['subject']['CN']) ? $sign['subject']['CN'] : '') . ' ' . (isset($sign['subject']['GN']) ? $sign['subject']['GN'] : '') .
                ':: ИИН: ' . str_replace('IIN', '', $sign['subject']['serialNumber']) .
                ':: БИН: ' . str_replace('BIN', '', $sign['subject']['OU']) .
                ':: Наименование : ' . $sign['subject']['O'];
            $content_sign = genAppHash($content_qr . getenv('NEW_SALT'));
            QRcode::png($content_qr . '::' . $content_sign, APP_PATH . '/storage/temp/epts_request_' . $request->id . '_x509.png', 'H', 3, 0);

            $cert = join('', file($certificate_template));

            $certificate_tmp = APP_PATH . '/storage/temp/epts_request_' . $request->id . '.html';

            $header = "Запрос в ЭПТС #" . $request->id;
            $cert = str_replace('[HEADER]', $header, $cert);

            $desc = $request->base_on;
            $cert = str_replace('[DESCRIPTION]', "(На основании: $desc)", $cert);

            $usr = "Автор запроса (ИИН): " . $user->idnum;
            $cert = str_replace('[AUTHOR]', $usr, $cert);

            $date = 'Дата: ' . date('d.m.Y', $request->created_at);
            $time = 'Время: ' . date('H:i', $request->created_at);
            $cert = str_replace('[DATE]', ' ' . $date . '<br>' . $time . ' ', $cert);

            if ($request->status_code == 200) {
                $status = "Статус : <b>$request->digital_passport_status</b>";
            }

            $cert = str_replace('[STATUS]', $status, $cert);

            $cert = str_replace('[TABLE]', $table, $cert);

            $cert = str_replace('[Z_QR]', '<img src="' . APP_PATH . '/storage/temp/epts_request_' . $request->id . '.png" width="150" height="150">', $cert);
            $cert = str_replace('[Z_QR_X509]', '<img src="' . APP_PATH . '/storage/temp/epts_request_' . $request->id . '_x509.png" width="150" height="150">', $cert);
            $cert = str_replace('[ORG_NAME]', $sign['subject']['O'] . '(' . str_replace('BIN', '', $sign['subject']['OU']) . ')', $cert);

            file_put_contents($certificate_tmp, $cert);
            (new PdfService())->generate($certificate_tmp, $path . 'epts_request_' . $request->id . '.pdf');

            $to_download = $path . 'epts_request_' . $request->id . '.pdf';

            if (file_exists($to_download) && $download) {
                __downloadFile($to_download);
            }
        }
    }

}
