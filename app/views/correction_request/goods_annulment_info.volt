<!-- содержимое заявки -->
<div class="card mt-3">
    <div class="card-header bg-dark text-light">
    {{ t._("Содержимое заявки") }}
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <ol>
                    {% for g in goods %}
                        <?php if($g->status == "DELETED") continue; ?>
                        <li> ID: <b>{{ g.id }}</b> || Дата импорта: <b><?php echo date("d-m-Y", convertTimeZone($g->date_import)); ?> </b> || Вес: <b>{{ g.weight }} кг</b> || Размер платежа: <b>{{ g.amount }} тг</b> || Счет фактура или ГТД: <b>{{ g.basis }}</b></li>
                    {% endfor %} 
               </ol>
            </div>
        </div>
    </div>
</div>