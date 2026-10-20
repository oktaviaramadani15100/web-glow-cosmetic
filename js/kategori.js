
const modal = document.getElementById("categoryModal");
const form = document.getElementById("categoryForm");

const idInput = document.getElementById("categoryId");
const nameInput = document.getElementById("categoryName");
const descriptionInput = document.getElementById("categoryDescription");
const statusInput = document.getElementById("categoryStatus");
const title = document.getElementById("modalTitle");

function openCategoryModal() {
    form.reset();
    idInput.value = "";
    statusInput.value = "active";
    title.textContent = "Tambah Kategori";
    modal.classList.add("show");
    nameInput.focus();
}

function closeCategoryModal() {
    modal.classList.remove("show");
}

function editCategory(button) {
    const row = button.closest("tr");

    idInput.value = row.dataset.id || "";
    nameInput.value = row.dataset.name || "";
    descriptionInput.value = row.dataset.description || "";
    statusInput.value =
        (row.dataset.status || "").toLowerCase() === "aktif"
            ? "active"
            : "inactive";

    title.textContent = "Edit Kategori";
    modal.classList.add("show");
    nameInput.focus();
}

async function kirimData(data) {
    try {
        const response = await fetch("../process/kategori_process.php", {
            method: "POST",
            body: data,
            redirect: "follow"
        });

        if (!response.ok) {
            throw new Error("Permintaan gagal: " + response.status);
        }

        // PHP mengarahkan kembali ke kategori.php?result=...
        window.location.href = response.url;
    } catch (error) {
        console.error(error);
        alert("Gagal menghubungi server. Periksa koneksi dan file PHP.");
    }
}

form.addEventListener("submit", function (event) {
    event.preventDefault();

    const name = nameInput.value.trim();

    if (!name) {
        alert("Nama kategori wajib diisi.");
        nameInput.focus();
        return;
    }

    const data = new FormData();
    data.append("action", idInput.value ? "update" : "create");
    data.append("id", idInput.value);
    data.append("name", name);
    data.append("description", descriptionInput.value.trim());
    data.append("status", statusInput.value);

    kirimData(data);
});

async function deleteCategory(button) {
    const row = button.closest("tr");
    const id = row.dataset.id;
    const name = row.dataset.name || "kategori ini";

    if (!confirm(`Yakin ingin menghapus kategori "${name}"?`)) {
        return;
    }

    const data = new FormData();
    data.append("action", "delete");
    data.append("id", id);

    await kirimData(data);
}

modal.addEventListener("click", function (event) {
    if (event.target === modal) {
        closeCategoryModal();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeCategoryModal();
    }
});

// Fungsi dipakai oleh onclick di HTML.
window.openCategoryModal = openCategoryModal;
window.closeCategoryModal = closeCategoryModal;
window.editCategory = editCategory;
window.deleteCategory = deleteCategory;

// Pencarian dan filter tabel dari data yang ditampilkan PHP.
function applyFilter() {
    const keyword = document
        .getElementById("searchCategory")
        .value.toLowerCase().trim();

    const selectedStatus =
        document.getElementById("statusFilter").value;

    const rows = document.querySelectorAll(
        "#categoryTableBody tr[data-id]"
    );

    let visible = 0;

    rows.forEach(function (row) {
        const name = (row.dataset.name || "").toLowerCase();
        const status = row.dataset.status || "";

        const cocokNama = name.includes(keyword);
        const cocokStatus =
            selectedStatus === "all" || status === selectedStatus;

        row.style.display = cocokNama && cocokStatus ? "" : "none";

        if (cocokNama && cocokStatus) visible++;
    });

    const empty = document.getElementById("emptyTable");
    if (empty) {
        empty.style.display = visible === 0 ? "block" : "none";
    }
}

document
    .getElementById("searchCategory")
    .addEventListener("input", applyFilter);

document
    .getElementById("statusFilter")
    .addEventListener("change", applyFilter);

// Notifikasi setelah PHP selesai memproses data.
const result = new URLSearchParams(window.location.search).get("result");

const messages = {
    created: ["Berhasil", "Kategori berhasil ditambahkan."],
    updated: ["Berhasil", "Kategori berhasil diperbarui."],
    deleted: ["Berhasil", "Kategori berhasil dihapus."],
    in_use: ["Tidak bisa dihapus", "Kategori masih digunakan oleh produk."],
    not_found: ["Tidak ditemukan", "Kategori tidak ditemukan."],
    invalid: ["Periksa data", "Nama, status, atau ID kategori tidak valid."],
    error: ["Gagal", "Data tidak berhasil diproses. Periksa log PHP dan struktur database."]
};

if (result && messages[result]) {
    const [heading, message] = messages[result];

    if (window.Swal) {
        Swal.fire({
            title: heading,
            text: message,
            icon: ["created", "updated", "deleted"].includes(result)
                ? "success"
                : "error"
        });
    } else {
        alert(heading + "\n" + message);
    }

    window.history.replaceState({}, document.title, window.location.pathname);
}
