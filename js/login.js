document.querySelectorAll("[data-toggle-password]").forEach((icon) => {
    const toggle = () => {
        const input = document.getElementById(icon.dataset.togglePassword);

        if (!input) {
            return;
        }

        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
        icon.classList.toggle("show", isPassword);
        icon.setAttribute("aria-label", isPassword ? "Sembunyikan password" : "Tampilkan password");
    };

    icon.addEventListener("click", toggle);
    icon.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            toggle();
        }
    });
});