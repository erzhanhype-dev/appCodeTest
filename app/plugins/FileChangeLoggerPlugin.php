<?php
use App\helpers\FileHelper; // если реально есть; иначе см. комментарий ниже
use Phalcon\Di\Injectable;
use Phalcon\Events\EventInterface;
use Phalcon\Logger\Exception;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Logger;

class FileChangeLoggerPlugin extends Injectable
{
    private Logger $logger;
    private array $filePaths;
    private string $checkTimeFile;
    private string $logDir;

    /**
     * @throws Exception
     */
    public function __construct(array $filePaths, string $checkTimeFile, string $logDir = APP_PATH . '/storage/logs/system/')
    {
        $this->filePaths     = $filePaths;
        $this->checkTimeFile = $checkTimeFile;
        $this->logDir        = rtrim($logDir, '/') . '/';

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }

        $logFileName  = 'files_' . date('d.m.Y') . '.log';
        $logFilePath  = $this->logDir . $logFileName;


        $adapter = new Stream($logFilePath);
        $this->logger = new Logger(
            'files',
            [
                'main' => $adapter,
            ]
        );
    }

    // Важно: правильная сигнатура слушателя для eventsManager->attach('dispatch:beforeDispatch', ...)
    public function beforeDispatch(EventInterface $event, Dispatcher $dispatcher): bool
    {
        $lastCheckTimes = $this->loadLastCheckTimes();
        $ipAddress      = $_SERVER['REMOTE_ADDR'] ?? '-';

        foreach ($this->filePaths as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            $lastCheckTime    = $lastCheckTimes[$filePath] ?? 0;
            $lastModifiedTime = @filemtime($filePath) ?: 0;

            if ($lastModifiedTime > $lastCheckTime) {
                $changedFields = $this->getChangedFields($filePath);

                if ($changedFields !== 'No significant changes') {
                    $logEntry = sprintf(
                        "%s | %s | %s | %s | %s | %s | %s | %s",
                        date('d:m:Y H:i:s'),
                        'FileWatcherService',
                        'system',
                        $ipAddress,
                        $lastCheckTime ? date('H:i:s', $lastCheckTime) : '-',
                        $lastModifiedTime ? date('H:i:s', $lastModifiedTime) : '-',
                        "File {$filePath} has been modified",
                        $changedFields
                    );
                    $this->logger->info($logEntry);
                }

                $lastCheckTimes[$filePath] = $lastModifiedTime;
            }
        }

        $this->saveLastCheckTimes($lastCheckTimes);
        return true;
    }

    private function loadLastCheckTimes(): array
    {
        if (is_file($this->checkTimeFile)) {
            $json = @file_get_contents($this->checkTimeFile);
            $this->deleteOldLogFiles(); // по твоей логике делаем чистку здесь
            return is_string($json) ? (json_decode($json, true) ?: []) : [];
        }
        $this->deleteOldLogFiles();
        return [];
    }

    private function saveLastCheckTimes(array $lastCheckTimes): void
    {
        // Если нет собственного FileHelper, можно заменить на file_put_contents:
        if (class_exists(\App\helpers\FileHelper::class)) {
            (new FileHelper())->update($this->checkTimeFile, json_encode($lastCheckTimes, JSON_UNESCAPED_UNICODE));
        } else {
            @file_put_contents($this->checkTimeFile, json_encode($lastCheckTimes, JSON_UNESCAPED_UNICODE));
        }
    }

    private function getPreviousContentFilePath(string $filePath): string
    {
        return $this->logDir . basename($filePath) . '.previous';
    }

    private function getChangedFields(string $filePath)
    {
        $previousContentFile = $this->getPreviousContentFilePath($filePath);

        if (!is_file($previousContentFile)) {
            // Первичная инициализация слепка
            $current = @file($filePath, FILE_IGNORE_NEW_LINES) ?: [];
            if (class_exists(\App\helpers\FileHelper::class)) {
                (new FileHelper())->update($previousContentFile, implode("\n", $current));
            } else {
                @file_put_contents($previousContentFile, implode("\n", $current));
            }
            return 'Initial file state recorded, no previous data for comparison';
        }

        $previousContent = @file($previousContentFile, FILE_IGNORE_NEW_LINES) ?: [];
        $currentContent  = @file($filePath, FILE_IGNORE_NEW_LINES) ?: [];

        $previousLinesMap = array_flip($previousContent);
        $currentLinesMap  = array_flip($currentContent);

        $changed = [];

        foreach ($currentContent as $lineNumber => $line) {
            if (!isset($previousLinesMap[$line])) {
                $changed[] = "Line " . ($lineNumber + 1) . ": {$line}";
            }
        }
        foreach ($previousContent as $lineNumber => $line) {
            if (!isset($currentLinesMap[$line])) {
                $changed[] = "Line " . ($lineNumber + 1) . " (deleted): {$line}";
            }
        }

        // Обновляем слепок
        if (class_exists(\App\helpers\FileHelper::class)) {
            (new FileHelper())->update($previousContentFile, implode("\n", $currentContent));
        } else {
            @file_put_contents($previousContentFile, implode("\n", $currentContent));
        }

        return $changed ? implode(', ', $changed) : 'No significant changes';
    }

    private function deleteOldLogFiles(): void
    {
        $days  = 30;
        $files = glob($this->logDir . 'files_*.log') ?: [];
        $now   = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileModifiedTime = @filemtime($file) ?: 0;
                if ($fileModifiedTime && ($now - $fileModifiedTime) >= ($days * 86400)) {
                    @unlink($file);
                }
            }
        }
    }
}
