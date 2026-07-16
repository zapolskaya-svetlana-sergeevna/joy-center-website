if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

function forceScrollTop() {
    if (!window.location.pathname.includes('cabinet.php')) {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }
}

// запуск при загруз
forceScrollTop();
setTimeout(forceScrollTop, 10);
window.addEventListener('load', forceScrollTop);
window.addEventListener('pageshow', (event) => {
    forceScrollTop();
    setTimeout(forceScrollTop, 50);
});

window.showToast = function(message, isError = false) {
    let t = document.getElementById('toast');
    if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.appendChild(t); }
    const icon = isError ? '<i class="fas fa-exclamation-circle text-danger toast-icon" style="font-size:1.5rem; margin-right:10px;"></i>' : '<i class="fas fa-check-circle toast-icon" style="color: #3D3935; font-size:1.5rem; margin-right:10px;"></i>';
    t.className = `toast-notification ${isError ? 'error' : ''}`;
    t.innerHTML = `${icon} <span style="font-family: 'Lato', sans-serif;">${message}</span>`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 4000);
}

let confirmCallback = null;

window.saveCabinetPlace = function() {
    if (!document.body.classList.contains('cabinet-page')) return;
    document.querySelectorAll('.content-tab').forEach(function(el) {
        if (el.style.display !== 'none') {
            localStorage.setItem('activeCabinetTab', el.id.replace('tab-', ''));
        }
    });
    document.querySelectorAll('.inner-tabs-container').forEach(function(container) {
        var activeIdx = Array.from(container.querySelectorAll('.inner-tab-btn')).findIndex(function(b) { return b.classList.contains('active'); });
        if (activeIdx >= 0) localStorage.setItem('innerTab_' + container.id, activeIdx);
    });
};

window.initAllInnerTabs = function() {
    document.querySelectorAll('.inner-tabs-container').forEach(function(container) {
        var saved = localStorage.getItem('innerTab_' + container.id);
        var activeIdx = Array.from(container.querySelectorAll('.inner-tab-btn')).findIndex(function(b) { return b.classList.contains('active'); });
        var index = saved !== null ? parseInt(saved, 10) : (activeIdx >= 0 ? activeIdx : 0);
        if (isNaN(index) || index < 0) index = 0;
        switchInnerTab(container.id, index);
    });
};

window.joyNavigateCabinet = function(url) {
    saveCabinetPlace();
    window.location.href = url;
};

window.joyConfirm = function(message, callback) { 
    document.getElementById('joyConfirmText').innerText = message; 
    document.getElementById('joyConfirmModal').style.display = 'flex'; 
    document.body.style.overflow = 'hidden'; 
    confirmCallback = function() {
        saveCabinetPlace();
        if (callback) callback();
    };
}
window.closeJoyConfirm = function() { 
    document.getElementById('joyConfirmModal').style.display = 'none'; 
    document.body.style.overflow = ''; 
    confirmCallback = null; 
}

const joyCustomSelectMq = { matches: true };

function joyShouldSkipCustomSelect(select) {
    if (!select || select.tagName !== 'SELECT') return true;
    if (select.multiple || select.size > 1) return true;
    if (select.dataset.joyNative === 'true') return true;
    if (select.classList.contains('country-select') || select.classList.contains('country-select-joy')) return true;
    return false;
}

function joyDestroyCustomSelect(select) {
    const wrap = select.closest('.joy-custom-select');
    if (!wrap || !wrap.parentNode) return;
    select.classList.remove('joy-custom-select__native');
    select.removeAttribute('tabindex');
    delete select.dataset.joyCustomized;
    wrap.parentNode.insertBefore(select, wrap);
    wrap.remove();
}

function joyRefreshCustomSelect(selectOrId) {
    const el = typeof selectOrId === 'string' ? document.getElementById(selectOrId) : selectOrId;
    if (!el || el.tagName !== 'SELECT') return;
    const wrap = el.closest('.joy-custom-select');
    if (wrap && typeof wrap._joySync === 'function') {
        wrap._joySync();
        return;
    }
    if (joyCustomSelectMq.matches && !joyShouldSkipCustomSelect(el)) {
        joyBuildCustomSelect(el);
    }
}

function joyBuildCustomSelect(select) {
    if (!joyCustomSelectMq.matches || joyShouldSkipCustomSelect(select)) return;
    if (select.dataset.joyCustomized === 'true') {
        joyRefreshCustomSelect(select);
        return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'joy-custom-select';
    if (select.classList.contains('form-control-sm')) wrap.classList.add('joy-custom-select--sm');
    if (select.classList.contains('w-auto') || select.classList.contains('filter-select-fixed') || select.classList.contains('filter-select-spec')) {
        wrap.style.width = 'auto';
        wrap.style.display = 'inline-block';
        wrap.style.maxWidth = '100%';
    }
    const parent = select.parentNode;
    parent.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('joy-custom-select__native');
    select.setAttribute('tabindex', '-1');
    select.dataset.joyCustomized = 'true';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'joy-custom-select__trigger';
    const list = document.createElement('ul');
    list.className = 'joy-custom-select__list';
    list.setAttribute('role', 'listbox');
    wrap.appendChild(trigger);
    wrap.appendChild(list);

    function closeList() {
        wrap.classList.remove('is-open');
    }
    function openList() {
        document.querySelectorAll('.joy-custom-select.is-open').forEach(function(w) {
            if (w !== wrap) w.classList.remove('is-open');
        });
        wrap.classList.add('is-open');
    }

    function sync() {
        list.innerHTML = '';
        const opts = Array.from(select.options);
        let label = '';
        opts.forEach(function(opt, index) {
            const li = document.createElement('li');
            li.className = 'joy-custom-select__option';
            li.setAttribute('role', 'option');
            if (opt.disabled) li.classList.add('is-disabled');
            if (opt.selected) {
                li.classList.add('is-selected');
                label = opt.textContent;
            }
            li.textContent = opt.textContent;
            if (!opt.disabled) {
                li.addEventListener('mousedown', function(e) { e.preventDefault(); });
                li.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    select.selectedIndex = index;
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    sync();
                    closeList();
                });
            }
            list.appendChild(li);
        });
        if (!label && opts.length) label = opts[0].textContent;
        trigger.textContent = label;
        trigger.disabled = select.disabled;
    }

    wrap._joySync = sync;
    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (select.disabled) return;
        if (wrap.classList.contains('is-open')) closeList();
        else {
            sync();
            openList();
        }
    });

    select.addEventListener('change', sync);
    sync();
}

window.refreshCustomSelect = joyRefreshCustomSelect;
window.initJoyCustomSelects = function(root) {
    if (!joyCustomSelectMq.matches) return;
    const scope = root || document;
    scope.querySelectorAll('select').forEach(function(sel) {
        if (!joyShouldSkipCustomSelect(sel)) joyBuildCustomSelect(sel);
    });
};

