<h3>{{ t._("edit-good") }}</h3>
<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("edit-good") }} {{ good.id }} (заявка
                #{{ good.profile_id }})
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-block">
                    <a class="btn btn-primary" href="/goods/correction/{{ good.id }}">Редактирование</a>
                    <a class="btn btn-danger" href="/goods/annulment/{{ good.profile_id }}">Аннулирование</a>
                </div>
                <hr>

                <h2 class="h4 mb-3">{{ t._("edit-good") }}</h2>
                <div class="tab-content p-3">
                    <!-- Edit form -->
                    <form id="formCorrectionRequestSign" action="/goods/correction/{{ good.id }}" method="POST"
                          enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                        <input type="hidden" name="good_id" value="{{ good.id }}">
                        <input type="hidden" name="correction_data" value="{{ correction_data }}">
                        <input type="hidden" name="correction_sum" value="{{ good.goods_cost }}">
                        <div class="form-group">
                            <label class="form-label">{{ t._("goods-weight") }}</label>
                            <div class="controls">
                                <input type="text" name="good_weight" class="form-control" value="{{ good.weight }}"
                                       required id="good_weight">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Номер счет-фактуры или ГТД</label>
                            <div class="controls">
                                <input type="text" name="good_basis" class="form-control" value="{{ good.basis }}"
                                       placeholder="XXXXX/XXXXX/XXXXX" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ t._("basis-date") }}</label>
                            <div class="controls">
                                <input type="text" name="basis_date" value="{{ date("d.m.Y", good.basis_date) }}"
                                       data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}"
                                       data-date-end-date="0d" class="form-control datepicker" onfocus="this.blur()"
                                       readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Дата импорта или реализации</label>
                            <div class="controls">
                                <input type="text" name="good_date" value="{{ date("d.m.Y", good.date_import) }}"
                                       data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}"
                                       data-date-end-date="0d" class="form-control datepicker" onfocus="this.blur()"
                                       readonly>
                            </div>
                        </div>
                        <div class="form-group" id="car_cat_group">
                            <label class="form-label">{{ t._("country") }}</label>
                            <select name="good_country" id="good_country" class="selectpicker form-control"
                                    data-live-search="true">
                                {% for cc in country %}
                                    <option value="{{ cc.id }}"{% if good.ref_country == cc.id %} selected="selected"{% endif %}>{{ t._(cc.name) }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class="form-group" id="car_cat_group">
                            <label class="form-label">{{ t._("tn-code") }}</label>
                            <select name="tn_code" id="tn_code" class="selectpicker form-control"
                                    data-live-search="true">
                                {% for code in tn_codes %}
                                    <option value="{{ code.id }}"{% if good.ref_tn == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <!-- товар в упаковке -->
                        <div class="form-group">
                            <button class="btn btn-primary" type="button" data-toggle="collapse"
                                    data-target="#tn-code-add-correction" aria-expanded="false">
                                Товар в упаковке?
                            </button>
                        </div>
                        <div class="collapse{% if good.ref_tn_add %} show{% endif %}" id="tn-code-add-correction">
                            <div class="form-group">
                                <label class="form-label">Упаковка</label>
                                <select name="tn_code_add" id="tn_code_add" class="selectpicker form-control"
                                        data-live-search="true">
                                    <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                                    {% for code in tn_codes_package %}
                                        <option value="{{ code.id }}"{% if good.ref_tn_add == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                                    {% endfor %}
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ t._("package-weight") }}</label>
                                <div class="controls">
                                    <input type="text" name="package_weight" class="form-control" placeholder="0.000"
                                           value="{{ good.package_weight }}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="EditCarDtSent" style="display:none;">
                            <label class="form-label">{{ t._("sent-date") }}</label>
                            <div class="controls">
                                <input type="text" name="md_dt_sent" data-provide="datepicker"
                                       data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
                                       class="form-control datepicker" value="{{ date('d.m.Y', md_dt_sent) }}"
                                       disabled="disabled">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ t._("comment") }}</label>
                            <textarea name="good_comment" id="correctionRequestComment" class="form-control"
                                      placeholder="Ваш комментарий ... "></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ t._("Загрузить файл") }}</label>
                            <input type="file" id="EditGoodFile" name="good_file" class="form-control-file">
                        </div>
                        <hr>

                        <div class="form-group" id="pay_file" style="display:none;">
                            <label class="form-label">
                                <b>{{ t._("pay_correction") }}</b>
                                <b class="text text-danger">*</b>
                            </label>
                            <input type="file" name="goods_pay_file" class="form-control-file">
                        </div>

                        <!-- конец товара в упаковке -->
                        <input type="hidden" name="profile" value="{{ good.profile_id }}">
                        <input type="hidden" value="{{ sign_data }}" name="hash" id="profileHash">
                        <textarea type="hidden" name="sign" id="profileSign" style="display: none;"></textarea>
                        <div class="row">
                            <div class="col-2">
                                <select id="storageSelect" class="form-control">
                                    <option value="PKCS12" selected>Файл</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-warning" id="correction_goods_submit">Подписать и сохранить изменения</button>
                                <a href="/order/view/{{ good.profile_id }}" class="btn btn-danger">Отмена</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("История изменения") }}</div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>{{ t._("ID") }}</th>
                        <th>{{ t._("Пользователь") }}</th>
                        <th>{{ t._("Действия") }}</th>
                        <th>{{ t._("Время") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if page.items|length %}
                        {% for log in page.items %}
                            <tr>
                                <td>{{ log.ccp_id }}</td>
                                <td>
                                    <?php
                        $id = $log->user_id;
                                    echo __getClientTitleByUserId($id);
                                    ?>
                                    <br>({{ log.iin }})
                                </td>
                                <td>{{ t._(log.action) | upper }}</td>
                                <td>{{ date("d.m.Y H:i:s", log.dt) }}</td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade correction_pay_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Уведомление</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="font-family: 'Montserrat'; font-size: 14px;">
                <p class="text-justify">
                    <b>Перед отправкой заявки на корректировку сертификата о внесении утилизационного платежа с
                        увеличением суммы утилизационного платежа необходимо осуществить доплату</b>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>




