/* =================================
   WISHLIST
================================= */

document.addEventListener("DOMContentLoaded", function () {

    loadWishlistButtons();
    updateWishlistCount();

});

function toggleWishlist(button) {

    const productCard = button.closest(".product-card");

    if (!productCard) {
        return;
    }

    const image = productCard.querySelector(".product-image img");

    const product = {
        name: productCard.dataset.name,

        price: Number(
            productCard.dataset.price
        ),

        image: image
            ? image.getAttribute("src")
            : "",

        category: productCard
            .querySelector(".product-category")
            ?.textContent
            .trim() || "",

        rating: productCard
            .querySelector(".rating")
            ?.textContent
            .trim() || ""
    };


    // Ambil wishlist lama
    let wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];


    // Cek apakah produk sudah ada
    const existingIndex =
        wishlist.findIndex(function (item) {

            return item.name === product.name;

        });


    // ==============================
    // JIKA SUDAH ADA → HAPUS
    // ==============================

    if (existingIndex !== -1) {

        wishlist.splice(
            existingIndex,
            1
        );

        button.classList.remove("active");

        button.innerHTML =
            '<i class="fa-regular fa-heart"></i>';

        showWishlistMessage(
            "Dihapus dari Wishlist"
        );

    }

    // ==============================
    // JIKA BELUM ADA → TAMBAH
    // ==============================

    else {

        wishlist.push(product);

        button.classList.add("active");

        button.innerHTML =
            '<i class="fa-solid fa-heart"></i>';

        showWishlistMessage(
            "Ditambahkan ke Wishlist"
        );

    }


    // SIMPAN KE LOCAL STORAGE
    localStorage.setItem(
        "wishlist",
        JSON.stringify(wishlist)
    );

    updateWishlistCount();


    // DEBUG
    console.log(
        "Wishlist sekarang:",
        wishlist
    );
}

function loadWishlistButtons() {

    const wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];


    document
        .querySelectorAll(".product-card")
        .forEach(function (card) {

            const name =
                card.dataset.name;

            const button =
                card.querySelector(".wishlist");


            if (!button) {
                return;
            }


            const exists =
                wishlist.some(function (item) {

                    return item.name === name;

                });


            if (exists) {

                button.classList.add("active");

                button.innerHTML =
                    '<i class="fa-solid fa-heart"></i>';

            } else {

                button.classList.remove("active");

                button.innerHTML =
                    '<i class="fa-regular fa-heart"></i>';

            }

        });
}

function showWishlistMessage(message) {

    let notification =
        document.getElementById(
            "wishlistNotification"
        );


    if (!notification) {

        notification =
            document.createElement("div");

        notification.id =
            "wishlistNotification";

        document.body.appendChild(
            notification
        );

    }


    notification.innerHTML = `

        <i class="fa-solid fa-heart"></i>

        <span>
            ${message}
        </span>

    `;


    notification.classList.add("show");


    setTimeout(function () {

        notification.classList.remove(
            "show"
        );

    }, 1500);

}

function updateWishlistCount() {

    const wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];

    const count =
        document.getElementById("wishlist-count");

    if (!count) {
        return;
    }

    count.textContent = wishlist.length;

    count.style.display =
        wishlist.length > 0
            ? "flex"
            : "none";
}