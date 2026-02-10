  <!-- заголовок -->
  <h2>{{ t._("Привязка платежей") }}</h2>
  <!-- /заголовок -->

  <!-- форма -->
  <div class="card">
    <div class="card-body">
      <form action="admin_bank/set/{{ bank_id }}" method="POST" id="frm_set" autocomplete="off">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

        <div class="form-row">
          <div class="col">
            <div class="form-group">
              <label class="form-label">{{ t._("Привязать платежи") }}</label>
              <input type="text" name="tr_id" id="tr_id" value="{{ bank_tr }}" class="form-control">
              <small id="trHelp" class="form-text text-muted">{{ t._("Номер заявки или несколько номеров через запятую") }}</small>
              <input type="hidden" name="bank_id" value="{{ bank_id }}">
              <?php if(isset($_GET['p'])): ?><input type="hidden" name="profile_back" value="<?php echo $_GET['p']; ?>"><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-success" name="button">{{ t._("Привязать платежи") }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- /форма  -->