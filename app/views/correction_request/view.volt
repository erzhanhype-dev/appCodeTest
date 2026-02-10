<!-- заголовок -->
<h2 class="mt-4">
    {{ t._("Заявка на ") }}
    <?php echo $t->_($cc_pr->action);?>
</h2>

<input type="hidden" id="pid" value="{{ pr.id }}">
<!-- /заголовок -->

<? $diff_value = '';?>
<div class="row">
    <div class="col">
        {% if user is defined %}
            <!-- клиент -->
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    {{ t._("Клиент") }}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col"><strong>{{ t._("Наименование, ФИО") }}</strong></div>
                        <div class="col">{{ title | upper }}</div>
                    </div>
                    <div class="row">
                        <div class="col"><strong>{{ t._("Логин") }}</strong></div>
                        <div class="col">{{ user.login }}</div>
                    </div>
                    <div class="row">
                        <div class="col"><strong>{{ t._("Почта") }}</strong></div>
                        <div class="col">{{ user.email | upper }}</div>
                    </div>
                    <div class="row">
                        <div class="col"><strong>{{ t._("Тип клиента") }}</strong></div>
                        <div class="col">
                            {% if (user.user_type is defined) and user.user_type == constant('PERSON') %}{{ t._("person") }}{% endif %}
                            {% if (user.user_type is defined) and user.user_type == constant('COMPANY') %}{{ t._("company") }}{% endif %}
                        </div>
                    </div>
                    {% if user.phone %}
                        <div class="row">
                            <div class="col"><strong>{{ t._("Контактный телефон") }}</strong></div>
                            <div class="col">
                                {{ user.phone }}
                            </div>
                        </div>
                    {% endif %}
                </div>
            </div>
            <!-- /клиент -->
        {% endif %}
    </div>
    <div class="col">
        <!-- сведения -->
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("Сведения") }}
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col"><strong>{{ t._("Дата создания") }}</strong></div>
                    <div class="col">{{ date("d.m.Y H:i", profile.p_created) }}</div>
                </div>
                {% if profile.t_dt_sent %}
                    <div class="row">
                        <div class="col"><strong>{{ t._("Дата отправки модератору") }}</strong></div>
                        <div class="col"> {{ date("d.m.Y H:i", profile.t_dt_sent) }}</div>
                    </div>
                {% endif %}
                <div class="row">
                    <div class="col"><strong>{{ t._("Сумма") }}</strong></div>
                    <div class="col">
                        {% if pr.type == 'CAR' %}
                            {% if cc_pr.action == 'ANNULMENT' %}
                                <?php
                                            $amount_will_be = ($profile->t_amount-$before->cost);
                                                                                                           $diff_value = '
                                <b
                                        style="color:#FF5733;">'.__money($before->cost).' <i
                                            class="fa fa-long-arrow-down" aria-hidden="true"></i></b>';

                                                                                                     $amount_before = '
                                <del style="color:orange;">'.__money($profile->t_amount).'</del>';
                                                                                                $amount_after = '<b
                                    style="color:green;">'.__money($amount_will_be).'</b> тенге';
                                ?>
                                {{ amount_before }} {{ diff_value }} => {{ amount_after }}
                            {% else %}
                                {% if before.cost != after.cost %}
                                    <?php
                        $diff_cost = ($after->cost-$before->cost);
                                                                                    $amount = $profile->t_amount;
                                                                                    $amount_will_be = ($amount+$diff_cost);
                                                                                    $diff_value = ($diff_cost > 0) ?
                                                                                    ' <b style="color:#32CD32;">+
                                    '.__money($diff_cost).' <i class="fa fa-long-arrow-up"
                                                               aria-hidden="true"></i></b>' :
                                                                                          ' <b
                                        style="color:#FF5733;">'.__money($diff_cost).' <i
                                            class="fa fa-long-arrow-down" aria-hidden="true"></i></b>';

                                                                                                     $amount_before = '
                                    <del style="color:orange;">'.__money($profile->t_amount).'</del>';
                                                                                                    $amount_after = '
                                    <b style="color:green;">'.__money($amount_will_be).'</b> тенге';
                                    ?>
                                    {{ amount_before }} {{ diff_value }} => {{ amount_after }}
                                {% else %}
                                    <?php echo number_format($profile->t_amount, 2, ",", "&nbsp;"); ?> тенге
                                {% endif %}
                            {% endif %}
                        {% else %}
                            {% if cc_pr.action == 'ANNULMENT' %}
                                <del style="color:orange;"><?php echo number_format($profile->t_amount, 2, ",", "&nbsp;");
                                    ?> тенге
                                </del>
                                => <b style="color:green;">0, 00 тенге</b>
                            {% else %}
                                {% if before and after and before.amount != after.amount %}
                                    <?php
                          $diff_cost = ($after->amount-$before->amount);
                                                                                      $amount = $profile->t_amount;
                                                                                      $amount_will_be = ($amount+$diff_cost);
                                                                                      $diff_value = ($diff_cost > 0) ?
                                                                                      ' <b
                                        style="color:#32CD32;">+ '.__money($diff_cost).' <i
                                            class="fa fa-long-arrow-up" aria-hidden="true"></i></b>' :
                                                                                                   ' <b
                                        style="color:#FF5733;">'.__money($diff_cost).' <i
                                            class="fa fa-long-arrow-down" aria-hidden="true"></i></b>';

                                                                                                     $amount_before = '
                                    <del style="color:orange;">'.__money($profile->t_amount).'</del>';
                                                                                                    $amount_after = '
                                    <b style="color:green;">'.__money($amount_will_be).'</b> тенге';
                                    ?>
                                    {{ amount_before }} {{ diff_value }} => {{ amount_after }}
                                {% else %}
                                    <?php echo number_format($profile->t_amount, 2, ",", "&nbsp;"); ?> тенге
                                {% endif %}
                            {% endif %}
                        {% endif %}
                    </div>
                </div>
                <div class="row">
                    <div class="col"><strong>{{ t._("Агентская заявка") }}</strong></div>
                    <div class="col">
                        {% if profile.agent_name %}
                            {{ profile.agent_name }} /
                            {{ profile.agent_iin }} /
                            {{ profile.agent_city }} /
                            {{ profile.agent_phone }}
                        {% else %}
                            нет
                        {% endif %}
                    </div>
                </div>
                {% if auth is defined and auth.isEmployee() %}
                    {% if profile.p_moderator != NULL or profile.p_moderator != '' %}
                        <div class="row">
                            <div class="col"><strong>{{ profile.p_name }}</strong></div>
                            <div class="col">{{ profile.title }}</div>
                        </div>
                    {% endif %}
                {% endif %}
            </div>
        </div>
    </div>
