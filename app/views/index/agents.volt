<div class="container-fluid" id="agents">
  <div class="container">
    <div class="row">
        <h3>{{ t._("our-agents") }}</h3>
		<p>{{ t._("all-serivces-are-free") }}</p>
        <table>
          <thead>
            <tr>
              <th>{{ t._("index-agent-city") }}</th>
              <th>{{ t._("index-agent-name") }}</th>
              <th>{{ t._("index-agent-phone") }}</th>
              <th>{{ t._("index-agent-address") }}</th>
            </tr>
          </thead>
          <tbody>
            {% if agents is defined %}
            {% for agent in agents %}
            <tr>
              <td>{{ agent.city }}</td>
              <td>{{ agent.name }}</td>
              <td>{{ agent.phone }}</td>
              <td>{{ agent.address }}</td>
            </tr>
            {% endfor %}
            {% endif %}
          </tbody>
        </table>
    </div>
  </div>
</div>
