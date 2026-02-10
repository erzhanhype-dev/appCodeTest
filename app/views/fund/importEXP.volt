<h3>{{ t._("new-import") }}</h3>

<form enctype="multipart/form-data" action="/fund/uploadFromExcelEXP" method="POST" autocomplete="off">
  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header">{{ t._("load-machines") }}</span></div>
      <div class="card-body">
        <div class="form-group" id="order">
          <label class="form-label">{{ t._("import-file") }}</label>
          <span class="help">{{ t._("csv-divide") }}</span>
          <div class="controls">
            <input type="file" name="files_import" id="files_import" class="form-control-file">
          </div>
        </div>
        <input type="hidden" name="order_id" value="{{ pid }}">
        <button type="submit" class="btn btn-success" name="button">{{ t._("add-application") }}</button>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card mt-3">
      <div class="card-header">Пример заполнения <span class="semi-bold">заявки</span></div>
      <div class="card-body">
        <p>Для подготовки корректного CSV-файла для импорта, воспользуйтесь <a href="/main/generateFundCarEXPImportExample">примером <span class="badge badge-pill badge-danger">Новый</span></a>. Данном файле приложен справочник типов, стран и категорий транспортных средств.</p>
        <p>Скопируйте и заполните по образцу вашу собственную таблицу, сохраните её как файл CSV - и загрузите в приведенную здесь форму.</p>
        <p>Для корректной загрузки файла импорта - сохраните его как CSV-файл.</p>
        <p><strong>Обратите внимание!</strong> Все загружаемые в заявку ТС должны представлять собой ТС одну категорию и модельный ряд.</p>
      </div>
    </div>
  </div>
</div>
</form>
