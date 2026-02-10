<div class="row">
    {# Левая колонка с формой #}
    <div class="col-4">
        <div class="card mt-3 h-100">
            <div class="card-header bg-dark text-light">
                {{ t._("msx_integration") }}
            </div>
            <div class="card-body">
                <form action="/msx/send" id="msxRequestForm" method="POST" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="form-group mt-2">
                        <label id="msxColumnName"><b>Введите номер двигателя:</b><span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="uniqueNumber" placeholder="XXXXXXXXXXXXXXXXX"
                               value="" autocomplete="off" required>
                    </div>
                    <div class="form-group mt-2">
                        <label for="base_on"><b>Комментарий: </b><span style="color:red">*</span></label>
                        <textarea class="form-control" name="comment" rows="3" required></textarea>
                        <small id="base_on" class="form-text text-muted">Введите на основании чего вы делаете запрос</small>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary msxRequestSubmitBtn">
                        <span class="spinner-border spinner-border-sm" id="msx_request_spinner" style="display:none"></span>
                        Отправить запрос
                    </button>
                    <a href="/msx/reset" class="btn btn-danger ml-3">
                        <i data-feather="refresh-cw" width="16" height="16"> </i>
                        Очистить все
                    </a>
                </form>
            </div>
        </div>
    </div>

    {# Правая колонка: Техническая информация о запросе (РЯДОМ с формой) #}
    <div class="col-8">
        {% if msxResult is defined and msxResult['data'] is defined %}

            {% if msxResult['status'] == 'success' %}
                <div class="card mt-3 h-100">
                    <div class="card-header bg-info text-white">
                        <b><i data-feather="info" width="16" height="16"></i> Техническая информация запроса</b>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-sm mb-0">
                            <tbody>
                            {% if msxResult['data']['request_time'] is defined %}
                                <tr>
                                    <th>Дата и время запроса:</th>
                                    <td>{{ msxResult['data']['request_time']|datetime_format }}</td>
                                </tr>
                            {% endif %}
                            <tr>
                                <th width="30%">Message ID:</th>
                                <td><code>{{ msxResult['data']['message_id'] }}</code></td>
                            </tr>
                            <tr>
                                <th>Время выполнения:</th>
                                <td>{{ msxResult['data']['execution_time'] }} сек.</td>
                            </tr>
                            <tr>
                                <th>Файл запроса:</th>
                                <td><a href="/msx/downloadFile/{{ msxResult['data']['request'] }}">{{ msxResult['data']['request'] }}</a></td>
                            </tr>
                            <tr>
                                <th>Файл ответа:</th>
                                <td><a href="/msx/downloadFile/{{ msxResult['data']['response'] }}">{{ msxResult['data']['response'] }}</a></td>
                            </tr>
                            <tr>
                                <th>Справка запроса:</th>
                                <td><a href="/msx/download/{{ msxResult['msx_request_id'] }}">Скачать</a></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            {% else %}
                {# Ошибка (показываем здесь же, рядом с формой) #}
                <div class="alert alert-danger mt-3">
                    <h4><i data-feather="alert-triangle"></i> Ошибка!</h4>
                    <p>{{ msxResult['message']|default('Произошла ошибка при получении данных.') }}</p>
                    {% if msxResult['data'] is defined %}
                        <hr>
                        <pre>{{ dump(msxResult['data']) }}</pre>
                    {% endif %}
                </div>
            {% endif %}

        {% endif %}
    </div>
</div>
{# Блок основных результатов #}
{% if msxMatrix is defined and msxMatrix['colCount'] > 0 %}

<style>
  .msx-left { width: 250px; }
</style>

<div class="card msx-card mt-5" style="overflow:scroll">
  <div class="card-body">
    <table class="table table-bordered table-sm msx-table">
      <thead>
        <tr>
          <th class="msx-left bg-light">Параметр</th>
          {% for col in msxMatrix['cols'] %}
            <th class="bg-light">Результат #{{ col }}</th>
          {% endfor %}
        </tr>
      </thead>

      <tbody>
        {% for r in msxMatrix['rows'] %}

     {% if r['type'] == 'group' %}
         <tr>
             {# 1-я колонка: заголовок #}
             <td class="msx-group"
                 style="background-color: {{ r['headerColor'] }}; color:#fff; font-weight:700;">
                 {{ r['title'] }}
             </td>

             {# остальные колонки: пустые, но с тем же цветом до конца строки #}
             {% for col in msxMatrix['cols'] %}
                 <td style="background-color: {{ r['headerColor'] }};"></td>
             {% endfor %}
         </tr>

          {% else %}
            <tr style="background-color: {{ r['bodyColor'] }};">
              <td class="msx-left">
                <small><b>{{ t._(r['key']) }}</b></small>
              </td>

              {% for col in msxMatrix['cols'] %}
                {% set value = r['vals'][col] %}
                <td class="msx-val">
                  {% if value === 'true' or value === true %}
                    <span class="badge badge-success">Да</span>
                  {% elseif value === 'false' or value === false %}
                    <span class="badge badge-secondary">Нет</span>
                  {% elseif value is empty %}
                    <span class="text-muted">-</span>
                  {% else %}
                    {{ value }}
                  {% endif %}
                </td>
              {% endfor %}
            </tr>
          {% endif %}

        {% endfor %}
      </tbody>
    </table>
  </div>
</div>

{% endif %}
