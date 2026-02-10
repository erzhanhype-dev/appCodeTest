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

                <h2 class="h4 mb-3">Аннулирование</h2>
                <!-- Edit car form -->
                <form id="formCorrectionRequestSign" action="/car/annulment/{{ car.id }}" method="POST"
                      enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                    <div class="form-group" id="car_cat_group">
                        <label class="form-label">
                            <b>{{ t._("car-category") }}</b>
                        </label>
                        <select name="car_cat" class="form-control" disabled="disabled">
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
                    <div class="form-group" id="car_volume_group">
                        <label class="form-label">
                            {% if car.ref_car_cat >= 3 AND car.ref_car_cat <= 8 %}
                                <b id="truck_weight">{{ t._("full-mass-for-truck") }} </b>
                            {% else %}
                                <b id="car_bus_volume">{{ t._("volume-cm-for-car-and-bus") }} </b>
                            {% endif %}
                        </label>
                        <div class="controls">
                            <input type="text" name="car_volume" id="car_volume" class="form-control"
                                   disabled="disabled" value="{{ car.volume }}">
                        </div>
                    </div>
                    {% if m == 'CAR' %}
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("vin-code") }}</b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_vin" class="form-control" maxlength="17" minlength="17"
                                       disabled="disabled" value="{{ car.vin }}">
                            </div>
                        </div>
                    {% endif %}
                    {% if m == 'TRAC' %}
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("id-code") }}</b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_id_code" class="form-control" disabled="disabled"
                                       value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <b>{{ t._("body-code") }}></b>
                            </label>
                            <div class="controls">
                                <input type="text" name="car_body_code" class="form-control" disabled="disabled"
                                       value="<?php $_ic = preg_split('/[-&]/', (string)$car->vin, 2); echo htmlspecialchars($_ic[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            </div>
                        </div>
                    {% endif %}
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("year-of-manufacture") }}</b>
                        </label>
                        <div class="controls">
                            <input type="text" name="car_year" class="form-control" maxlength="4" value="{{ car.year }}"
                                   disabled="disabled">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("import-date") }}</b>
                        </label>
                        <div class="controls">
                            <input type="text" name="car_date" data-provide="datepicker"
                                   data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
                                   disabled="disabled" class="form-control datepicker"
                                   value="{{ date("d.m.Y", car.date_import) }}">
                        </div>
                    </div>
                    <div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %}>
                        <label class="form-label">
                            <b>{{ t._("ref-st") }}</b>
                        </label>
                        <select name="ref_st" id="ref_st" class="form-control" disabled="disabled">
                            <option value="0"{% if car.ref_st_type == 0 %} selected{% endif %}>{{ t._("ref-st-not") }}</option>
                            <option value="1"{% if car.ref_st_type == 1 %} selected{% endif %}>{{ t._("ref-st-yes") }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("country-of-manufacture") }}</b>
                        </label>
                        <select name="car_country" id="car_country" class="selectpicker form-control"
                                data-live-search="true" disabled="disabled">
                            {% for country in countries %}
                                <option value="{{ country.id }}"{% if car.ref_country == country.id %} selected{% endif %}>{{ country.name }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("comment") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <textarea name="car_comment" id="correctionRequestComment" class="form-control"
                                  placeholder="Ваш комментарий ... "></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("Загрузить файл") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <input type="file" name="car_file" class="form-control-file">
                    </div>
                    <hr>
                    <input type="hidden" name="profile" value="{{ car.profile_id }}">
                    <input type="hidden" value="{{ sign_data }}" name="hash" id="profileHash">
                    <textarea type="hidden" name="sign" id="profileSign" style="display: none;"></textarea>
                    <div class="row">
                        <div class="col-auto">
                            <button type="button" class="btn btn-warning signCorrectionRequestBtn">Подписать и
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




