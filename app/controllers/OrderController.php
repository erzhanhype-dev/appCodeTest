<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Resources\CarRowResource;
use App\Resources\GoodsRowResource;
use App\Resources\OrderRowResource;
use App\Services\Car\CarService;
use App\Services\Cms\CmsService;
use App\Services\Goods\GoodsService;
use App\Services\Order\Dto\OrderCreateDTO;
use App\Services\Order\Dto\OrderFilterDTO;
use App\Services\Order\OrderService;
use App\Services\Pdf\PdfService;
use Car;
use CompanyDetail;
use ContactDetail;
use ControllerBase;
use File;
use FileLogs;
use Goods;
use PersonDetail;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\View;
use Profile;
use ProfileLogs;
use RefBank;
use RefCarValue;
use RefTnCode;
use RefVinMask;
use Sf;
use Transaction;
use User;

class OrderController extends ControllerBase
{
    private const int MAX_FILE_SIZE = 50 * 1024 * 1024; // 15 MB
    private OrderService $orderService;
    private CarService $carService;
    private GoodsService $goodsService;

    public function onConstruct(): void
    {
        $this->orderService = $this->di->getShared('orderService');
        $this->carService = $this->di->getShared('carService');
        $this->goodsService = $this->di->getShared('goodsService');
    }

