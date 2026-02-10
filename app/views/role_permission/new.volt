<!-- заголовок -->
<h2>{{ t._("Добавить разрешение") }}</h2>
<!-- /заголовок -->
{% set pageId = dispatcher.getParam(0) %}
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Разрешение") }}
    </div>
    <div class="card-body">
        <form action="/role_permission/add/{{ pageId }}" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <select name="permission_id" class="form-control">
                    {% for i, item in permissions %}
                        {% if item.id not in rolePermissions %}
                            <option value="{{ item.id }}">{{ item.controller }} - {{ item.action }}</option>
                        {% endif %}
                    {% endfor %}
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ t._('add') }}</button>
    </div>
</div>