function joySyncAllCustomSelectsMode() {
    if (joyCustomSelectMq.matches) {
        initJoyCustomSelects();
    } else {
        document.querySelectorAll('select.joy-custom-select__native').forEach(joyDestroyCustomSelect);
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.joy-custom-select')) {
        document.querySelectorAll('.joy-custom-select.is-open').forEach(function(w) {
            w.classList.remove('is-open');
        });
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.joy-custom-select.is-open').forEach(function(w) {
            w.classList.remove('is-open');
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const savedTab = localStorage.getItem('activeCabinetTab');

    let defaultTab = 'sessions'; 
    if (document.querySelector('[onclick*="admin-appointments"]')) defaultTab = 'admin-appointments';
    if (document.querySelector('[onclick*="psych-appointments"]')) defaultTab = 'psych-appointments';

    const tabToOpen = tabFromUrl || savedTab || defaultTab;
    
    if (document.getElementById('tab-' + tabToOpen)) {
        showTab(tabToOpen);
    } else {
        showTab(defaultTab);
    }

    if (document.body.classList.contains('cabinet-page')) {
        initAllInnerTabs();
        window.addEventListener('resize', function() {
            if (typeof joyRefreshVisibleInnerTabSliders === 'function') joyRefreshVisibleInnerTabSliders();
        });
        document.querySelectorAll('form[method="POST"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (form.id === 'psychSlotForm' && !syncPsychSlotForm()) {
                    e.preventDefault();
                    return;
                }
                saveCabinetPlace();
                var tab = localStorage.getItem('activeCabinetTab');
                if (tab && !form.querySelector('input[name="_cabinet_tab"]')) {
                    var h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = '_cabinet_tab';
                    h.value = tab;
                    form.appendChild(h);
                }
                document.querySelectorAll('.inner-tabs-container').forEach(function(container) {
                    var idx = localStorage.getItem('innerTab_' + container.id);
                    if (idx === null || form.querySelector('input[name="_cabinet_inner_id"][value="' + container.id + '"]')) return;
                    var hi = document.createElement('input');
                    hi.type = 'hidden';
                    hi.name = '_cabinet_inner_id';
                    hi.value = container.id;
                    form.appendChild(hi);
                    var hv = document.createElement('input');
                    hv.type = 'hidden';
                    hv.name = '_cabinet_inner';
                    hv.value = idx;
                    form.appendChild(hv);
                });
            });
        });
    }

    if (window.location.pathname.includes('cabinet.php')) {
        const scrollPos = localStorage.getItem('cabinetScrollPos');
        if (scrollPos) {
            setTimeout(() => window.scrollTo(0, parseInt(scrollPos)), 50);
            localStorage.removeItem('cabinetScrollPos');
        }
        window.addEventListener('beforeunload', () => {
            localStorage.setItem('cabinetScrollPos', window.scrollY);
        });
    }

    // общ иниц 
    const confBtn = document.getElementById('joyConfirmBtn');
    if(confBtn) confBtn.addEventListener('click', function() { if (confirmCallback) confirmCallback(); closeJoyConfirm(); });

    if (localStorage.getItem('flashToast')) {
        const flash = JSON.parse(localStorage.getItem('flashToast'));
        showToast(flash.msg, flash.isError);
        localStorage.removeItem('flashToast');
    }

    if (localStorage.getItem('openAuth')) {
        const authMode = localStorage.getItem('openAuth');
        localStorage.removeItem('openAuth');
        if (typeof openAuthModal === 'function') openAuthModal();
        if (typeof toggleAuth === 'function') toggleAuth(authMode === 'forgot' ? 'forgot' : 'login');
    }

    if (urlParams.has('order_success')) { 
        localStorage.removeItem('joyCart'); 
        localStorage.setItem('activeCabinetTab', 'my-orders'); 
        const modal = document.getElementById('orderSuccessModal');
        if (modal) { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
        window.history.replaceState({}, document.title, window.location.pathname); 
    }
    if (urlParams.has('appoint_success')) { showToast('Заявка отправлена! Ожидайте подтверждения.', false); window.history.replaceState({}, document.title, window.location.pathname); }

    // гамбург
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobileNav');
    if (hamburger && mobileNav) {
        const closeMobileMenu = () => {
            hamburger.classList.remove('active'); mobileNav.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        };
        hamburger.addEventListener('click', () => {
            if (mobileNav.classList.contains('active')) closeMobileMenu();
            else { hamburger.classList.add('active'); mobileNav.classList.add('active'); document.body.classList.add('mobile-menu-open'); }
        });
        mobileNav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMobileMenu));
    }

    // маски окна
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput && typeof IMask !== 'undefined') {
        const countrySelector = document.getElementById('countrySelector');
        let phoneMask = IMask(phoneInput, { mask: '+{375} (00) 000-00-00' });
        if (countrySelector) {
            countrySelector.addEventListener('change', function() { 
                phoneMask.value = ''; 
                phoneMask.updateOptions({ mask: this.value === 'ru' ? '+{7} (000) 000-00-00' : '+{375} (00) 000-00-00' }); 
            });
        }
    }

    const specSelect = document.getElementById('appointSpecialistId');
    if (specSelect) {
        specSelect.addEventListener('change', function() {
            const specId = parseInt(this.value) || 0;
            if (typeof joyFillAppointSlots === 'function') joyFillAppointSlots(specId);
            if (typeof filterServicesBySpecialist === 'function') filterServicesBySpecialist();
        });
    }

    // корзина
    const cabinetSidebar = document.querySelector('.joy-sidebar');
    if (cabinetSidebar && document.body.classList.contains('cabinet-page')) {
        const cabinetMq = window.matchMedia('(max-width: 991.98px)');
        cabinetSidebar.addEventListener('mouseenter', () => { if(cabinetMq.matches) document.body.classList.add('sidebar-overlay-open'); });
        cabinetSidebar.addEventListener('mouseleave', () => { if(cabinetMq.matches) document.body.classList.remove('sidebar-overlay-open'); });
    }

    updateCartCount();
    if (document.getElementById('cartItemsContainer')) renderCart();

    if (typeof $ !== 'undefined' && $('#postContent').length) {
        $('#postContent').summernote({ lang: 'ru-RU', height: 300 });
    }
    
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', e => { var fileName = e.target.files[0].name; e.target.nextElementSibling.innerText = fileName; });
    });

    initTypewriter();
    initHomeCarousel();
    joySyncAllCustomSelectsMode();

    if (document.getElementById('calGrid') && typeof renderPsychCalendar === 'function') {
        renderPsychCalendar();
        var today = new Date();
        var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        if (typeof selectCalDay === 'function') selectCalDay(todayStr);
    }

    var resizeInnerTabsTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeInnerTabsTimer);
        resizeInnerTabsTimer = setTimeout(function() {
            if (typeof initAllInnerTabs === 'function') initAllInnerTabs();
        }, 120);
    });
});

window.openAppointmentForSpec = function(specId, specName, slotId = null, slotTimeStr = null, e) {
    if(e) e.preventDefault();
    if (typeof isLoggedIn !== 'undefined' && !isLoggedIn) { showToast("Для записи к специалисту необходимо авторизоваться.", true); openAuthModal(); return; } 
    let specSelect = document.getElementById('appointSpecialistId');
    if(specSelect) { specSelect.value = specId; specSelect.dispatchEvent(new Event('change')); }
    setTimeout(() => {
        let slotSelect = document.getElementById('appointSlotDropdown');
        if (slotSelect && slotId) slotSelect.value = slotId;
        if (typeof filterServicesBySpecialist === 'function') filterServicesBySpecialist();
        if (typeof refreshCustomSelect === 'function') {
            refreshCustomSelect('appointSpecialistId');
            refreshCustomSelect('appointSlotDropdown');
            refreshCustomSelect('appointServiceType');
            refreshCustomSelect('appointTopic');
        }
    }, 150);
    let titleEl = document.querySelector('#appointmentModal .form-title'); if(titleEl) titleEl.innerHTML = `ЗАПИСЬ К ПСИХОЛОГУ`;
    document.getElementById('appointmentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    if (typeof initJoyCustomSelects === 'function') {
        setTimeout(function() { initJoyCustomSelects(document.getElementById('appointmentModal')); }, 50);
    }
}

window.openAppointment = function(e) { 
    if(e) e.preventDefault(); 
    if (typeof isLoggedIn !== 'undefined' && !isLoggedIn) { showToast("Для записи на сессию необходимо войти в аккаунт.", true); openAuthModal(); return; }
    document.getElementById('appointmentModal').style.display = 'flex'; 
    document.body.style.overflow = 'hidden'; 
    if(typeof filterSpecialistsByService === 'function') filterSpecialistsByService();
    if(typeof filterServicesBySpecialist === 'function') filterServicesBySpecialist();
}

