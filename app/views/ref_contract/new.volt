<!-- заголовок -->
<h2>{{ t._("Новый договор") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Договор") }}
    </div>
    <div class="card-body">
        <form action="/ref_contract/create" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldBik">{{ t._("bin") }}</label>
                {{ text_field("bin", "size" : 30, "class" : "form-control", "id" : "fieldBin") }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("contract") }}</label>
                {{ text_field("contract", "size" : 30, "class" : "form-control", "id" : "fieldContract") }}
            </div>
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->
