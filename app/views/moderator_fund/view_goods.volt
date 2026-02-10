<?php
  $_RU_MONTH = array("январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь");
?>

<div class="alert alert-warning">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>Внимание!</strong> Для корректной работы сайта, рекомендуем вам очистить кэш браузера(обновите страницу
    через <strong>CTRL + F5 или CTRL+SHIFT+R </strong>).
</div>

<!-- заголовок -->
<h2>{{ t._("Заявка на финансирование") }}</h2>
<!-- /заголовок -->

<!-- /авто -->
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                <div class="row">
                    <div class="col-10">
                        {{ t._("Заявка №") }} {{ fund.number }}
                        <input type="hidden" value="{{ fund.id }}" id="moderatorFundViewId">
                    </div>
                    <?php ?>
                    {% if fund.approve == 'FUND_DONE' and auth is defined and auth.isSuperModerator() and (fund_cars|length > 0 or fund_goods|length > 0) %}
                        <div class="row ml-1">
                            <a href="/fund_correction/car_annulment_list/{{ fund.id }}" class="btn btn-danger">
                                <i data-feather="trash" width="14" height="14"></i> Аннулировать все
                            </a>
                        </div>
                    {% endif %}
                </div>
            </div>
            <div class="card-body">
                <table id="moderatorFundGoodsList" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>{{ t._("ID") }}</th>
                        <th>{{ t._("Вес") }}</th>
                        <th>{{ t._("Сумма, тенге") }}</th>
                        <th>{{ t._("код ТНВЭД") }}</th>
                        <th>{{ t._("Дата производства") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if fund_goods is not empty %}
                        {% for item in fund_goods %}
                            <tr>
                                <td>{{ item['id'] }}</td>
                                <td>{{ item['weight'] }}</td>
                                <td>{{ item['amount'] }}</td>
                                <td>
                                    {% if item['ref_tn_code']|length %}
                                        {{ item['ref_tn_code']['code'] }} | {{ item['ref_tn_code']['name'] }}
                                    {% endif %}
                                </td>
                                <td>{{ item['date_produce'] }}</td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- /товар(Аннулированные) -->
{% if deleted_goods is not empty %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-danger text-light">
                    {{ t._("Аннулированные и Отклоненные товары") }}
                </div>
                <div class="card-body">
                    <table id="moderatorFundGoodsCancelledList" class="display" cellspacing="0" width="100%">
                        <thead>
                        <tr class="">
                            <th>{{ t._("ID") }}</th>
                            <th>{{ t._("Вес") }}</th>
                            <th>{{ t._("Сумма, тенге") }}</th>
                            <th>{{ t._("код ТНВЭД") }}</th>
                            <th>{{ t._("Дата производства") }}</th>
                            <th>{{ t._("Статус") }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {% for item in deleted_goods %}
                            <tr>
                                <td>{{ item['id'] }}</td>
                                <td>{{ item['weight'] }}</td>
                                <td>{{ item['amount'] }}</td>
                                <td>
                                    {% if item['ref_tn_code']|length %}
                                        {{ item['ref_tn_code']['code'] }} | {{ item['ref_tn_code']['name'] }}
                                    {% endif %}
                                </td>
                                <td>{{ item['date_produce'] }}</td>
                                <td>{{ t._(item['status']) }}</td>
                            </tr>
                        {% endfor %}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
{% endif %}

{% if (fund.blocked or fund.approve == 'FUND_ANNULMENT') %}
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
                                          target="_blank"><?php echo $fn; ?>. {{ t._(file.type) }}
                                            <i>
                                                <?php
                                                $path = APP_PATH.'/private/fund/';
                                                if ($file->type == 'calculation_cost' || $file->type == 'other' ||
                                                $file->type == 'application') {
                                                $filename = $path . 'fund_' . $file->type . '_' . $file->id . '.'
                                                .$file->ext;
                                                }else{
                                                $filename = $path . $file->type . '_' . $file->id . '.' .$file->ext;
                                                }
                                                if (file_exists($filename)) {
                                                echo '('.date("d.m.Y H:i", convertTimeZone(filemtime($filename))).')';
                                                }else{
                                                echo '(Файл не найден !)';
                                                }
                                                ?>
                                            </i>
                                        </a>&nbsp;
                                        <a href="/fund/viewdoc/{{ file.id }}"
                                           class="btn btn-sm btn-primary preview{% if file.ext|upper == 'PDF' %}pdf{% endif %}"><i
                                                    data-feather="eye" width="14"
                                                    height="14"></i>&nbsp;{{ file.ext|upper }}</a>&nbsp;
                                        <a class="btn btn-xs btn-success" href="/fund/getdoc/{{ file.id }}"><i
                                                    data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                                    </p>
                                {% endif %}
                            {% endfor %}
                        {% endif %}
                        <hr>
                        <a href="/moderator_fund/download_zip/{{ fund.id }}" class="btn btn-primary"
                           title="Файлы в архиве" target="_blank">
                            <i data-feather="archive" width="14" height="14"></i> Скачать в архиве
                        </a>
                    </div>
                {% if !fund.blocked %}
                    <div class="col">
                        <form enctype="multipart/form-data" action="/fund/doc" method="POST">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="form-group" id="order">
                                <div class="controls">
                                    <select name="doc_type" class="form-control" style="width: 100%;">
                                        <option>-- тип документа не выбран --</option>
                                        <option value="gtd">ГТД</option>
                                        <option value="smr">СМР</option>
                                        <option value="acttrans">Акт приема-передачи</option>
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
                            <button type="submit" class="btn btn-success" name="button"{% if fund.blocked %}
                                    disabled{% endif %}>Загрузить документ
                            </button>&nbsp;
                        </form>
                    </div>
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>
{% endif %}

<?php if($auth->fund_stage == 'STAGE_NOT_SET'): ?>
{% if (fund_cars|length > 0 or fund_goods|length > 0) and (fund.approve == 'FUND_NEUTRAL' or fund.approve == 'FUND_DECLINED') and app_form %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Отправка заявки на рассмотрение") }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            {% if fund.approve == 'FUND_NEUTRAL' %}
                                <p>Это новая заявка. С ней еще ничего нельзя сделать.</p>
                            {% endif %}
                            {% if fund.approve == 'FUND_DECLINED' %}
                                <p>Это отклоненная заявка. С ней ничего нельзя сделать, пока её не отправят на
                                    рассмотрение заново.</p>
                            {% endif %}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}
  {% if (fund.approve == 'FUND_REVIEW') and app_form %}
      <div class="row">
          <div class="col">
              <div class="card mt-3">
                  <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                  <div class="card-body">
                      <!-- Проект заявление(Служебная записка) -->
                      <div class="row">
                          <div class="col">
                              <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                    class="btn btn-sm btn-secondary"
                                    target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                  <a class="btn btn-xs btn-success"
                                     href="/fund/viewtmp/<?php echo $fund->id; ?>/payment">
                                      <i data-feather="download" width="14" height="14"></i>&nbsp;
                                      Cкачать
                                  </a>&nbsp;
                              </p>
                          </div>
                      </div>
                      <hr>
                      <div class="row">
                          <div class="col">
                              {% if auth is defined and auth.isEmployee() %}
                                  <p>Заявка находится в процессе рассмотрения.</p>
                                  <a href="/moderator_fund/approve/{{ fund.id }}" class="btn btn-success">Одобрить</a>
                                  <button type="button" class="btn btn-primary" data-toggle="modal"
                                          data-target="#formDecline">Отклонить
                                  </button>
                              {% endif %}
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  {% endif %}

  {% if (fund_goods|length > 0 or fund_cars|length > 0) and (fund.approve == 'FUND_PREAPPROVED' and fund.sign_hof == '') and app_form %}
      <div class="row">
          <div class="col">
              <div class="card mt-3">
                  <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                  <div class="card-body">
                      <!-- Проект заявление(Служебная записка) -->
                      <div class="row">
                          <div class="col">
                              <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                    class="btn btn-sm btn-secondary"
                                    target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                  <a class="btn btn-xs btn-success"
                                     href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i data-feather="download"
                                                                                              width="14"
                                                                                              height="14"></i>&nbsp;Cкачать</a>&nbsp;
                              </p>
                          </div>
                      </div>
                      <hr>
                      <div class="row">
                          <div class="col">
                              <p>Заявка на финансирование одобрена и ожидает согласования с другими участниками
                                  процесса.</p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  {% endif %}
<?php endif; ?>

<?php if($auth->fund_stage == 'HOD'): ?>
{% if fund.approve == 'FUND_PREAPPROVED' and fund.sign_hod == '' %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <!-- Проект заявление(Служебная записка) -->
                    <div class="row">
                        <div class="col">
                            <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                  class="btn btn-sm btn-secondary"
                                  target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                <a class="btn btn-xs btn-success" href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена модератором и ожидает Вашей подписи.</p>
                            <form id="formFund" action="/moderator_fund/sign" method="POST">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                <input type="hidden" value="{{ fund.hash }}" name="fundHash" id="fundHash">
                                <input type="hidden" name="orderType" id="orderType">
                                <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                                <div class="row">
                                    {% if auth is defined and auth.isEmployee() %}
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-success signFundBtn" data-role="hod">
                                                Подписать
                                            </button>
                                            <a href="/moderator_fund/stage_decline/{{ fund.id }}"
                                               class="btn btn-danger">Вернуть модератору</a>
                                        </div>
                                    {% endif %}
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}
<?php endif; ?>
<!-- /авто -->


<?php if($auth->fund_stage == 'FAD'): ?>
{% if fund.approve == 'FUND_PREAPPROVED' and fund.sign_hod != '' and fund.sign_fad == '' %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <!-- Проект заявление(Служебная записка) -->
                    <div class="row">
                        <div class="col">
                            <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                  class="btn btn-sm btn-secondary"
                                  target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                <a class="btn btn-xs btn-success" href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена модератором и ожидает Вашей подписи.</p>
                            <form id="formFund" action="/moderator_fund/sign" method="POST">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                <input type="hidden" value="{{ fund.hash }}" name="fundHash" id="fundHash">
                                <input type="hidden" name="orderType" id="orderType">
                                <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                                <div class="row">
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-success signFundBtn" data-role="fad">
                                            Подписать
                                        </button>
                                        <a href="/moderator_fund/stage_decline/{{ fund.id }}" class="btn btn-danger">Вернуть
                                            модератору</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}
<?php endif; ?>

<?php if($auth->fund_stage == 'HOP'): ?>
{% if fund.approve == 'FUND_PREAPPROVED' and fund.sign_hod != '' and fund.sign_fad != '' and fund.sign_hop == '' %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <!-- Проект заявление(Служебная записка) -->
                    <div class="row">
                        <div class="col">
                            <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                  class="btn btn-sm btn-secondary"
                                  target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                <a class="btn btn-xs btn-success" href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена модератором и ожидает Вашей подписи.</p>
                            <form id="formFund" action="/moderator_fund/sign" method="POST">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                <input type="hidden" value="{{ fund.hash }}" name="fundHash" id="fundHash">
                                <input type="hidden" name="orderType" id="orderType">
                                <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                                <div class="row">
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-success signFundBtn" data-role="hop">
                                            Подписать
                                        </button>
                                        <a href="/moderator_fund/stage_decline/{{ fund.id }}" class="btn btn-danger">Вернуть
                                            модератору</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}
<?php endif; ?>

<?php if($auth->fund_stage == 'HOF'): ?>
{% if fund.approve == 'FUND_PREAPPROVED' and fund.sign_hod != '' and fund.sign_fad != '' and fund.sign_hop != '' and fund.sign_hof == '' %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <!-- Проект заявление(Служебная записка) -->
                    <div class="row">
                        <div class="col">
                            <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                  class="btn btn-sm btn-secondary"
                                  target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                <a class="btn btn-xs btn-success" href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена модератором и ожидает Вашей подписи.</p>
                            <form id="formFund" action="/moderator_fund/sign" method="POST">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                <input type="hidden" value="{{ fund.hash }}" name="fundHash" id="fundHash">
                                <input type="hidden" name="orderType" id="orderType">
                                <textarea name="fundSign" id="fundSign" style="display: none;"></textarea>
                                <div class="row">
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-success signFundBtn" data-role="hof">
                                            Подписать
                                        </button>
                                        <a href="/moderator_fund/stage_decline/{{ fund.id }}" class="btn btn-danger">Вернуть
                                            модератору</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}
<?php endif; ?>

{% if fund.sign_hof != '' and auth.isAccountant() === false %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                <div class="card-body">
                    <!-- Проект заявление(Служебная записка) -->
                    <div class="row">
                        <div class="col">
                            <p>Заявка на финансирование одобрена всеми участниками процесса и передана на оплату.</p>
                            <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                  class="btn btn-sm btn-secondary"
                                  target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                <a class="btn btn-xs btn-success" href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i
                                            data-feather="download" width="14" height="14"></i>&nbsp;Cкачать</a>&nbsp;
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}

<?php if($auth->isAccountant()): ?>
{% if fund.sign_hod != '' and fund.sign_fad != '' and fund.sign_hop != '' and fund.sign_hof != '' %}
    {% if fund.reference == '' %}
        <div class="row">
            <div class="col">
                <div class="card mt-3">
                    <div class="card-header bg-dark text-light">{{ t._("Выполнение оплаты") }}</div>
                    <div class="card-body">
                        <!-- Проект заявление(Служебная записка) -->
                        <div class="row">
                            <div class="col">
                                <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                      class="btn btn-sm btn-secondary"
                                      target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                    <a class="btn btn-xs btn-success"
                                       href="/fund/viewtmp/<?php echo $fund->id; ?>/payment">
                                        <i data-feather="download" width="14" height="14"></i>&nbsp;
                                        Cкачать
                                    </a>&nbsp;
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col">
                                <p>Заявка на финансирование одобрена. Укажите референс оплаты, если она произведена.</p>
                                <form id="formFund" action="/moderator_fund/reference" method="POST">
                                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                    <input type="hidden" name="orderId" id="orderId" value="{{ pid }}">
                                    <input type="text" class="form-control" name="orderReference" id="orderReference"
                                           placeholder="Референс оплаты">
                                    <button type="submit" class="btn btn-success mt-1">Подписать</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {% else %}
        <div class="row">
            <div class="col">
                <div class="card mt-3">
                    <div class="card-header bg-dark text-light">{{ t._("Текущий статус заявки") }}</div>
                    <div class="card-body">
                        <!-- Проект заявление(Служебная записка) -->
                        <div class="row">
                            <div class="col">
                                <p><a href="/fund/viewtmp/<?php echo $fund->id; ?>/payment/"
                                      class="btn btn-sm btn-secondary"
                                      target="_blank">{{ t._('Подписанная заявка на оплату') }}</a>&nbsp;
                                    <a class="btn btn-xs btn-success"
                                       href="/fund/viewtmp/<?php echo $fund->id; ?>/payment"><i data-feather="download"
                                                                                                width="14"
                                                                                                height="14"></i>&nbsp;Cкачать</a>&nbsp;
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {% endif %}
{% endif %}
<?php endif; ?>


<!-- логи -->
<div class="row">
    <div class="col">
        <div class="card mt-3 mb-5">
            <div class="card-header bg-dark text-light">
                <div class="row ml-1">
                    <div>
                        Логи действий с заявкой
                    </div>
                    <div class="ml-auto mr-3">
                        <form id="getFundLogsForm" method="POST" action="/moderator_fund/get_logs/">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <input type="hidden" name="pid" value="{{ fund.id }}">
                            <button type="submit" class="btn btn-info"><span class="spinner-border spinner-border-sm"
                                                                             id="moderator_fund_view_logs_spinner"
                                                                             style="display: none"></span> Просмотр логи
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="fund_logs_table">
                        <thead>
                        <tr>
                            <th>{{ t._("Пользователь") }}</th>
                            <th style="max-width: 50%;">{{ t._("logged-action") }}</th>
                            <th>{{ t._("logged-date") }}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="text-center" id="fund_logs_body_spinner" style="display: none">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formDecline" tabindex="-1" aria-labelledby="formDecline" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Отклонение</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {% if auth is defined and auth.isEmployee() %}
                    <form action="/moderator_fund/decline/{{ fund.id }}" method="POST">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                        <textarea name="msg" id="msg" cols="30" rows="10" class="form-control"></textarea>
                        <button type="submit" class="btn btn-danger mt-3">Отклонить</button>
                    </form>
                {% endif %}
            </div>
        </div>
    </div>
</div>