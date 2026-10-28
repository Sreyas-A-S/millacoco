// Tailwind CSS Configuration with Royal Maroon & Warm Amber Gold Palette (#611B10, #D4A853, #FAF5EC)
tailwind.config = {
    theme: {
        extend: {
            colors: {
                maroon: {
                    DEFAULT: '#611B10',
                    50: '#fbf5f4',
                    100: '#f6e9e6',
                    200: '#edd3cd',
                    300: '#dfb4ab',
                    400: '#be6f5f',
                    500: '#8f3221',
                    600: '#782415',
                    700: '#611B10', // Label Header Maroon
                    800: '#4f140b',
                    900: '#3a0e07',
                },
                gold: {
                    DEFAULT: '#D4A853',
                    50: '#fdfbf6',
                    100: '#f9f4e9',
                    200: '#f1e6cf',
                    300: '#e5d2aa',
                    400: '#D4A853', // Label Warm Gold Sheen
                    500: '#c4953e',
                    600: '#a87b2c',
                    700: '#855f22',
                    800: '#68481d',
                    900: '#4a3313',
                },
                cream: {
                    DEFAULT: '#FAF5EC',
                    50: '#fefdfb',
                    100: '#FAF5EC', // Soft Warm Linen
                    200: '#f2e8d5',
                    300: '#e5d4b7',
                    400: '#d4ba92',
                },
                palm: {
                    DEFAULT: '#2E6F40',
                    50: '#f2f8f4',
                    100: '#e1efe4',
                    500: '#2E6F40',
                    600: '#255b34',
                    700: '#1d4829',
                },

                // Aliases to seamlessly update existing classes
                sage: {
                    50: '#fbf5f4',
                    100: '#f6e9e6',
                    200: '#edd3cd',
                    300: '#dfb4ab',
                    400: '#be6f5f',
                    500: '#8f3221',
                    600: '#782415',
                    700: '#611B10', // Deep Royal Maroon
                    800: '#4f140b', // Rich Mahogany
                    900: '#3a0e07', // Deepest Royal Maroon
                },
                ambergold: {
                    50: '#fdfbf6',
                    100: '#f9f4e9',
                    200: '#f1e6cf',
                    300: '#e5d2aa',
                    400: '#D4A853', // Warm Gold
                    500: '#c4953e',
                    600: '#a87b2c',
                    700: '#855f22',
                },
                stonebg: '#FAF5EC', // Base Linen Background
                cardbg: '#FFFFFF',
            },
            fontFamily: {
                serif: ['Playfair Display', 'serif'],
                sans: ['Plus Jakarta Sans', 'sans-serif'],
                handwriting: ['Caveat', 'cursive'],
            },
            keyframes: {
                letterBlurIn: {
                    '0%': { opacity: '0', filter: 'blur(12px)', transform: 'translateY(12px) scale(0.96)' },
                    '100%': { opacity: '1', filter: 'blur(0px)', transform: 'translateY(0) scale(1)' }
                },
                floatGentle: {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-8px)' }
                },
                glowPulse: {
                    '0%, 100%': { transform: 'scale(1)', opacity: '0.65' },
                    '50%': { transform: 'scale(1.08)', opacity: '0.9' }
                },
                shimmer: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(200%)' }
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' }
                }
            },
            animation: {
                'blur-in': 'letterBlurIn 0.7s cubic-bezier(0.25, 1, 0.5, 1) forwards',
                'float-slow': 'floatGentle 4.5s ease-in-out infinite',
                'glow-pulse': 'glowPulse 3.5s ease-in-out infinite',
                'shimmer': 'shimmer 2.2s infinite',
                'marquee': 'marquee 38s linear infinite'
            }
        }
    }
};

// Cart Drawer Helpers
function openCartDrawer() {
    const cartDrawer = document.getElementById('cart-drawer');
    const cartContent = document.getElementById('cart-content');
    if (cartDrawer && cartContent) {
        cartDrawer.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
        cartContent.classList.remove('translate-x-full');
    }
}

function closeCartDrawer() {
    const cartDrawer = document.getElementById('cart-drawer');
    const cartContent = document.getElementById('cart-content');
    if (cartDrawer && cartContent) {
        cartContent.classList.add('translate-x-full');
        setTimeout(() => {
            cartDrawer.classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }, 300);
    }
}

// Favorites State & Logic
let favorites = [];

function isProductFavorited(title) {
    return favorites.some(item => item.title === title);
}

function updateFavoriteButtonsUI() {
    updateFavoritesUI();
}

function toggleFavorite(event, title, price, image = '') {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    const index = favorites.findIndex(item => item.title === title);
    let isFav = false;

    if (index > -1) {
        favorites.splice(index, 1);
        isFav = false;
        showToast(`Removed ${title} from favorites`);
    } else {
        favorites.push({ title, price, image });
        isFav = true;
        showToast(`Saved ${title} to favorites!`);
    }

    // Trigger pop animation on target button
    if (event && event.currentTarget) {
        const btn = event.currentTarget;
        btn.classList.add('heart-pop');
        setTimeout(() => btn.classList.remove('heart-pop'), 450);
    }

    updateFavoritesUI();
}

