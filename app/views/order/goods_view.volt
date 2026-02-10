<div class="row">
    <div class="col-4">
        <h2>{{ t._("goods-list") }}</h2>
    </div>
    <div class="col-2">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-6">
        <div class="float-right">
            {% if auth is defined and auth.isClient() %}
                {% if profile['blocked'] == false %}
                    <a href="/goods/new/{{ profile['id'] }}" class="btn btn-success btn-lg">
                        <i data-feather="plus" width="20" height="14"></i>
                        {{ t._("Добавить товар (упаковку)") }}
                    </a>
                    <a href="/goods/import/{{ profile['id'] }}" class="btn btn-success btn-lg">
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
                {{ t._('goods-in-application') }}{{ tr['profile_id'] }}
                <span class="badge badge-warning mb-2" style="font-size:14px;">
                    {{ t._('Тип плательщика:') }} {{ t._(profile['agent_status']) }}
                </span>
            </div>

            <div class="card-body" id="ORDER_GOODS_VIEW_FORM">
                <table id="viewGoodsList" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>{{ t._('operations') }}</th>
                        <th>{{ t._('num-symbol') }}</th>
                        <th>{{ t._('tn-code') }}</th>
                        <th>{{ t._('basis-good') }}</th>
                        <th>{{ t._('basis-date') }}</th>
                        <th>{{ t._('goods-weight') }}</th>
                        <th>{{ t._('goods-cost') }}</th>
                        <th>{{ t._('date-of-import') }}</th>
                        <th>{{ t._('package-weight') }}</th>
                        <th>{{ t._('package-cost') }}</th>
                        <th>{{ t._('total-amount') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if goods is defined and goods|length %}
                        {% for r in goods %}
                            <tr>
                                <td style="width: 88px;">
                                    {% if profile['blocked'] != 1 and (tr['approve'] == 'DECLINED' or tr['approve'] == 'NEUTRAL') %}
                                        {% if auth is defined and auth.isClient() %}
                                            <a href="/goods/edit/{{ r['id'] }}" class="btn btn-primary">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="/goods/delete/{{ r['id'] }}" class="btn btn-danger">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        {% endif %}
                                    {% elseif tr['approve'] == 'GLOBAL' and tr['ac_approve'] == 'SIGNED' %}
                                        {% set btn_active = '' %}
                                        {% if tr['dt_approve'] < 1642528800 %}
                                            {% set btn_active = 'disabled' %}
                                        {% endif %}

                                        <div class="btn-group">
                                            <a href="/goods/correction/{{ r['id'] }}" class="btn btn-primary">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-warning dropdown-toggle" type="button"
                                                        id="svupDropDownMenuButton"
                                                        data-toggle="dropdown" aria-haspopup="true"
                                                        aria-expanded="false">
                                                    <i class="fa fa-download"></i> Сертификат
                                                </button>
                                                <div class="dropdown-menu" aria-labelledby="svupDropDownMenuButton">
                                                    <a class="dropdown-item {{ btn_active }}"
                                                       href="/main/certificate_kz/{{ tr['id'] }}/{{ r['id'] }}">
                                                        <i class="fa fa-download"></i> Қазақ тілінде жүктеу
                                                    </a>
                                                    <a class="dropdown-item"
                                                       href="/main/certificate/{{ tr['id'] }}/{{ r['id'] }}">
                                                        <i class="fa fa-download"></i> Скачать на русском языке
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    {% else %}
                                        —
                                    {% endif %}
                                </td>
                                <td>{{ r['id'] }}</td>
                                <td>{{ r['tn_code'] }}</td>
                                <td>{{ r['basis'] }}</td>
                                <td>{{ r['basis_date'] }}</td>
                                <td>{{ r['weight'] }}</td>
                                <td>{{ r['goods_cost'] }} </td>
                                <td>{{ r['date_import'] }}</td>
                                <td>{{ r['package_weight'] }}</td>
                                <td>{{ r['package_cost'] }}</td>
                                <td>{{ r['amount'] }}</td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>

                <hr>

                {% if profile['created'] > constant('ROP_ESIGN_DATE') %}
                    {% if tr['ac_approve'] == 'SIGNED' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display:none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    {{ 'Сгенерировать архив' }}
                                </button>
                            </div>
                            <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
                        </div>
                    {% endif %}
                {% else %}
                    {% if tr['approve'] == 'GLOBAL' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display:none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    {{ 'Сгенерировать архив' }}
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
