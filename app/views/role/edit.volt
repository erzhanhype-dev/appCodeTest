<!-- заголовок -->
<h2>{{ t._("Редактировать роль") }}</h2>
<!-- /заголовок -->

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Роль") }}
    </div>
    <div class="card-body">
        <form action="/role/edit" method="POST" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldName"><b>{{ t._('Код') }}</b><span style="color:red">*</span></label>
                {{ text_field("name", "value": role.name, "size" : 30, "class" : "form-control", "id" : "fieldName", "required":"required", "readonly":"readonly") }}
            </div>
            <div class="form-group">
                <label for="fieldDescription"><b>{{ t._('Название') }}</b><span style="color:red">*</span></label>
                {{ text_field("description",  "value": role.description, "size" : 30, "class" : "form-control", "id" : "fieldDescription", "required":"required", "readonly":"readonly") }}
            </div>
            <hr>
    </div>
</div>


<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        <span>Разрешения для роли: {{ role.description }}</span>
    </div>
    <div class="card-body">

        <a href="/role_permission/new/{{ role.id }}" class="btn btn-primary btn-sm">Добавить разрешения</a>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Контроллер</th>
                <th>Действие</th>
                <th>Описание</th>
                <th>-</th>
            </tr>
            </thead>
            <tbody>
            {% for item in role.getPermissions({ 'order': 'controller' }) %}
                <tr>
                    <td>{{ item.controller }}</td>
                    <td>{{ item.action }}</td>
                    <td>{{ item.description }}</td>
                    <td>
                        <a href="/role_permission/delete/{{ role.id }}/{{ item.id }}" class="btn btn-danger btn-sm"
                           data-confirm='Вы уверены, что хотите удалить это разрешение?'>
                            <i data-feather="trash" width="12" height="12"></i>
                        </a>
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan="2">Нет доступных разрешений для этой роли.</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>

        <table>
            <thead>
            <tr>
                <th>Роль</th>
                <th>Группы разрешений</th>
            </tr>
            </thead>
            <tbody>
            {% for role, groups in groupedPermissions %}
                <tr>
                    <td>{{ role }}</td>
                    <td>{{ groups|join(', ') }}</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>

    </div>
</div>

<style>
    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 8px;
        text-align: left;
        border: 1px solid #ddd;
    }

    th {
        background-color: #f2f2f2;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }
</style>