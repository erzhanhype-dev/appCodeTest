<h3>{{ t._("add-good") }}</h3>

<form action="/fund_goods/add" method="post" id="frm_goods">
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("add-good") }}</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">{{ t._("goods-weight") }}</label>
          <div class="controls">
            <input type="text" name="good_weight" id="good_weight" class="form-control" maxlength="17" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Номер счет-фактуры или ГТД</label>
          <div class="controls">
            <input type="text" name="good_basis" id="good_basis" class="form-control" maxlength="100" placeholder="XXXXX/XXXXX/XXXXX" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t._("basis-date") }}</label>
          <div class="controls">
            <input type="text" name="basis_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t._("import-date") }}</label>
          <div class="controls">
            <input type="text" name="good_date" id="good_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
          </div>
        </div>
        <div class="form-group" id="car_cat_group">
          <label class="form-label">{{ t._("country") }}</label><br />
          <select name="good_country" id="good_country" class="selectpicker form-control" data-live-search="true">
            {% for i, cc in country %}
              <option value="{{ cc.id }}"{% if i == 0 %}selected{% endif %}>{{ t._(cc.name) }}</option>
            {% endfor %}
          </select>
        </div>
        <div class="form-group" id="car_cat_group">
          <label class="form-label">{{ t._("tn-code") }}</label><br />
          <select name="tn_code" id="tn_code" class="selectpicker form-control" data-live-search="true">
            {% for i, code in tn_codes %}
              <option value="{{ code.id }}"{% if i == 0 %} selected{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
            {% endfor %}
          </select>
        </div>
        <input type="hidden" name="fund" value="{{ pid }}">
        <button type="submit" class="btn btn-success" name="button">{{ t._("add-good") }}</button>
      </div>
    </div>
  </div>
</div>
</form>

