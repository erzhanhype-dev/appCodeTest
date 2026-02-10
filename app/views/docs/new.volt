<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("docs-directory") }}</h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("docs-edit") }}</div>
      <div class="panel-body">
        {{ form("docs/create", "method":"post", "enctype":"multipart/form-data", "autocomplete" : "off", "class" : "form-horizontal") }}
          <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <div class="form-group">
            <label for="fieldTitle" class="col-sm-2 control-label">{{ t._('docs-title') }}</label>
            <div class="col-sm-10">
                {{ text_field("title", "size" : 30, "class" : "form-control", "id" : "fieldTitle") }}
            </div>
        </div>
        <div class="form-group">
            <label for="fieldTitleKk" class="col-sm-2 control-label">{{ t._('docs-title-kk') }}</label>
            <div class="col-sm-10">
                {{ text_field("title_kk", "size" : 30, "class" : "form-control", "id" : "fieldTitleKk") }}
            </div>
        </div>
        <div class="form-group">
            <label for="fieldLink" class="col-sm-2 control-label">{{ t._('docs-link') }}</label>
            <div class="col-sm-10">
                {{ text_field("link", "size" : 30, "class" : "form-control", "id" : "fieldLink") }}
            </div>
        </div>
        <div class="form-group">
            <label for="fieldLink" class="col-sm-2 control-label">{{ t._('docs-preview') }}</label>
            <div class="col-sm-10">
                <input type="file" name="preview" id="preview" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <label for="fieldLink" class="col-sm-2 control-label">{{ t._('docs-file') }}</label>
            <div class="col-sm-10">
                <input type="file" name="doc" id="doc" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                {{ submit_button(t._('save'), 'class': 'btn btn-default') }}
            </div>
        </div>
        </form>
      </div>
    </div>
  </div>
</div>
