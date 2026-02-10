<!-- заголовок -->
<h2>{{ t._("Новый КБЕ") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("КБЕ") }}
    </div>
    <div class="card-body">
        <form action="/ref_kbe/create" method="POST" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldKbe">{{ t._("kbe") }}</label>
                {{ text_field("kbe", "size" : 30, "class" : "form-control", "id" : "fieldKbe") }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("kbe-id") }}</label>
                {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
            </div>
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->


