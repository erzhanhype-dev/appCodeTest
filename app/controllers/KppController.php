<?php

/*******************************************************************************
 * Модуль для КПП для заявок.
 *******************************************************************************/




;

class KppController extends ControllerBase
{
    public function newAction($pid = 0)
    {
        $auth = User::getUserBySession();

        if ($pid == 0) {
            return $this->response->redirect("/order/index");
        }

        $profile = Profile::findFirstById($pid);

        if (!$profile || $auth->id != $profile->user_id) {
            $this->logAction("У вас нет прав на это действие!");
            $this->flash->error("У вас нет прав на это действие!");
            return $this->response->redirect("/order/index");
        }

        if (in_array('KPP', DEACTIVATED_PROFILE_TYPES)) {
            return $this->response->redirect('/order');
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            return $this->response->redirect("/order/index");
        }

        actualizeCurrencies();
        $tn_codes = RefTnCode::find(["code = 8544", 'order' => 'name', 'is_active' => '1']);
        $country = RefCountry::find(array('id NOT IN (1, 201)'));
        $request = CurrencyRequest::findFirst(['order' => 'id DESC']);
        $currencies = CurrencyEach::find([
            "conditions" => "request_id = ?1",
            "bind" => [
                1 => $request->id
            ]
        ]);

        // $filter_add = "(id > 107 AND id < 852 AND id <> 322) OR id IN (30, 31)";
        $filter = "code IS NOT NULL AND is_active = 1";

        $tn_codes_add = RefTnCode::find([
            $filter,
            'order' => 'name'
        ]);

        $this->view->setVars(array(
            "tn_codes" => $tn_codes,
            "pid" => $pid,
            "currencies" => $currencies,
            "country" => $country,
            "tn_codes_add" => $tn_codes_add
        ));
    }

    public function addAction()
    {
        $auth = User::getUserBySession();
        $this->view->disable();

        if (in_array('KPP', DEACTIVATED_PROFILE_TYPES)) {
            return $this->response->redirect('/order');
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь!");
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $pid = $this->request->getPost("profile");
            $profile = Profile::findFirstById($pid);

            if ($auth->idnum != $profile->user_id || $profile->blocked) {
                $this->logAction("Вы не имеете права создавать новый КПП.");
                $this->flash->error("Вы не имеете права создавать новый КПП.");

                $this->dispatcher->forward(array(
                    "controller" => "order",
                    "action" => "view",
                    "params" => array($profile->id)
                ));
            } else {
                unset($profile);

                $kpp_weight = (float)str_replace(',', '.', $this->request->getPost("kpp_weight"));
                $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
                $kpp_date = $this->request->getPost("kpp_date");
                $tn_code = $this->request->getPost("tn_code");
                $package_tn_id = $this->request->getPost("package_tn_id");
                $country = $this->request->getPost("kpp_country");
                $kpp_basis = $this->request->getPost("kpp_basis");
                $basis_date = $this->request->getPost("basis_date");
                $tn = RefTnCode::findFirstById($tn_code);
                $currency_type = $this->request->getPost("currency_type");
                $invoice_sum = (float)str_replace(',', '.', $this->request->getPost("sum"));
                $invoice_sum = round($invoice_sum, 2);
                $package_cost = 0;
                $kpp_amount = 0;

                if (strtotime($kpp_date) > time()) {
                    $this->flash->error('Дата импорта не может быть в будущем');
                    $this->response->redirect('/kpp/new');
                }

                if ($tn != false) {

                    $c = new Kpp();
                    if ($currency_type != 'KZT') {
                        $c->invoice_sum_currency = (float)$this->request->getPost("sum");
                        $currAr = currencyToTenge($currency_type, $invoice_sum, $kpp_date);
                        $invoice_sum = $currAr['sum'] > 0 ? $currAr['sum'] : 0;
                        $c->currency = $currAr['from'] . " " . $currency_type . " / " . $currAr['to'] . " KZT";
                    }

                    $c->profile_id = $pid;
                    $c->ref_tn = $tn->id;
                    $c->ref_country = $country;
                    $c->date_import = strtotime($kpp_date);
                    $c->weight = $kpp_weight;
                    $c->basis = $kpp_basis;
                    $c->basis_date = strtotime($basis_date);

                    if ($package_tn_id) {
                        $package_tn = RefTnCode::findFirstById($package_tn_id);
                        if ($package_tn) {
                            $package = Goods::calculateAmountByDate($kpp_date, $package_weight, json_encode($package_tn));
                            $c->package_tn_code = $package_tn->code;
                            $c->package_weight = $package_weight;
                            $package_cost = $package['sum'];
                            $c->package_cost = $package_cost;
                        }
                    }

                    // расчет от суммы
                    $sum = (float)$invoice_sum * 0.05;
                    $sum = round($sum, 2);
                    $kpp_amount = round($sum + $package_cost, 2);
                    $c->amount = $kpp_amount;
                    $c->invoice_sum = $invoice_sum;
                    $c->currency_type = $currency_type;
                    $ok = $c->save();

                    $tr = Transaction::findFirstByProfileId($pid);
                    $tr->amount = (float)$tr->amount + $kpp_amount;
                    $tr->save();

                    if ($ok) {
                        $this->logAction("Новая позиция добавлена");
                        $this->flash->success("Новая позиция добавлена");
                    } else {
                        $this->logAction("Невозможно сохранить новую позицию");
                        $this->flash->warning("Невозможно сохранить новую позицию");
                    }

                    return $this->response->redirect("order/view/{$pid}");
                }
            }
        } else {
            $this->flash->error("Прежде вам нужно заполнить форму.");

            $this->dispatcher->forward([
                "controller" => "order",
                "action" => "index"
            ]);
        }
    }

