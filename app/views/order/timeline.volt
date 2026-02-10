{% if tr is defined and tr['approve'] == 'DECLINED' %} {% set step_status = 0 %} {% endif %}
{% if tr is defined and tr['approve'] == 'NEUTRAL' %} {% set step_status = 1 %} {% endif %}
{% if tr is defined and tr['approve'] == 'REVIEW' %} {% set step_status = 2 %} {% endif %}
{% if tr is defined and tr['approve'] == 'APPROVE' %} {% set step_status = 3 %} {% endif %}
{% if tr is defined and tr['status'] == 'PAID' %} {% set step_status = 4 %} {% endif %}
{% if tr is defined and tr['approve'] == 'CERT_FORMATION' %} {% set step_status = 5 %} {% endif %}
{% if tr is defined and tr['approve'] == 'GLOBAL' %} {% set step_status = 6 %} {% endif %}

<div class="timeline mb-2">
    <div class="order_timeline_wrapper">
        <h6 class="mb-2">Статус заявки</h6>
        <hr>

        <div class="order_timeline_card">

            <div class="timeline_item {% if (step_status is defined and (step_status == 1 or step_status == 0)) %} active {% endif %} {% if (step_status is defined and step_status > 1) %} check {% endif %}">
                <div class="timeline_content">
                    {% if step_status is defined and step_status == 1 %}
                        <div class="current-status"><i class="fa fa-arrow-down fa-2x text-secondary"></i></div>
                    {% endif %}
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa {% if step_status is defined and step_status > 1 %} fa-check {% else %} fa-pencil-alt {% endif %} fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Создание заявки</div>
                    </div>
                </div>
            </div>

            <div class="timeline_item {% if step_status is defined and step_status == 2 %} active {% endif %} {% if step_status is defined and step_status > 2 %} check {% endif %}">
                <div class="timeline_content">
                    {% if step_status is defined and step_status == 2 %}
                        <div class="current-status"><i class="fa fa-arrow-down fa-2x text-secondary"></i></div>
                    {% endif %}
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa {% if step_status is defined and step_status > 2 %} fa-check {% else %} fa-eye {% endif %} fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Рассмотрение</div>
                        <div class="description">
                            <div class="info">
                                3 рабочих дня согласно пункту 14 ППРК № 763
                                <button style="border:none" type="button" data-toggle="tooltip" data-placement="top"
                                        title="Пункт 14 Постановления Правительства Республики Казахстан от 25 октября 2021 года № 763">
                                    <i class="fa fa-info"></i>
                                </button>
                                <br>
                                {% if tr is defined and tr['md_dt_sent'] %}
                                    {% if tr['approve'] == 'DECLINED' %}
                                        <span style="color: indianred">Отклонено</span>
                                    {% endif %}
                                    {% if tr['approve'] == 'REVIEW' %}
                                        <span style="color: darkblue">{{ remainingReview|default('') }}</span>
                                    {% endif %}
                                {% endif %}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="timeline_item {% if step_status is defined and step_status == 3 %} active {% endif %} {% if step_status is defined and step_status > 3 %} check {% endif %}">
                <div class="timeline_content">
                    {% if step_status is defined and step_status == 3 %}
                        <div class="current-status"><i class="fa fa-arrow-down fa-2x text-secondary"></i></div>
                    {% endif %}
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa {% if step_status is defined and step_status > 3 %} fa-check {% else %} fa-file-invoice-dollar {% endif %} fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Выдан счет</div>
                        {% if tr is defined and (tr['approve'] in ['GLOBAL', 'APPROVE', 'CERT_FORMATION']) %}
                            {% if profile is defined and profile['id'] %}
                                <a href="/pay/invoice/{{ profile['id'] }}" class="btn btn-primary">
                                    <i class="fa fa-download"></i>
                                    {{ t._("Скачать счет") }}
                                </a>
                                <br><br>
                            {% endif %}
                        {% endif %}
                        <div class="description">
                            <div class="info">
                                Необходимо произвести оплату по выставленному счету
                                <b>
                                    с указанием <font class="text-primary">номера</font> и <br>
                                    <font class="text-primary">даты заявки</font>
                                    в назначении платежа
                                </b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="timeline_item {% if step_status is defined and step_status == 4 %} active {% endif %} {% if step_status is defined and step_status > 4 %} check {% endif %}">
                <div class="timeline_content">
                    {% if step_status is defined and step_status == 4 %}
                        <div class="current-status"><i class="fa fa-arrow-down fa-2x text-secondary"></i></div>
                    {% endif %}
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa {% if step_status is defined and step_status > 4 %} fa-check {% else %} fa-clock {% endif %} fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Ожидание поступления оплаты</div>
                        <div class="description">
                            <div class="info">
                                В среднем, поступление денежных средств на расчётный счёт АО «Жасыл даму» осуществляется
                                в течение одного рабочего дня.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="timeline_item {% if step_status is defined and step_status == 5 %} active {% endif %} {% if step_status is defined and step_status > 5 %} check {% endif %}">
                <div class="timeline_content">
                    {% if tr is defined and tr['approve'] == 'CERT_FORMATION' %}
                        <div class="current-status"><i class="fa fa-arrow-down fa-2x text-secondary"></i></div>
                    {% endif %}
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa {% if step_status is defined and step_status > 5 %} fa-check {% else %} fa-file {% endif %} fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Формирование сертификата</div>
                        <div class="description">
                            <div class="info">
                                3 рабочих дня с момента поступления денежных средств на расчетный счет АО «Жасыл даму»,
                                согласно пункту 14 ППРК 763
                                <button style="border:none" type="button" data-toggle="tooltip" data-placement="bottom"
                                        title="Пункт 14 Постановления Правительства Республики Казахстан от 25 октября 2021 года № 763">
                                    <i class="fa fa-info"></i>
                                </button>
                                <br>
                                {% if tr is defined and tr['approve'] == 'CERT_FORMATION' %}
                                    <span style="color: darkblue">{{ remainingCert|default('') }}</span>
                                {% endif %}

                                {% if p_logs is defined %}
                                    {% for log in p_logs %}
                                        {% if log['action'] == 'CERT_FORMATION' %}
                                            <div class="log-item">

                                                {% if log['dt'] %}<small>{{ date('d.m.Y', log['dt']) }}</small>{% endif %}
                                            </div>
                                        {% endif %}
                                    {% endfor %}
                                {% endif %}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="timeline_item {% if step_status is defined and step_status == 6 %} active {% endif %} {% if step_status is defined and step_status > 6 %} check {% endif %}">
                <div class="timeline_content">
                    <div class="line"></div>
                    <div class="icon">
                        <i class="fa fa-check-square fa-2x text-secondary"></i>
                    </div>
                    <div class="data">
                        <div class="title">Сертификат выдан</div>
                        {% if tr is defined and tr['approve'] == 'GLOBAL' %}
                            <div class="description">
                                <div class="info">
                                    <a id="btnCertJump" style="cursor: pointer;color: darkblue">
                                        Можете скачать сертификат из таблицы ниже
                                        <i class="fa fa-download text-secondary"></i>
                                    </a>
                                    {% if p_logs is defined %}
                                        {% for log in p_logs %}
                                            {% if log['action'] == 'GLOBAL' %}
                                                <hr>
                                                {{ date('d.m.Y', log['dt']) }}
                                            {% endif %}
                                        {% endfor %}
                                    {% endif %}
                                </div>
                            </div>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
