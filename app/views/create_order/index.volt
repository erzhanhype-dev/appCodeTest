<!-- заголовок -->
<div class="row">
  <div class="col-4">
    <h2>{{ t._("Список заявок") }}</h2>
  </div>
  <div class="col-2">
    <div class="d-flex justify-content-center"></div>
  </div>
  <div class="col-6">
    <div class="float-right">
      <a href="/create_order/new/" class="btn btn-success btn-lg" id="ddActions">
        <i data-feather="plus" width="20" height="14"></i>
        {{ t._("Создать новую заявку") }}
      </a>
    </div>
  </div>
</div>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Заявки") }}
  </div>
  <div class="card-body">
    <table class="table table-hover">
      <thead>
      <tr class="">
        <th>{{ t._("num-symbol") }}</th>
        <th>{{ t._("application-date") }}</th>
        <th>{{ t._("create-sent-date") }}</th>
        <th>{{ t._("amount") }}</th>
        <th>{{ t._("order-type") }}</th>
        <th>{{ t._("profile-paid") }}</th>
        <th>{{ t._("profile-approve") }}</th>
        <th>{{ t._("operations") }}</th>
      </tr>
      </thead>
      <tbody>
      {% if page.items|length %}
        {% for item in page.items %}
        <tr class="">
          <td class="v-align-middle">{{ item.p_id }}</td>
          <td class="v-align-middle"><?php echo date("d.m.Y H:i", convertTimeZone($item->p_created)); ?></td>
          <td class="v-align-middle">
            <?php echo ($item->dt_sent > 0) ? date("d.m.Y H:i", convertTimeZone($item->dt_sent)) : '—';?>
          </td>
          <td class="v-align-middle">
            <?php echo number_format($item->tr_amount, 2, ",", "&nbsp;"); ?>
          </td>
          <td class="v-align-middle"><i>{{ t._(item.p_type)  }}</i></td>
          {% set status = 'status-new' %}
          {% set paid = 'paid-false' %}
          {% set approve = 'approve-not-set' %}
          {# проверяем статусы и выводим высший по приоритету #}
          {% if item.p_blocked == 1 %}{% set status = 'status-blocked' %}{% endif %}
          {% if item.p_blocked == 0 %}{% set status = 'status-unblocked' %}{% endif %}
          {% if item.tr_status == 'NOT_PAID' %}{% set paid = 'paid-false' %}{% endif %}
          {% if item.tr_status == 'PAID' %}{% set paid = 'paid-true' %}{% endif %}
          {% if item.tr_approve == 'REVIEW' %}{% set approve = 'approve-review' %}{% endif %}
          {% if item.tr_approve == 'NEUTRAL' %}{% set approve = 'approve-neutral' %}{% endif %}
          {% if item.tr_approve == 'DECLINED' %}{% set approve = 'approve-declined' %}{% endif %}
          {% if item.tr_approve == 'APPROVE' %}{% set approve = 'approve-approve' %}{% endif %}
          {% if item.tr_approve == 'CERT_FORMATION' %}{% set approve = 'approve-cert-formation' %}{% endif %}
          {% if item.tr_approve == 'GLOBAL' %}{% set approve = 'approve-global' %}{% endif %}
          {# выводим статус ↓ #}
          <td class="v-align-middle">{{ t._(paid) }}</td>
          <td class="v-align-middle">{{ t._(approve) }}</td>
          <td class="v-align-middle">
            <a href="/create_order/view/{{ item.p_id }}" title='{{ t._("browsing") }}' class="btn btn-primary btn-sm"><i data-feather="eye" width="14" height="14"></i></a>
              <a href="/create_order/edit/{{ item.p_id }}" title='{{ t._("edit") }}' class="btn btn-warning btn-sm"><i data-feather="edit" width="14" height="14"></i></a>
          </td>
        </tr>
        {% endfor %}
      {% endif %}
      </tbody>
    </table>
    {% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
  </div>
</div>
<!-- /банки -->
