{% set __rn = router.getControllerName() %}
<?php if(isset($_SESSION['auth'])){?>
<?php $auth = User::getUserBySession();?>
<?php }?>

{% if auth is defined and (auth.isAdmin() or auth.isAdminSoft() or auth.isAdminSec()) %}
    {% if __rn == 'users' %}
    <div class="dropdown float-right">
        <button class="btn btn-warning dropdown-toggle" type="button" id="ddUsers" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
            {{ t._("Расширенные настройки") }}
        </button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ddUsers">
            <a class="dropdown-item" href="/users/new">{{ t._("Создать пользователя") }}</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/report_admin">{{ t._("Выгрузка по пользователям") }}</a>
        </div>
    </div>
    {% endif %}
    <?php if(substr($__rn, 0, 4) == 'ref_'): ?>
    <div class="dropdown float-right">
        <button class="btn btn-warning dropdown-toggle" type="button" id="ddExt" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
            {{ t._("Расширенные настройки") }}
        </button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ddExt">
            <a class="dropdown-item" href="/ref_bank">{{ t._("Справочник банков") }}</a>
            <a class="dropdown-item" href="/ref_car_type">{{ t._("Типы машин") }}</a>
            <a class="dropdown-item" href="/ref_car_value">{{ t._("Справочник коэффициентов") }}</a>
            <a class="dropdown-item" href="/ref_country">{{ t._("Справочник стран") }}</a>
            <a class="dropdown-item" href="/ref_kbe">{{ t._("Справочник КБе") }}</a>
            <a class="dropdown-item" href="/ref_tn_code">{{ t._("Реестр кодов ТН ВЭД") }}</a>
            <a class="dropdown-item" href="/ref_contract">{{ t._("Справочник договоров") }}</a>
            <a class="dropdown-item" href="/ref_car_model">{{ t._("model-directory") }}</a>
            <a class="dropdown-item" href="/ref_manufacturers">{{ t._("ref-manufacturers") }}</a>
            <a class="dropdown-item" href="/ref_bank_black_list/">{{ t._("ref_bank_black_list") }}</a>
        </div>
    </div>
    <?php endif; ?>
{% endif %}

<!-- меню заявок для супермодератора -->
{% if auth is defined and auth.isSuperModerator() %}
    <?php if(substr($__rn, 0, 4) == 'ref_'): ?>
    <div class="dropdown float-right">
        <button class="btn btn-warning dropdown-toggle" type="button" id="ddExt" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
            {{ t._("Расширенные настройки") }}
        </button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ddExt">
            <a class="dropdown-item" href="/ref_car_model">{{ t._("model-directory") }}</a>
            <!--                 <a class="dropdown-item" href="/ref_tn_code">{{ t._("Реестр кодов ТН ВЭД") }}</a> -->
            <a class="dropdown-item" href="/ref_car_type">{{ t._("Типы машин") }}</a>
            <a class="dropdown-item" href="/ref_fund">{{ t._("Лимиты финансирования") }}</a>
            <a class="dropdown-item" href="/ref_manufacturers">{{ t._("ref-manufacturers") }}</a>
            <a class="dropdown-item" href="/ref_vin_mask">{{ t._("ref_vin_mask") }}</a>
        </div>
    </div>
    <?php endif; ?>
{% endif %}
