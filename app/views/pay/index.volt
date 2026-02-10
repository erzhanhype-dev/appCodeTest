<div class="page-title"><a href="#" class="backlink"><i class="icon-custom-left"></i></a> <h3>{{ t._("your-payments-list") }}</h3></div>

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("your-payments") }}</div>
      <div class="panel-body">
        <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr class="">
              <th>{{ t._("num-symbol") }}</th>
              <th>{{ t._("pay-date") }}</th>
              <th>{{ t._("summ") }}</th>
              <th>{{ t._("payment-system") }}</th>
              <th>{{ t._("payment-status") }}</th>
              <th>{{ t._("operations") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if page.items is defined %}
            {% for item in page.items %}
            <tr class="">
              <td class="v-align-middle">{{ item.t_id }}</td>
              <td class="v-align-middle">{{ date("d.m.Y H:i", item.t_date) }}</td>
              <td class="v-align-middle"><?php echo number_format($item->t_amount, 2, ",", " "); ?></td>
              <td class="v-align-middle">
                {% if item.t_source == constant("VISA") %}VISA/MasterCard{% endif %}
                {% if item.t_source == constant("INVOICE") %}Через банк{% endif %}
              </td>
              <td class="v-align-middle">
                {% if item.t_status == 'NOT_PAID' %}<span class="label label-default">{{ t._("not-paid-msg") }}</span>{% endif %}
                {% if item.t_status == 'PAID' %}<span class="label label-success">{{ t._("paid-msg") }}</span>{% endif %}
              </td>
              <td class="v-align-middle">
                {{ link_to("order/view/"~item.t_profile_id, '<button class="btn btn-mini btn-success btn-xs" title="'~t._("view-application")~'"><i class="fa fa-reorder"></i></button>') }}
                {% if item.t_approve == 'APPROVE' %}
                    {{ link_to("pay/invoice/"~item.t_id, '<button class="btn btn-mini btn-primary btn-xs" title="'~t._("re-pay-bank")~'"><i class="fa fa-bank"></i></button>') }}
                {% endif %}
              </td>
            </tr>
            {% endfor %}
            {% endif %}
          </tbody>
        </table>
      </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-sm-12">
    <div class="grid simple">
      <div class="grid-body">
        <div class="col-sm-1">
          <h4>{{ page.current~"/"~page.total_pages }}</h4>
        </div>
        <div class="col-sm-9 text-center">
          {{ link_to("pay/index/", '<button class="btn btn-default">'~t._("first")~'</button>') }}
          {{ link_to("pay/index/?page="~page.before, '<button class="btn btn-default">←</button>') }}
          {{ link_to("pay/index/?page="~page.next, '<button class="btn btn-default">→</button>') }}
          {{ link_to("pay/index/?page="~page.last, '<button class="btn btn-default">'~t._("last")~'</button>') }}
        </div>
      </div>
    </div>
  </div>
</div>
