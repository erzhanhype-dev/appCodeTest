 <?php $mc = mysqli_connect(getenv('DB_HOST'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); mysqli_set_charset($mc, "utf8"); mysqli_select_db($mc, getenv('DB_NAME')); ?>
 <!-- заголовок -->
  <h2> {{ t._("Корректирование финансирования") }}</h2>
  <!-- /заголовок -->

  <!-- форма поиска -->
  <div class="card mt-3">
    <div class="card-header bg-dark text-light">
      {{ t._("Поиск") }}
    </div>
    <div class="card-body">
      <form method="POST" action="/fund_correction/">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <div class="row">
          <div class="col-6">
            <label><b>Поиск по номер заявки:</b></label>
            <input name="num" id="num" type="text" class="form-control" value="<?=$_SESSION['FundCorrection_Filter']?>" placeholder="Например: САП-2021/2121 или 2121">
          </div>
          <div class="col-auto mt-4">
            <button type="submit"  class="btn btn-primary">{{ t._("search") }}</button>
            <button type="submit" name="clear" value="clear"  class="btn btn-warning">{{ t._("Сбросить") }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
   <!-- /форма поиска -->

   <!-- стимулирование -->
{% if fund != null %}
<div class="card mt-3">
  <div class="card-header bg-primary text-light">
    {{ t._("Заявка на финансирование") }}
  </div>
  <div class="card-body">
    <table class="table table table-hover">
      <thead>
        <tr class="">
          <th>{{ t._("Номер") }}</th>
          <th>{{ t._("Отправитель") }}</th>
          <th>{{ t._("Сумма заявки, тенге") }}</th>
          <th>{{ t._("Отправлена") }}</th>
          <th>{{ t._("Стимулирование") }}</th>
          <th>{{ t._("Состояние") }}</th>
          <th>{{ t._("Текущий статус") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr class="{% if fund.approve == 'FUND_DECLINED' %}table-danger{% endif %}{% if fund.approve == 'FUND_DONE' %}table-success{% endif %}">
          <td class="v-align-middle"><b>{{ fund.number }}</b></td>
          <td style="text-transform: uppercase;">
            <a href="/moderator_fund/view/{{ fund.id }}">
              <?php
                $id = $fund->user_id;
                $rs = mysqli_query($mc, 'SELECT `name` as title FROM company_detail WHERE user_id = '.$id.' LIMIT 1');
                if(mysqli_num_rows($rs) == 0) {
                  $rs = mysqli_query($mc, 'SELECT CONCAT(last_name, " ", first_name, " ", parent_name) as title FROM person_detail WHERE user_id = '.$id.' LIMIT 1');  
                }
                $rw = mysqli_fetch_assoc($rs);
                echo str_replace('ТОВАРИЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ', 'ТОО', $rw['title']);
              ?>
            </a>
          </td>
          <td class="v-align-middle"><?php echo number_format($fund->amount, 2, ",", "&nbsp;"); ?></td>
          <td class="v-align-middle">{% if fund.md_dt_sent > 0 %}{{ date("d.m.Y H:i", fund.md_dt_sent) }}{% else %}—{% endif %}</td>
          <td class="v-align-middle"><?php echo $fund->type == 'INS' ? 'Внутреннее' : 'Экспорт'; ?></td>
          <td class="v-align-middle">
            {% if fund.sign_hod == '' and (fund.approve == 'FUND_NEUTRAL' or fund.approve == 'FUND_DECLINED') %}<i data-feather="refresh-cw" width="14" height="14"></i>{% endif %}
            {% if fund.sign_hod == '' and fund.approve == 'FUND_PREAPPROVED' %}<i data-feather="clock" width="14" height="14"></i>{% endif %}
            {% if fund.sign_hod != '' %}<i data-feather="user" width="14" height="14"></i>{% endif %}
            {% if fund.sign_fad != '' %}<i data-feather="user" width="14" height="14"></i>{% endif %}
            {% if fund.sign_hop != '' %}<i data-feather="user" width="14" height="14"></i>{% endif %}
            {% if fund.sign_hof != '' %}<i data-feather="user" width="14" height="14"></i>{% endif %}
          </td>
          <td class="v-align-middle">{{ t._(fund.approve) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<!-- /стимулирование -->

<!-- /авто -->
<div class="row">
  <div class="col">
    <div class="card mt-1">
      <div class="card-body">
       <span class="badge badge-success mb-2" style="font-size: 16px;">Кол.машин в заявке: {{ count }}</span>
        <table class="table table-hover">
          <thead>
            <tr class="">
              <th>{{ t._("ID") }}</th>
              <th>{{ t._("Объем, вес или мощность") }}</th>
              <th>{{ t._("Сумма, тенге") }}</th>
              <th>{{ t._("VIN или номер") }}</th>
              <th>{{ t._("Дата производства") }}</th>
              <th>{{ t._("Категория") }}</th>
              <th>{{ t._("Операции") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if page.items is defined %}
            {% for item in page.items %}
            <tr class="">
              <td class="v-align-middle">{{ item.c_id }}</td>
              <td class="v-align-middle">
                {{ item.c_volume }}
                {% if item.c_type == constant('TRUCK') %}
                  {{ t._("кг") }}
                {% else %}
                  {{ t._("см") }}
                {% endif %}
              </td>
              <td class="v-align-middle"><?php echo number_format($item->c_cost, 2, ",", "&nbsp;"); ?></td>
              <td class="v-align-middle">{{ item.c_vin }}</td>
              <td class="v-align-middle">{{ date("d.m.Y", item.c_date_produce) }}</td>
              <td class="v-align-middle">{{ t._(item.c_cat) }}</td>
              <td class="v-align-middle">
                <?php $invisible =''; if($item->c_status == 'ANNULLED') { $invisible='style="display:none"'; echo '<b style="color:red">ТС аннулирован!</b>';}?>
                <a href="/fund_correction/view_car/{{ item.c_id }}" title={{ t._("edit-car") }} class="btn btn-primary btn-sm" <?php echo $invisible;?>><i data-feather="edit" width="14" height="14"></i></a>
              </td>
            </tr>
            {% endfor %}
            {% endif %}
          </tbody>
        </table>

        <div class="row">
          <div class="col-auto">
            <span class="btn btn-light">{{ page.current~"/"~page.total_pages }}</span>
          </div>
          <div class="col text-center">
            {% if page.total_pages > 1 %}
            {{ link_to("fund_correction/", t._("Первая"), 'class': 'btn btn-secondary') }}
            {{ link_to("fund_correction?page="~page.before, '←', 'class': 'btn btn-secondary') }}
            {{ link_to("fund_correction?page="~page.next, '→', 'class': 'btn btn-secondary') }}
            {{ link_to("fund_correction?page="~page.last, t._("Последняя"), 'class': 'btn btn-secondary') }}
            {% endif %}
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<!-- /авто -->
{% endif %}
