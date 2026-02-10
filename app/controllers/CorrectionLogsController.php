<?php

namespace App\Controllers;

use ControllerBase;
use CorrectionLogs;
use FileLogs;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorBuilder;
use RefCarCat;
use RefCarType;
use RefCountry;
use RefFundLogs;
use RefInitiator;
use RefTnCode;
use User;

class CorrectionLogsController extends ControllerBase
{
    public function indexAction()
    {
        $phql = <<<SQL
          SELECT 
            u.id AS id,
            u.idnum AS idnum,
            u.fio AS fio,
            i.name AS initiator_name
          FROM CorrectionLogs cl
          LEFT JOIN User u ON cl.user_id = u.id
          LEFT JOIN RefInitiator i ON cl.initiator_id = i.id
          GROUP BY u.id, u.idnum, u.fio, i.name
        SQL;

        $users = $this->modelsManager->executeQuery($phql);

        $req = $this->request;

        // DataTables params
        $draw            = (int)$req->getPost('draw', 'int', 0);
        $row             = (int)$req->getPost('start', 'int', 0);
        $rowperpage      = (int)$req->getPost('length', 'int', 10);
        $order = $req->getPost('order');

        $columnIndex = 0;
        if (is_array($order) && isset($order[0]['column'])) {
            $columnIndex = (int)$order[0]['column'];
        }
        $columnSortOrder = $req->getPost('order')[0]['dir'] ?? 'desc';

        $columnName = $req->getPost('columns')[$columnIndex]['data'] ?? 'c_dt';

        // filters
        $profile_id = $req->getPost('searchByProfileId', 'int', null);
        $object_id  = $req->getPost('searchByObjectId',  'int', null);
        $user_id    = $req->getPost('searchByUserId',    'int', null);
        $type       = $req->getPost('searchByType',      'string', null);
        $action     = $req->getPost('searchByAction',    'string', null);

        // whitelists (важно: не даём инъекций через сортировку)
        $dir = strtolower($columnSortOrder) === 'asc' ? 'ASC' : 'DESC';

        $orderMap = [
            // DataTables "data" => SQL expression/alias
            'c_profile_id' => 'c.profile_id',
            'c_type'       => 'c.type',
            'c_fio'        => 'u.fio',
            'c_action'     => 'c.action',
            'c_initiator'  => 'i.name',
            'c_dt'         => 'c.dt',
            'c_comment'    => 'c.comment',
        ];
        $orderExpr = $orderMap[$columnName] ?? 'c.dt';

        // Общие условия
        $conditions = ['c.id <> 0'];
        $bind       = [];
        $bindTypes  = [];

        if ($profile_id) {
            $conditions[] = 'c.profile_id = :profile_id:';
            $bind['profile_id'] = (int)$profile_id;
            $bindTypes['profile_id'] = Enum::BIND_PARAM_INT;
        }
        if ($object_id) {
            $conditions[] = 'c.object_id = :object_id:';
            $bind['object_id'] = (int)$object_id;
            $bindTypes['object_id'] = Enum::BIND_PARAM_INT;
        }
        if ($user_id) {
            $conditions[] = 'c.user_id = :user_id:';
            $bind['user_id'] = (int)$user_id;
            $bindTypes['user_id'] = Enum::BIND_PARAM_INT;
        }
        if ($type !== null && $type !== '') {
            $conditions[] = 'c.type = :type:';
            $bind['type'] = $type;
            $bindTypes['type'] = Enum::BIND_PARAM_STR;
        }
        if ($action !== null && $action !== '') {
            $conditions[] = 'c.action = :action:';
            $bind['action'] = $action;
            $bindTypes['action'] = Enum::BIND_PARAM_STR;
        }

        $where = implode(' AND ', $conditions);

        // 1) Total number of records without filtering
        $totalRecords = (int) CorrectionLogs::count();

        // 2) Total number of records with filtering
        $countBuilder = $this->modelsManager->createBuilder()
            ->from(['c' => CorrectionLogs::class])
            ->leftJoin(RefInitiator::class, 'c.initiator_id = i.id', 'i')
            ->join(User::class, 'c.user_id = u.id', 'u')
            ->columns('COUNT(c.id) AS allcount')
            ->where($where, $bind, $bindTypes);

        $countRow = $countBuilder->getQuery()->execute()->getFirst();
        $totalRecordwithFilter = $countRow ? (int)$countRow->allcount : 0;

        // 3) Data page
        $dataBuilder = $this->modelsManager->createBuilder()
            ->from(['c' => CorrectionLogs::class])
            ->leftJoin(RefInitiator::class, 'c.initiator_id = i.id', 'i')
            ->join(User::class, 'c.user_id = u.id', 'u')
            ->columns([
                'c_id'         => 'c.id',
                'c_type'       => 'c.type',
                'c_object_id'  => 'c.object_id',
                'c_profile_id' => 'c.profile_id',
                'c_iin'        => 'c.iin',
                'c_action'     => 'c.action',
                // dt хранится unix timestamp:
                'c_dt'         => 'FROM_UNIXTIME(c.dt)',
                'c_comment'    => 'c.comment',
                'c_file'       => 'c.file',
                'c_fio'        => 'u.fio',
                'c_initiator'  => 'i.name',
                'c_user_id'    => 'c.user_id',
            ])
            ->where($where, $bind, $bindTypes)
            ->orderBy("c.dt DESC, {$orderExpr} {$dir}")
            ->limit($rowperpage, $row);

        $corrections = $dataBuilder->getQuery()->execute();

        $data = [];
        foreach ($corrections as $c) {
            // если есть связь User в моделях — лучше взять её, но оставляю максимально близко к вашему коду:
            $user = User::findFirst((int)$c->c_user_id);

            $typeTitle = $this->translator->_($c->c_type);

            $actionTitle = $c->c_action;
            if ($c->c_initiator || ($user && ($user->isSuperModerator() || $user->isModerator() || $user->isAccountant()))) {
                $actionTitle = 'CORRECTION';
            } else {
                if ($c->c_action === 'CORRECTION_APPROVED') {
                    $actionTitle = $c->c_action . '_TITLE';
                }
            }

            $data[] = [
                "c_id"        => $c->c_id,
                "c_type"      => $typeTitle . " ({$c->c_object_id})",
                "c_profile_id"=> $c->c_profile_id,
                "c_action"    => $this->translator->_($actionTitle),
                "c_dt"        => $c->c_dt,
                "c_comment"   => $c->c_comment,
                "c_initiator" => $c->c_initiator,
                "c_file"      => $c->c_file,
                "c_fio"       => $c->c_fio . "({$c->c_iin})",
            ];
        }

        $json = [
            "draw" => $draw,
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordwithFilter,
            // DataTables ожидает aaData (как у вас)
            "aaData" => $data,
        ];

        $this->view->setVars(array(
            "users" => $users,
            "logs" => $json
        ));
    }

