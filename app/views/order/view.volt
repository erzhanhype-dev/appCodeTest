<input type="hidden" value="{{ profile['id'] }}" id="pid">

{% include 'order/timeline.volt' %}

{% if profile['type'] == 'CAR' %}
    {% include 'order/car_view.volt' %}
{% elseif profile['type'] == 'GOODS' %}
    {% include 'order/goods_view.volt' %}
{% elseif profile['type'] == 'KPP' %}
    {% include 'order/kpp_view.volt' %}
{% endif %}

{% include 'order/file_view.volt' %}

{% if can_send %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("agent-pay") }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <p>Как только заявка заполнена, все необходимые документы приложены, заявление сформировано
                                и подписано - вы можете отправить заявку на рассмотрение менеджеру. Помните, что счет на
                                оплату вы получите только после одобрения заявки менеджером.</p>
                            {% if tr['approve'] == 'NEUTRAL' or tr['approve'] == 'DECLINED' %}
                                <form action="/order/review/" method="POST" autocomplete="off">
                                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                    <input type="hidden" name="profile_id" value="{{ profile['id'] }}">
                                    <button type="submit" class="btn btn-success"><i
                                                data-feather="user"></i> {{ t._("Отправить менеджеру") }}</button>
                                </form>
                            {% endif %}
                            {% if tr['approve'] == 'APPROVE' %}
                                <a href="/pay/invoice/{{ profile['id'] }}" title="{{ t._("invoice") }}">
                                    <button type="button" name="b_view" class="btn btn-success"><i
                                                data-feather="credit-card"></i> Скачать счет на оплату
                                    </button>
                                </a>
                            {% endif %}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}

<!-- order_sign_modal -->
<div class="modal fade order_sign_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Уведомление об ответственности</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="font-family: 'Montserrat'; font-size: 14px;">
                <p class="text-justify">
                    &nbsp;&nbsp; Согласно подпункту 1) пункта 14 Правил реализации расширенных обязательств
                    производителей (импортеров),
                    утверждённых постановлением Правительства Республики Казахстан от 25 октября 2021 года № 763
                    (далее-Правила),
                    производитель (импортер) гарантирует подлинность документов, прикладываемых к заявке в соответствии
                    с Правилами,
                    и достоверность указываемых в них сведений. В случае указания неполных или недостоверных данных в
                    заявке, и (или)
                    в документах, прилагаемых к такой заявке, я несу ответственность, предусмотренную гражданским и
                    уголовным законодательством
                    Республики Казахстан
                </p>
                <br>
                <label class="font-weight-light">
                    <input type="checkbox" id="orderSignCheckbox"> Ознакомлен и согласен с данным пунктом правил
                    <small class="form-text text-muted ml-3">
                        * Поставьте галочку чтобы подписать заявление.
                    </small>
                </label>
            </div>
            <div class="modal-footer">
                <div id="clientOrderSignForm" style="display: none;">
                    <form id="formSign" action="/order/sign" method="POST" autocomplete="off">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                        <input type="hidden" name="profileId" id="orderId" value="{{ profile['id'] }}">
                        <input type="hidden" name="profileHash" id="profileHash" value="{{ sign_data }}">
                        <textarea name="profileSign" id="profileSign" style="display: none;"></textarea>
                        <button type="button" class="btn btn-primary signApplicationBtn" data-dismiss="modal" disabled>
                            Подписать с ЭЦП
                        </button>
                    </form>
                </div>
                <button type="button" class="btn btn-danger" data-dismiss="modal">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>
<!-- /order_sign_modal -->
