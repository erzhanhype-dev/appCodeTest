<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Resources\CarRowResource;
use App\Services\Car\CarService;
use App\Services\Car\CarValidator;
use App\Services\Car\VinService;
use App\Services\Cms\CmsService;
use Car;
use CompanyDetail;
use ContactDetail;
use ControllerBase;
use File;
use Goods;
use PersonDetail;
use Phalcon\Db\Column;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorBuilder;
use Phalcon\Translate\Adapter\NativeArray;
use Profile;
use ProfileLogs;
use Reasons;
use RefBank;
use RefCarCat;
use RefCarType;
use RefCarValue;
use RefCountry;
use RefIdnSource;
use RefInitiator;
use RefKbe;
use RefTnCode;
use Sf;
use Transaction;
use User;
use UserLogs;
use UserType;

// TODO:0 Редактирование заявки
// DONE:30 Удаление пустой заявки
// DONE:80 Выбор метода оплаты изнутри заявки
// DECLINED:20 Синие кнопочки на панели управления
// DONE:40 Ссылка на редактирование автомобиля, если профиль не заблокирован
// DONE:20 Удаление автомобиля, если профиль не заблокирован

class CreateOrderController extends ControllerBase
{
    /**
     * Получение перевода по выбранному языку
     * @return NativeArray экземпляр объекта
     * @var CarService
     * @var vinService
     */

    private CarService $carService;
    private CarValidator $carValidator;
    /**
     * @var mixed
     */
    private VinService $vinService;

    public function initialize()
    {
        parent::initialize();
        $this->carService = $this->di->get(CarService::class);
        $this->carValidator = new CarValidator();
        $this->vinService = $this->di->get(VinService::class);
    }

    public function searchUserAction($idnum = null)
    {
        if (!$idnum) {
            return json_encode(array("error" => "ИИН/БИН не указан"));
        }

        $company = CompanyDetail::findFirst([
            'conditions' => 'bin = :bin:',
            'bind'       => ['bin' => $idnum],
            'order'      => 'id DESC', // или created_at DESC / updated_at DESC
        ]);

        $person = PersonDetail::findFirst([
            'conditions' => 'iin = :iin:',
            'bind'       => ['iin' => $idnum],
            'order'      => 'id DESC', // или created_at DESC / updated_at DESC
        ]);
        if ($company) {
            return json_encode(array('company' => $company, 'user_type' => 'company'));
        } elseif ($person) {
            return json_encode(array('person' => $person, 'user_type' => 'person'));
        } else {
            return json_encode(array("error" => "Пользователь не найден"));
        }
    }

    public function newUserAction()
    {
        $types = UserType::find();
        $this->view->setVar("types", $types);
    }

    /**
     * Creates a new user
     */
    public function createUserAction()
    {
        // $this->view->disable();
        $auth = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect("/create_order/new/");
        }

        if (strlen($this->request->getPost("idnum")) !== 12) {
            $this->flash->error("ИИН/БИН некорректный");
            return $this->response->redirect("/create_order/new_user");
        }

        $reg_user = User::findFirstByIdnum($this->request->getPost("idnum"));

