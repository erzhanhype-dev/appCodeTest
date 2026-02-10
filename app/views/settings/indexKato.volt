<!-- заголовок -->
<h2>{{ t._("Настройки") }}</h2>
<!-- /заголовок -->

<!-- общие настройки -->
<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("user-set") }}
  </div>
  <div class="card-body">
    <form action="/settings/profile" method="POST" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <label for="set_type">{{ t._("Используемая ЭЦП") }}</label>
        <input type="text" class="form-control" value="<?php $__s = $this->session->get('__settings'); if($user->user_type_id == 2) { echo __checkHOC($__s->eku) ? 'ПЕРВЫЙ РУКОВОДИТЕЛЬ' : 'СОТРУДНИК'; } else { echo 'ЛИЧНАЯ'; } ?>" disabled="disabled">
      </div>
      <div class="form-group">
        <label for="set_type">{{ t._("Тип пользователя") }}</label>
        {{ text_field("set_type", "value": user_type.name, "size": 50, "class" : "form-control", "id" : "set_type", "disabled" : "disabled") }}
      </div>
      <div class="form-group">
        <label for="set_lang">{{ t._("Язык интерфейса") }}</label>
        <select name="set_lang" class="form-control" id="set_lang">
          <option value="ru"{% if user.lang == 'ru' %}selected="selected"{% endif %}>{{t._("rus")}}</option>
          {#<option value="kk"{% if user.lang == 'kk' %}selected="selected"{% endif %}>{{ t._("kaz") }}</option>#}
        </select>
      </div>
      {{ submit_button(t._("Сохранить настройки"), 'class': 'btn btn-primary') }}
    </form>
  </div>
</div>
<!-- /общие настройки -->

<!-- детальная информация -->
<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Детальная информация") }}
  </div>
  <div class="card-body">
    <form action="/settings/details" method="POST" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      {% if user.user_type_id == constant("PERSON") %}
        <div class="form-group">
          <label for="det_last_name">{{ t._("Фамилия *") }}</label>
          {% if details.last_name != '' %}
            {{ text_field("det_last_name", "value": details.last_name, "maxlength": 50, "class" : "form-control", "id" : "det_last_name", "readonly" : "readonly") }}
          {% else %}
            {{ text_field("det_last_name", "value": details.last_name, "maxlength": 50, "class" : "form-control", "id" : "det_last_name") }}
          {% endif %}
        </div>
        <div class="form-group">
          <label for="det_first_name">{{ t._("Имя *") }}</label>
          {% if details.first_name != '' %}
            {{ text_field("det_first_name", "value": details.first_name, "maxlength": 50, "class" : "form-control", "id" : "det_first_name", "readonly" : "readonly") }}
          {% else %}
            {{ text_field("det_first_name", "value": details.first_name, "maxlength": 50, "class" : "form-control", "id" : "det_first_name") }}
          {% endif %}
        </div>
        <div class="form-group">
          <label for="det_parent_name">{{ t._("Отчество") }}</label>
          {{ text_field("det_parent_name", "value": details.parent_name, "maxlength": 50, "class" : "form-control", "id" : "det_parent_name", "readonly" : "readonly") }}
        </div>
        <div class="form-group">
          <label for="det_iin">{{ t._("ИИН *") }}</label>
          {% if details.iin != '' %}
            {{ text_field("det_iin", "value": details.iin, "maxlength": 12, "class" : "form-control", "id" : "det_iin", "readonly" : "readonly", "placeholder" : "XXXXXXXXXXXX") }}
          {% else %}
            {{ text_field("det_iin", "value": details.iin, "maxlength": 12, "class" : "form-control", "id" : "det_iin", "placeholder" : "XXXXXXXXXXXX") }}
          {% endif %}
        </div>
        <div class="form-group">
          <label for="det_birthdate">{{ t._("Дата рождения *") }}</label>
          <input type="text" name="det_birthdate" value="{{ date("d.m.Y", details.birthdate) }}" id="det_birthdate" data-provide="datepicker" data-date-end-date="-18y" class="form-control datepicker">
        </div>
      {% endif %}
      {% if user.user_type_id == constant("COMPANY") %}
        <div class="form-group">
          <label for="det_name">{{ t._("Наименование *") }}</label>
          {% if details.name != '' %}
            {{ text_field("det_name", "value": details.name, "maxlength": 250, "class" : "form-control", "id" : "det_name", "readonly" : "readonly", "placeholder" : "ТОО «АвтоМир»") }}
          {% else %}
            {{ text_field("det_name", "value": details.name, "maxlength": 250, "class" : "form-control", "id" : "det_name", "placeholder" : "ТОО «АвтоМир»") }}
          {% endif %}
        </div>
        <div class="form-group">
          <label for="det_bin">{{ t._("БИН *") }}</label>
          {% if details.bin != '' %}
            {{ text_field("det_bin", "value": details.bin, "maxlength": 12, "class" : "form-control", "id" : "det_bin", "readonly" : "readonly", "placeholder" : "XXXXXXXXXXXX") }}
          {% else %}
            {{ text_field("det_bin", "value": details.bin, "maxlength": 12, "class" : "form-control", "id" : "det_bin", "placeholder" : "XXXXXXXXXXXX") }}
          {% endif %}
        </div>
        <div class="form-group">
          <label for="det_reg_date">{{ t._("Дата регистрации *") }}</label>
          <input type="text" name="det_reg_date" value="{% if details.reg_date %}{{ date("d.m.Y", details.reg_date) }}{% endif %}" id="det_reg_date" data-provide="datepicker" data-date-end-date="0d" class="form-control datepicker">
        </div>
        <div class="form-group">
          <label for="det_iban">{{ t._("IBAN (номер счета)") }}</label>
          {{ text_field("det_iban", "value": details.iban, "maxlength": 20, "class" : "form-control", "id" : "det_iban", "placeholder" : "KZXXXXXXXXXXXXXXXXXX") }}
        </div>
        <div class="form-group">
          <label for="det_ref_bank">{{ t._("Банк") }}</label>
          {{ select_static("det_ref_bank", ref_bank, "using": ["id", "name"], "id": "det_ref_bank", "class": "form-control", "style" : "width: 100%;") }}
        </div>
        <div class="form-group">
          <label for="det_ref_kbe">{{ t._("КБЕ") }}</label>
          {{ select_static("det_ref_kbe", ref_kbe, "using": ["id", "name"], "id": "det_ref_kbe", "class": "form-control", "style" : "width: 100%;") }}
        </div>
        <div class="form-group">
          <label for="det_oked">{{ t._("ОКЭД") }}</label>
          {{ text_field("det_oked", "value": details.oked, "maxlength": 20, "class" : "form-control", "id" : "det_oked", "placeholder" : "ОКЭД") }}
        </div>
        <div class="form-group">
          <label for="det_reg_num">{{ t._("Свидетельство о регистрации") }}</label>
          {{ text_field("det_reg_num", "value": details.reg_num, "maxlength": 50, "class" : "form-control", "id" : "det_reg_num", "placeholder" : "№00000 от 01.12.2010") }}
        </div>
      {% endif %}
      {{ submit_button(t._("Сохранить детальную информацию"), 'class': 'btn btn-primary') }}
    </form>
  </div>
</div>
<!-- /детальная информация -->

<!-- файлы -->
{% if user.user_type_id == constant("COMPANY") %}
  <div class="card my-3">
    <div class="card-header bg-dark text-light">
      {{ t._("verif-documents") }}
    </div>
    <div class="card-body">
      <?php $path = APP_PATH."/private/user/".$this->session->get('auth')['id']."/docs/";
      $c = 0;
      $file = array();
      if(file_exists($path)) {
      $dir = scandir($path);
      $dir = array_diff($dir, array('.', '..'));
      foreach($dir as $k => $v) {
      $file[] = '<a href="/settings/getfile/'.$c.'">'.$t->_("downloaded").'</a>';
      $c++;
      }
      } ?>
      <form enctype="multipart/form-data" action="/settings/upload/company" method="POST">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

        <div class="form-group">
          <label for="files_doc">{{ t._("company-certificate") }} {% if file %}{{ file[0] }}{% endif %}*</label>
          <input type="file" name="files_doc" id="files_doc" class="form-control-file">
        </div>
        {{ submit_button(t._("load-document"), 'class': 'btn btn-primary') }}
      </form>
    </div>
  </div>
{% endif %}
<!-- /файлы -->

{#test#}

<!-- контакты -->
<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("contact-set") }}
  </div>
  <div class="card-body">
    <form action="/settings/contacts" method="POST" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <input value="{{ contacts.ref_kato_region }}" id="ref_kato_region" class="d-none">
      <input value="{{ contacts.ref_kato_city }}" id="ref_kato_city" class="d-none">
      <input value="{{ contacts.ref_kato_district }}" id="ref_kato_district" class="d-none">
      <input value="{{ contacts.kato_region }}" id="kato_region" class="d-none">
      <input value="{{ contacts.kato_city }}" id="kato_city" class="d-none">
      <input value="{{ contacts.kato_district }}" id="kato_district" class="d-none">

      <div class="form-group">
        <label for="con_ref_reg_country">{{ t._("Страна") }}</label>
        {{ select_static("con_ref_reg_country", ref_country, "using": ["id", "name"], "class": "form-control", "id": "con_ref_reg_country", "style" : "width: 100%;") }}
      </div>

      <div class="form-group">
        <label for="reg_city">{{ t._("Город") }}</label>
        {{ text_field("reg_city", "value": contacts.reg_city, "maxlength": 50, "class" : "form-control", "id" : "reg_city", "placeholder" : t._("example-city")) }}
      </div>
      <div class="form-group d-none">
        <label for="con_reg_city">{{ t._("Город") }}</label>
        {{ text_field("con_reg_city", "value": contacts.reg_city, "maxlength": 50, "class" : "form-control", "id" : "con_reg_city", "placeholder" : t._("example-city")) }}
      </div>

      <div class="form-group d-none">
        <label for="region">Регион</label>
        <select class="form-control" id="region" name="kato_region">
          <option value="">Выберите регион</option>
          <!-- Опции подгрузим изначально -->
        </select>
      </div>

      <div class="form-group d-none">
        <label for="city">Город</label>
        <select class="form-control" id="city" name="kato_city" disabled>
          <option value="">Сначала выберите регион</option>
        </select>
      </div>

      <div class="form-group d-none">
        <label for="district">Район / с.о.</label>
        <select class="form-control" id="district" name="kato_district" disabled>
          <option value="">Сначала выберите город</option>
        </select>
      </div>

      <div class="form-group">
        <label for="con_reg_address">{{ t._("Адрес регистрации *") }}</label>
        {{ text_field("con_reg_address", "value": contacts.reg_address, "maxlength": 50, "class" : "form-control", "id" : "con_reg_address", "placeholder" : t._("example-address")) }}
      </div>
      <div class="form-group">
        <label for="con_reg_zipcode">{{ t._("Индекс") }}</label>
        {{ text_field("con_reg_zipcode", "value": contacts.reg_zipcode, "maxlength": 6, "class" : "form-control", "id" : "con_reg_zipcode", "placeholder" : "XXXXXX") }}
      </div>
      <div class="form-group">
        <button type="button" name="eq_address" id="eq_address" class="btn btn-secondary">{{ t._("copy-address") }}</button>
      </div>
      <div class="form-group">
        <label for="con_ref_country">{{ t._("Страна") }}</label>
        {{ select_static("con_ref_country", ref_country, "using": ["id", "name"], "class": "form-control", "id": "con_ref_country", "style" : "width: 100%;") }}
      </div>
      <div class="form-group d-none">
        <label for="con_city">{{ t._("Город") }}</label>
        {{ text_field("con_city", "value": contacts.city, "maxlength": 50, "class" : "form-control", "id" : "con_city", "placeholder" : t._("example-city")) }}
      </div>

      <div class="form-group d-none">
        <label for="fact_region">Регион</label>
        <select class="form-control" id="fact_region" name="fact_kato_region">
          <option value="">Выберите регион</option>
          <!-- Опции подгрузим изначально -->
        </select>
      </div>

      <div class="form-group d-none">
        <label for="fact_city">Город</label>
        <select class="form-control" id="fact_city" name="fact_kato_city" disabled>
          <option value="">Сначала выберите регион</option>
        </select>
      </div>

      <div class="form-group d-none">
        <label for="fact_district">Район / с.о.</label>
        <select class="form-control" id="fact_district" name="fact_kato_district" disabled>
          <option value="">Сначала выберите город</option>
        </select>
      </div>

      <div class="form-group">
        <label for="con_address">{{ t._("Фактический адрес") }}</label>
        {{ text_field("con_address", "value": contacts.address, "maxlength": 50, "class" : "form-control", "id" : "con_address", "placeholder" : t._("example-address")) }}
      </div>
      <div class="form-group">
        <label for="con_zipcode">{{ t._("Индекс") }}</label>
        {{ text_field("con_zipcode", "value": contacts.zipcode, "maxlength": 6, "class" : "form-control", "id" : "con_zipcode", "placeholder" : "XXXXXX") }}
      </div>
      <div class="form-group">
        <label for="con_phone">{{ t._("Контактный телефон") }}</label>
        {{ text_field("con_phone", "value": contacts.phone, "class" : "form-control", "id" : "con_phone", "placeholder" : "+7 (111) 111-11-11") }}
      </div>
      <div class="form-group">
        <label for="con_mobile_phone">{{ t._("Мобильный телефон") }}</label>
        {{ text_field("con_mobile_phone", "value": contacts.mobile_phone, "class" : "form-control", "id" : "con_mobile_phone", "placeholder" : "+7 (111) 111-11-11") }}
      </div>
      {{ submit_button(t._("Сохранить контакты"), 'class': 'btn btn-primary') }}
    </form>
  </div>
