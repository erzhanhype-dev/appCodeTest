<?php
      $trans = [
        "Товарищество с ограниченной ответственностью" => "ТОО",
        "ТОВАРИЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ" => "ТОО",
        "Акционерное общество" => "АО",
        "АКЦИОНЕРНОЕ ОБЩЕСТВО" => "АО"
      ];
?>

<?php if(isset($_SESSION['auth'])){?>
<?php $auth = User::findFirstById($_SESSION['auth']['id']);?>
<?php }?>

<!-- заголовок -->
  <h2>{{ t._("Общие отчеты") }}</h2>
  <!-- /заголовок -->

  <div class="row">
    <div class="col-5">
        <!-- общий отчет(Оператор РОП) -->
        <div class="card my-3">
          <div class="card-header bg-dark text-light">
            {{ t._("Общий отчет (импортеры, юридические лица)") }}
            <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
          </div>
          <div class="card-body">
            <form method="POST" action="/report_importer" autocomplete="off">
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
        <!-- /общий отчет(Оператор РОП) -->
    </div>
    <div class="col-7">
      <!-- общий отчет(Жасыл даму) -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Общий отчет (импортеры, юридические лица)") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/zd_main" autocomplete="off">
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
                       data-date-end-date="0d" class="form-control datepicker" placeholder="<?php echo date('d.m.Y'); ?>" required>
              </div>
              <div class="col-auto">
                <button type="submit" name="search" class="btn btn-primary"><i data-feather="download" width="14" height="14"></i> {{ t._("download") }}</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <!-- /общий отчет(Жасул даму) -->
    </div>
  </div>

  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по кодам ТН ВЭД
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/tnved" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
              <div class="col">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo STARTROP; ?>"
                       data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       class="form-control datepicker"
                       placeholder="<?php echo STARTROP; ?>" required>
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
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по кодам ТН ВЭД
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/zd_tnved" autocomplete="off">
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
    </div>
  </div>

  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по кодам ТН ВЭД(КПП)
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/tnved_kpp" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
              <div class="col">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo STARTROP; ?>"
                       data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       class="form-control datepicker"
                       placeholder="<?php echo STARTROP; ?>" required>
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
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по кодам ТН ВЭД(КПП)
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/zd_tnved_kpp" autocomplete="off">
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
    </div>
  </div>

  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по категориям ТС
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/carcat">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="row">
              <div class="col-6">
                <select name="year" class="selectpicker form-control" data-live-search="true">
                  <?php
                    foreach(range(2016, (int)date(2022)) as $year) {
                      echo '<option value="'.$year.'" selected>'.$year.'</option>';
                    }
                  ?>
                </select>
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
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          Срез по категориям ТС <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/zd_carcat">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="row">
              <div class="col-6">
                <select name="year" class="selectpicker form-control" data-live-search="true">
                  <?php
                    foreach(range(2022, (int)date('Y')) as $year) {
                      echo '<option value="'.$year.'" selected>'.$year.'</option>';
                  }
                  ?>
                </select>
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
    </div>
  </div>

  <div class="row">
    <div class="col-5">
      <!-- детальный отчет -->
      <div class="accordion my-3" id="accordionReport">
        <!-- ТС -->
        <div class="card">
          <div class="card-header" id="hCar">
            <h2 class="mb-0">
              <button class="btn btn-dark" type="button" data-toggle="collapse" data-target="#cCar" aria-expanded="true" aria-controls="cCar">
                Детальный отчет, ТС <span class="badge badge-warning" style="font-size: 14px;">{{ constant('ROP') }}</span>
              </button>
            </h2>
          </div>
          <div id="cCar" class="collapse" aria-labelledby="hCar" data-parent="#accordionReport">
            <div class="card-body">
              <!-- тело отчета -->
              <form method="POST" id="car_form" action="/report_importer/car" autocomplete="off">
                <!-- группа -->
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                <div class="row">
                  <div class="col">
                      <h5>БИН, VIN, категория:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="bin" id="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                  </div>
                  <div class="col">
                    <input name="vin" id="vin" type="text" class="form-control" placeholder="VIN ввозимого / производимого ТС">
                  </div>
                  <div class="col">
                    <select name="cat" id="car_cat" data-live-search="true">
                      <option value="0" selected>Все категории</option>
                        {% for i, cat in cats %}
                            <option value="{{ cat.id }}">{{ t._(cat.name) }}</option>
                        {% endfor %}
                    </select>
                  </div>
                </div>
                <div class="row mt-2">
                  <div class="col-6">
                    <label><b>Статус заявки:</b></label>
                    <select name="status[]" class="selectpicker form-control" multiple>
                      <option value="REVIEW" selected>На рассмотрении</option>
                      <option value="GLOBAL" selected>Сертификат выдан</option>
                      <option value="DECLINED" selected>Отклонено</option>
                      <option value="NEUTRAL" selected>Нейтральная</option>
                      <option value="APPROVE" selected>Выдан счет на оплату</option>
                      <option value="CERT_FORMATION" selected>Формирование сертификата</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label><b>Седельность:</b></label>
                    <select name="st_type" class="selectpicker form-control">
                      <option value="ALL" selected>Выбрать все</option>
                      <option value="NO">Не седельный тягач</option>
                      <option value="YES">Седельный тягач</option>
                      <option value="INT_TR">Седельный тягач(Международная перевозка)</option>
                    </select>
                  </div>
                </div>
                <!-- конец группы -->

                <div class="row my-3">
                  <div class="col">
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#car-date-collapse" aria-expanded="false" aria-controls="car-date-collapse">Фильтр по дате</button>
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#car-reg-collapse" aria-expanded="false" aria-controls="car-reg-collapse">Фильтр по региону</button>
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#car-volume-collapse" aria-expanded="false" aria-controls="car-volume-collapse">Фильтр по объему и платежу</button>
                  </div>
                </div>
                <hr>
                <!-- collapse date -->
                <div class="collapse" id="car-date-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате создания заявки:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->

                   <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате отправки модератору:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате импорта:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате выдачи документа о полноте:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_global" id="dstart_global" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend_global" id="dend_global" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse date -->

                <!-- collapse country -->
                <div class="collapse" id="car-reg-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по стране экспорта / производства:</h5>
                    </div>
                    <div class="col">
                      <h5>Фильтр по региону импорта (город, область):</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <div class="form-group">
                        {{ select([
                          'country',
                          ref_country,
                          'using': ['id', 'name'],
                          'class': 'form-control',
                          'useEmpty': true
                        ]) }}
                      </div>
                    </div>
                    <div class="col">
                      <input name="icity" id="icity" type="text" class="form-control" placeholder="Нур-Султан">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse country -->

                <!-- collapse volume -->
                <div class="collapse" id="car-volume-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Объем или вес транспортного средства:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="volume_min" id="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                    </div>
                    <div class="col">
                      <input name="volume_max" id="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Размер платежа:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="total_min" id="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                    </div>
                    <div class="col">
                      <input name="total_max" id="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse volume -->

                <div class="row" style="display: none;" id="item_table">
                  <div class="col">
                    <p><strong>Сводная форма</strong></p>
                    <table class="table table-stripped table-bordered">
                      <thead>
                        <tr>
                          <th>Количество</th>
                          <th>Сумма</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td id="item_count"></td>
                          <td id="item_sum"></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <button type="submit" name="search" class="btn btn-primary">Скачать (максимум — 31 день)</button>
                    <button id="car_ajax" type="button" name="ajax" class="btn btn-warning">Сводная форма <i id="car_loader" style="display: none;" data-feather="loader"></i></button>
                    <button type="submit" id="all_cars_report" class="btn btn-success ml-3" formaction="/report_importer/cars_all_time">Скачать заявки</button>
                  </div>
                </div>

              </form>
              <!-- /тело отчета -->
            </div>
          </div>
        </div>
        <!-- /ТС -->
      </div>
      <!-- /детальный отчет -->
    </div>
    <div class="col-7">
      <!-- детальный отчет -->
      <div class="accordion my-3" id="zd_car_accordionReport">
        <!-- ТС -->
        <div class="card">
          <div class="card-header" id="zd_hCar">
            <h2 class="mb-0">
              <button class="btn btn-dark" type="button" data-toggle="collapse" data-target="#zd_cCar" aria-expanded="true" aria-controls="zd_cCar">
                Детальный отчет, ТС <span class="badge badge-success" style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
              </button>
            </h2>
          </div>
          <div id="zd_cCar" class="collapse" aria-labelledby="zd_hCar" data-parent="#zd_car_accordionReport">
            <div class="card-body">
              <!-- тело отчета -->
              <form method="POST" id="zd_car_form" autocomplete="off">
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                <!-- группа -->
                <div class="row">
                  <div class="col">
                    <h5>БИН, VIN, категория:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="bin" id="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                  </div>
                  <div class="col">
                    <input name="vin" id="vin" type="text" class="form-control" placeholder="VIN ввозимого / производимого ТС">
                  </div>
                  <div class="col">
                    <select name="cat" id="car_cat">
                      <option value="0" selected>Все категории</option>
                        {% for i, cat in cats %}
                          <option value="{{ cat.id }}">{{ t._(cat.name) }}</option>
                        {% endfor %}
                    </select>
                  </div>
                </div>
                <div class="row mt-2">
                  <div class="col-6">
                    <label><b>Статус заявки:</b></label>
                    <select name="status[]" class="selectpicker form-control" multiple>
                      <option value="REVIEW" selected>На рассмотрении</option>
                      <option value="GLOBAL" selected>Сертификат выдан</option>
                      <option value="DECLINED" selected>Отклонено</option>
                      <option value="NEUTRAL" selected>Нейтральная</option>
                      <option value="APPROVE" selected>Выдан счет на оплату</option>
                      <option value="CERT_FORMATION" selected>Формирование сертификата</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label><b>Седельность:</b></label>
                    <select name="st_type" class="selectpicker form-control">
                      <option value="ALL" selected>Выбрать все</option>
                      <option value="NO">Не седельный тягач</option>
                      <option value="YES">Седельный тягач</option>
                      <option value="INT_TR">Седельный тягач(Международная перевозка)</option>
                    </select>
                  </div>
                </div>
                <!-- конец группы -->

                <div class="row my-3">
                  <div class="col">
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-car-date-collapse" aria-expanded="false" aria-controls="zd-car-date-collapse">Фильтр по дате</button>
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-car-reg-collapse" aria-expanded="false" aria-controls="zd-car-reg-collapse">Фильтр по региону</button>
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-car-volume-collapse" aria-expanded="false" aria-controls="zd-car-volume-collapse">Фильтр по объему и платежу</button>
                  </div>
                </div>

                <hr>

                <!-- collapse date -->
                <div class="collapse" id="zd-car-date-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по дате создания заявки:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                    <div class="col">
                      <input name="dend" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по дате отправки модератору:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                    <div class="col">
                      <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по дате импорта:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                    <div class="col">
                      <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по дате выдачи документа о полноте:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_global" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                    <div class="col">
                      <input name="dend_global" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse date -->

                <!-- collapse country -->
                <div class="collapse" id="zd-car-reg-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по стране экспорта / производства:</h5>
                    </div>
                    <div class="col">
                      <h5>Фильтр по региону импорта (город, область):</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <div class="form-group">
                        {{ select([
                          'country',
                          ref_country,
                          'using': ['id', 'name'],
                          'id': 'country',
                          'class': 'form-control',
                          'useEmpty': true
                        ]) }}
                      </div>
                    </div>
                    <div class="col">
                      <input name="icity" type="text" class="form-control" placeholder="Нур-Султан">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse country -->

                <!-- collapse volume -->
                <div class="collapse" id="zd-car-volume-collapse">
                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Объем или вес транспортного средства:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                    </div>
                    <div class="col">
                      <input name="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Размер платежа:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                    </div>
                    <div class="col">
                      <input name="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                    </div>
                  </div>
                  <!-- конец группы -->
                  <hr>
                </div>
                <!-- end collapse volume -->

                <div class="row" style="display: none;" id="zd_car_item_table">
                  <div class="col">
                    <p><strong>Сводная форма</strong></p>
                    <table class="table table-stripped table-bordered">
                      <thead>
                      <tr>
                        <th>Количество</th>
                        <th>Сумма</th>
                      </tr>
                      </thead>
                      <tbody>
                      <tr>
                        <td id="zd_car_item_count"></td>
                        <td id="zd_car_item_sum"></td>
                      </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <button type="submit" name="search" class="btn btn-primary" formaction="/report_importer/car">Скачать (максимум — 31 день)</button>
                    <button id="zd_car_ajax" type="button" name="ajax" class="btn btn-warning">Сводная форма <i id="zd_car_loader" style="display: none;" data-feather="loader"></i></button>
                    <button type="submit" id="all_cars_report" class="btn btn-success ml-3" formaction="/report_importer/cars_all_time">Скачать заявки</button>
                  </div>
                </div>

              </form>
              <!-- /тело отчета -->
            </div>
          </div>
        </div>
        <!-- /ТС -->
      </div>
      <!-- /детальный отчет -->
    </div>
  </div>

  <div class="row">
      <div class="col-5">
        <!-- детальный отчет -->
        <div class="accordion my-3" id="good_accordionReport">
          <!-- товары -->
          <div class="card">
            <div class="card-header" id="hGood">
              <h2 class="mb-0">
                <button class="btn btn-dark collapsed" type="button" data-toggle="collapse" data-target="#cGood" aria-expanded="false" aria-controls="cGood">
                  Детальный отчет, товары и упаковка  <span class="badge badge-warning" style="font-size: 14px;">{{ constant('ROP') }}</span>
                </button>
              </h2>
            </div>
            <div id="cGood" class="collapse" aria-labelledby="hGood" data-parent="#good_accordionReport">
              <div class="card-body">
                <!-- тело отчета -->
                <form method="POST" id="goods_form" action="/report_importer/goods" autocomplete="off">
                  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                  <!-- группа -->
                  <div class="row">
                    <div class="col">
                      <h5>Наименование, БИН, наименование товара:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="title" id="title" type="text" class="form-control" placeholder="Часть или полное наименование импортера / производителя">
                    </div>
                    <div class="col">
                      <input name="bin" id="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                    </div>
                    <div class="col">
                      <input name="good_name" id="good_name" type="text" class="form-control" placeholder="Наименование товара">
                    </div>
                    <div class="col">
                      <input name="up_name" id="up_name" type="text" class="form-control" placeholder="Наименование упаковки">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col-sm-12">
                      <h5>Код ТН ВЭД:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <select name="good_tncode[]" id="good_tncode" class="form-control" multiple="multiple">
                        <option value=""></option>
                        {% for code in tn_codes %}
                          <option value="{{ code.code }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
                        {% endfor %}
                      </select>
                    </div>
                  </div>
                  <!-- конец группы -->

                  <div class="row mt-3">
                    <div class="col-sm-12">
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#good-date-collapse" aria-expanded="false" aria-controls="good-date-collapse">Фильтр по дате</button>
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#good-reg-collapse" aria-expanded="false" aria-controls="good-reg-collapse">Фильтр по региону</button>
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#good-volume-collapse" aria-expanded="false" aria-controls="good-volume-collapse">Фильтр по массе и платежу</button>
                    </div>
                  </div>
                  <hr>
                  <!-- collapse date -->
                  <div class="collapse" id="good-date-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате создания заявки:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                      </div>
                      <div class="col">
                        <input name="dend" id="dend" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате отправки модератору:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате импорта:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                      </div>
                      <div class="col">
                        <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате выдачи документа о полноте:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_global" id="dstart_global" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                      </div>
                      <div class="col">
                        <input name="dend_global" id="dend_global" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате реализации:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_real" id="dstart_real" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                      </div>
                      <div class="col">
                        <input name="dend_real" id="dend_real" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo STARTROP; ?>"
                               data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <hr>
                  </div>
                  <!-- end collapse -->

                  <!-- collapse region -->
                  <div class="collapse" id="good-reg-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по стране экспорта / производства:</h5>
                      </div>
                      <div class="col">
                        <h5>Фильтр по региону импорта (город, область):</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <div class="form-group">
                          {{ select([
                            'country',
                            ref_country,
                            'using': ['id', 'name'],
                            'id': 'country',
                            'class': 'form-control',
                            'useEmpty': true
                          ]) }}
                        </div>
                      </div>
                      <div class="col">
                        <input name="icity" id="icity" type="text" class="form-control" placeholder="Нур-Султан">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <hr>
                  </div>
                  <!-- end collapse -->

                  <!-- collapse volume -->
                  <div class="collapse" id="good-volume-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Масса упаковки, кг:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="up_min" id="up_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="up_max" id="up_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Масса товара, кг:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="volume_min" id="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="volume_max" id="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Размер платежа:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="total_min" id="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="total_max" id="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <div class="row"><div class="col"><hr></div></div>
                  </div>
                  <!-- end collapse -->

                  <div class="row" style="display: none;" id="item_goods_table">
                    <div class="col">
                      <h5>Сводная форма</h5>
                      <table class="table table-stripped table-bordered">
                        <thead>
                        <tr>
                          <th>Количество</th>
                          <th>Вес</th>
                          <th>Сумма</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                          <td id="item_goods_count"></td>
                          <td id="item_goods_weight"></td>
                          <td id="item_goods_sum"></td>
                        </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <button type="submit" name="search" class="btn btn-primary">Скачать (максимум — 31 день)</button>
                      <button id="goods_ajax" type="button" name="ajax" class="btn btn-warning">Сводная форма <i id="goods_loader" style="display: none;" data-feather="loader"></i></button>
                    </div>
                  </div>

                </form>
                <!-- /тело отчета -->
              </div>
            </div>
          </div>
          <!-- /товары -->
        </div>
        <!-- /детальный отчет -->
      </div>
      <div class="col-7">
        <!-- детальный отчет -->
        <div class="accordion my-3" id="zd_good_accordionReport">
          <!-- товары -->
          <div class="card">
            <div class="card-header" id="zd_hGood">
              <h2 class="mb-0">
                <button class="btn btn-dark collapsed" type="button" data-toggle="collapse" data-target="#zd_cGood" aria-expanded="false" aria-controls="zd_cGood">
                  Детальный отчет, товары и упаковка  <span class="badge badge-success" style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
                </button>
              </h2>
            </div>
            <div id="zd_cGood" class="collapse" aria-labelledby="zd_hGood" data-parent="#zd_good_accordionReport">
              <div class="card-body">
                <!-- тело отчета -->
                <form method="POST" id="zd_goods_form" action="/report_importer/goods" autocomplete="off">
                  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                  <!-- группа -->
                  <div class="row">
                    <div class="col">
                      <h5>Наименование, БИН, наименование товара:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="title" type="text" class="form-control" placeholder="Часть или полное наименование импортера / производителя">
                    </div>
                    <div class="col">
                      <input name="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                    </div>
                    <div class="col">
                      <input name="good_name" type="text" class="form-control" placeholder="Наименование товара">
                    </div>
                    <div class="col">
                      <input name="up_name" type="text" class="form-control" placeholder="Наименование упаковки">
                    </div>
                  </div>
                  <!-- конец группы -->

                  <!-- группа -->
                  <div class="row mt-3">
                    <div class="col-sm-12">
                      <h5>Код ТН ВЭД:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <select name="good_tncode[]" class="form-control" multiple="multiple">
                        <option value=""></option>
                        {% for code in tn_codes %}
                          <option value="{{ code.code }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
                        {% endfor %}
                      </select>
                    </div>
                  </div>
                  <!-- конец группы -->

                  <div class="row mt-3">
                    <div class="col-sm-12">
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-good-date-collapse" aria-expanded="false" aria-controls="zd-good-date-collapse">Фильтр по дате</button>
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-good-reg-collapse" aria-expanded="false" aria-controls="zd-good-reg-collapse">Фильтр по региону</button>
                      <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-good-volume-collapse" aria-expanded="false" aria-controls="zd-good-volume-collapse">Фильтр по массе и платежу</button>
                    </div>
                  </div>
                  <hr>
                  <!-- collapse date -->
                  <div class="collapse" id="zd-good-date-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате создания заявки:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                      <div class="col">
                        <input name="dend" id="dend" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="31.01.2022">
                      </div>
                    </div>
                    <!-- конец группы -->

                     <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате отправки модератору:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                              data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                              data-date-end-date="0d" class="form-control datepicker"
                              placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                      <div class="col">
                        <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                              data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                              data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате импорта:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                      <div class="col">
                        <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="31.01.2022">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате выдачи документа о полноте:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_global" id="dstart_global" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                      <div class="col">
                        <input name="dend_global" id="dend_global" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="31.01.2022">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по дате реализации:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="dstart_real" id="dstart_real" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                      </div>
                      <div class="col">
                        <input name="dend_real" id="dend_real" type="text" data-provide="datepicker"
                               data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                               data-date-end-date="0d"
                               class="form-control datepicker"
                               placeholder="31.01.2022">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <hr>
                  </div>
                  <!-- end collapse -->

                  <!-- collapse region -->
                  <div class="collapse" id="zd-good-reg-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Фильтр по стране экспорта / производства:</h5>
                      </div>
                      <div class="col">
                        <h5>Фильтр по региону импорта (город, область):</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <div class="form-group">
                          {{ select([
                            'country',
                            ref_country,
                            'using': ['id', 'name'],
                            'id': 'country',
                            'class': 'form-control',
                            'useEmpty': true
                          ]) }}
                        </div>
                      </div>
                      <div class="col">
                        <input name="icity" type="text" class="form-control" placeholder="Нур-Султан">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <hr>
                  </div>
                  <!-- end collapse -->

                  <!-- collapse volume -->
                  <div class="collapse" id="zd-good-volume-collapse">
                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Масса упаковки, кг:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="up_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="up_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Масса товара, кг:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->

                    <!-- группа -->
                    <div class="row mt-3">
                      <div class="col">
                        <h5>Размер платежа:</h5>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <input name="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                      </div>
                      <div class="col">
                        <input name="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                      </div>
                    </div>
                    <!-- конец группы -->
                    <div class="row"><div class="col"><hr></div></div>
                  </div>
                  <!-- end collapse -->

                  <div class="row" style="display: none;" id="zd_item_goods_table">
                    <div class="col">
                      <h5>Сводная форма</h5>
                      <table class="table table-stripped table-bordered">
                        <thead>
                        <tr>
                          <th>Количество</th>
                          <th>Вес</th>
                          <th>Сумма</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                          <td id="zd_item_goods_count"></td>
                          <td id="zd_item_goods_weight"></td>
                          <td id="zd_item_goods_sum"></td>
                        </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <button type="submit" name="search" class="btn btn-primary">Скачать (максимум — 31 день)</button>
                      <button id="zd_goods_ajax" type="button" name="ajax" class="btn btn-warning">
                        Сводная форма
                        <i id="zd_goods_loader" style="display: none;" data-feather="loader"></i></button>
                    </div>
                  </div>

                </form>
                <!-- /тело отчета -->
              </div>
            </div>
          </div>
          <!-- /товары -->
        </div>
        <!-- /детальный отчет -->
      </div>
  </div>
  <br>
  <!-- KPP -->
  <div class="row">
    <div class="col-5">
      <div class="card">
        <div class="card-header" id="hKpp">
          <h2 class="mb-0">
            <button class="btn btn-dark collapsed" type="button" data-toggle="collapse" data-target="#cKpp" aria-expanded="false" aria-controls="cKpp">
              Детальный отчет, Кабельно-проводниковые продукции
              <span class="badge badge-warning" style="font-size: 14px;">{{ constant('ROP') }}</span>
            </button>
          </h2>
        </div>
        <div id="cKpp" class="collapse" aria-labelledby="hKpp" data-parent="#accordionReport">
          <div class="card-body">
            <!-- тело отчета -->
            <form method="POST" id="kpps_form" action="/report_importer/kpps" autocomplete="off">
              <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
              <!-- группа -->
              <div class="row">
                <div class="col">
                  <h5>Наименование, БИН:</h5>
                </div>
              </div>

              <div class="row">
                <div class="col">
                  <input name="title" id="title" type="text" class="form-control" placeholder="Часть или полное наименование импортера / производителя">
                </div>
                <div class="col">
                  <input name="bin" id="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                </div>
              </div>
              <!-- конец группы -->

              <!-- группа -->
              <div class="row mt-3">
                <div class="col-sm-12">
                  <h5>Код ТН ВЭД:</h5>
                </div>
              </div>

              <div class="row">
                <div class="col">
                  <select name="kpp_tncode[]" id="kpp_tncode" class="form-control" multiple="multiple">
                    <option value=""></option>
                    {% for code in kpp_tn_codes %}
                      <option value="{{ code.code }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
                    {% endfor %}
                  </select>
                </div>
              </div>
              <!-- конец группы -->

              <div class="row mt-3">
                <div class="col-sm-12">
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#kpp-date-collapse" aria-expanded="false" aria-controls="good-date-collapse">Фильтр по дате</button>
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#kpp-reg-collapse" aria-expanded="false" aria-controls="good-reg-collapse">Фильтр по странам</button>
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#kpp-volume-collapse" aria-expanded="false" aria-controls="good-volume-collapse">Фильтр по массе и платежу</button>
                </div>
              </div>

              <hr>

              <!-- collapse date -->
              <div class="collapse" id="kpp-date-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате создания заявки:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                  </div>
                  <div class="col">
                    <input name="dend" id="dend" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                </div>
                <!-- конец группы -->

                <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                        <h5>Фильтр по дате отправки модератору:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo STARTROP; ?>">
                    </div>
                    <div class="col">
                      <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo STARTROP; ?>"
                             data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                  </div>
                  <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате импорта:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                  </div>
                  <div class="col">
                    <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                </div>
                <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате выдачи документа о полноте:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart_global" id="dstart_global" type="text"data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker" placeholder="<?php echo STARTROP; ?>">
                  </div>
                  <div class="col">
                    <input name="dend_global" id="dend_global" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo STARTROP; ?>"
                           data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                </div>
                <!-- конец группы -->
              </div>
              <!-- end collapse -->

              <!-- collapse region -->
              <div class="collapse" id="kpp-reg-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по стране экспорта / производства:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <div class="form-group">
                      {{ select([
                        'country',
                        ref_country,
                        'using': ['id', 'name'],
                        'id': 'country',
                        'class': 'form-control',
                        'useEmpty': true
                      ]) }}
                    </div>
                  </div>
                </div>
                <!-- конец группы -->
                <hr>
              </div>
              <!-- end collapse -->

              <!-- collapse volume -->
              <div class="collapse" id="kpp-volume-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Масса товара, кг:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="volume_min" id="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                  </div>
                  <div class="col">
                    <input name="volume_max" id="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                  </div>
                </div>
                <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Размер платежа:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="total_min" id="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                  </div>
                  <div class="col">
                    <input name="total_max" id="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                  </div>
                </div>
                <!-- конец группы -->
                <div class="row"><div class="col"><hr></div></div>
              </div>
              <!-- end collapse -->

              <div class="row mt-1" style="display: none;" id="item_kpps_table">
                <div class="col">
                  <p><strong>Сводная форма</strong></p>
                  <table class="table table-stripped table-bordered">
                    <thead>
                    <tr>
                      <th>Количество</th>
                      <th>Вес</th>
                      <th>Сумма</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                      <td id="item_kpps_count"></td>
                      <td id="item_kpps_weight"></td>
                      <td id="item_kpps_sum"></td>
                    </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="row mt-1">
                <div class="col">
                  <button type="submit" name="search" class="btn btn-primary">Скачать (максимум — 31 день)</button>
                  <button id="kpps_ajax" type="button" name="ajax" class="btn btn-warning">Сводная форма <i id="kpps_loader" style="display: none;" data-feather="loader"></i></button>
                </div>
              </div>
            </form>
            <!-- /тело отчета -->
          </div>
        </div>
      </div>
    </div>
    <div class="col-7">
      <div class="card">
        <div class="card-header" id="zd_hKpp">
          <h2 class="mb-0">
            <button class="btn btn-dark collapsed" type="button" data-toggle="collapse" data-target="#zd_cKpp" aria-expanded="false" aria-controls="zd_cKpp">
              Детальный отчет, Кабельно-проводниковые продукции
              <span class="badge badge-success" style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
            </button>
          </h2>
        </div>
        <div id="zd_cKpp" class="collapse" aria-labelledby="zd_hKpp" data-parent="#accordionReport">
          <div class="card-body">
            <!-- тело отчета -->
            <form method="POST" id="zd_kpps_form" action="/report_importer/kpps" autocomplete="off">
              <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
              <!-- группа -->
              <div class="row">
                <div class="col">
                  <h5>Наименование, БИН:</h5>
                </div>
              </div>

              <div class="row">
                <div class="col">
                  <input name="title" type="text" class="form-control" placeholder="Часть или полное наименование импортера / производителя">
                </div>
                <div class="col">
                  <input name="bin" type="text" class="form-control" placeholder="БИН импортера / производителя">
                </div>
              </div>
              <!-- конец группы -->

              <!-- группа -->
              <div class="row mt-3">
                <div class="col-sm-12">
                  <h5>Код ТН ВЭД:</h5>
                </div>
              </div>

              <div class="row">
                <div class="col">
                  <select name="kpp_tncode[]" class="form-control" multiple="multiple">
                    <option value=""></option>
                    {% for code in kpp_tn_codes %}
                      <option value="{{ code.code }}">{{ t._(code.code)~" - "~t._(code.name) }}</option>
                    {% endfor %}
                  </select>
                </div>
              </div>
              <!-- конец группы -->

              <div class="row mt-3">
                <div class="col-sm-12">
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-kpp-date-collapse" aria-expanded="false" aria-controls="zd-good-date-collapse">Фильтр по дате</button>
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-kpp-reg-collapse" aria-expanded="false" aria-controls="zd-good-reg-collapse">Фильтр по странам</button>
                  <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#zd-kpp-volume-collapse" aria-expanded="false" aria-controls="zd-good-volume-collapse">Фильтр по массе и платежу</button>
                </div>
              </div>

              <hr>

              <!-- collapse date -->
              <div class="collapse" id="zd-kpp-date-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате создания заявки:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                  <div class="col">
                    <input name="dend" id="dend" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="31.01.2022">
                  </div>
                </div>
                <!-- конец группы -->

                 <!-- группа -->
                  <div class="row mt-3">
                    <div class="col">
                      <h5>Фильтр по дате отправки модератору:</h5>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col">
                      <input name="dstart_md_dt_sent" id="dstart" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker"
                             placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                    </div>
                    <div class="col">
                      <input name="dend_md_dt_sent" id="dend" type="text" data-provide="datepicker"
                             data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                             data-date-end-date="0d" class="form-control datepicker" placeholder="31.01.2022">
                    </div>
                  </div>
                <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате импорта:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart_import" id="dstart" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                  <div class="col">
                    <input name="dend_import" id="dend" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="31.01.2022">
                  </div>
                </div>
                <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по дате выдачи документа о полноте:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="dstart_global" id="dstart" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>">
                  </div>
                  <div class="col">
                    <input name="dend_global" id="dend" type="text" data-provide="datepicker"
                           data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                           data-date-end-date="0d"
                           class="form-control datepicker"
                           placeholder="31.01.2022">
                  </div>
                </div>
                <!-- конец группы -->
              </div>
              <!-- end collapse -->

              <!-- collapse region -->
              <div class="collapse" id="zd-kpp-reg-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Фильтр по стране экспорта / производства:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <div class="form-group">
                      {{ select([
                        'country',
                        ref_country,
                        'using': ['id', 'name'],
                        'id': 'country',
                        'class': 'form-control',
                        'useEmpty': true
                      ]) }}
                    </div>
                  </div>
                </div>
                <!-- конец группы -->
                <hr>
              </div>
              <!-- end collapse -->

              <!-- collapse volume -->
              <div class="collapse" id="zd-kpp-volume-collapse">
                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Масса товара, кг:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="volume_min" type="number" class="form-control" placeholder="Минимальное значение">
                  </div>
                  <div class="col">
                    <input name="volume_max" type="number" class="form-control" placeholder="Максимальное значение">
                  </div>
                </div>
                <!-- конец группы -->

                <!-- группа -->
                <div class="row mt-3">
                  <div class="col">
                    <h5>Размер платежа:</h5>
                  </div>
                </div>

                <div class="row">
                  <div class="col">
                    <input name="total_min" type="number" class="form-control" placeholder="Минимальное значение">
                  </div>
                  <div class="col">
                    <input name="total_max" type="number" class="form-control" placeholder="Максимальное значение">
                  </div>
                </div>
                <!-- конец группы -->
                <div class="row"><div class="col"><hr></div></div>
              </div>
              <!-- end collapse -->

              <div class="row mt-1" style="display: none;" id="zd_item_kpps_table">
                <div class="col">
                  <p><strong>Сводная форма</strong></p>
                  <table class="table table-stripped table-bordered">
                    <thead>
                    <tr>
                      <th>Количество</th>
                      <th>Вес</th>
                      <th>Сумма</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                      <td id="zd_item_kpps_count"></td>
                      <td id="zd_item_kpps_weight"></td>
                      <td id="zd_item_kpps_sum"></td>
                    </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="row mt-1">
                <div class="col">
                  <button type="submit" name="search" class="btn btn-primary">Скачать (максимум — 31 день)</button>
                  <button id="zd_kpps_ajax" type="button" name="ajax" class="btn btn-warning">
                    Сводная форма
                    <i id="zd_kpps_loader" style="display: none;" data-feather="loader"></i></button>
                </div>
              </div>
            </form>
            <!-- /тело отчета -->
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /kpp -->
  <br>
  <div class="row">
    <div class="col-5">
      <!-- Отчет Банк и бухгалтерия(Оператор РОП) -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Банк и бухгалтерия(детальный, максимум 31 день)") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/bank" autocomplete="off">
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
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <!-- /Отчет Банк и бухгалтерия(Оператор РОП)  -->
    </div>
    <div class="col-7">
      <!-- Отчет Банк и бухгалтерия(Жасыл даму) -->
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Банк и бухгалтерия(детальный, максимум 31 день)") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/bank_zd" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
              <div class="col">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       data-date-end-date="0d"
                       class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
              </div>
              <div class="col">
                <input name="dend" id="dend" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       data-date-end-date="0d"
                       class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y'); ?>" required>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <!-- /Отчет Банк и бухгалтерия(Жасыл даму)  -->
    </div>
  </div>

  <!-- Отчет по стимулированию -->
  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по стимулированию") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
                <div class="col-2">
                  <select name="fund_uid" data-size="5" class="selectpicker form-control" data-live-search="true"  data-live-search-placeholder="Введите БИН или Название организации" >
                    <option value="all" selected> - Показать все  (<?php echo count($fund_companies); ?>) - </option>
                    {% for i, company in fund_companies %}
                      <option value="{{ company.user_id }}">
                        {{ (company.bin != '') ? company.bin : company.idnum }}
                        <p>(<?php echo strtr($company->name, $trans); ?>)</p>
                      </option>
                    {% endfor %}
                  </select>
                </div>
                <div class="col-2">
                  <select name="dt_type" class="form-control">
                      <option value="2">По дате отправки</option>
                      <option value="1">По дате оплаты</option>
                      <option value="0">По дате создания</option>
                  </select>
                </div>
                {% if auth.isAccountant()  %}
                <div class="col">
                    <select name="status" class="form-control">
                        <option value="0">Оплачено</option>
                        <option value="1">На оплате</option>
                    </select>
                 </div>
                 {% endif  %}
              <div class="col-2">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo STARTROP; ?>"
                       data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       class="form-control datepicker" placeholder="<?php echo STARTROP; ?>" required>
              </div>
              <div class="col-2">
                <input name="dend" id="dend" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo STARTROP; ?>"
                       data-date-end-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       class="form-control datepicker" placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
              </div>
              <div class="col-2">
                <label>
                  <input type="checkbox" name="withAnnullments" value="1">
                  С учетом аннулированных позиций на момент выгрузки
                </label>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по стимулированию") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-row">
              <div class="col-2">
                <select name="fund_uid" data-size="5" class="selectpicker form-control" data-live-search="true" data-live-search-placeholder="Введите БИН или Название организации" >
                  <option value="all" selected> - Показать все  (<?php echo count($fund_companies); ?>) - </option>
                  {% for i, company in fund_companies %}
                    <option value="{{ company.user_id }}">
                      {{ (company.bin != '') ? company.bin : company.idnum }}
                      <p>(<?php echo strtr($company->name, $trans); ?>)</p>
                    </option>
                  {% endfor %}
                </select>
              </div>
              <div class="col-2">
                <select name="dt_type" class="form-control">
                  <option value="2">По дате отправки</option>
                  <option value="1">По дате оплаты</option>
                  <option value="0">По дате создания</option>
                </select>
              </div>
              {% if auth.isAccountant()  %}
                <div class="col">
                  <select name="status" class="form-control">
                    <option value="0">Оплачено</option>
                    <option value="1">На оплате</option>
                  </select>
                </div>
              {% endif  %}
              <div class="col-2">
                <input name="dstart" id="dstart" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       data-date-end-date="0d" class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>" required>
              </div>
              <div class="col-2">
                <input name="dend" id="dend" type="text" data-provide="datepicker"
                       data-date-start-date="<?php echo date('d.m.Y', START_ZHASYL_DAMU); ?>"
                       data-date-end-date="0d" class="form-control datepicker"
                       placeholder="<?php echo date('d.m.Y'); ?>" required>
              </div>
              <div class="col-2">
                  <label>
                    <input type="checkbox" name="withAnnullments" value="1">
                    С учетом аннулированных позиций на момент выгрузки
                  </label>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- /Отчет по стимулированию -->

  {% if auth is defined and (auth.isSuperModerator() or auth.isAccountant()) %}
  <!-- Отчет по стимулированию другой шаблон -->
  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по стимулированию(детальный, максимум 31 день)") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund_supermoderator" autocomplete="off">
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
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Отчет по стимулированию(детальный, максимум 31 день)") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund_supermoderator" autocomplete="off">
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
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- /Отчет по стимулированию другой шаблон -->
  {% endif %}

  {% if (auth.isAccountant() or auth.isAdminSoft()) %}
  <!-- Детальный отчет по стимулированию(only for Accountants) -->
  <div class="row">
    <div class="col-5">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Детальный отчет по стимулированию") }}
          <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund_detailed" autocomplete="off">
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
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-7">
      <div class="card my-3">
        <div class="card-header bg-dark text-light">
          {{ t._("Детальный отчет по стимулированию") }}
          <span class="badge badge-success " style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="/report_importer/fund_detailed" autocomplete="off">
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
                <button type="submit" class="btn btn-primary">
                  <i data-feather="download" width="14" height="14"></i> {{ t._("download") }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- /Детальный отчет по стимулированию(only for Accountants) -->
  {% endif %}

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

