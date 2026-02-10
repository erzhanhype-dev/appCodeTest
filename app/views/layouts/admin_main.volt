{% include "partials/header.volt" %}
{% include "partials/menu.volt" %}
<div id="page-content-wrapper">
  <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <button class="btn btn-primary btn-dark" id="menu-toggle"><i data-feather="menu"></i></button>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    {% include "partials/sub_menu.volt" %}
  </nav>

  <div class="container-fluid">
   {{ content() }}
  </div>  
</div>
{% include "partials/footer.volt" %}