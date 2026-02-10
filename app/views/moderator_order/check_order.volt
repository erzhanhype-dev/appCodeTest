<!-- заголовок -->
<h2 class="mt-4">{{ t._("Поиск по VIN") }} </h2>
<!-- /заголовок -->
<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/moderator_order/check_order">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="row">
                <div class="col">
                    <input name="vin" type="text" class="form-control" value="{{ (vin is defined) ? vin : NULL }}" placeholder="Введите номер VIN ">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
                    <a href="/moderator_order/check_order" type="button" class="btn btn-warning" >Сбросить</a>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->
{% if car is defined and car != '' %}
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("УП") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ t._("num-symbol") }}</th>
                <th>{{ t._("Номер заявки") }}</th>
                <th>{{ t._("volume-weight") }}</th>
                <th>{{ t._("Сумма, тенге") }}</th>
                <th>{{ t._("vin-code") }}</th>
                <th>{{ t._("year-of-manufacture") }}</th>
                <th>{{ t._("date-of-import") }}</th>
                <th>{{ t._("country-of-manufacture") }}</th>
                <th>{{ t._("operations") }}</th>
            </tr>
            </thead>
            <tbody>
                    <tr>
                        <td class="v-align-middle">{{ car.id }}</td>
                        <td class="v-align-middle"><a href="/moderator_order/view/{{ car.profile_id }}" target="_blank" >{{ car.profile_id }}</a></td>
                        <td class="v-align-middle">{{ car.volume }}</td>
                        <td class="v-align-middle"><?php echo number_format($car->cost, 2, ",", "&nbsp;"); ?></td>
                        <td class="v-align-middle"><b>{{ car.vin }}</b></td>
                        <td class="v-align-middle">{{ car.year }}</td>
                        <td class="v-align-middle">
                            <?php echo date("d.m.Y", convertTimeZone($car->date_import));?>
                        </td>
                        <td class="v-align-middle">{{ car_country.name }}</td>
                        <td class="v-align-middle">
                             <a href="/moderator_order/view/{{ car.profile_id }}" target="_blank" title='{{ t._("browsing") }}' class="btn btn-primary btn-sm"><i data-feather="eye" width="14" height="14"></i></a>
                        </td>
                    </tr>
            </tbody>
        </table>
    </div>
</div>
{% endif %}
{% if f_car is defined and f_car != '' %}
    <div class="card mt-3">
        <div class="card-header bg-dark text-light">
            {{ t._("Финансирование") }}
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr class="">
                    <th>{{ t._("ID") }}</th>
                    <th>{{ t._("Номер заявки") }}</th>
                    <th>{{ t._("volume-weight") }}</th>
                    <th>{{ t._("Сумма, тенге") }}</th>
                    <th>{{ t._("VIN или номер") }}</th>
                    <th>{{ t._("Стимулирование") }}</th>
                    <th>{{ t._("Дата производства") }}</th>
                    <th>{{ t._("Категория") }}</th>
                    <th>{{ t._("Операции") }}</th>
                </tr>
                </thead>
                <tbody>
                        <tr class="">
                            <td class="v-align-middle">{{ f_car.id }}</td>
                            <td class="v-align-middle"><a href="/moderator_fund/view/{{ f_car.fund_id }}" target="_blank" >{{ f_car.fund_id }}</a></td>
                            <td class="v-align-middle">
                                {{ f_car.volume }}
                            </td>
                            <td class="v-align-middle"><?php echo number_format($f_car->cost, 2, ",", "&nbsp;"); ?></td>
                            <td class="v-align-middle"><b>{{ f_car.vin }}</b></td>
                            <td class="v-align-middle"><?php echo $f_car_profile->type == 'INS' ? 'Внутреннее' : 'Экспорт'; ?></td>
                            <td class="v-align-middle">{{ date("d.m.Y", f_car.date_produce) }}</td>
                            <td class="v-align-middle">{{ t._(f_car_cat.name) }}</td>
                            <td class="v-align-middle">
                                <a href="/moderator_fund/view/{{ f_car.fund_id }}" target="_blank" title='{{ t._("browsing") }}' class="btn btn-primary btn-sm"><i data-feather="eye" width="14" height="14"></i></a>
                            </td>
                        </tr>

                </tbody>
            </table>
        </div>
    </div>
{% endif %}
<br>
{% if checkIn is defined and checkExp is defined %}
<div class="card-header bg-info text-light">
    <div class="row">
        <div class="col-6">
            {% if checkIn == 'YES' %}
                Внутреннее финансирование до АИС имеется:
                <span class="badge badge-success" style="font-size: 14px;">Да</span>
            {% else %}
                Внутреннее финансирование до АИС имеется:
                <span class="badge badge-warning" style="font-size: 14px;">НЕТ</span>
            {% endif %}
        </div>
        <div class="col-6">
            {% if checkExp == 'YES' %}
                Экпорт финансирование до АИС имеется:
                <span class="badge badge-success" style="font-size: 14px;">Да</span>
            {% else %}
                Экпорт финансирование до АИС имеется:
                <span class="badge badge-warning" style="font-size: 14px;">НЕТ</span>
            {% endif %}
        </div>
    </div>
</div>
{% endif %}