window.openAuthModal = function(e) { 
    if(e) e.preventDefault(); 
    const m = document.getElementById('registrationForm'); 
    if(m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; } 
}

window.closeAuthModal = function() { 
    const m = document.getElementById('registrationForm'); 
    if(m) { m.style.display = 'none'; document.body.style.overflow = ''; } 
}

window.closeMeditationModal = function() { const m = document.getElementById('meditationModal'); if(m) { m.style.display = 'none'; document.body.style.overflow = ''; } }

// закр формы
window.closeAnyModal = function(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = '';
}

window.toggleAuth = function(type) {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotForm = document.getElementById('forgotForm');
    const tabLogin = document.getElementById('tabLogin');
    const tabRegister = document.getElementById('tabRegister');
    const authTabs = document.querySelector('.auth-tabs');
    if (!loginForm) return;

    loginForm.style.display = type === 'login' ? 'block' : 'none';
    if (registerForm) registerForm.style.display = type === 'register' ? 'block' : 'none';
    if (forgotForm) forgotForm.style.display = type === 'forgot' ? 'block' : 'none';
    if (authTabs) authTabs.style.display = type === 'forgot' ? 'none' : 'flex';
    if (tabLogin) tabLogin.style.borderBottom = type === 'login' ? '2px solid #E0C6AD' : 'none';
    if (tabRegister) tabRegister.style.borderBottom = type === 'register' ? '2px solid #E0C6AD' : 'none';
}

window.flipResult = function(circle) { if(circle) circle.classList.toggle('flipped'); }
window.toggleHiddenText = function() { const t = document.getElementById('hiddenText'); t.style.display = t.style.display === 'block' ? 'none' : 'block'; }

// выбор медитации в мод
let selectedMeditation = null;
window.selectOption = function(el) {
    document.querySelectorAll('.meditation-section .option').forEach(o => o.style.border = 'none');
    el.style.border = '3px solid #E0C6AD';
    el.style.borderRadius = '15px';
    selectedMeditation = { id: el.getAttribute('data-id'), price: el.getAttribute('data-price'), title: el.nextElementSibling.innerText.replace('\n', ' '), image: el.querySelector('img').src };
}
window.addSelectedMeditationToCart = function() {
    if(!selectedMeditation) { showToast('Пожалуйста, выберите медитацию (нажмите на картинку)', true); return; }
    addToCart(selectedMeditation.id, selectedMeditation.title, selectedMeditation.price, selectedMeditation.image);
    closeMeditationModal();
}

window.addToCart = function(id, title, price, image) {
    // Проверка входа
    if (typeof isLoggedIn === 'undefined' || !isLoggedIn) { 
        showToast("Пожалуйста, авторизуйтесь для покупки.", true); 
        openAuthModal(); 
        return; 
    }
    
    let formData = new FormData();
    formData.append('product_id', id);

    fetch('cart_handler.php?action=add', { 
        method: 'POST', 
        body: formData 
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartCount(); // Обновляем цифру на иконке
            showToast('Добавлено в корзину!', false);
        } else {
            console.error("Ошибка сервера:", data.message);
            showToast("Ошибка при добавлении", true);
        }
    })
    .catch(err => {
        console.error("Ошибка запроса:", err);
    });
}

window.updateCartCount = function() {
    if (typeof isLoggedIn === 'undefined' || !isLoggedIn) return;
    
    fetch('cart_handler.php?action=get')
    .then(res => res.json())
    .then(data => {
        const el = document.getElementById('cartCount');
        if(el) {
            const count = data.items ? data.items.length : 0;
            el.innerText = count;
            el.style.display = count > 0 ? 'inline-block' : 'none';
        }
    })
    .catch(err => console.error("Ошибка счета корзины:", err));
}

window.updateCartCount = function() {
    if (!isLoggedIn) return;
    fetch('cart_handler.php?action=get')
    .then(res => res.json())
    .then(data => {
        const el = document.getElementById('cartCount');
        if(el && data.items) {
            el.innerText = data.items.length;
            el.style.display = data.items.length > 0 ? 'inline-block' : 'none';
        }
    });
}
window.renderCart = function() {
    const c = document.getElementById('cartItemsContainer'); 
    const t = document.getElementById('cartTotal'); 
    const f = document.getElementById('cartFooter'); 
    if (!c) return;

    fetch('cart_handler.php?action=get')
    .then(res => res.json())
    .then(data => {
        c.innerHTML = '';
        let total = 0;

        // Если корзина пуста
        if (!data.items || data.items.length === 0) {
            c.innerHTML = `
                <div class="empty-state" style="border: none; padding: 40px 0;">
                    <i class="fas fa-shopping-basket empty-icon"></i>
                    <h4 class="empty-text">Ваша корзина пуста</h4>
                    <p class="text-muted small">Добавьте медитации или курсы, чтобы начать обучение.</p>
                    <a href="catalog.php" class="main-button small-btn mt-3" style="display: inline-block; text-decoration: none;">Перейти в каталог</a>
                </div>`;
            
            if (f) f.style.display = 'none';
            
        } else {
            if (f) f.style.display = 'block';
            
            data.items.forEach(item => {
                total += parseInt(item.price);
                c.innerHTML += `
                <div class="cart-item-row d-flex align-items-center border-bottom py-3">
                    <img src="${item.image}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 10px; margin-right: 15px;">
                    <div class="flex-grow-1">
                        <h6 class="mb-0 font-weight-bold">${item.title}</h6>
                        <span class="text-muted">${item.price} BYN</span>
                    </div>
                    <button class="action-btn btn-delete" onclick="removeFromCart(${item.item_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>`;
            });
            if(t) t.innerText = total;
        }
    })
    .catch(err => console.error("Ошибка загрузки корзины:", err));
}

window.removeFromCart = function(itemId) {
    let formData = new FormData();
    formData.append('item_id', itemId);

    fetch('cart_handler.php?action=remove', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            renderCart();
            updateCartCount();
        }
    });
}
window.submitOrder = function() {
    // товары с бд
    fetch('cart_handler.php?action=get')
    .then(res => res.json())
    .then(data => {
        if (!data.items || data.items.length === 0) {
            showToast('Ваша корзина пуста.', true);
            return;
        }
        const cartData = data.items.map(item => ({
            id: item.product_id,
            title: item.title,
            price: item.price,
            image: item.image
        }));

        document.getElementById('hiddenCartInput').value = JSON.stringify(cartData);
        document.getElementById('orderForm').submit();
    })

    .catch(err => {
        console.error("Ошибка при подготовке заказа:", err);
        showToast("Произошла ошибка, попробуйте снова", true);
    });
}

window.showTab = function(id) {
    document.querySelectorAll('.content-tab').forEach(d => d.style.display = 'none');
    const target = document.getElementById('tab-' + id);
    if(target) target.style.display = 'block';
    
    document.querySelectorAll('.joy-nav-item').forEach(n => n.classList.remove('active'));
    const btn = document.querySelector(`.joy-nav-item[onclick*="showTab('${id}')"]`) || document.querySelector(`.joy-nav-item[onclick*="showTab('${id}'"]`);
    if(btn) btn.classList.add('active');
    
    localStorage.setItem('activeCabinetTab', id);

    if (typeof initJoyCustomSelects === 'function') {
        initJoyCustomSelects(target);
    }

    requestAnimationFrame(function() {
        if (typeof joyRefreshVisibleInnerTabSliders === 'function') joyRefreshVisibleInnerTabSliders();
    });
    
    if(id === 'cart') renderCart();
}

window.positionInnerTabSlider = function(container) {
    if (!container) return;
    const slider = container.querySelector('.inner-tab-slider');
    const active = container.querySelector('.inner-tab-btn.active');
    if (!slider || !active) return;
    const sync = function() {
        slider.style.width = active.offsetWidth + 'px';
        slider.style.left = active.offsetLeft + 'px';
    };
    sync();
    requestAnimationFrame(sync);
};

