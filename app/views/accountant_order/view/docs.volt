{% set fn = 0 %}
{% for file in data['files'] %}
    {% set fn = fn + 1 %}
    {% set filename = constant('APP_PATH') ~ '/private/docs/' ~ file['id'] ~ '.' ~ file['ext'] %}
    <p>
        <a href="{{ url('order/viewdoc/' ~ file['id']) }}"
           class="btn btn-sm btn-secondary"
           target="_blank">
            {{ fn }}. {{ t._(file['type']) }}
            <i>
                {% if file_exists(filename) %}
                    ({{ date("d.m.Y H:i", filemtime(filename)) }})
                {% else %}
                    (Файл не найден!)
                {% endif %}
            </i>
            {% if file['visible'] == 0 %}
                [удален]
            {% endif %}
        </a>&nbsp;

        <a href="{{ url('order/viewdoc/' ~ file['id']) }}"
           class="btn btn-sm btn-primary preview{% if file['ext']|upper == 'PDF' %}pdf{% endif %}">
            <i data-feather="eye" width="14" height="14"></i>&nbsp;{{ file['ext']|upper }}
        </a>&nbsp;

        <a href="{{ url('order/getdoc/' ~ file['id']) }}" class="btn btn-sm btn-success">
            <i data-feather="download" width="14" height="14"></i>&nbsp;Скачать
        </a>&nbsp;

        {% if auth.isAdminSoft() and file['visible'] == 1 %}
            <a href="{{ url('order/rmdoc/' ~ file['id']) }}" class="btn btn-sm btn-danger">
                <i data-feather="x-circle" width="14" height="14"></i>&nbsp;{{ t._("delete") }}
            </a>
        {% endif %}

        {% if auth.isAdminSoft() and file['visible'] == 0 %}
            <a href="{{ url('order/restore/' ~ file['id']) }}" class="btn btn-sm btn-warning">
                <i data-feather="upload" width="14" height="14"></i>&nbsp;{{ t._("Восстановить") }}
            </a>
        {% endif %}

        {% if file['modified_at'] and file['modifier'] %}
            <small class="form-text text-muted">
                Последнее изменение: ({{ file['modifier']['fio'] }} {{ date("d-m-Y H:i", file['modified_at']) }})
            </small>
        {% endif %}
    </p>
{% endfor %}
