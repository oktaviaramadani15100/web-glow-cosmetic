const cart =
    JSON.parse(localStorage.getItem("cart")) || [];

const shippingCost =
    Number(localStorage.getItem("shippingCost")) || 15000;


function formatRupiah(number) {

    return "Rp " +
        number.toLocaleString("id-ID");

}


const subtotal =
    cart.reduce(
        (total, item) =>
            total + (item.price * item.quantity),
        0
    );


const total =
    subtotal + shippingCost;


document.getElementById(
    "payment-total"
).textContent =
    formatRupiah(total);



function processPayment() {

    const selectedPayment =
        document.querySelector(
            'input[name="payment"]:checked'
        );


    // Belum memilih metode pembayaran
    if (!selectedPayment) {

        Swal.fire({

            icon: "warning",

            title: "Pilih Metode Pembayaran",

            text: "Silakan pilih metode pembayaran terlebih dahulu.",

            confirmButtonText: "OK",

            confirmButtonColor: "#d94b78"

        });

        return;
    }


    // Ambil metode yang dipilih
    const payment =
        selectedPayment.value;


    // Simpan metode pembayaran
    localStorage.setItem(
        "paymentMethod",
        payment
    );


    // Tampilkan popup sesuai metode
    showPaymentDetail(payment);

}

function showPaymentDetail(payment) {

    const modal =
        document.getElementById("paymentModal");

    const detail =
        document.getElementById("payment-detail");


    const total =
        document.getElementById("payment-total").textContent;


    let content = "";


    /* =====================
       TRANSFER BANK
    ===================== */

    if (payment === "bank") {

        content = `

            <div class="payment-detail-header">

                <div class="payment-detail-icon">

                    <i class="fa-solid fa-building-columns"></i>

                </div>

                <h3>Transfer Bank</h3>

                <p>
                    Silakan transfer sesuai total pembayaran
                </p>

            </div>


            <div class="payment-detail-box">

                <div class="payment-detail-row">

                    <span>Total Pembayaran</span>

                    <strong>${total}</strong>

                </div>


                <div class="payment-detail-row">

                    <span>Bank</span>

                    <strong>BCA</strong>

                </div>


                <div class="payment-number">

                    <strong>
                        1234567890
                    </strong>

                    <button
                        class="copy-number"
                        onclick="copyPaymentNumber('1234567890')">

                        Salin

                    </button>

                </div>

                <p style="font-size:9px;color:#999;margin:8px 0 0;">
                    a.n. Glow.Cosmetic
                </p>

            </div>


            <button
                class="payment-action"
                onclick="paymentSuccess()">

                Saya Sudah Bayar

            </button>

        `;
    }


    /* =====================
       E-WALLET
    ===================== */

    else if (payment === "ewallet") {

        content = `

            <div class="payment-detail-header">

                <div class="payment-detail-icon">

                    <i class="fa-solid fa-wallet"></i>

                </div>

                <h3>E-Wallet</h3>

                <p>
                    Pilih e-wallet yang ingin digunakan
                </p>

            </div>


            <div class="payment-detail-box">

                <div class="payment-detail-row">

                    <span>Total Pembayaran</span>

                    <strong>${total}</strong>

                </div>


                <div class="payment-number">

                    <strong>
                        0812-3456-7890
                    </strong>

                    <button
                        class="copy-number"
                        onclick="copyPaymentNumber('0812-3456-7890')">

                        Salin

                    </button>

                </div>

                <p style="font-size:9px;color:#999;margin:8px 0 0;">
                    OVO / GoPay / DANA / ShopeePay
                </p>

            </div>


            <button
                class="payment-action"
                onclick="paymentSuccess()">

                Saya Sudah Bayar

            </button>

        `;
    }


    /* =====================
       KARTU
    ===================== */

    else if (payment === "card") {

        content = `

            <div class="payment-detail-header">

                <div class="payment-detail-icon">

                    <i class="fa-solid fa-credit-card"></i>

                </div>

                <h3>Kartu Kredit / Debit</h3>

                <p>
                    Masukkan informasi kartu
                </p>

            </div>


            <div class="card-form">

                <div>

                    <label>
                        Nomor Kartu
                    </label>

                    <input
                        type="text"
                        placeholder="1234 5678 9012 3456"
                        maxlength="19">

                </div>


                <div>

                    <label>
                        Nama Pemegang Kartu
                    </label>

                    <input
                        type="text"
                        placeholder="Nama pada kartu">

                </div>


                <div class="card-row">

                    <div>

                        <label>
                            Masa Berlaku
                        </label>

                        <input
                            type="text"
                            placeholder="MM/YY">

                    </div>


                    <div>

                        <label>
                            CVV
                        </label>

                        <input
                            type="password"
                            placeholder="123"
                            maxlength="3">

                    </div>

                </div>

            </div>


            <button
                class="payment-action"
                onclick="paymentSuccess()">

                Bayar ${total}

            </button>

        `;
    }


    /* =====================
       COD
    ===================== */

    else if (payment === "cod") {

        content = `

            <div class="payment-detail-header">

                <div class="payment-detail-icon">

                    <i class="fa-solid fa-money-bill-wave"></i>

                </div>

                <h3>COD</h3>

                <p>
                    Bayar saat pesanan diterima
                </p>

            </div>


            <div class="payment-detail-box">

                <div class="payment-detail-row">

                    <span>Total Pembayaran</span>

                    <strong>${total}</strong>

                </div>


                <p style="
                    font-size:10px;
                    color:#888;
                    line-height:1.6;
                    margin:10px 0 0;
                ">

                    Pastikan kamu menyiapkan uang sesuai
                    dengan total pembayaran ketika pesanan
                    sampai.

                </p>

            </div>


            <button
                class="payment-action"
                onclick="paymentSuccess()">

                Konfirmasi Pesanan

            </button>

        `;
    }


    /* =====================
       QRIS
    ===================== */

    else if (payment === "qris") {

        content = `

        <div class="payment-detail-header">

            <div class="payment-detail-icon">
                <i class="fa-solid fa-qrcode"></i>
            </div>

            <h3>Bayar dengan QRIS</h3>

            <p>
                Scan QR menggunakan aplikasi pembayaran
            </p>

        </div>


        <div class="qris-container">

            <strong style="font-size:12px;color:#444;">
                Total ${total}
            </strong>

            <div id="qris-code"></div>

            <p>
                Scan QR menggunakan GoPay, OVO, DANA,
                ShopeePay, mobile banking, atau aplikasi
                pembayaran yang mendukung QRIS.
            </p>

        </div>


        <button
            class="payment-action"
            onclick="paymentSuccess()">

            Saya Sudah Bayar

        </button>

    `;
    }


    detail.innerHTML = content;

    modal.classList.add("show");


    if (payment === "qris") {

        new QRCode(
            document.getElementById("qris-code"),
            {
                text: "GLOWCOSMETIC-PEMBAYARAN-001",
                width: 210,
                height: 210,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            }
        );

    }
}

