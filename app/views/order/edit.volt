<h3>{{ t._("edit-application-assembly") }}</h3>

{{ form({'action': '/order/edit/' ~ profile['id'], 'method': 'post', 'id': 'frm_order'}) }}
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
{% set deactivated = constant("DEACTIVATED_PROFILE_TYPES") %}

<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("edit-application-assembly") }}</div>
      <div class="card-body">
        <div class="form-group" id="order">
          <div class="form-row">
            <div class="col-8">
              <label class="form-label"><b>{{ t._("order-type") }}</b></label>
              <div class="controls">
                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                  {% if 'CAR' not in deactivated %}
                    <label id="bavto" class="btn btn-wide {% if profile['type'] == 'CAR' %} btn-success active {% else %} btn-secondary {% endif %}">
                      <input type="radio" name="order_type" value="CAR" {% if profile['type'] == 'CAR' %} checked="checked" {% endif %}> <i data-feather="arrow-right"></i> Автотранспорт
                    </label>
                  {% endif %}

                  {% if 'GOODS' not in deactivated %}
                    <label id="bcomp" class="btn btn-wide {% if profile['type'] == 'GOODS' %} btn-success active {% else %} btn-secondary {% endif %}">
                      <input type="radio" name="order_type" value="GOODS" {% if profile['type'] == 'GOODS' %} checked="checked" {% endif %}> <i data-feather="arrow-right"></i> Автокомпоненты
                    </label>
                  {% endif %}

                  {% if 'KPP' not in deactivated %}
                    <label id="bkpp" class="btn btn-wide {% if profile['type'] == 'KPP' %} btn-success active {% else %} btn-secondary {% endif %}">
                      <input type="radio" name="order_type" value="KPP" {% if profile['type'] == 'KPP' %} checked="checked" {% endif %}> <i data-feather="arrow-right"></i> КПП (Кабельно-проводниковая продукция)
                    </label>
                  {% endif %}
                </div>
              </div>
            </div>

            <div class="col-4">
              <label class="form-label"><b>{{ t._("Внимание для справки") }}</b></label><br>
              <i data-feather="square" class="btn btn-wide btn-success mr-2" width="30" height="20"></i>
              Выбрано

              <i data-feather="square" class="btn btn-wide btn-secondary ml-4 mr-2" width="30" height="20"></i>
              Невыбрано
            </div>
          </div>
        </div>

        <div class="form-group" id="order">
          <label class="form-label"><b>{{ t._("agent-status") }}</b></label>
          <div class="controls">
            <div class="btn-group btn-group-toggle" data-toggle="buttons">
              <label id="order_client_type_importer" class="btn btn-wide {% if profile['agent_status'] == 'IMPORTER' %} btn-success active {% else %} btn-secondary {% endif %} mr-2">
                <input type="radio" name="agent_status" value="IMPORTER" {% if profile['agent_status'] == 'IMPORTER' %} checked {% endif %}>
                {{ t._('IMPORTER') }}
              </label>

              <label id="order_client_type_vendor" class="btn btn-wide {% if profile['agent_status'] == 'VENDOR' %} btn-success active {% else %} btn-secondary  {% endif %}">
                <input type="radio" name="agent_status" value="VENDOR" {% if profile['agent_status'] == 'VENDOR' %} checked {% endif %}>
                {{ t._('VENDOR') }}
              </label>
            </div>
          </div>
        </div>

        <div class="form-group" id="order">
          <label class="form-label"><b>{{ t._("comment") }}</b></label>
          <div class="controls">
            <input type="text" name="order_comment" id="order_comment" class="form-control" value="{{ profile['name']|e }}">
          </div>
        </div>

        <button type="submit" class="btn btn-success" name="button">{{ t._("save-application") }}</button>
      </div>
    </div>
  </div>
</div>
</form>