function updateFavoritesUI() {
    const counter = document.getElementById('fav-counter');
    const itemsContainer = document.getElementById('fav-items');

    if (counter) {
        counter.innerText = favorites.length;
        counter.classList.remove('cart-badge-bounce');
        void counter.offsetWidth; // trigger reflow
        counter.classList.add('cart-badge-bounce');
    }

    // Update heart icons across the page
    document.querySelectorAll('.fav-btn-toggle').forEach(btn => {
        const itemTitle = btn.getAttribute('data-title');
        const icon = btn.querySelector('i');
        const isFav = favorites.some(f => f.title === itemTitle);

        if (icon) {
            if (isFav) {
                icon.className = 'fa-solid fa-heart text-xs text-rose-500';
            } else {
                icon.className = 'fa-regular fa-heart text-xs text-sage-400 group-hover:text-rose-500';
            }
        }
    });

    if (!itemsContainer) return;

    if (favorites.length === 0) {
        itemsContainer.innerHTML = '<p id="empty-fav-msg" class="text-center text-xs text-sage-700 py-12">No favorite items yet. Click the heart icon on any product to save it here!</p>';
        return;
    }

    let html = '';
    favorites.forEach((item, index) => {
        const imgHtml = item.image 
            ? `<img src="${item.image}" alt="${item.title}" class="w-12 h-12 object-cover rounded-xl border border-white/80 shadow-xs mr-3 shrink-0">`
            : '';
        html += `
            <div class="flex items-center justify-between bg-white/80 p-3.5 rounded-2xl border border-white/90 shadow-sm transition hover:shadow-md">
                <div class="flex items-center">
                    ${imgHtml}
                    <div>
                        <h4 class="font-serif font-bold text-xs text-sage-900 leading-snug">${item.title}</h4>
                        <p class="price-tag text-[11px] font-bold text-sage-800 mt-0.5">₹${item.price}.00</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="addToCart('${item.title}', ${item.price}, '${item.image}', event)" class="bg-sage-800 hover:bg-sage-900 text-white text-[11px] px-3 py-1.5 rounded-full font-medium transition shadow-xs flex items-center space-x-1" title="Add to Cart">
                        <i class="fa-solid fa-cart-plus text-[10px]"></i>
                        <span>Add</span>
                    </button>
                    <button onclick="toggleFavorite(null, '${item.title}', ${item.price}, '${item.image}')" class="text-rose-400 hover:text-rose-600 p-1.5 transition text-xs" title="Remove">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        `;
    });

    itemsContainer.innerHTML = html;
}

function openFavoritesModal() {
    const modal = document.getElementById('favorites-modal');
    const content = document.getElementById('favorites-content');
    if (!modal || !content) return;

    modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
    setTimeout(() => {
        content.classList.remove('translate-x-full');
    }, 10);
    updateFavoritesUI();
}

function closeFavoritesModal() {
    const modal = document.getElementById('favorites-modal');
    const content = document.getElementById('favorites-content');
    if (!modal || !content) return;

    content.classList.add('translate-x-full');
    setTimeout(() => {
        modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
    }, 300);
}

function addAllFavoritesToCart() {
    if (favorites.length === 0) {
        showToast('Your favorites list is empty!');
        return;
    }

    favorites.forEach(item => {
        const existing = cart.find(c => c.title === item.title);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({ title: item.title, price: item.price, qty: 1, image: item.image });
        }
    });

    updateCartUI();
    closeFavoritesModal();
    showToast(`Added all ${favorites.length} favorite items to cart!`);
    openCartDrawer();
}

// Shopping Cart Logic
let cart = [];

function addToCart(title, price, image = '', event = null) {
    const existing = cart.find(item => item.title === title);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ title, price, qty: 1, image });
    }

    // Interactive button animation
    if (event && event.currentTarget) {
        const btn = event.currentTarget;
        btn.classList.add('btn-press');
        setTimeout(() => btn.classList.remove('btn-press'), 300);
        animateFlyingDotToCart(event.currentTarget);
    }

    updateCartUI();
    showToast(`Added ${title} to cart!`);
}

// Flying particle animation to cart icon
function animateFlyingDotToCart(sourceElement) {
    const cartBtn = document.getElementById('cart-btn');
    if (!sourceElement || !cartBtn) return;

    const sourceRect = sourceElement.getBoundingClientRect();
    const targetRect = cartBtn.getBoundingClientRect();

    const particle = document.createElement('div');
    particle.className = 'flying-particle';
    particle.style.width = '14px';
    particle.style.height = '14px';
    particle.style.left = `${sourceRect.left + sourceRect.width / 2 - 7}px`;
    particle.style.top = `${sourceRect.top + sourceRect.height / 2 - 7}px`;
    particle.style.transform = 'translate(0, 0) scale(1)';
    particle.style.opacity = '1';

    document.body.appendChild(particle);

    requestAnimationFrame(() => {
        const deltaX = (targetRect.left + targetRect.width / 2) - (sourceRect.left + sourceRect.width / 2);
        const deltaY = (targetRect.top + targetRect.height / 2) - (sourceRect.top + sourceRect.height / 2);

        particle.style.transform = `translate(${deltaX}px, ${deltaY}px) scale(0.3)`;
        particle.style.opacity = '0.3';
    });

    setTimeout(() => {
        particle.remove();
        const badge = document.getElementById('cart-counter');
        if (badge) {
            badge.classList.remove('cart-badge-bounce');
            void badge.offsetWidth;
            badge.classList.add('cart-badge-bounce');
        }
    }, 650);
}