window.joyRefreshVisibleInnerTabSliders = function() {
    document.querySelectorAll('.content-tab').forEach(function(tab) {
        if (tab.style.display === 'none') return;
        tab.querySelectorAll('.inner-tabs-container').forEach(positionInnerTabSlider);
    });
};

window.switchInnerTab = function(containerId, index) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const buttons = container.querySelectorAll('.inner-tab-btn');
    const targetPrefix = container.getAttribute('data-target-prefix');

    buttons.forEach((btn, i) => {
        const block = document.getElementById(`${targetPrefix}-${i}`);
        if (i === index) { 
            btn.classList.add('active'); 
            if(block) block.classList.add('active');
        }
        else { btn.classList.remove('active'); if(block) block.classList.remove('active'); }
    });
    positionInnerTabSlider(container);
    localStorage.setItem('innerTab_' + containerId, index);

    if (containerId === 'psychAppTabs' && index === 1 && typeof renderPsychCalendar === 'function') {
        renderPsychCalendar();
        if (!selectedCalDate) {
            var today = new Date();
            var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
            selectCalDay(todayStr);
        }
    }
}

// фильтры в кабе
window.applyAdminAppFilters = function() {
    const status = document.getElementById('filterStatus').value;
    const service = document.getElementById('filterAdminService').value;
    const spec = document.getElementById('filterSpec').value;
    const client = document.getElementById('filterClient').value.toLowerCase();
    const date = document.getElementById('filterDate').value;
    const container = document.getElementById('adminAppsContainer');

    Array.from(container.querySelectorAll('.admin-app-item')).forEach(item => {
        let show = true;
        if (status !== 'all' && item.getAttribute('data-status') !== status) {
            if (status === 'callback' && item.getAttribute('data-status') === 'callback') { show = true; }
            else { show = false; }
        }
        if (show && service !== 'all' && item.getAttribute('data-service') !== service) show = false;
        if (show && spec !== 'all' && item.getAttribute('data-spec') !== spec) show = false;
        if (show && client && !item.getAttribute('data-client').includes(client)) show = false;
        if (show && date && item.getAttribute('data-appdate') !== date) show = false;
        
        item.style.display = show ? 'block' : 'none';
    });
}
window.applyAdminSchedFilters = function() {
    const specId = document.getElementById('filterAdminSchedSpec').value; const dateVal = document.getElementById('filterAdminSchedDate').value; const container = document.getElementById('adminSchedContainer');
    if(!container) return;
    Array.from(container.querySelectorAll('.admin-sched-item')).forEach(item => { let show = true; if (specId !== 'all' && item.getAttribute('data-spec') !== specId) show = false; if (dateVal && item.getAttribute('data-date') !== dateVal) show = false; item.style.display = show ? 'block' : 'none'; });
}
window.applyPsychAppFilters = function() {
    const status = document.getElementById('filterPsychStatus').value;
    const serviceType = document.getElementById('filterPsychService').value;
    const clientText = document.getElementById('filterPsychClient').value.toLowerCase();
    const dateVal = document.getElementById('filterPsychDate').value;
    const container = document.getElementById('psychAppsContainer');
    
    if(!container) return;

    Array.from(container.querySelectorAll('.psych-app-item')).forEach(item => {
        let show = true;
        const itemStatus = item.getAttribute('data-status').trim();
        const itemTopic = item.querySelector('h4').innerText;

        if (status !== 'all') {
            if (status === 'new') {
                if (itemStatus !== 'new' && !itemTopic.includes('Обратная связь')) show = false;
            } else {
                if (itemStatus !== status) show = false;
            }
        }
        
        if (serviceType !== 'all' && item.getAttribute('data-service') !== serviceType) show = false;
        if (clientText && !item.getAttribute('data-client').includes(clientText)) show = false;
        if (dateVal && item.getAttribute('data-appdate') !== dateVal) show = false;
        
        item.style.display = show ? 'block' : 'none';
    });
}
window.sortAdminOrders = function() {
    const order = document.getElementById('sortOrderDate').value; const container = document.getElementById('adminOrdersContainer'); 
    if(!container) return; let items = Array.from(container.querySelectorAll('.admin-order-item'));
    items.sort((a, b) => { return order === 'desc' ? b.getAttribute('data-time') - a.getAttribute('data-time') : a.getAttribute('data-time') - b.getAttribute('data-time'); });
    items.forEach(item => container.appendChild(item));
}
window.applyUserFilters = function() {
    const role = document.getElementById('filterUserRole').value; const text = document.getElementById('filterUserText').value.toLowerCase(); const container = document.getElementById('crmUsersContainer');
    if(!container) return;
    Array.from(container.querySelectorAll('.crm-user-item')).forEach(item => { let show = true; if (role !== 'all' && item.getAttribute('data-role') !== role) show = false; if (text && !item.getAttribute('data-search').includes(text)) show = false; item.style.display = show ? 'table-row' : 'none'; });
}

// формы ред
window.editProduct = function(data) {
    document.getElementById('productFormBlock').style.display = 'block';
    if(data) {
        document.getElementById('formTitle').innerText = 'Редактирование продукта';
        document.getElementById('prodId').value = data.id; document.getElementById('prodTitle').value = data.title;
        document.getElementById('prodPrice').value = data.price; document.getElementById('prodDesc').value = data.description;
        document.getElementById('prodImgOld').value = data.image; document.getElementById('prodCat').value = data.category || 'general';
        if(document.getElementById('prodAccessLink')) document.getElementById('prodAccessLink').value = data.access_link || '';
    } else {
        document.getElementById('formTitle').innerText = 'Добавление продукта';
        document.getElementById('productFormBlock').querySelector('form').reset(); document.getElementById('prodId').value = ''; document.getElementById('customFileProd').nextElementSibling.innerText = 'Выберите файл'; document.getElementById('prodImgOld').value = '';
    }
    document.getElementById('productFormBlock').scrollIntoView({behavior: 'smooth'});
}
window.editPost = function(data) {
    document.getElementById('postFormBlock').style.display = 'block';
    if(data) {
        document.getElementById('postFormTitle').innerText = 'Редактирование статьи';
        document.getElementById('postId').value = data.id; document.getElementById('postTitle').value = data.title;
        document.getElementById('postShortDesc').value = data.short_desc; $('#postContent').summernote('code', data.content); 
        if(document.getElementById('postAuthor')) document.getElementById('postAuthor').value = data.author_id || ''; 
        if(document.getElementById('postImgOld')) document.getElementById('postImgOld').value = data.image || '';
    } else {
        document.getElementById('postFormTitle').innerText = 'Новая статья'; document.getElementById('postFormBlock').querySelector('form').reset(); $('#postContent').summernote('code', ''); document.getElementById('postId').value = '';
        if(document.getElementById('postAuthor')) document.getElementById('postAuthor').value = ''; document.getElementById('customFilePost').nextElementSibling.innerText = 'Выберите файл';
        if(document.getElementById('postImgOld')) document.getElementById('postImgOld').value = '';
    }
    document.getElementById('postFormBlock').scrollIntoView({behavior: 'smooth'});
}
window.editSpecialist = function(data) {
    document.getElementById('specialistFormBlock').style.display = 'block';
    if(data) {
        document.getElementById('specFormTitle').innerText = 'Редактирование профиля: ' + data.first_name;
        document.getElementById('formSpecId').value = data.id;
        document.getElementById('formSpecFirstName').value = data.first_name; document.getElementById('formSpecLastName').value = data.last_name;
        document.getElementById('formSpecPatronymic').value = data.patronymic || '';
        document.getElementById('formSpecSpec').value = data.specialization; document.getElementById('formSpecExp').value = data.experience_years;
        document.getElementById('formSpecEdu').value = data.education; document.getElementById('formSpecDesc').value = data.description;
        if(document.getElementById('formSpecSched')) document.getElementById('formSpecSched').value = data.work_schedule || '';
        if(document.getElementById('formSpecImgOld')) document.getElementById('formSpecImgOld').value = data.photo || '';
        document.getElementById('newUserFields').style.display = 'none';
    } else {
        document.getElementById('specFormTitle').innerText = 'Добавление психолога'; document.getElementById('specialistFormBlock').querySelector('form').reset();
        document.getElementById('formSpecId').value = ''; document.getElementById('customFileSpec').nextElementSibling.innerText = 'Выберите файл...';
        if(document.getElementById('formSpecImgOld')) document.getElementById('formSpecImgOld').value = '';
        document.getElementById('newUserFields').style.display = 'flex';
    }
    document.getElementById('specialistFormBlock').scrollIntoView({behavior: 'smooth'});
}
window.editGroup = function(data) {
    document.getElementById('groupFormBlock').style.display = 'block';
    if(data) {
        document.getElementById('grpFormTitle').innerText = 'Редактирование группы';
        document.getElementById('formGrpId').value = data.id; document.getElementById('formGrpTitle').value = data.title;
        document.getElementById('formGrpDate').value = data.event_date ? data.event_date.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('formGrpSeats').value = data.max_seats;
        if (document.getElementById('formGrpSpec')) document.getElementById('formGrpSpec').value = data.spec_id;
        if (document.getElementById('formGrpRoom')) document.getElementById('formGrpRoom').value = data.room_id || '';
        document.getElementById('formGrpDesc').value = data.description;
    } else {
        document.getElementById('grpFormTitle').innerText = 'Новая группа'; document.getElementById('groupFormBlock').querySelector('form').reset(); document.getElementById('formGrpId').value = '';
    }
    document.getElementById('groupFormBlock').scrollIntoView({behavior: 'smooth'});
}
window.editFaq = function(id, q, a, s) {
    document.getElementById('faqFormId').value = id; document.getElementById('faqFormQ').value = q; document.getElementById('faqFormA').value = a; document.getElementById('faqFormStatus').value = s; document.getElementById('faqFormTitle').innerText = 'Редактировать вопрос';
}

