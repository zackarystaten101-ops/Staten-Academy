/**
 * Toast Notification System
 * Replaces alert() and confirm() with user-friendly notifications
 */

class ToastNotification {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'true');
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.style.cssText = `
            background: ${this.getBackgroundColor(type)};
            color: ${this.getTextColor(type)};
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            pointer-events: auto;
            animation: slideInRight 0.3s ease-out;
            min-height: 44px;
        `;

        const icon = this.getIcon(type);
        toast.innerHTML = `
            <i class="${icon}" style="font-size: 1.2rem; flex-shrink: 0;"></i>
            <span style="flex: 1; line-height: 1.4;">${this.escapeHtml(message)}</span>
            <button class="toast-close" aria-label="Close notification" style="
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                padding: 4px;
                font-size: 1.2rem;
                opacity: 0.7;
                transition: opacity 0.2s;
                min-width: 24px;
                min-height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
            ">&times;</button>
        `;

        this.container.appendChild(toast);

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(toast));
        closeBtn.addEventListener('mouseenter', () => closeBtn.style.opacity = '1');
        closeBtn.addEventListener('mouseleave', () => closeBtn.style.opacity = '0.7');

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }

        return toast;
    }

    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }

    remove(toast) {
        if (toast && toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }

    confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'toast-modal-overlay';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: fadeIn 0.2s ease;
            `;

            const dialog = document.createElement('div');
            dialog.className = 'toast-modal';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'toast-modal-title');
            dialog.style.cssText = `
                background: white;
                border-radius: 12px;
                padding: 24px;
                max-width: 400px;
                width: 100%;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                animation: slideUp 0.3s ease;
            `;

            dialog.innerHTML = `
                <h3 id="toast-modal-title" style="margin: 0 0 16px 0; color: #333; font-size: 1.3rem;">${this.escapeHtml(title)}</h3>
                <p style="margin: 0 0 24px 0; color: #666; line-height: 1.6;">${this.escapeHtml(message)}</p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button class="toast-modal-btn toast-modal-cancel" style="
                        padding: 10px 20px;
                        border: 2px solid #ddd;
                        background: white;
                        color: #333;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        min-height: 44px;
                        transition: all 0.2s;
                    ">Cancel</button>
                    <button class="toast-modal-btn toast-modal-confirm" style="
                        padding: 10px 20px;
                        border: none;
                        background: #0b6cf5;
                        color: white;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        min-height: 44px;
                        transition: all 0.2s;
                    ">Confirm</button>
                </div>
            `;

            modal.appendChild(dialog);
            document.body.appendChild(modal);

            // Focus trap
            const focusableElements = dialog.querySelectorAll('button');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            const handleTabKey = (e) => {
                if (e.key !== 'Tab') return;
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            };

            dialog.addEventListener('keydown', handleTabKey);

            const close = (result) => {
                modal.style.animation = 'fadeOut 0.2s ease';
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                }, 200);
                resolve(result);
            };

            dialog.querySelector('.toast-modal-cancel').addEventListener('click', () => close(false));
            dialog.querySelector('.toast-modal-confirm').addEventListener('click', () => close(true));
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) close(false);
            });

            // Escape key to close
            const handleEscape = (e) => {
                if (e.key === 'Escape') close(false);
            };
            dialog.addEventListener('keydown', handleEscape);

            // Focus first button
            setTimeout(() => firstElement.focus(), 100);
        });
    }

    getBackgroundColor(type) {
        const colors = {
            success: 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)',
            error: 'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)',
            warning: 'linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%)',
            info: 'linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%)'
        };
        return colors[type] || colors.info;
    }

    getTextColor(type) {
        const colors = {
            success: '#155724',
            error: '#721c24',
            warning: '#856404',
            info: '#0c5460'
        };
        return colors[type] || colors.info;
    }

    getIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .toast-modal-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .toast-modal-btn:active {
        transform: translateY(0);
    }
    
    @media (max-width: 480px) {
        #toast-container {
            top: 10px;
            right: 10px;
            left: 10px;
            max-width: calc(100% - 20px);
        }
        
        .toast-modal {
            max-width: 100% !important;
            margin: 10px;
        }
    }
`;
document.head.appendChild(style);

// Initialize global toast instance
const toast = new ToastNotification();

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.toast = toast;
}









