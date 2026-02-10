<?php
namespace App\Controllers;

use Car;
use ControllerBase;
use Phalcon\Http\ResponseInterface;
use RefCarCat;
use Transaction;
use User;

class IndexController extends ControllerBase
{
    /**
     * Главная страница
     * @return void
     */
    public function indexAction(): ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$auth) {
            return $this->response->redirect($this->url->get('/session/index'));
        }

        if ($auth->isAdmin()) {
            return $this->response->redirect($this->url->get('/settings/index/'));
        }

        if ($auth->isModerator()) {
            return $this->response->redirect($this->url->get('/moderator_main/index/'));
        }

        if ($auth->isAgent()) {
            return $this->response->redirect($this->url->get('/home/index/'));
        }

        if ($auth->isAccountant()) {
            return $this->response->redirect($this->url->get('/accountant_main/index/'));
        }

        // fallback
        return $this->response->redirect($this->url->get('/home/index/'));
    }

    public
    function ajaxCheckAction()
    {
        $this->view->disable();
        $t = $this->translator;

        $allowedOrigins = ['https://recycle.kz', 'https://app.recycle.kz'];
        if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        }

        // ответ по умолчанию
        $r = array('found' => false);

        if ($this->request->isPost()) {
            $vin = $this->request->getPost("vin");

            $car = Car::findFirstByVin($vin);

            if (!$car || $car->status == "CANCELLED") {
                return json_encode($r);
            }

            $tr = Transaction::findFirstByProfileId($car->profile_id);
            if (!$tr || $tr->approve != 'GLOBAL') {
                return json_encode($r);
            }

            $cat = RefCarCat::findFirstById($car->ref_car_cat);
            if (!$cat) {
                return json_encode($r);
            }

            // седельность
            $sed_t = '';
            if ($car->ref_car_type_id == 2 && $car->ref_st_type == 1) {
                $sed_t = ' (седельный тягач)';
            }
            if ($car->ref_car_type_id == 2 && $car->ref_st_type == 0) {
                $sed_t = ' (не седельный тягач)';
            }
            if ($car->ref_car_type_id == 2 && $car->ref_st_type == 2) {
                $sed_t = ' (седельный тягач (Международные перевозки))';
            }

            $dt = '-';
            if ($tr->dt_approve > 0) {
                $dt = date('d.m.Y', $tr->dt_approve);
            } else {
                $dt = date('d.m.Y', $tr->date);
            }

            $found = true;

            if ($tr->amount == 0) {
                if ($car->volume > 0) {
                    if ($car->electric_car == 1 || $car->ref_st_type == 2) {
                        $found = true;
                    } else {
                        $found = false;
                    }
                } elseif ($car->volume == 0) {
                    if ($car->date_import < ROP_ELECTRIC_CAR_DATE) {
                        $found = false;
                    }
                }
            }

            $this->logAction('Проверка статуса платежа по VIN коду из сайта recycle.kz', 'access');

            if ($car && $tr && $cat && $tr->approve == 'GLOBAL') {
                $r = array(
                    'vin' => $car->vin,
                    'volume' => $car->volume,
                    'date' => $dt,
                    'order' => $car->id . ' (заявка #' . $car->profile_id . ')',
                    'category' => $t->_($cat->name) . $sed_t,
                    'found' => $found
                );
            }
        }

        echo json_encode($r);
    }
}
