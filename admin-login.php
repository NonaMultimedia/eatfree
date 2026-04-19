<?php
/**
 * Admin Login Page
 * Separate login for administrators
 */

require_once __DIR__ . '/config/config.php';

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    header("Location: admin/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <title>Admin Login | EatFree</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Fonts -->
    <link rel="stylesheet" href="assets/css/Cooper%20Black%20Regular.css">
    <link rel="stylesheet" href="assets/css/Montserrat.css">
    
    <style>
        :root {
            --ef-primary: #6db049;
            --ef-primary-dark: #5a9a3d;
            --ef-dark: #1a1a1a;
            --ef-gray-900: #212529;
            --ef-gray-700: #495057;
            --ef-gray-500: #6c757d;
            --ef-gray-300: #dee2e6;
            --ef-white: #ffffff;
        }
        
        .admin-login-page {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--ef-dark) 0%, var(--ef-gray-900) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .admin-login-card {
            background: var(--ef-white);
            border-radius: 16px;
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        .admin-login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .admin-login-icon {
            width: 80px;
            height: 80px;
            background: var(--ef-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--ef-primary);
            font-size: 2.5rem;
        }
        .admin-login-title {
            font-family: 'Cooper Black Regular', serif;
            color: var(--ef-dark);
            margin-bottom: 0.5rem;
        }
        .admin-login-subtitle {
            color: var(--ef-gray-500);
        }
        .ef-input-group {
            margin-bottom: 1.5rem;
        }
        .ef-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--ef-gray-700);
            margin-bottom: 0.5rem;
        }
        .ef-input {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            border: 2px solid var(--ef-gray-300);
            border-radius: 12px;
            background: var(--ef-white);
            transition: all 0.3s ease;
        }
        .ef-input:focus {
            outline: none;
            border-color: var(--ef-primary);
            box-shadow: 0 0 0 4px rgba(109, 176, 73, 0.1);
        }
        .ef-input-icon {
            position: relative;
        }
        .ef-input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ef-gray-500);
        }
        .ef-input-icon .ef-input {
            padding-left: 3rem;
        }
        .btn-admin-login {
            background: var(--ef-dark);
            color: var(--ef-white);
            padding: 1rem;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-admin-login:hover {
            background: var(--ef-primary);
            transform: translateY(-2px);
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: var(--ef-gray-500);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .back-link a:hover {
            color: var(--ef-primary);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="admin-login-page">
        <div class="admin-login-card">
            <div class="admin-login-header">
                <div class="admin-login-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h2 class="admin-login-title">Admin Portal</h2>
                <p class="admin-login-subtitle">Secure access for administrators</p>
            </div>
            
            <form id="adminLoginForm">
                <div class="ef-input-group">
                    <label class="ef-label">Username or Email</label>
                    <div class="ef-input-icon">
                        <i class="bi bi-person"></i>
                        <input type="text" id="username" class="ef-input" placeholder="Enter username or email" required autocomplete="username">
                    </div>
                </div>
                
                <div class="ef-input-group">
                    <label class="ef-label">Password</label>
                    <div class="ef-input-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="password" class="ef-input" placeholder="Enter password" required>
                    </div>
                </div>
                
                <div id="errorMessage" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i><span id="errorText"></span>
                </div>
                
                <button type="submit" class="btn-admin-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
            
            <div class="back-link">
                <a href="/"><i class="bi bi-arrow-left me-1"></i>Back to Main Site</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const API_BASE = 'api/';
        
        // Form submission
        document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Signing in...';
            btn.disabled = true;
            
            // Hide error message
            document.getElementById('errorMessage').style.display = 'none';
            
            try {
                const response = await fetch(API_BASE + 'admin-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to admin dashboard
                    window.location.href = 'admin/dashboard.php';
                } else {
                    document.getElementById('errorText').textContent = result.message || 'Invalid username or password';
                    document.getElementById('errorMessage').style.display = 'block';
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('errorText').textContent = 'Network error. Please try again.';
                document.getElementById('errorMessage').style.display = 'block';
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
