<!-- Форма -->
<div class="card mb-4">
    <div class="card-body">
        <h1 class="card-title h2 mb-4">SQL Export (SELECT → CSV)</h1>

        <form method="post" action="{{ url('sqlexport/enqueue') }}">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="form-group">
                <label for="sql" class="font-weight-bold">SQL SELECT запрос:</label>
                <br>
                <span>Выполнение sql запроса без лимита на сервере для больших запросов.<br>Ограничение на время выполнения запроса максимум 24 часа</span>
                <br>
                <span>Файл храниться на сервере до 00:00</span>
                <textarea
                        name="sql"
                        id="sql"
                        class="form-control sql-textarea"
                        rows="8"
                        placeholder="Введите ваш SQL SELECT запрос здесь..."
                        required></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane mr-2"></i>Поставить в очередь
            </button>
        </form>
    </div>
</div>

<hr class="my-4">

<!-- Таблица задач -->
<div class="card">
    <div class="card-body">
        <h2 class="card-title h3 mb-4">Последние задачи</h2>

        <div class="table-responsive">
            <table class="table table-hover table-bordered" >
                <thead class="thead-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Создано</th>
                    <th scope="col">Завершено</th>
                    <th scope="col">Файл</th>
                </tr>
                </thead>
                <tbody id="task-table-body">
                {% for task in tasks %}
                    <tr>
                        <td><strong>#{{ task.id }}</strong></td>
                        <td>
                            {% set statusClass = 'badge-secondary' %}
                            {% if task.status == 'done' %}
                                {% set statusClass = 'badge-success' %}
                            {% elseif task.status == 'processing' %}
                                {% set statusClass = 'badge-warning' %}
                            {% elseif task.status == 'failed' %}
                                {% set statusClass = 'badge-danger' %}
                            {% elseif task.status == 'pending' %}
                                {% set statusClass = 'badge-info' %}
                            {% endif %}
                            <span class="badge {{ statusClass }} status-badge">{{ task.status }}</span>
                            <br>
                            {% if task.status == 'failed' %}
                                <span class="text-info">{{ task.error }}</span>
                            {% endif %}
                        </td>
                        <td>
                            {% if task.created is defined %}
                                <span class="text-muted">{{ date('Y-m-d H:i:s', task.created) }}</span>
                            {% else %}
                                <span class="text-muted">-</span>
                            {% endif %}
                        </td>
                        <td>
                            {% if task.completed is defined and task.completed %}
                                <span class="text-muted">{{ date('Y-m-d H:i:s', task.completed) }}</span>
                            {% else %}
                                <span class="text-muted">-</span>
                            {% endif %}
                        </td>
                        <td>
                            {% if task.status == 'done' and task.filename %}
                                <a href="{{ url('sqlexport/download/' ~ task.id) }}"
                                   class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-download mr-1"></i>Скачать
                                </a>
                            {% else %}
                                <span class="text-muted">-</span>
                            {% endif %}
                        </td>
                    </tr>
                {% else %}
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p class="mb-0">Задач пока нет</p>
                            </div>
                        </td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
        </div>
        {% if page is defined %}
            {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
        {% endif %}
    </div>
</div>
