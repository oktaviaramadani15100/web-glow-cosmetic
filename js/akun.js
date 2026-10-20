
document.addEventListener("DOMContentLoaded", function () {

    // Status login dan tampilan akun diatur oleh PHP (akun.php).
    const accountContainer =
        document.getElementById("accountContainer");

    const isLoggedIn =
        accountContainer?.classList.contains("show");

    if (isLoggedIn) {
        updateWishlistCount();
    }

    updateCartCount();

    const logoutButton =
        document.getElementById("logoutButton");

    if (logoutButton) {
        logoutButton.addEventListener("click", function (event) {
            event.preventDefault();

            const confirmLogout = confirm(
                "Apakah kamu yakin ingin logout?"
            );

            if (!confirmLogout) {
                return;
            }

            window.location.href = "../process/logout.php";
        });
    }

});


function loadUserData() {

    const user =
        JSON.parse(
            localStorage.getItem("user")
        ) || {

            name: "Oktavia Ramadani",

            email: "oktavia@gmail.com",

            phone: "0812-3456-7890"

        };


    const nameInput =
        document.getElementById("accountName");


    const emailInput =
        document.getElementById("accountEmail");


    const phoneInput =
        document.getElementById("accountPhone");


    const profileEmail =
        document.getElementById("profileEmail");


    const sidebarName =
        document.getElementById("sidebarName");


    if (nameInput) {
        nameInput.value = user.name;
    }


    if (emailInput) {
        emailInput.value = user.email;
    }


    if (phoneInput) {
        phoneInput.value =
            user.phone || "0812-3456-7890";
    }


    if (sidebarName) {
        sidebarName.textContent =
            user.name;
    }


    if (profileEmail) {
        profileEmail.textContent =
            user.email;
    }

}

function formatRupiah(number) {
    return "Rp " + Number(number || 0).toLocaleString("id-ID");
}

function saveProfile() {

    const name =
        document
            .getElementById("accountName")
            .value
            .trim();


    const email =
        document
            .getElementById("accountEmail")
            .value
            .trim();


    const phone =
        document
            .getElementById("accountPhone")
            .value
            .trim();


    if (!name || !email || !phone) {

        alert(
            "Semua data harus diisi."
        );

        return;

    }


    const user = {

        name: name,

        email: email,

        phone: phone

    };


    localStorage.setItem(
        "user",
        JSON.stringify(user)
    );


    loadUserData();


    alert(
        "Perubahan berhasil disimpan."
    );

}


/* =================================
   LOAD ORDERS
================================= */

function loadOrders(filter = "all") {

    const orderList =
        document.getElementById("ordersList");

    const ordersEmpty =
        document.getElementById("ordersEmpty");

    if (!orderList) {
        return;
    }

    const orderCards =
        orderList.querySelectorAll(".order-card");

    let visibleOrders = 0;

    orderCards.forEach(function (orderCard) {
        const matchesFilter =
            filter === "all" ||
            orderCard.dataset.status === filter;

        orderCard.hidden = !matchesFilter;

        if (matchesFilter) {
            visibleOrders++;
        }
    });

    if (ordersEmpty) {
        ordersEmpty.style.display =
            orderCards.length > 0 && visibleOrders === 0
                ? "flex"
                : "none";
    }

}



/* =================================
   CREATE ORDER CARD
================================= */

