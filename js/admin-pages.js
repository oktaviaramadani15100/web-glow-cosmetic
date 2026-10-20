/* =====================================
   SIDEBAR MOBILE
===================================== */

const menuButton = document.getElementById("menuButton");
const sidebar = document.getElementById("sidebar");

if (menuButton) {
    menuButton.addEventListener("click", function () {
        sidebar.classList.toggle("show");
    });
}


/* =====================================
   MODAL
===================================== */

function openModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.add("show");
    }
}


function closeModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.remove("show");
    }
}


/* Klik area luar modal */

document.querySelectorAll(".modal").forEach(function (modal) {

    modal.addEventListener("click", function (event) {

        if (event.target === modal) {
            modal.classList.remove("show");
        }

    });

});


/* =====================================
   TAMBAH USER
===================================== */

function saveUser() {

    const name =
        document.getElementById("userName")?.value.trim();

    const username =
        document.getElementById("username")?.value.trim();

    const role =
        document.getElementById("userRole")?.value;

    if (!name || !username || !role) {

        alert("Silakan lengkapi data user.");

        return;
    }

    alert("User berhasil ditambahkan.");

    closeModal("userModal");

}

let selectedImage = "";

function renderProducts(keyword = "") {
    const tbody = document.getElementById("productTable");
    if (!tbody) return;

    const search = keyword.trim().toLocaleLowerCase("id");
    const rows = [...tbody.querySelectorAll("[data-product-row]")];
    let visibleRows = 0;

    rows.forEach(function (row) {
        const matches = row.textContent.toLocaleLowerCase("id").includes(search);
        row.hidden = !matches;

        if (matches) {
            visibleRows++;
        }
    });

    let emptyRow = tbody.querySelector("[data-search-empty]");

    if (rows.length > 0 && visibleRows === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement("tr");
            emptyRow.dataset.searchEmpty = "";

            const cell = document.createElement("td");
            cell.colSpan = 7;
            cell.style.textAlign = "center";
            cell.style.padding = "25px";
            cell.textContent = "Produk tidak ditemukan.";

            emptyRow.appendChild(cell);
            tbody.appendChild(emptyRow);
        }
    } else {
        emptyRow?.remove();
    }
}


function openProductModal() {
    selectedImage = "";

    document.getElementById("productModalTitle").textContent = "Tambah Produk";
    document.getElementById("productName").value = "";
    document.getElementById("productCategory").value = "";
    document.getElementById("productStock").value = 0;
    document.getElementById("productPrice").value = "";
    document.getElementById("productDescription").value = "";
    document.getElementById("productImage").value = "";

    document.getElementById("imagePreview").style.display = "none";
    document.getElementById("previewImg").src = "";

    document.getElementById("productModal").classList.add("show");
}

function closeModal(modalId) {
    document.getElementById(modalId)?.classList.remove("show");
}

// Preview gambar yang dipilih
document.getElementById("productImage")?.addEventListener("change", function () {
    const file = this.files[0];

    if (!file) return;

    if (!file.type.startsWith("image/")) {
        this.value = "";
        Swal.fire("File tidak valid", "Pilih file gambar.", "warning");
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        this.value = "";
        Swal.fire(
            "Gambar terlalu besar",
            "Pilih gambar maksimal 2 MB.",
            "warning"
        );
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        selectedImage = event.target.result;

        document.getElementById("previewImg").src = selectedImage;
        document.getElementById("imagePreview").style.display = "flex";
    };

    reader.readAsDataURL(file);
});

function removeProductImage() {
    selectedImage = "";
    document.getElementById("productImage").value = "";
    document.getElementById("previewImg").src = "";
    document.getElementById("imagePreview").style.display = "none";
}

document.getElementById("searchProduct")?.addEventListener("input", function () {
    renderProducts(this.value);
});

renderProducts();
