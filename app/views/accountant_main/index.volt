<h3>{{ t._("main-page-accountant-bold") }}</h3>

{{ flash.output() }}

<div class="row">
  <div class="col-6">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("bank-transfers") }}
      </div>  
      <div class="card-body">
        <p>{{ t._("bank-transfers") }}</p>
        <a href="/admin_bank/bank" class="btn btn-primary">Перейти</a>
      </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Импортеры") }}
      </div>  
      <div class="card-body">
        <p>{{ t._("Отчеты по импортерам") }}</p>
        <a href="/report_importer" class="btn btn-primary">Перейти</a>
      </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("report-realization") }}
      </div>  
      <div class="card-body">
        <p>{{ t._("help-report-realization") }}</p>
        <a href="/report_realization" class="btn btn-primary">Перейти</a>
      </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Настройки") }}
      </div>  
      <div class="card-body">
        <p>{{ t._("personal-set") }}</p>
        <a href="/settings" class="btn btn-primary">Перейти</a>
      </div>
    </div>
  </div>
</div>