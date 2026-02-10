<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefCountry;

class RefCountryController extends ControllerBase
{

    public function indexAction()
    {
        // чтение и установка фильтров
        if ($this->request->isPost()) {
            $num = trim((string)$this->request->getPost('num', 'string', ''));
            $isCustomUnion = $this->request->getPost('is_custom_union', 'string', '');
            $reset = $this->request->getPost('reset', 'string', '');

            if ($reset === 'all') {
                $this->session->set('filter_country_id', '');
                $this->session->set('filter_is_custom_union', '');
                return $this->response->redirect('/ref_country/');
            }

            if ($num !== '') {
                $this->session->set('filter_country_id', $num); // "all" или ID
            }
            if ($isCustomUnion !== '') {
                $this->session->set('filter_is_custom_union', $isCustomUnion); // "all" | "0" | "1"
            }
        }

        // значения по умолчанию
        if (!$this->session->has('filter_country_id')) {
            $this->session->set('filter_country_id', '');
        }
        if (!$this->session->has('filter_is_custom_union')) {
            $this->session->set('filter_is_custom_union', '');
        }

        $countryId = (string)$this->session->get('filter_country_id');         // '' | 'all' | '123'
        $isCustomUnion = (string)$this->session->get('filter_is_custom_union');     // '' | 'all' | '0' | '1'
        $page = $this->request->getQuery('page', 'int', 1);

        // билдер
        $builder = $this->modelsManager->createBuilder()
            ->from(['c' => RefCountry::class])
            ->where('c.id <> 0')
            ->orderBy('c.name'); // при необходимости иное поле

        $bind = [];

        // фильтр по стране
        if ($countryId !== '' && $countryId !== 'all') {
            if (ctype_digit($countryId)) {
                $builder->andWhere('c.id = :cid:', ['cid' => (int)$countryId]);
            } else {
                // защита от мусора
                $builder->andWhere('1 = 0');
            }
        }

        // фильтр по is_custom_union (булево 0/1)
        if ($isCustomUnion !== '' && $isCustomUnion !== 'all') {
            if ($isCustomUnion === '0' || $isCustomUnion === '1') {
                $builder->andWhere('c.is_custom_union = :icu:', ['icu' => (int)$isCustomUnion]);
            } else {
                $builder->andWhere('1 = 0');
            }
        }

        // пагинация
        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 25,
            'page' => $page,
        ]);
        $pageObj = $paginator->paginate();

        // справочники
        $countries = RefCountry::find([
            'conditions' => 'id <> :id:',
            'bind' => ['id' => 1],
            'order' => 'name',
        ]);

        $this->view->setVars([
            'page' => $pageObj,
            'countries' => $countries,
            'car_type_id' => null, // не используется здесь, оставлено для совместимости
            'filter_country_id' => $countryId,
            'filter_is_custom_union' => $isCustomUnion,
        ]);
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
    }

    public function editAction(int $id)
    {
        $country = RefCountry::findFirstById($id);
        if (!$country) {
            $this->flash->error('Страна не найдена.');
            return $this->response->redirect('/ref_country/index/');
        }

        if (!$this->request->isPost()) {
            $this->view->setVars([
                'id'              => (int)$country->id,
                'name'            => (string)$country->name,
                'alpha2'          => (string)$country->alpha2,
                'is_custom_union' => (int)$country->is_custom_union,
                'begin_date'      => (string)$country->begin_date, // или форматируйте как нужно
                'end_date'        => (string)$country->end_date,
            ]);
            return null;
        }
    }


    /**
     * Creates a new ref_country
     */
    public function createAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_country/index/");
        }

        $is_custom_union = $this->request->getPost("is_custom_union");
        $begin_date = $this->request->getPost("period_start");
        $end_date = $this->request->getPost("period_end");

        $ref_country = new RefCountry();

        $ref_country->name = $this->request->getPost("name");
        $ref_country->alpha2 = $this->request->getPost("alpha2");
        if ($is_custom_union) {
            $ref_country->is_custom_union = $is_custom_union;
            if ($begin_date) {
                $ref_country->begin_date = $begin_date;
            }
            if ($end_date) {
                $ref_country->end_date = $end_date;
            }
        } else {
            $ref_country->is_custom_union = 0;
            $ref_country->begin_date = null;
            $ref_country->end_date = null;
        }

        if (!$ref_country->save()) {
            foreach ($ref_country->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_country/new/");
        }

        $this->flash->success("Страна создана успешно.");
        return $this->response->redirect("/ref_country/index/");
    }

    /**
     * Saves a ref_country edited
     *
     */
    public function saveAction()
    {

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_country/index/");
        }

        $id = $this->request->getPost("id");
        $begin_date = $this->request->getPost("begin_date");
        $end_date = $this->request->getPost("end_date");

        $ref_country = RefCountry::findFirstByid($id);
        if (!$ref_country) {
            $this->flash->error("Страна не существует: " . $id);
            return $this->response->redirect("/ref_country/index/");
        }

        $ref_country->name = $this->request->getPost("name");
        $ref_country->alpha2 = $this->request->getPost("alpha2");
        $ref_country->is_custom_union = $this->request->getPost("is_custom_union");
        if ($this->request->getPost("begin_date")) {
            $ref_country->begin_date = $begin_date;
        } else {
            $ref_country->begin_date = null;
        }
        if ($this->request->getPost("end_date")) {
            $ref_country->end_date = $end_date;
        } else {
            $ref_country->end_date = null;
        }

        if (!$ref_country->save()) {
            foreach ($ref_country->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_country/edit/$ref_country->id");
        }

        $this->flash->success("Страна изменена успешно.");
        return $this->response->redirect("/ref_country/index/");
    }
}
