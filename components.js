/* ================================================
   VoyageVista — Shared Components (avec session)
   Injects navbar and footer into every page
   ================================================ */

function getNavbarHTML(activePage = '') {
  const links = [
    { href: 'destinations.html', label: 'Destinations' },
    { href: 'transports.html', label: 'Transports' },
    { href: 'hebergements.html', label: 'Hébergements' },
    { href: 'activites.html', label: 'Activités' },
  ];

  const navLinks = links.map(l =>
    `<a href="${l.href}" class="${activePage === l.href ? 'active' : ''}">${l.label}</a>`
  ).join('');

  const mobileLinks = links.map(l =>
    `<a href="${l.href}" class="${activePage === l.href ? 'active' : ''}">${l.label}</a>`
  ).join('');

  return `
    <nav class="navbar" id="navbar">
      <div class="navbar-inner">
        <a href="index.html" class="navbar-logo"><div class="logo-icon">✈</div>Voyage<span>Vista</span></a>
        <div class="navbar-nav">${navLinks}</div>
        <div class="navbar-actions">
          <a href="panier.html" class="navbar-cart">🛒<span class="cart-badge">0</span></a>
          <a href="connexion.html" id="navUserBtn" class="navbar-user" title="Mon compte">V</a>
        </div>
        <div class="navbar-hamburger" id="hamburger"><span></span><span></span><span></span></div>
      </div>
    </nav>
    <div class="mobile-menu" id="mobileMenu">
      ${mobileLinks}
      <a href="panier.html">🛒 Mon Panier</a>
      <a href="connexion.html">👤 Mon Compte</a>
    </div>
  `;
}

function getFooterHTML() { /* inchangé, identique à votre version */ }

async function injectSharedComponents(activePage = '') {
  const navPlaceholder = document.getElementById('navbar-placeholder');
  if (navPlaceholder) navPlaceholder.innerHTML = getNavbarHTML(activePage);

  const footerPlaceholder = document.getElementById('footer-placeholder');
  if (footerPlaceholder) footerPlaceholder.innerHTML = getFooterHTML();

  // Récupération de l'utilisateur connecté via session.php
  try {
    const res = await fetch('session.php');
    const user = await res.json();
    const userBtn = document.getElementById('navUserBtn');
    if (user && user.name) {
      userBtn.textContent = user.name.charAt(0).toUpperCase();
      userBtn.href = 'profil.html';
      userBtn.title = `Mon compte (${user.name})`;
    } else {
      userBtn.textContent = 'V';
      userBtn.href = 'connexion.html';
      userBtn.title = 'Se connecter';
    }
  } catch (err) {
    console.warn('Impossible de charger la session', err);
  }

  // Mise à jour du badge du panier (déjà dans Cart.updateBadge)
  Cart.updateBadge();
}

document.addEventListener('DOMContentLoaded', () => {
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  injectSharedComponents(currentPage);
});