// календ
let currentCalDate = new Date();
let selectedCalDate = null;

const SCHEDULE_DAY_START = 8 * 60;
const SCHEDULE_DAY_END = 21 * 60 + 30;
const SCHEDULE_STEP = 30;

function joyParseCalDt(str) {
    if (!str) return null;
    const d = new Date(String(str).trim().replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
}

function joyCalDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function joyCalTimeLabel(str) {
    const d = joyParseCalDt(str);
    if (!d) return '';
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function joyMinutesToTime(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function joyCalEventBucket(str, dateStr) {
    const d = joyParseCalDt(str);
    if (!d || joyCalDateStr(d) !== dateStr) return null;
    const total = d.getHours() * 60 + d.getMinutes();
    return Math.floor(total / SCHEDULE_STEP) * SCHEDULE_STEP;
}

function joyScheduleAppMeta(app) {
    const exact = joyCalTimeLabel(app.time);
    if (app.isGroup) {
        return { type: 'booked', exact, tag: 'Группа', sub: app.topic || 'Занятие' };
    }
    switch (app.status) {
        case 'completed':
            return { type: 'completed', exact, tag: 'Завершена', sub: app.client || 'Клиент', appId: app.id || 0 };
        case 'new':
            return { type: 'pending', exact, tag: 'Запрос', sub: app.client || 'Клиент', appId: app.id || 0 };
        case 'assigned':
            return { type: 'assigned', exact, tag: 'Запись', sub: app.client || 'Клиент', appId: app.id || 0 };
        case 'confirmed':
        default:
            return { type: 'booked', exact, tag: 'Запись', sub: app.client || 'Клиент', appId: app.id || 0 };
    }
}

function joyScheduleBucketItems(bucketMins, dateStr) {
    const items = [];
    if (typeof globalPsychApps !== 'undefined') {
        globalPsychApps.forEach(function(app) {
            if (joyCalEventBucket(app.time, dateStr) !== bucketMins) return;
            items.push(Object.assign({ kind: 'app' }, joyScheduleAppMeta(app)));
        });
    }
    if (typeof globalPsychSlots !== 'undefined') {
        globalPsychSlots.forEach(function(slot) {
            if (joyCalEventBucket(slot.time, dateStr) !== bucketMins) return;
            const exact = joyCalTimeLabel(slot.time);
            const dup = items.some(function(it) { return it.exact === exact; });
            if (!dup) {
                items.push({ kind: 'slot', type: 'open', exact, tag: 'Окно', sub: slot.notes || 'Свободно', slotId: slot.id || 0 });
            }
        });
    }
    const rank = { completed: 0, booked: 1, assigned: 2, pending: 3, open: 4 };
    items.sort(function(a, b) { return (rank[a.type] ?? 9) - (rank[b.type] ?? 9); });
    return items;
}

function joyScheduleCellActionsHtml(dateStr, bucketTime, items, isPast) {
    if (typeof psychCalEnabled === 'undefined' || isPast) return '';
    const primary = items.length ? items[0] : null;
    const dt = dateStr + ' ' + bucketTime;
    let btns = '';
    const mkBtn = (action, label, cls) =>
        `<button type="button" class="schedule-cell__act ${cls}" onclick="psychCalAction('${action}','${dateStr}','${bucketTime}')" title="${label}">${label}</button>`;

    if (!primary || primary.type === 'free') {
        btns += mkBtn('open', 'Окно', 'schedule-cell__act--open');
        btns += mkBtn('walkin', 'Запись', 'schedule-cell__act--book');
    } else if (primary.type === 'open') {
        btns += mkBtn('walkin', 'Запись', 'schedule-cell__act--book');
        btns += mkBtn('free', 'Свободно', 'schedule-cell__act--free');
    } else if (primary.type === 'completed') {
        btns += mkBtn('free', 'Свободно', 'schedule-cell__act--free');
    } else {
        btns += mkBtn('complete', 'Завершено', 'schedule-cell__act--done');
        btns += mkBtn('free', 'Свободно', 'schedule-cell__act--free');
    }
    return btns ? `<div class="schedule-cell__actions">${btns}</div>` : '';
}

function joyScheduleCellHtml(bucketMins, dateStr, now) {
    const bucketTime = joyMinutesToTime(bucketMins);
    const slotDt = joyParseCalDt(dateStr + ' ' + bucketTime + ':00');
    const isPast = slotDt && slotDt < now;
    const items = joyScheduleBucketItems(bucketMins, dateStr);

    let state = 'free';
    let tagHtml = '';
    let subHtml = '<span class="schedule-cell__sub">Свободно</span>';
    let onClick = typeof psychCalEnabled === 'undefined' ? `onclick="prefillPsychSlotTime('${bucketTime}')"` : '';

    if (items.length > 0) {
        const primary = items[0];
        state = primary.type;
        onClick = '';
        const showExact = primary.exact !== bucketTime ? primary.exact + ' · ' : '';
        tagHtml = `<span class="schedule-cell__tag">${showExact}${primary.tag}</span>`;
        subHtml = `<span class="schedule-cell__sub">${primary.sub || ''}</span>`;
        if (items.length > 1) {
            subHtml += `<span class="schedule-cell__sub">+${items.length - 1} ещё</span>`;
        }
    } else if (isPast) {
        state = 'past';
        onClick = '';
        tagHtml = '';
        subHtml = '';
    }

    const multiClass = items.length > 1 ? ' schedule-cell--multi' : '';
    const actionsHtml = joyScheduleCellActionsHtml(dateStr, bucketTime, items, isPast);
    return `<div class="schedule-cell schedule-cell--${state}${multiClass}" ${onClick} title="${items.length ? items.map(i => i.exact + ' ' + i.tag).join(', ') : 'Открыть окно'}">
        <span class="schedule-cell__time">${bucketTime}</span>
        ${tagHtml}
        ${subHtml}
        ${actionsHtml}
    </div>`;
}

function joyScheduleLegendHtml() {
    return `<div class="schedule-legend">
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--open"></span>Окно</span>
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--booked"></span>Запись</span>
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--assigned"></span>Назначено админом</span>
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--pending"></span>Запрос</span>
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--completed"></span>Завершена</span>
        <span class="schedule-legend__item"><span class="schedule-legend__dot schedule-legend__dot--free"></span>Свободно</span>
    </div>`;
}

window.psychCalAction = function(action, dateStr, timeStr) {
    const form = document.getElementById('psychCalActionForm');
    if (!form) return;
    const labels = { open: 'Открыть окно для записи?', walkin: 'Отметить как запись на месте?', complete: 'Завершить сессию?', free: 'Освободить ячейку?' };
    const msg = labels[action] || 'Подтвердить действие?';
    if (typeof joyConfirm === 'function') {
        joyConfirm(msg, function() {
            document.getElementById('psychCalActionType').value = action;
            document.getElementById('psychCalActionDt').value = dateStr + 'T' + timeStr;
            form.submit();
        });
    } else if (confirm(msg)) {
        document.getElementById('psychCalActionType').value = action;
        document.getElementById('psychCalActionDt').value = dateStr + 'T' + timeStr;
        form.submit();
    }
};

window.renderPsychCalendar = function() {
    const grid = document.getElementById('calGrid');
    const monthYearText = document.getElementById('calMonthYear');
    if (!grid) return;

    grid.innerHTML = '';
    const year = currentCalDate.getFullYear(); const month = currentCalDate.getMonth();
    const monthNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
    monthYearText.innerText = `${monthNames[month]} ${year}`;

    const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    days.forEach(d => { grid.innerHTML += `<div class="calendar-day-name">${d}</div>`; });

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let startOffset = firstDay === 0 ? 6 : firstDay - 1;

    for (let i = 0; i < startOffset; i++) grid.innerHTML += `<div class="calendar-day empty"></div>`;

    let offDays = JSON.parse(localStorage.getItem('psychOffDays')) || [];

    for (let day = 1; day <= daysInMonth; day++) {
        const fullDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        let hasEvent = false;
        if (typeof globalPsychApps !== 'undefined') { hasEvent = globalPsychApps.some(app => app.date === fullDateStr); }
        if (!hasEvent && typeof globalPsychSlots !== 'undefined') {
            hasEvent = globalPsychSlots.some(s => {
                const d = joyParseCalDt(s.time);
                return d && joyCalDateStr(d) === fullDateStr;
            });
        }
        
        let isOffDay = offDays.includes(fullDateStr);
        
        let cls = 'calendar-day';
        if (selectedCalDate === fullDateStr) { cls += ' selected'; } 
        else if (isOffDay) { cls += ' day-off'; } 
        else if (hasEvent) { cls += ' has-event'; }

        grid.innerHTML += `<div class="${cls}" onclick="selectCalDay('${fullDateStr}')" title="${isOffDay ? 'Выходной' : ''}">${day}</div>`;
    }
}

window.prevMonth = function() { currentCalDate.setMonth(currentCalDate.getMonth() - 1); renderPsychCalendar(); }
window.nextMonth = function() { currentCalDate.setMonth(currentCalDate.getMonth() + 1); renderPsychCalendar(); }

window.selectCalDay = function(dateStr) {
    selectedCalDate = dateStr;
    renderPsychCalendar();

    const dayView = document.getElementById('psychDayView');
    const dayTitle = document.getElementById('dayViewTitle');
    const timeline = document.getElementById('dailyTimeline');
    const slotPanel = document.getElementById('psychSlotOpenPanel');
    dayView.style.display = 'flex';
    dayView.classList.add('calendar-right--open');
    const d = dateStr.split('-');
    dayTitle.innerText = `Расписание на ${d[2]}.${d[1]}.${d[0]}`;
    timeline.innerHTML = '';

    const offDays = JSON.parse(localStorage.getItem('psychOffDays')) || [];
    const isOffDay = offDays.includes(dateStr);

    if (slotPanel) slotPanel.style.display = isOffDay ? 'none' : 'block';
    if (!isOffDay) prefillPsychSlotTime('09:00');

    timeline.innerHTML += `<button type="button" class="btn ${isOffDay ? 'btn-success' : 'btn-outline-secondary'} btn-sm mb-2 w-100" onclick="toggleOffDay('${dateStr}')" style="border-radius:15px;">
        <i class="fas ${isOffDay ? 'fa-check' : 'fa-bed'}"></i> ${isOffDay ? 'Сделать рабочим днем' : 'Отметить как выходной'}</button>`;

    if (isOffDay) {
        timeline.innerHTML += `<div class="text-center text-muted p-4"><i class="fas fa-mug-hot fa-2x mb-2"></i><br>Это ваш выходной день.</div>`;
        return;
    }

    const now = new Date();
    let gridHtml = joyScheduleLegendHtml() + '<div class="schedule-grid">';
    for (let mins = SCHEDULE_DAY_START; mins <= SCHEDULE_DAY_END; mins += SCHEDULE_STEP) {
        gridHtml += joyScheduleCellHtml(mins, dateStr, now);
    }
    gridHtml += '</div>';
    timeline.innerHTML += gridHtml;
};

window.toggleOffDay = function(dateStr) {
    let offDays = JSON.parse(localStorage.getItem('psychOffDays')) || [];
    if (offDays.includes(dateStr)) offDays = offDays.filter(function(d) { return d !== dateStr; });
    else offDays.push(dateStr);
    localStorage.setItem('psychOffDays', JSON.stringify(offDays));
    selectCalDay(dateStr);
};

window.prefillPsychSlotTime = function(timeStr) {
    const input = document.getElementById('psychCustomSlotTime');
    if (input) input.value = timeStr || '09:00';
    const panel = document.getElementById('psychSlotOpenPanel');
    if (panel) {
        panel.classList.add('psych-slot-open-form--highlight');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function() { panel.classList.remove('psych-slot-open-form--highlight'); }, 1200);
    }
    if (input) input.focus();
};

window.syncPsychSlotForm = function() {
    const hidden = document.getElementById('psychSlotDateTime');
    const timeInput = document.getElementById('psychCustomSlotTime');
    if (!hidden || !timeInput || !selectedCalDate) return false;
    if (!timeInput.value) return false;
    hidden.value = selectedCalDate + 'T' + timeInput.value;
    return true;
};

// модалка уведов
window.openClientsModal = function() { 
    document.getElementById('clientsModalOverlay').style.display = 'flex'; 
    document.body.style.overflow = 'hidden';
    if (typeof initJoyCustomSelects === 'function') {
        setTimeout(function() {
            initJoyCustomSelects(document.getElementById('clientsModalOverlay'));
        }, 50);
    }
}
window.closeClientsModal = function() { 
    document.getElementById('clientsModalOverlay').style.display = 'none'; 
    document.body.style.overflow = ''; 
}
window.filterNotifClients = function() {
    const text = document.getElementById('notifClientSearch').value.toLowerCase();
    const groupFilter = document.getElementById('notifGroupFilter').value;
    const items = document.querySelectorAll('.notif-client-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const groupId = item.getAttribute('data-group') || '';
        const isTomorrow = item.getAttribute('data-tomorrow');
        let show = true;
        if (text && !name.includes(text)) show = false;
        if (groupFilter === 'tomorrow') {
            if (isTomorrow !== 'yes') show = false;
        } else if (groupFilter === 'waitlist') {
            if (groupId.indexOf('waitlist') !== 0) show = false;
        } else if (groupFilter === 'material') {
            if (groupId !== 'material') show = false;
        } else if (groupFilter.startsWith('group_')) {
            const gid = groupFilter.replace('group_', '');
            if (groupId !== groupFilter && groupId !== 'waitlist_group_' + gid) show = false;
        } else if (groupFilter !== 'all' && groupFilter !== 'session' && groupId !== groupFilter) {
            show = false;
        }
        if (groupFilter === 'session' && groupId !== 'session') show = false;
        item.style.display = show ? 'block' : 'none';
    });
}
const NOTIF_TEMPLATES = {
    reminder: 'Здравствуйте, {имя}! Напоминаем о вашей записи в J.O.Y. Center на {дата} в {время}. Услуга: {услуга}. Кабинет: {кабинет}. Специалист: {специалист}. Ждём вас по адресу: {адрес}.',
    waitlist: 'Здравствуйте, {имя}! Благодарим вас за интерес к групповой терапии в J.O.Y. Center. К сожалению, все места на ближайшее занятие уже заняты. Ваша заявка сохранена в очереди — мы сообщим, как только откроется новая группа или освободится место. Спасибо за понимание и ожидайте следующих наборов!',
    material: 'Здравствуйте, {имя}! Для вас открыт доступ к материалам в J.O.Y. Center. Вы можете открыть их в любое время в вашем Личном кабинете во вкладке «Мои материалы».'
};
window.applyNotifTemplate = function(key) {
    const ta = document.getElementById('notifMessageText');
    if (!ta || !NOTIF_TEMPLATES[key]) return;
    ta.value = NOTIF_TEMPLATES[key];
    ta.focus();
};

window.selectAllNotifClients = function() {
    const checkboxes = document.querySelectorAll('.notif-client-item input[type="checkbox"]');
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => cb.closest('.notif-client-item').style.display !== 'none');
    if(visibleCheckboxes.length === 0) return;
    const allChecked = visibleCheckboxes.every(cb => cb.checked);
    visibleCheckboxes.forEach(cb => cb.checked = !allChecked);
    updateClientsBtnCount();
}
document.addEventListener('change', function(e) { if (e.target.matches('.notif-client-item input[type="checkbox"]')) { updateClientsBtnCount(); } });
function updateClientsBtnCount() { const checked = document.querySelectorAll('.notif-client-item input[type="checkbox"]:checked').length; const btn = document.getElementById('btnSelectClients'); if(btn) btn.innerText = `Отправить рассылку (${checked})`; }

