<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Repositories\CarRepository;
use App\Services\Car\CarService;
use App\Services\Car\CarValidator;
use App\Services\Car\Dto\CarCreateDto;
use App\Services\Cms\CmsService;
use Car;
use ClientCorrectionCars;
use ClientCorrectionFile;
use ClientCorrectionLogs;
use ClientCorrectionProfile;
use ControllerBase;
use File;
use FundCar;
use FundProfile;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\View;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;
use Profile;
use RefCarCat;
use RefCarType;
use RefCarValue;
use RefCountry;
use Transaction;
use User;

class CarController extends ControllerBase
{
    private CarService $carService;
    private CarValidator $carValidator;
    private CarRepository $carRepository;

    public function onConstruct(): void
    {
        $this->carService = $this->di->getShared('carService');
        $this->carValidator = $this->di->getShared('carValidator');
        $this->carRepository = $this->di->getShared(CarRepository::class);
    }

    public function indexAction(): void
    {
        $numberPage = $this->request->getQuery('page', 'int', 1);
        $auth = User::getUserBySession();

        if (!$auth) {
            $this->response->redirect('/login');
            return;
        }

        $builder = $this->carRepository->getCarBuilder((int)$auth->id);

        $paginator = new QueryBuilderPaginator([
            'builder' => $builder,
            'limit' => 20,
            'page' => $numberPage,
        ]);

        $this->view->setVars([
            'page' => $paginator->paginate(),
            'auth' => $auth,
        ]);
    }

    public function newAction($pid = 0)
    {
        $auth = User::getUserBySession();
        $m = $this->session->get('CAR_TYPE');
        $profile = Profile::findFirstById($pid);
        $baseRoute = $this->getBaseRoute($auth);

        if ((int)$pid === 0) {
            return $this->redirectBackOr($baseRoute);
        }

        if (!$profile) {
            return $this->redirectBackOr($baseRoute);
        }

        $this->checkAccess($auth, $profile->id, $profile->blocked);

        // В сервис передаётся владелец профиля, как в исходном коде
        $dto = CarCreateDto::fromRequest($this->request);
        if ($this->request->isPost()) {
            if ($m === 'TRAC') {
                $this->logAction("Ввод идентификационного номера: body_code: {$dto->body_code}, id_code: {$dto->id_code}", 'action', 'DEBUG');
            } else {
                $this->logAction("Ввод идентификационного номера: vin: {$dto->vin}", 'action', 'DEBUG');
            }
        }

        try {
            $data = $this->carService->new($dto, $profile->user, $profile, $auth);
        } catch (AppException $e) {
            $this->logAction($e->getMessage());
            $this->flash->warning($e->getMessage());
            return $this->response->redirect("/car/check_epts/" . $profile->id . "?m=" . $m);
        }

        $this->view->setVars($data);

        // Типы ТС по режиму
        $ids = ($m === 'TRAC') ? [4, 5] : [1, 2, 3, 6];

        $car_types = RefCarType::find([
            'conditions' => 'id IN ({ids:array})',
            'bind' => ['ids' => $ids],
        ]);

        $cats = RefCarCat::find();
        $countries = RefCountry::find(['id NOT IN (1, 201)']);

        // Проверка, что нельзя смешивать АГРО и авто в одной заявке
        $check_agro = Car::findFirst([
            "ref_car_cat IN (13, 14) AND profile_id = :profile_id:",
            "bind" => ["profile_id" => $pid],
        ]);

        $check_car = Car::findFirst([
            "ref_car_cat NOT IN (13, 14) AND profile_id = :profile_id:",
            "bind" => ["profile_id" => $pid],
        ]);

        if ($m === 'CAR' && $check_agro) {
            $this->flash->error("Нельзя добавлять автомобиль к заявке, где уже есть сельхозтехника.");
            return $this->response->redirect("/car/check_epts/" . $profile->id . "?m=" . $m);
        }

        if ($m === 'TRAC' && $check_car) {
            $this->flash->error("Нельзя добавлять сельхозтехнику к заявке, где уже есть автомобили.");
            return $this->response->redirect("/car/check_epts/" . $profile->id . "?m=" . $m);
        }

        $integration = $data['integration_data'] ?? [];

        $this->view->setVars([
            "cats" => $cats,
            "car_types" => $car_types,
            "countries" => $countries,
            "pid" => $pid,
            "m" => $m,
            "volume" => $data['volume'],
            "year" => $data['year'],
            "import_date" => $data['date_import'] ? date('d.m.Y', $data['date_import']) : '',
            "is_electric" => $data['electric_car'],
            "vehicle_type" => $data['vehicle_type'],
            "ref_country_id" => $data['ref_country'],
            "ref_country_import_id" => $data['ref_country_import'],
            "ref_car_cat_id" => $data['ref_car_cat'],
            "semi_truck" => $data['ref_st_type'],
            "id_code" => $data['id_code'],
            "body_code" => $data['body_code'],
            "vin" => $data['vin'],
            "permissible_max_weight" => $integration['permissible_max_weight'] ?? null,
            "engine_capacity" => $integration['engine_capacity'] ?? null,
            "integration_data" => $integration,
        ]);
    }

    public function addAction(): ResponseInterface
    {
        $auth = User::getUserBySession();
        $pid = (int)$this->request->getPost('profile_id');
        $baseRoute = $this->getBaseRoute($auth);
        $m = $this->session->get('CAR_TYPE');

        if ($pid === 0) {
            return $this->redirectBackOr($baseRoute);
        }

        $profile = Profile::findFirstById($pid);
        if (!$profile) {
            return $this->redirectBackOr($baseRoute);
        }

        try {
            $this->checkAccess($auth, $profile->id, $profile->blocked);
            $this->carService->checkProfileLockedForChanges($profile->id);

            $dto = CarCreateDto::fromRequest($this->request);
            if ($m === 'TRAC') {
                $this->logAction("Ввод идентификационного номера: body_code: {$dto->body_code}, id_code: {$dto->id_code}", 'action', 'DEBUG');
            } else {
                $this->logAction("Ввод идентификационного номера: vin: {$dto->vin}", 'action', 'DEBUG');
            }
            $car = $this->carService->create($dto, $profile->user, $profile, $auth);
            $this->carService->setTransactionSum($profile->id);

            if ($car->epts_request_id) {
                __uploadEptsPdfToProfile($car->epts_request_id, $profile->id, $car->id);
            }

            $message = 'Транспортное средство добавлено';
            $this->logAction($message . ', ID: ' . $car->id);
            $this->flash->success($message);
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            $this->logAction($e->getMessage());
            $this->flash->warning('Не удалось добавить транспортное средство');
        }

        return $this->response->redirect("$baseRoute/view/$pid");
    }

