(function () {
    'use strict';

    var STEPS = [
        { id: 'welcome', target: null, title: 'Welcome', message: 'This is your dashboard for tracking domains, subdomains, and database credentials.' },
        { id: 'add-domain', target: '[data-tour="add-domain"]', title: 'Add Domain', message: 'Add your first domain to get started. You can include registrar, expiry date, and DB credentials.' },
        { id: 'search', target: '[data-tour="search"]', title: 'Search', message: 'Search across all domains and subdomains to find what you need quickly.' },
        { id: 'import', target: '[data-tour="import"]', title: 'Import CSV', message: 'Migrating from a spreadsheet? Import domains and subdomains from CSV.' },
        { id: 'expiry', target: '[data-tour="nav-expiry"]', title: 'Expiry', message: 'Track domain expiry dates and see what\'s coming due in 7, 30, 60, or 90 days.' },
        { id: 'security', target: '[data-tour="nav-security"]', title: 'Security', message: 'Enable 2FA, set login limits, and rotate encryption keys.' },
        { id: 'done', target: null, title: 'You\'re all set', message: 'You can restart this tour anytime from the Help button.' }
    ];

    var currentStep = 0;
    var overlay = null;
    var card = null;
    var backdropTop = null;
    var backdropLeft = null;
    var backdropRight = null;
    var backdropBottom = null;
    var spotlight = null;

    function getEl(id) {
        return document.getElementById(id);
    }

    function createOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'tour-overlay';
        overlay.id = 'tour-overlay';
        overlay.setAttribute('aria-hidden', 'true');

        var backdrop = document.createElement('div');
        backdrop.className = 'tour-backdrop';
        backdrop.innerHTML = '<div id="tour-backdrop-full" class="tour-backdrop-panel"></div>' +
            '<div id="tour-backdrop-top" class="tour-backdrop-panel tour-backdrop-cutout"></div>' +
            '<div id="tour-backdrop-left" class="tour-backdrop-panel tour-backdrop-cutout"></div>' +
            '<div id="tour-backdrop-right" class="tour-backdrop-panel tour-backdrop-cutout"></div>' +
            '<div id="tour-backdrop-bottom" class="tour-backdrop-panel tour-backdrop-cutout"></div>';
        overlay.appendChild(backdrop);

        backdropTop = getEl('tour-backdrop-top');
        backdropLeft = getEl('tour-backdrop-left');
        backdropRight = getEl('tour-backdrop-right');
        backdropBottom = getEl('tour-backdrop-bottom');

        spotlight = document.createElement('div');
        spotlight.className = 'tour-spotlight';
        spotlight.id = 'tour-spotlight';
        overlay.appendChild(spotlight);

        card = document.createElement('div');
        card.className = 'tour-card';
        card.id = 'tour-card';
        card.innerHTML = '<div class="tour-progress" id="tour-progress"></div>' +
            '<h3 class="tour-title" id="tour-title"></h3>' +
            '<p class="tour-message" id="tour-message"></p>' +
            '<div class="tour-actions">' +
            '<button type="button" class="button tour-skip" id="tour-skip">Skip</button>' +
            '<div class="tour-nav">' +
            '<button type="button" class="button tour-back" id="tour-back">Back</button>' +
            '<button type="button" class="button primary tour-next" id="tour-next">Next</button>' +
            '</div>' +
            '</div>';
        overlay.appendChild(card);

        document.body.appendChild(overlay);

        getEl('tour-skip').addEventListener('click', skip);
        getEl('tour-back').addEventListener('click', back);
        getEl('tour-next').addEventListener('click', next);

        document.addEventListener('keydown', handleKeydown);
        return overlay;
    }

    function handleKeydown(e) {
        if (!overlay || !overlay.classList.contains('is-open')) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            skip();
        } else if (e.key === 'Enter' && !e.target.matches('textarea, input[type="text"]')) {
            e.preventDefault();
            if (currentStep < STEPS.length - 1) {
                next();
            } else {
                complete();
            }
        }
    }

    function positionCard(targetEl) {
        var cardEl = getEl('tour-card');
        var padding = 16;
        var viewW = window.innerWidth;
        var viewH = window.innerHeight;
        var cardRect = cardEl.getBoundingClientRect();
        var cardW = cardRect.width;
        var cardH = cardRect.height;

        if (!targetEl) {
            cardEl.style.top = '50%';
            cardEl.style.left = '50%';
            cardEl.style.transform = 'translate(-50%, -50%)';
            cardEl.classList.remove('tour-card-above', 'tour-card-below', 'tour-card-left', 'tour-card-right');
            return;
        }

        var targetRect = targetEl.getBoundingClientRect();
        var targetCenterX = targetRect.left + targetRect.width / 2;
        var targetCenterY = targetRect.top + targetRect.height / 2;

        var positions = [
            { pos: 'above', top: targetRect.top - cardH - padding, left: targetCenterX - cardW / 2 },
            { pos: 'below', top: targetRect.bottom + padding, left: targetCenterX - cardW / 2 },
            { pos: 'left', top: targetCenterY - cardH / 2, left: targetRect.left - cardW - padding },
            { pos: 'right', top: targetCenterY - cardH / 2, left: targetRect.right + padding }
        ];

        var best = null;
        for (var i = 0; i < positions.length; i++) {
            var p = positions[i];
            var fits = p.left >= 0 && p.left + cardW <= viewW && p.top >= 0 && p.top + cardH <= viewH;
            if (fits) {
                best = p;
                break;
            }
        }
        if (!best) {
            best = positions[0];
        }

        cardEl.style.top = Math.max(padding, Math.min(viewH - cardH - padding, best.top)) + 'px';
        cardEl.style.left = Math.max(padding, Math.min(viewW - cardW - padding, best.left)) + 'px';
        cardEl.style.transform = 'none';
        cardEl.classList.remove('tour-card-above', 'tour-card-below', 'tour-card-left', 'tour-card-right');
        cardEl.classList.add('tour-card-' + best.pos);
    }

    function updateBackdrop(targetEl) {
        var full = getEl('tour-backdrop-full');
        if (!targetEl) {
            if (full) full.style.display = 'block';
            backdropTop.style.display = 'none';
            backdropLeft.style.display = 'none';
            backdropRight.style.display = 'none';
            backdropBottom.style.display = 'none';
            spotlight.style.display = 'none';
            return;
        }
        if (full) full.style.display = 'none';

        var rect = targetEl.getBoundingClientRect();
        var pad = 4;
        var top = Math.max(0, rect.top - pad);
        var left = Math.max(0, rect.left - pad);
        var width = rect.width + pad * 2;
        var height = rect.height + pad * 2;

        backdropTop.style.display = 'block';
        backdropTop.style.top = '0';
        backdropTop.style.left = '0';
        backdropTop.style.right = '0';
        backdropTop.style.height = top + 'px';

        backdropBottom.style.display = 'block';
        backdropBottom.style.top = (top + height) + 'px';
        backdropBottom.style.left = '0';
        backdropBottom.style.right = '0';
        backdropBottom.style.bottom = '0';

        backdropLeft.style.display = 'block';
        backdropLeft.style.top = top + 'px';
        backdropLeft.style.left = '0';
        backdropLeft.style.width = left + 'px';
        backdropLeft.style.height = height + 'px';

        backdropRight.style.display = 'block';
        backdropRight.style.top = top + 'px';
        backdropRight.style.left = (left + width) + 'px';
        backdropRight.style.right = '0';
        backdropRight.style.height = height + 'px';

        spotlight.style.display = 'block';
        spotlight.style.top = top + 'px';
        spotlight.style.left = left + 'px';
        spotlight.style.width = width + 'px';
        spotlight.style.height = height + 'px';
    }

    function render() {
        var step = STEPS[currentStep];
        getEl('tour-progress').textContent = 'Step ' + (currentStep + 1) + ' of ' + STEPS.length;
        getEl('tour-title').textContent = step.title;
        getEl('tour-message').textContent = step.message;

        getEl('tour-back').style.display = currentStep === 0 ? 'none' : '';
        getEl('tour-next').textContent = currentStep === STEPS.length - 1 ? 'Done' : 'Next';

        var targetEl = step.target ? document.querySelector(step.target) : null;
        updateBackdrop(targetEl);
        positionCard(targetEl);

        if (targetEl) {
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function show() {
        createOverlay();
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        render();
    }

    function hide() {
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    function next() {
        if (currentStep < STEPS.length - 1) {
            currentStep++;
            render();
        } else {
            complete();
        }
    }

    function back() {
        if (currentStep > 0) {
            currentStep--;
            render();
        }
    }

    function skip() {
        dismiss();
    }

    function complete() {
        dismiss();
    }

    function dismiss() {
        hide();
        var form = getEl('tour-dismiss-form');
        if (form) {
            form.submit();
        }
    }

    function start() {
        currentStep = 0;
        show();
    }

    window.Tour = {
        start: start,
        show: show,
        hide: hide
    };

    var autoShow = window.TOUR_AUTO_SHOW === true;
    if (autoShow) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

    var helpBtn = getEl('tour-help-btn');
    if (helpBtn) {
        helpBtn.addEventListener('click', function (e) {
            e.preventDefault();
            start();
        });
    }
})();
