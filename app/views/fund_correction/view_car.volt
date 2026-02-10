<div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("edit-car") }} {{ car.id }} ( {{ t._("Заявка №") }} <span class="badge badge-success mb-2" style="font-size: 14px;"><?php echo __getFundNumber($car->fund_id); ?></span>)</div>
          <div class="card-body">
            <ul class="nav nav-tabs">
              <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#annulmentFundCarTab">Аннулирование</a>
              </li>
            </ul>
            <div class="tab-content p-3">

              <!-- Annulment Fund Car form -->
              <div class="tab-pane fade show active" id="annulmentFundCarTab">

                <h2 class="h4 mb-3">Аннулирование  ТС</h2>

                <form id="annulSingleFundCarForm" action="/fund_correction/annul_car/" method="POST"  enctype="multipart/form-data">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="form-group">
                      <label class="form-label">
                        {{ t._("volume-cm-for-car-and-bus") }} / {{ t._("full-mass-for-truck") }} / {{ t._("power-for-tc") }}
                      </label>
                      <div class="controls">
                        <input type="text" name="car_volume" class="form-control" value="{{ car.volume }}" disabled="disabled">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">{{ t._("cost") }}, тг</label>
                      <div class="controls">
                        <input type="text" name="car_cost" class="form-control" value="{{ car.cost }}" disabled="disabled">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label"> VIN-код, номер / Идентификационный или серийный номер / Номер кузова, шасси или двигателя</label>
                      <div class="controls">
                        <input type="text" name="car_vin" class="form-control" maxlength="17" value="{{ car.vin }}" disabled="disabled">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">{{ t._("Дата производства") }}</label>
                      <div class="controls">
                        <input type="text" name="car_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker"  value="{{ date("d.m.Y", car.date_produce) }}" disabled="disabled">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">{{ t._("Модель транспортного средства") }}</label>
                      <select name="model" class="selectpicker form-control" data-live-search="true" disabled="disabled">
                        {% for i, m in models %}
                        <option value="{{ m.id }}"{% if m.id == car.model_id %} selected{% endif %}>{{ m.brand }} {{ m.model }}</option>
                        {% endfor %}
                      </select>
                    </div>
                    <div class="form-group" id="car_cat_group">
                      <label class="form-label">{{ t._("car-category") }}</label>
                      <select name="car_cat" class="form-control" disabled="disabled">
                        {% for type in car_types %}
                          <optgroup label="{{ t._(type.name) }}">
                            {% for cat in car_cats %}
                            {% if type.id == cat.car_type %}
                              <option value="{{ cat.id }}"{% if car.ref_car_cat == cat.id %} selected{% endif %}>{{ t._(cat.name) }}</option>
                            {% endif %}
                            {% endfor %}
                          </optgroup>
                        {% endfor %}
                      </select>
                    </div>
                    <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                      <label class="form-label">{{ t._("ref-st") }}</label>
                      <select name="ref_st" class="form-control" disabled="disabled">
                        <option value="0"{% if car.ref_st_type == 0 %} selected{% endif %}>{{ t._("ref-st-not") }}</option>
                        <option value="1"{% if car.ref_st_type == 1 %} selected{% endif %}>{{ t._("ref-st-yes") }}</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">{{ t._("comment") }}</label>
                      <textarea name="car_comment" id="annulSingleFundCarComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
                    </div>
                    <div class="form-group">
                      <label class="form-label">{{ t._("Загрузить файл") }}</label>
                      <input type="file" id="annulSingleFundCarFile" name="car_file" class="form-control-file" required>
                    </div>
                    <hr>
                    <div class="row">
                      <input type="hidden" name="car_id" value="{{ car.id }}">
                      <input type="hidden" value="{{ sign_data }}" name="hash" id="annulSingleFundCarHash">
                      <textarea type="hidden" name="sign" id="annulSingleFundCarSign" style="display: none;"></textarea>
                      <div class="col-auto">
                        <button type="button" class="btn btn-warning signAnnulSingleFundCarsBtn">Подписать и аннулировать ТС</button>
                        <a href="/fund_correction/" class="btn btn-danger">Отмена</a>
                      </div>
                    </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>
{% if logs|length > 0 %}
<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("История корректирования") }}</div>
        <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{t._("Обьект ID")}}</th>
              <th>{{t._("Пользователь")}}</th>
              <th>{{t._("Действия")}}</th>
              <th>{{t._("Дата")}}</th>
              <th>{{t._("До")}}</th>
              <th>{{t._("После")}}</th>
              <th>{{t._("comment")}}</th>
              <th>{{t._("Файл")}}</th>
            </tr>
          </thead>
          <tbody>
            {% for log in logs %}
              <tr>
                <td>
                  № : <?php echo __getFundNumber($log->fund_id);?><br>
                  FundCarId: {{ log.object_id }}
                </td>
                <td>{{ log.iin }}</td>
                <td>{{ t._(log.action) }}</td>
                <td><?=date('d-m-Y H:i', $log->dt)?></td>
                <td>
                    <?php
                      $arr = json_decode($log->meta_before, true);
                      if(is_array($arr)){
                        foreach($arr as $a){
                            foreach($a as $key => $value){
                            echo $key . " : " . $value . "<br />";
                            }
                        }
                      }else{
                        echo $log->meta_before;
                      }
                    ?>
                </td>
                <td>
                    <?php
                        $arr = json_decode($log->meta_after, true);
                        if(is_array($arr)){
                            foreach($arr as $a){
                                foreach($a as $key => $value){
                                    echo $key . " : " . $value . "<br />";
                                }
                            }
                        }else{
                            echo $log->meta_after;
                        }
                    ?>
                </td>
                <td>{{ log.comment }}</td>
                <td><a href="/fund_correction/getdoc/{{ log.id }}">{{ log.file }}</a></td>
              </tr>
            {% endfor %}          
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
  {% endif %}


 


