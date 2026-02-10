<!-- заголовок -->
<h2>{{ t._("Редактировать КБЕ") }}</h2>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("КБЕ") }}</div>
    <div class="card-body">
        <form action="/ref_kbe/save" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <input type="hidden" name="id" value="{{ id }}">
            <div class="form-group">
                <label for="fieldKbe">{{ t._("kbe") }}</label>
                <input
                        type="text"
                        name="kbe"
                        id="fieldKbe"
                        class="form-control"
                        value="{{ kbe|default('') }}"
                        maxlength="30"
                >
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("kbe-id") }}</label>
                <input
                        type="text"
                        name="name"
                        id="fieldName"
                        class="form-control"
                        value="{{ name|default('') }}"
                        maxlength="30"
                >
            </div>

            {{ submit_button(t._("save"), "class":"btn btn-success") }}
        </form>
    </div>
</div>