    public function updateAction($cid = 0): ResponseInterface
    {
        $auth = User::getUserBySession();
        $cid = (int)$cid;
        $baseRoute = $this->getBaseRoute($auth);
        $m = $this->session->get('CAR_TYPE');

        if ($cid === 0) {
            return $this->redirectBackOr($baseRoute);
        }

        $car = Car::findFirstById($cid);
        if (!$car) {
            return $this->redirectBackOr($baseRoute);
        }

        $profile = Profile::findFirstById($car->profile_id);
        if (!$profile) {
            return $this->redirectBackOr($baseRoute);
        }

        try {
            $this->checkAccess($auth, $profile->id, $profile->blocked);
            $this->carService->checkProfileLockedForChanges($profile->id);

            $dto = CarCreateDto::fromRequest($this->request);
            if ($m === 'TRAC') {
                $this->logAction("Ввод идентификационного номера: body_code: {$dto->body_code}, id_code: {$dto->id_code}", 'action', 'DEBUG');
            } else {
                $this->logAction("Ввод идентификационного номера: vin: {$dto->vin}", 'action', 'DEBUG');
            }
            $car = $this->carService->update($dto, $profile->user, $car, $auth);
            $this->carService->setTransactionSum($profile->id);

            if ($car->epts_request_id) {
                __uploadEptsPdfToProfile($car->epts_request_id, $profile->id, $car->id);
            }

            $message = "Транспортное средство обновлено";
            $this->logAction($message . ', ID: ' . $car->id);
            $this->flash->success($message);
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            $this->flash->warning("Не удалось обновить транспортное средство");
            $this->logAction($e->getMessage());
        }

        return $this->response->redirect("$baseRoute/view/$profile->id");
    }

