'use strict';

// ── Helpers ────────────────────────────────────────────────────────────
function showResult(outId, ok, text) {
    const out = document.getElementById(outId);
    if (!out) return;
    out.className = 'test-out ' + (ok ? 'ok' : 'err');
    out.textContent = text;
}

// ══════════════════════════════════════════════════════════════════════
//  1. XSS – Quick-fill buttons + innerHTML demo
// ══════════════════════════════════════════════════════════════════════

document.querySelectorAll('[data-xss-fill]').forEach(btn => {
    btn.addEventListener('click', () => {
        const nameInput = document.querySelector('input[name="name"]');
        if (nameInput) {
            nameInput.value = btn.dataset.xssFill;
            nameInput.focus();
        }
    });
});

// ══════════════════════════════════════════════════════════════════════
//  2. CSRF – Interactive test
//     "С токен"  → трябва да получим 200 OK
//     "БЕЗ токен" → трябва да получим 403
// ══════════════════════════════════════════════════════════════════════
async function testCsrf(withToken) {
    const out = document.getElementById('csrf-test-out');
    out.className = 'test-out';
    out.textContent = 'Изпращам...';

    const fd = new FormData();
    if (withToken) {
        const tokenInput = document.querySelector('input[name="csrf_token"]');
        if (tokenInput) fd.append('csrf_token', tokenInput.value);
    }

    try {
        const res  = await fetch('/?action=csrf-test', { method: 'POST', body: fd });
        const data = await res.json();
        showResult('csrf-test-out', res.ok, `[${res.status}] ${JSON.stringify(data, null, 2)}`);
    } catch (e) {
        showResult('csrf-test-out', false, 'Грешка: ' + e.message);
    }
}

document.getElementById('csrf-valid-btn')
    .addEventListener('click', () => testCsrf(true));
document.getElementById('csrf-invalid-btn')
    .addEventListener('click', () => testCsrf(false));

// ══════════════════════════════════════════════════════════════════════
//  4. Security Headers – Check button
// ══════════════════════════════════════════════════════════════════════
document.getElementById('headers-check-btn').addEventListener('click', async () => {
    const out = document.getElementById('headers-out');
    out.className = 'test-out';
    out.textContent = 'Зареждам...';

    try {
        const res = await fetch('/');
        const headers = [
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];
        const lines = headers.map(h => {
            const val = res.headers.get(h);
            return `${h}:\n  ${val !== null ? val : '(не е зададен)'}`;
        });
        out.className = 'test-out ok';
        out.textContent = lines.join('\n\n');
    } catch (e) {
        showResult('headers-out', false, 'Грешка: ' + e.message);
    }
});

// ══════════════════════════════════════════════════════════════════════
//  5. SQL Injection – Interactive demo
// ══════════════════════════════════════════════════════════════════════
async function runSqlDemo() {
    const input = document.getElementById('sql-input').value;
    const out   = document.getElementById('sql-out');
    out.className = 'test-out';
    out.textContent = 'Изпращам...';

    try {
        const res  = await fetch('/?action=sql-demo&input=' + encodeURIComponent(input));
        const data = await res.json();

        if (!data.ok) {
            showResult('sql-out', false, 'Грешка: ' + data.error);
            return;
        }

        function fmtRows(rows) {
            if (!rows || rows.length === 0) return '  (нито един ред)';
            return rows.map(r => `  { id:${r.id}, name:"${r.name}", email:"${r.email}" }`).join('\n');
        }

        const isAttack = data.vuln_rows && data.vuln_rows.length > 1;

        const lines = [
            '╔══ УЯЗВИМА заявка (директна конкатенация) ══════════════╗',
            `SQL: ${data.vuln_query}`,
            data.vuln_error
                ? `⚠ ${data.vuln_error}`
                : `Резултати (${data.vuln_rows.length} реда):`,
            data.vuln_error ? '' : fmtRows(data.vuln_rows),
            '',
            '╔══ БЕЗОПАСНА заявка (Prepared Statement) ════════════════╗',
            `SQL: ${data.safe_query}`,
            `Параметър: "${data.input}"`,
            `Резултати (${data.safe_rows.length} реда):`,
            fmtRows(data.safe_rows),
        ];

        out.className = 'test-out ' + (isAttack ? 'err' : 'ok');
        out.textContent = lines.join('\n');
    } catch (e) {
        showResult('sql-out', false, 'Грешка: ' + e.message);
    }
}

document.getElementById('sql-btn-alice')
    .addEventListener('click', () => { document.getElementById('sql-input').value = 'Alice'; runSqlDemo(); });
document.getElementById('sql-btn-inject')
    .addEventListener('click', () => { document.getElementById('sql-input').value = "' OR '1'='1"; runSqlDemo(); });
document.getElementById('sql-btn-drop')
    .addEventListener('click', () => { document.getElementById('sql-input').value = "Alice'; DROP TABLE users --"; runSqlDemo(); });
document.getElementById('sql-btn-run')
    .addEventListener('click', runSqlDemo);
document.getElementById('sql-input')
    .addEventListener('keydown', e => { if (e.key === 'Enter') runSqlDemo(); });

// ══════════════════════════════════════════════════════════════════════
//  6. Replay Attack – Nonce + Timestamp demo
// ══════════════════════════════════════════════════════════════════════
let replayNonce = null;
let replayTs    = null;

document.getElementById('replay-get-btn').addEventListener('click', async () => {
    const display = document.getElementById('replay-nonce-display');
    display.textContent = 'Зареждам...';

    try {
        const res  = await fetch('/?action=new-nonce');
        const data = await res.json();
        replayNonce = data.nonce;
        replayTs    = data.timestamp;

        display.textContent =
            `Nonce: ${data.nonce.substring(0, 12)}…  ts: ${new Date(data.timestamp * 1000).toLocaleTimeString()}`;

        document.getElementById('replay-send-btn').disabled   = false;
        document.getElementById('replay-resend-btn').disabled = false;
        showResult('replay-out', true, 'Нов nonce е получен. Натиснете "Изпрати".');
    } catch (e) {
        display.textContent = '';
        showResult('replay-out', false, 'Грешка: ' + e.message);
    }
});

async function sendReplay(label) {
    if (!replayNonce) {
        showResult('replay-out', false, 'Първо вземете nonce!');
        return;
    }

    const fd = new FormData();
    fd.append('nonce',     replayNonce);
    fd.append('timestamp', replayTs);

    try {
        const res  = await fetch('/?action=replay-test', { method: 'POST', body: fd });
        const data = await res.json();
        showResult('replay-out', res.ok, `[${label}] [${res.status}] ${data.message || data.error}`);
    } catch (e) {
        showResult('replay-out', false, 'Грешка: ' + e.message);
    }
}

document.getElementById('replay-send-btn')
    .addEventListener('click', () => sendReplay('1-ви опит'));
document.getElementById('replay-resend-btn')
    .addEventListener('click', () => sendReplay('2-ри опит (Replay)'));
