// ============================================
// assets/js/app.js
// Global JavaScript - Ayam Penyet
// ============================================

'use strict';

// ===== UTILITY FUNCTIONS =====

/**
 * Format angka ke Rupiah
 */
function formatRupiah(angka) {
    return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
}

/**
 * Tampilkan toast notifikasi
 */
function showToast(message, type = 'success', duration = 2500) {
    const colors = {
        success: '#1A1A2E',
        error:   '#EF4444',
        warning: '#F59E0B',
        info:    '#3B82F6'
    };
    const icons = {
        success: '✅',
        error:   '❌',
        warning: '⚠️',
        info:    'ℹ️'
    };

    const container = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${colors[type] || colors.success};
        color: white;
        padding: 12px 20px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        animation: toastIn 0.3s ease forwards;
        font-family: 'Plus Jakarta Sans', sans-serif;
        margin-bottom: 8px;
    `;
    toast.innerHTML = `<span>${icons[type] || '✅'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function createToastContainer() {
    const div = document.createElement('div');
    div.id = 'toastContainer';
    div.style.cssText = `
        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        pointer-events: none;
        display: flex;
        flex-direction: column;
        align-items: center;
    `;
    document.body.appendChild(div);
    return div;
}

/**
 * Konfirmasi hapus dengan styling custom
 */
function confirmDelete(message = 'Yakin ingin menghapus?') {
    return confirm(message);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Format tanggal ke Indonesia
 */
function formatTanggal(dateStr) {
    const d = new Date(dateStr);
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

/**
 * Format waktu HH:MM
 */
function formatWaktu(dateStr) {
    const d = new Date(dateStr);
    return d.toTimeString().slice(0, 5);
}

/**
 * AJAX POST helper
 */
async function ajaxPost(url, data) {
    const body = typeof data === 'string' ? data :
        Object.entries(data).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return res.json();
}

/**
 * AJAX GET helper
 */
async function ajaxGet(url, params = {}) {
    const qs = Object.entries(params).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&');
    const res = await fetch(`${url}${qs ? '?' + qs : ''}`);
    return res.json();
}

// ===== KERANJANG FUNCTIONS =====

/**
 * Update tampilan badge keranjang di header
 */
function updateCartBadge(jumlah, total) {
    const badge = document.getElementById('cartCountBadge');
    const totalEl = document.getElementById('cartTotal');
    const floatBtn = document.getElementById('cartFloat');

    if (badge) badge.textContent = jumlah;
    if (totalEl) totalEl.textContent = formatRupiah(total);
    if (floatBtn) {
        floatBtn.style.display = jumlah > 0 ? 'block' : 'none';
    }
}

// ===== PRINT HELPER =====
function printPage() {
    window.print();
}

// ===== AUTO HIDE ALERT =====
document.addEventListener('DOMContentLoaded', () => {
    // Auto hide flash alerts setelah 4 detik
    const alerts = document.querySelectorAll('.flash-alert, .alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // Add toast CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
    `;
    document.head.appendChild(style);
});
