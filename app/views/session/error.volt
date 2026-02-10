<!-- форма входа -->
<div class="d-flex justify-content-center mt-5">
</div>

<div class="d-flex justify-content-center">
    <div class="card mt-2" style="max-width: 500px;">
        <div class="card-header">
            Вход в систему
        </div>
        <div class="card-body">
            <!-- авторизация -->
            <div class="row">
                <div class="col">
                    <p class="text-center">{{ flash.output() }}</p>
                    <p class="text-center"><a href="/session" class="btn btn-danger">Попытаться еще раз</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
