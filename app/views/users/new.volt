<!-- заголовок -->
<h2>Создание пользователя</h2>
<!-- /заголовок -->

<!-- пользователь -->
<div class="card mt-3 mb-5">
    <div class="card-header bg-dark text-light">
        Создание пользователя
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <form action="/users/create" method="post" autocomplete="off" class="form-horizontal">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                    <div class="form-group">
                        <label for="fieldLogin">Логин старого образца</label>
                        <input type="text" name="login" id="fieldLogin" class="form-control" placeholder="+7 (XXX) XXX-XX-XX">
                    </div>

                    <div class="form-group">
                        <label for="fieldIdnum">ИИН / БИН</label>
                        <input type="text" name="idnum" id="fieldIdnum" class="form-control" placeholder="XXXXXXXXXXXX">
                    </div>

                    <div class="form-group">
                        <label for="fieldPassword">Пароль</label>
                        <input type="password" name="password" id="fieldPassword" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="fieldEmail">Email</label>
                        <input type="email" name="email" id="fieldEmail" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="fieldActive">Активен</label>
                        <select name="active" id="fieldActive" class="form-control">
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldRoleId">Роль</label>
                        <select name="role_id" id="fieldRoleId" class="form-control">
                            {% for role in roles %}
                                <option value="{{ role.id }}">{{ role.description }}</option>
                            {% endfor %}
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldViewMode">Что видит пользователь?</label>
                        <select name="view_mode" id="fieldViewMode" class="form-control">
                            <option value="1">Все подряд</option>
                            <option value="2">Транспортные средства</option>
                            <option value="3">Автокомпоненты</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldLang">Язык</label>
                        <input type="text" name="lang" id="fieldLang" class="form-control" placeholder="ru или kk">
                    </div>

                    <div class="form-group">
                        <label for="fieldUserTypeId">Тип пользователя</label>
                        <select name="user_type_id" id="fieldUserTypeId" class="form-control">
                            {% for type in types %}
                                <option value="{{ type.id }}">{{ type.name }}</option>
                            {% endfor %}
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /пользователь -->
