<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$siteDesc = \App\Settings::get('site_description', 'Социальная платформа мастеров.');
$heroTitle = \App\Settings::get('hero_title', 'Добро пожаловать на арену');
$heroTagline = \App\Settings::get('hero_tagline', 'Смотрите работы мастеров, ставьте оценки и общайтесь в личных сообщениях.');
$sectionMastersTitle = \App\Settings::get('section_masters_title', 'Наши мастера');
$sectionFeedTitle = \App\Settings::get('section_feed_title', 'Лента');
$sectionServicesTitle = \App\Settings::get('section_services_title', 'Услуги студии');
$sectionMapTitle = \App\Settings::get('section_map_title', 'Как нас найти');
?>
<section class="hero-studio">
    <p class="tagline"><?= htmlspecialchars($heroTitle) ?></p>
    <p class="site-desc"><?= htmlspecialchars($heroTagline) ?></p>
</section>

<?php if (!empty($services)): ?>
<h2 class="section-heading"><?= htmlspecialchars($sectionServicesTitle) ?></h2>
<div class="services-block card" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
    <?php foreach ($services as $sv): ?>
        <a href="<?= htmlspecialchars($root . 'masters.php?service=' . (int)$sv['id']) ?>" class="service-card" style="padding: 20px; background: var(--bg-panel); border-radius: var(--radius-sm); border: 1px solid var(--border); text-decoration: none; color: inherit; transition: border-color var(--transition);">
            <strong style="color: var(--accent-gold);"><?= htmlspecialchars($sv['name']) ?></strong>
            <?php if (!empty($sv['description'])): ?>
                <p style="margin: 8px 0 0; font-size: 0.9rem; color: var(--text-muted);"><?= htmlspecialchars(mb_substr($sv['description'], 0, 80)) ?><?= mb_strlen($sv['description']) > 80 ? '...' : '' ?></p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="section-heading"><?= htmlspecialchars($sectionMastersTitle) ?></h2>