    public function indexAction()
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            return $this->response->redirect("/home");
        }

        $this->logAction("Просмотр списка заявок", 'access');

        $filters = OrderFilterDTO::createFromRequest($this->request->getQuery());

        $result = $this->orderService->getFilteredOrders($filters, $auth->id);
        $availableFilters = $this->orderService->getAvailableFilters();

        $this->view->setVars([
            'orders' => OrderRowResource::collection($result['items']),
            'page' => $result['pagination'],
            'allYears' => $availableFilters['years'],
            'allTypes' => $availableFilters['types'],
            'allStatuses' => $availableFilters['statuses'],
            'q_pid' => $filters->profileId,
            'q_types' => $filters->types,
            'q_states' => $filters->statuses,
            'q_from' => $filters->fromDate,
            'q_to' => $filters->toDate,
            'hasFilters' => $filters->hasFilters(),
        ]);

    }

    public function newAction()
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            return $this->response->redirect("/home");
        }

        if ($this->orderService->isOrderBlockedUser($auth->idnum)) {
            $this->logAction("Заблокированный пользователь.", 'security', 'ALERT');
            $this->flash->warning($this->translator->_('validation.profile_create_blocked_user'));
            return $this->response->redirect("/order/index");
        }

        if ($auth->isAgent()) {
            $this->logAction("Возможность создания заявок для агентов временно приостановлена.");
            $this->flash->warning($this->translator->_('validation.profile_create_blocked_for_agent'));
            return $this->response->redirect("/order/index");
        }

        return $this->view->pick('order/new');
    }

    /**
     * @throws AppException
     */
    public function addAction(): ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            return $this->response->redirect("/home");
        }

        $dto = OrderCreateDTO::fromRequest($this->request);

        if ($this->orderService->isOrderBlockedUser($auth->idnum)) {
            $message = $this->translator->_('validation.profile_create_blocked_user');
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
        }

        if ($auth->isAgent()) {
            $message = $this->translator->_('validation.profile_create_blocked_for_agent');
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
        }

        if (strlen($dto->comment) < 3) {
            $message = 'Комментарий заявки должно содержать минимум 3 символа.';
            $this->flash->error($message);
            $this->logAction($message);
        }

        try {
            $profile = $this->orderService->create($dto, $auth);
            $this->logAction('Создание заявки');
            $this->flash->success('Заявка создана');
            return $this->response->redirect("/order/view/{$profile->id}");
        } catch (AppException $e) {
            $message = $e->getMessage();
            $this->logAction($message);
            $this->flash->warning($message);
            return $this->response->redirect('/order/index');
        } catch (\Throwable $e) {
            $this->logAction('Ошибка создания заявки: ' . $e->getMessage());
            $this->flash->error('Не удалось создать заявку.');
            return $this->response->redirect('/order/new');
        }
    }

    /**
     * @throws AppException
     */
    public function editAction($profileId): View|ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            $this->logAction("Нет доступа", 'security');
            return $this->response->redirect("/home");
        }

        $dto = OrderCreateDTO::fromRequest($this->request);
        if ($this->request->isPost()) {
            if ($this->orderService->isOrderBlockedUser($auth->idnum)) {
                $message = $this->translator->_('validation.profile_create_blocked_user');
                $this->logAction($message, 'security');
                $this->flass->error($message);
            }

            $profile = Profile::findFirstById($profileId);

            if (!$profile) {
                $this->flass->error($this->translator->_('validation.profile_not_found'));
            }

            if ($profile->user_id !== (int)$auth->id) {
                $this->flass->error($this->translator->_('validation.no_rights'));
                $this->logAction('Доступ запрещен', 'security');
            }

            if (empty($profile->cars)) {
                $this->flass->error($this->translator->_('validation.no_rights'));
            }

            try {
                $this->orderService->edit($profile, $dto, $auth);
                $this->logAction('Редактирование заявки');
                $this->flash->success('Заявка отредактирована');
                return $this->response->redirect("/order");
            } catch (AppException $e) {
                $message = $e->getMessage();
                $this->logAction($message);
                $this->flash->warning($message);
                return $this->response->redirect('/order');
            } catch (\Throwable $e) {
                $this->logAction('Ошибка создания заявки: ' . $e->getMessage());
                $this->flash->error('Не удалось отредактировать заявку.' . $e->getMessage());
                return $this->response->redirect("/order/edit/{$profileId}");
            }
        } else {
            $banks = RefBank::find();
            $profile = Profile::findFirstById($profileId);

            if (in_array($profile->type, DEACTIVATED_PROFILE_TYPES)) {
                $this->logAction("Нет доступа, тип профиля деактивирован", 'security');
                return $this->response->redirect("/order/view/{$profile->id}");
            }

            if ($auth->id != $profile->user_id || $profile->blocked) {
                $this->logAction("Вы не имеете права редактировать этот объект.", 'security');
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                return $this->response->redirect("/order/view/{$profile->id}");
            }

            $this->logAction("Просмотр заявки", 'access');

            $this->view->setVars(array(
                "profile" => $profile->toArray(),
                "banks" => $banks
            ));
        }

        return $this->view->pick('order/edit');
    }

    public function viewAction(int $pid = 0): View|ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            return $this->response->redirect("/home");
        }

        if ($pid <= 0) {
            return $this->response->redirect('/order');
        }

        $profile = Profile::findFirstById($pid);
        if (!$profile) {
            $this->flash->error('Заявка не найдена.');
            return $this->response->redirect('/order');
        }

        if ((int)$profile->user_id !== (int)$auth->id) {
            $this->logAction('Нет прав на просмотр заявки.', 'security');
            $this->flash->error('У вас нет прав на это действие.');
            return $this->response->redirect('/order');
        }

        $cars = $this->carService->itemsByProfile((int)$profile->id);
        $goods = $this->goodsService->itemsByProfile((int)$profile->id);

        $files = File::find([
            'conditions' => "visible = 1 AND profile_id = :pid: AND (type NOT IN ({types:array}) OR type IS NULL)",
            'bind' => [
                'pid' => (int)$profile->id,
                'types' => ['digitalpass', 'spravka_epts'],
            ],
        ]);

        $appForm = File::count([
                'conditions' => "type = 'application' AND profile_id = :pid: AND visible = 1",
                'bind' => ['pid' => (int)$profile->id],
            ]) > 0;

        // связи могут быть null
        $trModel = $profile->tr ?? null;
        $tr = $trModel ? $trModel->toArray() : [];

        $logs = $profile->logs ?? null;

        $canSend = ($cars->count() > 0 || $goods->count() > 0)
            && (($tr['approve'] ?? null) !== 'GLOBAL')
            && $appForm;

        // генерация подписи через сервис
        $signData = $this->orderService->getSignData($profile->id, $profile->hash);

        if ($tr['approve'] == 'DECLINED') {
            $profileLogsMsg = ProfileLogs::findFirst([
                'conditions' => "profile_id = :pid: AND action = 'MSG'",
                'bind' => ['pid' => (int)$profile->id],
                'order' => 'id DESC',
            ]);
            $msg_modal = $profileLogsMsg->meta_after;
            $this->flash->error('<strong>Сообщение модератора:</strong> ' . $msg_modal);
        }

        $this->view->setVars([
            'profile' => $profile->toArray(),
            'tr' => $tr,
            'files' => $files->toArray(),
            'p_logs' => $logs ? $logs->toArray() : [],
            'cars' => CarRowResource::collection($cars),
            'goods' => GoodsRowResource::collection($goods),
            'can_send' => $canSend,
            'sign_data' => $signData,
            'pid' => $profile->id
        ]);

        $this->logAction("Просмотр заявки", 'access');

        return $this->view->pick('order/view');
    }

    /**
     * @throws AppException
     */
    public function reviewAction(): ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$auth->isClient()) {
            return $this->response->redirect("/home");
        }

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("profile_id");
            $can = true;

            $tr = Transaction::findFirstByProfileId($pid);
            $p = Profile::findFirstById($pid);

            if ($tr->approve == 'DECLINED' && __checkSignedAfterDeclined($p->id) != true) {
                $message = "Внимание! Вам необходимо подписать(сформировать) заявление занова, Вы не можете отправить отклоненную заявку на рассмотрение.";
                $this->flash->error($message);
                $this->logAction($message);
                return $this->response->redirect("/order/view/$pid");
            }

            if ($p->created < MD_DT_SENT_2022) {
                $can = false;
                $this->flash->warning("В целях осуществления расчета утилизационного платежа, согласно Методике расчета 
                                 утилизационного платежа действующего с 14 мая 2022 года, рекомендуется создать 
                                 новую заявку (с удалением объектов из данной заявки)");
                return $this->response->redirect("/order");
            }

            $electric_cars = 0;
            $st_type_int_transport = 0;
            $all_cars = 0;
            $zero_cost_cars = 0;

            if ($p->type == 'CAR') {
                if (__checkSignedIntTranApp($p->id) != true) {
                    $message = "Внимание! У вас отсутствует Заявление об обязательном использовании ввозимых 
                      седельных тягачей для международных перевозок.";
                    $this->flash->error($message);
                    $this->logAction($message);
                    return $this->response->redirect("/order/view/$p->id");
                }

                $cars = Car::findByProfileId($p->id);
                $vin_masks = RefVinMask::findByStatus('ACTIVE');

                foreach ($cars as $car) {
                    $all_cars++;

                    if ($car->cost == 0) {
                        $zero_cost_cars++;

                        if ($car->volume == 0) {
                            $electric_cars++;
                        } else {
                            if ($car->electric_car == 1) {
                                $electric_cars++;
                            }
                            if ($car->ref_st_type == 2) {
                                $st_type_int_transport++;
                            }
                        }
                    }

                    $mask_is_match = false;

                    foreach ($vin_masks as $mask) {
                        $mask_id = 0;
                        $check_mask = RefVinMask::checkVinMask($car->vin, $mask->name);
                        if ($check_mask) {
                            $mask_is_match = true;
                            $mask_id = $mask->id;
                            break;
                        }
                    }

                    if ($mask_is_match) {
                        $car->mask_id = $mask_id;
                        $car->save();
                    }
                }
            }

            if ($tr->amount == 0) {
                // ищем электр_машин
                if ($p->type == 'CAR') {
                    if (($electric_cars == $all_cars) || ($st_type_int_transport == $all_cars) || $zero_cost_cars == $all_cars) {
                        $can = true;
                    } else {
                        $can = false;
                    }
                } else {
                    $can = false;
                    $message = "Документы о полноте платы не выдаются с нулевой ставкой. Будьте внимательны при заполнении.";
                    $this->flash->success($message);
                    $this->logAction($message);
                    return $this->response->redirect("/order/view/$pid");
                }
            }

            if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
                $this->logAction("Заблокированный пользователь.", 'security');
                return $this->response->redirect("/order");
            }

            if ($auth->id != $p->user_id) {
                $can = false;
                $message = "У вас нет прав на это действие!";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->warning($message);
                return $this->response->redirect("/order/view/$pid");
            }

            if ($p->blocked != 0 && ($tr->approve == 'NEUTRAL' || $tr->approve == 'DECLINED')) {
                $message = "Не удалось отправить менеджеру, обратитесь к администратору!";
                $this->logAction($message);
                $this->flash->warning($message);
                return $this->response->redirect("/order/view/$pid");
            }

            if ($can) {
                $tr->approve = 'REVIEW';
                $tr->md_dt_sent = time();
                $tr->save();

                $p = Profile::findFirstById($pid);
                $p->blocked = 1;
                $p->save();

                // логгирование
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = 'SEND_TO_REVIEW';
                $l->profile_id = $pid;
                $l->dt = time();
                $l->meta_before = '—';
                $l->meta_after = json_encode(array($p, $tr));
                $l->save();

                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);

                if ($p->type == 'GOODS') {
                    __auto_approve($pid);
                }
                if ($p->type == 'CAR') {
                    __auto_car_approve($pid);
                }

                $this->logAction('Заявка отправлена на рассмотрение');

            }

            return $this->response->redirect("/order/view/$pid");
        } else {
            return $this->response->redirect("/order");
        }
    }

    public function signAction()
    {
        $auth = User::getUserBySession();

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Нет доступа", 'security');
            return $this->response->redirect("/order/index");
        }

        $pid = $this->request->getPost("profileId");
        $hash = $this->request->getPost("profileHash");
        $sign = $this->request->getPost("profileSign");
        $p = Profile::findFirstById($pid);
        $tr = $p->tr;

        if ($p) {
            $files = File::count(array(
                "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type: AND created_by = :created_by:",
                "bind" => array(
                    "pid" => $p->id,
                    "created_by" => $auth->id,
                    "type" => "application"
                )
            ));

            if ($files > 0) {
                $message = 'Уважаемый пользователь, вам необходимо удалить ранее подписанное заявление.';
                $this->logAction($message);
                $this->flash->warning($message);
                return $this->response->redirect("/order/view/$p->id");
            }

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $sum = 0;

            if ($result && isset($result['data'])) {
                $j = $result['data'];
                $__settings = $this->session->get("__settings");
                if ($__settings['iin'] === $j['iin'] && $__settings['bin'] === $j['bin']) {
                    if ($result['success'] === true) {

                        if ($p->type == 'CAR') {
                            if (__checkSignedIntTranApp($p->id) != true) {
                                $message = "Внимание! У вас отсутствует Заявление об обязательном использовании ввозимых седельных тягачей для международных перевозок.";
                                $this->flash->error($message);
                                $this->logAction($message);
                                return $this->response->redirect("/order/view/$p->id");
                            }

                            $cars = Car::find([
                                'conditions' => "profile_id = :pid:",
                                'bind' => [
                                    'pid' => $p->id
                                ]
                            ]);

                            foreach ($cars as $car) {
                                if ($car->ref_st_type == 0) {
                                    $value = RefCarValue::findFirst(array(
                                        "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                        "bind" => array(
                                            "car_type" => $car->ref_car_type_id,
                                            "volume_start" => $car->volume,
                                            "volume_end" => $car->volume
                                        )
                                    ));
                                } else {
                                    $value = RefCarValue::findFirst(array(
                                        "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:) AND id <> 12",
                                        "bind" => array(
                                            "car_type" => $car->ref_car_type_id,
                                            "volume_start" => $car->volume,
                                            "volume_end" => $car->volume
                                        )
                                    ));
                                }

                                // NOTE: Расчет платежа (добавление машины)
                                if ($value != false) {
                                    if ($car->calculate_method == 1) {
                                        $is_new_profile = true;
                                        if ($tr->approve !== 'GLOBAL' && $p->created < EAEU_NEW_COEFFICIENT_2026) {
                                            $is_new_profile = false;
                                        }

                                        $sum = __calculateCar($car->volume, json_encode($value), $car->ref_st_type, $car->electric_car, $car->ref_country_import, $is_new_profile);
                                        $car->cost = $sum;
                                        $car->updated = time();
                                        $car->save();
                                        __carRecalc($p->id);

                                        $this->logAction('Расчет платежа ТС\ССХТ');
                                    }
                                }
                            }
                        } elseif ($p->type == 'GOODS') {
                            $goods = Goods::find([
                                'conditions' => "profile_id = :pid:",
                                'bind' => [
                                    'pid' => $p->id
                                ]
                            ]);
                            foreach ($goods as $good) {
                                $package_cost = 0;
                                $tn = RefTnCode::findFirstById($good->ref_tn);

                                // NOTE: Расчет платежа (добавление товара)
                                if ($tn != false) {
                                    $good_calc_res = Goods::calculateAmount($good->weight, json_encode($tn));
                                    $sum = $good_calc_res['sum'];

                                    $tn_add = RefTnCode::findFirstById($good->ref_tn_add);
                                    if ($tn_add) {
                                        $package_calc_res = Goods::calculateAmount($good->package_weight, json_encode($tn_add));
                                        $package_cost = $package_calc_res['sum'];
                                        $good->package_cost = $package_cost;
                                    }
                                }

                                $good_amount = round($sum + $package_cost, 2);
                                $good->amount = $good_amount;
                                $good->goods_cost = $sum;

                                $good->save();
                            }

                            __goodRecalc($p->id);
                        }

                        $p->sign = $sign;
                        $p->sign_date = time();
                        $p->save();

                        __genApplication($pid, $this, $j);

                        $this->logAction("Заявление сформировано и подписано.");
                        $this->flash->success("Заявление сформировано и подписано.");
                    } else {
                        $this->logAction("Подпись не прошла проверку!", 'security', 'NOTICE');
                        $this->flash->error("Подпись не прошла проверку!");
                    }
                } else {
                    $this->logAction("Вы используете несоответствующую профилю подпись.", 'security', 'ALERT');
                    $this->flash->error("Вы используете несоответствующую профилю подпись.");
                }
            }
        }

        return $this->response->redirect("/order/view/$p->id");
    }

    public function inttrappsignAction()
    {
        $auth = User::getUserBySession();

        $pid = $this->request->getPost("profile_id");
        $hash = $this->request->getPost("profileHash");
        $sign = $this->request->getPost("profileSign");

        $p = Profile::findFirstById($pid);

        if ($p) {
            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($auth->idnum == $j['iin'] && $auth->bin == $j['bin']) {
                if ($result['success'] === true) {
                    $p->international_transporter = 1;
                    $p->int_tr_app_sign = $sign;
                    $p->save();

                    __genIntTranApp($pid, 'sign', $j);
                    $message = "Заявление сформировано и подписано.";
                    $this->logAction($message);
                    $this->flash->success($message);
                    return $this->response->redirect("/order/view/$pid");
                } else {
                    $message = "Подпись не прошла проверку!";
                    $this->logAction($message, 'security');
                    $this->flash->success($message);
                    return $this->response->redirect("/order/view/$pid");
                }
            } else {
                $message = "Вы используете несоответствующую профилю подпись.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->success($message);
                return $this->response->redirect("/order/view/$pid");
            }
        }
    }

    /**
     * Скачивание заявления для заполнения.
     * @param int $pid
     * @param string $type car|goods
     * @return void
     */
    public function applicationAction($pid, $type)
    {
        $this->view->disable();

        if ($type == 'car') {
            if (true) {
                $src = APP_PATH . '/app/templates/html/application/application.html';
                $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                $content = file_get_contents($src);

                $profile = Profile::findFirstById($pid);
                $tr = Transaction::findFirstByProfileId($profile->id);

                $user = User::findFirstById($profile->user_id);

                if ($user->isAgent() || $user->isAdminSoft()) {
                    if ($profile->agent_sign) {
                        $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                        $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                        $fio_bottom = $profile->agent_name;
                        $fio_line = '______________________________<br />М.П.';
                    } else {
                        $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                        $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                        $fio_bottom = $profile->agent_name;
                        $fio_line = '______________________________<br />';
                    }
                    $city = $profile->agent_city;
                    $address = $profile->agent_address;
                    $phone = $profile->agent_phone;
                } else {
                    if ($user->user_type_id == PERSON) {
                        $pd = PersonDetail::findFirstByUserId($user->id);
                        $cd = ContactDetail::findFirstByUserId($user->id);

                        $iin = '<strong>ИИН: </strong>' . $pd->iin;
                        $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                        $name = '<strong>Импортер: </strong>' . $fio_bottom;
                        $fio_line = '______________________________<br />';
                        $city = $cd->city;
                        $address = $cd->address;
                        $phone = $cd->phone;
                    } else {
                        // а это для ЮЛ
                        $pd = CompanyDetail::findFirstByUserId($user->id);
                        $cd = ContactDetail::findFirstByUserId($user->id);

                        $iin = '<strong>БИН: </strong>' . $pd->bin;
                        $fio_bottom = $pd->name;
                        $name = '<strong>Импортер: </strong>' . $fio_bottom;
                        $fio_line = '______________________________<br />М.П.';
                        $city = $cd->city;
                        $address = $cd->address;
                        $phone = $cd->phone;
                    }
                }

                $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                $content = str_replace('[Z_DATE]', date("d.m.Y", convertTimeZone($tr->date)), $content);
                $content = str_replace('[ZA_CITY]', '<strong>Город постановки на учет: </strong>' . $city, $content);
                $content = str_replace('[Z_CITY]', $city, $content);
                $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                $content = str_replace('[ZA_NAME]', $name, $content);
                $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                $content = str_replace('[Z_SIGN]', '', $content);
                $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                $query = $this->modelsManager->createQuery("
          SELECT
            c.volume AS volume,
            c.vin AS vin,
            c.year AS year,
            c.cost AS cost,
            cc.name AS cat,
            c.date_import AS date_import,
            country.name AS country
          FROM
            Car c
            JOIN Profile p
            JOIN RefCountry country
            JOIN RefCarCat cc
          WHERE
            p.id = :pid: AND
            country.id = c.ref_country AND
            cc.id = c.ref_car_cat AND
            c.profile_id = p.id
          GROUP BY c.id
          ORDER BY c.id DESC");

                $cars = $query->execute(array(
                    "pid" => $profile->id
                ));

                $c = 1;
                $car_content = '';
                foreach ($cars as $key => $v) {
                    $car_content = $car_content . '<tr><td>' . $c . '.</td><td>' . $v->volume . '</td><td>' . $v->vin . '</td><td>' . $v->year . '</td><td>' . $v->country . '</td><td>' . date("d.m.Y", convertTimeZone($v->date_import)) . '</td><td>' . $lc->_($v->cat) . '</td><td>' . number_format($v->cost, 2, ",",
                            "&nbsp;") . '</td></tr>';
                    $c++;
                }

                $content = str_replace('[Z_CONTENT]', $car_content, $content);
                file_put_contents($dst, $content);
                (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

                __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
            } else {
                $this->logAction("Вы должны быть агентом.");
                $this->flash->error("Вы должны быть агентом.");
                return $this->response->redirect("/order/view/$pid");
            }
        } else {
            if ($type == 'goods') {
                if (true) {
                    $src = APP_PATH . '/app/templates/html/application_goods/application.html';
                    $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                    $content = file_get_contents($src);

                    $profile = Profile::findFirstById($pid);
                    $tr = Transaction::findFirstByProfileId($profile->id);

                    $user = User::findFirstById($profile->user_id);

                    if ($user->role->name == 'agent' || $user->role->name == 'admin_soft') {
                        if ($profile->agent_sign) {
                            $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                            $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                            $fio_bottom = $profile->agent_name;
                            $fio_line = '______________________________<br />М.П.';
                        } else {
                            $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                            $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                            $fio_bottom = $profile->agent_name;
                            $fio_line = '______________________________<br />';
                        }
                        $city = $profile->agent_city;
                        $address = $profile->agent_address;
                        $phone = $profile->agent_phone;
                    } else {
                        if ($user->user_type_id == PERSON) {
                            $pd = PersonDetail::findFirstByUserId($user->id);
                            $cd = ContactDetail::findFirstByUserId($user->id);

                            $iin = '<strong>ИИН: </strong>' . $pd->iin;
                            $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                            $name = '<strong>Импортер: </strong>' . $fio_bottom;
                            $fio_line = '______________________________<br />';
                            $city = $cd->city;
                            $address = $cd->address;
                            $phone = $cd->phone;
                        } else {
                            // а это для ЮЛ
                            $pd = CompanyDetail::findFirstByUserId($user->id);
                            $cd = ContactDetail::findFirstByUserId($user->id);

                            $iin = '<strong>БИН: </strong>' . $pd->bin;
                            $fio_bottom = $pd->name;
                            $name = '<strong>Импортер: </strong>' . $fio_bottom;
                            $fio_line = '______________________________<br />М.П.';
                            $city = $cd->city;
                            $address = $cd->address;
                            $phone = $cd->phone;
                        }
                    }

                    $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                    $content = str_replace('[Z_DATE]', date("d.m.Y", $tr->date), $content);
                    $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
                    $content = str_replace('[Z_CITY]', $city, $content);
                    $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                    $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                    $content = str_replace('[ZA_NAME]', $name, $content);
                    $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                    $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                    $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                    $content = str_replace('[Z_SIGN]', '', $content);
                    $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                    $query = $this->modelsManager->createQuery("
          SELECT
            g.weight AS g_weight,
            g.date_import AS g_date,
            g.basis AS g_basis,
            g.amount AS g_amount,
            tn.code AS tn_code,
            g.ref_tn_add AS tn_add
          FROM
            Goods g
            JOIN Profile p
            JOIN RefTnCode tn
          WHERE
            p.id = :pid: AND
            tn.id = g.ref_tn AND
            g.profile_id = p.id
          GROUP BY g.id
          ORDER BY g.id DESC");

                    $goods = $query->execute(array(
                        "pid" => $profile->id
                    ));

                    $c = 1;
                    $goods_content = '';
                    foreach ($goods as $key => $v) {
                        $good_tn_add = '';
                        $tn_add = false;
                        if ($v->tn_add) {
                            $tn_add = RefTnCode::findFirstById($v->tn_add);
                            if ($tn_add) {
                                $good_tn_add = ' (упаковано ' . $tn_add->code . ')';
                            }
                        }
                        $goods_content = $goods_content . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . $good_tn_add . '</td><td>' . $v->g_weight . '</td><td>' . date("d.m.Y", convertTimeZone($v->g_date)) . '</td><td>' . $v->g_basis . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td></tr>';
                        $c++;
                    }

                    $content = str_replace('[Z_CONTENT]', $goods_content, $content);
                    file_put_contents($dst, $content);
                    (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

                    __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
                } else {
                    $this->logAction("Вы должны быть агентом.");
                    $this->flash->error("Вы должны быть агентом.");

                    return $this->response->redirect("/order/view/$pid");
                }
            } else {
                if ($type == 'r20') {
                    if (true) {
                        $src = APP_PATH . '/app/templates/html/application_goods/application_r20.html';
                        $dst = APP_PATH . '/storage/temp/application_' . $pid . '.html';

                        $content = file_get_contents($src);

                        $profile = Profile::findFirstById($pid);
                        $tr = Transaction::findFirstByProfileId($profile->id);

                        $user = User::findFirstById($profile->user_id);

                        if ($user->role->name == 'agent' || $user->role->name == 'admin_soft') {
                            if ($profile->agent_sign) {
                                $iin = '<strong>БИН: </strong>' . $profile->agent_iin;
                                $name = '<strong>Импортер: </strong>' . $profile->agent_name . ', которое представляет ' . $profile->agent_sign;
                                $fio_bottom = $profile->agent_name;
                                $fio_line = '______________________________<br />М.П.';
                            } else {
                                $iin = '<strong>ИИН: </strong>' . $profile->agent_iin;
                                $name = '<strong>Импортер: </strong>' . $profile->agent_name;
                                $fio_bottom = $profile->agent_name;
                                $fio_line = '______________________________<br />';
                            }
                            $city = $profile->agent_city;
                            $address = $profile->agent_address;
                            $phone = $profile->agent_phone;
                        } else {
                            if ($user->user_type_id == PERSON) {
                                $pd = PersonDetail::findFirstByUserId($user->id);
                                $cd = ContactDetail::findFirstByUserId($user->id);

                                $iin = '<strong>ИИН: </strong>' . $pd->iin;
                                $fio_bottom = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                                $fio_line = '______________________________<br />';
                                $city = $cd->city;
                                $address = $cd->address;
                                $phone = $cd->phone;
                            } else {
                                // а это для ЮЛ
                                $pd = CompanyDetail::findFirstByUserId($user->id);
                                $cd = ContactDetail::findFirstByUserId($user->id);

                                $iin = '<strong>БИН: </strong>' . $pd->bin;
                                $fio_bottom = $pd->name;
                                $name = '<strong>Импортер: </strong>' . $fio_bottom;
                                $fio_line = '______________________________<br />М.П.';
                                $city = $cd->city;
                                $address = $cd->address;
                                $phone = $cd->phone;
                            }
                        }

                        $content = str_replace('[Z_NUM]', $tr->profile_id, $content);
                        $content = str_replace('[Z_DATE]', date("d.m.Y", $tr->date), $content);
                        $content = str_replace('[ZA_CITY]', '<strong>Город: </strong>' . $city, $content);
                        $content = str_replace('[Z_CITY]', $city, $content);
                        $content = str_replace('[ZA_ADDRESS]', '<strong>Адрес: </strong>' . $address, $content);
                        $content = str_replace('[ZA_PHONE]', '<strong>Контактный телефон: </strong>' . $phone, $content);
                        $content = str_replace('[ZA_NAME]', $name, $content);
                        $content = str_replace('[ZA_IIN]', '' . $iin, $content);
                        $content = str_replace('[Z_FIO]', '' . $fio_bottom, $content);
                        $content = str_replace('[Z_LINE]', '' . $fio_line, $content);
                        $content = str_replace('[ZA_SUM]', '<strong>Общая сумма заявки: </strong>' . number_format($tr->amount, 2, ",", "&nbsp;") . ' тенге', $content);

                        $query = $this->modelsManager->createQuery("
                            SELECT
                            g.weight AS g_weight,
                            g.date_import AS g_date,
                            g.price AS g_price,
                            g.amount AS g_amount,
                            tn.code AS tn_code,
                            tn.name AS tn_name,
                            g.goods_type AS goods_type,
                            g.up_type AS up_type,
                            g.date_report AS g_report           
                            FROM
                                Goods g             
                            JOIN
                                Profile p             
                            JOIN
                                RefTnCode tn           
                            WHERE
                                p.id = :pid: 
                                AND             tn.id = g.ref_tn 
                                AND             g.profile_id = p.id           
                            GROUP BY
                                g.id           
                            ORDER BY
                            g.id DESC
                        ");

                        $goods = $query->execute(array(
                            "pid" => $profile->id
                        ));

                        $_RU_MONTH = array(
                            "январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"
                        );

                        $c = 1;
                        $goods_content = '';
                        $goods_content1 = '';
                        $goods_content2 = '';
                        foreach ($goods as $key => $v) {
                            if ($v->goods_type == 2) {
                                $up_type = '';
                                switch ($v->up_type) {
                                    case 1:
                                        $up_type = 'Бумажная и картонная упаковки, изделия из бумаги и картона';
                                        break;
                                    case 2:
                                        $up_type = 'Стеклянная упаковка';
                                        break;
                                    case 3:
                                        $up_type = 'Полимерная упаковка, изделия из пластмасс';
                                        break;
                                    case 4:
                                        $up_type = 'Металлическая упаковка';
                                        break;
                                    case 5:
                                        $up_type = 'Упаковка из комбинированных материалов';
                                        break;
                                }
                                $goods_content2 = $goods_content2 . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->tn_name . '</td><td>' . $up_type . '</td><td>' . number_format($v->g_weight, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td><td>' . $_RU_MONTH[date('n',
                                        $v->g_report) - 1] . ' ' . date('Y', $v->g_report) . '</td></tr>';
                            }
                            if ($v->goods_type == 1) {
                                $goods_content1 = $goods_content1 . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->tn_name . '</td><td>' . number_format($v->g_weight, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",", "&nbsp;") . '</td><td>' . $_RU_MONTH[date('n',
                                        $v->g_report) - 1] . ' ' . date('Y', $v->g_report) . '</td></tr>';
                            }
                            if ($v->goods_type == 0) {
                                $goods_content = $goods_content . '<tr><td>' . $c . '.</td><td>' . $v->tn_code . '</td><td>' . $v->g_weight . '</td><td>' . date("d.m.Y", convertTimeZone($v->g_date)) . '</td><td>' . number_format($v->g_price, 2, ",", "&nbsp;") . '</td><td>' . number_format($v->g_amount, 2, ",",
                                        "&nbsp;") . '</td></tr>';
                            }
                            $c++;
                        }

                        $content = str_replace('[Z_CONTENT]', $goods_content, $content);
                        $content = str_replace('[Z_CONTENT1]', $goods_content1, $content);
                        $content = str_replace('[Z_CONTENT2]', $goods_content2, $content);
                        file_put_contents($dst, $content);
                        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/application_' . $pid . '.pdf');

                        __downloadFile(APP_PATH . '/storage/temp/application_' . $pid . '.pdf');
                    } else {
                        $this->logAction("Вы должны быть агентом.");
                        $this->flash->error("Вы должны быть агентом.");

                        return $this->response->redirect("/order/view/$pid");
                    }
                }
            }
        }
    }

    /**
     * @throws AppException
     */
    public function docAction(): ResponseInterface
    {
        $auth = User::getUserBySession();
        $baseRoute = $this->getBaseRoute($auth);
        $profile_id = (int)$this->request->getPost('profile_id');
        $profile = Profile::findFirstById($profile_id);

        if (!$this->request->isPost()) {
            return $this->response
                ->redirect($baseRoute . '/view/' . $profile->id)
                ->send();
        }

        $docType = trim((string)$this->request->getPost('doc_type'));

        if (!$profile) {
            $this->flash->error('Профиль не найден.');
            return $this->response
                ->redirect($baseRoute)
                ->send();
        }

        if (!$this->canEdit($auth, $profile)) {
            $message = 'У вас нет прав на это действие.';
            $this->flash->warning($message);
            $this->logAction($message, 'security');
            return $this->response
                ->redirect($baseRoute . '/view/' . $profile->id)
                ->send();
        }

        if ($docType === '') {
            $message = 'Укажите тип документа.';
            $this->flash->error($message);
            $this->logAction($message);
            return $this->response
                ->redirect($baseRoute . '/view/' . $profile->id)
                ->send();
        }

        // Тело пришло, но PHP его отрезал по post_max_size → $_FILES пуст
        $cl = (int)($this->request->getServer('CONTENT_LENGTH') ?? 0);
        $pms = $this->bytesFromIni((string)ini_get('post_max_size'));
        if ($cl > 0 && $cl > $pms) {
            $message = 'Запрос больше post_max_size (' . ini_get('post_max_size') . ').';
            $this->flash->error($message);
            $this->logAction($message);

            return $this->response
                ->redirect($baseRoute . '/view/' . $profile->id)
                ->send();
        }

        $files = $this->request->getUploadedFiles(false); // включаем файлы с ошибками
        if (empty($files)) {
            $message = 'Файл не передан. Проверьте enctype="multipart/form-data".';
            $this->flash->error($message);
            $this->logAction($message);

            return $this->response
                ->redirect($baseRoute . '/view/' . $profile->id)
                ->send();
        }

        $saved = false;
        foreach ($files as $file) {
            // Сначала разбираем код ошибки PHP
            $err = $file->getError();
            if ($err !== UPLOAD_ERR_OK) {
                $message = match ($err) {
                    UPLOAD_ERR_INI_SIZE => 'Размер файла нельзя превышать 50 МБ',
                    UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE в форме.',
                    UPLOAD_ERR_PARTIAL => 'Файл загружен частично.',
                    UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории.',
                    UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP.',
                    default => 'Ошибка загрузки файла.'
                };
                $this->logAction($message);
                $this->flash->error($message);
                continue;
            }

            // Затем фактический размер и ваш лимит 15 МБ
            $size = (int)$file->getSize();
            if ($size <= 0) {
                $message = 'Пустой файл.';
                $this->flash->error($message);
                $this->logAction($message);
                continue;
            }
            if ($size > self::MAX_FILE_SIZE) {
                $message = 'Размер файла нельзя превышать 50 МБ.';
                $this->flash->error($message);
                $this->logAction($message);
                continue;
            }

            $original = $file->getName();
            $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));

            $nf = new File();
            $nf->profile_id = $profile->id;
            $nf->type = $docType;
            $nf->original_name = $original;
            $nf->created_by = $auth->id;
            $nf->ext = $ext;

            if (!$nf->save()) {
                $message = 'Не удалось сохранить метаданные файла.';
                $this->flash->error($message);
                $this->logAction($message);
                continue;
            }

            $target = APP_PATH . "/private/docs/{$nf->id}.{$ext}";
            if (!$file->moveTo($target)) {
                $nf->delete();
                $message = 'Не удалось сохранить файл на диск.';
                $this->flash->error($message);
                $this->logAction($message);
                continue;
            }

            $message = 'Файл добавлен';
            $this->logAction("Загружен файл: " . $original);
            $this->flash->success($message);
            $saved = true;
        }

        if (!$saved) {
            $message = 'Файлы не были добавлены.';
            $this->flash->warning($message);
        }

        return $this->response
            ->redirect($baseRoute . '/view/' . $profile->id)
            ->send();
    }

    private function getBaseRoute(User $auth): string
    {
        if ($auth->isSuperModerator()) {
            return '/create_order';
        } else if ($auth->isClient()) {
            return '/order';
        }

        return '/moderator_order';
    }

    private function bytesFromIni(string $v): int
    {
        $v = trim($v);
        $n = (int)$v;
        $s = strtolower(substr($v, -1));
        return $s === 'g' ? $n << 30 : ($s === 'm' ? $n << 20 : ($s === 'k' ? $n << 10 : (int)$v));
    }

    /**
     * Скачать документ.
     * @param int $id
     * @return void
     * @throws AppException
     */
    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || ($auth->isEmployee())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                $this->logAction('Просмотр файла', 'access');
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name);
            } else {
                $message = "Файл не найден";
                $this->flash->warning($message);
                $this->logAction($message, 'access');
                return $this->response->redirect($this->request->getHTTPReferer());
            }
        }
    }

    /**
     * Просмотреть документ.
     * @param int $id
     * @return void
     * @throws AppException
     */
    public function viewdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || ($auth->isEmployee())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                $this->logAction('Просмотр файла', 'access');
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
            } else {
                $message = "Файл не найден";
                $this->flash->warning($message);
                $this->logAction($message, 'access');
                return $this->response->redirect($this->request->getHTTPReferer());
            }
        }
    }

    public function viewfileAction($id)
    {
        $this->view->disable();
        $profile = Profile::findFirstById($id);
        $auth = User::getUserBySession();

        if ($profile->user_id == $auth->id || ($auth->isEmployee())) {
            $file = APP_PATH . '/private/docs/app_int_transport_' . $profile->id . '.pdf';
            __genIntTranApp($profile->id, 'view');
            __downloadFile($file, 'app_int_transport_' . $profile->id . '.pdf', 'view');
        }
    }

    /**
     * Удаление документа к заявке.
     * @param int $id
     * @return ResponseInterface
     * @throws AppException
     */
    public function rmdocAction($id)
    {
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $p = Profile::findFirstById($pf->profile_id);
        $_before = json_encode(array($pf));

        if (!$this->canEdit($auth, $p)) {
            $message = "Вы не можете удалить этот файл.";
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect($this->request->getHTTPReferer(), true);
        }

        $pf->visible = 0;
        if ($pf->type == 'app_international_transport') {
            $p->international_transporter = 0;
            $p->int_tr_app_sign = null;
            $p->save();
        }
        $pf->modified_at = time();
        $pf->modified_by = $auth->id;
        if ($pf->save()) {
            $f_logs = new FileLogs();
            $f_logs->file_id = $pf->id;
            $f_logs->type = $pf->type;
            $f_logs->profile_id = $pf->profile_id;
            $f_logs->user_id = $auth->id;
            $f_logs->iin = $auth->idnum;
            $f_logs->action = 'DELETED';
            $f_logs->dt = time();
            $f_logs->meta_before = $_before;
            $f_logs->meta_after = json_encode(array($pf));
            $f_logs->file = $pf->original_name;
            $f_logs->save();
            $this->logAction("Файл удален: " . $pf->type . ', ' . $pf->original_name);
        }

        return $this->response->redirect($this->request->getHTTPReferer(), true);
    }

    private function canEdit($auth, $p): bool
    {
        $isSuperModeratorCase =
            $auth->isSuperModerator()
            && $p->moderator_id === $auth->id
            && ($p->tr->approve === 'NEUTRAL' || $p->tr->approve === 'DECLINED') && $p->blocked == 0;

        $isAdminCase = $auth->isAdminSoft();

        $isOwnerCase = !$p->blocked && $p->user_id === $auth->id;

        return $isSuperModeratorCase || $isAdminCase || $isOwnerCase;
    }

    /**
     * Восстановление документа к заявке.
     * @param int $id
     * @return void
     */
    public
    function restoreAction($id)
    {
        $pf = File::findFirstById($id);
        $auth = User::getUserBySession();

        $pf->visible = 1;
        $pid = $pf->profile_id;
        $pf->modified_at = time();
        $pf->modified_by = $auth->id;
        $pf->save();

        $this->logAction('Восстановление файла');

        return $this->response->redirect("/moderator_order/view/$pid");
    }

    public
    function deleteAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        $profile = Profile::findFirstById($pid);
        $cars = Car::find(array(
            "profile_id = :profile_id:",
            "bind" => array(
                "profile_id" => $profile->id
            )
        ));

        if ($auth->id != $profile->user_id || $profile->blocked || count($cars) > 0) {
            $this->logAction("Вы не имеете права удалять этот объект.", 'security');
            $this->flash->error("Вы не имеете права удалять этот объект.");
            return $this->response->redirect("/order/index");
        } else {
            $this->logAction("Удаление произошло успешно.");
            $this->flash->success("Удаление произошло успешно.");
            return $this->response->redirect("/order/index");
        }
    }

    /**
     * Импорт машин.
     * @param int $pid
     * @return void
     * @throws AppException
     */
    public
    function importAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        $profile = Profile::findFirstById($pid);

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Доступ запрещен. Заблокированный пользователь.", 'security');
            return $this->response->redirect("/order/");
        }

        $isOwner = $auth->id === $profile->user_id;
        $isModerator = $auth->id === $profile->moderator_id;
        $isNotBlocked = $profile->blocked === 0;

        if (!$isModerator && !$isOwner && !$isNotBlocked) {
            $message = "Вы не имеете права редактировать этот объект.";
            $this->logAction($message, 'security', 'ALERT');
            $this->flash->error($message);
            return $this->response->redirect("/order/index/");
        }

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile->id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->flash->warning('Уважаемый пользователь, вы не можете добавить, отредактировать или удалить ТС, вы уже подписали электронное Заявление 
                            (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                            Если вы хотите отредактировать данные ТС, Вам необходимо удалить Подписанное Заявление. 
                            После внесения изменений в данные ТС подпишите Заявление повторно.');

            return $this->response->redirect("/order/view/$profile->id");
        }

        $check_queue = __checkQueue($profile->id);

        if ($check_queue['FOUND']) {
            $this->flash->warning('Осуществляется загрузка ТС. Пожалуйста, подождите.');
            return $this->response->redirect("/order/view/$profile->id");
        }

        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    public
    function getsfAction($trid)
    {
        $tr = Transaction::findFirstById($trid);
        $p = Profile::findFirstById($trid);
        $sf = Sf::findFirstByProfileId($p->id);

        if (!$sf || count($sf) == 0) {
            $sf = new Sf();

            $sf->num = '';
            $sf->profile_id = $p->id;
            $sf->amount = $tr->amount;

            if ($p->created < ROP_VAT_DATE) {
                $sf->vat = 0;
            } else {
                $sf->vat = 1;
            }

            if (!$p->agent_iban && $p->agent_name) {
                return $this->response->redirect("/order/correct/$p->id");
            }

            $user = User::findFirstById($p->user_id);

            if ($user->isAgent() || $user->isAdminSoft()) {
                if ($p->agent_iban) {
                    $client_iin = $p->agent_iin;
                    $client_name = $p->agent_name;
                    $client_address = $p->agent_address;
                    $client_phone = $p->agent_phone;
                    $client_iban = $p->agent_iban;
                    $bank = RefBank::findFirstById($p->agent_bank);
                    $client_bik = $bank->bik;
                    $client_bank = $bank->name;
                } else {
                    $client_iin = $p->agent_iin;
                    $client_name = $p->agent_name;
                    $client_address = $p->agent_address;
                    $client_phone = $p->agent_phone;
                    $client_iban = 'ИИК неизвестен';
                    $client_bik = 'неизвестен';
                    $client_bank = 'банк неизвестен';
                }
            } else {
                if ($user->user_type_id == PERSON) {
                    $pd = PersonDetail::findFirstByUserId($user->id);
                    $cd = ContactDetail::findFirstByUserId($user->id);

                    $client_iin = $pd->iin;
                    $client_name = $pd->last_name . ' ' . $pd->first_name . ' ' . $pd->parent_name;
                    $client_address = $cd->address;
                    $client_phone = $cd->phone;
                    $client_iban = 'ИИК неизвестен';
                    $client_bik = 'неизвестен';
                    $client_bank = 'банк неизвестен';
                } else {
                    // а это для ЮЛ
                    $pd = CompanyDetail::findFirstByUserId($user->id);
                    $cd = ContactDetail::findFirstByUserId($user->id);

                    $client_iin = $pd->bin;
                    $client_name = $pd->name;
                    $client_address = $cd->address;
                    $client_phone = $cd->phone;
                    $client_iban = $pd->iban;
                    $bank = RefBank::findFirstById($pd->ref_bank_id);
                    $client_bik = $bank->bik;
                    $client_bank = $bank->name;
                }
            }

            $sf->to = $client_name . ', БИН/ИИН: ' . $client_iin . ', адрес: ' . $client_address . ', телефон: ' . $client_phone . ', IBAN: ' . $client_iban . ', БИК: ' . $client_bik . ', банк: ' . $client_bank;
            $sf->posted = time();
            $sf->save();
        }

        return $this->response->redirect("/order/view/$p->id");
    }

    /**
     * Дополнение недостающих данных в заявке.
     * @param int $pid
     * @return void
     */
    public
    function correctAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!", 'security');
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $profile = Profile::findFirstById($pid);

            if ($auth->id != $profile->user_id && !$auth->isAdminSoft()) {
                $this->logAction("Вы не имеете права редактировать этот объект.", 'security');
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                return $this->response->redirect("/order/view/$profile->id");
            }

            $agent_bank = $this->request->getPost('agent_bank');
            $agent_iban = $this->request->getPost('agent_iban');
            $agent_type = $this->request->getPost('agent_type');

            $profile->agent_bank = htmlspecialchars($agent_bank);
            $profile->agent_iban = htmlspecialchars($agent_iban);
            $profile->agent_type = htmlspecialchars($agent_type);

            if ($profile->save()) {
                $this->logAction("Заявка отредактирована.");
                $this->flash->success("Изменения сохранены.");

                if ($profile->agent_type == PERSON) {
                    return $this->response->redirect("/order/view/$profile->id");
                }
                return $this->response->redirect("/order/getsf/$profile->id");
            } else {
                $this->logAction("Нет возможности сохранить ваши изменения.");
                $this->flash->error("Нет возможности сохранить ваши изменения.");
                return $this->response->redirect("/order/view/$profile->id");
            }
        } else {
            $profile = Profile::findFirstById($pid);
            $banks = RefBank::find();

            if ($auth->id != $profile->user_id && !$auth->isAdminSoft()) {
                $this->logAction("Вы не имеете права редактировать этот объект.", 'security');
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                return $this->response->redirect("/order/view/$profile->id");
            }

            $can_set_iban = false;
            if ($auth->isAgent() || $auth->isAdminSoft()) {
                $can_set_iban = true;
            }

            $this->view->setVars(array(
                "profile" => $profile,
                "banks" => $banks,
                "can_set_iban" => $can_set_iban
            ));
        }
    }

    public
    function clearFilterAction()
    {
        $_SESSION['order_filter_year'] = json_encode(array(date("Y")));
        $_SESSION['order_filter_status'] = json_encode(array('REVIEW', 'NEUTRAL', 'DECLINED', 'GLOBAL', 'APPROVE', 'CERT_FORMATION'));
        $_SESSION['order_filter_type'] = json_encode(array('CAR', 'GOODS'));

        $this->response->redirect("/order/index/");
    }
}
