<div class="row">
    <div class="col-4">
        <h2>{{ t._("Роли") }}</h2>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("Код") }}</th>
                <th>{{ t._("Наименование") }}</th>
                <th>{{ t._("-") }}</th>
            </tr>
            </thead>
            <tbody>
            {% for item in roles %}
                <tr>
                    <td width="10%">{{ item.id }}</td>
                    <td>{{ item.name }}</td>
                    <td>{{ item.description }}</td>
                    <td>
                        {% if user.role.priority < item.priority %}
                            <a href="{{ "/role/edit/"~item.id }}" class="btn btn-primary btn-sm" title="{{ t._("edit") }}"><i data-feather="edit" width="12" height="12"></i></a>
                        {% endif %}
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
</div>
<!-- /банки -->
