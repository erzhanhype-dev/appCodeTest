{# ---------- Поиск ---------- #}
<div class="card mt-1">
    <div class="card-header bg-dark text-light">{{ t._('Поиск') }}</div>
    <div class="card-body">
        <form method="POST" action="/operator_order">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col-2">
                    <label><b>Номер заявки:</b></label>
                    <input name="q" type="number" class="form-control" value="{{ filters.q }}" placeholder="Номер заявки">
                </div>

                <div class="col-2">
                    <label><b>Поиск по ИИН/БИН:</b></label>
                    <input name="idnum" type="number" class="form-control" maxlength="12" minlength="12" value="{{ filters.idnum }}" placeholder="ИИН / БИН">
                </div>

                <div class="col-2">
                    <label><b>Поиск по тип пользователя:</b></label>
                    <select name="user_types" class="form-control">
                        {% for opt in userTypeOptions %}
                            <option value="{{ opt.value }}"{{ opt.selected }}>{{ opt.label }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-6">
                    <label><b>Статус заявки:</b></label>
                    <select name="p_status[]" class="selectpicker form-control" multiple>
                        {% for opt in statusOptions %}
                            <option value="{{ opt.value }}"{{ opt.selected }}>{{ opt.label }}</option>
                        {% endfor %}
                    </select>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-2">
                    <label><b>Год:</b></label>
                    <select name="year[]" class="selectpicker form-control" multiple>
                        {% for opt in yearOptions %}
                            <option value="{{ opt.value }}"{{ opt.selected }}>{{ opt.label }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>Седельность:</b></label>
                    <select name="st_type" class="selectpicker form-control">
                        {% for opt in stTypeOptions %}
                            <option value="{{ opt.value }}"{{ opt.selected }}>{{ opt.label }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>Поиск по сумме УП, тг:</b></label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="amount_from" min="0" max="99999999999" value="{{ filters.amount_from }}" placeholder="С" required>
                        <input type="number" class="form-control" name="amount_end"   min="0" max="99999999999" value="{{ filters.amount_end }}"   placeholder="По" required>
                    </div>
                </div>

                {% if filters.view_mode == 1 %}
                    <div class="col-2">
                        <label><b>Тип заявки:</b></label>
                        <select name="p_type[]" class="selectpicker form-control" multiple>
                            {% for opt in typeOptions %}
                                <option value="{{ opt.value }}"{{ opt.selected }}>{{ opt.label }}</option>
                            {% endfor %}
                        </select>
                    </div>
                {% endif %}

                <div class="col-auto mt-4">
                    <button type="submit" name="search" class="btn btn-primary">{{ t._('search') }}</button>
                    <a href="/operator_order/clearFilter" class="btn btn-warning">{{ t._('Сбросить') }}</a>
                </div>
            </div>
        </form>
    </div>
</div>

{# ---------- Таблица ---------- #}
<div class="card mt-1">
    <div class="card-header bg-dark text-light">{{ t._('Заявки в системе') }}</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="order_list_table">
                <thead>
                <tr>
                    <th>{{ t._('num-symbol') }}</th>
                    <th width="20%">Наименование или ФИО</th>
                    <th>{{ t._('create-date') }}</th>
                    <th>{{ t._('sign-date') }}</th>
                    <th>{{ t._('create-sent-date') }}</th>
                    <th>{{ t._('summ-in-application') }}</th>
                    <th>{{ t._('profile-paid') }}</th>
                    <th>{{ t._('profile-approve') }}</th>
                </tr>
                </thead>
                <tbody>
                {% if items|length %}
                    {% for it in items %}
                        <tr class="{{ it.row_class }}">
                            <td class="v-align-middle">{{ it.p_id }}</td>
                            <td class="v-align-middle"><a href="/operator_order/view/{{ it.p_id }}" target="_blank">{{ it.name_line }}</a></td>
                            <td class="v-align-middle">{{ it.p_created_str }}</td>
                            <td class="v-align-middle">{{ it.sign_date_str }}</td>
                            <td class="v-align-middle">{{ it.dt_sent_str }}</td>
                            <td class="v-align-middle">{{ it.amount_str }}</td>
                            <td class="v-align-middle">
                                {{ t._(it.paid_label) }}{% if it.auto_detected == '1' %}&nbsp;<span style="background:#d35547;color:#fff;padding:0 2px;">{{ t._('auto-detected') }}</span>{% endif %}
                            </td>
                            <td class="v-align-middle">{{ t._(it.approve_label) }}

                            </td>
                        </tr>
                    {% endfor %}
                {% endif %}
                </tbody>
            </table>
        </div>
    </div>
</div>

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
