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
<body class="text-[#3a0e07] flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-[#611B10] text-[#FAF5EC] flex flex-col justify-between shrink-0 shadow-xl">
        <div>
            <!-- Brand Badge -->
            <div class="p-6 border-b border-[#D4A853]/20 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-[#1C3822] border border-[#D4A853]/40 flex items-center justify-center p-1.5 shadow">
                    <img src="/images/logo_gold.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="font-serif font-bold text-lg tracking-wider text-white">MILLACOCO</h1>
                    <span class="text-[9px] tracking-widest text-[#D4A853] uppercase block">Admin Control</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5 text-xs font-semibold">
                <a href="/mc-desk" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-chart-pie w-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/mc-desk/products" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-bottle-droplet w-4"></i>
                    <span>Products & Inventory</span>
                </a>
                <a href="/mc-desk/orders" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-cart-flatbed w-4"></i>
                    <span>Orders & Dispatch</span>
                </a>
                <a href="/mc-desk/agents" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-user-tie w-4"></i>
                    <span>Agents & Partners</span>
                </a>
                <a href="/mc-desk/documents" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-file-shield w-4"></i>
                    <span>Document Types</span>
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
    <main class="flex-1 p-8 overflow-y-auto">
        <header class="flex items-center justify-between mb-8 pb-4 border-b border-[#D4A853]/20">
            <div>
                <h2 class="font-serif text-2xl font-bold text-[#611B10]">Executive Overview</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Real-time store metrics and Kerala copra processing updates.</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Slim 4 Micro Engine Active
                </span>
            </div>
        </header>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Today's Revenue</span>
                    <i class="fa-solid fa-indian-rupee-sign text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">₹18,450</div>
                <div class="text-[10px] text-emerald-600 font-semibold mt-1">↑ 14% vs yesterday</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Active Orders</span>
                    <i class="fa-solid fa-box text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">24</div>
                <div class="text-[10px] text-[#611B10]/60 font-semibold mt-1">6 awaiting dispatch</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Active Agents</span>
                    <i class="fa-solid fa-handshake text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">12</div>
                <div class="text-[10px] text-emerald-600 font-semibold mt-1">All verified</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-[#D4A853]/30 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#611B10]/70">Oil Inventory</span>
                    <i class="fa-solid fa-cubes-stacked text-[#D4A853]"></i>
                </div>
                <div class="text-2xl font-bold text-[#611B10] mt-2">1,420 L</div>
                <div class="text-[10px] text-[#611B10]/60 font-semibold mt-1">Cold-pressed batch Kozhikode</div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="bg-white rounded-2xl border border-[#D4A853]/30 p-6 shadow-xs">
            <h3 class="font-serif font-bold text-base text-[#611B10] mb-4">Latest Store Orders</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">Order ID</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Items</th>
                            <th class="py-3 px-4">Total</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr class="hover:bg-amber-50/40">
                            <td class="py-3 px-4 font-mono font-bold text-[#611B10]">#MC-1082</td>
                            <td class="py-3 px-4 font-medium">Anand Menon (Kochi)</td>
                            <td class="py-3 px-4 text-stone-600">1L Classic Bottle × 2</td>
                            <td class="py-3 px-4 font-bold">₹580.00</td>
                            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Paid</span></td>
                        </tr>
                        <tr class="hover:bg-amber-50/40">
                            <td class="py-3 px-4 font-mono font-bold text-[#611B10]">#MC-1081</td>
                            <td class="py-3 px-4 font-medium">Divya Nambiar (Calicut)</td>
                            <td class="py-3 px-4 text-stone-600">Virgin Hair Oil × 1, Duo Pack × 1</td>
                            <td class="py-3 px-4 font-bold">₹910.00</td>
                            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">Processing</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
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
