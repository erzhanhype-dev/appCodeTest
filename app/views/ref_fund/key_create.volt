<!-- заголовок -->
<h2>{{ t._("Новый ключ") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Ключ") }}
  </div>
  <div class="card-body">
    {{ form("ref_fund/key_create", "method":"post", "autocomplete" : "off", "class" : "form-horizontal") }}
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <label for="fieldKeyName">{{ t._("Ключ (категория + диапазон)") }}</label>
        {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldKeyName") }}
      </div>
      <div class="form-group">
        <label for="fieldKeyDescription">{{ t._("Значение") }}</label>
        {{ text_field("description", "size" : 30, "class" : "form-control", "id" : "fieldKeyDescription") }}
      </div>
      {{ submit_button(t._('create'), 'class': 'btn btn-success') }}
    </form>
  </div>
</div>
<!-- /банки -->
