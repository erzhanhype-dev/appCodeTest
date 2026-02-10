<?php

// это изолированный код, который запускается вне среды Phalcon
// поэтому подгружаем переменные и константы
// include_once(APP_PATH.'/app/config/constants.php');

// автоматическая выдача счета по заявкам,
// соответствующим заданным требованиям




function __accept_action($pid, $val) {
  $dt_approve = 0;
  $block = 1;
  if($val == 'approved') {
    $approve = 'APPROVE';
  } else if ($val == 'declined') {
    $approve = 'DECLINED';
    $block = 0;
  } else if ($val == 'neutral') {
    $approve = 'NEUTRAL';
    $block = 0;
  } else if ($val == 'global') {
    $approve = 'GLOBAL';
    $dt_approve = time();
  } else {
    $approve = NULL;
  }

  // если не NULL, то меняем
  if($approve) {
    $tr = Transaction::findFirstByProfileId($pid);
    $p = Profile::findFirstById($pid);

    // ищем БИН в заявке
    $rnn = '';
    if($p->agent_iin) {
      $rnn = preg_replace('/(\D)/', '', $p->agent_iin);
    } else {
      $u = User::findFirstById($p->user_id);
      if($u->user_type_id == 1) {
        $pd = PersonDetail::findFirstByUserId($p->user_id);
        if($pd->iin) { $rnn = preg_replace('/(\D)/', '', $pd->iin); }  
      } else {
        $cd = CompanyDetail::findFirstByUserId($p->user_id);
        if($cd->bin) { $rnn = preg_replace('/(\D)/', '', $cd->bin); }
      }
    }

    // создаем списание
    $pe = ProfileExpense::findFirstByProfileId($pid);
    $ae = ApproveExpense::findFirstByProfileId($pid);
    if(!$pe) { $pe = new ProfileExpense(); }
    if(!$ae) { $ae = new ApproveExpense(); }
    // списание по одобрение
    $ae->profile_id = $p->id;
    $ae->rnn_recipient = $rnn;
    $ae->date_modified = time();
    if($approve == 'APPROVE' || $approve == 'GLOBAL') {
      $ae->amount = $tr->amount;
    } else {
      $ae->amount = 0.00;
    }
    // списание по завке
    $pe->profile_id = $p->id;
    $pe->rnn_recipient = $rnn;
    $pe->date_modified = time();
    if($approve == 'APPROVE') {
      $ae->amount = $tr->amount;
      $pe->amount = 0.00;
    } elseif($approve == 'GLOBAL') {
      $ae->amount = $tr->amount;
      $pe->amount = $tr->amount;
    } else {
      $pe->amount = 0.00;
    }
    $ae->save();
    $pe->save();
    // конец учета по БИН

    $_before = json_encode(array($p, $tr));
    if($tr) {
      if($val == 'declined') {
        $tr->ac_approve = 'NOT_SIGNED';
      }
      $tr->approve = $approve;
      if($tr->dt_approve == 0) {
        $tr->dt_approve = $dt_approve;
      }
      $p->blocked = $block;
      $tr->save();
      if($p->save()) {
        // логгирование
        $l = new ProfileLogs();
        $l->login = 'SYSTEM';
        $l->action = $approve;
        $l->profile_id = $p->id;
        $l->dt = time();
        $l->meta_before = $_before;
        $l->meta_after = json_encode(array($p, $tr));
        $l->save();

        return true;
      }
    }
  } else {
    return false;
  }  
}