</div>
<!-- /контакты -->

<?php if($user->user_type_id == 2 && __checkHOC($__s->eku)): ?>
<!-- *** -->
<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Назначение подписи") }}
  </div>
  <div class="card-body">
    <form action="/settings/accountant" method="POST" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <label for="iin_buh">{{ t._("ИНН бухгалтера") }}</label>
        {{ text_field("iin_buh", "value": iin_buh, "class" : "form-control", "id" : "iin_buh", "placeholder" : "123456789012") }}
      </div>
      {{ submit_button(t._("Сохранить ИИН"), 'class': 'btn btn-primary') }}
    </form>
  </div>
</div>
<!-- /*** -->
<?php endif; ?>

<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("История сеансов") }}
  </div>
  <div class="card-body">
    {% for attempt in lastAttempts %}
      <div class="mb-2">
        <i data-feather="smartphone" style="opacity:.6;width:18px"></i> {{ attempt.device_info ? attempt.device_info : 'Неизвестное устройство' }}<br>
        <i data-feather="clock" style="opacity:.6;width:18px"></i> {{ attempt.login_time }}<br>
        <i data-feather="map-pin" style="opacity:.6;width:18px"></i> {{ attempt.geolocation_info ? attempt.geolocation_info : attempt.ip }}<br>
        <i data-feather="lock" style="opacity:.6;width:18px;color:{{ attempt.status == 'success' ? 'green' : 'red' }}"></i> {{ attempt.status == 'success' ? 'Успешно' : 'Неудачно' }}
      </div>
    {% endfor %}
  </div>
</div>

<div class="card my-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Смена пароля") }}
  </div>
  <div class="card-body">
    <form action="/settings/changePassword" method="POST" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <div class="controls">
          <input name="restore_pass" id="reg_pass" type="password" class="form-control" placeholder="{{t._("password-text")}}">
        </div>
      </div>
      <div class="form-group">
        <div class="controls">
          <input name="restore_pass_again" id="reg_pass_again" type="password" class="form-control" placeholder="{{t._("confirm-the-password")}}">
        </div>
      </div>
      <div class="form-group">
        {{ submit_button(t._("save"), 'class': 'btn btn-primary') }}
      </div>
    </form>
  </div>
</div>
