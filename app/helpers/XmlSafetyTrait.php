<?php

use App\Exceptions\AppException;

trait XmlSafetyTrait
{
    /**
     * @throws AppException
     */
    public function getSignFromXmlString(string $xmlString): string
    {
        $hash = '';

        try {
            // 1) Парсим внешний XML безопасно
            $xmlObject = $this->loadXmlSafe($xmlString);

            // 2) Ищем <Payload>
            $payloadNodes = $xmlObject->xpath('//Payload');
            if (empty($payloadNodes)) {
                throw new AppException("Элемент <Payload> не найден");
            }

            // 3) Извлекаем текст payload
            $payloadRaw = (string)$payloadNodes[0];

            // Определяем, является ли payload экранированным XML
            $payloadXml = $payloadRaw;
            if (strpos($payloadRaw, '<') === false && preg_match('/&lt;.*&gt;/s', $payloadRaw)) {
                // Похоже на экранированный XML: распакуем
                $payloadXml = html_entity_decode($payloadRaw, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            // 4) Парсим вложенный XML безопасно
            $innerXmlObject = $this->loadXmlSafe($payloadXml);
            $certificateNodes = $innerXmlObject->xpath('//*[local-name()="X509Certificate"]');

            if (empty($certificateNodes)) {
                throw new AppException("Сертификаты не найдены в XML");
            }

            // 5) Извлекаем сертификаты
            foreach ($certificateNodes as $certificate) {
                $hash .= trim((string)$certificate) . PHP_EOL;
            }

        } catch (AppException $e) {
            // В реальном приложении здесь должна быть запись в лог
            // error_log($e->getMessage());
        }

        return $hash;
    }

    /**
     * Безопасная загрузка XML из строки.
     * - убирает BOM,
     * - гарантирует UTF-8,
     * - чинит только НЕвалидные амперсанды,
     * - возвращает SimpleXMLElement или бросает AppException.
     * * @throws AppException
     */
    private function loadXmlSafe(string $xmlString): SimpleXMLElement
    {
        // Удалим BOM
        $xmlString = preg_replace('/^\xEF\xBB\xBF/', '', $xmlString);

        // Нормализуем перевод строк
        $xmlString = str_replace("\r\n", "\n", $xmlString);

        // Кодировка -> UTF-8
        if (!mb_check_encoding($xmlString, 'UTF-8')) {
            $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'auto');
        }

        // Поправляем только "голые" &, не трогаем валидные сущности
        $xmlString = preg_replace(
            '/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;)/u',
            '&amp;',
            $xmlString
        );

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(
            $xmlString,
            'SimpleXMLElement',
            LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $msgs = array_map(function ($e) {
                return trim(sprintf(
                    '[%s] line %d, col %d: %s',
                    $e->level === LIBXML_ERR_WARNING ? 'WARN' :
                        ($e->level === LIBXML_ERR_ERROR ? 'ERROR' : 'FATAL'),
                    $e->line,
                    $e->column,
                    $e->message
                ));
            }, $errors);

            throw new AppException("Не удалось распарсить XML:\n" . implode("\n", $msgs));
        }

        return $xml;
    }
}