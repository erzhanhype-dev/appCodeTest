<!-- заголовок -->
<h2>{{ t._("Модели ТС") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="post" action="{{ url('ref_car_model/') }}">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col">
                    <input name="brand" id="b" type="text" class="form-control"
                           value="{{ f_brand }}" placeholder="{{ t._('Поиск по Марке') }}">
                </div>

                <div class="col">
                    <input name="model" id="m" type="text" class="form-control"
                           value="{{ f_model }}" placeholder="{{ t._('Поиск по Модели') }}">
                </div>

                <div class="col">
                    <select name="ref_car_cat_id" class="selectpicker form-control" data-live-search="true"
                            data-live-search-placeholder="Поиск по Категории">
                        <option value="all"{% if s_cat_id == 'all' %} selected{% endif %}>
                            - Показать все ({{ categories|length }}) -
                        </option>
                        {% for cat in categories %}
                            <option value="{{ cat.id }}"{% if s_cat_id == cat.id %} selected{% endif %}>
                                {{ t._(cat.name) }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" name="search" class="btn btn-primary">{{ t._('Найти') }}</button>
                    <button type="submit" name="clear" value="clear"
                            class="btn btn-warning">{{ t._('Сбросить') }}</button>
                    {{ link_to('ref_car_model/new', '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success ml-4') }}
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<!--Записи -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("model-directory") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("brand") }}</th>
                <th>{{ t._("model") }}</th>
                <th>{{ t._("Категория") }}</th>
                {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
                    <th>{{ t._("operations") }}</th>
                {% endif %}
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for item in page.items %}
                    <tr>
                        <td width="10%">{{ item.id }}</td>
                        <td>{{ item.brand }}</td>
                        <td>{{ item.model }}</td>
                        <td>{{ t._(item.category) }}</td>
                        {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
                            <td width="10%">
                                <a href="/ref_car_model/edit/{{ item.id }}" class="btn btn-secondary btn-sm"><i
                                            data-feather="edit" width="14" height="14"></i></a>
                            </td>
                        {% endif %}
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
<!-- /Записи -->
{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
