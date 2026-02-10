<h2>{{ t._("Новая Модель") }}</h2>

<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Модель") }}
    </div>
    <div class="card-body">
        <form action="/ref_car_model/create" method="post" autocomplete="off" class="form-horizontal form-100">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldName">{{ t._("Марка") }}</label>
                {{ text_field("brand", "type" : "numeric", "class" : "form-control") }}
            </div>

            <div class="form-group">
                <label for="fieldName">{{ t._("Модель") }}</label>
                {{ text_field("model", "type" : "numeric", "class" : "form-control") }}
            </div>

            <div class="form-group" id="car_cat_group">
                <label class="form-label">{{ t._("car-category") }}</label>
                <select name="car_cat" class="form-control">
                    {% for cat in categories %}
                        {% for cat in categories %}
                            <option value="{{ cat.id }}">{{ t._(cat.name) }}</option>
                        {% endfor %}
                    {% endfor %}
                </select>
            </div>

            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>





