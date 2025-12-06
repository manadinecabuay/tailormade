// navbar.js
document.addEventListener("DOMContentLoaded", () => {
  const navbarPlaceholder = document.getElementById("navbar-placeholder");

  // Load navbar.html dynamically
  fetch("navbar.html")
    .then(response => response.text())
    .then(data => {
      navbarPlaceholder.innerHTML = data;

      // Select elements after navbar is loaded
      const menuToggle = document.getElementById("menu-toggle");
      const navLinks = document.getElementById("nav-links");
      const links = navLinks.querySelectorAll("a");

      // Highlight active link based on current page
      const currentPage = window.location.pathname.split("/").pop();
      links.forEach(link => {
        if (link.getAttribute("href") === currentPage) {
          link.classList.add("active");
        }
      });

      // Toggle menu on mobile
      menuToggle.addEventListener("click", () => {
        navLinks.classList.toggle("active");
      });
    })
    .catch(error => console.error("Error loading navbar:", error));
});
