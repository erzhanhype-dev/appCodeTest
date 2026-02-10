<h3>{{ t._("edit-kpp") }}</h3>

<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("edit-kpp") }} {{ kpp.id }} (заявка #{{ kpp.profile_id }})</div>
        <div class="card-body">
      <ul class="nav nav-tabs">
        {# <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#editKPPTab">Редактирование</a>
        </li> #}
        <li class="nav-item">
          <a class="nav-link active" data-toggle="tab" href="#deleteKPPTab">Удаление</a>
        </li>
      </ul>
        <div class="tab-content p-3">

          <!-- Edit form -->
          <div class="tab-pane fade" id="editKPPTab">

              <h2 class="h4 mb-3">Редактирование КПП</h2>
              <hr>
              <h2 class="h4 mb-3"><b style="color:red; font-style: italic;">Раздел в обработке...</b></h2>
              <form id="editKPPForm" action="/correction/edit_kpp/{{ kpp.id }}" method="POST" enctype="multipart/form-data" style="display: none;" autocomplete="off">
                  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                <div class="form-group">
                    <label class="form-label">{{ t._("kpp-weight") }}</label>
                    <div class="controls">
                        <input type="text" name="kpp_weight" id="kpp_weight" value="{{ kpp.weight }}" class="form-control" maxlength="17" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Номер счет-фактуры или ГТД</label>
                    <div class="controls">
                        <input type="text" name="kpp_basis" id="kpp_basis" value="{{ kpp.basis }}" class="form-control" maxlength="50" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("basis-date") }}</label>
                    <div class="controls">
                        <input type="text" name="basis_date" value="<?php echo date('d.m.Y',$kpp->basis_date); ?>" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Код валюты в инвойсе</label><br />
                    <select name="currency_type" id="currency_type" class="selectpicker form-control" data-live-search="true">
                        <option value="KZT" {% if kpp.currency_type == 'KZT' %} selected {% endif %} >KZT</option>
                        {% for i, curr in currencies %}
                            <option value="{{ curr.title }}" {% if kpp.currency_type == curr.title %} selected {% endif %} >{{ t._(curr.title) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cумма в инвойсе</label>
                    <div class="controls">
                        {% if kpp.currency_type == 'KZT' %}
                            <input type="text" name="sum" id="sum" value="{{ kpp.invoice_sum }}" class="form-control" maxlength="50" required>
                        {% else %}
                            <input type="text" name="sum" id="sum" value="{{ kpp.invoice_sum_currency }}" class="form-control" maxlength="50" required>
                        {% endif %}
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("import-date") }}</label>
                    <div class="controls">
                        <?php $date = date('d.m.Y',$kpp->date_import); ?>
                        <input type="text" name="kpp_date" id="kpp_date" value="{{ date }}" data-provide="datepicker" data-date-start-date="{{ constant('START_GET_KPP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                    </div>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("country") }}</label><br />
                    <select name="kpp_country" id="kpp_country" class="selectpicker form-control" data-live-search="true">
                        {% for i, cc in country %}
                            <option value="{{ cc.id }}"{% if cc.id == kpp.ref_country %}selected{% endif %}>{{ t._(cc.name) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group" id="car_cat_group">
                    <label class="form-label">{{ t._("tn-code") }}</label><br />
                    <select name="tn_code" id="tn_code" class="selectpicker form-control" data-live-search="true">
                        {% for i, code in tn_codes %}
                            <option value="{{ code.id }}"{% if code == kpp.ref_tn %} selected{% endif %} >{{ t._(code.code)~" - "~t._(code.group) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <!-- товар в упаковке -->
                <div class="form-group">
                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#kpp-tn-code-add" aria-expanded="false" aria-controls="kpp-tn-code-add">Товар в упаковке?</button>
                </div>
                <div class="collapse{% if kpp.package_tn_code %} in{% endif %}" id="kpp-tn-code-add">
                    <div class="form-group">
                        <label class="form-label">Товар в упаковке</label>
                        <select name="package_tn_id" id="kpp-tn-code-add" class="selectpicker form-control" data-live-search="true">
                            <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                            {% for code in package_tn_codes %}
                                <option value="{{ code.id }}"{% if kpp.package_tn_code == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ t._("package-weight") }}</label>
                        <div class="controls">
                            <input type="text" name="package_weight" class="form-control" placeholder="0.000" value="{{ kpp.package_weight }}">
                        </div>
                    </div>
                </div>
                <!-- конец товара в упаковке -->

                <div class="form-group">
                  <label class="form-label">{{ t._("comment") }}</label>
                  <textarea name="kpp_comment" id="EditKPPComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
                </div>
                <div class="form-group">    
                  <label class="form-label">{{ t._("Загрузить файл") }}</label>
                  <input type="file" id="EditKPPFile" name="kpp_file" class="form-control-file" required>
                </div>
                <hr>
                <input type="hidden" name="profile" value="{{ pid }}">
                <input type="hidden" value="{{ sign_data }}" name="hash" id="EditKPPHash">
                <textarea type="hidden" name="sign" id="EditKPPSign" style="display: none;"></textarea>
                <div class="row">
                  <div class="col-auto">
                    <button type="button" class="btn btn-warning signEditKPPsBtn">Подписать и сохранить изменения</button>
                    <a href="/correction/" class="btn btn-danger">Отмена</a>
                  </div>
                </div>           
            </form>
          </div>

          <!-- Delete form -->
          <div class="tab-pane fade show active" id="deleteKPPTab">

            <h2 class="h4 mb-3">Удаление КПП</h2>

            <form id="deleteKPPForm" action="/correction/delete_kpp/{{ kpp.id }}" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

              <div class="form-group">
                  <label class="form-label">{{ t._("kpp-weight") }}</label>
                  <div class="controls">
                      <input type="text" name="kpp_weight" id="kpp_weight" disabled="disabled" value="{{ kpp.weight }}" class="form-control" maxlength="17" required>
                  </div>
              </div>
              <div class="form-group">
                  <label class="form-label">Номер счет-фактуры или ГТД</label>
                  <div class="controls">
                      <input type="text" name="kpp_basis" id="kpp_basis" disabled="disabled" value="{{ kpp.basis }}" class="form-control" maxlength="50" required>
                  </div>
              </div>
              <div class="form-group">
                  <label class="form-label">{{ t._("basis-date") }}</label>
                  <div class="controls">
                      <input type="text" name="basis_date" disabled="disabled" value="<?php echo date('d.m.Y',$kpp->basis_date); ?>" data-provide="datepicker" data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                  </div>
              </div>
              <div class="form-group">
                  <label class="form-label">Код валюты в инвойсе</label><br />
                  <select name="currency_type" id="currency_type" disabled="disabled" class="selectpicker form-control" data-live-search="true">
                      <option value="KZT" {% if kpp.currency_type == 'KZT' %} selected {% endif %} >KZT</option>
                      {% for i, curr in currencies %}
                          <option value="{{ curr.title }}" {% if kpp.currency_type == curr.title %} selected {% endif %} >{{ t._(curr.title) }}</option>
                      {% endfor %}
                  </select>
              </div>
              <div class="form-group">
                  <label class="form-label">Cумма в инвойсе</label>
                  <div class="controls">
                      {% if kpp.currency_type == 'KZT' %}
                          <input type="text" name="sum" id="sum" disabled="disabled" value="{{ kpp.invoice_sum }}" class="form-control" maxlength="50" required>
                      {% else %}
                          <input type="text" name="sum" id="sum" disabled="disabled" value="{{ kpp.invoice_sum_currency }}" class="form-control" maxlength="50" required>
                      {% endif %}
                  </div>
              </div>
              <div class="form-group">
                  <label class="form-label">{{ t._("import-date") }}</label>
                  <div class="controls">
                      <?php $date = date('d.m.Y',$kpp->date_import); ?>
                      <input type="text" name="kpp_date" id="kpp_date" disabled="disabled" value="{{ date }}" data-provide="datepicker" data-date-start-date="{{ constant('START_GET_KPP') }}" data-date-end-date="0d" class="form-control datepicker" required>
                  </div>
              </div>
              <div class="form-group" id="car_cat_group">
                  <label class="form-label">{{ t._("country") }}</label><br />
                  <select name="kpp_country" id="kpp_country" disabled="disabled" class="selectpicker form-control" data-live-search="true">
                      {% for i, cc in country %}
                          <option value="{{ cc.id }}"{% if cc.id == kpp.ref_country %}selected{% endif %}>{{ t._(cc.name) }}</option>
                      {% endfor %}
                  </select>
              </div>
              <div class="form-group" id="car_cat_group">
                  <label class="form-label">{{ t._("tn-code") }}</label><br />
                  <select name="tn_code" id="tn_code" disabled="disabled" class="selectpicker form-control" data-live-search="true">
                      {% for i, code in tn_codes %}
                          <option value="{{ code.id }}"{% if code == kpp.ref_tn %} selected{% endif %} >{{ t._(code.code)~" - "~t._(code.group) }}</option>
                      {% endfor %}
                  </select>
              </div>
              <!-- товар в упаковке -->
                <div class="form-group">
                    <label class="form-label">Товар в упаковке</label>
                    <select name="package_tn_id" id="kpp-tn-code-add" disabled="disabled" class="selectpicker form-control" data-live-search="true">
                        <option value="0" selected="selected">—— нет, это не товар в упаковке ——</option>
                        {% for code in package_tn_codes %}
                            <option value="{{ code.id }}"{% if kpp.package_tn_code == code.id %} selected="selected"{% endif %}>{{ t._(code.code)~" - "~t._(code.name) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ t._("package-weight") }}</label>
                    <div class="controls">
                        <input type="text" name="package_weight" disabled="disabled" class="form-control" placeholder="0.000" value="{{ kpp.package_weight }}">
                    </div>
                </div>
              <!-- конец товара в упаковке -->
                <div class="form-group">
                  <label class="form-label">{{ t._("comment") }}</label>
                  <textarea name="kpp_comment" id="deleteKPPComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
                </div>
                <div class="form-group">    
                  <label class="form-label">{{ t._("Загрузить файл") }}</label>
                  <input type="file" id="deleteKPPFile" name="kpp_file" class="form-control-file" required>
                </div>
                <hr>
                <input type="hidden" name="profile" value="{{ pid }}">
                <input type="hidden" value="{{ sign_data }}" name="hash" id="deleteKPPHash">
                <textarea type="hidden" name="sign" id="deleteKPPSign" style="display: none;"></textarea>
                <div class="row">
                  <div class="col-auto">
                    <button type="button" class="btn btn-warning signDeleteKPPsBtn">Подписать и удалить позицию</button>
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
        <input type="hidden" value="{{ pid }}" id="getCorrectionLogsByProfileId">
        <input type="hidden" value="kpp" id="getCorrectionLogsByType">
        <input type="hidden" value="{{ kpp.id }}" id="getCorrectionLogsByObjectId">
        <table id="correctionLogsByObjectId" class="display" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>{{t._("application-number")}}</th>
              <th>{{t._("type")}} / {{t._("Обьект ID")}}</th>
              <th>{{t._("Пользователь")}}</th>
              <th>{{t._("Действия")}}</th>
              <th>{{t._("Время")}}</th>
              <th>{{t._("comment")}}</th>
              <th>{{t._("operations")}}</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
 

 



