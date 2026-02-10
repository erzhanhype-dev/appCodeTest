<?php
$_RU_MONTH = array(
    "январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"
);

$vin_in_progress = isset($_GET['vin']) ? $_GET['vin'] : '';
?>
<div class="alert alert-warning">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>Внимание!</strong> Для корректной работы сайта, рекомендуем вам очистить кэш браузера(обновите страницу
    через <strong>CTRL + F5 или CTRL+SHIFT+R </strong>).
</div>

<!-- /авто -->
{% if profile.type == 'CAR' %}
<input type="hidden" value="{{ vin_in_progress }}" id="VIN_IN_PROGRESS">
<input type="hidden" value="{{ profile.id }}" id="pid">
<!-- заголовок CAR -->
<div class="row">
    <div class="col-3">
        <h2>{{ t._("cars-list") }}</h2>
    </div>
    <div class="col-1">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-8">
        <div class="float-right addCarButtons">
            {% if !profile.blocked %}
                <a href="/car/check_epts/{{ profile.id }}?m=CAR" class="btn btn-success btn-lg">
                    <i data-feather="plus" width="20" height="14"></i>
                    {{ t._("Добавить автомобиль") }}
                </a>
                <a href="/car/check_epts/{{ profile.id }}?m=TRAC" class="btn btn-success btn-lg">
                    <i data-feather="plus" width="20" height="14"></i>
                    {{ t._("Добавить с/х-технику") }}
                </a>
                <a href="/create_order/import_car/{{ profile.id }}" class="btn btn-info btn-lg">
                    <i data-feather="download" width="20" height="14"></i>
                    {{ t._("Импорт(с excel)") }}
                </a>
                <a href="/create_order/clear_all_cars/{{ profile.id }}" class="btn btn-danger btn-lg">
                    <i data-feather="trash" width="20" height="14"></i>
                    {{ t._("Удаление всех позиции") }}
                </a>
            {% endif %}
        </div>
    </div>
</div>
<!-- /заголовок CAR-->

    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    {{ t._("cars-in-application") }}{{ tr.profile_id }}
                    <span class="badge badge-warning mb-2" style="font-size: 14px;">
            Тип плательщика: {{ t._(profile.agent_status) }}
          </span>
                    <input type="hidden" value="{{ tr.profile_id }}" id="carViewProfileId">
                </div>
                <div class="card-body" id="ORDER_CAR_VIEW_FORM">

                    {% if profile.type === 'CAR' %}
                        {% include 'create_order/view_car.volt' %}
                    {% endif %}

                    <div class="text-center CarListAlertMessage" style="display:none;">
                        <div class="alert alert-danger">
                            <i class="fa fa-clock-o fa-2x text-success"></i>
                            Загрузка транспортных средств <br>
                            VIN<strong style="font-size: 26px"></strong> обрабатывается ....
                        </div>
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>

                    <hr>

                    {% if profile.created > constant("ROP_ESIGN_DATE") %}
                        {% if tr.ac_approve == 'SIGNED' %}
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
                        {% if tr.approve == 'GLOBAL' %}
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
{% endif %}

<!-- /авто -->

<!-- товары -->

{% if profile.type == 'GOODS' %}
<!-- заголовок GOODS -->
<div class="row">
<div class="col-4">
    <h2>{{ t._("goods-list") }}</h2>
</div>
<div class="col-2">
    <div class="d-flex justify-content-center"></div>
</div>
<div class="col-6">
<div class="float-right">
    {% if !profile.blocked and !app_form %}
        <a href="/create_order/new_good/{{ profile.id }}" class="btn btn-success btn-lg">
            <i data-feather="plus" width="20" height="14"></i>
            {{ t._("Добавить товар (упаковку)") }}
        </a>
        <a href="/create_order/import_good/{{ profile.id }}" class="btn btn-info btn-lg">
            <i data-feather="download" width="20" height="14"></i>
            {{ t._("Импорт") }}
        </a>
        <a href="/create_order/clear_all_goods/{{ profile.id }}" class="btn btn-danger btn-lg">
            <i data-feather="trash" width="20" height="14"></i>
            {{ t._("Удаление всех позиции") }}
        </a>
    {% endif %}
    </div>
    </div>
    </div>
<!-- /заголовок GOODS-->
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    {{ t._("goods-in-application") }}{{ tr.profile_id }}
                    <span class="badge badge-warning mb-2" style="font-size: 14px;">
            Тип плательщика: {{ t._(profile.agent_status) }}
          </span>
                    <input type="hidden" value="{{ tr.profile_id }}" id="goodViewProfileId">
                </div>
                <div class="card-body" id="ORDER_GOODS_VIEW_FORM">
                    <table id="createOrderViewGoodsList" class="display" cellspacing="0" width="100%">
                        <thead>
                        <tr class="">
                            <th>{{ t._("num-symbol") }}</th>
                            <th>{{ t._("tn-code") }}</th>
                            <th>{{ t._("basis-good") }}</th>
                            <th>{{ t._("basis-date") }}</th>
                            <th>{{ t._("goods-weight") }}</th>
                            <th>{{ t._("goods-cost") }}</th>
                            <th>{{ t._("date-of-import") }}</th>
                            <th>{{ t._("package-weight") }}</th>
                            <th>{{ t._("package-cost") }}</th>
                            <th>{{ t._("total-amount") }}</th>
                            <th>{{ t._("operations") }}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <hr>

                    {% if profile.created > constant("ROP_ESIGN_DATE") %}
                        {% if tr.ac_approve == 'SIGNED' %}
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
                        {% if tr.approve == 'GLOBAL' %}
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
    {% endif %}

