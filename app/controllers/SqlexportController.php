<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\Response;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use TaskLog;
use User;

class SqlexportController extends ControllerBase
{
    /**
     * Форма + список последних задач
     */
    public function indexAction()
    {
        $currentPage = $this->request->getQuery('page', 'int', 1);
        $limit = 10; // Количество записей на странице

        // Создаем builder для пагинации
        $builder = $this->modelsManager->createBuilder()
            ->from(['t' => TaskLog::class])
            ->where("t.name = 'sql_export'")
            ->orderBy('t.created DESC');

        // Создаем пагинатор
        $paginator = new QueryBuilderPaginator([
            'builder' => $builder,
            'limit'   => $limit,
            'page'    => $currentPage
        ]);

        $page = $paginator->paginate();

        $this->view->setVar('tasks', $page->items);
        $this->view->setVar('page', $page);
    }
    /**
     * Создаёт запись в task_log (ставим SELECT в очередь)
     */
    public function enqueueAction()
    {
        $user = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect('sqlexport/index');
        }

        $sql = trim($this->request->getPost('sql', 'string', ''));

        if ($sql === '') {
            $this->flashSession->error('SQL-запрос пустой');
            return $this->response->redirect('sqlexport/index');
        }

        // простая проверка, что это SELECT и без мусора
        $error = null;
        if (!$this->isSelectQuerySafe($sql, $error)) {
            $this->flashSession->error('Запрос отклонён: ' . $error);
            return $this->response->redirect('sqlexport/index');
        }

        $payload = json_encode([
            'sql' => $sql,
            'userId' => $user ? (int)$user->id : null,
        ], JSON_UNESCAPED_UNICODE);

        $task = new TaskLog();
        $task->name = 'sql_export';
        $task->status = 'pending';
        $task->payload = $payload;
        $task->created = time();

        // Fix: Do not explicitly set null if the DB column is NOT NULL.
        // Let the DB handle defaults if these are null, or set empty defaults if required.
        // If your DB allows NULL, these lines are fine, but if not, they caused the crash.
        $task->filename = null;
        $task->completed = null;

        try {
            // Attempt to save
            if ($task->save() === false) {
                // Handle Soft Validation Errors (Model rules)
                $messages = $task->getMessages();
                $text = [];
                foreach ($messages as $msg) {
                    $text[] = (string)$msg;
                }
                $this->flashSession->error('Валидация не прошла: ' . implode('; ', $text));
            } else {
                // Success
                $this->flashSession->success('Задача поставлена в очередь, ID: ' . $task->id);
            }

        } catch (\PDOException $e) {
            // Handle Hard SQL Errors (Constraints, Types, Connection)
            // This catches the specific error from your log
            $this->flashSession->error('Ошибка базы данных (PDO): ' . $e->getMessage());

        } catch (\Exception $e) {
            // Handle General Errors
            $this->flashSession->error('Системная ошибка: ' . $e->getMessage());
        }

        return $this->response->redirect('sqlexport/index');
    }

    /**
     * Скачивание готового файла
     */
    public function downloadAction($id)
    {
        $id = (int)$id;

        // No need to fetch user here unless you want to restrict download to the owner
        // $user = User::getUserBySession();

        $params = [
            'conditions' => 'id = :id: AND name = :name: AND status = :status:',
            'bind' => [
                'id' => $id,
                'name' => 'sql_export',
                'status' => 'done',
            ],
        ];

        /** @var TaskLog|null $task */
        $task = TaskLog::findFirst($params);

        if (!$task || !$task->filename || !is_file($task->filename)) {
            $this->flashSession->error('Файл не найден или ещё не готов');
            return $this->response->redirect('sqlexport/index');
        }

        // Disable view to prevent HTML injection into the download
        $this->view->disable();

        $response = new Response();
        $response->setHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->setHeader(
            'Content-Disposition',
            'attachment; filename="' . basename($task->filename) . '"'
        );
        $response->setHeader('Content-Length', filesize($task->filename));
        $response->setContent(file_get_contents($task->filename));

        return $response;
    }

    /**
     * Проверка, что запрос безопасный SELECT
     */
    private function isSelectQuerySafe(string $sql, ?string &$error = null): bool
    {
        if (strlen($sql) > 8000) {
            $error = 'слишком длинный запрос';
            return false;
        }

        if (!preg_match('/^\s*select/i', $sql)) {
            $error = 'разрешены только SELECT-запросы';
            return false;
        }

        $blockedPatterns = [
            '/\binto\s+outfile\b/i',
            '/\bload_file\s*\(/i',
            '/\bunion\b/i', // Added extra safety against union based injection if needed
            '/\bdelete\b/i',
            '/\bupdate\b/i',
            '/\binsert\b/i',
            '/\bdrop\b/i',
            '/\btruncate\b/i'
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $error = 'запрос содержит запрещённую конструкцию';
                return false;
            }
        }

        $error = null;
        return true;
    }

    public function listJsonAction() {
        $tasks = TaskLog::find([
            "conditions" => "name = 'sql_export'",
            'order' => 'id DESC',
            'limit' => 10
        ]);
        $data = [];

        foreach ($tasks as $task) {
            $data[] = [
                'id' => $task->id,
                'status' => $task->status,
                'error' => $task->error,
                'created_fmt' => date('Y-m-d H:i:s', $task->created),
                'completed_fmt' => $task->completed ? date('Y-m-d H:i:s', $task->completed) : null,
                'filename' => $task->filename
            ];
        }

        return $this->response->setJsonContent(['tasks' => $data]);
    }
}