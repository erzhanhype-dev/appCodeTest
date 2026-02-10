<!-- РОП и проверка -->

<div class="container-fluid" id="about">
  <div class="container">
    <div class="row">
      <div class="col-sm-7">
        <h2>{{ t._("index-what-is-rop") }}</h2>
        <p>{{ t._("index-what-is-rop-line-1") }}</p>
        <p>{{ t._("index-what-is-rop-line-2") }}</p>
        <p>{{ t._("index-what-is-rop-line-3") }}</p>
      </div>
      <div class="col-sm-5">
        <form id="frm_check" method="POST" action="/index/check/" autocomplete="off">
          <h3>{{ t._("pay") }}</h3>
          <div class="input-group index">
            <span class="input-group-addon" id="basic-addon-search"><i class="fa fa-search"></i></span>
            <input name="check_vin" id="check_vin" type="text" class="form-control" placeholder="{{ t._("vin-code") }}" aria-describedby="basic-addon-search">
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- меню -->

<div class="container-fluid" id="menu">
  <div class="container">
    <div class="row">
      <div class="col-sm-12 text-center">
        <div class="item">
          <a href="{{ t._("news-link") }}">{{ t._("news-page") }}</a>
        </div>
        <div class="item">
          <a href="#definition" class="smooth">{{ t._("decree") }}</a>
        </div>
        <div class="item">
          <a href="#reason" class="smooth">{{ t._("pay") }}</a>
        </div>
        <div class="item">
          <a href="#targets" class="smooth">{{ t._("strategic-goal") }}</a>
        </div>
        <div class="item">
          <a href="#docs" class="smooth">{{ t._("normative-documents") }}</a>
        </div>
        <div class="item">
          <a href="#login-form" class="smooth">{{ t._("enter") }}</a>
        </div>
        <div class="item">
          <a href="#footer" class="smooth">{{ t._("contacts") }}</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- про РОП -->

<div class="container-fluid" id="about-rop">
  <div class="container">
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2 text-center">
        <h3>{{ t._("index-rop-area") }}</h3>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2 text-justify">
        <p>{{ t._("index-decree-line-1") }}</p>
        <p>{{ t._("index-decree-line-2") }}</p>
        <p>{{ t._("index-decree-line-3") }}</p>
      </div>
    </div>
  </div>
</div>

<!-- оплата требуется -->

<div class="container-fluid" id="reason">
  <div class="container">
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2 text-center">
        <h3>{{ t._("index-check-info-reason") }}</h3>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-3 col-sm-offset-1">
          <div class="row">
            <div class="col-sm-12">
              <i class="fa fa-car"></i>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-12">
              <p>{{ t._("index-check-info-reason-1") }}</p>
            </div>
          </div>
      </div>
      <div class="col-sm-4">
          <div class="row">
            <div class="col-sm-12">
              <i class="fa fa-institution"></i>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-12">
              <p>{{ t._("index-check-info-reason-2") }}</p>
            </div>
          </div>
      </div>
      <div class="col-sm-3">
          <div class="row">
            <div class="col-sm-12">
              <i class="fa fa-gavel"></i>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-12">
              <p>{{ t._("index-check-info-reason-3") }}</p>
            </div>
          </div>
      </div>
    </div>
  </div>
</div>

<!-- самостоятельная доставка -->

<div class="container-fluid" id="selfship">
  <div class="container">
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2 text-center">
        <p>{{ t._("index-self-import-warning") }}</p>
      </div>
    </div>
  </div>
</div>

<!-- оплата не требуется -->