function createOrderCard(order) {

    const statusText = {

        diproses: "Diproses",

        dikirim: "Dikirim",

        selesai: "Selesai",

        dibatalkan: "Dibatalkan"

    };


    const products =
        order.items || [];


    let productHTML = "";


    products
        .slice(0, 2)
        .forEach(function (item) {

            productHTML += `

                <img
                    src="${item.image}"
                    alt="${item.name}"
                >

            `;

        });


    if (products.length > 2) {

        productHTML += `

            <div class="order-more">
                +${products.length - 2}
            </div>

        `;

    }


    const itemCount =
        products.reduce(
            function (total, item) {

                return total +
                    Number(item.quantity || 1);

            },
            0
        );


    return `

        <div
            class="order-card"
            data-order-id="${order.id}">

            <div class="order-card-header">

                <div>

                    <div class="order-number">
                        #${order.id}
                    </div>

                    <div class="order-date">
                        ${order.date}
                    </div>

                </div>


                <span
                    class="order-status status-${order.status}">

                    ${statusText[order.status] || "Diproses"}

                </span>

            </div>


            <div class="order-product">

                <div class="order-product-images">

                    ${productHTML}

                </div>


                <div class="order-product-info">

                    <strong>
                        ${products[0]?.name || "Produk Glow.Cosmetic"}
                    </strong>

                    <span>
                        ${itemCount} produk
                    </span>

                </div>

            </div>


            <div class="order-total">

                <span>
                    Total Pesanan
                </span>

                <strong>
                    ${formatRupiah(order.total)}
                </strong>

            </div>


            <div class="order-card-footer">

                <button
                    class="track-button"
                    onclick="trackOrder('${order.id}')">

                    <i class="fa-solid fa-location-dot"></i>

                    Lacak Pesanan

                </button>

            </div>

        </div>

    `;

}


function updateWishlistCount() {

    const count =
        document.getElementById("wishlist-count");

    if (!count) {
        return;
    }

    const wishlistList =
        document.getElementById("wishlistList");

    const wishlistItems =
        wishlistList?.querySelectorAll(".info-card").length || 0;

    count.textContent = String(wishlistItems);
    count.style.display = wishlistItems > 0 ? "flex" : "none";
}

function trackOrder(orderId) {

    const orders =
        JSON.parse(
            localStorage.getItem("orders")
        ) || [];

    const order =
        orders.find(function (item) {

            return String(item.id) === String(orderId);

        });

    if (!order) {

        alert("Data pesanan tidak ditemukan.");

        return;

    }

    const trackingPanel =
        document.getElementById("trackingPanel");

    if (!trackingPanel) {
        return;
    }

    trackingPanel.innerHTML = `

        <div class="tracking-header">

            <h3>
                Lacak Pesanan
            </h3>

            <p>
                No. Pesanan:
                <strong>#${order.id}</strong>
            </p>

        </div>


        <div class="tracking-search">

            <input
                type="text"
                value="${order.id}"
                readonly
            >

            <button onclick="searchTracking()">
                Cari
            </button>

        </div>


        <div class="tracking-order-title">

            Status Pesanan

        </div>


        <div class="timeline">

            ${createTimeline(order)}

        </div>

    `;

    trackingPanel.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });

}



/* =================================
   CREATE TIMELINE
================================= */

function createTimeline(order) {

    const currentStatus =
        order.status || "diproses";


    const timeline = [

        {
            title: "Pesanan Dibuat",
            text: order.date,
            completed: true
        },

        {
            title: "Pesanan Diproses",
            text: "Pesanan sedang dipersiapkan",
            completed:
                currentStatus === "dikirim" ||
                currentStatus === "selesai"
        },

        {
            title: "Dikirim",
            text:
                order.shippingName ||
                "Pesanan diserahkan ke kurir",
            completed:
                currentStatus === "dikirim" ||
                currentStatus === "selesai"
        },

        {
            title: "Dalam Perjalanan",
            text:
                order.trackingNumber
                    ? "Resi: " + order.trackingNumber
                    : "Pesanan sedang dalam perjalanan",
            completed:
                currentStatus === "selesai"
        },

        {
            title: "Selesai",
            text: "Pesanan telah sampai",
            completed:
                currentStatus === "selesai"
        }

    ];


    return timeline
        .map(function (item) {

            return `

                <div class="
                    timeline-item
                    ${item.completed ? "completed" : ""}
                ">

                    <div class="timeline-dot">

                        ${item.completed
                    ? '<i class="fa-solid fa-check"></i>'
                    : ""
                }

                    </div>


                    <h4>
                        ${item.title}
                    </h4>


                    <p>
                        ${item.text}
                    </p>

                </div>

            `;

        })
        .join("");

}



/* =================================
   FILTER STATUS
================================= */

function setOrderFilter(status, button) {

    document
        .querySelectorAll(".order-tab")
        .forEach(function (tab) {

            tab.classList.remove("active");

        });


    button.classList.add("active");


    loadOrders(status);

}



/* =================================
   FILTER PERIODE
================================= */

