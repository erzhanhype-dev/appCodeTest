<!-- заголовок -->
<h2>{{ t._("Справочник договоров") }}</h2>
<!-- /заголовок -->
<div class="text-right mb-3">
  {{ link_to("ref_contract/new", '<i data-feather="plus"></i> Добавить', 'class': 'btn btn-success') }}
</div>
<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Договора") }}
  </div>
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t._("bin") }}</th>
          <th>{{ t._("contract") }}</th>
          <th>{{ t._("operations") }}</th>
        </tr>
      </thead>
      <tbody>
      {% if page.items|length > 0 %}
        {% for ref_contract in page.items %}
        <tr>
          <td width="10%">{{ ref_contract.id }}</td>
          <td>{{ ref_contract.bin }}</td>
          <td>{{ ref_contract.contract }}</td>
          <td width="10%">{{ link_to("ref_contract/edit/"~ref_contract.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }} {{ link_to("ref_contract/delete/"~ref_contract.id, '<i data-feather="trash" width="14" height="14"></i>', 'class': 'btn btn-danger btn-sm') }}</td>
        </tr>
        {% endfor %}
        {% endif %}
      </tbody>
    </table>
  </div>
</div>
<!-- /банки -->

