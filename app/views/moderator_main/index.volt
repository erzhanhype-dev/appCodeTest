<h3>{{ t._("main-page-moderator") }}</h3>

<div class="row">
  <div class="col-6">
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Банк и бухгалтерия") }}
        </div>
        <div class="card-body">
          <p>{{ t._("payments-list") }}</p>
          <a href="/admin_bank/bank" class="btn btn-primary">Перейти</a>
        </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Заявки") }}
        </div>
        <div class="card-body">
          <p>{{ t._("Заявки на модерацию") }}</p>
          <a href="/moderator_order" class="btn btn-primary">Перейти</a>
        </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Общие отчеты") }}
        </div>
        <div class="card-body">
          <p>{{ t._("Общие отчеты в системе") }}</p>
          <a href="/report_importer" class="btn btn-primary">Перейти</a>
        </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Настройки") }}
        </div>
        <div class="card-body">
          <p>{{ t._("Настройки пользователя") }}</p>
          <a href="/settings" class="btn btn-primary">Перейти</a>
        </div>
    </div>
  </div>
</div>
