<div class="row">
    <div class="col">
        <div class="card mt-3 mb-5">
            <div class="card-header bg-dark text-light">
                <div class="row ml-1">
                    <div class="col-3 mt-2">
                        Логи действий с заявкой
                    </div>
                    <div class="ml-auto mr-3">
                        <form id="getLogsForm" method="POST" action="/moderator_order/get_logs/">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <input type="hidden" name="pid" value="{{ data['id'] }}">
                            <button type="submit" class="btn btn-info"><span
                                        class="spinner-border spinner-border-sm"
                                        id="moderator_view_logs_spinner"
                                        style="display: none"></span> Просмотр логи
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body" id="profile_logs_card" style="display: none">
                <div class="table-responsive">
                    <table class="table table-hover" id="logs_table">
                        <thead>
                        <tr>
                            <th>{{ t._("logged-user") }}</th>
                            <th style="max-width: 50%;">{{ t._("logged-action") }}</th>
                            <th>{{ t._("logged-date") }}</th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
                <div class="text-center" id="logs_body_spinner" style="display: none">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
