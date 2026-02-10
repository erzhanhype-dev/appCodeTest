{# ===== Заголовок ===== #}
<div class="row">
    <div class="col-6"><h2>{{ t._("Список заявок") }}</h2></div>
    <div class="col-6">
        <div class="float-right">
            <a href="/order/new/" class="btn btn-success btn-lg" id="ddActions">
                <i data-feather="plus" width="20" height="14"></i> {{ t._("Создать новую заявку") }}
            </a>
        </div>
    </div>
</div>

{# ===== Форма поиска (GET) ===== #}
<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Поиск") }}</div>
    <div class="card-body">
        <form method="get" action="/order/index" autocomplete="off">
            <div class="row g-2">

                <div class="col-2">
                    <label><b>Номер заявки:</b></label>
                    <input name="pid" type="text" class="form-control"
                           value="{{ q_pid|default('') }}"
                           placeholder="Номер заявки">
                </div>

                <div class="col-2">
                    <label><b>Год:</b></label>
                    {% set q_years = q_years|default([]) %}
                    <select name="year[]" class="selectpicker form-control" multiple>
                        {% for y in allYears %}
                            <option value="{{ y }}"
                                    {% if q_years and (y in q_years) %}selected{% endif %}>{{ y }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>Тип заявки:</b></label>
                    {% set q_types_safe = q_types|default([]) %}
                    <select name="type[]" class="selectpicker form-select" multiple>
                        {% for tcode in allTypes %}
                            <option value="{{ tcode }}"
                                    {% if q_types_safe and (tcode in q_types_safe) %}selected{% endif %}>
                                {{ t._(tcode) }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>Статус заявки:</b></label>
                    {% set q_states_safe = q_states|default([]) %}
                    <select name="status[]" class="selectpicker form-select" multiple>
                        {% for scode in allStatuses %}
                            <option value="{{ scode }}"
                                    {% if q_states_safe and (scode in q_states_safe) %}selected{% endif %}>
                                {{ t._(scode) }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-auto align-self-end">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    {% if hasFilters %}
                        <a href="/order/index" class="btn btn-warning">{{ t._("Сбросить") }}</a>
                    {% endif %}
                </div>

            </div>
        </form>

        {% if hasFilters %}
            <div class="mt-2 text-muted small">Применены фильтры</div>
        {% else %}
            <div class="mt-2 text-muted small">Фильтры не применены — показаны все записи</div>
        {% endif %}
    </div>
</div>

{# ===== Таблица ===== #}
<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Заявки") }}</div>
    <div class="card-body">
        <table id="orderList" class="table table-sm">
            <thead>
            <tr>
                <th>{{ t._("num-symbol") }}</th>
                <th>{{ t._("application-date") }}</th>
                <th>{{ t._("sign-date") }}</th>
                <th>{{ t._("create-sent-date") }}</th>
                <th>{{ t._("amount") }}</th>
                <th>{{ t._("order-type") }}</th>
                <th>{{ t._("profile-paid") }}</th>
                <th>{{ t._("profile-approve") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if orders is defined and orders|length %}
                {% for item in orders %}
                    <tr>
                        <td class="v-align-middle">{{ item['id'] }}</td>
                        <td class="v-align-middle">{{ item['created'] }}</td>
                        <td class="v-align-middle">{{ item['sign_date'] }}</td>
                        <td class="v-align-middle">{{ item['transaction']['dt_sent'] }}</td>
                        <td class="v-align-middle">{{ item['transaction']['amount'] }}</td>
                        <td class="v-align-middle"><i>{{ t._(item['type']) }}</i></td>
                        <td class="v-align-middle">{{ t._(item['transaction']['status']) }}</td>
                        <td class="v-align-middle">{{ t._(item['transaction']['approve']) }}</td>
                        <td class="v-align-middle">
                            <a href="/order/view/{{ item['id'] }}" title='{{ t._("browsing") }}'
                               class="btn btn-primary btn-sm">
                                <i data-feather="eye" width="14" height="14"></i>
                            </a>
                            {% if item['blocked'] == false %}
                                <a href="/order/edit/{{ item['id'] }}" title='{{ t._("edit") }}'
                                   class="btn btn-warning btn-sm">
                                    <i data-feather="edit" width="14" height="14"></i>
                                </a>
                            {% endif %}
                        </td>
                    </tr>
                {% endfor %}
            {% else %}
                <tr>
                    <td colspan="9" class="text-center text-muted">Ничего не найдено</td>
                </tr>
            {% endif %}
            </tbody>
        </table>
    </div>
</div>

{% if page is defined and page.current %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
