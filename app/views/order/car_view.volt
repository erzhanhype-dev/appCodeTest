<div class="row">
    <div class="col-4">
        <h2>{{ t._("cars-list") }}</h2>
    </div>
    <div class="col-2">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-6">
        <div class="float-right addCarButtons">
            {% if auth is defined and auth.isClient() %}
                {% if profile['blocked'] == false %}
                    <a href="/car/check_epts/{{ profile['id'] }}?m=CAR" class="btn btn-success btn-lg">
                        <i data-feather="plus" width="20" height="14"></i>
                        {{ t._("Добавить автомобиль") }}
                    </a>
                    <a href="/car/check_epts/{{ profile['id'] }}?m=TRAC" class="btn btn-success btn-lg">
                        <i data-feather="plus" width="20" height="14"></i>
                        {{ t._("Добавить с/х-технику") }}
                    </a>
                    <a href="/order/import/{{ profile['id'] }}" class="btn btn-success btn-lg">
                        <i data-feather="download" width="20" height="14"></i>
                        {{ t._("Импорт") }}
                    </a>
                {% endif %}
            {% endif %}
        </div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("cars-in-application") }}{{ profile['id'] }}
                <span class="badge badge-warning mb-2" style="font-size: 14px;">
            Тип плательщика: {{ t._(profile['agent_status']) }}
          </span>
                <input type="hidden" value="{{ profile['id'] }}" id="carViewProfileId">
            </div>
            <div class="card-body" id="ORDER_CAR_VIEW_FORM">
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
                                        {% if profile['blocked'] != 1 and (tr['approve'] == 'DECLINED' or tr['approve'] == 'NEUTRAL') %}

                                            {% if auth is defined and auth.isClient() %}
                                                <a href="{{ url('/car/edit/' ~ r['id']) }}" class="btn btn-secondary"
                                                   title="{{ 'Редактировать автомобиль' }}">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <a href="{{ url('/car/delete/' ~ r['id']) }}" class="btn btn-danger"
                                                   title="{{ 'Удалить автомобиль' }}"
                                                   data-confirm='Вы уверены, что хотите удалить этот автомобиль?'>
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            {% endif %}
                                        {% elseif (tr['approve'] == 'GLOBAL' and tr['ac_approve'] == 'SIGNED') %}
                                            <button class="btn btn-warning dropdown-toggle" type="button"
                                                    id="svupDropDownMenuButton" data-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                <i class="fa fa-download"></i> Сертификат
                                            </button>

                                            <a href="/car/correction/{{ r['id'] }}" class="btn btn-primary">
                                                <i class="fa fa-edit"></i>
                                            </a>

                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item"
                                                   href="/main/certificate_kz/{{ profile['id'] }}/{{ r['id'] }}">
                                                    <i class="fa fa-download"></i> Қазақ тілінде жүктеу
                                                </a>
                                                <a class="dropdown-item"
                                                   href="/main/certificate/{{ profile['id'] }}/{{ r['id'] }}">
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

                <hr>

                {% if profile['created'] > constant("ROP_ESIGN_DATE") %}
                    {% if tr['ac_approve'] == 'SIGNED' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display: none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    Сгенерировать архив
                                </button>
                            </div>
                            <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
                        </div>
                    {% endif %}
                {% else %}
                    {% if tr['approve'] == 'GLOBAL' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display: none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    Сгенерировать архив
                                </button>
                            </div>
                            <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
                        </div>
                    {% endif %}
                {% endif %}
            </div>
        </div>
    </div>
</div>
