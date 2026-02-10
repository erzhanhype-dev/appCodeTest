<?php
$token    = $this->session->get('sanctum_token');
$redirect = '/export/annulment';
?>

<?php if ($token): ?>
<iframe
        src="/app-core/iframe-auth?token=<?=$token; ?>&redirect=<?=$redirect; ?>"
        style="width: 100%; height: calc(100vh - 100px);border: none;">
</iframe>
<?php endif; ?>