    public function editAction($cid = 0)
    {
        $auth = User::getUserBySession();
        $cid = (int)$cid;
        $m = $this->session->get('CAR_TYPE');

        if ($cid === 0) {
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $car = Car::findFirstById($cid);
        if (!$car) {
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $profile = Profile::findFirstById($car->profile_id);
        if (!$profile) {
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        try {
            $this->checkAccess($auth, $profile->id, $profile->blocked);
            $this->carService->checkProfileLockedForChanges($profile->id);
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $user = User::findFirstById($profile->user_id);
        $dto = CarCreateDto::fromRequest($this->request);

        if ($this->request->isPost()) {
            if ($m === 'TRAC') {
                $this->logAction("Ввод идентификационного номера: body_code: {$dto->body_code}, id_code: {$dto->id_code}", 'action', 'DEBUG');
            } else {
                $this->logAction("Ввод идентификационного номера: vin: {$dto->vin}", 'action', 'DEBUG');
            }
        }

        try {
            $data = $this->carService->edit($dto, $user, $car, $auth);
            if ($this->request->isGet()) {
                if ($car->vehicle_type == 'AGRO') {
                    $codeParts = explode('&', $car->vin);
                    $id_code = $codeParts[0] ?? '';
                    $body_code = $codeParts[1] ?? '';
                }
            }
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            $this->logAction($e->getMessage());
        }

        $m = in_array($car->ref_car_type_id, [4, 5], true) ? 'TRAC' : 'CAR';
        $ids = ($m === 'TRAC') ? [4, 5] : [1, 2, 3, 6];

        $car_types = RefCarType::find([
            'conditions' => 'id IN ({ids:array})',
            'bind' => ['ids' => $ids],
        ]);

        $this->view->setVars([
            "cats" => RefCarCat::find(),
            "car_types" => $car_types,
            "countries" => RefCountry::find(['id NOT IN (1, 201)']),
            "pid" => $profile->id,
            "volume" => $car->volume,
            "year" => $car->year,
            "import_date" => $car->date_import ? date('d.m.Y', $car->date_import) : '',
            "is_electric" => $car->electric_car,
            "vehicle_type" => $car->vehicle_type,
            "ref_country_id" => $car->ref_country,
            "ref_country_import_id" => $car->ref_country_import,
            "ref_car_cat_id" => $car->ref_car_cat,
            "semi_truck" => $car->ref_st_type,
            "id_code" => $id_code ?? null,
            "body_code" => $body_code ?? null,
            "vin" => $car->vin,
            "permissible_max_weight" => ($data['integration_data'] ?? [])['permissible_max_weight'] ?? null,
            "engine_capacity" => ($data['integration_data'] ?? [])['engine_capacity'] ?? null,
            "integration_data" => $data['integration_data'] ?? [],
            "m" => $m,
            "car_id" => $car->id,
        ]);
    }

    public function checkEptsAction(int $pid = 0): View|ResponseInterface
    {
        if ($pid == 0) {
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $profile = Profile::findFirstById($pid);
        if (!$profile) {
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        try {
            if ($this->carValidator->hasReachedCarLimit($profile->id)) {
                throw new AppException($this->translator->_('max_car_add_profile_validate'));
            }
            $this->carService->checkProfileLockedForChanges($profile->id);
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            $this->logAction($e->getMessage());
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $m = ($this->request->getQuery('m') === 'TRAC') ? 'TRAC' : 'CAR';
        $this->session->set('CAR_TYPE', $m);

        $ids = ($m === 'TRAC') ? [4, 5] : [1, 2, 3, 6];
        $car_types = RefCarType::find([
            'conditions' => 'id IN ({ids:array})',
            'bind' => ['ids' => $ids],
        ]);

        $check_agro = Car::findFirst([
            "conditions" => "ref_car_cat IN (13, 14) AND profile_id = :profile_id:",
            "bind" => ["profile_id" => $pid]
        ]);

        $check_car = Car::findFirst([
            'conditions' => "ref_car_cat NOT IN (13, 14) AND profile_id = :profile_id:",
            'bind' => ['profile_id' => $pid]
        ]);

        if ($m === 'CAR' && $check_agro) {
            $this->flash->error("Нельзя добавлять автомобиль к заявке, где уже есть сельхозтехника.");
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        if ($m === 'TRAC' && $check_car) {
            $this->flash->error("Нельзя добавлять сельхозтехнику к заявке, где уже есть автомобили.");
            return $this->response->redirect($this->request->getHTTPReferer());
        }

        $this->view->setVars([
            "cats" => RefCarCat::find(),
            "car_types" => $car_types,
            "countries" => RefCountry::find(['id NOT IN (1, 201)']),
            "pid" => $pid,
            "m" => $m,
            "is_vendor" => ($profile->agent_status === "VENDOR"),
        ]);

        return $this->view->pick('car/check_epts');
    }

    public function uploadAction()
    {
        $auth = User::getUserBySession();
        $order = $this->request->getPost("order_id");
        $profile = Profile::findFirstById($order);

        $c = Car::findByProfileId($profile->id);
        $count = count($c);
        $existVin = [];
        $successfully_added = 0;

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

        if ($this->request->isPost()) {

            $isOwner = $auth->id === $profile->user_id;
            $isModerator = $auth->id === $profile->moderator_id;
            $isNotBlocked = $profile->blocked === 0;

            if (!$isModerator && !$isOwner && !$isNotBlocked) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/index/");
            }

            $filePath = APP_PATH . "/storage/temp/" . $order . ".csv";
            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo($filePath);
                }
            }

            if (!file_exists($filePath)) {
                $this->flash->error("Ошибка импорта:<br>файл не найден");
                $this->logAction('Ошибка импорта, файл не найден', 'action', 'ERROR');
                return $this->response->redirect("/order/view/$profile->id");
            }

            $import = file($filePath);
            $errors = [];

            if ($import === false) {
                $this->flash->error("Не удалось прочитать данные из файла.");
                $this->logAction('Не удалось прочитать данные из файла', 'action', 'ERROR');
                return $this->response->redirect("/order/view/" . ($profile->id ?? ''));
            }

            foreach ($import as $key => $value) {
                if ($key > 0) {
                    $val = __multiExplode([";", ","], $value);

                    $car_type = trim($val[0] ?? '');
                    $car_volume = trim($val[1] ?? '');
                    $vin = mb_strtoupper(trim($val[2] ?? ''));
                    $year = trim($val[3] ?? '');
                    $car_date_raw = trim($val[4] ?? '');
                    $car_date = $car_date_raw ? strtotime($car_date_raw) : '';
                    $car_cat = trim($val[5] ?? '');
                    $car_country = trim($val[6] ?? '');
                    $e_car = isset($val[8]) ? (int)trim($val[8]) : 0;
                    $car_country_import = trim($val[9] ?? '');
                    $missing_fields = [];

                    if (!$car_country) $missing_fields[] = 'Страна производства';
                    if (!$car_type) $missing_fields[] = 'Тип автомобиля';
                    if ($e_car == 0) {
                        if (!$car_volume) $missing_fields[] = 'Объем';
                    }
                    if (!$vin) $missing_fields[] = 'VIN';
                    if (!$year) $missing_fields[] = 'Год производства';
                    if (!$car_date) $missing_fields[] = 'Дата ввоза';
                    if (!$car_cat) $missing_fields[] = 'Категория ТС';

                    if (!empty($missing_fields)) {
                        $fields_text = implode(', ', $missing_fields);
                        $errors[] = "У записи с VIN `$vin` не заполнены поля: $fields_text.";
                    }
                }
            }

            if (!empty($errors)) {
                $errorText = implode('<br>', $errors);
                $this->flash->error("Ошибка импорта:<br>$errorText");
                $this->logAction('Ошибка импорта' . implode(', ', $errors), 'action', 'ERROR');
                return $this->response->redirect("/order/view/$profile->id");
            }

            foreach ($import as $key => $value) {
                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);
                    // кириллица в VIN
                    $val[2] = mb_strtoupper($val[2]);
                    $val[2] = preg_replace('/(\W)/', '', $val[2]);

                    $car_type = trim($val[0]); // Тип автомобиля

                    $raw_volume = isset($val[1]) ? trim($val[1]) : '0';
                    $raw_volume = str_replace(',', '.', $raw_volume);
                    $car_volume = (float)$raw_volume; // Объем (см3) или масса (кг)

                    $vin = mb_strtoupper(trim($val[2])); // VIN-код
                    $year = trim($val[3]); // Год производства
                    $car_date = strtotime(trim($val[4])); // Дата ввоза (импорта)
                    $car_cat = isset($val[5]) ? (int)trim($val[5]) : 1; // Категория ТС
                    $car_country = isset($val[6]) ? (int)trim($val[6]) : 0; // Страна производства
                    $ref_st = isset($val[7]) ? (int)trim($val[7]) : 0;; // Седельный тягач? (Да=1, Нет=0, Меж.перевозки=2)
                    $e_car = isset($val[8]) ? (int)trim($val[8]) : 0;
                    $car_country_import = isset($val[9]) ? (int)trim($val[9]) : 0; // Страна производства
                    $is_temporary_importation = false;
                    $kap_log_id = null;

                    $vehicle_type = 'PASSENGER';
                    if ($car_cat == 17) {
                        $car_cat = 15;
                        $vehicle_type = 'CARGO';
                    } else if ($car_cat == 18) {
                        $car_cat = 16;
                        $vehicle_type = 'CARGO';
                    } elseif ($car_cat == 13 || $car_cat == 14) {
                        $vehicle_type = 'AGRO';
                    } elseif (!in_array($car_cat, [3, 4, 5, 6, 7, 8])) {
                        $ref_st = 0;
                    }

                    if ($car_type == 2) {
                        $vehicle_type = 'CARGO';
                    }

                    $calculate_method = 1; // Способ расчета(1 - по дате отправки)

                    $missing_fields = [];
                    $vin_list[] = $vin;
                    if (!$car_country) {
                        $missing_fields[] = 'Страна производства';
                    }
                    if (!$car_type) {
                        $missing_fields[] = 'Тип автомобиля';
                    }
                    if ($e_car == 0) {
                        if (!$car_volume) {
                            $missing_fields[] = 'Объем';
                        }
                    }
                    if (!$vin) {
                        $missing_fields[] = 'VIN';
                    }
                    if (!$year) {
                        $missing_fields[] = 'Год производства';
                    }
                    if (!$car_date) {
                        $missing_fields[] = 'Дата ввоза';
                    }
                    if (!$car_cat) {
                        $missing_fields[] = 'Категория ТС';
                    }

                    if (!empty($missing_fields)) {
                        $fields_list = implode(', ', $missing_fields);
                        $this->flash->error("Ошибка: Отсутствуют обязательные поля ($fields_list)");
                        return $this->response->redirect("/order/view/$profile->id");
                    }

                    if ($year < 1900 || $year > date('Y')) {
                        $this->flash->error("Ошибка: Некорректный год производства (VIN: $vin).");
                        continue;
                    }

                    if (!$car_date) {
                        $this->flash->error("Ошибка: Некорректная дата ввоза (VIN: $vin).");
                        continue;
                    }
                    if ($profile->agent_status == "VENDOR" && (int)$ref_st == 2) {
                        continue;
                    }

                    if ($car_type != 6) {
                        if ($e_car == 1 && $car_type != 2 && $car_volume > 0) {
                            $this->flash->error("Объем электромобиля(легковой или автобус) должен быть 0(VIN: $vin)");
                            continue;
                        }
                    }

                    $ref_car_cat = RefCarCat::findFirstById($car_cat);
                    if ($ref_car_cat) {
                        if ($ref_car_cat->car_type != $car_type) {
                            $this->flash->error("Ошибка! Категория ТС не совпадает с тип автомобиля (VIN: $vin)");
                            continue;
                        }
                    } else {
                        $this->flash->error("Ошибка! Категория ТС неправильно указано (VIN: $vin)");
                        continue;
                    }

                    if ($car_date < strtotime(STARTROP)) {
                        $this->flash->error("Автомобиль с VIN-кодом " . $val[2] . " не может быть импортирован в систему, т.к. ввоз осуществлен до вступления в силу расширенных обязательств.");
                        continue;
                    } else {
                        if (strlen($vin) == 17) {
                            $car_check = Car::count([
                                "conditions" => "vin = :vin:",
                                "bind" => [
                                    "vin" => $vin
                                ]
                            ]);

                            if ($car_check > 0) {
                                $existVin[] = $vin;
                                continue;
                            }

                            if ($car_volume > 50000) {
                                $this->flash->error("Согласно Методики расчета утилизационного платежа транспортные средства с объемом 
                                двигателя более 50 тонн не подлежат к уплате утилизационного платежа.(VIN: $vin)");
                                continue;
                            }

                            if ($ref_st == 0) {
                                $value = RefCarValue::findFirst(array(
                                    "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                    "bind" => array(
                                        "car_type" => $car_type,
                                        "volume_start" => $car_volume,
                                        "volume_end" => $car_volume
                                    )
                                ));
                            } else {
                                if ($car_type == 2 && $car_cat >= 3 && $car_cat <= 8) {
                                    $value = RefCarValue::findFirst(array(
                                        "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                        "bind" => array(
                                            "car_type" => $car_type,
                                            "volume_start" => $car_volume,
                                            "volume_end" => $car_volume
                                        )
                                    ));
                                } else {
                                    $value = false;
                                    $this->flash->notice("Внимание! Данный тип автомобиля не может быть Седельный тягач (Автомобиль с VIN-кодом: $vin) !");
                                    continue;
                                }
                            }

                            if ($value != false) {
                                $count++;

                                if ($count >= 51) {
                                    $this->flash->error("Максимальное число ТС в заявке должно быть не больше 50.");
                                    break;
                                }

                                $carData = $this->carService->getCarDataFromStorage($vin);
                                if (!$carData) {
                                    $carData = $this->carService->getCarData(null, $vin);
                                }

                                if (!empty($carData)) {
                                    $is_temporary_importation = null;
                                    $kap_log_id = null;
                                    $ref_car_cat_id = $car_cat;
                                    $is_temporary_importation = $carData['is_temporary_importation'];
                                    $kap_log_id = $carData['kap_log_id'];
                                    $year = $carData['year'];

                                    if ($carData['ref_country_id']) {
                                        $car_country = $carData['ref_country_id'];
                                    }

                                    if ($carData['ref_car_cat_id']) {
                                        $car_cat = $carData['ref_car_cat_id'];
                                    }

                                    if ($year) {
                                        $cats_m = ['M1', 'M1G', 'M2', 'M3', 'M2G', 'M3G'];
                                        $cats_n = ['N1', 'N2', 'N3', 'N1G', 'N2G', 'N3G'];
                                        $cats_other = ['TRACTOR', 'COMBAIN'];
                                        $category = $car_cat ? RefCarcat::findFirstById($car_cat) : '';

                                        if ($category) {
                                            $car_cat = $category->id;

                                            //Если категория M
                                            if (in_array($category->tech_category, $cats_m)) {
                                                //то передаем Объем
                                                if (isset($carData['engine_capacity'])) {
                                                    $car_volume = $carData['engine_capacity'];
                                                }
                                                //не седельный тягач
                                                $ref_st_type = 0;
                                                //если объем больше 0 то это не электромобиль
                                                if ($car_volume > 0) {
                                                    $e_car = 0;
                                                }
                                            }

                                            //Если это категория N
                                            if (in_array($category->tech_category, $cats_n)) {
                                                //то передаем Макс. массу
                                                if (isset($carData['permissible_max_weight'])) {
                                                    $car_volume = $carData['permissible_max_weight'];
                                                }
                                                //если объем больше 0 то это не электромобиль
                                                if (isset($carData['permissible_max_weight']) && $carData['permissible_max_weight'] > 0) {
                                                    $e_car = 0;
                                                }
                                            }

                                            if (in_array($category->tech_category, $cats_other)) {
                                                $ref_st = 0;
                                                if (isset($carData['max_power_measure'])) {
                                                    $car_volume = $carData['max_power_measure'];
                                                }
                                            }
                                        }
                                        $ref_car_cat = $category;
                                    }
                                }

                                $car_value = $this->carService->getCarPriceValue(
                                    $car_volume,
                                    $vehicle_type,
                                    $ref_car_cat
                                );

                                $pay = $this->carService->calculationPaySum(
                                    $calculate_method,
                                    date('d.m.Y', $car_date),
                                    $car_volume,
                                    $car_value,
                                    $ref_st,
                                    $e_car,
                                    $is_temporary_importation,
                                    $kap_log_id,
                                    $car_country_import,
                                    time(),
                                    $profile->tr->approve
                                );

                                $sum = $pay['sum'];

                                $data = [
                                    'profile_id' => $profile->id,
                                    'ref_car_type_id' => $car_type,
                                    'volume' => $car_volume,
                                    'vin' => $vin,
                                    'year' => $year,
                                    'date_import' => $car_date,
                                    'ref_car_cat' => $car_cat,
                                    'ref_country' => $car_country,
                                    'ref_country_import' => $car_country_import,
                                    'vehicle_type' => $vehicle_type,
                                    'ref_st_type' => $ref_st,
                                    'electric_car' => $e_car,
                                    'cost' => $sum,
                                    'calculate_method' => $calculate_method,
                                    'created' => time(),
                                ];

                                try {
                                    $this->carService->upload($data);
                                    $this->carService->setTransactionSum($profile->id);
                                    $successfully_added++;
                                } catch (AppException $e) {
                                    $this->flash->error($e->getMessage());
                                }

                            } else {
                                continue;
                            }
                        } else {
                            $this->flash->error("Автомобиль с VIN-кодом " . $val[2] . " не может быть импортирован в систему, т.к. его VIN не соответствует формату или содержит кириллические символы.");
                            continue;
                        }
                    }
                }
            }
            if ($successfully_added > 0) {
                $this->logAction("Импорт ТС: $successfully_added");
                $this->flash->success("Успешно добавлено $successfully_added ТС");
            }
        }

        $exist_vin_list = implode(", ", $existVin);
        $existVinCount = count($existVin);

        if ($existVinCount > 0) {
            $htmlExistsVin = <<<TEXT
        Невозможно сохранить $existVinCount машин, указанные VIN номера или идентификатор зарегистрированы в нашей базе.
        <small id="car_date" class="form-text text-muted">
        Посмотреть список VIN кодов
          <icon data-feather="help-circle" type="button" data-toggle="collapse" data-target="#existsVinWhenCarUpload" aria-expanded="false"
          aria-controls="existsVinWhenCarUpload" color="green" width="18" height="18"></icon>
        </small>
        <div class="collapse" id="existsVinWhenCarUpload">
          <div class="card card-body">
            <div class="alert alert-danger" role="alert">
              <p style="text-align: justify;">$exist_vin_list</p>
            </div>
          </div>
        </div>
      TEXT;

            $this->flash->warning($htmlExistsVin);
        }

        if ($auth->isSuperModerator() || $auth->isAdminSoft()) {
            return $this->response->redirect("/create_order/view/$order");
        }

        return $this->response->redirect("/order/view/$order");
    }

    public function correctionAction($cid = 0)
    {
        $auth = User::getUserBySession();

        if ($cid == 0) {
            return $this->response->redirect("/order/index");
        }
        $car = Car::findFirstById($cid);
        $profile = Profile::findFirstById($car->profile_id);
        $tr = Transaction::findFirstByProfileId($profile->id);
        $is_vendor = false;
        $fundCar = FundCar::findFirstByVin($car->vin);
        if ($fundCar) {
            $fund = FundProfile::findFirst($fundCar->fund_id);

            if (in_array($fund->approve, ['FUND_DONE'])) {
                $message = "Корректирование сертификата о внесении утилизационного платежа невозможно, так как по данному транспортному средству/самоходной сельскохозяйственной технике произведено стимулирование";
                $this->logAction($message);
                $this->flash->error($message);
                return $this->response->redirect("/order/view/" . $profile->id);
            }
        }

        if ($this->request->isPost()) {

            $_before = json_encode(array($car));

            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            $__settings = $this->session->get("__settings");

            if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                if ($result['success'] === true) {

                    $car_date_import = $this->request->getPost("car_date");
                    $car_year = $this->request->getPost("car_year");
                    $car_country = $this->request->getPost("car_country");
                    $car_country_import = $this->request->getPost("car_country_import");

                    $car_volume = str_replace(',', '.', $this->request->getPost("car_volume"));

                    $ref_st = $this->request->getPost("ref_st");
                    $car_cat = $this->request->getPost("car_cat");

                    $car_vin = $this->request->getPost("car_vin");
                    $car_comment = $this->request->getPost("car_comment");

                    $car_id_code = mb_strtoupper((string)$this->request->getPost("car_id_code"));
                    $car_body_code = mb_strtoupper((string)$this->request->getPost("car_body_code"));
                    $e_car = $this->request->getPost("e_car");
                    $calculate_method = 1;

                    if ($car_comment == '' || strlen($car_comment) < 4) {
                        $this->flash->error("Поле «Комментарий» обязательно для заполнения.");
                        return $this->response->redirect("/car/correction/" . $cid);
                    }

                    if (!$car_vin) {
                        if (!$car_id_code) $car_id_code = 'I';
                        if (!$car_body_code) $car_body_code = 'B';
                    }

                    if ($car_vin) {
                        $car_vin = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_vin);
                        $car_vin = preg_replace('/(\W)/', '', $car_vin);
                        $vin_length = mb_strlen($car_vin);

                        if ($vin_length != 17) {
                            $message = $this->translator->_('vin_car_validate');
                            $this->flash->error($message);
                        }
                    } else {
                        $car_id_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_id_code);
                        $car_body_code = str_replace(array('А', 'В', 'Е', 'М', 'Н', 'К', 'Р', 'С', 'Т', 'Х', 'О'), array('A', 'B', 'E', 'M', 'H', 'K', 'P', 'C', 'T', 'X', 'O'), $car_body_code);
                        $car_vin = preg_replace('/(\W)/', '', $car_id_code) . '&' . $car_body_code;
                        $vin_length = mb_strlen($car_vin);

                        if ($vin_length <= 3) {
                            $message = "Вы не ввели обязательный идентификатор или номер кузова. Обязательно проверьте введенные вами данные, возможно, вы пытаетесь ввести кириллические символы, которые запрещены к использованию.";
                            $this->flash->error($message);
                        }
                    }

                    if (ClientCorrectionProfile::checkCurrentCorrection($car->id, 'CAR')) {
                        $message = "ТС с VIN $car->vin уже существует в базе корректировок";
                        $this->logAction($message);
                        $this->flash->error($message);
                        return $this->response->redirect("/order/");
                    }

                    if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
                        $this->logAction('Нет доступа!', 'security', 'ALERT');
                        return $this->response->redirect("/order/");
                    }

                    $check_vin = Car::findFirstByVin($car_vin);
                    if ($car->vin != $car_vin && $check_vin) {
                        $message = "VIN $car_vin уже был представлен в заявке №" . $check_vin->profile_id . ".";
                        $this->logAction($message);
                        $this->flash->error($message);
                        return $this->response->redirect("/order/");
                    }

                    if ($auth->id != $profile->user_id) {
                        $message = "Вы не имеете права редактировать этот объект.";
                        $this->logAction($message, 'security', 'ALERT');
                        $this->flash->error($message);
                        return $this->response->redirect("/order/");
                    }

                    $car_cats = RefCarCat::findFirstById($car_cat);
                    $car_type = $car_cats->car_type;

                    if ($car_type != 6) {
                        if ($e_car && $car_type != 2 && $car_volume > 0) {
                            $message = "Объем электромобиля(легковой или автобус) должен быть 0";
                            $this->logAction($message);
                            $this->flash->notice($message);
                            return $this->response->redirect("/order/view/$profile->id");
                        }
                    }

                    if ($car_volume > 50000) {
                        $message = "Согласно Методики расчета утилизационного платежа транспортные средства с объемом двигателя более 50 тонн не подлежат к уплате утилизационного платежа.";
                        $this->logAction($message);
                        $this->flash->error($message);
                        $this->response->redirect("/order/view/$profile->id");
                    }

                    if ($ref_st == 0) {
                        $value = RefCarValue::findFirst(array(
                            "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                            "bind" => array(
                                "car_type" => $car_type,
                                "volume_start" => $car_volume,
                                "volume_end" => $car_volume
                            )
                        ));

                    } else {
                        if ($car_type == 2 && $car_cat >= 3 && $car_cat <= 8) {
                            $value = RefCarValue::findFirst(array(
                                "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                "bind" => array(
                                    "car_type" => $car_type,
                                    "volume_start" => $car_volume,
                                    "volume_end" => $car_volume
                                )
                            ));
                        } else {
                            $value = false;
                            $_cat_name = $this->translator->_($car_cats->name);

                            $message = "Внимание! $_cat_name не может быть Седельный тягач !";
                            $this->logAction($message);
                            $this->flash->notice($message);
                            return $this->response->redirect("/order/view/$profile->id");
                        }
                    }

