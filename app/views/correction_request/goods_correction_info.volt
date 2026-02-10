<!-- содержимое заявки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Содержимое заявки") }}
    </div>
    <div class="card-body" id="APPROVED_CORRECTION_REQUEST">
        <input type="hidden" id="approvedCorrectionReqPid" value="{{ cc_pr.id }}">
        <table class="table table-bordered table-sm" id="corr_changes_after_approved" style="margin-bottom: -1px">
            <thead>
            <tr>
                <th>Название поля</th>
                <th>Данные до</th>
                <th>Данные после</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
