<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use CompanyDetail;
use ContactDetail;
use ControllerBase;
use LoginService;
use PersonDetail;
use Phalcon\Flash\Exception;
use Phalcon\Http\ResponseInterface;
use RefBank;
use RefCountry;
use RefKbe;
use RequestChangeStatus;
use User;
use UserType;

// DONE:50 Сопроводительные документы у ФЛ - убрать
// DONE:70 Проверка на наличие папки

class SettingsController extends ControllerBase
{
    private array $allowedExtensions = ['pdf', 'jpeg', 'jpg', 'png', 'jfif', 'PDF', 'JPEG', 'JPG', 'PNG', 'JFIF', 'tif', 'TIF', 'doc', 'docx'];

    public function indexAction()
    {
        // 0) Аутентификация
        $auth = User::getUserBySession();
        $settings = (array)($this->session->get('__settings') ?? []);
        $userType = UserType::findFirstById((int)$auth->user_type_id);
        if (!$userType) {
            $this->flash->error('Тип пользователя не найден.');
            return;
        }
        // Инфо-сообщение
        $this->flash->notice(
            'Уважаемые пользователи! В разделе настроек у каждой секции есть своя кнопка «Сохранить». ' .
            'Пожалуйста, будьте внимательнее при заполнении своих данных.'
        );

        // 2) Транзакция: детали + контакты + пользователь
        $details = null;

        // 2.1) ФЛ
        if ($auth->user_type_id === PERSON) {
            $details = PersonDetail::findFirst([
                'conditions' => 'user_id = :id:',
                'bind' => ['id' => (int)$auth->id],
            ]);
        }

        // 2.2) ЮЛ
        if ($auth->user_type_id === COMPANY) {
            $details = CompanyDetail::findFirst([
                'conditions' => 'user_id = :id:',
                'bind' => ['id' => (int)$auth->id],
            ]);
        }

        // 2.3) Контакты
        $contacts = ContactDetail::findFirst([
            'conditions' => 'user_id = :id:',
            'bind' => ['id' => (int)$auth->id],
        ]);

        // 3) Справочники
        $refKbe = RefKbe::find();
        $refBank = RefBank::find();
        $refCountry = RefCountry::find(['conditions' => 'id NOT IN (1, 201)']);

        // 4) История логинов
        $lastAttempts = $this->authService->getLastLoginAttempts($auth->id);

        // 5) Переменные для Volt (никаких PHP-функций в шаблоне)
        $isPerson = ((int)$auth->user_type_id === (int)PERSON);
        $isCompany = ((int)$auth->user_type_id === (int)COMPANY);

        $ekuLabel = 'ЛИЧНАЯ';
        $eku = null;

        if ($isCompany) {
            $eku = $settings['eku'] ?? null;
            $ekuLabel = (function_exists('__checkHOC') && __checkHOC($eku)) ? 'ПЕРВЫЙ РУКОВОДИТЕЛЬ' : 'СОТРУДНИК';
            $details->reg_date = date('d.m.Y', $details->reg_date);
        }

        if($isPerson){
            $details->birthdate = date('d.m.Y', $details->birthdate);
        }

        // 6) Загруженные файлы → ссылки
        $uploadedDocs = [];
        $docsPath = APP_PATH . '/private/user/' . (int)$auth->id . '/docs/';
        if (is_dir($docsPath)) {
            $i = 0;
            foreach (scandir($docsPath) as $f) {
                if ($f[0] === '.') continue;
                $uploadedDocs[] = '/settings/getfile/' . $i++;
            }
        }

        $isAccountant = $isCompany && (function_exists('__checkHOC') ? __checkHOC($eku) : false);

        $this->view->setVars([
            'ekuLabel' => $ekuLabel,
            'uploadedDocs' => $uploadedDocs,
            'isAccountant' => $isAccountant,
            'user' => $auth,
            'userType' => $userType,
            'refKbe' => $refKbe,
            'refBank' => $refBank,
            'refCountry' => $refCountry,
            'details' => $details ?? null,
            'contacts' => $contacts ?? null,
            'lastAttempts' => $lastAttempts,
        ]);
    }

    public function uploadAction($type): ResponseInterface
    {
        $auth = User::getUserBySession();
        $path = APP_PATH . "/private/user/" . $auth->id . "/docs/";

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $this->flash->warning(sprintf('Directory "%s" could not be created', $path));
            return $this->response->redirect("/settings/index/");
        }

