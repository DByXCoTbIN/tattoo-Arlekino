<?php $base = defined('BASE_PATH') ? BASE_PATH : ''; ?>
<?php if (empty($adminSidebarLayout)): ?></main><?php endif; ?>
<?php if (($bodyClass ?? '') !== 'messages-page'): ?>
<footer class="site-footer">
    <div class="wrapper">
        &copy; <?= date('Y') ?> <span class="arlequino-gothic"><?= htmlspecialchars($siteName ?? 'АрлекинО') ?></span>. Студия тату.
    </div>
</footer>
<?php endif; ?>
<?php if (!empty($pendingReviewRequest)): ?>
<div id="modalReviewRequest" class="modal-overlay is-open" role="dialog" aria-modal="true" aria-labelledby="reviewRequestTitle">
    <div class="modal-box" style="max-width: 420px;">
        <button type="button" class="modal-close" id="reviewRequestClose" aria-label="Закрыть">×</button>
        <div class="modal-body">
            <h2 class="modal-name" id="reviewRequestTitle">Оставить отзыв</h2>
            <p style="margin: 0 0 20px; color: var(--text-muted);">
                Ваш сеанс у <?= htmlspecialchars($pendingReviewRequest['master_name']) ?> прошёл. Поделитесь впечатлениями — это поможет другим клиентам.
            </p>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="<?= htmlspecialchars(($root ?? '') . 'master.php?id=' . (int)$pendingReviewRequest['master_id'] . '&review=1') ?>" class="btn btn-primary">Оставить отзыв</a>
                <button type="button" class="btn btn-secondary" id="reviewRequestDecline">Отказаться</button>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('modalReviewRequest');
    var closeBtn = document.getElementById('reviewRequestClose');
    var declineBtn = document.getElementById('reviewRequestDecline');
    var bookingId = <?= (int)$pendingReviewRequest['id'] ?>;
    var root = '<?= addslashes($root ?? '') ?>';
    function hide() { if (modal) modal.style.display = 'none'; }
    if (closeBtn) closeBtn.onclick = hide;
    if (declineBtn) declineBtn.onclick = function(){
        var xhr = new XMLHttpRequest();
        xhr.open('POST', root + 'review_dismiss.php');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function(){ hide(); };
        xhr.send('booking_id=' + bookingId);
    };
    modal && modal.addEventListener('click', function(e){ if (e.target === modal) hide(); });
})();
</script>
<?php endif; ?>
<div class="orientation-overlay" id="orientationOverlay" aria-hidden="true">
    <div class="orientation-overlay__content">
        <span class="orientation-overlay__icon" aria-hidden="true"></span>
        <p>Поверните устройство в портретный режим</p>
    </div>
</div>
</body>
</html>
