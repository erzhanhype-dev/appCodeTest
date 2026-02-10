{% set isCar   = (m == 'CAR') %}
{% set backMod = isCar ? 'car' : 'trac' %}

<div class="row">
    <div class="col-8">
        <form action="/car/update/{{ car_id }}" method="post" id="frm_car" autocomplete="off">

            <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

            <div class="card mt-3">
                <div class="card-header bg-dark text-light">{{ t._('add-car') }}</div>
                <div class="card-body">
                    {% include 'car/form.volt' %}

                    <button type="submit" class="btn btn-success" id="car_button_submit">
                        <i data-feather="check-circle" width="14" height="14"></i>
                        {{ t._('save-car') }}
                    </button>

                </div>
            </div>
        </form>
    </div>

    {% if isCar %}
        <div class="col-4">
            <div class="mt-3">
                <div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">* Примечание: </h4>
                    <p> Размер утилизационного платежа рассчитывается на момент подписания заявки.</p>
                    <p>Для категорий M1, MG1 - легковые транспортные средства - необходимо указывать объем двигателя в см3.</p>
                    <p>Для категорий N1, N2, N3, N1G, N2G, N3G седельные тягачи - грузовые транспортные средства - необходимо указывать технически допустимую максимальную массу (полную массу) в кг.</p>
                    <p>Для категории M2, M2G - автобусы - необходимо указывать объем двигателя в см3.</p>
                    <p class="mb-0">Для категории L6, L7:
                        <br>легковые транспортные средства - необходимо указывать объем двигателя в см3;
                        <br>грузовые транспортные средства - необходимо указывать технически допустимую максимальную сумму (полную массу) в кг.
                    </p>
                </div>
            </div>
        </div>
    {% endif %}
</div>
