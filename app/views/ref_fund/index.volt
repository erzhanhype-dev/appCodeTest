<?php
      if(isset($_SESSION['REF_FUND_SESSION_IDNUM'])){
        $s_idnum = $_SESSION['REF_FUND_SESSION_IDNUM'];
      }

      if(isset($_SESSION['REF_FUND_SESSION_KEY'])){
        $s_key = $_SESSION['REF_FUND_SESSION_KEY'];
      }

      if(isset($_SESSION['REF_FUND_SESSION_BEGIN']) ){
        $s_begin = $_SESSION['REF_FUND_SESSION_BEGIN'];
      }

      if(isset($_SESSION['REF_FUND_SESSION_END']) ){
        $s_end = $_SESSION['REF_FUND_SESSION_END'];
      }

      if(isset($_SESSION['REF_FUND_SESSION_OBJECT_TYPE']) ){
        $s_type = $_SESSION['REF_FUND_SESSION_OBJECT_TYPE'];
      }
?>

<!-- заголовок -->
<h2>{{ t._("Справочник лимитов (ТС/товар)") }}</h2>
<!-- /заголовок -->
<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/ref_fund/" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col-3">
                    <label><b>Поиск по БИН / Название организации:</b></label>
                    <select name="idnum" data-size="5" class="selectpicker form-control" data-live-search="true"
                            data-live-search-placeholder="Введите БИН или Название организации ">
                        <option value="0"
                        <?php if($s_idnum == 0) echo 'selected'; ?>> - Показать все (<?php echo count($companies); ?>)
                        - </option>
                        {% for i, company in companies %}
                            <option value="{{ company.idnum }}" {{ s_idnum == company.idnum ? 'selected' : '' }}>
                                БИН:{{ company.idnum }}
                                {% if company.name is not empty %}
                                    ({{ company.name }})
                                {% endif %}
                            </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label class="form-label"><b>{{ t._("Тип") }}</b></label>
                    <select name="type" class="form-control" id="ref_fund_type_select">
                        <option value="all"
                        <?php if($s_type == 'all') echo 'selected'; ?>> - Показать все - </option>
                        <option value="car"
                        <?php if($s_type == 'car') echo 'selected'; ?>>ТС</option>
                        <option value="goods"
                        <?php if($s_type == 'goods') echo 'selected'; ?>>Товар</option>
                    </select>
                </div>

                <div class="col-2">
                    <label class="form-label"><b>{{ t._("Ключ") }}</b></label>
                    <select name="key" class="form-control">
                        <option value="all"
                        <?php if($s_key == 'all') echo 'selected'; ?>> - Показать все - </option>
                        {% for i, key in keys %}
                            <option value="{{ key.name }}" <?php if($s_key == $key->name) echo 'selected'; ?>>{{ key.description }} ({{ key.name }}) </option>
                        {% endfor %}
                    </select>
                </div>

                <div class="col-2">
                    <label><b>От (Начало периода):</b></label>
                    <input type="date" value="<?php echo date($s_begin); ?>" class="form-control" name="begin"/>
                </div>
                <div class="col-2">
                    <label><b>До (Конец периода):</b></label>
                    <input type="date" value="<?php echo date($s_end); ?>" class="form-control" name="end"/>
                </div>
                <div class="col-auto mt-4">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    <button type="submit" name="reset" value="all" class="btn btn-warning">Сбросить</button>
                    {{ link_to("ref_fund/new/", '<i data-feather="plus" width="14" height="14"></i> Добавить', 'class': 'btn btn-success btn-sm ml-4') }}
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Лимиты") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ t._("ИИН / БИН") }}</th>
                <th>{{ t._("Наименование или ФИО") }}</th>
                <th>{{ t._("Начало периода") }}</th>
                <th>{{ t._("Конец периода") }}</th>
                <th>{{ t._("Год") }}</th>
                <th>{{ t._("Тип") }}</th>
                <th>{{ t._("Ключ (категория + диапазон, ТНВЭД)") }}</th>
                <th>{{ t._("Значение") }}</th>
                <th>{{ t._("Операции") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length > 0 %}
                {% for ref_fund in page.items %}
                    <tr>
                        <td>{{ ref_fund.id }}</td>
                        <td>{{ ref_fund.idnum }}</td>
                        <td>{{ ref_fund.name }}</td>
                        <td><?php echo date('d.m.Y', $ref_fund->prod_start); ?></td>
                        <td><?php echo date('d.m.Y', $ref_fund->prod_end); ?></td>
                        <td>{{ ref_fund.year }}</td>
                        <td>{{ ref_fund.entity_type == 'GOODS' ? 'Товар' : 'Транспорт' }}</td>
                        <td><?php echo __detect_in_cyrillic($ref_fund->key);?></td>
                        <td><?php echo __money($ref_fund->value); ?></td>
                        <td>
                            <a href="/ref_fund/delete/{{ ref_fund.id }}"
                               data-confirm='Вы действительно хотите удалить это?'
                               class="btn btn-danger btn-sm">
                                <i data-feather="trash" width="14" height="14"></i>
                            </a>
                        </td>
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
<!-- /table content -->

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}

<!-- общие количество -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Общая статистика по лимитам") }} (за <?php echo date('Y'); ?>)
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>#</th>
                <th>БИН организации</th>
                <th>Общие лимиты</th>
                <th>{{ t._("FUND_NEUTRAL") }}</th>
                <th>{{ t._("FUND_DECLINED") }}</th>
                <th>{{ t._("FUND_ANNULMENT") }}</th>
                <th>{{ t._("FUND_TOSIGN") }}</th>
                <th>{{ t._("FUND_REVIEW") }}</th>
                <th>{{ t._("FUND_PREAPPROVED") }}</th>
                <th>{{ t._("FUND_DONE") }}</th>
                <th>Доступные (в пилоте)</th>
            </tr>
            </thead>
            <tbody>
            {% for i, l in limits %}
                <tr>
                    <td>{{ i+1 }}</td>
                    <td>{{ l.idnum }}</td>
                    <td>
                        <span class="badge badge-warning p-1"><?php echo intval($l->limits_count);?></span>
                    </td>
                    <td>{{ l.FUND_NEUTRAL }}</td>
                    <td>{{ l.FUND_DECLINED }}</td>
                    <td>{{ l.FUND_ANNULMENT }}</td>
                    <td>{{ l.FUND_TOSIGN }}</td>
                    <td>{{ l.FUND_REVIEW }}</td>
                    <td>{{ l.FUND_PREAPPROVED }}</td>
                    <td>{{ l.FUND_DONE }}</td>
                    <td>
                        <?php $available_limit = $l->limits_count - $l->FUND_NEUTRAL - $l->FUND_TOSIGN - $l->FUND_REVIEW
                        - $l->FUND_PREAPPROVED - $l->FUND_DONE;?>
                        {% if available_limit > 0 %}
                            <span class="badge badge-success p-1">{{ available_limit }}</span>
                        {% else %}
                            <span class="badge badge-danger p-1">{{ available_limit }}</span>
                        {% endif %}
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
</div>
<!-- /общие количество -->