function filterOrders() {

    const select =
        document.getElementById("orderPeriod");


    if (!select) {
        return;
    }


    const value =
        select.value;


    if (value === "all") {

        loadOrders("all");

        return;

    }


    const days =
        Number(value);


    const now =
        new Date();


    let orders =
        JSON.parse(
            localStorage.getItem("orders")
        ) || [];


    orders =
        orders.filter(function (order) {

            const orderDate =
                new Date(order.createdAt);


            const difference =
                (
                    now - orderDate
                ) /
                (
                    1000 *
                    60 *
                    60 *
                    24
                );


            return difference <= days;

        });


    const orderList =
        document.getElementById("ordersList");


    const ordersEmpty =
        document.getElementById("ordersEmpty");


    orderList.innerHTML = "";


    if (orders.length === 0) {

        if (ordersEmpty) {
            ordersEmpty.style.display = "flex";
        }

        return;

    }


    if (ordersEmpty) {
        ordersEmpty.style.display = "none";
    }


    orders
        .slice()
        .reverse()
        .forEach(function (order) {

            orderList.innerHTML +=
                createOrderCard(order);

        });

}



/* =================================
   SEARCH TRACKING
================================= */

function searchTracking() {

    const input =
        document.querySelector(
            ".tracking-search input"
        );


    if (!input) {
        return;
    }


    const orderId =
        input.value.trim();


    if (orderId) {

        trackOrder(orderId);

    }

}


/* =================================
   CART COUNT
================================= */

function updateCartCount() {

    const cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    const count =
        cart.reduce(
            function (total, item) {

                return total +
                    Number(item.quantity || 0);

            },
            0
        );


    const cartCount =
        document.getElementById(
            "cart-count"
        );


    if (cartCount) {

        cartCount.textContent =
            count;

        cartCount.style.display =
            count > 0
                ? "flex"
                : "none";

    }

}

function removeWishlist(index) {

    let wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];


    wishlist.splice(
        index,
        1
    );


    localStorage.setItem(
        "wishlist",
        JSON.stringify(wishlist)
    );


    loadWishlist();

}



/* =================================
   HAPUS SEMUA WISHLIST
================================= */

function clearWishlist() {

    let wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];


    if (wishlist.length === 0) {
        return;
    }


    const confirmDelete =
        confirm(
            "Hapus semua produk dari wishlist?"
        );


    if (!confirmDelete) {
        return;
    }


    localStorage.removeItem(
        "wishlist"
    );


    loadWishlist();

}

function addWishlistToCart(index) {

    let wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];


    const product =
        wishlist[index];


    if (!product) {
        return;
    }


    let cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    const existingProduct =
        cart.find(function (item) {

            return item.name === product.name;

        });


    if (existingProduct) {

        existingProduct.quantity++;

    } else {

        cart.push({

            name:
                product.name,

            price:
                product.price,

            image:
                product.image,

            quantity: 1

        });

    }


    localStorage.setItem(
        "cart",
        JSON.stringify(cart)
    );


    updateCartCount();


    alert(
        product.name +
        " berhasil ditambahkan ke keranjang."
    );

}

document.addEventListener("DOMContentLoaded", function () {

    if (window.location.hash === "#wishlist") {

        setTimeout(function () {

            showAccountSection("wishlist");

        }, 100);

    }

});

function loadWishlist() {

    const wishlistList =
        document.getElementById("wishlistList");

    if (!wishlistList) {
        return;
    }

    updateWishlistCount();
    return;
}



function openAddressModal(mode = "add", addressId = null) {
    const modal = document.getElementById("addressModal");
    const form = document.getElementById("addressForm");

    if (!modal || !form) return;

    form.reset();

    document.getElementById("addressModalTitle").textContent =
        mode === "edit" ? "Ubah Alamat" : "Tambah Alamat";

    document.getElementById("addressId").value =
        mode === "edit" && addressId ? addressId : "";

    if (mode === "edit") {
        const card = document.querySelector(
            `.info-card[data-address-id="${addressId}"]`
        );

        if (!card) {
            alert("Data alamat tidak ditemukan. Muat ulang halaman.");
            return;
        }

        const data = card.dataset;

        document.getElementById("addressName").value = data.name || "";
        document.getElementById("addressPhone").value = data.phone || "";
        document.getElementById("addressDetail").value = data.address || "";
        document.getElementById("addressCity").value = data.city || "";
        document.getElementById("addressProvince").value = data.province || "";
        document.getElementById("addressPostal").value = data.postal || "";
        document.getElementById("mainAddress").checked = data.default === "1";
    }

    modal.classList.add("show");
}

