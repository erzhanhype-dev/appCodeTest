{% if fund.entity_type == "CAR" %}
  {% include "moderator_fund/view_car.volt" %}
{% else %}
  {% include "moderator_fund/view_goods.volt" %}
{% endif %}