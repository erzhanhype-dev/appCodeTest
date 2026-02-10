<h2>{{ t._("main-page") }}</h2>

<div class="container">
    <div class="row">
        <canvas id="count_line" style="width: 100%; height: 400px;" data-days='{{ days_line }}' data-count-line='{{ count_line }}' data-g-line='{{ g_line }}' data-d-line='{{ d_line }}' data-a-line='{{ a_line }}'></canvas>
    </div>
    <div class="row mt-5">
        <div class="col-4">
            <canvas id="car_goods_pie" style="width: 100%; height: 400px;" data-car-goods='{{ car_goods_pie }}'></canvas>
        </div>
        <div class="col-4">
            <canvas id="car_goods_pie_year" style="width: 100%; height: 400px;" data-car-goods='{{ car_goods_pie_year }}'></canvas>
        </div>
        <div class="col-4">
            <canvas id="car_goods_pie_all" style="width: 100%; height: 400px;" data-car-goods='{{ car_goods_pie_all }}'></canvas>
        </div>
    </div>
    <div class="row mt-5 mb-5">
        <canvas id="car_goods_bar" style="width: 100%; height: 400px;" data-days='{{ days_line }}' data-car='{{ car_line }}' data-goods='{{ goods_line }}'></canvas>
    </div>
</div>