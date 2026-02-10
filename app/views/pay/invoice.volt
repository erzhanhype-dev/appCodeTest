<h3>{{t._("making-payments")}}</h3>

<div class="row">
  <div class="col col-md-8 col-sm-12">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{t._("Важное примечание")}}</span></div>
      <div class="card-body">
        <p>При оплате онлайн (через интернет-банкинг или посредством QR-кода) необходимо указать номер заявки, указанный в документе.</p>
        <p>При оплате через банк необходимо указать номер и дату заявки строго в соответствии с данными, приведёнными в счете на оплату.</p>
        <p>Корректность и своевременность идентификации платежа обеспечивается только при точном указании вышеуказанных данных.</p>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{t._("Скачать счет на оплату")}}</span></div>
      <div class="card-body">
        <p>{{t._("payment-order-msg")}}</p>
        <p style="text-align: justify;">
          Для корректного зачисления оплаченной суммы необходимо
          <b class="text-danger">
            в назначении платежа корректно указать номер заявки и дату заявки согласно сведениям, указанным в счете на оплату.
          </b>
        </p>
        <p>
          В случае, если вы не указали номер и дату заявки либо указали их некорректно в назначении платежа, необходимо обратиться в АО «Жасыл даму» через платформу
          <a href="https://eotinish.gov.kz" target="_blank">https://eotinish.gov.kz</a>,
          с просьбой зачислить оплаченные средства в счет заявки
          <b>{{ tr }}</b>
          и приложить  платежное поручение к обращению.</p>
        <a href="/pay/print/{{ tr }}" class="btn btn-primary btn-large btn-block">
          <i class="fa fa-download"></i>
          {{t._("Скачать счет на оплату")}}
        </a>
      </div>
    </div>
  </div>

  <div class="col col-md-4 col-sm-12 text-center mt-2">
    <div><h5>Оплатить через Halyk Bank</h5></div>
    <img src="/assets/img/qr-code-halyk.png" style="width: 90%;height: auto"/>
  </div>

</div>
