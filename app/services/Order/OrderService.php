<?php

namespace App\Services\Order;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Repositories\OrderRepository;
use App\Services\Order\Dto\OrderCreateDTO;
use App\Services\Order\Dto\OrderFilterDTO;
use Car;
use Goods;
use Phalcon\Di\Injectable;
use Profile;

final class OrderService extends Injectable
{
    use LogTrait;
    private OrderTransactionService $orderTransactionService;

    public function onConstruct(): void
    {
        $this->orderRepository = $this->di->getShared(OrderRepository::class);
        $this->orderTransactionService = $this->di->getShared(OrderTransactionService::class);
        $this->txManager = $this->di->getShared('transactions');

    }

    public function getFilteredOrders(OrderFilterDTO $filters, int $userId): array
    {
        return $this->orderRepository->getFilteredOrders($filters, $userId);
    }

    /**
     * @throws AppException
     * @throws \Throwable
     */
    public function create(OrderCreateDTO $dto, object $auth): Profile
    {
        $tx = $this->transactions->get();

        try {
            $profile = new Profile();
            $profile->setTransaction($tx);

            $profile->assign([
                'name' => $dto->comment,
                'user_id' => (int)$auth->id,
                'type' => $dto->type,
                'agent_status' => $dto->agent_status,
                'created' => time(),
                'agent_size' => 1
            ]);

            if (!$profile->save()) {
                throw new AppException($this->translator->_('validation.profile_create_failed'));
                $this->writeLog($this->translator->_('validation.profile_create_failed'), 'action', 'WARNING');
            }

            $profileId = (int)$profile->id;
            $this->orderTransactionService->createNewOrderTransaction($profileId, $tx);

            $tx->commit();
            return $profile;

        } catch (\Throwable $e) {
            try {
                $tx->rollback($e->getMessage());
                $this->writeLog($e->getMessage(), 'action', 'WARNING');
            } catch (\Throwable) {
                $this->writeLog($this->translator->_('validation.profile_create_failed'), 'action', 'WARNING');
            }
            throw $e;
        }
    }

    /**
     * @throws \Throwable
     * @throws AppException
     */
    public function edit($profile, OrderCreateDTO $dto, object $auth): Profile
    {

        $tx = $this->transactions->get();

        try {
            $profile->setTransaction($tx);

            $profile->assign([
                'name' => $dto->comment,
                'type' => $dto->type,
                'agent_status' => $dto->agent_status,
            ]);

            if (!$profile->save()) {
                throw new AppException($this->translator->_('validation.profile_update_failed'));
            }

            $tx->commit();
            return $profile;
        } catch (\Throwable $e) {
            try {
                $tx->rollback($e->getMessage());
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    public function getSignData(int $profileId, ?string $hash): string
    {
        return $hash && $hash !== ''
            ? $hash
            : $this->makeSignData($profileId);
    }

    private function makeSignData(int $profileId): string
    {
        $p = Profile::findFirstById($profileId);
        $t = $p->tr;
        $__settings = $this->session->get("__settings");

        $s = '';

        $idnum = '';
        $title = '';

        if ($__settings) {
            $idnum = $__settings['iin'];
            $title = $__settings['fio'];
            if ($__settings['bin']) {
                $idnum = $__settings['bin'];
                $title = $__settings['company'];
            }

            $s .= "$idnum:$title";
        }

        // номер заявки и общая сумма
        $s .= ":" . $p->id . ":" . $t->amount;

        // дата создания
        $s .= ":" . date('d.m.Y', $p->created) . "(" . $p->created . ")";

        // заполнение заявки
        if ($p->type == 'CAR') {
            $list = Car::find(array('profile_id = :pid:',
                'bind' => array(
                    'pid' => $p->id,
                )
            ));
            foreach ($list as $n => $l) {
                $s .= ':CAR';
                $s .= ':' . $l->id . ':' . $l->volume . ':' . $l->vin . ':' . $l->year . ':' . $l->ref_car_cat . ':' . $l->ref_car_type_id . ':' . $l->ref_country . ':' . date('d.m.Y', $l->date_import) . '(' . $l->date_import . '):' . $l->cost . ':' . $l->ref_st_type;
            }
        } else {
            $list = Goods::find(array('profile_id = :pid:',
                'bind' => array(
                    'pid' => $p->id,
                )
            ));
            foreach ($list as $n => $l) {
                $s .= ':GOOD';
                $s .= ':' . $l->id . ':' . $l->ref_tn . '(' . $l->ref_tn_add . '):' . $l->ref_country . ':' . date('d.m.Y', $l->date_import) . '(' . $l->date_import . '):' . $l->weight . ':' . $l->price . ':' . $l->amount . ':' . $l->basis;
            }
        }

        $z = gzencode($s);
        $z = base64_encode($z);

        $p->hash = $z;
        $p->save();

        return $z;
    }

    public function getAvailableFilters(): array
    {
        return [
            'years' => range(2016, (int)date('Y')),
            'types' => ['CAR', 'GOODS', 'KPP'],
            'statuses' => ['REVIEW', 'GLOBAL', 'DECLINED', 'NEUTRAL', 'APPROVE', 'CERT_FORMATION'],
        ];
    }

    public function isOrderBlockedUser($idnum): bool
    {
        if (in_array($idnum, CAR_BLACK_LIST) || in_array("BLOCK_ALL", CAR_BLACK_LIST)) {
            return true;
        }
        return false;
    }

    public function isUserBlocked(object $auth): bool
    {
        if (!defined('CAR_BLACK_LIST')) {
            return false;
        }
        return in_array((string)$auth->idnum, CAR_BLACK_LIST, true);
    }
}