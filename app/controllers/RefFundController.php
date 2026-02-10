<?php

namespace App\Controllers;

use CompanyDetail;
use ControllerBase;
use FundCar;
use FundProfile;
use PersonDetail;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefFund;
use RefFundKeys;
use RefFundLogs;
use User;

class RefFundController extends ControllerBase
{
    public function indexAction(): void
    {
        $s = $this->session;
        $req = $this->request;

        // 1) Параметры и сессия
            $s->set('REF_FUND_SESSION_IDNUM', (int)$req->getPost('idnum', 'int', 0));
            $s->set('REF_FUND_SESSION_KEY', $req->getPost('key', 'string', 'all'));
            $s->set('REF_FUND_SESSION_BEGIN', $req->getPost('begin', 'string', ''));
            $s->set('REF_FUND_SESSION_END', $req->getPost('end', 'string', ''));
            $s->set('REF_FUND_SESSION_OBJECT_TYPE', $req->getPost('type', 'string', 'all'));

            if ($req->getPost('reset', 'string') === 'all') {
                $s->set('REF_FUND_SESSION_IDNUM', 0);
                $s->set('REF_FUND_SESSION_KEY', 'all');
                $s->set('REF_FUND_SESSION_KEY_TN_CODE', 'all');
                $s->set('REF_FUND_SESSION_BEGIN', '');
                $s->set('REF_FUND_SESSION_END', '');
                $s->set('REF_FUND_SESSION_OBJECT_TYPE', 'all');
            }

        $year = (int)date('Y');
        $sId = (int)$s->get('REF_FUND_SESSION_IDNUM', 0);
        $sKey = (string)$s->get('REF_FUND_SESSION_KEY', 'all');
        $sTnKey = (string)$s->get('REF_FUND_SESSION_KEY_TN_CODE', 'all');
        $sType = (string)$s->get('REF_FUND_SESSION_OBJECT_TYPE', 'all');
        $sBeg = (string)$s->get('REF_FUND_SESSION_BEGIN', '');
        $sEnd = (string)$s->get('REF_FUND_SESSION_END', '');

        $sBegVal = $sBeg ? date('Y-m-d', strtotime($sBeg)) : '';
        $sEndVal = $sEnd ? date('Y-m-d', strtotime($sEnd)) : '';
        $dtBegin = $sBeg ? strtotime($sBeg . ' 00:00:00') : strtotime("$year-01-01 00:00:00");
        $dtEnd = $sEnd ? strtotime($sEnd . ' 23:59:59') : strtotime("$year-12-31 23:59:59");

        // 2) Фильтр-условия (одно место)
        $conds = ["r.id <> 0"];
        $bind = [];

        if ($sBeg) {
            $conds[] = "r.prod_start >= :dt_begin:";
            $bind['dt_begin'] = $dtBegin;
        }
        if ($sEnd) {
            $conds[] = "r.prod_end   <= :dt_end:";
            $bind['dt_end'] = $dtEnd;
        }
        if ($sId) {
            $conds[] = "r.idnum = :idnum:";
            $bind['idnum'] = $sId;
        }
        if ($sKey !== 'all') {
            $conds[] = "[r].[key] = :key:";
            $bind['key'] = $sKey;
        }
        if ($sTnKey !== 'all') {
            $conds[] = "[r].[key] = :tn_key:";
            $bind['tn_key'] = $sTnKey;
        }
        if ($sType !== 'all') {
            $conds[] = "r.entity_type = :type:";
            $bind['type'] = strtoupper($sType);
        }

        $where = implode(' AND ', $conds);

        // 3) Список с именем (агрегат + groupBy по r.id)
        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'r.id', 'r.idnum', 'r.key', 'r.entity_type', 'r.prod_start', 'r.prod_end', 'r.year', 'r.value',
                "COALESCE(MAX(cd.name), MAX(CONCAT(pd.last_name,' ',pd.first_name,' ',pd.parent_name)), '') AS name",
            ])
            ->from(['r' => RefFund::class])
            ->join(User::class, 'r.idnum = u.idnum', 'u')
            ->leftJoin(CompanyDetail::class, 'cd.user_id = u.id AND u.user_type_id = 2', 'cd')
            ->leftJoin(PersonDetail::class, 'pd.user_id = u.id AND u.user_type_id = 1', 'pd')
            ->where($where, $bind)
            ->groupBy('r.id')
            ->orderBy('r.id DESC');

        $page = (new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 10,
            'page' => (int)$req->getQuery('page', 'int', 1),
        ]))->paginate();

        // 4) Компании для селекта (COALESCE с дефолтом '')
        $companies = $this->modelsManager->createBuilder()
            ->columns([
                'r.idnum AS idnum',
                "COALESCE(MAX(cd.name), MAX(CONCAT(pd.last_name,' ',pd.first_name,' ',pd.parent_name)), '') AS name",
            ])
            ->from(['r' => RefFund::class])
            ->join(User::class, 'r.idnum = u.idnum', 'u')
            ->leftJoin(CompanyDetail::class, 'cd.user_id = u.id AND u.user_type_id = 2', 'cd')
            ->leftJoin(PersonDetail::class, 'pd.user_id = u.id AND u.user_type_id = 1', 'pd')
            ->groupBy('r.idnum')
            ->getQuery()->execute();

        // 5) Ключи
        $keyCond = '';
        $keyBind = [];
        if ($sType !== 'all') {
            $keyCond = 'entity_type = :entity_type: AND type = "INS"';
            $keyBind['entity_type'] = strtoupper($sType);
        }
        $keys = RefFundKeys::find(['conditions' => $keyCond, 'bind' => $keyBind]);

        // 6) Лимиты: минимум данных + быстрая агрегация в PHP
        /** @var ResultsetInterface $limitsRows */
        $limitsRows = $this->modelsManager->createBuilder()
            ->columns(['r.idnum AS idnum', 'SUM(r.value) AS limits_count'])
            ->from(['r' => RefFund::class])
            ->where('r.year = :year:', ['year' => $year])
            ->groupBy('r.idnum')
            ->getQuery()->execute();

        $limitsById = [];
        foreach ($limitsRows as $row) {
            $limitsById[(int)$row->idnum] = [
                'idnum' => (int)$row->idnum,
                'limits_count' => (int)$row->limits_count,
                'FUND_NEUTRAL' => 0,
                'FUND_DECLINED' => 0,
                'FUND_ANNULMENT' => 0,
                'FUND_TOSIGN' => 0,
                'FUND_REVIEW' => 0,
                'FUND_PREAPPROVED' => 0,
                'FUND_DONE' => 0,
            ];
        }

        $rows = $this->modelsManager->createBuilder()
            ->columns(['r.idnum AS idnum', 'f.approve AS approve'])
            ->from(['r' => RefFund::class])
            ->join(User::class, 'u.idnum = r.idnum', 'u')
            ->join(FundProfile::class, 'f.user_id = u.id', 'f')
            ->join(FundCar::class, 'c.fund_id = f.id', 'c')
            ->where('r.year = :year: AND f.created BETWEEN :dt_begin: AND :dt_end:', [
                'year' => $year, 'dt_begin' => $dtBegin, 'dt_end' => $dtEnd,
            ])->getQuery()->execute();

        foreach ($rows as $row) {
            $id = (int)$row->idnum;
            $limitsById[$id] ??= [
                'idnum' => $id,
                'limits_count' => 0,
                'FUND_NEUTRAL' => 0,
                'FUND_DECLINED' => 0,
                'FUND_ANNULMENT' => 0,
                'FUND_TOSIGN' => 0,
                'FUND_REVIEW' => 0,
                'FUND_PREAPPROVED' => 0,
                'FUND_DONE' => 0,
            ];
            switch ($row->approve) {
                case 'FUND_NEUTRAL':
                    $limitsById[$id]['FUND_NEUTRAL']++;
                    break;
                case 'FUND_DECLINED':
                    $limitsById[$id]['FUND_DECLINED']++;
                    break;
                case 'FUND_ANNULMENT':
                    $limitsById[$id]['FUND_ANNULMENT']++;
                    break;
                case 'FUND_TOSIGN':
                    $limitsById[$id]['FUND_TOSIGN']++;
                    break;
                case 'FUND_REVIEW':
                    $limitsById[$id]['FUND_REVIEW']++;
                    break;
                case 'FUND_PREAPPROVED':
                    $limitsById[$id]['FUND_PREAPPROVED']++;
                    break;
                case 'FUND_DONE':
                    $limitsById[$id]['FUND_DONE']++;
                    break;
            }
        }

        $limits = array_map(static function (array $r) {
            $r['available_limit'] =
                $r['limits_count']
                - $r['FUND_NEUTRAL']
                - $r['FUND_TOSIGN']
                - $r['FUND_REVIEW']
                - $r['FUND_PREAPPROVED']
                - $r['FUND_DONE'];
            return (object)$r;
        }, array_values($limitsById));

        // 7) Вью
        $this->view->setVars([
            's_idnum' => $sId,
            's_key' => $sKey,
            's_type' => $sType,
            's_begin_value' => $sBegVal,
            's_end_value' => $sEndVal,
            'companies' => $companies,
            'keys' => $keys,
            'page' => $page,
            'limits' => $limits,
        ]);
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
        $keys = RefFundKeys::find(array(
            "name NOT IN ('N_20001_50000_ST')",
            "order" => "name ASC, weight ASC"
        ));

        $this->view->setVars(array(
            "keys" => $keys,
        ));
    }

    public function createAction()
    {
        $auth = User::getUserBySession();

        $idnum = $this->request->getPost("idnum");
        $year = $this->request->getPost("year");
        $value = $this->request->getPost("value");
        $prod_start = strtotime($this->request->getPost("prod_start") . ' 00:00:00');
        $prod_end = strtotime($this->request->getPost("prod_end") . ' 23:59:59');
        $key = $this->request->getPost("key");
        $type = $this->request->getPost("type");

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_fund/index/");
        }

        if ($r_f = __checkRefFund($idnum, $prod_start, $prod_end, $year, $key)) {
            $this->flash->error("Лимит по $r_f->key для БИН: $r_f->idnum уже установлен.");
            return $this->response->redirect("/ref_fund/index/");
        } else {
            $ref_fund = new RefFund();
            $ref_fund->idnum = $idnum;
            $ref_fund->year = $year;
            $ref_fund->value = $value;
            $ref_fund->prod_start = $prod_start;
            $ref_fund->prod_end = $prod_end;
            $ref_fund->key = $key;
            $ref_fund->entity_type = strtoupper($type);

            if ($ref_fund->save()) {

                $user = User::findFirstByIdnum($idnum);
                $user->fund_user = 1;
                $user->save();

                // save log
                $l = new RefFundLogs();
                $l->user_id = $auth->id;
                $l->login = $auth->idnum;
                $l->action = 'CREATED';
                $l->dt = time();
                $l->meta_before = '—';
                $l->meta_after = json_encode(array($ref_fund));
                $l->save();
            }

            if (!$ref_fund->save()) {
                foreach ($ref_fund->getMessages() as $message) {
                    $this->flash->error($message);
                }
                return $this->response->redirect("/ref_fund/new/");
            }
            $this->flash->success("Лимит создан успешно.");
        }

        return $this->response->redirect("/ref_fund/index/");
    }

    public function saveAction(): ResponseInterface
    {
        $auth = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect("/ref_fund/index/");
        }

        $id = $this->request->getPost("id");
        $ref_fund = RefFund::findFirstByid($id);
        if (!$ref_fund) {
            $this->flash->error("Такого лимита не существует: " . $id);
            return $this->response->redirect("/ref_fund/index/");
        }

        $_before = json_encode(array($ref_fund));

        $ref_fund->idnum = $this->request->getPost("idnum");
        $ref_fund->year = $this->request->getPost("year");
        $ref_fund->value = $this->request->getPost("value");
        $ref_fund->key = $this->request->getPost("key");
        $ref_fund->prod_start = strtotime($this->request->getPost("prod_start") . ' 00:00:00');
        $ref_fund->prod_end = strtotime($this->request->getPost("prod_end") . ' 23:59:59');

        if (!$ref_fund->save()) {
            foreach ($ref_fund->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect("/ref_fund/new");
        }

        // save log
        $l = new RefFundLogs();
        $l->user_id = $auth->id;
        $l->login = $auth->idnum;
        $l->action = 'CORRECTION';
        $l->dt = time();
        $l->meta_before = $_before;
        $l->meta_after = json_encode(array($ref_fund));
        $l->save();

        $this->flash->success("Лимиты сохранены.");

        return $this->response->redirect("/ref_fund/index/");
    }

    public function deleteAction($id): ResponseInterface
    {
        $auth = User::getUserBySession();

        $can = true;
        if (!$auth->isSuperModerator()) {
            $can = false;
            $this->flash->error("У вас нет прав на это действие");
        }

        $ref_fund = RefFund::findFirstByid($id);
        $_before = json_encode(array($ref_fund));
        if (!$ref_fund) {
            $can = false;
            $this->flash->error("Лимит не найден.");
        }
        $idnum = $ref_fund->idnum;

        if ($can != false) {
            $ref_fund->delete();

            $ref_fund_exist = RefFund::findFirstByIdnum($idnum);
            if (!$ref_fund_exist) {
                $user = User::findFirstByIdnum($idnum);
                if ($user) {
                    $user->fund_user = 0;
                    $user->save();
                }
            }

            $l = new RefFundLogs();
            $l->user_id = $auth->id;
            $l->login = $auth->idnum;
            $l->action = 'DELETED';
            $l->dt = time();
            $l->meta_before = $_before;
            $l->meta_after = '—';
            $l->save();

            $this->flash->success("Лимит удален успешно.");
            if (!$ref_fund->delete()) {
                foreach ($ref_fund->getMessages() as $message) {
                    $this->flash->error($message);
                }
            }
        }

        return $this->response->redirect("/ref_fund/index/");
    }
}