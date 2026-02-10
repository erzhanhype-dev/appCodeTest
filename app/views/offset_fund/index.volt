<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h5 class="mb-0">Реестр заявок</h5>
        <small class="text-muted">Финансирование методом взаимозачета</small>
    </div>
    <div class="col-md-6 text-right text-end">
        <a href="/offset_fund/new" class="btn btn-success">
            <i class="fa fa-plus"></i> Создать заявку
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ url('offset_fund/index') }}" method="get">

            <div class="row">
                <div class="col-md-2 mb-2">
                    <label>№ Заявки</label>
                    <input type="text" name="id" class="form-control" value="{{ request.getQuery('id') }}"
                           placeholder="ID">
                </div>

                <div class="col-md-3 mb-2">
                    <label>Заявитель</label>
                    <input type="text" name="search" class="form-control" value="{{ request.getQuery('search') }}"
                           placeholder="БИН или Название">
                </div>

                <div class="col-md-3 mb-2">
                    <label>Статус</label>
                    <select name="status" class="form-control">
                        <option value="">Все статусы</option>
                        {% for key, name in statuses %}
                            <option value="{{ key }}" {{ request.getQuery('status') == key ? 'selected' : '' }}>
                                {{ name }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-md-2 mb-2">
                    <label>Год</label>
                    <select name="year" class="form-control">
                        <option value="">Все года</option>
                        {% for y in 2022..date('Y') %}
                            <option value="{{ y }}" {{ request.getQuery('year') == y ? 'selected' : '' }}>{{ y }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-md-2 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-18 mr-2" title="Найти">
                        <i class="fa fa-search"></i>
                    </button>
                    <a href="{{ url('offset_fund/index') }}" class="btn btn-outline-secondary" title="Сбросить">
                        <i class="fa fa-times"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive mt-3">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="thead-light">
                <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Заявитель</th>
                    <th>Тип / Категория</th>
                    <th class="text-right">Объем/масса/вес</th>
                    <th class="text-right">Сумма</th>
                    <th>Статус</th>
                    <th class="text-right">Действия</th>
                </tr>
                </thead>
                <tbody>
                {% if page.items|length > 0 %}
                    {% for item in page.items %}
                        <tr>
                            <td>{{ item.id }}</td>
                            <td>
                                  <span class="text-muted small">
                                    Созд: {{ date('d.m.Y H:i', item.created_at) }}
                                  </span>
                                {% if item.sent_at %}
                                    <br><span class="small">Отпр: {{ date('d.m.Y H:i', item.sent_at) }}</span>
                                {% endif %}
                            </td>
                            <td>
                                <div class="">{{ item.user_fio }}</div>
                                <small class="">{{ item.user_idnum }}</small>
                            </td>

                            <td>
                                <small>{{ item.key_name ? item.key_name : '' }}</small>
                                {% if item.entity_type == 'GOODS' %}
                                    <span class="badge badge-info">Товар</span>
                                {% else %}
                                    <span class="badge badge-secondary">ТС</span>
                                {% endif %}
                            </td>

                            <td class="text-right">
                                {% if item.entity_type == 'GOODS' %}
                                    {{ item.total_value }} кг
                                {% else %}
                                    {{ item.total_value }} ед.
                                {% endif %}
                            </td>

                            <td class="text-right">
                                {{ item.amount|number_format(0, '.', '') }} ₸
                            </td>

                            <td>
                                {% set badge_class = 'secondary' %}
                                {% if item.status == 'NEW' %}{% set badge_class = 'primary' %}{% endif %}
                                {% if item.status == 'PENDING' %}{% set badge_class = 'warning' %}{% endif %}
                                {% if item.status == 'CERT_RECEIVED' %}{% set badge_class = 'success' %}{% endif %}
                                {% if item.status == 'DECLINED' %}{% set badge_class = 'danger' %}{% endif %}
                                {% if item.status == 'CANCELLED' %}{% set badge_class = 'dark' %}{% endif %}

                                <span class="badge badge-{{ badge_class }}">
                                {{ statuses[item.status] is defined ? statuses[item.status] : item.status }}
                            </span>
                            </td>

                            <td class="text-right">
                                <a href="{{ url('offset_fund/view/' ~ item.id) }}"
                                   class="btn btn-sm btn-primary"
                                   title="Просмотр">
                                    <i class="fa fa-eye"></i>
                                </a>

                                {% if item.status == 'NEW' %}
                                    <a href="{{ url('offset_fund/delete/' ~ item.id) }}"
                                       class="btn btn-sm btn-danger"
                                       title="Удалить"
                                       onclick="return confirm('Удалить')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                {% endif %}
                            </td>
                        </tr>
                    {% endfor %}
                {% else %}
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            Записей не найдено.
                        </td>
                    </tr>
                {% endif %}
                </tbody>
            </table>
        </div>
    </div>
</div>
