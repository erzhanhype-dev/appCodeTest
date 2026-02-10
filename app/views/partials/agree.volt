<div class="form-group">
  <div class="checkbox">
    <label>
      <input type="checkbox" name="i_pc" id="i_pc" value="pc" class="i_check"> {{ t._("i-agree-personal-data")|format("/help/policy") }}
      {#<input type="checkbox" name="i_pc" id="i_pc" value="pc" class="i_check"> {{ t._("i-agree") }} <a href="/help/policy" target="_blank">{{ t._("agree-personal-data") }}</a>#}
    </label>
  </div>
</div>
<div class="form-group">
  <div class="checkbox">
    <label>
      <input type="checkbox" name="i_pd" id="i_pd" value="pd" class="i_check"> {{ t._("i-agree-contract-and-offer")|format("/files/help/po_type_contract_ru.pdf", "/files/help/contract.pdf") }}
    </label>
  </div>
</div>
