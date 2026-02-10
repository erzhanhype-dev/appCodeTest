<!-- заголовок -->
<h2>{{ t._("Новая страна") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Страны") }}
    </div>
    <div class="card-body">
        <form action="/ref_country/create" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldName">{{ t._("country-name") }}</label>
                {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("Alpha-2") }}</label>
                {{ text_field("alpha2", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
            </div>
            <!-- Таможенный союз? -->
            <div class="form-group">
                <label for="fieldName">{{ t._("is_custom_union") }}</label>
                <select name="is_custom_union" class="selectpicker form-control" data-live-search="true">
                    <option value="1"> Да, это таможенный союз</option>
                    <option value="0" selected="selected"> Нет, это не таможенный союз</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ t._("Укажите периода") }}</label>
                <div class="controls">
                    От: <input type="date" name="period_start">
                    До: <input type="date" name="period_end">
                </div>
            </div>
            <!-- конец Таможенный союз? -->
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->
