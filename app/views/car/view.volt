<h3>{{ t._("car") }}</h3>

{% if car is defined %}
{% for item in car %}
<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">
        {{ t._("car-number") }}{{cid}}
      </div>
      <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr class="">
              <th>{{ t._("num-symbol") }}</th>
              <th>{{ t._("parameter") }}</th>
              <th>{{ t._("index") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr class="">
              <td class="v-align-middle">1</td>
              <td class="v-align-middle">{{ t._("vin-code") }}</td>
              <td class="v-align-middle">{{ item.c_vin }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">2</td>
              <td class="v-align-middle">{{ t._("volume-cm") }}</td>
              <td class="v-align-middle">{{ item.c_volume }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">3</td>
              <td class="v-align-middle">{{ t._("year-of-manufacture") }}</td>
              <td class="v-align-middle">{{ item.c_year }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">4</td>
              <td class="v-align-middle">{{ t._("date-of-import") }}</td>
              <td class="v-align-middle">{{ date("d.m.Y", item.c_date_import) }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">5</td>
              <td class="v-align-middle">{{ t._("country-of-manufacture") }}</td>
              <td class="v-align-middle">{{ item.c_country }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">6</td>
              <td class="v-align-middle">{{ t._("car-category") }}</td>
              <td class="v-align-middle">{{ t._(item.c_cat) }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">7</td>
              <td class="v-align-middle">Платеж</td>
              <td class="v-align-middle"><?php echo number_format($item->c_cost, 2, ",", "&nbsp;"); ?></td>
            </tr>
            <tr class="">
              <td class="v-align-middle">8</td>
              <td class="v-align-middle">{{ t._("transport-type") }}</td>
              <td class="v-align-middle">{{ item.c_type }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">9</td>
              <td class="v-align-middle">{{ t._("num-application") }}</td>
              <td class="v-align-middle">{{ item.c_profile }}</td>
            </tr>
            <tr class="">
              <td class="v-align-middle">10</td>
              <td class="v-align-middle">{{ t._("ref-st") }}</td>
              <td class="v-align-middle">
              {# седельность #}
              {% if(item.c_ref_st == 1)  %}
                  {{ t._("ref-st-yes") }}
              {% elseif(item.c_ref_st == 2) %}
                {{ t._("ref-st-international-transport") }}
              {% else %}
                  {{ t._("ref-st-not") }}
              {% endif %}
              </td>
            </tr>
            {% if(item.e_car != NULL)  %}
            <tr class="">
              <td class="v-align-middle">11</td>
              <td class="v-align-middle">{{ t._("is_electric_car?") }}</td>
              <td class="v-align-middle">
                {% if(item.e_car == 1)  %}
                  {{ t._("yesno-1") }}
                {% else %}
                  {{ t._("yesno-0") }}
                {% endif %}
              </td>
            </tr>
            {% endif %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
{% endfor %}
{% endif %}