</div>
{% if pr.type == 'CAR' %}
    <!-- если это корректировка ТС -->
    {% if cc_pr.action == 'CORRECTION' or cc_pr.action == '' %}
        {% include "/correction_request/car_correction_info.volt" %}
    {% endif %}

    <!-- если аннулирование ТС -->
    {% if cc_pr.action == 'ANNULMENT' %}
        {% include "/correction_request/car_annulment_info.volt" %}
    {% endif %}

{% else %}
    <!-- если это корректировка Товара -->
    {% if cc_pr.action == 'CORRECTION' or cc_pr.action == 'DELETED' or cc_pr.action == 'CREATED' %}
        {% include "/correction_request/goods_correction_info.volt" %}
    {% endif %}

    <!-- если это корректировка ТС -->
    {% if cc_pr.action == 'ANNULMENT' %}
        {% include "/correction_request/goods_annulment_info.volt" %}
    {% endif %}

{% endif %}

{% if files %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("documents-for-application") }}{{ pr.id }}</span></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <?php $fn = 0; ?>
                            {% for file in files %}
                                <?php $fn++; ?>
                                <p>
                                    <a href="/order/viewdoc/{{ file.id }}" class="btn btn-sm btn-secondary"
                                       target="_blank"><?php echo $fn; ?>
                                        . {{ t._(file.type) }}{% if file.visible == 0 %}{{ " [удален]" }}{% endif %}
                                        <?php if($auth && ($auth->isSuperModerator() || $auth->isAdminSoft())){ ?>
                                        <i><?php
                                            $filename = APP_PATH.'/private/docs/'.$file->id.'.'.$file->ext;
                                            if (file_exists($filename)) {
                                            echo '('.date("d.m.Y H:i", convertTimeZone(filemtime($filename))).')';
                                            }else{
                                            echo '(Файл не найден !)';
                                            }
                                            ?></i>
                                        <?php } ?>
                                    </a>&nbsp;
                                    <a href="/order/viewdoc/{{ file.id }}"
                                       class="btn btn-sm btn-primary preview{% if file.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                                data-feather="eye" width="14" height="14"></i>&nbsp;{{ file.ext|upper }}
                                    </a>&nbsp;
                                    <a class="btn btn-xs btn-success" href="/order/getdoc/{{ file.id }}"><i
                                                data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                                    {% if auth is defined and auth.isAdminSoft() and file.visible == 1 %} <a
                                            class="btn btn-sm btn-danger" href="/order/rmdoc/{{ file.id }}"><i
                                            data-feather="x-circle" width="14" height="14"></i>&nbsp;{{ t._("delete") }}
                                        </a>{% endif %}
                                    {% if auth is defined and auth.isAdminSoft() and file.visible == 0 %} <a
                                            class="btn btn-sm btn-warning" href="/order/restore/{{ file.id }}"><i
                                            data-feather="upload" width="14" height="14"></i>
                                        &nbsp;{{ t._("Восстановить") }}</a>{% endif %}
                                </p>
                            {% endfor %}
                            {% if c_app %}
                                <?php $cnt = 0; ?>
                                {% for app in c_app %}
                                    <?php $cnt++; ?>
                                    <?php if($auth && ($auth->isSuperModerator() || $auth->isAdminSoft() || $auth->isAccountant())){ ?>
                                    <p>
                                        <a href="/correction_request/viewdoc/{{ app.id }}"
                                           class="btn btn-sm btn-secondary" target="_blank"><?php echo $fn+$cnt; ?>
                                            . {{ t._(app.type) }}{% if app.visible == 0 %}{{ " [удален]" }}{% endif %}
                                            <i>
                                                <?php
                            $c_app = APP_PATH.'/private/client_correction_docs/'.$app->original_name;

                                                if (file_exists($c_app)) {
                                                echo '('.date("d.m.Y H:i", convertTimeZone(filemtime($c_app))).')';
                                                }else{
                                                echo '(Файл не найден !)';
                                                }
                                                ?>
                                            </i>
                                        </a>
                                        <a href="/correction_request/viewdoc/{{ app.id }}"
                                           class="btn btn-sm btn-primary preview{% if app.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                                    data-feather="eye" width="14"
                                                    height="14"></i>&nbsp;{{ app.ext|upper }}</a>&nbsp;
                                        <a class="btn btn-xs btn-success"
                                           href="/correction_request/getdoc/{{ app.id }}">
                                            <i data-feather="download" width="14" height="14"></i>&nbsp;Cкачать
                                        </a>
                                    </p>
                                    <?php }else{ ?>
                                    <?php if($app->type == 'app_correction' or $app->type == 'pay_correction' ) { ?>&nbsp;
                                    <p>
                                        <a href="/correction_request/viewdoc/{{ app.id }}"
                                           class="btn btn-sm btn-secondary" target="_blank"><?php echo $fn+$cnt; ?>
                                            . {{ t._(app.type) }}{% if app.visible == 0 %}{{ " [удален]" }}{% endif %}</a>
                                        <a href="/correction_request/viewdoc/{{ app.id }}"
                                           class="btn btn-sm btn-primary preview{% if app.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                                    data-feather="eye" width="14"
                                                    height="14"></i>&nbsp;{{ app.ext|upper }}</a>&nbsp;
                                        <a class="btn btn-xs btn-success"
                                           href="/correction_request/getdoc/{{ app.id }}"><i data-feather="download"
                                                                                             width="14" height="14"></i>&nbsp;Cкачать</a>
                                    </p>
                                    <?php }else{continue;}} ?>
                                {% endfor %}
                            {% endif %}
                        </div>
                    </div>
                    <?php if($auth->isEmployee()){ ?>
                    {% if cc_logs is defined %}
                        {% for cc_log in cc_logs %}
                            {% if cc_log.comment != '' %}
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <b>
                                            Текст обращения
                                            (
                                            <?php
                                          $id = $cc_log->user_id;
                                            echo __getClientTitleByUserId($id);
                                            ?>
                                            ):
                                        </b>
                                        <p class="text-muted">{{ date('d.m.Y H:i', cc_log.dt) }}</p>
                                        <textarea class="form-control" row="5" readonly>{{ cc_log.comment }}</textarea>
                                    </div>
                                </div>
                            {% else %}
                            {% endif %}
                        {% endfor %}
                    {% endif %}
                    {% if auth is defined and auth.isSuperModerator() %}
                        {% if cc_pr.status == "SEND_TO_MODERATOR" %}
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <ul class="nav nav-tabs">
                                        <li class="nav-item">
                                            <a class="btn btn-success" data-toggle="tab"
                                               href="#correctionAcceptForm">Одобрить</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="btn btn-danger" data-toggle="tab"
                                               href="#correctionDeclineForm">Отклонить</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="tab-content p-3">
                                    <!-- Edit car form -->
                                    <div class="tab-pane fade" id="correctionAcceptForm">
                                        <h6 class="h4 mb-3 mt-3">Одобрение</h6>
                                        <form id="formCorrectionRequestSign" action="/correction_request/sign"
                                              method="POST"
                                              enctype="multipart/form-data">
                                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                            <input type="hidden" name="user" id="correctionRequestUser"
                                                   value="moderator"/>
                                            <div class="form-group" id="car_volume_group">
                                                <div class="form-group">
                                                    <label class="form-label">{{ t._("comment") }}</label>
                                                    <textarea name="comment" id="correctionRequestComment"
                                                              class="form-control" placeholder="Ваш комментарий ... "
                                                              required></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">{{ t._("Загрузить файл") }}</label>
                                                    <input type="file" id="correctionAcceptFile" name="file"
                                                           class="form-control-file" required>
                                                </div>
                                                <hr>
                                                <div class="form-group">
                                                    <input type="hidden" name="ccp_id" value="{{ cc_pr.id }}">
                                                    <input type="hidden" value="{{ sign_data }}" name="hash"
                                                           id="profileHash">
                                                    <textarea name="sign" id="profileSign"
                                                              style="display: none;"></textarea>
                                                    <select id="storageSelect" class="hidden">
                                                        <option value="PKCS12" selected>Файл</option>
                                                    </select>
                                                    <button type="button"
                                                            class="btn btn-success signCorrectionRequestBtn"> Одобрить и
                                                        подписать
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Annulment form -->
                                    <div class="tab-pane fade" id="correctionDeclineForm">
                                        <h6 class="h4 mb-3 mt-3">Отклонение</h6>
                                        <form action="/correction_request/decline" method="POST"
                                              enctype="multipart/form-data">
                                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                            <input type="hidden" name="ccp_id" value="{{ cc_pr.id }}">
                                            <div class="form-group">
                                                <label class="form-label">{{ t._("comment") }}</label>
                                                <textarea name="comment" id="correctionDeclineComment"
                                                          class="form-control"
                                                          placeholder="Ваш комментарий ... " required></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">{{ t._("Загрузить файл") }}</label>
                                                <input type="file" id="correctionDeclineFile" name="file"
                                                       class="form-control-file" required>
                                            </div>
                                            <hr>
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-danger"> Отклонить</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        {% endif %}
                    {% endif %}
                    {% if auth.isAccountant() %}
                        {% if cc_pr.status == "SENT_TO_ACCOUNTANT" %}
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <ul class="nav nav-tabs">
                                        <li class="nav-item">
                                            <a class="btn btn-success" data-toggle="tab"
                                               href="#correctionAcceptForm">Одобрить</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="btn btn-danger" data-toggle="tab"
                                               href="#correctionDeclineForm">Отклонить</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="tab-content p-3">
                                    <!-- Edit car form -->
                                    <div class="tab-pane fade" id="correctionAcceptForm">
                                        <h6 class="h4 mb-3 mt-3">Одобрение</h6>
                                        <form action="/correction_request/accountant_sign" method="POST">
                                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                            <input type="hidden" name="ccp_id" value="{{ cc_pr.id }}">
                                            <div class="form-group" id="car_volume_group">
                                                <div class="form-group">
                                                    <label class="form-label">{{ t._("comment") }}</label>
                                                    <textarea name="comment" id="correctionRequestComment"
                                                              class="form-control" placeholder="Ваш комментарий ... "
                                                              required></textarea>
                                                </div>
                                                <hr>
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-success"> Одобрить</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Annulment form -->
                                    <div class="tab-pane fade" id="correctionDeclineForm">
                                        <h6 class="h4 mb-3 mt-3">Отклонение</h6>
                                        <form action="/correction_request/accountant_decline" method="POST"
                                              enctype="multipart/form-data">
                                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                            <input type="hidden" name="ccp_id" value="{{ cc_pr.id }}">
                                            <div class="form-group">
                                                <label class="form-label">{{ t._("comment") }}</label>
                                                <textarea name="comment" id="correctionDeclineComment"
                                                          class="form-control"
                                                          placeholder="Ваш комментарий ... " required></textarea>
                                            </div>
                                            <hr>
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-danger"> Отклонить</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        {% endif %}
                    {% endif %}
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
{% endif %}

{% if auth is defined and auth.isEmployee() %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    <div class="row">
                        <div class="col-6 mt-2">
                            Возможные варианты платежей
                            <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
                        </div>
                        <div class="ml-auto mr-3">
                            <button type="button" class="btn btn-info" id="getROPPaymentsList">
                                Получить данные
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="ropPaymentListDiv" style="display:none">
                    <table id="ropPaymentListTable" class="display" cellspacing="0" width="100%">
                        <thead>
                        <tr class="">
                            <th>На какой счет</th>
                            <th>Сумма</th>
                            <th>Дата платежа</th>
                            <th>Обнаружено</th>
                            <th>Операция</th>
                            <th>Платежи</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- /Возможные варианты платежей(Опрератор РОП) -->

    <!-- Возможные варианты платежей(Жасыл даму) -->
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    <div class="row">
                        <div class="col-6 mt-2">
                            Возможные варианты платежей
                            <span class="badge badge-success "
                                  style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
                        </div>
                        <div class="ml-auto mr-3">
                            <button type="button" class="btn btn-info" id="getJDPaymentsList">
                                Получить данные
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="jdPaymentListDiv" style="display:none">
                    <table id="jdPaymentListTable" class="display" cellspacing="0" width="100%">
                        <thead>
                        <tr class="">
                            <th>На какой счет</th>
                            <th>Сумма</th>
                            <th>Дата платежа</th>
                            <th>Обнаружено</th>
                            <th>Операция</th>
                            <th>Платежи</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- /Возможные варианты платежей(Жасыл даму) -->

    <!-- логи -->
    <div class="row">
        <div class="col-6">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    <div class="row ml-1">
                        <div>
                            Логи действий с заявкой
                        </div>
                        <div class="ml-auto mr-3">
                            <form id="getLogsForm" method="POST" action="/moderator_order/get_logs/">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                <input type="hidden" name="pid" value="{{ pr.id }}">
                                <button type="submit" class="btn btn-info"><span
                                            class="spinner-border spinner-border-sm"
                                            id="moderator_view_logs_spinner"
                                            style="display: none"></span> Просмотр логи
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="logs_table">
                            <thead>
                            <tr>
                                <th>{{ t._("logged-user") }}</th>
                                <th style="max-width: 50%;">{{ t._("logged-action") }}</th>
                                <th>{{ t._("logged-date") }}</th>
                            </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                    </div>
                    <div class="text-center" id="logs_body_spinner" style="display: none">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    <div class="row ml-1">
                        <div>
                            Логи действий с заявкой(заявки на корректировку)
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>{{ t._("logged-user") }}</th>
                                <th style="max-width: 50%;">{{ t._("logged-action") }}</th>
                                <th>{{ t._("logged-date") }}</th>
                                <th>{{ t._("comment") }}</th>
                            </tr>
                            {% for c_log in cc_logs %}
                                <tr>
                                    <td>
                                        <?php
                                      $id = $c_log->user_id;
                                        echo ($auth::isEmployee()) ? __getClientTitleByUserId($id) : 'МОДЕРАТОР';
                                        ?>
                                    </td>
                                    <td>{{ t._(c_log.action) }}</td>
                                    <td><?php echo date("d-m-Y H:i", convertTimeZone($c_log->dt)); ?></td>
                                    <td>{{ c_log.comment }}</td>
                                </tr>
                            {% endfor %}
                            </thead>
                            <tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

{% endif %}
