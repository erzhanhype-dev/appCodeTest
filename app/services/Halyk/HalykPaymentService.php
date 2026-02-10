<?php

namespace App\Services\Halyk;

use HalykPayment;
use Phalcon\Di\Injectable;

class HalykPaymentService extends Injectable
{
   public function store(array $obj): HalykPayment
   {
      $b_in = new HalykPayment();
      $b_in->document_number = $obj['document-number'];
      $b_in->idnum_invoice = $obj['idnum-invoice'];
      $b_in->statement_reference = $obj['statement-reference'];
      $b_in->amount_sender = round($obj['amount-sender'], 2);
      $b_in->name_sender = $obj['name-sender'];
      $b_in->name_recipient = $obj['name-recipient'];
      $b_in->idnum_sender = $obj['idnum-sender'];
      $b_in->idnum_owner = $obj['idnum-owner'];
      $b_in->idnum_recipient = $obj['idnum-recipient'];
      $b_in->account_sender = $obj['account-sender'];
      $b_in->account_recipient = $obj['account-recipient'];
      $b_in->knp_code = $obj['knp-code'];
      $b_in->date_sender = (strtotime($obj['date-sender']) !== false) ? strtotime($obj['date-sender']) : time();
      $b_in->payment_purpose = $obj['payment-purpose'];
      $b_in->mfo_sender = $obj['mfo-sender'];
      $b_in->mfo_recipient = $obj['mfo-recipient'];
      $b_in->order_number = $obj['order-number'];
      $b_in->bank_unique_id = $obj['id'];
      $b_in->is_third_party_payer = $obj['is-third-party-payer'] ?? 0;
      $b_in->created_dt = time();
      $b_in->save();
      return $b_in;
   }
}