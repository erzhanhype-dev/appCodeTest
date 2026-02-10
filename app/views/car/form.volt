{% if m == 'CAR' %}
    <div class="form-group">
        <label class="form-label"><b>{{ t._("vin-code") }}</b><b class="text text-danger">*</b></label>
        <div class="row">
            <div class="col">
                <input type="text" name="vin" id="vin"
                       class="form-control text-uppercase {{ ((integration_data and integration_data['vin'] and vin is defined and vin) or new is defined) ? 'readonly' : '' }}"
                       minlength="17" maxlength="17"
                       placeholder="XXXXXXXXXXXXXXXXX"
                       value="{{ vin is defined and vin ? vin : '' }}"
                       autocomplete="off"
                       required {{ ((integration_data and integration_data['vin'] and vin is defined and vin) or new is defined) ? 'readonly' : '' }}>
                <small id="car_vin" class="form-text text-muted">
                    VIN-код должен содержать в себе только символы на латинице и цифры, не
                    больше 17 символов.
                </small>
            </div>
        </div>
    </div>
{% endif %}
{% if m == 'TRAC' %}
<div class="form-group">
    <label class="form-label"><b>{{ t._("id-code") }}</b><b
                class="text text-danger">*</b></label>
    <div class="row">
        <div class="col">
            <input type="text" name="id_code" id="id_code" class="form-control text-uppercase {{ ((integration_data and integration_data['factory_number'] and id_code is defined and id_code) or new is defined) ? 'readonly' : '' }}"
                    {{ ((integration_data and integration_data['factory_number'] and id_code is defined and id_code) or new is defined) ? 'readonly' : '' }}
                   value="{{ id_code ? id_code : '' }}"
                   autocomplete="off"
                   required>
        </div>
    </div>
</div>

<div class="form-group mt-3">
    <label class="form-label"><b>{{ t._("body-code") }}</b></label>
    <div class="controls">
        <input type="text" name="body_code" id="body_code" class="form-control text-uppercase"
               value="{{ body_code ? body_code : '' }}"
               autocomplete="off">
    </div>
</div>
{% endif %}

<div class="form-group" id="car_cat_group">
    <label class="form-label">
        <b>{{ t._("car-category") }}</b>
        <b class="text text-danger">*</b>
    </label>
    {% set isDisabled = auth.isClient() and integration_data['ref_car_cat_id'] is defined and integration_data['ref_car_cat_id'] and ref_car_cat_id %}

    <select
            name="ref_car_cat_id"
            id="ref_car_cat"
            class="form-control{{ isDisabled ? ' readonly' : '' }}"
            {% if isDisabled %} readonly {% endif %}
    >
        {% for type in car_types %}
            <optgroup label="{{ t._(type.name) }}">
                {% for i, cat in cats %}
                    {% if type.id == cat.car_type %}
                        <option value="{{ cat.id }}"
                                {% if ref_car_cat_id and ref_car_cat_id is defined %}
                                    {% if cat.id == ref_car_cat_id or i == 0 %}
                                        selected
                                    {% endif %}
                                {% endif %}
                        >{{ t._(cat.name) }}
                        </option>
                    {% endif %}
                {% endfor %}
            </optgroup>
        {% endfor %}
    </select>

    <small id="ref_car_cat" class="form-text text-muted">
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

<input type="hidden" id="permissible_max_weight"
       value="{{ permissible_max_weight ? permissible_max_weight : '' }}">
<input type="hidden" id="engine_capacity"
       value="{{ engine_capacity ? engine_capacity : '' }}">

