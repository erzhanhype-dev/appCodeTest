<div class="row">
    <div class="col-6">
        <div class="card mt-3">
            <div class="card-header bg-dark text-light">
                {{ t._("kap-check") }}
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="radio"
                           class="kap_type_input"
                           checked="true"
                           id="vin"
                           name="type" value="VIN">
                    <label for="vin">VIN</label> <br>
                    <input type="radio"
                           id="grnz"
                           class="kap_type_input"
                           name="type" value="GRNZ">
                    <label for="grnz">GRNZ</label>

                    <div class="form-group" id="vininput">
                        <label for="vininput">VIN <span style="color:red">*</span></label>
                        {% if k_log is defined and k_log is not null %}
                            <input type="text" class="form-control" name="vininput" aria-describedby="vininput"
                                   placeholder="WXT0000000000" value="{{ k_log.vin }}">
                        {% else %}
                            <input type="text" class="form-control" name="vininput" aria-describedby="vininput"
                                   placeholder="WXT0000000000">
                        {% endif %}
                        <small class="form-text text-muted">Введите VIN и нажмите кнопку Запросить</small>
                    </div>

                    <div class="form-group" style="display:none;" id="grnzinput">
                        <label for="grnzinput">GRNZ <span style="color:red">*</span></label>
                        {% if k_log is defined and k_log is not null %}
                            <input type="text" class="form-control" name="grnzinput" aria-describedby="grnzinput"
                                   placeholder="000WXT00" value="{{ k_log.vin }}">
                        {% else %}
                            <input type="text" class="form-control" name="grnzinput" aria-describedby="grnzinput"
                                   placeholder="000WXT00">
                        {% endif %}
                        <small class="form-text text-muted">Введите GRNZ и нажмите кнопку Запросить</small>
                    </div>

                    <div class="form-group">
                        <label for="base_on">На основании <span style="color:red">*</span></label>
                        {% if k_log is defined and k_log is not null %}
                            <input type="text" class="form-control" id="base_on" name="base_on"
                                   aria-describedby="base_on" required placeholder="" value="{{ k_log.base_on }}">
                        {% else %}
                            <input type="text" class="form-control" id="base_on" name="base_on"
                                   aria-describedby="base_on" required placeholder="">
                        {% endif %}
                        <small id="base_on" class="form-text text-muted">Введите на основании чего вы делаете
                            запрос</small>
                    </div>

                    <button type="submit" formaction="/kap_request/search" class="btn btn-secondary">Запросить(Старый)
                    </button>
                    <a href="/kap_request/resetPage" class="btn btn-warning ml-3">
                        <i data-feather="refresh-cw" width="16" height="16"></i>
                        Очистить все
                    </a>
                </form>
            </div>
        </div>
    </div>
    {% if k_log is defined and k_log is not null %}
        <div class="col-6">
            <div class="card mt-3">
                <div class="card-header bg-info text-light">
                    Информация о запросе
                </div>
                <div class="card-body">

                    <p>
                        ФИО:
                        <b>{{ user.last_name }} {{ user.first_name }} {{ user.parent_name }} </b>
                    </p>

                    <p>
                        На основании:
                        <b>{{ k_log.base_on }} </b>
                    </p>

                    <p>
                        VIN:
                        <b>{{ k_log.vin }} </b>
                    </p>

                    <p>
                        Актуальный статус:
                        {% if k_log.state == 'Снят с учета' %}
                            <b class="btn btn-outline-success">{{ k_log.state }}</b>
                        {% elseif k_log.state == 'На регистрации' %}
                            <b class="btn btn-outline-warning">{{ k_log.state }}</b>
                        {% elseif k_log.state == 'Требуется проверка вручную' %}
                            <b class="btn btn-outline-danger">Требуется проверка вручную</b>
                        {% else %}
                            <?php
                  $string_s = '';
                  switch($k_log->state) {
                                                               case "P":
                                                               $string_s = ' карточка распечатана ('. $k_log->state .') ';
                                                               break;
                                                               case "S":
                                                               $string_s = ' Карточка снята с учета ('. $k_log->state .') ';
                                                               break;
                                                               case "B":
                                                               $string_s = ' распечатана, временный ввоз ('. $k_log->state .') ';
                                                               break;
                                                               case "U":
                                                               $string_s = ' карточка утверждена ('. $k_log->state .') ';
                                                               break;
                                                               case "N":
                                                               $string_s = ' Новая карточка ('. $k_log->state .') ';
                                                               break;
                                                               case "V":
                                                               $string_s = ' Карточка на временном учете ('. $k_log->state .') ';
                                                               break;
                                                               default:
                                                               $string_s = $k_log->state;
                                                               }
                                                               ?>
                            <b class="btn btn-outline-danger">{{ string_s }}</b>
                        {% endif %}
                    </p>

                    <p>
                        Дата запроса:
                        <b>{{ request_date }}</b>
                    </p>

                </div>

                <div class="card-footer">
                    {% if k_log.state == 'Снят с учета' or k_log.state == 'На регистрации' or k_log.state == 'В АИС КАП записей не найдено!' %}
                        <a href="/kap_request/download_old/{{ k_log.id }}" type="submit"
                           class="btn btn-primary btn-block">Скачать справку</a>
                    {% endif %}
                </div>
            </div>
        </div>
    {% else %}
    {% endif %}
