<?php

namespace App\Services\Kato;

use Kato;

class KatoService
{
    /**
     * Возвращает географическое описание по массиву кодов КАТО.
     * * Логика перенесена из KapService::getKatoDescription. Она ищет
     * запись КАТО и рекурсивно поднимается по родительским записям,
     * чтобы получить полное описание (например, 'Алматы, Медеуский район').
     * * @param array $katoCodes Массив кодов КАТО.
     * @return string Описание, разделенное запятыми, или дефис, если ничего не найдено.
     */
    public function getKatoDescription(array $katoCodes): string
    {
        $names = [];

        foreach ($katoCodes as $katoCode) {
            // Пропускаем пустые или нулевые коды
            if (empty($katoCode)) {
                continue;
            }

            // Ищем запись по коду КАТО
            // Предполагаем, что у модели Kato есть метод для поиска по коду
            $katoRecord = Kato::findFirstByKatoCode((int)$katoCode);

            if ($katoRecord) {
                // Добавляем имя текущей записи
                if (!empty($katoRecord->name_ru)) {
                    $names[] = $katoRecord->name_ru;
                }

                // Рекурсивный обход родителей (предполагая связь 'parent')
                $parent = $katoRecord->parent;
                $counter = 0;

                // Ограничиваем цикл, чтобы избежать бесконечного обхода в случае ошибок
                while ($parent && $counter < 5) {
                    if (!empty($parent->name_ru)) {
                        $names[] = $parent->name_ru;
                    }
                    // Предполагаем, что родительская связь также называется 'parent'
                    $parent = $parent->parent;
                    $counter++;
                }
            }
        }

        if (!empty($names)) {
            // Очищаем от дубликатов и пустых значений, и объединяем
            $uniqueNames = array_filter(array_unique($names));
            return implode(', ', $uniqueNames);
        }

        return '-';
    }
}