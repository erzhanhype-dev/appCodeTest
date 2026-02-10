<!-- заголовок -->
<h2>{{ t._("Редактировать договор") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Договор") }}
  </div>

  <div class="card-body">
      <form action="/ref_contract/save" method="post" autocomplete="off" class="form-horizontal">
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <label for="fieldBik">{{ t._("bin") }}</label>
          {{ text_field("bin","size" : 30, "class":"form-control", "id":"fieldBin", "value": bin) }}
      </div>
      <div class="form-group">
        <label for="fieldName">{{ t._("contract") }}</label>
          {{ text_field("contract", "size" : 30, "class":"form-control", "id":"fieldContract", "value": contract) }}
      </div>
          <input type="hidden" name="id" value="{{ id }}">
      {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
    </form>
  </div>
</div>
<!-- /банки -->
