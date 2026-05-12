/**
 * Posts admin list: select-all checkbox + bulk delete modal wiring.
 */
(function () {
    const form = document.getElementById('bulkSelectForm');
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.js-post-checkbox');
    const bulkBtn = document.querySelector('.js-bulk-delete-trigger');
    const counters = document.querySelectorAll('.js-bulk-count');
    const proceedBtn = document.getElementById('bulkProceedBtn');

    if (!form || !bulkBtn) {
        return;
    }

    function refreshState() {
        const checked = form.querySelectorAll('.js-post-checkbox:checked').length;
        const total = checkboxes.length;
        bulkBtn.disabled = checked === 0;
        counters.forEach(function (el) {
            el.textContent = el.classList.contains('d-none') || el.parentElement === bulkBtn
                ? '(' + checked + ')'
                : String(checked);
        });
        counters.forEach(function (el) {
            if (el.parentElement === bulkBtn) {
                el.classList.toggle('d-none', checked === 0);
            }
        });
        if (selectAll) {
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            refreshState();
        });
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', refreshState);
    });

    if (proceedBtn) {
        proceedBtn.addEventListener('click', function () {
            form.submit();
        });
    }

    refreshState();
})();
