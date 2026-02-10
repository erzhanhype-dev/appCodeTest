</div>
<!-- калькулятор -->
<div class="modal fade" tabindex="-1" role="dialog" aria-labelledby="rcalcModal" id="rcalcModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t._("test-calc") }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <!-- выбор -->
                <div class="rcalc-choice">
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label id="bavto" class="btn btn-wide btn-success active" style="width:34%;">
                            <input type="radio" name="options" checked>{{ t._("avto-and-tech") }}
                        </label>
                        <label id="bcomp" class="btn btn-wide btn-success" style="width:33%;">
                            <input type="radio" name="options">{{ t._("oils-and-comp") }}
                        </label>
                        <label id="bgoods" class="btn btn-wide btn-success" style="width:33%;">
                            <input type="radio" name="options">{{ t._("goods-and-tov") }}
                        </label>
                        {# <label id="bkpp" class="btn btn-wide btn-success" style="width:33%;">
		            <input type="radio" name="options">{{ t._("kpp") }}
		          </label> #}
                    </div>
                </div>
                <!-- /выбор -->

                <!-- ТС -->
                <div id="avto">
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="avto_cat">{{ t._("car-category") }}</label>
                                <select name="avto_cat" id="avto_cat" class="form-control"></select>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="avto_cat_type_block">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label for="avto_cat_type">Грузовой/Легковой</label>
                                <select name="avto_cat_type" id="avto_cat_type" class="form-control" required>
                                    <option value="passenger">Легковой</option>
                                    <option value="cargo">Грузовой</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="ts-st">
                        <div class="col">
                            <div class="form-group">
                                <label for="avto_cat">{{ t._("st-type") }}</label>
                                <select name="avto_ts" id="avto_ts" class="form-control"></select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label id="avto_volume_label" for="avto_volume">{{ t._("volume-cm") }}</label>
                                <input name="avto_volume" id="avto_volume" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <button type="button" id="avto_calc"
                                    class="btn btn-success">{{ t._("calc-by-car") }}</button>
                        </div>
                    </div>
                    <div id="avto_result"></div>
                </div>
                <!-- /ТС -->

                <!-- компоненты -->
                <div id="comp">
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="comp_cat">{{ t._("cat-comp") }}</label>
                                <select name="comp_cat" id="comp_cat" class="form-control"></select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="comp_volume">{{ t._("weight-kg") }}</label>
                                <input name="comp_volume" id="comp_volume" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <button type="button" id="comp_calc"
                                    class="btn btn-success">{{ t._("calc-by-comp") }}</button>
                        </div>
                    </div>
                    <div id="comp_result"></div>
                </div>
                <!-- /компоненты -->

                <!-- товар и упаковка -->
                <div id="goods">
                    <div id="goods_by_weight">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="up_cat">{{ t._("type-up") }}</label>
                                    <select name="up_cat" id="up_cat" class="form-control"></select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="up_volume">{{ t._("weight-kg") }}</label>
                                    <input name="up_volume" id="up_volume" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <button type="button" id="up_calc"
                                        class="btn btn-success">{{ t._("calc-by-weight") }}</button>
                            </div>
                        </div>
                        <div id="up_result"></div>
                    </div>
                </div>
                <!-- /товар и упаковка -->

                <!-- КПП -->
                <div id="kpp" class="mt-1">
                    <div id="kpp_by_invoice_sum">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="kpp_invoice_sum">{{ t._("kpp-invoice-sum") }}</label>
                                    <input type="text" name="kpp_invoice_sum" id="kpp_invoice_sum"
                                           class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <button type="button" id="kpp_calc"
                                        class="btn btn-success">{{ t._("calc-by-invoice-sum") }}</button>
                            </div>
                        </div>
                        <div id="kpp_result"></div>
                    </div>
                </div>
                <!-- /КПП -->

            </div>
        </div>
    </div>
</div>

<div id="btn-rcalc" data-toggle="tooltip" data-placement="left" title="{{ t._("test-calc") }}">
    <i data-feather="trello"></i>
</div>
<!-- /калькулятор -->

{% if auth is defined and (auth.isEmployee() or auth.isOperator()) %}
    <!-- modal window -->
    {% include "partials/info_modal.volt" %}
    <!-- /modal window -->
{% endif %}
<!-- уведомления -->
{% include "partials/notifications.volt" %}
{% include "partials/js_notifications.volt" %}
<!-- /уведомления -->

<div class="modal fade erros-modal-lg" tabindex="-1" role="dialog" aria-labelledby="errorsModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorsModalLabel">Ошибки</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body" style="text-align: justify;">
                <h6></h6>
                <p>{{ session.get('errors') ? session.get('errors'): '' }}</p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="/assets/vendor/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="/assets/vendor/jquery-ui.min.js"></script>
<script type="text/javascript" src="/assets/vendor/popper.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/bootstrap/js/bootstrap.min.js?v=3.0.1"></script>

<script type="text/javascript" src="/assets/vendor/bootstrap-datepicker.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/feather.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/jquery.fancybox.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/bootstrap-select.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/jquery.dataTables.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/defaults-ru_RU.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/enjoyhint/enjoyhint.min.js?v=3.0.1"></script>
<script type="text/javascript" src="/assets/vendor/jquery.blockui.min.js?v=3.0.1" charset="utf-8"></script>
<script type="text/javascript" src="/assets/vendor/ncalayer-client.js?v=3.0.1" charset="utf-8"></script>
<script type="text/javascript" src="/assets/js/ncalayer-client-process.js?v=3.0.1" charset="utf-8"></script>

<script src="/assets/js/constants.js?v=3.0.1" type="text/javascript"></script>
<script src="/assets/js/rcalculator.js?v=3.0.1" type="text/javascript"></script>

{% if auth is defined %}
    {% if auth.isEmployee() %}
        <script src="/assets/js/moderator.js?v=3.0.3" type="text/javascript"></script>
        <script src="/assets/js/simpleXML.js?v=3.0.1" type="text/javascript"></script>
        <script src="/assets/js/kap-form.js?v=3.0.1" type="text/javascript"></script>
    {% elseif auth.isOperator() %}
        <script src="/assets/js/operators_func.js?v=3.1.0" type="text/javascript"></script>
        <script src="/assets/js/operator_order.js?v=3.1.0" type="text/javascript"></script>
    {% else %}
        <script src="/assets/js/client.js?v=3.0.2" type="text/javascript"></script>
    {% endif %}
{% endif %}

<script type="text/javascript" src="/assets/js/main.js?v=3.0.2"></script>

</body>
</html>