    public function getCorrLogListAction()
    {
        $t = $this->translator;
        $this->view->disable();
        $data = array();

        $draw = $_POST['draw'];
        $row = $_POST['start'];
        $rowperpage = $_POST['length']; // Rows display per page
        $columnIndex = $_POST['order'][0]['column']; // Column index
        $columnName = $_POST['columns'][$columnIndex]['data']; // Column name
        $columnSortOrder = $_POST['order'][0]['dir']; // asc or desc
        $searchValue = $_POST['search']['value']; // Search value

        $profile_id = isset($_POST['searchByProfileId']) ? $_POST['searchByProfileId'] : '';
        $object_id = isset($_POST['searchByObjectId']) ? $_POST['searchByObjectId'] : '';
        $user_id = isset($_POST['searchByUserId']) ? $_POST['searchByUserId'] : '';
        $type = isset($_POST['searchByType']) ? $_POST['searchByType'] : '';
        $action = isset($_POST['searchByAction']) ? $_POST['searchByAction'] : '';

        ## Search
        $searchQuery = "";
        if ($profile_id != '') {
            $searchQuery .= " AND c.profile_id = $profile_id ";
        }
        if ($object_id != '') {
            $searchQuery .= " AND c.object_id = $object_id ";
        }
        if ($user_id != '') {
            $searchQuery .= " AND c.user_id = $user_id ";
        }
        if ($type != '') {
            $searchQuery .= " AND c.type = '$type' ";
        }
        if ($action != '') {
            $searchQuery .= " AND c.action = '$action' ";
        }

        ## Total number of records without filtering
        $totalRecords = CorrectionLogs::count();

        $sql = <<<SQL
      SELECT
        COUNT(c.id) as allcount
      FROM CorrectionLogs as c
      LEFT JOIN RefInitiator i ON c.initiator_id = i.id
        JOIN User as u ON c.user_id = u.id
      WHERE
        c.id <> 0
        {$searchQuery}
    SQL;
        $query = $this->modelsManager->createQuery($sql);
        $records = $query->execute();

        $totalRecordwithFilter = $records[0]->allcount;

        $sql = <<<SQL
        SELECT
          c.id as c_id,
          c.type as c_type,
          c.object_id as c_object_id,
          c.profile_id as c_profile_id,
          c.iin as c_iin,
          c.action as c_action,
          FROM_UNIXTIME(c.dt) as c_dt,
          c.comment as c_comment,
          c.file as c_file,
          u.fio AS c_fio,
          i.name as c_initiator,
          c.user_id as c_user_id
        FROM CorrectionLogs as c
        LEFT JOIN RefInitiator i ON c.initiator_id = i.id
        JOIN User as u ON c.user_id = u.id
        WHERE
        c.id <> 0
        {$searchQuery}
      ORDER BY c_dt DESC, {$columnName} {$columnSortOrder} LIMIT {$row},{$rowperpage}
    SQL;
        $query = $this->modelsManager->createQuery($sql);
        $corrections = $query->execute();

        if (count($corrections) > 0) {
            foreach ($corrections as $c) {
                $user = User::findFirst($c->c_user_id);
                $type = $this->translator->_($c->c_type);
                $action_title = $c->c_action;
                if ($c->c_initiator || $user->isSuperModerator() || $user->isModerator() || $user->isAccountant()) {
                    $action_title = 'CORRECTION';
                } else {
                    if ($c->c_action == 'CORRECTION_APPROVED') {
                        $action_title = $c->c_action . '_TITLE';
                    }
                }
                $data[] = [
                    "c_id" => $c->c_id,
                    "c_type" => "$type ($c->c_object_id)",
                    "c_profile_id" => $c->c_profile_id,
                    "c_action" => $this->translator->_($action_title),
                    "c_dt" => $c->c_dt,
                    "c_comment" => $c->c_comment,
                    "c_initiator" => $c->c_initiator,
                    "c_file" => $c->c_file,
                    "c_fio" => "$c->c_fio($c->c_iin)",
                ];
            }
        }

        if (is_array($data) && count($data) > 0) {
            $json_data = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordwithFilter,
                "aaData" => $data,
            );
            http_response_code(200);
            return json_encode($json_data);
        } else {
            $json_data = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordwithFilter,
                "data" => $data,
            );
            http_response_code(200);
            return json_encode($json_data);
        }
    }

    public function getOldNewValuesAction($id)
    {
        $this->view->disable();
        $t = $this->translator;
        $c_log = CorrectionLogs::findFirstById($id);
        $html = null;

        if ($c_log->meta_before != null && $c_log->meta_before != '-') {
            $before = json_decode($c_log->meta_before, true);
        }
        if ($c_log->meta_after != null && $c_log->meta_after != '-') {
            $after = json_decode($c_log->meta_after, true);
        }

        $num_application = $t->_("num-application");
        $car_calculate_method = $t->_("car-calculate-method");
        $date_of_import = $t->_("date-of-import");
        $country_of_manufacture = $t->_("country");
        $amount = $t->_("amount");

        $calc_method_before = (array_key_exists('calculate_method',
            $before[0])) ? CALCULATE_METHODS[$before[0]['calculate_method']] : '-';
        $calc_method_after = (array_key_exists('calculate_method',
            $after[0])) ? CALCULATE_METHODS[$after[0]['calculate_method']] : '-';

        if ($calc_method_before != $calc_method_after) {
            $calc_method_before = '<del style="color:red;">' . $calc_method_before . '</del>';
            $calc_method_after = '<b style="color:green;">' . $calc_method_after . '</b>';
        }

        if ($before[0]['date_import'] != $after[0]['date_import']) {
            $before_dt_import = '<del style="color:red;">' . date('d.m.Y', $before[0]['date_import']) . '</del>';
            $after_dt_import = '<b style="color:green;">' . date('d.m.Y', $after[0]['date_import']) . '</b>';
        } else {
            $before_dt_import = date('d.m.Y', $before[0]['date_import']);
            $after_dt_import = date('d.m.Y', $after[0]['date_import']);
        }

        if ($before[0]['ref_country'] != $after[0]['ref_country']) {
            $country_before = RefCountry::findFirstById($before[0]['ref_country']);
            $country_after = RefCountry::findFirstById($after[0]['ref_country']);

            $country_name_before = '<del style="color:red;">' . $country_before->name . '</del>';
            $country_name_after = '<b style="color:green;">' . $country_after->name . '</b>';
        } else {
            $country_before = RefCountry::findFirstById($before[0]['ref_country']);
            $country_after = RefCountry::findFirstById($after[0]['ref_country']);

            $country_name_before = $country_before->name;
            $country_name_after = $country_after->name;
        }

        $initiator_label = $t->_("initiator");
        $initiator_name = '';
        if ($c_log->initiator_id) {
            $initiator = RefInitiator::findFirstById($c_log->initiator_id);
            $initiator_name = $initiator->name;
        }

        if ($c_log->type == 'CAR') {
            $vin_code = $t->_("vin-code");
            $volume_cm = $t->_("volume-cm");
            $year_of_manufacture = $t->_("year-of-manufacture");
            $car_category = $t->_("car-category");
            $ref_st = $t->_("ref-st");
            $transport_type = $t->_("transport-type");
            $is_electric_car = $t->_("is_electric_car?");

            $c_type_before = RefCarType::findFirstById($before[0]['ref_car_type_id']);
            $c_type_after = RefCarType::findFirstById($after[0]['ref_car_type_id']);
            $car_type_before = $c_type_before->name;
            $car_type_after = $c_type_after->name;

            if ($before[0]['ref_car_type_id'] != $after[0]['ref_car_type_id']) {
                $car_type_before = '<del style="color:red;">' . $car_type_before . '</del>';
                $car_type_after = '<b style="color:green;">' . $car_type_after . '</b>';
            }

            $c_cat_before = RefCarCat::findFirstById($before[0]['ref_car_cat']);
            $c_cat_after = RefCarCat::findFirstById($after[0]['ref_car_cat']);
            $car_cat_before_name = $t->_($c_cat_before->name);
            $car_cat_after_name = $t->_($c_cat_after->name);

            if ($before[0]['ref_car_cat'] != $after[0]['ref_car_cat']) {
                $car_cat_before_name = '<del style="color:red;">' . $car_cat_before_name . '</del>';
                $car_cat_after_name = '<b style="color:green;">' . $car_cat_after_name . '</b>';
            }

            $is_electric_val_before = (array_key_exists('electric_car', $before[0])) ? $before[0]['electric_car'] : '-';
            $is_electric_val_after = (array_key_exists('electric_car', $after[0])) ? $after[0]['electric_car'] : '-';

            if ($is_electric_val_before != $is_electric_val_after) {
                $is_electric_before = ($is_electric_val_before == '-') ? $is_electric_val_before : '<del style="color:red;">' . $t->_("yesno-$is_electric_val_before") . '</del>';
                $is_electric_after = ($is_electric_val_after == '-') ? $is_electric_val_after : '<b style="color:green;">' . $t->_("yesno-$is_electric_val_after") . '</b>';
            } else {
                $is_electric_before = ($is_electric_val_before == '-') ? $is_electric_val_before : $t->_("yesno-$is_electric_val_before");
                $is_electric_after = ($is_electric_val_after == '-') ? $is_electric_val_after : $t->_("yesno-$is_electric_val_after");
            }

            $st_type_val_before = (array_key_exists('ref_st_type', $before[0])) ? $before[0]['ref_st_type'] : '-';
            $st_type_val_after = (array_key_exists('ref_st_type', $after[0])) ? $after[0]['ref_st_type'] : '-';

            if ($st_type_val_before != $st_type_val_after) {
                $st_type_before = '<del style="color:red;">' . REF_ST_TYPE[$st_type_val_before] . '</del>';
                $st_type_after = '<b style="color:green;">' . REF_ST_TYPE[$st_type_val_after] . '</b>';
            } else {
                $st_type_before = REF_ST_TYPE[$st_type_val_before];
                $st_type_after = REF_ST_TYPE[$st_type_val_after];
            }

            if ($before[0]['vin'] != $after[0]['vin']) {
                $vin_before = '<del style="color:red;">' .  str_replace('-', '&',$before[0]['vin']) . '</del>';
                $vin_after = '<b style="color:green;">' .  str_replace('-', '&',$after[0]['vin']) . '</b>';
            } else {
                $vin_before = $before[0]['vin'];
                $vin_after = $after[0]['vin'];
            }

            if ($before[0]['volume'] != $after[0]['volume']) {
                $volume_before = '<del style="color:red;">' . $before[0]['volume'] . '</del>';
                $volume_after = '<b style="color:green;">' . $after[0]['volume'] . '</b>';
            } else {
                $volume_before = $before[0]['volume'];
                $volume_after = $after[0]['volume'];
            }

            if ($before[0]['cost'] != $after[0]['cost']) {
                $amount_before = '<del style="color:red;">' . __money($before[0]['cost']) . '</del>';
                $amount_after = '<b style="color:green;">' . __money($after[0]['cost']) . '</b>';
            } else {
                $amount_before = __money($before[0]['cost']);
                $amount_after = __money($after[0]['cost']);
            }

            if ($before[0]['year'] != $after[0]['year']) {
                $year_before = '<del style="color:red;">' . $before[0]['year'] . '</del>';
                $year_after = '<b style="color:green;">' . $after[0]['year'] . '</b>';
            } else {
                $year_before = $before[0]['year'];
                $year_after = $after[0]['year'];
            }

            $html .= <<<CAR_T_BODY
        <tr>
          <td> $volume_cm </td><td> {$volume_before}  </td><td> {$volume_after}  </td>
        </tr>
        <tr>
          <td> $vin_code </td><td> {$vin_before} </td><td> {$vin_after}  </td>
        </tr>
        <tr>
          <td> $amount </td><td> {$amount_before} </td><td> {$amount_after}  </td>
        </tr>
        <tr>
          <td> $year_of_manufacture </td><td> {$year_before} </td><td> {$year_after} </td>
        </tr>
        <tr>
          <td> $date_of_import </td><td> {$before_dt_import} </td><td> {$after_dt_import} </td>
        </tr>
        <tr>
          <td> $country_of_manufacture </td><td> {$country_name_before} </td><td> {$country_name_after} </td>
        </tr>
        <tr>
          <td> $car_category </td><td> {$car_cat_before_name} </td><td> {$car_cat_after_name} </td>
        </tr>
        <tr>
          <td> $ref_st </td><td> {$st_type_before} </td><td> {$st_type_after} </td>
        </tr>
        <tr>
          <td> $transport_type </td><td> {$car_type_before} </td><td> {$car_type_after} </td>
        </tr>
        <tr>
          <td> $num_application </td><td> {$before[0]['profile_id']} </td><td> {$after[0]['profile_id']} </td>
          /tr>
        <tr>
          <td> $car_calculate_method </td><td> {$calc_method_before} </td><td> {$calc_method_after} </td>
        </tr>
        <tr>
          <td> $is_electric_car </td><td> {$is_electric_before} </td><td> {$is_electric_after} </td>
        </tr>
         <tr>
          <td colspan="1"> $initiator_label </td><td colspan="2"> $initiator_name </td>
        </tr>
      CAR_T_BODY;
        } else {
            $package_weight = $t->_("package-weight");
            $package_cost = $t->_("package-cost");
            $goods_weight = $t->_("goods-weight");
            $basis_date = $t->_("basis-date");
            $goods_cost = $t->_("goods-cost");

            if ($before[0]['ref_tn'] != $after[0]['ref_tn']) {
                $tn_before = RefTnCode::findFirstById($before[0]['ref_tn']);
                $tn_after = RefTnCode::findFirstById($after[0]['ref_tn']);

                $tn_code_before = '<del style="color:red;">' . $tn_before->code . '</del>';
                $tn_code_after = '<b style="color:green;">' . $tn_after->code . '</b>';
            } else {
                $tn_before = RefTnCode::findFirstById($before[0]['ref_tn']);

                $tn_code_before = $tn_before->code;
                $tn_code_after = $tn_before->code;
            }

            $basis_date_before = (array_key_exists('basis_date',
                    $before[0]) && $before[0]['basis_date'] > 0) ? date('d.m.Y', $before[0]['basis_date']) : '-';
            $basis_date_after = (array_key_exists('basis_date', $after[0]) && $after[0]['basis_date'] > 0) ? date('d.m.Y',
                $after[0]['basis_date']) : '-';

            if ($basis_date_before != $basis_date_after) {
                $basis_date_before = '<del style="color:red;">' . $basis_date_before . '</del>';
                $basis_date_after = '<b style="color:green;">' . $basis_date_after . '</b>';
            }

            $package_weight_before = (array_key_exists('package_weight', $before[0])) ? $before[0]['package_weight'] : '-';
            $package_weight_after = (array_key_exists('package_weight', $after[0])) ? $after[0]['package_weight'] : '-';

            if ($package_weight_before != $package_weight_after) {
                $package_weight_before = '<del style="color:red;">' . $package_weight_before . '</del>';
                $package_weight_after = '<b style="color:green;">' . $package_weight_after . '</b>';
            }

            $package_cost_before = (array_key_exists('package_cost', $before[0])) ? $before[0]['package_cost'] : '-';
            $package_cost_after = (array_key_exists('package_cost', $after[0])) ? $after[0]['package_cost'] : '-';

            if ($package_cost_before != $package_cost_after) {
                $package_cost_before = '<del style="color:red;">' . $package_cost_before . '</del>';
                $package_cost_before = '<b style="color:green;">' . $package_cost_before . '</b>';
            }

            $goods_cost_before = (array_key_exists('goods_cost', $before[0])) ? $before[0]['goods_cost'] : '-';
            $goods_cost_after = (array_key_exists('goods_cost', $after[0])) ? $after[0]['goods_cost'] : '-';

            if ($goods_cost_before != $goods_cost_after) {
                $goods_cost_before = '<del style="color:red;">' . $goods_cost_before . '</del>';
                $goods_cost_before = '<b style="color:green;">' . $goods_cost_before . '</b>';
            }

            $amount_before = ($before[0]['amount'] != $after[0]['amount']) ? '<del style="color:red;">' . __money($before[0]['amount']) . '</del>' : __money($before[0]['amount']);
            $amount_after = ($before[0]['amount'] != $after[0]['amount']) ? '<b style="color:green;">' . __money($after[0]['amount']) . '</b>' : __money($after[0]['amount']);

            $weight_before = ($before[0]['weight'] != $after[0]['weight']) ? '<del style="color:red;">' . $before[0]['weight'] . '</del>' : $before[0]['weight'];
            $weight_after = ($before[0]['weight'] != $after[0]['weight']) ? '<b style="color:green;">' . $after[0]['weight'] . '</b>' : $after[0]['weight'];

            $basis_before = ($before[0]['basis'] != $after[0]['basis']) ? '<del style="color:red;">' . $before[0]['basis'] . '</del>' : $before[0]['basis'];
            $basis_after = ($before[0]['basis'] != $after[0]['basis']) ? '<b style="color:green;">' . $after[0]['basis'] . '</b>' : $after[0]['basis'];

            if ($c_log->action == 'CREATED') {
                $html .= <<<T_BODY
          <tr>
            <td> Код ТНВЭД </td><td>_</td><td> {$tn_code_after}  </td>
          </tr>
          <tr>
            <td> $goods_weight </td><td>_</td><td> {$weight_after}  </td>
          </tr>
          <tr>
            <td> $amount </td><td>_</td><td> {$amount_after}  </td>
          </tr>
          <tr>
            <td> Номер счет-фактуры или ГТД </td><td>_</td><td> {$basis_after} </td>
          </tr>
          <tr>
            <td> $basis_date </td><td>_</td><td> {$basis_date_after} </td>
          </tr>
          <tr>
            <td> $date_of_import </td><td>_</td><td> {$after_dt_import} </td>
          </tr>
          <tr>
            <td> $country_of_manufacture </td><td>_</td><td> {$country_name_after} </td>
          </tr>
          <tr>
            <td> $num_application </td><td>_</td><td> {$after[0]['profile_id']} </td>
          </tr>
          <tr>
            <td> $package_weight </td><td>_</td><td> {$package_weight_after} </td>
          </tr>
          <tr>
            <td> $package_cost </td><td>_</td><td> {$package_cost_after} </td>
          </tr>
          <tr>
            <td> $goods_cost </td><td>_</td><td> {$goods_cost_after} </td>
          </tr>
          <tr>
            <td> $car_calculate_method </td><td>_</td><td> {$calc_method_after} </td>
          </tr>
        <tr>
          <td colspan="1"> $initiator_label </td><td colspan="2"> $initiator_name </td>
        </tr>
        T_BODY;
            } else {
                $html .= <<<T_BODY
          <tr>
            <td> Код ТНВЭД </td><td> {$tn_code_before}  </td><td> {$tn_code_after}  </td>
          </tr>
          <tr>
            <td> $goods_weight </td><td> {$weight_before} </td><td> {$weight_after}  </td>
          </tr>
          <tr>
            <td> $amount </td><td> {$amount_before} </td><td> {$amount_after}  </td>
          </tr>
          <tr>
            <td> Номер счет-фактуры или ГТД </td><td> {$basis_before} </td><td> {$basis_after} </td>
          </tr>
          <tr>
            <td> $basis_date </td><td> {$basis_date_before} </td><td> {$basis_date_after} </td>
          </tr>
          <tr>
            <td> $date_of_import </td><td> {$before_dt_import} </td><td> {$after_dt_import} </td>
          </tr>
          <tr>
            <td> $country_of_manufacture </td><td> {$country_name_before} </td><td> {$country_name_after} </td>
          </tr>
          <tr>
            <td> $num_application </td><td> {$before[0]['profile_id']} </td><td> {$after[0]['profile_id']} </td>
          </tr>
          <tr>
            <td> $package_weight </td><td> {$package_weight_before} </td><td> {$package_weight_after} </td>
          </tr>
          <tr>
            <td> $package_cost </td><td> {$package_cost_before} </td><td> {$package_cost_after} </td>
          </tr>
          <tr>
            <td> $goods_cost </td><td> {$goods_cost_before} </td><td> {$goods_cost_after} </td>
          </tr>
          <tr>
            <td> $car_calculate_method </td><td> {$calc_method_before} </td><td> {$calc_method_after} </td>
          </tr>
        <tr >
          <td colspan="1"> $initiator_label </td><td colspan="2"> $initiator_name </td>
        </tr>
        T_BODY;
            }
        }

        echo $html;
    }

    public function fileLogsAction(): void
    {
        // фильтры
        if ($this->request->isPost()) {
            $pid = $this->request->getPost('pid', ['int', 'absint']);
            $fid = $this->request->getPost('fid', ['int', 'absint']);
            $reset = $this->request->getPost('reset', 'string');

            if ($reset === 'all') {
                $this->session->remove('file_log_search_pid');
                $this->session->remove('file_log_search_fid');
            } else {
                if (!empty($pid)) {
                    $this->session->set('file_log_search_pid', (int)$pid);
                }
                if (!empty($fid)) {
                    $this->session->set('file_log_search_fid', (int)$fid);
                }
            }
        }

        $pid = $this->session->has('file_log_search_pid')
            ? (int)$this->session->get('file_log_search_pid')
            : null;

        $fid = $this->session->has('file_log_search_fid')
            ? (int)$this->session->get('file_log_search_fid')
            : null;

        $page = $this->request->getQuery('page', 'int', 1);

        // билдер
        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'f.profile_id    AS profile_id',
                'f.action        AS action',
                'f.dt            AS dt',
                'f.meta_before   AS meta_before',
                'f.meta_after    AS meta_after',
                'f.file_id       AS file_id',
                'f.type          AS type',
                'u.user_type_id  AS user_type_id',
                'u.fio           AS fio',
                'u.org_name      AS org_name',
                'u.idnum         AS idnum',
            ])
            ->from(['f' => FileLogs::class])
            ->join(User::class, 'u.id = f.user_id', 'u')
            ->where('f.id <> 0')
            ->orderBy('f.id DESC');

        $bind = [];

        if (!empty($pid)) {
            $builder->andWhere('f.profile_id = :pid:');
            $bind['pid'] = $pid;
        }
        if (!empty($fid)) {
            $builder->andWhere('f.file_id = :fid:');
            $bind['fid'] = $fid;
        }
        if ($bind) {
            $builder->setBindParams($bind);
        }

        $paginator = new PaginatorBuilder([
            'builder' => $builder,
            'limit' => 5,
            'page' => $page,
        ]);

        $this->view->page = $paginator->paginate();

        // можно передать текущие фильтры во view
        $this->view->setVars([
            'pid' => $pid,
            'fid' => $fid,
        ]);
    }

    public function getdocAction($id)
    {
        $this->view->disable();
        $path = APP_PATH . "/private/correction/";
        $cl = CorrectionLogs::findFirstById($id);
        if (file_exists($path . $cl->file) && $cl->file) {
            __downloadFile($path . $cl->file, $cl->file);
        } else {
            $this->flash->warning("Файл не найден");
            return $this->response->redirect("correction_logs");
        }
    }

    public function refFundLogsAction(): void
    {
        $page = $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->columns([
                'f.id           AS id',
                'f.action       AS action',
                'f.dt           AS dt',
                'f.meta_before  AS meta_before',
                'f.meta_after   AS meta_after',
                'u.user_type_id AS user_type_id',
                'u.fio          AS fio',
                'u.org_name     AS org_name',
                'u.idnum        AS idnum',
            ])
            ->from(['f' => RefFundLogs::class])
            ->join(User::class, 'u.id = f.user_id', 'u')
            ->orderBy('f.id DESC');

        $paginator = new PaginatorBuilder([
            'builder' => $builder,
            'limit'   => 5,
            'page'    => $page,
        ]);

        $this->view->page = $paginator->paginate();
    }
}
