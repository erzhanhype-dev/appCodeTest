<h3>{{ t._("add-good") }}</h3>

{{ form("kpp/edit/"~kpp.id, "method": "post", "id": "frm_goods") }}
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">

<div class="row">
    <div class="col-md-6 col-xs-12">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("edit-kpp") }}</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">{{ t._("kpp-weight") }}</label>
                    <div class="controls">
                        <input type="text" name="kpp_weight" id="kpp_weight" value="{{ kpp.weight }}" class="form-control" maxlength="17" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Номер счет-фактуры или ГТД</label>
                    <div class="controls">
                        <input type="text" name="kpp_basis" id="kpp_basis" value="{{ kpp.basis }}" class="form-control" maxlength="50" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("basis-date") }}</label>
                    <div class="controls">
                        <input type="text" name="basis_date" value="<?php echo date('d.m.Y',$kpp->basis_date); ?>" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Код валюты в инвойсе</label><br />
                    <select name="currency_type" id="currency_type" class="selectpicker form-control" data-live-search="true">
                        <option value="KZT" {% if kpp.currency_type == 'KZT' %} selected {% endif %} >KZT</option>
                        {% for i, curr in currencies %}
                            <option value="{{ curr.title }}" {% if kpp.currency_type == curr.title %} selected {% endif %} >{{ t._(curr.title) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cумма в инвойсе</label>
                    <div class="controls">
                        {% if kpp.currency_type == 'KZT' %}
                            <input type="text" name="sum" id="sum" value="{{ kpp.invoice_sum }}" class="form-control" maxlength="50" required>
                        {% else %}
                            <input type="text" name="sum" id="sum" value="{{ kpp.invoice_sum_currency }}" class="form-control" maxlength="50" required>
                        {% endif %}
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("import-date") }}</label>
                    <div class="controls">
                        <?php $date = date('d.m.Y',$kpp->date_import); ?>
                        <input type="text" name="kpp_date" id="kpp_date" value="{{ date }}" data-provide="datepicker" data-date-start-date="{{ constant('START_GET_KPP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("country") }}</label><br />
                    <select name="kpp_country" id="kpp_country" class="selectpicker form-control" data-live-search="true">
                        {% for i, cc in country %}
                            <option value="{{ cc.id }}"{% if cc.id == kpp.ref_country %}selected{% endif %}>{{ t._(cc.name) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("tn-code") }}</label><br />
                    <select name="tn_code" id="tn_code" class="selectpicker form-control" data-live-search="true">
                        {% for i, code in tn_codes %}
                            <option value="{{ code.id }}"{% if code == kpp.ref_tn %} selected{% endif %} >{{ t._(code.code)~" - "~t._(code.group) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <!-- товар в упаковке -->
                <div class="form-group">
                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#kpp-tn-code-add" aria-expanded="false" aria-controls="kpp-tn-code-add">Товар в упаковке?</button>
                </div>
                <div class="collapse{% if kpp.package_tn_code %} in{% endif %}" id="kpp-tn-code-add">
                    <div class="form-group">
                        <label class="form-label">Товар в упаковке</label>
                        <select name="package_tn_id" id="kpp-tn-code-add" class="selectpicker form-control" data-live-search="true">
                            <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                            {% for code in package_tn_codes %}
                                <option value="{{ code.id }}"{% if kpp.package_tn_code == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("package-weight") }}</label>
                        <div class="controls">
                            <input type="text" name="package_weight" class="form-control" placeholder="0.000" value="{{ kpp.package_weight }}">
                        </div>
                    </div>
                </div>
                <!-- конец товара в упаковке -->

                <input type="hidden" name="profile" value="{{ kpp.id }}">
                <button type="submit" class="btn btn-success" name="button">{{ t._("edit-kpp") }}</button>
            </div>
        </div>
    </div>
</div>
</form>
