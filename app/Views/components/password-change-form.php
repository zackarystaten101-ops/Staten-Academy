<?php
/**
 * Password Change Form Component
 * Reusable password change form for all dashboard pages
 * 
 * Required: $password_error (set by parent page if form was submitted)
 */
?>

<div class="card security-card">
    <h2><i class="fas fa-key"></i> Change Password</h2>
    
    <?php if (isset($password_error)): ?>
        <?php if ($password_error === 'password_changed'): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> Password changed successfully!
            </div>
        <?php else: ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo h($password_error); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <form method="POST" class="password-form">
        <div class="form-group">
            <label for="current_password">
                <i class="fas fa-lock"></i> Current Password
            </label>
            <div class="password-input-wrapper">
                <input type="password" id="current_password" name="current_password" required 
                       placeholder="Enter your current password" autocomplete="current-password">
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="form-group">
            <label for="new_password">
                <i class="fas fa-lock"></i> New Password
            </label>
            <div class="password-input-wrapper">
                <input type="password" id="new_password" name="new_password" required 
                       placeholder="Enter new password (minimum 6 characters)" 
                       minlength="6" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="password-strength" id="passwordStrength"></div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">
                <i class="fas fa-lock"></i> Confirm New Password
            </label>
            <div class="password-input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm your new password" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="password-match" id="passwordMatch"></div>
        </div>
        
        <button type="submit" name="change_password" class="btn-primary">
            <i class="fas fa-save"></i> Change Password
        </button>
    </form>
</div>

<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength indicator
document.getElementById('new_password')?.addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthDiv = document.getElementById('passwordStrength');
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    
    if (password.length > 0) {
        strengthDiv.innerHTML = `<span style="color: ${colors[strength-1] || colors[0]}">Strength: ${labels[strength-1] || labels[0]}</span>`;
    } else {
        strengthDiv.innerHTML = '';
    }
});

// Password match indicator
document.getElementById('confirm_password')?.addEventListener('input', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = e.target.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPass.length > 0) {
        if (newPass === confirmPass) {
            matchDiv.innerHTML = '<span style="color: #28a745"><i class="fas fa-check"></i> Passwords match</span>';
        } else {
            matchDiv.innerHTML = '<span style="color: #dc3545"><i class="fas fa-times"></i> Passwords do not match</span>';
        }
    } else {
        matchDiv.innerHTML = '';
    }
});
</script>

