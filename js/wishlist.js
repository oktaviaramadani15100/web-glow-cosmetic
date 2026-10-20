function updateWishlistCount() {
    if (typeof window.refreshCustomerHeaderCounts === "function") {
        window.refreshCustomerHeaderCounts();
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", updateWishlistCount);
} else {
    updateWishlistCount();
}

window.addEventListener("storage", function (event) {
    if (event.key === "wishlist" && typeof window.refreshCustomerHeaderCounts === "function") {
        updateWishlistCount();
    }
});