  <!-- заголовок  -->
  <h2>{{ t._("Отчеты по реализации") }}</h2>
  <!-- /заголовок -->

  <div class="row">
    <div class="col-5">
    <!-- отчет -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("report-realization") }} {{ t._("report-realization-create-date") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization"autocomplete="off">
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
                <input type="hidden" name="report_approve_date" value="0">
                <input type="hidden" name="report_agents" value="0">
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
          {{ t._("report-realization") }} {{ t._("report-realization-create-date") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization" autocomplete="off">
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
                       data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022" required>
              </div>
              <div class="col-auto">
                <input type="hidden" name="report_approve_date" value="0">
                <input type="hidden" name="report_agents" value="0">
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
    <!-- отчет -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("report-realization") }} {{ t._("report-realization-approve-date") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization" autocomplete="off">
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
                       class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
              </div>
              <div class="col-auto">
                <input type="hidden" name="report_approve_date" value="1">
                <input type="hidden" name="report_agents" value="0">
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
          {{ t._("report-realization") }} {{ t._("report-realization-approve-date") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization" autocomplete="off">
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
                <input type="hidden" name="report_approve_date" value="1">
                <input type="hidden" name="report_agents" value="0">
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
    <!-- отчет -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("report-realization") }} {{ t._("report-realization-agents") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization" autocomplete="off">
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
                       class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
              </div>
              <div class="col-auto">
                <input type="hidden" name="report_approve_date" value="1">
                <input type="hidden" name="report_agents" value="1">
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
          {{ t._("report-realization") }} {{ t._("report-realization-agents") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization" autocomplete="off">
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
                <input type="hidden" name="report_approve_date" value="1">
                <input type="hidden" name="report_agents" value="1">
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
    <!-- отчет -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по реализации (детальный, максимум 31 день)") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="/report_realization/dold" autocomplete="off">
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
                         class="form-control datepicker"
                         placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
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
          {{ t._("Отчет по реализации (детальный, максимум 31 день)") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization/dold" autocomplete="off">
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
    <!-- отчет -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по реализации (детальный, максимум 1 день, с привязкой к оплате)") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="/report_realization/detailed" autocomplete="off">
              <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
              <div class="form-row">
                <div class="col">
                  <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                         data-date-start-date="<?php echo STARTROP; ?>"
                         data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                         class="form-control datepicker" placeholder="<?php echo STARTROP; ?>" required>
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
          {{ t._("Отчет по реализации (детальный, максимум 1 день, с привязкой к оплате)") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization/jd_detailed" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
              <div class="col">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       data-date-end-date="0d"
                       class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
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
    <div class="col-5" style="pointer-events: none; opacity: 0.7;">
      <!-- отчет Акт сверки -->
        <!-- <div class="card my-3">
          <div class="card-header bg-dark text-light">
            {{ t._("Акт сверки") }}
            <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
          </div>
          <div class="card-body" style="display: none">
            <form method="POST" action="/report_realization/rop_act_sverki" autocomplete="off">
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
                         class="form-control datepicker"
                         placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
                </div>
                <div class="col">
                  <input name="idnum" type="text" class="form-control" placeholder="Введите ИИН / БИН"
                         maxlength="12" minlength="12" required>
                </div>
                <div class="col-auto">
                  <button type="submit" name="search" class="btn btn-primary">
                    <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div> -->
      <!-- /отчет Акт сверки -->
      <!-- <b style="color: red">Раздел в разработке !!!</b> -->
    </div>
    <div class="col-7">
      <!-- отчет Акт сверки -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Акт сверки") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_realization/jd_act_sverki" autocomplete="off">
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
              <div class="col">
                <input name="idnum" type="text" class="form-control" placeholder="Введите ИИН / БИН"
                       maxlength="12" minlength="12" required>
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
      <!-- /отчет Акт сверки -->
    </div>
  </div>

    <!-- Отчет по корректировкам -->
    <div class="row">
      <div class="col-5" style="pointer-events: none; opacity: 0.7;">
      </div>
      <div class="col-7">
        <div class="card my-3">
          <div class="card-header bg-dark text-light">
            {{ t._("Отчет по корректировкам") }}
            <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
          </div>
          <div class="card-body">
            <form method="POST" action="/report_realization/corrections" autocomplete="off">
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
                  <button type="submit" name="search" class="btn btn-primary">
                    <i data-feather="download" width="14" height="14"></i>
                    {{ t._("download") }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- /Отчет по корректировкам -->

