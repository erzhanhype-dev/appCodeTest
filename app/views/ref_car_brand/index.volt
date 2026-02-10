<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("cars-directory") }}</h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("cars-directory") }}</div>
      <div class="panel-body">
        <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>{{ t._("brand") }}</th>
              <th>{{ t._("operations") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if page.items is defined %}
            {% for ref_car_brand in page.items %}
            <tr>
              <td width="10%">{{ ref_car_brand.id }}</td>
              <td>{{ ref_car_brand.name }}</td>
              <td width="10%">{{ link_to("ref_car_brand/edit/"~ref_car_brand.id, '<button class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></button>') }} {{ link_to("ref_car_brand/delete/"~ref_car_brand.id, '<button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>') }}</td>
            </tr>
            {% endfor %}
            {% endif %}
          </tbody>
        </table>
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
          {{ link_to("ref_car_brand/index/", '<button class="btn btn-default">t._("first")</button>') }}
          {{ link_to("ref_car_brand/index/?page="~page.before, '<button class="btn btn-default">←</button>') }}
          {{ link_to("ref_car_brand/index/?page="~page.next, '<button class="btn btn-default">→</button>') }}
          {{ link_to("ref_car_brand/index/?page="~page.last, '<button class="btn btn-default">t._("last")</button>') }}
        </div>
        <div class="col-sm-2 text-right">
          {{ link_to("ref_car_brand/new", '<button class="btn btn-xs btn-danger"><i class="fa fa-file-o"></i></button>') }}
        </div>
      </div>
    </div>
  </div>
</div>
