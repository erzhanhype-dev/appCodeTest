<!-- заголовок -->
<h2>{{ t._("Настройки") }}</h2>
<!-- /заголовок -->
{% set user_id = '' %}
{% set user_idnum = '' %}
{% set user_title = '' %}

<!-- общие настройки -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">
        {{ t._("user-set") }}
    </div>
    <div class="card-body">
        <form action="/create_order/user_profile" method="POST" autocomplete="off">
            <!-- Используем токен для профиля -->
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <input type="hidden" name="user_id" value="{{ user.id }}"/>
            <div class="form-group">
                <label for="set_type">{{ t._("Используемая ЭЦП") }}</label>
                {% set __s = session.get('__settings') %}
                <input type="text" class="form-control"
                       value="{% if user.user_type_id == 2 %}{% if __s and __s['eku'] and __checkHOC(__s['eku']) %}ПЕРВЫЙ РУКОВОДИТЕЛЬ{% else %}СОТРУДНИК{% endif %}{% else %}ЛИЧНАЯ{% endif %}"
                       disabled="disabled">
            </div>
            <div class="form-group">
                <label for="set_type">{{ t._("Тип пользователя") }}</label>
                <input type="text" class="form-control" id="set_type" value="{{ user_type.name }}" disabled="disabled">
            </div>
            <div class="form-group">
                <label for="set_lang">{{ t._("Язык интерфейса") }}</label>
                <select name="set_lang" class="form-control" id="set_lang">
                    <option value="ru"{% if user.lang == 'ru' %} selected="selected"{% endif %}>{{ t._("rus") }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ t._("Сохранить настройки") }}</button>
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
        <form action="/create_order/user_details" method="POST" autocomplete="off">
            <!-- Используем токен для деталей -->
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <input type="hidden" name="uid" value="{{ user.id }}"/>
            {% if user.user_type_id == constant("PERSON") %}
                {% set user_id = user.id %}
                {% set user_idnum = user.idnum %}
                {% set user_title = details.last_name ~ " " ~ details.first_name ~ " " ~ details.parent_name %}

                <div class="form-group">
                    <label for="det_last_name" class="text text-danger">{{ t._("Фамилия *") }}</label>
                    {% if details.last_name != '' %}
                        <input type="text" name="det_last_name" id="det_last_name" class="form-control"
                               value="{{ details.last_name }}" maxlength="50" placeholder="Каиркенов">
                    {% else %}
                        <input type="text" name="det_last_name" id="det_last_name" class="form-control"
                               value="{{ details.last_name }}" maxlength="50" placeholder="Каиркенов">
                    {% endif %}
                </div>
                <div class="form-group">
                    <label for="det_first_name" class="text text-danger">{{ t._("Имя *") }}</label>
                    {% if details.first_name != '' %}
                        <input type="text" name="det_first_name" id="det_first_name" class="form-control"
                               value="{{ details.first_name }}" maxlength="50" placeholder="Канат">
                    {% else %}
                        <input type="text" name="det_first_name" id="det_first_name" class="form-control"
                               value="{{ details.first_name }}" maxlength="50" placeholder="Канат">
                    {% endif %}
                </div>
                <div class="form-group">
                    <label for="det_parent_name">{{ t._("Отчество") }}</label>
                    {% if details.parent_name != '' %}
                        <input type="text" name="det_parent_name" id="det_parent_name" class="form-control"
                               value="{{ details.parent_name }}" maxlength="50" placeholder="Мажитович">
                    {% else %}
                        <input type="text" name="det_parent_name" id="det_parent_name" class="form-control"
                               value="{{ details.parent_name }}" maxlength="50" placeholder="Мажитович">
                    {% endif %}
                </div>
                <div class="form-group">
                    <label for="det_iin" class="text text-danger">{{ t._("ИИН *") }}</label>
                    {% if details.iin != '' %}
                        <input type="text" name="det_iin" id="det_iin" class="form-control" value="{{ details.iin }}"
                               maxlength="12" readonly="readonly" placeholder="XXXXXXXXXXXX">
                    {% else %}
                        <input type="text" name="det_iin" id="det_iin" class="form-control" value="{{ details.iin }}"
                               maxlength="12" placeholder="XXXXXXXXXXXX">
                    {% endif %}
                </div>
                <div class="form-group">
                    <label for="det_birthdate">{{ t._("Дата рождения *") }}</label>
                    <input type="text" name="det_birthdate" value="{{ date('d.m.Y', details.birthdate) }}"
                           id="det_birthdate" data-provide="datepicker" data-date-end-date="-18y"
                           class="form-control datepicker">
                </div>
            {% endif %}

            {% if user.user_type_id == constant("COMPANY") %}
                {% set user_id = user.id %}
                {% set user_idnum = user.idnum %}
                {% set user_title = details.name %}

                <div class="form-group">
                    <label for="det_name" class="text text-danger">{{ t._("Наименование *") }}</label>
                    <input type="text" name="det_name" class="form-control"
                           value="<?= htmlspecialchars($details->name ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                           placeholder="ТОО «АвтоМир»">
                </div>
                <div class="form-group">
                    <label for="det_bin" class="text text-danger">{{ t._("БИН *") }}</label>
                    {% if details.bin != '' %}
                        <input type="text" name="det_bin" id="det_bin" class="form-control" value="{{ details.bin }}"
                               maxlength="12" readonly="readonly" placeholder="XXXXXXXXXXXX">
                    {% else %}
                        <input type="text" name="det_bin" id="det_bin" class="form-control" value="{{ details.bin }}"
                               maxlength="12" placeholder="XXXXXXXXXXXX">
                    {% endif %}
                </div>
                <div class="form-group">
                    <label for="det_reg_date">{{ t._("Дата регистрации *") }}</label>
                    <input type="text" name="det_reg_date"
                           value="{% if details.reg_date %}{{ date('d.m.Y', details.reg_date) }}{% endif %}"
                           id="det_reg_date" data-provide="datepicker" data-date-end-date="0d"
                           class="form-control datepicker">
                </div>
                <div class="form-group">
                    <label for="det_iban">{{ t._("IBAN (номер счета)") }}</label>
                    <input type="text" name="det_iban" id="det_iban" class="form-control" value="{{ details.iban }}"
                           maxlength="20" placeholder="KZXXXXXXXXXXXXXXXXXX">
                </div>
                <div class="form-group">
                    <label for="det_ref_bank">{{ t._("Банк") }}</label>
                    <select name="det_ref_bank" id="det_ref_bank" class="form-control" style="width: 100%;">
                        <option value="">{{ t._("Выберите банк") }}</option>
                        {% for bank in ref_bank %}
                            <option value="{{ bank.id }}"
                                    {% if details.ref_bank_id == bank.id %}selected="selected"{% endif %}>
                                {{ bank.name }}
                            </option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label for="det_ref_kbe">{{ t._("КБЕ") }}</label>
                    <select name="det_ref_kbe" id="det_ref_kbe" class="form-control" style="width: 100%;">
                        <option value="">{{ t._("Выберите КБЕ") }}</option>
                        {% for kbe in ref_kbe %}
                            <option value="{{ kbe.id }}"
                                    {% if details.ref_kbe_id == kbe.id %}selected="selected"{% endif %}>
                                {{ kbe.name }}
                            </option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label for="det_oked">{{ t._("ОКЭД") }}</label>
                    <input type="text" name="det_oked" id="det_oked" class="form-control" value="{{ details.oked }}"
                           maxlength="20" placeholder="ОКЭД">
                </div>
                <div class="form-group">
                    <label for="det_reg_num">{{ t._("Свидетельство о регистрации") }}</label>
                    <input type="text" name="det_reg_num" id="det_reg_num" class="form-control"
                           value="{{ details.reg_num }}" maxlength="50" placeholder="№00000 от 01.12.2010">
                </div>
            {% endif %}
            <button type="submit" class="btn btn-primary">{{ t._("Сохранить детальную информацию") }}</button>
        </form>
    </div>
