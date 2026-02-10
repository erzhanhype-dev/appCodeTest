<div class="alert alert-warning">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>Внимание!</strong> Для корректной работы сайта, рекомендуем вам очистить кэш браузера(обновите страницу
    через <strong>CTRL + F5 или CTRL+SHIFT+R </strong>).
</div>


<input type="hidden" id="kapLogId"
        {% if current_request is defined and current_request is not null %}
            value="{{ current_request.id }}"
        {% endif %}
/>
<div class="row">
    <div class="col-5">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("kap-check") }}
            </div>
            <div class="card-body">
                <form action="/kap_request/sendRequest" id="kapRequestForm" method="POST" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <label><b>Выберите тип запроса:</b></label>
                    <div class="row mt-2">
                        <div class="col">
                            <div class="btn-group" data-toggle="buttons">
                                <label class="btn btn-wide btn-info active" for="kapOperationType1">
                                    <input type="radio" name="req_type" class="kapOperationType" id="kapOperationType1"
                                           value="0" {{ (session.get('kap_req_type') == '0') ? 'checked="checked"' : NULL }}
                                           required> По VIN-коду
                                </label>
                                <label class="btn btn-wide btn-info" for="kapOperationType2">
                                    <input type="radio" name="req_type" class="kapOperationType" id="kapOperationType2"
                                           value="1" {{ (session.get('kap_req_type') == '1') ? 'checked="checked"' : NULL }}>
                                    По ГРНЗ
                                </label>

                                {% if auth.isAdminSoft() %}
                                    <label class="btn btn-wide btn-info" for="kapOperationType3">
                                        <input type="radio" name="req_type" class="kapOperationType"
                                               id="kapOperationType3"
                                               value="2" {{ (session.get('kap_req_type') == '2') ? 'checked="checked"' : NULL }}>
                                        По ИИН/БИН
                                    </label>
                                {% endif %}
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label id="kapColumnName"><b>Введите значение</b><span style="color:red">*</span></label>
                        <input type="text" class="form-control text-uppercase" name="req_value"
                               placeholder="XXXXXXXXXXXXXXXXX"
                               value="{{ session.get('kap_req_value') ? session.get('kap_req_value') : NULL }}"
                               minlength="4" maxlength="17" autocomplete="off" required id="kapOperationValue">
                        <small class="form-text text-muted" id="kapHelpMSG">
                            {% if session.get('kap_req_type') == 0 %}
                                {{ 'VIN-код должен содержать в себе только символы на латинице и цифры, не больше 17 символов.' }}
                            {% elseif session.get('kap_req_type') == 1 %}
                                {{ 'ГРНЗ должен содержать в себе только символы на латинице и цифры' }}
                            {% elseif session.get('kap_req_type') == 2 %}
                                {{ 'ИИН\БИН должен содержать в себе только цифры, не больше 12 символов.' }}
                            {% endif %}
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="base_on"><b>Укажите основание: </b><span style="color:red">*</span></label>
                        <textarea class="form-control" name="comment" rows="3"
                                  required>{{ session.get('kap_comment') ? session.get('kap_comment') : NULL }}</textarea>
                        <small id="base_on" class="form-text text-muted">Введите на основании чего вы делаете
                            запрос</small>
                    </div>
                    <button type="submit" class="btn btn-primary kapRequestSubmitBtn">
                        <span class="spinner-border spinner-border-sm" id="kap_request_spinner"
                              style="display:none"></span>
                        Отправить запрос
                    </button>
                    <a href="/kap_request/resetPage" class="btn btn-danger ml-3">
                        <i data-feather="refresh-cw" width="16" height="16"></i>
                        Очистить все
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Result section -->
    {% if current_request is defined and current_request is not null %}
    {% if current_request.payload_status == 200 %}
    <div class="col-7">
        <div class="card mt-3">
            <div class="card-header bg-success text-light">
                Статус запроса
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <strong>{{ current_request.payload_message_ru }} !</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <tbody>
                        <tr>
                            <td>ФИО:</td>
                            <td><strong> {{ auth.fio }}</strong></td>
                        </tr>
                        <tr>
                            <td>Дата и время запроса:</td>
                            <td><strong>{{ date('d.m.Y H:i:s', current_request.req_time) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Запрошенное значение:</td>
                            <td><strong>{{ current_request.req_value }}</strong></td>
                        </tr>
                        <tr>
                            <td>Тип запроса:</td>
                            <td><strong>По {{ current_request.req_type }}</strong></td>
                        </tr>
                        <tr>
                            <td>Актуальный статус:</td>
                            <td><strong>{{ lastStatus }}</strong></td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div style="padding:5px;">
                    {% if auth.isAdminSoft() or auth.isAdminSec() or auth.isAdmin() %}
                        <div class="btn-group mr-3">
                            <a href="/kap_request/downloadXml/{{ current_request.request }}"
                               class="btn btn-outline-primary"
                               target="_blank">
                                <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                <span> Запрос(request).xml</span>
                            </a>
                            <a href="/kap_request/downloadXml/{{ current_request.request }}"
                               class="btn btn-outline-primary" target="_blank">
                                <i class="fa fa-download" aria-hidden="true"></i>
                            </a>
                        </div>
                        {% if current_request.response != null or current_request.response != null %}
                            <div class="btn-group mr-3">
                                {% if current_request.response != null %}
                                    <a href="/kap_request/downloadXml/{{ current_request.response }}"
                                       class="btn btn-outline-success" target="_blank">
                                        <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                        <span> Ответ(response).xml</span>
                                    </a>
                                {% endif %}
                                {% if current_request.response != null %}
                                    <a href="/kap_request/downloadXml/{{ current_request.response }}"
                                       class="btn btn-outline-success" target="_blank">
                                        <i class="fa fa-download" aria-hidden="true"></i>
                                    </a>
                                {% endif %}
                            </div>
                        {% else %}
                            <span class="btn btn-outline-danger mr-3"> Отсутствует 'response.xml' файл</span>
                        {% endif %}

                        <a href="#" type="submit" class="btn btn-green" id="kapDownloadExcelAction">
                            <icon data-feather="download" width="18" height="18"></icon>
                            Скачать в Excel
                        </a>
                    {% endif %}
                    <a href="/kap_request/download/{{ current_request.id }}" type="submit" class="btn btn-primary">
                        <icon data-feather="download" width="18" height="18"></icon>
                        Скачать справку
                    </a>
                </div>
            </div>
        </div>
    </div>
    {% else %}
    <div class="col-7">
        <div class="card mt-2">
            <div class="card-header bg-danger text-light">
                Статус запроса
            </div>
            <div class="card-body">
                {% if current_request.payload_message_ru != null %}
                    <div class="alert alert-danger">
                        <strong>{{ current_request.payload_message_ru }}!</strong>
                    </div>
                    <div class="alert alert-danger">
                        <strong>{{ current_request.payload_message_kz }}!</strong>
                    </div>
                {% else %}
                    <div class="alert alert-danger">
                        <strong> {{ current_request.code }} ({{ current_request.message }})</strong>
                    </div>
                {% endif %}
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <tbody>
                        <tr>
                            <td>Дата и время запроса:</td>
                            <td><strong>{{ date('d.m.Y H:i:s', current_request.req_time) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Запрошенное значение:</td>
                            <td><strong>{{ current_request.req_value }}</strong></td>
                        </tr>
                        <tr>
                            <td>Тип запроса:</td>
                            <td><strong>По {{ current_request.req_type }}</strong></td>
                        </tr>
                        <tr>
                            <td>Message Id:</td>
                            <td><strong>{{ current_request.message_id }}</strong></td>
                        </tr>
                        <tr>
                            <td>Session Id:</td>
                            <td><strong>{{ current_request.session_id }}</strong></td>
                        </tr>
                        <tr>
                            <td>ResponseDate:</td>
                            <td><strong>{{ date(current_request.response_date) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Время выполнения запроса:</td>
                            <td><strong><i> {{ current_request.execution_time }} sec</i></strong></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                {% if auth.isAdminSoft() or auth.isAdminSec() or auth.isAdmin() %}
                    <div style="padding:5px;">
                        <div class="btn-group mr-3">
                            <a href="/kap_request/downloadXml/{{ current_request.request }}"
                               class="btn btn-outline-primary"
                               target="_blank">
                                <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                <span> Запрос(request).xml</span>
                            </a>
                            <a href="/kap_request/downloadXml/{{ current_request.request }}"
                               class="btn btn-outline-primary" target="_blank">
                                <i class="fa fa-download" aria-hidden="true"></i>
                            </a>
                        </div>
                        {% if current_request.response != null or current_request.response != null %}
                            <div class="btn-group mr-3">
                                {% if current_request.response != null %}
                                    <a href="/kap_request/downloadXml/{{ current_request.response }}"
                                       class="btn btn-outline-success" target="_blank">
                                        <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                        <span> Ответ(response).xml</span>
                                    </a>
                                {% endif %}
                                {% if current_request.response != null %}
                                    <a href="/kap_request/downloadXml/{{ current_request.response }}"
                                       class="btn btn-outline-success" target="_blank">
                                        <i class="fa fa-download" aria-hidden="true"></i>
                                    </a>
                                {% endif %}
                            </div>
                        {% else %}
                            <span class="btn btn-outline-danger mr-3"> Отсутствует 'response.xml' файл</span>
                        {% endif %}
                    </div>
                {% endif %}
                <a href="/kap_request/download/{{ current_request.id }}" type="submit" class="btn btn-primary">
                    <icon data-feather="download" width="18" height="18"></icon>
                    Скачать справку
                </a>
            </div>
        </div>
    </div>
</div>
{% endif %}
  {% endif %}
<!-- /Result section -->
</div>
{% if current_request is defined and current_request is not null %}
    {% if current_request.payload_status == 200 %}
        <div class="card card-body mt-2"
             style="display: flex;flex-direction: row;align-items: flex-start;overflow-x: scroll;width: 100%;">
            <table border="1" style="border-collapse: collapse; width: 100%;" class="table table-bordered table-sm"
                   id="kap_table">
                <thead>
                <tr>
                    <td><b>Параметры</b></td>
                    {% for i in 0..(items|length - 2) %}
                        <td><b>#{{ i + 1 }}</b></td>
                    {% endfor %}
                </tr>
                </thead>
                <tbody>
                <?php
                function getColor($index) {
                    if ($index >= 1 && $index <= 24) {
                return '#324f67';
                } elseif ($index > 24 && $index <= 35) {
                return '#e36c14';
                } else {
                return '#319d76';
                }
                }

                $maxRows = max(array_map('count', $items));
                for ($i = 0; $i < $maxRows; $i++) {
                echo "
                <tr>";
                    foreach ($items as $column) {
                    $color = getColor($i + 1);
                    echo "
                    <td style='color: $color;'>" . ($column[$i] ?? '-') . "</td>
                    ";
                    }
                    echo "
                </tr>
                ";
                }
                ?>
                </tbody>
            </table>
        </div>
    {% endif %}
{% endif %}
