<?php
// --- API mode ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $file = __DIR__ . '/queue_state.json';
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null) {
        echo json_encode(['ok' => false]);
        exit;
    }
    file_put_contents($file, json_encode($data));
    echo json_encode(['ok' => true]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['state'])) {
    header('Content-Type: application/json');
    $file = __DIR__ . '/queue_state.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['members' => [], 'currentIndex' => 0, 'history' => []]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standup Queue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #0f0f0f;
            --surface: #181818;
            --surface2: #222222;
            --border: #2a2a2a;
            --accent: #c8f04a;
            --accent-dim: #9ab835;
            --text: #f0f0f0;
            --text-muted: #777;
            --text-dim: #444;
            --danger: #ff5c5c;
            --radius: 10px;
            --mono: 'DM Mono', monospace;
            --sans: 'DM Sans', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem 4rem;
        }

        header {
            width: 100%;
            max-width: 520px;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        header h1 {
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 400;
            color: var(--text-muted);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        header .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
            flex-shrink: 0;
            animation: pulse 2s ease-in-out infinite;
            margin-bottom: 1px;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }
        }

        .main {
            width: 100%;
            max-width: 520px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
        }

        .card-label {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text-dim);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        /* Current card */
        .current-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 1.25rem;
        }

        .avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--accent);
            color: #0f0f0f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--mono);
            font-size: 17px;
            font-weight: 500;
            flex-shrink: 0;
        }

        .current-name {
            font-size: 26px;
            font-weight: 300;
            color: var(--text);
            line-height: 1.2;
        }

        .current-pos {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .empty-state {
            font-size: 14px;
            color: var(--text-dim);
            padding: 6px 0;
        }

        /* Buttons */
        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        button {
            font-family: var(--sans);
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        button:hover {
            border-color: #444;
            color: var(--text);
            background: var(--surface2);
        }

        button:active {
            transform: scale(0.97);
        }

        .btn-primary {
            background: var(--accent);
            color: #0f0f0f;
            border-color: var(--accent);
            font-weight: 500;
        }

        .btn-primary:hover {
            background: var(--accent-dim);
            border-color: var(--accent-dim);
            color: #0f0f0f;
        }

        .btn-skip {
            border-color: #3a3a2a;
            color: #a0a060;
        }

        .btn-skip:hover {
            border-color: #666640;
            color: #c8c870;
            background: #2a2a18;
        }

        /* Queue list */
        .queue-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 1rem;
        }

        .queue-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 6px;
            background: var(--surface2);
            border: 1px solid transparent;
            transition: border-color 0.15s;
        }

        .queue-item.is-next {
            border-color: var(--accent);
        }

        .q-num {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-dim);
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        .q-name {
            flex: 1;
            font-size: 14px;
            color: var(--text);
        }

        .q-badge {
            font-family: var(--mono);
            font-size: 9px;
            background: var(--accent);
            color: #0f0f0f;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.08em;
        }

        .q-badge-skip {
            font-family: var(--mono);
            font-size: 9px;
            background: #2a2a18;
            color: #a0a060;
            border: 1px solid #3a3a28;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.08em;
        }

        .q-promote {
            background: none;
            border: 1px solid #555;
            color: #999;
            font-size: 12px;
            padding: 2px 7px;
            cursor: pointer;
            border-radius: 4px;
            line-height: 1.4;
            transition: all 0.15s;
            font-family: var(--mono);
        }

        .q-promote:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: transparent;
            transform: none;
        }

        .q-remove {
            background: none;
            border: none;
            color: #888;
            font-size: 16px;
            padding: 0 2px;
            cursor: pointer;
            border-radius: 4px;
            line-height: 1;
            transition: color 0.15s;
        }

        .q-remove:hover {
            color: var(--danger);
            background: transparent;
            transform: none;
        }

        /* Add row */
        .add-row {
            display: flex;
            gap: 8px;
        }

        input[type="text"] {
            flex: 1;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            color: var(--text);
            font-family: var(--sans);
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s;
        }

        input[type="text"]::placeholder {
            color: var(--text-dim);
        }

        input[type="text"]:focus {
            border-color: #444;
        }

        /* History */
        .history-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .h-name {
            color: var(--text);
            flex: 1;
        }

        .h-date {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-dim);
        }

        /* Status */
        #status {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-dim);
            text-align: center;
            margin-top: 1.5rem;
            min-height: 16px;
            transition: color 0.3s;
        }

        #status.ok {
            color: var(--accent-dim);
        }

        #status.err {
            color: var(--danger);
        }

        /* Loading overlay */
        #loading {
            position: fixed;
            inset: 0;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text-dim);
            letter-spacing: 0.1em;
            z-index: 100;
            transition: opacity 0.3s;
        }
    </style>
</head>

