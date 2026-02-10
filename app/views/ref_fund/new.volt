<!-- заголовок -->
<h2>{{ t._("Новый лимит") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Лимит") }}
    </div>
    <div class="card-body">
        <form action="/ref_fund/create" method="post" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="fieldIdnum">{{ t._("ИИН / БИН") }}</label>
                {{ text_field("idnum", "size" : 30, "class" : "form-control", "id" : "fieldIdnum", "maxlength": "12", "minlength": "12") }}
            </div>
            <div class="form-group">
                <label for="fieldStart">{{ t._("Начало периода производства") }}</label>
                {{ text_field("prod_start", "size" : 30, "class" : "form-control datepicker", "id" : "fieldStart", "data-provide" : "datepicker") }}
            </div>
            <div class="form-group">
                <label for="fieldEnd">{{ t._("Конец периода производства") }}</label>
                {{ text_field("prod_end", "size" : 30, "class" : "form-control datepicker", "id" : "fieldEnd", "data-provide" : "datepicker") }}
            </div>
            <div class="form-group">
                <label for="fieldYear">{{ t._("Год финансирования") }}</label>
                {{ text_field("year", "size" : 30, "class" : "form-control", "id" : "fieldYear" ) }}
            </div>

            <div class="form-group">
                <label class="form-label">{{ t._("Объект финансирования") }}</label>
                <select name="type" id="ref_fund_type" class="form-control">
                    <option value="">-</option>
                    <option value="car">ТС</option>
                    <option value="goods">Товар</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">{{ t._("Ключ (категория + диапазон)") }}</label>
                <select name="key" class="selectpicker form-control" data-live-search="true" id="key-select">
                    <option value="">-</option>
                    {% for i, key in keys %}
                        <option value="{{ key.name }}" data-type="{{ key.entity_type }}">{{ key.description }}
                            ({{ key.name }})
                        </option>
                    {% endfor %}
                </select>
            </div>

            <div class="form-group">
                <label for="fieldAmount" id="fieldAmountLabel">{{ t._("Количество (ед.)") }}</label>
                {{ text_field("value", "size" : 30, "class" : "form-control", "id" : "fieldAmount") }}
            </div>
            {{ submit_button(t._('create'), 'class': 'btn btn-success') }}
        </form>
    </div>
</div>