<form id="changeProfileStatus" action="/moderator_order/change_status/" method="POST">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="card-body">
        <input type="hidden" name="id" value="{{ data['id']  }}">
        <div class="row">
            <div class="col">
                <label><b>Статус оплаты:</b></label>
                <select class="form-control" name="status">
                    {% if data['amount'] == 0 %}
                        <option value="NOT_PAID" {% if data['status'] == 'NOT_PAID' %} selected {% endif %}>{{ t._("no_payment_required") }}</option>
                        <option value="PAID" {% if data['status'] == 'PAID' %} selected {% endif %}>Оплачен
                        </option>
                    {% else %}
                        <option value="NOT_PAID" {% if data['status'] == 'NOT_PAID' %} selected {% endif %}>
                            Не
                            оплачен
                        </option>
                        <option value="PAID" {% if data['status'] == 'PAID' %} selected {% endif %}>Оплачен
                        </option>
                    {% endif %}
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col">
                <label><b>Статус заявки:</b></label>
                <select class="form-control" name="approve">
                    <option value="REVIEW" {% if data['approve'] == 'REVIEW' %} selected {% endif %}>{{ t._("REVIEW") }}</option>
                    <option value="GLOBAL" {% if data['approve'] == 'GLOBAL' %} selected {% endif %}>{{ t._("GLOBAL") }}</option>
                    <option value="DECLINED" {% if data['approve'] == 'DECLINED' %} selected {% endif %}>{{ t._("DECLINED") }}</option>
                    <option value="NEUTRAL" {% if data['approve'] == 'NEUTRAL' %} selected {% endif %}>{{ t._("NEUTRAL") }}</option>
                    <option value="APPROVE" {% if data['approve'] == 'APPROVE' %} selected {% endif %}>{{ t._("APPROVE") }}</option>
                    <option value="CERT_FORMATION" {% if data['approve'] == 'CERT_FORMATION' %} selected {% endif %}>{{ t._("CERT_FORMATION") }}</option>
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col">
                <label><b>Подпись бухгалтера:</b></label>
                <select class="form-control" name="ac_approve">
                    <option value="SIGNED" {% if data['ac_approve'] == 'SIGNED' %} selected {% endif %}>
                        Подписано
                    </option>
                    <option value="NOT_SIGNED" {% if data['ac_approve'] == 'NOT_SIGNED' %} selected {% endif %}>
                        Не подписано
                    </option>
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col">
                <label><b>Комментарий:</b></label>
                <textarea class="form-control" name="comment" placeholder="Оставьте комментарий..."
                          required></textarea>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="row col-12">
            <div class="col-6">
            </div>
            <div class="col-6">
                <button type="submit" class="btn btn-success">
                                <span class="spinner-border spinner-border-sm" id="change_status_spinner"
                                      style="display: none"></span>
                    Сохранить
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
            </div>
        </div>
    </div>

</form>