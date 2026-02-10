<?php

namespace App\Helpers;

use App\Exceptions\AppException;

class FileHelper
{
    /**
     * Обновляет содержимое файла.
     *
     * @param string $filePath
     * @param string $fileContent
     * @throws AppException
     */
    public function update(string $filePath, string $fileContent): void
    {
        // Проверка существования директории
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            throw new AppException("Directory does not exist: {$directory}");
        }

        // Проверка прав записи
        if (file_exists($filePath) && !is_writable($filePath)) {
            throw new AppException("File is not writable: {$filePath}");
        }

        // Открытие файла с блокировкой
        $fileHandle = @fopen($filePath, 'w');
        if ($fileHandle === false || !flock($fileHandle, LOCK_EX)) {
            throw new AppException("Unable to open or lock file for writing: {$filePath}");
        }

        // Запись в файл
        if (fwrite($fileHandle, $fileContent) === false) {
            fclose($fileHandle);
            throw new AppException("Unable to write to file: {$filePath}");
        }

        // Освобождение блокировки и закрытие файла
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);
    }
}