<!-- заголовок  -->
<h2>{{ t._("Админские отчеты") }}</h2>
<!-- /заголовок -->

<!-- отчет -->
<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Выгрузка пользователей") }}
  </div>
  <div class="card-body">
    <form method="POST" action="/report_admin">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
      <div class="row">
        <div class="col-sm-12 col-xs-12">
          <button type="submit" name="search" class="btn btn-primary">{{ t._("Скачать выгрузку (все пользователи)") }}</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- /отчет -->

<div class="row">
  <div class="col-5">
  <!-- отчет -->
    <div class="card my-3">
    <div class="card-header bg-dark text-light">
      {{ t._("Выгрузка логов по операциям с пользователями") }}
      <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
    </div>
    <div class="card-body">
      <form method="POST" action="/report_admin/user_logs" autocomplete="off">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <div class="row">
          <div class="col">
            <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                   data-date-start-date="<?php echo STARTROP; ?>"
                   data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                   class="form-control datepicker" placeholder="<?php echo STARTROP; ?>" required>
          </div>
          <div class="col">
            <input name="dend" id="dend" type="text" data-provide="datepicker"
                   data-date-start-date="<?php echo STARTROP; ?>"
                   data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                   class="form-control datepicker" placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
          </div>
          <div class="col-auto">
            <button type="submit" name="search" class="btn btn-primary">
              <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- /отчет -->
  </div>
  <div class="col-7">
    <!-- отчет -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Выгрузка логов по операциям с пользователями") }}
        <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_admin/user_logs" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker"
                     placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
            </div>
            <div class="col">
              <input name="dend" id="dend" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker" placeholder="<?php echo date('d.m.Y'); ?>" required>
            </div>
            <div class="col-auto">
              <button type="submit" name="search" class="btn btn-primary">
                <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <!-- /отчет -->
  </div>
</div>

<div class="row">
  <div class="col-5">
  <!-- Учет неидентифицированных платежей для РОП -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Учет неидентифицированных платежей") }}
        <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_importer/unidentified_payments" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo STARTROP; ?>"
                     data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     class="form-control datepicker" placeholder="<?php echo STARTROP; ?>" required>
            </div>
            <div class="col">
              <input name="dend" id="dend" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo STARTROP; ?>"
                     data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     class="form-control datepicker" placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
            </div>
            <div class="col-auto">
              <button type="submit"  class="btn btn-primary">
                <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <!-- /Учет неидентифицированных платежей для РОП -->
  </div>
  <div class="col-7">
  <!-- Учет неидентифицированных платежей для Жасыл даму -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Учет неидентифицированных платежей") }}
        <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_importer/unidentified_payments_zd" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker"
                     placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
            </div>
            <div class="col">
              <input name="dend" id="dend" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker"
                     placeholder="<?php echo date('d.m.Y'); ?>" required>
            </div>
            <div class="col-auto">
              <button type="submit"  class="btn btn-primary">
                <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <!-- /Учет неидентифицированных платежей для Жасыл даму -->
  </div>
</div>

<div class="row">
  <div class="col-5">
  <!-- отчет -->
    <div class="card my-3">
    <div class="card-header bg-dark text-light">
      {{ t._("Выгрузка логов по операциям с заявками") }}
      <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
    </div>
    <div class="card-body">
      <form method="POST" action="/report_admin/profile_logs" autocomplete="off">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <div class="row">
          <div class="col">
            <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                   data-date-start-date="<?php echo STARTROP; ?>"
                   data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                   class="form-control datepicker" placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
          </div>
          <div class="col">
              <select name="action[]"  class="selectpicker form-control" multiple>       
                <option value="SEND_TO_REVIEW">{{ t._("SEND_TO_REVIEW") }}</option>
                <option value="GLOBAL">{{ t._("GLOBAL") }}</option>
                <option value="DECLINED" selected>{{ t._("DECLINED") }}</option>
                <option value="SIGNED">{{ t._("SIGNED") }}</option>
                <option value="APPROVE" selected>{{ t._("APPROVE") }}</option>
                <option value="CERT_FORMATION">{{ t._("CERT_FORMATION") }}</option>               
                <option value="MSG">{{ t._("MSG") }}</option>               
              </select>      
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i data-feather="download" width="14" height="14"></i> {{ t._("download") }} (максимум — 1 день)
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- /отчет -->
  </div>
  <div class="col-7">
  <!-- отчет -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Выгрузка логов по операциям с заявками") }}
        <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_admin/profile_logs" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="<?php echo date('d.m.Y'); ?>"
                     class="form-control datepicker" placeholder="<?php echo date('d.m.Y'); ?>" required>
            </div>
            <div class="col">
              <select name="action[]"  class="selectpicker form-control" multiple>       
                <option value="SEND_TO_REVIEW">{{ t._("SEND_TO_REVIEW") }}</option>
                <option value="GLOBAL">{{ t._("GLOBAL") }}</option>
                <option value="DECLINED" selected>{{ t._("DECLINED") }}</option>
                <option value="SIGNED">{{ t._("SIGNED") }}</option>
                <option value="APPROVE" selected>{{ t._("APPROVE") }}</option>
                <option value="CERT_FORMATION">{{ t._("CERT_FORMATION") }}</option>               
                <option value="MSG">{{ t._("MSG") }}</option>               
              </select>      
          </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-primary">
                <i data-feather="download" width="14" height="14"></i> {{ t._("download") }} (максимум — 1 день)
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- /отчет -->

<div class="row">
  <div class="col-5">
    <!--Отчет по привязке(Оператор РОП) -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Отчет по привязке") }}
        <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_admin/detection_logs" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="form-row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo STARTROP; ?>"
                     data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     class="form-control datepicker" placeholder="<?php echo STARTROP; ?>" required>
            </div>
            <div class="col">
              <input name="dend" id="dend" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo STARTROP; ?>"
                     data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     class="form-control datepicker" placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
            </div>
            <div class="col-auto">
              <button type="submit" name="search" class="btn btn-primary"><i data-feather="download" width="14" height="14"></i> {{ t._("download") }}</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <!-- /Отчет по привязке(Оператор РОП) -->
  </div>
  <div class="col-7">
    <!-- Отчет по привязке(Жасыл даму) -->
    <div class="card my-3">
      <div class="card-header bg-dark text-light">
        {{ t._("Отчет по привязке") }}
        <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
      </div>
      <div class="card-body">
        <form method="POST" action="/report_admin/detection_logs" autocomplete="off">
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
          <div class="form-row">
            <div class="col">
              <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker"
                     placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
            </div>
            <div class="col">
              <input name="dend" id="dend" type="text" data-provide="datepicker"
                     data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                     data-date-end-date="0d" class="form-control datepicker"
                     placeholder="<?php echo date('d.m.Y'); ?>" required>
            </div>
            <div class="col-auto">
              <button type="submit" name="search" class="btn btn-primary"><i data-feather="download" width="14" height="14"></i> {{ t._("download") }}</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <!-- /Отчет по привязке(Жасул даму) -->
  </div>
</div>