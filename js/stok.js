const menuButton = document.getElementById("menuButton");
const sidebar = document.querySelector(".sidebar");

if (menuButton && sidebar) {
    menuButton.addEventListener("click", function () {
        sidebar.classList.toggle("show");
    });
}

const stockModal = document.getElementById("stockModal");
const stockForm = document.getElementById("stockForm");
const modalTitle = document.getElementById("modalTitle");
const productSelect = document.getElementById("productName");
const stockAmount = document.getElementById("stockAmount");
const minimumStock = document.getElementById("minimumStock");
const stockNote = document.getElementById("stockNote");
const searchInput = document.getElementById("searchStock");
const stockFilter = document.getElementById("stockFilter");

let stockMode = "add";

function openStockModal() {
    stockMode = "add";

    stockForm.reset();

    modalTitle.textContent = "Tambah Stok";
    minimumStock.value = 10;

    productSelect.disabled = false;
    stockAmount.disabled = false;

    stockModal.classList.add("show");
}


function editStock(productId, productName, currentStock, currentMinimum) {
    stockMode = "edit";

    stockForm.reset();

    modalTitle.textContent = "Ubah Stok";

    productSelect.value = String(productId);
    stockAmount.value = currentStock;
    minimumStock.value = currentMinimum;

    productSelect.disabled = false;
    stockAmount.disabled = false;

    stockModal.classList.add("show");
}

function closeStockModal() {
    stockModal.classList.remove("show");
}

if (stockModal) {
    stockModal.addEventListener("click", function (event) {
        if (event.target === stockModal) {
            closeStockModal();
        }
    });
}

if (stockForm) {
    stockForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        const productId = productSelect.value;
        const quantity = stockAmount.value;
        const minimum = minimumStock.value;

        if (
            !productId ||
            quantity === "" ||
            minimum === "" ||
            Number(quantity) < 0 ||
            Number(minimum) < 0
        ) {
            alert("Silakan isi produk dan jumlah stok dengan benar.");
            return;
        }

        const submitButton = stockForm.querySelector(
            'button[type="submit"]'
        );

        const originalButtonText = submitButton.innerHTML;

        submitButton.disabled = true;
        submitButton.textContent = "Menyimpan...";

        const formData = new FormData();

        formData.append("action", stockMode);
        formData.append("product_id", productId);
        formData.append("quantity", quantity);
        formData.append("minimum_stock", minimum);

        try {
            const response = await fetch("../process/stok_action.php", {
                method: "POST",
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || "Stok gagal disimpan."
                );
            }

            alert(result.message);

            closeStockModal();

            window.location.reload();

        } catch (error) {
            alert(error.message || "Terjadi kesalahan saat menyimpan stok.");
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    });
}

function applyStockFilters() {
    const keyword = searchInput
        ? searchInput.value.trim().toLowerCase()
        : "";

    const selectedStatus = stockFilter
        ? stockFilter.value
        : "all";

    const rows = document.querySelectorAll("#stockTable tr");

    rows.forEach(function (row) {
        const rowText = row.textContent.toLowerCase();
        const rowStatus = row.dataset.status;

        const matchesSearch = rowText.includes(keyword);

        const matchesStatus =
            selectedStatus === "all" ||
            rowStatus === selectedStatus;

        row.style.display =
            matchesSearch && matchesStatus ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("input", applyStockFilters);
}

if (stockFilter) {
    stockFilter.addEventListener("change", applyStockFilters);
}
