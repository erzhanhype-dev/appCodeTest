<!-- заголовок -->
<h2>{{ t._('Настройки') }}</h2>

<!-- общие настройки -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">{{ t._('user-set') }}</div>
    <div class="card-body">
        <form action="/settings/profile" method="POST" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="set_eku">{{ t._('Используемая ЭЦП') }}</label>
                <input type="text" id="set_eku" class="form-control" value="{{ ekuLabel }}" disabled>
            </div>

            <div class="form-group">
                <label for="set_type">{{ t._('Тип пользователя') }}</label>
                <input type="text" name="set_type" id="set_type" class="form-control" value="{{ userType.name }}" disabled>
            </div>

            <div class="form-group">
                <label for="set_lang">{{ t._('Язык интерфейса') }}</label>
                <select name="set_lang" class="form-control" id="set_lang">
                    <option value="ru" {% if user.lang == 'ru' %}selected{% endif %}>{{ t._('rus') }}</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">{{ t._('Сохранить настройки') }}</button>
        </form>
    </div>
</div>

<!-- детальная информация -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">{{ t._('Детальная информация') }}</div>
    <div class="card-body">
        <form action="/settings/details" method="POST" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            {% if userType.id === 1 %}
                <div class="form-group">
                    <label for="det_last_name">{{ t._('Фамилия *') }}</label>
                    <input type="text" name="det_last_name" id="det_last_name" class="form-control" maxlength="50"
                           value="{{ details.last_name }}" {% if details and details.last_name != '' %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_first_name">{{ t._('Имя *') }}</label>
                    <input type="text" name="det_first_name" id="det_first_name" class="form-control" maxlength="50"
                           value="{{ details.first_name }}" {% if details and details.first_name != '' %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_parent_name">{{ t._('Отчество') }}</label>
                    <input type="text" name="det_parent_name" id="det_parent_name" class="form-control" maxlength="50"
                           value="{{ details.parent_name }}" {% if details %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_iin">{{ t._('ИИН *') }}</label>
                    <input type="text" name="det_iin" id="det_iin" class="form-control" maxlength="12" placeholder="XXXXXXXXXXXX"
                           value="{{ details.iin }}" {% if details and details.iin != '' %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_birthdate">{{ t._('Дата рождения *') }}</label>
                    <input type="text" name="det_birthdate" id="det_birthdate" class="form-control datepicker"
                           data-provide="datepicker" data-date-end-date="-18y" value="{{ details.birthdate }}">
                </div>
            {% endif %}

            {% if userType.id === 2 %}
                <div class="form-group">
                    <label for="det_name">{{ t._('Наименование *') }}</label>
                    <input type="text" name="det_name" id="det_name" class="form-control" maxlength="250" placeholder="ТОО «АвтоМир»"
                           value="{{ details.name|htmlspecialchars }}" {% if details and details.name != '' %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_bin">{{ t._('БИН *') }}</label>
                    <input type="text" name="det_bin" id="det_bin" class="form-control" maxlength="12" placeholder="XXXXXXXXXXXX"
                           value="{{ details.bin }}" {% if details and details.bin != '' %}readonly{% endif %}>
                </div>
                <div class="form-group">
                    <label for="det_reg_date">{{ t._('Дата регистрации *') }}</label>
                    <input type="text" name="det_reg_date" id="det_reg_date" class="form-control datepicker"
                           data-provide="datepicker" data-date-end-date="0d" value="{{ details.reg_date }}">
                </div>
                <div class="form-group">
                    <label for="det_iban">{{ t._('IBAN (номер счета)') }}</label>
                    <input type="text" name="det_iban" id="det_iban" class="form-control" maxlength="20"
                           placeholder="KZXXXXXXXXXXXXXXXXXX" value="{{ details.iban }}">
                </div>
                <div class="form-group">
                    <label for="det_ref_bank">{{ t._('Банк') }}</label>
                    <select name="det_ref_bank" id="det_ref_bank" class="form-control" style="width:100%;">
                        {% for bank in refBank %}
                            <option value="{{ bank.id }}" {% if details and details.ref_bank_id == bank.id %}selected{% endif %}>{{ bank.name }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label for="det_ref_kbe">{{ t._('КБЕ') }}</label>
                    <select name="det_ref_kbe" id="det_ref_kbe" class="form-control" style="width:100%;">
                        {% for kbe in refKbe %}
                            <option value="{{ kbe.id }}" {% if details and details.ref_kbe_id == kbe.id %}selected{% endif %}>{{ kbe.name }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label for="det_oked">{{ t._('ОКЭД') }}</label>
                    <input type="text" name="det_oked" id="det_oked" class="form-control" maxlength="20" placeholder="ОКЭД"
                           value="{{ details.oked }}">
                </div>
                <div class="form-group">
                    <label for="det_reg_num">{{ t._('Свидетельство о регистрации') }}</label>
                    <input type="text" name="det_reg_num" id="det_reg_num" class="form-control" maxlength="50"
                           placeholder="№00000 от 01.12.2010" value="{{ details.reg_num }}">
                </div>
            {% endif %}

            <button type="submit" class="btn btn-primary">{{ t._('Сохранить детальную информацию') }}</button>
        </form>
    </div>
</div>

<!-- контакты -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">{{ t._('contact-set') }}</div>
    <div class="card-body">
        <form action="/settings/contacts" method="POST" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="con_ref_reg_country">{{ t._('Страна') }}</label>
                <select name="con_ref_reg_country" id="con_ref_reg_country" class="form-control" style="width:100%;">
                    {% for country in refCountry %}
                        <option value="{{ country.id }}" {% if contacts and contacts.ref_reg_country_id == country.id %}selected{% endif %}>{{ country.name }}</option>
                    {% endfor %}
                </select>
            </div>

            <div class="form-group">
                <label for="con_reg_city">{{ t._('Город') }}</label>
                <input type="text" name="con_reg_city" id="con_reg_city" class="form-control" maxlength="50"
                       value="{{ contacts ? contacts.reg_city : '' }}" placeholder="{{ t._('example-city') }}">
            </div>

            <div class="form-group">
                <label for="con_reg_address">{{ t._('Адрес регистрации *') }}</label>
                <input type="text" name="con_reg_address" id="con_reg_address" class="form-control" maxlength="50"
                       value="{{ contacts ? contacts.reg_address : '' }}" placeholder="{{ t._('example-address') }}">
            </div>

            <div class="form-group">
                <label for="con_reg_zipcode">{{ t._('Индекс') }}</label>
                <input type="text" name="con_reg_zipcode" id="con_reg_zipcode" class="form-control" maxlength="6"
                       value="{{ contacts ? contacts.reg_zipcode : '' }}" placeholder="XXXXXX">
            </div>

            <div class="form-group">
                <button type="button" id="eq_address" class="btn btn-secondary">{{ t._('copy-address') }}</button>
            </div>

            <div class="form-group">
                <label for="con_ref_country">{{ t._('Страна') }}</label>
                <select name="con_ref_country" id="con_ref_country" class="form-control" style="width:100%;">
                    {% for country in refCountry %}
                        <option value="{{ country.id }}" {% if contacts and contacts.ref_country_id == country.id %}selected{% endif %}>{{ country.name }}</option>
                    {% endfor %}
                </select>
            </div>

            <div class="form-group">
                <label for="con_city">{{ t._('Город') }}</label>
                <input type="text" name="con_city" id="con_city" class="form-control" maxlength="50"
                       value="{{ contacts ? contacts.city : '' }}" placeholder="{{ t._('example-city') }}">
            </div>

            <div class="form-group">
                <label for="con_address">{{ t._('Фактический адрес') }}</label>
                <input type="text" name="con_address" id="con_address" class="form-control" maxlength="50"
                       value="{{ contacts ? contacts.address : '' }}" placeholder="{{ t._('example-address') }}">
            </div>

            <div class="form-group">
                <label for="con_zipcode">{{ t._('Индекс') }}</label>
                <input type="text" name="con_zipcode" id="con_zipcode" class="form-control" maxlength="6"
                       value="{{ contacts ? contacts.zipcode : '' }}" placeholder="XXXXXX">
            </div>

            <div class="form-group">
                <label for="con_phone">{{ t._('Контактный телефон') }}</label>
                <input type="text" name="con_phone" id="con_phone" class="form-control"
                       value="{{ contacts ? contacts.phone : '' }}" placeholder="+7 (111) 111-11-11">
            </div>

            <div class="form-group">
                <label for="con_mobile_phone">{{ t._('Мобильный телефон') }}</label>
                <input type="text" name="con_mobile_phone" id="con_mobile_phone" class="form-control"
                       value="{{ contacts ? contacts.mobile_phone : '' }}" placeholder="+7 (111) 111-11-11">
            </div>

            <button type="submit" class="btn btn-primary">{{ t._('Сохранить контакты') }}</button>
        </form>
    </div>
</div>

<!-- назначение подписи -->
{% if isAccountant %}
    <div class="card my-3">
        <div class="card-header bg-dark text-light">{{ t._('Назначение подписи') }}</div>
        <div class="card-body">
            <form action="/settings/accountant" method="POST" autocomplete="off">
                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                <div class="form-group">
                    <label for="iin_buh">{{ t._('ИНН бухгалтера') }}</label>
                    <input type="text" name="iin_buh" id="iin_buh" class="form-control" placeholder="123456789012" value="{{ user.accountant }}">
                </div>
                <button type="submit" class="btn btn-primary">{{ t._('Сохранить ИИН') }}</button>
            </form>
        </div>
    </div>
{% endif %}

<!-- история входов -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">{{ t._('История сеансов') }}</div>
    <div class="card-body">
        {% for attempt in lastAttempts %}
            <div class="mb-2">
                <i data-feather="smartphone" style="opacity:.6;width:18px"></i>
                {{ attempt.device_info|default('Неизвестное устройство') }}<br>

                <i data-feather="clock" style="opacity:.6;width:18px"></i>
                {{ attempt.login_time }}<br>

                <i data-feather="map-pin" style="opacity:.6;width:18px"></i>
                {{ attempt.geolocation_info|default(attempt.ip) }}<br>

                <i data-feather="lock"
                   style="opacity:.6;width:18px;color:{{ attempt.status == 'success' ? 'green' : 'red' }}">
                </i>
                {{ attempt.status == 'success' ? 'Успешно' : 'Неудачно' }}
            </div>
        {% endfor %}
    </div>
</div>

<!-- смена пароля -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">{{ t._('Смена пароля') }}</div>
    <div class="card-body">
        <form action="/settings/changePassword" method="POST" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <input name="restore_pass" id="reg_pass" type="password" class="form-control" placeholder="{{ t._('password-text') }}">
            </div>
            <div class="form-group">
                <input name="restore_pass_again" id="reg_pass_again" type="password" class="form-control" placeholder="{{ t._('confirm-the-password') }}">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">{{ t._('save') }}</button>
            </div>
        </form>
    </div>
</div>
