<h3>{{ t._("edit-good") }}</h3>

<form action="/goods/edit/{{ good.id }}" method="post" id="frm_goods" autocomplete="off">
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("edit-good") }}</div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">{{ t._("goods-weight") }}</label>
            <div class="controls">
              <input type="text" name="good_weight" id="good_weight" class="form-control" maxlength="17" value="{{ good.weight }}" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Номер счет-фактуры или ГТД</label>
            <div class="controls">
              <input type="text" name="good_basis" id="good_basis" class="form-control" maxlength="100" placeholder="XXXXX/XXXXX/XXXXX" value="{{ good.basis }}" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("basis-date") }}</label>
            <div class="controls">
              <input type="text" name="basis_date" value="{{ date("d.m.Y", good.basis_date) }}" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Дата импорта или реализации</label>
            <div class="controls">
              <input type="text" name="good_date" id="good_date"  value="{{ date("d.m.Y", good.date_import) }}" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker">
            </div>
          </div>
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("country") }}</label>
            <select name="good_country" id="good_country" class="selectpicker form-control" data-live-search="true">
              {% for cc in country %}
                <option value="{{ cc.id }}"{% if good.ref_country == cc.id %} selected="selected"{% endif %}>{{ t._(cc.name) }}</option>
              {% endfor %}
            </select>
          </div>
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("tn-code") }}</label>
            <select name="tn_code" id="tn_code" class="selectpicker form-control" data-live-search="true">
              {% for code in tn_codes %}
                <option value="{{ code.id }}"{% if good.ref_tn == code.id %} selected="selected"{% endif %} data-pack="{{ code.pay_pack }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
              {% endfor %}
            </select>
          </div>
          <!-- товар в упаковке -->
          <div class="form-group" id="pay_pack">
            <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#tn-code-add" aria-expanded="false" aria-controls="tn-code-add">Товар в упаковке?</button>
          </div>
          <div class="collapse{% if good.ref_tn_add %} in{% endif %}" id="tn-code-add">
            <div class="form-group">
              <label class="form-label">Вид упаковки</label>
              <select name="tn_code_add" id="tn_code_add" class="selectpicker form-control" data-live-search="true">
                <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                {% for code in tn_codes_package %}
                <option value="{{ code.id }}"{% if good.ref_tn_add == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                {% endfor %}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t._("package-weight") }}</label>
              <div class="controls">
                <input type="text" name="package_weight" class="form-control" placeholder="0.000" value="{{ good.package_weight }}">
              </div>
            </div>
          </div>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ good.profile_id }}">
          <button type="submit" class="btn btn-success" name="button">{{ t._("edit-good") }}</button>    
        </div>
      </div>
    </div>
  </div>
</form>
