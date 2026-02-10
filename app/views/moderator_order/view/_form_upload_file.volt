<form enctype="multipart/form-data" action="/moderator_order/doc" method="POST">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="form-group" id="order">
        <div class="controls">
            <select name="doc_type" class="form-control" style="width: 100%;">
                <option value="__empty__">(тип файла не указан)</option>
                {% if data['type'] == 'CAR' %}
                    <option value="regcertificate">Свидетельство о регистрации</option>
                    <option value="techpass">Технический паспорт</option>
                    <option value="customdec">Таможенная декларация</option>
                    <option value="customtal">Талон о прохождении границы</option>
                    <option value="ptsrts">ПТС / СРТС</option>
                    <option value="dover">Доверенность</option>
                    <option value="accepttypecar">Одобрение типа ТС</option>
                    <option value="other">Другое</option>
                {% endif %}
                {% if data['type'] == 'GOODS' %}
                    <option value="regcertificate">Свидетельство о регистрации</option>
                    <option value="invoiceimp">Счет фактура (импортера)</option>
                    <option value="packlist">Упаковочный лист</option>
                    <option value="trdocs">Транспортные накладные</option>
                    <option value="prpassport">Паспорта продукции</option>
                    <option value="importapp">Заявление о ввозе товаров</option>
                    <option value="talgovcontrol">Талон о прохождении гос. контроля
                    </option>
                    <option value="intgoods">Международная товарная накладная</option>
                    <option value="customgoods">Таможенная накладная</option>
                    <option value="railwaysorder">Железнодорожная накладная</option>
                    <option value="customdec">Таможенная декларация</option>
                    <option value="other">Другое</option>
                {% endif %}
                <option value="inprotocol">Протокол о зачете и возврате</option>
                <option value="inletter">Письмо</option>
            </select>
        </div>
    </div>
    {% if auth is defined and auth.isEmployee() %}
        <div class="form-group" id="order">
            <div class="controls">
                <input type="file" name="files_import" id="files_import"
                       class="form-control-file">
            </div>
        </div>
        <input type="hidden" name="profile_id" value="{{ data['id'] }}">
        <button type="submit" class="btn btn-sm btn-success" name="button">Загрузить
            документ
        </button>
    {% endif %}
</form>