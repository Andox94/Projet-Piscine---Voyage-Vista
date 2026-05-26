/* ================================================
   VoyageVista — Shared Components
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
        <a href="index.html" class="navbar-logo">
          <div class="logo-icon">✈</div>
          Voyage<span>Vista</span>
        </a>
 
        <div class="navbar-nav">
          ${navLinks}
        </div>
 
        <div class="navbar-actions">
          <a href="panier.html" class="navbar-cart">
            🛒
            <span class="cart-badge">0</span>
          </a>
          <a href="connexion.html" id="navUserBtn" class="navbar-user" title="Mon compte">
            V
          </a>
        </div>
 
        <div class="navbar-hamburger" id="hamburger" aria-label="Menu">
          <span></span><span></span><span></span>
        </div>
      </div>
    </nav>
 
    <div class="mobile-menu" id="mobileMenu">
      ${mobileLinks}
      <a href="panier.html">🛒 Mon Panier</a>
      <a href="connexion.html">👤 Mon Compte</a>
    </div>
  `;
}
 
function getFooterHTML() {
  return `
    <footer class="footer">
      <div class="container">
        <div class="footer-grid">
          <div class="footer-brand">
            <div class="logo">Voyage<span>Vista</span></div>
            <p>La plateforme de planification de voyages sur mesure pour les voyageurs exigeants. Découvrez, planifiez, partez.</p>
            <div class="footer-socials">
              <div class="social-icon">𝕏</div>
              <div class="social-icon">in</div>
              <div class="social-icon">ig</div>
              <div class="social-icon">fb</div>
            </div>
          </div>
          <div class="footer-col">
            <h4>Explorer</h4>
            <ul class="footer-links">
              <li><a href="destinations.html">Destinations</a></li>
              <li><a href="transports.html">Transports</a></li>
              <li><a href="hebergements.html">Hébergements</a></li>
              <li><a href="activites.html">Activités</a></li>
            </ul>
          </div>
          <div class="footer-col">
            <h4>Compte</h4>
            <ul class="footer-links">
              <li><a href="connexion.html">Se connecter</a></li>
              <li><a href="inscription.html">S'inscrire</a></li>
              <li><a href="profil.html">Mon profil</a></li>
              <li><a href="mes-voyages.html">Mes voyages</a></li>
            </ul>
          </div>
          <div class="footer-col">
            <h4>Informations</h4>
            <ul class="footer-links">
              <li><a href="#">À propos</a></li>
              <li><a href="#">Contact</a></li>
              <li><a href="#">CGU</a></li>
              <li><a href="#">Politique de confidentialité</a></li>
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <span>© 2026 VoyageVista — Projet Web Dynamique ECE Paris</span>
          <div class="footer-bottom-links">
            <a href="#">CGU</a>
            <a href="#">Cookies</a>
            <a href="#">Contact</a>
          </div>
        </div>
      </div>
    </footer>
  `;
}
 
function injectSharedComponents(activePage = '') {
  // Inject navbar
  const navPlaceholder = document.getElementById('navbar-placeholder');
  if (navPlaceholder) navPlaceholder.innerHTML = getNavbarHTML(activePage);
 
  // Inject footer
  const footerPlaceholder = document.getElementById('footer-placeholder');
  if (footerPlaceholder) footerPlaceholder.innerHTML = getFooterHTML();
 
  // Update user button if logged in
  const userData = JSON.parse(localStorage.getItem('vv_user') || 'null');
  const userBtn = document.getElementById('navUserBtn');
  if (userBtn && userData) {
    userBtn.textContent = userData.name?.charAt(0).toUpperCase() || 'U';
    userBtn.href = 'profil.html';
  }
}
 
document.addEventListener('DOMContentLoaded', () => {
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  injectSharedComponents(currentPage);
});
 
