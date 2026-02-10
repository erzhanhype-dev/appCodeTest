
<!-- заголовок KPP -->
<div class="row">
    <div class="col-4">
        <h2>{{ t._("kpp-list") }}</h2>
    </div>
    <div class="col-2">
        <div class="d-flex justify-content-center"></div>
    </div>
    <div class="col-6">
        <div class="float-right">
            {% if !profile.blocked and !app_form %}
                <a href="/kpp/new/{{ profile.id }}" class="btn btn-success btn-lg">
                    <i data-feather="plus" width="20" height="14"></i>
                    {{ t._("Добавить КПП") }}
                </a>
            {% endif %}
        </div>
    </div>
</div>
<!-- /заголовок KPP-->

<div class="row">
    <div class="col">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("kpp-in-application") }}{{ tr.profile_id }}
                <span class="badge badge-warning mb-2" style="font-size: 14px;">
          Тип плательщика: {{ t._(profile.agent_status) }}
          <input type="hidden" value="{{ tr.profile_id }}" id="kppViewProfileId">
        </span>
            </div>
            <div class="card-body"id="ORDER_KPP_VIEW_FORM">
                <table id="viewKppList" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr class="">
                        <th>{{ t._("num-symbol") }}</th>
                        <th>{{ t._("tn-code") }}</th>
                        <th>{{ t._("kpp-weight") }}</th>
                        <th>{{ t._("basis-good") }}</th>
                        <th>{{ t._("basis-date") }}</th>
                        <th>{{ t._("kpp-invoice-sum") }}</th>
                        <th>{{ t._("kpp-invoice-sum-currency") }}</th>
                        <th>{{ t._("Валюта к тенге") }}</th>
                        <th>{{ t._("amount") }}</th>
                        <th>{{ t._("date-of-import") }}</th>
                        <th>{{ t._("package-tn-code") }}</th>
                        <th>{{ t._("package-weight") }}</th>
                        <th>{{ t._("package-cost") }}</th>
                        <th>{{ t._("operations") }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <hr>

                {%if profile.created > constant("ROP_ESIGN_DATE") %}
                    {% if tr.ac_approve == 'SIGNED' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display: none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    Сгенерировать архив
                                </button>
                            </div>
                            <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
                        </div>
                    {% endif %}
                {% else %}
                    {% if tr.approve == 'GLOBAL' %}
                        <div class="row" id="SVUP_ZIP_DIV" style="display: none">
                            <div class="col-3" id="GEN_ZIP_DIV">
                                <button class="btn btn-primary" id="gen_zip_SVUP">
                                    <span class="spinner-border spinner-border-sm" id="zip_SVUP_spinner"></span>
                                    Сгенерировать архив
                                </button>
                            </div>
                            <div class="col-3" id="SVUP_ZIP_DOWNLOAD_LINK"></div>
                        </div>
                    {% endif %}
                {% endif %}

            </div>
        </div>
    </div>
</div>
