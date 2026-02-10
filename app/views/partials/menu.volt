<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="text-left text-white mt-1 mb-1">
        <div style="padding: .75rem 1.25rem;">
            {% if(role.name !== 'client') %}
                <span style="font-size: 12px">{{ role.description }}</span>
                <br>
            {% endif %}
            <span style="font-size: 10px;color: #c0c0c0;">
                {{ auth.fio ? auth.fio : auth.org_name }}
            </span>
        </div>
    </div>
    <div class="list-group list-group-flush">
        {% set hasVisibleItems = false %}

        {% for item in menuItems %}
            {% set hasPermission = false %}

            {% if item['submenu'] is not defined %}
                {% for permission in permissions %}
                    {% if (permission.controller == item['controller'] and ( permission.action == item['action'] or permission.action == '*')) %}
                        {% set hasPermission = true %}
                        {% break %}
                    {% endif %}
                {% endfor %}

                {% if (role.name !== 'client' and role.name !== 'agent') and item['controller'] === 'order' %}
                    {% set hasPermission = false %}
                {% endif %}

                {% if hasPermission %}
                    {% set hasVisibleItems = true %}
                    <a href="{{ item['url'] }}" class="list-group-item list-group-item-action bg-dark text-light">
                        <i data-feather="{{ item['icon'] }}"></i> {{ t._(item['text']) }}
                    </a>
                {% endif %}
            {% else %}
                {% set subMenuHasPermission = false %}

                {% for subItem in item['submenu'] %}
                    {% for permission in permissions %}
                        {% if permission.controller == subItem['controller'] %}
                            {% set subMenuHasPermission = true %}
                            {% break %}
                        {% endif %}
                    {% endfor %}
                    {% if subMenuHasPermission %}
                        {% set hasPermission = true %}
                        {% break %}
                    {% endif %}
                {% endfor %}

                {% if hasPermission %}
                    {% set hasVisibleItems = true %}
                    <a data-toggle="collapse" href="#submenu{{ loop.index }}" role="button" aria-expanded="false"
                       aria-controls="submenu" class="list-group-item list-group-item-action bg-dark text-light">
                        <i data-feather="{{ item['icon'] }}"></i> {{ t._(item['text']) }} <i data-feather="chevron-down"></i>
                    </a>
                    <div class="collapse" id="submenu{{ loop.index }}">
                        <div class="list-group list-group-flush">
                            {% for subItem in item['submenu'] %}
                                {% set hasPermission = false %}
                                {% for permission in permissions %}
                                    {% if permission.controller == subItem['controller'] and ( permission.action == subItem['action'] or permission.action == '*') %}
                                        {% set hasPermission = true %}
                                        {% break %}
                                    {% endif %}
                                {% endfor %}
                                {% if hasPermission %}
                                    <a href="{{ subItem['url'] }}"
                                       class="list-group-item list-group-item-action bg-secondary text-light">
                                        {{ t._(subItem['text']) }}
                                    </a>
                                {% endif %}
                            {% endfor %}
                        </div>
                    </div>
                {% endif %}
            {% endif %}
        {% endfor %}

        <a href="/settings/index" class="list-group-item list-group-item-action bg-dark text-light">
            <i data-feather="settings"></i> {{ t._("Настройки") }}
        </a>
        <a href="/session/signout" class="list-group-item list-group-item-action bg-dark text-light">
            <i data-feather="log-out"></i> {{ t._("exit") }}
        </a>
    </div>
</div>
