<div class="card mb-4">
    <div class="card-body">
        <form action="/offset_fund_car/new/{{ offset_fund.id }}" method="get" autocomplete="off">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
            <?php if(str_contains($offset_fund->ref_fund_key->name, 'TRACTOR') || str_contains($offset_fund->ref_fund_key->name, 'COMBAIN')) {?>

            <div class="form-group">
                <label class="form-label">
                    <b>{{ t._("id-code") }}</b>
                    <b class="text text-danger">*</b>
                </label>
                <div class="row">
                    <div class="col">
                        <input type="text"
                               name="id_code"
                               id="id_code"
                               class="form-control text-uppercase"
                               value=""
                               autocomplete="off"
                               required>
                    </div>
                </div>
            </div>

            <div class="form-group mt-3">
                <label class="form-label"><b>{{ t._("body-code") }}</b></label>
                <div class="controls">
                    <input type="text"
                           name="body_code"
                           id="body_code"
                           class="form-control text-uppercase"
                           value=""
                           autocomplete="off">
                </div>
            </div>
            <?php } else { ?>

            <div class="form-group">
                <label class="form-label">
                    <b>{{ t._('vin-code') }}</b>
                    <b class="text text-danger">*</b>
                </label>
                <div class="row">
                    <div class="col-md-4">
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
                </div>
            </div>

            <?php } ?>
            <div class="">
                <button type="submit"
                        class="btn btn-primary"
                        name="button">
                    <i data-feather="check-circle" width="14" height="14"></i>
                    {{ t._('Далее') }}
                </button>
                <a href="" class="btn btn-danger">
                    <i data-feather="x-square" width="14" height="14"></i>
                    {{ t._('Отменить') }}
                </a>
            </div>
        </form>
    </div>
</div>