document.addEventListener('DOMContentLoaded', function() {
    const notifForm = document.getElementById('notifForm');
    if (notifForm) {
        notifForm.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.notif-client-item input[type="checkbox"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                if (typeof showToast === 'function') showToast('Выберите хотя бы одного получателя', true);
                return;
            }
            const btn = document.getElementById('btnSelectClients');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerText = 'Отправка...';
            }
        });
    }
});

window.openAppointmentForService = function(serviceTitle, e) {
    if(e) e.preventDefault();
    if (typeof isLoggedIn !== 'undefined' && !isLoggedIn) { showToast("Для записи необходимо авторизоваться.", true); openAuthModal(); return; }
    document.getElementById('appointmentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; 
    
    const sTypeSelect = document.getElementById('appointServiceType');
    if (sTypeSelect) {
        let titleLower = serviceTitle.toLowerCase();
        if (titleLower.includes('онлайн')) { sTypeSelect.value = 'Онлайн сессия'; } 
        else if (joySpecDoesCoupleTherapy(titleLower)) { sTypeSelect.value = 'Парная терапия'; } 
        else { sTypeSelect.value = 'Очная индивидуальная сессия'; }
        filterSpecialistsByService();
        if (typeof filterServicesBySpecialist === 'function') filterServicesBySpecialist();
    }
}

window.filterSpecialistsByService = function() {
    const serviceTypeSelect = document.getElementById('appointServiceType');
    const specSelect = document.getElementById('appointSpecialistId');
    if (!specSelect || !serviceTypeSelect) return;
    const serviceType = serviceTypeSelect.value;
    const prevSpec = specSelect.value;
    const options = specSelect.querySelectorAll('option:not([disabled])');
    
    options.forEach(opt => {
        const searchStr = opt.getAttribute('data-search') || '';
        let show = true;
        if (serviceType === 'Парная терапия') {
            if (!joySpecDoesCoupleTherapy(searchStr)) { show = false; }
        }
        opt.style.display = show ? 'block' : 'none';
        opt.hidden = !show;
    });

    const prevStillValid = prevSpec && Array.from(options).some(o => o.value === prevSpec && o.style.display !== 'none' && !o.hidden);
    if (prevStillValid) {
        specSelect.value = prevSpec;
        joyFillAppointSlots(parseInt(prevSpec, 10));
    } else {
        specSelect.value = '';
        joyFillAppointSlots(0);
    }
    if (typeof refreshCustomSelect === 'function') refreshCustomSelect('appointSpecialistId');
};

window.filterServicesBySpecialist = function() {
    const specSelect = document.getElementById('appointSpecialistId');
    const serviceSelect = document.getElementById('appointServiceType');
    if (!specSelect || !serviceSelect) return;
    const specId = parseInt(specSelect.value, 10) || 0;
    const selectedOpt = specSelect.options[specSelect.selectedIndex];
    const searchStr = selectedOpt ? (selectedOpt.getAttribute('data-search') || '') : '';
    const doesCouple = specId > 0 ? joySpecDoesCoupleTherapy(searchStr) : true;

    serviceSelect.querySelectorAll('option').forEach(opt => {
        const isCouple = opt.value === 'Парная терапия';
        const show = !isCouple || doesCouple;
        opt.style.display = show ? 'block' : 'none';
        opt.hidden = !show;
    });

    if (serviceSelect.value === 'Парная терапия' && !doesCouple) {
        serviceSelect.value = 'Очная индивидуальная сессия';
    }
    if (typeof refreshCustomSelect === 'function') refreshCustomSelect('appointServiceType');
};

window.JOY_COUPLE_THERAPY_KEYWORDS = [
    'семейн', 'семей', 'семья', 'семьи',
    'парн', 'парная', 'парной', 'парные', 'парную', 'парный',
    'отношен', 'отношени',
    'супруж', 'супруг', 'супружеск',
    'брак', 'развод',
    'родител', 'родствен', 'детско-род',
    'сожител', 'партнер', 'партнёр',
    'family', 'couple'
];

window.joySpecDoesCoupleTherapy = function(searchStr) {
    if (!searchStr) return false;
    const s = searchStr.toLowerCase();
    return window.JOY_COUPLE_THERAPY_KEYWORDS.some(function(kw) { return s.includes(kw); });
};

window.joyFillAppointSlots = function(specId) {
    const slotSelect = document.getElementById('appointSlotDropdown');
    const slotContainer = document.getElementById('slotDropdownContainer');
    if (!slotSelect || typeof availableGlobalSlots === 'undefined') return;
    const prevSlot = slotSelect.value;
    slotSelect.innerHTML = '<option value="" disabled selected>Выберите свободное время</option>';
    if (specId > 0) {
        availableGlobalSlots.filter(s => s.specialist_id == specId).forEach(slot => {
            const dateObj = new Date(String(slot.slot_datetime).replace(' ', 'T'));
            slotSelect.innerHTML += `<option value="${slot.id}">${dateObj.toLocaleString('ru-RU')}</option>`;
        });
        if (slotContainer) slotContainer.style.display = 'block';
        if (prevSlot && slotSelect.querySelector(`option[value="${prevSlot}"]`)) {
            slotSelect.value = prevSlot;
        }
    } else if (slotContainer) {
        slotContainer.style.display = 'none';
    }
    if (typeof refreshCustomSelect === 'function') refreshCustomSelect(slotSelect);
};

document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const specCards = document.querySelectorAll('.spec-card-wrapper');
    if (filterBtns.length > 0 && specCards.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filterVal = this.getAttribute('data-filter');
                specCards.forEach(card => {
                    if (filterVal === 'all') { card.style.display = 'block'; } 
                    else {
                        const specs = card.getAttribute('data-specs') || '';
                        if (specs.includes(filterVal)) { card.style.display = 'block'; } 
                        else { card.style.display = 'none'; }
                    }
                });
            });
        });
    }
});

