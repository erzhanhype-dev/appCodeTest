<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("edit-car") }} {{ car.id }}(заявка #{{ car.profile_id }}
                )
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-block">
                    <a class="btn btn-primary" href="/car/correction/{{ car.id }}">Редактирование</a>
                    <a class="btn btn-danger" href="/car/annulment/{{ car.id }}">Аннулирование</a>
                </div>
                <hr>

                <h2 class="h4 mb-3">Редактирование</h2>

                <!-- Edit car form -->
                <form id="formCorrectionRequestSign" action="/car/correction/{{ car.id }}" method="POST"
                      enctype="multipart/form-data">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="hidden" name="correction_data" value="{{ correction_data }}">
                    <input type="hidden" name="correction_sum" value="{{ car.cost }}">
                    <input type="hidden" name="car_id" value="{{ car.id }}">
                    <div class="form-group" id="car_cat_group">
                        <label class="form-label">
                            <b>{{ t._("car-category") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <select name="car_cat" id="car_cat" class="form-control">
                            {% for type in car_types %}
                                <optgroup label="{{ t._(type.name) }}">
                                    {% for cat in car_cats %}
                                        {% if type.id == cat.car_type %}
                                            <option value="{{ cat.id }}"{% if car.ref_car_cat == cat.id %} selected{% endif %}>{{ t._(cat.name) }}</option>
                                        {% endif %}
                                    {% endfor %}
                                </optgroup>
                            {% endfor %}
                        </select>
                        <small id="car_cat" class="form-text text-muted">
                            Укажите категорию транспортного средства согласно его подтверждающих документов.
                        </small>
                    </div>

                    <div class="form-group" id="vehicle_type">
                        <select name="vehicle_type" id="vehicle_type" class="form-control">
                            <option value="PASSENGER" {% if vehicle_type == 'PASSENGER' %} selected {% endif %}>{{ 'Легковые' }}</option>
                            <option value="CARGO" {% if vehicle_type == 'CARGO' %} selected {% endif %}>{{ 'Грузовые' }}</option>
                        </select>
                        <small id="vehicle_type" class="form-text text-muted">
                            Укажите тип транспортного средства.
                        </small>
                    </div>

                    <div class="form-group" id="car_volume_group">
                        {% if m == 'CAR' %}
                            <label class="form-label">
                                {% if (car.ref_car_cat >= 3 AND car.ref_car_cat <= 8) %}
                                    <b id="truck_weight">{{ t._("full-mass-for-truck") }} </b>
                                    <b id="car_bus_volume"
                                       style="display: none">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                {% else %}
                                    <b id="truck_weight" style="display: none">{{ t._("full-mass-for-truck") }} </b>
                                    <b id="car_bus_volume">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                {% endif %}
                                <b class="text text-danger">*</b>
                            </label>
                            <div class="controls">
                                <input type="number" name="car_volume" id="car_volume" class="form-control"
                                       value="{{ car.volume }}"
                                       min="0" max="50000" placeholder="1600.00" step=".01" autocomplete="off" required>
                                <small id="car_volume" class="form-text text-muted">
                                    Вам необходимо указать объем двигателя цилиндров для автомобиля или автобуса,
                                    либо технически допустимую максимальную массу для грузового автомобиля
                                </small>
                            </div>
                        {% endif %}
                        {% if m == 'TRAC' %}
                            <label class="form-label">
                                <b>{{ t._("power-for-tc") }}</b>
                                <b class="text text-danger">*</b>
                            </label>
                            <div class="controls">
                                <input type="number" name="car_volume" id="car_volume" class="form-control"
                                       value="{{ car.volume }}"
                                       min="0" max="9999" placeholder="160.00" step=".01" autocomplete="off" required>
                                <small id="car_volume" class="form-text text-muted">
                                    Вам необходимо указать мощность двигателя трактора или комбайна.
                                </small>
                            </div>
                        {% endif %}
                    </div>
                    {% if m == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("vin-code") }}</b>
                                <b class="text text-danger">*</b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_vin" class="form-control" maxlength="17" minlength="17"
                                       value="{{ car.vin }}" placeholder="XXXXXXXXXXXXXXXXX" autocomplete="off"
                                       required>
                                <small id="car_vin" class="form-text text-muted">
                                    VIN-код должен содержать в себе только символы на латинице и цифры, не больше 17
                                    символов.
                                </small>
                            </div>
                        </div>
                    {% endif %}
                    {% if m == 'TRAC' %}
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("id-code") }}</b>
                                <b class="text text-danger">*</b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_id_code" class="form-control"
                                       value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                       autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("body-code") }}</b>
                                <b class="text text-danger">*</b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_body_code" class="form-control"
                                       value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                       autocomplete="off">
                            </div>
                        </div>
                    {% endif %}
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("year-of-manufacture") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <div class="controls">
                            <input type="number" name="car_year" class="form-control" min="1900" max="{{ date('Y') }}"
                                   value="{{ car.year }}" autocomplete="off" required id="correction_year">
                            <small id="car_date" class="form-text text-muted">
                                Укажите дату импорта ТС, которая подтверждена соответствующими документами.
                                <icon data-feather="help-circle" type="button" data-toggle="collapse"
                                      data-target="#collapseCarDateInfo" aria-expanded="false"
                                      aria-controls="collapseCarDateInfo" color="green" width="18" height="18"
                                      id="car-date-to-hover"></icon>
                            </small>
                            <div class="collapse" id="collapseCarDateInfo">
                                <div class="card card-body">
                                    <div class="alert alert-danger" role="alert">
                                        <p>Внимание! Дату импорта необходимо указать согласно дате на печати в
                                            талоне либо в транспортной накладной, указывающей на дату пересечения
                                            границы РК. Также если Вы
                                            завозите продукцию из стран ЕЭС (Российская Федерация, Армения, Республика
                                            Беларусь, Киргизия)
                                            согласно п. 28 Правил реализации РОП в случае отсутствия талона, необходимо
                                            датой импорта указать
                                            дату подачи заявки.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("import-date") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <div class="controls">
                            <input type="text" name="car_date" data-provide="datepicker"
                                   data-date-start-date="{{ constant('STARTROP') }}" id="correction_car_date"
                                   data-date-end-date="0d" class="form-control datepicker"
                                   value="{{ date("d.m.Y", car.date_import) }}" placeholder="{{ date('d.m.Y') }}"
                                   autocomplete="off">
                            <small id="car_date" class="form-text text-muted">
                                Укажите дату импорта ТС, которая подтверждена соответствующими документами.
                            </small>
                        </div>
                    </div>
                    <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                        <label class="form-label">
                            <b>{{ t._("ref-st") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <select name="ref_st" id="ref_st" class="form-control">
                            <option value="0"{% if car.ref_st_type == 0 %} selected{% endif %}>{{ t._("ref-st-not") }}</option>
                            <option value="1"{% if car.ref_st_type == 1 %} selected{% endif %}>{{ t._("ref-st-yes") }}</option>
                            {% if is_vendor != true %}
                                <option value="2"{% if car.ref_st_type == 2 %} selected{% endif %}>{{ t._("ref-st-international-transport") }}</option>
                            {% endif %}
                        </select>
                        <small id="ref_st" class="form-text text-muted">
                            Укажите седельность, если грузовой автомобиль(категория N).
                        </small>
                    </div>
                    <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                        <label class="form-label">
                            <b>{{ t._("is_electric_car?") }}</b>
                            <b class="text text-danger">*</b>
                        </label><br>
                        <input type="radio" name="e_car" value="1" {% if car.electric_car == 1 %} checked {% endif %}/>
                        Да
                        <input type="radio" name="e_car" value="0" {% if car.electric_car == 0 %} checked {% endif %}/>
                        Нет
                        <small id="is_electric_car" class="form-text text-muted">
                            Укажите "Да", если автомобиль с электрическим двигателем.
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("country-of-manufacture") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <select name="car_country" id="car_country" class="selectpicker form-control"
                                data-live-search="true">
                            {% for country in countries %}
                                <option value="{{ country.id }}"{% if car.ref_country == country.id %} selected{% endif %}>{{ country.name }}</option>
                            {% endfor %}
                        </select>
                        <small id="car_country" class="form-text text-muted">
                            Укажите страну, в которой было осуществлено произоводство ТС.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("country-of-import") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <select name="car_country_import" id="car_country_import" class="selectpicker form-control"
                                data-live-search="true">
                            <option value="0" {% if ref_country_import is defined %} {% else %} selected {% endif %}>
                                Нет данных
                            </option>
                            {% for country in countries %}
                                <option value="{{ country.id }}"{% if car.ref_country_import == country.id %} selected{% endif %}>{{ country.name }}</option>
                            {% endfor %}
                        </select>
                        <small id="car_country_import" class="form-text text-muted">
                            Укажите страну, из которой был произведен импорт.
                        </small>
                    </div>

                    <div class="form-group" id="EditCarDtSent" style="display:none;">
                        <label class="form-label">
                            <b>{{ t._("sent-date") }}</b>
                        </label>
                        <div class="controls">
                            <input type="text" name="md_dt_sent" data-provide="datepicker"
                                   data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
                                   class="form-control datepicker" value="{{ date("d.m.Y", md_dt_sent) }}"
                                   disabled='disabled'>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("comment") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <textarea name="car_comment" id="correctionRequestComment" class="form-control"
                                  placeholder="Ваш комментарий ... " required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("Загрузить файл") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <input type="file" name="car_file" class="form-control-file">
                    </div>

                    <hr>
                    <div class="form-group" id="pay_file" style="display:none;">
                        <label class="form-label">
                            <b>{{ t._("pay_correction") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <input type="file" name="car_pay_file" class="form-control-file">
                    </div>

                    <hr>
                    <input type="hidden" name="profile" value="{{ car.profile_id }}">
                    <input type="hidden" value="{{ sign_data }}" name="hash" id="profileHash">
                    <textarea type="hidden" name="sign" id="profileSign" style="display: none;"></textarea>
                    <div class="row">
                        <div class="col-auto">
                            <button type="button" class="btn btn-warning" id="correction_car_submit">Подписать и
                                сохранить изменения
                            </button>
                            <a href="/order/view/{{ car.profile_id }}" class="btn btn-danger">Отмена</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("История изменения") }}</div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>{{ t._("ID") }}</th>
                        <th>{{ t._("Пользователь") }}</th>
                        <th>{{ t._("Действия") }}</th>
                        <th>{{ t._("Время") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if page.items|length %}
                        {% for log in page.items %}
                            <tr>
                                <td>{{ log.ccp_id }}</td>
                                <td>
                                    <?php
                        $id = $log->user_id;
                                    echo __getClientTitleByUserId($id);
                                    ?>
                                    <br>({{ log.iin }})
                                </td>
                                <td>{{ t._(log.action) | upper }}</td>
                                <td>{{ date("d.m.Y H:i", log.dt) }}</td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade correction_pay_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Уведомление</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="font-family: 'Montserrat'; font-size: 14px;">
                <p class="text-justify">
                    <b>Перед отправкой заявки на корректировку сертификата о внесении утилизационного платежа с
                        увеличением суммы утилизационного платежа необходимо осуществить доплату</b>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>
