<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form action="/ref_bank_black_list/" method="get" class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Поиск") }}</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <label for="idnum"><b>{{ t._("search_by_iin_bin") }}:</b></label>
                        <input type="text" name="idnum" id="idnum"
                               value="{{ idnum|default('') }}"
                               class="form-control"
                               pattern="[0-9]*"
                               inputmode="numeric"
                               maxlength="12"
                               placeholder="XXXXXXXXXXXX">
                    </div>

                    <div class="col-2">
                        <label for="status"><b>{{ t._("Статус") }}:</b></label>
                        <select name="status" id="status" class="form-control">
                            <option value="">{{ t._("Любой") }}</option>
                            <option value="ACTIVE"  {{ status=='ACTIVE' ? 'selected' : '' }}>{{ t._("ACTIVE") }}</option>
                            <option value="DELETED" {{ status=='DELETED' ? 'selected' : '' }}>{{ t._("DELETED") }}</option>
                        </select>
                    </div>

                    <div class="col-auto mt-4">
                        <button type="submit" class="btn btn-primary">{{ t._("Применить") }}</button>
                        <a href="/ref_bank_black_list/" class="btn btn-warning">{{ t._("Сбросить фильтр") }}</a>
                        <a href="#" class="btn btn-success" data-toggle="modal" data-target="#createBankBlackList">
                            <i data-feather="plus" width="20" height="14"></i>
                            {{ t._("Добавить") }}
                        </a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
<!-- /форма поиска -->

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("ref_bank_black_list") }}
    </div>
    <div class="card-body" id="REF_BANK_BLACK_LIST_FORM">
        <table id="bankBlackList" class="table table-hover" style="width: 100%">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("iin-bin") }}</th>
                <th>{{ t._("Статус") }}</th>
                <th>{{ t._("Создание(кто? когда?)") }}</th>
                <th>{{ t._("Удаление(кто? когда?)") }}</th>
                <th>{{ t._("Операции") }}</th>
            </tr>
            </thead>
            <tbody>
            {% for r in page.items %}
                {% set created_fmt = r.created_at ? date('Y-m-d H:i:s', r.created_at) : '_' %}
                {% set deleted_fmt = r.deleted_at ? date('Y-m-d H:i:s', r.deleted_at) : '_' %}
                <tr>
                    <td>{{ r.id }}</td>
                    <td><p style="font-family: Montserrat,serif; font-size:14px;">{{ r.idnum }}</p></td>
                    <td>
                        {% if r.status == 'ACTIVE' %}
                            <span class="badge badge-success">{{ t._(r.status) }}</span>
                        {% else %}
                            <span class="badge badge-danger">{{ t._(r.status) }}</span>
                        {% endif %}
                    </td>
                    <td>
                        {{ created_fmt }}
                        {% if r.created_by %}
                            <small class="form-text text-muted">{{ r.created_by }} ({{ r.created_user_idnum }})</small>
                        {% endif %}
                    </td>
                    <td>
                        {% if r.deleted_at %}
                            {{ deleted_fmt }}
                            {% if r.deleted_by %}
                                <small class="form-text text-muted">{{ r.deleted_by }} ({{ r.deleted_user_idnum }})</small>
                            {% endif %}
                        {% else %}
                            _
                        {% endif %}
                    </td>
                    <td class="text-nowrap">
                        <a href="/ref_bank_black_list/delete/{{ r.id }}" class="btn btn-danger"
                           data-confirm='Вы действительно хотите удалить это?'>
                            <i class="fa fa-trash"></i>
                        </a>
                    </td>
                </tr>
            {% else %}
                <tr><td colspan="6">Нет данных</td></tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createBankBlackList" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="myModalLabel">{{ t._("Добавить ИИН/БИН") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <form action="/ref_bank_black_list/create" method="POST">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="form-group">
                        <label for="addBankBlackListInput" class="control-label"><b>{{ t._("Введите ИИН/БИН") }}
                                : </b></label>
                        <input type="text" name="idnum" class="form-control"
                               placeholder="XXXXXXXXXXXX" maxlength="12" minlength="12" autocomplete="off" required>
                        <small id="bank_black_list_input_error" class="form-text text-danger"></small>
                    </div>
                    <div class="form-group float-right">
                        <button type="submit" class="btn crud-submit btn-success">
                            <span class="spinner-border spinner-border-sm" id="add_bank_black_list_spinner"
                                  style="display: none"></span>
                            {{ t._("save") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
