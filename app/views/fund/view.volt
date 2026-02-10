{% if fund.entity_type == "CAR" %}
  {% include "fund/view_car.volt" %}
{% else %}
  {% include "fund/view_goods.volt" %}
{% endif %}