function closePaymentModal() {

    document
        .getElementById("paymentModal")
        .classList.remove("show");

}

function paymentSuccess() {

    const cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    if (cart.length === 0) {

        Swal.fire({
            icon: "warning",
            title: "Pesanan Tidak Ditemukan",
            text: "Keranjang kamu kosong.",
            confirmButtonColor: "#d94b78"
        });

        return;

    }


    const paymentMethod =
        localStorage.getItem("paymentMethod") ||
        "qris";


    const shippingCost =
        Number(
            localStorage.getItem("shippingCost")
        ) || 15000;


    const shippingName =
        localStorage.getItem("shippingName") ||
        "Kurir";


    const subtotal =
        cart.reduce(
            function (total, item) {

                return total +
                    (
                        Number(item.price) *
                        Number(item.quantity)
                    );

            },
            0
        );


    const total =
        subtotal +
        shippingCost;


    /* BUAT NOMOR PESANAN */

    const orderId =
        "GLW" +
        Date.now()
            .toString()
            .slice(-6);


    /* DATA PESANAN */

    const order = {

        id: orderId,

        date:
            new Date()
                .toLocaleDateString(
                    "id-ID",
                    {
                        day: "2-digit",
                        month: "short",
                        year: "numeric"
                    }
                ),

        createdAt:
            new Date().toISOString(),

        status: "diproses",

        items: cart,

        subtotal: subtotal,

        shippingCost: shippingCost,

        shippingName: shippingName,

        total: total,

        paymentMethod: paymentMethod,

        trackingNumber: ""

    };


    /* AMBIL ORDER LAMA */

    const orders =
        JSON.parse(
            localStorage.getItem("orders")
        ) || [];


    /* TAMBAHKAN ORDER BARU */

    orders.push(order);


    localStorage.setItem(
        "orders",
        JSON.stringify(orders)
    );


    /* KOSONGKAN KERANJANG */

    localStorage.removeItem("cart");

    localStorage.removeItem("cartCount");


    /* TUTUP POPUP */

    closePaymentModal();


    /* BERHASIL */

    Swal.fire({

        icon: "success",

        title: "Pembayaran Berhasil! 💕",

        html: `
            <p>Pesanan kamu berhasil dibuat.</p>

            <strong>
                No. Pesanan #${orderId}
            </strong>
        `,

        confirmButtonText:
            "Lihat Pesanan",

        confirmButtonColor:
            "#d94b78",

        allowOutsideClick:
            false

    }).then(function (result) {

        if (result.isConfirmed) {

            window.location.href =
                "akun.html";

        }

    });

}


function copyPaymentNumber(number) {

    navigator.clipboard.writeText(number);


    Swal.fire({

        icon: "success",

        title: "Berhasil Disalin",

        text: "Nomor pembayaran berhasil disalin.",

        timer: 1200,

        showConfirmButton: false

    });

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
