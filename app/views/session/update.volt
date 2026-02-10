<div class="d-flex justify-content-center align-items-center">
    <div class="card mt-5 w-25">
        <div class="card-body">
            <div class="row">
                <div class="col">
                    {{ flash.output() }}
                    <p>Ваш email: {{ email }}</p>
                    <form id="frm_forgot" class="animated fadeIn" method="POST" action="/session/send_password_reset_link">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                        <div class="form-group">
                            <div class="controls">
                                <input name="forgot_email" id="forgot_email" type="email" class="form-control"
                                       placeholder="Email">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="controls">
                                <button type="submit" name="forgot_submit" style="font-size: 12px;"
                                        class="btn btn-primary">{{ t._("send-link-to-email") }}</button>
                                <a href="/session/reset_eds"
                                   style="font-size: 12px;"
                                   class="btn btn-success">{{ t._("reset-data-from-eds") }}</a>
                            </div>
                        </div>
                        <a href="/session/auth" class="afterline">{{ t._("back") }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>