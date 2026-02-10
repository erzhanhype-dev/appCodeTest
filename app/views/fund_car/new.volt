<h3>{{ t._("add-car") }}</h3>

<form action="/fund_car/add" method="post" id='frm_car' autocomplete="off">
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("add-car") }}</div>
        <div class="card-body">
          <div class="form-group" id="car_volume_group">
            <label class="form-label">
              {% if m == 'CAR' %}{{ t._("volume-cm-for-car-and-bus") }} / {{ t._("full-mass-for-truck") }}{% endif %}
              {% if m == 'TRAC' %}{{ t._("power-for-tc") }}{% endif %}
            </label>
            <div class="controls">
              <input type="number" name="car_volume" id="car_volume" class="form-control" placeholder="1600.00" 
                         step=".01" min="0" max="50000" autocomplete="off" required>
            </div>
          </div>
          {% if m == 'CAR' %}
          <div class="form-group">
            <label class="form-label">{{ t._("vin-code") }}</label>
            <div class="controls">
              <input type="text" name="car_vin" id="car_vin" class="form-control" minlength="17" maxlength="17"
               placeholder="XXXXXXXXXXXXXXXXX" autocomplete="off" required>
               <small id="car_vin" class="form-text text-muted">
                VIN-код должен содержать в себе только символы на латинице и цифры, не больше 17 символов.
               </small>
            </div>
          </div>
          {% endif %}
          {% if m == 'TRAC' %}
          <div class="form-group">
            <label class="form-label">{{ t._("id-code") }}</label>
            <div class="controls">
              <input type="text" name="car_id_code" id="car_id_code" class="form-control" autocomplete="off">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("body-code") }}</label>
            <div class="controls">
              <input type="text" name="car_body_code" id="car_body_code" class="form-control" autocomplete="off">
            </div>
          </div>
          {% endif %}
          <div class="form-group">
            <label class="form-label">{{ t._("Дата производства") }}</label>
            <div class="controls">
              <input type="text" name="car_date" id="car_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" 
                data-date-end-date="0d" class="form-control datepicker" placeholder="{{ date('d.m.Y') }}" autocomplete="off" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("Модель транспортного средства") }}</label>
            <select name="model" id="model" class="selectpicker form-control" data-live-search="true">
              {% for i, m in model %}
              <option value="{{ m.id }}"{% if i == 0 %} selected{% endif %}>{{ m.brand }} {{ m.model }}</option>
              {% endfor %}
            </select>
          </div>
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("car-category") }}</label>
            <select name="car_cat" id="car_cat" class="selectpicker form-control" data-live-search="true">
              {% for type in car_types %}
                <optgroup label="{{ t._(type.name) }}">
                  {% for i, cat in cats %}
                  {% if type.id == cat.car_type %}
                    <option value="{{ cat.id }}"{% if i == 0 %} selected{% endif %}>{{ t._(cat.name) }}</option>
                  {% endif %}
                  {% endfor %}
                </optgroup>
              {% endfor %}
            </select>
            <small id="car_cat" class="form-text text-muted">
              Укажите категорию транспортного средства согласно его подтверждающих документов.
            </small>
          </div>
          <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
            <label class="form-label">{{ t._("ref-st") }}</label>
            <select name="ref_st" id="ref_st" class="selectpicker form-control" data-live-search="true">
              <option value="0" selected="selected">{{ t._("ref-st-not") }}</option>
              <option value="1">{{ t._("ref-st-yes") }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("car-calculate-method") }}</label><br>
            <input type="radio" name="calculate_method" value="0" checked> По дате производства<br>
            <input type="radio" name="calculate_method" value="1"> По дате подачи заявки<br>
            <input type="radio" name="calculate_method" value="2"> По дате УП<br>
          </div>
          <input type="hidden" name="fund" value="{{ pid }}">
          <button type="submit" class="btn btn-success" id="car_button_submit" name="button">{{ t._("add-car") }}</button>        
        </div>
      </div>
    </div>
  </div>
</form>
