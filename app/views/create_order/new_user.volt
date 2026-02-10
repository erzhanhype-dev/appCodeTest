  <!-- заголовок -->
  <h2>{{ t._("Создание пользователя") }}</h2>
  <!-- /заголовок -->

  <!-- пользователь -->
  <div class="card mt-3 mb-5">
    <div class="card-header bg-dark text-light">
      {{ t._("Создание пользователя") }}
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col">
            <form action="/create_order/create_user" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                <div class="form-group">
                    <label for="fieldLogin">{{ t._("ИИН / БИН") }} *</label>
                    {{ text_field("idnum", [
                        "minlength": 12,
                        "maxlength": 12,
                        "class": "form-control",
                        "id": "fieldIdnum",
                        "placeholder": "XXXXXXXXXXXX",
                        "required": "required"
                    ]) }}
                </div>

                <div class="form-group">
                    <label for="fieldUserTypeId">{{ t._("user-type") }} *</label>
                    {{ select([
                        "user_type_id",
                        types,
                        "using": ["id", "name"],
                        "class": "form-control",
                        "id": "fieldUserTypeId",
                        "useEmpty": true,
                        "emptyText": "Выберите тип...",
                        "emptyValue": ""
                    ]) }}
                </div>
                {{ submit_button(t._("save"), "class" : "btn btn-primary") }}
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- /пользователь -->