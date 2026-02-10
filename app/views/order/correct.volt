<div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>{{ t._("new-application") }}</h3> </div>

{{ form("order/correct/"~profile.id, "method": "post", "id": "frm_order") }}
<input type="hidden" name="csrfToken" value="{{ csrfToken }}">
<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default panel-primary">
      <div class="panel-heading">{{ t._("edit-application-assembly") }}</div>
      <div class="panel-body">
        <div class="form-group" id="order">
          <label class="form-label">{{ t._("agent-type") }}</label>
          <span class="help">{{ t._("agent-type-help") }}</span>
          <div class="controls">
            <input type="radio" class="controls" name="agent_type" id="agent_type_person" value="{{ constant('PERSON') }}"{% if profile.agent_type == constant('PERSON') %} checked="checked"{% endif %}> {{ t._('person') }}
            <input type="radio" class="controls" name="agent_type" id="agent_type_company" value="{{ constant('COMPANY') }}"{% if profile.agent_type == constant('COMPANY') %} checked="checked"{% endif %}> {{ t._('company') }}
          </div>
        </div>
        {% if can_set_iban %}
        <div class="form-group{% if profile.agent_type == constant('PERSON') %} v-company{% endif %} v-anch" id="order">
          <label class="form-label">{{ t._("iban") }}</label>
          <span class="help">{{ t._("example-iban-number") }}</span>
          <div class="controls">
            <input type="text" name="agent_iban" maxlength="20" id="agent_iban" class="form-control" value="{{ profile.agent_iban }}">
          </div>
        </div>
        <div class="form-group{% if profile.agent_type == constant('PERSON') %} v-company{% endif %} v-anch" id="order">
          <label class="form-label">{{ t._("bank") }}</label>
          <span class="help">{{ t._("agent-bank-help") }}</span>
          <div class="controls">
            <select name="agent_bank">
              {% for bank in banks %}
                <option value="{{ bank.id }}"{% if profile.agent_bank == bank.id %}selected="selected"{% endif %}>{{ bank.name }}</option>
              {% endfor %}
            </select>
          </div>
        </div>
        {% endif %}
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-xs-12 text-center">
    <button type="submit" class="btn btn-success" name="button">{{ t._("save-application") }}</button>
  </div>
</div>
</form>
