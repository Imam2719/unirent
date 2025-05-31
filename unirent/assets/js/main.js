document.addEventListener("DOMContentLoaded", () => {
  // Mobile menu toggle
  const mobileMenuToggle = document.querySelector(".mobile-menu-toggle")
  const mobileMenu = document.querySelector(".mobile-menu")

  if (mobileMenuToggle && mobileMenu) {
    mobileMenuToggle.addEventListener("click", () => {
      mobileMenu.classList.toggle("active")
      document.body.classList.toggle("menu-open")
    })
  }

  // Date picker initialization for rental forms
  const dateInputs = document.querySelectorAll(".date-picker")
  if (dateInputs.length > 0) {
    dateInputs.forEach((input) => {
      // This is a placeholder for a date picker library
      // You would typically use something like flatpickr or datepicker.js
      input.setAttribute("type", "date")
    })
  }

  // Equipment image gallery
  const mainImage = document.querySelector(".equipment-main-image img")
  const thumbnails = document.querySelectorAll(".equipment-thumbnail")

  if (mainImage && thumbnails.length > 0) {
    thumbnails.forEach((thumbnail) => {
      thumbnail.addEventListener("click", function () {
        const src = this.getAttribute("data-src")
        mainImage.setAttribute("src", src)

        // Remove active class from all thumbnails
        thumbnails.forEach((thumb) => thumb.classList.remove("active"))

        // Add active class to clicked thumbnail
        this.classList.add("active")
      })
    })
  }

  // Form validation
  const forms = document.querySelectorAll("form.validate")

  if (forms.length > 0) {
    forms.forEach((form) => {
      form.addEventListener("submit", (e) => {
        let isValid = true
        const requiredFields = form.querySelectorAll("[required]")

        requiredFields.forEach((field) => {
          if (!field.value.trim()) {
            isValid = false
            field.classList.add("error")

            // Add error message if it doesn't exist
            let errorMessage = field.nextElementSibling
            if (!errorMessage || !errorMessage.classList.contains("error-message")) {
              errorMessage = document.createElement("div")
              errorMessage.classList.add("error-message")
              errorMessage.textContent = "This field is required"
              field.parentNode.insertBefore(errorMessage, field.nextSibling)
            }
          } else {
            field.classList.remove("error")

            // Remove error message if it exists
            const errorMessage = field.nextElementSibling
            if (errorMessage && errorMessage.classList.contains("error-message")) {
              errorMessage.remove()
            }
          }
        })

        if (!isValid) {
          e.preventDefault()
        }
      })
    })
  }

  // Notification dismissal
  const notifications = document.querySelectorAll(".notification")

  if (notifications.length > 0) {
    notifications.forEach((notification) => {
      const closeBtn = notification.querySelector(".notification-close")

      if (closeBtn) {
        closeBtn.addEventListener("click", () => {
          notification.classList.add("fade-out")

          setTimeout(() => {
            notification.remove()
          }, 300)
        })
      }

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        notification.classList.add("fade-out")

        setTimeout(() => {
          notification.remove()
        }, 300)
      }, 5000)
    })
  }
})


// Image preview functionality
document.addEventListener('DOMContentLoaded', function() {
  const imageInput = document.getElementById('image');

  if (imageInput) {
    const imagePreview = document.createElement('div');
    imagePreview.className = 'image-preview';
    imageInput.parentNode.appendChild(imagePreview);

    imageInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
          const img = document.createElement('img');
          img.src = e.target.result;

          while (imagePreview.firstChild) {
            imagePreview.removeChild(imagePreview.firstChild);
          }

          imagePreview.appendChild(img);
          imagePreview.classList.add('visible');
        }

        reader.readAsDataURL(this.files[0]);
      } else {
        imagePreview.classList.remove('visible');
      }
    });
  }
});
