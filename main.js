/* ================================================
   VoyageVista — Global JS
   Navbar, Cart, Toasts, Shared Utilities
   ================================================ */
 
/* ---- NAVBAR SCROLL + HAMBURGER ---- */
(function () {
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    });
  }
 
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
    });
  }
 
  // Highlight active nav link
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.navbar-nav a, .mobile-menu a').forEach(link => {
    if (link.getAttribute('href') === currentPage) link.classList.add('active');
  });
})();
 
/* ---- CART STATE (localStorage) ---- */
const Cart = (() => {
  const KEY = 'vv_cart';
 
  function get() {
    try { return JSON.parse(localStorage.getItem(KEY)) || { destination: null, transport: null, accommodation: null, activities: [] }; }
    catch { return { destination: null, transport: null, accommodation: null, activities: [] }; }
  }
 
  function save(cart) {
    localStorage.setItem(KEY, JSON.stringify(cart));
    updateBadge();
  }
 
  function updateBadge() {
    const cart = get();
    let count = 0;
    if (cart.destination) count++;
    if (cart.transport) count++;
    if (cart.accommodation) count++;
    count += (cart.activities || []).length;
    const badge = document.querySelector('.cart-badge');
    if (badge) badge.textContent = count;
  }
 
  function setDestination(dest) {
    const cart = get();
    cart.destination = dest;
    cart.transport = null; cart.accommodation = null; cart.activities = [];
    save(cart);
  }
 
  function setTransport(t) { const c = get(); c.transport = t; save(c); }
  function setAccommodation(a) { const c = get(); c.accommodation = a; save(c); }
 
  function toggleActivity(act) {
    const c = get();
    const idx = (c.activities || []).findIndex(a => a.id === act.id);
    if (idx === -1) c.activities.push(act); else c.activities.splice(idx, 1);
    save(c);
  }
 
  function clear() { save({ destination: null, transport: null, accommodation: null, activities: [] }); }
  function getTotal() {
    const c = get();
    let t = (c.destination?.price || 0) + (c.transport?.price || 0) + (c.accommodation?.price_per_night || 0) * 3;
    (c.activities || []).forEach(a => t += (a.price || 0));
    return t;
  }
 
  return { get, save, setDestination, setTransport, setAccommodation, toggleActivity, clear, getTotal, updateBadge };
})();
 
/* ---- TOAST NOTIFICATIONS ---- */
const Toast = (() => {
  function show(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
 
    const icons = { info: 'ℹ️', success: '✅', error: '❌', warning: '⚠️' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
      <span class="toast-text">${message}</span>
      <span class="toast-close" onclick="this.parentElement.remove()">×</span>
    `;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3500);
  }
 
  return { show };
})();
 
/* ---- TABS ---- */
function initTabs(tabsSelector = '.tabs') {
  document.querySelectorAll(tabsSelector).forEach(tabsEl => {
    tabsEl.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        tabsEl.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => {
          p.classList.toggle('active', p.id === target);
        });
      });
    });
  });
}
 
/* ---- FILTER CHIPS ---- */
function initFilterChips() {
  document.querySelectorAll('.filter-chips').forEach(group => {
    group.querySelectorAll('.chip').forEach(chip => {
      chip.addEventListener('click', () => {
        const multi = group.dataset.multi === 'true';
        if (!multi) group.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.toggle('active');
      });
    });
  });
}
 
/* ---- WISHLIST TOGGLE ---- */
function initWishlists() {
  document.querySelectorAll('.card-wishlist').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      btn.classList.toggle('active');
      btn.textContent = btn.classList.contains('active') ? '❤️' : '🤍';
      Toast.show(btn.classList.contains('active') ? 'Ajouté aux favoris' : 'Retiré des favoris', 'info');
    });
  });
}
 
/* ---- BOOKING STEPS HIGHLIGHT ---- */
function setActiveStep(stepNumber) {
  document.querySelectorAll('.step').forEach((step, i) => {
    step.classList.remove('active', 'done');
    if (i + 1 < stepNumber) step.classList.add('done');
    if (i + 1 === stepNumber) step.classList.add('active');
  });
}
 
/* ---- LAZY IMG PLACEHOLDERS ---- */
function initLazyImages() {
  const images = document.querySelectorAll('img[data-src]');
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.src = e.target.dataset.src; obs.unobserve(e.target); } });
    }, { rootMargin: '200px' });
    images.forEach(img => obs.observe(img));
  } else {
    images.forEach(img => img.src = img.dataset.src);
  }
}
 
/* ---- INIT ON DOM READY ---- */
document.addEventListener('DOMContentLoaded', () => {
  Cart.updateBadge();
  initTabs();
  initFilterChips();
  initWishlists();
  initLazyImages();
});
