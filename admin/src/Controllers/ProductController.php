<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Public API: Returns active products with their external marketplace URLs
     */
    public function getPublicProducts(Request $request, Response $response): Response
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, tag, category, price, image, external_url, platform_name, is_active 
             FROM products 
             WHERE is_active = 1 
             ORDER BY sort_order ASC, id ASC"
        );
        $products = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $products
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * HTML View: Product Management Screen in Admin
     */
    public function renderProductsPage(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');
        $adminName = htmlspecialchars($jwtUser->name ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $adminRole = htmlspecialchars($jwtUser->role ?? 'Staff', ENT_QUOTES, 'UTF-8');

        $stmt = $this->db->query(
            "SELECT id, name, tag, category, price, image, external_url, platform_name, is_active 
             FROM products 
             ORDER BY sort_order ASC, id ASC"
        );
        $products = $stmt->fetchAll();

        $rowsHtml = '';
        foreach ($products as $p) {
            $id = (int)$p['id'];
            $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
            $img = htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8');
            $tag = htmlspecialchars($p['tag'], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($p['external_url'], ENT_QUOTES, 'UTF-8');
            $platform = htmlspecialchars($p['platform_name'], ENT_QUOTES, 'UTF-8');
            $price = number_format((float)$p['price'], 2);

            $rowsHtml .= <<<HTML
            <tr class="hover:bg-amber-50/40 transition">
                <td class="py-3 px-4 font-mono font-bold text-stone-600">#{$id}</td>
                <td class="py-3 px-4">
                    <div class="flex items-center space-x-3">
                        <img src="/{$img}" alt="{$name}" class="w-10 h-10 object-contain rounded-xl bg-stone-50 border border-[#D4A853]/20 p-1">
                        <div>
                            <div class="font-bold text-[#611B10] text-xs">{$name}</div>
                            <span class="text-[9px] font-semibold bg-stone-100 text-stone-600 px-1.5 py-0.5 rounded">{$tag}</span>
                        </div>
                    </div>
                </td>
                <td class="py-3 px-4 font-bold text-[#611B10]">₹{$price}</td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#D4A853]/20 text-[#611B10]">{$platform}</span>
                </td>
                <td class="py-3 px-4 max-w-xs truncate text-stone-500 font-mono text-[11px]">
                    <a href="{$url}" target="_blank" class="hover:underline text-sky-700 truncate block">{$url}</a>
                </td>
                <td class="py-3 px-4 text-right">
                    <button onclick="openEditModal({$id}, '{$name}', {$p['price']}, '{$platform}', '{$url}')" class="px-3 py-1 bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white rounded-lg text-xs font-semibold transition shadow-xs">
                        Edit Link
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
    <title>Products & Marketplace Redirects - Millacoco Admin</title>
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
                <a href="/mc-desk" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-chart-pie w-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/mc-desk/products" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-arrow-up-right-from-square w-4"></i>
                    <span>Marketplace Redirects</span>
                </a>
                <a href="/" target="_blank" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-store w-4"></i>
                    <span>View Public Storefront ↗</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-[#D4A853]/20 bg-black/10 flex items-center justify-between">
            <div>
                <div class="text-xs font-bold text-white">{$adminName}</div>
                <div class="text-[10px] text-[#D4A853]">{$adminRole}</div>
            </div>
            <button onclick="logoutAdmin()" class="text-xs text-[#FAF5EC]/70 hover:text-white p-2" title="Logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex items-center justify-between mb-8 pb-4 border-b border-[#D4A853]/20">
            <div>
                <h2 class="font-serif text-2xl font-bold text-[#611B10]">Products & Marketplace Redirects</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Manage product pricing and destination URLs (Amazon, Flipkart, etc.)</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                SQLite Live Sync
            </span>
        </header>

        <div class="bg-white rounded-3xl border border-[#D4A853]/30 p-6 shadow-xs overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Product Name</th>
                        <th class="py-3 px-4">Price</th>
                        <th class="py-3 px-4">Platform</th>
                        <th class="py-3 px-4">External URL</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    {$rowsHtml}
                </tbody>
            </table>
        </div>
    </main>

    <!-- Edit Product Modal -->
    <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full border border-[#D4A853]/40 shadow-2xl">
            <div class="flex items-center justify-between pb-4 border-b border-stone-200">
                <h3 class="font-serif text-lg font-bold text-[#611B10]" id="modal-product-name">Edit Product</h3>
                <button onclick="closeEditModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <form onsubmit="handleProductUpdate(event)" class="space-y-4 pt-4">
                <input type="hidden" id="edit-id">
                <div>
                    <label class="block text-xs font-bold text-[#611B10] uppercase mb-1">Price (₹)</label>
                    <input type="number" step="0.01" id="edit-price" required class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-xs focus:ring-2 focus:ring-[#D4A853] focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#611B10] uppercase mb-1">Platform Name</label>
                    <input type="text" id="edit-platform" required class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-xs focus:ring-2 focus:ring-[#D4A853] focus:outline-none" placeholder="e.g. Amazon, Flipkart, WhatsApp">
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#611B10] uppercase mb-1">External Redirect URL</label>
                    <input type="url" id="edit-url" required class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-xs focus:ring-2 focus:ring-[#D4A853] focus:outline-none" placeholder="https://www.amazon.in/dp/...">
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-xs font-semibold text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" id="save-btn" class="px-6 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white text-xs font-bold transition">Save Changes</button>
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

        function openEditModal(id, name, price, platform, url) {
            document.getElementById('edit-id').value = id;
            document.getElementById('modal-product-name').textContent = 'Edit: ' + name;
            document.getElementById('edit-price').value = price;
            document.getElementById('edit-platform').value = platform;
            document.getElementById('edit-url').value = url;
            document.getElementById('edit-modal').classList.remove('hidden');
            document.getElementById('edit-modal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
        }

        async function handleProductUpdate(e) {
            e.preventDefault();
            const id = document.getElementById('edit-id').value;
            const price = document.getElementById('edit-price').value;
            const platform = document.getElementById('edit-platform').value;
            const url = document.getElementById('edit-url').value;
            const btn = document.getElementById('save-btn');

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch('/mc-desk/api/admin/products/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ price: price, platform_name: platform, external_url: url })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'Error updating product.');
                }
            } catch (err) {
                alert('Connection error.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
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

    /**
     * Admin API: List all products JSON
     */
    public function index(Request $request, Response $response): Response
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, tag, category, price, image, external_url, platform_name, is_active, sort_order 
             FROM products 
             ORDER BY sort_order ASC, id ASC"
        );
        $products = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $products
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Update a product's external redirect URL, platform name, and price
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $productId = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();

        $externalUrl = filter_var(trim($data['external_url'] ?? ''), FILTER_SANITIZE_URL);
        $platformName = htmlspecialchars(trim($data['platform_name'] ?? 'Amazon'), ENT_QUOTES, 'UTF-8');
        $price = filter_var($data['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if (empty($externalUrl) || !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Please provide a valid destination URL (e.g. https://www.amazon.in/dp/...)'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare(
            "UPDATE products 
             SET external_url = :external_url,
                 platform_name = :platform_name,
                 price = :price,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );

        $stmt->execute([
            'external_url' => $externalUrl,
            'platform_name' => $platformName,
            'price' => $price,
            'is_active' => $isActive,
            'id' => $productId
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Product redirect URL updated successfully.'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
