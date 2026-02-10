<?php

namespace App\Services\Pdf;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Services\Log\EventLogService;
use Knp\Snappy\Pdf;
use Phalcon\Di\Injectable;

class PdfService extends Injectable
{
    use LogTrait;
    /**
     * @var float
     */
    private float $startTime;

    /**
     * Generates a PDF from the given HTML template and saves it to the specified path.
     *
     * @param string $template The path to the HTML template.
     * @param string $path The path where the PDF should be saved.
     * @param string|null $orientation The orientation of the PDF (optional).
     *
     * @throws AppException If an error occurs during PDF generation.
     */
    public function generate(string $template, string $path, string $orientation = null): void
    {
        setlocale(LC_CTYPE, "en_US.UTF-8");
        $env = [
            'LANG' => 'en_US.UTF-8',
            'LC_ALL' => 'en_US.UTF-8'
        ];

        $this->startTime = microtime(true);
        $wkhtmltopdf_path = getenv('WKHTMLTOPDF_PATH');
        try {
            // Проверка наличия wkhtmltopdf
            if (!file_exists($wkhtmltopdf_path)) {
                $this->writeLog('wkhtmltopdf не найден по указанному пути.', 'action', 'WARNING');
                throw new AppException('wkhtmltopdf не найден по указанному пути.');
            }

            // Проверка прав на директорию
            if (!is_writable(dirname($path))) {
                $this->writeLog('Невозможно записать файл в указанную директорию.', 'action', 'WARNING');
                throw new AppException('Невозможно записать файл в указанную директорию.');
            }

            // Проверка существования HTML-шаблона
            if (!file_exists($template)) {
                $this->writeLog('Шаблон HTML не найден: ' . $template, 'action', 'WARNING');
                throw new AppException('Шаблон HTML не найден: ' . $template);
            }

            $snappy = new Pdf($wkhtmltopdf_path, [], $env);
            // Настройка Snappy
            $snappy->setOption('page-size', 'A4');
            $snappy->setOption('dpi', 150);
            $snappy->setOption('margin-top', 10);
            $snappy->setOption('margin-bottom', 10);
            $snappy->setOption('margin-left', 10);
            $snappy->setOption('margin-right', 10);
            $snappy->setOption('enable-local-file-access', true);
            $snappy->setOption('disable-smart-shrinking', true);
            $snappy->setOption('no-pdf-compression', false);
            $snappy->setOption('print-media-type', true);
            $snappy->setOption('image-quality', 70);
            $snappy->setOption('lowquality', true);
            $snappy->setOption('encoding', 'UTF-8');

            if ($orientation !== null) {
                $snappy->setOption('orientation', $orientation);
            }

            if (file_exists($path)) {
                unlink($path);
            }

            // Генерация PDF
             $snappy->generate($template, $path);

        } catch (\Exception $e) {
            $this->writeLog('Ошибка при генерации PDF: '.$e->getMessage(), 'action', 'WARNING');
            throw new AppException('Ошибка при генерации PDF', 0, $e);
        }
    }
}