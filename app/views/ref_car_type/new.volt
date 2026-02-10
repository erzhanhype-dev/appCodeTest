<h2>{{ t._("Новый тип") }}</h2>

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Тип") }}
    </div>
    <div class="card-body">
        <form action="/ref_car_type/create" method="post" autocomplete="off" class="form-horizontal form-100">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldName">{{ t._("car-type-name") }}</label>
                {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
            </div>
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->
  