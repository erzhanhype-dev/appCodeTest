<table class="display" cellspacing="0" width="100%" id="viewGoodsList">
    <thead>
    <tr class="">
        <th>{{ t._("tn-code") }}</th>
        <th>{{ t._("basis-good") }}</th>
        <th>{{ t._("basis-date") }}</th>
        <th>{{ t._("goods-weight") }}</th>
        <th>{{ t._("goods-cost") }}</th>
        <th>{{ t._("date-of-import") }}</th>
        <th>{{ t._("package-weight") }}</th>
        <th>{{ t._("package-cost") }}</th>
        <th>{{ t._("total-amount") }}</th>
    </tr>
    </thead>
    <tbody>
    {% if data.goods is defined and data.goods|length %}
        {% for g in data.goods %}
            <tr>
                <td>{{ g.tn_code }}</td>
                <td>{{ g.basis }}</td>
                <td>{{ g.basis_date }}</td>
                <td>{{ g.weight }}</td>
                <td>{{ g.goods_cost }}</td>
                <td>{{ g.date_import }}</td>
                <td>{{ g.package_weight }}</td>
                <td>{{ g.package_cost }}</td>
                <td>{{ g.amount }}</td>
            </tr>
        {% endfor %}
    {% endif %}
    </tbody>
</table>
