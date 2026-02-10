<div class="d-flex justify-content-center align-items-center">
    <div class="card mt-5 w-25">
        <div class="card-body">
            <div class="row">
                <div class="col">
                    {{ flash.output() }}
                    <form method="post" action="/session/verify" id="verifyTwoFactorForm" class="text-center" autocomplete="off">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

                        <div class="form-group">
                            <input
                                name="code"
                                id="code"
                                type="text"
                                 class="form-control"
                                placeholder="{{t._("Введите код подтверждения")}}"
                             />
                        </div>
                        <div class="form-group">
                            <div class="controls text-right">
                                <label style="max-width: 75%;float:left;text-align:left;font-size:12px;">Необходимо ввести код подтверждения, направленный на указанный адрес электронной почты</label>
                                <button type="submit" class="btn btn-info">{{t._("next")}}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>