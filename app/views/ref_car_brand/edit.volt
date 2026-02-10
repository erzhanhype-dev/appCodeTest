<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("cars-directory") }}</h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("brand-edit") }}</div>
      <div class="panel-body">
        {{ form("ref_car_brand/save", "method":"post", "autocomplete" : "off", "class" : "form-horizontal form-100") }}
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <div class="form-group">
          <label for="fieldName" class="col-sm-2 control-label">{{ t._("brand") }}</label>
          <div class="col-sm-10">
            {{ text_field("name", "size" : 30, "class" : "form-control", "id" : "fieldName") }}
          </div>
        </div>
        {{ hidden_field("id") }}
        <div class="form-group">
          <div class="col-sm-offset-2 col-sm-10">
            {{ submit_button(t._("save"), 'class': 'btn btn-success') }}
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</div>