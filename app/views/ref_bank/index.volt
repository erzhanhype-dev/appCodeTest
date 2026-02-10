<h2>{{ t._("Справочник банков") }}</h2>

<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Поиск") }}
  </div>
  <div class="card-body">
    <form method="POST" action="/ref_bank/" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
      <div class="row">
        <div class="col-4">
          <input type="text" name="bik" class="form-control" value="<?php echo $_SESSION['ref_bank_bik'] ? $_SESSION['ref_bank_bik'] : '';?>" placeholder="Поиск по БИК">
        </div>
        <div class="col-4">
          <input type="text" name="title" class="form-control" value="<?php echo $_SESSION['ref_bank_title'] ? $_SESSION['ref_bank_title'] : '';?>" placeholder="Поиск по Название банка">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
          <button type="submit" name="reset" value="all" class="btn btn-warning">Сбросить</button>
          <a href="/ref_bank/new" class="btn btn-success ml-4"><i data-feather="plus"></i> Добавить</a>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Банки") }}
  </div>
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t._("bik") }}</th>
          <th>{{ t._("bank") }}</th>
          {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
          <th>{{ t._("operations") }}</th>
          {% endif %}
        </tr>
      </thead>
      <tbody>
      {% if page.items|length > 0 %}
        {% for ref_bank in page.items %}
        <tr>
          <td>{{ ref_bank.id }}</td>
          <td>{{ ref_bank.bik }}</td>
          <td>{{ ref_bank.name }}</td>
          {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
          <td>
            <a href="/ref_bank/edit/{{ ref_bank.id }}" class="btn btn-secondary btn-sm"><i data-feather="edit" width="14" height="14"></i></a>
            <a href="/ref_bank/delete/{{ ref_bank.id }}" data-confirm='Вы действительно хотите удалить это?' class="btn btn-danger btn-sm confirmBtn">
              <i data-feather="trash" width="14" height="14"></i>
            </a>
          </td>
          {% endif %}
        </tr>
        {% endfor %}
        {% endif %}
      </tbody>
    </table>
  </div>
</div>

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