<div class="form-group" id="car_volume_group">
    {% if m == 'CAR' %}
        <label class="form-label">
            <b id="car_bus_volume">{{ t._("volume-cm-for-car-and-bus") }} </b>
            <b id="truck_weight" style="display: none">{{ t._("full-mass-for-truck") }} </b>
            <b class="text text-danger">*</b>
        </label>
        <div class="controls">
            <input type="number"
                   name="volume"
                   id="volume"
                   class="form-control {% if auth.isClient() and volume is defined and volume and integration_data['volume'] is defined and integration_data['volume'] %}readonly{% endif %}"
                   min="0"
                   max="50000"
                   placeholder="1600.00"
                   step=".01"
                   value="{{ volume is defined and volume ? volume : null }}"
                   autocomplete="off"
                   required
                   {% if auth.isClient() and volume is defined and volume and integration_data['volume'] is defined and integration_data['volume'] %}readonly{% endif %} >

            <small id="car_volume" class="form-text text-muted">
                Вам необходимо указать объем двигателя цилиндров для автомобиля или
                автобуса,
                либо технически допустимую максимальную массу для грузового автомобиля
            </small>
        </div>
    {% endif %}
    {% if m == 'TRAC' %}
        <label class="form-label">
            <b>{{ t._("power-for-tc") }}
                {% if integration_data['unit_name'] is defined and integration_data['unit_name'] != '-'%}
                    <span>, {{ integration_data['unit_name'] }}</span>
                {% else %}
                    <span>, л.c.</span>
                {% endif %}
                </b>
            <b class="text text-danger">*</b>
        </label>
        <div class="controls">
            <input type="number" name="volume" id="volume"
                   class="form-control {% if auth.isClient() and volume is defined and volume and integration_data['volume'] is defined and integration_data['volume'] %}readonly{% endif %}"
                   min="0" max="9999" placeholder="160.00" step=".01"
                   value="{{ volume is defined and volume ? volume : null }}"
                   autocomplete="off"
                   required
                   {% if auth.isClient() and volume is defined and volume and integration_data['volume'] is defined and integration_data['volume'] %}readonly{% endif %}
                   >
            <small id="car_volume" class="form-text text-muted">
                Вам необходимо указать мощность двигателя трактора или комбайна.
            </small>
        </div>
    {% endif %}
</div>
<div class="form-group">
    <label class="form-label"><b>{{ t._("year-of-manufacture") }}</b><b
                class="text text-danger">*</b></label>
    <div class="controls">
        <input type="number" name="year" id="year" min="1900" max="{{ date('Y') }}"
               class="form-control {{ auth.isClient() and year is defined and year and integration_data['year'] is defined and integration_data['year'] ? 'readonly' : null }}"
               placeholder="{{ date('Y') }}"
               value="{{ year is defined and year ? year: '' }}"
               autocomplete="off" required
               {% if auth.isClient() and year is defined and year and integration_data['year'] is defined and integration_data['year'] %}readonly{% endif %}>
        <small id="year" class="form-text text-muted">
            Укажите год производства (выпуска) транспортного средства.
        </small>
    </div>
</div>
<div class="form-group">
    <label class="form-label"><b>{{ t._("import-date") }}</b><b
                class="text text-danger">*</b></label>
    <div class="controls">
        <input type="text" name="import_date" id="import_date" data-provide="datepicker"
               data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
               value="{{ import_date is defined and import_date ? import_date : '' }}"
               class="form-control datepicker  {% if auth.isClient() and import_date is defined and import_date and integration_data['operation_date'] is defined and integration_data['operation_date'] %}readonly{% endif %}"
               {% if auth.isClient() and import_date is defined and import_date and integration_data['operation_date'] is defined and integration_data['operation_date'] %}readonly{% endif %}
               placeholder="{{ date('d.m.Y') }}"
               autocomplete="off"
               required>
        <small id="car_date" class="form-text text-muted">
            Укажите дату импорта ТС, которая подтверждена соответствующими документами.
            <icon data-feather="help-circle" type="button" data-toggle="collapse"
                  data-target="#collapseCarDateInfo" aria-expanded="false"
                  aria-controls="collapseCarDateInfo" color="green" width="16"
                  height="16"></icon>
        </small>
        <div class="collapse" id="collapseCarDateInfo">
            <div class="card card-body">
                <div class="alert alert-danger" role="alert">
                    <p style="text-align: justify;">Внимание! Дату импорта необходимо
                        указать согласно дате на печати в
                        талоне либо в транспортной накладной, указывающей на дату
                        пересечения границы РК. Также если Вы
                        завозите продукцию из стран ЕЭС (Российская Федерация, Армения,
                        Республика Беларусь, Киргизия)
                        согласно п. 28 Правил реализации РОП в случае отсутствия талона,
                        необходимо датой импорта указать
                        дату подачи заявки.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="form-group"{% if m == 'TRAC' %} style="display: none;"{% endif %} id="semi_truck">
    <label class="form-label"><b>{{ t._("ref-st") }}</b><b
                class="text text-danger">*</b></label>
    <select name="semi_truck"
            class="form-control {% if auth.isClient() and semi_truck is defined and integration_data['is_truck'] is defined %} readonly{% endif %}"
            {% if auth.isClient() and semi_truck is defined and integration_data['is_truck'] is defined %} readonly{% endif %} >
        <option value="0" {{ semi_truck == 0 ? 'selected' : '' }}>{{ t._("ref-st-not") }}</option>
        <option value="1" {{ semi_truck == 1 ? 'selected' : '' }}>{{ t._("ref-st-yes") }}</option>
    </select>
    <small id="ref_st" class="form-text text-muted">
        Укажите седельность, если грузовой автомобиль(категория N).
    </small>
