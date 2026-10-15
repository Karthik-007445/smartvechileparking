/* ParkSmart front-end behaviour (vanilla JS) */
(() => {
    'use strict';
    const body = document.body;
    const base = body.dataset.base || '/';
    const currency = body.dataset.currency || '₹';
    const taxPct = parseFloat(body.dataset.tax || '0');
    const grace = parseInt(body.dataset.grace || '0', 10);
    const serverNow = parseInt(body.dataset.now || '0', 10);
    const skew = serverNow ? serverNow - Math.floor(Date.now() / 1000) : 0;
    const nowSec = () => Math.floor(Date.now() / 1000) + skew;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const money = (n) => currency + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* Same billing rule as calc_fee() in PHP */
    function fee(seconds, rate) {
        const minutes = Math.ceil(Math.max(0, seconds) / 60);
        const hours = minutes <= grace ? 0 : Math.max(1, Math.ceil(minutes / 60));
        const sub = hours * rate;
        return sub + sub * taxPct / 100;
    }

    /* ---------- sidebar ---------- */
    $$('[data-toggle-sidebar]').forEach((el) => el.addEventListener('click', () => body.classList.toggle('sidebar-open')));

    /* ---------- clock ---------- */
    const clock = $('[data-clock]');
    if (clock) {
        const tick = () => { clock.textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
        tick(); setInterval(tick, 1000);
    }

    /* ---------- toasts ---------- */
    $$('.flash-toast').forEach((t) => {
        const close = () => { t.classList.add('leaving'); setTimeout(() => t.remove(), 300); };
        $('.flash-close', t)?.addEventListener('click', close);
        setTimeout(close, 5500);
    });

    /* ---------- confirm dialogs on forms ---------- */
    $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
    }));

    /* ---------- show / hide password ---------- */
    $$('[data-toggle-pass]').forEach((btn) => btn.addEventListener('click', () => {
        const input = $(btn.dataset.togglePass);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = `<i class="bi bi-eye${show ? '-slash' : ''}"></i>`;
    }));

    /* ---------- demo credential buttons ---------- */
    $$('[data-fill-email]').forEach((btn) => btn.addEventListener('click', () => {
        $('#email').value = btn.dataset.fillEmail;
        $('#password').value = btn.dataset.fillPass;
    }));

    /* ---------- password strength meter ---------- */
    $$('[data-strength-for]').forEach((box) => {
        const input = $(box.dataset.strengthFor);
        const bar = $('span', box);
        const label = $('small', box);
        input?.addEventListener('input', () => {
            const v = input.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const levels = [['Too weak', '#fb7185'], ['Weak', '#fb7185'], ['Okay', '#fbbf24'], ['Good', '#34d399'], ['Strong', '#22d3ee']];
            const [text, color] = levels[v ? score : 0];
            bar.style.setProperty('--w', (v ? Math.max(score, 1) * 25 : 0) + '%');
            bar.style.setProperty('--c', color);
            label.textContent = v ? text : 'Password strength';
        });
    });

    /* ---------- count-up numbers ---------- */
    $$('[data-count]').forEach((el) => {
        const target = parseFloat(el.dataset.count) || 0;
        const decimals = parseInt(el.dataset.decimals || '0', 10);
        const prefix = el.dataset.prefix || '';
        const dur = 900;
        const start = performance.now();
        const render = (v) => { el.textContent = prefix + v.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); };
        const step = (t) => {
            const p = Math.min(1, (t - start) / dur);
            render(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });

    /* ---------- live parking timers ---------- */
    const timers = $$('.booking-card[data-entry]');
    if (timers.length) {
        const pad = (n) => String(n).padStart(2, '0');
        const update = () => {
            timers.forEach((card) => {
                const secs = Math.max(0, nowSec() - parseInt(card.dataset.entry, 10));
                $('[data-timer]', card).textContent = `${pad(Math.floor(secs / 3600))}:${pad(Math.floor(secs % 3600 / 60))}:${pad(secs % 60)}`;
                $('[data-fee]', card).textContent = money(fee(secs, parseFloat(card.dataset.rate)));
            });
        };
        update(); setInterval(update, 1000);
    }

    /* ---------- mini animated parking lot (landing + auth) ---------- */
    $$('[data-mini-lot]').forEach((lot) => {
        const total = 32;
        const tiles = [];
        for (let i = 0; i < total; i++) {
            const t = document.createElement('i');
            const r = Math.random();
            if (r < 0.42) t.className = 'occ'; else if (r > 0.95) t.className = 'mnt';
            lot.appendChild(t); tiles.push(t);
        }
        const counter = lot.closest('.lot-card')?.querySelector('[data-mini-free]');
        const recount = () => { if (counter) counter.textContent = tiles.filter((t) => !t.classList.contains('occ') && !t.classList.contains('mnt')).length; };
        recount();
        setInterval(() => {
            const t = tiles[Math.floor(Math.random() * total)];
            if (t.classList.contains('mnt')) return;
            t.classList.toggle('occ');
            t.classList.add('flash');
            setTimeout(() => t.classList.remove('flash'), 700);
            recount();
        }, 1100);
    });

    /* ---------- live slot map ---------- */
    const slotMap = $('#slotMap[data-live]');
    if (slotMap) {
        const clickable = slotMap.dataset.clickable === '1';
        const render = (data) => {
            const floors = {};
            data.slots.forEach((s) => { (floors[s.floor] = floors[s.floor] || []).push(s); });
            slotMap.innerHTML = Object.keys(floors).sort().map((f) => {
                const list = floors[f];
                const free = list.filter((s) => s.status === 'available').length;
                const tiles = list.map((s) => {
                    const title = esc(`${s.code} · ${s.label} · ${money(s.rate)}/hr · ${s.status}`);
                    const inner = `<i class="bi ${esc(s.icon)}"></i><span>${esc(s.code)}</span>`;
                    return clickable && s.status === 'available'
                        ? `<a class="slot available" href="${base}book.php?slot=${esc(s.id)}" title="${title}">${inner}</a>`
                        : `<div class="slot ${esc(s.status)}" title="${title}">${inner}</div>`;
                }).join('');
                return `<div class="floor"><div class="floor-head"><span><i class="bi bi-layers-fill"></i> Floor ${esc(f)}</span><small>${free} / ${list.length} free</small></div><div class="slot-grid">${tiles}</div></div>`;
            }).join('');
            const c = data.counts;
            $$('[data-stat="available"]').forEach((el) => { el.textContent = c.available; });
            $$('[data-stat="occupied"]').forEach((el) => { el.textContent = c.occupied; });
            $$('[data-stat="maintenance"]').forEach((el) => { el.textContent = c.maintenance; });
            $$('[data-stat="percent"]').forEach((el) => { el.textContent = c.percent; });
            $$('[data-ring]').forEach((el) => el.style.setProperty('--p', c.percent));
        };
        const poll = () => fetch(base + 'api/slots.php', { cache: 'no-store' })
            .then((r) => r.json()).then((d) => { if (d.ok) render(d); }).catch(() => {});
        setInterval(poll, 10000);
    }

    /* ---------- booking page ---------- */
    const form = $('#bookForm');
    if (form) {
        const vehicle = $('#vehicle');
        const hours = $('#hours');
        const picks = $$('.slot-pick', form);
        const btn = $('#bookBtn');
        const els = { v: $('#sumVehicle'), s: $('#sumSlot'), r: $('#sumRate'), h: $('#sumHours'), t: $('#sumTotal') };
        const type = () => vehicle.selectedOptions[0]?.dataset.type;

        const refresh = () => {
            const sel = $('input[name="slot_id"]:checked', form);
            els.v.textContent = vehicle.selectedOptions[0]?.textContent.trim().split(' — ')[0] || '—';
            const h = Math.max(1, Math.min(24, parseInt(hours.value || '1', 10)));
            els.h.textContent = h + ' h';
            if (sel) {
                const rate = parseFloat(sel.dataset.rate);
                els.s.textContent = `${sel.dataset.code} · Floor ${sel.dataset.floor}`;
                els.r.textContent = money(rate) + ' / hr';
                els.t.textContent = money(fee(h * 3600, rate));
            } else {
                els.s.textContent = 'Not selected'; els.r.textContent = '—'; els.t.textContent = '—';
            }
            btn.disabled = !sel || !vehicle.value;
        };

        const filter = () => {
            const t = type();
            let any = false;
            picks.forEach((p) => {
                const match = p.dataset.type === t;
                p.classList.toggle('hidden', !match);
                if (!match) { const i = $('input', p); if (i.checked) i.checked = false; } else any = true;
            });
            $$('[data-floor]', form).forEach((f) => f.classList.toggle('hidden', !$('.slot-pick:not(.hidden)', f)));
            $('#noMatch')?.classList.toggle('d-none', any || !picks.length);
            refresh();
        };

        vehicle.addEventListener('change', filter);
        hours.addEventListener('input', refresh);
        $$('[data-hours]', form).forEach((b) => b.addEventListener('click', () => {
            hours.value = Math.max(1, Math.min(24, (parseInt(hours.value || '1', 10)) + parseInt(b.dataset.hours, 10)));
            refresh();
        }));
        form.addEventListener('change', (e) => { if (e.target.name === 'slot_id') refresh(); });

        // Pre-selected slot from ?slot=ID
        const pre = form.dataset.preSlot;
        if (pre) {
            const radio = $(`input[name="slot_id"][value="${pre}"]`, form);
            if (radio) {
                const slotType = radio.closest('.slot-pick').dataset.type;
                const match = Array.from(vehicle.options).find((o) => o.dataset.type === slotType);
                if (match) vehicle.value = match.value;
                filter();
                radio.checked = true;
                refresh();
                radio.closest('.slot-pick').scrollIntoView({ block: 'center', behavior: 'smooth' });
            } else filter();
        } else filter();
    }

    /* ---------- add-slot form: default rate follows type ---------- */
    const rateInput = $('#rateInput');
    if (rateInput) {
        $$('input[name="type"][data-rate]').forEach((r) => r.addEventListener('change', () => { rateInput.value = r.dataset.rate; }));
    }

    /* ---------- receipt QR ---------- */
    const qr = $('#qrcode');
    if (qr && window.QRCode) {
        new QRCode(qr, { text: qr.dataset.text, width: 96, height: 96, colorDark: '#0a1124', colorLight: '#ffffff' });
    }

    /* ---------- admin charts ---------- */
    if (window.Chart && window.PARK_CHARTS) {
        const d = window.PARK_CHARTS;
        Chart.defaults.color = '#8f9ab8';
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.borderColor = 'rgba(255,255,255,.07)';

        const rev = $('#revChart');
        if (rev) {
            const ctx = rev.getContext('2d');
            const grad = ctx.createLinearGradient(0, 0, 0, 300);
            grad.addColorStop(0, 'rgba(99,102,241,.55)');
            grad.addColorStop(1, 'rgba(34,211,238,.02)');
            new Chart(rev, {
                type: 'line',
                data: { labels: d.labels, datasets: [{ label: 'Revenue', data: d.revenue, borderColor: '#22d3ee', backgroundColor: grad, fill: true, tension: .4, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#22d3ee', pointBorderColor: '#050816', pointBorderWidth: 2 }] },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${d.currency}${Number(c.parsed.y).toLocaleString('en-IN')} · ${d.sessions[c.dataIndex]} sessions` } } },
                    scales: { y: { beginAtZero: true, ticks: { callback: (v) => d.currency + v } }, x: { grid: { display: false } } },
                },
            });
        }
        const donut = (id, labels, data, colors) => {
            const el = $(id);
            if (!el) return;
            new Chart(el, {
                type: 'doughnut',
                data: { labels, datasets: [{ data, backgroundColor: colors, borderColor: '#0a1124', borderWidth: 4, hoverOffset: 8 }] },
                options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } } },
            });
        };
        donut('#occChart', ['Available', 'Occupied', 'Maintenance'], d.occ, ['#34d399', '#fb7185', '#fbbf24']);
        donut('#mixChart', d.mixLabels, d.mixData, ['#22d3ee', '#a78bfa', '#34d399']);
    }
})();
