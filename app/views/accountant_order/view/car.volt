<table id="viewCarList" class="display dataTable">
    <thead>
    <tr>
        <th>{{ t._("operations") }}</th>
        <th>{{ t._("vin-code") }}</th>
        <th>{{ t._("car-category") }}</th>
        <th>{{ t._("volume-weight") }}</th>
        <th>{{ t._("year-of-manufacture") }}</th>
        <th>{{ t._("date-of-import") }}</th>
        <th>{{ t._("country-of-manufacture") }}</th>
        <th>{{ t._("amount-of-payment") }}</th>
    </tr>
    </thead>
    <tbody>
    {% if data['cars']|length %}
        {% for r in data['cars'] %}
            <tr>
                <td class="v-align-middle">
                    <div class="btn-group">
                        <a href="#" data-toggle="modal" class="btn btn-primary" data-id="{{ r.id }}"
                           data-target=".car_info_modal" id="displayCarInfo"><i class="fa fa-eye"></i>
                        </a>
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
                                       href="/main/certificate_kz/{{ data.id }}/{{ r.id }}">
                                        <i class="fa fa-download"></i> Қазақ тілінде жүктеу
                                    </a>
                                    <a class="dropdown-item"
                                       href="/main/certificate/{{ data.id }}/{{ r.id }}">
                                        <i class="fa fa-download"></i> Скачать на русском языке
                                    </a>
                                </div>
                            </div>
                        {% endif %}
                        <hr>
                        {% if r.kap_request_id > 0 or r.kap_log_id > 0 %}
                            <a href="#" data-toggle="modal" class="btn btn-info" data-id="{{ r.id }}"
                               data-target=".kap_info_modal" id="displayKAPInfo">
                                <div style="width: 50px"><i class="fa fa-eye"></i> КАП</div>
                            </a>
                            <hr>
                        {% endif %}
                        {% if r.epts_request_id > 0 %}
                            <a href="#" data-toggle="modal" class="btn btn-success" data-car_id="{{ r.id }}"
                               data-id="{{ r.id }}" data-target=".epts_info_modal"
                               id="displayEPTSInfo">
                                <div style="width: 60px"><i class="fa fa-eye"></i> ЭПТС</div>
                            </a>
                        {% endif %}
                    </div>
                </td>
                <td class="v-align-middle">
                    <b>{{ r.vin }}</b><br>
                    {% if (r.status and r.status == 'CANCELLED') %}
                        <b style="color:red">ДПП аннулирован!</b>
                        {% if auth is defined and auth.isSuperModerator() %}
                            <a href="#" data-toggle="modal" class="btn btn-outline-success btn-sm"
                               data-id="{{ r.id }}" data-target=".restore_annulled_car_form_modal"
                               id="restoreAnnulledCar">
                                <i class="fa fa-undo"></i> Восстановить СВУП?
                            </a>
                        {% endif %}
                    {% endif %}
                </td>
                <td class="v-align-middle">{{ r.category }} <br> ({{ r.st_type }})</td>
                <td class="v-align-middle">{{ r.volume }}</td>
                <td class="v-align-middle">{{ r.year }}</td>
                <td class="v-align-middle">{{ r.date_import }}</td>
                <td class="v-align-middle">{{ r.country }}</td>
                <td>
                    <b>{{ r.cost }}</b>
                    {% if r.calculate_method != null %}
                        {% set _date = '' %}
                        {% if r.calculate_method == 0 %}
                            {% set _date = r.date_import %}
                        {% elseif r.calculate_method == 1 %}
                            {% set _date = data.sent_dt  ? data.sent_dt : '' %}
                        {% else %}
                            {% set _date = r.date_import ? r.date_import : '' %}
                        {% endif %}
                        <br>
                        <span class="badge badge-warning mb-2">Способ расчета: <br>
                            {{ constant('CALCULATE_METHODS')[r.calculate_method] }} ({{ _date }})
                        </span>
                    {% endif %}
                </td>
            </tr>
        {% endfor %}
    {% endif %}
    </tbody>
</table>



