<table id="viewCarList" class="display" cellspacing="0" width="100%">
    <thead>
    <tr class="">
        <th>{{ t._("num-symbol") }}</th>
        <th>{{ t._("Значение") }}</th>
        <th>{{ t._("cost") }}</th>
        <th>{{ t._("vin-code") }}</th>
        <th>{{ t._("year-of-manufacture") }}</th>
        <th>{{ t._("date-of-import") }}</th>
        <th>{{ t._("country-of-manufacture") }}</th>
        <th>{{ t._("country-of-import") }}</th>
        <th>{{ t._("car-category") }}</th>
        <th>{{ t._("ref-st") }}</th>
        <th>{{ t._("operations") }}</th>
    </tr>
    </thead>
    <tbody>
    {% if cars is defined and cars|length %}
        {% for r in cars %}
            <tr>
                <td class="v-align-middle">{{ r['id'] }}</td>
                <td class="v-align-middle">{{ r['volume'] }}</td>
                <td class="v-align-middle">{{ r['cost'] }}</td>
                <td class="v-align-middle">{{ r['vin']|dash_to_amp }}</td>
                <td class="v-align-middle">{{ r['year'] }}</td>
                <td class="v-align-middle">{{ r['date_import'] }}</td>
                <td class="v-align-middle">{{ r['country'] }}</td>
                <td class="v-align-middle">{{ r['country_import'] }}</td>
                <td class="v-align-middle">{{ r['category'] }}</td>
                <td class="v-align-middle">{{ r['st_type'] }}</td>
                <td>
                    <div class="btn-group">
                        {% if profile.blocked != 1 and (tr.approve == 'DECLINED' or tr.approve == 'NEUTRAL') %}
                            <a href="{{ url('/car/edit/' ~ r['id']) }}" class="btn btn-secondary"
                               title="{{ 'Редактировать автомобиль' }}">
                                <i class="fa fa-edit"></i>
                            </a>
                            <a href="{{ url('/car/delete/' ~ r['id']) }}" class="btn btn-danger confirmBtn"
                               title="{{ 'Удалить автомобиль' }}"
                               data-confirm="Вы уверены, что хотите удалить этот автомобиль?">
                                <i class="fa fa-trash"></i>
                            </a>
                        {% elseif (tr.approve == 'GLOBAL' and tr.ac_approve == 'SIGNED') %}
                            <button class="btn btn-warning dropdown-toggle" type="button"
                                    id="svupDropDownMenuButton" data-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-download"></i> Сертификат
                            </button>

                            <a href="/car/correction/{{ r['id'] }}" class="btn btn-primary">
                                <i class="fa fa-edit"></i>
                            </a>

                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item disabled {{ tr.dt_approve < 1642528800 ? 'disabled' : '' }}"
                                   href="/main/certificate_kz/{{ profile.id }}/{{ r['id'] }}">
                                    <i class="fa fa-download"></i> Қазақ тілінде жүктеу
                                </a>
                                <a class="dropdown-item"
                                   href="/main/certificate/{{ profile.id }}/{{ r['id'] }}">
                                    <i class="fa fa-download"></i> Скачать на русском языке
                                </a>
                            </div>
                        {% endif %}
                    </div>
                </td>
            </tr>
        {% endfor %}
    {% endif %}
    </tbody>
</table>



