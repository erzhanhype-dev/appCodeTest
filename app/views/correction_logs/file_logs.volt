<!-- заголовок -->
<h2>{{ t._("История файлов") }}</h2>
<!-- /заголовок -->

<!-- форма поиска -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Поиск") }}
    </div>
    <div class="card-body">
        <form method="POST" action="/correction_logs/file_logs">
            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="row">
                <div class="col-4">
                    <label><b>Поиск по Номер заявки:</b></label>
                    <input name="pid" type="text" class="form-control" placeholder="Номер заявки"
                           value="<?php echo isset($_SESSION['file_log_search_pid']) ? $_SESSION['file_log_search_pid']: '';?>">
                </div>
                <div class="col-4">
                    <label><b>Поиск по файл ID:</b></label>
                    <input name="fid" type="text" class="form-control" placeholder="Файл ID"
                           value="<?php echo isset($_SESSION['file_log_search_fid']) ? $_SESSION['file_log_search_fid']: '';?>">
                </div>
                <div class="col-auto mt-4">
                    <button type="submit" name="car_search" class="btn btn-primary">{{ t._("search") }}</button>
                    <button type="submit" name="reset" value="all" class="btn btn-warning">Сбросить</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- /форма поиска -->

<!--Логи -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Логи") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ t._("Номер заявки") }}</th>
                <th>{{ t._("Пользователь") }}</th>
                <th>{{ t._("Действия") }}</th>
                <th>{{ t._("Время") }}</th>
                <th>{{ t._("До") }}</th>
                <th>{{ t._("После") }}</th>
                <th>{{ t._("Файл") }}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length %}
                {% for log in page.items %}
                    <tr>
                        <td>
                            <a href="/moderator_order/view/{{ log.profile_id }}">
                                {{ log.profile_id }}
                            </a>
                        </td>
                        <td>
                            {{ (log.user_type_id == 1) ? log.fio : log.org_name }}
                            (<b>{{ log.idnum }}</b>)
                        </td>
                        <td>{{ t._(log.action) | upper }}</td>
                        <td>{{ date("d.m.Y H:i:s", log.dt) }}</td>
                        <td>
                            <?php
                    $arr = json_decode($log->meta_before, true);
                            foreach($arr as $a){
                            foreach($a as $key => $value){
                            echo $key . " : " . $value . "<br/>";
                            }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                      $arr = json_decode($log->meta_after, true);
                            if(is_array($arr)){
                            foreach($arr as $a){
                            foreach($a as $key => $value){
                            echo $key . " : " . $value . "<br/>";
                            }
                            }
                            }else{
                            echo $log->meta_after;
                            }
                            ?>
                        </td>
                        <td>
                            <a href="/order/viewdoc/{{ log.file_id }}" class="preview">{{ t._(log.type) }}</a>
                        </td>
                    </tr>
                {% endfor %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
<!-- /пользователи -->

{% if page is defined %}
    {{ partial('components/paginator', ['page': page, 'window': 2, 'showFirstLast': true]) }}
{% endif %}


