<!-- заголовок -->
<h2>{{ t._("Новый код ТН ВЭД") }}</h2>
<!-- /заголовок -->

<!-- банки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Код ТН ВЭД") }}
    </div>
    <div class="card-body">
        <form action="/ref_tn_code/create" method="POST" autocomplete="off" class="form-horizontal">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <div class="form-group">
                <label for="fieldCode"><b>{{ t._('Код ТН ВЭД') }}</b><span style="color:red">*</span></label>
                {{ text_field("code", "size" : 30, "class" : "form-control", "id" : "fieldCode", "required":"required") }}
            </div>
            <div class="form-group">
                <label for="fieldGroup"><b>{{ t._('code-group') }}</b><span style="color:red">*</span></label>
                {{ text_field("group", "size" : 30, "class" : "form-control", "id" : "fieldGroup", "required":"required") }}
            </div>
            <div class="form-group">
                <label for="fieldName"><b>{{ t._('code-name') }}</b><span style="color:red">*</span></label>
                {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName", "required":"required") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice1"><b>{{ t._('price1') }}</b></label>
                {{ text_field("price1", "size" : 30, "class" : "form-control", "id" : "editFieldPrice1") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice2"><b>{{ t._('price2') }}</b></label>
                {{ text_field("price2", "size" : 30, "class" : "form-control", "id" : "editFieldPrice2") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice3"><b>{{ t._('price3') }}</b></label>
                {{ text_field("price3", "size" : 30, "class" : "form-control", "id" : "editFieldPrice3") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice4"><b>{{ t._('price4') }}</b></label>
                {{ text_field("price4", "size" : 30, "class" : "form-control", "id" : "editFieldPrice4") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice5"><b>{{ t._('price5') }}</b></label>
                {{ text_field("price5", "size" : 30, "class" : "form-control", "id" : "editFieldPrice5") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice6"><b>{{ t._('price6') }}</b></label>
                {{ text_field("price6", "size" : 30, "class" : "form-control", "id" : "editFieldPrice6") }}
            </div>
            <div class="form-group">
                <label for="editFieldPrice7"><b>{{ t._('price7') }}</b></label>
                {{ text_field("price7", "size" : 30, "class" : "form-control", "id" : "editFieldPrice7") }}
            </div>
            <div class="form-group">
                <label><b>Укажите статус:</b><span style="color:red">*</span></label>
                <div class="row mt-2">
                    <div class="col">
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-wide btn-success active">
                                {{ radio_field("is_correct", "size" : 30, "value" : 1) }} Активный
                            </label>
                            <label class="btn btn-wide btn-danger">
                                {{ radio_field("is_correct", "size" : 30, "value" : 0, "checked" : "checked") }}
                                Неактивный
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label><b>Относится к продукции с упаковкой:</b><span style="color:red">*</span></label>
                <div class="row mt-2">
                    <div class="col">
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-wide btn-success active">
                                {{ radio_field("pay_pack", "size" : 30, "value" : 1) }} Да
                            </label>
                            <label class="btn btn-wide btn-danger">
                                {{ radio_field("pay_pack", "size" : 30, "value" : 0, "checked" : "checked") }} Нет
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label><b>Укажите тип:</b><span style="color:red">*</span></label>
                <div class="row mt-2">
                    <div class="col">
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-wide btn-info active">
                                {{ radio_field("type", "size" : 30, "value" : "PRODUCT", "checked" : "checked") }} Товар
                            </label>
                            <label class="btn btn-wide btn-warning">
                                {{ radio_field("type", "size" : 30, "value" : "PACKAGE") }} Упаковка
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            {{ submit_button(t._('create'), 'class': 'btn btn-success') }}
            <a href="/ref_tn_code" class="btn btn-danger ml-3">
                <i data-feather="x" width="16" height="16"> </i>
                Отменить
            </a>
        </form>
    </div>
</div>
<!-- /банки -->
