<!-- заголовок -->
<h2> {{ t._(" Заявки на корректировку") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/correction_request/index">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col-2">
                    <label><b>Поиск по ID:</b></label>
                    <input name="num" id="num" type="number" class="form-control"
                           value="<?php echo (isset($_SESSION['cr_num_search'])) ? $_SESSION['cr_num_search'] : NULL;?>"
                           placeholder="Введите номер ID">
                </div>
                <div class="col-3">
                    <label><b>Поиск по номеру заявки на УП:</b></label>
                    <input name="pid" type="number" class="form-control"
                           value="<?php echo (isset($_SESSION['cr_pid_search'])) ? $_SESSION['cr_pid_search'] : NULL;?>"
                           placeholder="Введите номер заявки(на УП)">
                </div>
                <?php $status = json_decode($_SESSION['cr_status_search']);?>
                <div class="col-3">
                    <label><b>Поиск по статусу:</b></label>
                    <select name="status[]" class="selectpicker form-control" multiple>
                        <option value="SEND_TO_MODERATOR"
                        <?php if(in_array('SEND_TO_MODERATOR', $status)) echo 'selected'; ?>
                        >{{ t._("SEND_TO_MODERATOR") }}</option>
                        <option value="SENT_TO_ACCOUNTANT"
                        <?php if(in_array('SENT_TO_ACCOUNTANT', $status)) echo 'selected'; ?>
                        >{{ t._("SENT_TO_ACCOUNTANT") }}</option>
                        <option value="APPROVED_BY_MODERATOR"
                        <?php if(in_array('APPROVED_BY_MODERATOR', $status)) echo 'selected'; ?>
                        >{{ t._("APPROVED_BY_MODERATOR") }}</option>
                        <option value="DECLINED"
                        <?php if(in_array('DECLINED', $status)) echo 'selected'; ?>>{{ t._("DECLINED") }}</option>
                    </select>
                </div>
                <?php $action = json_decode($_SESSION['cr_action_search']);?>
                <div class="col-2">
                    <label><b>Поиск по действию:</b></label>
                    <select name="action[]" class="selectpicker form-control" multiple>
                        <option value="CORRECTION"
                        <?php if(in_array('CORRECTION', $action)) echo 'selected'; ?>>{{ t._("CORRECTION") }}</option>
                        <option value="ANNULMENT"
                        <?php if(in_array('ANNULMENT', $action)) echo 'selected'; ?>>{{ t._("ANNULMENT") }}</option>
                        <option value="DELETED"
                        <?php if(in_array('DELETED', $action)) echo 'selected'; ?>>{{ t._("DELETED") }}</option>
                        <option value="CREATED"
                        <?php if(in_array('CREATED', $action)) echo 'selected'; ?>>{{ t._("CREATED") }}</option>
                    </select>
                </div>
                <div class="col-auto mt-4">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    <button type="submit" name="reset" value="all"
                            class="btn btn-warning">{{ t._("Сбросить") }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->
<!-- содержимое заявки -->
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Содержимое заявки") }}</div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>{{ t._("operations") }}</th>
                        <th>{{ t._("ID") }}</th>
                        <th>{{ t._("importer-name") }}</th>
                        <th>{{ t._("num-application") }}</th>
                        <th>{{ t._("type") }}</th>
                        <th>{{ t._("date-of-application") }}</th>
                        <th>{{ t._("Действия") }}</th>
                        <th>{{ t._("Статус") }}</th>
                    </tr>
                    </thead>
                    <tbody>

                    {% if page.items|length %}
                        {% for item in page.items %}
                            <tr class="{% if item.c_status == 'DECLINED' %}table-danger{% endif %}{% if item.c_status == 'APPROVED_BY_MODERATOR' %}table-success{% endif %}">
                                <td class="v-align-middle">
                                    <a href="/correction_request/view/{{ item.c_profile }}/{{ item.id }}"
                                       title="{{ t._("view") }}" class="btn btn-primary" target="_blank"><i
                                                data-feather="eye" width="14" height="14"></i></a>
                                </td>
                                <td class="v-align-middle">{{ item.id }}</td>
                                <td class="v-align-middle">
                                    {{ (item.user_type_id == 1) ? item.fio : item.org_name }}
                                    (<b>{{ item.idnum }}</b>)
                                </td>
                                <td class="v-align-middle">
                                    {% if auth is defined %}
                                        {% if auth.isEmployee() %}
                                            <a href="/moderator_order/view/{{ item.c_profile }}"
                                               target="_blank">{{ item.c_profile }}</a>
                                        {% else %}
                                            <a href="/order/view/{{ item.c_profile }}"
                                               target="_blank">{{ item.c_profile }}</a>
                                        {% endif %}
                                    {% endif %}
                                </td>
                                <td class="v-align-middle">
                                    {{ t._(item.ptype) }}
                                    {% if item.ptype == 'CAR' %}
                                        ( VIN: <a href="/correction_request/view/{{ item.c_profile }}/{{ item.id }}"
                                                  target="_blank">{{ item.vin|dash_to_amp }}</a>)
                                    {% endif %}
                                <td class="v-align-middle">
                                    <?php echo date("d.m.Y H:i", convertTimeZone($item->created));?>
                                </td>
                                <td class="v-align-middle">{{ t._(item.action) | upper }}</td>
                                <td class="v-align-middle">{{ t._(item.c_status) | upper }}</td>

                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>

                {% if page is defined %}
                    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
                {% endif %}

            </div>
        </div>
    </div>
</div>
