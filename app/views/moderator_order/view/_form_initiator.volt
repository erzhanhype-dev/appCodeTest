<form action="/moderator_order/setInitiator/{{ data['id'] }}" method="POST">
    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
    <div class="modal-body">
        <div class="form-group">
            <label for="modalSelect">Выберите значение:</label>
            <select name="initiator" id="initiator" class="form-control">
                {% if data['initiators'] is defined %}
                    {% for item in data['initiators'] %}
                        {% if data['initiator'] is defined %}
                            <option value="{{ item['id'] }}"{% if item['id'] == data['initiator']['id'] %} selected{% endif %}>{{ t._(item['name']) }}</option>
                        {% else %}
                            <option value="{{ item['id'] }}">{{ t._(item['name']) }}</option>
                        {% endif %}
                    {% endfor %}
                {% endif %}
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>