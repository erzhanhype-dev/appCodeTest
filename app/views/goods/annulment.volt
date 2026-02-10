<h3>{{ t._("Аннулирование товара") }}</h3>
<form id="formCorrectionRequestSign" action="/goods/annulment/{{ pid  }}" method="POST" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("Аннулирование") }} (заявка #{{ pid }})</div>
        <div class="card-body">
        <div class="d-grid gap-2 d-md-block">
            <a class="btn btn-primary" href="/order/view/{{ pid }}">Редактирование</a>
            <a class="btn btn-danger" href="/goods/annulment/{{ pid }}">Аннулирование</a>
          </div>
          <hr>

        <h2 class="h4 mb-3">Аннулирование</h2>
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
            <textarea name="good_comment" id="correctionRequestComment" class="form-control" placeholder="Ваш комментарий ... " required></textarea>
          </div>
          <div class="form-group">    
            <label class="form-label">{{ t._("Загрузить файл") }}</label>
            <input type="file" name="good_file" class="form-control-file" required>
          </div>
          <hr>
          <!-- конец товара в упаковке -->
          <input type="hidden" name="profile" value="{{ pid }}">
           <input type="hidden" value="{{ sign_data }}" name="hash" id="profileHash">
            <textarea type="hidden" name="sign" id="profileSign" style="display: none;"></textarea>
           <div class="row">
            <div class="col-2">
              <select id="storageSelect" class="form-control">
                <option value="PKCS12" selected>Файл</option>
              </select>
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-warning signCorrectionRequestBtn">Подписать и аннулировать</button>
              <a href="/order/view/{{ pid }}" class="btn btn-danger">Отмена</a>
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
