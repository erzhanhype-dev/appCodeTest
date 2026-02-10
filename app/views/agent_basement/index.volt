<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("basement-list") }}</h3></div>

{{ flash.output() }}

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("basement-list") }}</div>
      <div class="panel-body">
        <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>{{ t._("user_id") }}</th>
              <th>{{ t._("basement") }}</th>
              <th>{{ t._("wt") }}</th>
              <th>{{ t._("operations") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if page.items is defined and page.items|length %}
            {% for basement in page.items %}
            <tr>
              <td width="10%">{{ basement.id }}</td>
              <td>{{ basement.user_id }}</td>
              <td>{{ basement.title }}</td>
              <td>{{ basement.wt }}</td>
              <td width="10%">{{ link_to("agent_basement/edit/"~basement.id, '<button class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></button>') }} {{ link_to("agent_basement/delete/"~basement.id, '<button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>') }}</td>
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
{% if page is defined and page.current is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}
