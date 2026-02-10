<h3>{{ t._("yours-car-list") }}</h3>

<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("cars-for-payment") }}</div>
      <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{ t._("num-symbol") }}</th>
              <th>{{ t._("volume-weight") }}</th>
              <th>{{ t._("cost") }}</th>
              <th>{{ t._("vin-code") }}</th>
              <th>{{ t._("year-of-manufacture") }}</th>
              <th>{{ t._("date-of-import") }}</th>
              <th>{{ t._("country-of-manufacture") }}</th>
              <th>{{ t._("car-category") }}</th>
              <th>{{ t._("operations") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if page.items is defined %}
            {% for item in page.items %}
            <tr>
              <td class="v-align-middle">{{ item.p_id }}</td>
              <td class="v-align-middle">
                {{ item.c_volume }}
                {% if item.c_type == constant('TRUCK') %}
                  {{ t._("kg") }}
                {% else %}
                  {{ t._("cm") }}
                {% endif %}
              </td>
              <td class="v-align-middle"><?php echo number_format($item->c_cost, 2, ",", "&nbsp;"); ?></td>
              <td class="v-align-middle">{{ item.c_vin }}</td>
              <td class="v-align-middle">{{ item.c_year }}</td>
              <td class="v-align-middle">{{ date("d.m.Y", item.c_date_import) }}</td>
              <td class="v-align-middle">{{ item.c_country }}</td>
              <td class="v-align-middle">{{ t._(item.c_cat) }}</td>
              <td class="v-align-middle">
                <a href="/car/view/{{ item.c_id }}" title="{{ t._("view-car") }}"><button type="button" name="b_view" class="btn btn-primary btn-xs"><i class="fa fa-eye"></i></button></a>
                <a href="/order/view/{{ item.c_profile }}" title="{{ t._("view-application") }}"><button type="button" name="b_view" class="btn btn-primary btn-xs"><i class="fa fa-reorder"></i></button></a>
                {% if !item.p_blocked %}
                  <a href="/car/edit/{{ item.c_id }}" title="{{ t._("edit") }}"><button type="button" name="b_view" class="btn btn-default btn-xs"><i class="fa fa-edit"></i></button></a>
                  <a href="/car/delete/{{ item.c_id }}" title="{{ t._("delete") }}"><button type="button" name="b_view" class="btn btn-default btn-xs"><i class="fa fa-remove"></i></button></a>
                {% endif %}
                {% if item.c_tr_approve == 'GLOBAL' %}
                  <a href="/main/certificate/{{ item.c_tr }}/{{ item.c_id }}" title="{{ t._("certificate") }}" target="_blank"><button type="button" name="b_view" class="btn btn-warning btn-xs"><i class="fa fa-certificate"></i></button></a>
                {% endif %}
              </td>
            </tr>
            {% endfor %}
            {% endif %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{% if page is defined and page.current is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
