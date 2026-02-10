<!-- содержимое заявки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
    {{ t._("Содержимое заявки") }} <span class="badge badge-success mb-2" style="font-size: 14px;">{{ t._("Заявка №") }} {{ fund.number }}</span>
    </div>
    <div class="card-body">
        <h4 class="mb-3">Кол.машин в заявке: <b>{{ count }}</b></h4>
        <div class="row">
             <ul>
                 {% if page.items is defined %}
                     {% for item in page.items %}
                        <li> <b>{{ item.c_id }}</b> || VIN: <b>{{ item.c_vin }}</b> || Дата производства: <b><?php echo date("d-m-Y", $item->c_date_produce ); ?> </b> || {{ t._("Модель") }}: <b>{{ t._(item.c_cat) }} </b> || Объем/Масса <b>{{ item.c_volume }} </b> || Размер платежа: <b>{{ item.c_cost }} тг</b> || Седельный тягач?: <b><?php echo ($item->ref_st_type == 1) ? 'Да' : 'Нет'; ?></b></li>
                    {% endfor %}
                 {% endif %}
              </ul>
        </div>
        <div class="row">
            <div class="col-auto">
                <span class="btn btn-light">{{ page.current~"/"~page.total_pages }}</span>
            </div>
            <div class="col text-center">
                {% if page.total_pages > 1 %}
                    {{ link_to("fund_correction/car_annulment_list/"~fund.id, t._("Первая"), 'class': 'btn btn-secondary') }}
                    {{ link_to("fund_correction/car_annulment_list/"~fund.id~"?page="~page.before, '←', 'class': 'btn btn-secondary') }}
                    {{ link_to("fund_correction/car_annulment_list/"~fund.id~"?page="~page.next, '→', 'class': 'btn btn-secondary') }}
                    {{ link_to("fund_correction/car_annulment_list/"~fund.id~"?page="~page.last, t._("Последняя"), 'class': 'btn btn-secondary') }}
                {% endif %}
            </div>
        </div>
        <hr>
        <form id="annulFundCarForm" action="/fund_correction/annul_all_cars/" method="POST"  enctype="multipart/form-data">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label class="form-label">{{ t._("comment") }}</label>
                <textarea name="car_comment" id="annulFundCarComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">{{ t._("Загрузить файл") }}</label>
                <input type="file" id="annulFundCarFile" name="car_file" class="form-control-file" required>
            </div>
            <hr>
            <div class="row">
                <input type="hidden" name="fund_id" value="{{ fund.id }}">
                <input type="hidden" value="{{ sign_data }}" name="hash" id="annulFundCarHash">
                <textarea type="hidden" name="sign" id="annulFundCarSign" style="display: none;"></textarea>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger signAnnulFundCarsBtn">Подписать и аннулировать всех ТС</button>
                    <a href="/moderator_fund/view/{{ fund.id }}" class="btn btn-warning">Отмена</a>
                </div>
            </div>
        </form>
    </div>
</div>