<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\Car\CarService;
use App\Services\Cms\CmsService;
use App\Services\Fund\FundGoodsDocumentService;
use App\Services\Fund\FundService;
use App\Services\Goods\GoodsService;
use Car;
use ControllerBase;
use FundCar;
use FundFile;
use FundGoods;
use FundLogs;
use FundProfile;
use Goods;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\View;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefCarCat;
use RefCarValue;
use RefCountry;
use RefFund;
use RefFundKeys;
use RefModel;
use RefTnCode;
use User;

class FundController extends ControllerBase
{
    public function indexAction(): View|ResponseInterface
    {
        $this->session->remove("fund");
        $auth = User::getUserBySession();

        // 1) Обработка формы
        if ($this->request->isPost()) {
            // Сброс
            if ($this->request->getPost('reset') === 'all') {
                unset($_SESSION['fund_filter_year'], $_SESSION['fund_filter_status'],
                    $_SESSION['fund_filter_type'], $_SESSION['fund_filter_entity_type']);
                return $this->response->redirect('/fund/');
            }

            // Значения из формы
            $years = (array)$this->request->getPost('year', 'int');                 // [2023,2024]
            $status = (array)$this->request->getPost('status');                     // ['FUND_...']
            $type = (array)$this->request->getPost('type');                         // ['INS','EXP']
            $entityType = (array)$this->request->getPost('entity_type');            // ['CAR','GOODS']

            // Белые списки на всякий случай
            $statusAllowed = ['FUND_NEUTRAL', 'FUND_REVIEW', 'FUND_DECLINED', 'FUND_PREAPPROVED', 'FUND_TOSIGN', 'FUND_DONE', 'FUND_ANNULMENT'];
            $typeAllowed = ['INS', 'EXP'];
            $entityAllowed = ['CAR', 'GOODS'];

            $years = array_values(array_unique(array_filter($years, 'is_int')));
            $status = array_values(array_intersect($status, $statusAllowed));
            $type = array_values(array_intersect($type, $typeAllowed));
            $entityType = array_values(array_intersect($entityType, $entityAllowed));

            if ($years) $_SESSION['fund_filter_year'] = json_encode($years, JSON_UNESCAPED_UNICODE);
            if ($status) $_SESSION['fund_filter_status'] = json_encode($status, JSON_UNESCAPED_UNICODE);
            if ($type) $_SESSION['fund_filter_type'] = json_encode($type, JSON_UNESCAPED_UNICODE);
            if ($entityType) $_SESSION['fund_filter_entity_type'] = json_encode($entityType, JSON_UNESCAPED_UNICODE);

            return $this->response->redirect('/fund/'); // PRG, чтобы избежать повторной отправки
        }

        // 2) Значения по умолчанию
        if (!isset($_SESSION['fund_filter_year'])) {
            $_SESSION['fund_filter_year'] = json_encode([(int)date('Y')]);
        }
        if (!isset($_SESSION['fund_filter_status'])) {
            $_SESSION['fund_filter_status'] = json_encode(['FUND_NEUTRAL', 'FUND_REVIEW', 'FUND_DECLINED', 'FUND_PREAPPROVED', 'FUND_TOSIGN', 'FUND_DONE', 'FUND_ANNULMENT']);
        }
        if (!isset($_SESSION['fund_filter_type'])) {
            $_SESSION['fund_filter_type'] = json_encode(['INS', 'EXP']);
        }
        if (!isset($_SESSION['fund_filter_entity_type'])) {
            $_SESSION['fund_filter_entity_type'] = json_encode(['CAR', 'GOODS']);
        }

        $s_years = json_decode($_SESSION['fund_filter_year'], true) ?: [(int)date('Y')];
        sort($s_years);

        // 3) Границы дат: строки для DATETIME
        $dt_begin = sprintf('%d-01-01 00:00:00', (int)min($s_years));
        $dt_end = sprintf('%d-12-31 23:59:59', (int)max($s_years));

        $p_status = json_decode($_SESSION['fund_filter_status'], true) ?: [];
        $p_type = json_decode($_SESSION['fund_filter_type'], true) ?: [];
        $p_entity_type = json_decode($_SESSION['fund_filter_entity_type'], true) ?: [];

        $numberPage = (int)$this->request->getQuery("page", "int", 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(['f' => FundProfile::class])
            ->columns('f.id, f.number AS f_number, f.ref_fund_key, f.amount, f.created, f.md_dt_sent, f.type, f.approve, f.blocked, f.entity_type')
            ->where('f.user_id = :uid:', ['uid' => $auth->id])
            ->andWhere('f.created BETWEEN :dt_begin: AND :dt_end:', [
                'dt_begin' => strtotime($dt_begin),
                'dt_end' => strtotime($dt_end),
            ])
            ->orderBy('f.id DESC')
            ->groupBy('f.id');

        if ($p_status) {
            $builder->inWhere('f.approve', $p_status);
        }
        if ($p_type) {
            $builder->inWhere('f.type', $p_type);
        }
        if ($p_entity_type) {
            $builder->inWhere('f.entity_type', $p_entity_type);
        }

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => $numberPage,
        ]);
        $this->view->page = $paginator->paginate();

        $this->logAction('Просмотр списка заявок', 'access');

