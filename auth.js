(function() {
  const adminPages = ['products.html', 'inventory.html', 'reports.html', 'paint-shades.html'];
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const isAdminPage = adminPages.some(p => currentPage.includes(p));
  
  // 1. Immediately block access on admin pages if unauthorized
  if (isAdminPage && sessionStorage.getItem('adminLoggedIn') !== 'true') {
    document.documentElement.classList.add('admin-unauthorized');
    injectModalStyles();
  }
  
  // 2. Add lifecycle handlers
  if (isAdminPage) {
    if (sessionStorage.getItem('adminLoggedIn') === 'true') {
      document.addEventListener('DOMContentLoaded', addLogoutButton);
    } else {
      document.addEventListener('DOMContentLoaded', function() {
        showLoginOverlay();
      });
    }
  } else {
    // On public pages, intercept admin links
    document.addEventListener('DOMContentLoaded', interceptAdminLinks);
  }
  
  function injectModalStyles() {
    if (document.getElementById('auth-style')) return;
    
    const style = document.createElement('style');
    style.id = 'auth-style';
    style.innerHTML = `
      .admin-unauthorized body {
        background: radial-gradient(circle at center, #0f172a 0%, #090f1d 100%) !important;
        height: 100vh !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .admin-unauthorized body > *:not(.admin-login-overlay) {
        display: none !important;
      }
      
      /* Overlay & Modal Styles */
      .admin-login-overlay {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        background: radial-gradient(circle at center, rgba(15, 23, 42, 0.9) 0%, rgba(9, 15, 29, 0.95) 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 20px;
      }
      .admin-login-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 16px;
        padding: 40px 32px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.3);
        text-align: center;
        animation: cardEntry 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      @keyframes cardEntry {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      .admin-login-card.shake {
        animation: shake 0.4s ease-in-out;
      }
      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-10px); }
        40%, 80% { transform: translateX(10px); }
      }
      .admin-logo-badge {
        width: 64px;
        height: 64px;
        background: #4f46e5;
        color: #fff;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto 20px;
        box-shadow: 0 8px 16px rgba(79, 70, 229, 0.3);
      }
      .admin-login-card h3 {
        font-family: 'Outfit', -apple-system, sans-serif;
        font-size: 24px;
        color: #0f172a;
        margin-bottom: 8px;
        font-weight: 700;
      }
      .admin-login-card p {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 24px;
      }
      .admin-input-group {
        position: relative;
        margin-bottom: 16px;
        text-align: left;
      }
      .admin-input-group i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 16px;
        transition: color 0.2s;
      }
      .admin-input-group input {
        width: 100%;
        padding: 14px 16px 14px 44px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 14px;
        background: rgba(248, 250, 252, 0.8);
        color: #0f172a;
        outline: none;
        transition: all 0.2s;
      }
      .admin-input-group input:focus {
        border-color: #4f46e5;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
      }
      .admin-input-group input:focus + i {
        color: #4f46e5;
      }
      .admin-login-btn {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Outfit', -apple-system, sans-serif;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        transition: all 0.2s;
        margin-top: 10px;
      }
      .admin-login-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(79, 70, 229, 0.3);
      }
      .admin-login-btn:active {
        transform: translateY(0);
      }
      .admin-cancel-btn {
        display: inline-block;
        margin-top: 16px;
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.2s;
      }
      .admin-cancel-btn:hover {
        color: #0f172a;
      }
      .admin-error-msg {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(239, 68, 68, 0.1);
        color: #991b1b;
        border: 1px solid rgba(239, 68, 68, 0.2);
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 16px;
        text-align: left;
        animation: fadeIn 0.2s;
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }
  
  function showLoginOverlay(targetUrl = '') {
    if (document.querySelector('.admin-login-overlay')) return;
    
    const overlay = document.createElement('div');
    overlay.className = 'admin-login-overlay';
    
    const card = document.createElement('div');
    card.className = 'admin-login-card';
    card.innerHTML = `
      <div class="admin-logo-badge">
        <i class="fa-solid fa-lock"></i>
      </div>
      <h3>Admin Authentication</h3>
      <p>Please enter your administrator credentials to access this dashboard.</p>
      
      <div id="adminErrorContainer"></div>
      
      <form id="adminLoginForm">
        <div class="admin-input-group">
          <input type="text" id="adminUsername" placeholder="Username" required autocomplete="username">
          <i class="fa-solid fa-user"></i>
        </div>
        <div class="admin-input-group">
          <input type="password" id="adminPassword" placeholder="Password" required autocomplete="current-password">
          <i class="fa-solid fa-key"></i>
        </div>
        <button type="submit" class="admin-login-btn">Log In</button>
      </form>
      <a href="#" class="admin-cancel-btn" id="adminCancelBtn">Cancel</a>
    `;
    
    overlay.appendChild(card);
    document.body.appendChild(overlay);
    
    setTimeout(() => {
      const userField = document.getElementById('adminUsername');
      if (userField) userField.focus();
    }, 100);
    
    const form = document.getElementById('adminLoginForm');
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const u = document.getElementById('adminUsername').value;
      const p = document.getElementById('adminPassword').value;
      
      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username: u, password: p })
        });
        
        const data = await res.json();
        
        if (res.ok && data.success) {
          sessionStorage.setItem('adminLoggedIn', 'true');
          overlay.remove();
          document.documentElement.classList.remove('admin-unauthorized');
          
          const authStyle = document.getElementById('auth-style');
          if (authStyle && isAdminPage) {
            authStyle.remove();
          }
          
          if (isAdminPage) {
            addLogoutButton();
            window.location.reload();
          } else {
            window.location.href = targetUrl || 'products.html';
          }
        } else {
          showError();
        }
      } catch (err) {
        showError();
        console.error('Auth error:', err);
      }
      
      function showError() {
        card.classList.add('shake');
        setTimeout(() => card.classList.remove('shake'), 400);
        
        const errContainer = document.getElementById('adminErrorContainer');
        errContainer.innerHTML = `
          <div class="admin-error-msg">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Invalid username or password.</span>
          </div>
        `;
      }
    });
    
    const cancelBtn = document.getElementById('adminCancelBtn');
    cancelBtn.addEventListener('click', function(e) {
      e.preventDefault();
      overlay.remove();
      if (isAdminPage) {
        document.documentElement.classList.remove('admin-unauthorized');
        window.location.href = 'index.html';
      }
    });
  }
  
  function addLogoutButton() {
    const topNav = document.querySelector('.top-nav');
    if (topNav && !document.getElementById('logoutBtn')) {
      const logoutBtn = document.createElement('a');
      logoutBtn.href = '#';
      logoutBtn.id = 'logoutBtn';
      logoutBtn.style.color = 'var(--danger-text)';
      logoutBtn.style.fontWeight = '600';
      logoutBtn.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Logout';
      logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        sessionStorage.removeItem('adminLoggedIn');
        window.location.href = 'index.html';
      });
      topNav.appendChild(logoutBtn);
    }
  }
  
  function interceptAdminLinks() {
    document.querySelectorAll('a').forEach(link => {
      const href = link.getAttribute('href');
      if (href) {
        const isTargetingAdmin = adminPages.some(p => href.includes(p));
        if (isTargetingAdmin) {
          link.addEventListener('click', function(e) {
            if (sessionStorage.getItem('adminLoggedIn') !== 'true') {
              e.preventDefault();
              injectModalStyles();
              showLoginOverlay(href);
            }
          });
        }
      }
    });
  }
  
  // Support check for query param login from redirects if necessary
  if (!isAdminPage && window.location.search.includes('login=true')) {
    document.addEventListener('DOMContentLoaded', function() {
      injectModalStyles();
      const params = new URLSearchParams(window.location.search);
      const redirect = params.get('redirect') || 'products.html';
      showLoginOverlay(redirect);
    });
  }
})();
