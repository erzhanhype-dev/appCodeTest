<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">Статусы</div>
            <div class="card-body">
                {% set status = data['blocked'] == 1 ? 'status-blocked' : (data['blocked'] == 0 ? 'status-unblocked' : 'status-new') %}
                {% set paid = data['status']  == 'PAID' ? 'paid-true' : 'paid-false' %}
                {% set approve = {
                    'REVIEW': 'approve-review',
                    'NEUTRAL': 'approve-neutral',
                    'DECLINED': 'approve-declined',
                    'APPROVE': 'approve-approve',
                    'CERT_FORMATION': 'approve-cert-formation',
                    'GLOBAL': 'approve-global'
                }[data['approve']] | default('approve-not-set') %}

                <div class="row">
                    <div class="col">
                        <div class="row">
                            <div class="col"><strong>Статус оплаты</strong></div>
                            <div class="col" id="profileStatusOnModeratorOrderView">
                                {% if data['amount'] == 0 or data['amount'] == '0,00' %}
                                    {{ t._("no_payment_required") }}
                                {% else %}
                                    {{ t._(paid) }}
                                {% endif %}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col"><strong>Статус заявки</strong></div>
                            <div class="col" id="profileApproveOnModeratorOrderView">
                                {{ t._(data['approve']) }}
                            </div>
                        </div>
                        <div class="row mt-5">
                            <div class="col">
                                {% if auth is defined and (auth.isAdmin() or auth.isModerator() or auth.isSuperModerator()) %}
                                    {% if data['approve'] == 'REVIEW' and data.permissions.can_approve %}
                                        {% if data['calculate_method'] == 2 %}
                                            <a data-toggle="modal" data-target="#confirmAccept"
                                               class="btn btn-sm btn-success text-light">
                                                {{ t._("Выдать счет") }}
                                            </a>
                                        {% else %}
                                            <a href="/moderator_order/accept/{{ data['id'] }}/approved"
                                               class="btn btn-sm btn-success">
                                                {{ t._("Выдать счет") }}
                                            </a>
                                        {% endif %}
                                    {% endif %}

                                    <a href="/pay/print/{{ data['id'] }}" class="btn btn-sm btn-secondary mr-4">
                                        {{ t._("Скачать счет") }}
                                    </a>

                                    {% if data['approve'] == 'APPROVE' %}
                                        <a href="/moderator_order/accept/{{ data['id'] }}/cert_formation"
                                           class="btn btn-sm btn-primary">
                                            {{ t._("Выдать ДПП") }}
                                        </a>
                                    {% endif %}
                                {% endif %}

                                {% if auth.isAdmin() %}
                                    <a href="/moderator_order/accept/{{ data['id'] }}/neutral"
                                       class="btn btn-sm btn-secondary">
                                        {{ t._("neutral") }}
                                    </a>
                                {% endif %}

                                {% if auth.isAdminSoft() %}
                                    <a href="#" data-toggle="modal" class="btn btn-sm btn-primary"
                                       data-id="{{ data['id'] }}"
                                       data-target=".change_profile_status_modal" id="displayProfileStatusInfo">
                                        <i class="fa fa-edit"></i>
                                        {{ t._("Сменить статус") }}
                                    </a>
                                    <a href="#" data-toggle="modal" class="btn btn-sm btn-warning" data-id="{{ data['id'] }}"
                                       data-target=".change_profile_calc_modal" id="displayProfileCalcInfo">
                                        <i class="fa fa-edit"></i>
                                        {{ t._("Способ расчета") }}
                                    </a>
                                {% endif %}
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <!-- отклонение с сообщением -->
                        {% if auth is defined and (auth.isAdmin() or auth.isModerator() or auth.isSuperModerator()) %}
                            {% include "moderator_order/view/_form_decline.volt" %}
                        {% endif %}
                        <!-- /отклонение с сообщением -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
