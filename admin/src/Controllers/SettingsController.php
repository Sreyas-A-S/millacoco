<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Public API: Returns site settings as a key-value JSON object
     */
    public function getPublicSettings(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("SELECT key, value, category FROM site_settings");
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $settings
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin HTML: User-friendly Content & Settings Management Screen
     */
    public function renderSettingsPage(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');
        $adminName = htmlspecialchars($jwtUser->name ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $adminRole = htmlspecialchars($jwtUser->role ?? 'Staff', ENT_QUOTES, 'UTF-8');

        $stmt = $this->db->query("SELECT key, value, category, label, description, field_type FROM site_settings ORDER BY category ASC, key ASC");
        $rows = $stmt->fetchAll();

        // Categorize settings
        $categorized = [
            'contact' => [],
            'homepage' => [],
            'about' => [],
            'social' => []
        ];

        foreach ($rows as $row) {
            $cat = $row['category'] ?? 'general';
            if (!isset($categorized[$cat])) {
                $categorized[$cat] = [];
            }
            $categorized[$cat][] = $row;
        }

        $categoryMeta = [
            'contact' => [
                'title' => 'Contact Details & Location',
                'desc' => 'Manage phone number, support email, milling desk address, working hours, and Google Map embed.',
                'icon' => 'fa-location-dot'
            ],
            'homepage' => [
                'title' => 'Homepage Copy & Hero Banner',
                'desc' => 'Update the homepage main hero title, subtitle, copra badge, and top announcement banner.',
                'icon' => 'fa-house'
            ],
            'about' => [
                'title' => 'About Us & Brand Heritage',
                'desc' => 'Craft your brand story, traditional Kuttiyadi heritage story, and purity mission statement.',
                'icon' => 'fa-book-open'
            ],
            'social' => [
                'title' => 'Marketplaces & Social Profiles',
                'desc' => 'Update Instagram, Facebook, and official Amazon store links.',
                'icon' => 'fa-share-nodes'
            ]
        ];

        $sectionsHtml = '';
        foreach ($categorized as $catKey => $items) {
            if (empty($items)) continue;
            $meta = $categoryMeta[$catKey] ?? [
                'title' => ucfirst($catKey),
                'desc' => 'Manage settings for ' . $catKey,
                'icon' => 'fa-sliders'
            ];

            $fieldsHtml = '';
            foreach ($items as $item) {
                $key = htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8');
                $val = htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $type = $item['field_type'] ?? 'text';

                $inputHtml = '';
                if ($type === 'textarea') {
                    $inputHtml = <<<HTML
                    <textarea 
                        name="{$key}" 
                        id="setting-{$key}" 
                        rows="3" 
                        class="w-full px-4 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] focus:ring-2 focus:ring-[#611B10]/15 outline-none transition text-xs sm:text-sm text-stone-900 bg-stone-50/50"
                        placeholder="Enter {$label}..."
                    >{$val}</textarea>
HTML;
                } else {
                    $inputHtml = <<<HTML
                    <input 
                        type="text" 
                        name="{$key}" 
                        id="setting-{$key}" 
                        value="{$val}" 
                        class="w-full px-4 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] focus:ring-2 focus:ring-[#611B10]/15 outline-none transition text-xs sm:text-sm text-stone-900 bg-stone-50/50"
                        placeholder="Enter {$label}..."
                    >
HTML;
                }

                $fieldsHtml .= <<<HTML
                <div class="p-4 sm:p-5 rounded-2xl bg-white border border-[#D4A853]/20 hover:border-[#D4A853]/50 transition shadow-2xs">
                    <div class="mb-2">
                        <label for="setting-{$key}" class="block text-xs font-bold text-[#611B10]">
                            {$label}
                        </label>
                        <p class="text-[11px] text-stone-500 mt-0.5">{$desc}</p>
                    </div>
                    {$inputHtml}
                </div>
HTML;
            }

            $sectionsHtml .= <<<HTML
            <div id="tab-{$catKey}" class="tab-content hidden space-y-4">
                <div class="bg-white p-5 rounded-2xl border border-[#D4A853]/30 shadow-2xs flex items-center justify-between">
                    <div>
                        <h3 class="font-serif font-bold text-base text-[#611B10] flex items-center gap-2">
                            <i class="fa-solid {$meta['icon']} text-[#D4A853]"></i>
                            <span>{$meta['title']}</span>
                        </h3>
                        <p class="text-xs text-stone-500 mt-0.5">{$meta['desc']}</p>
                    </div>
                    <button type="button" onclick="saveSettingsForm()" class="px-5 py-2.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs shadow-md transition active:scale-95 flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {$fieldsHtml}
                </div>
            </div>
HTML;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Millacoco Content & Settings Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAF5EC; }
        .font-serif { font-family: 'Playfair Display', serif; }
        .tab-btn.active {
            background-color: #611B10;
            color: #D4A853;
            box-shadow: 0 4px 12px rgba(97, 27, 16, 0.15);
        }
    </style>
</head>
<body class="text-[#3a0e07] flex flex-col md:flex-row min-h-screen">

    <!-- Mobile Top Header Bar -->
    <div class="md:hidden bg-[#611B10] text-[#FAF5EC] px-4 py-3 flex items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-xl bg-[#1C3822] border border-[#D4A853]/40 flex items-center justify-center p-1">
                <img src="/images/logo_gold.webp" alt="Logo" class="w-full h-full object-contain">
            </div>
            <span class="font-serif font-bold text-sm tracking-wider text-white">MILLACOCO</span>
        </div>
        <button onclick="toggleMobileSidebar()" class="p-2 rounded-lg bg-white/10 text-[#D4A853] hover:text-white focus:outline-none">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
    </div>

    <!-- Backdrop for Mobile Drawer -->
    <div id="mobile-sidebar-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-black/60 z-40 hidden md:hidden transition-opacity"></div>

    <!-- Responsive Sidebar -->
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#611B10] text-[#FAF5EC] flex flex-col justify-between shrink-0 shadow-2xl transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300">
        <div>
            <!-- Brand Badge -->
            <div class="p-6 border-b border-[#D4A853]/20 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-[#1C3822] border border-[#D4A853]/40 flex items-center justify-center p-1.5 shadow">
                        <img src="/images/logo_gold.webp" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <h1 class="font-serif font-bold text-lg tracking-wider text-white">MILLACOCO</h1>
                        <span class="text-[9px] tracking-widest text-[#D4A853] uppercase block">Admin Control</span>
                    </div>
                </div>
                <button onclick="toggleMobileSidebar()" class="md:hidden text-stone-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Focused Navigation Links -->
            <nav class="p-4 space-y-1.5 text-xs font-semibold">
                <a href="/mc-desk" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-chart-pie w-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/mc-desk/products" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-arrow-up-right-from-square w-4"></i>
                    <span>Marketplace Redirects</span>
                </a>
                <a href="/mc-desk/settings" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-sliders w-4"></i>
                    <span>Site Content & Copy</span>
                </a>
                <a href="/" target="_blank" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-store w-4"></i>
                    <span>View Public Storefront ↗</span>
                </a>
            </nav>
        </div>

        <!-- User Info / Logout -->
        <div class="p-4 border-t border-[#D4A853]/20 bg-black/10">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-white">{$adminName}</div>
                    <div class="text-[10px] text-[#D4A853]">{$adminRole}</div>
                </div>
                <button onclick="logoutAdmin()" class="text-xs text-[#FAF5EC]/70 hover:text-white p-2" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b border-[#D4A853]/20 gap-3">
            <div>
                <h2 class="font-serif text-xl sm:text-2xl font-bold text-[#611B10]">Site Content & Copy Management</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Edit homepage texts, contact details, About Us story, and social links in real time.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="saveSettingsForm()" class="px-5 py-2.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs shadow-md transition active:scale-95 flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save All Changes</span>
                </button>
            </div>
        </header>

        <!-- Category Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <button type="button" onclick="switchCategory('contact', this)" class="tab-btn active px-4 py-2.5 rounded-full text-xs font-bold bg-white text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-location-dot"></i>
                <span>Contact Details</span>
            </button>
            <button type="button" onclick="switchCategory('homepage', this)" class="tab-btn px-4 py-2.5 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-house"></i>
                <span>Homepage Texts</span>
            </button>
            <button type="button" onclick="switchCategory('about', this)" class="tab-btn px-4 py-2.5 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-book-open"></i>
                <span>About Us & Heritage</span>
            </button>
            <button type="button" onclick="switchCategory('social', this)" class="tab-btn px-4 py-2.5 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-share-nodes"></i>
                <span>Social & Channels</span>
            </button>
        </div>

        <!-- Form Container -->
        <form id="settings-form" onsubmit="event.preventDefault(); saveSettingsForm();">
            {$sectionsHtml}
        </form>

        <!-- Toast Feedback Notification -->
        <div id="settings-toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 pointer-events-none transition-all duration-300 bg-[#611B10] text-[#D4A853] px-5 py-3.5 rounded-2xl shadow-2xl border border-[#D4A853]/40 flex items-center gap-3 text-xs font-bold">
            <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
            <span id="settings-toast-msg">Settings updated successfully!</span>
        </div>
    </main>

    <script>
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('mobile-sidebar-backdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function switchCategory(catKey, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('tab-' + catKey);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'bg-[#611B10]', 'text-[#D4A853]');
                b.classList.add('bg-white', 'text-stone-700');
            });
            btn.classList.add('active', 'bg-[#611B10]', 'text-[#D4A853]');
            btn.classList.remove('bg-white', 'text-stone-700');
        }

        // Show first tab by default
        document.addEventListener('DOMContentLoaded', () => {
            const firstTab = document.querySelector('.tab-content');
            if (firstTab) firstTab.classList.remove('hidden');
        });

        async function saveSettingsForm() {
            const form = document.getElementById('settings-form');
            const formData = new FormData(form);
            const payload = {};
            for (let [k, v] of formData.entries()) {
                payload[k] = v;
            }

            try {
                const res = await fetch('/mc-desk/api/admin/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    showToast('All changes saved and published live to storefront!');
                } else {
                    showToast(data.message || 'Error saving changes', true);
                }
            } catch (e) {
                showToast('Network error while saving settings', true);
            }
        }

        function showToast(msg, isError = false) {
            const toast = document.getElementById('settings-toast');
            const toastMsg = document.getElementById('settings-toast-msg');
            toastMsg.innerText = msg;
            toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
            }, 3500);
        }

        function logoutAdmin() {
            document.cookie = "admin_jwt=; Path=/mc-desk; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
            window.location.href = '/mc-desk/login';
        }
    </script>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Admin API: Updates multiple settings in batch
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || empty($body)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Invalid or empty settings data'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare("UPDATE site_settings SET value = :value, updated_at = CURRENT_TIMESTAMP WHERE key = :key");

        $this->db->beginTransaction();
        try {
            foreach ($body as $key => $value) {
                $stmt->execute([
                    ':value' => (string)$value,
                    ':key' => (string)$key
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Failed to save settings: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Site settings successfully updated!'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
