<!-- заголовок -->
<h2>{{ t._("Новый банк") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Банки") }}
    </div>
    <div class="card-body">
        <form action="/ref_bank/create" method="POST" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldBik">{{ t._("bik") }}</label>
                {{ text_field("bik", "size" : 30, "class" : "form-control", "id" : "fieldBik") }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("bank-name") }}</label>
                {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
            </div>
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->