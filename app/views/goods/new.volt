<h3>{{ t._("add-good") }}</h3>

<form action="/goods/add" method="post" id="frm_goods" autocomplete="off">
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
                <option value="{{ code.id }}"{% if i == 0 %} selected{% endif %} data-pack="{{ code.pay_pack }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
              {% endfor %}
            </select>
          </div>
          <!-- товар в упаковке -->
          <div class="form-group" id="pay_pack">
            <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#tn-code-add" aria-expanded="false" aria-controls="tn-code-add">Товар в упаковке?</button>
          </div>
          <div class="collapse" id="tn-code-add" >
            <div class="form-group">
              <label class="form-label">Вид упаковки</label>
              <select name="tn_code_add" id="tn_code_add" class="selectpicker form-control" data-live-search="true">
                <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                {% for i, code in tn_codes_package %}
                <option value="{{ code.id }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
                {% endfor %}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t._("package-weight") }}</label>
              <div class="controls">
                <input type="text" name="package_weight" class="form-control" placeholder="0.000">
              </div>
            </div>
          </div>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ pid }}">
          <button type="submit" class="btn btn-success" name="button">{{ t._("add-good") }}</button>      
        </div>
      </div>
    </div>
  </div>
</form>
