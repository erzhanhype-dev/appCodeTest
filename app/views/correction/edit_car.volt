<h3>{{ t._("edit-car") }}</h3>
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("edit-car") }} {{ car.id }}(заявка #{{ car.profile_id }})</div>
            <div class="card-body">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#editCarTab">Редактирование</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#annulmentCarTab">Аннулирование</a>
                    </li>
                </ul>
                <div class="tab-content p-3">

                    <!-- Edit car form -->
                    <div class="tab-pane fade show active" id="editCarTab">

                        <h2 class="h4 mb-3">Редактирование ТС</h2>

                        <form id="editCarForm" action="/correction/edit_car/{{ car.id }}" method="POST" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

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
                                        {% if car.ref_car_cat >= 3 AND car.ref_car_cat <= 8 %}
                                            <b id="truck_weight">{{ t._("full-mass-for-truck") }} </b>
                                            <b id="car_bus_volume" style="display: none">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                        {% else %}
                                            <b id="truck_weight" style="display: none">{{ t._("full-mass-for-truck") }} </b>
                                            <b id="car_bus_volume">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                        {% endif %}
                                        <b class="text text-danger">*</b>
                                    </label>
                                    <div class="controls">
                                        <input type="number" name="car_volume" id="car_volume" class="form-control" value="{{ car.volume }}" step=".01" placeholder="1600.00" autocomplete="off" required>
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
                                        <input type="number" name="car_volume" id="car_volume" class="form-control" value="{{ car.volume }}" step=".01" placeholder="1600.00" autocomplete="off" required>
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
                                        <input type="text" name="car_vin" class="form-control" maxlength="17" minlength="17" value="{{ car.vin }}">
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
                                        <input type="text" name="car_id_code" class="form-control" value="<?php $_ic = explode('-', $car->vin); echo $_ic[0]; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <b>{{ t._("body-code") }}</b>
                                        <b class="text text-danger">*</b>
                                    </label>
                                    <div class="controls">
                                        <input type="text" name="car_body_code" class="form-control" value="<?php $_ic = explode('-', $car->vin); echo $_ic[1]; ?>">
                                    </div>
                                </div>
                            {% endif %}
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("year-of-manufacture") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <div class="controls">
                                    <input type="text" name="car_year" class="form-control" maxlength="4" value="{{ car.year }}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("import-date") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <div class="controls">
                                    <input type="text" name="car_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" value="{{ date("d.m.Y", car.date_import) }}">
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
                                    <option value="2"{% if car.ref_st_type == 2 %} selected{% endif %}>{{ t._("ref-st-international-transport") }}</option>
                                </select>
                            </div>
                            <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                                <label class="form-label">
                                    <b>{{ t._("is_electric_car?") }}</b>
                                    <b class="text text-danger">*</b>
                                </label><br>
                                <input type="radio" name="e_car" value="1" {% if car.electric_car == 1 %} checked {% endif %}/> Да
                                <input type="radio" name="e_car" value="0" {% if car.electric_car == 0 %} checked {% endif %}/> Нет
                                <small id="is_electric_car" class="form-text text-muted">
                                    Укажите "Да", если автомобиль с электрическим двигателем.
                                </small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("country-of-manufacture") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <select name="car_country" id="car_country" class="selectpicker form-control" data-live-search="true">
                                    {% for country in countries %}
                                        <option value="{{ country.id }}"{% if car.ref_country == country.id %} selected{% endif %}>{{ country.name }}</option>
                                    {% endfor %}
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("Выберите способ расчета") }}</b>
                                    <b class="text text-danger">*</b>
                                </label><br>
                                <label>
                                    <input type="radio" name="calculate_method" class="EditCarCalcMethod" value="0" {% if car.calculate_method== 0 %} checked="checked" {% endif %}>
                                    По дате импорта
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="calculate_method" class="EditCarCalcMethod" value="1" {% if car.calculate_method== 1 %} checked="checked" {% endif %}>
                                    По дате подачи заявки
                                </label>
                                <br>
{#                                <label>#}
{#                                    <input type="radio" name="calculate_method" class="EditCarCalcMethod" value="2" {% if car.calculate_method== 2 %} checked="checked" {% endif %}>#}
{#                                    По дате первичной регистрации#}
{#                                </label>#}

                            </div>
                            <div class="form-group" id="EditCarDtSent" style="display:none;">
                                <label class="form-label">
                                    <b>{{ t._("sent-date") }}</b>
                                </label>
                                <div class="controls">
                                    <input type="text" name="md_dt_sent" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" value="{{ date("d.m.Y", md_dt_sent) }}" disabled="disabled">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("correction_initiator") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <select name="initiator" id="initiator" class="form-control">
                                    {% for item in initiators %}
                                        {% if initiator_id == '' %}
                                            <option value="{{ item.id }}">{{ t._(item.name) }}</option>
                                        {% else %}
                                            <option value="{{ item.id }}"{% if item.id == initiator_id %} selected{% endif %}>{{ t._(item.name) }}</option>
                                        {% endif %}
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("comment") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <textarea name="car_comment" id="EditCarComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("Загрузить файл") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <input type="file" id="EditCarFile" name="car_file" class="form-control-file" required>
                            </div>
                            <hr>
                            <input type="hidden" name="profile" value="{{ car.profile_id }}">
                            <input type="hidden" value="{{ sign_data }}" name="hash" id="EditCarHash">
                            <textarea type="hidden" name="sign" id="EditCarSign" style="display: none;"></textarea>
                            <div class="row">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-warning signEditCarsBtn">Подписать и сохранить изменения</button>
                                    <a href="/correction/" class="btn btn-danger">Отмена</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Annulment form -->
                    <div class="tab-pane fade" id="annulmentCarTab">

                        <h2 class="h4 mb-3">Аннулирование ДПП</h2>

                        <form id="annulCarForm" action="/correction/annul_car/{{ car.id }}" method="POST" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="form-group">
                                <label class="form-label">
                                    {% if m == 'CAR' %}
                                        {% if car.ref_car_cat >= 3 AND car.ref_car_cat <= 8 %}
                                            <b id="truck_weight">{{ t._("full-mass-for-truck") }} </b>
                                            <b id="car_bus_volume" style="display: none">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                        {% else %}
                                            <b id="truck_weight" style="display: none">{{ t._("full-mass-for-truck") }} </b>
                                            <b id="car_bus_volume">{{ t._("volume-cm-for-car-and-bus") }} </b>
                                        {% endif %}
                                    {% endif %}
                                    {% if m == 'TRAC' %}<b>{{ t._("power-for-tc") }}</b>{% endif %}
                                </label>
                                <div class="controls">
                                    <input type="text" name="car_volume" disabled="disabled" class="form-control" value="{{ car.volume }}">
                                </div>
                            </div>
                            {% if m == 'CAR' %}
                                <div class="form-group">
                                    <label class="form-label">
                                        <b>{{ t._("vin-code") }}</b>
                                    </label>
                                    <div class="controls">
                                        <input type="text" name="car_vin" disabled="disabled" class="form-control" maxlength="17" minlength="17" value="{{ car.vin }}">
                                    </div>
                                </div>
                            {% endif %}
                            {% if m == 'TRAC' %}
                                <div class="form-group">
                                    <label class="form-label">
                                        <b>{{ t._("id-code") }}</b>
                                    </label>
                                    <div class="controls">
                                        <input type="text" name="car_id_code" disabled="disabled" class="form-control" value="<?php $_ic = explode('-', $car->vin); echo $_ic[0]; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <b>{{ t._("body-code") }}></b>
                                    </label>
                                    <div class="controls">
                                        <input type="text" name="car_body_code" class="form-control" disabled="disabled" value="<?php $_ic = explode('-', $car->vin); echo $_ic[1]; ?>">
                                    </div>
                                </div>
                            {% endif %}
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("year-of-manufacture") }}</b>
                                </label>
                                <div class="controls">
                                    <input type="text" name="car_year" class="form-control" maxlength="4" value="{{ car.year }}" disabled="disabled">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("import-date") }}</b>
                                </label>
                                <div class="controls">
                                    <input type="text" name="car_date" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" value="{{ date("d.m.Y", car.date_import) }}" disabled="disabled">
                                </div>
                            </div>
                            <div class="form-group" id="car_cat_group">
                                <label class="form-label">
                                    <b>{{ t._("car-category") }}</b>
                                </label>
                                <select name="car_cat" id="car_cat" class="form-control" disabled="disabled">
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
                            </div>
                            <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                                <label class="form-label">
                                    <b>{{ t._("ref-st") }}</b>
                                </label>
                                <select name="ref_st" class="form-control" disabled="disabled">
                                    <option value="0"{% if car.ref_st_type == 0 %} selected{% endif %}>{{ t._("ref-st-not") }}</option>
                                    <option value="1"{% if car.ref_st_type == 1 %} selected{% endif %}>{{ t._("ref-st-yes") }}</option>
                                </select>
                            </div>
                            <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                                <label class="form-label">
                                    <b>{{ t._("is_electric_car?") }}</b>
                                    <b class="text text-danger">*</b>
                                </label><br>
                                <input type="radio" name="e_car" value="1" {% if car.electric_car == 1 %} checked {% endif %}/> Да
                                <input type="radio" name="e_car" value="0" {% if car.electric_car == 0 %} checked {% endif %}/> Нет
                                <small id="is_electric_car" class="form-text text-muted">
                                    Укажите "Да", если автомобиль с электрическим двигателем.
                                </small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("country-of-manufacture") }}</b>
                                </label>
                                <select name="car_country" class="selectpicker form-control" data-live-search="true" disabled="disabled">
                                    {% for country in countries %}
                                        <option value="{{ country.id }}"{% if car.ref_country == country.id %} selected{% endif %}>{{ country.name }}</option>
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("annul_initiator") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <select name="initiator" id="initiator" class="form-control">
                                    {% for item in initiators %}
                                        {% if initiator_id == '' %}
                                            <option value="{{ item.id }}">{{ t._(item.name) }}</option>
                                        {% else %}
                                            <option value="{{ item.id }}"{% if item.id == initiator_id %} selected{% endif %}>{{ t._(item.name) }}</option>
                                        {% endif %}
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("comment") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <textarea name="car_comment" id="annulCarComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("Загрузить файл") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <input type="file" id="annulCarFile" name="car_file" class="form-control-file" required>
                            </div>
                            <hr>
                            <input type="hidden" name="profile" value="{{ car.profile_id }}">
                            <input type="hidden" value="{{ sign_data }}" name="hash" id="annulCarHash">
                            <textarea type="hidden" name="sign" id="annulCarSign" style="display: none;"></textarea>
                            <div class="row">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-warning signAnnulCarsBtn">Подписать и аннулировать</button>
                                    <a href="/correction/" class="btn btn-danger">Отмена</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("История изменения") }}</div>
    <div class="card-body" id="DISPLAY_CORRECTION_LOGS_BY_OBJECT_ID">
        <input type="hidden" value="{{ car.profile_id }}" id="getCorrectionLogsByProfileId">
        <input type="hidden" value="CAR" id="getCorrectionLogsByType">
        <input type="hidden" value="{{ car.id }}" id="getCorrectionLogsByObjectId">
        <table id="correctionLogsByObjectId" class="display" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th>{{ t._("application-number") }}</th>
                <th>{{ t._("type") }} / {{ t._("Обьект ID") }}</th>
                <th>{{ t._("Пользователь") }}</th>
                <th>{{ t._("Действия") }}</th>
                <th>{{ t._("Время") }}</th>
                <th>{{ t._("comment") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
</div>
</div>


 


