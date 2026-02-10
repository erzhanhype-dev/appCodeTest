<!-- форма входа -->
<div class="d-flex justify-content-center mt-5">
    <div class="card mt-5" style="max-width: 550px;">
        <div class="card-header">
            <div class="row">
                <div class="col">Вход в систему</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <form id="signIn" action="/session/secure" method="POST" autocomplete="off">
                        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                        <p class="text-center">С 1 июня 2020 года вход в систему осуществляется при помощи ЭЦП.</p>
                        <p class="text-center"><strong>Напоминаем, что для работы с ЭЦП вам понадобится <a
                                        target="_blank" href="https://pki.gov.kz/ncalayer/">NCALayer</a>.</strong></p>

                        <div style="max-width: 453px;margin: 10px auto;">
                            <label>
                                <input type="checkbox" id="agreeCheckbox">
                                Настоящим Вы даете согласие на сбор, обработку, передачу и хранение персональных
                                данных согласно Закону Республики Казахстан <a href="/examples/Tipovoi-dogovor.pdf" download>"О персональных данных и их защите»</a>
                            </label>
                            <label>
                                <input type="checkbox" id="acceptanceCheckbox">
                                Ознакомлен(а) с <a href="#" data-target=".agree_modal" data-toggle="modal">ответственностью </a>
                                за использование ЭЦП согласно Закону Республики
                                Казахстан «Об электронном документе и электронной цифровой подписи»
                            </label>
                        </div>

                        <p class="text-center">
                            <textarea type="text" id="pem" name="pem" style="display: none;" readonly></textarea>
                            <input id="base64ToSign" name="hash" type="hidden" value="{{ hash }}"/>
                            <button type="button" class="btn btn-success" id="orderSignBtn" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                     class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                          d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"/>
                                    <path fill-rule="evenodd"
                                          d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                                </svg>
                                Войти в систему
                            </button>
                        </p>

                        <div class="alert alert-warning">
                            <strong>Внимание!</strong> Для входа требуется подтверждение обоих пунктов согласия.</strong>
                        </div>

                        <div class="text-center">Ввиду добавления двухфакторной аутентификации просим ознакомиться с
                            инструкцией по входу в
                            систему (<a href="/examples/twofactorauth.ppsx?v=3">
                                <i class="fa fa-download" aria-hidden="true"></i>
                                Двухфакторная аутентификация
                            </a>)
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal agree_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-body" style="font-size: 14px;">
                <p class="text-justify">
                    Я, пользователь автоматизированной информационной
                    системы «Учет поступлений утилизационных платежей» (далее -
                    АИС App.recycle.kz), продолжая работу на интернет-ресурсе
                    App.recycle.kz), подтверждаю свое согласие, что несу
                    ответственность за все совершаемые действия в соответствии с
                    Законом РК от 7 января 2003 года №370 «Об электронном
                    документе и электронной цифровой подписи», главы 7
                    «Уголовные правонарушения в сфере информатизации и связи»
                    Уголовного кодекса РК.
                    Согласно статье №640 Кодекса РК об административных
                    правонарушениях, незаконная передача закрытого ключа
                    электронной цифровой подписи другим лицам влечет
                    административную ответственность.
                    Я принимаю условия пользовательского соглашения и
                    ознакомлен(а) с ответственностью.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>
