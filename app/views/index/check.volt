<div class="container-fluid" id="top">
  <div class="container">
    <div class="row">
      <!-- <div class="col-md-6 col-sm-4 col-xs-6"> -->
      {#      <div class="col-md-2 col-sm-2 col-xs-6">#}
      {#        <a href="{{ constant('HTTP_ADDRESS') }}"><img src="/assets/img/logo_black.svg" onerror="this.onerror=null; this.src='/assets/img/logo2x_black.png'" width="150" height="65"></a>#}
      {#      </div>#}
      <!-- <div class="col-md-3 col-sm-4 hidden-xs"> -->
      <div class="col-md-7 col-sm-7 hidden-xs">
        <span class="phone hidden"><i class="fa fa-phone"></i>+7&nbsp;(7172)&nbsp;72&nbsp;79&nbsp;65</span><br />
        <span class="freecall hidden">{{ t._("call-us") }}</span>
      </div>
      <div class="col-md-1 col-md-offset-0 col-sm-2 col-sm-offset-0 col-xs-3 col-xs-offset-3 text-center lang">
        {% if !session.get('pl') or session.get('pl') == 'ru' %}<a href="/index/cl/kk"><img src="/assets/img/rukz.png" alt="" /></a>{% endif %}
        {% if session.get('pl') == 'kk' %}<a href="/index/cl/ru"><img src="/assets/img/kzru.png" alt="" /></a>{% endif %}
      </div>
      <div class="col-md-2 col-sm-2 hidden-xs text-center">
        <a href="/session" class="circled">{{ t._("enter") }}</a>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid" id="check-form">
  <div class="container">
    <div class="row">
      <form id="frm_check" method="POST" action="/index/check/" autocomplete="off">
        <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
        <h3>{{ t._("pay") }}</h3>
        <div class="input-group index">
          <span class="input-group-addon" id="basic-addon-search"><i class="fa fa-search"></i></span>
          <input name="check_vin" id="check_vin" type="text" class="form-control" placeholder="{{ t._("vin-code") }}" aria-describedby="basic-addon-search">
        </div>
      </form>
    </div>
  </div>
</div>

<!-- результаты -->

<div class="container-fluid" id="check-results">
  <div class="container">
    {% if found is defined %}
      {% if found == true %}
      <div class="row">
        <div class="col-sm-12">
          <h3>{{ t._("search-results") }}</h3>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-7 col-sm-offset-3">
          <div class="whiteboard">
            <h4>{{ t._("search-results-by-vin") }} <b>{{ car.vin }}</b></h4>
            <table>
              <tr>
                <td class="text-left">{{ t._("car-num-system") }}</td>
                <td class="text-right"><?php echo str_pad($car->profile_id, 9, 0, STR_PAD_LEFT); ?></td>
              </tr>
              <tr>
                <td class="text-left">{{ t._("car-category") }}</td>
                <td class="text-right">
                  {{ t._(cat.name) }}
                  {# уточнение по седельным тягачам от 21.09.2020
                 дата импорта после 24.05.2019 применять признак седельности, а до не применяит #}
                  {% if(car.date_import >= 1558634400) %}
                    {% if(car.ref_st_type == 1 and car.ref_car_type_id == 2)  %}
                      (Седельный тягач)
                    {% else %}
                      (Не седельный тягач)
                    {% endif %}
                  {% endif %}
                </td>
              </tr>
              <tr>
                <td class="text-left">{{ t._("volume-weight") }}</td>
                <td class="text-right">{{ car.volume }}</td>
              </tr>
              <tr>
                <td class="text-left">{{ t._("doc-date") }}</td>
                <td class="text-right"><?php echo date('d.m.Y', $tr->date); ?></td>
              </tr>
            </table>
            {# Кнопка на скачивание #}
            {# <a href="/main/certificate/{{ tr.id }}/{{ car.id }}" class="circled">{{ t._("download") }}</a> #}
            {# <p class="text-center text-uppercase" style="margin-top: 20px;"><strong>{{ t._("doc-issued") }}</strong></p> #}
          </div>
        </div>
      </div>
      {% endif %}
      {% if found == false %}
      <div class="row">
        <div class="col-sm-12">
          <h3>{{ t._("search-results-no-results") }}</h3>
        </div>
      </div>
      {% endif %}
    {% else %}
    <div class="row">
      <div class="col-sm-4 col-sm-offset-4">
        <div class="text-center"><i class="fa fa-commenting"></i></div>
        <p class="text-center">{{ t._("if-you-want-pay") }}</p>
        <p class="text-center last"><a href="/session" class="circled">{{ t._("signin-and-registration") }}</a></p>
      </div>
    </div>
    {% endif %}
  </div>
</div>