<div class="carousel-wrap">
    <button type="button" class="carousel-btn prev" aria-label="Назад">‹</button>
    <div class="carousel-track" id="carouselTrack">
        <?php foreach ($masters as $m): ?>
            <div class="carousel-item master-card" data-master-id="<?= (int)$m['id'] ?>"
                 data-name="<?= htmlspecialchars($m['full_name']) ?>"
                 data-rating="<?= htmlspecialchars($m['rating_avg'] ?? '0') ?>"
                 data-count="<?= (int)($m['rating_count'] ?? 0) ?>"
                 data-bio="<?= htmlspecialchars($m['bio'] ?? '') ?>"
                 data-avatar="<?= !empty($m['avatar_path']) ? htmlspecialchars($root . ltrim($m['avatar_path'], '/')) : '' ?>"
                 data-url="<?= htmlspecialchars($root . 'master.php?id=' . (int)$m['id']) ?>"
                 data-reviews-url="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$m['id']) ?>">
                <div class="avatar-wrap">
                    <?php if (!empty($m['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($root . ltrim($m['avatar_path'] ?? '', '/')) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar-initials-sm" aria-hidden="true"><?= mb_strtoupper(mb_substr($m['full_name'] ?? '?', 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <h3 class="name"><?= htmlspecialchars($m['full_name']) ?><?= !empty($m['is_verified']) ? ' ✓' : '' ?></h3>
                    <div class="rating">
                        ★ <?= htmlspecialchars($m['rating_avg'] ?? '0') ?> (<?= (int)($m['rating_count'] ?? 0) ?>)
                        <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$m['id']) ?>" class="btn-link-reviews-sm" onclick="event.stopPropagation();">Отзывы</a>
                    </div>
                    <?php if (!empty($m['bio'])): ?>
                        <p class="bio"><?= htmlspecialchars(mb_substr($m['bio'], 0, 100)) ?>...</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="carousel-btn next" aria-label="Вперёд">›</button>
</div>
<a href="<?= htmlspecialchars($root . 'masters.php') ?>" class="link-all-masters">Все мастера</a>

<?php if (!empty($mapLocations)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<h2 class="section-heading" style="margin-top: 48px;"><?= htmlspecialchars($sectionMapTitle) ?></h2>
<div class="map-block card">
    <div class="map-block__inner">
        <div class="map-block__map" id="studioMap"></div>
        <div class="map-block__addresses">
            <?php foreach ($mapLocations as $i => $ml): ?>
            <button type="button" class="map-address-btn" data-lat="<?= (float)$ml['lat'] ?>" data-lng="<?= (float)$ml['lng'] ?>" data-idx="<?= $i ?>">
                <?php if (!empty(trim($ml['title'] ?? ''))): ?>
                    <strong><?= htmlspecialchars($ml['title']) ?></strong><br>
                <?php endif; ?>
                <?= nl2br(htmlspecialchars($ml['address'])) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(function(){
    var locations = <?= json_encode(array_map(function($m){ return ['lat'=>(float)$m['lat'],'lng'=>(float)$m['lng'],'title'=>$m['title']??'','address'=>$m['address']??'']; }, $mapLocations)) ?>;
    if (locations.length === 0) return;
    var center = { lat: locations[0].lat, lng: locations[0].lng };
    if (locations.length > 1) {
        center.lat = locations.reduce(function(s,l){ return s + l.lat; }, 0) / locations.length;
        center.lng = locations.reduce(function(s,l){ return s + l.lng; }, 0) / locations.length;
    }
    var mapEl = document.getElementById('studioMap');
    if (!mapEl) return;
    var map = L.map('studioMap').setView([center.lat, center.lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
    var markers = [];
    locations.forEach(function(loc, i){
        var m = L.marker([loc.lat, loc.lng]).addTo(map);
        var popup = (loc.title ? '<strong>'+loc.title+'</strong><br>' : '') + (loc.address || '').replace(/\n/g,'<br>');
        if (popup) m.bindPopup(popup);
        markers.push(m);
    });
    if (locations.length > 1) map.fitBounds(markers.map(function(m){ return m.getLatLng(); }));
    document.querySelectorAll('.map-address-btn').forEach(function(btn, i){
        btn.addEventListener('click', function(){
            var lat = parseFloat(btn.getAttribute('data-lat'));
            var lng = parseFloat(btn.getAttribute('data-lng'));
            map.flyTo([lat, lng], 16, { duration: 0.5 });
            if (markers[i]) markers[i].openPopup();
            document.querySelectorAll('.map-address-btn').forEach(function(b){ b.classList.remove('is-active'); });
            btn.classList.add('is-active');
        });
    });
})();
</script>
<?php endif; ?>

<div id="masterModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <button type="button" class="modal-close" id="modalClose" aria-label="Закрыть">×</button>
        <div id="modalAvatarWrap" class="modal-avatar-wrap avatar-initials-sm" style="display:none;"><span aria-hidden="true" id="modalAvatarInitial">?</span></div>
        <img id="modalAvatar" class="modal-avatar" src="" alt="" style="display:none;">
        <div class="modal-body">
            <h2 id="modalTitle" class="modal-name"></h2>
            <div class="modal-rating-row">
                <span id="modalRating" class="modal-rating"></span>
                <a id="modalReviewsLink" href="#" class="btn-link-reviews">Просмотреть отзывы</a>
            </div>
            <p id="modalBio" class="modal-bio"></p>
            <div class="modal-actions">
                <a id="modalLink" href="#" class="btn btn-primary">Перейти на страницу мастера</a>
            </div>
        </div>
    </div>
</div>

<h2 class="section-heading" style="margin-top: 48px;"><?= htmlspecialchars($sectionFeedTitle) ?></h2>
<div class="feed">
    <?php foreach ($feed as $post): ?>
        <article class="post-card">
            <div class="post-header">
                <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$post['master_id']) ?>">
                    <?php if (!empty($post['avatar_path'])): ?>
                        <img class="post-avatar" src="<?= htmlspecialchars($root . ltrim($post['avatar_path'] ?? '', '/')) ?>" alt="">
                    <?php else: ?>
                        <div class="post-avatar avatar-initials-sm"><?= mb_strtoupper(mb_substr($post['full_name'] ?? '?', 0, 1)) ?></div>
                    <?php endif; ?>
                </a>
                <div>
                    <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$post['master_id']) ?>" class="post-author"><?= htmlspecialchars($post['full_name']) ?></a>
                    <div class="post-date"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></div>
                </div>
            </div>
            <?php if (!empty(trim($post['content_text'] ?? ''))): ?>
                <div class="post-text"><?= nl2br(htmlspecialchars($post['content_text'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($post['media'])): ?>
                <div class="post-media">
                    <?php foreach ($post['media'] as $med): ?>
                        <?php if ($med['media_type'] === 'image'): ?>
                            <img src="<?= htmlspecialchars($root . ltrim($med['file_path'] ?? '', '/')) ?>" alt="">
                        <?php elseif ($med['media_type'] === 'video'): ?>
                            <video controls src="<?= htmlspecialchars($root . ltrim($med['file_path'] ?? '', '/')) ?>"></video>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (empty($feed)): ?>
        <p class="card" style="color: var(--text-muted); text-align: center;">Пока нет записей в ленте.</p>
    <?php endif; ?>
</div>

<script>
(function() {
    var track = document.getElementById('carouselTrack');
    var items = track ? track.querySelectorAll('.carousel-item') : [];
    var modal = document.getElementById('masterModal');
    var modalClose = document.getElementById('modalClose');
    var modalAvatar = document.getElementById('modalAvatar');
    var modalAvatarWrap = document.getElementById('modalAvatarWrap');
    var modalTitle = document.getElementById('modalTitle');
    var modalRating = document.getElementById('modalRating');
    var modalBio = document.getElementById('modalBio');
    var modalLink = document.getElementById('modalLink');

    function openModal(el) {
        if (!el || !modal) return;
        var name = el.getAttribute('data-name') || '';
        var rating = el.getAttribute('data-rating') || '0';
        var count = el.getAttribute('data-count') || '0';
        var bio = el.getAttribute('data-bio') || '';
        var avatar = el.getAttribute('data-avatar') || '';
        var url = el.getAttribute('data-url') || '#';
        var reviewsUrl = el.getAttribute('data-reviews-url') || (url.replace('master.php', 'reviews.php'));
        modalTitle.textContent = name;
        modalRating.textContent = '★ Рейтинг: ' + rating + ' (' + count + ' оценок)';
        modalBio.textContent = bio || 'Нет описания.';
        modalLink.href = url;
        var reviewsLink = document.getElementById('modalReviewsLink');
        if (reviewsLink) reviewsLink.href = reviewsUrl;
        if (avatar) {
            modalAvatar.src = avatar;
            modalAvatar.style.display = 'block';
            modalAvatarWrap.style.display = 'none';
        } else {
            modalAvatar.style.display = 'none';
            modalAvatarWrap.style.display = 'flex';
            var initEl = document.getElementById('modalAvatarInitial');
            if (initEl) initEl.textContent = name ? name.charAt(0).toUpperCase() : '?';
        }
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    items.forEach(function(item) {
        item.addEventListener('click', function() { openModal(item); });
    });
    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

    var prev = document.querySelector('.carousel-btn.prev');
    var next = document.querySelector('.carousel-btn.next');
    if (track && prev) prev.addEventListener('click', function() { track.scrollBy({ left: -304, behavior: 'smooth' }); });
    if (track && next) next.addEventListener('click', function() { track.scrollBy({ left: 304, behavior: 'smooth' }); });

    var carouselInterval = setInterval(function() {
        if (!track || items.length === 0) return;
        var maxScroll = track.scrollWidth - track.clientWidth;
        if (maxScroll <= 0) return;
        var nextPos = track.scrollLeft + 304;
        if (nextPos >= maxScroll) nextPos = 0;
        track.scrollTo({ left: nextPos, behavior: 'smooth' });
    }, 4000);
})();
</script>
