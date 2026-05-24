/**
 * resDl — Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // --- VIP Password Modal ---
    const modalOverlay = document.getElementById('vip-modal');
    const modalClose   = document.getElementById('vip-modal-close');
    const modalForm    = document.getElementById('vip-form');
    const modalError   = document.getElementById('vip-modal-error');
    const modalResourceId = document.getElementById('vip-resource-id');

    if (modalClose) {
        modalClose.addEventListener('click', function () {
            modalOverlay.classList.remove('active');
            if (modalError) modalError.style.display = 'none';
            if (modalForm) modalForm.reset();
        });
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('active');
                if (modalError) modalError.style.display = 'none';
                if (modalForm) modalForm.reset();
            }
        });
    }

    // VIP download buttons
    document.querySelectorAll('.vip-download-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const resourceId = this.dataset.resourceId;
            const returnUrl  = this.dataset.returnUrl || '';
            if (modalResourceId) modalResourceId.value = resourceId;
            const returnInput = document.getElementById('vip-return-url');
            if (returnInput) returnInput.value = returnUrl;
            if (modalError) modalError.style.display = 'none';
            if (modalForm) modalForm.reset();
            modalOverlay.classList.add('active');
        });
    });

    // VIP form submission
    if (modalForm) {
        modalForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const resourceId = modalResourceId ? modalResourceId.value : '';
            const password   = document.getElementById('vip-password-input').value;

            if (!password) {
                if (modalError) { modalError.textContent = '请输入密码。'; modalError.style.display = 'block'; }
                return;
            }

            const formData = new FormData();
            formData.append('resource_id', resourceId);
            formData.append('password', password);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

            fetch(BASE_URL + '/ajax_verify_vip.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: formData
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    modalOverlay.classList.remove('active');
                    // Redirect to download
                    const returnUrl = document.getElementById('vip-return-url').value;
                    window.location.href = returnUrl || (BASE_URL + '/download.php?id=' + resourceId);
                } else {
                    if (modalError) { modalError.textContent = data.message || '密码错误。'; modalError.style.display = 'block'; }
                }
            })
            .catch(function () {
                if (modalError) { modalError.textContent = '网络错误，请重试。'; modalError.style.display = 'block'; }
            });
        });
    }

    // --- Confirm Delete ---
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || '确定执行此操作？')) {
                e.preventDefault();
            }
        });
    });

    // --- Auto-dismiss alerts ---
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });

    // --- Search clear button ---
    const searchInput = document.querySelector('.search-bar input[type="text"], .search-bar input[type="search"]');
    if (searchInput) {
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.textContent = '清除';
        clearBtn.className = 'btn btn-outline btn-sm';
        clearBtn.style.display = searchInput.value ? 'inline-block' : 'none';
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            window.location.href = window.location.pathname;
        });
        searchInput.parentNode.appendChild(clearBtn);
        searchInput.addEventListener('input', function () {
            clearBtn.style.display = this.value ? 'inline-block' : 'none';
        });
    }
});
