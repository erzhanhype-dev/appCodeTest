<!-- заголовок -->
<h2>{{ t._("Справочник лимитов") }}</h2>
<!-- /заголовок -->

<!-- content -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    <div class="row">
      <div class="col-3">
         {{ t._("Список ключей (категория + диапазон) лимитов") }}
      </div>
      <div class="ml-auto mr-1">
        <a href="/ref_fund/key_create/" class="btn btn-primary"><i data-feather="plus" width="16" height="16"></i> Создать новый ключ</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <table class="table table-hover table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t._("Ключ (категория + диапазон)") }}</th>
          <th>{{ t._("Значение") }}</th>
          <th>{{ t._("Операции") }}</th>
        </tr>
      </thead>
      <tbody>
        {% for key in keys %}
        <tr>
          <td>{{ key.id }}</td>
          <td><?php echo __detect_in_cyrillic($key->name);?></td>
          <td>{{ key.description }}</td>
          <td>
            {{ link_to("ref_fund/key_edit/"~key.id, '<i data-feather="edit" width="14" height="14"></i>', 'class': 'btn btn-secondary btn-sm') }}
            <a href="/ref_fund/key_delete/{{key.id}}" data-confirm="Вы действительно хотите удалить это?" class="btn btn-danger btn-sm confirmBtn"><i data-feather="trash" width="14" height="14"></i></a>
          </td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</div>
<!-- /content -->