// квиз
let quizAnswers = { target: '', problem: '', format: '', style: '', gender: '' };
let quizResultSpec = null;

window.nextQuizStep = function(nextStepId, answerVal) {
    if (nextStepId === 2) quizAnswers.target = answerVal;
    if (nextStepId === 3) quizAnswers.problem = answerVal;
    if (nextStepId === 4) quizAnswers.format = answerVal;
    if (nextStepId === 5) quizAnswers.style = answerVal;
    
    document.querySelectorAll('.quiz-step').forEach(step => step.style.display = 'none');
    document.getElementById('q-step-' + nextStepId).style.display = 'block';
}

window.finishQuiz = function(answerVal) {
    quizAnswers.gender = answerVal;
    const formData = new FormData();
    formData.append('target', quizAnswers.target);
    formData.append('problem', quizAnswers.problem);
    formData.append('format', quizAnswers.format);
    formData.append('style', quizAnswers.style);
    formData.append('gender', quizAnswers.gender);
    
    fetch('quiz_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        document.querySelectorAll('.quiz-step').forEach(step => step.style.display = 'none');
        const resultBlock = document.getElementById('q-step-result');
        resultBlock.style.display = 'block';
        if (data && data.success) {
            quizResultSpec = data;
            document.getElementById('quiz-result-card').innerHTML = `
                <img src="${data.img}" alt="${data.name}" style="width:130px; height:130px; object-fit:cover; border-radius:50%; margin-bottom:15px; border:3px solid #E0C6AD; box-shadow: 0 4px 10px rgba(224,198,173,0.3);">
                <h4 style="margin:0; font-family:'Tenor Sans', sans-serif; color:#3D3935; font-weight: bold;">${data.name}</h4>
                <p class="text-muted small m-0 mt-2">${data.role}</p>
                <div class="mt-3 text-muted small" style="line-height: 1.5; font-family: 'Lato', sans-serif;">
                    Этот специалист обладает высоким индексом соответствия по Вашему запросу и готов принять Вас как в офисе, так и онлайн.
                </div>
            `;
            document.getElementById('quiz-book-btn').style.display = 'inline-block';
        } else {
            document.getElementById('quiz-result-card').innerHTML = `
                <p class="m-0 py-3 text-muted">Не удалось подобрать узкопрофильного специалиста. Наш куратор готов связаться с Вами для индивидуального подбора.</p>
            `;
            document.getElementById('quiz-book-btn').style.display = 'none';
        }
    })
    .catch(err => { console.error(err); showToast("Ошибка при обработке результатов.", true); });
}

