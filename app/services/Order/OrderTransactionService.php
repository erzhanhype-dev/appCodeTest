<?php

namespace App\Services\Order;

use App\Helpers\LogTrait;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\Model\TransactionInterface;
use Transaction;

final class OrderTransactionService extends Injectable
{
    use LogTrait;
    public function createNewOrderTransaction(int $profileId, TransactionInterface $tx): Transaction
    {
        $transaction = new Transaction();
        $transaction->setTransaction($tx);
        $transaction->date = time();
        $transaction->amount = 0;
        $transaction->status = Transaction::STATUS_NOT_PAID;
        $transaction->source = Transaction::SOURCE_INVOICE;
        $transaction->profile_id = $profileId;

        if (!$transaction->save()) {
            $tx->rollback('transaction_save_failed');
        }

        return $transaction;
    }
}
