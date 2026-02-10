<!-- заголовок -->
<h2>{{ t._("Редактировать страну") }}</h2>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">{{ t._("Страна") }}</div>
    <div class="card-body">
        <form action="/ref_country/save" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <input type="hidden" name="id" value="{{ id }}">

            <div class="form-group">
                <label for="fieldName">{{ t._("country-name") }}</label>
                {{ text_field("name", "class":"form-control", "id":"fieldName", "value": name) }}
            </div>

            <div class="form-group">
                <label for="fieldAlpha2">{{ t._("Alpha-2") }}</label>
                {{ text_field("alpha2", "class":"form-control", "id":"fieldAlpha2", "maxlength":"2", "value": alpha2) }}
            </div>

            <div class="form-group">
                <label for="fieldIsCustomUnion">{{ t._("is_custom_union") }}</label>
                {{ select_static(
                    ['is_custom_union', 'class':'form-control', 'id':'fieldIsCustomUnion', 'value': is_custom_union],
                    ['1': t._('yes'), '0': t._('no')]
                ) }}
            </div>

            <div class="form-group">
                <label class="form-label">{{ t._("begin_date") }}</label>
                <div class="controls">
                    {{ date_field("begin_date", "value": begin_date) }}
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">{{ t._("end_date") }}</label>
                <div class="controls">
                    {{ date_field("end_date", "value": end_date) }}
                </div>
            </div>

            {{ submit_button(t._("save"), "class":"btn btn-success") }}
        </form>
    </div>
</div>
