<div style="position: fixed; top: 0; right: 0; margin: 20px 20px;">

    <?php
        $flash_messages = $this->flashSession->getMessages();
    if($flash_messages && count($flash_messages) > 0){
    foreach($flash_messages as $type => $messages){
    foreach($messages as $message){
    ?>

    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="false" data-delay="10000">
        <div class="toast-header">
            <div class="toast-mark bg-{{ type }}">&nbsp;</div>
            <strong class="mr-auto">АО «Жасыл Даму»</strong>
            <small class="text-{{ type }}"></small>
            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Закрыть">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body">
            {{ message }}
        </div>
    </div>

    <?php
        }
        }
    }
    ?>
</div>
