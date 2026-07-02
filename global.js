/**
 * AETHER — Global UI Logic
 * Handles shared interactions like the floating navbar and global theme toggling
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── Floating Navbar Effect ──
    const handleScroll = () => {
        const nav = document.querySelector('.g-navbar');
        if (!nav) return;
        if (window.scrollY > 40) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll);
    handleScroll(); // Initial check

    // ── Theme Toggle Shared Logic ──
    const toggleBtn = document.getElementById('theme-toggle');
    const body = document.body;

    function updateThemeToggleLabel(isLight) {
        if (!toggleBtn) return;
        const sunSvg = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="M4.93 4.93l1.41 1.41"></path><path d="M17.66 17.66l1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="M4.93 19.07l1.41-1.41"></path><path d="M17.66 6.34l1.41-1.41"></path></svg>';
        const moonSvg = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>';

        // When in light mode, show sun and offer to switch to dark; when dark, show moon and offer to switch to light
        if (isLight) {
            toggleBtn.innerHTML = sunSvg + '<span class="sr-only">Switch to dark mode</span>';
            toggleBtn.setAttribute('title', 'Switch to dark mode');
        } else {
            toggleBtn.innerHTML = moonSvg + '<span class="sr-only">Switch to light mode</span>';
            toggleBtn.setAttribute('title', 'Switch to light mode');
        }
        toggleBtn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
    }

    // Apply saved theme immediately on load
    const savedTheme = localStorage.getItem('theme');
    const isLight = savedTheme === 'light';
    if (isLight) {
        body.classList.add('light-mode');
    } else {
        body.classList.remove('light-mode');
    }
    updateThemeToggleLabel(isLight);

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const currentLight = body.classList.toggle('light-mode');
            updateThemeToggleLabel(currentLight);
            localStorage.setItem('theme', currentLight ? 'light' : 'dark');
            
            // Dispatch a global event for charts or other page-specific listeners to react
            window.dispatchEvent(new CustomEvent('theme-changed', { 
                detail: { isLight: currentLight } 
            }));
        });
    }

    // ── Auto-dismiss Flash Messages ──
    const flashMessages = document.querySelectorAll('.g-alert, div[style*="background:var(--green-bg)"], div[style*="background: var(--green-bg)"], div[style*="background:var(--red-bg)"], div[style*="background: var(--red-bg)"]');
    flashMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });

    // ── Accessible Mobile Hamburger Menu Toggle ──
    const hamburgerBtn = document.querySelector('.hamburger-btn');
    const navLinks = document.querySelector('.g-nav-links');

    if (hamburgerBtn && navLinks) {
        hamburgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isExpanded = hamburgerBtn.getAttribute('aria-expanded') === 'true';
            hamburgerBtn.setAttribute('aria-expanded', !isExpanded);
            navLinks.classList.toggle('show');
        });

        // Close menu when clicking outside of it
        document.addEventListener('click', (e) => {
            if (!navLinks.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                if (navLinks.classList.contains('show')) {
                    navLinks.classList.remove('show');
                    hamburgerBtn.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }
});

// Global asset icon resolver for crypto proxies and equities
window.getAssetIcon = function(assetCode) {
  const code = String(assetCode).toUpperCase();
  if (code === 'USDT') {
    return `
      <div class="coin-avatar" style="background: rgba(38, 161, 123, 0.1); border-color: rgba(38, 161, 123, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#26A17B" />
          <path d="M12 5.5v3.6m0 0h4.2v2.5H12v6.9h-2.5v-6.9H5.3v-2.5h4.2v-3.6H12z M4 5.5h16v1.3H4V5.5z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'ETH') {
    return `
      <div class="coin-avatar" style="background: rgba(98, 126, 234, 0.1); border-color: rgba(98, 126, 234, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#627EEA" />
          <path d="M12 3v6.78l5.72-2.55zm0 18l5.72-8.07-5.72 3.38zm0-6.84l5.72-3.38L12 9.78zm-5.72-2.22L12 3v6.78zm0 2.22L12 17.54v3.46z" fill="white" fill-opacity="0.8"/>
        </svg>
      </div>
    `;
  }
  if (code === 'BTC') {
    return `
      <div class="coin-avatar" style="background: rgba(247, 147, 26, 0.1); border-color: rgba(247, 147, 26, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#F7931A" />
          <path d="M12 4.5c4.1 0 7.5 3.4 7.5 7.5s-3.4 7.5-7.5 7.5S4.5 16.1 4.5 12s3.4-7.5 7.5-7.5zm.9 3.4H10.1v1.1H9v1.3h1.1v2H9v1.3h1.1v2.1H9v1.3h1.1v1.1h2.8c1.3 0 2.2-.4 2.6-1.2.3-.5.4-1 .3-1.6-.1-.7-.5-1.2-1.1-1.4.8-.2 1.3-.8 1.4-1.6.1-.7-.1-1.3-.6-1.8-.4-.6-1.3-.9-2.5-.9zm.5 2.1c.6 0 1 .2 1.1.6s0 .8-.5.8h-1.8v-1.4h1.2zm.2 3.3c.7 0 1.1.2 1.2.7.1.4 0 .9-.6.9h-2v-1.6h1.4z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'XAU') {
    return `
      <div class="coin-avatar" style="background: rgba(229, 169, 59, 0.1); border-color: rgba(229, 169, 59, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#E5A93B" />
          <path d="M7 16h10l1.2-3H5.8L7 16zm4.5-9h6l1.2-3H10.3l1.2 3zm-6 4.5h10.4l1.2-3H4.8l1.2 3z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'XAG') {
    return `
      <div class="coin-avatar" style="background: rgba(166, 166, 166, 0.1); border-color: rgba(166, 166, 166, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#A6A6A6" />
          <circle cx="12" cy="12" r="8" stroke="white" stroke-width="1.8" fill="none"/>
          <circle cx="12" cy="12" r="4" fill="white" fill-opacity="0.6"/>
        </svg>
      </div>
    `;
  }
  if (code === 'OIL') {
    return `
      <div class="coin-avatar" style="background: rgba(40, 40, 40, 0.1); border-color: rgba(40, 40, 40, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#2E2E2E" />
          <path d="M12 5c-1.5 3-4 5.5-4 8.5 0 2.5 1.8 4.5 4 4.5s4-2 4-4.5c0-3-2.5-5.5-4-8.5z" fill="#E5A93B"/>
        </svg>
      </div>
    `;
  }
  if (code === 'GAS') {
    return `
      <div class="coin-avatar" style="background: rgba(41, 182, 246, 0.1); border-color: rgba(41, 182, 246, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#29B6F6" />
          <path d="M12 4c0 0-5 4.5-5 8.5C7 15.5 9 18 12 18s5-2.5 5-5.5C17 8.5 12 4 12 4zm-2 9c0-2 2-3.5 2-3.5s2 1.5 2 3.5c0 1.5-1 2.5-2 2.5s-2-1-2-2.5z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'CORN') {
    return `
      <div class="coin-avatar" style="background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#FFC107" />
          <path d="M12 5c-1.5 2-2 5-2 8 0 1.5.5 3 2 4 1.5-1 2-2.5 2-4 0-3-.5-6-2-8z" fill="#FFF" />
          <path d="M9 9c1 1 1.5 4 1.5 6.5M15 9c-1 1-1.5 4-1.5 6.5" stroke="#4CAF50" stroke-width="1.5" stroke-linecap="round" />
        </svg>
      </div>
    `;
  }
  if (code === 'WHEAT') {
    return `
      <div class="coin-avatar" style="background: rgba(244, 180, 26, 0.1); border-color: rgba(244, 180, 26, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#F4B41A" />
          <path d="M12 5l-1.5 2 1.5 1.5 1.5-1.5zm0 4.5l-1.5 2 1.5 1.5 1.5-1.5zm0 4.5l-1.5 2 1.5 1.5 1.5-1.5z M12 5v14" fill="white" stroke="white" stroke-width="1.5"/>
        </svg>
      </div>
    `;
  }
  if (code === 'COFFEE') {
    return `
      <div class="coin-avatar" style="background: rgba(109, 76, 65, 0.1); border-color: rgba(109, 76, 65, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#6D4C41" />
          <ellipse cx="12" cy="12" rx="7" ry="4" transform="rotate(-45 12 12)" fill="white"/>
          <path d="M7.5 16.5c2-2 5-2 9-9" stroke="#6D4C41" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
    `;
  }
  if (code === 'SUGAR') {
    return `
      <div class="coin-avatar" style="background: rgba(224, 242, 241, 0.1); border-color: rgba(224, 242, 241, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#B2DFDB" />
          <rect x="7" y="11" width="7" height="7" rx="1" fill="white"/>
          <rect x="11" y="6" width="7" height="7" rx="1" fill="white" fill-opacity="0.7" />
        </svg>
      </div>
    `;
  }
  if (code === 'COPPER') {
    return `
      <div class="coin-avatar" style="background: rgba(184, 115, 51, 0.1); border-color: rgba(184, 115, 51, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#B87333" />
          <path d="M5 16l2-6h10l2 6H5zm3.5-8l1.2-3h4.6l1.2 3H8.5z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'PLAT') {
    return `
      <div class="coin-avatar" style="background: rgba(229, 228, 226, 0.1); border-color: rgba(229, 228, 226, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#E5E4E2" />
          <path d="M5 15l2-5h10l2 5H5zm3-7l1-2.5h6L16 8H8z" fill="white" />
          <path d="M12 3v2M12 19v2M3 12h2M19 12h2" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
    `;
  }
  if (code === 'ZINC') {
    return `
      <div class="coin-avatar" style="background: rgba(112, 128, 144, 0.1); border-color: rgba(112, 128, 144, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#708090" />
          <path d="M5 15h14v3H5zm2-7h10v4H7z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'IRON') {
    return `
      <div class="coin-avatar" style="background: rgba(74, 85, 96, 0.1); border-color: rgba(74, 85, 96, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#4A5560" />
          <path d="M7 16l1-5h8l1 5zm-3-5c2 0 5-1.5 5-3H8v-1h8v1h-1c0 1.5 3 3 5 3H4z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'COAL') {
    return `
      <div class="coin-avatar" style="background: rgba(30, 30, 30, 0.1); border-color: rgba(30, 30, 30, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#1C1C1C" />
          <path d="M9 7l4-2 4 2-1 4 2 2-3 4-5-1-2-4z" fill="#4A4A4A"/>
        </svg>
      </div>
    `;
  }
  if (code === 'LUMBER') {
    return `
      <div class="coin-avatar" style="background: rgba(193, 154, 107, 0.1); border-color: rgba(193, 154, 107, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="#C19A6B">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" />
          <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z" />
          <line x1="4" y1="12" x2="20" y2="12" stroke="#C19A6B" stroke-width="2"/>
        </svg>
      </div>
    `;
  }
  if (code === 'COCOA') {
    return `
      <div class="coin-avatar" style="background: rgba(141, 110, 99, 0.1); border-color: rgba(141, 110, 99, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#8D6E63" />
          <path d="M12 6c-2.5 1.5-4 3.5-4 6s1.5 4.5 4 6 4-3.5 4-6-1.5-4.5-4-6z" fill="white"/>
          <path d="M12 6v12" stroke="#8D6E63" stroke-width="1.5"/>
        </svg>
      </div>
    `;
  }
  if (code === 'COTTON') {
    return `
      <div class="coin-avatar" style="background: rgba(245, 245, 245, 0.1); border-color: rgba(245, 245, 245, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#E0E0E0" />
          <circle cx="10" cy="10" r="3" fill="white"/>
          <circle cx="14" cy="10" r="3" fill="white"/>
          <circle cx="12" cy="13" r="3.2" fill="white"/>
          <path d="M12 15l-1 3h2l-1-3z" fill="#8D6E63"/>
        </svg>
      </div>
    `;
  }
  if (code === 'STEEL') {
    return `
      <div class="coin-avatar" style="background: rgba(120, 144, 156, 0.1); border-color: rgba(120, 144, 156, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#78909C" />
          <path d="M6 7h12v3h-4.5v4H18v3H6v-3h4.5v-4H6V7z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'LEAD') {
    return `
      <div class="coin-avatar" style="background: rgba(55, 71, 79, 0.1); border-color: rgba(55, 71, 79, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#37474F" />
          <path d="M8 9h8l2 8H6l2-8z" fill="white" />
          <circle cx="12" cy="7" r="1.5" stroke="white" stroke-width="1.5" fill="none"/>
        </svg>
      </div>
    `;
  }
  if (code === 'NICKEL') {
    return `
      <div class="coin-avatar" style="background: rgba(176, 190, 197, 0.1); border-color: rgba(176, 190, 197, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#B0BEC5" />
          <circle cx="12" cy="12" r="8.5" stroke="white" stroke-width="1.5" fill="none"/>
          <path d="M10 8.5v7h2.5c1 0 1.5-.5 1.5-1.5v-4c0-1-.5-1.5-1.5-1.5H10z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'TIN') {
    return `
      <div class="coin-avatar" style="background: rgba(207, 216, 220, 0.1); border-color: rgba(207, 216, 220, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#CFD8DC" />
          <rect x="7" y="7" width="10" height="10" rx="1.5" fill="white" />
          <line x1="7" y1="10" x2="17" y2="10" stroke="#CFD8DC" stroke-width="1.2"/>
          <line x1="7" y1="14" x2="17" y2="14" stroke="#CFD8DC" stroke-width="1.2"/>
        </svg>
      </div>
    `;
  }
  if (code === 'AAPL') {
    return `
      <div class="coin-avatar" style="background: rgba(163, 170, 174, 0.1); border-color: rgba(163, 170, 174, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="#A3AAAE">
          <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-1 .04-2.22.67-2.94 1.52-.63.73-1.18 1.87-1.03 2.97 1.1.09 2.24-.59 2.98-1.43z" />
        </svg>
      </div>
    `;
  }
  if (code === 'TSLA') {
    return `
      <div class="coin-avatar" style="background: rgba(227, 25, 38, 0.1); border-color: rgba(227, 25, 38, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#E31926" />
          <path d="M12 6.5c-2 0-3.5-.5-3.5-.5.5.8 1.5 1.2 3.5 1.2 2 0 3-.4 3.5-1.2 0 0-1.5.5-3.5.5zm-3.5 2v1h7v-1zm1 2.5l1.5 5.5 1.5-5.5zm1.5 5.8l-2.5-9h5z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'MSFT') {
    return `
      <div class="coin-avatar" style="background: rgba(0, 164, 239, 0.1); border-color: rgba(0, 164, 239, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#ECEFF1" />
          <rect x="6" y="6" width="5.5" height="5.5" fill="#F25022"/>
          <rect x="12.5" y="6" width="5.5" height="5.5" fill="#7FBA00"/>
          <rect x="6" y="12.5" width="5.5" height="5.5" fill="#00A4EF"/>
          <rect x="12.5" y="12.5" width="5.5" height="5.5" fill="#FFB900"/>
        </svg>
      </div>
    `;
  }
  if (code === 'AMZN') {
    return `
      <div class="coin-avatar" style="background: rgba(255, 153, 0, 0.1); border-color: rgba(255, 153, 0, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#111" />
          <path d="M7.5 13.5c1 1 3.5 2 4.5 2s3.5-1 4.5-2" stroke="#FF9900" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M15.5 13.5l1.5 1.5-2.5 1" fill="#FF9900"/>
        </svg>
      </div>
    `;
  }
  if (code === 'GOOG') {
    return `
      <div class="coin-avatar" style="background: rgba(234, 67, 53, 0.1); border-color: rgba(234, 67, 53, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="white" />
          <path d="M17.5 12.2c0-.4 0-.8-.1-1.2H12v2.3h3.1c-.1.7-.5 1.3-1.1 1.7v1.4h1.8c1.1-1 1.7-2.6 1.7-4.2z" fill="#4285F4"/>
          <path d="M12 17.8c1.6 0 2.9-.5 3.9-1.4l-1.8-1.4c-.5.3-1.1.5-2.1.5-1.6 0-3-1.1-3.5-2.5H6.6v1.5c1 2 3.1 3.3 5.4 3.3z" fill="#34A853"/>
          <path d="M8.5 13c-.1-.3-.2-.7-.2-1s.1-.7.2-1V9.5H6.6c-.5 1-0.8 2.1-0.8 3.5s.3 2.5.8 3.5l2.1-1.5z" fill="#FBBC05"/>
          <path d="M12 8.2c.9 0 1.6.3 2.2.9l1.7-1.7C14.9 6.5 13.6 6 12 6 9.7 6 7.6 7.3 6.6 9.3l2.1 1.6c.5-1.4 1.9-2.7 3.3-2.7z" fill="#EA4335"/>
        </svg>
      </div>
    `;
  }
  if (code === 'NVDA') {
    return `
      <div class="coin-avatar" style="background: rgba(118, 185, 0, 0.1); border-color: rgba(118, 185, 0, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#76B900" />
          <path d="M12 6c-3.3 0-6 2.7-6 6 0 .5.1 1 .2 1.5L8 12.3c-.1-.1-.1-.2-.1-.3 0-2.2 1.8-4 4-4s4 1.8 4 4c0 .2 0 .4-.1.5l1.8.6c.1-.4.1-.7.1-1.1 0-3.3-2.7-6-6-6zm0 3c-1.7 0-3 1.3-3 3 0 .3.1.6.2.8l1.4-.5c-.1-.1-.1-.2-.1-.3 0-1.1.9-2 2-2s2 .9 2 2c0 .2-.1.3-.1.5l1.4.5c.1-.3.2-.6.2-1 0-1.7-1.3-3-3-3z" fill="white" />
        </svg>
      </div>
    `;
  }
  if (code === 'META') {
    return `
      <div class="coin-avatar" style="background: rgba(0, 102, 204, 0.1); border-color: rgba(0, 102, 204, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#0066CC" />
          <path d="M16.5 9c-1.3 0-2.3.8-3.1 2-.8-1.2-1.8-2-3.1-2C8 9 6.5 10.3 6.5 12s1.5 3 3.8 3c1.3 0 2.3-.8 3.1-2 .8 1.2 1.8 2 3.1 2 2.3 0 3.8-1.3 3.8-3s-1.5-3-3.8-3zm-6.2 4.5c-1 0-1.8-.7-1.8-1.5s.8-1.5 1.8-1.5 1.8.7 1.8 1.5-.8 1.5-1.8 1.5zm6.2 0c-1 0-1.8-.7-1.8-1.5s.8-1.5 1.8-1.5 1.8.7 1.8 1.5-.8 1.5-1.8 1.5z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'AMD') {
    return `
      <div class="coin-avatar" style="background: rgba(0, 0, 0, 0.1); border-color: rgba(0, 0, 0, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="black" />
          <path d="M17 7h-5l-4 4h4v5l5-5V7z M7 11h4v4l-4-4V11z" fill="white"/>
        </svg>
      </div>
    `;
  }
  if (code === 'NFLX') {
    return `
      <div class="coin-avatar" style="background: rgba(0, 0, 0, 0.1); border-color: rgba(0, 0, 0, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="black" />
          <path d="M9 6h2.5v12H9z M12.5 6h2.5l-3.5 12h-2.5z M12.5 6h2.5v12h-2.5z" fill="#E50914" />
        </svg>
      </div>
    `;
  }
  if (code === 'BABA') {
    return `
      <div class="coin-avatar" style="background: rgba(255, 102, 0, 0.1); border-color: rgba(255, 102, 0, 0.2);">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <circle cx="12" cy="12" r="12" fill="#FF6600" />
          <path d="M12 6a6 6 0 0 0-6 6c0 3 2.5 5.5 5.5 5.5s4.5-1.5 5-3.5c0-.2.1-.4.1-.6 0-3.5-2.1-7.4-4.6-7.4z M10.5 12c.8 0 1.5.7 1.5 1.5S11.3 15 10.5 15s-1.5-.7-1.5-1.5.7-1.5 1.5-1.5z" fill="white"/>
        </svg>
      </div>
    `;
  }
  return `
    <div class="coin-avatar" style="background: rgba(141, 160, 148, 0.1); border-color: rgba(141, 160, 148, 0.2);">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#8da094" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
        <polyline points="17 6 23 6 23 12"/>
      </svg>
    </div>
  `;
};

