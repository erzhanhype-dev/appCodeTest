<?php
namespace App\Services\Log;

use Phalcon\Di\Injectable;
use TaskLog;

class TaskLogService extends Injectable
{
    public function start($data = null): TaskLog
    {
        $taskLog = TaskLog::findFirst(['condition' => 'name = kap_truck']);
        if($taskLog){
            $task = $taskLog;
        }else{
            $task = new TaskLog();
        }
        $task->name = $data ? $data['name'] : null;
        $task->created = time();
        $task->status = 'progress';
        $task->save();

        return $task;
    }

    public function complete($taskLogId, $data = null): TaskLog
    {
        $task = TaskLog::findFirstById($taskLogId);
        $task->completed = time();
        $task->status = 'completed';
        $task->filename = $data ? $data['filename'] : null;
        $task->save();

        return $task;
    }
}