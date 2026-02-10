<input type="hidden" value="{{ data['id'] }}" id="pid">
<input type="hidden" value="{{ data['executor']['status_code'] }}" id="order_status_update">

<h2 class="mt-2">{{ t._("Заявка") }} #{{ data['id'] }}</h2>

<div class="row">
    <div class="col col-lg-4 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="card-header bg-dark text-light">
                {{ t._("Клиент") }}
            </div>
            <div class="card-body" style="max-width: 80%">
                <div class="row">
                    <div class="col"><strong>{{ t._("Наименование, ФИО") }}</strong></div>
                    <div class="col">{{ data['user']['title'] | upper }}</div>
                </div>
                <div class="row">
                    <div class="col"><strong>{{ t._("Логин") }}</strong></div>
                    <div class="col">{{ data['user']['idnum'] }}</div>
                </div>
                <div class="row">
                    <div class="col"><strong>{{ t._("Почта") }}</strong></div>
                    <div class="col">{{ data['user']['email'] }}</div>
                </div>
                <div class="row">
                    <div class="col"><strong>{{ t._("Тип клиента") }}</strong></div>
                    <div class="col">
                        {% if data['user']['user_type_id'] == constant('PERSON') %}{{ t._("person") }}{% endif %}
                        {% if data['user']['user_type_id'] == constant('COMPANY') %}{{ t._("company") }}{% endif %}
                    </div>
                </div>
                {% if data['user']['phone'] %}
                    <div class="row">
                        <div class="col"><strong>{{ t._("Контактный телефон") }}</strong></div>
                        <div class="col">
                            {{ data['user']['phone'] }}
                        </div>
                    </div>
                {% endif %}
            </div>
        </div>
    </div>
    <div class="col col-lg-8 col-md-12 col-sm-12 col-xs-12">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("Сведения") }}
            </div>
            <div class="card-body" style="max-width: 80%">
                <div class="row">
                    <div class="col"><strong>{{ t._("Дата создания") }}</strong></div>
                    <div class="col">{{ data['created_dt'] }}</div>
                </div>
                {% if data['sent_dt'] %}
                    <div class="row">
                        <div class="col"><strong>{{ t._("Дата отправки модератору") }}</strong></div>
                        <div class="col">{{ data['sent_dt'] }}</div>
                    </div>
                {% endif %}

                <div class="row">
                    <div class="col"><strong>{{ t._("Сумма") }}</strong></div>
                    <div class="col">{{ data['amount'] }} тенге
                    </div>
                </div>
                <div class="row">
                    <div class="col"><strong>{{ t._("Агентская заявка") }}</strong></div>
                    <div class="col">
                        {% if data['agent_name'] %}
                            {{ data['agent_name'] }} /
                            {{ data['agent_iin'] }} /
                            {{ data['agent_city'] }} /
                            {{ data['agent_phone'] }}
                        {% else %}
                            нет
                        {% endif %}
                    </div>
                </div>
                {% if data['moderator'] %}
                    <div class="row">
                        <div class="col"><strong>Создано супермодератором</strong></div>
                        <div class="col">{{ data['moderator'] ? data['moderator']['fio'] : '' }}
                            ({{ data['moderator']['idnum'] }})
                        </div>
                    </div>
                {% endif %}
                <div class="row">
                    <div class="col"><strong>Инициатор</strong></div>
                    <div class="col">
                        {% if data['initiator'] %}
                            {{ data['initiator']['name'] }}
                        {% endif %}
                        {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
                            <i style="cursor:pointer;" data-feather="edit" width="14" height="14"
                               data-toggle="modal" data-target="#selectInitiatorModal"></i>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Содержимое заявки") }}</div>
            <div class="card-body" id="OPERATOR_ORDER_CAR_VIEW_FORM">
                {% if data['type'] === 'CAR' %}
                    {% include 'operator_order/view/car.volt' %}
                {% endif %}
            </div>
            <div class="card-body" id="OPERATOR_ORDER_GOODS_VIEW_FORM">
                {% if data['type'] === 'GOODS' %}
                    {% include 'operator_order/view/goods.volt' %}
                {% endif %}
            </div>
            <div class="card-body" id="OPERATOR_ORDER_KPP_VIEW_FORM">
                {% if data['type'] === 'KPP' %}
                    {% include 'operator_order/view/kpp.volt' %}
                {% endif %}
            </div>
        </div>
    </div>
</div>

{% if data['type'] === 'CAR' %}
    {% if data.cancelled_cars is defined and data.cancelled_cars|length %}
        <div class="row">
            <div class="col">
                <div class="card mt-3">
                    <div class="card-header bg-danger text-light">{{ t._("Отклоненные ТС") }}</div>
                    <div class="card-body">
                        <table id="moderatorOrderCancelledCarList" class="display" cellspacing="0" width="100%">
                            <thead>
                            <tr>
                                <th>{{ t._("num-symbol") }}</th>
                                <th>{{ t._("car-category") }}</th>
                                <th>{{ t._("volume-weight") }}</th>
                                <th>{{ t._("vin-code") }}</th>
                                <th>{{ t._("year-of-manufacture") }}</th>
                                <th>{{ t._("date-of-import") }}</th>
                                <th>{{ t._("country-of-manufacture") }}</th>
                                <th>{{ t._("Статус") }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {% for c in data.cancelled_cars %}
                                <tr>
                                    <td>{{ c.id }}</td>
                                    <td>{{ t._(c.car_cat) }}</td>
                                    <td>{{ c.volume }}</td>
                                    <td>{{ c.vin }}</td>
                                    <td>{{ c.year }}</td>
                                    <td>{{ c.date_import }}</td>
                                    <td>{{ c.country }}</td>
                                    <td>{{ c.action ? t._(c.action) : '' }}</td>
                                </tr>
                            {% endfor %}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    {% endif %}
{% endif %}


{% include 'operator_order/view/status.volt' %}

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("documents-for-application") }}{{ data['id'] }}</span></div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        {% include 'operator_order/view/docs.volt' %}
                    </div>
                    <div class="col">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{% include 'operator_order/view/payments.volt' %}

{% include 'operator_order/view/logs.volt' %}


<!-- car_info_modal -->
<div class="modal fade car_info_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Данные ТС</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">
                <table class="table table-bordered" id="car_info_table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Параметр</th>
                        <th>Показатель</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- /car_info_modal -->