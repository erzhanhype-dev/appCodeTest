<!-- заголовок -->
<h2>{{ t._("Справочник типов") }}</h2>
<!-- /заголовок -->
<div class="text-right mb-3">
    {{ link_to("ref_car_type/new", '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success') }}
</div>
<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Типы") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("car-type-name") }}</th>
                {% if  auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
                    <th>{{ t._("operations") }}</th>
                {% endif %}
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for ref_car_type in page.items %}
                    <tr>
                        <td width="10%">{{ ref_car_type.id }}</td>
                        <td>{{ ref_car_type.name }}</td>
                        {% if  auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
                            <td width="10%">
                                {{ link_to("ref_car_type/edit/"~ref_car_type.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }}
                            </td>
                        {% endif %}
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
<!-- /банки -->

{% if page is defined and page.current is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}