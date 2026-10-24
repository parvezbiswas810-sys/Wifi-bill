/**
 * Application JavaScript.
 *
 * Uses Alpine.js for interactive UI (menus, modals, searchable dropdowns) and
 * provides a small helper layer for AJAX calls with CSRF protection.
 */

import Alpine from 'alpinejs';
import { initCollectionChart } from './chart';

window.Alpine = Alpine;

/** CSRF-aware fetch helper used by every AJAX interaction. */
window.apiFetch = async function (url, options = {}) {
    const headers = Object.assign(
        {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        options.headers || {}
    );

    const method = (options.method || 'GET').toUpperCase();

    if (method !== 'GET') {
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            headers['X-CSRF-TOKEN'] = token.getAttribute('content');
        }
        if (options.json) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
        }
    }

    const response = await fetch(url, Object.assign({}, options, { headers }));

    let payload = null;
    const text = await response.text();
    try {
        payload = text ? JSON.parse(text) : null;
    } catch (e) {
        payload = { message: text };
    }

    if (!response.ok) {
        const error = new Error((payload && payload.message) || 'Request failed');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
};

/** Toast notifications. */
window.toast = function (message, type = 'info', timeout = 5000) {
    let stack = document.getElementById('toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'toast-stack';
        stack.className = 'toast-stack';
        document.body.appendChild(stack);
    }

    const styles = {
        success: 'alert-success',
        error: 'alert-danger',
        warning: 'alert-warning',
        info: 'alert-info',
    };

    const el = document.createElement('div');
    el.className = 'alert ' + (styles[type] || styles.info);
    el.setAttribute('role', 'status');
    el.textContent = message;
    stack.appendChild(el);

    setTimeout(() => {
        el.style.transition = 'opacity .25s ease, transform .25s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(12px)';
        setTimeout(() => el.remove(), 260);
    }, timeout);
};

/** Theme handling with system preference and localStorage persistence. */
window.theme = {
    get() {
        return localStorage.getItem('isp-theme') || 'system';
    },
    apply(mode) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = mode === 'dark' || (mode === 'system' && prefersDark);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    },
    set(mode) {
        localStorage.setItem('isp-theme', mode);
        this.apply(mode);
    },
    init() {
        this.apply(this.get());
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.get() === 'system') {
                this.apply('system');
            }
        });
    },
};

theme.init();

Alpine.start();

document.addEventListener('DOMContentLoaded', initCollectionChart);
