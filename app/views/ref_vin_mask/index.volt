<!-- форма поиска -->
<form method="get" class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Поиск") }}</div>
    <div class="card-body">
        <div class="form-row">
            <div class="col-md-4">
                <label><b>{{ t._("search_by_vin_mask") }}: </b></label>
                <input type="text" name="name" class="form-control" value="{{ filters['name'] }}"
                       placeholder="{{ t._('Название') }}">
            </div>
            <div class="col-md-2">
                <label><b>Действия:</b></label>
                <select name="status" class="form-control">
                    <option value="">{{ t._("Любой") }}</option>
                    <option value="ACTIVE" {{ filters['status']=='ACTIVE'  ? 'selected' : '' }}>{{ t._("ACTIVE") }}</option>
                    <option value="DELETED" {{ filters['status']=='DELETED' ? 'selected' : '' }}>{{ t._("DELETED") }}</option>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button type="submit" class="btn btn-primary">{{ t._("Применить") }}</button>
                <a href="/ref_vin_mask/" class="btn btn-warning ml-2">{{ t._("Сбросить") }}</a>
                <a href="#" class="btn btn-success ml-4" data-toggle="modal" data-target="#createVinMask">
                    <i data-feather="plus" width="20" height="14"></i> {{ t._("Добавить") }}
                </a>
            </div>
        </div>
        <input type="hidden" name="limit" value="{{ limit }}">
        <input type="hidden" name="sort" value="{{ sort }}">
        <input type="hidden" name="dir" value="{{ dir }}">
    </div>
</form>
<!-- /форма поиска -->

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("ref_vin_mask") }}
    </div>
    <div class="card-body" id="REF_VIN_MASK_FORM">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("Маска(VIN)") }}</th>
                <th>{{ t._("Статус") }}</th>
                <th>{{ t._("Создание(кто? когда?)") }}</th>
                <th>{{ t._("Удаление(кто? когда?)") }}</th>
                <th>{{ t._("Операции") }}</th>
            </tr>
            </thead>
            <tbody>
            {% for r in page.items %}
                {% set created_at_fmt = r.created_at > 0 ? r.created_at : '_' %}
                {% set deleted_at_fmt = r.deleted_at > 0 ? r.deleted_at : '_' %}

                <tr>
                    <td>{{ r.id }}</td>
                    <td><p style="font-family: Montserrat; font-size: 14px;">{{ r.name }}</p></td>
                    <td>
                        {% if r.status == 'ACTIVE' %}
                            <span class="badge badge-success">{{ t._(r.status) }}</span>
                        {% else %}
                            <span class="badge badge-danger">{{ t._(r.status) }}</span>
                        {% endif %}
                    </td>
                    <td>
                        {{ created_at_fmt }}
                        {% if r.created_by %}
                            <small class="form-text text-muted">{{ r.created_by }} ({{ r.created_user_idnum }})</small>
                        {% endif %}
                    </td>
                    <td>
                        {% if r.deleted_at %}
                            {{ deleted_at_fmt }}
                            {% if r.deleted_by %}
                                <small class="form-text text-muted">{{ r.deleted_by }} ({{ r.deleted_user_idnum }}
                                    )</small>
                            {% endif %}
                        {% endif %}
                    </td>
                    <td>
                        <a href="/ref_vin_mask/delete/{{ r.id }}" class="btn btn-danger"
                           data-confirm='Вы действительно хотите удалить это?'>
                            <i class="fa fa-trash"></i>
                        </a>
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan="5">Нет данных</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
        {% if page is defined %}
            {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
        {% endif %}

    </div>
</div>

<!-- Create Item Modal -->
<div class="modal fade" id="createVinMask" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="myModalLabel">{{ t._("create_ref_vin_mask") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <form id="addVinMaskForm" action="/ref_vin_mask/create" method="POST">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="form-group">
                        <label class="control-label" for="title">
                            <b>{{ t._("please_type_vin_mask") }}: </b>
                        </label>
                        <input type="text" name="mask" id="addVinMaskInput" class="form-control"
                               style="text-transform: uppercase" placeholder="???ABCD??123?????" maxlength="17"
                               minlength="17" autocomplete="off" required>
                        <small id="vin_mask_input_error" class="form-text text-danger"></small>
                    </div>
                    <div class="form-group float-right">
                        <button type="submit" id="vin_mask_submit_btn" class="btn crud-submit btn-success">
                            <span class="spinner-border spinner-border-sm" id="add_vin_mask_spinner"
                                  style="display: none"></span>
                            {{ t._("save") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Create Item Modal -->

