<div class="row justify-content-center">
    <div class="col-md-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0">Реестр заявок</h5>
                <small class="text-muted">Финансирование методом взаимозачета</small>
            </div>
            <a href="{{ url('offset_fund/index') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Назад
            </a>
        </div>

        {% set q_period_start_at = request.getQuery('period_start_at') %}
        {% set q_period_end_at = request.getQuery('period_end_at') %}
        {% set q_country_id = request.getQuery('country_id', 'int') %}
        {% set q_ref_car_cat_id = request.getQuery('ref_car_cat_id', 'int') %}
        {% set q_ref_tn_code_id = request.getQuery('ref_tn_code_id', 'int') %}
        {% set q_ref_fund_key_id = request.getQuery('ref_fund_key_id', 'int') %}
        {% set q_total_value = request.getQuery('total_value') %}

        <div id="ajax-update-container">

            {{ flash.output() }}

            <div class="card mb-4">
                <div class="card-body">
                    <form id="offsetFiltersForm" action="{{ url('offset_fund/new') }}" method="get">

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label class="font-weight-bold small text-uppercase text-muted">Тип заявки</label>
                                <select name="object" class="form-control js-auto-reload">
                                    <option value="car" {% if entity_type == 'car' %}selected{% endif %}>
                                        Транспортные средства / ССХТ
                                    </option>
                                    <option value="goods" {% if entity_type == 'goods' %}selected{% endif %}>
                                        Товары(Автокомпоненты)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label class="font-weight-bold small text-uppercase text-muted">Финансирование</label>
                                <select name="type" class="form-control js-auto-reload">
                                    <option value="INS" {% if type == 'INS' %}selected{% endif %}>Внутреннее</option>
                                    <option value="EXP" {% if type == 'EXP' %}selected{% endif %}>Экспорт</option>
                                </select>
                            </div>
                        </div>

                        <div id="step-dates" style="display:none;">
                            <div class="form-group row">
                                <div class="col-md-4">
                                    <label class="font-weight-bold small text-uppercase text-muted">Дата начала отчетного
                                        периода</label>
                                    <input type="date" name="period_start_at" id="inp_start"
                                           class="form-control js-check-visibility"
                                           value="{{ q_period_start_at }}" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-4">
                                    <label class="font-weight-bold small text-uppercase text-muted">Дата окончания отчетного
                                        периода</label>
                                    <input type="date" name="period_end_at" id="inp_end"
                                           class="form-control js-check-visibility"
                                           value="{{ q_period_end_at }}" required>
                                </div>
                            </div>
                        </div>

                        <div id="step-category" style="display:none;">
                            <div class="form-group row">
                                <div class="col-md-4">
                                    <label class="font-weight-bold small text-uppercase text-muted">Категория</label>
                                    <select name="ref_fund_key_id" id="inp_category" class="form-control js-auto-reload" required>
                                        <option value="">-- Выберите категорию --</option>
                                        {% if ref_fund_keys is defined %}
                                            {% for fund_key in ref_fund_keys %}
                                                <option value="{{ fund_key.id }}"
                                                        {% if q_ref_fund_key_id == fund_key.id %}selected{% endif %}>
                                                    {{ t._(fund_key.description) }}
                                                </option>
                                            {% endfor %}
                                        {% else %}
                                            <option value="" disabled>Справочник пуст</option>
                                        {% endif %}
                                    </select>

                                    {% if limit_obj is defined and limit_obj %}
                                        <div class="mt-2 text-info small">
                                            <i class="fa fa-info-circle"></i> Доступный лимит: <strong>{{ limit_obj.value }}</strong>
                                        </div>
                                    {% endif %}
                                </div>
                            </div>
                        </div>

                        <div id="step-amount" style="display:none;">
                            {% if entity_type == 'car' %}
                                <div class="form-group row">
                                    <div class="col-md-4">
                                        <label class="font-weight-bold small text-uppercase text-muted">Параметр(Объем/Масса)</label>
                                        <input type="number" name="total_value" class="form-control js-auto-reload" step="0.01" min="0.01"
                                               placeholder="Введите значение" value="{{ q_total_value }}" required>
                                    </div>
                                </div>
                            {% else %}
                                <div class="form-group row">
                                    <div class="col-md-4">
                                        <label class="font-weight-bold small text-uppercase text-muted">Общий вес (кг)</label>
                                        <input type="number" name="total_value" class="form-control js-auto-reload"
                                               step="0.01" min="0.01" placeholder="Введите вес" value="{{ q_total_value }}"
                                               required>
                                    </div>
                                </div>
                            {% endif %}
                        </div>

                    </form>
                </div>

                <form id="realSubmitForm" action="{{ url('offset_fund/add') }}" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="hidden" name="type" value="{{ type }}">
                    <input type="hidden" name="entity_type" value="{{ entity_type }}">

                    <input type="hidden" name="period_start_at" id="post_start" value="{{ q_period_start_at }}">
                    <input type="hidden" name="period_end_at" id="post_end" value="{{ q_period_end_at }}">
                    <input type="hidden" name="total_value" id="post_amount" value="{{ q_total_value }}">
                    <input type="hidden" name="ref_car_cat_id" value="{{ q_ref_car_cat_id }}">
                    <input type="hidden" name="ref_tn_code_id" value="{{ q_ref_tn_code_id }}">
                    <input type="hidden" name="ref_fund_key_id" id="post_cat" value="{{ q_ref_fund_key_id }}">

                    <div class="form-group mt-4 text-right pr-4 pb-3" id="step-submit" style="display: none;">
                        <a href="{{ url('offset_fund/index') }}" class="btn btn-light mr-2">Отмена</a>
                        <button type="button" class="btn btn-success" onclick="submitFinalForm()">
                            <i class="fa fa-check"></i> Создать
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        function checkVisibility() {
            var stepDates = document.getElementById('step-dates');
            var stepCategory = document.getElementById('step-category');
            var stepAmount = document.getElementById('step-amount');
            var stepSubmit = document.getElementById('step-submit');

            var inpStart = document.getElementById('inp_start');
            var inpEnd = document.getElementById('inp_end');
            var inpCategory = document.getElementById('inp_category');

            if(stepDates) stepDates.style.display = 'block';

            if (stepDates && inpStart && inpEnd && inpStart.value && inpEnd.value) {
                stepCategory.style.display = 'block';
            } else {
                if(stepCategory) stepCategory.style.display = 'none';
                if(stepAmount) stepAmount.style.display = 'none';
                if(stepSubmit) stepSubmit.style.display = 'none';
                return;
            }

            if (stepCategory && inpCategory && inpCategory.value) {
                stepAmount.style.display = 'block';
                stepSubmit.style.display = 'block';
            } else {
                if(stepAmount) stepAmount.style.display = 'none';
                if(stepSubmit) stepSubmit.style.display = 'none';
            }
        }

        checkVisibility();

        document.body.addEventListener('change', function(e) {
            if (e.target.classList.contains('js-check-visibility')) {
                checkVisibility();
            }
        });

        function initOffsetForm() {
            var form = document.getElementById('offsetFiltersForm');
            if (!form) return;
            var t = null;

            function reloadWithAllFields() {
                var params = new URLSearchParams(new FormData(form));
                var qs = params.toString();
                var url = form.action + (qs ? ('?' + qs) : '');

                var container = document.getElementById('ajax-update-container');
                if(container) container.style.opacity = '0.5';

                fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(response) {
                    return response.text();
                }).then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newContent = doc.getElementById('ajax-update-container');

                    if (container && newContent) {
                        container.innerHTML = newContent.innerHTML;
                        container.style.opacity = '1';
                        initOffsetForm();
                        checkVisibility();
                    } else {
                        window.location.assign(url);
                    }
                }).catch(function(err) {
                    console.error('Error:', err);
                    if(container) container.style.opacity = '1';
                });
            }

            function handleReload(e) {
                if (!e.target || !e.target.classList || !e.target.classList.contains('js-auto-reload')) return;

                clearTimeout(t);
                t = setTimeout(reloadWithAllFields, 100);
            }

            form.addEventListener('change', handleReload);

            form.addEventListener('keydown', function(e){
                if(e.key === 'Enter' && e.target.classList.contains('js-auto-reload')) {
                    e.preventDefault();
                    e.target.blur();
                }
            });
        }

        initOffsetForm();

        window.submitFinalForm = function() {
            var formGet = document.getElementById('offsetFiltersForm');
            var formPost = document.getElementById('realSubmitForm');

            if(!formGet.reportValidity()) return;

            document.getElementById('post_start').value = formGet.querySelector('[name="period_start_at"]').value;
            document.getElementById('post_end').value = formGet.querySelector('[name="period_end_at"]').value;
            document.getElementById('post_cat').value = formGet.querySelector('[name="ref_fund_key_id"]').value;
            document.getElementById('post_amount').value = formGet.querySelector('[name="total_value"]').value;

            formPost.submit();
        };
    });
</script>