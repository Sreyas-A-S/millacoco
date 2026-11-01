<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContentController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Public API: Returns active combo packs
     */
    public function getPublicCombos(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("SELECT combo_id as id, name, badge, subtitle, title, description as desc, price, old_price as oldPrice, image, btn_label as btnLabel FROM combo_packs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $combos = $stmt->fetchAll();

        // Convert numeric prices to float
        foreach ($combos as &$c) {
            $c['price'] = (float)$c['price'];
            $c['oldPrice'] = $c['oldPrice'] !== null ? (float)$c['oldPrice'] : null;
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $combos
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Public API: Returns active testimonials
     */
    public function getPublicTestimonials(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("SELECT id, author_name, author_location, rating, quote, verified_buyer, tag FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $testimonials = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $testimonials
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin HTML: Combos & Testimonials Management Screen
     */
    public function renderContentManagerPage(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');
        $adminName = htmlspecialchars($jwtUser->name ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $adminRole = htmlspecialchars($jwtUser->role ?? 'Staff', ENT_QUOTES, 'UTF-8');

        // Fetch combos
        $stmtCombos = $this->db->query("SELECT * FROM combo_packs ORDER BY sort_order ASC, id ASC");
        $combos = $stmtCombos->fetchAll();

        // Fetch testimonials
        $stmtTestimonials = $this->db->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id ASC");
        $testimonials = $stmtTestimonials->fetchAll();

        // Build Combos HTML Cards
        $combosHtml = '';
        foreach ($combos as $c) {
            $id = (int)$c['id'];
            $cid = htmlspecialchars($c['combo_id'], ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8');
            $badge = htmlspecialchars($c['badge'], ENT_QUOTES, 'UTF-8');
            $subtitle = htmlspecialchars($c['subtitle'], ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8');
            $price = number_format((float)$c['price'], 2);
            $oldPrice = $c['old_price'] ? number_format((float)$c['old_price'], 2) : '';
            $img = htmlspecialchars($c['image'], ENT_QUOTES, 'UTF-8');
            $btnLabel = htmlspecialchars($c['btn_label'], ENT_QUOTES, 'UTF-8');
            $activeStatus = (int)$c['is_active'] === 1 
                ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Active</span>'
                : '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-500">Draft</span>';

            $combosHtml .= <<<HTML
            <div class="bg-white rounded-2xl sm:rounded-3xl p-5 border border-[#D4A853]/30 shadow-2xs hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider bg-[#611B10] text-[#D4A853] px-2.5 py-1 rounded-full">{$badge}</span>
                        {$activeStatus}
                    </div>
                    <div class="flex items-start gap-4 mb-3">
                        <img src="/{$img}" alt="{$name}" class="w-16 h-16 object-contain rounded-xl bg-stone-50 border border-stone-200 p-1 shrink-0">
                        <div>
                            <h4 class="font-serif font-bold text-sm text-[#611B10] leading-snug">{$name}</h4>
                            <p class="text-[11px] text-stone-500 mt-0.5 font-medium">{$subtitle}</p>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="font-bold text-sm text-stone-900">₹{$price}</span>
                                <span class="text-[11px] text-stone-400 line-through">₹{$oldPrice}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-stone-600 line-clamp-2 mb-4 bg-stone-50 p-2.5 rounded-xl border border-stone-100">{$desc}</p>
                </div>
                <div class="pt-2 border-t border-stone-100 flex items-center justify-between">
                    <span class="text-[11px] font-mono text-stone-400">ID: {$cid}</span>
                    <button type="button" onclick='openEditComboModal({$id}, {$c['price']}, "{$btnLabel}", "{$desc}")' class="px-4 py-1.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs transition shadow-sm active:scale-95 flex items-center gap-1.5">
                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        <span>Edit Combo</span>
                    </button>
                </div>
            </div>
HTML;
        }

        // Build Testimonials HTML Table
        $testimonialsHtml = '';
        foreach ($testimonials as $t) {
            $id = (int)$t['id'];
            $name = htmlspecialchars($t['author_name'], ENT_QUOTES, 'UTF-8');
            $location = htmlspecialchars($t['author_location'], ENT_QUOTES, 'UTF-8');
            $tag = htmlspecialchars($t['tag'] ?? 'Verified Buyer', ENT_QUOTES, 'UTF-8');
            $quote = htmlspecialchars($t['quote'], ENT_QUOTES, 'UTF-8');
            $rating = (int)$t['rating'];
            $stars = str_repeat('<i class="fa-solid fa-star text-amber-500 text-[10px]"></i>', $rating);

            $testimonialsHtml .= <<<HTML
            <tr class="hover:bg-amber-50/40 transition">
                <td class="py-3 px-4 font-mono font-bold text-stone-500">#{$id}</td>
                <td class="py-3 px-4">
                    <div class="font-bold text-[#611B10] text-xs">{$name}</div>
                    <span class="text-[10px] text-stone-500">{$location}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-0.5">{$stars}</div>
                    <span class="text-[9px] font-semibold bg-emerald-50 text-emerald-700 px-1.5 py-0.2 rounded-full mt-0.5 inline-block">{$tag}</span>
                </td>
                <td class="py-3 px-4 text-xs text-stone-600 max-w-sm italic">
                    "{$quote}"
                </td>
                <td class="py-3 px-4 text-right">
                    <button type="button" onclick="deleteTestimonial({$id})" class="text-rose-500 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 transition" title="Delete Review">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            </tr>
HTML;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Millacoco Combos & Testimonials Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAF5EC; }
        .font-serif { font-family: 'Playfair Display', serif; }
        .content-tab.active {
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
                <a href="/mc-desk/combos-reviews" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-box-open w-4"></i>
                    <span>Combos &amp; Reviews</span>
                </a>
                <a href="/mc-desk/settings" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-sliders w-4"></i>
                    <span>Site Content &amp; Copy</span>
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
                                <button onclick="openPasswordModal()" class="text-xs text-[#FAF5EC]/70 hover:text-[#D4A853] p-1.5 transition" title="Change Password">
                    <i class="fa-solid fa-key"></i>
                </button>
                <button onclick="logoutAdmin()" class="text-xs text-[#FAF5EC]/70 hover:text-white p-1.5 transition" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b border-[#D4A853]/20 gap-3">
            <div>
                <h2 class="font-serif text-xl sm:text-2xl font-bold text-[#611B10]">Combos &amp; Reviews Manager</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Control homepage 3D coverflow combo packs and customer testimonials.</p>
            </div>
        </header>

        <!-- Segment Tabs -->
        <div class="flex items-center gap-2 mb-6">
            <button type="button" onclick="switchContentTab('combos', this)" class="content-tab active px-5 py-2.5 rounded-full text-xs font-bold bg-white text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Special Combo Packs</span>
            </button>
            <button type="button" onclick="switchContentTab('reviews', this)" class="content-tab px-5 py-2.5 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-star"></i>
                <span>Customer Reviews</span>
            </button>
        </div>

        <!-- Section 1: Combos Grid -->
        <div id="content-tab-combos" class="content-pane space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {$combosHtml}
            </div>
        </div>

        <!-- Section 2: Testimonials Table & Add Form -->
        <div id="content-tab-reviews" class="content-pane hidden space-y-6">
            <!-- Add New Testimonial Form Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 border border-[#D4A853]/30 shadow-2xs">
                <h3 class="font-serif font-bold text-sm sm:text-base text-[#611B10] mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle text-[#D4A853]"></i>
                    <span>Add Verified Customer Review</span>
                </h3>
                <form onsubmit="handleCreateTestimonial(event)" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
                    <div class="sm:col-span-4">
                        <label class="block font-bold text-stone-700 mb-1">Customer Name</label>
                        <input type="text" id="t-author" required placeholder="e.g. Ananya Nair" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block font-bold text-stone-700 mb-1">Location / City</label>
                        <input type="text" id="t-location" required placeholder="e.g. Kozhikode, Kerala" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block font-bold text-stone-700 mb-1">Tag / Category</label>
                        <input type="text" id="t-tag" placeholder="e.g. Traditional Cooking" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div class="sm:col-span-12">
                        <label class="block font-bold text-stone-700 mb-1">Review Quote</label>
                        <textarea id="t-quote" required rows="2" placeholder="Customer experience feedback..." class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]"></textarea>
                    </div>
                    <div class="sm:col-span-12 flex justify-end">
                        <button type="submit" class="px-5 py-2 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition shadow-sm active:scale-95 flex items-center gap-1.5">
                            <i class="fa-solid fa-plus"></i>
                            <span>Publish Testimonial</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Testimonials Table -->
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-2xs">
                <h3 class="font-serif font-bold text-sm sm:text-base text-[#611B10] mb-4">Published Reviews</h3>
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full text-left text-xs min-w-[600px] sm:min-w-full">
                        <thead>
                            <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Author</th>
                                <th class="py-3 px-4">Rating</th>
                                <th class="py-3 px-4">Review Quote</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="testimonials-table-body" class="divide-y divide-stone-100">
                            {$testimonialsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Combo Modal -->
    <div id="combo-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeComboModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-[#D4A853]/30">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h4 class="font-serif font-bold text-base text-[#611B10]">Edit Combo Offer</h4>
                <button onclick="closeComboModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleSaveCombo(event)" class="space-y-4 pt-4 text-xs">
                <input type="hidden" id="edit-combo-id">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Offer Price (₹)</label>
                    <input type="number" step="0.01" id="edit-combo-price" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Button Action Text</label>
                    <input type="text" id="edit-combo-btn" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Description</label>
                    <textarea id="edit-combo-desc" rows="3" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeComboModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition">Save Combo</button>
                </div>
            </form>
        </div>
    </div>

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

        function switchContentTab(tabKey, btn) {
            document.querySelectorAll('.content-pane').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('content-tab-' + tabKey);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('.content-tab').forEach(b => {
                b.classList.remove('active', 'bg-[#611B10]', 'text-[#D4A853]');
                b.classList.add('bg-white', 'text-stone-700');
            });
            btn.classList.add('active', 'bg-[#611B10]', 'text-[#D4A853]');
            btn.classList.remove('bg-white', 'text-stone-700');
        }

        function openEditComboModal(id, price, btnLabel, desc) {
            document.getElementById('edit-combo-id').value = id;
            document.getElementById('edit-combo-price').value = price;
            document.getElementById('edit-combo-btn').value = btnLabel;
            document.getElementById('edit-combo-desc').value = desc;

            const modal = document.getElementById('combo-modal');
            modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }

        function closeComboModal() {
            const modal = document.getElementById('combo-modal');
            modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleSaveCombo(e) {
            e.preventDefault();
            const id = document.getElementById('edit-combo-id').value;
            const price = parseFloat(document.getElementById('edit-combo-price').value);
            const btn_label = document.getElementById('edit-combo-btn').value;
            const description = document.getElementById('edit-combo-desc').value;

            try {
                const res = await fetch('/mc-desk/api/admin/combos/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ price, btn_label, description })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating combo');
                }
            } catch (err) {
                alert('Network error');
            }
        }

        async function handleCreateTestimonial(e) {
            e.preventDefault();
            const author_name = document.getElementById('t-author').value;
            const author_location = document.getElementById('t-location').value;
            const tag = document.getElementById('t-tag').value || 'Verified Buyer';
            const quote = document.getElementById('t-quote').value;

            try {
                const res = await fetch('/mc-desk/api/admin/testimonials', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ author_name, author_location, tag, quote, rating: 5 })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding review');
                }
            } catch (err) {
                alert('Network error');
            }
        }

        async function deleteTestimonial(id) {
            if (!confirm('Are you sure you want to remove this customer review?')) return;
            try {
                const res = await fetch('/mc-desk/api/admin/testimonials/' + id, {
                    method: 'DELETE'
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting review');
                }
            } catch (err) {
                alert('Network error');
            }
        }

        function logoutAdmin() {
            document.cookie = "admin_jwt=; Path=/mc-desk; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
            window.location.href = '/mc-desk/login';
        }
    </script>

    <!-- Change Password Modal -->
    <div id="password-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closePasswordModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-[#D4A853]/40">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-[#611B10] text-[#D4A853] flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Update Admin Password</h4>
                </div>
                <button onclick="closePasswordModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div id="pwd-alert" class="hidden my-3 p-3 rounded-xl text-xs font-semibold"></div>

            <form onsubmit="handleChangePassword(event)" class="space-y-3.5 pt-2 text-xs">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Current Password</label>
                    <input type="password" id="pwd-current" required placeholder="Enter current password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] focus:ring-2 focus:ring-[#611B10]/15 outline-none transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">New Password (min 6 chars)</label>
                    <input type="password" id="pwd-new" required minlength="6" placeholder="Enter new strong password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] focus:ring-2 focus:ring-[#611B10]/15 outline-none transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Confirm New Password</label>
                    <input type="password" id="pwd-confirm" required minlength="6" placeholder="Repeat new password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] focus:ring-2 focus:ring-[#611B10]/15 outline-none transition">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closePasswordModal()" class="px-4 py-2.5 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50 font-semibold transition">Cancel</button>
                    <button type="submit" id="pwd-submit-btn" class="px-5 py-2.5 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition shadow-sm active:scale-95 flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>Save Password</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
        function openPasswordModal() {
            const modal = document.getElementById('password-modal');
            const alertBox = document.getElementById('pwd-alert');
            alertBox.classList.add('hidden');
            document.getElementById('pwd-current').value = '';
            document.getElementById('pwd-new').value = '';
            document.getElementById('pwd-confirm').value = '';
            modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }

        function closePasswordModal() {
            const modal = document.getElementById('password-modal');
            modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleChangePassword(e) {
            e.preventDefault();
            const alertBox = document.getElementById('pwd-alert');
            const btn = document.getElementById('pwd-submit-btn');

            const current_password = document.getElementById('pwd-current').value;
            const new_password = document.getElementById('pwd-new').value;
            const confirm_password = document.getElementById('pwd-confirm').value;

            if (new_password !== confirm_password) {
                alertBox.className = 'my-3 p-3 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-200 text-rose-800';
                alertBox.textContent = 'New password and confirm password do not match.';
                alertBox.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch('/mc-desk/api/admin/change-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ current_password, new_password, confirm_password })
                });

                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    alertBox.className = 'my-3 p-3 rounded-xl text-xs font-semibold bg-emerald-50 border border-emerald-200 text-emerald-800';
                    alertBox.textContent = data.message;
                    alertBox.classList.remove('hidden');
                    setTimeout(() => {
                        closePasswordModal();
                    }, 2000);
                } else {
                    alertBox.className = 'my-3 p-3 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-200 text-rose-800';
                    alertBox.textContent = data.message || 'Error updating password';
                    alertBox.classList.remove('hidden');
                }
            } catch (err) {
                alertBox.className = 'my-3 p-3 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-200 text-rose-800';
                alertBox.textContent = 'Network or server error';
                alertBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-shield-halved text-xs"></i> <span>Save Password</span>';
            }
        }
</script>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Admin API: Updates a Combo Pack
     */
    public function updateCombo(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $body = $request->getParsedBody();

        $price = (float)($body['price'] ?? 0);
        $btnLabel = trim((string)($body['btn_label'] ?? 'ORDER PACK'));
        $description = trim((string)($body['description'] ?? ''));

        $stmt = $this->db->prepare("UPDATE combo_packs SET price = :price, btn_label = :btn_label, description = :description WHERE id = :id");
        $stmt->execute([
            ':price' => $price,
            ':btn_label' => $btnLabel,
            ':description' => $description,
            ':id' => $id
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Combo pack updated successfully'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Creates a Testimonial
     */
    public function createTestimonial(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $authorName = trim((string)($body['author_name'] ?? ''));
        $authorLocation = trim((string)($body['author_location'] ?? ''));
        $tag = trim((string)($body['tag'] ?? 'Verified Buyer'));
        $quote = trim((string)($body['quote'] ?? ''));
        $rating = (int)($body['rating'] ?? 5);

        if (empty($authorName) || empty($quote)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Author name and review quote are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare("INSERT INTO testimonials (author_name, author_location, tag, quote, rating, verified_buyer) VALUES (:author, :loc, :tag, :quote, :rating, 1)");
        $stmt->execute([
            ':author' => $authorName,
            ':loc' => $authorLocation,
            ':tag' => $tag,
            ':quote' => $quote,
            ':rating' => $rating
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Review published successfully'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Deletes a Testimonial
     */
    public function deleteTestimonial(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $stmt = $this->db->prepare("DELETE FROM testimonials WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Review deleted successfully'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
