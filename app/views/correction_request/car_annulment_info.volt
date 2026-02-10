<!-- содержимое заявки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
    {{ t._("Содержимое заявки") }}
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
             <ol>
               {% for c in cars %}
                <li> 
                    ID: <b>{{ c.id }}</b> || 
                    VIN: <b>{{ c.vin }}</b> || 
                    Дата импорта: <b><?php echo date("d-m-Y", convertTimeZone($c->date_import)); ?> </b> ||
                    Год производства: <b>{{ c.year }} </b> || 
                    Объем, см3 / Масса, кг <b>{{ c.volume }} </b> || 
                    Размер платежа: <b><del style="color:orange;">{{ c.cost }} тг</del></b> => <b style="color:green;">0.00 тг</b> ||
                    Седельный тягач?: <b><?php echo ($c->ref_st_type == 1) ? 'Да' : 'Нет'; ?></b> || 
                    <b style="color:red">Аннулировано</b>
                </li>
               {% endfor %} 
              </ol>
            </div>
        </div>
    </div>
</div>