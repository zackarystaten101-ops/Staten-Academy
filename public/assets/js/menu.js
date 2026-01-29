/**
 * Mobile Menu Toggle Script
 * Handles hamburger menu functionality for mobile navigation
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenu);
    } else {
        initMenu();
    }

    function initMenu() {
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileClose = document.getElementById('mobile-close');
        const mobileBackdrop = document.getElementById('mobile-backdrop');

        if (!menuToggle || !mobileMenu) {
            console.warn('Menu elements not found');
            return;
        }

        // Toggle menu function
        function toggleMenu() {
            const isOpen = mobileMenu.getAttribute('aria-hidden') === 'false';
            
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        }

        function openMenu() {
            const header = document.querySelector('header.site-header');
            mobileMenu.setAttribute('aria-hidden', 'false');
            menuToggle.setAttribute('aria-expanded', 'true');
            if (header) {
                header.classList.add('open');
            }
            if (mobileBackdrop) {
                mobileBackdrop.setAttribute('aria-hidden', 'false');
                mobileBackdrop.classList.add('active');
            }
            document.body.style.overflow = 'hidden'; // Prevent body scroll
        }

        function closeMenu() {
            const header = document.querySelector('header.site-header');
            mobileMenu.setAttribute('aria-hidden', 'true');
            menuToggle.setAttribute('aria-expanded', 'false');
            if (header) {
                header.classList.remove('open');
            }
            if (mobileBackdrop) {
                mobileBackdrop.setAttribute('aria-hidden', 'true');
                mobileBackdrop.classList.remove('active');
            }
            document.body.style.overflow = ''; // Restore body scroll
        }

        // Event listeners
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        if (mobileClose) {
            mobileClose.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeMenu();
            });
        }

        if (mobileBackdrop) {
            mobileBackdrop.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeMenu();
            });
        }

        // Close menu when clicking on menu links
        const menuLinks = mobileMenu.querySelectorAll('a');
        menuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                closeMenu();
            });
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenu.getAttribute('aria-hidden') === 'false') {
                closeMenu();
            }
        });

        // Close menu on window resize (if resizing to desktop)
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768 && mobileMenu.getAttribute('aria-hidden') === 'false') {
                    closeMenu();
                }
            }, 250);
        });
    }
})();
