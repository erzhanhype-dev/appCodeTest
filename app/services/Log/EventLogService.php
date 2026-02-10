<?php

namespace App\Services\Log;

use App\Exceptions\AppException;

final class EventLogService
{
    private const string LOG_DIR = APP_PATH . '/storage/logs/events/';
    private const int MAX_DESC_LEN = 1000;

    private const array LEVELS = [
        'DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY',
    ];

    /**
     * Базовый лог (совместимость со старым вызовом)
     */
    public function store(
        string $source,
        string $userId,
        string $userIp,
        string $startTime,
        string $endTime,
        string $description,
        string $url,
        string $params,
        string $log_type = 'action',
        string $level = 'INFO'
    ): void {
        $this->storeByType(
            $log_type,
            $source,
            $userId,
            $userIp,
            $startTime,
            $endTime,
            $description,
            $url,
            $params,
            $level
        );
    }

    public function storeByType(
        string $type,
        string $source,
        string $userId,
        string $userIp,
        string $startTime,
        string $endTime,
        string $description,
        string $url,
        string $params,
        string $level = 'INFO'
    ): void {
        $this->writeLog($type, $level, $source, $userId, $userIp, $startTime, $endTime, $description, $url, $params);
    }

    /**
     * Основной метод записи лога
     */
    private function writeLog(
        string $filePrefix,
        string $level,
        string $source,
        string $userId,
        string $userIp,
        string $startTime,
        string $endTime,
        string $description,
        string $url,
        string $params
    ): void {
        $this->ensureLogDir();

        $level       = $this->normalizeLevel($level);
        $description = $this->normalizeText($description, self::MAX_DESC_LEN);
        $url         = $this->normalizeSingleLine($url);
        $params      = $params ? $this->normalizeSingleLine($params) : '-';

        $logEntry = sprintf(
            "[%s] [%s] Source: %s | UserID: %s | UserIP: %s | StartTime: %s | EndTime: %s | Description: %s | Url: %s | Params: %s%s",
            date('d:m:Y H:i:s'),
            $level,
            $source,
            $userId,
            $userIp,
            $startTime,
            $endTime,
            $description,
            $url,
            $params,
            PHP_EOL
        );

        $filePath = self::LOG_DIR . $this->buildFileName($filePrefix);

        if (file_put_contents($filePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log("EventLogService: Не удалось записать в файл: " . $filePath);
        }
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtoupper(trim($level));
        return in_array($level, self::LEVELS, true) ? $level : 'INFO';
    }

    private function buildFileName(string $prefix): string
    {
        return $prefix . '_log_' . date('d.m.Y') . '.log';
    }

    private function ensureLogDir(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!is_dir(self::LOG_DIR)) {
            if (!mkdir(self::LOG_DIR, 0755, true) && !is_dir(self::LOG_DIR)) {
                error_log("EventLogService: Не удалось создать директорию логов: " . self::LOG_DIR);
            }
        }
    }

    private function normalizeText(string $text, int $maxLen): string
    {
        $len = mb_strlen($text);

        if ($len > $maxLen) {
            $text = mb_substr($text, 0, $maxLen) . " ... [TRUNCATED, original_len={$len}]";
        }

        return $this->normalizeSingleLine($text);
    }

    private function normalizeSingleLine(string $text): string
    {
        return str_replace(["\r", "\n"], ['\\r', '\\n'], trim($text));
    }
}
