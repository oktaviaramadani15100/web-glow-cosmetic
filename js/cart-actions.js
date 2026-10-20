document.querySelectorAll(".js-cart-add").forEach((form) => {
    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector(".cart-add-status") || document.createElement("small");

        status.className = "cart-add-status";
        status.setAttribute("role", "status");
        if (!status.isConnected) {
            form.append(status);
        }

        button.disabled = true;
        status.textContent = "Menambahkan ke keranjang...";
        status.classList.remove("is-error");

        try {
            const response = await fetch(form.getAttribute("action"), {
                method: "POST",
                body: new FormData(form),
                headers: { Accept: "application/json" }
            });

            if (!response.headers.get("content-type")?.includes("application/json")) {
                throw new Error(`Server mengirim respons tidak valid (HTTP ${response.status}).`);
            }

            const data = await response.json();

            if (response.status === 401) {
                window.location.href = "login.php";
                return;
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || "Produk gagal ditambahkan ke keranjang.");
            }

            document.querySelectorAll("#cart-count, #mobile-cart-count, .cart-count, .gc-mobile-count")
                .forEach((count) => {
                    count.textContent = data.cart_count;
                    count.hidden = data.cart_count < 1;
                });
            status.textContent = "Produk berhasil ditambahkan ke keranjang.";
        } catch (error) {
            status.textContent = error.message || "Produk gagal ditambahkan ke keranjang.";
            status.classList.add("is-error");
        } finally {
            button.disabled = false;
        }
    });
});
