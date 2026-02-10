<!-- заголовок -->
<h2>{{ t._("Справочник КБЕ") }}</h2>
<!-- /заголовок -->
<div class="text-right mb-3">
    {{ link_to("ref_kbe/new", '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success') }}
</div>

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("КБЕ") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("kbe") }}</th>
                <th>{{ t._("kbe-id") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for ref_kbe in page.items %}
                    <tr>
                        <td width="10%">{{ ref_kbe.id }}</td>
                        <td>{{ ref_kbe.kbe }}</td>
                        <td>{{ ref_kbe.name }}</td>
                        <td width="10%">{{ link_to("ref_kbe/edit/"~ref_kbe.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }} {{ link_to("ref_kbe/delete/"~ref_kbe.id, '<i data-feather="trash" width="14" height="14"></i>', 'class': 'btn btn-danger btn-sm') }}</td>
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
