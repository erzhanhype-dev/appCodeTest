<div class="d-flex justify-content-center align-items-center">
    <div class="card mt-5 w-25">
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <p><b>{{ t._("reset-access-from-eds") }}</b></p>
                    <p style="text-indent: 2em;margin-bottom: 0.2em">
                        Настоящим сообщаем, что при регистрации в автоматизированной
                        информационной системе app.recycle.kz указана электронная почта, к
                        которой в настоящее время отсутствует доступ.
                    </p>
                    <p style="text-indent: 2em;">
                        В связи с вышеуказанным прошу осуществить сброс (удаление) ранее
                        указанного электронного адреса, с целью предоставления возможности
                        повторной регистрации в системе с актуальным адресом электронной
                        почты.
                    </p>
                    <p style="font-weight: 500">
                            <i>
                                После подтверждения через ЭЦП ваши учетные данные будут обновлены, и вы будете
                                перенаправлены на страницу смены пароля
                            </i>
                    </p>
                    {{ flash.output() }}
                    <div class="d-flex justify-content-between align-items-center">
                        <form id="formSign" action="/session/reset_eds" method="POST">
                            <textarea type="text" id="sign" name="sign" style="display: none;" readonly></textarea>
                            <input id="hash" name="hash" type="hidden" value="{{ hash }}"/>
                            <button type="button" class="btn btn-success resetAppBtn">
                                Подписать и сбросить
                            </button>
                        </form>
                        <a href="/session/forgot_password" class="afterline">{{ t._("back") }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>