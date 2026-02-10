<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Вес продукции (товара), кг<b class="text text-danger">*</b>
    </label>
    <input type="number"
           name="weight"
           value=""
           class="form-control form-control-sm"
           placeholder="Введите значение"
           required>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Номер счет-фактуры или ГТД<b class="text text-danger">*</b>
    </label>
    <input type="number"
           name="basis"
           value=""
           class="form-control form-control-sm"
           placeholder="Введите значение"
           required>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Дата счет-фактуры / ГТД<b class="text text-danger">*</b>
    </label>
    <input type="number"
           name="basis_at"
           value=""
           class="form-control form-control-sm"
           placeholder="Введите значение"
           required>
</div>

<div class="form-group mb-2">
    <label class="font-weight-bold small">
        Страна <b class="text text-danger">*</b>
    </label>
    <select name="ref_country_id" id="ref_country_id" class="selectpicker form-control" data-live-search="true">
        <option value="0">
            Нет данных
        </option>
        {% for i, country in countries %}
            <option value="{{ country.id }}"
            >{{ country.name }}</option>
        {% endfor %}
    </select>
</div>
