define(['jquery', 'domReady!'], function ($) {
    'use strict';

    const INIT_FLAG = '__amazonPayInit';
    const HOST_SEL  = '.amazon-checkout-button > div';

    // Recursively traverse nested shadow DOMs and find matches
    function deepQueryAll(root, selector) {
        const results = [];

        function walk(node) {
            if (!node) return;

            if (node.nodeType === 1 && node.matches && node.matches(selector)) {
                results.push(node);
            }

            if (node.shadowRoot) walk(node.shadowRoot);

            const kids = node.children || [];
            for (let i = 0; i < kids.length; i++) walk(kids[i]);
        }

        walk(root);
        return results;
    }

    function processHosts() {
        document.querySelectorAll(HOST_SEL).forEach(function (host) {
            const root = host.shadowRoot;
            if (!root) return;

            // Find all elements with aria-label (in any nested shadow)
            const labeledElements = deepQueryAll(host, '[aria-label]');
            labeledElements.forEach(function (el) {
                const ariaLabel = el.getAttribute('aria-label');
                if (!ariaLabel) return;

                if (!el.hasAttribute('alt')) {
                    el.setAttribute('alt', ariaLabel);
                }
            });
        });
    }

    function apply() {
        if (window[INIT_FLAG]) return;
        window[INIT_FLAG] = true;

        // Try repeatedly for a few seconds while Amazon Pay initializes
        let tries = 0, maxTries = 60;
        const poll = setInterval(function () {
            processHosts();
            if (++tries >= maxTries) clearInterval(poll);
        }, 250);

        const obs = new MutationObserver(processHosts);
        obs.observe(document.body, { childList: true, subtree: true });

        processHosts();
    }

    $(document).on('contentUpdated ajaxComplete', apply);
});
