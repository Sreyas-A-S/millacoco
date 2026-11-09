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
     * Public API: Track product link clicks and increment click_count
     */
    public function trackClick(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("UPDATE products SET click_count = COALESCE(click_count, 0) + 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }

        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Public API: Returns active products with their external marketplace URLs
     */
    public function getPublicProducts(Request $request, Response $response): Response
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, tag, category, price, discounted_from, image, external_url, platform_name, is_active, COALESCE(click_count, 0) AS click_count 
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
     * Admin HTML: Unified Content & Catalog Management Hub
     */
    public function renderProductsPage(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');
        $adminName = htmlspecialchars($jwtUser->name ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $adminRole = htmlspecialchars($jwtUser->role ?? 'Staff', ENT_QUOTES, 'UTF-8');

        // 1. Fetch Products
        $stmt = $this->db->query("SELECT * FROM products ORDER BY sort_order ASC, id ASC");
        $products = $stmt->fetchAll();

        // 2. Fetch Combos
        $stmtCombos = $this->db->query("SELECT * FROM combo_packs ORDER BY sort_order ASC, id ASC");
        $combos = $stmtCombos->fetchAll();

        // 3. Fetch Testimonials
        $stmtTestimonials = $this->db->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id ASC");
        $testimonials = $stmtTestimonials->fetchAll();

        // 4. Fetch Site Settings
        $stmtSettings = $this->db->query("SELECT * FROM site_settings ORDER BY category ASC, key ASC");
        $settingsRows = $stmtSettings->fetchAll();

        // 4b. Fetch Flagship Showcase Slides
        $stmtFlagship = $this->db->query("SELECT * FROM flagship_slides ORDER BY slide_index ASC");
        $flagshipSlides = $stmtFlagship->fetchAll();

        // 5. Fetch all distinct Tags and Categories for auto-complete datalists
        $distinctTagsStmt = $this->db->query("SELECT DISTINCT tag FROM products WHERE tag IS NOT NULL AND TRIM(tag) != '' ORDER BY tag ASC");
        $existingTags = $distinctTagsStmt->fetchAll(\PDO::FETCH_COLUMN);

        $distinctCatsStmt = $this->db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category ASC");
        $existingCats = $distinctCatsStmt->fetchAll(\PDO::FETCH_COLUMN);

        // Standard default recommendations if empty
        $defaultTags = ['Bestseller', 'Spout Pouch', 'Twin Value', 'Bulk Supply', 'Skincare', 'New Arrival', 'Signature Bottle', 'Kitchen Essential', 'Ayurvedic Wellness'];
        $defaultCats = ['daily', 'bulk', 'wellness', 'cooking', 'bundles'];

        $allTags = array_values(array_unique(array_filter(array_merge($defaultTags, $existingTags))));
        $allCats = array_values(array_unique(array_filter(array_merge($defaultCats, $existingCats))));

        $tagsDatalistOptions = '';
        foreach ($allTags as $tVal) {
            $safeVal = htmlspecialchars($tVal, ENT_QUOTES, 'UTF-8');
            $tagsDatalistOptions .= "<option value='{$safeVal}'>";
        }

        $catsDatalistOptions = '';
        foreach ($allCats as $cVal) {
            $safeVal = htmlspecialchars($cVal, ENT_QUOTES, 'UTF-8');
            $catsDatalistOptions .= "<option value='{$safeVal}'>";
        }

        // Build Products Rows & Mobile Cards
        $productRowsHtml = '';
        $productCardsHtml = '';
        foreach ($products as $p) {
            $id = (int)$p['id'];
            $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
            $img = htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8');
            $tag = htmlspecialchars($p['tag'] ?? 'Bestseller', ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($p['external_url'], ENT_QUOTES, 'UTF-8');
            $price = number_format((float)$p['price'], 2);
            $beforePrice = !empty($p['discounted_from']) ? (float)$p['discounted_from'] : 0;
            $beforePriceFmt = $beforePrice > 0 ? "₹" . number_format($beforePrice, 2) : "-";

            $nameJs = htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8');
            $tagJs = htmlspecialchars(json_encode($p['tag'] ?? 'Bestseller'), ENT_QUOTES, 'UTF-8');
            $catJs = htmlspecialchars(json_encode($p['category'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imgJs = htmlspecialchars(json_encode($p['image'] ?? ''), ENT_QUOTES, 'UTF-8');
            $urlJs = htmlspecialchars(json_encode($p['external_url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $descJs = htmlspecialchars(json_encode($p['description'] ?? ''), ENT_QUOTES, 'UTF-8');

            // Desktop Table Row
            $productRowsHtml .= <<<HTML
            <tr class="hover:bg-amber-50/40 transition searchable-product-row" data-search="{$name} {$tag} {$id} {$p['category']}">
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
                <td class="py-3 px-4 font-bold text-[#611B10]">
                    ₹{$price}
                    <span class="block text-[10px] text-stone-400 line-through font-normal">{$beforePriceFmt}</span>
                </td>
                <td class="py-3 px-4 max-w-xs truncate text-stone-500 font-mono text-[11px]">
                    <a href="{$url}" target="_blank" class="hover:underline text-sky-700 truncate block">{$url}</a>
                </td>
                <td class="py-3 px-4 text-right">
                    <button onclick='openEditModal({$id}, {$nameJs}, {$p['price']}, {$beforePrice}, {$tagJs}, {$catJs}, {$imgJs}, {$urlJs}, {$descJs})' class="px-3.5 py-1.5 bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center gap-1.5 ml-auto">
                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        <span>Edit Product</span>
                    </button>
                </td>
            </tr>
HTML;

            // Mobile Card View
            $productCardsHtml .= <<<HTML
            <div class="bg-white rounded-2xl border border-stone-200/80 p-3.5 shadow-2xs space-y-3 searchable-product-card" data-search="{$name} {$tag} {$id} {$p['category']}">
                <div class="flex items-start gap-3">
                    <img src="/{$img}" alt="{$name}" class="w-14 h-14 object-contain rounded-xl bg-stone-50 border border-stone-200 p-1 shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] font-mono text-stone-400 font-bold">#{$id}</span>
                            <span class="text-[9px] font-semibold bg-[#FAF5EC] text-[#611B10] border border-[#D4A853]/40 px-2 py-0.5 rounded-full truncate">{$tag}</span>
                        </div>
                        <h4 class="font-bold text-[#611B10] text-xs leading-snug truncate mt-0.5">{$name}</h4>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="font-bold text-sm text-stone-900">₹{$price}</span>
                            <span class="text-[10px] text-stone-400 line-through">{$beforePriceFmt}</span>
                        </div>
                    </div>
                </div>
                <div class="text-[11px] font-mono bg-stone-50 px-2.5 py-1.5 rounded-lg border border-stone-100 truncate text-stone-500">
                    <i class="fa-solid fa-link text-[#D4A853] mr-1"></i> <a href="{$url}" target="_blank" class="hover:underline text-sky-700">{$url}</a>
                </div>
                <button onclick='openEditModal({$id}, {$nameJs}, {$p['price']}, {$beforePrice}, {$tagJs}, {$catJs}, {$imgJs}, {$urlJs}, {$descJs})' class="w-full py-2 bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 active:scale-98">
                    <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                    <span>Edit Product</span>
                </button>
            </div>
HTML;
        }

        // Build Flagship Showcase Cards
        $flagshipHtml = '';
        foreach ($flagshipSlides as $fs) {
            $fsId = (int)$fs['id'];
            $fsIndex = (int)$fs['slide_index'];
            $fsTab = htmlspecialchars($fs['tab_label'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsScript = htmlspecialchars($fs['script_accent'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsTitle = htmlspecialchars($fs['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsSubtitle = htmlspecialchars($fs['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsDesc = htmlspecialchars($fs['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsPrice = number_format((float)$fs['price'], 2);
            $fsRawPrice = (float)$fs['price'];
            $fsImg = htmlspecialchars($fs['image_cutout'] ?? '', ENT_QUOTES, 'UTF-8');
            $fsUrl = htmlspecialchars($fs['external_url'] ?? '', ENT_QUOTES, 'UTF-8');

            $fsScriptJs = htmlspecialchars(json_encode($fs['script_accent'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsTitleJs = htmlspecialchars(json_encode($fs['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsSubtitleJs = htmlspecialchars(json_encode($fs['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsTabJs = htmlspecialchars(json_encode($fs['tab_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsDescJs = htmlspecialchars(json_encode($fs['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsImgJs = htmlspecialchars(json_encode($fs['image_cutout'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fsUrlJs = htmlspecialchars(json_encode($fs['external_url'] ?? ''), ENT_QUOTES, 'UTF-8');

            $flagshipHtml .= <<<HTML
            <div class="bg-white rounded-2xl p-5 border border-[#D4A853]/30 shadow-2xs hover:shadow-md transition flex flex-col justify-between searchable-flagship-card" data-search="{$fsTitle} {$fsTab} {$fsScript} {$fsSubtitle} {$fsId}">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider bg-[#611B10] text-[#D4A853] px-2.5 py-1 rounded-full">Slide #{$fsIndex} &bull; {$fsTab}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Active Showcase</span>
                    </div>
                    <div class="flex items-start gap-4 mb-3">
                        <img src="/{$fsImg}" alt="{$fsTitle}" class="w-16 h-20 object-contain rounded-xl bg-stone-50 border border-stone-200 p-1 shrink-0">
                        <div class="flex-1 min-w-0">
                            <span class="text-[11px] font-semibold text-[#2E6F40] italic block truncate">{$fsScript}</span>
                            <h4 class="font-serif font-bold text-sm text-[#611B10] leading-snug truncate">{$fsTitle}</h4>
                            <p class="text-[11px] text-stone-500 mt-0.5 font-medium truncate">{$fsSubtitle}</p>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="font-bold text-sm text-stone-900">₹{$fsPrice}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-stone-600 line-clamp-3 mb-4 bg-stone-50 p-2.5 rounded-xl border border-stone-100">{$fsDesc}</p>
                    <div class="text-[10px] text-stone-500 truncate mb-3 bg-stone-50 px-2.5 py-1.5 rounded-lg border border-stone-100">
                        <i class="fa-solid fa-link text-[#D4A853] mr-1"></i> <span class="font-mono">{$fsUrl}</span>
                    </div>
                </div>
                <div class="flex items-center justify-end">
                    <span class="text-[11px] font-mono text-stone-400">ID: {$fsId}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick='openEditFlagshipModal({$fsId}, {$fsTitleJs}, {$fsSubtitleJs}, {$fsScriptJs}, {$fsTabJs}, {$fsDescJs}, {$fsRawPrice}, {$fsImgJs}, {$fsUrlJs})' class="px-3.5 py-1.5 rounded-xl bg-stone-100 hover:bg-[#611B10] text-stone-700 hover:text-white font-bold text-xs transition flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                            <span>Edit Slide</span>
                        </button>
                        <button type="button" onclick="deleteFlagshipSlide({$fsId})" class="p-1.5 rounded-xl hover:bg-rose-50 text-rose-500 hover:text-rose-700 transition" title="Delete Slide">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
HTML;
        }

        // Build Combos Cards
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

            $combosHtml .= <<<HTML
            <div class="bg-white rounded-2xl p-5 border border-[#D4A853]/30 shadow-2xs hover:shadow-md transition flex flex-col justify-between searchable-combo-card" data-search="{$name} {$badge} {$subtitle} {$cid}">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider bg-[#611B10] text-[#D4A853] px-2.5 py-1 rounded-full">{$badge}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Active</span>
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
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick='openEditComboModal({$id}, {$c['price']}, "{$btnLabel}", "{$desc}")' class="px-3.5 py-1.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs transition shadow-sm active:scale-95 flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                            <span>Edit Combo</span>
                        </button>
                        <button type="button" onclick="deleteComboPack({$id})" class="p-1.5 rounded-full hover:bg-rose-50 text-rose-500 hover:text-rose-700 transition" title="Delete Combo">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
HTML;
        }

        // Build Testimonials Rows & Mobile Cards
        $testimonialsHtml = '';
        $testimonialsCardsHtml = '';
        foreach ($testimonials as $t) {
            $id = (int)$t['id'];
            $name = htmlspecialchars($t['author_name'], ENT_QUOTES, 'UTF-8');
            $location = htmlspecialchars($t['author_location'], ENT_QUOTES, 'UTF-8');
            $tag = htmlspecialchars($t['tag'] ?? 'Verified Buyer', ENT_QUOTES, 'UTF-8');
            $quote = htmlspecialchars($t['quote'], ENT_QUOTES, 'UTF-8');
            $avatar = htmlspecialchars($t['avatar_url'] ?? '', ENT_QUOTES, 'UTF-8');
            $rating = (int)$t['rating'];
            $stars = str_repeat('<i class="fa-solid fa-star text-amber-500 text-[10px]"></i>', $rating);

            $nameJs = htmlspecialchars(json_encode($t['author_name']), ENT_QUOTES, 'UTF-8');
            $locJs = htmlspecialchars(json_encode($t['author_location']), ENT_QUOTES, 'UTF-8');
            $tagJs = htmlspecialchars(json_encode($t['tag'] ?? 'Verified Buyer'), ENT_QUOTES, 'UTF-8');
            $quoteJs = htmlspecialchars(json_encode($t['quote']), ENT_QUOTES, 'UTF-8');
            $avatarJs = htmlspecialchars(json_encode($t['avatar_url'] ?? ''), ENT_QUOTES, 'UTF-8');

            $avatarImg = $avatar 
                ? "<img src='/{$avatar}' alt='{$name}' class='w-8 h-8 rounded-full object-cover border border-[#D4A853]/40 shadow-xs'>" 
                : "<div class='w-8 h-8 rounded-full bg-[#FAF5EC] border border-[#D4A853]/30 flex items-center justify-center text-[#611B10] text-xs font-serif font-bold'>" . mb_substr($t['author_name'], 0, 1) . "</div>";

            // Desktop Table Row
            $testimonialsHtml .= <<<HTML
            <tr class="hover:bg-amber-50/40 transition searchable-review-row" data-search="{$name} {$location} {$tag} {$quote} {$id}">
                <td class="py-3 px-4 font-mono font-bold text-stone-500">#{$id}</td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2.5">
                        {$avatarImg}
                        <div>
                            <div class="font-bold text-[#611B10] text-xs">{$name}</div>
                            <span class="text-[10px] text-stone-500">{$location}</span>
                        </div>
                    </div>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-0.5">{$stars}</div>
                    <span class="text-[9px] font-semibold bg-emerald-50 text-emerald-700 px-1.5 py-0.2 rounded-full mt-0.5 inline-block">{$tag}</span>
                </td>
                <td class="py-3 px-4 text-xs text-stone-600 max-w-sm italic">
                    "{$quote}"
                </td>
                <td class="py-3 px-4 text-right whitespace-nowrap">
                    <button type="button" onclick='openEditTestimonialModal({$id}, {$nameJs}, {$locJs}, {$tagJs}, {$rating}, {$quoteJs}, {$avatarJs})' class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold mr-1.5 transition" title="Edit Review">
                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                    </button>
                    <button type="button" onclick="deleteTestimonial({$id})" class="text-rose-500 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 transition" title="Delete Review">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            </tr>
HTML;

            // Mobile Card View
            $testimonialsCardsHtml .= <<<HTML
            <div class="bg-white rounded-2xl border border-stone-200/80 p-3.5 shadow-2xs space-y-2.5 searchable-review-card" data-search="{$name} {$location} {$tag} {$quote} {$id}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        {$avatarImg}
                        <div>
                            <div class="font-bold text-[#611B10] text-xs">{$name}</div>
                            <span class="text-[10px] text-stone-500">{$location}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" onclick='openEditTestimonialModal({$id}, {$nameJs}, {$locJs}, {$tagJs}, {$rating}, {$quoteJs}, {$avatarJs})' class="p-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold transition" title="Edit Review">
                            <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                        </button>
                        <button type="button" onclick="deleteTestimonial({$id})" class="text-rose-500 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 transition" title="Delete Review">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between text-[11px] pt-1">
                    <div class="flex items-center gap-0.5">{$stars}</div>
                    <span class="text-[9px] font-semibold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">{$tag}</span>
                </div>
                <p class="text-xs text-stone-600 italic bg-stone-50 p-2.5 rounded-xl border border-stone-100">
                    "{$quote}"
                </p>
            </div>
HTML;
        }

        // Build Settings Fields Grouped
        $settingsCategorized = ['contact' => [], 'homepage' => [], 'about' => [], 'social' => []];
        foreach ($settingsRows as $row) {
            $cat = $row['category'] ?? 'general';
            if (!isset($settingsCategorized[$cat])) $settingsCategorized[$cat] = [];
            $settingsCategorized[$cat][] = $row;
        }

        $settingsFieldsHtml = '';
        foreach ($settingsCategorized as $catKey => $items) {
            if (empty($items)) continue;
            $catTitle = ucfirst($catKey);
            $settingsFieldsHtml .= "<div class='sm:col-span-2 pt-2 border-b border-stone-200 pb-1 font-serif font-bold text-sm text-[#611B10]'>{$catTitle} Settings</div>";
            foreach ($items as $item) {
                $key = htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8');
                $val = htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $type = $item['field_type'] ?? 'text';

                $input = $type === 'textarea'
                    ? "<textarea name='{$key}' rows='2' class='w-full px-3 py-2 rounded-xl border border-stone-200 text-xs focus:border-[#611B10] outline-none'>{$val}</textarea>"
                    : "<input type='text' name='{$key}' value='{$val}' class='w-full px-3 py-2 rounded-xl border border-stone-200 text-xs focus:border-[#611B10] outline-none'>";

                $settingsFieldsHtml .= <<<HTML
                <div class="bg-stone-50/60 p-3.5 rounded-2xl border border-stone-200/80">
                    <label class="block text-xs font-bold text-[#611B10] mb-0.5">{$label}</label>
                    <p class="text-[10px] text-stone-500 mb-2">{$desc}</p>
                    {$input}
                </div>
HTML;
            }
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management Hub - Millacoco Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAF5EC; }
        .font-serif { font-family: 'Playfair Display', serif; }
        .hub-tab.active {
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

    <!-- Responsive Sidebar (Streamlined to single Content Management item) -->
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
                <a href="/mc-desk/content" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-white/10 text-[#D4A853] transition">
                    <i class="fa-solid fa-layer-group w-4"></i>
                    <span>Content Management</span>
                </a>
                <a href="/" target="_blank" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-white/5 text-[#FAF5EC]/80 hover:text-white transition">
                    <i class="fa-solid fa-store w-4"></i>
                    <span>View Public Storefront ↗</span>
                </a>
            </nav>
        </div>

        <!-- User Info / Logout -->
        <div class="p-4 border-t border-[#D4A853]/20 bg-black/10 flex items-center justify-between">
            <div>
                <div class="text-xs font-bold text-white">{$adminName}</div>
                <div class="text-[10px] text-[#D4A853]">{$adminRole}</div>
            </div>
            <div class="flex items-center gap-1">
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
                <h2 class="font-serif text-xl sm:text-2xl font-bold text-[#611B10]">Content Management Hub</h2>
                <p class="text-xs text-[#611B10]/70 mt-0.5">Manage products, combo packs, customer reviews, and site text copy in one unified place.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openAddProductModal()" class="px-4 py-2.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs shadow-md transition active:scale-95 flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add New Product</span>
                </button>
            </div>
        </header>

        <!-- Sub-Navigation Tabs inside Content Management -->
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <button type="button" onclick="switchHubSection('products', this)" class="hub-tab active px-4 py-2 rounded-full text-xs font-bold bg-white text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-bottle-droplet"></i>
                <span>Products Catalog</span>
            </button>
            <button type="button" onclick="switchHubSection('flagship', this)" class="hub-tab px-4 py-2 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-bottle-water"></i>
                <span>Flagship Showcase</span>
            </button>
            <button type="button" onclick="switchHubSection('combos', this)" class="hub-tab px-4 py-2 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Combo Offers</span>
            </button>
            <button type="button" onclick="switchHubSection('reviews', this)" class="hub-tab px-4 py-2 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-star"></i>
                <span>Customer Reviews</span>
            </button>
            <button type="button" onclick="switchHubSection('settings', this)" class="hub-tab px-4 py-2 rounded-full text-xs font-bold bg-white text-stone-700 hover:text-[#611B10] border border-[#D4A853]/30 transition flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i>
                <span>Site Texts &amp; Contact</span>
            </button>
        </div>

        <!-- Section 1: Products Catalog Table -->
        <div id="hub-section-products" class="hub-pane space-y-4">
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-2xs overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-serif font-bold text-base text-[#611B10]">Active Products &amp; Purchase Redirection</h3>
                        <p class="text-[11px] text-stone-500">Manage products, pricing, and destination storefront links.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs"></i>
                            <input type="text" oninput="filterSectionItems('products', this.value)" placeholder="Search products..." class="w-full bg-stone-50 border border-stone-200 rounded-full pl-8 pr-3 py-1.5 text-xs text-stone-800 focus:outline-none focus:border-[#611B10] focus:bg-white transition">
                        </div>
                        <button onclick="openAddProductModal()" class="px-4 py-2 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs shadow-xs transition active:scale-95 flex items-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span class="hidden xs:inline">Add Product</span>
                        </button>
                    </div>
                </div>
                <!-- Desktop Table View (Hidden on mobile) -->
                <div class="hidden sm:block overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full text-left text-xs min-w-[650px] sm:min-w-full">
                        <thead>
                            <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Product Name</th>
                                <th class="py-3 px-4">Price</th>
                                <th class="py-3 px-4">Marketplace URL</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100" id="products-table-body">
                            {$productRowsHtml}
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Cards View (Shown only on mobile) -->
                <div class="sm:hidden grid grid-cols-1 gap-3" id="products-cards-body">
                    {$productCardsHtml}
                </div>
            </div>
        </div>

        <!-- Section 1b: Flagship Bottle Showcase -->
        <div id="hub-section-flagship" class="hub-pane hidden space-y-4">
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-serif font-bold text-base text-[#611B10]">Interactive Flagship Bottle Showcase</h3>
                        <p class="text-xs text-stone-500">Manage the interactive products featured in the homepage scroll-scrub showcase.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-60">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs"></i>
                            <input type="text" oninput="filterSectionItems('flagship', this.value)" placeholder="Search flagship slides..." class="w-full bg-stone-50 border border-stone-200 rounded-full pl-8 pr-3 py-1.5 text-xs text-stone-800 focus:outline-none focus:border-[#611B10] focus:bg-white transition">
                        </div>
                        <button type="button" onclick="openAddFlagshipModal()" class="px-4 py-2 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>Add Slide</span>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5" id="flagship-cards-grid">
                    {$flagshipHtml}
                </div>
            </div>
        </div>

        <!-- Section 2: Combo Offers -->
        <div id="hub-section-combos" class="hub-pane hidden space-y-4">
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-serif font-bold text-base text-[#611B10]">Special Combo Packs</h3>
                        <p class="text-xs text-stone-500">Manage the 3D coverflow combo bundles featured on the homepage.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-60">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs"></i>
                            <input type="text" oninput="filterSectionItems('combos', this.value)" placeholder="Search combo deals..." class="w-full bg-stone-50 border border-stone-200 rounded-full pl-8 pr-3 py-1.5 text-xs text-stone-800 focus:outline-none focus:border-[#611B10] focus:bg-white transition">
                        </div>
                        <button type="button" onclick="openAddComboModal()" class="px-4 py-2 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>Add Combo</span>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5" id="combos-cards-grid">
                    {$combosHtml}
                </div>
            </div>
        </div>

        <!-- Section 3: Customer Reviews -->
        <div id="hub-section-reviews" class="hub-pane hidden space-y-4">
            <!-- Published Reviews Table -->
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-4 sm:p-6 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h4 class="font-serif font-bold text-base text-[#611B10]">Verified Customer Reviews</h4>
                        <p class="text-xs text-stone-500">Manage testimonial quotes, customer locations, avatars, and ratings.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs"></i>
                            <input type="text" oninput="filterSectionItems('reviews', this.value)" placeholder="Search customer reviews..." class="w-full bg-stone-50 border border-stone-200 rounded-full pl-8 pr-3 py-1.5 text-xs text-stone-800 focus:outline-none focus:border-[#611B10] focus:bg-white transition">
                        </div>
                        <button type="button" onclick="openAddTestimonialModal()" class="px-4 py-2 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>Add Review</span>
                        </button>
                    </div>
                </div>

                <!-- Desktop Table View (Hidden on mobile) -->
                <div class="hidden sm:block overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full text-left text-xs min-w-[600px] sm:min-w-full">
                        <thead>
                            <tr class="border-b border-[#D4A853]/20 text-[#611B10]/60 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Author</th>
                                <th class="py-3 px-4">Rating</th>
                                <th class="py-3 px-4">Quote</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100" id="reviews-table-body">
                            {$testimonialsHtml}
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Cards View (Shown only on mobile) -->
                <div class="sm:hidden grid grid-cols-1 gap-3" id="reviews-cards-body">
                    {$testimonialsCardsHtml}
                </div>
            </div>
        </div>

        <!-- Section 4: Site Texts & Contact -->
        <div id="hub-section-settings" class="hub-pane hidden space-y-4">
            <form id="unified-settings-form" onsubmit="event.preventDefault(); saveUnifiedSettings();" class="bg-white rounded-2xl sm:rounded-3xl border border-[#D4A853]/30 p-5 sm:p-7 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                    <div>
                        <h4 class="font-serif font-bold text-base text-[#611B10]">Site Copy &amp; Contact Details</h4>
                        <p class="text-xs text-stone-500 mt-0.5">Changes update live on all public storefront pages.</p>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-full bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold text-xs shadow-md transition active:scale-95 flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {$settingsFieldsHtml}
                </div>
            </form>
        </div>
    </main>

        <!-- Reusable Dynamic Datalists for Tags and Categories -->
    <datalist id="product-tags-list">
        {$tagsDatalistOptions}
    </datalist>

    <datalist id="product-categories-list">
        {$catsDatalistOptions}
    </datalist>

    <!-- ADD PRODUCT MODAL (With Live Image Inspector & Custom Upload) -->
    <div id="add-product-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeAddProductModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h4 class="font-serif font-bold text-base text-[#611B10] flex items-center gap-2">
                    <i class="fa-solid fa-plus text-[#D4A853]"></i>
                    <span>Add New Product</span>
                </h4>
                <button onclick="closeAddProductModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form onsubmit="handleCreateProduct(event)" class="space-y-3.5 pt-3 text-xs">
                
                <!-- Destination Purchase URL -->
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Destination Purchase URL (Amazon, Meesho, etc.)</label>
                    <input type="url" id="add-p-url" required placeholder="https://www.amazon.in/dp/..." class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                </div>

                <!-- Live Image Preview & Choice Box -->
                <div class="p-3.5 rounded-2xl bg-stone-50 border border-stone-200 flex items-center gap-4">
                    <div class="relative w-16 h-16 rounded-xl bg-white border border-stone-300 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img id="add-p-preview-img" src="/images/product_bottle.webp" alt="Preview" class="w-full h-full object-contain">
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block">Product Photo Selection</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload New Photo</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('add', this, 1)">
                            </label>
                            <span id="add-p-source-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Ready</span>
                        </div>
                        <input type="hidden" id="add-p-image" value="images/product_bottle.webp">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Product Title</label>
                    <input type="text" id="add-p-name" required placeholder="e.g. Millacoco 500ml Glass Jar" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Selling Price (₹)</label>
                        <input type="number" step="0.01" id="add-p-price" required placeholder="290.00" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Before Price / MRP (₹)</label>
                        <input type="number" step="0.01" id="add-p-discounted-from" placeholder="340.00 (Optional)" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Category (Type or Search)</label>
                        <input type="text" id="add-p-cat" list="product-categories-list" required placeholder="Choose or type custom category..." class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Badge Tag (Type or Search)</label>
                        <input type="text" id="add-p-tag" list="product-tags-list" placeholder="Choose or type custom tag..." class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Description</label>
                    <textarea id="add-p-desc" rows="2" placeholder="Brief product benefits and features..." class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeAddProductModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition shadow-sm active:scale-95">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL (Full Product Info, Live Image Inspector & Custom Upload) -->
    <div id="edit-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeEditModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h4 class="font-serif font-bold text-base text-[#611B10]" id="modal-product-name">Edit Product Details</h4>
                <button onclick="closeEditModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleProductUpdate(event)" class="space-y-3 pt-3 text-xs">
                <input type="hidden" id="edit-id">

                <!-- Destination Purchase URL -->
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Destination Purchase URL</label>
                    <input type="url" id="edit-url" required class="w-full px-3.5 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>

                <!-- Live Image Preview & Choice Box -->
                <div class="p-3.5 rounded-2xl bg-stone-50 border border-stone-200 flex items-center gap-4">
                    <div class="relative w-16 h-16 rounded-xl bg-white border border-stone-300 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img id="edit-p-preview-img" src="/images/product_bottle.webp" alt="Preview" class="w-full h-full object-contain">
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block">Current Photo</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload New Photo</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('edit', this, 1)">
                            </label>
                            <span id="edit-p-source-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Current photo</span>
                        </div>
                        <input type="hidden" id="edit-image" required>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Product Title</label>
                    <input type="text" id="edit-name" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Selling Price (₹)</label>
                        <input type="number" step="0.01" id="edit-price" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Before Price / MRP (₹)</label>
                        <input type="number" step="0.01" id="edit-discounted-from" placeholder="e.g. 340.00" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Category (Type or Search)</label>
                        <input type="text" id="edit-cat" list="product-categories-list" required placeholder="Choose or type custom category..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Badge Tag (Type or Search)</label>
                        <input type="text" id="edit-tag" list="product-tags-list" placeholder="Choose or type custom tag..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Description</label>
                    <textarea id="edit-desc" rows="2" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD FLAGSHIP SLIDE MODAL -->
    <div id="add-flagship-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeAddFlagshipModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Add New Flagship Product Slide</h4>
                    <p class="text-[11px] text-stone-500">Add an interactive bottle slide to the homepage showcase.</p>
                </div>
                <button type="button" onclick="closeAddFlagshipModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleCreateFlagshipSlide(event)" class="space-y-3.5 pt-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Navigation Tab Label</label>
                        <input type="text" id="add-flagship-tab" required placeholder="e.g. 500ml Jar" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Handwriting Script Accent</label>
                        <input type="text" id="add-flagship-script" placeholder="e.g. The Signature Heritage" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Product Title</label>
                    <input type="text" id="add-flagship-title" required placeholder="e.g. 100% Virgin Kerala Coconut Oil" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none font-bold">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Subtitle / Tagline</label>
                    <input type="text" id="add-flagship-subtitle" placeholder="e.g. Cold-pressed from Kuttiyadi copra" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Detailed Description</label>
                    <textarea id="add-flagship-desc" rows="3" required placeholder="Describe the aroma, extraction process, and uses..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>

                <!-- Flagship Cutout Image Upload -->
                <div class="p-3.5 rounded-2xl bg-stone-50 border border-stone-200 flex items-center gap-4">
                    <div class="relative w-16 h-16 rounded-xl bg-white border border-stone-300 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img id="add-flagship-preview-img" src="/images/flagship_bottle_cutout.webp" alt="Preview" class="w-full h-full object-contain">
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block">Cutout / Slide Graphic</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload Cutout (Cropper)</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('add-flagship', this, 0.75)">
                            </label>
                            <span id="add-flagship-source-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Ready</span>
                        </div>
                        <input type="hidden" id="add-flagship-image" value="images/flagship_bottle_cutout.webp">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Price (₹)</label>
                        <input type="number" step="0.01" id="add-flagship-price" required placeholder="320.00" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Direct Order URL</label>
                        <input type="text" id="add-flagship-url" placeholder="https://wa.me/918089757815?text=..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-stone-100">
                    <button type="button" onclick="closeAddFlagshipModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i>
                        <span>Create Slide</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT FLAGSHIP SLIDE MODAL -->
    <div id="flagship-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeFlagshipModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Edit Flagship Product Slide</h4>
                    <p class="text-[11px] text-stone-500">Updates live on homepage interactive bottle showcase</p>
                </div>
                <button onclick="closeFlagshipModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleSaveFlagship(event)" class="space-y-3 pt-3 text-xs">
                <input type="hidden" id="edit-flagship-id">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tab Label (e.g. 01 Bottle)</label>
                        <input type="text" id="edit-flagship-tab" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Script Handwriting Accent</label>
                        <input type="text" id="edit-flagship-script" placeholder="e.g. The Signature Flagship" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Product Title</label>
                    <input type="text" id="edit-flagship-title" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none font-bold">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Subtitle / Tagline</label>
                    <input type="text" id="edit-flagship-subtitle" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Detailed Description</label>
                    <textarea id="edit-flagship-desc" rows="3" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>

                <!-- Live Flagship Image Preview & Choice Box -->
                <div class="p-3.5 rounded-2xl bg-stone-50 border border-stone-200 flex items-center gap-4">
                    <div class="relative w-16 h-16 rounded-xl bg-white border border-stone-300 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img id="edit-flagship-preview-img" src="/images/flagship_bottle_cutout.webp" alt="Preview" class="w-full h-full object-contain">
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block">Cutout / Slide Graphic</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload Cutout Photo</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('flagship', this, 0.75)">
                            </label>
                            <span id="edit-flagship-source-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Ready</span>
                        </div>
                        <input type="hidden" id="edit-flagship-image" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Price (₹)</label>
                        <input type="number" step="0.01" id="edit-flagship-price" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Asset Path</label>
                        <input type="text" id="edit-flagship-image-display" readonly placeholder="images/flagship_bottle_cutout.webp" class="w-full px-3 py-2 rounded-xl border border-stone-200 bg-stone-50 text-stone-500 font-mono text-[11px] outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Direct Buy / WhatsApp Order URL</label>
                    <input type="text" id="edit-flagship-url" placeholder="https://wa.me/918089757815?text=..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-stone-100">
                    <button type="button" onclick="closeFlagshipModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" id="btn-save-flagship" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>Save Flagship Slide</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD COMBO MODAL -->
    <div id="add-combo-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeAddComboModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Add New Combo Offer</h4>
                    <p class="text-[11px] text-stone-500">Create a 3D coverflow card bundle for the homepage.</p>
                </div>
                <button type="button" onclick="closeAddComboModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleCreateCombo(event)" class="space-y-3 pt-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Combo Bundle Name</label>
                        <input type="text" id="add-combo-name" required placeholder="e.g. Master Chef Culinary Duo" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Badge Tag</label>
                        <input type="text" id="add-combo-badge" required placeholder="e.g. SPECIAL VALUE" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Short Subtitle / Items list</label>
                        <input type="text" id="add-combo-subtitle" placeholder="e.g. 1L Oil + 500ml Virgin" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Button Action Text</label>
                        <input type="text" id="add-combo-btn" required value="ORDER PACK" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <!-- Combo Photo Upload with Cropper -->
                <div class="p-3.5 rounded-2xl bg-stone-50 border border-stone-200 flex items-center gap-4">
                    <div class="relative w-16 h-16 rounded-xl bg-white border border-stone-300 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img id="add-combo-preview-img" src="/images/product_pouch.png" alt="Preview" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block">Combo Bundle Visual</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload Combo Photo (1:1)</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('add-combo', this, 1)">
                            </label>
                            <span id="add-combo-source-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Ready</span>
                        </div>
                        <input type="hidden" id="add-combo-image" value="images/product_pouch.png">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Discounted Combo Price (₹)</label>
                        <input type="number" step="0.01" id="add-combo-price" required placeholder="590.00" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Original Price (₹)</label>
                        <input type="number" step="0.01" id="add-combo-old-price" placeholder="680.00" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Direct Order Destination URL</label>
                    <input type="url" id="add-combo-url" placeholder="https://wa.me/918089757815?text=..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Description</label>
                    <textarea id="add-combo-desc" rows="2" required placeholder="Describe savings and ingredients included..." class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeAddComboModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition">Create Combo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT COMBO MODAL -->
    <div id="combo-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeComboModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-[#D4A853]/40">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h4 class="font-serif font-bold text-base text-[#611B10]">Edit Combo Offer</h4>
                <button onclick="closeComboModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleSaveCombo(event)" class="space-y-3 pt-3 text-xs">
                <input type="hidden" id="edit-combo-id">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Price (₹)</label>
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

    
    <!-- ADD TESTIMONIAL MODAL -->
    <div id="add-testimonial-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeAddTestimonialModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-[#D4A853]/40 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Add Verified Customer Review</h4>
                    <p class="text-[11px] text-stone-500">Publish genuine feedback to the public storefront testimonials reel.</p>
                </div>
                <button type="button" onclick="closeAddTestimonialModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleCreateTestimonial(event)" class="space-y-3 pt-3 text-xs">
                <div class="flex items-center gap-3 p-3 bg-stone-50 rounded-2xl border border-stone-200">
                    <div class="w-12 h-12 rounded-full bg-white border border-stone-300 flex items-center justify-center overflow-hidden shrink-0 shadow-2xs">
                        <img id="add-t-avatar-preview" src="/images/favicon.png" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Upload User Avatar</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('add-t-avatar', this, 1)">
                            </label>
                            <span id="add-t-avatar-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-200 text-stone-600 font-medium">Optional</span>
                        </div>
                        <input type="hidden" id="t-avatar-url" value="">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Customer Name</label>
                        <input type="text" id="t-author" required placeholder="e.g. Maya Suresh" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">City / Location</label>
                        <input type="text" id="t-location" required placeholder="e.g. Kozhikode, Kerala" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Category / Tag</label>
                        <input type="text" id="t-tag" placeholder="e.g. Traditional Cooking" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Star Rating (1 - 5)</label>
                        <select id="t-rating" class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]">
                            <option value="5">⭐⭐⭐⭐⭐ (5 Stars)</option>
                            <option value="4">⭐⭐⭐⭐ (4 Stars)</option>
                            <option value="3">⭐⭐⭐ (3 Stars)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Review Quote</label>
                    <textarea id="t-quote" required rows="3" placeholder="Customer experience feedback..." class="w-full px-3 py-2 rounded-xl border border-stone-200 outline-none focus:border-[#611B10]"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeAddTestimonialModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition">Publish Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT TESTIMONIAL MODAL -->
    <div id="edit-testimonial-modal" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeEditTestimonialModal()" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-[#D4A853]/40">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h4 class="font-serif font-bold text-base text-[#611B10]">Edit Customer Review</h4>
                <button onclick="closeEditTestimonialModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleUpdateTestimonial(event)" class="space-y-3 pt-3 text-xs">
                <input type="hidden" id="edit-t-id">
                <div class="flex items-center gap-3 p-3 bg-stone-50 rounded-2xl border border-stone-200">
                    <div class="w-12 h-12 rounded-full bg-white border border-stone-300 flex items-center justify-center overflow-hidden shrink-0 shadow-2xs">
                        <img id="edit-t-avatar-preview" src="/images/favicon.png" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-stone-300 hover:border-[#611B10] text-stone-700 font-semibold text-[11px] transition shadow-2xs">
                                <i class="fa-solid fa-cloud-arrow-up text-[#611B10]"></i>
                                <span>Change User Avatar</span>
                                <input type="file" accept="image/*" class="hidden" onchange="handleInitiateCrop('edit-t-avatar', this, 1)">
                            </label>
                            <span id="edit-t-avatar-badge" class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 font-medium">Avatar</span>
                        </div>
                        <input type="hidden" id="edit-t-avatar-url" value="">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Customer Name</label>
                    <input type="text" id="edit-t-author" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Location / City</label>
                        <input type="text" id="edit-t-location" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tag / Category</label>
                        <input type="text" id="edit-t-tag" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Star Rating (1 - 5)</label>
                    <select id="edit-t-rating" class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                        <option value="5">⭐⭐⭐⭐⭐ (5 Stars)</option>
                        <option value="4">⭐⭐⭐⭐ (4 Stars)</option>
                        <option value="3">⭐⭐⭐ (3 Stars)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Review Quote</label>
                    <textarea id="edit-t-quote" rows="3" required class="w-full px-3 py-2 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditTestimonialModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition">Update Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CROPPER MODAL -->
    <div id="cropper-modal" class="fixed inset-0 z-[60] invisible opacity-0 pointer-events-none transition-all duration-300 flex items-center justify-center p-4">
        <div onclick="closeCropperModal()" class="absolute inset-0 bg-black/75 backdrop-blur-xs"></div>
        <div class="relative bg-white rounded-3xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-[#D4A853]/40 flex flex-col max-h-[92vh]">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-crop-simple text-[#611B10]"></i>
                    <h4 class="font-serif font-bold text-base text-[#611B10]">Crop & Optimize Image</h4>
                </div>
                <button type="button" onclick="closeCropperModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="my-3 flex-1 overflow-hidden bg-stone-900 rounded-2xl flex items-center justify-center min-h-[300px] max-h-[55vh]">
                <img id="cropper-target-img" src="" alt="To Crop" class="max-w-full max-h-full block">
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-stone-100 text-xs">
                <div class="flex items-center gap-2 text-stone-500">
                    <span id="cropper-aspect-label" class="px-2.5 py-1 rounded-lg bg-stone-100 font-semibold text-stone-700">1:1 Aspect Ratio</span>
                    <button type="button" id="btn-toggle-remove-bg" onclick="toggleBackgroundRemoval()" class="hidden px-2.5 py-1 rounded-lg border border-stone-300 text-stone-600 hover:text-[#611B10] hover:border-[#611B10] transition items-center gap-1.5" title="Removes solid or white/light background">
                        <i class="fa-solid fa-wand-magic-sparkles text-[#D4A853]"></i>
                        <span id="bg-remove-label">Erase Background: OFF</span>
                    </button>
                    <span class="text-[11px] text-stone-400">WebP 85%</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeCropperModal()" class="px-4 py-2 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50 font-medium">Cancel</button>
                    <button type="button" id="cropper-confirm-btn" onclick="applyCropAndUpload()" class="px-5 py-2 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition shadow-sm active:scale-95 flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>Crop & Save</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
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
                    <input type="password" id="pwd-current" required placeholder="Enter current password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">New Password (min 6 chars)</label>
                    <input type="password" id="pwd-new" required minlength="6" placeholder="Enter new strong password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Confirm New Password</label>
                    <input type="password" id="pwd-confirm" required minlength="6" placeholder="Repeat new password" class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 focus:border-[#611B10] outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closePasswordModal()" class="px-4 py-2.5 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50 font-semibold">Cancel</button>
                    <button type="submit" id="pwd-submit-btn" class="px-5 py-2.5 rounded-xl bg-[#611B10] hover:bg-[#782315] text-[#D4A853] hover:text-white font-bold transition shadow-sm active:scale-95 flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>Save Password</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Feedback Notification -->
    <div id="hub-toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 pointer-events-none transition-all duration-300 bg-[#611B10] text-[#D4A853] px-5 py-3.5 rounded-2xl shadow-2xl border border-[#D4A853]/40 flex items-center gap-3 text-xs font-bold">
        <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
        <span id="hub-toast-msg">Settings saved successfully!</span>
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

        function switchHubSection(tabKey, btn) {
            document.querySelectorAll('.hub-pane').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('hub-section-' + tabKey);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('.hub-tab').forEach(b => {
                b.classList.remove('active', 'bg-[#611B10]', 'text-[#D4A853]');
                b.classList.add('bg-white', 'text-stone-700');
            });
            btn.classList.add('active', 'bg-[#611B10]', 'text-[#D4A853]');
            btn.classList.remove('bg-white', 'text-stone-700');
        }

        function showHubToast(msg, isError = false) {
            const toast = document.getElementById('hub-toast');
            const toastMsg = document.getElementById('hub-toast-msg');
            toastMsg.innerText = msg;
            toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
            }, 3000);
        }

        // Add Product Modal
        function openAddProductModal() {
            const m = document.getElementById('add-product-modal');
            m.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeAddProductModal() {
            const m = document.getElementById('add-product-modal');
            m.classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleCreateProduct(e) {
            e.preventDefault();
            const name = document.getElementById('add-p-name').value;
            const price = parseFloat(document.getElementById('add-p-price').value);
            const discounted_from = parseFloat(document.getElementById('add-p-discounted-from').value) || null;
            const tag = document.getElementById('add-p-tag').value || 'Bestseller';
            const category = document.getElementById('add-p-cat').value;
            const image = document.getElementById('add-p-image').value || 'images/product_bottle.webp';
            const external_url = document.getElementById('add-p-url').value;
            const description = document.getElementById('add-p-desc').value;

            try {
                const res = await fetch('/mc-desk/api/admin/products', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, price, discounted_from, tag, category, image, external_url, description })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding product');
                }
            } catch (err) {
                alert('Network connection error');
            }
        }

        
        // Auto Fetch / Inspect Image & Title from URL
        async function autoInspectUrl(mode) {
            const urlInput = document.getElementById(mode + '-p-url') || document.getElementById(mode + '-url');
            const statusEl = document.getElementById(mode + '-fetch-status');
            const previewImg = document.getElementById(mode + '-p-preview-img');
            const imageInput = document.getElementById(mode + '-p-image') || document.getElementById(mode + '-image');
            const sourceBadge = document.getElementById(mode + '-p-source-badge');
            const nameInput = document.getElementById(mode + '-p-name') || document.getElementById(mode + '-name');

            const url = urlInput.value.trim();
            if (!url || !url.startsWith('http')) return;

            statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-stone-500"></i> Fetching product image...';

            try {
                const res = await fetch('/mc-desk/api/admin/fetch-metadata', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                });

                const json = await res.json();
                if (res.ok && json.status === 'success' && json.data) {
                    if (json.data.image) {
                        previewImg.src = json.data.image;
                        imageInput.value = json.data.image;
                        sourceBadge.innerText = 'Source Image (from link)';
                        sourceBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold';
                        statusEl.innerHTML = '<span class="text-emerald-700 font-bold"><i class="fa-solid fa-circle-check"></i> Image loaded</span>';
                    } else if (json.data.protected) {
                        statusEl.innerHTML = '<span class="text-amber-700 font-medium"><i class="fa-solid fa-shield-halved"></i> Site protected &bull; Name inferred &bull; Upload photo below</span>';
                    } else {
                        statusEl.innerHTML = '<span class="text-stone-500">No open image tag found on page</span>';
                    }

                    if (json.data.title && nameInput && !nameInput.value) {
                        nameInput.value = json.data.title.split('|')[0].split(':')[0].trim();
                    }
                } else {
                    statusEl.innerHTML = '<span class="text-stone-400">Could not read image from URL</span>';
                }
            } catch (e) {
                statusEl.innerHTML = '<span class="text-stone-400">Link reachable or protected</span>';
            }
        }

        // Cropper JS Global State & Handlers
        let activeCropper = null;
        let cropperTargetMode = null; // 'add', 'edit', 'flagship', 'add-t-avatar', 'edit-t-avatar'
        let currentFileInput = null;
        let removeBackgroundActive = false;

        function toggleBackgroundRemoval() {
            removeBackgroundActive = !removeBackgroundActive;
            const label = document.getElementById('bg-remove-label');
            const btn = document.getElementById('btn-toggle-remove-bg');
            if (removeBackgroundActive) {
                label.innerText = 'Erase Background: ON';
                btn.className = 'px-2.5 py-1 rounded-lg border border-emerald-500 bg-emerald-50 text-emerald-800 font-bold transition flex items-center gap-1.5';
            } else {
                label.innerText = 'Erase Background: OFF';
                btn.className = 'px-2.5 py-1 rounded-lg border border-stone-300 text-stone-600 hover:text-[#611B10] hover:border-[#611B10] transition flex items-center gap-1.5';
            }
        }

        // High-speed canvas background eraser
        function eraseCanvasBackground(canvas) {
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            const imgData = ctx.getImageData(0, 0, width, height);
            const data = imgData.data;
            const len = data.length;

            // Sample corner pixels fast
            const c1 = 0;
            const c2 = (width - 1) * 4;
            const c3 = (height - 1) * width * 4;
            const c4 = len - 4;

            const bgR = (data[c1] + data[c2] + data[c3] + data[c4]) >> 2;
            const bgG = (data[c1 + 1] + data[c2 + 1] + data[c3 + 1] + data[c4 + 1]) >> 2;
            const bgB = (data[c1 + 2] + data[c2 + 2] + data[c3 + 2] + data[c4 + 2]) >> 2;

            const isLight = (bgR > 215 && bgG > 215 && bgB > 215);
            const maxDiff = isLight ? 35 : 28;
            const maxDiffSq = maxDiff * maxDiff;
            const softRangeSq = (maxDiff + 15) * (maxDiff + 15);

            // Fast typed 32-bit pixel array loop (order of magnitude faster than float sqrt)
            for (let i = 0; i < len; i += 4) {
                const dr = data[i] - bgR;
                const dg = data[i + 1] - bgG;
                const db = data[i + 2] - bgB;
                const distSq = dr * dr + dg * dg + db * db;

                if (distSq < maxDiffSq) {
                    data[i + 3] = 0;
                } else if (distSq < softRangeSq) {
                    const factor = (distSq - maxDiffSq) / (softRangeSq - maxDiffSq);
                    data[i + 3] = (data[i + 3] * factor) | 0;
                }
            }

            ctx.putImageData(imgData, 0, 0);
        }

        function handleInitiateCrop(mode, inputEl, aspectRatio = 1) {
            if (!inputEl.files || !inputEl.files[0]) return;
            const file = inputEl.files[0];
            cropperTargetMode = mode;
            currentFileInput = inputEl;

            const reader = new FileReader();
            reader.onload = function(e) {
                const cropperModal = document.getElementById('cropper-modal');
                const cropperTargetImg = document.getElementById('cropper-target-img');
                const aspectLabel = document.getElementById('cropper-aspect-label');

                if (aspectRatio === 1) {
                    aspectLabel.innerText = '1:1 Square (Product Photo)';
                } else {
                    aspectLabel.innerText = aspectRatio + ':1 Custom Cutout Ratio';
                }

                cropperTargetImg.src = e.target.result;
                cropperModal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');

                if (activeCropper) {
                    activeCropper.destroy();
                    activeCropper = null;
                }

                activeCropper = new Cropper(cropperTargetImg, {
                    aspectRatio: aspectRatio,
                    viewMode: 2,
                    autoCropArea: 0.95,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
            };
            reader.readAsDataURL(file);
        }

        function closeCropperModal() {
            const cropperModal = document.getElementById('cropper-modal');
            cropperModal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
            if (activeCropper) {
                activeCropper.destroy();
                activeCropper = null;
            }
            if (currentFileInput) {
                currentFileInput.value = '';
                currentFileInput = null;
            }
        }

        async function applyCropAndUpload() {
            if (!activeCropper) return;
            const confirmBtn = document.getElementById('cropper-confirm-btn');
            const originalBtnHtml = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing & Compressing...';
            confirmBtn.disabled = true;

            try {
                // Generate high quality canvas (max 900px for instant cropping & compression)
                const canvas = activeCropper.getCroppedCanvas({
                    maxWidth: 900,
                    maxHeight: 900,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'medium'
                });

                if (removeBackgroundActive) {
                    eraseCanvasBackground(canvas);
                }

                // Client-side WebP compression with quality 0.85
                canvas.toBlob(async function(blob) {
                    if (!blob) {
                        alert('Could not process cropped canvas');
                        confirmBtn.innerHTML = originalBtnHtml;
                        confirmBtn.disabled = false;
                        return;
                    }

                    let previewImg = null;
                    let imageInput = null;
                    let sourceBadge = null;
                    let pathDisplay = null;

                    if (mode === 'add-t-avatar') {
                        previewImg = document.getElementById('add-t-avatar-preview');
                        imageInput = document.getElementById('t-avatar-url');
                        sourceBadge = document.getElementById('add-t-avatar-badge');
                    } else if (mode === 'edit-t-avatar') {
                        previewImg = document.getElementById('edit-t-avatar-preview');
                        imageInput = document.getElementById('edit-t-avatar-url');
                        sourceBadge = document.getElementById('edit-t-avatar-badge');
                    } else if (mode === 'add-flagship') {
                        previewImg = document.getElementById('add-flagship-preview-img');
                        imageInput = document.getElementById('add-flagship-image');
                        sourceBadge = document.getElementById('add-flagship-source-badge');
                    } else if (mode === 'add-combo') {
                        previewImg = document.getElementById('add-combo-preview-img');
                        imageInput = document.getElementById('add-combo-image');
                        sourceBadge = document.getElementById('add-combo-source-badge');
                    } else {
                        previewImg = document.getElementById(mode + '-p-preview-img') || document.getElementById('edit-flagship-preview-img');
                        imageInput = document.getElementById(mode + '-p-image') || document.getElementById(mode + '-image') || document.getElementById('edit-flagship-image');
                        sourceBadge = document.getElementById(mode + '-p-source-badge') || document.getElementById('edit-flagship-source-badge');
                        pathDisplay = document.getElementById('edit-flagship-image-display');
                    }

                    if (sourceBadge) {
                        sourceBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading WebP...';
                    }

                    const formData = new FormData();
                    formData.append('photo', blob, 'crop_' + Date.now() + '.webp');

                    try {
                        const res = await fetch('/mc-desk/api/admin/upload-photo', {
                            method: 'POST',
                            body: formData
                        });
                        const json = await res.json();
                        if (res.ok && json.status === 'success') {
                            if (previewImg) previewImg.src = '/' + json.image_path;
                            if (imageInput) imageInput.value = json.image_path;
                            if (pathDisplay) pathDisplay.value = json.image_path;
                            if (sourceBadge) {
                                sourceBadge.innerText = 'Optimized WebP Photo';
                                sourceBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold';
                            }
                            closeCropperModal();
                        } else {
                            alert(json.message || 'Failed to upload photo');
                            if (sourceBadge) sourceBadge.innerText = 'Upload failed';
                        }
                    } catch (err) {
                        alert('Network error during upload');
                        if (sourceBadge) sourceBadge.innerText = 'Upload failed';
                    } finally {
                        confirmBtn.innerHTML = originalBtnHtml;
                        confirmBtn.disabled = false;
                    }
                }, 'image/webp', 0.85);

            } catch (err) {
                alert('Crop processing error: ' + err.message);
                confirmBtn.innerHTML = originalBtnHtml;
                confirmBtn.disabled = false;
            }
        }

        // Handle Custom Local Photo Upload (Fallback direct)
        async function handleUploadPhoto(mode, inputEl) {
            handleInitiateCrop(mode, inputEl, 1);
        }

        // Edit Product (Full Product Info & Image)
        function openEditModal(id, name, price, discountedFrom, tag, category, image, url, desc) {
            const previewImg = document.getElementById('edit-p-preview-img');
            const sourceBadge = document.getElementById('edit-p-source-badge');
            if (previewImg) previewImg.src = image.startsWith('http') ? image : '/' + image;
            if (sourceBadge) {
                sourceBadge.innerText = 'Current product image';
                sourceBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-stone-200 text-stone-600 font-medium';
            }

            document.getElementById('edit-id').value = id;
            document.getElementById('modal-product-name').innerText = 'Edit ' + name;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-price').value = price;
            document.getElementById('edit-discounted-from').value = (discountedFrom && discountedFrom > 0) ? discountedFrom : '';
            document.getElementById('edit-tag').value = tag || 'Bestseller';
            document.getElementById('edit-cat').value = category || 'daily';
            document.getElementById('edit-image').value = image || 'images/product_bottle.webp';
            document.getElementById('edit-url').value = url || '';
            document.getElementById('edit-desc').value = desc || '';

            const modal = document.getElementById('edit-modal');
            modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleProductUpdate(e) {
            e.preventDefault();
            const id = document.getElementById('edit-id').value;
            const name = document.getElementById('edit-name').value;
            const price = parseFloat(document.getElementById('edit-price').value);
            const discounted_from = parseFloat(document.getElementById('edit-discounted-from').value) || null;
            const tag = document.getElementById('edit-tag').value;
            const category = document.getElementById('edit-cat').value;
            const image = document.getElementById('edit-image').value;
            const external_url = document.getElementById('edit-url').value;
            const description = document.getElementById('edit-desc').value;

            try {
                const res = await fetch('/mc-desk/api/admin/products/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, price, discounted_from, tag, category, image, external_url, description })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating product');
                }
            } catch (err) {
                alert('Network error');
            }
        }

        // Combos
        // Flagship Showcase Functions
        function openAddFlagshipModal() {
            document.getElementById('add-flagship-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeAddFlagshipModal() {
            document.getElementById('add-flagship-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleCreateFlagshipSlide(e) {
            e.preventDefault();
            const payload = {
                tab_label: document.getElementById('add-flagship-tab').value,
                script_accent: document.getElementById('add-flagship-script').value,
                title: document.getElementById('add-flagship-title').value,
                subtitle: document.getElementById('add-flagship-subtitle').value,
                description: document.getElementById('add-flagship-desc').value,
                image_cutout: document.getElementById('add-flagship-image').value,
                price: parseFloat(document.getElementById('add-flagship-price').value),
                external_url: document.getElementById('add-flagship-url').value
            };

            try {
                const res = await fetch('/mc-desk/api/admin/flagship', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error creating flagship slide');
                }
            } catch (err) {
                alert('Network error creating flagship slide');
            }
        }

        async function deleteFlagshipSlide(id) {
            if (!confirm('Are you sure you want to remove this flagship slide?')) return;
            try {
                const res = await fetch('/mc-desk/api/admin/flagship/' + id, { method: 'DELETE' });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting slide');
                }
            } catch (err) {
                alert('Network error deleting slide');
            }
        }

        function openEditFlagshipModal(id, title, subtitle, scriptAccent, tabLabel, desc, price, imageCutout, externalUrl) {
            document.getElementById('edit-flagship-id').value = id;
            document.getElementById('edit-flagship-title').value = title || '';
            document.getElementById('edit-flagship-subtitle').value = subtitle || '';
            document.getElementById('edit-flagship-script').value = scriptAccent || '';
            document.getElementById('edit-flagship-tab').value = tabLabel || '';
            document.getElementById('edit-flagship-desc').value = desc || '';
            document.getElementById('edit-flagship-price').value = price || 0;
            document.getElementById('edit-flagship-image').value = imageCutout || '';
            const pathDisplay = document.getElementById('edit-flagship-image-display');
            if (pathDisplay) pathDisplay.value = imageCutout || '';
            const previewImg = document.getElementById('edit-flagship-preview-img');
            if (previewImg && imageCutout) previewImg.src = imageCutout.startsWith('http') ? imageCutout : '/' + imageCutout;
            document.getElementById('edit-flagship-url').value = externalUrl || '';
            document.getElementById('flagship-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }

        function closeFlagshipModal() {
            document.getElementById('flagship-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleSaveFlagship(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save-flagship');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
            btn.disabled = true;

            const id = document.getElementById('edit-flagship-id').value;
            const payload = {
                title: document.getElementById('edit-flagship-title').value,
                subtitle: document.getElementById('edit-flagship-subtitle').value,
                script_accent: document.getElementById('edit-flagship-script').value,
                tab_label: document.getElementById('edit-flagship-tab').value,
                description: document.getElementById('edit-flagship-desc').value,
                price: parseFloat(document.getElementById('edit-flagship-price').value),
                image_cutout: document.getElementById('edit-flagship-image').value,
                external_url: document.getElementById('edit-flagship-url').value
            };

            try {
                const res = await fetch('/mc-desk/api/admin/flagship/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    showHubToast('Flagship slide updated successfully!');
                    closeFlagshipModal();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    alert(data.message || 'Error updating flagship slide');
                }
            } catch (err) {
                alert('Network error updating flagship slide');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // Combos
        function openAddComboModal() {
            document.getElementById('add-combo-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeAddComboModal() {
            document.getElementById('add-combo-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleCreateCombo(e) {
            e.preventDefault();
            const payload = {
                name: document.getElementById('add-combo-name').value,
                badge: document.getElementById('add-combo-badge').value,
                subtitle: document.getElementById('add-combo-subtitle').value,
                btn_label: document.getElementById('add-combo-btn').value,
                price: parseFloat(document.getElementById('add-combo-price').value),
                old_price: parseFloat(document.getElementById('add-combo-old-price').value) || null,
                image: document.getElementById('add-combo-image').value,
                external_url: document.getElementById('add-combo-url').value,
                description: document.getElementById('add-combo-desc').value
            };

            try {
                const res = await fetch('/mc-desk/api/admin/combos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error creating combo deal');
                }
            } catch (err) {
                alert('Network error creating combo');
            }
        }

        async function deleteComboPack(id) {
            if (!confirm('Are you sure you want to delete this combo deal?')) return;
            try {
                const res = await fetch('/mc-desk/api/admin/combos/' + id, { method: 'DELETE' });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting combo');
                }
            } catch (err) {
                alert('Network error deleting combo');
            }
        }

        function openEditComboModal(id, price, btnLabel, desc) {
            document.getElementById('edit-combo-id').value = id;
            document.getElementById('edit-combo-price').value = price;
            document.getElementById('edit-combo-btn').value = btnLabel;
            document.getElementById('edit-combo-desc').value = desc;
            document.getElementById('combo-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeComboModal() {
            document.getElementById('combo-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
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

        // Add / Edit Testimonials Modal handlers
        function openAddTestimonialModal() {
            document.getElementById('add-testimonial-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeAddTestimonialModal() {
            document.getElementById('add-testimonial-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        // Real-time Search Filter for Hub Tabs
        function filterSectionItems(section, query) {
            const q = (query || '').toLowerCase().trim();
            if (section === 'products') {
                const rows = document.querySelectorAll('.searchable-product-row');
                const cards = document.querySelectorAll('.searchable-product-card');
                rows.forEach(r => {
                    const txt = (r.getAttribute('data-search') || '').toLowerCase();
                    r.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
                cards.forEach(c => {
                    const txt = (c.getAttribute('data-search') || '').toLowerCase();
                    c.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
            } else if (section === 'flagship') {
                const cards = document.querySelectorAll('.searchable-flagship-card');
                cards.forEach(c => {
                    const txt = (c.getAttribute('data-search') || '').toLowerCase();
                    c.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
            } else if (section === 'combos') {
                const cards = document.querySelectorAll('.searchable-combo-card');
                cards.forEach(c => {
                    const txt = (c.getAttribute('data-search') || '').toLowerCase();
                    c.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
            } else if (section === 'reviews') {
                const rows = document.querySelectorAll('.searchable-review-row');
                const cards = document.querySelectorAll('.searchable-review-card');
                rows.forEach(r => {
                    const txt = (r.getAttribute('data-search') || '').toLowerCase();
                    r.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
                cards.forEach(c => {
                    const txt = (c.getAttribute('data-search') || '').toLowerCase();
                    c.style.display = (!q || txt.includes(q)) ? '' : 'none';
                });
            }
        }

        // Testimonials
        async function handleCreateTestimonial(e) {
            e.preventDefault();
            const author_name = document.getElementById('t-author').value;
            const author_location = document.getElementById('t-location').value;
            const tag = document.getElementById('t-tag').value || 'Verified Buyer';
            const quote = document.getElementById('t-quote').value;
            const avatar_url = document.getElementById('t-avatar-url').value;
            const rating = parseInt(document.getElementById('t-rating').value) || 5;

            try {
                const res = await fetch('/mc-desk/api/admin/testimonials', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ author_name, author_location, tag, quote, avatar_url, rating })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    showHubToast('Review added successfully!');
                    closeAddTestimonialModal();
                    setTimeout(() => window.location.reload(), 500);
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
                const res = await fetch('/mc-desk/api/admin/testimonials/' + id, { method: 'DELETE' });
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

        // Settings
        async function saveUnifiedSettings() {
            const form = document.getElementById('unified-settings-form');
            const formData = new FormData(form);
            const payload = {};
            for (let [k, v] of formData.entries()) {
                payload[k] = v;
            }

            try {
                const res = await fetch('/mc-desk/api/admin/settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    showHubToast('All site copy and contact settings saved!');
                } else {
                    alert(data.message || 'Error saving settings');
                }
            } catch (err) {
                alert('Network error');
            }
        }

        // Password Modal
        
        function openEditTestimonialModal(id, author, location, tag, rating, quote, avatar) {
            document.getElementById('edit-t-id').value = id;
            document.getElementById('edit-t-author').value = author;
            document.getElementById('edit-t-location').value = location;
            document.getElementById('edit-t-tag').value = tag || 'Verified Buyer';
            document.getElementById('edit-t-rating').value = rating || 5;
            document.getElementById('edit-t-quote').value = quote;
            document.getElementById('edit-t-avatar-url').value = avatar || '';

            const avatarPreview = document.getElementById('edit-t-avatar-preview');
            if (avatarPreview) {
                avatarPreview.src = avatar ? (avatar.startsWith('http') ? avatar : '/' + avatar) : '/images/favicon.png';
            }

            document.getElementById('edit-testimonial-modal').classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        }
        function closeEditTestimonialModal() {
            document.getElementById('edit-testimonial-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }

        async function handleUpdateTestimonial(e) {
            e.preventDefault();
            const id = document.getElementById('edit-t-id').value;
            const author_name = document.getElementById('edit-t-author').value;
            const author_location = document.getElementById('edit-t-location').value;
            const tag = document.getElementById('edit-t-tag').value;
            const rating = parseInt(document.getElementById('edit-t-rating').value, 10);
            const quote = document.getElementById('edit-t-quote').value;
            const avatar_url = document.getElementById('edit-t-avatar-url').value;

            try {
                const res = await fetch('/mc-desk/api/admin/testimonials/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ author_name, author_location, tag, rating, quote, avatar_url })
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating review');
                }
            } catch (err) {
                alert('Network error');
            }
        }

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
            document.getElementById('password-modal').classList.add('invisible', 'opacity-0', 'pointer-events-none');
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
                    setTimeout(() => closePasswordModal(), 2000);
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
     * Admin API: Create a new Product
     */
    public function create(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        $name = trim((string)($data['name'] ?? ''));
        $price = filter_var($data['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $discountedFrom = !empty($data['discounted_from']) ? filter_var($data['discounted_from'], FILTER_VALIDATE_FLOAT) : null;
        $tag = trim((string)($data['tag'] ?? 'Bestseller'));
        $category = trim((string)($data['category'] ?? 'daily'));
        $platform = trim((string)($data['platform_name'] ?? 'Amazon'));
        $image = trim((string)($data['image'] ?? 'images/product_bottle.webp'));
        $externalUrl = filter_var(trim($data['external_url'] ?? ''), FILTER_SANITIZE_URL);
        $desc = trim((string)($data['description'] ?? ''));

        if (empty($name) || $price <= 0 || empty($externalUrl)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Product name, valid price, and external purchase URL are required.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $slug = $slug . '-' . time();

        $stmt = $this->db->prepare(
            "INSERT INTO products (name, slug, tag, category, price, discounted_from, image, description, external_url, platform_name, sort_order, is_active)
             VALUES (:name, :slug, :tag, :category, :price, :discounted_from, :image, :description, :external_url, :platform_name, 10, 1)"
        );

        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':tag' => $tag,
            ':category' => $category,
            ':price' => $price,
            ':discounted_from' => $discountedFrom,
            ':image' => $image,
            ':description' => $desc,
            ':external_url' => $externalUrl,
            ':platform_name' => $platform
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Product added successfully!'
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

        $name = trim((string)($data['name'] ?? ''));
        $price = filter_var($data['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $discountedFrom = !empty($data['discounted_from']) ? filter_var($data['discounted_from'], FILTER_VALIDATE_FLOAT) : null;
        $tag = trim((string)($data['tag'] ?? 'Bestseller'));
        $category = trim((string)($data['category'] ?? 'daily'));
        $platformName = htmlspecialchars(trim($data['platform_name'] ?? 'Amazon'), ENT_QUOTES, 'UTF-8');
        $image = trim((string)($data['image'] ?? 'images/product_bottle.webp'));
        $externalUrl = filter_var(trim($data['external_url'] ?? ''), FILTER_SANITIZE_URL);
        $description = trim((string)($data['description'] ?? ''));
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if (empty($name) || $price <= 0 || empty($externalUrl) || !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Product name, valid price, and valid destination URL are required.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare(
            "UPDATE products 
             SET name = :name,
                 price = :price,
                 discounted_from = :discounted_from,
                 tag = :tag,
                 category = :category,
                 platform_name = :platform_name,
                 image = :image,
                 external_url = :external_url,
                 description = :description,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );

        $stmt->execute([
            ':name' => $name,
            ':price' => $price,
            ':discounted_from' => $discountedFrom,
            ':tag' => $tag,
            ':category' => $category,
            ':platform_name' => $platformName,
            ':image' => $image,
            ':external_url' => $externalUrl,
            ':description' => $description,
            ':is_active' => $isActive,
            ':id' => $productId
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Product details and redirect updated successfully.'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Fetches preview metadata & image from an external product URL (OpenGraph / Twitter card / Schema.org)
     */
    public function fetchUrlMetadata(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $targetUrl = filter_var(trim($data['url'] ?? ''), FILTER_SANITIZE_URL);

        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Please provide a valid URL to inspect.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // SSRF Guard: Disallow non-http/https schemes and private/internal IP ranges
        $parsed = parse_url($targetUrl);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = $parsed['host'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true) || empty($host)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid URL scheme.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $ip = gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Internal or private network targets are restricted.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Multi-tier scraper mimicking genuine browser and social-link preview crawlers (WhatsApp/Facebook)
        $fetchHtml = function(string $url, string $userAgent, array $extraHeaders = []): ?string {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 6);
                curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
                curl_setopt($ch, CURLOPT_TIMEOUT, 9);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $headers = array_merge([
                    'User-Agent: ' . $userAgent,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                    'Sec-Ch-Ua-Mobile: ?0',
                    'Sec-Ch-Ua-Platform: "Windows"',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Sec-Fetch-User: ?1',
                    'Upgrade-Insecure-Requests: 1'
                ], $extraHeaders);

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $result = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($result !== false && $code >= 200 && $code < 400 && strlen($result) > 100) {
                    return $result;
                }
            }

            // Fallback to stream context
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'user_agent' => $userAgent
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $res = @file_get_contents($url, false, $ctx);
            return ($res !== false && !empty($res)) ? $res : null;
        };

        // Tier 1: Real Chrome Browser UA
        $html = $fetchHtml($targetUrl, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');

        // Tier 2: WhatsApp / Meta Social Link Preview Crawler UA (how WhatsApp unrolls links)
        if (!$html) {
            $html = $fetchHtml($targetUrl, 'WhatsApp/2.23.23.77 N', ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']);
        }

        // Tier 3: Facebook External Hit Crawler UA
        if (!$html) {
            $html = $fetchHtml($targetUrl, 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
        }

        // If HTML could not be directly fetched due to strict retail bot walls (e.g. Akamai on Meesho)
        if ($html === false || empty($html)) {
            $parsedUrl = parse_url($targetUrl);
            $path = $parsedUrl['path'] ?? '';
            $inferredTitle = null;

            // Extract readable product title from e-commerce URL slugs
            if (str_contains($targetUrl, 'meesho.com')) {
                // e.g. /portronics-luxcell-.../p/6ihva0
                $slug = explode('/p/', $path)[0];
                $slug = trim($slug, '/');
                if (!empty($slug)) {
                    $inferredTitle = ucwords(str_replace(['-', '_'], ' ', $slug));
                }
            } elseif (str_contains($targetUrl, 'flipkart.com')) {
                // e.g. /product-slug/p/itm...
                $slug = explode('/p/', $path)[0];
                $slug = trim($slug, '/');
                if (!empty($slug)) {
                    $inferredTitle = ucwords(str_replace(['-', '_'], ' ', $slug));
                }
            } elseif (str_contains($targetUrl, 'amazon.')) {
                // e.g. /product-title/dp/...
                if (preg_match('#/([^/]+)/dp/#i', $path, $sm)) {
                    $inferredTitle = ucwords(str_replace(['-', '_'], ' ', $sm[1]));
                }
            }

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'data' => [
                    'title' => $inferredTitle,
                    'image' => null,
                    'protected' => true,
                    'notice' => 'Destination site (e.g. Meesho/Akamai) requires browser JS or is protected. Product name auto-inferred from URL. You can upload or paste the product photo directly.'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $imageUrl = null;
        $title = null;

        // 1. og:image
        if (preg_match('/<meta\s+[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta\s+[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:image[\'"]/i', $html, $m)) {
            $imageUrl = $m[1];
        }

        // 2. twitter:image
        if (!$imageUrl) {
            if (preg_match('/<meta\s+[^>]*name=[\'"]twitter:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
                $imageUrl = $m[1];
            } elseif (preg_match('/<meta\s+[^>]*content=[\'"]([^\'"]+)[\'"][^>]*name=[\'"]twitter:image[\'"]/i', $html, $m)) {
                $imageUrl = $m[1];
            }
        }

        // 3. Amazon specific main image extraction (data-old-hires or dynamic landingImage)
        if (!$imageUrl && str_contains($targetUrl, 'amazon')) {
            if (preg_match('/data-old-hires=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
                $imageUrl = $m[1];
            } elseif (preg_match('/id=[\'"]landingImage[\'"][^>]*src=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
                $imageUrl = $m[1];
            }
        }

        // 4. Title tag extraction
        if (preg_match('/<meta\s+[^>]*property=[\'"]og:title[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => [
                'title' => $title,
                'image' => $imageUrl
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Upload custom product photo
     */
    public function uploadProductPhoto(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['photo'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'No image file uploaded.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $photo = $uploadedFiles['photo'];
        if ($photo->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Upload error occurred.'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $filename = $photo->getClientFilename();
        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Only JPG, PNG, WEBP, or GIF image formats are accepted.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $imagesDir = __DIR__ . '/../../../images';
        if (!is_dir($imagesDir)) {
            @mkdir($imagesDir, 0755, true);
        }

        $baseName = 'upload_' . time() . '_' . bin2hex(random_bytes(3));
        $targetWebpName = $baseName . '.webp';
        $targetPath = $imagesDir . '/' . $targetWebpName;

        try {
            $stream = $photo->getStream();
            $tempFile = tempnam(sys_get_temp_dir(), 'upl_');
            $stream->rewind();
            file_put_contents($tempFile, $stream->getContents());

            // Check if GD can create image and convert/compress to WebP
            $imageResource = null;
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    if (function_exists('imagecreatefromjpeg')) $imageResource = @imagecreatefromjpeg($tempFile);
                    break;
                case 'png':
                    if (function_exists('imagecreatefrompng')) {
                        $imageResource = @imagecreatefrompng($tempFile);
                        if ($imageResource) {
                            imagepalettetotruecolor($imageResource);
                            imagealphablending($imageResource, true);
                            imagesavealpha($imageResource, true);
                        }
                    }
                    break;
                case 'webp':
                    if (function_exists('imagecreatefromwebp')) $imageResource = @imagecreatefromwebp($tempFile);
                    break;
                case 'gif':
                    if (function_exists('imagecreatefromgif')) $imageResource = @imagecreatefromgif($tempFile);
                    break;
            }

            if ($imageResource && function_exists('imagewebp')) {
                // Resize if oversized (keep max dimension 1400px)
                $origW = imagesx($imageResource);
                $origH = imagesy($imageResource);
                $maxDim = 1400;

                if ($origW > $maxDim || $origH > $maxDim) {
                    $ratio = min($maxDim / $origW, $maxDim / $origH);
                    $newW = (int)round($origW * $ratio);
                    $newH = (int)round($origH * $ratio);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagecopyresampled($resized, $imageResource, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($imageResource);
                    $imageResource = $resized;
                }

                // Compress to WebP at 85 quality
                imagewebp($imageResource, $targetPath, 85);
                imagedestroy($imageResource);
                @unlink($tempFile);
            } else {
                // Fallback direct move if GD cannot decode
                if ($imageResource) imagedestroy($imageResource);
                if (file_exists($tempFile)) {
                    @copy($tempFile, $targetPath);
                    @unlink($tempFile);
                } else {
                    $photo->moveTo($targetPath);
                }
            }
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Failed to process and compress image: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $relativePath = 'images/' . $targetWebpName;
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'image_path' => $relativePath,
            'message' => 'Photo uploaded successfully!'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

}