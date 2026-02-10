<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefVinMask;
use User;

class RefVinMaskController extends ControllerBase
{
    public function indexAction(): void
    {
        $page   = max(1, (int)$this->request->getQuery('page', 'int', 1));
        $limit  = max(1, (int)$this->request->getQuery('limit', 'int', 20));

        $name   = trim((string)$this->request->getQuery('name', 'string', ''));
        $status = trim((string)$this->request->getQuery('status', 'string', ''));

        $sort   = $this->request->getQuery('sort', 'string', 'id');
        $dirRaw = strtolower($this->request->getQuery('dir', 'string', 'desc'));
        $dir    = in_array($dirRaw, ['asc','desc'], true) ? $dirRaw : 'desc';

        $whitelist = [
            'id'         => 'm.id',
            'name'       => 'm.name',
            'status'     => 'm.status',
            'created_at' => 'm.created_at',
            'deleted_at' => 'm.deleted_at',
        ];
        $orderBy = $whitelist[$sort] ?? 'm.id';

        $conds = ['m.id <> 0'];
        $bind  = [];
        if ($name !== '')   { $conds[] = 'm.name LIKE :name:';     $bind['name'] = '%'.$name.'%'; }
        if ($status !== '') { $conds[] = 'm.status = :status:';    $bind['status'] = $status; }
        $where = implode(' AND ', $conds);

        $builder = $this->modelsManager->createBuilder()
            ->from(['m' => RefVinMask::class])
            ->leftJoin(User::class, 'm.created_by = created_user.id', 'created_user')
            ->leftJoin(User::class, 'm.deleted_by = deleted_user.id', 'deleted_user')
            ->columns([
                'id'                 => 'm.id',
                'name'               => 'm.name',
                'status'             => 'm.status',
                'created_at'         => 'm.created_at',
                'deleted_at'         => 'm.deleted_at',
                'created_by'         => 'IF(created_user.user_type_id = 1, created_user.fio, created_user.org_name)',
                'created_user_idnum' => 'created_user.idnum',
                'deleted_by'         => 'IF(deleted_user.user_type_id = 1, deleted_user.fio, deleted_user.org_name)',
                'deleted_user_idnum' => 'deleted_user.idnum',
            ])
            ->where($where, $bind)
            ->orderBy($orderBy.' '.$dir);

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => $limit,
            'page'    => $page,
        ]);
        $pageObj = $paginator->paginate();

        $this->view->setVars([
            'page'          => $pageObj,
            'limit'         => $limit,
            'filters'       => ['name' => $name, 'status' => $status],
            'sort'          => $sort,
            'dir'           => $dir,
            'totalAll'      => (int) RefVinMask::count(),
            'totalFiltered' => (int) $pageObj->getTotalItems(),
        ]);
    }

    public function createAction()
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        if($this->request->isPost()) {
            $mask = strtoupper($this->request->getPost('mask'));

            $check = RefVinMask::findFirstByName($mask);
            $createdBy = null;
            if ($auth && is_scalar($auth->id) && preg_match('/^\d+$/', (string)$auth->id)) {
                $createdBy = (int) $auth->id;
            }
            if($check) {
                $this->flash->success("Маска(VIN) <b>$mask</b> уже есть в базе, вы не можете добавить повторно !");
            }else{
                $vin_mask = new RefVinMask();
                $vin_mask->name = $mask;
                $vin_mask->created_by = $createdBy;
                $vin_mask->created_at = time();
                $vin_mask->status = "ACTIVE";

                if($vin_mask->save()) {
                    $this->flash->success("Маска(VIN) <b>$mask</b> успешно добавлено !");
                }
            }
        }
        return $this->response->redirect("/ref_vin_mask/");
    }

    /**
     *
     * @param string $id
     */
    public function deleteAction($id)
    {
        $auth = User::getUserBySession();

        if(!$auth->isSuperModerator()){
            $this->flash->error("У вас нет прав на это действие");
            return $this->response->redirect("/ref_vin_mask/");
        }
		
        $vin_mask = RefVinMask::findFirstByid($id);
        $mask_name = $vin_mask->name;
        $vin_mask->deleted_by = $auth->id;
        $vin_mask->name = $vin_mask->name."_DELETED_".time();
        $vin_mask->deleted_at = time();
        $vin_mask->status = 'DELETED';
        $vin_mask->save();

        $this->flash->success("Маска(VIN): <b> $mask_name </b> удалена.");
        return $this->response->redirect("/ref_vin_mask/");
    }
}