        if ($reg_user) {
            $this->flash->success("Пользователь с таким БИН/ИИН: $reg_user->idnum уже зарегистрирован !");
            $this->logAction('"Пользователь с таким БИН/ИИН: $reg_user->idnum уже зарегистрирован !"', 'account');
            return $this->response->redirect("/create_order/user_settings/$reg_user->id");
        } else {
            $user = new User();

            $user->login = 'gost';
            $user->idnum = $this->request->getPost("idnum");
            $user->password = password_hash(getenv('NEW_SALT') . $this->request->getPost("idnum"), PASSWORD_DEFAULT);
            $user->active = 0;
            $user->view_mode = 1;
            $user->lang = "ru";
            $user->user_type_id = $this->request->getPost("user_type_id");
            $user->lastip = $this->request->getClientAddress();
            $user->last_login = time();
            $user->role_id = 9;

            if ($user->save()) {
                $ul = new UserLogs();
                $ul->user_id = $auth->id;
                $ul->action = 'CREATE';
                $ul->affected_user_id = $user->id;
                $ul->dt = time();
                $ul->info = json_encode(array($user));
                $ul->ip = $this->request->getClientAddress();
                $ul->save();

                $this->flash->success("Пользователь создан успешно.");
                $logString = json_encode($ul->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString,'account');

                return $this->response->redirect("/create_order/user_settings/$user->id");
            } else {
                foreach ($user->getMessages() as $message) {
                    $this->flash->error($message);
                    $this->logAction($message);

                }

                return $this->response->redirect("/create_order/new_user/");
            }
        }
    }

    public function userSettingsAction($id)
    {
        $user = User::findFirstById($id);

        if ($user) {
            $user_type = UserType::findFirstById($user->user_type_id);
            $this->flash->notice("Уважаемые пользователи! В разделе настроек у каждой секции есть своя кнопка «Сохранить». Пожалуйста, будьте внимательнее при заполнении своих данных.");

            // Инициализируем переменные
            $dt = null;
            $con_ref_reg_country = KZ_CODE;
            $con_ref_country = KZ_CODE;

            // если пользователь юридическое лицо
            if ($user->user_type_id == COMPANY) {
                $dt = CompanyDetail::findFirst([
                    "user_id = :id:",
                    "bind" => ["id" => $user->id]
                ]);

                if ($dt == false) {
                    $dt = new CompanyDetail();
                    $dt->user_id = $user->id;
                    $dt->bin = $user->idnum;
                    $dt->save();
                }
            } elseif ($user->user_type_id == PERSON) {
                $dt = PersonDetail::findFirst([
                    "user_id = :id:",
                    "bind" => ["id" => $user->id]
                ]);

                if ($dt == false) {
                    $dt = new PersonDetail();
                    $dt->user_id = $user->id;
                    $dt->iin = $user->idnum;
                    $dt->save();
                }
            }

            // адресная информация
            $ct = ContactDetail::findFirst([
                "user_id = :id:",
                "bind" => ["id" => $user->id]
            ]);

            if ($ct != false) {
                $con_ref_reg_country = $ct->ref_reg_country_id;
                $con_ref_country = $ct->ref_country_id;
            } else {
                $ct = new ContactDetail();
                $ct->user_id = $user->id;
                $ct->ref_country_id = KZ_CODE;
                $ct->ref_reg_country_id = KZ_CODE;
                $ct->save();
            }

            $idn_sources = RefIdnSource::find();
            $ref_kbe = RefKbe::find();
            $ref_bank = RefBank::find();
            $ref_country = RefCountry::find(['id NOT IN (1)']);

            $user->save();

            // Генерация УНИКАЛЬНЫХ CSRF токенов для каждой формы
            $csrfProfile = $this->security->getToken(); // Для формы профиля
            $csrfDetails = $this->security->getToken(); // Для формы деталей
            $csrfContacts = $this->security->getToken(); // Для формы контактов
            $csrfAccountant = $this->security->getToken(); // Для формы бухгалтера

            // Получаем ключ токена (он одинаковый для всех)
            $csrfTokenKey = $this->security->getTokenKey();

            $this->view->setVars([
                "user" => $user,
                "user_type" => $user_type,
                "idn_sources" => $idn_sources,
                "ref_kbe" => $ref_kbe,
                "ref_bank" => $ref_bank,
                "ref_country" => $ref_country,
                "details" => $dt,
                "contacts" => $ct,
                "iin_buh" => $user->accountant,

                // Передаем РАЗНЫЕ токены для каждой формы
                "csrfProfile" => $csrfProfile,
                "csrfDetails" => $csrfDetails,
                "csrfContacts" => $csrfContacts,
                "csrfAccountant" => $csrfAccountant,
                "csrfTokenKey" => $csrfTokenKey,

                // Передаем значения для полей формы
                "con_ref_reg_country" => $con_ref_reg_country,
                "con_ref_country" => $con_ref_country,
                // Передаем флаги для проверки в шаблоне
                "is_company" => ($user->user_type_id == COMPANY),
                "is_person" => ($user->user_type_id == PERSON)
            ]);
        } else {
            $this->flash->error("Пользователь не найден!");
            return $this->response->redirect("/create_order/new_user");
        }
    }

    public function userProfileAction()
    {
        if ($this->request->isPost()) {
            $password = $this->request->getPost("set_password");
            $password_again = $this->request->getPost("set_password_again");
            $lang = $this->request->getPost("set_lang");
            $user_id = $this->request->getPost("user_id");
            $user = User::findFirstById($user_id);
            $auth = User::getUserBySession();

            if ($password != "" && $password == $password_again) {
                $user->password = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);
            }
            $user->last_login = time();
            $user->lastip = $this->request->getClientAddress();
            $user->lang = $lang;

            if ($user->update()) {
                $this->logAction("Данные изменены: ".$user->id, 'account');
                $this->flash->success("Данные сохранены!");
                return $this->response->redirect("/create_order/user_settings/$user_id");
            }
        }
    }

    public function userDetailsAction()
    {
        if ($this->request->isPost()) {
            $user_id = $this->request->getPost("uid");
            $user = User::findFirstById($user_id);

            if ($user->user_type_id == PERSON) {
                $dt = PersonDetail::findFirst(array(
                    "user_id = :id:",
                    "bind" => array(
                        "id" => $user->id
                    )
                ));

                if ($dt) {
                    // теперь собираем данные
                    $last_name = $this->request->getPost("det_last_name");
                    $first_name = $this->request->getPost("det_first_name");
                    $parent_name = $this->request->getPost("det_parent_name");
                    $iin = $this->request->getPost("det_iin");
                    $birthdate = $this->request->getPost("det_birthdate");

                    // сохраняем
                    if ($dt->last_name == '') {
                        $dt->last_name = $last_name;
                    }
                    if ($dt->first_name == '') {
                        $dt->first_name = $first_name;
                    }
                    if ($dt->parent_name == '') {
                        $dt->parent_name = $parent_name;
                    }

                    $user->fio = "$last_name $first_name $parent_name";
                    $user->save();

                    $dt->iin = $user->idnum;
                    $dt->birthdate = strtotime($birthdate);

                    if ($dt->update() != false) {
                        $this->flash->success("Данные сохранены!");
                        $this->logAction("Данные изменены: ".$user->id, 'account');
                        return $this->response->redirect("/create_order/user_settings/$user_id");
                    }
                }
            }

            if ($user->user_type_id == COMPANY) {
                $dt = CompanyDetail::findFirst(array(
                    "user_id = :id:",
                    "bind" => array(
                        "id" => $user->id
                    )
                ));

                if ($dt) {
                    // теперь собираем данные
                    $name = htmlspecialchars_decode(
                        $this->request->getPost('det_name'),
                        ENT_QUOTES
                    );
                    $reg_date = $this->request->getPost("det_reg_date");
                    $iban = $this->request->getPost("det_iban");
                    $ref_bank = $this->request->getPost("det_ref_bank");
                    $ref_kbe = $this->request->getPost("det_ref_kbe");
                    $oked = $this->request->getPost("det_oked");
                    $reg_num = $this->request->getPost("det_reg_num");

                    // сохраняем
                    $dt->name = $name;
                    $dt->bin = $user->idnum;
                    $dt->reg_date = strtotime($reg_date);
                    $dt->iban = $iban;
                    $dt->ref_bank_id = $ref_bank ? (int)$ref_bank : null;
                    $dt->ref_kbe_id = $ref_kbe ? (int)$ref_kbe : null;
                    $dt->oked = $oked;
                    $dt->reg_num = $reg_num;

                    $user->org_name = $name;
                    $user->save();

                    $b_region = $this->request->getPost("b_region");
                    $b_size = $this->request->getPost("b_size");

                    $dt->b_region = $b_region;
                    $dt->b_size = $b_size;

                    if ($dt->update() != false) {
                        $this->flash->success("Данные сохранены!");
                        $this->logAction("Данные изменены: ".$user->id, 'account');
                        return $this->response->redirect("/create_order/user_settings/$user_id");
                    }
                }
            }
        }
    }

    public function userContactsAction()
    {
        if ($this->request->isPost()) {
            $user_id = $this->request->getPost("user_id");
            $user = User::findFirstById($user_id);

            $dt = ContactDetail::findFirst(array(
                "user_id = :id:",
                "bind" => array(
                    "id" => $user->id
                )
            ));

            if ($dt != false) {
                // теперь собираем данные
                $ref_reg_country = $this->request->getPost("con_ref_reg_country");
                $reg_city = $this->request->getPost("con_reg_city");
                $reg_address = $this->request->getPost("con_reg_address");
                $reg_zipcode = $this->request->getPost("con_reg_zipcode");
                $ref_country = $this->request->getPost("con_ref_country");
                $city = $this->request->getPost("con_city");
                $address = $this->request->getPost("con_address");
                $zipcode = $this->request->getPost("con_zipcode");
                $phone = $this->request->getPost("con_phone");
                $mobile_phone = $this->request->getPost("con_mobile_phone");

                // сохраняем
                $dt->ref_reg_country_id = $ref_reg_country;
                $dt->reg_city = $reg_city;
                $dt->reg_address = $reg_address;
                $dt->reg_zipcode = $reg_zipcode;
                $dt->ref_country_id = $ref_country;
                $dt->city = $city;
                $dt->address = $address;
                $dt->zipcode = $zipcode;
                $dt->phone = ltrim($phone, '+');
                $dt->mobile_phone = ltrim($mobile_phone, '+');

                if ($dt->update() != false) {
                    $this->flash->success("Данные сохранены!");
                    $this->logAction("Данные изменены: ".$user->id, 'account');
                    return $this->response->redirect("/create_order/user_settings/$user_id");
                }
            }
        }
    }

    public function reviewAction($pid)
    {
        $can = true;
        $tr = Transaction::findFirstByProfileId($pid);
        $p = Profile::findFirstById($pid);
        $auth = User::getUserBySession();

        if ($p->created < MD_DT_SENT_2022) {
            $can = false;
            $this->flash->warning("В целях осуществления расчета утилизационного платежа, согласно Методике расчета 
                                 утилизационного платежа действующего с 14 мая 2022 года, рекомендуется создать 
                                 новую заявку (с удалением объектов из данной заявки)");
            return $this->response->redirect("/create_order/index/");
        }

        $electric_cars = 0;
        $all_cars = 0;

        if ($tr->amount == 0) {
            // ищем электр_машин
            if ($p->type == 'CAR') {
                $cars = Car::findByProfileId($p->id);

                foreach ($cars as $car) {
                    $all_cars++;
                    if ($car->volume == 0 && $car->cost == 0) {
                        $electric_cars++;
                    }
                }
                if ($electric_cars != $all_cars) {
                    $can = false;
                }
            } else {
                $can = false;
                $this->flash->success("Документы о полноте платы не выдаются с нулевой ставкой. Будьте внимательны при заполнении.");
                return $this->response->redirect("/create_order/index/");
            }
        }

        if ($tr->approve == 'REVIEW') {
            $can = false;
            $this->flash->warning("Заявка # $pid  уже отправлена на рассмотрении.");
            return $this->response->redirect("/create_order/index/");
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
            } else {
                if ($p->type == 'CAR') {
                    __auto_car_approve($pid);
                }
            }
        }

        return $this->response->redirect("/create_order/view/$pid");
    }

    /**
     * Список всех заявок пользователя.
     * @return void
     */
    public function indexAction(): void
    {
        $auth = User::getUserBySession();
        $page = $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'p.id           AS p_id',
                'p.type         AS p_type',
                'p.created      AS p_created',
                'p.blocked      AS p_blocked',
                'p.moderator_id AS p_moderator_id',
                'tr.id          AS c_tr',
                'tr.status      AS tr_status',
                'tr.amount      AS tr_amount',
                'tr.approve     AS tr_approve',
                'tr.md_dt_sent  AS dt_sent',
            ])
            ->from(['p' => Profile::class])
            ->join(Transaction::class, 'tr.profile_id = p.id', 'tr')
            ->where('p.moderator_id = :uid:', ['uid' => (int)$auth->id])
            ->orderBy('p.id DESC');

        $paginator = new PaginatorBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => $page,
        ]);

        $this->view->page = $paginator->paginate();
    }

    public function signAction()
    {
        $__settings = $this->session->get("__settings");

        $pid = $this->request->getPost("orderId");
        $hash = $this->request->getPost("profileHash");
        $sign = $this->request->getPost("profileSign");

        $p = Profile::findFirstById($pid);
        if ($p) {
            $cmsService = new CmsService();
            $result = $cmsService->check($hash, $sign);
            $j = $result['data'];
            $sign = $j['sign'];
            if ($__settings['iin'] == $j['iin'] && $__settings['bin'] == $j['bin']) {
                if ($result['success'] === true) {
                    if ($p->type == 'CAR') {
                        if (__checkSignedIntTranApp($p->id) != true) {
                            $this->flash->error("Внимание! У вас отсутствует Заявление об обязательном использовании ввозимых 
                      седельных тягачей для международных перевозок.");

                            return $this->response->redirect("/create_order/view/$pid");
                        }

                        __carRecalc($p->id);
                    } elseif ($p->type == 'GOODS') {
                        __goodRecalc($p->id);
                    }

                    $p->sign = $sign;
                    $p->sign_date = time();
                    $p->save();

                    __genApplication($pid, $this, $j);

                    $this->flash->success("Заявление сформировано и подписано.");
                } else {
                    $this->flash->error("Подпись не прошла проверку!");
                }
            } else {
                $this->flash->error("Вы используете несоответствующую профилю подпись.");
            }
        }

        return $this->response->redirect("/create_order/view/$pid");
    }

    /**
     * Загрузка необходимых документов.
     * @return void
     */
    public function docAction()
    {
        $line_num = $this->request->getPost("line_num");
        if ($line_num == '') {
            $line_num = 0;
        }

        $order = $this->request->getPost("order_id");
        $doc_type = $this->request->getPost("doc_type");
        $auth = User::getUserBySession();

        $profile = Profile::findFirstById($order);

        if ($profile->user_id == $auth->id || $auth->isAdminSoft() || $auth->isAdminSec() || $auth->isAdmin() || $auth->isModerator() || $auth->isSuperModerator()) {
            if ($this->request->hasFiles() && $doc_type != '') {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getSize() > 0) {
                        $nf = new File();
                        $nf->profile_id = $profile->id;
                        $nf->type = $doc_type;
                        $nf->good_id = $line_num;
                        $nf->original_name = $file->getName();
                        $nf->ext = pathinfo($file->getName(), PATHINFO_EXTENSION);
                        $nf->save();
                        $file->moveTo(APP_PATH . "/private/docs/" . $nf->id . "." . pathinfo($file->getName(), PATHINFO_EXTENSION));
                        $this->flash->success("Файл добавлен.");
                    }
                }
            } else {
                $this->flash->warning("Укажите тип документа.");
            }
        } else {
            $this->flash->warning("У вас нет прав на это действие.");
        }

        if ($profile->type == 'R20') {
            $this->response->redirect("/create_order/view/$order");
        }

        $this->response->redirect("/create_order/view/$order");
    }

    /**
     * Скачать документ.
     * @param int $id
     * @return void
     */
    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || ($auth->isAdminSoft() || $auth->isAdminSec() || $auth->isAdmin() || $auth->isAccountant() || $auth->isModerator() || $auth->isSuperModerator())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name);
            }
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
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $profile = Profile::findFirstById($pf->profile_id);

        if ($profile->user_id == $auth->id || ($auth->isAdminSoft() || $auth->isAdminSec() || $auth->isAdmin() || $auth->isAccountant() || $auth->isModerator() || $auth->isSuperModerator())) {
            if (file_exists($path . $pf->id . '.' . $pf->ext)) {
                __downloadFile($path . $pf->id . '.' . $pf->ext, $pf->original_name, 'view');
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
        $path = APP_PATH . "/private/docs/";
        $auth = User::getUserBySession();

        $pf = File::findFirstById($id);
        $p = Profile::findFirstById($pf->profile_id);

        if ($auth->isSuperModerator() || (!$p->blocked && $p->user_id == $auth->id)) {
            $pf->visible = 0;
            $pf->save();
            $this->response->redirect("/create_order/view/$p->id");
        } else {
            $this->flash->error("Вы не можете удалить этот файл.");
            $this->response->redirect("/moderator_main/");
        }
    }

    /**
     * Восстановление документа к заявке.
     * @param int $id
     * @return void
     */
    public function restoreAction($id)
    {
        $pf = File::findFirstById($id);
        $pf->visible = 1;
        $pf->save();

        $this->response->redirect("/create_order/view/$pf->profile_id");
    }

    /**
     * Просмотр заявки.
     * @param integer $pid
     * @return void
     */
    public function viewAction($pid)
    {
        $auth = User::getUserBySession();

        // только наши машины
        $profile = Profile::findFirstById($pid);

        if ($auth->id != $profile->moderator_id) {
            $message = "У вас нет прав на это действие.";
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/create_order/");
        }

        if ($profile->hash != null) {
            $signData = $profile->hash;
        } else {
            $signData = __signData($pid, $this);
        }

        $tr = Transaction::findFirstByProfileId($pid);

        $sf = Sf::findFirstByProfileId($pid);

        $files = File::findByProfileId($profile->id);

        $cars = $this->carService->itemsBySuperModeratorProfile((int)$profile->id);

        if (file_exists(APP_PATH . '/storage/temp/msg_' . $pid . '.txt') && $tr->approve == 'DECLINED') {
            $msg_modal = file_get_contents(APP_PATH . '/storage/temp/msg_' . $pid . '.txt');
            $this->flash->error('<strong>Сообщение модератора:</strong> ' . $msg_modal);
        }

        $app_form = false;
        $app_form_query = File::find(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1",
            "bind" => array(
                "pid" => $profile->id
            )
        ));

        if (count($app_form_query) > 0) {
            $app_form = true;
        }

        $this->logAction('Просмотр заявки', 'access');

        $this->view->setVars(array(
            "pid" => $pid,
            "profile" => $profile,
            "tr" => $tr,
            "files" => $files,
            "cars" => !empty($cars) ? CarRowResource::collection($cars) : [],
            "app_form" => $app_form,
            "sign_data" => $signData,
            "sf" => $sf
        ));
    }

    /**
     * Правка заявки.
     * @param int $pid
     * @return void
     */
    public function editAction($pid)
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $profile = Profile::findFirstById($pid);

            $comment = $this->request->getPost('comment');
            $order_type = $this->request->getPost('order_type');

            if ($profile->type != $order_type) {
                $editable = __checkProfileIsEditable($profile->id);

                if ($editable == false) {
                    $this->flash->error("Вы не можете отредактировать тип заявки, так как добавили обьект (Товар ТС КПП) в заявку.");
                    return $this->response->redirect("/create_order/index");
                }
            }

            $agent_status = $this->request->getPost('agent_status');
            $agent_name = $this->request->getPost('agent_name');
            $agent_iin = $this->request->getPost('agent_iin');
            $agent_sign = $this->request->getPost('agent_sign');
            $agent_address = $this->request->getPost('agent_address');
            $agent_city = $this->request->getPost('agent_city');
            $agent_bank = $this->request->getPost('agent_bank');
            $agent_phone = $this->request->getPost('agent_phone');
            $agent_iban = $this->request->getPost('agent_iban');
            $agent_type = $this->request->getPost('agent_type');
            $initiator_id = $this->request->getPost('initiator_id');

            $profile->name = htmlspecialchars($comment);
            $profile->agent_status = $agent_status;
            $profile->type = $order_type;
            $profile->reason_id = $this->request->getPost('reason_id');
            $profile->agent_name = $agent_name ? htmlspecialchars($agent_name) : null;
            $profile->agent_iin = $agent_iin ? htmlspecialchars($agent_iin) : null;
            $profile->agent_sign = $agent_sign ? htmlspecialchars($agent_sign) : null;
            $profile->agent_address = $agent_address ? htmlspecialchars($agent_address) : null;
            $profile->agent_city = $agent_city ? htmlspecialchars($agent_city) : null;
            $profile->agent_phone = $agent_phone ? htmlspecialchars($agent_phone) : null;
            $profile->agent_bank = $agent_bank ? (int)htmlspecialchars($agent_bank) : null;
            $profile->agent_iban = $agent_iban ? htmlspecialchars($agent_iban) : null;
            $profile->agent_type = $agent_type ? htmlspecialchars($agent_type) : null;
            $profile->initiator_id = $initiator_id ? intval($initiator_id) : null;

            if ($profile->save()) {
                $this->flash->success("Изменения сохранены.");
                $this->response->redirect("/create_order/index/");
            } else {
                $this->flash->error("Нет возможности сохранить ваши изменения.");
                $this->response->redirect("/create_order/index/");
            }
        } else {
            $profile = Profile::findFirstById($pid);
            $tr = Transaction::findFirstByProfileId($profile->id);
            $banks = RefBank::find();
            if ($auth->isAdmin()) {
                $initiators = RefInitiator::find([
                    'conditions' => 'id NOT IN ({ids:array})',
                    'bind' => [
                        'ids' => [1]
                    ]
                ]);
            } else {
                $initiators = RefInitiator::find([
                    'conditions' => 'id NOT IN ({ids:array})',
                    'bind' => [
                        'ids' => [1, 4]
                    ]
                ]);
            }

            if (!($auth->isSuperModerator() || $auth->isAdminSoft())) {
                $message = "Вы не имеете права редактировать этот объект.";
                $this->logAction($message, 'security', 'ALERT');
                $this->flash->error($message);
                $this->response->redirect("/create_order/index/");
            }

            $this->view->setVars(array(
                "profile" => $profile,
                "banks" => $banks,
                "initiators" => $initiators,
                "auth" => $auth,
                "tr" => $tr
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
        $reasons = Reasons::find();
        if ($auth->isAdmin()) {
            $initiators = RefInitiator::find([
                'conditions' => 'id NOT IN ({ids:array})',
                'bind' => [
                    'ids' => [1]
                ]
            ]);
        } else {
            $initiators = RefInitiator::find([
                'conditions' => 'id NOT IN ({ids:array})',
                'bind' => [
                    'ids' => [1, 4]
                ]
            ]);
        }

        $client_idnum = $this->request->get('idnum');
        $client_uid = $this->request->get('user_id');
        $client_title = $this->request->get('title');

        $this->view->setVars(array(
            "client_idnum" => $client_idnum,
            "client_uid" => $client_uid,
            "client_title" => $client_title,
            "reasons" => $reasons,
            "initiators" => $initiators
        ));
    }

    public function checkEptsAction(int $pid = 0)
    {
        $translate = $this->translator;

        if ($pid == 0) {
            return $this->response->redirect("/create_order/index");
        }

        $profile = Profile::findFirstById($pid);
        $is_vendor = false;

        if ($this->carValidator->hasReachedCarLimit($profile->id)) {
            $this->flash->warning($translate->_('max_car_add_profile_validate'));
            return $this->response->redirect("/create_order/view/$profile->id");
        }

        $files = File::count(array(
            "type = 'application' AND profile_id = :pid: AND visible = 1 AND type = :type:",
            "bind" => array(
                "pid" => $profile->id,
                "type" => "application"
            )
        ));

        if ($files > 0) {
            $this->flash->warning($translate->_('edit_car_locked'));
            return $this->response->redirect("/create_order/view/$profile->id");
        }

        $car_types = RefCarType::find("id IN (1,2,3,6)");
        $m = 'CAR';

        if ($_GET['m'] == 'TRAC') {
            $car_types = RefCarType::find("id IN (4,5)");
            $m = 'TRAC';
        }

        $this->session->set('CAR_TYPE', $m);

        $cats = RefCarCat::find();
        $countries = RefCountry::find(array('id NOT IN (1)'));

        $check_agro = Car::findFirst(array(
            "ref_car_cat IN (13, 14) AND profile_id = :profile_id:",
            "bind" => array(
                "profile_id" => $pid
            )
        ));

        $check_car = Car::findFirst(array(
            "ref_car_cat NOT IN (13, 14) AND profile_id = :profile_id:",
            "bind" => array(
                "profile_id" => $pid
            )
        ));

        if ($m == 'CAR' && $check_agro) {
            $this->flash->error("Нельзя добавлять автомобиль к заявке, где уже есть сельхозтехника.");
            return $this->response->redirect("/create_order/view/$pid");
        }

        if ($m == 'TRAC' && $check_car) {
            $this->flash->error("Нельзя добавлять сельхозтехнику к заявке, где уже есть автомобили.");
            return $this->response->redirect("/create_order/view/$pid");
        }

        if ($profile->agent_status == "VENDOR") $is_vendor = true;

        $this->view->setVars(array(
            "cats" => $cats,
            "car_types" => $car_types,
            "countries" => $countries,
            "pid" => $pid,
            "m" => $m,
            "is_vendor" => $is_vendor,
        ));
    }

    /**
     * Добавить новую заявку (в базу).
     */
    public function addAction()
    {
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $comment = $this->request->getPost("order_comment");
            $type = $this->request->getPost("order_type");
            $user_id = $this->request->getPost("user_id");

            if (!$comment) {
                $comment = "(создано супермодератором)";
            }

            $p = new Profile();
            $p->created = time();
            $p->name = $comment;
            $p->user_id = $user_id;
            $p->moderator_id = $auth->id;
            $p->type = $type;
            $p->agent_status = $this->request->getPost("agent_status");
            $p->comment = $this->request->getPost("comment");
            $p->reason_id = $this->request->getPost("reason_id");
            $p->initiator_id = $this->request->getPost("initiator_id");

            if (!$user_id) {
                $p->agent_name = $this->request->getPost("agent_name");
                $p->agent_city = $this->request->getPost("agent_city");
                $p->agent_iin = $this->request->getPost("agent_iin");
                $p->agent_sign = $this->request->getPost("agent_sign");
                $p->agent_address = $this->request->getPost("agent_address");
                $p->agent_phone = $this->request->getPost("agent_phone");
                $p->agent_iban = $this->request->getPost("agent_iban");
                $p->agent_bank = (int)$this->request->getPost("agent_bank");
                $p->agent_type = $this->request->getPost("agent_type");
                $p->agent_size = $this->request->getPost("agent_size");
            }

            if ($p->save()) {
                $tr = new Transaction();
                $tr->date = time();
                $tr->amount = 0;
                $tr->status = NOT_PAID;
                $tr->source = INVOICE;
                $tr->profile_id = $p->id;
                $tr->save();

                $this->logAction('Создание заявки: №'. $p->id);

                $this->response->redirect("/create_order/view/$p->id");
            }
        }
    }

    /**
     * Импорт машин.
     * @param int $pid
     * @return void
     */
    public function importCarAction($pid)
    {
        $check_queue = __checkQueue($pid);

        if ($check_queue['FOUND']) {
            $this->flash->warning('Осуществляется загрузка ТС. Пожалуйста, подождите.');
            return $this->response->redirect("/create_order/view/$pid");
        }

        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    /**
     * Импорт полученных файлов.
     * @return void
     */
    public function uploadCarAction()
    {
        $auth = User::getUserBySession();

        $order = $this->request->getPost("order_id");
        $profile = Profile::findFirstById($order);
        $existVin = [];
        $successfully_added = 0;

        $c = Car::findByProfileId($profile->id);
        $count = count($c);
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

            return $this->response->redirect("/create_order/view/$profile->id");
        }

        if ($this->request->isPost()) {
            if (!$auth->isSuperModerator()) {
                $message = "Вы не имеете права совершать импорт.";
                $this->flash->error($message);
                $this->logAction('', 'security', 'ALERT');
                $this->response->redirect("/create_order/index/");
            }

            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo(APP_PATH . "/storage/temp/" . $order . ".csv");
                }
            }

            $import = file(APP_PATH . "/storage/temp/" . $order . ".csv");
            foreach ($import as $key => $value) {
                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);
                    // кириллица в VIN
                    $val[2] = mb_strtoupper($val[2]);
                    $val[2] = preg_replace('/(\W)/', '', $val[2]);

                    $car_type = trim($val[0]); // Тип автомобиля
                    $car_volume = trim($val[1]); // Объем (см3) или масса (кг)
                    $vin = mb_strtoupper(trim($val[2])); // VIN-код
                    $year = trim($val[3]); // Год производства
                    $car_date = strtotime(trim($val[4])); // Дата ввоза (импорта)
                    $car_cat = trim($val[5]); // Категория ТС
                    $car_country = trim($val[6]); // Страна производства
                    $ref_st = trim($val[7]); // Седельный тягач? (Да=1, Нет=0, Меж.перевозки=2)
                    $e_car = trim($val[8]); // С электродвигателям? (Да=1, Нет=0)
                    $calculate_method = trim($val[9]); // Способ расчета (0 = По дате импорта, 1 = По дате подачи заявки, 2 = По дате.перв.рег)
                    $car_country_import = trim($val[10]); // Страна импорта

                    $missing_fields = [];

                    if (!$car_type) {
                        $missing_fields[] = 'Тип автомобиля';
                    }
                    if (!$car_volume) {
                        $missing_fields[] = 'Объем';
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
                        return $this->response->redirect("/create_order/import_car/$profile->id");
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

                    if ($e_car == 1 && $car_type != 2 && $car_volume > 0) {
                        $this->flash->error("Объем электромобиля(легковой или автобус) должен быть 0(VIN: $vin)");
                        continue;
                    }

                    if ($car_volume > 50000) {
                        $this->flash->error("Согласно Методики расчета утилизационного платежа транспортные средства 
                    с объемом двигателя более 50 тонн не подлежат к уплате утилизационного платежа(Автомобиль с 
                    VIN-кодом: $vin) !");
                        continue;
                    }

                    $vehicle_type = 'PASSENGER';
                    if ($car_cat == 17) {
                        $car_cat = 15;
                        $vehicle_type = 'CARGO';
                    } else if ($car_cat == 18) {
                        $car_cat = 16;
                        $vehicle_type = 'CARGO';
                    } elseif ($car_cat == 13 || $car_cat == 14) {
                        $vehicle_type = 'AGRO';
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
                        $this->flash->error("Автомобиль с VIN-кодом " . $vin . " не может быть импортирован в систему, т.к. ввоз осуществлен до вступления в силу расширенных обязательств.");
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

                            if ($ref_st == 0) {
                                $value = RefCarValue::findFirst(array(
                                    "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
                                    "bind" => array(
                                        "car_type" => $car_type,
                                        "volume_start" => $car_volume,
                                        "volume_end" => $car_volume
                                    )
                                ));
                            } else {
                                if ($car_type == 2 && $car_cat >= 3 && $car_cat <= 8) {
                                    $value = RefCarValue::findFirst(array(
                                        "conditions" => "car_type = :car_type: AND (volume_end >= :volume_end: AND volume_start <= :volume_start:)",
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
                                        $ref_car_cat_id = $carData['ref_car_cat_id'];
                                    }

                                    if ($year) {
                                        $cats_m = ['M1', 'M1G', 'M2', 'M3', 'M2G', 'M3G'];
                                        $cats_n = ['N1', 'N2', 'N3', 'N1G', 'N2G', 'N3G'];
                                        $cats_other = ['TRACTOR', 'COMBAIN'];
                                        $category = $ref_car_cat_id ? RefCarcat::findFirstById($ref_car_cat_id) : '';

                                        if ($category) {
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

                                $car_value = $this->carService->getCarPriceValue($car_volume, $vehicle_type, $ref_car_cat);

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
                                    'ref_car_cat' => $ref_car_cat ? $ref_car_cat->id : null,
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
                                    $car = $this->carService->upload($data);
                                    $this->carService->setTransactionSum($profile->id);
                                    if ($car) {
                                        $successfully_added++;
                                    }
                                } catch (AppException $e) {
                                    $this->flash->success($e->getMessage());
                                }

                            } else {
                                continue;
                            }
                        } else {
                            $this->flash->success("Автомобиль с VIN-кодом " . $vin . " не может быть импортирован в систему, т.к. его VIN не соответствует формату или содержит кириллические символы.");
                        }
                    }
                }
            }
        }

        if ($successfully_added > 0) {
            $message = "Успешно добавлено $successfully_added ТС";
            $this->logAction($message);
            $this->flash->success($message);
        }

        $exist_vin_list = implode(", ", $existVin);
        $existVinCount = count($existVin);

        if ($existVinCount > 0) {
            $htmlExistsVin = <<<HTML
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
      HTML;

            $this->flash->warning($htmlExistsVin);
        }

        return $this->response->redirect("/create_order/view/$profile->id");
    }

    /**
     * Импорт товар.
     * @param int $pid
     * @return void
     */
    public function importGoodAction($pid)
    {
        $this->view->setVars(array(
            "pid" => $pid
        ));
    }

    /**
     * Импорт полученных файлов(товар).
     * @return void
     */
    public function uploadGoodAction()
    {
        $auth = User::getUserBySession();
        $order = $this->request->getPost("order_id");
        $profile = Profile::findFirstById($order);

        if ($this->request->isPost()) {

            $filename = $order . '_' . time();

            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    $file->moveTo(APP_PATH . "/storage/temp/" . $filename . ".csv");
                }
            }

            $import = file(APP_PATH . "/storage/temp/" . $filename . ".csv");

            foreach ($import as $key => $value) {
                if ($key > 0) {
                    $val = __multiExplode(array(";", ","), $value);

                    $good_weight = (float)str_replace(',', '.', trim($val[0]));
                    $good_date = trim($val[2]);
                    $tn_code = trim($val[3]);
                    $tn_code_add = trim($val[4]);
                    $country = trim($val[5]);
                    $good_basis = trim($val[1]);
                    $basis_date = trim($val[6]);
                    $package_weight = (float)str_replace(',', '.', trim($val[7]));
                    $calculate_method = trim($val[8]);
                    $package_cost = 0;
                    $good_amount = 0;

                    // если дата импорта меньше, чем дата введения постановления
                    // в действие - то перекидываем пользователя на соответствующее
                    // сообщение
                    if (strtotime($good_date) < strtotime(STARTROP)) {
                        $this->flash->notice("За товары, ввезенные / произведенные на территорию Республики Казахстан до 27 января 2016 года включительно, не оплачивается утилизационный платеж.");
                        continue;
                    } else {
                        $tn = RefTnCode::findFirstByCode($tn_code);

                        // NOTE: Расчет платежа (добавление товара)
                        if ($tn != false) {
                            if ($calculate_method == 1) {
                                $good_calc_res = Goods::calculateAmount($good_weight, json_encode($tn));
                                $sum = $good_calc_res['sum'];
                            } else {
                                $good_calc_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn));
                                $sum = $good_calc_res['sum'];
                            }

                            $g = new Goods();
                            $g->weight = $good_weight;
                            $g->basis = $good_basis;
                            $g->date_import = strtotime($good_date);
                            $g->basis_date = strtotime($basis_date);
                            $g->profile_id = $profile->id;
                            $g->ref_tn = $tn->id;
                            $g->price = $good_calc_res['price'];
                            $g->created = time();
                            $g->calculate_method = $calculate_method ? 1 : 0;

                            if ($tn_code_add > 0 && $tn_code_add != 0) {
                                $tn_add = RefTnCode::findFirstByCode($tn_code_add);
                                if ($tn_add) {
                                    $g->ref_tn_add = $tn_add->id;
                                    $g->package_weight = $package_weight;

                                    if ($calculate_method == 1) {
                                        $package_calc_res = Goods::calculateAmount($package_weight, json_encode($tn_add));
                                        $package_cost = $package_calc_res['sum'];
                                    } else {
                                        $package_calc_res = Goods::calculateAmountByDate($good_date, $package_weight, json_encode($tn_add));
                                        $package_cost = $package_calc_res['sum'];
                                    }

                                    $g->package_cost = $package_cost;
                                }
                            } else {
                                $g->ref_tn_add = 0;
                            }

                            $good_amount = round($sum + $package_cost, 2);
                            $g->amount = $good_amount;
                            $g->goods_cost = round($sum, 2);
                            $g->ref_country = $country;

                            $g->goods_type = 0;
                            $g->up_type = 0;
                            $g->up_tn = 0;
                            $g->date_report = 0;
                            $g->save();

                            $tr = Transaction::findFirstByProfileId($profile->id);
                            $tr->amount = $tr->amount + $good_amount;
                            $tr->save();

                            $message = "Новая позиция добавлена";
                            $this->logAction($message);
                            $this->flash->success($message);
                        }
                    }
                }
            }

            $this->response->redirect("/create_order/view/$profile->id");
        }
    }

    public function goodEditAction($gid)
    {
        $auth = User::getUserBySession();
        $good = Goods::findFirstById($gid);
        $profile = Profile::findFirstById($good->profile_id);
        $tr = Transaction::findFirstByProfileId($profile->id);

        if (!$this->isSuperModeratorProfile($profile->moderator_id, $tr->approve)) {
            $message = "Вы не имеете права редактировать этот объект.";
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            $this->response->redirect("/create_order/view/" . $profile->id);
        }

        if ($this->request->isPost()) {
            $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $good_date = $this->request->getPost("good_date");
            $calculate_method = $this->request->getPost("calculate_method");
            $good_basis = $this->request->getPost("good_basis");
            $basis_date = $this->request->getPost("basis_date");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");

            $up_type = 0;
            $date_real = 0;
            $date_report = 0;
            $t_type_i = 0;
            $package_cost = 0;
            $good_amount = 0;

            if ($calculate_method == "dt_sent") {
                $calculate_method = 1;
            } else {
                $calculate_method = 0;
            }

            $t_type = $this->request->getPost("t_type");
            if ($t_type && $t_type == 'up') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 1;
            }
            if ($t_type && $t_type == 'goods') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 2;
            }

            $tn = RefTnCode::findFirstById($tn_code);

            // NOTE: Расчет платежа (правка товара)
            if ($tn != false) {
                if ($calculate_method == 1) {
                    $calc_good_res = Goods::calculateAmount($good_weight, json_encode($tn));
                    $sum = $calc_good_res['sum'];
                } else {
                    $calc_good_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn));
                    $sum = $calc_good_res['sum'];
                }

                $tr = Transaction::findFirstByProfileId($profile->id);
                $tr->amount = $tr->amount - $good->amount;

                $good->weight = $good_weight;
                $good->basis = $good_basis;
                $good->ref_tn = $tn->id;
                $good->date_import = strtotime($good_date);
                $good->profile_id = $profile->id;
                $good->price = $calc_good_res['price'];
                $good->calculate_method = $calculate_method;

                $tn_add = RefTnCode::findFirstById($tn_code_add);
                if ($tn_add) {
                    $good->ref_tn_add = $tn_add->id;
                    $good->package_weight = $package_weight;

                    if ($calculate_method == 1) {
                        $package_calc_res = Goods::calculateAmount($package_weight, json_encode($tn_add));
                        $package_cost = $package_calc_res['sum'];
                    } else {
                        $package_calc_res = Goods::calculateAmountByDate($good_date, $package_weight, json_encode($tn_add));
                        $package_cost = $package_calc_res['sum'];
                    }

                    $good->package_cost = $package_cost;

                    $good_amount = round($sum + $package_cost, 2);
                    $good->amount = $good_amount;
                    $good->goods_cost = round($sum, 2);
                } else {
                    $good->ref_tn_add = 0;
                    $good->package_weight = 0;
                    $good->package_cost = 0;

                    $good_amount = $sum;
                    $good->amount = $good_amount;
                    $good->goods_cost = $sum;
                }

                $good->ref_country = $country;
                $good->updated = time();

                if ($t_type_i > 0) {
                    $good->goods_type = $t_type_i;
                    $good->up_type = $up_type;
                    $good->date_report = strtotime($date_report);

                    if ($t_type_i == 1) {
                        $v = 0;
                        switch ($up_type) {
                            case 1:
                                $v = 1.76;
                                break;
                            case 2:
                                $v = 1.14;
                                break;
                            case 3:
                                $v = 1.40;
                                break;
                            case 4:
                                $v = 0.20;
                                break;
                            case 5:
                                $v = 0.91;
                                break;
                        }

                        $sum = round($good_weight * $v, 2);
                        $good_amount = round($sum + $package_cost, 2);
                        $good->amount = $good_amount;
                        $good->goods_cost = round($sum, 2);
                        $good->price = $v;
                    }
                }

                $tr->amount = $tr->amount + $good_amount;
                $tr->save();

                if ($good->save()) {
                    $message = "Позиция отредактирована.";
                    $this->flash->success($message);
                    $this->logAction($message);
                } else {
                    $message = "Невозможно отредактировать эту позицию.";
                    $this->flash->warning($message);
                    $this->logAction($message);
                }

                $this->response->redirect("/create_order/view/$profile->id");
            }
        } else {
            $good = Goods::findFirstById($gid);
            $profile = Profile::findFirstById($good->profile_id);

            $filter = "code IS NOT NULL AND is_correct = 1";
            //$filter = "price > 0 OR price_old > 0";
            //$filter_add = "(id > 107 AND id < 852 AND id <> 322) OR id IN (30, 31)";
            if ($profile->type == 'R20') {
                if ($good->goods_type == 0) {
                    $filter = "(id > 0 AND id < 102) OR id > 850 AND price > 0";
                }
                if ($good->goods_type == 1) {
                    $filter = "id > 107 AND id < 144 AND price > 0";
                }
                if ($good->goods_type == 2) {
                    $filter = "id > 143 AND id < 851 AND price > 0";
                }
            }

            $tn_codes = RefTnCode::find([
                $filter,
                'order' => 'code'
            ]);

            $country = RefCountry::find(array('id <> 1'));

            if (!$auth->isSuperModerator()) {
                $this->flash->error("Вы не имеете права редактировать этот объект.");
                $this->response->redirect("/create_order/index/");
            }

            $this->view->setVars(array(
                "good" => $good,
                "tn_codes" => $tn_codes,
                "country" => $country
            ));
        }
    }

    public function goodDeleteAction($gid)
    {
        $auth = User::getUserBySession();

        $good = Goods::findFirstById($gid);
        $profile = Profile::findFirstById($good->profile_id);

        $t_type = $good->goods_type;
        $_SESSION['goods_show'] = $t_type;

        if ($auth->id !== $profile->moderator_id) {
            $message = "Вы не имеете права удалять этот объект.";
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            $this->response->redirect("/create_order/index/");
        } else {
            $tr = Transaction::findFirstByProfileId($profile->id);
            $tr->amount = $tr->amount - $good->amount;
            $tr->save();

            if ($good->delete()) {
                $message = "Удаление произошло успешно.";
                $this->flash->success($message);
                $this->logAction($message);
                $this->response->redirect("/create_order/view/$profile->id");
            }
        }
    }

    public function newGoodAction($pid)
    {
        $filter = "code IS NOT NULL AND is_active = 1 AND is_correct = 1";
        $filter_add = "code IS NOT NULL AND type = 'PACKAGE' AND ((is_active = 1 AND is_correct = 1) OR id = 970)";

        $tn_codes = RefTnCode::find([
            $filter,
            'order' => 'name'
        ]);

        $tn_codes_add = RefTnCode::find([
            $filter_add,
            'order' => 'name'
        ]);

        $country = RefCountry::find(array('id <> 1'));

        $this->view->setVars(array(
            "tn_codes" => $tn_codes,
            "package_tn_codes" => $tn_codes_add,
            "pid" => $pid,
            "country" => $country
        ));
    }

    public function addGoodAction()
    {
        if ($this->request->isPost()) {
            $pid = $this->request->getPost("profile");

            $profile = Profile::findFirstById($pid);

            $good_weight = (float)str_replace(',', '.', $this->request->getPost("good_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $good_date = $this->request->getPost("good_date");
            $calculate_method = $this->request->getPost("calculate_method");
            $tn_code = $this->request->getPost("tn_code");
            $tn_code_add = $this->request->getPost("tn_code_add");
            $country = $this->request->getPost("good_country");
            $good_basis = $this->request->getPost("good_basis");
            $basis_date = $this->request->getPost("basis_date");

            $up_type = 0;
            $date_real = 0;
            $date_report = 0;
            $t_type_i = 0;
            $package_cost = 0;
            $good_amount = 0;

            if ($calculate_method == "dt_sent") {
                $calculate_method = 1;
            } else {
                $calculate_method = 0;
            }

            $t_type = $this->request->getPost("t_type");
            if ($t_type && $t_type == 'up') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 1;
            }
            if ($t_type && $t_type == 'goods') {
                $up_type = $this->request->getPost("up_type");
                $date_report = $this->request->getPost("date_report");
                $t_type_i = 2;
            }

            // если дата импорта меньше, чем дата введения постановления
            // в действие - то перекидываем пользователя на соответствующее
            // сообщение
            if (strtotime($good_date) < strtotime(STARTROP)) {
                $this->flash->notice("За товары, ввезенные / произведенные на территорию Республики Казахстан до 27 января 2016 года включительно, не оплачивается утилизационный платеж.");
                return $this->response->redirect("/create_order/view/$profile->id");
            }

            $tn = RefTnCode::findFirstById($tn_code);

            // NOTE: Расчет платежа (добавление товара)
            if ($tn != false) {
                if ($calculate_method == 1) {
                    $good_calc_res = Goods::calculateAmount($good_weight, json_encode($tn));
                    $sum = $good_calc_res['sum'];
                } else {
                    $good_calc_res = Goods::calculateAmountByDate($good_date, $good_weight, json_encode($tn));
                    $sum = $good_calc_res['sum'];
                }

                $c = new Goods();
                $c->weight = $good_weight;
                $c->basis = $good_basis;
                $c->date_import = strtotime($good_date);
                $c->basis_date = strtotime($basis_date);
                $c->profile_id = $profile->id;
                $c->ref_tn = $tn->id;
                $c->price = $good_calc_res['price'];
                $c->calculate_method = $calculate_method;
                $c->created = time();

                $tn_add = RefTnCode::findFirstById($tn_code_add);
                $c->goods_cost = round($sum, 2);

                if ($tn_add) {
                    $c->ref_tn_add = $tn_add->id;
                    $c->package_weight = $package_weight;

                    if ($calculate_method == 1) {
                        $package_calc_res = Goods::calculateAmount($package_weight, json_encode($tn_add));
                        $package_cost = $package_calc_res['sum'];
                    } else {
                        $package_calc_res = Goods::calculateAmountByDate($good_date, $package_weight, json_encode($tn_add));
                        $package_cost = $package_calc_res['sum'];
                    }

                    $c->package_cost = $package_cost;
                }

                $good_amount = round($sum + $package_cost, 2);
                $c->amount = $good_amount;
                $c->ref_country = $country;

                if ($t_type_i > 0) {
                    $c->goods_type = $t_type_i;
                    $c->up_type = $up_type;
                    $c->date_report = strtotime($date_report);

                    if ($t_type_i == 1) {
                        $v = 0;
                        switch ($up_type) {
                            case 1:
                                $v = 1.76;
                                break;
                            case 2:
                                $v = 1.14;
                                break;
                            case 3:
                                $v = 1.40;
                                break;
                            case 4:
                                $v = 0.20;
                                break;
                            case 5:
                                $v = 0.91;
                                break;
                        }

                        $sum = round($good_weight * $v, 2);
                        $good_amount = round($sum + $package_cost, 2);
                        $c->goods_cost = round($sum, 2);
                        $c->amount = $good_amount;
                        $c->price = $v;
                    }
                }

                $tr = Transaction::findFirstByProfileId($profile->id);
                $tr->amount = $tr->amount + $good_amount;
                $tr->save();

                if ($c->save()) {
                    $message = "Новая позиция добавлена.";
                    $this->flash->success($message);
                    $this->logAction($message);
                } else {
                    $message = "Невозможно сохранить новую позицию.";
                    $this->flash->warning($message);
                    $this->logAction($message);
                }

                return $this->response->redirect("/create_order/view/$profile->id");
            }
        }
    }

    // изменение общая сумма(transaction->amount)
    public function updateAmountAction()
    {
        $auth = User::getUserBySession();
        $can = true;
        $tr_bafore = 0;
        $diff = 0;

        if ($this->request->isPost()) {
            $tr_id = $this->request->getPost("transaction_id");
            $sum = (float)str_replace(',', '.', $this->request->getPost("sum"));

            $tr = Transaction::findFirstById($tr_id);

            if ($tr) {
                $tr_bafore = $tr->amount;
                // разница
                $diff = $sum - $tr_bafore;

                if (($diff >= 100) || ($diff <= -100)) {
                    $can = false;
                    $this->flash->error("Нельзя превышать 100 тг.");
                }
            }

            if (!$auth->isSuperModerator()) {
                $can = false;
                $message = "У вас нет прав на это действие.";
                $this->logAction($message);
                $this->flash->error($message);
            }

            if ($can != false) {
                $tr->amount = $sum;

                if ($tr->save()) {
                    // логгирование
                    $l = new ProfileLogs();
                    $l->login = $auth->idnum;
                    $l->action = 'UPDATED_TRANSACTION_AMOUNT';
                    $l->profile_id = $tr->profile_id;
                    $l->dt = time();
                    $l->meta_before = $tr_bafore;
                    $l->meta_after = $sum;
                    $l->save();
                    $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $this->logAction($logString);
                }
                $message = "Изменение успешно сохранено.";
                $this->logAction($message);
                $this->flash->success($message);
            }
        }

        $this->response->redirect("/create_order/view/$tr->profile_id");
    }

    public function clearAllGoodsAction($pid)
    {
        $p = Profile::findFirstById($pid);
        $auth = User::getUserBySession();

        $can = true;

        if ($p) {
            if ($auth->id != $p->moderator_id && $p->blocked) {
                $can = false;
            }

            if ($can) {
                $goods = Goods::findByProfileId($p->id);
                $tr = Transaction::findFirstByProfileId($p->id);
                $tr->amount = 0;
                $tr->save();

                foreach ($goods as $g) {
                    $good = Goods::findFirstById($g->id);

                    if ($good) {
                        $good->delete();
                    }
                }
            }

            return $this->response->redirect("/create_order/view/$pid");
        }
    }

    public function clearAllCarsAction($pid)
    {
        $auth = User::getUserBySession();
        $p = Profile::findFirstById($pid);
        $can = true;

        if ($p) {
            if ($auth->id != $p->moderator_id && $p->blocked) {
                $can = false;
            }

            if ($can) {
                $cars = Car::findByProfileId($p->id);
                $tr = Transaction::findFirstByProfileId($p->id);
                $tr->amount = 0;
                $tr->save();

                foreach ($cars as $c) {
                    $car = Car::findFirstById($c->id);

                    if ($car) {
                        $car->delete();

                        $digitalpass = File::findFirst(array(
                            "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                            "bind" => array(
                                "pid" => $car->profile_id,
                                "type" => "digitalpass"
                            )
                        ));

                        if ($digitalpass) {
                            $filename = APP_PATH . '/private/docs/' . $digitalpass->id . '.' . $digitalpass->ext;
                            @unlink(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/epts_' . $car->vin . '.pdf');

                            $epts_pdf_num = count(glob(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . "/epts_*.pdf"));
                            if ($epts_pdf_num > 0) {
                                exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $filename . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/epts_*.pdf');
                            } else {
                                if (file_exists($filename)) {
                                    @unlink($filename);
                                }

                                $digitalpass->delete();
                            }
                        }

                        $spravka_epts = File::findFirst(array(
                            "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                            "bind" => array(
                                "pid" => $car->profile_id,
                                "type" => "spravka_epts"
                            )
                        ));

                        if ($spravka_epts) {
                            $filename = APP_PATH . '/private/docs/' . $spravka_epts->id . '.' . $spravka_epts->ext;
                            @unlink(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/spravka_' . $car->vin . '.pdf');

                            $epts_pdf_num = count(glob(APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . "/spravka_*.pdf"));
                            if ($epts_pdf_num > 0) {
                                exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . $filename . ' ' . APP_PATH . '/private/docs/epts_pdf/' . $car->profile_id . '/spravka_*.pdf');
                            } else {
                                if (file_exists($filename)) {
                                    @unlink($filename);
                                }

                                $spravka_epts->delete();
                            }
                        }
                    }
                }
            }

            return $this->response->redirect("/create_order/view/$pid");
        } else {
            $message = "У вас нет прав на это действие.";
            $this->flash->warning($message);
            $this->logAction($message);
            return $this->response->redirect("/index/index/");
        }
    }

    public function getCarListAction(int $pid = 0)
    {
        $t = $this->translator;
        $this->view->disable();
        $auth = User::getUserBySession();

        $p = Profile::findFirstById($pid);
        $data = array();

        if ($auth->isAdmin() || $auth->isAdminSoft() || $auth->isAdminSec() || $auth->isModerator() || $auth->isSuperModerator() || $auth->isAccountant() || ($p->moderator_id == $auth->id)) {
            $sql = <<<SQL
        SELECT
          c.id AS c_id,
          c.volume AS c_volume,
          c.vin AS c_vin,
          c.year AS c_year,
          c.cost AS c_cost,
          cc.name AS c_cat,
          c.ref_car_type_id AS c_type,
          t.name AS c_type_name,
          c.ref_st_type AS c_ref_st,
          c.kap_request_id AS kap_request_id,
          c.epts_request_id AS epts_request_id,
          c.calculate_method AS calculate_method,
          c.first_reg_date AS first_reg_date,
          c.status AS c_status,
          FROM_UNIXTIME(c.date_import, "%d.%m.%Y") AS c_date_import,
          country.name AS c_country,
          tr.profile_id AS c_tr,
          tr.status AS c_tr_status,
          tr.approve AS c_approve,
          tr.ac_approve AS ac_approve,
          tr.md_dt_sent AS tr_md_dt_sent,
          c.vehicle_type AS c_vehicle_type
        FROM Car c
          JOIN RefCountry country
          JOIN RefCarCat cc
          JOIN RefCarType t
          JOIN Transaction tr
        WHERE
          c.profile_id = :pid: AND
          tr.profile_id = :pid: AND
          cc.id = c.ref_car_cat AND
          country.id = c.ref_country
        GROUP BY c.id
        ORDER BY c.id ASC
      SQL;

            $query = $this->modelsManager->createQuery($sql);

            $cars = $query->execute(array(
                "pid" => $pid
            ));

            if (count($cars) > 0) {
                foreach ($cars as $c) {
                    $volume = null;
                    $status = null;
                    $st_type = $this->translator->_("ref-st-not");

                    if ($c->c_type == TRUCK || $c->c_vehicle_type == 'CARGO') {
                        $volume = "$c->c_volume кг";
                    } elseif ($c->c_type < 4 || $c->c_vehicle_type == 'PASSENGER') {
                        $volume = "$c->c_volume см*3";
                    } else {
                        $volume = "$c->c_volume л.с.";
                    }

                    if ($c->c_ref_st == 1) {
                        $st_type = $this->translator->_("ref-st-yes");
                    } elseif ($c->c_ref_st == 2) {
                        $st_type = $this->translator->_("ref-st-international-transport");
                    }

                    if (in_array($c->c_cat, ['cat-l6', 'cat-l7'])) {
                        $st_type = '-';
                    }

                    $cost = '<b>' . __money($c->c_cost) . ' тг</b>';
                    if ($c->c_status == "CANCELLED") {
                        $status = '<b style="color:red">ДПП аннулирован!</b>';
                    }
                    $vehicle_type = $this->translator->_($c->c_cat) . (in_array($c->c_cat, ['cat-l6', 'cat-l7']) ? ' (' . $this->translator->_(mb_strtolower($c->c_vehicle_type)) . ')' : '');

                    $data[] = [
                        "c_id" => $c->c_id,
                        "c_volume" => $volume,
                        "c_vin" => $c->c_vin,
                        "c_year" => $c->c_year,
                        "c_cost" => $cost,
                        "c_cat" => $vehicle_type,
                        "c_type" => $c->c_type,
                        "c_type_name" => $c->c_type_name,
                        "c_status" => $status,
                        "c_date_import" => $c->c_date_import,
                        "c_country" => $c->c_country,
                        "c_tr" => $c->c_tr,
                        "c_tr_status" => $c->c_tr_status,
                        "c_approve" => $c->c_approve,
                        "ac_approve" => $c->ac_approve,
                        "c_st_type" => $st_type,
                        "kap_request_id" => $c->kap_request_id,
                        "epts_request_id" => $c->epts_request_id,
                    ];
                }
            }

            if (is_array($data) && count($data) > 0) {
                $json_data = array(
                    "draw" => 1,
                    "recordsTotal" => count($data),
                    "recordsFiltered" => count($data),
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
            $this->flash->error($message);
            $this->logAction($message, 'security', 'ALERT');
            return $this->response->redirect("/create_order/index/");
        }
    }

    public function getGoodsListAction(int $pid = 0)
    {
        $this->view->disable();

        $auth = User::getUserBySession();
        $p = Profile::findFirstById($pid);
        if (!$auth || !$p) {
            return $this->response->redirect('/create_order/index/');
        }

        $hasAccess =
            $auth->isAdmin()
            || $auth->isAdminSoft()
            || $auth->isAdminSec()
            || $auth->isModerator()
            || $auth->isSuperModerator()
            || $auth->isAccountant()
            || ((int)$p->moderator_id === (int)$auth->id);

        if (!$hasAccess) {
            $message = 'У вас нет прав на это действие!';
            $this->flash->error($message);
            $this->logAction($message, 'security');
            return $this->response->redirect('/create_order/index/');
        }

        // Берём один "актуальный" Transaction для профиля, чтобы не плодить JOIN-дубликаты
        $tr = Transaction::findFirst([
            'conditions' => 'profile_id = :pid:',
            'bind' => ['pid' => $pid],
            'bindTypes' => ['pid' => Column::BIND_PARAM_INT],
            'order' => 'id DESC',
        ]);

        // PHQL без GROUP BY и без JOIN Transaction
        $phql = <<<PHQL
        SELECT
            g.id              AS g_id,
            g.date_import     AS g_date,
            g.weight          AS g_weight,
            g.amount          AS g_amount,
            g.goods_cost      AS g_cost,
            g.basis           AS g_basis,
            g.ref_tn_add      AS tn_add,
            g.package_weight  AS p_weight,
            g.package_cost    AS p_cost,
            g.basis_date      AS b_date,
            g.status          AS g_status,
            tn.code           AS tn_code
        FROM Goods g
        LEFT JOIN RefTnCode tn ON tn.id = g.ref_tn
        WHERE g.profile_id = :pid:
        ORDER BY g.id DESC
    PHQL;

        $goods = $this->modelsManager->executeQuery(
            $phql,
            ['pid' => $pid],
            ['bindTypes' => ['pid' => Column::BIND_PARAM_INT]]
        );

        $data = [];
        foreach ($goods as $g) {
            $statusHtml = ($g->g_status === 'CANCELLED') ? '<b style="color:red">ДПП аннулирован!</b>' : null;

            $data[] = [
                'g_id' => (int)$g->g_id,
                'g_date' => ($g->g_date > 0) ? date('d.m.Y', (int)$g->g_date) : '—',
                'g_weight' => (float)$g->g_weight,
                'tn_code' => (string)$g->tn_code,
                'g_amount' => number_format((float)$g->g_amount, 2, ',', '&nbsp;'),
                'g_cost' => number_format((float)$g->g_cost, 2, ',', '&nbsp;'),
                'g_basis' => (string)$g->g_basis,
                'tn_add' => (string)$g->tn_add,

                // Поля Transaction от последней записи по профилю
                'c_tr' => $tr ? (int)$tr->profile_id : null,
                'c_tr_status' => $tr ? (string)$tr->status : null,
                'c_approve' => $tr ? $tr->approve : null,
                'ac_approve' => $tr ? (int)$tr->ac_approve : null,

                'p_weight' => (float)$g->p_weight,
                'p_cost' => number_format((float)$g->p_cost, 2, ',', '&nbsp;'),
                'b_date' => ($g->b_date > 0) ? date('d.m.Y', (int)$g->b_date) : '—',
                'status' => $statusHtml,
                'p_blocked' => (int)$p->blocked,
            ];
        }

        $this->logAction('Просмотр списка товаров');

        $json = [
            'draw' => 1,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
            'data' => $data,
        ];

        return $this->response
            ->setStatusCode(200)
            ->setJsonContent($json);
    }

    protected function isSuperModeratorProfile($moderator_id, $approve): bool
    {
        $auth = User::getUserBySession();
        if ($moderator_id && $approve) {
            if ($auth->id === $moderator_id && $auth->isSuperModerator() && in_array($approve, ['NEUTRAL', 'DECLINED'])) {
                return true;
            }
        }

        return false;
    }
}

