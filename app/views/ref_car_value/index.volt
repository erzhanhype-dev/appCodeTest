<!-- заголовок -->
<h2>{{ t._("Справочник коэффициентов") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<form method="get" id="filterForm" class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Поиск") }}</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <select name="car_type_id" class="selectpicker form-control" data-live-search="true"
                        data-live-search-placeholder="Поиск по Категории" onchange="this.form.submit()">
                    <option value="ALL" {{ car_type_id == 'ALL' ? 'selected' : '' }}>- Показать все -</option>
                    {% for type in car_types %}
                        <option value="{{ type.id }}" {{ car_type_id == type.id ? 'selected' : '' }}>{{ t._(type.name) }}</option>
                    {% endfor %}
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" name="search" class="btn btn-primary">{{ t._("Найти") }}</button>
                <button type="submit" name="clear" value="ALL" class="btn btn-warning">{{ t._("Сбросить") }}</button>
                {{ link_to("ref_car_value/new", '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success') }}
            </div>
        </div>
    </div>
</form>
<!-- /форма поиска -->

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Коэффициенты") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("group") }}</th>
                <th>{{ t._("volume-from") }}</th>
                <th>{{ t._("volume-to") }}</th>
                <th>{{ t._("mrp") }}</th>
                <th>{{ t._("koef") }}</th>
                <th>{{ t._("koef_2022") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for ref_car_value in page.items %}
                    <tr>
                        <td width="10%">{{ ref_car_value.id }}</td>
                        <td>{{ ref_car_value.car_type }}</td>
                        <td>{{ ref_car_value.volume_start }}</td>
                        <td>{{ ref_car_value.volume_end }}</td>
                        <td>{{ ref_car_value.price }}</td>
                        <td>{{ ref_car_value.k }}</td>
                        <td>{{ ref_car_value.k_2022 }}</td>
                        <td width="10%">{{ link_to("ref_car_value/edit/"~ref_car_value.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }} {{ link_to("ref_car_value/delete/"~ref_car_value.id, '<i data-feather="trash" width="14" height="14"></i>', 'class': 'btn btn-danger btn-sm') }}</td>
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
