<style>
/* Login Modal specific styles extracted for global use */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    justify-content: center;
    align-items: center;
    padding: 20px;
    z-index: 9999; /* Ensure it's above everything */
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}
.modal-overlay.active {
    display: flex;
}
.modal-card {
    background: linear-gradient(135deg, rgba(218, 241, 222, 0.35), rgba(142, 182, 155, 0.15));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    width: 100%;
    max-width: 900px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    position: relative;
    color: white;
    overflow: hidden;
}
.modal-split {
    display: flex;
    flex-direction: row;
    min-height: 500px;
}
.modal-image {
    flex: 1;
    max-width: 45%;
}
.modal-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.modal-form-container {
    flex: 1;
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
}
.close-btn {
    position: absolute;
    top: 20px; right: 20px;
    background: none; border: none;
    color: white; font-size: 2rem;
    cursor: pointer; line-height: 1;
    opacity: 0.6; transition: opacity 0.2s;
    z-index: 10;
}
.close-btn:hover { opacity: 1; }

.auth-header { margin-bottom: 30px; }
.auth-logo {
    width: 40px; height: 40px;
    background-color: rgba(43, 71, 62, 0.8);
    border-radius: 12px; padding: 8px; margin-bottom: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    object-fit: contain; display: inline-block;
}
.auth-header h2 { font-size: 2.2rem; font-weight: 600; margin-bottom: 8px; color: white; }
.auth-header p { font-size: 1rem; color: rgba(255, 255, 255, 0.8); font-weight: 300; margin:0;}

.auth-form { display: flex; flex-direction: column; gap: 20px; }
.input-group { display: flex; flex-direction: column; text-align: left; }
.input-group label { font-size: 0.9rem; color: white; margin-bottom: 8px; margin-left: 2px; font-weight: 600; }
.input-group input {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 15px 20px; border-radius: 14px;
    color: white; font-family: inherit; font-size: 1rem; outline: none;
    transition: border-color 0.3s ease;
}
.input-group input:focus { border-color: rgba(255, 255, 255, 0.4); }
.input-group input::placeholder { color: rgba(255, 255, 255, 0.3); }