<div class="container-fluid" id="nopay">
  <div class="container">
    <div class="row">
      <div class="col-sm-12 text-center">
        <h3>{{ t._("index-check-no-reason") }}</h3>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 text-center">
        <h4>{{ t._("index-check-no-reason-1") }}</h4>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2">
        <div class="whiteboard">
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">1</div>
            </div>
            <div class="col-sm-10">
              <p>{{ t._("index-check-no-reason-11") }}</p>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">2</div>
            </div>
            <div class="col-sm-10">
              <p>{{ t._("index-check-no-reason-12") }}</p>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">3</div>
            </div>
            <div class="col-sm-10">
              <p class="last">{{ t._("index-check-no-reason-13") }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 text-center">
        <h4>{{ t._("index-check-no-reason-2") }}</h4>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2">
        <div class="whiteboard">
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">1</div>
            </div>
            <div class="col-sm-10">
              <p>{{ t._("index-check-no-reason-21") }}</p>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">2</div>
            </div>
            <div class="col-sm-10">
              <p>{{ t._("index-check-no-reason-22") }}</p>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-2">
              <div class="circle-num">3</div>
            </div>
            <div class="col-sm-10">
              <p class="last">{{ t._("index-check-no-reason-23") }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- определение РОП -->

<div class="container-fluid" id="definition">
  <div class="container">
    <div class="row">
      <div class="col-sm-6 col-sm-offset-3 text-center">
        <p>{{ t._("index-definition-1") }}</p>
        <p>{{ t._("index-definition-2") }}</p>
      </div>
    </div>
  </div>
</div>

<!-- цели и стратегия -->

<div class="container-fluid" id="targets">
  <div class="container">
    <div class="row">
      <div class="col-sm-12 text-center">
        <h3>{{ t._("strategic-goal-rop") }}</h3>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-6 col-sm-offset-3 text-center">
        <p>{{ t._("strategic-goal-rop-msg") }}</p>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg1") }}</span></div>
      </div>
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg2") }}</span></div>
      </div>
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg3") }}</span></div>
      </div>
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg4") }}</span></div>
      </div>
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg5") }}</span></div>
      </div>
      <div class="col-sm-6">
        <div class="wrapper"><span class="bullet">{{ t._("strategic-goal-rop-msg6") }}</span></div>
      </div>
    </div>
    <div class="row" id="tasks">
      <div class="col-sm-12 text-center">
        <h3>{{ t._("priorities") }}</h3>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-6">
        <span class="bullet">{{ t._("priorities-msg1") }}</span>
      </div>
      <div class="col-sm-6">
        <span class="bullet">{{ t._("priorities-msg2") }}</span>
      </div>
      <div class="col-sm-6">
        <span class="bullet">{{ t._("priorities-msg3") }}</span>
      </div>
      <div class="col-sm-6">
        <span class="bullet">{{ t._("priorities-msg4") }}</span>
      </div>
      <div class="col-sm-6">
        <span class="bullet">{{ t._("priorities-msg5") }}</span>
      </div>
    </div>
  </div>
</div>

<!-- документы -->

<div class="container-fluid" id="docs">
  <div class="container">
    <div class="row">
      <div class="col-sm-12 text-center">
        <h3>{{ t._("normative-documents") }}</h3>
      </div>
    </div>

    {% for index, doc in docs %}
    <div class="row">
      <div class="col-sm-12">
        <a href="{% if doc.link %}{{ doc.link }}{% endif %}" target="_blank">
          <div class="item">
            <img src="/docs-preview/{{ preview[doc.id] }}" />
            <h4>{% if lang == "ru" %}{{ doc.title }}{% else %}{{ doc.title_kk }}{% endif %}</h4>
            <p>{{ t._("view") }}</p>
            <div class="clearfix"></div>
          </div>
        </a>
      </div>
    </div>
    {% endfor %}

    <div id="hidden-docs">
      {% for index, doc in docs_expand %}
      <div class="row">
        <div class="col-sm-12">
          <a href="{% if doc.link %}{{ doc.link }}{% endif %}" target="_blank">
            <div class="item">
              <img src="/docs-preview/{{ preview[doc.id] }}" />
              <h4>{% if lang == "ru" %}{{ doc.title }}{% else %}{{ doc.title_kk }}{% endif %}</h4>
              <p>{{ t._("view") }}</p>
              <div class="clearfix"></div>
            </div>
          </a>
        </div>
      </div>
      {% endfor %}
    </div>

    <!-- конец документов -->

    <div class="row" id="link-to-view">
      <div class="col-sm-12 text-center">
        <p class="text-center last">
            <a href="#" id="view-documents" class="non-circled">{{ t._("view-documents") }}</a>
            <a href="#" id="close-documents" class="non-circled">{{ t._("close-documents") }}</a>
        </p>
      </div>
    </div>
  </div>
</div>

<!-- наши представительства -->

<div class="container-fluid" id="agents">
  <div class="container">
    <div class="row">
      <!-- <div class="col-sm-6">
        <h3 class="text-center">{{ t._("our-agents") }}</h3>
        <p class="text-center">{{ t._("our-agents-desc") }}</p>
        <p class="text-center last"><a href="/index/agents" class="circled">{{ t._("view-list") }}</a></p>
      </div> -->
      <div class="col-sm-offset-3 col-sm-6">
        <h3 class="text-center">{{ t._("recycle-companies-title") }}</h3>
        <p class="text-center">{{ t._("recycle-companies-text") }}</p>
        <p class="text-center last"><a href="{{ t._("recycle-companies-link") }}" class="circled" target="_blank">{{ t._("view-list") }}</a></p>
      </div>
    </div>
  </div>
</div>

<!-- вход -->
<div class="container-fluid" id="login-form">
  <div class="container">
    <div class="row">
      <h3 class="text-center">{{ t._("entrance") }}</h3>
      <p class="text-center">{{ t._("auth-please-or-register") }}</p>
      <p class="text-center last"><a href="/session" class="circled">{{ t._("signin-and-registration") }}</a></p>
    </div>
  </div>
</div>

<div class="modal fade" id="infoModalNov" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">{{ t._("main-modal-info-title") }}</h4>
      </div>
      <div class="modal-body">
        {{ t._("main-modal-info-body") }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Закрыть</button>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
