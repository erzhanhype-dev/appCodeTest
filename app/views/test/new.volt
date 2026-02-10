<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("new-application") }}</h3> </div>

{{ form("test/edit", "method": "post", "id": "frm_order") }}
<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("add-application-assembly") }}</div>
      <div class="panel-body">
        <div class="form-group" id="order">
          <label class="form-label">{{ t._("order-type") }}</label>
          <span class="help">{{ t._("order-type-help") }}</span>
          <div class="controls">
            <select name="order_type" id="df-order-type">
              <option value="CAR">Автомобиль</option>
              <option value="GOODS">Автокомпоненты</option>
              <option value="_1">Упаковка, товары из бумаги и пластмасс, электроника и бытовая техника, кабельная продукция</option>
              <option value="_2">Импортируемые товары в упаковке</option>
            </select>
          </div>
        </div>

        <div class="form-group" id="order">
          <label class="form-label">{{ t._("agent-status") }}</label>
          <span class="help">(доступно только для администраторов и агентов)</span>
          <div class="controls">
            <select name="agent_status">
              <option value="IMPORTER1">Импортер из третьих стран (не входящих в ЕАЭС)</option>
              <option value="IMPORTER2">Импортер из стран ЕАЭС</option>
              <option id="order-vendor" value="VENDOR">Производитель</option>
            </select>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-xs-12 text-center">
    <button type="submit" class="btn btn-success" name="button">{{ t._("add-application") }}</button>
  </div>
</div>
</form>