.auth-submit {
    justify-content: center; margin-top: 15px; width: 100%;
    padding: 12px 12px 12px 30px; gap: 15px;
    background: white; color: #051f20; font-weight:600; border:none; border-radius:30px;
    display:inline-flex; align-items:center; cursor:pointer; font-size:1.1rem;
}
.auth-footer { margin-top: 30px; font-size: 0.9rem; color: #e0e0e0; display: flex; flex-direction: column; gap: 10px; }
.auth-footer a { color: #8EB69B; text-decoration: none; font-weight: 600; cursor: pointer; }
.auth-footer a:hover { color: #DAF1DE; }

@media(max-width:768px) {
    .modal-image { display:none; }
    .modal-form-container { padding: 40px 20px; }
}
</style>

<!-- Auth Modal Overlay -->
<div id="auth-modal" class="modal-overlay">
  <div class="modal-card">
    <button id="close-modal" class="close-btn">&times;</button>
    
    <div class="modal-split">
      <div class="modal-image">
        <img src="ee7f6d5b496baf6f362f7a67c045a59b.jpg" alt="White Flowers">
      </div>
      
      <div class="modal-form-container">
        <div id="signup-view" style="display:none;">
          <div class="auth-header">
            <img src="favicon.png" alt="EcoLoop Logo" class="auth-logo">
            <h2>Create an Account</h2>
            <p>Join EcoLoop and start trading.</p>
          </div>
          <form class="auth-form" id="signup-form">
            <div class="input-group">
              <label for="signup-username">Username</label>
              <input type="text" id="signup-username" placeholder="Create a username" required>
            </div>
            <div class="input-group">
              <label for="signup-pincode">6-digit Pincode</label>
              <input type="text" id="signup-pincode" placeholder="e.g. 110001" required pattern="[0-9]{6}">
            </div>
            <div class="input-group">
              <label for="signup-password">Password</label>
              <input type="password" id="signup-password" placeholder="Create a strong password (min 8 chars)" required minlength="8">
            </div>
            <div id="signup-error" style="color: #ff6b6b; font-size: 0.85rem; display: none;"></div>
            <button type="submit" class="auth-submit">Sign Up</button>
          </form>
          <div class="auth-footer">
            <p>Already have an account? <a id="show-signin">Login</a></p>
          </div>
        </div>

        <div id="signin-view" style="display: none;">
          <div class="auth-header">
            <img src="favicon.png" alt="EcoLoop Logo" class="auth-logo">
            <h2>Welcome Back</h2>
            <p>Login to continue to EcoLoop.</p>
          </div>
          <form class="auth-form" id="signin-form">
            <div class="input-group">
              <label for="signin-username">Username</label>
              <input type="text" id="signin-username" placeholder="Enter your username" required>
            </div>
            <div class="input-group">
              <label for="signin-password">Password</label>
              <input type="password" id="signin-password" placeholder="Enter your password" required>
            </div>
            <div id="signin-error" style="color: #ff6b6b; font-size: 0.85rem; display: none;"></div>
            <button type="submit" class="auth-submit">Login</button>
          </form>
          <div class="auth-footer">
            <p>Don't have an account? <a id="show-signup">Sign Up</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const authModal = document.getElementById('auth-modal');
    if(!authModal) return;

    const closeModalBtn = document.getElementById('close-modal');
    const signupView = document.getElementById('signup-view');
    const signinView = document.getElementById('signin-view');
    const showSigninLink = document.getElementById('show-signin');
    const showSignupLink = document.getElementById('show-signup');
    
    // Auto-open modal if hash is #login
    if (window.location.hash === '#login') {
        openLoginModal();
    }
    
    // Bind global function for login buttons
    window.openLoginModal = function(e) {
        if(e) e.preventDefault();
        authModal.classList.add('active');
        signupView.style.display = 'none';
        signinView.style.display = 'block';
    };

    window.openSignupModal = function(e) {
        if(e) e.preventDefault();
        authModal.classList.add('active');
        signupView.style.display = 'block';
        signinView.style.display = 'none';
    };

    if(closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            authModal.classList.remove('active');
            window.location.hash = ''; // clear hash
        });
    }

    if(showSigninLink) {
        showSigninLink.addEventListener('click', window.openLoginModal);
    }

    if(showSignupLink) {
        showSignupLink.addEventListener('click', window.openSignupModal);
    }

    // Intercept login buttons across all pages dynamically
    document.querySelectorAll('a[href="index.php#login"], a[href="index.php"], #nav-signin').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (window.location.pathname.endsWith('index.php') || window.location.pathname === '/' || window.location.pathname === '/ecoloop/') {
                // If on homepage, just show modal without redirect
                e.preventDefault();
                openLoginModal();
            } else {
                // If on other pages, open modal dynamically without refresh
                e.preventDefault();
                openLoginModal();
            }
        });
    });

    const signupForm = document.getElementById('signup-form');
    const signinForm = document.getElementById('signin-form');

    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('signup-username').value;
            const pincode = document.getElementById('signup-pincode').value;
            const password = document.getElementById('signup-password').value;
            const errorDiv = document.getElementById('signup-error');
            errorDiv.style.display = 'none';

            try {
                const res = await fetch(`api/auth.php?action=register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password, pincode })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload(); // Refresh properly logs them in
                } else {
                    errorDiv.textContent = data.error || 'Authentication failed.';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.style.display = 'block';
            }
        });
    }

    if (signinForm) {
        signinForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('signin-username').value;
            const password = document.getElementById('signin-password').value;
            const errorDiv = document.getElementById('signin-error');
            errorDiv.style.display = 'none';

            try {
                const res = await fetch(`api/auth.php?action=login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();
                if (data.success) {
                    // Update UI dynamically instead of reload to save page state
                    authModal.classList.remove('active');
                    if (window.location.pathname.endsWith('index.php') || window.location.pathname === '/') {
                         window.location.href = 'dashboard.php';
                    } else {
                        // In projects.php
                        document.querySelectorAll('.button.primary[href="index.php#login"], .button.primary[href="index.php"]').forEach(btn => {
                             btn.outerHTML = `<span class="account"><span class="status-dot"></span>${data.user.username}</span><a href="logout.php" class="text-link" style="font-size: 11px; margin-left: 15px; color: var(--muted); text-decoration: underline;" title="Logout">Logout</a>`;
                        });

                        // In dashboard.php or generic
                        document.querySelectorAll('a[href="index.php#login"], a[href="index.php"]').forEach(btn => {
                            btn.outerHTML = `<a href="logout.php" id="logout-btn" class="text-neutral-500 hover:text-white hover:bg-neutral-900 w-9 h-9 rounded-lg flex items-center justify-center transition-colors" title="Logout"><i class="fa-solid fa-right-from-bracket text-sm"></i></a>`;
                        });
                        
                        // In dashboard.php, we need to inject the user info but we can just let it be. The main thing is they are logged in.
                    }
                } else {
                    errorDiv.textContent = data.error || 'Authentication failed.';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.style.display = 'block';
            }
        });
    }
});
</script>
