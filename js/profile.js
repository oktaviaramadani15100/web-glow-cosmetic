function openEditProfile() {

    document
        .getElementById("editProfileModal")
        .classList.add("show");

}


function closeEditProfile() {

    document
        .getElementById("editProfileModal")
        .classList.remove("show");

}


function saveProfile() {

    const name =
        document.getElementById("editName").value.trim();

    if (!name) {

        alert("Nama tidak boleh kosong.");

        return;

    }


    document
        .getElementById("profileName")
        .textContent = name;


    document
        .querySelector(".profile-header-info h2")
        .textContent = name;


    document
        .querySelector(".admin-profile-info strong")
        .textContent = name;


    closeEditProfile();


    alert("Profil berhasil diperbarui.");

}


function openPasswordModal() {

    document
        .getElementById("passwordModal")
        .classList.add("show");

}


function closePasswordModal() {

    document
        .getElementById("passwordModal")
        .classList.remove("show");

}


function savePassword() {

    alert("Password berhasil diperbarui.");

    closePasswordModal();

}


function logoutAdmin() {

    const confirmLogout =
        confirm("Apakah kamu yakin ingin keluar dari akun admin?");


    if (confirmLogout) {

        window.location.href = "../home.html";

    }

}


/* Klik area gelap untuk menutup modal */

document.addEventListener("click", function(event) {

    const editModal =
        document.getElementById("editProfileModal");

    const passwordModal =
        document.getElementById("passwordModal");


    if (event.target === editModal) {

        closeEditProfile();

    }


    if (event.target === passwordModal) {

        closePasswordModal();

    }

});