</div>

{% if k_log is defined and k_log is not null %}
    <div class="row">

        {% if xml.record|length > 0 %}
            <div class="col">
                <div class="card mt-3">

                    <a class="card-header bg-info text-light" data-toggle="collapse" href="#kap-check" role="button"
                       aria-expanded="false" aria-controls="kap-check">
                        Показать весь список статусов
                    </a>

                    <div class="collapse" id="kap-check">
                        <div class="table-responsive">

                            <table class="table table-striped table-bordered table-hover table-sm">
                                <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    {% for i in xml.record[0].field %}
                                        <?php $b = $i->attributes(); ?>

                                        <?php
                if( isset(KAP_INTEG_DATA_TYPE[strval($b[0])]) ){
                  echo ('<th>'.KAP_INTEG_DATA_TYPE[strval($b)].' </th> ');
                                        }
                                        ?>

                                    {% endfor %}
                                </tr>
                                </thead>

                                {% set key = 0 %}
                                {% for record in xml.record %}
                                    {% set key = key + 1 %}

                                    {% set string = '<td>'~ key ~'</td>' %}
                                    {% set urlstring = '' %}

                                    {% for i in record.field %}

                                        {% for b in i.attributes() %}
                                            <?php
                      if( isset(KAP_INTEG_DATA_TYPE[strval($b)]) ){
                        if($b == "STATUS"){
                          switch($i) {
                            case "P":
                              $string .= ' <td>карточка распечатана ('. $i .')</td> ';
                                                                                                                                  break;
                                                                                                                                  case "S":
                                                                                                                                  $string .= '
                                            <td>Карточка снята с учета ('. $i .')</td> ';
                                                                                       break;
                                                                                       case "B":
                                                                                       $string .= '
                                            <td>распечатана, временный ввоз ('. $i .')</td> ';
                                                                                            break;
                                                                                            case "U":
                                                                                            $string .= '
                                            <td>карточка утверждена ('. $i .')</td> ';
                                                                                    break;
                                                                                    case "N":
                                                                                    $string .= '
                                            <td>Новая карточка ('. $i .')</td> ';
                                                                               break;
                                                                               case "V":
                                                                               $string .= '
                                            <td>Карточка на временном учете ('. $i .')</td> ';
                                                                                            break;
                                                                                            }
                                                                                            } else {
                                                                                            $string .= '
                                            <td>'.$i.'</td> ';
                                            }
                                            }
                                            ?>
                                        {% endfor %}

                                    {% endfor %}

                                    <tr>
                                        {{ string }}
                                    </tr>


                                {% endfor %}
                            </table>

                        </div>
                    </div>

                </div>
            </div>
        {% endif %}
    </div>
{% else %}
{% endif %}
