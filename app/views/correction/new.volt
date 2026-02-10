<h3>{{ t._("add-good") }}</h3>
<form id="addGoodForm" method="POST" action="/correction/add_goods/" enctype="multipart/form-data" autocomplete="off">
  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("add-good") }} (в заявке {{ pid }})</div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">{{ t._("goods-weight") }}</label>
            <div class="controls">
              <input type="text" name="good_weight" class="form-control" maxlength="17" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Номер счет-фактуры или ГТД</label>
            <div class="controls">
              <input type="text" name="good_basis" class="form-control" maxlength="50" required>
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
              <input type="text" name="good_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
            </div>
          </div>
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("country") }}</label><br />
            <select name="good_country" class="selectpicker form-control" data-live-search="true">
              {% for i, cc in country %}
                <option value="{{ cc.id }}"{% if i == 0 %}selected{% endif %}>{{ t._(cc.name) }}</option>
              {% endfor %}
            </select>
          </div>
          <div class="form-group" id="car_cat_group">
            <label class="form-label">{{ t._("tn-code") }}</label><br />
            <select name="tn_code" class="selectpicker form-control" data-live-search="true">
              {% for i, code in tn_codes %}
                <option value="{{ code.id }}"{% if i == 0 %} selected{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
              {% endfor %}
            </select>
          </div>
          <!-- товар в упаковке -->
          <div class="form-group">
            <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#tn-code-add" aria-expanded="false" aria-controls="tn-code-add">Товар в упаковке?</button>
          </div>
          <div class="collapse" id="tn-code-add">
            <div class="form-group">
              <label class="form-label">Упаковка</label>
              <select name="tn_code_add" id="tn_code_add" class="selectpicker form-control" data-live-search="true">
                <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                {% for i, code in tn_codes %}
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
          <div class="form-group">
            <label class="form-label">{{ t._("Выберите способ расчета") }}</label><br>
            <input type="radio" name="calculate_method" class="EditCarCalcMethod" value="0" checked="checked"> По дате импорта<br>
            <input type="radio" name="calculate_method" class="EditCarCalcMethod" value="1"> По дате подачи заявки<br>
          </div>
          <div class="form-group" id="EditCarDtSent" style="display:none;">
            <label class="form-label">{{ t._("sent-date") }}</label>
            <div class="controls">
              <input type="text" name="md_dt_sent" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" value="{{ date("d.m.Y", md_dt_sent) }}" disabled="disabled"">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("comment") }}</label>
            <textarea name="good_comment" id="addGoodComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
          </div>
          <div class="form-group">    
            <label class="form-label">{{ t._("Загрузить файл") }}</label>
            <input type="file" id="addGoodFile" name="good_file" class="form-control-file" required>
          </div>
          <hr>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ pid }}">
          <input type="hidden" value="{{ sign_data }}" name="hash" id="addGoodHash">
          <textarea type="hidden" name="sign" id="addGoodSign" style="display: none;"></textarea>  
          <div class="row">
              <div class="col-auto">
                <button type="button" class="btn btn-warning signAddGoodsBtn">Подписать и добавить товар</button>
                <a href="/correction/" class="btn btn-danger">Отмена</a>
              </div>
            </div>   
        </div>
      </div>
    </div>
  </div>
</form>
