<?php
/**
 * Reset Password Page
 * Allows users to set a new password using a valid token
 */

// Start session
session_start();

// Include database connection
require_once '../config/db.php';

$error_message = '';
$success_message = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;

// If form submitted, token comes from POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';
}

// Function to validate strong password (copied from register.php)
function validateStrongPassword($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)";
    }
    
    $weak_passwords = ['password123', 'qwerty123', 'admin123', '12345678'];
    if (in_array(strtolower($password), $weak_passwords)) {
        $errors[] = "Password is too common. Please choose a stronger password";
    }
    
    return $errors;
}

// Basic validation: Check if token exists
// UPGRADE: Fetch password_hash for history check
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND status != 'Inactive'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $valid_token = true;
        $user = $result->fetch_assoc();
    } else {
        $error_message = "Invalid or expired password reset token. Please request a new one.";
    }
    $stmt->close();
} else {
    $error_message = "No reset token provided.";
}

// Handle Password Reset Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate strong password
    $password_errors = validateStrongPassword($password);
    
    if (!empty($password_errors)) {
        $error_message = implode(". ", $password_errors);
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // History Check: Compare with old password
        if (password_verify($password, $user['password_hash'])) {
             $error_message = "You cannot use your previous password. Please choose a new one.";
        } else {
             // Hash new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password and clear token
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
            $update_stmt->bind_param("si", $password_hash, $user['user_id']);
            
            if ($update_stmt->execute()) {
                $success_message = "Your password has been successfully reset. You can now login.";
                $valid_token = false; // Hide the form
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
            $update_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reset Password - Fundline</title>
   
    <!-- Google Fonts: Manrope -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
   
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
   
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
   
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            margin: 0; 
            padding: 0; 
            min-height: 100vh; 
            background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Manrope', sans-serif;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .reset-container {
            width: 100%;
            max-width: 520px;
            padding: 2.5rem;
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin: 1rem;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header */
        .header-text { 
            margin-bottom: 2rem; 
            text-align: center; 
        }
        
        .header-text .display-3 {
            background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        /* Alerts */
        .alert { 
            padding: 1rem 1.25rem; 
            border-radius: 1rem; 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: start; 
            gap: 0.75rem;
            border: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }
        
        .alert-success { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }
        
        /* Form Elements */
        .form-group { margin-bottom: 1.5rem; }
        
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-input-group {
            display: flex;
            border: 2px solid #e5e7eb;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-input-group:focus-within {
            border-color: #EF4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
            background: white;
        }
        
        .form-input {
            flex: 1;
            border: none;
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
            background: transparent;
            outline: none;
        }
        
        .form-input-addon {
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .form-input-addon:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .password-toggle { cursor: pointer; user-select: none; }
        
        /* Password Requirements */
        .password-requirements {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 4px solid #EF4444;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-top: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #6b7280;
            margin: 0.4rem 0;
            transition: all 0.3s;
        }
        
        .requirement-item.met { 
            color: #059669;
            font-weight: 600;
        }
        
        .requirement-item .material-symbols-outlined { 
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .requirement-item.met .material-symbols-outlined {
            color: #059669;
        }
        
        /* Password Strength */
        .password-strength {
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            margin-top: 0.75rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.4s ease, background-color 0.4s ease;
            border-radius: 3px;
        }
        
        .strength-very-weak { width: 20%; background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); }
        .strength-weak { width: 40%; background: linear-gradient(90deg, #ef4444 0%, #f97316 100%); }
        .strength-medium { width: 60%; background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); }
        .strength-strong { width: 80%; background: linear-gradient(90deg, #10b981 0%, #34d399 100%); }
        .strength-very-strong { width: 100%; background: linear-gradient(90deg, #059669 0%, #10b981 100%); }
        
        /* Button */
        .btn {
            border: none;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-lg { padding: 1rem 2rem; font-size: 1.1rem; }
        .w-full { width: 100%; }
        
        /* Footer */
        .footer-text { 
            margin-top: 2rem; 
            text-align: center; 
            font-size: 0.75rem; 
            color: #9ca3af;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Link */
        .link {
            color: #EF4444;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .link:hover {
            color: #B91C1C;
            text-decoration: underline;
        }
        
        /* Utilities */
        .text-center { text-align: center; }
        .text-main { color: #1f2937; }
        .text-muted { color: #6b7280; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-1 { margin-top: 0.25rem; }
        .mt-4 { margin-top: 1.5rem; }
        .pt-2 { padding-top: 0.5rem; }
        .border-t { border-top: 1px solid #e5e7eb; }
        .border-gray-100 { border-color: #f3f4f6; }
        .body-small { font-size: 0.875rem; }
        
        .rounded-r-none { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; }
        .border-r-0 { border-right: 0 !important; }
        
        /* Responsive */
        @media (max-width: 576px) {
            .reset-container {
                padding: 2rem 1.5rem;
                margin: 0.5rem;
            }
            
            .header-text .display-3 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body class="light">
    <main class="reset-container">
        <div class="header-text">
            <h1 class="display-3 text-main mb-2">Reset Password</h1>
            <p class="body-small text-muted">Create a new secure password</p>
        </div>
       
        <?php if (!empty($error_message) && empty($success_message)): ?>
        <div class="alert alert-error">
            <span class="material-symbols-outlined">error</span>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <span class="material-symbols-outlined">check_circle</span>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>
       
        <?php if ($valid_token): ?>
        <form method="POST" action="reset_password.php" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group mb-4">
                <label class="form-label" for="password">New Password</label>
                <div class="form-input-group">
                    <input
                        class="form-input rounded-r-none border-r-0"
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Create a strong password"
                        required
                    >
                    <div class="form-input-addon password-toggle" onclick="togglePassword('password', this)">
                        <span class="material-symbols-outlined text-muted">visibility</span>
                    </div>
                </div>

                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
                
                <div class="password-requirements">
                    <div class="requirement-item" id="req-length">
                        <span class="material-symbols-outlined">radio_button_unchecked</span>
                        <span>At least 12 characters</span>
                    </div>
                    <div class="requirement-item" id="req-lowercase">
                        <span class="material-symbols-outlined">radio_button_unchecked</span>
                        <span>One lowercase letter (a-z)</span>
                    </div>
                    <div class="requirement-item" id="req-uppercase">
                        <span class="material-symbols-outlined">radio_button_unchecked</span>
                        <span>One uppercase letter (A-Z)</span>
                    </div>
                    <div class="requirement-item" id="req-number">
                        <span class="material-symbols-outlined">radio_button_unchecked</span>
                        <span>One number (0-9)</span>
                    </div>
                    <div class="requirement-item" id="req-special">
                        <span class="material-symbols-outlined">radio_button_unchecked</span>
                        <span>One special character (!@#$%^&*)</span>
                    </div>
                </div>
                <p class="body-small text-muted mt-1" id="strengthText">Enter a password to see strength</p>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <div class="form-input-group">
                    <input
                        class="form-input rounded-r-none border-r-0"
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        placeholder="Confirm new password"
                        required
                    >
                    <div class="form-input-addon password-toggle" onclick="togglePassword('confirm_password', this)">
                        <span class="material-symbols-outlined text-muted">visibility</span>
                    </div>
                </div>
            </div>
           
            <button type="submit" class="btn btn-primary btn-lg w-full mt-4">
                <span>Reset Password</span>
                <span class="material-symbols-outlined">check</span>
            </button>
        </form>
        <?php endif; ?>
       
        <div class="text-center mt-4 pt-2 border-t border-gray-100">
            <p class="body-small text-muted mt-4">
                <a class="link font-bold" href="login.php">Back to Login</a>
            </p>
        </div>
        
        <div class="footer-text">
            © 2026 Fundline Micro Financing Services.
        </div>
    </main>
    
    <script>
        function togglePassword(inputId, toggle) {
            const input = document.getElementById(inputId);
            const icon = toggle.querySelector('.material-symbols-outlined');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // JS Password Validations
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.getElementById('strengthBar');
                const strengthText = document.getElementById('strengthText');
                
                // Check individual requirements
                const hasLength = password.length >= 12;
                const hasLowercase = /[a-z]/.test(password);
                const hasUppercase = /[A-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^a-zA-Z0-9]/.test(password);
                
                // Update requirement indicators
                updateRequirement('req-length', hasLength);
                updateRequirement('req-lowercase', hasLowercase);
                updateRequirement('req-uppercase', hasUppercase);
                updateRequirement('req-number', hasNumber);
                updateRequirement('req-special', hasSpecial);
                
                // Calculate strength score
                let strength = 0;
                if (hasLength) strength++;
                if (hasLowercase) strength++;
                if (hasUppercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                
                // Update strength bar
                strengthBar.className = 'password-strength-bar';
                if (password.length === 0) {
                    strengthText.textContent = 'Enter a password to see strength';
                } else if (strength <= 1) {
                    strengthBar.classList.add('strength-very-weak');
                    strengthText.textContent = 'Very weak password';
                } else if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                } else if (strength <= 3) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.textContent = 'Medium password';
                } else if (strength <= 4) {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                } else {
                    strengthBar.classList.add('strength-very-strong');
                    strengthText.textContent = 'Very strong password!';
                }
            });
            
            function updateRequirement(elementId, isMet) {
                const element = document.getElementById(elementId);
                const icon = element.querySelector('.material-symbols-outlined');
                
                if (isMet) {
                    element.classList.add('met');
                    icon.textContent = 'check_circle';
                } else {
                    element.classList.remove('met');
                    icon.textContent = 'radio_button_unchecked';
                }
            }
        }
    </script>
</body>
</html>

