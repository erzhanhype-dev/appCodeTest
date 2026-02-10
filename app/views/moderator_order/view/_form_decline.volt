<form method="POST">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

    <textarea name="msg" id="msgModal" cols="30" rows="3" class="form-control" required></textarea>

    <input type="hidden" value="{{ data['id'] }}" name="order_id">

    <div class="btn-group">
        <button type="submit"
                formaction="{{ url('moderator_order/msg') }}"
                class="btn btn-sm btn-danger ml-2 mt-2">
            Отклонить
        </button>

        <div class="dropdown ml-1 mt-2">
            <button class="btn btn-sm btn-secondary dropdown-toggle"
                    type="button"
                    id="msgList0"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false">
                Шаблоны сообщений 1
            </button>
            <div class="dropdown-menu" aria-labelledby="msgList0">
                {% set __len = data['decline_reasons']|length %}
                {% for i in 0..15 %}
                    {% if i < __len %}
                        {% set __item = data['decline_reasons'][i] %}
                        <a class="dropdown-item msg-item"
                           data-msg="{{ __item|e }}">
                            {{ __item|slice(0, 40) }}{% if (__item|length) > 40 %}…{% endif %}
                        </a>
                    {% endif %}
                {% endfor %}
            </div>
        </div>

        <div class="dropdown ml-1 mt-2">
            <button class="btn btn-sm btn-secondary dropdown-toggle"
                    type="button"
                    id="msgList1"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false">
                Шаблоны сообщений 2
            </button>
            <div class="dropdown-menu" aria-labelledby="msgList1">
                {% for i in 16..31 %}
                    {% if i < __len %}
                        {% set __item = data['decline_reasons'][i] %}
                        <a class="dropdown-item msg-item"
                           data-msg="{{ __item|e }}">
                            {{ __item|slice(0, 40) }}{% if (__item|length) > 40 %}…{% endif %}
                        </a>
                    {% endif %}
                {% endfor %}
            </div>
        </div>

        {% if data['type'] == 'CAR' %}
            <button type="submit"
                    formaction="{{ url('moderator_order/clear_cars') }}"
                    class="btn btn-sm btn-danger ml-2 mt-2">
                <i data-feather="trash-2" width="20" height="14"></i>
                Отклонение с очисткой
            </button>
        {% endif %}
    </div>
</form>
