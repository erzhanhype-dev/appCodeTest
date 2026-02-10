{% if vehicle_type == 'AGRO' %}
    <div class="form-group mb-2">
        <label class="form-label small">
            <b>{{ t._("id-code") }}</b>
            <b class="text text-danger">*</b>
        </label>
        <input type="text"
               name="id_code"
               id="id_code"
               class="form-control text-uppercase"
               value="{{ id_code }}"
               autocomplete="off"
               required>
    </div>

    <div class="form-group">
        <label class="form-label small"><b>{{ t._("body-code") }}</b></label>
        <input type="text"
               name="body_code"
               id="body_code"
               class="form-control text-uppercase"
               value="{{ body_code }}"
               autocomplete="off">
    </div>
{% else %}
    <div class="form-group mb-2">
        <label class="font-weight-bold small text-uppercase">
            VIN<b class="text text-danger">*</b>
        </label>
        <input type="text"
               name="vin"
               class="text-uppercase form-control form-control-sm"
               step="0.01"
               min="0.01"
               placeholder="XXXXXXXXXXXXXXXXX"
               maxlength="17"
               value="{{ vin }}"
               required>
    </div>
{% endif %}

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Категория <b class="text text-danger">*</b>
    </label>
    <select name="ref_car_cat_id"
            class="form-control form-control-sm js-auto-reload"
            required>
        {% if ref_car_type is defined %}
            {% for type in ref_car_type %}
                <optgroup label="{{ t._(type.name) }}">
                    {% for i, cat in ref_car_cat %}
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
        {% endif %}
    </select>
</div>

<div class="form-group mb-2" id="vehicle_type_block">
    <label class="font-weight-bold small">
        Тип транспортного средства<b class="text text-danger">*</b>
    </label>
    <select name="vehicle_type" id="vehicle_type_select"
            class="form-control form-control-sm">
        <option value="PASSENGER" {% if vehicle_type == 'PASSENGER' %} selected {% endif %}>
            Легковые
        </option>
        <option value="CARGO" {% if vehicle_type == 'CARGO' %} selected {% endif %}>
            Грузовые
        </option>
    </select>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Объем/вес<b class="text text-danger">*</b>
    </label>
    <input type="number"
           name="volume"
           value="{{ volume == 0 ? '' : volume }}"
           class="form-control form-control-sm"
           placeholder="Введите значение"
           required>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Дата импорта<b class="text text-danger">*</b>
    </label>
    <input type="text"
           name="date_import"
           data-provide="datepicker"
           value="{{ date_import }}"
           class="form-control form-control-sm datepicker"
           placeholder="{{ date('d.m.Y') }}"
           autocomplete="off"
           required>
    </small>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Год производства<b class="text text-danger">*</b>
    </label>
    <input type="text"
           name="production_year"
           value="{{ production_year }}"
           class="form-control form-control-sm"
           required
           inputmode="numeric"
           maxlength="4"
           placeholder="{{ date('Y') }}">
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Страна производства <b class="text text-danger">*</b>
    </label>
    <select name="ref_country_id" id="ref_country_id" class="selectpicker form-control" data-live-search="true">
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

<div class="form-group mb-2" id="semi_truck_block">
    <label class="font-weight-bold small">
        {{ t._("ref-st") }}<b class="text text-danger">*</b>
    </label>
    <select name="is_truck" class="form-control form-control-sm" id="semi_truck_select">
        <option value="0" {{ is_truck == 0 ? 'selected' : '' }}>{{ t._("ref-st-not") }}</option>
        <option value="1" {{ is_truck == 1 ? 'selected' : '' }}>{{ t._("ref-st-yes") }}</option>
    </select>
    <small class="form-text text-muted">
        Укажите седельность, если грузовой автомобиль(категория N).
    </small>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        {{ t._("is_electric_car?") }}<b class="text text-danger">*</b>
        <i><small class="form-text text-muted">{{ t._("is_electric_car_description") }}</small></i>
    </label>
    <br>
    <input type="radio" name="is_electric"
           value="1" {{ is_electric == 1 ? 'checked' : '' }}/>Да
    <input type="radio" name="is_electric"
           value="0" {{ is_electric == 0 ? 'checked' : '' }}/>Нет
    <small id="is_electric_car" class="form-text text-muted">
        Укажите "Да", если автомобиль с электрическим двигателем.
    </small>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const catSelect = document.querySelector('select[name="ref_car_cat_id"]');

        const vehicleBlock = document.getElementById('vehicle_type_block'); // из прошлого шага
        const semiTruckBlock = document.getElementById('semi_truck_block');
        const semiTruckSelect = document.getElementById('semi_truck_select');

        if (!catSelect) return;

        const inSet = (value, setArr) => setArr.includes(String(value));

        const updateVisibility = () => {
            const v = String(catSelect.value);

            // 1) Тип ТС показываем только для 15/16
            if (vehicleBlock) {
                vehicleBlock.style.display = (v === '15' || v === '16') ? '' : 'none';
            }

            // 2) Седельный тягач показываем только для 3..8
            const showSemi = inSet(v, ['3', '4', '5', '6', '7', '8']);
            if (semiTruckBlock) semiTruckBlock.style.display = showSemi ? '' : 'none';

            // опционально: когда скрыто — не отправляем и не валидируем
            if (semiTruckSelect) semiTruckSelect.disabled = !showSemi;
        };

        updateVisibility();
        catSelect.addEventListener('change', updateVisibility);
    });
</script>