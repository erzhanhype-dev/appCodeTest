<?php

namespace App\Controllers;

use Phalcon\Mvc\Controller;

class ErrorController extends Controller
{
    public function show404Action(): string
    {
        // Устанавливаем статус 404
        $this->response->setStatusCode(404, 'Not Found');
        // Простой ответ
        return "404 - Страница не найдена!";
    }
}