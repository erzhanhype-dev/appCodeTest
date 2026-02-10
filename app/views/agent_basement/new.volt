<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("basement-list") }}</h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("basement-new") }}</div>
      <div class="panel-body">
        {{ form("agent_basement/create", "method":"post", "autocomplete" : "off", "class" : "form-horizontal form-100") }}
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

        <div class="form-group">
          <label for="fieldUserId" class="col-sm-2 control-label">{{ t._("user_id") }}</label>
          <div class="col-sm-10">
            {{ text_field("user_id", "size" : 30, "class" : "form-control", "id" : "fieldUserId") }}
          </div>
        </div>
        <div class="form-group">
          <label for="fieldTitle" class="col-sm-2 control-label">{{ t._("basement") }}</label>
          <div class="col-sm-10">
            {{ text_field("title", "size" : 30, "class" : "form-control", "id" : "fieldTitle") }}
          </div>
        </div>
        <div class="form-group">
          <label for="fieldWt" class="col-sm-2 control-label">{{ t._("wt") }}</label>
          <div class="col-sm-10">
            {{ text_field("wt", "size" : 30, "class" : "form-control", "id" : "fieldWt") }}
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-2 col-sm-10">
            {{ submit_button(t._("create"), 'class': 'btn btn-success') }}
          </div>
        </div>
      </form>
    </form>
  </div>
</div>
</div>
</div>
