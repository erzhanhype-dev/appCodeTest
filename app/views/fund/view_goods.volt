<!-- заголовок -->
<div class="row">
    <div class="col-4">
        <h2>{{ t._("Заявка на финансирование") }}</h2>
    </div>
    <div class="col-2">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-6">
        <div class="float-right">
            {% if !fund.blocked %}
                {% if fund.type == "INS" %}
                    <a href="#" data-toggle="modal" class="btn btn-primary btn-lg" data-id="{{ fund.id }}"
                       data-target=".car_list_modal" id="displayUPCarList">
                        <i data-feather="list" width="20" height="14"></i>
                        {{ t._("Загрузить товар из УП") }}
                    </a>
                {% else %}
                    <a href="/fund_goods/new/{{ fund.id }}?m=car" class="btn btn-primary btn-lg">
                        <i data-feather="plus" width="20" height="14"></i>
                        {{ t._("Добавить товар") }}
                    </a>
                {% endif %}
                <a data-toggle="modal" data-target="#confirmClear" class="btn btn-danger btn-lg text-light">
                    <i data-feather="trash" width="20" height="14"></i>
                    {{ t._("Очистить заявку от товаров") }}
                </a>
            {% endif %}

            {% if fund.approve == 'FUND_NEUTRAL' %}
            <a data-toggle="modal" data-target="#confirmDelete" class="btn btn-danger btn-lg text-light">
                <i data-feather="trash" width="20" height="14"></i>
                {{ t._("Удалить заявку") }}
            </a>
            {% endif %}

        </div>
    </div>
</div>
<!-- /заголовок -->

