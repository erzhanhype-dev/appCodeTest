<h2>Редактирование пользователя</h2>

<div class="card mt-3 mb-5">
    <div class="card-header bg-dark text-light">
        Редактирование пользователя
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <form action="/users/save" method="POST" autocomplete="off" class="form-horizontal">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="hidden" name="id" value="{{ current_user['id'] }}">

                    <div class="form-group">
                        <label for="fieldLogin">Логин старого образца</label>
                        <input type="text" name="login" id="fieldLogin" class="form-control"
                               value="{{ current_user['login'] }}">
                    </div>

                    <div class="form-group">
                        <label for="fieldIdnum">ИИН / БИН</label>
                        <input type="text" name="idnum" id="fieldIdnum" class="form-control"
                               value="{{ current_user['idnum'] }}">
                    </div>

                    <div class="form-group">
                        <label for="fieldPassword">Пароль</label>
                        <input type="text" name="password" id="fieldPassword" class="form-control"
                               placeholder="Введите новый пароль">
                    </div>

                    <div class="form-group">
                        <label for="fieldEmail">Email</label>
                        <input type="email" name="email" id="fieldEmail" class="form-control"
                               value="{{ current_user['email'] }}">
                    </div>

                    <div class="form-group">
                        <label for="fieldActive">Активен</label>
                        <select name="active" id="fieldActive" class="form-control">
                            <option value="1" {% if current_user['active'] == 1 %}selected{% endif %}>Да</option>
                            <option value="0" {% if current_user['active'] == 0 %}selected{% endif %}>Нет</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldIsEmployee">Сотрудник компании</label>
                        <select name="is_employee" id="fieldIsEmployee" class="form-control" disabled>
                            <option value="1" {% if current_user['is_employee'] == 1 %}selected{% endif %}>Да</option>
                            <option value="0" {% if current_user['is_employee'] == 0 %}selected{% endif %}>Нет</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldRoleId">Роль</label>
                        <select name="role_id" id="fieldRoleId" class="form-control">
                            {% for role in roles %}
                                <option value="{{ role.id }}"
                                        {% if current_user['role_id'] == role.id %}selected{% endif %}>{{ role.description }}</option>
                            {% endfor %}
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldFundStage">Финансирование (этап)</label>
                        <select name="fund_stage" id="fieldFundStage" class="form-control">
                            <option value="STAGE_NOT_SET"
                                    {% if current_user['fund_stage'] == "STAGE_NOT_SET" %}selected{% endif %}>Не
                                участвует в согласовании
                            </option>
                            <option value="HOD" {% if current_user['fund_stage'] == "HOD" %}selected{% endif %}>
                                Согласует от руководителя ДРПУП
                            </option>
                            <option value="FAD" {% if current_user['fund_stage'] == "FAD" %}selected{% endif %}>
                                Согласует от ДБП
                            </option>
                            <option value="HOP" {% if current_user['fund_stage'] == "HOP" %}selected{% endif %}>
                                Управляющий директор по вопросам утилизации транспорта и сельхозтехники
                            </option>
                            <option value="HOF" {% if current_user['fund_stage'] == "HOF" %}selected{% endif %}>
                                Управляющий директор по финансовым вопросам
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldViewMode">Что видит пользователь?</label>
                        <select name="view_mode" id="fieldViewMode" class="form-control">
                            <option value="1" {% if current_user['view_mode'] == 1 %}selected{% endif %}>Все подряд
                            </option>
                            <option value="2" {% if current_user['view_mode'] == 2 %}selected{% endif %}>Транспортные
                                средства
                            </option>
                            <option value="3" {% if current_user['view_mode'] == 3 %}selected{% endif %}>
                                Автокомпоненты
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldLang">Язык</label>
                        <input type="text" name="lang" id="fieldLang" class="form-control"
                               value="{{ current_user['lang'] }}">
                    </div>

                    <div class="form-group">
                        <label for="fieldLastLogin">Последний вход</label>
                        <input type="text" name="lastlogin" id="fieldLastLogin" class="form-control"
                               value="{{ current_user['last_login'] }}" disabled>
                    </div>

                    <div class="form-group">
                        <label for="fieldUserTypeId">Тип пользователя</label>
                        <select name="user_type_id" id="fieldUserTypeId" class="form-control">
                            {% for type in user_types_list %}
                                <option value="{{ type.id }}"
                                        {% if current_user['user_type_id'] == type.id %}selected{% endif %}>{{ type.name }}</option>
                            {% endfor %}
                        </select>
                    </div>

                    <hr>
                    <div class="mt-3">
                        <a href="/users/reset_mail/{{ current_user['id'] }}" class="btn btn-warning">Сбросить почту
                            (перерегистрация)</a>
                        <a href="/users/reset_password/{{ current_user['id'] }}" class="btn btn-info">Отправить ссылку
                            на восстановление пароля</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
