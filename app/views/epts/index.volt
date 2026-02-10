<div class="alert alert-warning">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>Внимание!</strong> Для корректной работы сайта, рекомендуем вам очистить кэш браузера(обновите страницу через <strong>CTRL + F5 или CTRL+SHIFT+R </strong>).
</div>

<div class="row">
    <div class="col-4">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("epts_integration") }}
            </div>
            <div class="card-body">
                <form action="/epts/sendRequest" id="eptsRequestForm" method="POST" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <label><b>Выберите тип запроса:</b><span style="color:red">*</span></label>
                    <div class="row mt-2">
                        <div class="col">
                            <div class="btn-group" data-toggle="buttons">
                                <label class="btn btn-wide btn-warning active">
                                    <input type="radio" name="type" class="eptsOperationType" value="0" required {{ (type == '0') ?  'checked="checked"' : '' }}>  По VIN-коду
                                </label>
                                <label class="btn btn-wide btn-warning">
                                    <input type="radio" name="type" class="eptsOperationType" value="1" {{ (type == '1') ?  'checked="checked"' : '' }}> По уникальному номеру
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <label id="eptsColumnName"><b>{{ (type == 0) ? 'Введите VIN:' : 'Введите уникальный номер:' }}</b><span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="uniqueNumber" placeholder="XXXXXXXXXXXXXXXXX"
                               value="{{ unique_number ? unique_number : '' }}" minlength="15" maxlength="17" autocomplete="off" required>
                        <small class="form-text text-muted" id="eptsHelpMSG">
                            {{ (type == 0) ? 'VIN-код должен содержать в себе только символы на латинице и цифры, не больше 17 символов.' : 'Уникальный номер должен содержать в себе только символы на латинице и цифры,, не больше 15 символов.' }}
                        </small>
                    </div>
                    <div class="form-group mt-2">
                        <label for="base_on"><b>Комментарий: </b><span style="color:red">*</span></label>
                        <textarea class="form-control" name="base_on" rows="3" required>{{ base_on ? base_on : '' }}</textarea>
                        <small id="base_on" class="form-text text-muted">Введите на основании чего вы делаете запрос</small>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary eptsRequestSubmitBtn">
                        <span class="spinner-border spinner-border-sm" id="epts_request_spinner" style="display:none"></span>
                        Отправить запрос
                    </button>
                    <a href="/epts/resetPage" class="btn btn-danger ml-3">
                        <i data-feather="refresh-cw" width="16" height="16"> </i>
                        Очистить все
                    </a>
                </form>
            </div>
        </div>
    </div>

    {% if current_request is defined and current_request != null %}
        {% if current_request.status_code == 200 %}
            <div class="col-8">
                <div class="card mt-3">
                    <div class="card-header bg-success text-light">
                        Статус запроса
                    </div>
                    <div class="card-body">

                        <div class="row">
                            <div class="col-6">
                                <strong>Сервер ШЭП: </strong>
                                <label class="sr-only" for="showMsgSHEP"></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text badge-success">
                                            {{ current_request.shep_status_code }}
                                        </div>
                                        <label class="input-group-text badge-info" id="showMsgSHEP">
                                            <strong> {{ current_request.shep_status_message }} </strong>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6">
                                <strong>Сервер АО НИТ: </strong>
                                <label class="sr-only" for="showMsgNIT"></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text badge-success">
                                            {{ current_request.status_code }}
                                        </div>
                                        <label class="input-group-text badge-info" id="showMsgNIT">
                                            <strong> {{ current_request.description }} </strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <tbody>
                                <tr>
                                    <td>Дата и время запроса:</td>
                                    <td>
                                        <strong>
                                            <?php if($current_request && $current_request->created_at) {?>
                                            <?php echo $current_request->request_time ? date("d.m.Y H:i:s", $current_request->request_time) : '';?>
                                            <?php }?>
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Запрошенное значение:</td>
                                    <td><strong>{{ current_request.request_num }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Тип запроса:</td>
                                    <td><strong>{{ (current_request.operation_type == 1) ? 'По уникальному номеру' : 'По VIN-коду' }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Message ID:</td>
                                    <td><strong>{{ current_request.message_id }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Session ID:</td>
                                    <td><strong>{{ current_request.session_id }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Время выполнения запроса:</td>
                                    <td><strong><i> {{ current_request.execution_time }} sec</i></strong></td>
                                </tr>
                                <tr>
                                    <td>Response Date:</td>
                                    <td><strong>{{ date(current_request.response_date) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Статус электронного паспорта:</td>
                                    <td>
                                        {{ current_request.digital_passport_status == 'действующий' ?
                                        '<span class="badge badge-success">'~current_request.digital_passport_status~'</span>' :
                                        '<span class="badge badge-secondary">'~current_request.digital_passport_status~'</span>' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>VIN:</td>
                                    <td><strong>{{ current_request.vin }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Уникальный номер:</td>
                                    <td><strong>{{ current_request.unique_code }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Данные(полученных) с ЭПТС:</td>
                                    <td>
                                        <a href="#" data-toggle="modal" data-id="{{ current_request.green_response }}" data-target=".epts_info_modal" id="displayEPTSInfo">
                                            Данные с ЭПТС
                                            <icon data-feather="help-circle" color="green" width="20" height="20"></icon>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Выписка:</td>
                                    <td>
                                        {% if session.get('epts_pdf_base64') %}
                                            <a href="#" data-toggle="modal" data-id="{{ current_request.green_response }}" data-target="#modalEptsPdf" id="viewEptsPdfBtn">
                                                Электронный паспорт &nbsp;
                                                <icon data-feather="file-text" color="green" width="20" height="20"></icon>
                                            </a>
                                        {% else %}
                                            <span class="badge badge-danger">Отсутствует</span>
                                        {% endif %}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Изображение ТС:</td>
                                    <td>
                                        {% if session.get('epts_image_base64') %}
                                            <a href="#" data-toggle="modal" data-id="{{ current_request.green_response }}" data-target="#modalEptsImage" id="viewEptsImageBtn">
                                                Изображение &nbsp;
                                                <icon data-feather="image" color="green" width="20" height="20"></icon>
                                            </a>
                                        {% else %}
                                            <span class="badge badge-danger">Отсутствует</span>
                                        {% endif %}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Дата и время(получ.данных):</td>
                                    <td><strong>
                                            <?php if($current_request && $current_request->created_at) {?>
                                            <?php echo $current_request->created_at ? date("d.m.Y H:i:s", $current_request->created_at) : '';?>
                                            <?php } ?>
                                        </strong>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        {% if auth.isAdminSoft() %}
                            <div class="row mt-3">
                                <div class="btn-group ml-3">
                                    <a href="/epts/viewXml/{{ current_request.request }}" class="btn btn-outline-primary" target="_blank">
                                        <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                        <span> Запрос(request).xml</span>
                                    </a>
                                    <a href="/epts/downloadXml/{{ current_request.request }}" class="btn btn-outline-primary" target="_blank">
                                        <i class="fa fa-download" aria-hidden="true"></i>
                                    </a>
                                </div>
                                {% if current_request.green_response != null%}
                                    <div class="btn-group ml-3">
                                        <a href="/epts/viewXml/{{ current_request.green_response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> GreenResponse(v1).xml </span>
                                        </a>
                                        <a href="#" data-toggle="modal" class="btn btn-outline-info" data-id="{{ current_request.green_response }}" data-target=".xml_view_modal" id="showResponseOnXMLViewer">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> GreenResponse(v2).xml </span>
                                        </a>
                                        <a href="/epts/downloadXml/{{ current_request.green_response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                {% else %}
                                    <span class="btn btn-outline-danger ml-3"> Отсутствует 'green_response.xml' файл</span>
                                {% endif %}

                                {% if current_request.response != null%}
                                    <div class="btn-group ml-3">
                                        <a href="/epts/viewXml/{{ current_request.response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> Response(v1).xml </span>
                                        </a>
                                        <a href="#" data-toggle="modal" class="btn btn-outline-info" data-id="{{ current_request.response }}" data-target=".xml_view_modal" id="showResponseOnXMLViewer">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> Response(v2).xml </span>
                                        </a>
                                        <a href="/epts/downloadXml/{{ current_request.response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                {% else %}
                                    <span class="btn btn-outline-danger"> Отсутствует 'response.xml' файл</span>
                                {% endif %}
                            </div>
                        {% endif %}

                    </div>
                    <div class="card-footer">
                        <a href="/epts/download/{{ current_request.id }}" class="btn btn-primary btn-block" target="_blank">
                            Скачать справку
                            <icon data-feather="download" width="18" height="18"></icon>
                        </a>
                    </div>
                </div>
            </div>
        {% else %}
            <div class="col-8">
                <div class="card mt-2">
                    <div class="card-header bg-danger text-light">
                        Статус запроса
                    </div>

                    <div class="card-body">
                        <div class="row">
                            {% if(current_request.shep_status_code) %}
                                <div class="col-6">
                                    <strong>Сервер ШЭП: </strong>
                                    <label class="sr-only" for="showMsgSHEP"></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text badge-{{ ( current_request.shep_status_code == 'Success') ? 'success' : 'danger' }}">
                                                {{ current_request.shep_status_code }}
                                            </div>
                                            <label class="input-group-text badge-warning" id="showMsgSHEP">
                                                <strong> {{ current_request.shep_status_message }} </strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            {% endif %}

                            {% if(current_request.status_code) %}
                                <div class="col-6">
                                    <strong>Сервер АО НИТ: </strong>
                                    <label class="sr-only" for="showMsgNIT"></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text badge-danger">
                                                {{ current_request.status_code }}
                                            </div>
                                            <label class="input-group-text badge-warning" id="showMsgNIT">
                                                <strong> {{ current_request.description }} </strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            {% endif %}
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <tbody>
                                <tr>
                                    <td>Дата и время запроса:</td>
                                    <td><strong>
                                            <?php if($current_request && $current_request->created_at) {?>
                                            <?php echo date("d.m.Y H:i:s", $current_request->request_time);?>
                                            <?php } ?>
                                        </strong></td>
                                </tr>
                                <tr>
                                    <td>Запрошенное значение:</td>
                                    <td><strong>{{ current_request.request_num }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Тип запроса:</td>
                                    <td><strong>{{ (current_request.operation_type and current_request.operation_type == 1) ? 'По уникальному номеру' : 'По VIN-коду' }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Message ID:</td>
                                    <td><strong>{{ current_request.message_id }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Session ID:</td>
                                    <td><strong>{{ current_request.session_id }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Время выполнения запроса:</td>
                                    <td><strong><i> {{ current_request.execution_time }} sec</i></strong></td>
                                </tr>
                                <tr>
                                    <td>Response Date:</td>
                                    <td><strong>{
<?php
                                            if (!empty($current_request->response_date)) {
                                            echo date(
                                            'Y-m-d',
                                            strtotime($current_request->response_date)
                                            );
                                            } else {
                                            echo '';
                                            }
                                            ?>
                                        </strong></td>
                                </tr>
                                <tr>
                                    <td>Дата и время(получ.данных):</td>
                                    <td><strong>
                                            <?php if($current_request && $current_request->created_at) {?>
                                            <?php echo date("d.m.Y H:i:s", $current_request->created_at);?>
                                            <?php } ?>
                                        </strong></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        {% if auth.isAdminSoft() %}
                            <div class="row mt-3">
                                {% if current_request.request %}
                                    <div class="btn-group ml-3">
                                        <a href="/epts/viewXml/{{ current_request.request }}" class="btn btn-outline-primary" target="_blank">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> Запрос(request).xml</span>
                                        </a>
                                        <a href="/epts/downloadXml/{{ current_request.request }}" class="btn btn-outline-primary" target="_blank">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                {% else %}
                                    <span class="btn btn-outline-danger ml-3"> Отсутствует 'request.xml' файл</span>
                                {% endif %}

                                {% if current_request.green_response %}
                                    <div class="btn-group ml-3">
                                        <a href="/epts/viewXml/{{ current_request.green_response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> GreenResponse(v1).xml</span>
                                        </a>
                                        <a href="#" data-toggle="modal" class="btn btn-outline-info" data-id="{{ current_request.green_response }}" data-target=".xml_view_modal" id="showGreenResponseOnXMLViewer">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> GreenResponse(v2).xml </span>
                                        </a>
                                        <a href="/epts/downloadXml/{{ current_request.green_response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                {% else %}
                                    <span class="btn btn-outline-danger ml-3"> Отсутствует 'green_response.xml' файл</span>
                                {% endif %}

                                {% if current_request.response %}
                                    <div class="btn-group ml-3">
                                        <a href="/epts/viewXml/{{ current_request.response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> Response(v1).xml </span>
                                        </a>
                                        <a href="#" data-toggle="modal" class="btn btn-outline-info" data-id="{{ current_request.response }}" data-target=".xml_view_modal" id="showResponseOnXMLViewer">
                                            <i class="fa fa-file-code-o" aria-hidden="true"></i> &nbsp;
                                            <span> Response(v2).xml </span>
                                        </a>
                                        <a href="/epts/downloadXml/{{ current_request.response }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                {% else %}
                                    <span class="btn btn-outline-danger ml-3"> Отсутствует 'response.xml' файл</span>
                                {% endif %}
                            </div>
                        {% endif %}
                    </div>
                </div>
            </div>
        {% endif %}
    {% endif %}
</div>

{% if current_request is defined and current_request != null %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">
                    Похожие список запросов(логи)
                </div>
                <div class="card-body" id="EPTS_LOG_DIV">
                    <input type="hidden" value="{{ session.get('epts_uniqueNumber') ? session.get('epts_uniqueNumber') : NULL }}" id="eptsUniqueNum">
                    <table id="eptsRequestList" class="display" cellspacing="0" width="100%">
                        <thead>
                        <th>{{ t._("Дата запроса") }}</th>
                        <th>{{ t._("Запрошенное значение") }}</th>
                        <th>{{ t._("Тип запроса") }}</th>
                        <th>{{ t._("Статус") }}</th>
                        <th>{{ t._("Данные с ЭПТС") }}</th>
                        <th>{{ t._("Файлы") }}</th>
                        <th>{{ t._("Справка") }}</th>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
{% endif %}