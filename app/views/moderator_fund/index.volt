<!-- заголовок -->
<h2>{{ t._("Список заявок на финансирование") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-1">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/moderator_fund/">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col-2">
                    <label><b>Номер заявки:</b></label>
                    <input type="text"
                           name="fund_number"
                           class="form-control"
                           placeholder="Например: САП-2023/12291"
                           value="{{ fund_filter_number|default('') }}">
                </div>

                <div class="col-3">
                    <label><b>Поиск по БИН или Название организации:</b></label>
                    <select name="num"
                            data-size="5"
                            class="selectpicker form-control"
                            data-live-search="true"
                            data-live-search-placeholder="Введите БИН или Название организации">
                        <option value="all"{% if selected_uid == 'all' %} selected{% endif %}>
                            - Показать все ({{ companies_view|length }}) -
                        </option>
                        {% for c in companies_view %}
                            <option value="{{ c.user_id }}"{% if selected_uid == c.user_id %} selected{% endif %}>
                                {{ c.label }}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                {% if not fund_stage_user %}
                    <div class="col-3">
                        <label><b>Статус заявки:</b></label>
                        <select name="status[]" class="selectpicker form-control" multiple>
                            {% set allStatuses = ['FUND_REVIEW','FUND_DECLINED','FUND_TOSIGN','FUND_PREAPPROVED','FUND_DONE','FUND_NEUTRAL'] %}
                            {% for st in allStatuses %}
                                <option value="{{ st }}"{% if st in selected_status %} selected{% endif %}>{{ t._(st) }}</option>
                            {% endfor %}
                        </select>
                    </div>
                {% endif %}

                <div class="col-2">
                    <label><b>Поиск по Год:</b></label>
                    <select name="year[]" class="selectpicker form-control" multiple>
                        {% for y in years_list %}
                            <option value="{{ y }}"{% if y in selected_years %} selected{% endif %}>{{ y }}</option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>Тип заявки:</b></label>
                    <select name="type[]" class="selectpicker form-control" multiple>
                        <option value="INS"{% if 'INS' in selected_types %} selected{% endif %}>Внутреннее</option>
                        <option value="EXP"{% if 'EXP' in selected_types %} selected{% endif %}>Экспорт</option>
                    </select>
                </div>
            </div>

            <div class="row mt-2">
                {% if not fund_stage_user %}
                    <div class="col-3">
                        <label><b>По состояние(подписанта) заявки:</b></label>
                        <select name="state" class="selectpicker form-control">
                            <option value="ALL"{% if selected_state == 'ALL' %} selected{% endif %}>{{ t._('Показать все') }}</option>
                            <option value="AT_HOD"{% if selected_state == 'AT_HOD' %} selected{% endif %}>{{ t._('FUND_AT_THE_HOD') }}</option>
                            <option value="AT_FAD"{% if selected_state == 'AT_FAD' %} selected{% endif %}>{{ t._('FUND_AT_THE_FAD') }}</option>
                            <option value="AT_HOP"{% if selected_state == 'AT_HOP' %} selected{% endif %}>{{ t._('FUND_AT_THE_HOP') }}</option>
                            <option value="AT_HOF"{% if selected_state == 'AT_HOF' %} selected{% endif %}>{{ t._('FUND_AT_THE_HOF') }}</option>
                        </select>
                    </div>
                {% endif %}

                <div class="col-auto mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="search" width="20" height="14"></i>
                        {{ t._('search') }}
                    </button>
                    <button type="submit" name="reset" value="all" class="btn btn-warning">
                        <i data-feather="refresh-cw" width="20" height="14"></i>
                        {{ t._('Сбросить') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<div class="card mt-1">
    <div class="card-header bg-dark text-light">
        {{ t._("Заявки на финансирование") }}
    </div>
    <div class="card-body">
        <table class="table table-hover table-sm">
            <thead>
            <tr class="">
                {% if auth is defined and (auth.isSuperModerator() or auth.isAccountant()) and (auth.fund_stage == 'HOP' or auth.fund_stage == 'HOF') %}
                    <th style="width:32px;" style="display: none">
                        <input type="checkbox" id="checkAll">
                    </th>
                {% else %}
                    <th></th>
                {% endif %}
                <th>{{ t._("Номер") }}</th>
                <th style="width:28%;">{{ t._("Отправитель") }}</th>
                <th>{{ t._("Сумма заявки, тенге") }}</th>
                <th>{{ t._("Отправлена") }}</th>
                <th>{{ t._("Стимулирование") }}</th>
                <th>{{ t._("Тип") }}</th>
                <th>{{ t._("Состояние") }}</th>
                <th>{{ t._("Текущий статус") }}</th>
                <th>{{ t._("Операции") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length %}
                {% for item in page.items %}
                    <tr class="{% if item.approve == 'FUND_DECLINED' or item.approve == 'FUND_ANNULMENT' %}table-danger{% endif %}{% if item.approve == 'FUND_DONE' %}table-success{% endif %}">
                        {% if auth is defined and (auth.isSuperModerator() or auth.isAccountant()) and ((auth.fund_stage == 'HOP' and item.signed_by == 2) or (auth.fund_stage == 'HOF' and item.signed_by == 3)) %}
                            <td>
                                <input type="checkbox" class="fund_id-checkbox" value="{{ item.id }}">
                            </td>
                        {% else %}
                            <td></td>
                        {% endif %}
                        <td class="v-align-middle">{{ item.number }}</td>
                        <td style="text-transform: uppercase;">
                            <a href="/moderator_fund/view/{{ item.id }}">
                                {{ item.fio }} ({{ item.idnum }})
                            </a>
                        </td>
                        <td class="v-align-middle"><?php echo number_format($item->amount, 2, ",", "&nbsp;"); ?></td>
                        <td class="v-align-middle">
                            <?php echo ($item->md_dt_sent > 0) ? date("d.m.Y H:i", convertTimeZone($item->md_dt_sent)) :
                            '—';?>
                        </td>
                        <td class="v-align-middle"><?php echo $item->type == 'INS' ? 'Внутреннее' : 'Экспорт'; ?></td>
                        <td class="v-align-middle"><?php echo $item->entity_type == 'CAR' ? 'ТС' : 'Товар'; ?></td>
                        <td class="v-align-middle">
                            {% if item.signed_by == 0 and (item.approve == 'FUND_NEUTRAL' or item.approve == 'FUND_DECLINED') %}
                                <i data-feather="refresh-cw" width="14" height="14"></i>{% endif %}
                            {% if item.signed_by == 0 and item.approve == 'FUND_PREAPPROVED' %}<i data-feather="clock"
                                                                                                  width="14"
                                                                                                  height="14"></i>{% endif %}

                            <?php for($i=0; $i < $item->signed_by; $i++) : ?>
                            <i data-feather="user" width="14" height="14"></i>
                            <?php endfor; ?>
                        </td>
                        <td class="v-align-middle">{{ t._(item.approve) }}</td>
                        <td class="v-align-middle">
                            <a href="/moderator_fund/view/{{ item.id }}" title='{{ t._("browsing") }}'
                               class="btn btn-primary btn-sm"><i data-feather="server" width="14" height="14"></i></a>
                            <a href="/moderator_fund/download_zip/{{ item.id }}" title='{{ t._("download") }}'
                               class="btn btn-primary btn-sm" target="_blank"><i data-feather="star" width="14"
                                                                                 height="14"></i> Скачать все</a>
                        </td>
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
        {% if auth is defined and (auth.isSuperModerator() or auth.isAccountant()) and (auth.fund_stage == 'HOP' or auth.fund_stage == 'HOF') %}
            <form id="formSign" action="/moderator_fund/sign_mass" method="post">
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                <textarea id="sign" name="fundSigns" class="hidden" type="hidden"></textarea>
                <button class="btn btn-sm btn-primary d-none" id="signMultipleFundCheck">Подписать и согласовать
                    выбранные
                </button>
            </form>
        {% endif %}
    </div>
</div>

{{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}