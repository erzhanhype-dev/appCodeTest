{% if (cars is defined and cars|length) or (goods is defined and goods|length ) %}
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._('order-docs') }}</div>

                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            {% if files is defined and files|length %}
                                {% set profileIntl = profile['international_transporter'] is defined ? profile['international_transporter'] : 0 %}
                                {% set blocked = profile['blocked'] is defined and profile['blocked'] %}

                                {% if (has_int_transport is defined and has_int_transport > 0) and (tr is defined and tr['approve'] in ['NEUTRAL','DECLINED']) %}
                                    <div class="form-group">
                                        <div class="form-row">
                                            <div class="col-12">
                                                <label class="form-label"><b>{{ t._('Вы участвуете в международных перевозках?') }}</b></label>
                                                <div class="controls">
                                                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                                        <label id="ref_st_int_tr_yes"
                                                               class="btn btn-wide {{ profileIntl == 1 ? 'btn-success active' : 'btn-secondary' }} mr-2">
                                                            <input type="radio" name="order_type" value="YES"
                                                                    {% if profileIntl == 0 %} data-toggle="modal" data-id="{{ pid }}" data-target="#modalContract" id="viewContractBtn" {% endif %}>
                                                            {{ t._('Да') }}
                                                        </label>

                                                        {% if profileIntl == 0 %}
                                                            <label id="ref_st_int_tr_no"
                                                                   class="btn btn-wide btn-success active">
                                                                <input type="radio" name="order_type"
                                                                       value="NO"> {{ t._('Нет') }}
                                                            </label>
                                                        {% endif %}
                                                    </div>

                                                    <small id="ref_st" class="form-text text-muted">
                                                        {{ t._('Нажмите') }}
                                                        <b>{{ t._('Да') }}</b> {{ t._('если вы участвуете в международных перевозках (надо подписать заявление через ЭЦП),') }}
                                                        {{ t._('нажмите') }}
                                                        <b>{{ t._('Нет') }}</b> {{ t._('если не участвуете ...') }}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- modal -->
                                    <div class="modal" id="modalContract" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h4 class="modal-title">Просмотр заявление (№<b
                                                                id="statementProfileId"></b>)</h4>
                                                    <button type="button" class="close" data-dismiss="modal">&times;
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <iframe id="cotractPdfViewer" frameborder="0" width="100%"
                                                            height="600"></iframe>
                                                </div>
                                                <div class="modal-footer">
                                                    <div class="actions text-left">
                                                        <form id="formIntTrSign" action="/order/inttrappsign"
                                                              method="POST">
                                                            <input type="hidden" name="csrfToken"
                                                                   value="{{ csrfToken }}">
                                                            <input type="hidden" name="profile_id"
                                                                   value="{{ profile['id'] }}">
                                                            <input type="hidden" name="profileHash"
                                                                   id="intTrProfileHash" value="{{ sign_data }}">
                                                            <textarea name="profileSign" id="intTrProfileSign"
                                                                      style="display:none"></textarea>
                                                            <button type="button" class="btn btn-primary signIntTrApplicationBtn">
                                                                {{ t._('Сформировать и подписать заявление') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <button type="button" class="btn btn-danger"
                                                            data-dismiss="modal">{{ t._('Закрыть') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /modal -->
                                {% endif %}

                                {% for i, file in files %}
                                    <p class="mb-2">
                                        <a href="/order/viewdoc/{{ file['id'] }}" class="btn btn-sm btn-secondary"
                                           target="_blank">
                                            {{ i + 1 }}. {{ t._(file['type']) }}
                                            {% if file['type'] in ['application','app_international_transport'] %}
                                                {% set filename = constant('APP_PATH') ~ '/private/docs/' ~ file['id'] ~ '.' ~ file['ext'] %}
                                                <i>
                                                    {% if file_exists(filename) %}
                                                        ({{ date("d.m.Y H:i", filemtime(filename)) }})
                                                    {% else %}
                                                        ({{ t._("Файл не найден !") }})
                                                    {% endif %}
                                                </i>
                                            {% endif %}
                                        </a>

                                        <a href="/order/viewdoc/{{ file['id'] }}"
                                           class="btn btn-sm btn-primary preview{{ file['ext']|upper == 'PDF' ? ' pdf' : '' }}">
                                            <i data-feather="eye" width="14"
                                               height="14"></i>&nbsp;{{ file['ext']|upper }}
                                        </a>

                                        <a class="btn btn-sm btn-success" href="/order/getdoc/{{ file['id'] }}">
                                            <i data-feather="download" width="14"
                                               height="14"></i>&nbsp;{{ t._('Cкачать') }}
                                        </a>

                                        {% if not blocked %}
                                            <a class="btn btn-sm btn-danger" href="/order/rmdoc/{{ file['id'] }}">
                                                <i data-feather="x-circle" width="14"
                                                   height="14"></i>&nbsp;{{ t._('delete') }}
                                            </a>
                                        {% endif %}
                                    </p>
                                {% endfor %}
                            {% endif %}
                        </div>

                        {% if not (profile['blocked'] is defined and profile['blocked']) %}
                            <div class="col">
                                <label type="button" data-toggle="modal" data-target=".bd-example-modal-lg">
                                    <b>{{ t._('Перечень необходимых документов') }}</b>
                                    <icon data-feather="help-circle" color="green" width="18" height="18"></icon>
                                </label>

                                <div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog"
                                     aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ t._('Какие документы загружать?') }}</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="card card-body text-justify">
                                                <h6>Для произведенных в Республике Казахстан:</h6>
                                                <p>- паспорт транспортного средства (самоходной машины), подтверждающего
                                                    производство продукции (при наличии),
                                                    и накладной на отпуск запасов на сторону по форме, утвержденной в
                                                    соответствии с законодательством Республики
                                                    Казахстан о бухгалтерском учете и финансовой отчетности</p>

                                                <h6>Для ввезенных (импортированных) в Республику Казахстан из
                                                    государств, не являющихся членами ЕАЭС:</h6>
                                                <p>- таможенная декларация, в соответствии с которой продукция была
                                                    помещена под таможенную процедуру выпуска для
                                                    внутреннего потребления;</p>
                                                <p>- талон о прохождении государственного контроля (либо копии талона о
                                                    прохождении государственного контроля) для
                                                    подтверждения даты пересечения Государственной границы Республики
                                                    Казахстан;</p>
                                                <p>свидетельство о безопасности конструкции транспортного средства или
                                                    одобрения типа транспортного средства для
                                                    автотранспортных средств и сертификата соответствия для самоходной
                                                    сельскохозяйственной техники.</p>

                                                <h6>Для ввезенных (импортированных) в Республику Казахстан из
                                                    государств, являющихся членами ЕАЭС:</h6>
                                                <p>- талон о прохождении государственного контроля (либо копии талона о
                                                    прохождении государственного контроля) для
                                                    подтверждения даты пересечения Государственной границы Республики
                                                    Казахстан (при наличии);</p>
                                                <p>- паспорт транспортного средства (самоходной машины) или
                                                    свидетельство о регистрации транспортного средства
                                                    государства – члена Евразийского экономического союза;</p>
                                                <p>- свидетельство о безопасности конструкции транспортного средства или
                                                    одобрение типа транспортного средства для
                                                    автотранспортных средств и сертификат соответствия для самоходной
                                                    сельскохозяйственной техники;</p>
                                                <p>- приемо-сдаточный акт, подтверждающий передачу от экспортера
                                                    государства-члена Евразийского экономического союза
                                                    импортеру в Республике Казахстан, с указанием идентификационного
                                                    номера передаваемых автотранспортных средств либо
                                                    идентификационного или заводского номера самоходной
                                                    сельскохозяйственной техники (при наличии);</p>
                                                <p>- транспортную накладную (с указанием идентификационного номера
                                                    ввозимых автомобилей либо идентификационного или
                                                    заводского номера самоходной сельскохозяйственной техники) или
                                                    международную транспортную накладную CMR, подтверждающую
                                                    международную перевозку (с указанием идентификационного номера
                                                    ввозимых автотранспортных средств либо идентификационного
                                                    или заводского номера самоходной сельскохозяйственной техники) (при
                                                    наличии).</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <form enctype="multipart/form-data" action="/order/doc" method="POST">
                                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                                    <div class="form-group" id="order-doc-type">
                                        <div class="controls">
                                            <select name="doc_type" class="form-control" style="width:100%;">
                                                <option selected disabled>{{ t._('Выберите тип документа') }}</option>

                                                {% if profile['type'] == 'CAR' %}
                                                    <option value="regcertificate">{{ t._('Свидетельство о регистрации') }}</option>
                                                    <option value="techpass">{{ t._('Технический паспорт') }}</option>
                                                    <option value="customdec">{{ t._('Таможенная декларация') }}</option>
                                                    <option value="customtal">{{ t._('Талон о прохождении границы') }}</option>
                                                    <option value="ptsrts">PTS / SRTS</option>
                                                    <option value="dover">{{ t._('Доверенность') }}</option>
                                                    <option value="accepttypecar">{{ t._('Одобрение типа ТС') }}</option>
                                                    <option value="other">{{ t._('Другое') }}</option>
                                                {% elseif profile['type'] == 'GOODS' %}
                                                    <option value="regcertificate">{{ t._('Свидетельство о регистрации') }}</option>
                                                    <option value="invoiceimp">{{ t._('Счет фактура (импортера)') }}</option>
                                                    <option value="packlist">{{ t._('Упаковочный лист') }}</option>
                                                    <option value="trdocs">{{ t._('Транспортные накладные') }}</option>
                                                    <option value="prpassport">{{ t._('Паспорта продукции') }}</option>
                                                    <option value="importapp">{{ t._('Заявление о ввозе товаров') }}</option>
                                                    <option value="talgovcontrol">{{ t._('Талон о прохождении гос. контроля') }}</option>
                                                    <option value="intgoods">{{ t._('Международная товарная накладная') }}</option>
                                                    <option value="customgoods">{{ t._('Таможенная накладная') }}</option>
                                                    <option value="railwaysorder">{{ t._('Железнодорожная накладная') }}</option>
                                                    <option value="customdec">{{ t._('Таможенная декларация') }}</option>
                                                    <option value="other">{{ t._('Другое') }}</option>
                                                {% elseif profile['type'] == 'KPP' %}
                                                    <option value="kpp_invoice">{{ t._('Счет-фактура (инвойс)') }}</option>
                                                    <option value="kpp_invoice_translate">{{ t._('Перевод Счет-фактуры (инвойс)') }}</option>
                                                    <option value="kpp_certificate">{{ t._('Сертификат соответствия') }}</option>
                                                    <option value="kpp_waybill">{{ t._('Накладная на отпуск запасов') }}</option>
                                                    <option value="kpp_product_passport">{{ t._('Паспорт продукции (при наличии)') }}</option>
                                                    <option value="other">{{ t._('Другое') }}</option>
                                                {% endif %}
                                            </select>
                                            <small id="files_import_help" class="form-text text-muted">
                                                {{ t._('Размер файла нельзя превышать 50 мб !') }}
                                            </small>
                                        </div>
                                    </div>

{#                                    <input type="file" name="files_import" id="files_import"#}
{#                                           class="form-control-file hidden">#}

                                    <input type="file" name="files_import" id="files_import" class="form-control-file">

                                    <input type="hidden" name="profile_id" value="{{ profile['id'] }}">
                                    <button type="submit"
                                            class="btn btn-success">{{ t._('Загрузить документ') }}</button>
                                </form>

                                <hr>

                                <a href="#" data-toggle="modal" class="btn btn-warning" data-id="{{ profile['id'] }}"
                                   data-target=".order_sign_modal" id="orderSignBtn">
                                    {{ t._('Сформировать и подписать заявление') }}
                                </a>
                            </div>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endif %}