{% if tr.amount != 0 %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    {{ t._("summ-in-application") }}
                </div>
                <div class="card-body">
                    <form method="POST" action="/create_order/update_amount/" autocomplete="off">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                        <input type="hidden" name="transaction_id" value="{{ tr.id }}">
                        <div class="row">
                            <div class="col-6">
                                <input type="text" name="sum" class="form-control" value="{{ tr.amount }}"/>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"> {{ t._("save") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
{% endif %}

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("order-docs") }}
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        {% if files %}
                            <?php $fn = 0; ?>
                            {% for file in files %}
                                <?php $fn++; ?>
                                {% if file.type != 'inprotocol' and file.type != 'inletter' %}
                                <p><a href="/order/viewdoc/{{ file.id }}" class="btn btn-sm btn-secondary"
                                      target="_blank"><?php echo $fn; ?>. {{ t._(file.type) }}
                                <i>
                                    <?php
                        $filename = APP_PATH.'/private/docs/'.$file->id.'.'.$file->ext;

                                    if (file_exists($filename)) {
                                    echo '('.date("d.m.Y H:i", filemtime($filename)).')';
                                    }else{
                                    echo '(Файл не найден !)';
                                    }
                                    ?>
                                    {% if file.visible == 0 %}{{ " [удален]" }}{% endif %}
                                </i>
                            </a>&nbsp;
                                <a href="/order/viewdoc/{{ file.id }}"
                                   class="btn btn-sm btn-primary preview{% if file.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                            data-feather="eye" width="14" height="14"></i>&nbsp;{{ file.ext|upper }}</a>
                                &nbsp;
                                <a class="btn btn-xs btn-success" href="/order/getdoc/{{ file.id }}"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                                {% if !profile.blocked and file.type != 'digitalpass' and file.type != 'spravka_epts' and file.visible != 0  %}
                                <a class="btn btn-sm btn-danger" href="/order/rmdoc/{{ file.id }}"><i
                                        data-feather="x-circle" width="14" height="14"></i>&nbsp;{{ t._("delete") }}
                                    </a>{% endif %}</p>
                                {% endif %}
                            {% endfor %}
                        {% endif %}
                    </div>
                {% if !profile.blocked %}
                    <div class="col">
                        <form enctype="multipart/form-data" action="/order/doc" method="POST">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                            <div class="form-group" id="order">
                                <div class="controls">
                                    <select name="doc_type" class="form-control" style="width: 100%;">
                                        <option></option>
                                        {% if profile.type == 'CAR' %}
                                        <option value="regcertificate">Свидетельство о регистрации</option>
                                        <option value="techpass">Технический паспорт</option>
                                        <option value="customdec">Таможенная декларация</option>
                                        <option value="customtal">Талон о прохождении границы</option>
                                        <option value="ptsrts">ПТС / СРТС</option>
                                        <option value="dover">Доверенность</option>
                                        <option value="accepttypecar">Одобрение типа ТС</option>
                                        <option value="other">Другое</option>
                                        <!-- <option value="application">Подписанное заявление</option> -->
                                        {% endif %}
                                        {% if profile.type == 'GOODS' %}
                                            <option value="regcertificate">Свидетельство о регистрации</option>
                                            <option value="invoiceimp">Счет фактура (импортера)</option>
                                            <option value="packlist">Упаковочный лист</option>
                                            <option value="trdocs">Транспортные накладные</option>
                                            <option value="prpassport">Паспорта продукции</option>
                                            <option value="importapp">Заявление о ввозе товаров</option>
                                            <option value="talgovcontrol">Талон о прохождении гос. контроля</option>
                                            <option value="intgoods">Международная товарная накладная</option>
                                            <option value="customgoods">Таможенная накладная</option>
                                            <option value="railwaysorder">Железнодорожная накладная</option>
                                            <option value="customdec">Таможенная декларация</option>
                                            <!-- <option value="application">Подписанное заявление</option> -->
                                            <option value="other">Другое</option>
                                        {% endif %}
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" id="order">
                                <div class="controls">
                                    <input type="file" name="files_import" id="files_import" class="form-control-file">
                                </div>
                            </div>
                            <input type="hidden" name="profile_id" value="{{ pid }}">
                            <button type="submit" class="btn btn-success" name="button">Загрузить документ</button>&nbsp;
                            <!-- {% if profile.type == 'CAR' %}<a href="/create_order/application/{{ profile.id }}/car">Скачать форму заявления</a>{% endif %}
            {% if profile.type == 'GOODS' %}<a href="/create_order/application/{{ profile.id }}/goods">Скачать форму заявления</a>{% endif %} -->
                        </form>
                        <form id="formSign" action="/create_order/sign" method="POST">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                            <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                            <input type="hidden" value="{{ sign_data }}" name="profileHash" id="profileHash">
                            <textarea name="profileSign" id="profileSign" style="display: none;"></textarea>
                            <hr>
                            <button type="button" class="btn btn-warning signApplicationBtn">Сформировать и
                                подписать заявление
                            </button>
                        </form>
                    </div>
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>

{% if tr.approve != 'GLOBAL' and app_form %}
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
                            {% if tr.approve == 'NEUTRAL' or tr.approve == 'DECLINED' %}<a
                                href="/create_order/review/{{ profile.id }}" title="{{ t._("send-to-review") }}">
                                <button type="button" name="b_view" class="btn btn-success"><i
                                            data-feather="user"></i> {{ t._("Отправить менеджеру") }}</button>
                                </a>{% endif %}
                            {% if tr.approve == 'APPROVE' %}<a href="/pay/invoice/{{ profile.id }}"
                                                               title="{{ t._("invoice") }}">
                                    <button type="button" name="b_view" class="btn btn-success"><i
                                                data-feather="credit-card"></i> Скачать счет на оплату
                                    </button></a>{% endif %}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}

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