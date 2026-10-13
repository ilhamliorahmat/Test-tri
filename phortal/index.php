<?php
/**
 * Phortal - Photography Portal Main Application Interface
 * Pure Vanilla PHP Web Application
 */

require_once __DIR__ . '/includes/auth.php';
$user = get_current_user_session();
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phortal — Photography Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-serif-title { font-family: 'Playfair Display', serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        /* Prevent unauthorized right click image downloading */
        .protected-img { user-select: none; -webkit-user-drag: none; }
    </style>
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-zinc-950">

    <!-- Top Bar Navigation Contract -->
    <header class="border-b border-zinc-800/80 bg-zinc-950/80 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-8">
            <!-- Zone 1: Single Element Brand Wordmark -->
            <a href="index.php" class="flex items-center gap-3 shrink-0 group">
                <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center font-bold text-zinc-950 text-sm tracking-widest shadow-lg shadow-amber-500/20 group-hover:scale-105 transition-transform">P</div>
                <span class="text-xl font-bold tracking-tight font-serif-title text-zinc-100 whitespace-nowrap">Phortal</span>
            </a>

            <!-- Zone 2: Navigation Links -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-zinc-400">
                <button onclick="switchTab('exhibitions')" class="hover:text-zinc-100 transition-colors whitespace-nowrap shrink-0 flex items-center gap-2" id="nav-exhibitions">
                    Exhibitions
                </button>
                <button onclick="openUnlockModal()" class="hover:text-zinc-100 transition-colors whitespace-nowrap shrink-0 flex items-center gap-2 text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Enter Code
                </button>
                <?php if ($user && in_array($user['role'], ['admin', 'curator'])): ?>
                <button onclick="switchTab('curator')" class="hover:text-zinc-100 transition-colors whitespace-nowrap shrink-0" id="nav-curator">
                    Curator Studio
                </button>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <button onclick="switchTab('admin')" class="hover:text-zinc-100 transition-colors whitespace-nowrap shrink-0" id="nav-admin">
                    Admin & Audit Logs
                </button>
                <?php endif; ?>
            </nav>

            <!-- Zone 3: Primary Action / User Role Badge -->
            <div class="flex items-center gap-4 shrink-0">
                <?php if ($user): ?>
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <div class="text-xs font-semibold text-zinc-200"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div class="text-[10px] uppercase font-mono-code text-amber-400 font-medium"><?= htmlspecialchars($user['role']) ?></div>
                        </div>
                        <button onclick="logout()" class="px-3 py-1.5 text-xs font-medium text-zinc-300 hover:text-white bg-zinc-900 hover:bg-zinc-800 border border-zinc-700/60 rounded-lg transition-colors">
                            Sign Out
                        </button>
                    </div>
                <?php else: ?>
                    <button onclick="openLoginModal()" class="px-4 py-2 text-xs font-semibold text-zinc-950 bg-amber-400 hover:bg-amber-300 rounded-lg shadow-lg shadow-amber-400/10 transition-colors whitespace-nowrap">
                        Staff Sign In
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-6 py-8 flex-1 w-full">

        <!-- Tab 1: Exhibitions & Gallery Viewer -->
        <section id="tab-exhibitions" class="space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-zinc-800/80 pb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold font-serif-title tracking-tight text-zinc-100">Photography Exhibitions</h1>
                    <p class="text-sm text-zinc-400 mt-2 max-w-xl">Fine art photography archives. Open public collections or enter a guest access code to unlock private exhibitions.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openUnlockModal()" class="px-4 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-xs font-semibold flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 0121 9z"></path></svg>
                        Unlock Private Gallery
                    </button>
                </div>
            </div>

            <!-- Galleries Grid -->
            <div id="galleries-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Loaded dynamically -->
            </div>

            <!-- Selected Gallery View -->
            <div id="gallery-detail" class="hidden space-y-6 pt-6 border-t border-zinc-800">
                <button onclick="closeGalleryDetail()" class="text-xs text-zinc-400 hover:text-zinc-100 flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to All Exhibitions
                </button>

                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-zinc-900/60 border border-zinc-800 p-6 rounded-2xl">
                    <div>
                        <span id="detail-code" class="text-xs font-mono-code text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded">CODE</span>
                        <h2 id="detail-title" class="text-2xl md:text-3xl font-bold font-serif-title mt-2 text-zinc-100">Gallery Title</h2>
                        <p id="detail-desc" class="text-sm text-zinc-400 mt-1">Gallery description text...</p>
                    </div>
                    <div class="text-xs text-zinc-500 space-y-1">
                        <div>Curated by <span id="detail-curator" class="text-zinc-300 font-medium">Curator Name</span></div>
                        <div>Watermark: <span id="detail-watermark" class="text-zinc-300 font-mono-code">Custom Text</span></div>
                    </div>
                </div>

                <!-- Photos Grid -->
                <div id="photos-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Photos rendered dynamically -->
                </div>
            </div>
        </section>

        <?php if ($user && in_array($user['role'], ['admin', 'curator'])): ?>
        <!-- Tab 2: Curator Studio -->
        <section id="tab-curator" class="hidden space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-zinc-800 pb-6">
                <div>
                    <h1 class="text-3xl font-bold font-serif-title text-zinc-100">Curator Studio</h1>
                    <p class="text-sm text-zinc-400 mt-1">Create new galleries, upload high-resolution photos directly to web server, generate guest access codes, and customize text watermarks.</p>
                </div>
                <button onclick="openCreateGalleryModal()" class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-zinc-950 rounded-lg text-xs font-bold transition-colors shadow-lg shadow-amber-400/10 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New Gallery
                </button>
            </div>

            <div id="curator-galleries-list" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Curator managed galleries -->
            </div>
        </section>
        <?php endif; ?>

        <?php if ($user && $user['role'] === 'admin'): ?>
        <!-- Tab 3: Admin Command Center & Audit Logs -->
        <section id="tab-admin" class="hidden space-y-8">
            <div class="border-b border-zinc-800 pb-6">
                <h1 class="text-3xl font-bold font-serif-title text-zinc-100">Admin Command Center</h1>
                <p class="text-sm text-zinc-400 mt-1">User role permissions management and immutable system audit logs tracking all administrative changes.</p>
            </div>

            <!-- User Management Card -->
            <div class="bg-zinc-900/60 border border-zinc-800 rounded-2xl p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-zinc-100">Staff Account Permissions</h3>
                        <p class="text-xs text-zinc-400">Manage Administrators and Curators.</p>
                    </div>
                    <button onclick="openCreateUserModal()" class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700 rounded-lg text-xs font-medium transition-colors">
                        Add New User
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-zinc-300">
                        <thead class="bg-zinc-950/80 uppercase font-mono-code text-zinc-500 border-b border-zinc-800">
                            <tr>
                                <th class="p-3">User</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Created</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body" class="divide-y divide-zinc-800/60">
                            <!-- Loaded via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Audit Logs Card -->
            <div class="bg-zinc-900/60 border border-zinc-800 rounded-2xl p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-zinc-100">Administrative Audit Trail</h3>
                        <p class="text-xs text-zinc-400">Real-time log tracking user actions, gallery creation/deletion, photo uploads, and security events.</p>
                    </div>
                    <button onclick="loadAuditLogs()" class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded-lg text-xs font-mono-code flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Refresh Logs
                    </button>
                </div>

                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-left text-xs text-zinc-300">
                        <thead class="bg-zinc-950/90 sticky top-0 uppercase font-mono-code text-zinc-500 border-b border-zinc-800">
                            <tr>
                                <th class="p-3">Timestamp</th>
                                <th class="p-3">User</th>
                                <th class="p-3">Action</th>
                                <th class="p-3">Resource</th>
                                <th class="p-3">IP Address</th>
                                <th class="p-3">Details</th>
                            </tr>
                        </thead>
                        <tbody id="audit-table-body" class="divide-y divide-zinc-800/40 font-mono-code text-[11px]">
                            <!-- Loaded via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <!-- Modals -->

    <!-- Guest Access Code Modal -->
    <div id="modal-unlock" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-zinc-900 border border-zinc-800 w-full max-w-md rounded-2xl p-6 space-y-6 shadow-2xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold font-serif-title text-zinc-100">Enter Access Code</h3>
                <button onclick="closeModal('modal-unlock')" class="text-zinc-500 hover:text-zinc-200">✕</button>
            </div>
            <p class="text-xs text-zinc-400">Enter the 6-character access code provided by your curator to unlock a private gallery exhibition.</p>
            
            <form onsubmit="submitGalleryCode(event)" class="space-y-4">
                <div>
                    <label class="text-xs uppercase font-mono-code text-zinc-400">Gallery Access Code</label>
                    <input type="text" id="input-code" required uppercase placeholder="e.g. ARCH2026" class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2.5 text-lg font-mono-code text-amber-400 uppercase tracking-widest focus:outline-none focus:border-amber-400">
                </div>
                <div id="unlock-error" class="text-xs text-rose-400 hidden">Invalid code. Please try again.</div>
                <button type="submit" class="w-full py-3 bg-amber-400 hover:bg-amber-300 text-zinc-950 font-bold rounded-lg text-sm transition-colors shadow-lg shadow-amber-400/10">
                    Unlock Gallery
                </button>
            </form>
        </div>
    </div>

    <!-- Staff Login Modal -->
    <div id="modal-login" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-zinc-900 border border-zinc-800 w-full max-w-md rounded-2xl p-6 space-y-6 shadow-2xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold font-serif-title text-zinc-100">Staff Sign In</h3>
                <button onclick="closeModal('modal-login')" class="text-zinc-500 hover:text-zinc-200">✕</button>
            </div>
            <p class="text-xs text-zinc-400">Sign in as an Administrator or Curator to manage galleries, upload photos, and view audit logs.</p>

            <form onsubmit="submitLogin(event)" class="space-y-4">
                <div>
                    <label class="text-xs text-zinc-400">Username</label>
                    <input type="text" id="login-user" required placeholder="admin or curator" class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2 text-sm text-zinc-100 focus:outline-none focus:border-amber-400">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Password</label>
                    <input type="password" id="login-pass" required placeholder="••••••••" class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2 text-sm text-zinc-100 focus:outline-none focus:border-amber-400">
                </div>
                <div class="p-3 bg-zinc-950/60 border border-zinc-800 rounded-lg text-[11px] text-zinc-400 font-mono-code space-y-1">
                    <div>Default Demo Credentials:</div>
                    <div class="text-amber-400">Admin: admin / admin123</div>
                    <div class="text-amber-400">Curator: curator / curator123</div>
                </div>
                <div id="login-error" class="text-xs text-rose-400 hidden">Invalid credentials.</div>
                <button type="submit" class="w-full py-2.5 bg-amber-400 hover:bg-amber-300 text-zinc-950 font-bold rounded-lg text-sm transition-colors">
                    Sign In
                </button>
            </form>
        </div>
    </div>

    <!-- Create Gallery Modal -->
    <div id="modal-create-gallery" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-zinc-900 border border-zinc-800 w-full max-w-lg rounded-2xl p-6 space-y-6 shadow-2xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold font-serif-title text-zinc-100">New Exhibition Gallery</h3>
                <button onclick="closeModal('modal-create-gallery')" class="text-zinc-500 hover:text-zinc-200">✕</button>
            </div>

            <form onsubmit="submitCreateGallery(event)" class="space-y-4">
                <div>
                    <label class="text-xs text-zinc-400">Gallery Title</label>
                    <input type="text" id="cg-title" required placeholder="e.g. Modern Architecture Study" class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2 text-sm text-zinc-100 focus:outline-none focus:border-amber-400">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Description</label>
                    <textarea id="cg-desc" rows="3" placeholder="Brief curator notes about this exhibition..." class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2 text-sm text-zinc-100 focus:outline-none focus:border-amber-400"></textarea>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Unique Access Code</label>
                    <input type="text" id="cg-code" required placeholder="e.g. PORTAL-X01" class="w-full mt-1 bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-2 text-sm font-mono-code text-amber-400 uppercase tracking-wider focus:outline-none focus:border-amber-400">
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <input type="checkbox" id="cg-private" checked class="w-4 h-4 rounded bg-zinc-950 border-zinc-800 text-amber-400 focus:ring-0">
                    <label for="cg-private" class="text-xs text-zinc-300">Private Gallery (Requires Access Code for Guests)</label>
                </div>
                <button type="submit" class="w-full py-2.5 bg-amber-400 hover:bg-amber-300 text-zinc-950 font-bold rounded-lg text-sm transition-colors">
                    Create Exhibition
                </button>
            </form>
        </div>
    </div>

    <!-- Photo Lightbox Modal -->
    <div id="modal-lightbox" class="fixed inset-0 bg-black/95 z-50 hidden flex flex-col justify-between p-4 md:p-8">
        <div class="flex justify-between items-center text-zinc-400">
            <div>
                <h4 id="lightbox-title" class="text-lg font-serif-title font-bold text-zinc-100">Photo Title</h4>
                <p id="lightbox-caption" class="text-xs text-zinc-400">Caption...</p>
            </div>
            <button onclick="closeModal('modal-lightbox')" class="px-3 py-1 bg-zinc-800 text-zinc-200 hover:text-white rounded-lg text-sm font-mono-code">✕ Close</button>
        </div>
        <div class="flex-1 flex items-center justify-center p-4 my-2 overflow-hidden">
            <img id="lightbox-img" src="" alt="Watermarked Photography" class="max-h-[80vh] max-w-full object-contain rounded-lg shadow-2xl protected-img border border-zinc-800" oncontextmenu="return false;">
        </div>
        <div class="flex justify-between items-center text-xs text-zinc-500 font-mono-code border-t border-zinc-800/80 pt-3">
            <span>Watermarked Dynamic Stream (Bandwidth Optimized)</span>
            <span>Phortal Anti-Piracy Protection Enforced</span>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-zinc-800/80 bg-zinc-950 py-6 mt-12 text-center text-xs text-zinc-500">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>Phortal © <?= date('Y') ?> — Photography Portal & Gallery Management</div>
            <div class="font-mono-code text-[11px] text-zinc-600">Pure Vanilla PHP + SQLite Architecture</div>
        </div>
    </footer>

    <!-- App Logic Script -->
    <script>
        const API_BASE = '/api';
        let currentUnlockedCode = '';

        document.addEventListener('DOMContentLoaded', () => {
            loadGalleries();
        });

        function switchTab(tab) {
            ['exhibitions', 'curator', 'admin'].forEach(t => {
                const el = document.getElementById(`tab-${t}`);
                if (el) el.classList.add('hidden');
            });
            const active = document.getElementById(`tab-${tab}`);
            if (active) active.classList.remove('hidden');

            if (tab === 'exhibitions') loadGalleries();
            if (tab === 'curator') loadCuratorGalleries();
            if (tab === 'admin') {
                loadUsers();
                loadAuditLogs();
            }
        }

        async function loadGalleries() {
            try {
                const res = await fetch(`${API_BASE}/galleries`);
                const data = await res.json();
                const container = document.getElementById('galleries-container');
                container.innerHTML = '';

                (data.galleries || []).forEach(g => {
                    const card = document.createElement('div');
                    card.className = 'bg-zinc-900/60 border border-zinc-800/80 rounded-2xl p-6 space-y-4 hover:border-zinc-700 transition-all group flex flex-col justify-between';
                    card.innerHTML = `
                        <div>
                            <div class="flex items-center justify-between text-xs mb-2">
                                <span class="font-mono-code text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded">${g.code}</span>
                                <span class="text-zinc-500">${g.is_private ? '🔒 Private' : '🌐 Public'}</span>
                            </div>
                            <h3 class="text-xl font-bold font-serif-title text-zinc-100 group-hover:text-amber-400 transition-colors">${g.title}</h3>
                            <p class="text-xs text-zinc-400 mt-2 line-clamp-2">${g.description || 'No curator notes provided.'}</p>
                        </div>
                        <div class="pt-4 border-t border-zinc-800/60 flex items-center justify-between text-xs">
                            <span class="text-zinc-500">${g.photo_count || 0} Photos</span>
                            <button onclick="viewGallery(${g.id}, '${g.code}')" class="px-3 py-1.5 bg-amber-400/10 hover:bg-amber-400/20 text-amber-400 font-semibold rounded-lg transition-colors">
                                Open Exhibition →
                            </button>
                        </div>
                    `;
                    container.appendChild(card);
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function viewGallery(id, code) {
            try {
                const res = await fetch(`${API_BASE}/galleries?id=${id}&code=${code || ''}`);
                if (res.status === 403) {
                    openUnlockModal(code);
                    return;
                }
                const data = await res.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }

                const g = data.gallery;
                currentUnlockedCode = code || g.code;

                document.getElementById('detail-title').innerText = g.title;
                document.getElementById('detail-desc').innerText = g.description || '';
                document.getElementById('detail-code').innerText = g.code;
                document.getElementById('detail-curator').innerText = g.curator_name || 'Staff Curator';
                document.getElementById('detail-watermark').innerText = g.watermark_text || 'Custom Watermark';

                const grid = document.getElementById('photos-grid');
                grid.innerHTML = '';

                (g.photos || []).forEach(p => {
                    const imgUrl = `/api/images/${p.id}?w=800&code=${currentUnlockedCode}`;
                    const fullUrl = `/api/images/${p.id}?code=${currentUnlockedCode}`;
                    const tile = document.createElement('div');
                    tile.className = 'bg-zinc-900 border border-zinc-800/80 rounded-xl overflow-hidden group hover:border-zinc-700 transition-all flex flex-col justify-between';
                    tile.innerHTML = `
                        <div class="relative overflow-hidden cursor-pointer" onclick="openLightbox('${fullUrl}', '${p.title}', '${p.caption || ''}')">
                            <img src="${imgUrl}" alt="${p.title}" class="w-full h-56 object-cover protected-img group-hover:scale-105 transition-transform duration-500" oncontextmenu="return false;">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                                <span class="text-xs font-semibold text-zinc-100">Click to View High-Res</span>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-between border-t border-zinc-800/80 bg-zinc-950/40">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-200">${p.title}</h4>
                                <span class="text-[10px] font-mono-code text-zinc-500">${p.width} × ${p.height} px</span>
                            </div>
                        </div>
                    `;
                    grid.appendChild(tile);
                });

                document.getElementById('gallery-detail').classList.remove('hidden');
                document.getElementById('gallery-detail').scrollIntoView({ behavior: 'smooth' });
            } catch (e) {
                console.error(e);
            }
        }

        function closeGalleryDetail() {
            document.getElementById('gallery-detail').classList.add('hidden');
        }

        function openUnlockModal(presetCode = '') {
            if (presetCode) document.getElementById('input-code').value = presetCode;
            document.getElementById('modal-unlock').classList.remove('hidden');
        }

        function openLoginModal() {
            document.getElementById('modal-login').classList.remove('hidden');
        }

        function openCreateGalleryModal() {
            document.getElementById('modal-create-gallery').classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        async function submitGalleryCode(e) {
            e.preventDefault();
            const code = document.getElementById('input-code').value.trim();
            const res = await fetch(`${API_BASE}/galleries/verify-code`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-unlock');
                viewGallery(data.gallery_id, code);
            } else {
                document.getElementById('unlock-error').classList.remove('hidden');
            }
        }

        async function submitLogin(e) {
            e.preventDefault();
            const username = document.getElementById('login-user').value.trim();
            const password = document.getElementById('login-pass').value.trim();

            const res = await fetch(`${API_BASE}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                document.getElementById('login-error').classList.remove('hidden');
            }
        }

        async function logout() {
            await fetch(`${API_BASE}/auth/logout`, { method: 'POST' });
            location.reload();
        }

        async function submitCreateGallery(e) {
            e.preventDefault();
            const title = document.getElementById('cg-title').value.trim();
            const description = document.getElementById('cg-desc').value.trim();
            const code = document.getElementById('cg-code').value.trim();
            const is_private = document.getElementById('cg-private').checked;

            const res = await fetch(`${API_BASE}/galleries`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, description, code, is_private })
            });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-create-gallery');
                loadGalleries();
            } else {
                alert(data.error || 'Failed to create gallery');
            }
        }

        function openLightbox(url, title, caption) {
            document.getElementById('lightbox-img').src = url;
            document.getElementById('lightbox-title').innerText = title;
            document.getElementById('lightbox-caption').innerText = caption;
            document.getElementById('modal-lightbox').classList.remove('hidden');
        }

        async function loadAuditLogs() {
            try {
                const res = await fetch(`${API_BASE}/audit`);
                const data = await res.json();
                const tbody = document.getElementById('audit-table-body');
                tbody.innerHTML = '';

                (data.audit_logs || []).forEach(log => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-zinc-900/80 border-b border-zinc-800/40';
                    tr.innerHTML = `
                        <td class="p-3 text-zinc-500 whitespace-nowrap">${log.created_at}</td>
                        <td class="p-3 font-semibold text-amber-400">${log.username}</td>
                        <td class="p-3 text-zinc-200">${log.action}</td>
                        <td class="p-3 text-zinc-400">${log.target_type} #${log.target_id}</td>
                        <td class="p-3 text-zinc-500">${log.ip_address}</td>
                        <td class="p-3 text-zinc-300 max-w-xs truncate">${log.details || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function loadUsers() {
            try {
                const res = await fetch(`${API_BASE}/users`);
                const data = await res.json();
                const tbody = document.getElementById('users-table-body');
                tbody.innerHTML = '';

                (data.users || []).forEach(u => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-zinc-900/60 border-b border-zinc-800/60';
                    tr.innerHTML = `
                        <td class="p-3 font-semibold text-zinc-100">${u.full_name}</td>
                        <td class="p-3 font-mono-code text-zinc-400">@${u.username}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-mono-code ${u.role === 'admin' ? 'bg-amber-400/10 text-amber-400 border border-amber-400/20' : 'bg-zinc-800 text-zinc-300'}">${u.role}</span></td>
                        <td class="p-3 text-zinc-500 font-mono-code">${u.created_at}</td>
                        <td class="p-3 text-right">
                            ${u.username !== 'admin' ? `<button onclick="deleteUser(${u.id})" class="text-rose-400 hover:text-rose-300 font-mono-code">Delete</button>` : ''}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this staff user account?')) return;
            await fetch(`${API_BASE}/users/${id}`, { method: 'DELETE' });
            loadUsers();
            loadAuditLogs();
        }
    </script>
</body>
</html>
