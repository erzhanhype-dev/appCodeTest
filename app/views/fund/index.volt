<?php $status = json_decode($_SESSION['fund_filter_status'] ?? '[]', true) ?: []; ?>
<?php $type = json_decode($_SESSION['fund_filter_type'] ?? '[]', true) ?: []; ?>
<?php $entity_type = json_decode($_SESSION['fund_filter_entity_type'] ?? '[]', true) ?: []; ?>
<?php $s_year = json_decode($_SESSION['fund_filter_year'] ?? '[]', true) ?: []; ?>

<!-- заголовок -->
<div class="row">
    <div class="col-4">
        <h2>{{ t._("Список заявок") }}</h2>
    </div>
    <div class="col-2">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-6">
        <div class="float-right">
            <a href="#" class="btn btn-success btn-lg" data-toggle="modal" data-target="#fundAddModal">
                <i data-feather="plus" width="20" height="14"></i>
                Создать заявку на финансирования
            </a>
        </div>
    </div>
</div>

<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/fund/" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="row">
                <div class="col-4">
                    <label><b>Статус заявки:</b></label>
                    <?php $status = json_decode($_SESSION['fund_filter_status']); ?>
                    <select name="status[]" class="selectpicker form-control" multiple>
                        <option value="FUND_NEUTRAL"
                        <?php if(in_array('FUND_NEUTRAL', $status)) echo 'selected'; ?>
                        >{{ t._("FUND_NEUTRAL") }}</option>
                        <option value="FUND_DECLINED"
                        <?php if(in_array('FUND_DECLINED', $status)) echo 'selected'; ?>
                        >{{ t._("FUND_DECLINED") }}</option>
                        <option value="FUND_ANNULMENT"
                        <?php if(in_array('FUND_ANNULMENT', $status)) echo 'selected'; ?>
                        >{{ t._("FUND_ANNULMENT") }}</option>
                        <option value="FUND_TOSIGN"
                        <?php if(in_array('FUND_TOSIGN', $status)) echo 'selected'; ?>>{{ t._("FUND_TOSIGN") }}</option>
                        <option value="FUND_REVIEW"
                        <?php if(in_array('FUND_REVIEW', $status)) echo 'selected'; ?>>{{ t._("FUND_REVIEW") }}</option>
                        <option value="FUND_PREAPPROVED"
                        <?php if(in_array('FUND_PREAPPROVED', $status)) echo 'selected'; ?>
                        >{{ t._("FUND_PREAPPROVED") }}</option>
                        <option value="FUND_DONE"
                        <?php if(in_array('FUND_DONE', $status)) echo 'selected'; ?>>{{ t._("FUND_DONE") }}</option>
                    </select>
                </div>
                <div class="col-2">
                    <label><b>Год:</b></label>
                    <select name="year[]" class="selectpicker form-control" multiple>
                        <?php
                $s_year = json_decode($_SESSION['fund_filter_year']);
                $years = array();
                foreach(range(2020, (int)date("Y")) as $year) {
                  $years[] = $year;
                  if(in_array($year, $s_year)){
                    echo '<option value="'.$year.'" selected>'.$year.'</option>';
                        }else{
                        echo '
                        <option value="'.$year.'">'.$year.'</option>
                        ';
                        }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-2">
                    <label><b>Тип заявки:</b></label>
                    <?php $type = json_decode($_SESSION['fund_filter_type']); ?>
                    <select name="type[]" class="selectpicker form-control" multiple>
                        <option value="INS"
                        <?php if(in_array('INS', $type)) echo 'selected'; ?>>Внутреннее</option>
                        <option value="EXP"
                        <?php if(in_array('EXP', $type)) echo 'selected'; ?>>Экспорт</option>
                    </select>
                </div>
                <div class="col-2">
                    <label><b>Объект финансирования:</b></label>
                    <?php $entity_type = json_decode($_SESSION['fund_filter_entity_type']); ?>
                    <select name="entity_type[]" class="selectpicker form-control" multiple>
                        <option value="CAR"
                        <?php if(in_array('CAR', $entity_type)) echo 'selected'; ?>>ТС</option>
                        <option value="GOODS"
                        <?php if(in_array('GOODS', $entity_type)) echo 'selected'; ?>>Товар</option>
                    </select>
                </div>
                <div class="col-auto mt-4">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    <button type="submit" name="reset" value="all" class="btn btn-warning">Сбросить</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Заявки на финансирование") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr class="">
                <th>{{ t._("#") }}</th>
                <th>{{ t._("Тип") }}</th>
                <th>{{ t._("Значения") }}</th>
                <th>{{ t._("Сумма заявки, тенге") }}</th>
                <th>{{ t._("Создана") }}</th>
                <th>{{ t._("Отправлена") }}</th>
                <th>{{ t._("Стимулирование") }}</th>
                <th>{{ t._("Текущий статус") }}</th>
                <th>{{ t._("Операции") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page is defined and page.items|length > 0 %}
                {% for item in page.items %}
                    <tr class="{% if item.approve == 'FUND_DONE' %}table-success{% endif %}">
                        <td class="v-align-middle">{{ item.f_number }}</td>
                        <td class="v-align-middle">{{  t._(item.entity_type) }}</td>
                        <td class="v-align-middle"><?php echo (($item->entity_type == 'CAR') ? __getRefFundKeyDescription($item->ref_fund_key) : $item->ref_fund_key);?></td>
                        <td class="v-align-middle"><?php echo number_format($item->amount, 2, ",", "&nbsp;"); ?> &#8376;</td>
                        <td class="v-align-middle">{{ date("d.m.Y H:i", item.created) }}</td>
                        <td class="v-align-middle">{% if item.md_dt_sent > 0 %}{{ date("d.m.Y H:i", item.md_dt_sent) }}{% else %}—{% endif %}</td>
                        <td class="v-align-middle"><?php echo $item->type == 'INS' ? 'Внутреннее' : 'Экспорт'; ?></td>
                        <td class="v-align-middle">{{ t._(item.approve) }}</td>
                        <td class="v-align-middle" style="min-width: 84px;">
                            <a href="/fund/view/{{ item.id }}" title='{{ t._("browsing") }}'
                               class="btn btn-primary btn-sm"><i data-feather="eye" width="14" height="14"></i></a>
                            {% if item.blocked == 0 %}
                                <a href="/fund/edit/{{ item.id }}" title='{{ t._("edit") }}'
                                   class="btn btn-warning btn-sm"><i data-feather="edit" width="14" height="14"></i></a>
                            {% endif %}
                        </td>
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

<div class="modal fade fund_add_modal" tabindex="-1" role="dialog" aria-labelledby="fundAddModalLabel"
     aria-hidden="true" id="fundAddModal">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t._("Создание заявки") }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">
                <form action="/fund/new" method="get" autocomplete="off">
                    <div class="form-group" id="order">
                        <label class="form-label">{{ t._("Объект финансирования") }}</label>
                        <select name="object" id="fund_type" class="form-control">
                            <option value="CAR">ТС</option>
                            <option value="GOODS">Товар</option>
                        </select>
                    </div>
                    <div class="form-group" id="order">
                        <label class="form-label">{{ t._("Тип финансирования") }}</label>
                        <select name="mode" id="fund_type" class="form-control">
                            <option value="INS">Внутренний</option>
                            <option value="EXP">Экспорт</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success" name="button">Создать</button>
                </form>
            </div>
        </div>
    </div>
</div>