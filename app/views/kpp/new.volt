<h3>{{ t._("add-good") }}</h3>

{{ form("kpp/add", "method": "post", "id": "frm_goods") }}
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
    <div class="col-md-6 col-xs-12">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("add-kpp") }}</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">{{ t._("kpp-weight") }}</label>
                    <div class="controls">
                        <input type="text" name="kpp_weight" id="kpp_weight" class="form-control" maxlength="17" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Номер счет-фактуры или ГТД</label>
                    <div class="controls">
                        <input type="text" name="kpp_basis" id="kpp_basis" class="form-control" maxlength="50" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("basis-date") }}</label>
                    <div class="controls">
                        <input type="text" name="basis_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Код валюты в инвойсе</label><br />
                    <select name="currency_type" id="currency_type" class="selectpicker form-control" data-live-search="true">
                        <option value="KZT" >KZT</option>
                        {% for i, curr in currencies %}
                            <option value="{{ curr.title }}" >{{ t._(curr.title) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cумма в инвойсе</label>
                    <div class="controls">
                        <input type="text" name="sum" id="sum" class="form-control" maxlength="50" required>
                    </div>
                </div>
                <div class="f orm-group">
                    <label class="form-label">{{ t._("import-date") }}</label>
                    <div class="controls">
                        <input type="text" name="kpp_date" id="kpp_date" data-provide="datepicker" data-date-start-date="{{ constant('START_GET_KPP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("country") }}</label><br />
                    <select name="kpp_country" id="kpp_country" class="selectpicker form-control" data-live-search="true">
                        {% for i, cc in country %}
                            <option value="{{ cc.id }}"{% if i == 0 %}selected{% endif %}>{{ t._(cc.name) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("tn-code") }}</label><br />
                    <select name="tn_code" id="tn_code" class="selectpicker form-control" data-live-search="true">
                        {% for i, code in tn_codes %}
                            <option value="{{ code.id }}"{% if i == 0 %} selected{% endif %} >{{ t._(code.code)~" - "~t._(code.group) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <!-- товар в упаковке -->
                <div class="form-group">
                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#tn-code-add-kpp" aria-expanded="false" aria-controls="tn-code-add">Товар в упаковке?</button>
                </div>
                <div class="collapse" id="tn-code-add-kpp">
                    <div class="form-group">
                        <label class="form-label">Товар в упаковке</label>
                        <select name="package_tn_id" class="selectpicker form-control" data-live-search="true">
                            <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                            {% for i, code in tn_codes_add %}
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
                <button type="submit" class="btn btn-success" name="button">{{ t._("add-kpp") }}</button>
            </div>
        </div>
    </div>
</div>
</form>