function closeAddressModal() {
    document.getElementById("addressModal")?.classList.remove("show");
}


function saveAddress() {

    const name =
        document.getElementById("addressName").value.trim();

    const phone =
        document.getElementById("addressPhone").value.trim();

    const detail =
        document.getElementById("addressDetail").value.trim();

    const city =
        document.getElementById("addressCity").value.trim();

    const postal =
        document.getElementById("addressPostal").value.trim();


    if (!name || !phone || !detail || !city || !postal) {

        Swal.fire({
            icon: "warning",
            title: "Data Belum Lengkap",
            text: "Silakan lengkapi semua data alamat.",
            confirmButtonColor: "#df4772"
        });

        return;
    }


    /* Untuk sementara tampilkan alamat baru */

    const addressCard = document.querySelector(
        "#address .info-card"
    );


    addressCard.querySelector(
        ".address-name strong"
    ).textContent = name;


    addressCard.querySelector(
        ".address-detail p"
    ).textContent =
        detail + ", " + city;


    addressCard.querySelector(
        ".address-detail > span"
    ).textContent = phone;


    closeAddressModal();


    Swal.fire({
        icon: "success",
        title: "Alamat Tersimpan",
        text: "Alamat pengiriman berhasil diperbarui.",
        confirmButtonColor: "#df4772"
    });

}


function showTracking(orderId) {
    const orderCard = document.querySelector(
        '#ordersList .order-card'
    );

    const trackingPanel = document.getElementById('trackingPanel');

    if (!trackingPanel) {
        console.error('Elemen trackingPanel tidak ditemukan.');
        return;
    }

    // Cari kartu pesanan berdasarkan ID
    const cards = document.querySelectorAll(
        '#ordersList .order-card'
    );

    let selectedCard = null;

    cards.forEach(function (card) {
        const button = card.querySelector('.btn-track-order');

        if (button) {
            const onclickValue = button.getAttribute('onclick');

            if (onclickValue === `showTracking(${orderId})`) {
                selectedCard = card;
            }
        }
    });

    if (!selectedCard) {
        console.error('Pesanan tidak ditemukan.');
        return;
    }

    const orderNumber = selectedCard.querySelector(
        '.order-number strong'
    )?.textContent.trim() || 'Pesanan';

    const status = selectedCard.querySelector(
        '.order-status'
    )?.textContent.trim() || 'Pending';

    trackingPanel.hidden = false;

    trackingPanel.innerHTML = `
        <div class="tracking-content">
            <div class="tracking-heading">
                <i class="fa-solid fa-location-dot"></i>
                <div>
                    <h3>Lacak Pesanan</h3>
                    <p>${orderNumber}</p>
                </div>
            </div>

            <div class="tracking-status">
                <span>Status pesanan saat ini</span>
                <strong>${status}</strong>
            </div>

            <div class="tracking-timeline">
                <div class="tracking-step active">
                    <span><i class="fa-solid fa-wallet"></i></span>
                    <strong>Menunggu Pembayaran</strong>
                </div>

                <div class="tracking-step">
                    <span><i class="fa-solid fa-box"></i></span>
                    <strong>Diproses</strong>
                </div>

                <div class="tracking-step">
                    <span><i class="fa-solid fa-truck"></i></span>
                    <strong>Dikirim</strong>
                </div>

                <div class="tracking-step">
                    <span><i class="fa-solid fa-check"></i></span>
                    <strong>Selesai</strong>
                </div>
            </div>

            <p class="tracking-note">
                Status ini mengikuti informasi pesanan yang tersedia.
                Perubahan status akan ditampilkan setelah diperbarui oleh penjual.
            </p>

            <button type="button" class="tracking-close"
                onclick="document.getElementById('trackingPanel').hidden = true">
                Tutup
            </button>
        </div>
    `;

    trackingPanel.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}
