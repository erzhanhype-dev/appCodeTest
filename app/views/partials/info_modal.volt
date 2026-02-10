<!-- xml_viewer -->
<div class="modal fade xml_view_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t._("XML Viewer") }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body" style="width:100%; height:780px; overflow:auto;">
                <div id="dynamicXML-output"></div>
            </div>
        </div>
    </div>
</div>
<!-- /xml_viewer -->

<!-- pdf_viewer_modal -->
<div class="modal" id="modalEptsPdf">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Электронный паспорт транспортного средства</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="clear:both" id="eptsPdfViewer">
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /pdf_viewer_modal -->

<!-- image_viewer_modal -->
<div class="modal" id="modalEptsImage">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Изображение транспортного средства</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="clear:both" id="eptsImageViewer">
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /image_viewer_modal -->

<!-- epts_info_modal -->
<div class="modal fade epts_info_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Данные с ЭПТС</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">
                <table class="table table-hover table-bordered table-sm" id="epts_info_table">
                    <thead>
                    <tr>
                        <th>Название поля</th>
                        <th>Значение</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- /epts_info_modal -->

<!-- display_corr_changes_modal -->
<div class="modal fade display_corr_changes_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Посмотреть изменения</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">
                <table class="table table-bordered" id="display_corr_changes_table">
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
    </div>
</div>
<!-- /display_corr_changes_modal -->

<!-- restore_annulled_car_form_modal -->
<div class="modal fade restore_annulled_car_form_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t._("restore-annulment") }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">
                <table class="table table-bordered" id="car_info_before_and_after_restore">
                    <thead>
                    <tr>
                        <th>Название поля</th>
                        <th>Данные до</th>
                        <th>Данные после</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <form id="restoreCarForm" action="/correction/restore_annulled_car/" method="POST"
                      enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="hidden" name="car_id" id="RestoreCarId">
                    <input type="hidden" name="expected_amount" id="RestoreCarAmount">
                    <input type="hidden" name="expected_cost" id="RestoreCarCost">
                    <input type="hidden" name="expected_vin" id="RestoreCarVin">
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("comment") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <textarea name="car_comment" id="RestoreCarComment" class="form-control"
                                  placeholder="Ваш комментарий ... "></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <b>{{ t._("Загрузить файл") }}</b>
                            <b class="text text-danger">*</b>
                        </label>
                        <input type="file" id="RestoreCarFile" name="car_file" class="form-control-file">
                    </div>
                    <hr>
                    <input type="hidden" name="profile" id="RestoreCarProfileId">
                    <input type="hidden" name="hash" id="RestoreCarHash">
                    <textarea type="hidden" name="sign" id="RestoreCarSign" style="display: none;"></textarea>
                    <div class="row ml-1">
                        <div class="col-12">
                            <div class="alert alert-danger" id="restore_car_alerts" style="display: none">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <button type="button" class="btn btn-primary signRestoreCarsBtn">
                                <i data-feather="check-circle" width="14" height="14"></i>
                                Подписать с ЭЦП
                            </button>
                        </div>

                        <div class="col-1" id="restore_car_spinner" style="display: none">
                            <span class="spinner-border spinner-border-sm"></span>
                        </div>

                        <div class="col-auto">
                            <button data-dismiss="modal" aria-label="Close" class="btn btn-danger">
                                <i data-feather="x-square" width="14" height="14"></i>
                                Отмена
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /restore_annulled_car_form_modal -->

<!-- new_kap_info_modal -->
<div class="modal fade kap_info_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true" id="kap_info_table">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Данные с КАП</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card card-body">

            </div>
        </div>
    </div>
</div>
<!-- /new_kap_info_modal -->