function updateCartUI() {
    const counter = document.getElementById('cart-counter');
    const itemsContainer = document.getElementById('cart-items');
    const subtotalElem = document.getElementById('cart-subtotal');

    if (!counter || !itemsContainer || !subtotalElem) return;

    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
    counter.innerText = totalItems;

    if (cart.length === 0) {
        itemsContainer.innerHTML = '<p class="text-center text-xs text-sage-700 py-12">Your cart is currently empty.</p>';
        subtotalElem.innerText = '₹0.00';
        return;
    }

    let html = '';
    let subtotal = 0;

    cart.forEach((item, index) => {
        subtotal += item.price * item.qty;
        const imgHtml = item.image 
            ? `<img src="${item.image}" alt="${item.title}" class="w-10 h-10 object-cover rounded-xl border border-white/80 shadow-xs mr-3">`
            : '';
        html += `
            <div class="flex items-center justify-between bg-white/80 p-3.5 rounded-2xl border border-white/90 shadow-sm">
                <div class="flex items-center">
                    ${imgHtml}
                    <div>
                        <h4 class="font-serif font-bold text-xs text-sage-900">${item.title}</h4>
                        <p class="price-tag text-[11px] text-sage-700">₹${item.price}.00 x ${item.qty}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="changeQty(${index}, -1)" class="w-6 h-6 rounded-full bg-sage-100 text-sage-900 text-xs flex items-center justify-center font-bold hover:bg-sage-200 transition">-</button>
                    <span class="text-xs font-bold text-sage-900">${item.qty}</span>
                    <button onclick="changeQty(${index}, 1)" class="w-6 h-6 rounded-full bg-sage-100 text-sage-900 text-xs flex items-center justify-center font-bold hover:bg-sage-200 transition">+</button>
                </div>
            </div>
        `;
    });

    itemsContainer.innerHTML = html;
    subtotalElem.innerText = `₹${subtotal.toFixed(2)}`;
}

function changeQty(index, delta) {
    cart[index].qty += delta;
    if (cart[index].qty <= 0) {
        cart.splice(index, 1);
    }
    updateCartUI();
}

function checkout() {
    if (cart.length === 0) {
        showToast('Your cart is empty!');
        return;
    }
    showToast('Redirecting to Millacoco WhatsApp ordering line...');
    
    let msg = "Hello Millacoco, I would like to order:\n";
    cart.forEach(item => {
        msg += `- ${item.title} x ${item.qty}\n`;
    });
    const encoded = encodeURIComponent(msg);
    setTimeout(() => {
        window.open(`https://wa.me/918089757815?text=${encoded}`, '_blank');
    }, 1000);

    cart = [];
    updateCartUI();
    closeCartDrawer();
}

