<!-- заголовок -->
<h2>{{ t._("Новый коэффициент") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Коэффициент") }}
    </div>
    <div class="card-body">
        <form action="/ref_car_value/create" method="post" autocomplete="off" class="form-horizontal form-100">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldCarType">{{ t._("type") }}</label>
                {{ text_field("car_type", "type" : "numeric", "class" : "form-control", "id" : "fieldCarType") }}
            </div>
            <div class="form-group">
                <label for="fieldVolumeStart">{{ t._("volume-from") }}</label>
                {{ text_field("volume_start", "type" : "numeric", "class" : "form-control", "id" : "fieldVolumeStart") }}
            </div>
            <div class="form-group">
                <label for="fieldVolumeEnd">{{ t._("volume-to") }}</label>
                {{ text_field("volume_end", "type" : "numeric", "class" : "form-control", "id" : "fieldVolumeEnd") }}
            </div>
            <div class="form-group">
                <label for="fieldPrice">{{ t._("mrp") }}</label>
                {{ text_field("price", "size" : 30, "class" : "form-control", "id" : "fieldPrice") }}
            </div>
            <div class="form-group">
                <label for="fieldK">{{ t._("koef") }}</label>
                {{ text_field("k", "size" : 30, "class" : "form-control", "id" : "fieldK") }}
            </div>
            <div class="form-group">
                <label for="fieldK_2022">{{ t._("koef_2022") }}</label>
                {{ text_field("k_2022", "size" : 30, "class" : "form-control", "id" : "fieldK_2022") }}
            </div>
            {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>
<!-- /банки -->
