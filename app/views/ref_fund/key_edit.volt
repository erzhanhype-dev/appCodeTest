<!-- заголовок -->
<h2>{{ t._("Редактировать ключ(категория + диапазон)") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
  <div class="card-header bg-dark text-light">
    {{ t._("Ключ (категория + диапазон)") }}
  </div>
  <div class="card-body">
    {{ form("ref_fund/key_save", "method":"post", "autocomplete" : "off", "class" : "form-horizontal form-100") }}
      <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

      <div class="form-group">
        <label for="fieldName">{{ t._('	Ключ (категория + диапазон)') }}</label>
        {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
      </div>
      <div class="form-group">
        <label for="fieldDescription">{{ t._('Значение') }}</label>
        {{ text_field("description", "size" : 30, "class" : "form-control", "id" : "fieldDescription") }}
      </div>
      {{ hidden_field("id") }}
      {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
    </form>
  </div>
</div>
<!-- /банки -->