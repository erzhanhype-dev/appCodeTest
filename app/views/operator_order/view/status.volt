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
                                {% if auth.isOperator() %}
                                    {% if data['approve'] == 'REVIEW' and data.permissions.can_approve %}
                                    <a href="/pay/print/{{ data['id'] }}" class="btn btn-sm btn-secondary mr-4">
                                        {{ t._("Скачать счет") }}
                                    </a>
                                {% endif %}
                                {% endif %}
                            </div>
                        </div>
                    </div>
                    <div class="col">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
