<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefCarType;
use RefCarValue;

class RefCarValueController extends ControllerBase
{
    public function indexAction()
    {
        if (!$this->session->has('REF_CAR_VALUE_CAR_TYPE_ID')) {
            $this->session->set('REF_CAR_VALUE_CAR_TYPE_ID', 'ALL');
        }
        $ctId = (string)$this->session->get('REF_CAR_VALUE_CAR_TYPE_ID');

        $clear = $this->request->getQuery('clear', 'string', '');
        $carTypeIdQuery = $this->request->getQuery('car_type_id', 'string', '');

        if ($clear === 'ALL') {
            $ctId = 'ALL';
            $this->session->set('REF_CAR_VALUE_CAR_TYPE_ID', $ctId);
            return $this->response->redirect('/ref_car_value/');
        }

        if ($carTypeIdQuery !== '') {
            $ctId = $carTypeIdQuery; // строка "ALL" или id
            $this->session->set('REF_CAR_VALUE_CAR_TYPE_ID', $ctId);
        }

        $page = $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(['v' => RefCarValue::class])
            ->where('v.id <> 0')
            ->orderBy('v.car_type');

        if ($ctId !== 'ALL') {
            if (ctype_digit($ctId)) {
                $builder->andWhere('v.car_type = :ct:', ['ct' => (int)$ctId]);
            } else {
                // защита на случай мусора
                $builder->andWhere('1 = 0');
            }
        }

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => 10,
            'page'    => $page,
        ]);
        $pageObj = $paginator->paginate();

        $this->view->setVars([
            'page'        => $pageObj,
            'car_types'   => RefCarType::find(),
            'car_type_id' => $ctId, // для selected в <select>
        ]);
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
    }

    public function editAction(int $id): ?ResponseInterface
    {
        // только GET — показать форму
        if (!$this->request->isPost()) {
            $ref = RefCarValue::findFirstById($id);
            if (!$ref) {
                $this->flash->error('Коэффициент не найден.');
                return $this->response->redirect('/ref_car_value/index/');
            }

            // передаём объект во view
            $this->view->setVars([
                'id' => (int)$ref->id,
                'car_type' => (string)$ref->car_type,
                'volume_start' => (float)$ref->volume_start,
                'volume_end' => (float)$ref->volume_end,
                'price' => (float)$ref->price,
                'k' => (float)$ref->k,
                'k_2022' => (float)$ref->k_2022,
            ]);

            return null; // отрисует view по умолчанию
        }

        // POST — сохранить изменения
        $ref = RefCarValue::findFirstById($id);
        if (!$ref) {
            $this->flash->error('Коэффициент не найден.');
            return $this->response->redirect('/ref_car_value/index/');
        }

        $ref->car_type = trim((string)$this->request->getPost('car_type', 'string'));
        $ref->volume_start = (float)$this->request->getPost('volume_start', 'float');
        $ref->volume_end = (float)$this->request->getPost('volume_end', 'float');
        $ref->price = (float)$this->request->getPost('price', 'float');
        $ref->k = (float)$this->request->getPost('k', 'float');
        $ref->k_2022 = (float)$this->request->getPost('k_2022', 'float');

        if ($ref->save() === false) {
            foreach ($ref->getMessages() as $m) {
                $this->flash->error($m->getMessage());
            }
            return $this->response->redirect('/ref_car_value/edit/' . $id);
        }

        $this->flash->success('Коэффициент обновлён.');
        return $this->response->redirect('/ref_car_value/index/');
    }

    /**
     * Creates a new ref_car_value
     */
    public function createAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/ref_car_value/index/');
        }

        // сырой ввод
        $carType     = trim((string)$this->request->getPost('car_type', 'string', ''));
        $vStartRaw   = trim((string)$this->request->getPost('volume_start', 'string', ''));
        $vEndRaw     = trim((string)$this->request->getPost('volume_end', 'string', ''));
        $priceRaw    = trim((string)$this->request->getPost('price', 'string', ''));
        $kRaw        = trim((string)$this->request->getPost('k', 'string', ''));
        $k2022Raw    = trim((string)$this->request->getPost('k_2022', 'string', ''));

        // нормализация чисел: убираем пробелы, NBSP, запятую → точку
        $cleanup = function (string $s): string {
            $s = str_replace(["\xC2\xA0", ' '], '', $s);
            return str_replace(',', '.', $s);
        };
        $vStart   = $cleanup($vStartRaw);
        $vEnd     = $cleanup($vEndRaw);
        $price    = $cleanup($priceRaw);
        $k        = $cleanup($kRaw);
        $k2022    = $cleanup($k2022Raw);

        $isNum = function (string $s): bool {
            return $s !== '' && preg_match('/^-?\d+(\.\d+)?$/', $s) === 1;
        };

        $errors = [];

        // required
        if ($carType === '')             { $errors[] = 'Поле "Тип ТС" обязательно.'; }
        if (!$isNum($vStart))            { $errors[] = 'Поле "Объём от" должно быть числом.'; }
        if (!$isNum($vEnd))              { $errors[] = 'Поле "Объём до" должно быть числом.'; }
        if (!$isNum($price))             { $errors[] = 'Поле "Цена" должно быть числом.'; }

        // optional числовые
        if ($k !== '' && !$isNum($k))          { $errors[] = 'Поле "k" должно быть числом.'; }
        if ($k2022 !== '' && !$isNum($k2022))  { $errors[] = 'Поле "k 2022" должно быть числом.'; }

        // диапазоны
        if ($isNum($vStart) && (float)$vStart < 0) { $errors[] = 'Поле "Объём от" не может быть отрицательным.'; }
        if ($isNum($vEnd)   && (float)$vEnd   < 0) { $errors[] = 'Поле "Объём до" не может быть отрицательным.'; }
        if ($isNum($price)  && (float)$price  < 0) { $errors[] = 'Поле "Цена" не может быть отрицательной.'; }
        if ($k !== ''     && $isNum($k)     && (float)$k     < 0) { $errors[] = 'Поле "k" не может быть отрицательным.'; }
        if ($k2022 !== '' && $isNum($k2022) && (float)$k2022 < 0) { $errors[] = 'Поле "k 2022" не может быть отрицательным.'; }

        // зависимость: конец ≥ начало
        if ($isNum($vStart) && $isNum($vEnd) && (float)$vEnd < (float)$vStart) {
            $errors[] = 'Поле "Объём до" должно быть больше или равно "Объём от".';
        }

        if ($errors) {
            foreach ($errors as $e) { $this->flash->error($e); }
            return $this->response->redirect('/ref_car_value/new/');
        }

        // запись
        $ref = new RefCarValue();
        $ref->car_type     = $carType;
        $ref->volume_start = (float)$vStart;
        $ref->volume_end   = (float)$vEnd;
        $ref->price        = (float)$price;
        $ref->k            = ($k === '') ? null : (float)$k;
        $ref->k_2022       = ($k2022 === '') ? null : (float)$k2022;

        if (!$ref->save()) {
            foreach ($ref->getMessages() as $m) { $this->flash->error($m->getMessage()); }
            return $this->response->redirect('/ref_car_value/new/');
        }

        $this->flash->success('Коэффициент добавлен.');
        return $this->response->redirect('/ref_car_value/index/');
    }

    /**
     * Saves a ref_car_value edited
     *
     */
    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_car_value/index/");
        }

        $id = $this->request->getPost("id");

        $ref_car_value = RefCarValue::findFirstByid($id);
        if (!$ref_car_value) {
            $this->flash->error("Коэффициент не существует: " . $id);
            return $this->response->redirect("/ref_car_value/index/");
        }

        $ref_car_value->car_type = $this->request->getPost("car_type");
        $ref_car_value->volume_start = $this->request->getPost("volume_start");
        $ref_car_value->volume_end = str_replace(',', '.', $this->request->getPost("volume_end"));
        $ref_car_value->price = str_replace(',', '.', $this->request->getPost("price"));
        $ref_car_value->k = str_replace(',', '.', $this->request->getPost("k"));
        $ref_car_value->k_2022 = str_replace(',', '.', $this->request->getPost("k_2022"));

        if (!$ref_car_value->save()) {
            foreach ($ref_car_value->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_car_value/edit/$ref_car_value->id");
        }

        $this->flash->success("Коэффициент изменен успешно.");
        return $this->response->redirect("/ref_car_value/index/");
    }

    /**
     * Deletes a ref_car_value
     *
     * @param string $id
     */
    public function deleteAction($id)
    {

        $ref_car_value = RefCarValue::findFirstByid($id);
        if (!$ref_car_value) {
            $this->flash->error("Коэффициент не найден.");
            return $this->response->redirect("/ref_car_value/index/");
        }

        if (!$ref_car_value->delete()) {
            foreach ($ref_car_value->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->response->redirect("/ref_car_value/index/");
        }

        $this->flash->success("Коэффициент удален успешно.");
        return $this->response->redirect("/ref_car_value/index/");
    }

}
