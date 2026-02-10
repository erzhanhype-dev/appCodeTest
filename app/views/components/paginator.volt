{% set controller = dispatcher.getControllerName() %}
{% set action     = dispatcher.getActionName() %}

{# Если передали baseUrl (например /offset_fund/view/7), используем его #}
{% set base = (baseUrl is defined and baseUrl) ? baseUrl : url(controller ~ '/' ~ action) %}

{# собираем query-string, кроме page #}
{% set query = request.getQuery() %}
{% set qs = '' %}

{% for k, v in query %}
    {% if k != 'page' and v is not null and v != '' %}
        {% if v is iterable %}
            {% for vv in v %}
                {% if vv is not null and vv != '' %}
                    {% set qs = qs ~ (qs ? '&' : '') ~ k ~ '[]=' ~ (vv|url_encode) %}
                {% endif %}
            {% endfor %}
        {% else %}
            {% set qs = qs ~ (qs ? '&' : '') ~ k ~ '=' ~ (v|url_encode) %}
        {% endif %}
    {% endif %}
{% endfor %}

{% set tail = qs ? '&' ~ qs : '' %}

{% if page is defined %}
    {% set current = page.getCurrent() %}
    {% set last    = page.getLast() %}
    {% set prev    = page.getPrevious() %}
    {% set next    = page.getNext() %}
    {% set total   = page.getTotalItems() %}
    {% set shown   = page.getItems()|length %}

    {# окно страниц (если не передали — 5) #}
    {% set win = (window is defined and window) ? window : 5 %}
    {% set half = (win // 2) %}

    {% set start = current - half %}
    {% if start < 1 %}{% set start = 1 %}{% endif %}

    {% set end = start + win - 1 %}
    {% if end > last %}
        {% set end = last %}
        {% set start = end - win + 1 %}
        {% if start < 1 %}{% set start = 1 %}{% endif %}
    {% endif %}

    {% if last > 0 %}
        <div class="row my-4">
            <div class="col-auto">
                <span class="btn btn-light">
                    {{ current ~ "/" ~ last }} ({{ shown }} из {{ total }})
                </span>
            </div>

            <div class="col text-center">

                {% set showFL = (showFirstLast is defined and showFirstLast) ? true : false %}

                {% if showFL %}
                    {{ link_to(base ~ "?page=1" ~ tail, t._("Первая"), 'class': 'btn btn-dark' ~ (current == 1 ? ' disabled' : '')) }}
                {% endif %}

                {{ link_to(base ~ "?page=" ~ prev ~ tail, '←', 'class': 'btn btn-dark' ~ (current == 1 ? ' disabled' : '')) }}

                {# если окно не с 1 — покажем "1 …" #}
                {% if start > 1 %}
                    {{ link_to(base ~ "?page=1" ~ tail, '1', 'class': 'btn btn-dark') }}
                    {% if start > 2 %}<span class="mx-1">…</span>{% endif %}
                {% endif %}

                {% for i in start..end %}
                    {{ link_to(
                        base ~ "?page=" ~ i ~ tail,
                        i,
                        'class': 'btn ' ~ (i == current ? 'btn-warning active' : 'btn btn-dark')
                    ) }}
                {% endfor %}

                {% if end < last %}
                    {% if end < last - 1 %}<span class="mx-1">…</span>{% endif %}
                    {{ link_to(base ~ "?page=" ~ last ~ tail, last, 'class': 'btn btn-dark') }}
                {% endif %}

                {{ link_to(base ~ "?page=" ~ next ~ tail, '→', 'class': 'btn btn-dark' ~ (current == last ? ' disabled' : '')) }}

                {% if showFL %}
                    {{ link_to(base ~ "?page=" ~ last ~ tail, t._("Последняя"), 'class': 'btn btn-dark' ~ (current == last ? ' disabled' : '')) }}
                {% endif %}
            </div>

            <div class="col-auto">
                <div style="width: 160px;"></div>
            </div>
        </div>
    {% endif %}
{% endif %}
