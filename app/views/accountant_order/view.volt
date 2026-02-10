<input type="hidden" value="{{ data['id'] }}" id="pid">

<h2 class="mt-2">{{ t._("Заявка") }} #{{ data['id'] }}</h2>

<div class="row">
    {% include 'accountant_order/view/client.volt' %}
</div>

<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("Содержимое заявки") }}</div>
      <div class="card-body">
        {% if data['type'] === 'CAR' %}
          {% include 'accountant_order/view/car.volt' %}
        {% endif %}

        {% if data['type'] === 'GOODS' %}
          {% include 'accountant_order/view/goods.volt' %}
        {% endif %}

        {% if data['type'] === 'KPP' %}
          {% include 'accountant_order/view/kpp.volt' %}
        {% endif %}
        {% if data.created_dt > constant("ROP_ESIGN_DATE") %}
          {% if data.ac_approve == 'SIGNED' %}
            <hr>
            <div class="row" id="SVUP_ZIP_DIV" style="display: none">
              <div class="col-3" id="GEN_ZIP_DIV">
                <button class="btn btn-primary" id="gen_zip_SVUP">
                  <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                  Сгенерировать архив
                </button>
              </div>
              <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
            </div>
          {% endif %}
        {% else %}
          {% if data.approve == 'GLOBAL' %}
            <hr>
            <div class="row" id="SVUP_ZIP_DIV" style="display: none">
              <div class="col-3" id="GEN_ZIP_DIV">
                <button class="btn btn-primary" id="gen_zip_SVUP">
                  <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                  Сгенерировать архив
                </button>
              </div>
              <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
            </div>
          {% endif %}
        {% endif %}
      </div>
    </div>
  </div>
</div>

{% if data['type'] === 'CAR' %}
  {% if data.cancelled_cars is defined and data.cancelled_cars|length %}
    <div class="row">
      <div class="col">
        <div class="card mt-3">
          <div class="card-header bg-danger text-light">{{ t._("Отклоненные ТС") }}</div>
          <div class="card-body">
            <table id="moderatorOrderCancelledCarList" class="display" cellspacing="0" width="100%">
              <thead>
              <tr>
                <th>{{ t._("num-symbol") }}</th>
                <th>{{ t._("car-category") }}</th>
                <th>{{ t._("volume-weight") }}</th>
                <th>{{ t._("vin-code") }}</th>
                <th>{{ t._("year-of-manufacture") }}</th>
                <th>{{ t._("date-of-import") }}</th>
                <th>{{ t._("country-of-manufacture") }}</th>
                <th>{{ t._("Статус") }}</th>
              </tr>
              </thead>
              <tbody>
              {% for c in data.cancelled_cars %}
                <tr>
                  <td>{{ c.id }}</td>
                  <td>{{ t._(c.car_cat) }}</td>
                  <td>{{ c.volume }}</td>
                  <td>{{ c.vin }}</td>
                  <td>{{ c.year }}</td>
                  <td>{{ c.date_import }}</td>
                  <td>{{ c.country }}</td>
                  <td>{{ c.action ? t._(c.action) : '' }}</td>
                </tr>
              {% endfor %}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  {% endif %}
{% endif %}


{% include 'accountant_order/view/status.volt' %}

<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("documents-for-application") }}{{ data['id'] }}</span></div>
      <div class="card-body">
        <div class="row">
          <div class="col">
            {% include 'accountant_order/view/docs.volt' %}
          </div>
          <div class="col">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