        if (!is_dir($path)) {
            $this->flash->warning(sprintf('"%s" is not a directory', $path));
            return $this->response->redirect("/settings/index/");
        }

        if (!is_readable($path)) {
            $this->flash->warning(sprintf('Directory "%s" is not readable', $path));
            return $this->response->redirect("/settings/index/");
        }

        $dir = @scandir($path);
        if ($dir === false) {
            $this->flash->warning(sprintf('Unable to scan directory "%s"', $path));
            return $this->response->redirect("/settings/index/");
        }

        $baseDir = realpath($path);
        if ($baseDir === false) {
            $this->flash->warning(sprintf('Directory "%s" does not exist', $path));
            return $this->response->redirect("/settings/index/");
        }

        foreach ($dir as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Собираем путь внутри директории
            $filePath = $baseDir . DIRECTORY_SEPARATOR . $file;

            // Нормализуем путь и убеждаемся, что он действительно в $baseDir (защита от path traversal + symlink)
            $realFilePath = realpath($filePath);
            if ($realFilePath === false) {
                // файл уже удалили/переместили – пропускаем
                continue;
            }
            if (strpos($realFilePath, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
                // внезапно путь указывает вне требуемой директории – не трогаем
                continue;
            }

            if (is_file($realFilePath)) {
                // Пытаемся удалить напрямую, без is_writable()
                if (!@unlink($realFilePath)) {
                    $this->flash->warning(sprintf('Unable to delete file "%s"', $realFilePath));
                    return $this->response->redirect("/settings/index/");
                }
            }
        }

        // грузим новый файл
        if ($type !== 'company') {
            $this->flash->warning('Некорректный тип загрузки');
            return $this->response->redirect("/settings/index/");
        }

        if (!$this->request->isPost()) {
            $this->flash->warning('Неверный метод запроса');
            return $this->response->redirect("/settings/index/");
        }

        if (!$this->request->hasFiles()) {
            $this->flash->warning('Файлы для загрузки не найдены');
            return $this->response->redirect("/settings/index/");
        }

        $allowedExtensions = ['pdf', 'jpeg', 'jpg', 'png'];
        $allowedMimeTypes = [
            'pdf'  => 'application/pdf',
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
        ];

        $maxFileSize = 5 * 1024 * 1024; // 5MB

        $isValid  = false;
        $messages = [];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($this->request->getUploadedFiles() as $file) {
            // 1. Проверяем ошибку загрузки
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $messages[] = "Ошибка загрузки файла: " . $file->getName();
                continue;
            }

            // 2. Проверка размера
            if ($file->getSize() > $maxFileSize) {
                $messages[] = "Слишком большой файл: " . $file->getName() . ". Максимальный размер 5 МБ.";
                continue;
            }

            // 3. Проверка расширения (по имени файла)
            $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $messages[] = "Недопустимый файл: " . $file->getName() .
                    ". Загрузите файл в формате pdf, jpeg, jpg, png.";
                continue;
            }

            // 4. Проверка MIME-типа по содержимому
            $mimeType = $finfo->file($file->getTempName());
            if (!isset($allowedMimeTypes[$extension]) || $allowedMimeTypes[$extension] !== $mimeType) {
                $messages[] = "Тип содержимого файла не соответствует расширению: " . $file->getName();
                continue;
            }

            // 5. Формируем безопасный путь назначения:
            //    только внутри директории пользователя, фиксированное имя
            $destinationPath = $baseDir . DIRECTORY_SEPARATOR . 'registration_certificate.' . $extension;

            // 6. Перемещаем файл и проверяем результат
            if (!$file->moveTo($destinationPath)) {
                $messages[] = "Не удалось сохранить файл: " . $file->getName();
                continue;
            }

