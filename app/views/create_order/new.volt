<h3>Создание заявки</h3>
<form id="addOrderForm" action="/create_order/add" method="POST" autocomplete="off">
  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-dark text-light">{{ t._("add-application-assembly") }}</div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">{{ t._("order-type") }}</label>
            <div class="controls">
              <div class="btn-group btn-group-toggle" data-toggle="buttons">
                <label id="bavto" class="btn btn-wide btn-secondary active">
                  <input type="radio" name="order_type" value="CAR" checked> <i data-feather="arrow-right"></i> Автотранспорт, сельхозтехника
                </label>
                <label id="bcomp" class="btn btn-wide btn-secondary">
                  <input type="radio" name="order_type" value="GOODS"> <i data-feather="arrow-right"></i> Автокомпоненты, товары и упаковка
                </label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t._("agent-status") }}</label>
            <div class="controls">
              <select name="agent_status" class="form-control">
                <option value="IMPORTER">Импортер</option>
                <option value="VENDOR">Производитель</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t._("order_initiator") }}</label>
            <div class="controls">
              <select name="initiator_id" class="form-control">
                {% for i, item in initiators %}
                  <option value="{{ item.id }}">{{ item.name }}</option>
                {% endfor %}
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t._("Поиск пользователя в базе app.recycle.kz") }}</label>
            <div class="row">
              <div class="col-4">
                <input class="form-control" type="text" id="user_idnum" placeholder="Введите БИН или ИИН клиента" maxlength="12" minlength="12">
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-primary"  id="clientInfoBtn"><i data-feather="search" width="16" height="16"></i> Поиск</button>
              </div>
            </div>
          </div>
          <div class="alert alert-success alert-block" id="successMessageBlok" style="display:none">
              <button type="button" class="close" data-dismiss="alert">×</button>
              <strong>Пользователь найден</strong>
              <strong id="linkToUserSettings"></strong>   
            </div>
            <div class="alert alert-danger alert-block" id="errorMessageBlok" style="display:none">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong id="errorMessage"></strong>   
            </div>
            <div id="createNewUserButton" style="display:none">
               <div class="form-group">
                    <a href="/create_order/new_user" class="btn btn-primary">{{ t._("Создать пользователя") }}</a>
               </div>
            </div>
          <div id="clientInfoSection" style="{% if client_title != '' and client_idnum != '' and client_uid != '' %} {{ 'display:block' }} {% else %} {{ 'display:none' }} {% endif %}">
            <div class="form-group">
              <label class="form-label">{{ t._("Название, ФИО") }}</label>
              <div class="controls">
                <input type="text" name="agent_name" id="clientName" class="form-control" value="<?php $client_title ? htmlspecialchars($client_title) : ''; ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t._("БИН(ИИН)") }}</label>
              <div class="controls">
                <input type="text" name="agent_iin" id="clientIdnum" class="form-control" maxlength="12" minlength="12" value="{{ client_idnum }}">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t._("comment") }}</label>
              <div class="controls">
                <textarea name="comment" class="form-control" rows="2" required></textarea>
              </div>
            </div>
            <input type="hidden" name="user_id" id="clientUserId" value="{{ client_uid }}">
            <button type="submit" class="btn btn-success">{{ t._("Добавить заявку") }}</button>
            <a href="/create_order/" class="btn btn-danger">Отмена</a>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</form>
