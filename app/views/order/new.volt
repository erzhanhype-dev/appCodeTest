<h3>Создание заявки</h3>

<form action="/order/add" method="post" id="frm_order" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("add-application-assembly") }}</div>
                <div class="card-body">
                    <div class="form-group" id="order">
                        <div class="form-row">
                            <div class="col-8">
                                <label class="form-label"><b>{{ t._("order-type") }}</b></label>
                                <div class="controls">
                                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                        <label id="bavto" class="btn btn-wide btn-secondary active">
                                            <input type="radio" name="order_type" value="CAR" checked> <i
                                                    data-feather="arrow-right"></i> Автотранспорт, сельхозтехника
                                        </label>
                                        <label id="bcomp" class="btn btn-wide btn-secondary">
                                            <input type="radio" name="order_type" value="GOODS"> <i
                                                    data-feather="arrow-right"></i> Автокомпоненты, товары и упаковка
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <label class="form-label"><b>{{ t._("Внимание для справки") }}</b></label><br>
                                <i data-feather="square" class="btn btn-wide btn-success mr-2" width="30"
                                   height="20"></i>
                                Выбрано

                                <i data-feather="square" class="btn btn-wide btn-secondary ml-4 mr-2" width="30"
                                   height="20"></i>
                                Невыбрано
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="order">
                        <label class="form-label"><b>{{ t._("agent-status") }}</b></label>
                        <div class="controls">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <label id="order_client_type_importer" class="btn btn-wide btn-secondary active mr-2">
                                    <input type="radio" name="agent_status" value="IMPORTER" checked>
                                    {{ t._('IMPORTER') }}
                                </label>
                                <label id="order_client_type_vendor" class="btn btn-wide btn-secondary">
                                    <input type="radio" name="agent_status" value="VENDOR">
                                    {{ t._('VENDOR') }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="order">
                        <label class="form-label"><b>{{ t._("comment") }}</b></label>
                        <div class="controls">
                            <input type="text" name="order_comment" id="order_comment" class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success" name="button">{{ t._("add-application") }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
