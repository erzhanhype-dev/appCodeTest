<!-- заголовок -->
<h2>{{ t._("Справочник стран") }}</h2>
<!-- /заголовок -->
<!-- форма поиска -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Поиск") }}
  </div>
  <div class="card-body">
    <form method="POST" action="/ref_country/" autocomplete="off">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="row">
        <div class="col-4">
          <label><b>Поиск по стран:</b></label>
          <?php $cid = json_decode($_SESSION['filter_country_id']); ?>
          <select name="num" data-size="5" class="selectpicker form-control" data-live-search="true"  data-live-search-placeholder="Введите Название страны" >
            <option value="all" <?php if($cid == "all") echo 'selected'; ?>> - Показать все  (<?php echo count($countries); ?>) - </option>
            {% for i, country in countries %}
              <option value="{{ country.id }}" <?php if($cid == $country->id) echo 'selected'; ?>>{{ country.name }}</option>
            {% endfor %}
          </select>
        </div>
      <div class="col-4">
        <label><b>{{ t._("is_custom_union") }}</b></label>
        <?php $is_custom_union = json_decode($_SESSION['filter_is_custom_union']); ?>
        <select name="is_custom_union" class="selectpicker form-control">
          <option value="all" <?php if($is_custom_union == "all") echo 'selected'; ?>> - Показать все - </option>
          <option value="1" <?php if($is_custom_union == 1) echo 'selected'; ?>>Да</option>
          <option value="0" <?php if($is_custom_union == 0) echo 'selected'; ?>>Нет</option>
        </select>
      </div>
      <div class="col-auto mt-4">
        <button type="submit" class="btn btn-primary">{{ t._("search") }}</button>
        <button type="submit" name="reset" value="all" class="btn btn-warning">Сбросить</button>
        {{ link_to("ref_country/new", '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success ml-4') }}
      </div>
  </div>
  </form>
</div>
</div>
<!-- /форма поиска -->
<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Страны") }}
  </div>
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t._("country-name") }}</th>
          <th>{{ t._("Alpha-2") }}</th>
          <th width="40%">{{ t._("is_custom_union") }} / ({{ t._("begin_date") }} ~ {{ t._("end_date") }})</th>
          <th>{{ t._("operations") }}</th>
        </tr>
      </thead>
      <tbody>
      {% if page.items|length > 0 %}
        {% for ref_country in page.items %}
        <tr>
          <td width="10%">{{ ref_country.id }}</td>
          <td>{{ ref_country.name }}</td>
          <td>{{ ref_country.alpha2 }}</td>
          <td>
            {{ t._("yesno-"~ref_country.is_custom_union) | upper  }}
            {% if ref_country.is_custom_union and (ref_country.begin_date or ref_country.begin_date) %}
            <font class="font-italic">
              (С: <b>{% if ref_country.begin_date %}{{ ref_country.begin_date }}{% else %} Не указан {% endif%}</b>
               ПО: <b>{% if ref_country.end_date %}{{ ref_country.end_date }}{% else %} Не указан {% endif%}</b> )
            </font>
            {% else %}
            {% endif%}
          </td>
          <td width="10%">
            {{ link_to("ref_country/edit/"~ref_country.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }}
          </td>
        </tr>
        {% endfor %}
        {% endif %}
      </tbody>
    </table>
  </div>
</div>
<!-- /банки -->

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
