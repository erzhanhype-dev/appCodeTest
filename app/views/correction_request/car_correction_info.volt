 {% if (cc_pr.status != 'APPROVED_BY_MODERATOR') %}
<?php
      $diff_value = '';

      $vin_before = '';
      $volume_before = '';
      $cost_before = '';
      $before_country = '';
      $before_country_import = '-';
      $cat_before = '';
      $dimport_before = '';
      $ctype_before = '';
      $year_before = '';
      $calculate_method_before = 'По дате производства (импорта)';
      $e_car_before = 'NULL';

      $vin_after = '';
      $volume_after = '';
      $cost_after = '';
      $after_country = '';
      $after_country_import = '-';
      $cat_after = '';
      $dimport_after = '';
      $ctype_after = '';
      $year_after = '';
      $ref_st_before = '';
      $ref_st_after = '';
      $calculate_method_after = 'По дате производства (импорта)';
      $e_car_after = 'NULL';

      if($before->vin == $after->vin){
        $vin_before = str_replace('-', '&',$before->vin);
        $vin_after =  str_replace('-', '&',$after->vin);
      }else{
        $vin_before =  '<del style="color:orange;">'.str_replace('-', '&',$before->vin).'</del>';
        $vin_after = '<b style="color:green;">'.str_replace('-', '&',$after->vin).'</b>';
      }

      if($before->cost == $after->cost){
        $cost_before = $before->cost;
        $cost_after =  $after->cost;
      }else{
        $cost_before =  '<del style="color:orange;">'.number_format($before->cost, 2, ",", "&nbsp;").'</del>';
        $cost_after = '<b style="color:green;">'.number_format($after->cost, 2, ",", "&nbsp;").'</b>';
      }

      if($before->year == $after->year){
        $year_before = $before->year;
        $year_after =  $after->year;
      }else{
        $year_before =  '<del style="color:orange;">'.$before->year.'</del>';
        $year_after = '<b style="color:green;">'.$after->year.'</b>';
      }

      if($before->volume == $after->volume){
        $volume_before = $before->volume;
        $volume_after =  $after->volume;
      }else{
        $volume_before =  '<del style="color:orange;">'.$before->volume.'</del>';
        $volume_after = '<b style="color:green;">'.$after->volume.'</b>';
      }

      if($country_before->id == $country_after->id){
        $before_country = $country_before->name;
        $after_country =  $country_after->name;
      }else{
        $before_country =  '<del style="color:orange;">'.$country_before->name.'</del>';
        $after_country = '<b style="color:green;">'.$country_after->name.'</b>';
      }

     if($country_import_before && $country_import_after){
         if($country_import_before->id == $country_import_after->id){
            $before_country_import = $country_import_before->name;
            $after_country_import =  $country_import_after->name;
         }else{
            $before_country_import =  '<del style="color:orange;">'.$country_import_before->name.'</del>';
            $after_country_import = '<b style="color:green;">'.$country_import_after->name.'</b>';
         }
     }

      if($car_type_before->id == $car_type_after->id){
        $ctype_before = $car_type_before->name;
        $ctype_after =  $car_type_after->name;
      }else{
        $ctype_before =  '<del style="color:orange;">'.$car_type_before->name.'</del>';
        $ctype_after = '<b style="color:green;">'.$car_type_after->name.'</b>';
      }

      if($car_cat_before->id == $car_cat_after->id){
        $cat_before = $t->_($car_cat_before->name);
        $cat_after =  $t->_($car_cat_after->name);
      }else{
        $cat_before =  '<del style="color:orange;">'.$t->_($car_cat_before->name).'</del>';
        $cat_after = '<b style="color:green;">'.$t->_($car_cat_after->name).'</b>';
      }

      if($before->date_import == $after->date_import){
        $dimport_before = date("d-m-Y",  $before->date_import );
        $dimport_after =  date("d-m-Y",  $after->date_import );
      }else{
        $dimport_before =  '<del style="color:orange;">'.date("d-m-Y", convertTimeZone($before->date_import)).'</del>';
        $dimport_after = '<b style="color:green;">'.date("d-m-Y", convertTimeZone($after->date_import)).'</b>';
      }

      if(intval($before->ref_st_type) == 0){
            $ref_st_before = $t->_('ref-st-not');
      }elseif(intval($before->ref_st_type) == 1){
            $ref_st_before = $t->_('ref-st-yes');
      }elseif(intval($before->ref_st_type) == 2){
          $ref_st_before = $t->_('ref-st-international-transport');
      }

      if(intval($after->ref_st_type ) == 0){
          $ref_st_after = $t->_('ref-st-not');
      }elseif( intval($after->ref_st_type ) == 1){
          $ref_st_after = $t->_('ref-st-yes');
      }else{
          $ref_st_after = $t->_('ref-st-international-transport');
      }

      if($ref_st_before == $ref_st_after){
          $ref_st_before = $ref_st_before;
          $ref_st_after = $ref_st_after;
      }else{
          $ref_st_before =  '<del style="color:orange;">'.$ref_st_before.'</del>';
          $ref_st_after = '<b style="color:green;">'.$ref_st_after.'</b>';
      }

      if(intval($before->calculate_method) == 1){
          $calculate_method_before = 'По дате подачи заявки';
      }

      if(intval($after->calculate_method) == 1){
          $calculate_method_after = 'По дате подачи заявки';
      }

      if($before->calculate_method != $after->calculate_method){
         $calculate_method_before =  '<del style="color:orange;">'.$calculate_method_before.'</del>';
         $calculate_method_after = '<b style="color:green;">'.$calculate_method_after.'</b>';
      }

     if(intval($before->electric_car) == 1){
        $e_car_before = $t->_('yesno-1');
     }

     if(intval($before->electric_car) == 0){
        $e_car_before = $t->_('yesno-0');
     }

     if(intval($after->electric_car) == 1){
        $e_car_after = $t->_('yesno-1');
     }

     if(intval($after->electric_car) == 0){
        $e_car_after = $t->_('yesno-0');
     }

     if(intval($before->electric_car) != intval($after->electric_car)){
         $e_car_before =  '<del style="color:orange;">'.$e_car_before.'</del>';
         $e_car_after = '<b style="color:green;">'.$e_car_after.'</b>';
     }

    ?>
    <!-- содержимое заявки -->
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Содержимое заявки") }}
      </div>
        <div class="card-body">
          <div class="row">
            <div class="col-8">
              <!-- Данные до -->
              <div class="card mt-3">
                <div class="card-header bg-light text-dark">
                  {{ t._("Данные до") }}
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col"><strong>{{ t._("vin-code") }}</strong></div>
                    <div class="col">{{ vin_before }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("volume-cm") }}</strong></div>
                    <div class="col">{{ volume_before }}</del></div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("year-of-manufacture") }}</strong></div>
                    <div class="col">{{ year_before }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("date-of-import") }}</strong></div>
                    <div class="col"> {{ dimport_before }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("country-of-manufacture") }}</strong></div>
                    <div class="col">{{ before_country }}</div>
                  </div>
                    <div class="row">
                        <div class="col"><strong>{{ t._("country-of-import") }}</strong></div>
                        <div class="col">{{ before_country_import }}</div>
                    </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("car-category") }}</strong></div>
                    <div class="col">{{ cat_before }}</div>
                  </div>
                    <div class="row">
                        <div class="col"><strong>{{ t._("ref-st") }}</strong></div>
                        <div class="col">{{ ref_st_before }}</div>
                    </div>
                  <div class="row">
                    <div class="col"><strong>Платеж</strong></div>
                    <div class="col">{{ cost_before }} тг {{ diff_value }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("transport-type") }}</strong></div>
                    <div class="col">{{ ctype_before }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("num-application") }}</strong></div>
                    <div class="col">{{ before.profile_id }}</div>
                  </div>
                  <div class="row">
                    <div class="col"><strong>{{ t._("car-calculate-method") }}</strong></div>
                    <div class="col">{{ calculate_method_before }}</div>
                  </div>
                  <div class="row">
                      <div class="col"><strong>{{ t._("is_electric_car?") }}</strong></div>
                      <div class="col">{{ e_car_before }}</div>
                  </div>
                </div>
              </div>
              <!-- /Данные до -->

            </div>
            <div class="col-4">
              <!-- Данные после -->
              <div class="card mt-3">
                <div class="card-header bg-light text-dark">
                  {{ t._("Данные после") }}
                </div>
                <div class="card-body">
                <div class="row">
                    <div class="col">{{ vin_after }}</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ volume_after }}</b></div>
                  </div>
                  <div class="row">
                    <div class="col">{{ year_after }}</div>
                  </div>
                  <div class="row">
                    <div class="col"> {{ dimport_after }}</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ after_country }}</div>
                  </div>
                    <div class="row">
                        <div class="col">{{ after_country_import }}</div>
                    </div>
                  <div class="row">
                    <div class="col">{{ cat_after }}</div>
                  </div>
                  <div class="row">
                      <div class="col">{{ ref_st_after }}</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ cost_after }} тг</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ ctype_after }}</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ after.profile_id }}</div>
                  </div>
                  <div class="row">
                    <div class="col">{{ calculate_method_after }}</div>
                  </div>
                  <div class="row">
                      <div class="col">{{ e_car_after }}</div>
                  </div>
                </div>
              </div>
              <!-- /Данные после -->
            </div>
          </div>
            {% if(initiator) %}
                <div class="row">
                    <div class="col">Инициатор: {{ initiator.name }}</div>
                </div>
            {% endif %}
      </div>
    </div>
{% else %}
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
            {{ t._("Содержимое заявки") }}
        </div>
        <div class="card-body" id="APPROVED_CORRECTION_REQUEST">
          <input type="hidden" id="approvedCorrectionReqPid" value="{{ cc_pr.id }}">
            <table class="table table-bordered table-sm" id="corr_changes_after_approved">
              <thead>
                <tr>
                  <th>Название поля</th>
                  <th>Данные до</th>
                  <th>Данные после</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            {% if(initiator) %}
                <div class="row">
                    <div class="col">Инициатор: {{ initiator.name }}</div>
                </div>
            {% endif %}
        </div>
    </div>
{% endif %}