window.resetQuiz = function() {
    quizAnswers = { target: '', problem: '', format: '', style: '', gender: '' };
    quizResultSpec = null;
    document.querySelectorAll('.quiz-step').forEach(step => step.style.display = 'none');
    document.getElementById('q-step-1').style.display = 'block';
}

window.bookFromQuiz = function() {
    if (quizResultSpec) {
        document.getElementById('quizModal').style.display = 'none';
        document.body.style.overflow = '';
        openAppointmentForSpec(quizResultSpec.id, quizResultSpec.name);
        resetQuiz();
    }
}

// цитата
function initTypewriter() {
    const quotes = document.querySelectorAll('.dissolve-quote');
    if (quotes.length === 0) return;
    const originalTexts = Array.from(quotes).map(q => q.textContent.trim());
    quotes.forEach(q => q.textContent = '');

    let currentLine = 0;

    function typeLine() {
        if (currentLine >= quotes.length) return;
        const el = quotes[currentLine];
        const text = originalTexts[currentLine];
        el.classList.add('typing');
        let charIndex = 0;

        function typeChar() {
            if (charIndex < text.length) {
                el.textContent += text.charAt(charIndex);
                charIndex++;
                setTimeout(typeChar, 35);
            } else {
                el.classList.remove('typing');
                currentLine++;
                setTimeout(typeLine, 600); 
            }
        }
        typeChar();
    }

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    typeLine();
                    obs.disconnect();
                }
            });
        }, { threshold: 0.1 });
        const target = document.querySelector('.quote-section');
        if (target) observer.observe(target);
        else typeLine();
    } else {
        typeLine();
    }
}

// карусель
function initHomeCarousel() {
    const carousel = document.querySelector('.carousel');
    if (!carousel) return;

    const items = carousel.querySelectorAll('.carousel-item');
    const dots = carousel.querySelectorAll('.carousel-dot');
    let currentIndex = 0;
    let slideInterval;

    function showSlide(index) {
        items.forEach((item, i) => {
            if (i === index) {
                item.classList.add('active');
                dots[i].classList.add('active');
            } else {
                item.classList.remove('active');
                dots[i].classList.remove('active');
            }
        });
        currentIndex = index;
    }

    function nextSlide() {
        let nextIndex = (currentIndex + 1) % items.length;
        showSlide(nextIndex);
    }

    function startSlideShow() {
        slideInterval = setInterval(nextSlide, 4000); 
    }

    function stopSlideShow() {
        clearInterval(slideInterval);
    }

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            stopSlideShow();
            showSlide(i);
            startSlideShow();
        });
    });

    startSlideShow();
}

// прокрутка карт-
let currentSpecOffset = 0;
let currentPubOffset = 0;

function getSliderStep(container) {
    const first = container.firstElementChild;
    if (!first) return 335;
    const gap = parseFloat(getComputedStyle(container).gap) || 35;
    return first.offsetWidth + gap;
}

function resetSliderOffsets() {
    currentSpecOffset = 0;
    currentPubOffset = 0;
    ['spec-slider', 'slider'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.style.transform = '';
    });
}

window.addEventListener('resize', resetSliderOffsets);

window.slideSpecLeft = function() {
    const container = document.getElementById('spec-slider');
    if (!container) return;
    const cardWidth = getSliderStep(container);
    const maxOffset = 0;
    currentSpecOffset += cardWidth;
    if (currentSpecOffset > maxOffset) currentSpecOffset = 0;
    container.style.transform = `translateX(${currentSpecOffset}px)`;
}

window.slideSpecRight = function() {
    const container = document.getElementById('spec-slider');
    if (!container) return;
    const cardWidth = getSliderStep(container);
    const visibleWidth = container.parentElement.offsetWidth;
    const totalWidth = container.scrollWidth;
    const minOffset = -(totalWidth - visibleWidth);
    
    currentSpecOffset -= cardWidth;
    if (currentSpecOffset < minOffset) {
        currentSpecOffset = minOffset;
    }
    container.style.transform = `translateX(${currentSpecOffset}px)`;
}

window.slideLeft = function() {
    const container = document.getElementById('slider');
    if (!container) return;
    const cardWidth = getSliderStep(container);
    const maxOffset = 0;
    currentPubOffset += cardWidth;
    if (currentPubOffset > maxOffset) currentPubOffset = 0;
    container.style.transform = `translateX(${currentPubOffset}px)`;
}

window.slideRight = function() {
    const container = document.getElementById('slider');
    if (!container) return;
    const cardWidth = getSliderStep(container);
    const visibleWidth = container.parentElement.offsetWidth;
    const totalWidth = container.scrollWidth;
    const minOffset = -(totalWidth - visibleWidth);
    
    currentPubOffset -= cardWidth;
    if (currentPubOffset < minOffset) {
        currentPubOffset = minOffset;
    }
    container.style.transform = `translateX(${currentPubOffset}px)`;
}