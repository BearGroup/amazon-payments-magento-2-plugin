define(['jquery', 'mage/translate', 'domReady!'], function ($, $t) {
    'use strict';

    const INIT_FLAG = '__amazonPayInit';
    const HOST_SEL  = '.amazon-checkout-button > div';

    function ensureShadowStyle(root) {
        if (root.__amazonPayStyleAdded) return;

        const style = document.createElement('style');
        style.textContent = `
            .amazonpay-button-microtext {
                margin-top: -3px;
            }
        `;

        // put it at the top of the shadow-root
        root.insertBefore(style, root.firstChild);
        root.__amazonPayStyleAdded = true;
    }

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
            ensureShadowStyle(root);

            const logoImgs = deepQueryAll(root, '.amazonpay-button-logo img');
            logoImgs.forEach(function (img) {
                img.removeAttribute('alt');
                img.setAttribute('aria-label', $t('Amazon Pay - Use your Amazon account'));
            });

            const microtextBlocks = deepQueryAll(root, '.amazonpay-button-microtext');
            microtextBlocks.forEach(function (block) {
                const img = block.querySelector('img');

                const label =
                    (img && (img.getAttribute('aria-label') || img.getAttribute('alt'))) ||
                    block.getAttribute('aria-label');

                if (!label) {
                    return;
                }

                const p = document.createElement('p');
                p.textContent = $t(label);
                p.className   = 'amazonpay-button-microtext';

                if (block.parentNode) {
                    block.parentNode.replaceChild(p, block);
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
