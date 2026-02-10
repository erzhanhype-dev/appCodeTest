<div class="row">
    <div class="col-8">
        <div class="row">
            <div class="col">
                <div class="card mt-3">
                    <div class="card-header bg-dark text-light">
                        {{ t._('add-car') }}
                    </div>
                    <div class="card-body">
                        {% if m == 'CAR' %}
                            <form action="/car/new/{{ pid }}" method="post" id="frm_order" autocomplete="off">
                                <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                                <div class="form-group">
                                    <label class="form-label">
                                        <b>{{ t._('vin-code') }}</b>
                                        <b class="text text-danger">*</b>
                                    </label>
                                    <div class="row">
                                        <div class="col">
                                            <input type="text"
                                                   name="vin"
                                                   id="vin"
                                                   class="form-control text-uppercase"
                                                   minlength="17"
                                                   maxlength="17"
                                                   placeholder="XXXXXXXXXXXXXXXXX"
                                                   autocomplete="off"
                                                   required>
                                            <small class="form-text text-muted">
                                                VIN-код должен содержать в себе только символы на латинице и цифры, не
                                                больше 17 символов.
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <input type="hidden" name="profile" value="{{ pid }}">
                                            <input type="hidden" name="type" value="{{ m }}">
                                            <button type="submit"
                                                    class="btn btn-primary"
                                                    id="car_button_submit"
                                                    name="button">
                                                <i data-feather="check-circle" width="14" height="14"></i>
                                                {{ t._('Далее') }}
                                            </button>
                                            <a href="/order/view/{{ pid }}" class="btn btn-danger">
                                                <i data-feather="x-square" width="14" height="14"></i>
                                                {{ t._('Отменить') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        {% elseif m == 'TRAC' %}
                            <form action="/car/new/{{ pid }}" ~ pid method="post" id="frm_order" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                            <div class="form-group">
                                <div class="row">
                                    <div class="col">
                                        <label class="form-label">
                                            <b>{{ t._('id-code') }}</b>
                                            <b class="text text-danger">*</b>
                                        </label>
                                        <input type="text"
                                               name="id_code"
                                               id="id_code"
                                               class="form-control text-uppercase"
                                               minlength="3"
                                               maxlength="17"
                                               placeholder="XXXXXXXXXXXXXXXXX"
                                               autocomplete="off"
                                               required>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col">
                                        <label class="form-label">
                                            <b>{{ t._('body-code') }}</b>
                                        </label>
                                        <div class="controls">
                                            <input type="text"
                                                   name="body_code"
                                                   id="body_code"
                                                   class="form-control text-uppercase"
                                                   autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-auto mt-4">
                                        <input type="hidden" name="profile" value="{{ pid }}">
                                        <input type="hidden" name="type" value="{{ m }}">
                                        <button type="submit"
                                                class="btn btn-primary"
                                                id="car_button_submit"
                                                name="button">
                                            {{ t._('Далее') }}
                                        </button>
                                        <a href="/order/view/{{ pid }}" class="btn btn-danger">
                                            <i data-feather="x-square" width="14" height="14"></i>
                                            {{ t._('Отменить') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                    </form>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>