                    // NOTE: Расчет платежа (правка машины)
                    if ($value != false) {

                        if ($car->calculate_method == 2 || $car->calculate_method == 0) {
                            $sum = __calculateCarByDate($car_date_import, $car_volume, json_encode($value), $ref_st, $e_car, $car->ref_country_import, false);
                            $calculate_method = 2;
                        } else {
                            $sum = __calculateCarByDate(date('d.m.Y', $tr->md_dt_sent), $car_volume, json_encode($value), $ref_st, $e_car, $car->ref_country_import, false);
                        }

                        if ($car_type <= 3) {
                            if ($vin_length == 17) $next = true;
                        } else {
                            if ($vin_length >= 7) $next = true;
                        }

                        // если с длиной VIN все в порядке
                        if ($next) {
                            // add to correction_client_profile
                            $cc_p = new ClientCorrectionProfile();
                            $cc_p->created = time();
                            $cc_p->user_id = $auth->id;
                            $cc_p->profile_id = $profile->id;
                            $cc_p->object_id = $car->id;
                            $cc_p->type = 'CAR';
                            $cc_p->status = "SEND_TO_MODERATOR";
                            $cc_p->action = "CORRECTION";

                            if ($cc_p->save()) {

                                $c = new ClientCorrectionCars();
                                $c->volume = $car_volume;
                                $c->car_id = $car->id;
                                $c->vin = $car_vin;
                                $c->year = $car_year;
                                $c->date_import = strtotime($car_date_import);
                                $c->profile_id = $profile->id;
                                $c->ccp_id = $cc_p->id;
                                $c->ref_car_cat = $car_cat;
                                $c->ref_car_type_id = $car_type;
                                $c->ref_country = $car_country;
                                $c->ref_country_import = $car_country_import;
                                $c->ref_st_type = $ref_st;
                                $c->calculate_method = $calculate_method;
                                $c->electric_car = $e_car ? $e_car : 0;
                                $c->cost = $sum;
                                $c->vehicle_type = $car->vehicle_type;
                                $c->save();

                                if ($this->request->hasFiles()) {
                                    foreach ($this->request->getUploadedFiles() as $file) {
                                        if ($file->getSize() > 0) {
                                            $careditfilename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                            $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                                            $file->moveTo(APP_PATH . "/private/client_correction_docs/" . $careditfilename);


                                            // добавляем файл
                                            $f = new ClientCorrectionFile();
                                            $f->profile_id = $cc_p->profile_id;
                                            $f->original_name = $careditfilename;
                                            $f->ext = $ext;
                                            $f->ccp_id = $cc_p->id;
                                            $f->visible = 1;
                                            $f->user_id = $auth->id;

                                            if ($file->getKey() === 'car_file') {
                                                $f->type = 'other';
                                            } elseif ($file->getKey() === 'car_pay_file') {
                                                $f->type = 'pay_correction';
                                            }
                                            $f->save();

                                            if ($f->save()) {
                                                copy(APP_PATH . "/private/client_correction_docs/" . $careditfilename, APP_PATH . "/private/client_corrections/" . $careditfilename);
                                            }
                                        }
                                    }
                                }

                                // логгирование
                                $l = new ClientCorrectionLogs();
                                $l->iin = $auth->idnum;
                                $l->type = 'CAR';
                                $l->user_id = $auth->id;
                                $l->action = 'SEND_TO_MODERATOR';
                                $l->object_id = $car->id;
                                $l->ccp_id = $cc_p->id;
                                $l->dt = time();
                                $l->meta_before = $_before;
                                $l->meta_after = json_encode(array($c));
                                $l->comment = $car_comment;
                                $l->file = $careditfilename;
                                $l->sign = $sign;
                                $l->save();

                                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                                $this->logAction($logString);

                                // Генерация заявка на корректировку.
                                __genAppCorrection($cc_p->id);

                                $message = "Корректировка отправлена на согласование.";
                                $this->flash->success($message);
                                return $this->response->redirect("/correction_request/");
                            }
                        } else {
                            $message = "Невозможно отредактировать это транспортное средство.";
                            $this->logAction($message);
                            $this->flash->warning($message);
                            return $this->response->redirect("/order/");
                        }

                        $message = "Невозможно отредактировать это транспортное средство.";
                        $this->logAction($message);
                        $this->flash->warning($message);
                        return $this->response->redirect("/order/");
                    }
                } else {
                    $message = "Подпись не прошла проверку!";
                    $this->logAction($message, 'security', 'NOTICE');
                    $this->flash->error($message);
                    return $this->response->redirect("/order/");
                }
            } else {
                $message = "Вы используете несоответствующую профилю подпись.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/");
            }

        } else {
            $car = Car::findFirstById($cid);
            $profile = Profile::findFirstById($car->profile_id);

            if ($car && $profile) {

                if ($profile->agent_status == "VENDOR") $is_vendor = true;

                $tr = Transaction::findFirstByProfileId($profile->id);

                if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
                    return $this->response->redirect("/order/");
                }

                if ($auth->id != $profile->user_id) {
                    $message = "Вы не имеете права редактировать этот объект.";
                    $this->logAction($message, 'security', 'ALERT');
                    $this->flash->error($message);
                    return $this->response->redirect("/order/index/");
                }

                $signData = __signData($car->profile_id, $this);
                $car_types = RefCarType::find("id IN (1,2,3,6)");
                $m = 'CAR';
                if (in_array($car->ref_car_type_id, [4, 5])) {
                    $car_types = RefCarType::find("id IN (4,5)");
                    $m = 'TRAC';
                }

                $numberPage = (int)($numberPage ?? $this->request->getQuery('page', 'int', 1));
                $numberPage = max(1, $numberPage);

                $builder = $this->modelsManager->createBuilder()
                    ->from(['c' => ClientCorrectionLogs::class])
                    ->columns([
                        'c.id AS id',
                        'c.user_id AS user_id',
                        'c.iin AS iin',
                        'c.action AS action',
                        'c.dt AS dt',
                        'c.ccp_id AS ccp_id',
                    ])
                    ->where('c.type = :type: AND c.object_id = :cid:', [
                        'type' => 'CAR',
                        'cid' => $cid,
                    ])
                    ->orderBy('id DESC');

                $paginator = new QueryBuilderPaginator([
                    'builder' => $builder,
                    'limit' => 100,
                    'page' => $numberPage,
                ]);

                $car_cats = RefCarCat::find();
                $countries = RefCountry::find(array('id NOT IN (1, 201)'));

                $correction_data = [
                    'ref_car_cat' => $car->ref_car_cat,
                    'volume' => $car->volume,
                    'year' => $car->year,
                    'date_import' => date("d.m.Y", $car->date_import),
                    'ref_st_type' => strval($car->ref_st_type ? $car->ref_st_type : 0),
                    'electric_car' => strval($car->electric_car ? $car->electric_car : 0),
                ];

                $this->view->setVars(array(
                    "car" => $car,
                    "car_types" => $car_types,
                    "car_cats" => $car_cats,
                    "countries" => $countries,
                    "m" => $m,
                    "sign_data" => $signData,
                    "md_dt_sent" => $tr->md_dt_sent,
                    "is_vendor" => $is_vendor,
                    "vehicle_type" => $car->vehicle_type,
                    "correction_data" => base64_encode(json_encode($correction_data))
                ));

                $this->view->page = $paginator->paginate();
            } else {
                $this->flash->error("Объект не найден!");
                return $this->response->redirect("/order/");
            }
        }
    }

    public function calcCostAction($cid)
    {
        $car = Car::findFirstById($cid);
        $sum = $car->cost;
        $resp = $this->response->setContentType('application/json', 'UTF-8');

        if ($this->request->isPost()) {
            $profile = Profile::findFirstById($car->profile_id);
            $tr = Transaction::findFirstByProfileId($profile->id);
            $car_volume = str_replace(',', '.', $this->request->getPost("car_volume"));
            $ref_st = $this->request->getPost("ref_st");
            $car_cat = $this->request->getPost("car_cat");
            $e_car = $this->request->getPost("e_car");
            $car_cats = RefCarCat::findFirstById($car_cat);
            $car_type = $car_cats->car_type;
            $year = $this->request->getPost("year");

            if ($year && date('Y', strtotime($year)) < 2016) {
                return $resp->setJsonContent(['sum' => $sum, 'note' => 'range_not_found']);
            }

            $value = RefCarValue::findFirst(array(
                "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                "bind" => array(
                    "car_type" => $car_type,
                    "volume_start" => $car_volume,
                    "volume_end" => $car_volume
                )
            ));

            if ($value == null || $value == false) {
                return $resp->setJsonContent(['sum' => $sum, 'note' => 'range_not_found']);
            }

            $sum = __calculateCarByDate(date('d.m.Y', $tr->md_dt_sent), $car_volume, json_encode($value), $ref_st, $e_car);
        }

        return $resp->setJsonContent(['sum' => (float)$sum]);
    }

    public function annulmentAction($cid = 0)
    {
        $auth = User::getUserBySession();

        if ($cid == 0) {
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {

            $car = Car::findFirstById($cid);
            $_before = json_encode(array($car));
            $hash = $this->request->getPost("hash");
            $sign = $this->request->getPost("sign");
            $__settings = $this->session->get("__settings");
            $car_comment = $this->request->getPost("car_comment");

            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                if ($result['success'] === true) {

                    $profile = Profile::findFirstById($car->profile_id);

                    if (ClientCorrectionProfile::checkCurrentCorrection($car->id, 'CAR')) {
                        $message = "ТС с VIN $car->vin уже существует в базе корректировок";
                        $this->flash->error($message);
                        $this->logAction($message);
                        return $this->response->redirect("/order/");
                    }

                    if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
                        $this->logAction('Заблокированный пользователь!', 'security', 'ALERT');
                        return $this->response->redirect("/order/");
                    }

                    if ($auth->id != $profile->user_id) {
                        $message = "Вы не имеете права редактировать этот объект.";
                        $this->logAction($message, 'security', 'ALERT');
                        $this->flash->error($message);
                        return $this->response->redirect("/order/");
                    }


                    // add to correction_client_profile
                    $cc_p = new ClientCorrectionProfile();
                    $cc_p->created = time();
                    $cc_p->user_id = $auth->id;
                    $cc_p->profile_id = $profile->id;
                    $cc_p->object_id = $car->id;
                    $cc_p->type = 'CAR';
                    $cc_p->status = "SEND_TO_MODERATOR";
                    $cc_p->action = "ANNULMENT";

                    if ($cc_p->save()) {

                        if ($this->request->hasFiles()) {
                            foreach ($this->request->getUploadedFiles() as $file) {
                                if ($file->getSize() > 0) {
                                    $careditfilename = time() . "." . pathinfo($file->getName(), PATHINFO_BASENAME);
                                    $ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                                    $file->moveTo(APP_PATH . "/private/client_correction_docs/" . $careditfilename);

                                    // добавляем файл
                                    $f = new ClientCorrectionFile();
                                    $f->profile_id = $cc_p->profile_id;
                                    $f->type = 'app_annulment';
                                    $f->original_name = $careditfilename;
                                    $f->ext = $ext;
                                    $f->ccp_id = $cc_p->id;
                                    $f->visible = 1;
                                    $f->save();

                                    if ($f->save()) {
                                        copy(APP_PATH . "/private/client_correction_docs/" . $careditfilename, APP_PATH . "/private/client_corrections/" . $careditfilename);
                                    }
                                }
                            }
                        }

                        // логгирование
                        $l = new ClientCorrectionLogs();
                        $l->iin = $auth->idnum;
                        $l->type = 'CAR';
                        $l->user_id = $auth->id;
                        $l->action = 'SEND_TO_MODERATOR';
                        $l->object_id = $car->id;
                        $l->ccp_id = $cc_p->id;
                        $l->dt = time();
                        $l->meta_before = $_before;
                        $l->meta_after = "Запрос на аннулирование!";
                        $l->comment = $car_comment;
                        $l->file = $careditfilename;
                        $l->sign = $sign;
                        $l->hash = $hash;
                        $l->save();

                        $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        $this->logAction($logString);

                        // Генерация заявка на корректировку.
                        __genAppAnnulment($cc_p->id);

                        $message = "Аннулирование отправлено на согласование.";
                        $this->flash->success($message);
                        return $this->response->redirect("/correction_request/");
                    } else {
                        $message = "Невозможно отредактировать это транспортное средство.";
                        $this->logAction($message);
                        $this->flash->warning($message);
                        return $this->response->redirect("/order/");
                    }

                } else {
                    $message = "Подпись не прошла проверку!";
                    $this->logAction($message, 'security', 'ALERT');
                    $this->flash->error($message);
                    return $this->response->redirect("/order/");
                }
            } else {
                $message = "Вы используете несоответствующую профилю подпись.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                return $this->response->redirect("/order/");
            }

        } else {
            $car = Car::findFirstById($cid);
            $profile = Profile::findFirstById($car->profile_id);

            if ($car && $profile) {

                if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
                    $this->logAction('Заблокированный пользователь', 'security', 'ALERT');
                    return $this->response->redirect("/order/");
                }

                if ($auth->id != $profile->user_id) {
                    $message = "Вы не имеете права редактировать этот объект.";
                    $this->flash->error($message);
                    $this->logAction($message, 'security', 'ALERT');
                    return $this->response->redirect("/order/index/");
                }

                $signData = __signData($car->profile_id, $this);

                $car_types = RefCarType::find(array('id <= 3'));
                $m = 'CAR';
                if ($car->ref_car_type_id > 3) {
                    $car_types = RefCarType::find(array('id > 3'));
                    $m = 'TRAC';
                }

                $numberPage = (int)$this->request->getQuery('page', 'int', 1);
                if ($numberPage < 1) {
                    $numberPage = 1;
                }

                $builder = $this->modelsManager->createBuilder()
                    ->columns([
                        'id' => 'c.id',
                        'user_id' => 'c.user_id',
                        'iin' => 'c.iin',
                        'action' => 'c.action',
                        'dt' => 'c.dt',
                        'ccp_id' => 'c.ccp_id',
                    ])
                    ->from(['c' => ClientCorrectionLogs::class])
                    ->where('c.type = :type: AND c.object_id = :cid:', [
                        'type' => 'CAR',
                        'cid' => $cid,
                    ])
                    ->orderBy('c.id DESC');

                $paginator = new QueryBuilderPaginator([
                    'builder' => $builder,
                    'limit' => 100,
                    'page' => $numberPage,
                ]);

                $this->view->page = $paginator->paginate();

                $car_cats = RefCarCat::find();
                $countries = RefCountry::find(array('id NOT IN (1, 201)'));

                $this->view->setVars(array(
                    "car" => $car,
                    "car_types" => $car_types,
                    "car_cats" => $car_cats,
                    "countries" => $countries,
                    "m" => $m,
                    "sign_data" => $signData
                ));

            } else {
                $this->flash->error("Объект не найден.");
                return $this->response->redirect("/order/");
            }
        }
    }

    public function deleteAction($cid = 0): ResponseInterface
    {
        $auth = User::getUserBySession();
        $baseRoute = $this->getBaseRoute($auth);

        if ($cid == 0) {
            return $this->response->redirect("/order/index");
        }

        $car = Car::findFirstById($cid);
        if (!$car) {
            $this->flash->error("ТС не найден");
            return $this->response->redirect("/order/index");
        }

        try {
            $profileId = $car->profile_id;
            $this->carService->delete($car, $auth);
            $this->logAction("ТС удален.");
            $this->flash->success("Удаление произошло успешно.");
            return $this->response->redirect("$baseRoute/view/$profileId");
        } catch (AppException $e) {
            $this->flash->warning($e->getMessage());
            $this->logAction($e->getMessage(), 'action', 'ERROR');
            return $this->response->redirect("$baseRoute/view/" . ($car->profile_id ?? ''));
        }
    }

    public function viewAction($cid = 0)
    {
        $auth = User::getUserBySession();

        if ($cid == 0) {
            return $this->response->redirect("/order/index");
        }

        $car = Car::findFirstById($cid);

        if ($car) {
            $query = $this->modelsManager->createQuery("
              SELECT
                c.id AS c_id,
                c.volume AS c_volume,
                c.vin AS c_vin,
                c.year AS c_year,
                c.cost AS c_cost,
                c.ref_car_type_id AS c_type_id,
                c.ref_st_type AS c_ref_st,
                cc.name AS c_cat,
                c.date_import AS c_date_import,
                c.electric_car AS e_car,
                country.name AS c_country,
                t.name AS c_type,
                p.id AS c_profile,
                p.blocked AS p_blocked,
                p.user_id AS p_uid,
                tr.status AS tr_status,
                tr.id AS tr_id
              FROM Car c
              JOIN Profile p
                JOIN RefCountry country
                JOIN RefCarCat cc
                JOIN RefCarType t
                JOIN Transaction tr
              WHERE
                c.id = :cid: AND
                c.profile_id = p.id AND
                c.ref_country = country.id AND
                c.ref_car_cat = cc.id AND
                t.id = c.ref_car_type_id AND
                tr.profile_id = p.id
              GROUP BY c.id");

            $car = $query->execute(array(
                "cid" => $cid
            ));

            if (!$auth->isSuperModerator() && !$auth->isModerator() && !$auth->isAdmin() && !$auth->isAdminSoft() && !$auth->isAdminSec()) {
                if ($car[0]->p_uid != $auth->id) {
                    $this->logAction("Нет доступа.", 'security', 'ALERT');
                    return $this->response->redirect("/index/index/");
                }
            }

            $this->view->setVars(array(
                "cid" => $cid,
                "car" => $car
            ));
        } else {
            $this->flash->error("Объект не найден.");
            return $this->response->redirect("/home/index");
        }
    }


    /**
     * @throws AppException
     */
    public function getCarInfoAction($id = 0): string
    {
        $this->view->disable();
        $auth = User::getUserBySession();
        $html = NULL;

        $sql = <<<SQL
        SELECT c.profile_id as profile_id,
          c.vin as vin,
          c.year as year,          
          rcc.name as cat_name, 
          rct.name as type_name, 
          FROM_UNIXTIME(c.date_import, "%d.%m.%Y") as date_import,  
          rc.name as country_name, 
          rci.name as country_import_name, 
          c.cost as cost, 
          c.volume as volume,  
          c.ref_st_type as st_type,
          c.calculate_method as calculate_method,
          IF(c.electric_car = 1, "Да", "Нет") as e_car
        FROM Car c
            JOIN RefCountry rc  ON rc.id  = c.ref_country
            JOIN RefCountry rci ON rci.id = c.ref_country_import
            JOIN RefCarCat rcc
            JOIN RefCarType rct
        WHERE  
              c.ref_car_cat = rcc.id AND
              c.ref_car_type_id = rct.id AND
              c.id = {$id}
      SQL;

        $query = $this->modelsManager->createQuery($sql);
        $car = $query->execute();

        if ($auth && count($car) > 0) {
            $vin_code = $this->translator->_("vin-code");
            $volume_cm = $this->translator->_("volume-cm");
            $year_of_manufacture = $this->translator->_("year-of-manufacture");
            $car_category = $this->translator->_("car-category");
            $ref_st = $this->translator->_("ref-st");
            $transport_type = $this->translator->_("transport-type");
            $is_electric_car = $this->translator->_("is_electric_car?");
            $num_application = $this->translator->_("num-application");
            $car_calculate_method = $this->translator->_("car-calculate-method");
            $date_of_import = $this->translator->_("date-of-import");
            $country_of_manufacture = $this->translator->_("country-of-manufacture");
            $country_of_import = $this->translator->_("country-of-import");
            $amount = $this->translator->_("amount");

            $car_cost = __money($car[0]->cost);
            $car_calc_method = CALCULATE_METHODS[$car[0]->calculate_method];
            $st_type = REF_ST_TYPE[$car[0]->st_type];
            $category_name = $this->translator->_($car[0]->cat_name);
            $vin_view = str_replace('-', '&', $car[0]->vin);

            $this->logAction("Информация о ТС/ССХТ.", 'access');

            $html .= <<<TABLE_BODY
                  <tr><td>1</td><td>{$num_application}</td><td>{$car[0]->profile_id}</td></tr>
                  <tr><td>2</td><td>{$vin_code}</td><td>{$vin_view}</td></tr>
                  <tr><td>3</td><td>{$volume_cm}</td><td>{$car[0]->volume}</td></tr>
                  <tr><td>4</td><td>{$year_of_manufacture}</td><td>{$car[0]->year}</td></tr>
                  <tr><td>5</td><td>{$date_of_import}</td><td>{$car[0]->date_import}</td></tr>
                  <tr><td>6</td><td>{$country_of_manufacture}</td><td>{$car[0]->country_name}</td></tr>
                  <tr><td>6</td><td>{$country_of_import}</td><td>{$car[0]->country_import_name}</td></tr>
                  <tr><td>7</td><td>{$car_category}</td><td>{$category_name}</td></tr>
                  <tr><td>8</td><td>{$amount}</td><td>{$car_cost}</td></tr>
                  <tr><td>9</td><td>{$transport_type}</td><td>{$car[0]->type_name}</td></tr>
                  <tr><td>10</td><td>{$ref_st}</td><td>{$st_type}</td></tr>
                  <tr><td>11</td><td>{$is_electric_car}</td><td>{$car[0]->e_car}</td></tr>
                  <tr><td>12</td><td>{$car_calculate_method}</td><td>{$car_calc_method}</td></tr>
                TABLE_BODY;

            http_response_code(200);
            return $html;
        }
        return '';
    }

    private function checkAccess($auth, $profile_id, $profile_blocked)
    {
        if ($this->carService->isUserBlacklisted($auth)) {
            $this->logAction("Нет доступа", 'security', 'ALERT');
            $this->flash->error("Доступ запрещен. Вы не имеете права выполнять это действие.");
            return $this->response->redirect("/order/view/" . $profile_id);
        }

        if ($this->carService->isProfileBlockedForUser($auth, $profile_id, $profile_blocked)) {
            $this->flash->error("Вы не имеете права выполнять это действие.");
            $this->logAction("Доступ запрещен. Заявка заблокирована", 'security', 'ALERT');
            return $this->response->redirect("/order/view/" . $profile_id);
        }

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile_id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->logAction('Уважаемый пользователь, вы не можете отредактировать или удалить ТС, вы уже подписали электронное Заявление 
                            (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                            Если вы хотите отредактировать данные ТС, Вам необходимо удалить Подписанное Заявление. 
                            После внесения изменений в  данные ТС подпишите Заявление повторно.');

            $this->flash->warning('Уважаемый пользователь, вы не можете отредактировать или удалить ТС, вы уже подписали электронное Заявление 
                            (PDF файл уже сгенерирован в секции Документы под названием "Подписанное Заявление")! 
                            Если вы хотите отредактировать данные ТС, Вам необходимо удалить Подписанное Заявление. 
                            После внесения изменений в  данные ТС подпишите Заявление повторно.');

            return $this->response->redirect("/order/view/$profile_id");
        }

        return null;
    }

    private function getBaseRoute(User $auth): string
    {
        return $auth->isSuperModerator() ? '/create_order' : '/order';
    }

    private function redirectBackOr(string $fallback): ResponseInterface
    {
        $back = $this->request->getHTTPReferer();

        // если referer пустой или ведёт на текущий URI — идём на fallback
        $currentPath = $this->router->getRewriteUri();
        $backPath = $back ? parse_url($back, PHP_URL_PATH) : null;

        if (!$back || $backPath === $currentPath) {
            $back = $fallback;
        }

        return $this->response->redirect($back);
    }

    private function canEdit($auth, $p): bool
    {
        if ($p) {
            $isSuperModeratorCase =
                $auth->isSuperModerator()
                && $p->moderator_id === $auth->id
                && $p->name === '(создано супермодератором)'
                && $p->tr->approve === 'NEUTRAL' && !$p->blocked;

            $isAdminCase = $auth->isAdminSoft();

            $isOwnerCase = !$p->blocked && $p->user_id === $auth->id;

            return $isSuperModeratorCase || $isAdminCase || $isOwnerCase;
        }

        return false;
    }
}
