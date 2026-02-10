<h2>{{ t._("Список пользователей в системе") }}</h2>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="post" action="/users" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row g-2">
                <div class="col-auto">
                    <label class="form-label"><b>ID:</b></label>
                    <input type="number" name="id" class="form-control"
                           placeholder="{{ t._("Введите ID") }}" value="{{ filters['id'] }}">
                </div>

                <div class="col-auto">
                    <label class="form-label"><b>ИИН/БИН:</b></label>
                    <input type="text" name="idnum" class="form-control"
                           placeholder="{{ t._("Введите БИН, ИИН") }}" value="{{ filters['idnum'] }}">
                </div>

                <div class="col-auto">
                    <label class="form-label"><b>ФИО:</b></label>
                    <input type="text" name="name" class="form-control"
                           placeholder="{{ t._("Введите ФИО") }}" value="{{ filters['name'] }}">
                </div>

                <div class="col-auto">
                    <label class="form-label"><b>Активен?:</b></label>
                    <select name="is_active" class="form-control">
                        <option value="">{{ t._("Выберите статус") }}</option>
                        <option value="1" {{ filters['is_active'] is defined and filters['is_active'] == 1 ? 'selected' : '' }}>{{ t._("Активный") }}</option>
                        <option value="0" {{ filters['is_active'] is defined and filters['is_active'] == 0 ? 'selected' : '' }}>{{ t._("Неактивный") }}</option>
                    </select>
                </div>

                <div class="col-auto">
                    <label class="form-label"><b>Сотрудник АО “Жасыл даму?”:</b></label>
                    <select name="is_employee" class="form-control">
                        <option value="">{{ t._("Выберите статус") }}</option>
                        <option value="1" {{ filters['is_employee'] is defined and filters['is_employee'] == 1 ? 'selected' : '' }}>{{ t._("Да") }}</option>
                        <option value="0" {{ filters['is_employee'] is defined and filters['is_employee'] == 0 ? 'selected' : '' }}>{{ t._("Нет") }}</option>
                    </select>
                </div>

                <div class="col-auto">
                    <label class="form-label"><b>Роль:</b></label>
                    <select name="role_id" class="form-control">
                        <option value="">{{ t._("Все") }}</option>
                        {% for item in roles %}
                            <option value="{{ item.id }}" {{ filters['role_id'] is defined and filters['role_id'] == item.id ? 'selected' : '' }}>
                                {{ item.description }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-success me-2 mr-1">
                        {{ t._("Найти") }}
                    </button>
                    <a href="/users?clear=1" class="btn btn-warning">
                        <i data-feather="refresh-cw" width="20" height="14"></i>
                        {{ t._("Сбросить") }}
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Пользователи") }}</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>{{ t._("login") }}</th>
                    <th>{{ t._("ФИО / Наименование") }}</th>
                    <th>{{ t._("active") }}</th>
                    <th>{{ t._("is_employee") }} {{ t._("copyright-company") }}</th>
                    <th>{{ t._("role") }}</th>
                    <th>{{ t._("last_login") }}</th>
                    <th>{{ t._("operations") }}</th>
                </tr>
                </thead>
                <tbody>
                {% if page.items|length > 0 %}
                    {% for user in page.items %}
                        <tr>
                            <td>{{ user.id }}</td>
                            <td>{{ user.idnum }}</td>
                            <td>{{ (user.fio ? user.fio : user.org_name) | default('') | upper }}</td>
                            <td>{{ t._("yesno-" ~ user.active) | upper }}</td>
                            <td>{{ t._("yesno-" ~ user.is_employee) | upper }}</td>
                            <td>{{ user.role.description | default('') }}</td>
                            <td>{{ user.last_login ? date("d.m.Y H:i:s", user.last_login) : '' }}</td>
                            <td class="text-nowrap">
                                <a href="{{ "/users/edit/" ~ user.id }}" class="btn btn-primary btn-sm"
                                   title="{{ t._("edit") }}">
                                    <i data-feather="edit" width="12" height="12"></i>
                                </a>
                                <a href="{{ "/users/shadow/" ~ user.id }}" class="btn btn-warning btn-sm"
                                   title="{{ t._("shadow-mode") }}">
                                    <i data-feather="user" width="12" height="12"></i>
                                </a>
                            </td>
                        </tr>
                    {% endfor %}
                {% endif %}
                </tbody>
            </table>
        </div>
        {% if page is defined %}
            {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
        {% endif %}
    </div>
</div>
