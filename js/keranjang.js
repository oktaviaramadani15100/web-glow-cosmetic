const cartContainer = document.getElementById("cart-container");
const cartStatus = document.getElementById("cart-status");
const csrfTokenInput = document.getElementById("cart-csrf-token");
function formatRupiah(value) {
    return "Rp " + Number(value).toLocaleString("id-ID");
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[character]);
}

function updateCartCount(count) {
    document.querySelectorAll("#cart-count, #mobile-cart-count, .cart-count, .gc-mobile-count")
        .forEach((element) => {
            element.textContent = count;
            element.hidden = count < 1;
        });
}

function showCartMessage(message, isError = false) {
    cartStatus.textContent = message;
    cartStatus.classList.toggle("is-error", isError);
    cartStatus.hidden = !message;
}

const query = new URLSearchParams(window.location.search);
if (query.has("success")) {
    showCartMessage("Keranjang berhasil diperbarui.");
} else if (query.get("error") === "stock") {
    showCartMessage("Jumlah yang diminta melebihi stok produk yang tersedia.", true);
} else if (query.has("error")) {
    showCartMessage("Keranjang gagal diperbarui. Silakan coba lagi.", true);
}

function renderCart(data) {
    csrfTokenInput.value = data.csrf_token;
    updateCartCount(data.cart_count);
    document.getElementById("cart-product-count").textContent =
        `${data.items.length} produk`;

    const checkoutButton = document.getElementById("checkout-button");
    checkoutButton.setAttribute("aria-disabled", String(data.items.length === 0));
    checkoutButton.classList.toggle("is-disabled", data.items.length === 0);
    if (data.items.length === 0) {
        checkoutButton.removeAttribute("href");
    } else {
        checkoutButton.href = "checkout.html";
    }

    if (data.items.length === 0) {
        cartContainer.innerHTML = `
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <h3>Keranjang masih kosong</h3>
                <p>Produk yang kamu pilih akan muncul di sini. Yuk, temukan skincare favoritmu.</p>
                <a href="produk.php">
                    Mulai Belanja
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        `;
    } else {
        cartContainer.innerHTML = data.items.map((item) => {
            const image = item.image
                ? `<img src="../${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}">`
                : `<i class="fa-regular fa-image cart-no-image" aria-hidden="true"></i>`;
            const stockText = item.stock_quantity > 0
                ? `<i class="fa-solid fa-circle-check"></i> Sisa stok ${item.stock_quantity}`
                : `<i class="fa-solid fa-circle-exclamation"></i> Stok habis`;

            return `
                <article class="cart-item">
                    <div class="cart-item-image">${image}</div>
                    <div class="cart-item-info">
                        <div class="cart-item-top">
                            <div>
                                <h3>${escapeHtml(item.name)}</h3>
                                <div class="cart-item-price">${formatRupiah(item.price)}</div>
                                <div class="cart-item-unit">Harga per produk</div>
                            </div>
                            <form class="cart-remove-form" aria-label="Hapus ${escapeHtml(item.name)}">
                                <input type="hidden" name="item_id" value="${item.item_id}">
                                <button type="submit" class="cart-remove-btn" aria-label="Hapus produk">
                                    <i class="fa-regular fa-trash-can"></i>
                                    <span>Hapus</span>
                                </button>
                            </form>
                        </div>
                        <p class="cart-stock ${item.stock_quantity < 1 ? "is-empty" : ""}">
                            ${stockText}
                        </p>
                        <div class="cart-item-bottom">
                        <form class="cart-update-form">
                            <input type="hidden" name="item_id" value="${item.item_id}">
                            <label>
                                Jumlah
                                <input type="number" name="quantity" min="1"
                                    max="${item.stock_quantity}" value="${item.quantity}" required>
                            </label>
                            <button type="submit" class="cart-update-btn">
                                <i class="fa-solid fa-rotate"></i>
                                Perbarui
                            </button>
                        </form>
                        <div class="cart-item-total">
                            <span>Total harga</span>
                            <strong>${formatRupiah(item.price * item.quantity)}</strong>
                        </div>
                        </div>
                    </div>
                </article>
            `;
        }).join("");
    }

    document.getElementById("subtotal").textContent = formatRupiah(data.subtotal);
    document.getElementById("shipping").textContent = formatRupiah(data.shipping);
    document.getElementById("total").textContent = formatRupiah(data.total);
}

async function loadCart() {
    try {
        const response = await fetch("../process/cart_data.php", {
            cache: "no-store",
            headers: { Accept: "application/json" }
        });
        if (!response.headers.get("content-type")?.includes("application/json")) {
            throw new Error(`Keranjang gagal dimuat (HTTP ${response.status}).`);
        }
        const data = await response.json();

        if (response.status === 401) {
            window.location.href = "login.php";
            return;
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Keranjang gagal dimuat.");
        }

        renderCart(data);
    } catch (error) {
        showCartMessage(error.message || "Keranjang gagal dimuat. Silakan coba lagi.", true);
    }
}

async function submitCartAction(form, action) {
    const formData = new FormData(form);
    formData.set("csrf_token", csrfTokenInput.value);
    formData.set("action", action);

    try {
        const response = await fetch("../process/cart_action.php", {
            method: "POST",
            body: formData,
            headers: { Accept: "application/json" }
        });
        if (!response.headers.get("content-type")?.includes("application/json")) {
            throw new Error(`Keranjang gagal diperbarui (HTTP ${response.status}).`);
        }
        const data = await response.json();

        if (response.status === 401) {
            window.location.href = "login.php";
            return;
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Keranjang gagal diperbarui.");
        }

        showCartMessage(data.message);
        await loadCart();
    } catch (error) {
        showCartMessage(error.message || "Keranjang gagal diperbarui. Silakan coba lagi.", true);
    }
}

cartContainer.addEventListener("submit", (event) => {
    const form = event.target;

    if (form.matches(".cart-update-form, .cart-remove-form")) {
        event.preventDefault();
        submitCartAction(form, form.matches(".cart-remove-form") ? "remove" : "update");
    }
});

loadCart();
