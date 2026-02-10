<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\ResponseInterface;
use RefManufacturer;
use User;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class RefManufacturersController extends ControllerBase
{

    /**
     * Index action
     */
    public function indexAction(): void
    {
        $page   = max(1, (int) $this->request->getQuery('page', 'int', 1));
        $limit  = max(1, (int) $this->request->getQuery('limit', 'int', 20));

        // фильтры из GET
        $idnum  = trim((string) $this->request->getQuery('idnum', 'string', ''));
        $status = trim((string) $this->request->getQuery('status', 'string', ''));

        // нормализация БИН: только цифры, опционально строго 12
        if ($idnum !== '') {
            $idnum = preg_replace('/\D+/', '', $idnum) ?? '';
            if (strlen($idnum) !== 12) {
                // мягко игнорируем некорректный ввод
                $idnum = '';
            }
        }

        $sort   = $this->request->getQuery('sort', 'string', 'id');
        $dirRaw = strtolower($this->request->getQuery('dir', 'string', 'desc'));
        $dir    = in_array($dirRaw, ['asc','desc'], true) ? $dirRaw : 'desc';

        $orderWhitelist = [
            'id'         => 'm.id',
            'idnum'      => 'm.idnum',
            'status'     => 'm.status',
            'created_at' => 'm.created_at',
            'deleted_at' => 'm.deleted_at',
        ];
        $orderBy = $orderWhitelist[$sort] ?? 'm.id';

        $conditions = ['m.id <> 0'];
        $bind = [];

        if ($idnum !== '') {
            $conditions[] = 'm.idnum = :idnum:';      // точный БИН быстрее
            $bind['idnum'] = $idnum;
        }
        if ($status !== '') {                         // пусто = любой статус
            $conditions[] = 'm.status = :status:';
            $bind['status'] = $status;
        }
        $where = implode(' AND ', $conditions);

        $builder = $this->modelsManager->createBuilder()
            ->from(['m' => RefManufacturer::class])
            ->leftJoin(User::class, 'm.created_by = created_user.id', 'created_user')
            ->leftJoin(User::class, 'm.deleted_by = deleted_user.id', 'deleted_user')
            ->columns([
                'id'                 => 'm.id',
                'idnum'              => 'm.idnum',
                'status'             => 'm.status',
                'created_at'         => 'm.created_at',
                'deleted_at'         => 'm.deleted_at',
                'created_by'         => 'IF(created_user.user_type_id = 1, created_user.fio, created_user.org_name)',
                'created_user_idnum' => 'created_user.idnum',
                'deleted_by'         => 'IF(deleted_user.user_type_id = 1, deleted_user.fio, deleted_user.org_name)',
                'deleted_user_idnum' => 'deleted_user.idnum',
            ])
            ->where($where, $bind)
            ->orderBy($orderBy . ' ' . $dir);

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => $limit,
            'page'    => $page,
        ]);
        $pageObj = $paginator->paginate();

        $this->view->setVars([
            'page'          => $pageObj,
            'limit'         => $limit,
            'filters'       => ['idnum' => $idnum, 'status' => $status],
            'sort'          => $sort,
            'dir'           => $dir,
            'totalAll'      => (int) RefManufacturer::count(),
            'totalFiltered' => (int) $pageObj->getTotalItems(),
        ]);
    }

    public function createAction(): ResponseInterface
    {
        $this->view->disable();
        $auth = User::getUserBySession();

        if ($this->request->isPost()) {
            $idnum = preg_replace('/\D+/', '', (string)$this->request->getPost('idnum'));
            if (strlen($idnum) !== 12) {
                $this->flash->error("БИН должен состоять из 12 цифр");
                return $this->response->redirect("/ref_manufacturers/index/");
            }
            $check = RefManufacturer::findFirstByIdnum($idnum);

            if ($check) {
                $this->flash->error("БИН <b>$idnum</b> уже есть в базе, вы не можете добавить повторно !");
            } else {
                $manufacturer = new RefManufacturer();
                $manufacturer->idnum = $idnum;
                $manufacturer->created_by = $auth->id;
                $manufacturer->created_at = time();
                $manufacturer->status = "ACTIVE";

                if ($manufacturer->save()) {
                    $this->flash->success("БИН <b>$idnum</b> успешно добавлено !");
                }
            }
        }

        return $this->response->redirect("/ref_manufacturers/index/");
    }

    public function deleteAction(string $id)
    {
        $auth = User::getUserBySession();

        if ($auth->isSuperModerator() || $auth->isSoftAdmin()) {
            $manufacturer = RefManufacturer::findFirstById($id);
            $bin = $manufacturer->idnum;
            $manufacturer->deleted_by = $auth->id;
            $manufacturer->idnum = $bin . "_DELETED_" . time();
            $manufacturer->deleted_at = time();
            $manufacturer->status = 'DELETED';
            $manufacturer->save();

            $this->flash->success("БИН <b>$bin</b> удалена.");
        } else {
            $this->flash->error("У вас нет прав на это действие");
        }

        return $this->response->redirect("/ref_manufacturers/index/");
    }
}
