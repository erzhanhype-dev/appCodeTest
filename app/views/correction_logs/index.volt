<!-- заголовок -->
<h2>{{ t._("История корректировки и аннулировании") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Поиск") }}
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-2">
        <label><b>Пользователь:</b></label>
        <select id="searchCorrectionLogsByUserId" class="selectpicker form-control" data-live-search="true">
          <option value=''>-- Выберите Пользователь --</option>
            {% for i, user in users %}
              <option value="{{ user.id }}">
                {{ user.fio }}
                <b>({{ user.idnum }})</b>
              </option>
            {% endfor %}
        </select>
      </div>
      <div class="col-2">
        <label><b>Номер заявки:</b></label>
        <input type="number" id="searchCorrectionLogsByProfileId" class="form-control" placeholder="Введите номер заявки(на УП)">
      </div>
      <div class="col-2">
        <label><b>Тип:</b></label>
        <select id="searchCorrectionLogsByType" class="form-control">
          <option value=''>-- Выберите тип --</option>
          <option value='CAR'>{{ t._("CAR") }}</option>
          <option value='GOODS'>{{ t._("GOODS") }}</option>
          <option value='KPP'>{{ t._("KPP") }}</option>
        </select>
      </div>
      <div class="col-2">
        <label><b>Действия:</b></label>
        <select id="searchCorrectionLogsByAction" class="form-control">
          <option value=''>-- Выберите действия --</option>
          <option value='CORRECTION'>{{ t._("CORRECTION") }}</option>
          <option value='ANNULMENT'>{{ t._("ANNULMENT") }}</option>
          <option value='RESTORED'>{{ t._("RESTORED") }}</option>
          <option value='DELETED'>{{ t._("DELETED") }}</option>
          <option value='CREATED'>{{ t._("CREATED") }}</option>
          <option value='CORRECTION_APPROVED'>{{ t._("CORRECTION_APPROVED") }}</option>
        </select>
      </div>
      <div class="col-auto mt-4">
        <a href="/correction_logs/" id="resetFilters" class="btn btn-warning">
          <i data-feather="refresh-cw" width="20" height="14"></i>
          {{ t._("Сбросить фильтр") }}
        </a>
      </div>
    </div>
  </div>
</div>
<!-- /форма поиска -->

<!--Логи -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Логи") }}
  </div>
  <div class="card-body" id="CORRECTION_LOGS_FORM">
    <table id="correctionLogsList" class="display" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th>{{t._("operations")}}</th>
          <th>{{t._("application-number")}}</th>
          <th>{{t._("type")}} / {{t._("Обьект ID")}}</th>
          <th>{{t._("Пользователь")}}</th>
          <th>{{t._("Действия")}}</th>
          <th>{{t._("Инициатор")}}</th>
          <th>{{t._("Время")}}</th>
          <th>{{t._("comment")}}</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
