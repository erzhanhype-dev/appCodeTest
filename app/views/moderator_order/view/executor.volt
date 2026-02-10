<input type="hidden" value="{{ data.executor.id }}" id="order_executor">
<input type="hidden" value="{{ data.executor.name }}" id="order_watcher">
<input type="hidden" value="{{ auth.fio }}" id="order_watcher_name">

<div class="card" style="height:100%">
    <div class="card-header bg-dark text-light">
        {{ t._("Исполнитель") }}
    </div>
    <div class="card-body">
        <div class="order-status view">
            <div class="order-executor">
                      <span class="line order-executor-username">
                        {% if data.executor.name == NULL %}
                            Не назначен
                        {% else %}
                            <b id="executor_username">{{ data.executor.name }}</b>
                        {% endif %}
                      </span>
                <div class="list" id="order-{{ data.id }}">
                    {% if watchers is defined and data.watchers|length > 0 %}
                        {% for watcher in data.watchers %}
                            <div class="username"
                                 id="{{ watcher.socket_id }}">{{ watcher.username }}</div>
                        {% endfor %}
                    {% endif %}
                </div>
            </div>

            <div class="actions mb-3">
                <form action="/moderator_order/status/{{ data.id }}" method="POST">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <div class="status_form_submit" id="watcher_status_form_{{ auth.id }}"
                         style="display: flex;flex-direction: column;">
                        {% if (data.permissions.stop_execute_order) %}
                            <input type='hidden' value="0" name="status">
                            <button class="btn btn-danger"
                                    style="padding: 4px;font-size: 12px; margin-bottom:6px;">
                                Прекратить исполнение
                            </button>
                        {% else %}
                            {% if (data.permissions.start_execute_order) %}
                                <input type='hidden' value="1" name="status">
                                <button class="btn btn-primary">Взять на исполнение</button>
                            {% endif %}
                        {% endif %}
                    </div>
                </form>
            </div>

            {% if (data.executor.status_code == 'ORDER_STATUS_OPENED') %}
                <br>
                <div class="order_status_access"> Заявка будет активна для действий после определения
                    исполнителя
                </div>
            {% endif %}

            {% if (data.executor.status_code == 'ORDER_STATUS_IN_PROGRESS' and data.executor.id != auth.id) %}
                <br>
                <div class="order_status_in_progress">Заявка активна для действия только исполнителю</div>
            {% endif %}
        </div>

        <div class="chat-watchers">
            <div class="chat-area">
                <div class="chat-list" id="chat-list-{{ data.id }}">
                    {% if data.chats|length > 0 %}
                        {% for chat in data.chats %}
                            <div class="chat{% if chat.user_id == auth.id %} current{% endif %}">
                                <div class="chat_user">{{ chat.username }}</div>
                                <div class="chat_message">{{ chat.message }}</div>
                                <div class="chat_datetime">{{ chat.datetime }}</div>
                            </div>
                        {% endfor %}
                    {% endif %}
                </div>
            </div>
            <div class="form-group actions" style="margin-bottom: 0">
                {% if auth is defined and auth.isEmployee() %}
                    <input class="form-control" type="text" max="200" id="chat_message" placeholder="Сообщения">
                    <button class="btn btn-primary" id="send_chat_message">Отправить</button>
                {% endif %}
            </div>
        </div>

    </div>
</div>

