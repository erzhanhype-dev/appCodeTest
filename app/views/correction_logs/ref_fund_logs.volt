<!-- заголовок -->
<h2>{{ t._("История изменения лимитов(Финансирование)") }}</h2>
<!-- /заголовок -->
<!--Логи -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
        {{ t._("Логи") }}
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{t._("№")}}</th>
                <th>{{t._("Пользователь")}}</th>
                <th>{{t._("Действия")}}</th>
                <th>{{t._("Время")}}</th>
                <th>{{t._("До")}}</th>
                <th>{{t._("После")}}</th>
            </tr>
            </thead>
            <tbody>
            {% if page.items|length %}
                {% for log in page.items %}
                    <tr>
                        <td>{{ log.id }}</td>
                        <td>
                            {{ (log.user_type_id == 1) ? log.fio : log.org_name }}
                            (<b>{{ log.idnum }}</b>)
                        </td>
                        <td>{{ t._(log.action) | upper }}</td>
                        <td>{{ date("d.m.Y H:i:s", log.dt) }}</td>
                        <td>
                            <?php
                                $arr_b = json_decode($log->meta_before, true);
                                if(is_array($arr_b)){
                                    foreach($arr_b as $a){
                                        foreach($a as $key => $value){
                                         echo $key . " : " . $value . "<br />";
                                        }
                                    }
                                }else{
                                    echo $log->meta_before;
                                }
                            ?>
                        </td>
                        <td>
                            <?php
                                $arr = json_decode($log->meta_after, true);
                                if(is_array($arr)){
                                    foreach($arr as $a){
                                        foreach($a as $key => $value){
                                            echo $key . " : " . $value . "<br />";
                                        }
                                    }
                                }else{
                                    echo $log->meta_after;
                                }
                            ?>
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
