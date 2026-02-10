<?php $invisible =''; ?>

<div class="alert alert-warning">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>Внимание!</strong> Для корректной работы сайта, рекомендуем вам очистить кэш браузера(обновите страницу
    через <strong>CTRL + F5 или CTRL+SHIFT+R </strong>).
</div>

<!-- заголовок -->
<h2> {{ t._(" Корректировка") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/correction/" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col">
                    <input name="num" id="num" type="text" class="form-control"
                           value="<?php echo (isset($_SESSION['filter_correction'])) ? $_SESSION['filter_correction'] : ''; ?>"
                           placeholder="Введите номер заявки">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    <button type="submit" name="clear" value="clear"
                            class="btn btn-warning">{{ t._("Сбросить") }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->
<!-- содержимое заявки -->
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("Содержимое заявки") }}</div>
            <div class="card-body">
                {% if pr is defined and pr.type == 'CAR' %}
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>{{ t._("num-symbol") }}</th>
                            <th>{{ t._("type") }}</th>
                            <th>{{ t._("volume-weight") }}</th>
                            <th>{{ t._("vin-code") }}</th>
                            <th>{{ t._("year-of-manufacture") }}</th>
                            <th>{{ t._("date-of-import") }}</th>
                            <th>{{ t._("country-of-manufacture") }}</th>
                            <th width="28%">{{ t._("operations") }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {% if page.items|length %}
                            {% for item in page.items %}
                                <tr>
                                    <td class="v-align-middle">{{ item.c_id }}</td>
                                    <td class="v-align-middle">{{ item.c_type }}</td>
                                    <td class="v-align-middle">{{ item.c_volume }}</td>
                                    <td class="v-align-middle">{{ item.c_vin|dash_to_amp }}</td>
                                    <td class="v-align-middle">{{ item.c_year }}</td>
                                    <td class="v-align-middle">
                                        <?php echo date("d.m.Y", convertTimeZone($item->c_date_import));?>
                                    </td>
                                    <td class="v-align-middle">{{ item.c_country }}</td>
                                    <td class="v-align-middle">
                                        {% if item.c_status == 'CANCELLED' %}
                                            <?php $invisible='style="display:none"';?>
                                            <b style="color:red">ДПП аннулирован!</b><br>
                                            <a href="#" data-toggle="modal" class="btn btn-outline-success mt-2"
                                               data-id="{{ item.c_id }}"
                                               data-target=".restore_annulled_car_form_modal" id="restoreAnnulledCar">
                                                <i class="fa fa-undo"></i>
                                                Восстановить СВУП?
                                            </a>
                                        {% else %}
                                            <?php $invisible='';?>
                                        {% endif %}

                                        {% if item.tr_approve == 'GLOBAL' AND item.tr_ac_approve == 'SIGNED' %}
                                            <a href="/correction/edit_car/{{ item.c_id }}"
                                               title={{ t._("edit-car") }} {{ invisible }} class="btn btn-primary
                                               btn-sm">
                                            <i data-feather="edit" width="14" height="14"></i>
                                            </a>
                                            <a href="/main/certificate/{{ item.c_tr }}/{{ item.c_id }}" target="_blank"
                                               class="btn btn-warning btn-sm">
                                                Скачать сертификат
                                                <i data-feather="download" width="14" height="14"></i>
                                            </a>
                                        {% endif %}
                                    </td>
                                </tr>
                            {% endfor %}
                        {% endif %}
                        </tbody>
                    </table>
                    <div class="col text-center">

                    </div>
                {% endif %}
                {% if pr is defined and pr.type == 'GOODS' %}
                    <div class="row">
                        <div class="ml-auto mr-1">
                            <form method="POST" autocomplete="off" autocomplete="off">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                {% if can_annul %}
                                    <button class="btn btn-danger" formaction="/correction/goods_view/{{ pid }}">
                                        Аннулировать все (<?php echo count($goods); ?> позиции)
                                    </button>
                                {% endif %}
                                {% if can_add_goods %}
                                    <button type="submit" class="btn btn-success"
                                            formaction="/correction/new/{{ pid }}"><i data-feather="plus"
                                                                                      style="font-size:16px;"></i>
                                        Добавить товар
                                    </button>
                                {% endif %}
                            </form>
                        </div>

                    </div>
                    <hr>
                    <table class="table table-hover">
                        <thead>
                        <tr class="">
                            <th>{{ t._("num-symbol") }}</th>
                            <th>{{ t._("tn-code") }}</th>
                            <th>{{ t._("goods-weight") }}</th>
                            <th>{{ t._("basis-good") }}</th>
                            <th>{{ t._("amount") }}</th>
                            <th>{{ t._("date-of-import") }}</th>
                            <th>{{ t._("operations") }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {% if page.items|length %}
                            {% for item in page.items %}
                                <?php $tn_add = false; if($item->tn_add) { $tn_add = RefTnCode::findFirstById($item->tn_add); }; ?>
                                <tr>
                                    <td class="v-align-middle">{{ item.g_id }}</td>
                                    <td class="v-align-middle"
                                        title="{{ item.tn_name }}">{{ item.tn_code }}<?php if($tn_add) { echo ' (упаковано '.$tn_add->
                                        code.')'; } ?>
                                    </td>
                                    <td class="v-align-middle">{{ item.g_weight }} {{ t._("kg") }}</td>
                                    <td class="v-align-middle">{{ item.g_basis }}</td>
                                    <td class="v-align-middle"><?php echo number_format($item->g_amount, 2, ",", "&nbsp;");
                                        ?>
                                    </td>
                                    <td class="v-align-middle">
                                        <?php echo date("d.m.Y", convertTimeZone($item->g_date));?>
                                    </td>
                                    <td class="v-align-middle" style="width: 250px">
                                        <?php
                $invisible =''; 
                if($item->g_status == 'CANCELLED') {
                                        $invisible='style="display:none"';
                                        echo '<b style="color:red">Позиция аннулирована!</b>';
                                        }elseif($item->g_status == 'DELETED') {
                                        $invisible='style="display:none"';
                                        echo '<b style="color:darkred">Позиция удалена!</b>';
                                        }
                                        ?>
                                        {% if item.tr_approve == 'GLOBAL' AND item.tr_ac_approve == 'SIGNED' %}
                                            <a href="/correction/edit_goods/{{ item.g_id }}" {{ invisible }}
                                               class="btn btn-primary btn-sm">
                                                <i data-feather="edit" width="14" height="14"></i>
                                            </a>
                                            <a href="/main/certificate/{{ item.g_pid }}/{{ item.g_id }}" target="_blank"
                                               class="btn btn-warning btn-sm">
                                                Скачать сертификат
                                                <i data-feather="download" width="14" height="14"></i>
                                            </a>
                                        {% endif %}
                                    </td>
                                </tr>
                            {% endfor %}
                        {% endif %}
                        </tbody>
                    </table>
                {% endif %}

                {# KPP list begin #}
                {% if pr is defined and pr.type == 'KPP' %}
                    <div class="row">
                        <div class="ml-auto mr-1">
                            <form method="POST" autocomplete="off" autocomplete="off">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                {% if can_annul %}
                                    <button class="btn btn-danger" formaction="/correction/kpps_view/{{ pid }}">
                                        Аннулировать все (<?php echo count($kpps); ?> позиции)
                                    </button>
                                {% endif %}
                            </form>
                        </div>
                    </div>
                    <hr>
                    <table class="table table-hover">
                        <thead>
                        <tr class="">
                            <th>{{ t._("num-symbol") }}</th>
                            <th>{{ t._("tn-code") }}</th>
                            <th>{{ t._("kpp-weight") }}</th>
                            <th>{{ t._("basis-good") }}</th>
                            <th>{{ t._("basis-date") }}</th>
                            <th>{{ t._("kpp-invoice-sum") }}</th>
                            <th>{{ t._("kpp-invoice-sum-currency") }}</th>
                            <th>{{ t._("amount") }}</th>
                            <th>{{ t._("date-of-import") }}</th>
                            <th>{{ t._("package-tn-code") }}</th>
                            <th>{{ t._("package-weight") }}</th>
                            <th>{{ t._("package-cost") }}</th>
                            <th>{{ t._("operations") }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {% if page.items|length %}
                            {% for item in page.items %}
                                <?php $tn_add = false; if($item->tn_add) { $tn_add = RefTnCode::findFirstById($item->tn_add); }; ?>
                                <tr>
                                    <td class="v-align-middle">{{ item.k_id }}</td>
                                    <td class="v-align-middle" title="{{ item.tn_name }}">{{ item.tn_code }}</td>
                                    <td class="v-align-middle">{{ item.k_weight }} тонна</td>
                                    <td class="v-align-middle">{{ item.k_basis }}</td>
                                    <td class="v-align-middle">
                                        {% if item.b_date > 0 %}
                                            <?php echo date('d.m.Y',$item->b_date);?>
                                        {% endif %}
                                    </td>
                                    <td class="v-align-middle">{{ item.k_invoice_sum }}</td>
                                    <td class="v-align-middle">
                                        {% if item.k_invoice_sum_currency is empty %} - / {% else %} {{ item.k_invoice_sum_currency }} {% endif %}
                                        {{ item.k_currency_type }}
                                    </td>
                                    <td class="v-align-middle"><?php echo number_format($item->k_amount, 2, ",", "&nbsp;");
                                        ?>
                                    </td>
                                    <td class="v-align-middle">{{ date("d.m.Y", item.k_date) }}</td>
                                    <td class="v-align-middle">{{ item.p_tn_code }}</td>
                                    <td class="v-align-middle">{{ item.p_weight }} {{ t._("kg") }}</td>
                                    <td class="v-align-middle"><?php echo number_format($item->p_cost, 2, ",", "&nbsp;");
                                        ?>
                                    </td>
                                    <td class="v-align-middle" style="width: 250px">
                                        <?php
                    $invisible =''; 
                    if($item->k_status == 'CANCELLED') {
                                        $invisible='style="display:none"';
                                        echo '<b style="color:red">Позиция аннулирована!</b>';
                                        }elseif($item->k_status == 'DELETED') {
                                        $invisible='style="display:none"';
                                        echo '<b style="color:darkred">Позиция удалена!</b>';
                                        }
                                        ?>
                                        {% if item.tr_approve == 'GLOBAL' AND item.tr_ac_approve == 'SIGNED' %}
                                            <a href="/correction/show_kpp/{{ item.k_id }}" {{ invisible }}
                                               class="btn btn-primary btn-sm">
                                                <i data-feather="edit" width="14" height="14"></i>
                                            </a>
                                            <a href="/main/certificate/{{ item.tr_profile_id }}/{{ item.k_id }}"
                                               target="_blank" class="btn btn-warning btn-sm">
                                                Скачать сертификат
                                                <i data-feather="download" width="14" height="14"></i>
                                            </a>
                                        {% endif %}
                                    </td>
                                </tr>
                            {% endfor %}
                        {% endif %}
                        </tbody>
                    </table>

                {% endif %}
                {# KPP list end #}

            {% if page is defined %}
                {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
            {% endif %}
            </div>
        </div>
    </div>
</div>

