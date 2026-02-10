<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h5 class="mb-0">
            Заявка №{{ offset_fund.id }}
            <span class="text-muted">({{ offset_fund.ref_fund_key.name }})</span>
        </h5>
        <small class="text-muted">Финансирование методом взаимозачета</small>
    </div>
    <div class="col-md-6 text-right text-end">
        {% if offset_fund.entity_type === 'CAR' %}
            <?php if(str_contains($offset_fund->ref_fund_key->name, 'TRACTOR') || str_contains($offset_fund->ref_fund_key->name, 'COMBAIN')) {?>
            <a href="/offset_fund_car/check/{{ offset_fund.id }}" class="btn btn-success">
                <i class="fa fa-plus"></i> Добавить с/х-технику
            </a>
            <?php } else {?>
            <a href="/offset_fund_car/check/{{ offset_fund.id }}" class="btn btn-success">
                <i class="fa fa-plus"></i> Добавить автомобиль
            </a>
            <?php }?>
            <a href="/offset_fund_car/import/{{ offset_fund.id }}" class="btn btn-success">
                <i class="fa fa-file-excel"></i> Импорт
            </a>
        {% else %}
            <a href="/offset_fund_goods/new/{{ offset_fund.id }}" class="btn btn-success">
                <i class="fa fa-plus"></i> Добавить товар
            </a>
            <a href="/offset_fund_goods/import/{{ offset_fund.id }}" class="btn btn-success">
                <i class="fa fa-file-excel"></i> Импорт
            </a>
        {% endif %}
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">

        <div class="table-responsive mt-3">

            {% if offset_fund.entity_type === 'CAR' %}
                <table id="carsTable" class="table table-hover table-striped mb-0 align-middle" style="width:100%">
                    <thead class="thead-light">
                    <tr>
                        <th>VIN</th>
                        <th>Объем/Вес</th>
                        <th>Категория</th>
                        <th>Дата импорта</th>
                        <th>Год производства</th>
                        <th>Страна производства</th>
                        <th>Действие</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if offset_fund_cars|length > 0 %}
                        {% for item in offset_fund_cars %}
                            <tr>
                                <td>{{ item.vin }}</td>
                                <td>{{ item.volume }}</td>
                                <td>{{ t._(item.ref_car_cat.tech_category) }}</td>
                                <td>{{ item.import_at|date_format }}</td>
                                <td>{{ item.manufacture_year }}</td>
                                <td>{{ item.ref_country.name }}</td>
                                <td>
                                    <a href="{{ url('offset_fund_car/edit/' ~ item.id) }}"
                                       class="btn btn-sm btn-primary" title="Редактировать">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="{{ url('offset_fund_car/delete/' ~ item.id) }}"
                                       class="btn btn-sm btn-danger" title="Удалить"
                                       onclick="return confirm('Удалить')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            {% else %}
                <table id="carsTable" class="table table-hover table-striped mb-0 align-middle" style="width:100%">
                    <thead class="thead-light">
                    <tr>
                        <th>Счет фактура</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% if offset_fund_goods is defined and offset_fund_goods|length > 0 %}
                        {% for item in offset_fund_goods %}
                            <tr>
                                <td>{{ item.basis }}</td>
                                <td>{{ item.basis_at }}</td>
                                <td>{{ item.weight }}</td>
                            </tr>
                        {% endfor %}
                    {% endif %}
                    </tbody>
                </table>
            {% endif %}
        </div>

        <hr>

        <a href="/offset_fund/generate_application/{{ offset_fund.id }}" class="btn btn-primary">
            Сформировать заявление
        </a>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('#carsTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            stateSave: true,
            order: [],
            autoWidth: false,
            columnDefs: [{orderable: false, targets: -1}],
            language: {url: '/assets/js/ru.json'}
        });
    });
</script>