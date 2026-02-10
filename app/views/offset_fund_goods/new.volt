<div class="row justify-content-center">
    <div class="col-md-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0">Добавление товара</h5>
                <small class="text-muted">Финансирование методом взаимозачета</small>
            </div>
            <a href="{{ url('offset_fund/index') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Назад
            </a>
        </div>

        <div id="ajax-update-container">
            {{ flash.output() }}
            <div class="card">
                <div class="card-body pl-0 pr-0">
                    <div class="col-md-8 col-lg-4 col-sm-12">
                        <form action="/offset_fund_goods/add/{{ offset_fund.id }}" method="post">
                            {{ partial('offset_fund_goods/form') }}
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Добавить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>