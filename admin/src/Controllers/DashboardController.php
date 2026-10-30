<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');

        // Escape outputs to prevent XSS
        $adminName = htmlspecialchars($jwtUser->name ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $adminEmail = htmlspecialchars($jwtUser->email ?? '', ENT_QUOTES, 'UTF-8');
        $adminRole = htmlspecialchars($jwtUser->role ?? 'Staff', ENT_QUOTES, 'UTF-8');

        // Fetch actual showcase metrics from SQLite
        $totalProducts = (int)$this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $activeProducts = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
        $amazonCount = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE platform_name LIKE '%Amazon%'")->fetchColumn();

        // Fetch products for redirect link overview
        $stmt = $this->db->query("SELECT id, name, tag, price, platform_name, external_url FROM products ORDER BY sort_order ASC, id ASC");
        $products = $stmt->fetchAll();

        $rowsHtml = '';
        foreach ($products as $p) {
            $id = (int)$p['id'];
            $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
            $tag = htmlspecialchars($p['tag'], ENT_QUOTES, 'UTF-8');
            $platform = htmlspecialchars($p['platform_name'], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($p['external_url'], ENT_QUOTES, 'UTF-8');
            $price = number_format((float)$p['price'], 2);

            $rowsHtml .= <<<HTML
            <tr class="hover:bg-amber-50/40 transition">
                <td class="py-3 px-3 sm:px-4 font-mono font-bold text-stone-600">#{$id}</td>
                <td class="py-3 px-3 sm:px-4 font-bold text-[#611B10]">
                    {$name}
                    <span class="block text-[10px] text-stone-500 font-normal">{$tag}</span>
                </td>
                <td class="py-3 px-3 sm:px-4 font-bold">₹{$price}</td>
                <td class="py-3 px-3 sm:px-4">
                    <span class="px-2.5 py-0.5 rounded-full bg-[#D4A853]/20 text-[#611B10] text-[10px] font-bold">{$platform}</span>
                </td>
                <td class="py-3 px-3 sm:px-4 max-w-xs truncate text-[11px] font-mono">
                    <a href="{$url}" target="_blank" class="text-sky-700 hover:underline truncate block">{$url}</a>
                </td>
                <td class="py-3 px-3 sm:px-4 text-right">
                    <a href="/mc-desk/products" class="px-3 py-1 bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white rounded-lg text-xs font-semibold transition">
                        Manage
                    </a>
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
    <title>Millacoco Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAF5EC; }
        .font-serif { font-family: 'Playfair Display', serif; }
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

    <!-- Responsive Sidebar (Slide-over on mobile, sticky on desktop) -->
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
                <a href="/mc-desk" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-chart-pie w-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/mc-desk/products" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-arrow-up-right-from-square w-4"></i>
                    <span>Marketplace Redirects</span>
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
        <header class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 sm:mb-8 pb-4 border-b border-[#D4A853]/20 gap-3">
            <div>
                <h2 class="font-serif text-xl sm:text-2xl font-bold text-[#611B10]">Showcase & Redirects Hub</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Manage public product presentation and outbound e-commerce destination links.</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Showcase Catalog Live
                </span>
            </div>
        </header>

        <!-- Redirection & Catalog Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5 mb-6 sm:mb-8">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Listed Products</span>
                    <i class="fa-solid fa-bottle-droplet text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">{$totalProducts} Sizes</div>
                <div class="text-[10px] text-emerald-600 font-semibold mt-1">{$activeProducts} currently active</div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Primary Channel</span>
                    <i class="fa-brands fa-amazon text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">Amazon India</div>
                <div class="text-[10px] text-stone-500 font-semibold mt-1">{$amazonCount} direct links configured</div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Direct Inquiries</span>
                    <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                </div>
                <div class="text-xl sm:text-2xl font-bold text-[#611B10] mt-2 break-all sm:break-normal">+91 80897 57815</div>
                <div class="text-[10px] text-stone-500 font-semibold mt-1">WhatsApp business desk</div>
            </div>
        </div>

        <!-- Active Marketplace Redirects Table -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-serif font-bold text-sm sm:text-base text-[#611B10]">Active Marketplace Redirect Links</h3>
                <a href="/mc-desk/products" class="text-xs font-bold text-[#611B10] hover:text-[#782315] flex items-center gap-1">
                    <span>Edit Destination URLs</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="w-full text-left text-xs min-w-[600px] sm:min-w-full">
                    <thead>
                        <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-3 sm:px-4">ID</th>
                            <th class="py-3 px-3 sm:px-4">Product</th>
                            <th class="py-3 px-3 sm:px-4">Listed Price</th>
                            <th class="py-3 px-3 sm:px-4">Destination</th>
                            <th class="py-3 px-3 sm:px-4">Marketplace URL</th>
                            <th class="py-3 px-3 sm:px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        {$rowsHtml}
                    </tbody>
                </table>
            </div>
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
}
