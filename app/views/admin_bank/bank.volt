<h2>{{ t._('Банковские транзакции') }}</h2>

<form method="get" action="/admin_bank/bank" class="card mt-3" autocomplete="off">
  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
  <div class="card-header bg-dark text-light">{{ t._('Поиск') }}</div>
  <div class="card-body">
    <div class="row">
      <div class="col-2">
        <label><b>Поиск по БИН, ИИН:</b></label>
        <input name="idnum" type="number" class="form-control" value="{{ filters.idnum }}">
      </div>
      <div class="col-2">
        <label><b>Поиск по Референс:</b></label>
        <input name="ref" type="text" class="form-control" value="{{ filters.reference }}">
      </div>
      <div class="col-2">
        <label><b>Поиск по Год:</b></label>
        <select name="year" class="form-control">
          {% for y in years %}
            <option value="{{ y }}" {{ y == filters.year ? 'selected' : '' }}>{{ y }}</option>
          {% endfor %}
        </select>
      </div>
      <div class="col-2">
        <label><b>Статус платежа:</b></label>
        <select name="status" class="form-control">
          <option value="" {{ filters.status == '' ? 'selected' : '' }}>{{ t._('Любой') }}</option>
          <option value="NOT_SET" {{ filters.status == 'NOT_SET' ? 'selected' : '' }}>{{ t._('Не привязан') }}</option>
          <option value="SET" {{ filters.status == 'SET' ? 'selected' : '' }}>{{ t._('Привязан') }}</option>
        </select>
      </div>
      <div class="col-auto mt-4">
        <button type="submit" class="btn btn-primary">{{ t._('Применить') }}</button>
        <a href="/admin_bank/bank?clear_filters=1" class="btn btn-warning">{{ t._('Сбросить фильтр') }}</a>
      </div>
    </div>
  </div>
</form>

<div class="card mt-3">
  <div class="card-header">{{ t._('Банковские транзакции') }}</div>
  <div class="card-body">
    <table class="table table-response">
      <thead>
      <tr>
        <th>{{ t._('ФИО/Название (БИН/ИИН)') }}</th>
        <th>IBAN отправителя</th>
        <th>{{ t._('Размер платежа') }}</th>
        <th>IBAN получателя</th>
        <th>{{ t._('Дата и время') }}</th>
        <th style="width:40%">{{ t._('Назначение') }}</th>
        <th>{{ t._('Функции') }}</th>
        <th>{{ t._('Привязки') }}</th>
      </tr>
      </thead>
      <tbody>
      {% if items is iterable and items|length > 0 %}
        {% for r in items %}
          <tr>
            <td>{{ r.name_sender }}</td>
            <td>{{ r.iban_from }}</td>
            <td class="text-nowrap">{{ r.amount_fmt }}</td>
            <td>{{ r.iban_to }}</td>
            <td>{{ r.paid_fmt }}</td>
            <td>{{ r.comment }}</td>
            <td>
              {% set disabled_btn = 'style="pointer-events: none; opacity: 0.7;"' %}
              {% if auth is defined and (auth.isModerator() or auth.isSuperModerator() or auth.isAdminSoft() or auth.isAdmin()) %}
                {% set disabled_btn = '' %}
              {% endif %}
              <a href="/admin_bank/set/{{ r.id }}" class="btn btn-danger" {{ disabled_btn }}>
                <i class="fa fa-edit"></i>
              </a>
            </td>
            <td>{{ r.transactions }}</td>
          </tr>
        {% endfor %}
      {% else %}
        <tr><td colspan="8" class="text-center text-muted">Нет данных</td></tr>
      {% endif %}
      </tbody>
    </table>

    {% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}

  </div>
</div>
