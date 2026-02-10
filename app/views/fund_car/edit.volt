<?php
 $editable = ($type == 'INS') ? 'disabled="disabled"' : '';
?>
<h3>{{ t._("edit-car") }}</h3>

<form action="/fund_car/edit/{{ car.id }}" method="post" id="frm_car" autocomplete="off">
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("edit-car") }}</div>
        <div class="card-body">
          <div class="form-group" id="car_volume_group">
            <label class="form-label">
              {% if m == 'CAR' %}{{ t._("volume-cm-for-car-and-bus") }} / {{ t._("full-mass-for-truck") }}{% endif %}
              {% if m == 'TRAC' %}{{ t._("power-for-tc") }}{% endif %}
            </label>
            <div class="controls">
              <input type="text" name="car_volume" id="car_volume" class="form-control" step=".01" placeholder="1600.00"
                value="{{ car.volume }}" autocomplete="off" required {{ editable }}>
            </div>
          </div>
          {% if m == 'CAR' %}
          <div class="form-group">
            <label class="form-label">{{ t._("vin-code") }}</label>
            <div class="controls">
              <input type="text" name="car_vin" id="car_vin" class="form-control" maxlength="17" value="{{ car.vin }}"
                    maxlength="17" minlength="17" placeholder="XXXXXXXXXXXXXXXXX" autocomplete="off" 
                    required {{ editable }}>
            </div>
          </div>
          {% endif %}
          {% if m == 'TRAC' %}
          <div class="form-group">
            <label class="form-label">{{ t._("id-code") }}</label>
            <div class="controls">
              <input type="text" name="car_id_code" id="car_id_code" class="form-control" 
              value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off"
                    required {{ editable }}>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("body-code") }}</label>
            <div class="controls">
              <input type="text" name="car_body_code" id="car_body_code" class="form-control" 
              value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off"
                    required {{ editable }}>
            </div>
          </div>
          {% endif %}
          <div class="form-group">
            <label class="form-label">{{ t._("Дата производства") }}</label>
            <div class="controls">
              {% if type == 'INS' %}
                <input type="text" name="car_date" class="form-control" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
                          value="{{ date("d.m.Y", car.date_produce) }}" readonly>
              {% else %}
                <input type="text" name="car_date" id="car_date" data-provide="datepicker" 
                  data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" 
                  class="form-control datepicker" value="{{ date("d.m.Y", car.date_produce) }}"
                  placeholder="{{ date('d.m.Y') }}" autocomplete="off" required>
              {% endif %}
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("Модель транспортного средства") }}</label>
            <select name="model" id="model" class="selectpicker form-control" data-live-search="true">
              {% if car.model_id == 0 %}
                  <option value="0" selected>- Не указан -</option>
              {% endif %}
              {% for i, m in model %}
                <option value="{{ m.id }}"{% if m.id == car.model_id %} selected{% endif %}>{{ m.brand }} {{ m.model }}</option>
              {% endfor %}
            </select>
          </div>  
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("car-category") }}</label>
            <select name="car_cat" id="car_cat" class="form-control" {{ (type == 'INS') ? 'disabled' : '' }}>
              {% for type in car_types %}
                <optgroup label="{{ t._(type.name) }}">
                  {% for cat in car_cats %}
                  {% if type.id == cat.car_type %}
                    <option value="{{ cat.id }}" {% if car.ref_car_cat == cat.id %} selected{% endif %}>{{ t._(cat.name) }}</option>
                  {% endif %}
                  {% endfor %}
                </optgroup>
              {% endfor %}
            </select>
          </div>
          <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
            <label class="form-label">{{ t._("ref-st") }}</label>
            <select name="ref_st" id="ref_st" class="form-control" {{ editable }}>
              <option value="0"{% if car.ref_st_type == 0 %} selected{% endif %}>{{ t._("ref-st-not") }}</option>
              <option value="1"{% if car.ref_st_type == 1 %} selected{% endif %}>{{ t._("ref-st-yes") }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("car-calculate-method") }}</label><br>
            <label class="form-label">
              <input type="radio" name="calculate_method" value="0" {% if car.calculate_method == 0 %} checked{% endif %} {{ editable }}> По дате производства
            </label><br>
            <label class="form-label">
              <input type="radio" name="calculate_method" value="1" {% if car.calculate_method == 1 %} checked{% endif %} {{ editable }}> По дате подачи заявки
            </label><br>
            <label class="form-label">
              <input type="radio" name="calculate_method" value="2" {% if car.calculate_method == 2 %} checked{% endif %} {{ editable }}> По дате УП
            </label><br>
          </div>
          <button type="submit" class="btn btn-success" name="button">{{ t._("save-car") }}</button>
        </div>
      </div>
    </div>
  </div>
</form>
