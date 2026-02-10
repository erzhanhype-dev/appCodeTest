<div class="page-title"><i class="fa fa-home"></i> <h3>{{ t._("main-page-agent") }}</span></h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-md-4 col-sm-6">
    <div class="panel panel-default no-link-main">
      <a href="/order">
        <div class="panel-body">
        <p><i class="fa fa-bank fa-5x"></i></p>
        <h3>{{ t._("applications") }}</h3>
        <p>{{ t._("applications-and-payments") }}</p>
      </div>
      </a>
    </div>
  </div>
  <div class="col-md-4 col-sm-6">
    <div class="panel panel-default no-link-main">
      <a href="/settings">
        <div class="panel-body">
        <p><i class="fa fa-gears fa-5x"></i></p>
        <h3>{{ t._("agent-data") }}</h3>
        <p>{{ t._("personal-set") }}</p>
      </div>
      </a>
    </div>
  </div>
  <div class="col-md-4 col-sm-6">
    <div class="panel panel-default no-link-main">
      <a href="/help">
        <div class="panel-body">
        <p><i class="fa fa-question-circle fa-5x"></i></p>
        <h3>{{ t._("help") }}</h3>
        <p>{{ t._("help-data") }}</p>
      </div>
      </a>
    </div>
  </div>
</div>
