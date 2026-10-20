const blogModal = document.getElementById("blogModal");
const blogForm = document.getElementById("blogForm");
const blogTable = document.getElementById("blogTable");
const blogSearch = document.getElementById("searchBlog");
const blogTitleInput = document.getElementById("blogTitle");
const blogContentInput = document.getElementById("blogContent");
const blogStatusInput = document.getElementById("blogStatus");
const blogModalTitle = document.getElementById("blogModalTitle");
const blogModalDescription = document.getElementById("blogModalDescription");
const blogSubmitLabel = document.getElementById("blogSubmitLabel");

let editingBlogRow = null;

function openBlogModal() {
    editingBlogRow = null;
    blogForm.reset();
    blogModalTitle.textContent = "Tambah Blog";
    blogModalDescription.textContent = "Tambahkan artikel baru untuk customer.";
    blogSubmitLabel.textContent = "Simpan Blog";
    blogModal.classList.add("show");
    blogTitleInput.focus();
}

function closeBlogModal() {
    blogModal.classList.remove("show");
    blogForm.reset();
    editingBlogRow = null;
}

function editBlog(button) {
    editingBlogRow = button.closest("tr");
    blogTitleInput.value = editingBlogRow.querySelector("td:nth-child(2) strong").textContent;
    blogContentInput.value = editingBlogRow.dataset.content || "";
    blogStatusInput.value = editingBlogRow.dataset.status || "published";
    blogModalTitle.textContent = "Edit Blog";
    blogModalDescription.textContent = "Perbarui informasi artikel blog.";
    blogSubmitLabel.textContent = "Simpan Perubahan";
    blogModal.classList.add("show");
    blogTitleInput.focus();
}

function deleteBlog(button) {
    const row = button.closest("tr");
    const title = row.querySelector("td:nth-child(2) strong").textContent;

    if (!confirm(`Hapus blog "${title}"?`)) {
        return;
    }

    row.remove();
    renumberBlogRows();
    applyBlogSearch();
}

function formatBlogDate() {
    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    }).format(new Date());
}

function updateBlogStatus(row, status) {
    row.dataset.status = status;
    const badge = row.querySelector(".status");
    badge.className = `status ${status === "published" ? "success" : "warning"}`;
    badge.textContent = status === "published" ? "Published" : "Draft";
}

function createBlogAction(action, iconClass, title) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `table-action ${action}`;
    button.dataset.action = action;
    button.title = title;

    const icon = document.createElement("i");
    icon.className = iconClass;
    button.appendChild(icon);

    return button;
}

function createBlogRow(title, content, status) {
    const row = document.createElement("tr");
    row.dataset.content = content;

    const numberCell = document.createElement("td");
    const titleCell = document.createElement("td");
    const titleElement = document.createElement("strong");
    const authorCell = document.createElement("td");
    const dateCell = document.createElement("td");
    const statusCell = document.createElement("td");
    const statusBadge = document.createElement("span");
    const actionsCell = document.createElement("td");

    titleElement.textContent = title;
    authorCell.textContent = "Admin";
    dateCell.textContent = formatBlogDate();
    statusBadge.className = "status";
    statusCell.appendChild(statusBadge);
    actionsCell.append(
        createBlogAction("edit", "fa-solid fa-pen", "Edit blog"),
        createBlogAction("delete", "fa-solid fa-trash", "Hapus blog")
    );
    titleCell.appendChild(titleElement);
    row.append(numberCell, titleCell, authorCell, dateCell, statusCell, actionsCell);
    updateBlogStatus(row, status);

    return row;
}

function renumberBlogRows() {
    blogTable.querySelectorAll("tr").forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}

function applyBlogSearch() {
    const keyword = blogSearch.value.toLowerCase().trim();

    blogTable.querySelectorAll("tr").forEach((row) => {
        row.hidden = !row.textContent.toLowerCase().includes(keyword);
    });
}

blogTable.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");

    if (!button) {
        return;
    }

    if (button.dataset.action === "edit") {
        editBlog(button);
    } else if (button.dataset.action === "delete") {
        deleteBlog(button);
    }
});

blogSearch.addEventListener("input", applyBlogSearch);

blogForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const title = blogTitleInput.value.trim();
    const content = blogContentInput.value.trim();
    const status = blogStatusInput.value;

    if (!title || !content) {
        return;
    }

    if (editingBlogRow) {
        editingBlogRow.querySelector("td:nth-child(2) strong").textContent = title;
        editingBlogRow.dataset.content = content;
        editingBlogRow.cells[3].textContent = formatBlogDate();
        updateBlogStatus(editingBlogRow, status);
    } else {
        blogTable.appendChild(createBlogRow(title, content, status));
        renumberBlogRows();
    }

    closeBlogModal();
    applyBlogSearch();
});

blogModal.addEventListener("click", (event) => {
    if (event.target === blogModal) {
        closeBlogModal();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && blogModal.classList.contains("show")) {
        closeBlogModal();
    }
});