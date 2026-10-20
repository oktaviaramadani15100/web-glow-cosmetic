/* =================================
   BLOG SEARCH
================================= */

document.addEventListener("DOMContentLoaded", function () {

    const searchInput =
        document.getElementById("blogSearch");

    const blogList =
        document.getElementById("blogList");

    const blogEmpty =
        document.getElementById("blogEmpty");


    if (!searchInput) {
        return;
    }


    searchInput.addEventListener("input", function () {

        const keyword =
            this.value
                .toLowerCase()
                .trim();


        const cards =
            blogList.querySelectorAll(".blog-card");


        let found = 0;


        cards.forEach(function (card) {

            const title =
                card
                    .querySelector("h2")
                    .textContent
                    .toLowerCase();


            if (title.includes(keyword)) {

                card.style.display = "";

                found++;

            } else {

                card.style.display = "none";

            }

        });


        if (found === 0) {

            blogEmpty.classList.add("show");

        } else {

            blogEmpty.classList.remove("show");

        }

    });

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

});