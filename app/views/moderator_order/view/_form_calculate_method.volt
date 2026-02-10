<form id="changeProfileStatus" action="/moderator_order/change_calc/" method="POST">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <input type="hidden" name="id" value="{{ data['id'] }}">
    <div class="row">
        <div class="col">
            <label><b>Способ рассчета:</b></label>
            <select class="form-control" name="calculate_method">
                <option value="1">По дате отправки</option>
            </select>
        </div>
    </div>
    <button type="submit" class="btn btn-success mt-3">
        <span class="spinner-border spinner-border-sm" id="change_status_spinner" style="display: none"></span>
        Сохранить
    </button>
</form>