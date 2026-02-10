<table class="display" cellspacing="0" width="100%" id="viewGoodsList">
    <thead>
    <tr class="">
        <th>{{ t._("operations") }}</th>
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
                <td>
                    <div class="btn-group">
                        {% if (data.approve == 'GLOBAL' and data.ac_approve == 'SIGNED') %}
                            <hr>
                            <div class="dropdown">
                                <button class="btn btn-warning dropdown-toggle" type="button"
                                        id="svupDropDownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                        aria-expanded="false">
                                    <i class="fa fa-download"></i> Сертификат
                                </button>
                                <div class="dropdown-menu" aria-labelledby="svupDropDownMenuButton">
                                    <a class="dropdown-item disabled {{ data.approve_dt|strtotime < 1642528800 ? 'disabled' : '' }}"
                                       href="/main/certificate_kz/{{ data.id }} / {{ g.id }}">
                                        <i class="fa fa-download"></i> Қазақ тілінде жүктеу
                                    </a>
                                    <a class="dropdown-item"
                                       href="/main/certificate/{{ data.id }} / {{ g.id }}">
                                        <i class="fa fa-download"></i> Скачать на русском языке
                                    </a>
                                </div>
                            </div>
                        {% else %}
                            —
                        {% endif %}
                    </div>
                </td>
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
