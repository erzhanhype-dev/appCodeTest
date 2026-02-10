<h2>{{ t._("Редактировать Модель ТС") }}</h2>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Модель") }}
    </div>
    <div class="card-body">
        <form action="/ref_car_model/save" method="post" autocomplete="off" class="form-horizontal form-100">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldCarType">{{ t._("Марка") }}</label>
                {{ text_field("brand", "type" : "numeric", "class" : "form-control", "brand" : "fieldCarType", 'value': brand) }}
            </div>
            <div class="form-group">
                <label for="fieldVolumeStart">{{ t._("Модель") }}</label>
                {{ text_field("model", "type" : "numeric", "class" : "form-control", "model" : "fieldVolumeStart",  'value': model) }}
            </div>
            <div class="form-group">
                <label for="fieldName">{{ t._("Класс") }}</label>
                <select name="ref_car_cat_id" class="form-control">
                    {% for cat in categories %}
                        <option value="{{ cat.id }}">{{ t._(cat.name) }}</option>
                    {% endfor %}
                </select>
            </div>
            <input type="hidden" name="id" value="{{ id }}">

            {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
