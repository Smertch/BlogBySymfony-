/**
 * POST /locale then force a full navigation to the redirect URL so the page
 * reloads with translations (avoids stale cached HTML / incomplete reload).
 */
(() => {
    document.querySelectorAll('form.js-site-locale-form').forEach((form) => {
        if (form.dataset.localeBound === '1') {
            return;
        }
        form.dataset.localeBound = '1';

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const fd = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                redirect: 'manual',
            })
                .then((response) => {
                    const loc = response.headers.get('Location');
                    const fallbackEl = form.querySelector('input[name="redirect"]');
                    const fallback = fallbackEl instanceof HTMLInputElement ? fallbackEl.value : '/';

                    if (loc) {
                        window.location.assign(loc);

                        return;
                    }

                    window.location.assign(fallback);
                })
                .catch(() => {
                    form.submit();
                });
        });
    });
})();
