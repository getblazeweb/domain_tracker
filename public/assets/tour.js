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
        backdrop.innerHTML = '<div id="tour-backdrop-full" class="tour-backdrop-panel"></div>';
        overlay.appendChild(backdrop);

        spotlight = document.createElement('div');
        spotlight.className = 'tour-spotlight';
        spotlight.id = 'tour-spotlight';
        overlay.appendChild(spotlight);

        var cardWrapper = document.createElement('div');
        cardWrapper.className = 'tour-card-wrapper tour-card-wrapper--centered';
        cardWrapper.id = 'tour-card-wrapper';
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
        cardWrapper.appendChild(card);
        overlay.appendChild(cardWrapper);

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

    function positionCard() {
        var wrapper = getEl('tour-card-wrapper');
        wrapper.classList.add('tour-card-wrapper--centered');
    }

    function updateBackdrop(targetEl) {
        var full = getEl('tour-backdrop-full');
        if (!targetEl) {
            if (full) full.style.display = 'block';
            spotlight.style.display = 'none';
            spotlight.style.visibility = 'hidden';
            spotlight.style.top = '-9999px';
            spotlight.style.left = '-9999px';
            spotlight.style.width = '0';
            spotlight.style.height = '0';
            spotlight.style.boxShadow = '';
            return;
        }

        if (full) full.style.display = 'none';

        var rect = targetEl.getBoundingClientRect();
        var pad = 8;
        var t = Math.max(0, rect.top - pad);
        var l = Math.max(0, rect.left - pad);
        var w = Math.max(24, rect.width + pad * 2);
        var h = Math.max(24, rect.height + pad * 2);

        spotlight.style.display = 'block';
        spotlight.style.visibility = 'visible';
        spotlight.style.background = 'transparent';
        spotlight.style.boxShadow = '0 0 0 9999px rgba(15, 23, 42, 0.55)';
        spotlight.style.top = t + 'px';
        spotlight.style.left = l + 'px';
        spotlight.style.width = w + 'px';
        spotlight.style.height = h + 'px';
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
        positionCard();

        if (targetEl) {
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () {
                updateBackdrop(targetEl);
            }, 400);
        }

        requestAnimationFrame(function () {
            updateBackdrop(targetEl);
            positionCard();
        });
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