    public function editAction($kid = 0)
    {
        $auth = User::getUserBySession();

        if ($kid == 0) {
            return $this->response->redirect("/order/index");
        }

        if (in_array('KPP', DEACTIVATED_PROFILE_TYPES)) {
            return $this->response->redirect('/order');
        }

        if (in_array($auth->idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            $this->logAction("Заблокированный пользователь.");
            return $this->response->redirect("/order/index");
        }

        if ($this->request->isPost()) {
            $kpp = Kpp::findFirstById($kid);
            $profile = Profile::findFirstById($kpp->profile_id);

            $kpp_weight = (float)str_replace(',', '.', $this->request->getPost("kpp_weight"));
            $package_weight = (float)str_replace(',', '.', $this->request->getPost("package_weight"));
            $kpp_date = $this->request->getPost("kpp_date");
            $basis_date = $this->request->getPost("basis_date");
            $tn_code = $this->request->getPost("tn_code");
            $package_tn_id = $this->request->getPost("package_tn_id");
            $country = $this->request->getPost("kpp_country");
            $kpp_basis = $this->request->getPost("kpp_basis");
            $tn = RefTnCode::findFirstById($tn_code);
            $currency_type = $this->request->getPost("currency_type");
            $invoice_sum = (float)str_replace(',', '.', $this->request->getPost("sum"));
            $invoice_sum = round($invoice_sum, 2);
            $package_cost = 0;
            $kpp_amount = 0;

            if (strtotime($kpp_date) > time()) {
                $this->flash->error('Дата импорта не может быть в будущем');
                $this->response->redirect('/kpp/edit');
            }

            if ($tn != false) {

                if ($currency_type != 'KZT') {
                    $kpp->invoice_sum_currency = (float)$this->request->getPost("sum");
                    $currAr = currencyToTenge($currency_type, $invoice_sum, $kpp_date);
                    $invoice_sum = $currAr['sum'] > 0 ? $currAr['sum'] : 0;
                    $kpp->currency = $currAr['from'] . " " . $currency_type . " / " . $currAr['to'] . " KZT";
                }

                $kpp->ref_tn = $tn->id;
                $kpp->ref_country = $country;
                $kpp->date_import = strtotime($kpp_date);
                $kpp->weight = $kpp_weight;
                $kpp->basis = $kpp_basis;

                if ($package_tn_id) {
                    $package_tn = RefTnCode::findFirstById($package_tn_id);
                    if ($package_tn) {
                        $package = Goods::calculateAmountByDate($kpp_date, $package_weight, json_encode($package_tn));
                        $kpp->package_tn_code = $package_tn->code;
                        $kpp->package_weight = $package_weight;
                        $package_cost = $package['sum'];
                        $kpp->package_cost = $package_cost;
                    }
                }

                // расчет от суммы
                $sum = (float)$invoice_sum * 0.05;
                $sum = round($sum, 2);
                $old_amount = $kpp->amount;
                $kpp_amount = round($package_cost + $sum, 2);
                $kpp->amount = $kpp_amount;
                $kpp->invoice_sum = $invoice_sum;
                $kpp->currency_type = $currency_type;
                $ok = $kpp->save();

                $tr = Transaction::findFirstByProfileId($profile->id);
                $tr->amount = ((float)$tr->amount - $old_amount) + $kpp_amount;
                $tr->save();

                if ($ok) {
                    $this->logAction("Объект был изменен.");
                    $this->flash->success("Объект был изменен.");
                } else {
                    $this->logAction("Невозможно сохранить изменение.");
                    $this->flash->warning("Невозможно сохранить изменение.");
                }

                return $this->response->redirect("order/view/$profile->id");
            }
        } else {

            $kpp = Kpp::findFirstById($kid);
            $profile = Profile::findFirstById($kpp->profile_id);
            $tn_codes = RefTnCode::find(["code = 8544", 'order' => 'name', 'is_active = 1']);
            $country = RefCountry::find(array('id NOT IN (1, 201)'));
            $request = CurrencyRequest::findFirst(['order' => 'id DESC']);
            $currencies = CurrencyEach::find([
                "conditions" => "request_id = ?1",
                "bind" => [
                    1 => $request->id
                ]
            ]);

            //$filter_add = "(id > 107 AND id < 852 AND id <> 322) OR id IN (30, 31)";
            $filter = "code IS NOT NULL AND is_active = 1";

            $package_tn_codes = RefTnCode::find([
                $filter,
                'order' => 'code'
            ]);

            if ($auth->id != $profile->user_id || $profile->blocked) {
                $this->logAction("Вы не имеете права редактировать этот объект.");
                $this->flash->error("Вы не имеете права редактировать этот объект.");

                return $this->view->redirect('/order/index');
            }

            $this->view->setVars(array(
                "tn_codes" => $tn_codes,
                "kpp" => $kpp,
                "currencies" => $currencies,
                "country" => $country,
                "package_tn_codes" => $package_tn_codes
            ));
        }
    }

    public function deleteAction($gid = 0)
    {
        $auth = User::getUserBySession();

        if ($gid == 0) {
            return $this->response->redirect("/order/index");
        }

        $kpp = Kpp::findFirstById($gid);
        $profile = Profile::findFirstById($kpp->profile_id);

        if ($auth->id != $profile->user_id || $profile->blocked) {
            $this->logAction("Вы не имеете права удалять этот объект.");
            $this->flash->error("Вы не имеете права удалять этот объект.");

            $this->dispatcher->forward(array(
                "controller" => "order",
                "action" => "index"
            ));
        } else {
            $tr = Transaction::findFirstByProfileId($profile->id);
            $tr->amount = $tr->amount - $kpp->amount;
            $tr->save();

            if ($kpp->delete()) {
                $this->logAction("Удаление произошло успешно.");
                $this->flash->success("Удаление произошло успешно.");

                $this->dispatcher->forward(array(
                    "controller" => "order",
                    "action" => "view",
                    "params" => array($profile->id)
                ));
            }
        }
    }
}

