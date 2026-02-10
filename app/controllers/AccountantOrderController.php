<?php

namespace App\Controllers;

use App\Resources\CarRowResource;
use App\Resources\GoodsRowResource;
use ContactDetail;
use ControllerBase;
use File;
use Profile;
use ProfileLogs;
use RefInitiator;
use Transaction;
use User;

class  AccountantOrderController extends ControllerBase
{
    public function signAction($pid, $val)
    {
        // $this->view->disable();

        $auth = User::getUserBySession();
        $dt_approve = 0;
        $block = 1;

        if ($val == 'signed') {
            $approve = 'SIGNED';
            $dt_approve = time();
            $this->logAction('Заявка подписано бухгалтером');
        } else {
            $approve = 'NOT_SIGNED';
            $dt_approve = 0;
        }

        $tr = Transaction::findFirstByProfileId($pid);
        $p = Profile::findFirstById($pid);
        $_before = json_encode(array($p, $tr));

        if ($tr) {
            $tr->ac_approve = $approve;
            $tr->approve = "GLOBAL";
            $tr->ac_dt_approve = $dt_approve;
            $p->blocked = $block;
            $tr->save();
            if ($p->save()) {
                // логгирование

                // ДПП выдан
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = "GLOBAL";
                $l->profile_id = $p->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($p, $tr));
                $l->save();

                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);

                // 	Подписано
                $l = new ProfileLogs();
                $l->login = $auth->idnum;
                $l->action = $approve;
                $l->profile_id = $p->id;
                $l->dt = time();
                $l->meta_before = $_before;
                $l->meta_after = json_encode(array($p, $tr));
                $l->save();

                $logString = json_encode($l->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->logAction($logString);
            }
        }

