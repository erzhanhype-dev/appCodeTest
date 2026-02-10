<?php

namespace App\Services\Kap\Parser;

use Phalcon\Di\Injectable;

class KapResponseParser extends Injectable
{
    public function getItems(array $response): array
    {
        $items = [];
        $data = $response['data'] ?? [];

        if (isset($data['list']['GreenDevVrResponse']['Items']['Item'])) {
            $items = $data['list']['GreenDevVrResponse']['Items']['Item'];
        }

        // Нормализация: если пришёл одиночный объект — оборачиваем в массив
        if ($items && (isset($items['VehicleRegistration']) || isset($items[0]['VehicleRegistration']))) {
            return is_array($items) && array_keys($items) === range(0, count($items) - 1)
                ? $items
                : [$items];
        }

        return [];
    }

    public function getStatus(array $response): array
    {
        return $response['data']['list']['Status'] ?? [
            'Code' => 'N/A',
            'MessageRu' => 'Статус не найден',
            'MessageKz' => 'Статус не найден',
        ];
    }

    public function getXmlSignature(string $xmlString): string
    {
        return $this->xmlSafeParser->getSignFromXmlString($xmlString);
    }
}