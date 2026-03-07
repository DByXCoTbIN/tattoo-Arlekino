<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$myGroups = $myGroups ?? [];
$currentGroup = $currentGroup ?? null;
$groupId = $groupId ?? 0;
$currentConv = $currentConv ?? null;
$isMaster = in_array($user['role'] ?? '', ['master', 'admin'], true);
$isChatPage = $currentConv || $currentGroup;
?>
<?php if (!($isBanned ?? false)): ?>
<h1 class="card-title messages-page-title">Сообщения <?php if (!$isChatPage && isset($unreadCount) && $unreadCount > 0): ?><span style="color: var(--accent);">(<?= $unreadCount ?>)</span><?php endif; ?></h1>
<?php endif; ?>

<div class="messages-app <?= $isChatPage ? 'is-chat-view' : '' ?> <?= ($isBanned ?? false) ? 'messages-app--banned' : '' ?>" data-root="<?= htmlspecialchars($root) ?>" data-conv="<?= $currentConv ? (int)$currentConv['id'] : '' ?>" data-group="<?= (int)$groupId ?>" data-user-id="<?= (int)($user['id'] ?? 0) ?>" data-is-master="<?= (($isBanned ?? false) ? '0' : ($isMaster ? '1' : '0')) ?>">
    <!-- Левая панель: список чатов (скрыта для заблокированных) -->
    <?php if (!($isBanned ?? false)): ?>
    <div class="messages-list-panel card">
        <h3 class="card-title" style="font-size: 1rem;">Диалоги</h3>
        <?php foreach ($conversations as $c): ?>
            <?php $isActive = $currentConv && (int)$currentConv['id'] === (int)$c['id']; ?>
            <a href="<?= htmlspecialchars($root) ?>messages.php?conv=<?= (int)$c['id'] ?>" class="messages-conv-item <?= $isActive ? 'is-active' : '' ?>" data-type="conv" data-conv-id="<?= (int)$c['id'] ?>">
                <div class="messages-conv-avatar">
                    <?php if (!empty($c['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($root . ltrim($c['avatar_path'] ?? '', '/')) ?>" alt="">
                    <?php else: ?>
                        <span class="messages-conv-initial"><?= mb_strtoupper(mb_substr($c['full_name'] ?? '?', 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="messages-conv-info">
                    <strong class="messages-conv-name"><?= htmlspecialchars($c['full_name']) ?><?php if (!empty($c['unread_count'])): ?><span class="messages-conv-unread"><?= (int)$c['unread_count'] ?></span><?php endif; ?></strong>
                    <div class="messages-conv-preview"><?= htmlspecialchars($c['last_message'] ?? '') ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        <h3 class="card-title" style="font-size: 1rem; margin-top: 20px;">Групповые чаты</h3>
        <?php foreach ($myGroups as $g): ?>
            <?php $isActive = $currentGroup && (int)$currentGroup['id'] === (int)$g['id']; ?>
            <a href="<?= htmlspecialchars($root) ?>messages.php?group=<?= (int)$g['id'] ?>" class="messages-conv-item <?= $isActive ? 'is-active' : '' ?>" data-type="group" data-group-id="<?= (int)$g['id'] ?>">
                <div class="messages-conv-avatar messages-conv-avatar--group">
                    <span class="messages-conv-initial messages-conv-initial--group" aria-hidden="true">Гр</span>
                </div>
                <div class="messages-conv-info">
                    <strong class="messages-conv-name"><?= htmlspecialchars($g['name']) ?></strong>
                    <div class="messages-conv-preview"><?= htmlspecialchars($g['last_message'] ?? '') ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if ($isMaster): ?>
        <p style="margin-top: 16px;"><button type="button" class="btn btn-small" id="btnCreateGroup">+ Создать группу</button></p>
        <?php endif; ?>
        <p style="margin-top: 12px; font-size: 0.9rem;"><a href="#" id="linkJoinGroup">Присоединиться к группе</a></p>
        <?php if (empty($conversations) && empty($myGroups)): ?>
            <p style="color: var(--text-muted); margin-top: 12px;">Нет диалогов. Перейдите на страницу мастера и нажмите «Написать сообщение».</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Правая панель: чат (на ПК) или полноэкранный чат (на телефоне при выборе) -->
    <div class="messages-chat-panel card">
        <?php if ($isChatPage): ?>
        <div class="messages-chat messages-chat--full">
            <div class="messages-chat-header messages-chat-header--with-back">
                <a href="<?= htmlspecialchars(($isBanned ?? false) ? $root . 'blocked.php' : $root . 'messages.php') ?>" class="messages-back-link" aria-label="К диалогам">←</a>
                <div class="messages-chat-header-top">
                    <h3 class="card-title messages-chat-title" id="messagesChatTitle" style="font-size: 1rem;"><?= $currentGroup ? htmlspecialchars($currentGroup['name'] ?? 'Чат') : htmlspecialchars($otherUser['full_name'] ?? 'Чат') ?></h3>
                    <span id="messagesOnline" class="messages-online"></span>
                </div>
                <?php if (!($isBanned ?? false)): ?>
                <div id="messagesGroupActions" class="messages-group-actions" style="display: none;">
                    <a href="#" id="linkCopyGroup" class="btn btn-small" title="Копировать ссылку">Ссылка</a>
                    <button type="button" id="btnSendGroupLink" class="btn btn-small" title="Отправить в чаты">В чаты</button>
                </div>
                <?php if ($isMaster): ?>
                <div class="messages-chat-header-actions">
                    <button type="button" class="btn btn-small messages-btn-delete-chat" id="btnDeleteChat" title="<?= $currentGroup ? 'Удалить группу (доступно создателю или админу)' : 'Удалить диалог' ?>">Удалить чат</button>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <div id="messagesTyping" class="messages-typing"></div>
            <div class="messages-area-wrap">
                <div id="messagesArea" class="messages-area"></div>
                <button type="button" class="messages-scroll-down" id="messagesScrollDown" title="Вниз" style="display: none;">↓</button>
            </div>
            <form id="messagesForm" class="messages-form">
                <div id="messageReplyBlock" class="message-reply-block" style="display:none;">
                    <span class="message-reply-label">Ответ на:</span>
                    <span id="messageReplyText" class="message-reply-text"></span>
                    <button type="button" class="message-reply-close" id="messageReplyClose" aria-label="Отменить">×</button>
                </div>
                <div class="messages-input-wrap">
                    <div class="messages-input-inner">
                        <textarea name="body" rows="2" placeholder="Сообщение... (Enter — отправить, Shift+Enter — новая строка)" id="messageBody"></textarea>
                        <div id="mediaPreview" class="media-preview" style="display:none;"></div>
                    </div>
                    <label class="btn btn-secondary messages-media-btn" title="Прикрепить файл">
                        <input type="file" name="media" accept="image/*,video/*" id="messageMedia" style="display:none;">
                        <span class="messages-media-btn__label">Файл</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Отправить</button>
            </form>
        </div>
        <?php else: ?>
        <div class="messages-chat-empty-state">
            <p>Выберите диалог или группу</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalCreateGroup" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 360px;">
        <button type="button" class="modal-close" id="modalCreateGroupClose">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Создать групповой чат</h2>
            <form id="formCreateGroup">
                <div class="form-group">
                    <label>Название группы</label>
                    <input type="text" name="name" required maxlength="255">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Создать</button>
            </form>
        </div>
    </div>
</div>

<div id="modalJoinGroup" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 360px;">
        <button type="button" class="modal-close" id="modalJoinGroupClose">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Присоединиться к группе</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Введите название группы или перейдите по ссылке от мастера:</p>
            <form id="formJoinGroup">
                <div class="form-group">
                    <input type="text" name="group_name" id="joinGroupName" placeholder="Название группы" maxlength="255">
                    <span style="color: var(--text-muted); font-size: 0.85rem;">или ID из ссылки</span>
                    <input type="number" name="group_id" id="joinGroupId" placeholder="ID группы (если есть)" min="1" style="margin-top: 6px;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Войти</button>
            </form>
        </div>
    </div>
</div>

<div id="modalMediaView" class="modal-media-view">
    <button type="button" class="modal-media-close" id="modalMediaViewClose" aria-label="Закрыть">×</button>
    <div id="modalMediaContent"></div>
</div>

<div id="modalSendGroupLink" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 420px;">
        <button type="button" class="modal-close" id="modalSendGroupLinkClose">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Отправить ссылку в чаты</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 12px;">Выберите чаты:</p>
            <div id="sendLinkChatsList" class="send-link-chats-list"></div>
            <button type="button" class="btn btn-primary" id="btnConfirmSendLink" style="width:100%; margin-top: 16px;">Отправить</button>
        </div>
    </div>
</div>

<script>
(function() {
    var layout = document.querySelector('.messages-app');
    if (!layout) return;

    var root = layout.getAttribute('data-root') || '/';
    var baseConv = layout.getAttribute('data-conv') || '';
    var baseGroup = layout.getAttribute('data-group') || '';
    var userId = parseInt(layout.getAttribute('data-user-id') || '0', 10);
    var isMaster = layout.getAttribute('data-is-master') === '1';
    var apiUrl = root + 'messages_api.php';
    var groupsUrl = root + 'groups_api.php';
    var isChatView = layout.classList.contains('is-chat-view');
    var pollInterval, typingTimeout;
    var hasGroup = baseGroup && baseGroup !== '0';
    var currentType = hasGroup ? 'group' : 'conv';
    var currentId = hasGroup ? baseGroup : baseConv;
    var shouldScrollToBottom = true;
    var lastMessageIds = [];

    var chatTitle = document.getElementById('messagesChatTitle');
    var messagesOnline = document.getElementById('messagesOnline');
    var messagesTyping = document.getElementById('messagesTyping');
    var messagesArea = document.getElementById('messagesArea');
    var form = document.getElementById('messagesForm');
    var bodyEl = document.getElementById('messageBody');
    var mediaEl = document.getElementById('messageMedia');
    var scrollDownBtn = document.getElementById('messagesScrollDown');

    if (form) {
        form.dataset.chatType = currentType;
        form.dataset.chatId = currentId;
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function linkify(text) {
        if (!text || typeof text !== 'string') return '';
        var escaped = escapeHtml(text).replace(/\n/g, '<br>');
        var urlRe = /(https?:\/\/[^\s<>"\']+|www\.[^\s<>"\']+)/gi;
        return escaped.replace(urlRe, function(m) {
            var url = m.toLowerCase().indexOf('http') === 0 ? m : 'https://' + m;
            return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(m) + '</a>';
        });
    }
    var replyToId = null, replyToBody = null, replyToSender = null;
    function parseQuotedBody(text) {
        if (!text || typeof text !== 'string') return { quote: null, main: text || '' };
        var lines = text.split('\n');
        var quoteLines = [];
        var i = 0;
        while (i < lines.length && lines[i].indexOf('>') === 0) { quoteLines.push(lines[i].replace(/^>\s?/, '')); i++; }
        while (i < lines.length && lines[i] === '' && quoteLines.length) i++;
        return { quote: quoteLines.length ? quoteLines.join('\n') : null, main: lines.slice(i).join('\n') };
    }

    function renderMessage(m) {
        var div = document.createElement('div');
        div.className = 'message-row' + (m.is_mine ? ' message-mine' : '');
        div.id = 'msg-' + (m.id || '');
        div.setAttribute('data-msg-id', m.id || '');
        div.setAttribute('data-msg-body', (m.body || '').replace(/"/g, '&quot;'));
        div.setAttribute('data-msg-sender', m.sender_name || '');
        var time = (m.created_at || '').replace(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}).*/, '$3.$2.$1 $4:$5');
        var parsed = parseQuotedBody(m.body || '');
        var body = '';
        if (parsed.quote) body += '<div class="message-quote-block">' + linkify(parsed.quote) + '</div>';
        var mediaUrl = root + (m.media_path || '').replace(/^\//, '');
        if (m.media_path && m.media_type) {
            if (m.media_type === 'image') body += '<div class="message-media message-media--expandable" data-src="' + escapeHtml(mediaUrl) + '" data-type="image"><img src="' + escapeHtml(mediaUrl) + '" alt=""></div>';
            else if (m.media_type === 'video') body += '<div class="message-media message-media--expandable" data-src="' + escapeHtml(mediaUrl) + '" data-type="video"><video src="' + escapeHtml(mediaUrl) + '" preload="metadata"></video><span class="message-media-play">▶</span></div>';
        }
        if (parsed.main) body += '<div class="message-bubble">' + linkify(parsed.main) + '</div>';
        body += '<div class="message-meta"><span class="message-time">' + (m.sender_name ? escapeHtml(m.sender_name) + ' · ' : '') + escapeHtml(time) + '</span><span class="message-actions"><button type="button" class="message-action-btn" data-action="link" title="Ссылка">Ссылка</button><button type="button" class="message-action-btn" data-action="reply" title="Ответить">Ответ</button></span></div>';
        div.innerHTML = body;
        div.querySelectorAll('.message-media--expandable').forEach(function(wrap) {
            wrap.addEventListener('click', function(e) {
                e.preventDefault();
                var src = wrap.getAttribute('data-src');
                var type = wrap.getAttribute('data-type') || 'image';
                if (!src) return;
                var fullUrl = (src.indexOf('/') === 0 || src.indexOf('http') === 0) ? src : (window.location.origin + (root === '/' ? '' : root) + src);
                var modal = document.getElementById('modalMediaView');
                var content = document.getElementById('modalMediaContent');
                if (modal && content) {
                    content.innerHTML = type === 'image' ? '<img src="' + escapeHtml(fullUrl) + '" alt="">' : '<video controls autoplay src="' + escapeHtml(fullUrl) + '"></video>';
                    modal.classList.add('is-open'); modal.style.display = 'flex';
                }
            });
        });
        div.querySelectorAll('.message-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var id = div.getAttribute('data-msg-id');
                var txt = (div.getAttribute('data-msg-body') || '').replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
                var sender = div.getAttribute('data-msg-sender') || '';
                if (btn.getAttribute('data-action') === 'link') {
                    var param = currentType === 'group' ? 'group' : 'conv';
                    var newUrl = root + 'messages.php?' + param + '=' + currentId + '#msg-' + id;
                    history.pushState({}, '', newUrl);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        var path = (root === '/' ? '' : root) + 'messages.php?' + param + '=' + currentId + '#msg-' + id;
                        navigator.clipboard.writeText(window.location.origin + (path.charAt(0) === '/' ? path : '/' + path)).then(function() {}).catch(function() {});
                    }
                    var el = document.getElementById('msg-' + id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    replyToId = id; replyToBody = txt; replyToSender = sender;
                    var replyBlock = document.getElementById('messageReplyBlock');
                    var replyText = document.getElementById('messageReplyText');
                    if (replyBlock && replyText) {
                        replyText.textContent = (sender ? sender + ': ' : '') + (txt.length > 80 ? txt.substring(0, 80) + '…' : txt);
                        replyBlock.style.display = 'flex';
                    }
                    if (bodyEl) { bodyEl.focus(); bodyEl.placeholder = 'Ваш ответ...'; }
                }
            });
        });
        return div;
    }

    document.getElementById('messageReplyClose') && document.getElementById('messageReplyClose').addEventListener('click', function() {
        replyToId = null; replyToBody = null; replyToSender = null;
        var rb = document.getElementById('messageReplyBlock');
        if (rb) rb.style.display = 'none';
        if (bodyEl) bodyEl.placeholder = 'Сообщение... (Enter — отправить, Shift+Enter — новая строка)';
    });

    function loadMessages() {
        if (!currentType || !currentId) return;
        var url = currentType === 'group' ? (apiUrl + '?group=' + currentId) : (apiUrl + '?conv=' + currentId);
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    if (data.error === 'Forbidden' && currentType === 'group') {
                        if (messagesArea) {
                            messagesArea.innerHTML = '<p class="messages-not-member">Вы не в этой группе. <a href="#" id="linkJoinFromGroup">Присоединиться</a></p>';
                            document.getElementById('linkJoinFromGroup') && document.getElementById('linkJoinFromGroup').addEventListener('click', function(e) {
                                e.preventDefault();
                                var gi = document.getElementById('joinGroupId');
                                if (gi) gi.value = currentId;
                                var mj = document.getElementById('modalJoinGroup');
                                if (mj) { mj.style.display = 'flex'; mj.classList.add('is-open'); }
                            });
                        }
                    }
                    return;
                }
                if (chatTitle) {
                    if (data.type === 'group') {
                        chatTitle.textContent = data.group_name || 'Чат';
                        if (messagesOnline) messagesOnline.textContent = data.online && data.online.length ? 'Онлайн: ' + data.online.join(', ') : '';
                        var ga = document.getElementById('messagesGroupActions');
                        if (ga) ga.style.display = (isMaster && data.creator_id === userId) ? 'flex' : 'none';
                    } else {
                        chatTitle.textContent = data.other ? data.other.full_name : 'Чат';
                        if (messagesOnline) {
                            if (data.other && data.other.online) {
                                messagesOnline.textContent = 'Онлайн';
                                messagesOnline.classList.add('messages-online--active');
                            } else {
                                messagesOnline.classList.remove('messages-online--active');
                                if (data.other && data.other.last_seen_at) {
                                    var d = new Date(data.other.last_seen_at.replace(/-/g, '/'));
                                    var day = ('0' + d.getDate()).slice(-2), month = ('0' + (d.getMonth() + 1)).slice(-2), year = d.getFullYear();
                                    var h = ('0' + d.getHours()).slice(-2), m = ('0' + d.getMinutes()).slice(-2);
                                    messagesOnline.textContent = 'Был в сети: ' + day + '.' + month + '.' + year + ' ' + h + ':' + m;
                                } else {
                                    messagesOnline.textContent = '';
                                }
                            }
                        }
                        var ga = document.getElementById('messagesGroupActions');
                        if (ga) ga.style.display = 'none';
                    }
                }
                if (messagesTyping) {
                    if (data.typing && data.typing.length) {
                        messagesTyping.textContent = data.typing.join(', ') + ' печатает...';
                        messagesTyping.style.display = 'block';
                    } else {
                        messagesTyping.style.display = 'none';
                        messagesTyping.textContent = '';
                    }
                }
                if (messagesArea) {
                    messagesArea.innerHTML = '';
                    (data.messages || []).forEach(function(m) {
                        messagesArea.appendChild(renderMessage(m));
                    });
                    if (shouldScrollToBottom) {
                        messagesArea.scrollTop = messagesArea.scrollHeight;
                        shouldScrollToBottom = false;
                        requestAnimationFrame(function() { messagesArea.scrollTop = messagesArea.scrollHeight; });
                    }
                    if (scrollDownBtn) scrollDownBtn.style.display = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight > 60) ? 'block' : 'none';
                }
            })
            .catch(function() {});
    }

    function sendTyping() {
        if (!currentType || !currentId) return;
        var fd = new FormData();
        fd.append('action', 'typing');
        if (currentType === 'group') fd.append('group', currentId);
        else fd.append('conv', currentId);
        fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
    }

    window.addEventListener('hashchange', function() {
        var hash = window.location.hash;
        if (hash && hash.indexOf('msg-') !== -1) {
            var el = document.getElementById(hash.slice(1));
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    if (scrollDownBtn) scrollDownBtn.addEventListener('click', function() {
        if (messagesArea) { messagesArea.scrollTop = messagesArea.scrollHeight; scrollDownBtn.style.display = 'none'; }
    });
    if (messagesArea) messagesArea.addEventListener('scroll', function() {
        if (scrollDownBtn) scrollDownBtn.style.display = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight > 60) ? 'block' : 'none';
    });

    if (form) form.addEventListener('submit', function(e) {
        e.preventDefault();
        var type = form.dataset.chatType;
        var id = form.dataset.chatId;
        if (!id) return;
        var body = (bodyEl || form.querySelector('textarea[name=body]')).value;
        var fileInput = mediaEl || form.querySelector('input[name=media]');
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (!body.trim() && !hasFile) return;
        if (replyToId && replyToBody) {
            body = '> ' + (replyToSender ? replyToSender + ': ' : '') + replyToBody.replace(/\n/g, '\n> ') + '\n\n' + body;
            replyToId = null; replyToBody = null; replyToSender = null;
            var rb = document.getElementById('messageReplyBlock');
            if (rb) rb.style.display = 'none';
            if (bodyEl) bodyEl.placeholder = 'Сообщение...';
        }
        var fd = new FormData();
        fd.append(type === 'group' ? 'group' : 'conv', id);
        fd.append('body', (body || '').trim());
        if (hasFile) fd.append('media', fileInput.files[0]);
        fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) return r.text().then(function(t) { throw new Error(t || 'Ошибка ' + r.status); });
                return r.json();
            })
            .then(function(data) {
                if (data.error) { alert(data.error); return; }
                (bodyEl || form.querySelector('textarea[name=body]')).value = '';
                if (fileInput) { fileInput.value = ''; if (window.clearMediaPreview) window.clearMediaPreview(); }
                shouldScrollToBottom = true;
                loadMessages();
            })
            .catch(function(err) { alert('Ошибка: ' + (err.message || 'неизвестная')); });
    });

    if (bodyEl) {
        bodyEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        });
        bodyEl.addEventListener('input', function() {
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(sendTyping, 300);
        });
    }
    var mediaPreview = document.getElementById('mediaPreview');
    if (mediaEl && mediaPreview) {
        mediaEl.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var f = this.files[0];
                var isImg = (f.type || '').indexOf('image/') === 0;
                if (isImg) {
                    var r = new FileReader();
                    r.onload = function() { mediaPreview.innerHTML = '<img src="' + r.result + '" alt="" class="media-preview-img"><span class="media-preview-name">' + (f.name || '') + '</span>'; mediaPreview.style.display = 'block'; };
                    r.readAsDataURL(f);
                } else {
                    mediaPreview.innerHTML = '<span class="media-preview-name">' + (f.name || 'файл') + '</span>';
                    mediaPreview.style.display = 'block';
                }
            } else { mediaPreview.style.display = 'none'; mediaPreview.innerHTML = ''; }
        });
    }
    window.clearMediaPreview = function() {
        if (mediaPreview) { mediaPreview.style.display = 'none'; mediaPreview.innerHTML = ''; }
        if (mediaEl) mediaEl.value = '';
    };

    (function() {
        var modalMedia = document.getElementById('modalMediaView');
        var modalMediaClose = document.getElementById('modalMediaViewClose');
        function closeMediaModal() {
            if (modalMedia) { modalMedia.classList.remove('is-open'); modalMedia.style.display = 'none'; }
            var c = document.getElementById('modalMediaContent');
            if (c) c.innerHTML = '';
        }
        modalMediaClose && modalMediaClose.addEventListener('click', function(e) { e.stopPropagation(); closeMediaModal(); });
        modalMedia && modalMedia.addEventListener('click', function(e) { if (e.target === modalMedia) closeMediaModal(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMediaModal(); });
    })();

    document.getElementById('btnCreateGroup') && document.getElementById('btnCreateGroup').addEventListener('click', function() {
        var el = document.getElementById('modalCreateGroup');
        if (el) { el.style.display = 'flex'; el.classList.add('is-open'); }
    });
    document.getElementById('modalCreateGroupClose') && document.getElementById('modalCreateGroupClose').addEventListener('click', function() {
        var el = document.getElementById('modalCreateGroup');
        if (el) { el.style.display = 'none'; el.classList.remove('is-open'); }
    });
    document.getElementById('linkJoinGroup') && document.getElementById('linkJoinGroup').addEventListener('click', function(e) {
        e.preventDefault();
        var el = document.getElementById('modalJoinGroup');
        if (el) { el.style.display = 'flex'; el.classList.add('is-open'); }
    });
    document.getElementById('modalJoinGroupClose') && document.getElementById('modalJoinGroupClose').addEventListener('click', function() {
        var el = document.getElementById('modalJoinGroup');
        if (el) { el.style.display = 'none'; el.classList.remove('is-open'); }
    });
    document.getElementById('formCreateGroup') && document.getElementById('formCreateGroup').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = form.querySelector('button[type="submit"]');
        var nameInput = form.querySelector('input[name="name"]');
        var name = (nameInput && nameInput.value || '').trim();
        if (!name) { alert('Введите название группы'); return; }
        if (btn) btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'create');
        fd.append('name', name);
        fetch(groupsUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                if (res.data.ok && res.data.group_id) {
                    if (nameInput) nameInput.value = '';
                    var m = document.getElementById('modalCreateGroup');
                    if (m) { m.style.display = 'none'; m.classList.remove('is-open'); }
                    location.href = root + 'messages.php?group=' + res.data.group_id;
                } else if (res.data.error) alert(res.data.error);
            })
            .catch(function(err) { alert('Ошибка: ' + (err.message || 'нет связи')); })
            .finally(function() { if (btn) btn.disabled = false; });
    });
    document.getElementById('formJoinGroup') && document.getElementById('formJoinGroup').addEventListener('submit', function(e) {
        e.preventDefault();
        var nameInput = document.getElementById('joinGroupName');
        var idInput = document.getElementById('joinGroupId');
        var name = (nameInput && nameInput.value || '').trim();
        var id = (idInput && idInput.value || '').trim();
        if (!name && !id) { alert('Введите название или ID'); return; }
        var fd = new FormData();
        fd.append('action', 'join');
        if (name) fd.append('group_name', name);
        if (id) fd.append('group_id', id);
        fetch(groupsUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                if (res.data.ok && res.data.group_id) {
                    var mj = document.getElementById('modalJoinGroup');
                    if (mj) { mj.style.display = 'none'; mj.classList.remove('is-open'); }
                    location.href = root + 'messages.php?group=' + res.data.group_id;
                } else if (res.data.error) alert(res.data.error);
            })
            .catch(function(err) { alert('Ошибка: ' + (err.message || 'нет связи')); });
    });
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('invite')) {
        var mj = document.getElementById('modalJoinGroup');
        if (mj) { mj.style.display = 'flex'; mj.classList.add('is-open'); }
        var gi = document.getElementById('joinGroupId');
        if (gi) gi.value = urlParams.get('invite');
    }

    (function() {
        var linkCopy = document.getElementById('linkCopyGroup');
        var btnSend = document.getElementById('btnSendGroupLink');
        var modalSend = document.getElementById('modalSendGroupLink');
        var modalSendClose = document.getElementById('modalSendGroupLinkClose');
        var sendLinkList = document.getElementById('sendLinkChatsList');
        var btnConfirmSend = document.getElementById('btnConfirmSendLink');
        if (!linkCopy || !btnSend || !modalSend) return;
        linkCopy.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentType !== 'group' || !currentId) return;
            var path = (root === '/' ? '' : root) + 'messages.php?group=' + currentId;
            var url = window.location.origin + (path.charAt(0) === '/' ? path : '/' + path);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() { alert('Ссылка скопирована'); }).catch(function() { alert('Не удалось'); });
            } else prompt('Скопируйте:', url);
        });
        btnSend.addEventListener('click', function() {
            if (currentType !== 'group' || !currentId) return;
            fetch(groupsUrl + '?for_share=1', { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    sendLinkList.innerHTML = '';
                    (data.conversations || []).forEach(function(c) {
                        if (parseInt(c.id, 10) <= 0) return;
                        var label = document.createElement('label');
                        label.className = 'send-link-item';
                        label.innerHTML = '<input type="checkbox" name="conv_ids[]" value="' + c.id + '"> ' + (c.full_name || 'Диалог');
                        sendLinkList.appendChild(label);
                    });
                    (data.groups || []).forEach(function(g) {
                        if (parseInt(g.id, 10) === parseInt(currentId, 10)) return;
                        var label = document.createElement('label');
                        label.className = 'send-link-item';
                        label.innerHTML = '<input type="checkbox" name="group_ids[]" value="' + g.id + '"> ' + (g.name || 'Группа');
                        sendLinkList.appendChild(label);
                    });
                    modalSend.style.display = 'flex';
                    modalSend.classList.add('is-open');
                })
                .catch(function() { alert('Не удалось загрузить чаты'); });
        });
        modalSendClose && modalSendClose.addEventListener('click', function() {
            modalSend.style.display = 'none';
            modalSend.classList.remove('is-open');
        });
        modalSend.addEventListener('click', function(e) {
            if (e.target === modalSend) { modalSend.style.display = 'none'; modalSend.classList.remove('is-open'); }
        });
        btnConfirmSend && btnConfirmSend.addEventListener('click', function() {
            var convIds = [].slice.call(sendLinkList.querySelectorAll('input[name="conv_ids[]"]:checked')).map(function(c) { return c.value; });
            var groupIds = [].slice.call(sendLinkList.querySelectorAll('input[name="group_ids[]"]:checked')).map(function(c) { return c.value; });
            if (convIds.length === 0 && groupIds.length === 0) { alert('Выберите чат'); return; }
            var fd = new FormData();
            fd.append('action', 'send_link');
            fd.append('group_id', currentId);
            convIds.forEach(function(id) { fd.append('conv_ids[]', id); });
            groupIds.forEach(function(id) { fd.append('group_ids[]', id); });
            btnConfirmSend.disabled = true;
            fetch(groupsUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        alert('Ссылка отправлена в ' + (data.sent || 0) + ' чат(ов)');
                        modalSend.style.display = 'none';
                        modalSend.classList.remove('is-open');
                    } else if (data.error) alert(data.error);
                })
                .catch(function() { alert('Ошибка'); })
                .finally(function() { btnConfirmSend.disabled = false; });
        });
    })();

    (function() {
        var btnDelete = document.getElementById('btnDeleteChat');
        if (!btnDelete || !currentId) return;
        btnDelete.addEventListener('click', function() {
            var msg = currentType === 'group' ? 'Удалить эту группу? Все сообщения будут удалены.' : 'Удалить этот диалог? Все сообщения будут удалены.';
            if (!confirm(msg)) return;
            var fd = new FormData();
            if (currentType === 'group') {
                fd.append('action', 'delete_group');
                fd.append('group', currentId);
            } else {
                fd.append('action', 'delete_conv');
                fd.append('conv', currentId);
            }
            btnDelete.disabled = true;
            fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok && data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.error) {
                        alert(data.error);
                        btnDelete.disabled = false;
                    } else {
                        btnDelete.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Ошибка при удалении');
                    btnDelete.disabled = false;
                });
        });
    })();

    if (isChatView) {
        loadMessages();
        pollInterval = setInterval(loadMessages, 3000);
    }

    window.addEventListener('beforeunload', function() {
        if (pollInterval) clearInterval(pollInterval);
    });
})();
</script>
