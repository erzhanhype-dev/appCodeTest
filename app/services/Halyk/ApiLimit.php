<?php
namespace App\Services\Halyk;

class ApiLimit
{

    private $ip;
    private $limitPerSecond;

    public function __construct(string $ip, int $limitPerSecond = 5)
    {
        $this->ip = $ip;
        $this->limitPerSecond = $limitPerSecond;
    }

    public function checkLimit(): bool
    {
        $filePath = APP_PATH . "/storage/temp/apiReqCounter.json";

        if (!file_exists($filePath)) {
            touch($filePath);
            chmod($filePath, 0600);
            file_put_contents($filePath, json_encode([]));
        }

        $currentTime = time();
        $throttleTime = $currentTime - 60;
        $reqCountPerSecond = 0;
        $newReqTimeList = [];

        $handle = fopen($filePath, 'c+');
        if (flock($handle, LOCK_EX)) {
            $fileContent = stream_get_contents($handle);
            $reqTimeList = json_decode($fileContent, true) ?: [];

            foreach ($reqTimeList as $entry) {
                if ($entry["timestamp"] >= $throttleTime) {
                    $newReqTimeList[] = $entry;
                    if ($entry["timestamp"] == $currentTime) {
                        $reqCountPerSecond++;
                    }
                }
            }

            if ($reqCountPerSecond >= $this->limitPerSecond) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }

            $newReqTimeList[] = [
                "ip" => $this->ip,
                "timestamp" => $currentTime
            ];

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($newReqTimeList));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return true;
    }
}