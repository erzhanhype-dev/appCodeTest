<!-- заголовок -->
<h2>{{ t._("Редактировать банк") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Банки") }}
    </div>
    <div class="card-body">
        <form action="/ref_bank/save" method="post" autocomplete="off" class="form-horizontal form-100">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldBik">{{ t._("bik") }}</label>
                {{ text_field("bik",  "value": bik,  "size":30, "class":"form-control", "id":"fieldBik") }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("bank-name") }}</label>
                {{ text_field("name", "value": name, "size":30, "class":"form-control", "id":"fieldName") }}
            </div>
            {{ hidden_field("id", "value": id) }}
            {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->