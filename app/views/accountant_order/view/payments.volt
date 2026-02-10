<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                <div class="row">
                    <div class="col-6 mt-2">
                        Возможные варианты платежей
                        <span class="badge badge-warning " style="font-size: 14px;">{{ constant('ROP') }}</span>
                    </div>
                    <div class="ml-auto mr-3">
                        <button type="button" class="btn btn-info" id="getROPPaymentsList">
                            Получить данные
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body" id="ropPaymentListDiv" style="display:none">
                <table id="ropPaymentListTable" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr class="">
                        <th>На какой счет</th>
                        <th>Сумма</th>
                        <th>Дата платежа</th>
                        <th>Обнаружено</th>
                        <th>Операция</th>
                        <th>Платежи</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                <div class="row">
                    <div class="col-6 mt-2">
                        Возможные варианты платежей
                        <span class="badge badge-success "
                              style="font-size: 14px;">{{ constant('ZHASYL_DAMU') }}</span>
                    </div>
                    <div class="ml-auto mr-3">
                        <button type="button" class="btn btn-info" id="getJDPaymentsList">
                            Получить данные
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body" id="jdPaymentListDiv" style="display:none">
                <table id="jdPaymentListTable" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr class="">
                        <th>На какой счет</th>
                        <th>Сумма</th>
                        <th>Дата платежа</th>
                        <th>Обнаружено</th>
                        <th>Операция</th>
                        <th>Платежи</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
