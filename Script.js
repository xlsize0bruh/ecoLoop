// JavaScript for EcoLoop

document.addEventListener('DOMContentLoaded', () => {
    const authModal = document.getElementById('auth-modal');
    const closeModalBtn = document.getElementById('close-modal');
    
    // We can use the primary button to open the signup modal
    const exploreBtn = document.getElementById('explore-btn');
    
    const signupView = document.getElementById('signup-view');
    const signinView = document.getElementById('signin-view');
    
    const showSigninLink = document.getElementById('show-signin');
    const showSignupLink = document.getElementById('show-signup');

    // Top Nav triggers
    const navSigninBtn = document.getElementById('nav-signin');
    const navSignupBtn = document.getElementById('nav-signup');

    // Explore button is a standard link to dashboard.php

    // Top Nav Sign In
    if (navSigninBtn) {
        navSigninBtn.addEventListener('click', (e) => {
            e.preventDefault();
            authModal.classList.add('active');
            signupView.style.display = 'none';
            signinView.style.display = 'block';
        });
    }

    // Top Nav Sign Up
    if (navSignupBtn) {
        navSignupBtn.addEventListener('click', (e) => {
            e.preventDefault();
            authModal.classList.add('active');
            signupView.style.display = 'block';
            signinView.style.display = 'none';
        });
    }

    // Handle Authentication Forms
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
                    window.location.href = 'dashboard.php';
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
                    window.location.href = 'dashboard.php';
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

    // Close Modal
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            authModal.classList.remove('active');
        });
    }

    // Close Modal on clicking outside
    authModal.addEventListener('click', (e) => {
        if (e.target === authModal) {
            authModal.classList.remove('active');
        }
    });

    // Toggle to Sign In View
    if (showSigninLink) {
        showSigninLink.addEventListener('click', (e) => {
            e.preventDefault();
            signupView.style.display = 'none';
            signinView.style.display = 'block';
        });
    }

    // Toggle to Sign Up View
    if (showSignupLink) {
        showSignupLink.addEventListener('click', (e) => {
            e.preventDefault();
            signinView.style.display = 'none';
            signupView.style.display = 'block';
        });
    }
});
