// ============================================================
// main.js — Webpack Encore entry point (loaded via encore_entry_*)
// Contains: CSS, jQuery, DataTables, and all UI scripting.
// DO NOT import this from importmap/app.js — it runs via webpack.
// ============================================================

// CSS
import './styles/app.css';
import './styles/rain.css';

// jQuery — webpack bundles this (not importmap); expose as global for inline scripts
import $ from 'jquery';
// Safely expose jQuery globally (window.$ and window.jQuery are normal writable props)
if (typeof window !== 'undefined') {
    window.$ = $;
    window.jQuery = $;
}

// DataTables v2: side-effect import auto-attaches to the bundled jQuery above.
// Do NOT call the export as a factory (dt(window,$)) — in webpack browser mode
// the module is already initialized and calling it with window as a selector crashes.
import 'datatables.net';
import 'datatables.net-dt/css/dataTables.dataTables.css';

// Initialize DataTables on page load
$(document).ready(function () {
    $('.datatable').DataTable({
        paging:    true,
        searching: true,
        ordering:  true,
        info:      true,
    });
});

// -----------------------------------------------------------------------------
// Landing page enhancements (navbar toggle, scroll reveal, FAQ accordion,
// lightweight form validation). Kept intentionally dependency-free.
// -----------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // Mobile navbar toggle
    const navToggle = document.querySelector('.nav-toggle');
    const primaryNav = document.getElementById('primary-nav');

    if (navToggle && primaryNav) {
        const setOpen = (open) => {
            document.body.classList.toggle('nav-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        navToggle.setAttribute('aria-expanded', 'false');

        navToggle.addEventListener('click', () => {
            const isOpen = document.body.classList.contains('nav-open');
            setOpen(!isOpen);
        });

        primaryNav.querySelectorAll('a').forEach((a) => {
            a.addEventListener('click', () => setOpen(false));
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') setOpen(false);
        });

        document.addEventListener('click', (e) => {
            const isOpen = document.body.classList.contains('nav-open');
            if (!isOpen) return;
            if (primaryNav.contains(e.target) || navToggle.contains(e.target)) return;
            setOpen(false);
        });
    }

    // Navbar shadow on scroll
    const siteNav = document.querySelector('.site-nav');
    if (siteNav) {
        const onScroll = () => {
            siteNav.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // Scroll reveal animations
    const revealEls = Array.from(document.querySelectorAll('.reveal'));
    if (revealEls.length) {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            document.documentElement.classList.add('reveal-ready');
            revealEls.forEach((el) => el.classList.add('is-visible'));
        } else {
            const vh = window.innerHeight || document.documentElement.clientHeight;
            const isInViewport = (el) => {
                const rect = el.getBoundingClientRect();
                return rect.top < vh * 0.9 && rect.bottom > vh * 0.1;
            };
            revealEls.forEach((el) => {
                if (isInViewport(el)) el.classList.add('is-visible');
            });

            document.documentElement.classList.add('reveal-ready');

            const io = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                },
                { threshold: 0.14 }
            );

            revealEls.forEach((el) => io.observe(el));
        }
    }

    // FAQ accordion
    const faqItems = Array.from(document.querySelectorAll('.faq-item'));
    if (faqItems.length) {
        const updateAria = (item, isOpen) => {
            item.classList.toggle('open', isOpen);
            item.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        faqItems.forEach((item) => {
            const existing = item.getAttribute('aria-expanded');
            if (existing === null) updateAria(item, item.classList.contains('open'));

            item.addEventListener('click', () => {
                const isOpen = item.classList.contains('open');
                faqItems.forEach((other) => {
                    if (other === item) return updateAria(other, !isOpen);
                    updateAria(other, false);
                });
            });

            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });
    }

    // Client-side form validation
    const getFieldMessage = (field) => {
        const label = field.dataset.fieldLabel || field.getAttribute('aria-label') || field.placeholder || field.name || 'This field';
        const v = field.validity;

        if (v.valueMissing) return `${label} is required.`;
        if (v.typeMismatch) return field.type === 'email' ? 'Please enter a valid email address.' : `Please enter a valid ${label.toLowerCase()}.`;
        if (v.tooShort) return `${label} must be at least ${field.minLength} characters.`;
        if (v.patternMismatch) return `Please check the ${label.toLowerCase()} format.`;

        return `Please check the ${label.toLowerCase()}.`;
    };

    const clearFormErrors = (form) => {
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });
        form.querySelectorAll('.field-error.visible').forEach((el) => {
            el.classList.remove('visible');
            el.textContent = '';
        });
        const feedback = form.querySelector('.form-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.classList.remove('is-success', 'is-error');
        }
    };

    const showFieldError = (form, field, message) => {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        const errorEl = form.querySelector(`.field-error[data-error-for="${field.name}"]`);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
    };

    const validateForm = (form) => {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'));
        let firstInvalid = null;

        fields.forEach((field) => {
            if (field.checkValidity()) return;
            if (!firstInvalid) firstInvalid = field;

            const message = getFieldMessage(field);
            showFieldError(form, field, message);
        });

        return { isValid: !firstInvalid, firstInvalid };
    };

    document.querySelectorAll('form.js-validate').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const submitMode = form.dataset.submitMode || 'client';
            clearFormErrors(form);

            const { isValid, firstInvalid } = validateForm(form);
            const feedback = form.querySelector('.form-feedback');

            if (!isValid) {
                e.preventDefault();
                if (feedback) {
                    feedback.textContent = 'Please check the highlighted fields.';
                    feedback.classList.add('is-error');
                    feedback.classList.remove('is-success');
                }
                if (firstInvalid) firstInvalid.focus({ preventScroll: true });
                return;
            }

            if (submitMode === 'client') {
                e.preventDefault();
                if (feedback) {
                    feedback.textContent = 'Thanks! Your message has been captured. We will contact you soon.';
                    feedback.classList.add('is-success');
                    feedback.classList.remove('is-error');
                }
                form.reset();
            }
        });
    });
});