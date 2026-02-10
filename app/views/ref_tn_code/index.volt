<!-- заголовок -->
<h2>{{ t._("Справочник кодов ТН ВЭД") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/ref_tn_code/" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="row">
                <div class="col-2">
                    <input name="num" type="text" class="form-control"
                           value="<?php echo isset($_SESSION['ref_tn_code_id']) ? $_SESSION['ref_tn_code_id'] : ''; ?>"
                           placeholder="{{ t._("Поиск по ID") }}">
                </div>
                <div class="col">
                    <input name="code" type="text" class="form-control"
                           value="<?php echo isset($_SESSION['ref_tn_code_tnvd']) ? $_SESSION['ref_tn_code_tnvd'] : ''; ?>"
                           placeholder="{{ t._("Поиск по код ТНВЭД") }}">
                </div>
                <div class="col">
                    <input name="name" type="text" class="form-control"
                           value="<?php echo isset($_SESSION['ref_tn_code_name']) ? $_SESSION['ref_tn_code_name'] : ''; ?>"
                           placeholder="{{ t._("Поиск по Наименованию") }}">
                </div>
                <div class="col">
                    <select name="status" class="form-control">
                        <option value="ALL"
                        <?php if(isset($_SESSION['ref_tn_code_status']) && $_SESSION['ref_tn_code_status'] == 'ALL') { echo 'selected';} ?>
                        >Все</option>
                        <option value="YES"
                        <?php if(isset($_SESSION['ref_tn_code_status']) && $_SESSION['ref_tn_code_status'] == 'YES') { echo 'selected';} ?>
                        >Активный</option>
                        <option value="NO"
                        <?php if(isset($_SESSION['ref_tn_code_status']) && $_SESSION['ref_tn_code_status'] == 'NO') { echo 'selected';} ?>
                        >Неактивный</option>
                    </select>
                </div>
                <div class="col">
                    <select name="type" class="form-control">
                        <option value="ALL"
                        <?php if(isset($_SESSION['ref_tn_code_type']) && $_SESSION['ref_tn_code_type'] == 'ALL') { echo 'selected';} ?>
                        >Все</option>
                        <option value="PRODUCT"
                        <?php if(isset($_SESSION['ref_tn_code_type']) && $_SESSION['ref_tn_code_type'] == 'PRODUCT') { echo 'selected';} ?>
                        >Товар</option>
                        <option value="PACKAGE"
                        <?php if(isset($_SESSION['ref_tn_code_type']) &&  $_SESSION['ref_tn_code_type'] == 'PACKAGE') { echo 'selected';} ?>
                        >Упаковка</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" name="search" class="btn btn-primary">{{ t._("Найти") }}</button>
                    <button type="submit" name="clear" value="clear"
                            class="btn btn-warning">{{ t._("Сбросить") }}</button>
                    <a href="/ref_tn_code/new" class="btn btn-success ml-2" target="_blank"><i data-feather="plus"></i>
                        Добавить</a>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<!-- Записи -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Список кодов ТН ВЭД") }}
    </div>
    <div class="card-body">
        <table class="table table-hover table-bordered">
            <colgroup>
                <col>
                <col>
                <col>
                <col>
                <col>
                <col span="1" class="error">
                <col span="2" class="warning">
                <col span="1" class="success">
            </colgroup>
            <thead>
            <tr>
                <th>{{ t._("ID") }}</th>
                <th>{{ t._("code") }}</th>
                <th>{{ t._("type") }}</th>
                <th width="10%">{{ t._("Относится к продукции с упаковкой") }}</th>
                <th width="15%">{{ t._("code-group") }}</th>
                <th width="25%">{{ t._("code-name") }}</th>
                <th>Коэффициент 1(с 27.01.2016 по 31.12.2019 (Пр.№12 от 16.01.19))</th>
                <th>Коэффициент 2(с 01.01.2020 по 16.01.2020 (Пр.№12 от 16.01.19))</th>
                <th>Коэффициент 3(с 17.01.2020 по 10.01.2022 (Пр.№12 от 16.01.19))</th>
                <th>Коэффициент 4(с 17.01.2020 по 10.01.2022 (Пр.№110 от 20.04.21))</th>
                <th>Коэффициент 5(с 17.01.2020 по 10.01.2022 (Пр.№136 от 13.05.21))</th>
                <th>Коэффициент 6(с 11.01.2022 (Пр.№136 от 13.05.21))</th>
                <th>Коэффициент 7(с 11.01.2022 (Пр.№689 от 09.11.22))</th>
                <th>{{ t._("Статус") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for ref_tn_code in page.items %}
                    <tr>
                        <td>{{ ref_tn_code.id }}</td>
                        <td>
                            <?php echo (string)$ref_tn_code->code;?>
                        </td>
                        <td>
                            {{ (ref_tn_code.type == 'PRODUCT') ?
                            '<h5><span class="badge badge-info">Товар</span></h5>'
                            :
                            '<h5><span class="badge badge-warning">Упаковка</span></h5>' }}
                        </td>
                        <td>
                            {{ (ref_tn_code.pay_pack == 1) ?
                            '<h5><span class="badge badge-info">Да</span></h5>'
                            :
                            '<h5><span class="badge badge-warning">Нет</span></h5>' }}
                        </td>
                        <td>{{ ref_tn_code.group }}</td>
                        <td>{{ ref_tn_code.name }}</td>
                        <td>{{ ref_tn_code.price1 }}</td>
                        <td>{{ ref_tn_code.price2 }}</td>
                        <td>{{ ref_tn_code.price3 }}</td>
                        <td>{{ ref_tn_code.price4 }}</td>
                        <td>{{ ref_tn_code.price5 }}</td>
                        <td>{{ ref_tn_code.price6 }}</td>
                        <td>{{ ref_tn_code.price7 }}</td>
                        <td>
                            {{ (ref_tn_code.is_correct == 1) ?
                            '<h5><span class="badge badge-success">Активный</span></h5>' :
                            '<h5><span class="badge badge-danger">Неактивный</span></h5>' }}
                        </td>
                        <td>
                            <!--             <a href="/ref_tn_code/edit/{{ ref_tn_code.id }}" class="btn btn-secondary btn-sm" target="_blank"> -->
                            <!--               <i data-feather="edit" width="14" height="14"></i> -->
                            <!--             </a> -->
                            <a href="#" class="btn btn-success btn-sm" data-toggle="modal" id="goodAmountCalculator"
                               data-id="{{ ref_tn_code.code }}" data-target=".goodAmountCalculatorModal">
                                <i class="fa fa-calculator"></i>
                            </a>
                        </td>
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>

        {% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}

    </div>
</div>
<!-- /Записи -->

<!-- Good amount calculator Modal -->
<div class="modal fade goodAmountCalculatorModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="myModalLabel">
                    {{ t._("trial_calculator") }}
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="trialCalculatorForm" action="/ref_tn_code/calculator" method="POST">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="form-group">
                        <label class="form-label"><b>{{ t._("Код ТН ВЭД") }}</b></label>
                        <div class="controls">
                            <input type="text" name="code" id="trial_calc_code" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><b>{{ t._("goods-weight") }}</b></label>
                        <div class="controls">
                            <input type="text" name="good_weight" id="good_weight" class="form-control"
                                   placeholder="0.000" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><b>{{ t._("Укажите дату") }}</b></label>
                        <div class="controls">
                            <input type="text" name="good_date" id="good_date" data-provide="datepicker"
                                   placeholder="{{ date('d.m.Y') }}"
                                   data-date-start-date="{{ constant('STARTROP') }}" data-date-end-date="0d"
                                   class="form-control datepicker" required autocomplete="off">
                        </div>
                    </div>

                    <hr>

                    <div class="form-group float-right">
                        <button type="submit" class="btn crud-submit btn-success">
                            <span class="spinner-border spinner-border-sm" id="trial_calculator_spinner"
                                  style="display: none"></span>
                            {{ t._("Рассчитать") }}
                        </button>
                    </div>
                    <div id="avto_result"></div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Good amount calculator Modal -->