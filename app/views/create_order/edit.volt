<h3>{{ t._("new-application") }}</h3>

{% if (tr.approve == 'NEUTRAL' or tr.approve == 'DECLINED') and auth.id == profile.moderator_id %}
    <form action="/create_order/edit/{{ profile.id }}" method="post" id="frm_order" autocomplete="off">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

    <div class="row">
        <div class="col">
            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._("edit-application-assembly") }}</div>
                <div class="card-body">
                    <div class="form-group" id="order">
                        <label class="form-label">{{ t._("order-type") }}</label>
                        <div class="controls">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <label id="bavto"
                                       class="btn btn-wide btn-secondary{% if profile.type == 'CAR' %} active{% endif %}">
                                    <input type="radio" name="order_type"
                                           value="CAR"{% if profile.type == 'CAR' %} checked="checked"{% endif %}> <i
                                            data-feather="arrow-right"></i> Автотранспорт
                                </label>
                                <label id="bcomp"
                                       class="btn btn-wide btn-secondary{% if profile.type == 'GOODS' %} active{% endif %}">
                                    <input type="radio" name="order_type"
                                           value="GOODS"{% if profile.type == 'GOODS' %} checked="checked"{% endif %}>
                                    <i
                                            data-feather="arrow-right"></i> Автокомпоненты
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="order">
                        <label class="form-label">{{ t._("agent-status") }}</label>
                        <div class="controls">
                            <select name="agent_status" class="form-control">
                                <option value="IMPORTER"{% if profile.agent_status == 'IMPORTER' %} selected="selected"{% endif %}>
                                    Импортер
                                </option>
                                <option value="VENDOR"{% if profile.agent_status == 'VENDOR' %} selected="selected"{% endif %}>
                                    Производитель
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ t._("order_initiator") }}</label>
                        <div class="controls">
                            <select name="initiator_id" class="form-control">
                                {% for i, item in initiators %}
                                    <option value="{{ item.id }}" {% if profile.initiator_id == item.id %} selected="selected"{% endif %}>{{ item.name }}</option>
                                {% endfor %}
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="order">
                        <label class="form-label">{{ t._("comment") }}</label>
                        <div class="controls">
                            <input type="text" name="comment" class="form-control" value="{{ profile.comment }}"
                                   required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success" name="button">{{ t._("save-application") }}</button>
                </div>
            </div>
        </div>
    </div>
    </form>
{% else %}
    {% if auth is defined and (auth.isAdminSoft() or auth.isSuperModerator()) %}
        <div class="row">
            <div class="col">
                <div class="card mt-3">
                    <div class="card-header bg-dark text-light">{{ t._("edit-application-assembly") }}</div>
                    <div class="card-body">
                        <form action="/moderator_order/setInitiator/{{ profile.id }}" method="POST" autocomplete="off">
                            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label class="form-label">{{ t._("order_initiator") }}</label>
                                    <div class="controls">
                                        <select name="initiator" class="form-control">
                                            {% for i, item in initiators %}
                                                <option value="{{ item.id }}" {% if profile.initiator_id == item.id %} selected="selected"{% endif %}>{{ item.name }}</option>
                                            {% endfor %}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    {% endif %}
{% endif %}