<h3>{{ t._("application-list") }}</h3>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("applications-in-system") }}</div>
            <div class="card-body">
                <table class="table table-hover linked-table">
                    <thead>
                    <tr>
                        <th>{{ t._("num-symbol") }}</th>
                        <th>{{ t._("name-comment") }}</th>
                        <th>{{ t._("profile-type") }}</th>
                        <th>{{ t._("create-date") }}</th>
                        <th>{{ t._("summ-in-application") }}</th>
                        <th>{{ t._("admin-approve-dt") }}</th>
                        <th>{{ t._("ac-approve-bh") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if page.items|length %}
                        {% for item in page.items %}
                            <tr>
                                <td class="v-align-middle">{{ item.p_id }}</td>
                                <td class="v-align-middle">
                                    <a href="/accountant_order/view/{{ item.p_id }}">
                                        {% if item.p_name %}
                                            {{ item.p_name }}
                                        {% else %}
                                            (без имени)
                                        {% endif %}
                                    </a>
                                </td>
                                <td class="v-align-middle">{{ t._(item.p_type) }}</td>
                                <td class="v-align-middle">{{ date("d.m.Y", item.p_created) }}</td>
                                <td class="v-align-middle">
                                    <?php echo number_format($item->t_amount, 2, ",", "&nbsp;");?>
                                </td>
                                <td class="v-align-middle">{{ date("d.m.Y", item.admin_dt_approve) }}</td>
                                <td class="v-align-middle">
                                    <a href="/accountant_order/sign/{{ item.p_id }}/signed"
                                       class="btn btn-sm btn-primary">
                                        {{ t._("ac-approve-button") }}
                                    </a>
                                </td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}