<h3>Создание заявки на финансирование</h3>

<form action="/fund/add" method="post" id="frm_order" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Создать заявку") }}</div>
                <div class="card-body">
                    <input type="hidden" name="order_type"
                           value="<?php if($_GET['mode'] == 'INS') { echo 'INS'; } else { echo 'EXP'; }; ?>">
                    <div class="form-group">
                        <label class="form-label">{{ t._("Место реализации") }}</label>
                        <select name="car_country" id="car_country" class="selectpicker form-control"
                                data-live-search="true">
                            {% for i, country in countries %}
                                <option value="{{ country.id }}"{% if i == 0 %} selected{% endif %}>{{ country.name }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Начало отчетного периода") }}</label>
                        <div class="controls">
                            <input type="text" name="period_start" id="period_start" data-provide="datepicker"
                                   class="form-control datepicker">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Конец отчетного периода") }}</label>
                        <div class="controls">
                            <input type="text" name="period_end" id="period_end" data-provide="datepicker"
                                   class="form-control datepicker">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Фонд оплаты труда работников производителя (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="w_a" id="w_a" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма отчислений на страховые взносы по обязательному социальному страхованию (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="w_b" id="w_b" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма отчислений на социальный налог (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="w_c" id="w_c" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма отчислений на обязательное страхование работников от несчастных случаев при исполнении ими трудовых (служебных) обязанностей (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="w_d" id="w_d" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Затраты на оплату электрической и тепловой энергии (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="e_a" id="e_a" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Оплата труда работников, участвующих в выполнении научно-исследовательских и опытно-конструкторских работ, за период выполнения этими работниками указанных работ (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="r_a" id="r_a" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Материальные расходы, непосредственно связанные с выполнением научно-исследовательских и опытно-конструкторских работ (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="r_b" id="r_b" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Стоимость работ по договорам о выполнении научно-исследовательских и опытно-конструкторских работ в случае выполнения работ в иных научно-исследовательских организациях (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="r_c" id="r_c" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на проведение испытаний автомобильных транспортных средств и их компонентов (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tc_a" id="tc_a" class="form-control" value="0">
                        </div>
                    </div>

                    {% if entity_type == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на транспортировку образцов автомобильных транспортных средств до места проведения испытаний и обратно (тенге)") }}</label>
                            <div class="controls">
                                <input type="text" name="tc_b" id="tc_b" class="form-control" value="0">
                            </div>
                        </div>
                    {% endif %}

                    {% if entity_type == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на получение одобрения типа транспортного средства (шасси) (тенге)") }}</label>
                            <div class="controls">
                                <input type="text" name="tc_c" id="tc_c" class="form-control" value="0">
                            </div>
                        </div>
                    {% endif %}

                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на проведение испытаний самоходной сельскохозяйственной техники (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tt_a" id="tt_a" class="form-control" value="0">
                        </div>
                    </div>

                    {% if entity_type == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на транспортировку образцов самоходной сельскохозяйственной техники до места проведения испытаний и обратно (тенге)") }}</label>
                            <div class="controls">
                                <input type="text" name="tt_b" id="tt_b" class="form-control" value="0">
                            </div>
                        </div>
                    {% endif %}

                    <div class="form-group">
                        <label class="form-label">{{ t._("Сумма затрат, понесенных производителем на получение сертификата соответствия (тенге)") }}</label>
                        <div class="controls">
                            <input type="text" name="tt_c" id="tt_c" class="form-control" value="0">
                        </div>
                    </div>
                    {#          <div class="form-group"> #}
                    {#            <label class="form-label">{{ t._("Сумма финансирования, полученного с начала текущего календарного года (тенге)") }}</label> #}
                    {#            <div class="controls"> #}
                    {#              <input type="text" name="sum_before" class="form-control" value="0" required> #}
                    {#            </div> #}
                    {#          </div> #}
                    <?php if($_GET['object'] == 'CAR'){ ?>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Категория ТС") }}, {{ t._("volume-cm-for-car-and-bus") }}
                            / {{ t._("full-mass-for-truck") }} / {{ t._("power-for-tc") }}</label>
                        <select name="ref_fund_key" class="selectpicker form-control" data-live-search="true" required>
                            {% for i, key in ref_fund_keys %}
                                <option value="{{ key.name }}"{% if i == 0 %} selected{% endif %}>{{ key.description }}</option>
                            {% endfor %}
                        </select>
                        <input type="hidden" name="entity_type" value="CAR"/>
                    </div>
                    <?php }else{ ?>
                    <div class="form-group">
                        <label class="form-label">{{ t._("Код ТН ВЭД произведенных компонентов") }}</label>
                        <select name="ref_fund_key" class="selectpicker form-control" data-live-search="true" required>
                            {% for i, key in ref_fund_keys %}
                                <option value="{{ key.name }}"{% if i == 0 %} selected{% endif %}>{{ key.name }}
                                    - {{ key.description }}</option>
                            {% endfor %}
                        </select>
                        <input type="hidden" name="entity_type" value="GOODS"/>
                    </div>
                    <?php } ?>
                    <button type="submit" class="btn btn-success" name="button">{{ t._("Добавить заявку") }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