<body>

    <div id="loading">loading...</div>

    <header>
        <div class="dot"></div>
        <h1>Standup Queue</h1>
    </header>

    <div class="main">
        <div class="card" id="current-card">
            <div class="card-label">Up next</div>
            <div class="current-wrap" id="current-display"></div>
            <div class="btn-row">
                <button class="btn-primary" onclick="advance()">&#10003; Done — next person</button>
                <button class="btn-skip" onclick="skip()">⏭ Skip</button>
                <button onclick="shuffleQueue()">&#8635; Shuffle</button>
                <button onclick="resetToStart()">&#8617; Reset</button>
            </div>
        </div>

        <div class="card">
            <div class="card-label">Queue</div>
            <ul class="queue-list" id="queue-list"></ul>
            <div class="add-row">
                <input type="text" id="new-name" placeholder="Add team member…" maxlength="40"
                    onkeydown="if(event.key==='Enter')addMember()" />
                <button onclick="addMember()">+ Add</button>
            </div>
        </div>

        <div class="card">
            <div class="card-label">History</div>
            <ul class="history-list" id="history-list"></ul>
        </div>
    </div>

    <div id="status"></div>

    <script>
        let state = {
            members: [],
            currentIndex: 0,
            history: []
        };
        let saveTimer = null;

        async function load() {
            try {
                const r = await fetch('?state=1');
                state = await r.json();
            } catch (e) {
                setStatus('Could not load state', true);
            }
            document.getElementById('loading').style.opacity = '0';
            setTimeout(() => document.getElementById('loading').style.display = 'none', 300);
            render();
        }

        function scheduleSave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveNow, 400);
        }

        async function saveNow() {
            try {
                const r = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(state)
                });
                const d = await r.json();
                if (d.ok) setStatus('saved ' + new Date().toLocaleTimeString(), false);
                else setStatus('save failed', true);
            } catch (e) {
                setStatus('save error', true);
            }
        }

        function setStatus(msg, isErr) {
            const el = document.getElementById('status');
            el.textContent = msg;
            el.className = isErr ? 'err' : 'ok';
        }

        function initials(name) {
            return name.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();
        }

        function esc(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function render() {
            const cd = document.getElementById('current-display');
            const ql = document.getElementById('queue-list');
            const hl = document.getElementById('history-list');

            if (!state.members.length) {
                cd.innerHTML = '<span class="empty-state">Add team members below to get started.</span>';
            } else {
                const idx = state.currentIndex % state.members.length;
                const cur = state.members[idx];
                cd.innerHTML = `
      <div class="avatar">${initials(cur)}</div>
      <div>
        <div class="current-name">${esc(cur)}</div>
        <div class="current-pos">${idx + 1} / ${state.members.length}</div>
      </div>`;
            }

            ql.innerHTML = state.members.length ? state.members.map((m, i) => {
                const idx = state.currentIndex % state.members.length;
                const isNext = i === idx;
                const isPromotable = !isNext && state.members.length > 1;
                return `<li class="queue-item${isNext ? ' is-next' : ''}">
      <span class="q-num">${i + 1}</span>
      <span class="q-name">${esc(m)}</span>
      ${isNext ? '<span class="q-badge">next</span>' : ''}
      ${isPromotable ? `<button class="q-promote" onclick="promote(${i})" title="Move to next">&#8679;</button>` : ''}
      <button class="q-remove" onclick="removeMember(${i})" title="Remove">&#215;</button>
    </li>`;
            }).join('') : '<li class="empty-state">No members yet.</li>';

            hl.innerHTML = state.history.length ?
                [...state.history].reverse().slice(0, 8).map(h =>
                    `<li class="history-item">
          <span class="h-name" style="${h.skipped ? 'color:var(--text-muted)' : ''}">${esc(h.name)}</span>
          ${h.skipped ? '<span class="q-badge-skip">skipped</span>' : ''}
          <span class="h-date">${h.date}</span>
        </li>`).join('') :
                '<li class="empty-state">No history yet.</li>';
        }

        function advance() {
            if (!state.members.length) return;
            const cur = state.members[state.currentIndex % state.members.length];
            state.history.push({
                name: cur,
                date: new Date().toLocaleDateString('pt-PT', {
                    day: '2-digit',
                    month: 'short'
                }),
                skipped: false
            });
            if (state.history.length > 30) state.history.shift();
            state.currentIndex = (state.currentIndex + 1) % state.members.length;
            render();
            scheduleSave();
        }

        function skip() {
            if (!state.members.length) return;
            const cur = state.members[state.currentIndex % state.members.length];
            state.history.push({
                name: cur,
                date: new Date().toLocaleDateString('pt-PT', {
                    day: '2-digit',
                    month: 'short'
                }),
                skipped: true
            });
            if (state.history.length > 30) state.history.shift();
            state.currentIndex = (state.currentIndex + 1) % state.members.length;
            render();
            scheduleSave();
        }

        function promote(i) {
            if (!state.members.length || state.members.length < 2) return;
            const currentIdx = state.currentIndex % state.members.length;
            const insertAt = (currentIdx + 1) % state.members.length;
            const [member] = state.members.splice(i, 1);
            const adjustedCurrent = i < currentIdx ? currentIdx - 1 : currentIdx;
            const adjustedInsert = i < insertAt ? insertAt - 1 : insertAt;
            state.members.splice(adjustedInsert, 0, member);
            state.currentIndex = adjustedCurrent;
            render();
            scheduleSave();
        }

        function addMember() {
            const inp = document.getElementById('new-name');
            const name = inp.value.trim();
            if (!name) return;
            if (state.members.includes(name)) return setStatus('already in queue', true);
            state.members.push(name);
            inp.value = '';
            render();
            scheduleSave();
        }

        function removeMember(i) {
            state.members.splice(i, 1);
            if (state.members.length && state.currentIndex >= state.members.length) {
                state.currentIndex = state.currentIndex % state.members.length;
            }
            render();
            scheduleSave();
        }

        function shuffleQueue() {
            if (state.members.length < 2) return;
            for (let i = state.members.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [state.members[i], state.members[j]] = [state.members[j], state.members[i]];
            }
            state.currentIndex = 0;
            render();
            scheduleSave();
        }

        function resetToStart() {
            state.currentIndex = 0;
            render();
            scheduleSave();
        }

        load();
    </script>
</body>

</html>