function __auto_approve($profile)
{
    $di = \Phalcon\Di\Di::getDefault();
    /** @var \Phalcon\Db\Adapter\Pdo\AbstractPdo $db */
    $db = $di->get('db');

    $id = (int)$profile;

    $__score_g = 0;
    $__score_d = 0;
    $__goods_decline = [];

    // --- 1. ТРАНЗАКЦИЯ ---
    $p = $db->fetchOne("
        SELECT t.*, p.*
        FROM `transaction` t
        JOIN `profile` p ON p.id = t.profile_id
        WHERE p.type = 'GOODS'
          AND t.approve = 'REVIEW'
          AND t.profile_id = :id
        LIMIT 1
    ",
        \Phalcon\Db\Enum::FETCH_ASSOC,
        ['id' => $id]);

    if (!$p) {
        return;
    }

    if ($p['approve'] === 'REVIEW' && $p['amount'] > 0) {

        // --- 2. GOODS ---
        $goods = $db->fetchAll("
            SELECT *
            FROM goods
            WHERE profile_id = :id
        ", \Phalcon\Db\Enum::FETCH_ASSOC, ['id' => $id]);

        if (!$goods) {
            $__score_g -= 10;
        } else {
            foreach ($goods as $g) {
                if (in_array($g['ref_tn'], $__goods_decline, true)) {
                    $__score_g--;
                }
            }
        }

        // --- 3. FILES ---
        $files = $db->fetchAll("
            SELECT *
            FROM file
            WHERE visible = 1 AND profile_id = :id
        ", \Phalcon\Db\Enum::FETCH_ASSOC, ['id' => $id]);

        foreach ($files as $f) {
            if ($f['type'] === 'application') {
                $__score_d += 10;
            } else {
                $__score_d += 1;
            }
        }

        // --- 4. Решение ---
        if ($__score_g === 0 && $__score_d > 10) {
            // одобряем
            $msg = "#$id APPROVE HAS ALLOWED: $__score_g | $__score_d";
            logProfile($db, $id, 'CHECK', $msg);
            __accept_action($id, 'approved');

        } else {

            if ($__score_d <= 10) {
                $msg = 'Приложите, пожалуйста, подписанное и отсканированное заявление.';
                if ($__score_d == 10) {
                    $msg = 'Приложите, пожалуйста, необходимые документы.';
                }

                file_put_contents(APP_PATH . '/storage/temp/msg_' . $id . '.txt', $msg);

                logProfile($db, $id, 'CHECK', "#$id DOESN'T HAVE THE APPLICATION: $__score_g | $__score_d");
                logProfile($db, $id, 'MSG', $msg);

                __accept_action($id, 'declined');

            } else {
                echo "#$id DID NOTHING: $__score_g | $__score_d\n";
            }
        }
    }
}

function logProfile($db, int $id, string $action, string $msg)
{
    $db->execute("
        INSERT INTO profile_logs (login, action, profile_id, dt, meta_before, meta_after)
        VALUES ('SYSTEM', :action, :id, :dt, '—', :msg)
    ", [
        'action' => $action,
        'id'     => $id,
        'dt'     => time(),
        'msg'    => $msg
    ]);
}

// для ТС 
function __auto_car_approve($profile)
{
    $di = \Phalcon\Di\Di::getDefault();
    /** @var \Phalcon\Db\Adapter\Pdo\AbstractPdo $db */
    $db = $di->get('db');

    $id = (int)$profile;

    $__score_c = 0;
    $__score_d = 0;

    $manufacturers_list = [];

    // --- 1. TRANSACTION ---
    $p = $db->fetchOne("
        SELECT t.*, p.*
        FROM `transaction` t
        JOIN `profile` p ON p.id = t.profile_id
        WHERE p.type = 'CAR'
          AND t.approve = 'REVIEW'
          AND t.profile_id = :id
        LIMIT 1
    ",
        \Phalcon\Db\Enum::FETCH_ASSOC,
        ['id' => $id]);

    if (!$p) {
        return;
    }

    if ($p['approve'] === 'REVIEW') {

        // --- 2. LOAD ACTIVE MANUFACTURERS ---
        $manufacturers = RefManufacturer::find([
            "status = 'ACTIVE'"
        ]);

        if ($manufacturers) {
            foreach ($manufacturers as $manufacturer) {
                $manufacturers_list[] = $manufacturer->idnum;
            }
        }

        // --- 3. CAR DATA ---
        $cars = $db->fetchAll("
            SELECT 
                c.profile_id AS pid,
                p.agent_status AS agent_status,
                u.idnum AS idnum
            FROM car c
            JOIN profile p ON p.id = c.profile_id
            JOIN user u ON u.id = p.user_id
            WHERE p.id = :id
        ",
            \Phalcon\Db\Enum::FETCH_ASSOC,
            ['id' => $id]);

        if (!$cars) {
            $__score_c -= 10;
        } else {
            foreach ($cars as $c) {
                if (in_array($c['idnum'], $manufacturers_list, true) && $c['agent_status'] === 'VENDOR') {
                    $__score_c = 0;
                } else {
                    $__score_c -= 10;
                }
            }
        }

        // --- 4. FILES ---
        $files = $db->fetchAll("
            SELECT *
            FROM file
            WHERE visible = 1 AND profile_id = :id
        ",
            \Phalcon\Db\Enum::FETCH_ASSOC,
            ['id' => $id]);

        foreach ($files as $f) {
            if ($f['type'] === 'application') {
                $__score_d += 10;
            } else {
                $__score_d += 1;
            }
        }

        // --- 5. ANALYTICS ---
        if ($__score_c === 0 && $__score_d > 10) {

            $msg = "#$id APPROVE HAS ALLOWED: $__score_c | $__score_d";
            logProfile($db, $id, 'CHECK', $msg);
            __accept_action($id, 'approved');

        } else {

            if ($__score_d <= 10) {

                $msg = 'Приложите, пожалуйста, подписанное и отсканированное заявление.';
                if ($__score_d == 10) {
                    $msg = 'Приложите, пожалуйста, необходимые документы.';
                }

                file_put_contents(APP_PATH . '/storage/temp/msg_' . $id . '.txt', $msg);

                logProfile($db, $id, 'CHECK', "#$id DOESN'T HAVE THE APPLICATION: $__score_c | $__score_d");
                logProfile($db, $id, 'MSG', $msg);

                __accept_action($id, 'declined');

            } else {
                echo "#$id DID NOTHING: $__score_c | $__score_d\n";
            }
        }
    }
}

?>
