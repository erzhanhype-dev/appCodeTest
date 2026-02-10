<input type="hidden" value="{{ data['id'] }}" id="pid">
<input type="hidden" value="{{ data['executor']['status_code'] }}" id="order_status_update">

<h2 class="mt-2">{{ t._("Заявка") }} #{{ data['id'] }}</h2>

<div class="row">
    <div class="col col-lg-4 col-md-12 col-sm-12 col-xs-12">
        {% include 'moderator_order/view/executor.volt' %}
    </div>
    <div class="col col-lg-8 col-md-12 col-sm-12 col-xs-12">
        {% include 'moderator_order/view/client.volt' %}
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Содержимое заявки") }}</div>
            <div class="card-body">
                <div id="MODERATOR_ORDER_CAR_VIEW_FORM">
                    {% if data['type'] === 'CAR' %}
                        {% include 'moderator_order/view/car.volt' %}
                    {% endif %}
                </div>

                <div id="MODERATOR_ORDER_GOODS_VIEW_FORM">
                    {% if data['type'] === 'GOODS' %}
                        {% include 'moderator_order/view/goods.volt' %}
                    {% endif %}
                </div>

                <div id="MODERATOR_ORDER_KPP_VIEW_FORM">
                    {% if data['type'] === 'KPP' %}
                        {% include 'moderator_order/view/kpp.volt' %}
                    {% endif %}
                </div>
                {% if data.created_dt > constant("ROP_ESIGN_DATE") %}
                    {% if data.ac_approve == 'SIGNED' %}
                        <hr>
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
                    {% if data.approve == 'GLOBAL' %}
                        <hr>
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


{% include 'moderator_order/view/status.volt' %}

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("documents-for-application") }}{{ data['id'] }}</span></div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        {% include 'moderator_order/view/docs.volt' %}
                    </div>
                    <div class="col">
                        {% include "moderator_order/view/_form_upload_file.volt" %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{% include 'moderator_order/view/payments.volt' %}

{% include 'moderator_order/view/logs.volt' %}

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

<!-- change_profile_status_modal -->
<div class="modal fade change_profile_status_modal" tabindex="-1" role="dialog"
     aria-labelledby="displayProfileStatusInfo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ t._("Заявка") }} #{{ data['id'] }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {% include "moderator_order/view/_form_change_status.volt" %}
        </div>
    </div>
</div>
<!-- /change_profile_status_modal -->

<!-- confirm_accept_modal -->
<div class="modal fade" id="confirmAccept" tabindex="-1" aria-labelledby="confirmAccept" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Подтверждение</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Вы уверены, что осуществили проверку с предоставленными данными с КАП МВД РК?
            </div>
            <div class="modal-footer">
                <a href="/moderator_order/accept/{{ data['id'] }}/approved" class="btn btn-primary">Да, подтверждаю</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Вернутся к рассмотрению заявки
                </button>
            </div>
        </div>
    </div>
</div>
<!-- /confirm_accept_modal -->

<!-- change_initiator_modal -->
<div class="modal fade" id="selectInitiatorModal" tabindex="-1" role="dialog"
     aria-labelledby="selectInitiatorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {% include "moderator_order/view/_form_initiator.volt" %}
        </div>
    </div>
</div>
<!-- /change_initiator_modal -->

<!-- change_profile_calc_modal -->
<div class="modal fade change_profile_calc_modal" tabindex="-1" role="dialog"
     aria-labelledby="displayProfileCalcInfo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ t._("Заявка") }} #{{ data['id'] }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card-body">
                {% include "moderator_order/view/_form_calculate_method.volt" %}
            </div>
        </div>
    </div>
</div>
<!-- /change_profile_calc_modal -->