        return $this->view->pick('fund/index');
    }

    /**
     * @throws AppException
     */
    public function reviewAction($pid): ResponseInterface
    {
        $this->checkFundAvailability();

        $auth = User::getUserBySession();
        $f = FundProfile::findFirstById($pid);
        $can = true;
        $this->view->disable();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security', 'ALERT');
            return $this->response->redirect("/fund")->send();
        }

        if ($f) {
            $year = date('Y', $f->created);

            if ($year != date('Y')) {
                $message = "Невозможно отправить на рассмотрение (несоответствие года), создайте новую заявку!";
                $this->flash->error($message);
                $this->logAction($message);
                return $this->response->redirect("/fund")->send();
            }

            if ($f->created < START_ZHASYL_DAMU_FUND_MAY) {
                $message = "Невозможно отправить на рассмотрение, создайте новую заявку!";
                $this->logAction($message);
                $this->flash->error($message);
                return $this->response->redirect("/fund")->send();
            }

            if (__checkRefFundUser($auth->idnum) != true) {
                $this->logAction("У вас нет прав на это действия!", 'security', 'ALERT');
                $this->flash->error("У вас нет прав на это действия!");
                return $this->response->redirect("/home/index")->send();
            } else {
                $fund_cars = FundCar::findByFundId($f->id);

                if ($fund_cars) {
                    foreach ($fund_cars as $car) {
                        $__lim = __checkLimitsWhenSend($f->id, $car->ref_car_cat, $car->volume, $car->ref_st_type, $car->date_produce, date('Y', $f->created));
                        //echo  "количество доступных лимитов(общий лимит - отправлен на рассмотрение): ".$__lim;
                        if ($__lim <= 0) {
                            $can = false;
                            $message = "Превышение лимитов по ТС $car->vin.";
                            $this->logAction($message);
                            $this->flash->error($message);
                        }
                    }
                }
            }

            if ($f->approve == 'FUND_DECLINED') {
                $message = "Заявка была отклонена, отправка невозможна, создайте новую заявку.";
                $this->logAction($message);
                $this->flash->error($message);
                return $this->response->redirect("/fund/view/$pid")->send();
            }
        } else {
            $this->flash->error("Заявка не найдена!!");
            return $this->response->redirect("/fund")->send();
        }

        if ($auth->id != $f->user_id) {
            $message = 'Нет доступа!';
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/fund/view/$pid")->send();
        }

        $types = ($f->entity_type == 'GOODS')
            ? ['fund_app3', 'fund_app4', 'fund_app5', 'fund_app6', 'fund_app11', 'fund_app14']
            : ['fund_app3', 'fund_app4', 'fund_app5', 'fund_app6', 'fund_app7', 'fund_app8', 'fund_app9',
                'fund_app10', 'fund_app11', 'fund_app12', 'fund_app13', 'fund_app14'];

        $fund_files = FundFile::find([
            'conditions' => "type IN ({types:array}) AND visible = 1 AND fund_id = :fund_id:",
            'bind' => [
                'fund_id' => $f->id,
                'types' => $types
            ]
        ]);

        if ($f->entity_type == 'GOODS') {
            if ($fund_files->count() !== count($types)) {
                $msg = $this->translator->_('fund_application_not_loaded');
                $this->logAction($msg);
                $this->flash->error($msg);
                return $this->response->redirect("/fund/view/$pid")->send();
            }
        }

        if ($can != false) {
            $f->blocked = 1;
            $f->md_dt_sent = time();
            $f->approve = 'FUND_REVIEW';
            $f->old_amount = $f->amount;
            $f->save();

            // логгирование
            $l = new FundLogs();
            $l->login = $auth->idnum;
            $l->action = 'SEND_TO_REVIEW';
            $l->fund_id = $pid;
            $l->dt = time();
            $l->meta_before = '—';
            $l->meta_after = json_encode(array($f));
            $l->save();

            $this->logAction('Отправлено на рассмотрение');
        }
        return $this->response->redirect("/fund/view/$pid")->send();
    }

    public function tosignAction($pid): ResponseInterface
    {
        $auth = User::getUserBySession();
        $f = FundProfile::findFirstById($pid);
        $can = true;

        if ($f->entity_type == "CAR") {
            $checkModelNotSetCars = FundCar::count([
                "conditions" => "fund_id = :pid: AND model_id = 0",
                "bind" => [
                    "pid" => $f->id
                ]
            ]);

            if ($checkModelNotSetCars > 0) {
                $can = false;
                $this->flash->error("Имеются незаполненные поля!");
                return $this->response->redirect("/fund/view/$pid");
            }
        }

        if ($f->approve == 'FUND_DECLINED') {
            $message = "Заявка была отклонена, отправка невозможна, создайте новую заявку.";
            $this->logAction($message);
            $this->flash->error($message);
            return $this->response->redirect("/fund/view/$pid");
        }

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security');
            return $this->response->redirect("/fund");
        }

        if ($auth->id != $f->user_id) {
            $this->logAction("У вас нет прав на это действие!", 'security');
            return $this->response->redirect("/fund/view/$pid");
        }

        if ($can != false) {
            $f->blocked = 1;
            $f->approve = 'FUND_TOSIGN';
            $f->save();
        }

        return $this->response->redirect("/fund/view/$pid");
    }

    public function todeclineAction($pid)
    {
        $auth = User::getUserBySession();
        $f = FundProfile::findFirstById($pid);
        if ($auth->id != $f->user_id) return;

        $f->blocked = 0;
        $f->approve = 'FUND_NEUTRAL';
        $f->save();

        return $this->response->redirect("/fund/view/$pid");
    }

    public function signAction()
    {
        $auth = User::getUserBySession();
        $__settings = $this->session->get("__settings");

        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST, true) || in_array("BLOCK_ALL", FUND_BLACK_LIST, true)) {
            return $this->response->redirect("/fund");
        }

        $pid = $this->request->getPost("orderId");
        $hash = $this->request->getPost("fundHash");
        $sign = $this->request->getPost("fundSign");
        $type = $this->request->getPost("orderType");

        $f = FundProfile::findFirstById($pid);
        if (!$f) {
            return $this->response->redirect("/fund");
        }

        $isGoods = ($f->entity_type === 'GOODS');

        $types = $isGoods
            ? ['fund', 'app3', 'app4', 'app5', 'app6', 'app11', 'app14']
            : ['fund', 'app3', 'app4', 'app5', 'app6', 'app7', 'app8', 'app9', 'app10', 'app11', 'app12', 'app13', 'app14'];

        $edsService = new CmsService();
        $result = $edsService->check($hash, $sign);
        $j = $result['data'];
        $sign = $j['sign'];

        if ($__settings['iin'] !== $j['iin'] || $__settings['bin'] !== $j['bin']) {
            $message = "Вы используете несоответствующую профилю подпись.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/fund/view/$pid")->send();
        }

        if ($result['success'] !== true) {
            $message = "Подпись не прошла проверку!";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/fund/view/$pid")->send();
        }

        // Сохраняем подпись
        if ($type === 'acc') {
            $f->sign_acc = $sign;
        } else {
            $f->sign = $sign;
        }
        $f->old_amount = $f->amount;
        $f->save();

        // Для acc только сохраняем подпись и выходим
        if ($type === 'acc') {
            $message = "Документ подписан.";
            $this->logAction($message);
            $this->flash->success($message);
            return $this->response->redirect("/fund/view/$pid")->send();
        }

        $fundGoodsDocumentService = new FundGoodsDocumentService();

        // Основная генерация документов по указанному типу / типам
        $typesToProcess = ($type === 'all') ? $types : [$type];
        foreach ($typesToProcess as $tp) {
            if ($isGoods) {
                if ($tp === 'fund') {
                    $fundGoodsDocumentService->generateStatement($f);
                } else {
                    $fundGoodsDocumentService->generateApp($f, $tp);
                }
            } else {
                __genFund($pid, $tp, $j);
            }
        }

        // Проверяем наличие файлов (только приложения, без основного fund-документа)
        $fileTypes = [];
        foreach ($types as $t) {
            if ($t !== 'fund') {
                $fileTypes[] = "fund_" . $t;
            }
        }

        $fund_files = FundFile::find([
            'conditions' => "type IN ({types:array}) AND visible = 1 AND fund_id = :fund_id:",
            'bind' => [
                'fund_id' => $f->id,
                'types' => $fileTypes
            ],
        ]);

        // Если файлов нет — догенерируем весь набор
        if ($fund_files->count() === 0) {
            if ($isGoods) {
                // Поведение как в исходнике:
                // в fallback для GOODS — только generateApp, даже для 'fund'
                foreach ($types as $tp) {
                    $fundGoodsDocumentService->generateApp($f, $tp);
                }
            } else {
                foreach ($types as $tp) {
                    __genFund($pid, $tp, $j);
                }
            }
        }

        $message = "Документ подписан.";
        $this->logAction($message);
        $this->flash->success($message);
        return $this->response->redirect("/fund/view/$pid")->send();
    }


    /**
     * Скачивание заявления для заполнения.
     * @param int $pid
     * @param string $type car|goods
     * @return void
     */
    public function applicationAction($pid)
    {
        $auth = User::getUserBySession();
        $this->view->disable();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security');
            return $this->response->redirect("/fund/");
        }

        $f = FundProfile::findFirstById($pid);

        if ($f) {
            $hash = $f->hash;
            $sign = $f->sign_hof;

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            if ($result['success'] === true) {
                if ($j['iin'] || $j['bin']) {
                    __genFund($pid, 'payment', $j);
                    $this->logAction("Заявление сформирована");
                }
            } else {
                $this->logAction("Ошибка: Подпись не прошла проверку!", 'security');
                $this->flash->error("Ошибка: Подпись не прошла проверку!");
                return $this->response->redirect("/home/index/");
            }
        }

    }

    /**
     * Загрузка необходимых документов.
     * @return void
     */
    public function docAction()
    {
        $order = $this->request->getPost("order_id");
        $doc_type = $this->request->getPost("doc_type");
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($order);

        if ($f->user_id == $auth->id || $auth->isEmployee()) {
            if ($this->request->hasFiles() && $doc_type != '') {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getSize() > 0) {
                        $nf = new FundFile();
                        $nf->fund_id = $f->id;
                        $nf->type = $doc_type;
                        $nf->original_name = $file->getName();
                        $nf->ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                        $nf->save();
                        $file->moveTo(APP_PATH . "/private/fund/" . 'fund_' . $nf->type . '_' . $nf->id . "." . pathinfo($file->getName(), PATHINFO_EXTENSION));
                        $this->flash->success("Файл добавлен.");
                    }
                }
            } else {
                $this->flash->warning("Укажите тип документа.");
            }
        } else {
            $message = "У вас нет прав на это действие.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->warning($message);
        }

        if ($auth->isEmployee()) {
            return $this->response->redirect("/moderator_fund/view/$order");
        } else {
            return $this->response->redirect("/fund/view/$order");
        }

    }

    /**
     * Скачать документ.
     * @param int $id
     * @return void
     */
    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund/";
        $auth = User::getUserBySession();

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);

        if ($f->user_id == $auth->id || ($auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET')) {
            if ($f->entity_type == 'GOODS') {
                if ($pf->type == 'application' || $pf->type == 'calculation_cost') {
                    if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                } else {
                    if (file_exists($path . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                }
            } else {
                if ($f->created > 1744012800) {
                    if ($pf->type == 'calculation_cost' || $pf->type == 'other') {
                        if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                        }
                    } else {
                        if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                        }
                    }
                } else {
                    if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                    }
                }
            }

            $this->logAction('Просмотр файла', 'access');
        }
    }

    /**
     * Просмотреть документ.
     * @param int $id
     * @return void
     */
    public function viewdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/fund/";

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);
        $auth = User::getUserBySession();
        if ($f->user_id == $auth->id || ($auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET')) {
            if ($f->entity_type == 'GOODS') {
                if ($pf->type == 'application' || $pf->type == 'calculation_cost' || $pf->type == 'other') {
                    if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                } else {
                    if (file_exists($path . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                    }
                }
            } else {
                if ($f->created > 1744012800) {
                    if ($pf->type == 'calculation_cost' || $pf->type == 'other') {
                        if (file_exists($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . 'fund_' . $pf->type . '_' . $pf->id . '.' . $pf->ext, null, 'view');
                        }
                    } else {
                        if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                            __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                        }
                    }
                } else {
                    if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                        __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
                    }
                }
            }

            $this->logAction('Просмотр файла', 'access');
        }
    }

    /**
     * Просмотреть документ.
     * @param int $id
     * @return void
     */
    public function viewtmpAction($id, $type)
    {
        $fund = FundProfile::findFirstById($id);
        if ($fund->entity_type == 'GOODS') {
            $fundGoodsDocumentService = new FundGoodsDocumentService();
            if ($type == 'fund') {
                $filePath = $fundGoodsDocumentService->generateStatement($fund);
            } else {
                $filePath = $fundGoodsDocumentService->generateApp($fund, $type);
            }
        } else {
            __genFund($id, $type, null);
            $filePath = APP_PATH . "/storage/temp/" . $type . '_' . $id . '.pdf';
        }
        $this->view->disable();
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($id);

        if ($f->user_id == $auth->id || ($auth->isEmployee() || $auth->fund_stage != 'STAGE_NOT_SET')) {
            if (file_exists($filePath)) {
                header('Content-Type: application/pdf');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);

                $this->logAction('Просмотр файла', 'access');
            }
        }
    }

    /**
     * Удаление документа к заявке.
     * @param int $id
     * @return void
     */
    public function rmdocAction($id)
    {
        $path = APP_PATH . "/private/fund/";
        $auth = User::getUserBySession();

        $pf = FundFile::findFirstById($id);
        $f = FundProfile::findFirstById($pf->fund_id);

        if ($auth->isAdminSoft() || (!$f->blocked && $f->user_id == $auth->id)) {
            $pf->visible = 0;
            $pid = $pf->fund_id;
            $pf->save();
            $this->logAction('Файл удален');
        } else {
            $message = "Вы не можете удалить этот файл.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/fund/view/");
        }

        if ($auth->isAdminSoft()) {
            return $this->response->redirect("/fund/view/$pid");
        } else {
            return $this->response->redirect("/fund/view/$pid");
        }
    }

    /**
     * Восстановление документа к заявке.
     * @param int $id
     */
    public function restoreAction($id)
    {
        $pf = FundFile::findFirstById($id);
        $auth = User::getUserBySession();

        if ($auth->isAdminSoft()) {
            $pf->visible = 1;
            $pid = $pf->fund_id;
            $pf->save();

            $this->logAction('Восстановление файла');
        }

        return $this->response->redirect("/fund/view/$pid");
    }

    public function viewAction(int $pid = 0)
    {
        $auth = User::getUserBySession();

        $f = FundProfile::findFirstById($pid);

        if ((int)$f->user_id !== (int)$auth->id) {
            $message = 'Нет прав на просмотр заявки.';
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect('/fund');
        }

        if ($f->user_id == $auth->id && $f->approve == 'FUND_DECLINED') {
            $msg = FundLogs::findFirst([
                "conditions" => "fund_id = :fund_id: AND action = 'FUND_MSG'",
                "bind" => [
                    "fund_id" => $f->id
                ],
                "order" => "id DESC"
            ]);
            if ($msg) {
                $this->flash->warning('Сообщение менеджера:' . $msg->meta_after);
            }
        }

        if ($f) {

            if ($f->hash != NULL) {
                $signData = $f->hash;
            } else {
                $signData = __signFund($pid, $this);
            }

            if ($f->number == NULL) {
                $f->number = __getFundNumber($f->id);
                $f->save();
            }

            $files = FundFile::find(array(
                "visible = 1 AND fund_id = :pid:",
                "bind" => array(
                    "pid" => $f->id
                )
            ));

            $app_form = false;
            $app_form_query = FundFile::find(array(
                "type = 'application' AND fund_id = :pid: AND visible = 1",
                "bind" => array(
                    "pid" => $f->id
                )
            ));

            if (count($app_form_query) > 0) {
                $app_form = true;
            }

            if ($f->entity_type == 'CAR') {
                $obj_count = FundCar::count([
                    "conditions" => "fund_id = :pid:",
                    "bind" => [
                        "pid" => $f->id
                    ]
                ]);
            } else {
                $obj_count = FundGoods::count([
                    "conditions" => "fund_id = :pid:",
                    "bind" => [
                        "pid" => $f->id
                    ]
                ]);
            }

            $fundService = new FundService();
            $auth = User::getUserBySession();
            $user_id = $auth->id;
            $cars = [];
            $fund_cars = [];
            $goods = [];
            $fund_goods = [];

            if ($f->user_id == $auth->id) {
                if ($f->entity_type == 'CAR') {
                    $fund_cars = $fundService->getFundCarsByFundId($f);
                } else if ($f->entity_type == 'GOODS') {
                    $fund_goods = $fundService->getFundGoodsByFundId($f);
                }
            }

            $_s = (array)$this->session->get("__settings");

            $this->view->setVars(array(
                "pid" => $pid,
                "fund" => $f,
                "files" => $files,
                "app_form" => $app_form,
                "sign_data" => $signData,
                "obj_count" => $obj_count,
                "cars" => $cars,
                "fund_cars" => $fund_cars,
                "goods" => $goods,
                "fund_goods" => $fund_goods,
                "_s" => $_s,
                "auth" => $auth,
            ));

            $this->logAction('Просмотр заявки', 'access');

        }
    }

    /**
     * Правка заявки.
     * @param int $pid
     * @return void
     */
    public function editAction($pid)
    {
        $auth = User::getUserBySession();

        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction('Заблокированный пользователь', 'security');
            return $this->response->redirect("/fund");
        }

        if ($this->request->isPost()) {
            $f = FundProfile::findFirstById($pid);

            if ($auth->id != $f->user_id || $f->blocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/fund/index/");
            }

            $w_a = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("w_a"));
            $w_b = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("w_b"));
            $w_c = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("w_c"));
            $w_d = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("w_d"));
            $e_a = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("e_a"));
            $r_a = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("r_a"));
            $r_b = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("r_b"));
            $r_c = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("r_c"));
            $tc_a = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tc_a"));
            $tc_b = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tc_b"));
            $tc_c = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tc_c"));
            $tt_a = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tt_a"));
            $tt_b = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tt_b"));
            $tt_c = (float)str_replace([' ', ','], ['', '.'], $this->request->getPost("tt_c"));
            $period_start = $this->request->getPost("period_start");
            $period_end = $this->request->getPost("period_end");
            $country = $this->request->getPost("car_country");
            $ref_fund_key = $this->request->getPost("ref_fund_key");

            $f->period_start = strtotime($period_start . ' 00:00:00');
            $f->period_end = strtotime($period_end . ' 00:00:00');
            $f->ref_country_id = $country;
            $f->w_a = $w_a;
            $f->w_b = $w_b;
            $f->w_c = $w_c;
            $f->w_d = $w_d;
            $f->e_a = $e_a;
            $f->r_a = $r_a;
            $f->r_b = $r_b;
            $f->r_c = $r_c;
            $f->tc_a = $tc_a;

            if ($f->entity_type == 'CAR') {
                $f->tc_b = $tc_b;
                $f->tc_c = $tc_c;
                $f->tt_b = $tt_b;
            } else {
                $f->tc_b = 0;
                $f->tc_c = 0;
                $f->tt_b = 0;
            }

            $f->tt_a = $tt_a;
            $f->tt_c = $tt_c;

            $f->sum_before = 0;
            if ($ref_fund_key) {
                $f->ref_fund_key = $ref_fund_key;
            }

            if ($f->save()) {
                $message = "Изменения сохранены.";
                $this->logAction($message);
                $this->flash->success($message);
                __fundRecalc($pid);
            } else {
                $messages = $f->getMessages();
                $this->logAction("Нет возможности сохранить ваши изменения." . ' - ' . json_encode($messages));
                $this->flash->error("Нет возможности сохранить ваши изменения.");
            }
            return $this->response->redirect("/fund/index/");
        } else {
            $key_is_editable = true;
            $f = FundProfile::findFirstById($pid);
            $countries = RefCountry::find(array('id NOT IN (1, 201)'));
            $model = RefModel::find();
            $cars = FundCar::findByFundId($f->id);

            if (count($cars) > 0) {
                $key_is_editable = false;
            }

            if ($auth->id != $f->user_id || $f->blocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security');
                $this->flash->error($message);
                return $this->response->redirect("/fund/index/");
            }

            if ($f->type == 'INS') {
                $ref_fund_keys = RefFundKeys::find(array(
                    "name NOT IN ('START', 'N_20001_50000_ST') AND type = :type: AND entity_type = :entity_type:",
                    "bind" => array(
                        'type' => 'INS',
                        'entity_type' => $f->entity_type
                    ),
                    "order" => "weight ASC",
                ));
            } else {
                $ref_fund_keys = RefFundKeys::find(array(
                    "name NOT IN ('START_EXP') AND type = :type: AND entity_type = :entity_type:",
                    "bind" => array(
                        'type' => 'EXP',
                        'entity_type' => $f->entity_type
                    )
                ));
            }

            $this->view->setVars(array(
                "fund" => $f,
                "countries" => $countries,
                "model" => $model,
                "ref_fund_keys" => $ref_fund_keys,
                "key_is_editable" => $key_is_editable
            ));
        }
    }

    /**
     * Форма новой заявки.
     * @return void
     */
    public function newAction()
    {
        $auth = User::getUserBySession();
        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security');
            $this->flash->error("Невозможно создать объект.");
            return $this->response->redirect("/fund/");
        }

        if ($_GET["mode"] == "INS") {
            $countries = RefCountry::find([
                "conditions" => "id = :id:",
                "bind" => [
                    "id" => 71,
                ]
            ]);

            $ref_fund_keys = RefFundKeys::find([
                "conditions" => "name NOT IN ('START_EXP') AND type = :type: AND entity_type = :entity_type:",
                "bind" => [
                    "type" => "INS",
                    "entity_type" => $_GET["object"]
                ]
            ]);

        } else {
            $countries = RefCountry::find([
                "conditions" => "id <> :id: AND id NOT IN (1, 201)",
                "bind" => [
                    "id" => 71,
                ]
            ]);

            $ref_fund_keys = RefFundKeys::find([
                "conditions" => "name NOT IN ('START_EXP') AND type = :type: AND entity_type = :entity_type:",
                "bind" => [
                    "type" => "EXP",
                    "entity_type" => $_GET["object"]
                ]
            ]);
        }

        $this->view->setVars(array(
            "countries" => $countries,
            "ref_fund_keys" => $ref_fund_keys,
            "entity_type" => $_GET['object'],
        ));
    }

    /**
     * Добавить новую заявку (в базу).
     */
    public function addAction()
    {
        $auth = User::getUserBySession();

        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security');
            $this->flash->error("Невозможно создать объект.");
            return $this->response->redirect("/fund/");
        }

        if ($this->request->isPost()) {
            $type = $this->request->getPost("order_type");
            $toFloatOrNull = function (string $key): ?float {
                $v = $this->request->getPost($key);

                if ($v === null || $v === '') {
                    return 0;
                }
                if (is_array($v)) {
                    $v = reset($v);
                    if ($v === false || $v === null || $v === '') {
                        return 0;
                    }
                }

                $v = str_replace([' ', ','], ['', '.'], (string)$v);
                return ($v === '') ? 0 : (float)$v;
            };

            $w_a = $toFloatOrNull('w_a');
            $w_b = $toFloatOrNull('w_b');
            $w_c = $toFloatOrNull('w_c');
            $w_d = $toFloatOrNull('w_d');
            $e_a = $toFloatOrNull('e_a');
            $r_a = $toFloatOrNull('r_a');
            $r_b = $toFloatOrNull('r_b');
            $r_c = $toFloatOrNull('r_c');
            $tc_a = $toFloatOrNull('tc_a');
            $tc_b = $toFloatOrNull('tc_b');
            $tc_c = $toFloatOrNull('tc_c');
            $tt_a = $toFloatOrNull('tt_a');
            $tt_b = $toFloatOrNull('tt_b');
            $tt_c = $toFloatOrNull('tt_c');
            $period_start = $this->request->getPost("period_start");
            $period_end = $this->request->getPost("period_end");
            $country = $this->request->getPost("car_country");
            $ref_fund_key = $this->request->getPost("ref_fund_key");
            $entity_type = $this->request->getPost("entity_type");

            $f = new FundProfile();
            $f->created = time();
            $f->user_id = $auth->id;
            $f->type = $type;
            $f->period_start = strtotime($period_start . ' 00:00:00');
            $f->period_end = strtotime($period_end . ' 00:00:00');
            $f->ref_country_id = $country;
            $f->w_a = $w_a;
            $f->w_b = $w_b;
            $f->w_c = $w_c;
            $f->w_d = $w_d;
            $f->e_a = $e_a;
            $f->r_a = $r_a;
            $f->r_b = $r_b;
            $f->r_c = $r_c;
            $f->tc_a = $tc_a;
            $f->tt_a = $tt_a;
            $f->tt_c = $tt_c;

            if ($entity_type == 'CAR') {
                $f->tc_b = $tc_b;
                $f->tc_c = $tc_c;
                $f->tt_b = $tt_b;
            } else {
                $f->tc_b = 0;
                $f->tc_c = 0;
                $f->tt_b = 0;
            }

            $f->sum_before = 0;
            $f->ref_fund_key = $ref_fund_key;
            $f->entity_type = $entity_type;

            if ($f->save()) {
                __fundRecalc($f->id);

                $this->logAction("Заявка создана");
                return $this->response->redirect("/fund/view/$f->id");
            }
        }
    }

    /**
     * Импорт машин.
     * @param int $pid
     * @return void
     */

    public function importINSAction($pid)
    {
        $auth = User::getUserBySession();

        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь", 'security');
            return $this->response->redirect("/fund/");
        }

        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    public function importEXPAction($pid)
    {
        $auth = User::getUserBySession();

        $this->checkFundAvailability();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь", 'security');
            return $this->response->redirect("/fund/");
        }

        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    /**
     * Импорт полученных файлов.
     * @return void
     */
    public function uploadFromExcelINSAction()
    {
        $auth = User::getUserBySession();
        $user_id = $auth->id;

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь", 'security');
            return $this->response->redirect("/fund");
        }

        $order = $this->request->getPost("order_id");
        $profile = FundProfile::findFirstById($order);
        $successfully_added = 0;
        $vinNotFoundInUP = [];
        $existVin = [];
        $exceedLimit = [];

        if ($this->request->isPost()) {
            if ($user_id != $profile->user_id || $profile->blocked || $profile->type != "INS") {
                $this->logAction("Вы не имеете права совершать импорт.");
                $this->flash->error("Вы не имеете права совершать импорт.");
                return $this->response->redirect("/fund/index/");
            }

            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo(APP_PATH . "/storage/temp/fund_" . $order . ".csv");
                }
            }

            $import = file(APP_PATH . "/storage/temp/fund_" . $order . ".csv");
            foreach ($import as $key => $value) {

                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);
                    // кириллица в VIN
                    $car_vin = mb_strtoupper($val[0]);
                    $car_vin = preg_replace('/(\W^-)/', '', $car_vin);
                    $model_id = trim($val[1]);
                    $accept = true;

                    // проверяем старые заявки
                    $car_check = FundCar::findFirstByVin($car_vin);

                    if ($car_check) {
                        $accept = false;
                        $existVin[] = "VIN: $car_vin(в заявке №)" . __getFundNumber($car_check->fund_id);
                        continue;
                    }

                    // проверяем старый экспорт, до октября 2020
                    if (__checkInner($car_vin)) {
                        $accept = false;
                        $message = "VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.";
                        $this->logAction($message);
                        $this->flash->error($message);
                        continue;
                    }

                    if ($accept) {

                        // проверка ref_fund_key
                        if ($profile->ref_fund_key != NULL) {

                            $exploted = explode("_", $profile->ref_fund_key);
                            $category_id = '';
                            $volume_start = $exploted[1];
                            $volume_end = $exploted[2];
                            $st_type = 'c.ref_st_type = 0';

                            if ($exploted[0] == "TRACTOR") {
                                $category_id = '(13)';
                            } else if ($exploted[0] == "COMBAIN") {
                                $category_id = '(14)';
                            } else if ($exploted[0] == "M1") {
                                $category_id = '(1, 2)';
                            } else if ($exploted[0] == "M2M3") {
                                $category_id = '(9, 10, 11, 12)';
                            } else if ($exploted[0] == "N") {
                                $category_id = '(3, 4, 5, 6, 7, 8)';

                                if ($exploted[3] == "ST") {
                                    $st_type = 'c.ref_st_type > 0';
                                }
                            }


                            if ($volume_end && $volume_end > 0) {
                                $sql = <<<SQL
                  SELECT
                    c.id AS c_id,
                    c.volume AS volume,
                    c.ref_car_type_id AS ref_car_type_id,
                    c.cost AS cost,
                    c.date_import AS date_import,
                    c.ref_car_cat AS ref_car_cat,
                    c.status AS c_status,
                    c.ref_st_type AS ref_st_type,
                    c.calculate_method AS calculate_method,
                    c.electric_car AS electric_car,
                    c.profile_id AS profile_id
                  FROM Profile p
                    JOIN Transaction t
                    JOIN User u
                    JOIN Car c
                  WHERE
                    p.id = t.profile_id AND
                    p.type = 'CAR' AND
                    c.profile_id = p.id AND
                    p.user_id = u.id AND
                    t.approve = 'GLOBAL' AND
                    t.ac_approve = 'SIGNED' AND
                    c.ref_car_cat IN $category_id AND
                    c.volume >= $volume_start AND
                    c.volume <= $volume_end AND
                    $st_type AND
                    p.user_id = $user_id AND
                    c.vin = :vin:
                SQL;
                            }

                            if ($volume_start == 0 && !$volume_end) {
                                $sql = <<<SQL
                  SELECT
                    c.id AS c_id,
                    c.volume AS volume,
                    c.ref_car_type_id AS ref_car_type_id,
                    c.cost AS cost,
                    c.date_import AS date_import,
                    c.ref_car_cat AS ref_car_cat,
                    c.status AS c_status,
                    c.ref_st_type AS ref_st_type,
                    c.calculate_method AS calculate_method,
                    c.electric_car AS electric_car,
                    c.profile_id AS profile_id
                  FROM Profile p
                    JOIN Transaction t
                    JOIN User u
                    JOIN Car c
                  WHERE
                    p.id = t.profile_id AND
                    p.type = 'CAR' AND
                    c.profile_id = p.id AND
                    p.user_id = u.id AND
                    t.approve = 'GLOBAL' AND
                    t.ac_approve = 'SIGNED' AND
                    c.ref_car_cat IN $category_id AND
                    c.volume = $volume_start AND
                    $st_type AND
                    p.user_id = $user_id AND
                    c.vin = :vin:
                SQL;
                            }

                            $query = $this->modelsManager->createQuery($sql);
                            $carUP = $query->execute(
                                ['vin' => "$car_vin"]
                            );

                            if (count($carUP) > 0) {

                                $__lim = __checkLimits($profile->id, $carUP[0]->ref_car_cat, $carUP[0]->volume, $carUP[0]->ref_st_type, $carUP[0]->date_import, date('Y', $profile->created));

                                if ($__lim <= 0) {
                                    $exceedLimit[] = $car_vin;
                                } else {
                                    $sum = 0;

                                    $c = new FundCar();
                                    $c->ref_car_type_id = $carUP[0]->ref_car_type_id;
                                    $c->volume = $carUP[0]->volume;
                                    $c->vin = $car_vin;
                                    $c->date_produce = $carUP[0]->date_import;
                                    $c->fund_id = $profile->id;
                                    $c->ref_car_cat = $carUP[0]->ref_car_cat;
                                    $c->model_id = $model_id;
                                    $c->ref_st_type = $carUP[0]->ref_st_type;
                                    $c->calculate_method = $carUP[0]->calculate_method;
                                    $c->profile_id = $carUP[0]->profile_id;

                                    if ($carUP[0]->volume == 0 || $carUP[0]->electric_car == 1) {

                                        if ($carUP[0]->volume == 0) {
                                            $value = RefCarValue::findFirst(array(
                                                "conditions" => "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                                                "bind" => array(
                                                    "car_type" => $carUP[0]->ref_car_type_id,
                                                    "volume_start" => $carUP[0]->volume,
                                                    "volume_end" => $carUP[0]->volume
                                                )
                                            ));
                                        } else {
                                            $value = RefCarValue::findFirst(array(
                                                "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                                "bind" => array(
                                                    "car_type" => $carUP[0]->ref_car_type_id,
                                                    "volume_start" => $carUP[0]->volume,
                                                    "volume_end" => $carUP[0]->volume,
                                                )
                                            ));
                                        }

                                        $sum = __calculateCarByDate(date('d.m.Y', $carUP[0]->date_import), $carUP[0]->volume, json_encode($value), $carUP[0]->ref_st_type);

                                    } else {
                                        $sum = $carUP[0]->cost;
                                    }

                                    $c->cost = $sum;

                                    if ($c->save()) $successfully_added++;
                                }

                            } else {
                                $vinNotFoundInUP[] = $car_vin;
                            }

                        } else {
                            $this->flash->error("# Ошибка(ref_fun_key)!");
                        }
                    }
                }
            }

            __fundRecalc($profile->id);

        }

        if ($successfully_added > 0) {
            $message = "Успешно добавлено $successfully_added ТС";
            $this->logAction($message);
            $this->flash->success($message);
        }

        $exceedLimitlist = implode(", ", $exceedLimit);
        $exceedLimitCount = count($exceedLimit);

        if ($exceedLimitCount > 0) {
            $htmlexceedLimit = <<<TEXT
        Невозможно сохранить $exceedLimitCount машин, причина Превышение лимитов.
        <small id="car_date" class="form-text text-muted" data-toggle="collapse" data-target="#exceedLimitWhenFundINSCarUpload" aria-expanded="false"
        aria-controls="exceedLimitWhenFundINSCarUpload">
        Посмотреть список VIN кодов
          <icon data-feather="help-circle" type="button" color="green" width="18" height="18"></icon>
        </small>
        <div class="collapse" id="exceedLimitWhenFundINSCarUpload">
          <div class="card card-body">
            <div class="alert alert-danger" role="alert">
              <p>$exceedLimitlist</p>
            </div>
          </div>
        </div>
      TEXT;

            $this->flash->warning($htmlexceedLimit);
        }

        $existVinCount = count($existVin);

        if ($existVinCount > 0) {

            $vinList = NULL;

            foreach ($existVin as $key => $v) {
                $vinList .= ($key + 1) . ", $v;<br>";
            }

            $htmlExistsVin = <<<TEXT
        Невозможно сохранить $existVinCount машин, финансирование уже было предоставлено.
        <small id="car_date" class="form-text text-muted" data-toggle="collapse" data-target="#existsVinWhenFundINSCarUpload" aria-expanded="false"
        aria-controls="existsVinWhenFundINSCarUpload" >
        Посмотреть список
          <icon data-feather="help-circle" type="button"color="green" width="18" height="18"></icon>
        </small>
        <div class="collapse" id="existsVinWhenFundINSCarUpload">
          <div class="card card-body">
            <div class="alert alert-danger" role="alert">
              <p>$vinList</p>
            </div>
          </div>
        </div>
      TEXT;

            $this->flash->warning($htmlExistsVin);
        }

        $not_fount_list = implode(", ", $vinNotFoundInUP);
        $vinNotFoundInUPCount = count($vinNotFoundInUP);

        if ($vinNotFoundInUPCount > 0) {
            $htmlNotFoundVin = <<<TEXT
        Невозможно сохранить $vinNotFoundInUPCount машин, по указанным VIN номерам или идентификаторам отсутствует оплата утилизационного платежа
        <small id="car_date" class="form-text text-muted" data-toggle="collapse" data-target="#NotFoundVinWhenFundINSCarUpload" aria-expanded="false"
        aria-controls="NotFoundVinWhenFundINSCarUpload">
        Посмотреть список VIN кодов
          <icon data-feather="help-circle" type="button" color="green" width="18" height="18"></icon>
        </small>
        <div class="collapse" id="NotFoundVinWhenFundINSCarUpload">
          <div class="card card-body">
            <div class="alert alert-danger" role="alert">
              <p>$not_fount_list</p>
            </div>
          </div>
        </div>
      TEXT;

            $this->flash->warning($htmlNotFoundVin);
        }

        return $this->response->redirect("/fund/view/$profile->id");
    }

    public function uploadFromExcelEXPAction()
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, FUND_BLACK_LIST) || in_array("BLOCK_ALL", FUND_BLACK_LIST)) {
            return $this->response->redirect("/fund");
        }

        $order = $this->request->getPost("order_id");
        $profile = FundProfile::findFirstById($order);

        if ($this->request->isPost()) {
            if ($auth->id != $profile->user_id || $profile->blocked || $profile->type != "EXP") {
                $this->logAction("Вы не имеете права совершать импорт.");
                $this->flash->error("Вы не имеете права совершать импорт.");
                return $this->response->redirect("/fund/index/");
            }

            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo(APP_PATH . "/storage/temp/fund_" . $order . ".csv");
                }
            }

            $tmpl_cat = 0;
            $tmpl_model = 0;
            $ex_car = FundCar::findFirstByFundId($profile->id);
            if ($ex_car) {
                $tmpl_cat = $ex_car->ref_car_cat;
                $tmpl_model = $ex_car->model_id;
            }

            $import = file(APP_PATH . "/storage/temp/fund_" . $order . ".csv");
            foreach ($import as $key => $value) {
                $can_add = true;

                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);
                    // кириллица в VIN
                    $val[2] = mb_strtoupper($val[2]);
                    $val[2] = preg_replace('/(\W^-)/', '', $val[2]);

                    if ($tmpl_cat != 0 && $tmpl_model != 0) {
                        if ($tmpl_cat != $val[4] || $tmpl_model != $val[5]) {
                            $can_add = false;
                            $this->flash->warning("Автомобиль с VIN-кодом " . $val[2] . " не может быть импортирован в заявку #" . __getFundNumber($profile->id) . ", т.к. его модель и категория отличаются.");
                        }
                    } else {
                        if ($key == 1) {
                            $tmpl_model = (int)$val[5];
                            $tmpl_cat = (int)$val[4];
                        }
                    }

                    $c = new FundCar();
                    $c->ref_car_type_id = $val[0];
                    $c->volume = $val[1];
                    $c->vin = $val[2];
                    $c->date_produce = strtotime($val[3]);
                    $c->fund_id = $profile->id;
                    $c->ref_car_cat = $val[4];
                    $c->model_id = $val[5];
                    $c->ref_st_type = $val[6];
                    $car_vin = $val[2];
                    $car_cat = $val[4];
                    $car_volume = $val[1];
                    $ref_st = $val[6];
                    $calculate_method = $val[7];
                    $car_date = $val[3];

                    // проверки при добавлении
                    $_car_check_cons = true;

                    // проверка ref_fund_key
                    if ($profile->ref_fund_key != NULL) {
                        // если электрокар
                        if ($profile->ref_fund_key == 'M1_0' || $profile->ref_fund_key == 'M2M3_0' || $profile->ref_fund_key == 'M1_0_EXP') {
                            $exploted = explode("_", $profile->ref_fund_key);
                            $cat_name = [];

                            if ($exploted[0] == "M1") {
                                $cat_name = array(1, 2);
                            } else if ($exploted[0] == "M2M3") {
                                $cat_name = array(9, 10, 11, 12);
                            }

                            if ($car_volume == $exploted[1] && in_array($car_cat, $cat_name)) {

                            } else {
                                $can_add = false;
                                $message = "Ошибка: Разрешенная категория ТС: $exploted[0], С электродвигателем";
                                $this->flash->error($message);
                                $this->logAction($message);
                                return $this->response->redirect("/fund/view/$profile->id");
                            }
                        } else {
                            $exploted = explode("_", $profile->ref_fund_key);
                            $cat_name = [];
                            if ($exploted[0] == "TRACTOR") {
                                $cat_name = array(13);
                            } else if ($exploted[0] == "COMBAIN") {
                                $cat_name = array(14);
                            } else if ($exploted[0] == "M1") {
                                $cat_name = array(1, 2);
                            } else if ($exploted[0] == "M2M3") {
                                $cat_name = array(9, 10, 11, 12);
                            } else if ($exploted[0] == "N") {
                                $cat_name = array(3, 4, 5, 6, 7, 8);
                                if ($exploted[3] != NULL) {
                                    if ($exploted[3] == 'ST') {
                                        if ($ref_st != 1) {
                                            $can_add = false;
                                            $message = "Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Седельный тягач)";
                                            $this->logAction($message);
                                            $this->flash->error($message);
                                            return $this->response->redirect("/fund/view/$profile->id");
                                        }
                                    } else {
                                        if ($ref_st != 0) {
                                            $can_add = false;
                                            $message = "Ошибка: Разрешенная категория ТС $exploted[0], Обьем  должен быть: От $exploted[1] до $exploted[2] (Не седельный тягач)";
                                            $this->logAction($message);
                                            $this->flash->error($message);
                                            return $this->response->redirect("/fund/view/$profile->id");
                                        }
                                    }
                                }
                            }

                            if ($car_volume <= $exploted[2] && $car_volume >= $exploted[1] && in_array($car_cat, $cat_name)) {

                            } else {
                                $can_add = false;
                                $message = "Ошибка: Разрешенная категория ТС $exploted[0], Обьем должен быть: От $exploted[1] до $exploted[2]";
                                $this->flash->error($message);
                                $this->logAction($message);
                                return $this->response->redirect("/fund/view/$profile->id");
                            }
                        }
                    }

                    // проверяем старые заявки
                    $car_check = FundCar::findFirstByVin($car_vin);

                    if ($car_check) {
                        $_car_check_cons = false;
                        $message = "VIN $car_vin уже был представлен в заявке №" . __getFundNumber($car_check->fund_id) . ".";
                        $this->logAction($message);
                        $this->flash->error($message);
                    }

                    if ($profile->type == 'EXP') {
                        // проверяем утильплатежи для экспортных машин
                        if ($pr = __checkPayment($car_vin)) {
                            $_car_check_cons = false;
                            $message = "VIN $car_vin обнаружен в базе утилизационных платежей в заявке №" . __getProfileNumber($pr) . ".";
                            $this->flash->error($message);
                            $this->logAction($message);

                        }
                        // проверяем старый экспорт, до октября 2020
                        if ($pr = __checkExport($car_vin)) {
                            $_car_check_cons = false;
                            $message = "VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.";
                            $this->flash->error($message);
                            $this->logAction($message);
                        }
                    } else {
                        // проверка ДПП
                        if ($pr = __checkDPP($car_vin, $car_volume)) {
                            $_car_check_cons = false;
                            $message = "По VIN $car_vin отсутствует оплата утилизационного платежа или не соответствуют характеристики";
                            $this->flash->error($message);
                            $this->logAction($message);
                        }

                        // проверяем старый экспорт, до октября 2020
                        if ($pr = __checkInner($car_vin)) {
                            $_car_check_cons = false;
                            $message = "VIN $car_vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.";
                            $this->flash->error($message);
                            $this->logAction($message);
                        }
                    }

                    $_lim_can_add = true;
                    $__lim = __checkLimits($profile->id, $car_cat, $car_volume, $ref_st, strtotime($car_date . " 00:00:00"), date('Y', $profile->created));
                    if ($__lim <= 0) {
                        $_lim_can_add = false;
                        $message = "Превышение лимитов по ТС $car_vin, объект не был добавлен.";
                        $this->logAction($message);
                        $this->flash->error($message);
                    }

                    if (strtotime($val[3]) >= strtotime(STARTROP)) {
                        if (strlen($val[2]) == 17 || $val[4] >= 13) {
                            if ($c) {
                                //для финансирования по электромобилям ставки не такие как в УП, поэтому отдельная проверка и ставка в таблице ref_car_value
                                if ($val[1] == 0) {
                                    $value = RefCarValue::findFirst(array(
                                        "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                                        "bind" => array(
                                            "car_type" => $val[0],
                                            "volume_end" => $val[1],
                                            "volume_start" => $val[1],
                                        )
                                    ));
                                } else {
                                    $value = RefCarValue::findFirst(array(
                                        "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                        "bind" => array(
                                            "car_type" => $val[0],
                                            "volume_end" => $val[1],
                                            "volume_start" => $val[1]
                                        )
                                    ));
                                }

                                if ($value != false) {

                                    $up_date = __getUpDatesByCarVin($car_vin);

                                    if ($calculate_method == 1 && $up_date['MD_DT_SENT'] > 0) {
                                        $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                                    } elseif ($calculate_method == 2) {
                                        if ($up_date['CALCULATE_METHOD'] == 0) {
                                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['DATE_IMPORT']), $car_volume, json_encode($value), $ref_st);
                                        }
                                        if ($up_date['CALCULATE_METHOD'] == 1) {
                                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['MD_DT_SENT']), $car_volume, json_encode($value), $ref_st);
                                        }
                                        if ($up_date['CALCULATE_METHOD'] == 2) {
                                            $sum = __calculateCarByDate(date('d.m.Y', $up_date['FIRST_REG_DATE']), $car_volume, json_encode($value), $ref_st);
                                        }
                                    } else {
                                        $sum = __calculateCarByDate($car_date, $car_volume, json_encode($value), $ref_st);
                                    }

                                    $c->calculate_method = $calculate_method;
                                    $c->cost = $sum;
                                    if ($can_add == true && $_car_check_cons == true && $_lim_can_add == true) {
                                        if ($c->save()) {
                                            __fundRecalc($profile->id);
                                        }
                                    }
                                }
                            }
                        } else {
                            $this->flash->success("Автомобиль с VIN-кодом " . $val[2] . " не может быть импортирован в заявку #" . __getFundNumber($profile->id) . ", т.к. его VIN не соответствует формату или содержит кириллические символы.");
                        }
                    } else {
                        $this->flash->success("Автомобиль с VIN-кодом " . $val[2] . " не может быть импортирован в заявку #" . __getFundNumber($profile->id) . ", т.к. производство выполненно до вступления в силу расширенных обязательств.");
                    }
                }
            }

            __fundRecalc($profile->id);
        }

        return $this->response->redirect("/fund/view/$profile->id");
    }

    public function clearAction($pid)
    {
        $auth = User::getUserBySession();

        $fund = FundProfile::findFirstById($pid);
        $fundService = new FundService();

        if ($fund->user_id != $auth->id) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            $this->flash->error("Нет доступа");

            return $this->response->redirect("/fund/index/");
        }

        if ($fund->entity_type == 'CAR') {
            $fund_car = FundCar::findByFundId($pid);
            foreach ($fund_car as $item) {
                $item->delete();
            }
        } else if ($fund->entity_type == 'GOODS') {
            $fund_goods = FundGoods::findByFundId($pid);
            foreach ($fund_goods as $item) {
                $item->delete();
            }
        }

        $this->logAction("Очистка заявки произведена успешно.");
        $this->flash->success("Очистка заявки произведена успешно.");
        $fundService->calculationFundAmount($fund);

        return $this->response->redirect("/fund/view/$pid");
    }

    public function cancelled_listAction(int $id = 0)
    {
        $this->view->disable();
        $data = array();

        $a = $this->session->get('auth');
        $f = FundProfile::findFirstById($id);

        if ($f->user_id == $a['id']) {
            $sql = <<<SQL
           SELECT
             c.car_id AS c_id,
             c.volume AS c_volume,
             c.vin AS c_vin,
             c.cost AS c_cost,
             cc.name AS c_cat,
             FROM_UNIXTIME(c.date_produce, '%d.%m.%Y') AS c_date_produce,
             c.status AS c_status
           FROM FundCarHistories c
             JOIN RefCarCat cc
             JOIN RefCarType t
           WHERE
             c.fund_id = :pid: AND
             cc.id = c.ref_car_cat
           GROUP BY c.id
           ORDER BY c.id DESC
         SQL;

            $deleted_cars = $this->modelsManager->createQuery($sql);

            $cancelled_cars = $deleted_cars->execute(array(
                "pid" => $id
            ));

            if (count($cancelled_cars) > 0) {
                foreach ($cancelled_cars as $c) {
                    $data[] = [
                        "c_id" => $c->c_id,
                        "c_volume" => $c->c_volume,
                        "c_vin" => $c->c_vin,
                        "c_cost" => $c->c_cost,
                        "c_cat" => $this->translator->_($c->c_cat),
                        "c_date_produce" => $c->c_date_produce,
                        "c_status" => $this->translator->_($c->c_status),
                    ];
                }
            }

            if (is_array($data) && count($data) > 0) {
                $json_data = array(
                    "draw" => 1,
                    "recordsTotal" => intval(count($data)),
                    "recordsFiltered" => intval(count($data)),
                    "data" => $data,
                );
                http_response_code(200);
                return json_encode($json_data);
            } else {
                $json_data = array(
                    "draw" => 1,
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                    "data" => [],
                );
                http_response_code(200);
                return json_encode($json_data);
            }
        } else {
            $message = "У вас нет прав на это действие!";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/fund/index/");
        }
    }

    function uploadCarListAction(): ResponseInterface
    {
        $this->checkFundAvailability();

        if ($this->request->isPost()) {
            $fund_id = $this->request->getPost('fund_id');
            $car_id_list = array_map('intval', $this->request->getPost('id'));
            $successfully_added = 0;
            $f = FundProfile::findFirstById($fund_id);
            $fundService = new FundService();

            if (count($car_id_list) > 0) {
                foreach ($car_id_list as $c_id) {
                    $car = Car::findFirstById($c_id);

                    // проверяем старые заявки
                    $car_check = FundCar::findFirstByVin($car->vin);

                    if ($car_check) {
                        $message = "VIN: $car->vin уже был представлен в заявке № " . __getFundNumber($car_check->fund_id);
                        $this->logAction($message);
                        $this->flash->warning($message);
                        continue;
                    }

                    // проверяем старый экспорт, до октября 2020
                    if (__checkInner($car->vin)) {
                        $message = "VIN $car->vin обнаружен в базе заявок на финансирования до 1 октября 2020 года.";
                        $this->logAction($message);
                        $this->flash->warning($message);
                        continue;
                    }

                    $__lim = __checkLimits($f->id, $car->ref_car_cat, $car->volume, $car->ref_st_type, $car->date_import, date('Y', $f->created));

                    if ($__lim <= 0) {
                        $message = "Превышение лимитов по ТС $car->vin, объект не был добавлен.";
                        $this->flash->warning($message);
                    } else {
                        $fund_car = new FundCar();
                        $fund_car->volume = $car->volume;
                        $fund_car->vin = $car->vin;
                        $fund_car->date_produce = $car->date_import;
                        $fund_car->fund_id = $fund_id;
                        $fund_car->ref_car_cat = $car->ref_car_cat;
                        $fund_car->ref_car_type_id = $car->ref_car_type_id;
                        $fund_car->ref_st_type = $car->ref_st_type;
                        $fund_car->calculate_method = $car->calculate_method;
                        $fund_car->profile_id = $car->profile_id;
                        $fund_car->model_id = 0;

                        if ($car->volume == 0 || $car->electric_car == 1) {

                            if ($car->volume == 0) {
                                $value = RefCarValue::findFirst(array(
                                    "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                                    "bind" => array(
                                        "car_type" => $car->ref_car_type_id,
                                        "volume_start" => $car->volume,
                                        "volume_end" => $car->volume,
                                    )
                                ));
                            } else {
                                $value = RefCarValue::findFirst(array(
                                    "car_type = :car_type: AND (volume_end = :volume_end: AND volume_start = :volume_start:)",
                                    "bind" => array(
                                        "car_type" => $car->ref_car_type_id,
                                        "volume_start" => $car->volume,
                                        "volume_end" => $car->volume,
                                    )
                                ));
                            }
                            $sum = __calculateCarByDate(date('d.m.Y', $car->date_import), $car->volume, json_encode($value), $car->ref_st_type);
                        } else {
                            $sum = $car->cost;
                        }

                        $fund_car->cost = $sum;

                        if ($fund_car->save()) $successfully_added++;
                    }
                }

                $fundService->calculationFundAmount($f);
            } else {
                $message = "Не выбрано ТС.";
                $this->logAction($message);
                $this->flash->success($message);
            }

            if ($successfully_added > 0) {
                $this->logAction("Успешно добавлено $successfully_added ТС!");
                $message = "Успешно добавлено $successfully_added ТС!";
                $this->flash->success($message);
            }
        }

        return $this->response->redirect("/fund/view/$f->id");
    }

    function uploadGoodsListAction()
    {
        $auth = User::getUserBySession();

        $this->checkFundAvailability();

        if (!$this->request->isPost()) {
            return $this->response->redirect('/fund')->send();
        }

        $fundService = new FundService();

        $fund_id = (int)$this->request->getPost('fund_id');
        $goods_id_list = array_map('intval', (array)$this->request->getPost('id'));
        $successfully_added = 0;

        $f = FundProfile::findFirstById($fund_id);
        if (!$f) {
            $this->flash->warning('Заявка не найдена.');
            return $this->response->redirect('/fund')->send();
        }

        $year = date('Y', $f->created);

        if (empty($goods_id_list)) {
            $message ='Не выбран товар.';
            $this->flash->warning($message);
            $this->logAction($message);
            return $this->response->redirect("/fund/view/$f->id")->send();
        }

        // Загружаем все товары одним запросом
        $goods = Goods::find([
            'conditions' => 'id IN ({ids:array})',
            'bind' => ['ids' => $goods_id_list],
        ]);

        // Индексируем товары по id
        $goodsById = [];
        foreach ($goods as $g) {
            $goodsById[$g->id] = $g;
        }

        // Проверяем, какие товары уже были в заявках (один запрос вместо N)
        $existingFundGoods = FundGoods::find([
            'conditions' => 'goods_id IN ({ids:array})',
            'bind' => ['ids' => $goods_id_list],
        ]);

        $existingByGoodsId = [];
        foreach ($existingFundGoods as $fg) {
            $existingByGoodsId[$fg->goods_id] = $fg;
        }

        // Кэшируем базовый вес по ref_fund (что уже профинансировано раньше)
        $baseWeightByRefFund = []; // [ref_fund_id => float]
        // Накопленный вес текущей партии по ref_fund (то, что добавляем сейчас)
        $batchWeightByRefFund = []; // [ref_fund_id => float]

        foreach ($goods_id_list as $g_id) {
            // Товар мог не загрузиться в выборку (ошибка данных) – пропускаем
            if (!isset($goodsById[$g_id])) {
                continue;
            }

            $goodsItem = $goodsById[$g_id];

            // Проверка: товар уже в какой-то заявке
            if (isset($existingByGoodsId[$g_id])) {
                $goods_check = $existingByGoodsId[$g_id];
                $message = "Товар с № $g_id уже был представлен в заявке № " . __getFundNumber($goods_check->fund_id);
                $this->logAction($message);
                $this->flash->warning($message);
                continue;
            }

            // Ищем RefFund для КОНКРЕТНОГО товара по дате импорта
            $refFund = RefFund::findFirst([
                'conditions' => 'key = :key:
                             AND idnum = :idnum:
                             AND year = :year:
                             AND prod_start <= :prod_start:
                             AND prod_end >= :prod_end:',
                'bind' => [
                    'key' => $f->ref_fund_key,
                    'idnum' => $auth->idnum,
                    'year' => $year,
                    'prod_start' => $goodsItem->date_import,
                    'prod_end' => $goodsItem->date_import,
                ],
            ]);

            if (!$refFund) {
                $message = "Превышение лимитов, товар не был добавлен.";
                $this->flash->warning($message);
                $this->logAction($message);
                continue;
            }

            $refFundId = $refFund->id;
            $limitWeight = (float)$refFund->value;

            // Базовый вес по этому ref_fund (уже профинансированные товары) – считаем один раз
            if (!array_key_exists($refFundId, $baseWeightByRefFund)) {
                $baseWeightByRefFund[$refFundId] = $fundService->getGoodsTotalWeight($f, $refFund);
                $batchWeightByRefFund[$refFundId] = 0.0;
            }

            // Добавляем вес текущего товара в партию по этому ref_fund
            $batchWeightByRefFund[$refFundId] += (float)$goodsItem->weight;

            // Общий вес по этому ref_fund: база + текущая партия (все уже принятые в этом запросе + текущий)
            $totalWeight = $baseWeightByRefFund[$refFundId] + $batchWeightByRefFund[$refFundId];

            if ($totalWeight > $limitWeight) {
                $message = "Превышение лимитов, товар не был добавлен.";
                $this->flash->warning($message);
                $this->logAction($message);
                // откатываем добавление текущего веса из партии, чтобы следующий товар не учитывал его
                $batchWeightByRefFund[$refFundId] -= (float)$goodsItem->weight;
                continue;
            }

            // Добавляем товар в FundGoods
            $fund_goods = new FundGoods();
            $fund_goods->fund_id = $f->id;
            $fund_goods->ref_tn = $goodsItem->ref_tn;
            $fund_goods->date_produce = $goodsItem->date_import;
            $fund_goods->cost = $goodsItem->goods_cost;
            $fund_goods->basis = $goodsItem->basis;
            $fund_goods->basis_date = $goodsItem->basis_date;
            $fund_goods->calculate_method = $goodsItem->calculate_method;
            $fund_goods->profile_id = $goodsItem->profile_id;
            $fund_goods->weight = $goodsItem->weight;
            $fund_goods->goods_id = $goodsItem->id;

            if ($fund_goods->save()) {
                $successfully_added++;
            }
        }

        // Пересчёт суммы фонда, если что-то добавили
        if ($successfully_added > 0) {
            $fundService->calculationFundAmount($f);
            $message = "Успешно добавлено $successfully_added товар!";
            $this->logAction($message);
            $this->flash->success($message);
        }

        return $this->response->redirect("/fund/view/$f->id")->send();

    }


    function calculateSumAction()
    {
        $auth = User::getUserBySession();
        $goods_a = [];
        if ($this->request->isPost()) {
            $fund_id = $this->request->getPost('fund_id');
            $car_id_list = $this->request->getPost('car_id_list');
            $goods_id_list = $this->request->getPost('goods_id_list');
            $messages = [];
            $sum = 0;
            $limit = 0;
            $fund = FundProfile::findFirst($fund_id);
            $fundService = new FundService();
            $year = date('Y', $fund->created);
            if ($fund->entity_type == 'CAR') {
                $refFund = RefFund::findFirst([
                    'conditions' => 'key = :key: AND idnum = :idnum: AND year = :year:',
                    'bind' => [
                        'key' => $fund->ref_fund_key,
                        'idnum' => $auth->idnum,
                        'year' => $year
                    ],
                    'order' => 'id DESC'
                ]);

                if ($refFund) {
                    $limit = $refFund->value;
                } else {
                    $messages[] = "Превышен лимит";
                }
                $carService = new CarService();
                $export_car_vin_list = $carService->isOldExportCar($car_id_list);
                if (count($export_car_vin_list) > 0) {
                    $exist_vin_list = implode(", ", $export_car_vin_list);
                    $messages[] = "VIN $exist_vin_list обнаружен в базе заявок на финансирования до 1 октября 2020 года.";
                }

                $car = Car::find([
                    'conditions' => 'id IN ({ids:array})',
                    'bind' => ['ids' => $car_id_list]
                ]);

                $totalCount = $fundService->getCarTotalCount($fund, $car, $refFund);
                if ($totalCount > $limit) {
                    $messages[] = "Превышен лимит";
                }

                $sum = $carService->calculationCarCost($car_id_list);
            } else if ($fund->entity_type == 'GOODS') {
                $goods = Goods::find([
                    'conditions' => 'id IN ({ids:array})',
                    'bind' => ['ids' => $goods_id_list]
                ]);

                if (count($goods) === 0) {
                    http_response_code(400);
                    return json_encode([
                        "sum" => __money(0),
                        "messages" => ["Товары не найдены"],
                        "data" => [],
                        "status" => 'failed'
                    ]);
                }

                $goodsService = new GoodsService();
                $sum = $goodsService->getTotalCostByIds($goods_id_list);

                // Кэш по ref_fund: базовый вес (что уже в БД)
                $baseWeightByRefFund = [];    // [ref_fund_id => float]
                // Накопленный вес текущей партии по ref_fund
                $batchWeightByRefFund = [];   // [ref_fund_id => float]

                foreach ($goods as $g) {

                    $refFund = RefFund::findFirst([
                        'conditions' => 'key = :key:
                             AND idnum = :idnum:
                             AND year = :year:
                             AND prod_start <= :prod_start:
                             AND prod_end >= :prod_end:',
                        'bind' => [
                            'key' => $fund->ref_fund_key,
                            'idnum' => $auth->idnum,
                            'year' => $year,
                            'prod_start' => $g->date_import,
                            'prod_end' => $g->date_import,
                        ],
                    ]);

                    if (!$refFund) {
                        $messages[] = "Превышен лимит";
                        $goods_a[] = [
                            'ref_fund' => null,
                            'goods' => [
                                'id' => $g->id,
                                'date_import' => $g->date_import,
                                'weight' => $g->weight,
                            ],
                            'total_weight' => 0,
                        ];
                        continue;
                    }

                    $refFundId = $refFund->id;
                    $limit = (float)$refFund->value;

                    if (!array_key_exists($refFundId, $baseWeightByRefFund)) {
                        $baseWeightByRefFund[$refFundId] = $fundService->getGoodsTotalWeight($fund, $refFund);
                        $batchWeightByRefFund[$refFundId] = 0.0; // инициализируем накопитель партии
                    }

                    // 2) Накопливаем вес текущей партии по этому ref_fund
                    $batchWeightByRefFund[$refFundId] += (float)$g->weight;

                    // 3) Общий вес "в реальном времени" для этого ref_fund:
                    //    база + всё, что уже "положили" из текущей партии для этого ref_fund
                    $totalWeight = $baseWeightByRefFund[$refFundId] + $batchWeightByRefFund[$refFundId];

                    // Записываем данные по товару
                    $goods_a[] = [
                        'ref_fund' => $refFund->toArray(),
                        'goods' => [
                            'id' => $g->id,
                            'date_import' => $g->date_import,
                            'weight' => $g->weight,
                        ],
                        'base_weight' => $baseWeightByRefFund[$refFundId],     // уже профинансировано
                        'batch_weight' => $batchWeightByRefFund[$refFundId],    // суммарно по текущей партии (по этому ref_fund)
                        'total_weight' => $totalWeight,                         // база + партия
                    ];

                    if ($totalWeight > $limit) {
                        $messages[] = "Превышен лимит";
                    }
                }
            }

            if (count($messages) > 0) {
                http_response_code(200);
                $data = array(
                    "sum" => __money($sum),
                    "messages" => $messages,
                    "data" => $goods_a,
                    "status" => 'failed',
                );
            } else {
                http_response_code(200);
                $data = array(
                    "sum" => __money($sum),
                    "status" => 'success',
                    "data" => $goods_a,
                );
            }

            return json_encode($data);
        }
    }

    public function deleteAction($fund_id)
    {
        $fundProfile = FundProfile::findFirst($fund_id);

        if ($fundProfile->entity_type == 'CAR') {
            $fundItems = FundCar::findFirstByFundId($fundProfile->id);
        } else {
            $fundItems = FundGoods::findFirstByFundId($fundProfile->id);
        }

        $user = User::getUserBySession();

        if (!$fundItems && $fundProfile->approve == 'FUND_NEUTRAL' && $user->id === $fundProfile->user_id) {
            $fundProfile->delete();
            $this->logAction("Успешно удалено");
            $this->flash->success("Успешно удалено");
        } else {
            $this->logAction("Удалите сперва записи в заявке");
            $this->flash->warning("Удалите сперва записи в заявке");
        }


        return $this->response->redirect("/fund/index");
    }

    public function getCarsListAction($fund_id)
    {
        return $this->dataTableList((int)$fund_id, 'cars');
    }

    public function getGoodsListAction($fund_id)
    {
        return $this->dataTableList((int)$fund_id, 'goods');
    }

    // =========================================================================
    // 2. ПРИВАТНЫЕ ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ (Унификация)
    // =========================================================================

    /**
     * Обрабатывает общие шаги: подготовка ответа,
     * чтение параметров DataTables, и вызывает getListData.
     * * @param int $fund_id
     * @param string $type 'cars' или 'goods'
     * @return \Phalcon\Http\ResponseInterface
     */
    private function dataTableList(int $fund_id, string $type)
    {
        $this->view->disable();
        $this->response->setContentType('application/json', 'utf-8');

        $fundProfile = FundProfile::findFirst($fund_id);
        $auth = User::getUserBySession();
        $user_id = $auth->id;

        $request = $this->request;
        $draw = (int)$request->getQuery('draw', 'int', 1);
        $start = (int)$request->getQuery('start', 'int', 0);
        $length = (int)$request->getQuery('length', 'int', 10);
        $search_value = $request->getQuery('search')['value'] ?? '';
        $order_data = $request->getQuery('order')[0] ?? ['column' => 1, 'dir' => 'asc'];

        $result = $this->getListData(
            $fundProfile,
            $user_id,
            $draw,
            $start,
            $length,
            $search_value,
            $order_data,
            $type
        );

        return $this->response->setJsonContent($result);
    }

    private function getListData(
        $fundProfile,
        int $user_id,
        int $draw,
        int $start,
        int $length,
        string $search_value,
        array $order_data,
        string $type
    ): array
    {
        $fundService = new FundService();
        $dataConfig = $this->getDataConfig($type);

        if (empty($dataConfig)) {
            return ['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];
        }

        $serviceMethod = $dataConfig['service_method'];
        $items = $fundService->{$serviceMethod}($fundProfile, $user_id);
        $totalCount = count($items);
        $filtered_items = $items;

        if (!empty($search_value)) {
            $search_lower = mb_strtolower($search_value, 'UTF-8');

            $filtered_items = array_filter($items, function ($item) use ($search_lower, $dataConfig) {
                foreach ($dataConfig['searchable_columns'] as $col) {
                    $item_value = mb_strtolower((string)($item[$col] ?? ''), 'UTF-8');
                    if (strpos($item_value, $search_lower) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        $recordsFiltered = count($filtered_items);
        $column_map = $dataConfig['column_map'];

        $order_column_index = (int)($order_data['column'] ?? 1);
        $order_direction = $order_data['dir'] ?? 'asc';

        if (isset($column_map[$order_column_index]) && $order_column_index !== 0) {
            $sort_by = $column_map[$order_column_index];
            $is_desc = $order_direction === 'desc';

            usort($filtered_items, function ($a, $b) use ($sort_by, $is_desc) {
                $val_a = $a[$sort_by] ?? null;
                $val_b = $b[$sort_by] ?? null;

                if (is_numeric($val_a) && is_numeric($val_b)) {
                    $comparison = $val_a <=> $val_b;
                } else {
                    $comparison = strcasecmp((string)$val_a, (string)$val_b);
                }

                return $is_desc ? -$comparison : $comparison;
            });
        }

        $pageSlice = array_slice($filtered_items, $start, $length);
        $data = [];
        $formatter = $dataConfig['formatter'];

        foreach ($pageSlice as $item) {
            $data[] = $formatter($item);
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * Определяет специфическую конфигурацию для каждого типа сущности ('cars', 'goods').
     * @param string $type
     * @return array
     */
    private function getDataConfig(string $type): array
    {
        if ($type === 'cars') {
            return [
                'service_method' => 'getCarsByFundProfile',
                'searchable_columns' => ['volume', 'vin', 'date_import', 'ref_car_cat', 'amount', 'date_approve'],
                'column_map' => [
                    0 => 'id', 1 => 'volume', 2 => 'vin', 3 => 'date_import',
                    4 => 'ref_car_cat', 5 => 'amount', 6 => 'date_approve',
                ],
                'formatter' => function ($item) {
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'volume' => $item['volume'] ?? '',
                        'vin' => $item['vin'] ?? '',
                        'date_import' => $item['date_import'] ?? '',
                        'ref_car_cat' => $item['ref_car_cat'] ?? '',
                        'amount' => $item['amount'] ?? 0,
                        'date_approve' => $item['date_approve'] ?? '',
                    ];
                }
            ];
        }

        if ($type === 'goods') {
            return [
                'service_method' => 'getGoodsByFundProfile',
                'searchable_columns' => ['weight', 'amount', 'ref_tn_code', 'date_import', 'basis_date', 'date_approve', 'profile_id'],
                'column_map' => [
                    0 => 'id', 1 => 'weight', 2 => 'amount', 3 => 'ref_tn_code',
                    4 => 'date_import', 5 => 'basis_date', 6 => 'date_approve', 7 => 'profile_id',
                ],
                'formatter' => function ($item) {
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'weight' => $item['weight'] ?? '',
                        'amount' => $item['amount'] ?? '',
                        'ref_tn_code' => $item['ref_tn_code'] ?? '',
                        'date_import' => $item['date_import'] ?? '',
                        'basis_date' => $item['basis_date'] ?? 0,
                        'date_approve' => $item['date_approve'] ?? '',
                        'profile_id' => $item['profile_id'] ?? '',
                    ];
                }
            ];
        }

        return [];
    }

    private function checkFundAvailability()
    {
        $fund_start_date = getenv('FUND_START_DATE'); // Y-m-d
        if (strtotime(date('Y-m-d')) < strtotime($fund_start_date)) {
            $this->flash->error("Финансирование отключено");
            return $this->response->redirect("/fund/");
        }
    }

}