// Product Filtering
function filterProducts(category) {
    const cards = document.querySelectorAll('.product-card');
    const buttons = document.querySelectorAll('.filter-btn');

    buttons.forEach(btn => {
        if (btn.dataset.filter === category) {
            btn.classList.remove('bg-white', 'text-sage-800');
            btn.classList.add('bg-sage-800', 'text-white');
        } else {
            btn.classList.remove('bg-sage-800', 'text-white');
            btn.classList.add('bg-white', 'text-sage-800');
        }
    });

    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Master Products Data for Search
const productCatalog = [
    { name: 'Millacoco 1 Ltr Kitchen Pouch', price: 280, image: 'images/product_pouch.png', tag: 'Spout Pouch', desc: '1 Ltr Easy Pour Kitchen Pouch' },
    { name: 'Millacoco 1 Ltr Classic Bottle', price: 290, image: 'images/product_bottle.png', tag: 'Bestseller', desc: '1 Ltr Pure Cold Pressed Bottle' },
    { name: 'Millacoco 2 Ltr Twin Pack (1L x 2)', price: 570, image: 'images/twin_pack.jpg', tag: 'Twin Value', desc: 'Twin Pack 2 Litres' },
    { name: 'Millacoco 5 Ltr Household Can', price: 1390, image: 'images/can_5ltr.jpg', tag: 'Bulk Supply', desc: '5 Ltr Heavy Can' },
    { name: 'Millacoco Virgin Hair & Face Oil', price: 320, image: 'images/virgin_oil.jpg', tag: 'Skincare', desc: 'Virgin Hair & Scalp Oil' },
    { name: 'Millacoco Kitchen & Skincare Duo Pack', price: 590, image: 'images/combo_duo.jpg', tag: 'Combo Offer', desc: '1L Classic + Virgin Oil' }
];

// Special Combo Packs Data
const comboPacks = [
    {
        id: 'combo-1',
        name: 'Millacoco Combo Duo Pack',
        badge: 'KITCHEN & WELLNESS DUO',
        subtitle: '1L Classic Cooking Oil + Virgin Hair Oil',
        title: 'Healthy Kitchen.<br><span class="font-handwriting font-normal text-ambergold-300 text-4xl sm:text-5xl">Happy Family.</span>',
        desc: '1 Ltr Classic Cooking Oil + Virgin Scalp & Skincare Oil with complimentary express delivery across India.',
        price: 590,
        oldPrice: 610,
        image: 'images/combo_duo.jpg',
        btnLabel: 'ORDER DUO PACK'
    },
    {
        id: 'combo-2',
        name: 'Millacoco 2L Twin Pack',
        badge: 'HOUSEHOLD TWIN VALUE',
        subtitle: '1L Classic Bottle x 2 Double Savings',
        title: 'Twin Savings.<br><span class="font-handwriting font-normal text-ambergold-300 text-4xl sm:text-5xl">Everyday Pure.</span>',
        desc: 'Two 1 Ltr Classic Cold Pressed Glass Bottles packed together with zero shipping charge and extra freshness seal.',
        price: 570,
        oldPrice: 580,
        image: 'images/twin_pack.jpg',
        btnLabel: 'ORDER TWIN PACK'
    },
    {
        id: 'combo-3',
        name: 'Millacoco 5L Master Culinary Pack',
        badge: 'BULK VALUE MASTER',
        subtitle: '5L Heavy Can + Free 500ml Kitchen Glass Jar',
        title: 'Master Kitchen.<br><span class="font-handwriting font-normal text-ambergold-300 text-4xl sm:text-5xl">Direct Mill Value.</span>',
        desc: '5 Ltr Heavy Duty Household Can with free refill dispenser. Ideal for joint families and authentic festive Malabar cooking.',
        price: 1390,
        oldPrice: 1450,
        image: 'images/can_5ltr.jpg',
        btnLabel: 'ORDER 5L PACK'
    }
];

let currentComboIndex = 0;
let comboCarouselTimer = null;
const COMBO_AUTOPLAY_INTERVAL = 5000;

// Header Search Bar Live Logic
function searchHeaderProducts() {
    const input = document.getElementById('header-search-input');
    const dropdown = document.getElementById('header-search-dropdown');
    const clearBtn = document.getElementById('header-search-clear');
    if (!input || !dropdown) return;

    const query = input.value.trim().toLowerCase();
    if (clearBtn) {
        clearBtn.classList.toggle('hidden', query.length === 0);
    }

    if (!query) {
        dropdown.classList.add('hidden');
        dropdown.innerHTML = '';
        return;
    }

    const filtered = productCatalog.filter(item => 
        item.name.toLowerCase().includes(query) || 
        item.desc.toLowerCase().includes(query) ||
        item.tag.toLowerCase().includes(query)
    );

    if (filtered.length === 0) {
        dropdown.innerHTML = '<p class="text-center text-xs text-sage-600 py-3">No matching Millacoco products found.</p>';
        dropdown.classList.remove('hidden');
        return;
    }

    let html = '<div class="text-[10px] uppercase font-bold text-sage-500 tracking-wider px-1 pb-1">Products Found</div>';
    filtered.forEach(item => {
        html += `
            <div class="flex items-center justify-between p-2 hover:bg-sage-50/90 rounded-xl transition duration-200 cursor-pointer group">
                <div class="flex items-center space-x-3" onclick="addToCart('${item.name}', ${item.price}, '${item.image}'); clearHeaderSearch();">
                    <img src="${item.image}" alt="${item.name}" class="w-10 h-10 object-cover rounded-lg border border-sage-200 shadow-xs">
                    <div>
                        <h5 class="text-xs font-semibold text-sage-900 group-hover:text-sage-700 leading-snug">${item.name}</h5>
                        <div class="flex items-center space-x-2 mt-0.5">
                            <span class="text-xs font-bold text-sage-900">₹${item.price}.00</span>
                            <span class="text-[9px] bg-ambergold-100 text-ambergold-600 px-1.5 py-0.2 rounded-full font-semibold">${item.tag}</span>
                        </div>
                    </div>
                </div>
                <button onclick="addToCart('${item.name}', ${item.price}, '${item.image}'); clearHeaderSearch();" class="text-[10px] bg-sage-800 hover:bg-sage-900 text-white px-2.5 py-1 rounded-full font-medium shadow-xs transition">
                    + Add
                </button>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.classList.remove('hidden');
}

function clearHeaderSearch() {
    const input = document.getElementById('header-search-input');
    const dropdown = document.getElementById('header-search-dropdown');
    const clearBtn = document.getElementById('header-search-clear');
    if (input) input.value = '';
    if (dropdown) dropdown.classList.add('hidden');
    if (clearBtn) clearBtn.classList.add('hidden');
}

// Modal Search Logic (for mobile / icon trigger)
function searchProducts() {
    const input = document.getElementById('search-input');
    const results = document.getElementById('search-results');
    if (!input || !results) return;

    const query = input.value.trim().toLowerCase();
    if (!query) {
        results.innerHTML = '<p class="text-sage-500 italic">Type above to search Millacoco products...</p>';
        return;
    }

    const filtered = productCatalog.filter(item => 
        item.name.toLowerCase().includes(query) || 
        item.desc.toLowerCase().includes(query) ||
        item.tag.toLowerCase().includes(query)
    );

    if (filtered.length === 0) {
        results.innerHTML = '<p class="text-sage-500 italic">No matching products found.</p>';
        return;
    }

    let html = '';
    filtered.forEach(item => {
        html += `
            <div class="flex items-center justify-between p-2.5 hover:bg-sage-50 rounded-xl cursor-pointer transition" onclick="addToCart('${item.name}', ${item.price}, '${item.image}'); document.getElementById('search-modal').classList.add('hidden');">
                <div class="flex items-center space-x-3">
                    <img src="${item.image}" alt="${item.name}" class="w-10 h-10 object-cover rounded-lg border border-sage-200">
                    <div>
                        <span class="block text-xs font-semibold text-sage-900">${item.name}</span>
                        <span class="text-[10px] text-sage-600 font-medium">₹${item.price}.00 • ${item.tag}</span>
                    </div>
                </div>
                <span class="text-xs font-bold text-sage-800 bg-sage-100 hover:bg-sage-200 px-3 py-1 rounded-full">+ Add</span>
            </div>
        `;
    });
    results.innerHTML = html;
}

// Toast Notification
function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-msg');
    if (toast && toastMsg) {
        toastMsg.innerText = message;
        toast.classList.remove('translate-y-20', 'opacity-0');
        
        setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0');
        }, 3000);
    }
}

// Newsletter Handler
function handleSubscribe(e) {
    e.preventDefault();
    showToast('Subscribed! You will receive Millacoco coconut harvest updates.');
    e.target.reset();
}

// Event Listeners Initialization
function initApp() {
    // Mobile Drawer Toggle
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Cart Drawer Buttons
    const cartBtn = document.getElementById('cart-btn');
    const closeCart = document.getElementById('close-cart');
    const cartOverlay = document.getElementById('cart-overlay');

    if (cartBtn) cartBtn.addEventListener('click', openCartDrawer);
    if (closeCart) closeCart.addEventListener('click', closeCartDrawer);
    if (cartOverlay) cartOverlay.addEventListener('click', closeCartDrawer);

    // Search Modal (mobile button & close)
    const searchBtn = document.getElementById('search-btn');
    const searchModal = document.getElementById('search-modal');
    const closeSearch = document.getElementById('close-search');

    if (searchBtn && searchModal) {
        searchBtn.addEventListener('click', () => {
            searchModal.classList.remove('hidden');
            searchModal.classList.add('flex');
            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.focus();
        });
    }

    if (closeSearch && searchModal) {
        closeSearch.addEventListener('click', () => {
            searchModal.classList.add('hidden');
            searchModal.classList.remove('flex');
        });
    }

    // Close header search dropdown when clicking outside
    document.addEventListener('click', (e) => {
        const headerSearch = document.getElementById('header-search-input');
        const headerDropdown = document.getElementById('header-search-dropdown');
        if (headerDropdown && headerSearch && !headerSearch.contains(e.target) && !headerDropdown.contains(e.target)) {
            headerDropdown.classList.add('hidden');
        }
    });

    // Process Video Modal Logic
    const videoModalBtn = document.getElementById('video-modal-btn');
    const videoModal = document.getElementById('video-modal');
    const closeVideo = document.getElementById('close-video');

    if (videoModalBtn && videoModal) {
        videoModalBtn.addEventListener('click', () => {
            videoModal.classList.remove('hidden');
            videoModal.classList.add('flex');
        });
    }

    if (closeVideo && videoModal) {
        closeVideo.addEventListener('click', () => {
            videoModal.classList.add('hidden');
            videoModal.classList.remove('flex');
        });
    }

    // Close mobile menu when anchor link is clicked
    if (mobileMenu) {
        mobileMenu.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
            });
        });
    }

    // Apply Letter-by-Letter Blur-In Animation to Hero Text
    initHeroTextBlurAnimation();

    // Scroll-reveal animations for sections and cards
    initScrollReveal();

    // Sticky header shrink & glassmorphism on scroll
    initStickyHeaderScroll();

    // 3D Tilt Interaction on product cards
    initCardTilt();

    // Single-row Auto-sliding Testimonials Carousel
    initTestimonialsCarousel();

    // Special Combo Pack Interactive Carousel
    initComboCarousel();
}

// Hardware-Accelerated Smooth Infinite Auto-Slide Testimonials
function initTestimonialsCarousel() {
    const track = document.getElementById('testimonials-track');
    if (!track) return;

    // Duplicate all items once for seamless continuous infinite scroll
    const originalItems = Array.from(track.children);
    originalItems.forEach(item => {
        const clone = item.cloneNode(true);
        track.appendChild(clone);
    });

    // Apply Tailwind marquee animation class
    track.classList.add('animate-marquee', 'hover:[animation-play-state:paused]');
}

// Scroll Reveal with Tailwind transitions
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.scroll-reveal');
    if (!revealElements.length) return;

    revealElements.forEach(el => {
        el.classList.add('opacity-0', 'translate-y-8', 'transition-all', 'duration-700', 'ease-out');
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.remove('opacity-0', 'translate-y-8');
                entry.target.classList.add('opacity-100', 'translate-y-0');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealElements.forEach(el => observer.observe(el));
}

// Sticky Header: Hide on scroll down, show on scroll up with glassmorphism
function initStickyHeaderScroll() {
    const mainHeader = document.getElementById('main-header');
    const headerInner = document.getElementById('header-inner');
    if (!mainHeader || !headerInner) return;

    let lastScrollY = window.scrollY;
    const scrollThreshold = 10; // minimum movement to trigger change

    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;

        // Apply glassmorphism styling past 40px
        if (currentScrollY > 40) {
            headerInner.classList.add('glass-pill', 'shadow-md', 'border', 'border-white/90');
        } else {
            headerInner.classList.remove('glass-pill', 'shadow-md', 'border', 'border-white/90');
        }

        // Hide when scrolling down, show when scrolling up
        if (Math.abs(currentScrollY - lastScrollY) > scrollThreshold) {
            if (currentScrollY > lastScrollY && currentScrollY > 120) {
                // Scrolling down -> hide header
                mainHeader.classList.add('-translate-y-24', 'opacity-0', 'pointer-events-none');
            } else {
                // Scrolling up -> reveal header
                mainHeader.classList.remove('-translate-y-24', 'opacity-0', 'pointer-events-none');
            }
            lastScrollY = currentScrollY;
        }
    }, { passive: true });
}

// Subtle 3D tilt on card hover
function initCardTilt() {
    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const rotateX = (-y / rect.height) * 6;
            const rotateY = (x / rect.width) * 6;
            card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) translateY(0px)';
        });
    });
}

function initHeroTextBlurAnimation() {
    const heroTitle = document.getElementById('hero-title');
    if (!heroTitle) return;

    // Helper to turn text into wrapped word/letter spans with progressive delay
    let currentDelay = 0.15; // base start delay in seconds
    const step = 0.035;      // delay per character

    function wrapNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent;
            if (!text.trim()) return node; // preserve spacing

            const fragment = document.createDocumentFragment();
            // Split by words while keeping whitespace
            const words = text.split(/(\s+)/);

            words.forEach(word => {
                if (/^\s+$/.test(word)) {
                    fragment.appendChild(document.createTextNode(word));
                } else if (word.length > 0) {
                    const wordSpan = document.createElement('span');
                    wordSpan.className = 'blur-word inline-block';

                    for (const char of word) {
                        const charSpan = document.createElement('span');
                        // Native Tailwind utility classes
                        charSpan.className = 'inline-block opacity-0 animate-blur-in will-change-transform';
                        charSpan.style.animationDelay = `${currentDelay.toFixed(3)}s`;
                        charSpan.textContent = char;
                        currentDelay += step;
                        wordSpan.appendChild(charSpan);
                    }
                    fragment.appendChild(wordSpan);
                }
            });
            return fragment;
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.tagName.toLowerCase() === 'br') return node;
            const clone = node.cloneNode(false);
            Array.from(node.childNodes).forEach(child => {
                clone.appendChild(wrapNode(child));
            });
            return clone;
        }
        return node;
    }

    const fragment = document.createDocumentFragment();
    Array.from(heroTitle.childNodes).forEach(child => {
        fragment.appendChild(wrapNode(child));
    });

    heroTitle.innerHTML = '';
    heroTitle.appendChild(fragment);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

// Simple Preloader Logic
(function initPreloader() {
    let progress = 0;
    let isCompleted = false;

    function updateProgress(val) {
        const progressBar = document.getElementById('preloader-progress');
        if (progressBar) progressBar.style.width = val + '%';
    }

    const progressInterval = setInterval(() => {
        if (isCompleted) return;
        progress = Math.min(progress + 25, 90);
        updateProgress(progress);
        if (progress >= 90) clearInterval(progressInterval);
    }, 60);

    function completePreloader() {
        if (isCompleted) return;
        isCompleted = true;
        clearInterval(progressInterval);
        updateProgress(100);

        setTimeout(() => {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('preloader-hidden');
                setTimeout(() => {
                    preloader.style.display = 'none';
                }, 400);
            }
        }, 120);
    }

    if (document.readyState === 'complete') {
        setTimeout(completePreloader, 200);
    } else {
        window.addEventListener('load', () => {
            setTimeout(completePreloader, 150);
        });
        setTimeout(completePreloader, 1200);
    }
})();

// Parallax Floating Bokeh Leaves & Flagship Bottle Controller
(function initParallaxLeaves() {
    const section = document.getElementById('flagship-bottle');
    if (!section) return;

    const leaves = section.querySelectorAll('.parallax-leaf');
    const bottleCard = section.querySelector('.bottle-display-wrapper');
    if (!leaves.length) return;

    let mouseX = 0;
    let mouseY = 0;
    let currentX = 0;
    let currentY = 0;
    let isHoveringSection = false;
    let ticking = false;

    // Track cursor over window & section
    window.addEventListener('mousemove', (e) => {
        const rect = section.getBoundingClientRect();
        
        // Check if cursor is roughly within view distance of the section
        if (rect.top < window.innerHeight && rect.bottom > 0) {
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            mouseX = (e.clientX - centerX) / (rect.width / 2);
            mouseY = (e.clientY - centerY) / (rect.height / 2);
            // Clamp between -1.5 and 1.5
            mouseX = Math.max(-1.5, Math.min(1.5, mouseX));
            mouseY = Math.max(-1.5, Math.min(1.5, mouseY));
        }

        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }, { passive: true });

    // Smooth render loop
    function updateParallax() {
        // Damping / interpolation for silky smooth liquid motion
        currentX += (mouseX - currentX) * 0.08;
        currentY += (mouseY - currentY) * 0.08;

        const rect = section.getBoundingClientRect();

        // Animate outer floating leaves based purely on mouse cursor
        leaves.forEach((leaf) => {
            const depth = parseFloat(leaf.getAttribute('data-depth')) || 0.1;
            const baseRot = parseFloat(leaf.getAttribute('data-rot')) || 0;

            const transX = currentX * depth * 80;
            const transY = currentY * depth * 70;
            const extraRot = currentX * depth * 12;

            leaf.style.transform = `translate3d(${transX.toFixed(2)}px, ${transY.toFixed(2)}px, 0) rotate(${(baseRot + extraRot).toFixed(1)}deg)`;
        });

        // Subtle 3D bottle perspective tilt
        if (bottleCard) {
            const tiltX = -currentY * 5;
            const tiltY = currentX * 6;
            bottleCard.style.transform = `perspective(1000px) rotateX(${tiltX.toFixed(2)}deg) rotateY(${tiltY.toFixed(2)}deg)`;
        }

        // Keep animating while difference is noticeable
        if (Math.abs(mouseX - currentX) > 0.002 || Math.abs(mouseY - currentY) > 0.002) {
            requestAnimationFrame(updateParallax);
        } else {
            ticking = false;
        }
    }

    // Scroll listener for dynamic scroll parallax
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }, { passive: true });

    // Trigger initial frame
    requestAnimationFrame(updateParallax);
})();

// ==========================================
// FLAGSHIP PRODUCT SCROLL SCRUB CONTROLLER
// ==========================================
const scrubSlides = [
    {
        name: 'Cold Pressed Coconut Oil Heritage Bottle (1L)',
        script: 'The Signature Flagship',
        title: 'Cold Pressed<br><span class="font-bold text-[#611B10]">Coconut Oil</span>',
        subtitle: 'Maximum purity in every drop',
        desc: 'Single-press cold expeller milled from sun-dried Kuttiyadi copra at Kozhikode. Pure aroma, zero chemicals, and natural coastal goodness.',
        priceDisplay: '₹340.00',
        rawPrice: 340,
        image: 'images/flagship_bottle_cutout.png'
    },
    {
        name: 'Easy-Pour Kitchen Spout Pouch (1L)',
        script: 'Kitchen Smart Pouch',
        title: 'Easy-Pour Spout<br><span class="font-bold text-[#611B10]">Refill Pouch</span>',
        subtitle: '100% Pure Kozhikode Milling',
        desc: 'Durable leak-proof spout pouch crafted for daily culinary use. Retains authentic wood-pressed freshness, zero spillage, and eco-smart packaging.',
        priceDisplay: '₹280.00',
        rawPrice: 280,
        image: 'images/product_pouch_cutout.png'
    },
    {
        name: 'Classic Pure Coconut Oil Bottle (1L)',
        script: 'Pure Malabar Classic',
        title: 'Classic Pure<br><span class="font-bold text-[#611B10]">Coconut Oil</span>',
        subtitle: 'Traditional wood expeller cold pressed',
        desc: 'Everyday traditional cold-pressed oil in an ergonomic family bottle with stay-fresh seal. Unrefined, unbleached, and naturally packed with lauric acid.',
        priceDisplay: '₹290.00',
        rawPrice: 290,
        image: 'images/product_bottle_cutout.png'
    }
];

let currentScrubIndex = 0;
let isScrubTransitioning = false;

function setScrubSlide(targetIndex, updateScroll = false) {
    if (targetIndex < 0 || targetIndex >= scrubSlides.length) return;
    if (targetIndex === currentScrubIndex && !updateScroll) return;

    currentScrubIndex = targetIndex;
    const data = scrubSlides[targetIndex];

    // 1. Update Tab Pills
    const tabs = document.querySelectorAll('.scrub-tab');
    tabs.forEach((tab, idx) => {
        if (idx === targetIndex) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    // 2. Transition Images (Cross-fade & Scale via .active class)
    for (let i = 0; i < scrubSlides.length; i++) {
        const img = document.getElementById(`scrub-img-${i}`);
        if (!img) continue;

        if (i === targetIndex) {
            img.classList.add('active');
        } else {
            img.classList.remove('active');
        }
    }

    // 3. Subtle Text Cross-fade
    const scriptEl = document.getElementById('scrub-script');
    const titleEl = document.getElementById('scrub-title');
    const subtitleEl = document.getElementById('scrub-subtitle');
    const descEl = document.getElementById('scrub-desc');
    const priceEl = document.getElementById('scrub-price');

    const textEls = [scriptEl, titleEl, subtitleEl, descEl, priceEl].filter(Boolean);
    
    textEls.forEach(el => {
        el.style.transition = 'opacity 0.18s ease, transform 0.18s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(4px)';
    });

    setTimeout(() => {
        if (scriptEl) scriptEl.innerHTML = data.script;
        if (titleEl) titleEl.innerHTML = `<span>${data.title}</span>`;
        if (subtitleEl) subtitleEl.innerHTML = data.subtitle;
        if (descEl) descEl.innerHTML = data.desc;
        if (priceEl) priceEl.innerText = data.priceDisplay;

        textEls.forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });
    }, 180);
}

// Click Handler for Scrub Tabs
window.scrubToSlide = function(targetIndex) {
    setScrubSlide(targetIndex);
    const section = document.getElementById('flagship-bottle');
    if (section) {
        const rect = section.getBoundingClientRect();
        const currentScrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
        const sectionTop = currentScrollY + rect.top;
        const totalDist = section.offsetHeight - window.innerHeight;

        if (totalDist > 0) {
            const ratios = [0.05, 0.45, 0.85];
            const targetY = sectionTop - 60 + (totalDist * ratios[targetIndex]);
            window.scrollTo({ top: Math.max(0, targetY), behavior: 'smooth' });
        }
    }
};

// Order Button Handler for Flagship Showcase
window.orderCurrentScrubProduct = function(event) {
    const product = scrubSlides[currentScrubIndex];
    if (product) {
        addToCart(product.name, product.rawPrice, product.image, event);
    }
};

// Interactive click on bottle display to advance slide
document.addEventListener('DOMContentLoaded', () => {
    const displayWrapper = document.querySelector('.bottle-display-wrapper');
    if (displayWrapper) {
        displayWrapper.addEventListener('click', () => {
            const nextIndex = (currentScrubIndex + 1) % scrubSlides.length;
            window.scrubToSlide(nextIndex);
        });
    }
});

// Scroll Scrub Listener (Runs on DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function initFlagshipScrollScrub() {
    const section = document.getElementById('flagship-bottle');
    if (!section) return;

    let scrubTicking = false;

    function evaluateScrollScrub() {
        const rect = section.getBoundingClientRect();
        // Distance from top of viewport to section top
        const stickyTop = 72; 
        const totalDist = section.offsetHeight - window.innerHeight;

        if (totalDist <= 0) return;

        // How much of the track has scrolled past the sticky anchor
        const scrolledInside = stickyTop - rect.top;
        const progress = Math.max(0, Math.min(1, scrolledInside / totalDist));

        // 3 steps with comfortable scroll dwell windows:
        // 0% - 33%: Heritage Bottle (Slide 0)
        // 33% - 66%: Kitchen Spout Pouch (Slide 1)
        // 66% - 100%: Classic Pure Bottle (Slide 2)
        let targetIndex = 0;
        if (progress >= 0.65) {
            targetIndex = 2;
        } else if (progress >= 0.32) {
            targetIndex = 1;
        } else {
            targetIndex = 0;
        }

        if (targetIndex !== currentScrubIndex) {
            setScrubSlide(targetIndex);
        }
    }

    window.addEventListener('scroll', () => {
        if (!scrubTicking) {
            window.requestAnimationFrame(() => {
                evaluateScrollScrub();
                scrubTicking = false;
            });
            scrubTicking = true;
        }
    }, { passive: true });

    document.addEventListener('scroll', evaluateScrollScrub, { passive: true });
    window.addEventListener('resize', evaluateScrollScrub);
    setTimeout(evaluateScrollScrub, 100);
});

// ==========================================
// SPECIAL COMBO PACK CAROUSEL LOGIC
// ==========================================
function renderComboCarousel() {
    const slidesContainer = document.getElementById('combo-carousel-slides');
    const dotsContainer = document.getElementById('combo-carousel-dots');
    if (!slidesContainer) return;

    // Render slides
    slidesContainer.innerHTML = comboPacks.map((item, idx) => {
        const isFav = favorites.some(f => f.title === item.name);
        return `
            <div class="w-full shrink-0 px-1">
                <div class="bg-white/10 backdrop-blur-md p-5 rounded-3xl border border-white/20 text-center w-full shadow-2xl relative select-none">
                    <button onclick="toggleFavorite(event, '${item.name.replace(/'/g, "\\'")}', ${item.price}, '${item.image}')" 
                            class="fav-btn-toggle absolute top-3.5 right-3.5 z-10 w-7 h-7 rounded-full bg-white/95 text-sage-400 hover:text-rose-500 shadow-sm flex items-center justify-center transition-transform hover:scale-110 active:scale-95" 
                            title="Add to Favorites" 
                            data-title="${item.name}">
                        <i class="${isFav ? 'fa-solid fa-heart text-rose-500' : 'fa-regular fa-heart'} text-xs"></i>
                    </button>
                    <div class="aspect-square w-full rounded-2xl overflow-hidden mb-3 border border-white/20 bg-sage-900/40">
                        <img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                    </div>
                    <span class="inline-block text-[9px] uppercase tracking-widest font-extrabold text-ambergold-300 bg-white/10 px-2 py-0.5 rounded-full mb-1 border border-white/10">
                        ${item.badge}
                    </span>
                    <h4 class="font-serif font-bold text-base text-white truncate">${item.name}</h4>
                    <p class="text-xs text-sage-200 mt-0.5 line-clamp-1">${item.subtitle}</p>
                    <div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between text-xs">
                        <span class="price-tag line-through text-sage-400">₹${item.oldPrice}.00</span>
                        <span class="price-tag text-ambergold-300 font-bold text-base">₹${item.price}.00</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Render navigation dots
    if (dotsContainer) {
        dotsContainer.innerHTML = comboPacks.map((_, idx) => `
            <button onclick="goToComboSlide(${idx})" 
                    class="combo-dot h-2 rounded-full transition-all duration-300 ${idx === currentComboIndex ? 'w-6 bg-ambergold-400' : 'w-2 bg-white/30 hover:bg-white/60'}" 
                    aria-label="Slide ${idx + 1}">
            </button>
        `).join('');
    }

    updateComboDisplay(currentComboIndex);
}

function updateComboDisplay(index) {
    currentComboIndex = (index + comboPacks.length) % comboPacks.length;
    const item = comboPacks[currentComboIndex];

    // Slide transform
    const slidesContainer = document.getElementById('combo-carousel-slides');
    if (slidesContainer) {
        slidesContainer.style.transform = `translateX(-${currentComboIndex * 100}%)`;
    }

    // Left copy updates with gentle fade
    const titleEl = document.getElementById('combo-title');
    const descEl = document.getElementById('combo-desc');
    const labelEl = document.getElementById('combo-btn-label');
    const priceEl = document.getElementById('combo-btn-price');
    const counterEl = document.getElementById('combo-carousel-counter');

    if (titleEl) {
        titleEl.style.opacity = '0';
        setTimeout(() => {
            titleEl.innerHTML = item.title;
            titleEl.style.opacity = '1';
        }, 150);
    }

    if (descEl) {
        descEl.style.opacity = '0';
        setTimeout(() => {
            descEl.innerHTML = item.desc;
            descEl.style.opacity = '1';
        }, 150);
    }

    if (labelEl) labelEl.textContent = item.btnLabel;
    if (priceEl) priceEl.textContent = `₹${item.price}.00`;
    if (counterEl) {
        const curPad = String(currentComboIndex + 1).padStart(2, '0');
        const totPad = String(comboPacks.length).padStart(2, '0');
        counterEl.textContent = `${curPad} / ${totPad}`;
    }

    // Update dot styles
    const dots = document.querySelectorAll('.combo-dot');
    dots.forEach((dot, idx) => {
        if (idx === currentComboIndex) {
            dot.className = 'combo-dot h-2 rounded-full transition-all duration-300 w-6 bg-ambergold-400';
        } else {
            dot.className = 'combo-dot h-2 rounded-full transition-all duration-300 w-2 bg-white/30 hover:bg-white/60';
        }
    });

    // Sync favorite heart icons on slide
    updateFavoritesUI();
}

function nextComboSlide() {
    updateComboDisplay(currentComboIndex + 1);
    restartComboAutoplay();
}

function prevComboSlide() {
    updateComboDisplay(currentComboIndex - 1);
    restartComboAutoplay();
}

function goToComboSlide(idx) {
    updateComboDisplay(idx);
    restartComboAutoplay();
}

function orderCurrentCombo(event) {
    const item = comboPacks[currentComboIndex];
    if (item) {
        addToCart(item.name, item.price, item.image, event);
    }
}

function pauseComboCarousel() {
    if (comboCarouselTimer) {
        clearInterval(comboCarouselTimer);
        comboCarouselTimer = null;
    }
}

function resumeComboCarousel() {
    pauseComboCarousel();
    comboCarouselTimer = setInterval(() => {
        updateComboDisplay(currentComboIndex + 1);
    }, COMBO_AUTOPLAY_INTERVAL);
}

function restartComboAutoplay() {
    resumeComboCarousel();
}

function initComboCarousel() {
    const section = document.getElementById('bestsellers');
    if (!section) return;

    renderComboCarousel();
    resumeComboCarousel();
}



