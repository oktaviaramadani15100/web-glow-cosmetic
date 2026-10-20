function continuePayment() {

    const selected =
        document.querySelector(
            'input[name="shipping"]:checked'
        );

    if (!selected) {

        alert("Silakan pilih metode pengiriman.");

        return;
    }


    const shippingCost =
        Number(selected.value);


    const shippingName =
        selected.closest(".shipping-option")
            .querySelector("strong")
            .textContent;


    localStorage.setItem(
        "shippingCost",
        shippingCost
    );

    localStorage.setItem(
        "shippingName",
        shippingName
    );


    window.location.href =
        "pembayaran.html";
}

function openAddressModal() {

    document
        .getElementById("addressModal")
        .classList.add("show");

}


function closeAddressModal() {

    document
        .getElementById("addressModal")
        .classList.remove("show");

}


function saveAddress() {

    const name =
        document.getElementById("addressName").value.trim();

    const address =
        document.getElementById("addressDetail").value.trim();

    const phone =
        document.getElementById("addressPhone").value.trim();


    if (!name || !address || !phone) {

        alert("Semua data alamat harus diisi.");

        return;

    }


    // Ubah isi card alamat

    document.querySelector(
        ".address-content strong"
    ).textContent = name;


    const addressText =
        document.querySelectorAll(
            ".address-content p"
        );

    addressText[0].textContent = address;

    addressText[1].textContent = phone;


    closeAddressModal();

    

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