        return $this->response->redirect("/accountant_order/index/");
    }

    public function indexAction()
    {
        $rop_esign_date = ROP_ESIGN_DATE;
        $numberPage = $this->request->getQuery("page", "int", 1);

        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'p_id' => 'p.id',
                'p_name' => 'p.name',
                'p_created' => 'p.created',
                'p_type' => 'p.type',
                't_amount' => 'ANY_VALUE(t.amount)',
                'admin_dt_approve' => 'ANY_VALUE(t.dt_approve)',
                't_id' => 'ANY_VALUE(t.id)',
            ])
            ->from(['p' => Profile::class])
            ->join(Transaction::class, 't.profile_id = p.id', 't')
            ->where("t.approve IN ('GLOBAL', 'CERT_FORMATION')")
            ->andWhere("t.ac_approve = 'NOT_SIGNED'")
            ->andWhere("t.dt_approve > 0")
            ->andWhere("t.dt_approve > :rop:", ['rop' => $rop_esign_date])
            ->groupBy('p.id')
            ->orderBy('p.id DESC');

        $paginator = new \Phalcon\Paginator\Adapter\QueryBuilder([
            'builder' => $builder,
            'limit' => 20,
            'page' => $numberPage,
        ]);
        $this->view->page = $paginator->paginate();
    }


    public function viewAction($pid)
    {
        $profile = Profile::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $pid],
        ]);

        if (!$profile) {
            return $this->response->redirect("/accountant_order/index");
        }

        $transaction = $profile->tr;

        $profile_user = User::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->user_id],
        ]);

        $profile_moderator = User::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->moderator_id],
        ]);

        $profile_initiator = RefInitiator::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $profile->initiator_id],
        ]);

        $contact_detail = ContactDetail::findFirst([
            'conditions' => 'user_id = :user_id:',
            'bind' => ['user_id' => $profile->user_id],
        ]);

        $files = File::find([
            'conditions' => 'profile_id = :pid:',
            'bind' => ['pid' => $pid],
        ]);

        $cars = $this->carService->itemsByProfile($profile->id);
        $cancelled_cars = $this->carService->itemsCancelledByProfile($profile->id);

        $goods = $this->goodsService->itemsByProfile($profile->id);

        $title = $profile_user->user_type_id === 1 ? $profile_user->fio : $profile_user->org_name;

        $userArr = [
            'id' => $profile_user->id,
            'title' => $title ? $title : $profile_user->fio,
            'idnum' => $profile_user->idnum,
            'user_type_id' => $profile_user->user_type_id,
            'email' => $profile_user->email,
            'phone' => $contact_detail->phone
        ];

        $executor_uid = ($profile->executor_uid ?? 0);
        $executorName = '';
        if ($executor_uid > 0) {
            $exUser = User::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $executor_uid],
            ]);
            $nm = $exUser ? (string)$exUser->fio : 'Не назначен';
            $executorName = $nm !== '' ? $nm : (string)$executor_uid;
        }

        $initiators = RefInitiator::find();

        $filesArr = [];
        foreach ($files as $file) {
            $modifier = null;
            $created = null;
            if ($file->modified_by) {
                $modifier = User::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $file->modified_by],
                ]);
            }
            if ($file->created_by) {
                $created = User::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $file->created_by],
                ]);
            }
            $filesArr[] = [
                'id' => $file->id,
                'type' => $file->type,
                'original_name' => $file->original_name,
                'ext' => $file->ext,
                'visible' => $file->visible,
                'modified_at' => $file->modified_at,
                'modifier' => $modifier ? $modifier->toArray() : null,
                'created_by' => $created ? $created->toArray() : null,
            ];
        }

        $executor = [
            'id' => $executor_uid,
            'name' => $executor_uid === 0 ? null : $executorName,
            'status_code' => $profile->status,
            'status_text' => $this->statusText($profile->status)
        ];

        $data = [
            'id' => $profile->id,
            'name' => $profile->name,
            'created_dt' => $this->formatTs($profile->created),
            'sent_dt' => $this->formatTs($transaction->md_dt_sent),
            'approve_dt' => $this->formatTs($transaction->dt_approve),
            'amount' => self::fmtMoney((float)($transaction->amount ?? 0)),
            'agent_name' => ($profile->agent_name ?? ''),
            'agent_iin' => ($profile->agent_iin ?? ''),
            'agent_city' => ($profile->agent_city ?? ''),
            'agent_phone' => ($profile->agent_phone ?? ''),
            'blocked' => $profile->blocked,
            'status' => $transaction->status,
            'approve' => $transaction->approve,
            'ac_approve' => $transaction->ac_approve,
            'type' => $profile->type,
            'executor' => $executor,
            'initiator' => $profile_initiator ? $profile_initiator->toArray() : null,
            'user' => $userArr,
            'moderator' => $profile_moderator ? $profile_moderator->toArray() : null,

            'files' => $filesArr,
            'initiators' => $initiators->toArray(),
            'cars' => CarRowResource::collection($cars),
            'goods' => GoodsRowResource::collection($goods)
        ];

        $this->view->setVar('data', $this->toObj($data));
    }

    private function statusText(string $code): string
    {
        return match ($code) {
            'new' => 'Новая',
            'in_progress' => 'В работе',
            'done' => 'Завершена',
            'declined' => 'Отклонена',
            default => $code,
        };
    }

    private function toObj(mixed $v): mixed
    {
        if (is_array($v)) {
            // сначала конвертируем все вложенные элементы
            $v = array_map(fn($x) => $this->toObj($x), $v);
            // затем отдаём объект с доступом через свойства
            return new \ArrayObject($v, \ArrayObject::ARRAY_AS_PROPS);
        }
        return $v;
    }

    private function formatTs(string $ts): string
    {
        if ($ts === '' || $ts === '0') return '';
        if (ctype_digit($ts)) return date('d.m.Y H:i', (int)$ts);
        $t = strtotime($ts);
        return $t === false ? '' : date('d.m.Y H:i', $t);
    }

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
    }

}
