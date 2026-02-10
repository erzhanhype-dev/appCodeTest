<h3>{{ t._("Аннулирование КПП") }}</h3>
<form id="annulKPPForm" action="/correction/annul_kpps/{{ pid  }}" method="POST" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("Аннулирование") }} (заявка #{{ pid }})</div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">{{ t._("Список товаров КПП") }}</label>
            <ol>
            {% for g in kpps %}
            <?php if($g->status == "DELETED") continue; ?>
              <li> 
                ID: <b>{{ g.id }}</b> || Сумма в инвойсе: <b>{{ g.invoice_sum }} тг</b> || 
                Сумма в инвойсе (валюте) Тип валюты: <b>{{ g.invoice_sum_currency }} ({{ g.currency_type}})</b>  || 
                Дата импорта: <b><?php echo date("d-m-Y", $g->date_import ); ?> </b> || 
                Масса КПП: <b>{{ g.weight }} тонна</b> || Размер платежа: <b>{{ g.amount }} тг</b> || 
                Счет фактура или ГТД: <b>{{ g.basis }}</b> || Вес упаковки: <b>{{ g.package_weight }}</b> || 
                Утилизационный платеж за упаковку: <b>{{ g.package_cost }} тг</b>;
              </li>
            {% endfor %}
          </ol>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("comment") }}</label>
            <textarea name="kpp_comment" id="annulKPPComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
          </div>
          <div class="form-group">    
            <label class="form-label">{{ t._("Загрузить файл") }}</label>
            <input type="file" id="annulKPPFile" name="kpp_file" class="form-control-file" required>
          </div>
          <hr>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ pid }}">
           <input type="hidden" value="{{ sign_data }}" name="hash" id="annulKPPHash">
            <textarea type="hidden" name="sign" id="annulKPPSign" style="display: none;"></textarea>
           <div class="row">
            <div class="col-auto">
              <button type="button" class="btn btn-warning signAnnulKPPsBtn">Подписать и аннулировать</button>
              <a href="/correction/" class="btn btn-danger">Отмена</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
{% if logs|length > 0 %}
 <div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("История изменения") }}</div>
        <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{t._("Обьект ID")}}</th>
              <th>{{t._("Пользователь")}}</th>
              <th>{{t._("Действия")}}</th>
              <th>{{t._("Время")}}</th>
              <th>{{t._("До")}}</th>
              <th>{{t._("После")}}</th>
              <th>{{t._("comment")}}</th>
              <th>{{t._("Файл")}}</th>
            </tr>
          </thead>
          <tbody>           
               {{logs}}         
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
{% endif %}