<!-- /авто -->
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("Заявка №") }} {{ fund.number }}
                {% if fund.ref_fund_key != NULL %}
                    <span class="badge badge-success mb-2" style="font-size: 14px;">
                        <?php echo __getRefFundKeyDescription($fund->ref_fund_key);?>
                    </span>
                {% endif %}
                <input type="hidden" value="{{ fund.id }}" id="fundViewId">
            </div>
            <div class="card-body" id="FUND_VIEW_FORM">
                <table id="fundGoodsList" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr class="">
                        <th>{{ t._("goods-weight") }}</th>
                        <th>{{ t._("order-number") }}</th>
                        <th>{{ t._("summ") }}</th>
                        <th>{{ t._("tnved-code") }}</th>
                        <th>{{ t._("date-of-manufacture") }}</th>
                        <th>{{ t._("operations") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if fund_goods is not empty %}
                        {% for item in fund_goods %}
                            <tr>
                                <td>{{ item['weight'] }} кг.</td>
                                <td>{{ item['profile_id'] }}</td>
                                <td>{{ item['amount'] }} &#8376;</td>
                                <td>{{ item['ref_tn_code']['code'] }} | {{ item['ref_tn_code']['name'] }}</td>
                                <td>{{ item['date_produce'] }}</td>
                                <td>
                                    {% if fund.approve == 'FUND_NEUTRAL' and fund.blocked == 0 %}
                                        <a href="/fund_goods/delete/{{ item['id'] }}" class="btn btn-danger">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    {% endif %}
                                </td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- /авто -->

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
                                <p><a href="/fund/viewdoc/{{ file.id }}" class="btn btn-sm btn-secondary"
                                      target="_blank"><?php echo $fn; ?>. {{ t._(file.type) }}</a>&nbsp;
                                <a href="/fund/viewdoc/{{ file.id }}"
                                   class="btn btn-sm btn-primary preview{% if file.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                            data-feather="eye" width="14" height="14"></i>&nbsp;{{ file.ext|upper }}</a>
                                &nbsp;
                                {% if !fund.blocked %}<a class="btn btn-sm btn-danger" href="/fund/rmdoc/{{ file.id }}">
                                    <i data-feather="x-circle" width="14" height="14"></i>&nbsp;{{ t._("delete") }}
                                    </a>{% endif %}</p>
                                {% endif %}
                            {% endfor %}
                        {% endif %}
                    </div>
                    <div class="col">
                        <?php if(__checkHOC($_s['eku']) == false && $fund->blocked == 0): ?>
                        <form enctype="multipart/form-data" action="/fund/doc" method="POST" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="form-group" id="order">
                                <div class="controls">
                                    <select name="doc_type" class="form-control" style="width: 100%;">
                                        <option>-- тип документа не выбран --</option>
                                        <option value="calculation_cost">Калькуляция себестоимости продукции
                                        </option>
                                        <option value="other">Другое</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" id="order">
                                <div class="controls">
                                    <input type="file" name="files_import" id="files_import" class="form-control-file">
                                </div>
                            </div>
                            <input type="hidden" name="order_id" value="{{ pid }}">
                            <button type="submit" class="btn btn-success"
                                    name="button"{% if fund.blocked %} disabled{% endif %}>Загрузить документ
                            </button>&nbsp;
                        </form>
                        <form id="formFund" action="/fund/sign" method="POST" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                            <input type="hidden" name="orderType" id="orderType" value="fund">
                            <input type="hidden" value="{{ sign_data }}" name="fundHash" id="fundHash">
                            <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                            <div class="dropdown mt-3">
                                <button class="btn btn-warning dropdown-toggle" type="button" id="dropdownMenuButton"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Предварительный просмотр
                                </button>
                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/fund">Заявление</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app3">Приложение
                                        3</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app4">Приложение
                                        4</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app5">Приложение
                                        5</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app6">Приложение
                                        6</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app11">Приложение
                                        11</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app14">Приложение
                                        14</a>
                                </div>
                            </div>
                            <div class="dropdown mt-3">
                                {% if fund.approve == 'FUND_NEUTRAL' or fund.approve == 'FUND_DECLINED' %}
                                    <a href="/fund/tosign/{{ pid }}" class="btn btn-primary">Отправить на подпись
                                        руководителю</a>
                                {% endif %}
                            </div>
                        </form>
                        <?php elseif(__checkHOC($_s['eku']) == false and $fund->blocked == 1 and ($fund->sign == '' or
                        $fund->sign_acc == '')): ?>
                        <?php if($auth->accountant == $_s['iin']): ?>
                        {% if fund.sign_acc == '' and fund.approve != 'FUND_DONE' %}
                            <form id="formFund" action="/fund/sign" method="POST" autocomplete="off">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                <input type="hidden" name="orderType" id="orderType" value="fund">
                                <input type="hidden" value="{{ sign_data }}" name="fundHash" id="fundHash">
                                <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                                <hr>
                                <button type="button" class="btn btn-success mt-1 signFundBtn" href="#" data-role="acc">
                                    Подписать от бухгалтера
                                </button>
                            </form>
                        {% elseif fund.approve == 'FUND_DONE' %}
                            <p>Заявка одобрена и оплачена.</p>
                        {% else %}
                            <p>Заявка ожидает подписи руководителя.</p>
                        {% endif %}
                        <?php elseif($fund->approve == 'FUND_DONE'): ?>
                        <p>Заявка одобрена и оплачена.</p>
                        <?php else: ?>
                        <p>Заявка ожидает подписи руководителя и бухгалтера.</p>
                        <?php endif; ?>
                        <?php elseif(__checkHOC($_s['eku']) == false and $fund->blocked == 1 and $fund->sign != '' and
                        $fund->sign_acc != ''): ?>
                        <p>Заявка готова к отправке.</p>
                        <?php endif; ?>
                        <?php if(__checkHOC($_s['eku']) and $fund->blocked == 1): ?>
                        <form id="formFund" action="/fund/sign" method="POST" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                            <input type="hidden" name="orderType" id="orderType" value="fund">
                            <input type="hidden" value="{{ sign_data }}" name="fundHash" id="fundHash">
                            <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                            <div class="dropdown">
                                <button class="btn btn-warning dropdown-toggle" type="button" id="dropdownMenuButton"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Предварительный просмотр
                                </button>
                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/fund">Заявление</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app3">Приложение
                                        3</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app4">Приложение
                                        4</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app5">Приложение
                                        5</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app6">Приложение
                                        6</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app11">Приложение
                                        11</a>
                                    <a class="dropdown-item" target="_blank" href="/fund/viewtmp/{{ pid }}/app14">Приложение
                                        14</a>
                                </div>
                            </div>
                            {% if fund.approve != 'FUND_NEUTRAL' and  fund.approve != 'FUND_DONE' %}
                                <a href="/fund/todecline/{{ pid }}" class="btn btn-primary mt-1">Отправить на
                                    доработку</a>
                            {% endif %}
                            {% if fund.sign_acc != '' %}
                                <hr>
                                <button type="button" class="btn btn-success mt-1 signFundBtn" href="#" data-role="all">
                                    Подписать и прикрепить
                                </button>
                            {% endif %}
                        </form>
                        <?php elseif(__checkHOC($_s['eku']) and $fund->blocked == 0): ?>
                        <p>Ожидаем отправки документа на подпись.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $_head = __checkHOC($_s['eku']); ?>

{% if !_head and obj_count > 0 and (fund.approve == 'FUND_TOSIGN' or fund.approve == 'FUND_DECLINED') and app_form and fund.sign != '' and fund.sign_acc != '' %}
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Отправка заявки на рассмотрение") }}</div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <p>Как только заявка заполнена, все необходимые документы приложены, заявление и необходимые
                            приложения сформированы и подписаны ЭЦП - вы можете отправить заявку на рассмотрение
                            менеджеру.</p>
                        {% if fund.approve == 'FUND_TOSIGN' %}
                        <a href="/fund/review/{{ fund.id }}"
                                                                 title="{{ t._("Отправить на рассмотрение") }}">
                            <button type="button" name="b_view" class="btn btn-success"><i
                                        data-feather="user"></i> {{ t._("Отправить на рассмотрение") }}</button>
                        </a>
                        {% endif %}
                        {% if fund.approve == 'FUND_DECLINED' %}
                            <a href="/fund/review/{{ fund.id }}"
                                                                   title="{{ t._("Повторно отправить на рассмотрение") }}">
                            <button type="button" name="b_view" class="btn btn-success"><i
                                        data-feather="user"></i> {{ t._("Повторно отправить на рассмотрение") }}
                            </button></a>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{% endif %}

{% if obj_count > 0 and (fund.approve == 'FUND_REVIEW') and app_form %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <p>Заявка находится в процессе рассмотрения.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}


{% if obj_count > 0 and (fund.approve == 'FUND_APPROVED') and app_form %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}

<div class="modal fade" id="confirmDelete" tabindex="-1" aria-labelledby="confirmDelete" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Подтверждение</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Уверены, что хотите удалить заявку?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отменить</button>
                <a href="/fund/delete/{{ fund.id }}" class="btn btn-primary">Удалить</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmClear" tabindex="-1" aria-labelledby="confirmClear" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Подтверждение</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Вы уверены, что хотите очистить заявку от заполненных сведений?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отменить</button>
                <a href="/fund/clear/{{ fund.id }}" class="btn btn-primary">Очистить</a>
            </div>
        </div>
    </div>
</div>
<!-- car_list_modal -->
<div class="modal fade car_list_modal" tabindex="-1" role="dialog" aria-labelledby="selectUpCarList" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ t._("Список товаров с оплаченным УП") }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="saveSelectedToFund" action="/fund/upload_goods_list/" method="POST" autocomplete="off">
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                <div class="card-body" id="FUND_VIEW_UP_LIST">
                    <input type="hidden" name="fund_id" value="{{ fund.id }}" id="fund_profile_id">
                    <table id="viewUPGoodsListForFund" class="display select" cellspacing="0" width="100%">
                        <thead>
                        <tr>
                            <th><input name="select_all" type="checkbox"></th>
                            <th>{{ t._("goods-weight") }}</th>
                            <th>{{ t._("order-number") }}</th>
                            <th>{{ t._("tnved-code") }}</th>
                            <th>{{ t._("date-of-manufacture") }}</th>
                            <th>{{ t._("basis-date") }}</th>
                            <th>{{ t._("summ") }}</th>
                            <th>{{ t._("cert-date-approved") }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class="spinner-border check_limit_spinner ml-5" role="status" style="display:none;"></div>
                <div class="modal-footer add_car_list_to_fund" style="display:none;">
                    <h6 class="mr-auto">Было выбрано
                        <span class="badge badge-success selected_car_list_for_fund" style="font-size: 18px;">0</span>,
                        Общая сумма: <span class="badge badge-warning selected_car_sum"
                                           style="font-size: 18px;">0</span> тг
                    </h6>

                    <button type="submit" class="btn btn-success lg">
                        <span class="spinner-border spinner-border-sm" id="save_selected_up_cars_spinner"
                              style="display: none"></span>
                        Добавить выбранные товары
                    </button>
                </div>
                <div class="modal-footer show_fund_check_messages" style="display:none;">
                    <div class="alert alert-danger mr-auto show_fund_check_messages_alert" style="max-width:800px;"
                         role="alert">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- /car_list_modal -->