            $isValid = true;
            $messages[] = "Ваш файл загружен: " . $file->getName();
        }

        foreach ($messages as $message) {
            $this->flash->message($isValid ? 'success' : 'error', $message);
        }

        return $this->response->redirect("/settings/index/");
    }

    public function getfileAction($num)
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        $path = APP_PATH . "/private/user/" . $auth->id . "/docs/";
        $dir = scandir($path);
        $dir = array_diff($dir, array('.', '..'));

        foreach ($dir as $k => $v) {
            $file[] = $v;
        }

        __downloadFile($path . $file[$num]);
    }

    public function statusAction($status)
    {
        $auth = User::getUserBySession();

        if ($status == 'person') {
            $req = new RequestChangeStatus();
            $req->user_id = $auth->id;
            $req->user_type = PERSON;
            $req->date_request = time();

            if ($req->save()) {
                $this->flash->notice("Вы отправили запрос «Стать физическим лицом». После одобрения заявки на смену модератором, вы получите соответствующее уведомление на e-mail.");
            } else {
                $this->flash->error("Вы отправили запрос «Стать физическим лицом». Произошла ошибка, попробуйте еще раз.");
            }
        } else {
            if ($status == 'company') {
                $req = new RequestChangeStatus();
                $req->user_id = $auth->id;
                $req->user_type = COMPANY;
                $req->date_request = time();

                if ($req->save()) {
                    $this->flash->notice("Вы отправили запрос «Стать юридическим лицом». После одобрения заявки на смену модератором, вы получите соответствующее уведомление на e-mail.");
                } else {
                    $this->flash->error("Вы отправили запрос «Стать юридическим лицом». Произошла ошибка, попробуйте еще раз.");
                }
            }
        }

        return $this->response->redirect("/settings/index/");
    }

    public function profileAction()
    {
        $auth = User::getUserBySession();
        if ($this->request->isPost()) {
            $lang = $this->request->getPost("set_lang");

            if ($auth != false) {
                $auth->last_login = time();
                $auth->lastip = $this->request->getClientAddress();
                $auth->lang = $lang;

                if ($auth->update()) {
                    $this->flash->success("Данные сохранены!");
                    return $this->response->redirect("/settings/index/");
                }
            }
        }
    }

    public function detailsAction()
    {
        $auth = User::getUserBySession();
        if ($this->request->isPost()) {
            $dev = $this->session->get("r20");

            if ($auth->user_type_id == PERSON) {
                $dt = PersonDetail::findFirst(array(
                    "user_id = :id:",
                    "bind" => array(
                        "id" => $auth->id
                    )
                ));

                if ($dt != false) {
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
                    $dt->iin = $auth->idnum;
                    $dt->birthdate = strtotime($birthdate);

                    $auth->fio = "$last_name $first_name $parent_name";
                    $auth->save();

                    if ($dt->update() != false) {
                        $this->flash->success("Данные сохранены!");
                        $this->logAction('Детальные данные сохранены', 'account');
                        return $this->response->redirect("/settings/index/");
                    }
                }
            }

            if ($auth->user_type_id == COMPANY) {
                $dt = CompanyDetail::findFirst(array(
                    "user_id = :id:",
                    "bind" => array(
                        "id" => $auth->id
                    )
                ));

                if ($dt != false) {
                    // теперь собираем данные
                    $name = $this->request->getPost("det_name");
                    $bin = $this->request->getPost("det_bin");
                    $reg_date = $this->request->getPost("det_reg_date");
                    $iban = $this->request->getPost("det_iban");
                    $ref_bank = $this->request->getPost("det_ref_bank");
                    $ref_kbe = $this->request->getPost("det_ref_kbe");
                    $oked = $this->request->getPost("det_oked");
                    $reg_num = $this->request->getPost("det_reg_num");

                    // сохраняем
                    if ($dt->name == '') {
                        $dt->name = $name;
                    }
                    $dt->bin = $auth->idnum;
                    $dt->reg_date = strtotime($reg_date);
                    $dt->iban = $iban;
                    $dt->ref_bank_id = $ref_bank;
                    $dt->ref_kbe_id = $ref_kbe;
                    $dt->oked = $oked;
                    $dt->reg_num = $reg_num;

                    $auth->org_name = $name;
                    $auth->save();

                    if ($dev && isset($dev['dev.features']) == 'enable') {
                        $b_region = $this->request->getPost("b_region");
                        $b_size = $this->request->getPost("b_size");

                        $dt->b_region = $b_region;
                        $dt->b_size = $b_size;
                    }

                    if ($dt->update() != false) {
                        $this->logAction('Детальные данные сохранены', 'account');
                        $this->flash->success("Данные сохранены!");
                        return $this->response->redirect("/settings/index/");
                    }
                }
            }
        }
    }

    public function contactsAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/settings/index/');
        }

        $auth = User::getUserBySession();
        if (!$auth) {
            $this->flash->error('Сессия недействительна.');
            return $this->response->redirect('/login');
        }

        // Нормализация телефонов
        $phone        = ltrim((string)$this->request->getPost('con_phone'), '+');
        $mobilePhone  = ltrim((string)$this->request->getPost('con_mobile_phone'), '+');

        // Обязательное поле
        if ($mobilePhone === '') {
            $this->flash->error("Поле 'Мобильный телефон' обязательно для заполнения.");
            return $this->response->redirect('/settings/index/');
        }

        // Берем существующую запись или создаем новую
        $dt = ContactDetail::findFirstByUserId($auth->id) ?: new ContactDetail();
        $dt->user_id = $auth->id;

        // Базовые поля
        $data = [
            'ref_reg_country_id' => $this->request->getPost('con_ref_reg_country'),
            'reg_city'           => $this->request->getPost('con_reg_city'),
            'reg_address'        => $this->request->getPost('con_reg_address'),
            'reg_zipcode'        => $this->request->getPost('con_reg_zipcode'),
            'ref_country_id'     => $this->request->getPost('con_ref_country'),
            'city'               => $this->request->getPost('con_city'),
            'address'            => $this->request->getPost('con_address'),
            'zipcode'            => $this->request->getPost('con_zipcode'),
            'phone'              => $phone,
            'mobile_phone'       => $mobilePhone,
        ];

        // Опциональные KATO-поля: добавляем только если пришли
        $optionalMap = [
            'kato_region'       => ['fact_kato_region', 'kato_region'],
            'kato_city'         => ['fact_kato_city', 'kato_city'],
            'kato_district'     => ['fact_kato_district', 'kato_district'],
            'ref_kato_region'   => ['kato_region', 'ref_kato_region'],
            'ref_kato_city'     => ['kato_city', 'ref_kato_city'],
            'ref_kato_district' => ['kato_district', 'ref_kato_district'],
        ];

        foreach ($optionalMap as $postKey => [$requestKey, $modelKey]) {
            $val = $this->request->getPost($requestKey);
            if ($val !== null && $val !== '') {
                $data[$modelKey] = $val;
            }
        }

        // Безопасное массовое присваивание
        $dt->assign($data, array_keys($data));

        if ($dt->save() === false) {
            foreach ($dt->getMessages() as $msg) {
                $this->flash->error((string)$msg);
                $this->logAction((string)$msg, 'account');
            }
        } else {
            $this->flash->success('Данные сохранены!');
            $this->logAction('Контактные данные сохранены', 'account');
            return $this->response->redirect('/settings/index/');
        }

        return $this->response->redirect('/settings/index/');
    }

    public function accountantAction()
    {
        $auth = User::getUserBySession();
        if ($this->request->isPost()) {

            if ($auth != false) {
                // теперь собираем данные
                $iin_buh = $this->request->getPost("iin_buh");

                // сохраняем
                $auth->accountant = $iin_buh;

                if ($auth->update() != false) {
                    $message = "Данные бухгалтера сохранены!";
                    $this->flash->success($message);
                    $this->logAction($message, 'account');
                    return $this->response->redirect("/settings/index/");
                }
            }
        }
    }

    public function changePasswordAction()
    {
        $auth = User::getUserBySession();
        $password = $this->request->getPost("restore_pass");
        $password_again = $this->request->getPost("restore_pass_again");

        if ($password && $password_again && $password === $password_again) {
            if (!$this->validatePassword($password)) {
                $message = 'Пароль не соответствует требованиям безопасности!';
                $this->logAction('Изменения пароля. '.$message, 'account');
                $this->flash->error($message);
            } else {
                $passwordHash = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);
                $expiryDate = date('Y-m-d', strtotime(PASSWORD_EXPIRY_DAYS . " days"));
                $auth->password = $passwordHash;
                $auth->password_expiry = $expiryDate;
                $auth->save();
                $this->flash->success($this->translator->_("password-is-changed"));

                $this->logAction('Изменения пароля. '.$this->translator->_("password-is-changed"), 'account');
            }
        } else {
            $this->flash->error($this->translator->_("bad-or-wrong-password"));
            $this->logAction('Изменения пароля. '.$this->translator->_("bad-or-wrong-password"), 'account');
        }

        return $this->response->redirect("/settings/");
    }

    private function validatePassword($password)
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
            return false;
        }
        return true;
    }

}
