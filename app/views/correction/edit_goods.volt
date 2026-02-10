<h3>{{ t._("edit-good") }}</h3>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("edit-good") }} {{ good.id }} (заявка
                #{{ good.profile_id }})
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#editGoodTab">Редактирование</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#deleteGoodTab">Удаление</a>
                    </li>
                </ul>
                <div class="tab-content p-3">

                    <!-- Edit form -->
                    <div class="tab-pane fade show active" id="editGoodTab">

                        <h2 class="h4 mb-3">Редактирование товара</h2>

                        <form id="editGoodForm" action="/correction/edit_goods/{{ good.id }}" method="POST"
                              enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                            <div class="form-group">
                                <label class="form-label">{{ t._("goods-weight") }}</label>
                                <div class="controls">
                                    <input type="text" name="good_weight" class="form-control" value="{{ good.weight }}"
                                           required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Номер счет-фактуры или ГТД</label>
                                <div class="controls">
                                    <input type="text" name="good_basis" class="form-control" value="{{ good.basis }}"
                                           required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ t._("basis-date") }}</label>
                                <div class="controls">
                                    <input type="text" name="basis_date" value="{{ date("d.m.Y", good.basis_date) }}"
                                           data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}"
                                           data-date-end-date="0d" class="form-control datepicker">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Дата импорта или реализации</label>
                                <div class="controls">
                                    <input type="text" name="good_date" value="{{ date("d.m.Y", good.date_import) }}"
                                           data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}"
                                           data-date-end-date="0d" class="form-control datepicker">
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
                                        <option value="{{ code.id }}"{% if good.ref_tn == code.id %} selected="selected" {% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Упаковка</label>
                                <select name="tn_code_add" id="tn_code_add" class="selectpicker form-control"
                                        data-live-search="true">
                                    <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                                    {% for code in tn_codes %}
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

                            <div class="form-group">
                                <label class="form-label">{{ t._("Выберите способ расчета") }}</label><br>
                                <input type="radio" name="calculate_method" class="EditCarCalcMethod"
                                       value="0" {{ (good.calculate_method != null and good.calculate_method == 0) ? 'checked' : '' }}>
                                По дате импорта<br>
                                <input type="radio" name="calculate_method" class="EditCarCalcMethod"
                                       value="1" {{ (good.calculate_method != null and good.calculate_method == 1) ? 'checked' : '' }}>
                                По дате подачи заявки<br>
                            </div>
                            <div class="form-group"
                                 id="EditCarDtSent" {{ (good.calculate_method == 0) ? 'style="display:none;"' : '' }} >
                                <label class="form-label">{{ t._("sent-date") }}</label>
                                <div class="controls">
                                    <input type="text" name="md_dt_sent" data-provide="datepicker"
                                           data-date-start-date="{{ constant('STARTROP') }}"
                                           data-date-end-date="0d" class="form-control datepicker"
                                           value="{{ date("d.m.Y", md_dt_sent) }}"
                                           disabled="disabled">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("correction_initiator") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <select name="initiator" id="initiator" class="form-control">
                                    {% for item in initiators %}
                                        {% if initiator_id == '' %}
                                            <option value="{{ item.id }}">{{ t._(item.name) }}</option>
                                        {% else %}
                                            <option value="{{ item.id }}"{% if item.id == initiator_id %} selected{% endif %}>{{ t._(item.name) }}</option>
                                        {% endif %}
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">{{ t._("comment") }}</label>
                                <textarea name="good_comment" id="EditGoodComment" class="form-control"
                                          placeholder="Ваш комментарий ... " required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ t._("Загрузить файл") }}</label>
                                <input type="file" id="EditGoodFile" name="good_file" class="form-control-file"
                                       required>
                            </div>
                            <hr>


                            <!-- конец товара в упаковке -->
                            <input type="hidden" name="profile" value="{{ good.profile_id }}">
                            <input type="hidden" value="{{ sign_data }}" name="hash" id="EditGoodHash">
                            <textarea type="hidden" name="sign" id="EditGoodSign" style="display: none;"></textarea>
                            <div class="row">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-warning signEditGoodsBtn">Подписать и
                                        сохранить изменения
                                    </button>
                                    <a href="/correction/" class="btn btn-danger">Отмена</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Delete form -->
                    <div class="tab-pane fade" id="deleteGoodTab">

                        <h2 class="h4 mb-3">Удаление товара</h2>

                        <form id="deleteGoodForm" action="/correction/delete_goods/{{ good.id }}" method="POST"
                              enctype="multipart/form-data">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="form-group">
                                <label class="form-label">{{ t._("goods-weight") }}</label>
                                <div class="controls">
                                    <input type="text" name="good_weight" disabled="disabled" class="form-control"
                                           value="{{ good.weight }}" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Номер счет-фактуры или ГТД</label>
                                <div class="controls">
                                    <input type="text" name="good_basis" disabled="disabled" class="form-control"
                                           value="{{ good.basis }}" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Дата импорта или реализации</label>
                                <div class="controls">
                                    <input type="text" name="good_date" value="{{ date("d.m.Y", good.date_import) }}"
                                           data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}"
                                           data-date-end-date="0d" class="form-control datepicker" disabled="disabled">
                                </div>
                            </div>
                            <div class="form-group" id="car_cat_group">
                                <label class="form-label">{{ t._("country") }}</label>
                                <select name="good_country" class="selectpicker form-control" data-live-search="true"
                                        disabled="disabled">
                                    {% for cc in country %}
                                        <option value="{{ cc.id }}"{% if good.ref_country == cc.id %} selected="selected"{% endif %}>{{ t._(cc.name) }}</option>
                                    {% endfor %}
                                </select>
                            </div>
                            <div class="form-group" id="car_cat_group">
                                <label class="form-label">{{ t._("tn-code") }}</label>
                                <select name="tn_code" class="selectpicker form-control" data-live-search="true"
                                        disabled="disabled">
                                    {% for code in tn_codes %}
                                        <option value="{{ code.id }}"{% if good.ref_tn == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                                    {% endfor %}
                                </select>
                            </div>
                            <!-- товар в упаковке -->
                            <div class="form-group">
                                <button class="btn btn-primary" type="button" data-toggle="collapse"
                                        data-target="#tn-code-add" aria-expanded="false" aria-controls="tn-code-add">
                                    Товар в упаковке?
                                </button>
                            </div>
                            <div class="collapse{% if good.ref_tn_add %} in{% endif %}" id="tn-code-add">
                                <div class="form-group">
                                    <label class="form-label">Товар в упаковке</label>
                                    <select name="tn_code_add" class="selectpicker form-control" data-live-search="true"
                                            disabled="disabled">
                                        <option value="0" selected="selected">—— нет, это не товар в упаковке ——
                                        </option>
                                        {% for code in tn_codes_add %}
                                            <option value="{{ code.id }}"{% if good.ref_tn_add == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                                        {% endfor %}
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <b>{{ t._("delete_initiator") }}</b>
                                    <b class="text text-danger">*</b>
                                </label>
                                <select name="initiator" id="initiator" class="form-control">
                                    {% for item in initiators %}
                                        {% if initiator_id == '' %}
                                            <option value="{{ item.id }}">{{ t._(item.name) }}</option>
                                        {% else %}
                                            <option value="{{ item.id }}"{% if item.id == initiator_id %} selected{% endif %}>{{ t._(item.name) }}</option>
                                        {% endif %}
                                    {% endfor %}
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">{{ t._("comment") }}</label>
                                <textarea name="good_comment" id="deleteGoodComment" class="form-control"
                                          placeholder="Ваш комментарий ... " required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ t._("Загрузить файл") }}</label>
                                <input type="file" id="deleteGoodFile" name="good_file" class="form-control-file"
                                       required>
                            </div>
                            <hr>
                            <!-- конец товара в упаковке -->
                            <input type="hidden" name="profile" value="{{ good.profile_id }}">
                            <input type="hidden" value="{{ sign_data }}" name="hash" id="deleteGoodHash">
                            <textarea type="hidden" name="sign" id="deleteGoodSign" style="display: none;"></textarea>
                            <div class="row">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-warning signDeleteGoodsBtn">Подписать
                                        и удалить позицию
                                    </button>
                                    <a href="/correction/" class="btn btn-danger">Отмена</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-dark text-light">{{ t._("История изменения") }}</div>
            <div class="card-body" id="DISPLAY_CORRECTION_LOGS_BY_OBJECT_ID">
                <input type="hidden" value="{{ good.profile_id }}" id="getCorrectionLogsByProfileId">
                <input type="hidden" value="GOODS" id="getCorrectionLogsByType">
                <input type="hidden" value="{{ good.id }}" id="getCorrectionLogsByObjectId">
                <table id="correctionLogsByObjectId" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>{{ t._("application-number") }}</th>
                        <th>{{ t._("type") }} / {{ t._("Обьект ID") }}</th>
                        <th>{{ t._("Пользователь") }}</th>
                        <th>{{ t._("Действия") }}</th>
                        <th>{{ t._("Время") }}</th>
                        <th>{{ t._("comment") }}</th>
                        <th>{{ t._("operations") }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
 



