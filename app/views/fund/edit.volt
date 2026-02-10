<h3>{{ t._("Изменения данных в заявке") }}</h3>

<form action="/fund/edit/{{ fund.id }}" method="post" id="frm_order" autocomplete="off">

<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Изменение заявки на финансирование") }}</div>
            <div class="card-body">
                <input type="hidden" name="order_type" value="{{ fund.type }}">
                <div class="form-group">
                    <label class="form-label">{{ t._("Место реализации") }}</label>
                    <select name="car_country" id="car_country" class="selectpicker form-control"
                            data-live-search="true">
                        {% for i, country in countries %}
                            <option value="{{ country.id }}"{% if country.id == fund.ref_country_id %} selected{% endif %}>{{ country.name }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Начало отчетного периода") }}</label>
                    <div class="controls">
                        <input type="text" name="period_start" id="period_start" data-provide="datepicker"
                               class="form-control datepicker"
                               value="<?php echo date('d.m.Y', $fund->period_start); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Конец отчетного периода") }}</label>
                    <div class="controls">
                        <input type="text" name="period_end" id="period_end" data-provide="datepicker"
                               class="form-control datepicker" value="<?php echo date('d.m.Y', $fund->period_end); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Фонд оплаты труда работников производителя (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="w_a" id="w_a" class="form-control" value="{{ fund.w_a }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма отчислений на страховые взносы по обязательному социальному страхованию (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="w_b" id="w_b" class="form-control" value="{{ fund.w_b }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма отчислений на социальный налог (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="w_c" id="w_c" class="form-control" value="{{ fund.w_c }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма отчислений на обязательное страхование работников от несчастных случаев при исполнении ими трудовых (служебных) обязанностей (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="w_d" id="w_d" class="form-control" value="{{ fund.w_d }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Затраты на оплату электрической и тепловой энергии (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="e_a" id="e_a" class="form-control" value="{{ fund.e_a }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Оплата труда работников, участвующих в выполнении научно-исследовательских и опытно-конструкторских работ, за период выполнения этими работниками указанных работ (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="r_a" id="r_a" class="form-control" value="{{ fund.r_a }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Материальные расходы, непосредственно связанные с выполнением научно-исследовательских и опытно-конструкторских работ (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="r_b" id="r_b" class="form-control" value="{{ fund.r_b }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Стоимость работ по договорам о выполнении научно-исследовательских и опытно-конструкторских работ в случае выполнения работ в иных научно-исследовательских организациях (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="r_c" id="r_c" class="form-control" value="{{ fund.r_c }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на проведение испытаний автомобильных транспортных средств и их компонентов(тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="tc_a" id="tc_a" class="form-control" value="{{ fund.tc_a }}">
                    </div>
                </div>

                {% if fund.entity_type == 'CAR' %}
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на транспортировку образцов автомобильных транспортных средств до места проведения испытаний и обратно (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tc_b" id="tc_b" class="form-control" value="{{ fund.tc_b }}">
                        </div>
                    </div>
                {% endif %}

                {% if fund.entity_type == 'CAR' %}
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на получение одобрения типа транспортного средства (шасси) (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tc_c" id="tc_c" class="form-control" value="{{ fund.tc_c }}">
                        </div>
                    </div>
                {% endif %}

                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на проведение испытаний самоходной сельскохозяйственной техники(тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="tt_a" id="tt_a" class="form-control" value="{{ fund.tt_a }}">
                    </div>
                </div>

                {% if fund.entity_type == 'CAR' %}
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на транспортировку образцов самоходной сельскохозяйственной техники до места проведения испытаний и обратно (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tt_b" id="tt_b" class="form-control" value="{{ fund.tt_b }}">
                        </div>
                    </div>
                {% endif %}

                <div class="form-group">
                    <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на получение сертификата соответствия (тенге)") }}</label>
                    <div class="controls">
                        <input type="text" name="tt_c" id="tt_c" class="form-control" value="{{ fund.tt_c }}">
                    </div>
                </div>

                {% if fund.ref_fund_key != NULL and key_is_editable != false %}
                    {% if fund.entity_type == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">{{ t._("Категория ТС") }}, {{ t._("volume-cm-for-car-and-bus") }}
                                / {{ t._("full-mass-for-truck") }} / {{ t._("power-for-tc") }}</label>
                            <select name="ref_fund_key" class="selectpicker form-control" data-live-search="true"
                                    required>
                                {% for i, key in ref_fund_keys %}
                                    <option value="{{ key.name }}"{% if key.name == fund.ref_fund_key %} selected{% endif %}>{{ key.description }}</option>
                                {% endfor %}
                            </select>
                            <input type="hidden" name="entity_type" value="CAR"/>
                        </div>
                    {% else %}
                        <div class="form-group">
                            <label class="form-label">{{ t._("Код ТН ВЭД произведенных компонентов") }}</label>
                            <select name="ref_fund_key" class="selectpicker form-control" data-live-search="true"
                                    required>
                                {% for i, key in ref_fund_keys %}
                                    <option value="{{ key.name }}"{% if key.name == fund.ref_fund_key %} selected{% endif %}>{{ key.name }}
                                        - {{ key.description }}</option>
                                {% endfor %}
                            </select>
                            <input type="hidden" name="entity_type" value="GOODS"/>
                        </div>
                    {% endif %}
                {% endif %}
                <button type="submit" class="btn btn-success" name="button">{{ t._("save-application") }}</button>
            </div>
        </div>
    </div>
</div>
</form>
