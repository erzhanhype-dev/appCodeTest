<?php
namespace App\Controllers;

use ControllerBase;
use RefBankBlackList;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use User;

class RefBankBlackListController extends ControllerBase
{
    public function indexAction(): void
    {
        $page   = $this->request->getQuery('page', 'int', 1);
        $limit  = $this->request->getQuery('limit', 'int', 20);
        $idnum  = trim((string)$this->request->getQuery('idnum', 'string', ''));
        $status = trim((string)$this->request->getQuery('status', 'string', ''));

        $conds = ['list.id <> 0'];
        $bind  = [];

        if ($idnum !== '') {
            // при желании оставить только цифры:
            // $idnum = preg_replace('/\D+/', '', $idnum) ?? '';
            $conds[]     = 'list.idnum LIKE :idnum:';
            $bind['idnum'] = '%' . $idnum . '%';
        }
        if ($status !== '') {
            $conds[]      = 'list.status = :status:';
            $bind['status'] = $status;
        }

        $where = implode(' AND ', $conds);

        $builder = $this->modelsManager->createBuilder()
            ->from(['list' => RefBankBlackList::class])
            ->leftJoin(User::class, 'list.created_by = cu.id', 'cu')
            ->leftJoin(User::class, 'list.deleted_by = du.id', 'du')
            ->columns([
                'list.id AS id',
                'list.idnum AS idnum',
                'list.status AS status',
                'list.created_at AS created_at',
                'list.deleted_at AS deleted_at',
                'IF(cu.user_type_id = 1, cu.fio, cu.org_name) AS created_by',
                'cu.idnum AS created_user_idnum',
                'IF(du.user_type_id = 1, du.fio, du.org_name) AS deleted_by',
                'du.idnum AS deleted_user_idnum',
            ])
            ->where($where, $bind)
            ->orderBy('list.id DESC');

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => $limit,
            'page'    => $page,
        ]);

        $this->view->setVars([
            'page'   => $paginator->paginate(),
            'idnum'  => $idnum,
            'status' => $status,
            'limit'  => $limit,
        ]);
    }

    public function createAction()
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        if($this->request->isPost()) {
            $idnum = $this->request->getPost('idnum');

            $check = RefBankBlackList::findFirstByIdnum($idnum);

            if($check) {
                $this->flash->error( "ИИН / БИН <b>$idnum</b> уже есть в базе, вы не можете добавить повторно !");
            }else{
                $black_list = new RefBankBlackList();
                $black_list->idnum = $idnum;
                $black_list->created_by = $auth->id;
                $black_list->created_at = time();
                $black_list->status = "ACTIVE";

                if($black_list->save()) {
                    $this->flash->success("ИИН / БИН <b>$idnum</b> успешно добавлено !");
                }
            }
        }

        return $this->response->redirect("/ref_bank_black_list");
    }

    /**
     *
     * @param string $id
     */
    public function deleteAction($id)
    {
        $auth = User::getUserBySession();

        if(!$auth->isAdminSoft()){
            $this->flash->error("У вас нет прав на это действие");
            return $this->response->redirect("/ref_bank_black_list/");
        }
		
        $black_list = RefBankBlackList::findFirstById($id);
        $idnum = $black_list->idnum;
        $black_list->deleted_by = $auth->id;
        $black_list->idnum = $black_list->idnum."_DELETED_".time();
        $black_list->deleted_at = time();
        $black_list->status = 'DELETED';
        $black_list->save();

        $this->flash->success("ИИН / БИН: <b> $idnum </b> удален.");
        return $this->response->redirect("/ref_bank_black_list/");
    }
}