</div>
<!-- /детальная информация -->

<!-- контакты -->
<div class="card my-3">
    <div class="card-header bg-dark text-light">
        {{ t._("contact-set") }}
    </div>
    <div class="card-body">
        <form action="/create_order/user_contacts" method="POST">
            <!-- Используем токен для контактов -->
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <input type="hidden" name="user_id" value="{{ user.id }}"/>
            <div class="form-group">
                <label for="con_ref_reg_country">{{ t._("Страна") }}</label>
                <select name="con_ref_reg_country" class="form-control" id="con_ref_reg_country" style="width: 100%;">
                    <option value="">{{ t._("Выберите страну") }}</option>
                    {% for country in ref_country %}
                        <option value="{{ country.id }}"
                                {% if con_ref_reg_country == country.id %}selected="selected"{% endif %}>
                            {{ country.name }}
                        </option>
                    {% endfor %}
                </select>
            </div>
            <div class="form-group">
                <label for="con_reg_city">{{ t._("Город *") }}</label>
                <input type="text" name="con_reg_city" id="con_reg_city" class="form-control"
                       value="{{ contacts.reg_city }}" maxlength="50" placeholder="{{ t._('example-city') }}">
            </div>
            <div class="form-group">
                <label for="con_reg_address">{{ t._("Адрес регистрации *") }}</label>
                <input type="text" name="con_reg_address" id="con_reg_address" class="form-control"
                       value="{{ contacts.reg_address }}" maxlength="50" placeholder="{{ t._('example-address') }}">
            </div>
            <div class="form-group">
                <label for="con_reg_zipcode">{{ t._("Индекс") }}</label>
                <input type="text" name="con_reg_zipcode" id="con_reg_zipcode" class="form-control"
                       value="{{ contacts.reg_zipcode }}" maxlength="6" placeholder="XXXXXX">
            </div>
            <div class="form-group">
                <button type="button" name="eq_address" id="eq_address"
                        class="btn btn-secondary">{{ t._("copy-address") }}</button>
            </div>
            <div class="form-group">
                <label for="con_ref_country">{{ t._("Страна") }}</label>
                <select name="con_ref_country" class="form-control" id="con_ref_country" style="width: 100%;">
                    <option value="">{{ t._("Выберите страну") }}</option>
                    {% for country in ref_country %}
                        <option value="{{ country.id }}"
                                {% if con_ref_country == country.id %}selected="selected"{% endif %}>
                            {{ country.name }}
                        </option>
                    {% endfor %}
                </select>
            </div>
            <div class="form-group">
                <label for="con_city">{{ t._("Город") }}</label>
                <input type="text" name="con_city" id="con_city" class="form-control" value="{{ contacts.city }}"
                       maxlength="50" placeholder="{{ t._('example-city') }}">
            </div>
            <div class="form-group">
                <label for="con_address">{{ t._("Фактический адрес") }}</label>
                <input type="text" name="con_address" id="con_address" class="form-control"
                       value="{{ contacts.address }}" maxlength="50" placeholder="{{ t._('example-address') }}">
            </div>
            <div class="form-group">
                <label for="con_zipcode">{{ t._("Индекс") }}</label>
                <input type="text" name="con_zipcode" id="con_zipcode" class="form-control"
                       value="{{ contacts.zipcode }}" maxlength="6" placeholder="XXXXXX">
            </div>
            <div class="form-group">
                <label for="con_phone">{{ t._("Контактный телефон *") }}</label>
                <input type="text" name="con_phone" id="con_phone" class="form-control" value="{{ contacts.phone }}"
                       placeholder="+7 (717) 233-69-69">
            </div>
            <div class="form-group">
                <label for="con_mobile_phone">{{ t._("Мобильный телефон") }}</label>
                <input type="text" name="con_mobile_phone" id="con_mobile_phone" class="form-control"
                       value="{{ contacts.mobile_phone }}" placeholder="+7 (701) 333-69-69">
            </div>
            <button type="submit" class="btn btn-primary">{{ t._("Сохранить контакты") }}</button>

            {% if is_company %}
                {% if details.bin != '' and details.name != '' %}
                    <a class="btn btn-success"
                       href="/create_order/new/?idnum={{ user.idnum }}&user_id={{ user.id }}&title={{ details.name|url_encode }}">{{ t._("Перейти на страницу создание заявки") }}</a>
                {% endif %}
            {% elseif is_person %}
                {% if details.iin != '' and details.first_name != '' and details.last_name != '' %}
                    {% set full_name = details.last_name ~ ' ' ~ details.first_name ~ ' ' ~ details.parent_name %}
                    <a class="btn btn-success"
                       href="/create_order/new/?idnum={{ user.idnum }}&user_id={{ user.id }}&title={{ full_name|url_encode }}">{{ t._("Перейти на страницу создание заявки") }}</a>
                {% endif %}
            {% endif %}
        </form>
    </div>
</div>
<!-- /контакты -->

{% if user.user_type_id == 2 %}
    {% set __s = session.get('__settings') %}
    {% if __s and __s['eku'] and __checkHOC(__s['eku']) %}
        <!-- *** -->
        <div class="card my-3">
            <div class="card-header bg-dark text-light">
                {{ t._("Назначение подписи") }}
            </div>
            <div class="card-body">
                <form action="/create_order/user_accountant" method="POST">
                    <!-- Используем токен для бухгалтера -->
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                    <input type="hidden" name="user_id" value="{{ user.id }}"/>
                    <div class="form-group">
                        <label for="iin_buh">{{ t._("ИНН бухгалтера") }}</label>
                        <input type="text" name="iin_buh" id="iin_buh" class="form-control" value="{{ iin_buh }}"
                               placeholder="123456789012">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ t._("Сохранить ИИН") }}</button>
                </form>
            </div>
        </div>
        <!-- /*** -->
    {% endif %}
{% endif %}