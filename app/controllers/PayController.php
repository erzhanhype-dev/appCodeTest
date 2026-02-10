<?php
namespace App\Controllers;

use App\Services\Pdf\PdfService;
use Car;
use ContactDetail;
use ControllerBase;
use Goods;
use Profile;
use RefFund;
use Transaction;
use UniqueContract;
use User;


// TODO:20 Символ тенге в формах.

class PayController extends ControllerBase
{

    public function invoiceAction($transaction_id)
    {
        $auth = User::getUserBySession();

        if (!$auth->isEmployee()) {
            $profile = Profile::find(array(
                'conditions' => "id = $transaction_id",
                'columns' => 'user_id'
            ));

            if (count($profile) > 0) {
                if ($profile[0]['user_id'] != $auth->id) {
                    $this->flash->error("У вас нет прав на это действие!.");
                    return $this->response->redirect("/home");
                }
            } else {
                $this->flash->error("Заявка не найдена!.");
                return $this->response->redirect("/home");
            }
        }

        $this->view->setVars(array(
            "tr" => $transaction_id
        ));
    }

    // выдача платежного поручения
    public function printAction($transaction_id)
    {
        // отключаем вью и подгружаем данные
        $this->view->disable();
        $auth = User::getUserBySession();

        if (!$auth->isEmployee()) {
            $profile = Profile::find(array(
                'conditions' => "id = $transaction_id",
                'columns' => 'user_id'
            ));

            if (count($profile) > 0) {
                if ($profile[0]['user_id'] != $auth->id) {
                    $this->flash->error("У вас нет прав на это действие!.");
                    return $this->response->redirect("/home");
                }
            } else {
                $this->flash->error("Заявка не найдена!.");
                return $this->response->redirect("/home");
            }
        }

        // полноценный запрос
        $query = $this->modelsManager->createQuery("
        SELECT t.id AS t_id,
            t.md_dt_sent AS t_date,
            t.amount AS t_amount,
            u.user_type_id AS t_user_type,
            u.id AS t_user_id,
             u.role_id AS role_id,
            r.name AS role_name,
            p.agent_name AS agent_name,
            p.agent_iin AS agent_iin,
            p.agent_address AS agent_address,
            p.id AS p_id
        FROM Transaction t
        JOIN Profile p
        JOIN User u
         JOIN Role r ON u.role_id = r.id
            WHERE t.profile_id = :tid:
            AND t.profile_id = p.id
            AND p.user_id = u.id
      ");

        $d = $query->execute(array(
            "tid" => $transaction_id
        ));

        $profile = Profile::findFirstById($d[0]->p_id);
        $tr = Transaction::findFirstById($transaction_id);
        if (
            $profile->blocked === 0
            && in_array($tr->approve, ['REVIEW', 'APPROVE', 'CERT_FORMATION', 'GLOBAL'])
        ) {
            $profile->blocked = 1;
        }
        $profile->save();

        // общие данные
        $po_num = $d[0]->p_id;
        if ($d[0]->t_date > 0) {
            $po_date = date("d.m.Y", $d[0]->t_date);
        } else {
            $po_date = date("d.m.Y", $profile->created);
        }

        $po_to = ZHASYL_DAMU;
        $po_to_in = ZHASYL_DAMU_BIN;
        $po_to_bank = ZHASYL_DAMU_BANK;
        $po_to_kbe = ZHASYL_DAMU_KBE;
        $po_to_bik = ZHASYL_DAMU_BIK;
        $po_amount = $d[0]->t_amount;
        $po_sum_text = __ucFirst(__numToStr($d[0]->t_amount));
        $po_target = 'Плата за организацию сбора, транспортировки, переработки, обезвреживания, использования и утилизации отходов, согласно заявки #' . $d[0]->p_id . ' от ' . date("d.m.Y",
                $d[0]->t_date);
        $po_knp = ZHASYL_DAMU_KNP;

        if (count($d) == 1) {
            if ($d[0]->t_user_type == PERSON) {
                $query_dt = $this->modelsManager->createQuery("
                  SELECT iin, last_name, first_name, parent_name
                  FROM PersonDetail
                  WHERE user_id = :uid:
                  LIMIT 1
                  ");

                $dt = $query_dt->execute(array(
                    "uid" => $d[0]->t_user_id
                ));

                $po_from = $dt[0]->last_name . ' ' . $dt[0]->first_name . ' ' . $dt[0]->parent_name;
                $po_from_in = $dt[0]->iin;
                $po_from_bank = '';
                $po_from_iban = '';
                $po_from_kbe = 19;
                $po_from_bik = '';
            } else {
                if ($d[0]->t_user_type == COMPANY) {
                    $query_dt = $this->modelsManager->createQuery("
                      SELECT cd.bin AS bin, cd.name AS name, cd.iban AS iban,
                      b.name AS bank_name, b.bik AS bank_bik, k.kbe AS kbe
                      FROM CompanyDetail cd
                      JOIN RefBank b
                      JOIN RefKbe k
                      WHERE user_id = :uid:
                      AND b.id = cd.ref_bank_id
                      AND k.id = cd.ref_kbe_id
                      LIMIT 1
                      ");

                    $dt = $query_dt->execute(array(
                        "uid" => $d[0]->t_user_id
                    ));

                    if (!count($dt)) {
                        $this->view->enable();
                        $this->flash->success("Заполните свои реквизиты, прежде чем запрашивать счет на оплату.");
                        return $this->response->redirect("/settings/index/");
                    }

                    $po_from = $dt[0]->name;
                    $po_from_in = $dt[0]->bin;
                    $po_from_bank = $dt[0]->bank_name;
                    $po_from_iban = $dt[0]->iban;
                    $po_from_kbe = $dt[0]->kbe;
                    $po_from_bik = $dt[0]->bank_bik;
                }
            }
        }

        if ($profile->type == 'CAR') {
            $check_agro = Car::findFirst(array(
                "ref_car_cat IN (13, 14) AND profile_id = :profile_id:",
                "bind" => array(
                    "profile_id" => $profile->id
                )
            ));

            if ($check_agro) {
                if ($profile->agent_status == 'NOT_SET' || $profile->agent_status == 'IMPORTER') {
                    $po_to_iban = IBAN_AGRO_IMPORTER;
                } else {
                    if ($profile->agent_status == 'VENDOR') {
                        $po_to_iban = IBAN_AGRO_VENDOR;
                    }
                }
            } else {
                if ($profile->agent_status == 'NOT_SET' || $profile->agent_status == 'IMPORTER') {
                    $po_to_iban = IBAN_PERSON;
                } elseif ($profile->agent_status == 'VENDOR') {
                    $car = Car::findFirstByProfileId($profile->id);
                    $firstCarYear = date('Y', $car->date_import);
                    $ref_fund = RefFund::find([
                        "idnum = :idnum: AND key = :key: AND year = :year:",
                        "bind" => array(
                            "idnum" => $po_from_in,
                            "key" => 'START',
                            "year" => $firstCarYear
                        )
                    ]);

                    if (isset($ref_fund) && count($ref_fund) > 0) {
                        $po_to_iban = IBAN_VENDOR_HAS_FUND;
                    } else {
                        $po_to_iban = IBAN_VENDOR_HAS_NOT_FUND;
                    }
                }
            }
        }

        if ($profile->type == 'GOODS') {
            if ($profile->agent_status == 'NOT_SET' || $profile->agent_status == 'IMPORTER') {
                $po_to_iban = IBAN_GOODS_IMPORTER;
            } else {
                if ($profile->agent_status == 'VENDOR') {
                    $po_to_iban = IBAN_GOODS_VENDOR;
                }
            }

            $goods = Goods::find([
                'conditions' => 'profile_id = :pid:',
                'bind' => ['pid' => $profile->id],
            ]);

            if ($goods) {
                $tiresCodes = ['401110000', '401120', '4011800000', '4011900000'];

                $hasTires = false;
                foreach ($goods as $good) {
                    if (in_array($good->ref_tn_code->code, $tiresCodes, true)) {
                        $hasTires = true;
                        break;
                    }
                }

                if ($profile->agent_status == 'VENDOR') {
                    if ($hasTires) {
                        $po_to_iban = IBAN_KPP_IMPORTER;
                    }
                }
            }
        }

        if ($profile->type == 'KPP') {
            if ($profile->agent_status == 'IMPORTER') {
                $po_to_iban = IBAN_KPP_IMPORTER;
            } else {
                if ($profile->agent_status == 'VENDOR') {
                    $po_to_iban = IBAN_KPP_VENDOR;
                }
            }
        }

        $cinfo = ContactDetail::findFirstByUserId($d[0]->t_user_id);
        $po_from_address = "г. " . $cinfo->reg_city . ", " . $cinfo->reg_address;

        if ($d[0]->role_name == 'agent' || $d[0]->role_name == 'admin_soft') {
            $po_from = $d[0]->agent_name;
            $po_from_in = $d[0]->agent_iin;
            $po_from_address = $d[0]->agent_address;
        }

        $src = APP_PATH . '/app/templates/html/payment_order/payment.html';
        if($profile->created > 1759896600){
            $src = APP_PATH . '/app/templates/html/payment_order/payment_new.html';
        }
        $dst = APP_PATH . '/storage/temp/payment_' . $d[0]->p_id . '.html';
        $sheet = file_get_contents($src);

        // замена данных
        $po_from = str_replace('&', '&amp;', $po_from);
        $sheet = str_replace('[PO_NUM]', $po_num, $sheet);
        $sheet = str_replace('[PO_DATE]', $po_date, $sheet);
        $sheet = str_replace('[PO_FROM]', $po_from, $sheet);
        $sheet = str_replace('[PO_FROM_IN]', $po_from_in, $sheet);
        $sheet = str_replace('[PO_FROM_BANK]', $po_from_bank, $sheet);
        $sheet = str_replace('[PO_FROM_IBAN]', $po_from_iban, $sheet);
        $sheet = str_replace('[PO_FROM_KBE]', $po_from_kbe, $sheet);
        $sheet = str_replace('[PO_FROM_BIK]', $po_from_bik, $sheet);
        $sheet = str_replace('[PO_FROM_ADDRESS]', $po_from_address, $sheet);
        $sheet = str_replace('[PO_TO]', $po_to, $sheet);
        $sheet = str_replace('[PO_TO_IN]', $po_to_in, $sheet);
        $sheet = str_replace('[PO_TO_BANK]', $po_to_bank, $sheet);
        $sheet = str_replace('[PO_TO_IBAN]', $po_to_iban, $sheet);
        $sheet = str_replace('[PO_TO_KBE]', $po_to_kbe, $sheet);
        $sheet = str_replace('[PO_TO_BIK]', $po_to_bik, $sheet);
        $sheet = str_replace('[PO_AMOUNT]', number_format($po_amount, 2, ",", " "), $sheet);
        $sheet = str_replace('[PO_SUM_TEXT]', $po_sum_text, $sheet);
        $sheet = str_replace('[PO_TARGET]', $po_target, $sheet);
        $sheet = str_replace('[PO_KNP]', $po_knp, $sheet);
        $sheet = str_replace('[ROP_ADDRESS]', ZHASYL_DAMU_ADDRESS, $sheet);

        $c_info = UniqueContract::findFirst([
            "bin = '" . $po_from_in . "'"
        ]);

        if ($c_info && $c_info->bin) {
            $sheet = str_replace('[PO_CONTRACT_NUM]', $c_info->contract, $sheet);
        } else {
            $sheet = str_replace('[PO_CONTRACT_NUM]', 'Типовой договор от 14 ноября 2024 года', $sheet);
        }

        if ($profile->created > ROP_VAT_DATE) {
            $sum = $tr->amount;
            $vat = $sum - ($sum * 100 / 112);
            $sheet = str_replace('[VAT_P]', ZHASYL_DAMU_VAT_P . '%', $sheet);
            $sheet = str_replace('[VAT_SUM]', number_format($vat, 2, ",", " "), $sheet);
            $sheet = str_replace('[VAT_CERT]', ', ' . ZHASYL_DAMU_VAT_CERT, $sheet);
            $sheet = str_replace('[VAT_HELPER]', 'Сумма НДС:', $sheet);
        } else {
            $sheet = str_replace('[VAT_P]', '', $sheet);
            $sheet = str_replace('[VAT_SUM]', '', $sheet);
            $sheet = str_replace('[VAT_CERT]', '', $sheet);
            $sheet = str_replace('[VAT_HELPER]', '', $sheet);
        }

        $this->logAction('Скачивание счета на оплату','access');
        file_put_contents($dst, $sheet);
        (new PdfService())->generate($dst, APP_PATH . '/storage/temp/payment_' . $d[0]->p_id . '.pdf');

        __downloadFile(APP_PATH . '/storage/temp/payment_' . $d[0]->p_id . '.pdf');

    }

}