</div>

<div class="form-group"{% if m == 'TRAC' %} style="display: none;" {% else %} {% if auth.isClient() and is_electric is defined and integration_data['is_electric'] is defined and integration_data['volume'] is defined and integration_data['volume'] > 0 %} style="filter: grayscale(1);" {% endif %} {% endif %} >
    <label class="form-label"><b>{{ t._("is_electric_car?") }}</b><b
                class="text text-danger">*</b>
        <br>
        <i><small class="form-text text-muted">{{ t._("is_electric_car_description") }}</small></i>
    </label><br>
    <input type="radio" name="is_electric"
           value="1" {% if is_electric is defined and is_electric == 1 %} checked{% endif %}
           class="{% if auth.isClient() and is_electric is defined and integration_data['is_electric'] is defined and integration_data['volume'] is defined and integration_data['volume'] > 0 %} readonly{% endif %}"/>
    Да
    <input type="radio" name="is_electric"
           value="0" {% if is_electric is defined and is_electric == 0 %} checked{% endif %}
           class="{% if auth.isClient() and is_electric is defined and integration_data['is_electric'] is defined and integration_data['volume'] is defined and integration_data['volume'] > 0 %} readonly{% endif %}"/>
    Нет
    <small id="is_electric_car" class="form-text text-muted">
        Укажите "Да", если автомобиль с электрическим двигателем.
    </small>
</div>

<div class="form-group">
    <label class="form-label"><b>{{ t._("country-of-manufacture") }}</b><b
                class="text text-danger">*</b></label>
    <select name="ref_country_id" id="ref_country_id" class="selectpicker form-control"
            {% if auth is defined and auth.isClient() %}
            required
            {% endif %}
            data-live-search="true">
        <option value="0" {% if ref_country_id is defined %} {% else %} selected {% endif %}>
            Нет данных
        </option>
        {% for i, country in countries %}
            <option value="{{ country.id }}"
                    {% if ref_country_id is defined %}
                        {% if country.id == ref_country_id %}
                            selected
                        {% endif %}
                    {% endif %}
            >{{ country.name }}</option>
        {% endfor %}
    </select>
    <small id="car_country" class="form-text text-muted">
        Укажите страну, в которой было осуществлено произоводство ТС.
    </small>
</div>

<div class="form-group">
    <label class="form-label"><b>{{ t._("country-of-import") }}</b><b
                class="text text-danger">*</b></label>
    <select name="ref_country_import_id" class="selectpicker form-control"
            {% if auth is defined and auth.isClient() %}
                required
            {% endif %}
            data-live-search="true">
        <option value="0" {% if ref_country_import_id is defined %} {% else %} selected {% endif %}>
            Нет данных
        </option>
        {% for i, country in countries %}
            <option value="{{ country.id }}"
                    {% if ref_country_id is defined %}
                        {% if country.id == ref_country_import_id %}
                            selected
                        {% endif %}
                    {% endif %}
            >{{ country.name }}</option>
        {% endfor %}
    </select>
    <small id="car_country" class="form-text text-muted">
        Укажите страну, из которой был произведен импорт.
    </small>
</div>