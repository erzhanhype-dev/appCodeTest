<!-- клиент -->
<div class="col col-lg-6 col-md-12 col-sm-12 col-xs-12">
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
<div class="col col-lg-6 col-md-12 col-sm-12 col-xs-12">
    <div class="card">
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
