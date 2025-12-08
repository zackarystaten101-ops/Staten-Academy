/**
 * Timezone Detection and Management
 * Auto-detects user's timezone and sends to server
 */

(function() {
    'use strict';
    
    /**
     * Detect user's timezone from browser
     */
    function detectTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            // Fallback for older browsers
            const offset = -new Date().getTimezoneOffset() / 60;
            return 'UTC' + (offset >= 0 ? '+' : '') + offset;
        }
    }
    
    /**
     * Send timezone to server
     */
    function updateTimezoneOnServer(timezone, autoDetected = true) {
        // Check if user is logged in by checking for session
        // Only update if we have a valid session (check for user_id in body data or session)
        const hasSession = document.body.dataset.userId || 
                          (typeof window.userId !== 'undefined' && window.userId) ||
                          document.cookie.indexOf('PHPSESSID') !== -1;
        
        if (!hasSession) {
            return;
        }
        
        fetch('/api/calendar.php?action=update-timezone', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                timezone: timezone,
                auto_detected: autoDetected
            })
        }).catch(err => {
            console.log('Timezone update failed:', err);
        });
    }
    
    /**
     * Initialize timezone detection
     */
    function initTimezone() {
        const detectedTimezone = detectTimezone();
        
        // Store in sessionStorage to avoid repeated updates
        const storedTimezone = sessionStorage.getItem('user_timezone');
        const storedAutoDetected = sessionStorage.getItem('timezone_auto_detected');
        
        if (!storedTimezone || storedTimezone !== detectedTimezone) {
            sessionStorage.setItem('user_timezone', detectedTimezone);
            sessionStorage.setItem('timezone_auto_detected', 'true');
            updateTimezoneOnServer(detectedTimezone, true);
        }
        
        // Set timezone on window for use by other scripts
        window.userTimezone = detectedTimezone;
        window.timezoneAutoDetected = true;
        
        // Dispatch event for other scripts
        document.dispatchEvent(new CustomEvent('timezoneDetected', {
            detail: { timezone: detectedTimezone, autoDetected: true }
        }));
    }
    
    /**
     * Convert UTC datetime to local timezone for display
     */
    function convertToLocalTime(utcDateTime, format = 'YYYY-MM-DD HH:mm') {
        if (!utcDateTime) return '';
        
        try {
            const date = new Date(utcDateTime);
            if (isNaN(date.getTime())) return utcDateTime;
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return format
                .replace('YYYY', year)
                .replace('MM', month)
                .replace('DD', day)
                .replace('HH', hours)
                .replace('mm', minutes);
        } catch (e) {
            return utcDateTime;
        }
    }
    
    /**
     * Format time for display
     */
    function formatTimeForDisplay(timeString, timezone = null) {
        if (!timeString) return '';
        
        try {
            const tz = timezone || window.userTimezone || 'UTC';
            const [hours, minutes] = timeString.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes), 0);
            
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
                timeZone: tz
            });
        } catch (e) {
            return timeString;
        }
    }
    
    /**
     * Get timezone offset in hours
     */
    function getTimezoneOffset(timezone = null) {
        const tz = timezone || window.userTimezone || 'UTC';
        try {
            const date = new Date();
            const utcTime = date.getTime() + (date.getTimezoneOffset() * 60000);
            const tzDate = new Date(utcTime + (getTimezoneOffsetMinutes(tz) * 60000));
            return (tzDate.getTime() - utcTime) / (1000 * 60 * 60);
        } catch (e) {
            return 0;
        }
    }
    
    function getTimezoneOffsetMinutes(timezone) {
        // This is a simplified version - for production, use a proper timezone library
        const date = new Date();
        const utcDate = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
        const tzDate = new Date(date.toLocaleString('en-US', { timeZone: timezone }));
        return (tzDate - utcDate) / (1000 * 60);
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimezone);
    } else {
        initTimezone();
    }
    
    // Export functions to window
    window.TimezoneUtils = {
        detect: detectTimezone,
        convertToLocal: convertToLocalTime,
        formatTime: formatTimeForDisplay,
        getOffset: getTimezoneOffset,
        updateOnServer: updateTimezoneOnServer
    };
})();

