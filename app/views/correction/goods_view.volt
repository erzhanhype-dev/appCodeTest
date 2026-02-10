<h3>{{ t._("Аннулирование товара") }}</h3>
<form id="annulGoodForm" action="/correction/annul_goods/{{ pid  }}" method="POST" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("Аннулирование") }} (заявка #{{ pid }})</div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">{{ t._("Список товаров") }}</label>
            <ol>
            {% for g in goods %}
            <?php if($g->status == "DELETED") continue; ?>
              <li> ID: <b>{{ g.id }}</b> || Дата импорта: <b><?php echo date("d-m-Y", $g->date_import ); ?> </b> || Вес: <b>{{ g.weight }} кг</b> || Размер платежа: <b>{{ g.amount }} тг</b> || Счет фактура или ГТД: <b>{{ g.basis }}</b></li>
            {% endfor %}
          </ol>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("comment") }}</label>
            <textarea name="good_comment" id="annulGoodComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
          </div>
          <div class="form-group">    
            <label class="form-label">{{ t._("Загрузить файл") }}</label>
            <input type="file" id="annulGoodFile" name="good_file" class="form-control-file" required>
          </div>
          <hr>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ pid }}">
           <input type="hidden" value="{{ sign_data }}" name="hash" id="annulGoodHash">
            <textarea type="hidden" name="sign" id="annulGoodSign" style="display: none;"></textarea>
           <div class="row">
            <div class="col-auto">
              <button type="button" class="btn btn-warning signAnnulGoodsBtn">Подписать и аннулировать</button>
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
