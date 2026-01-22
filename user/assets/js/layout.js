// Shared navbar interactions
(function () {
    const profileDropdown = document.getElementById('profileDropdown');
    const mobileNav = document.getElementById('mobileNav');
    const hamburger = document.querySelector('.hamburger');

    function toggleDropdown() {
        if (profileDropdown) {
            profileDropdown.classList.toggle('active');
        }
    }

    function closeDropdown(event) {
        if (event) event.preventDefault();
        if (profileDropdown) {
            profileDropdown.classList.remove('active');
        }
    }

    function confirmLogout(event) {
        if (event) event.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }

    function toggleMobileNav() {
        if (mobileNav) {
            mobileNav.classList.toggle('active');
        }
    }

    document.addEventListener('click', (event) => {
        if (profileDropdown && !event.target.closest('.profile-container')) {
            profileDropdown.classList.remove('active');
        }
        if (mobileNav && !event.target.closest('.hamburger')) {
            mobileNav.classList.remove('active');
        }
    });

    // expose globally for inline handlers
    window.toggleDropdown = toggleDropdown;
    window.closeDropdown = closeDropdown;
    window.confirmLogout = confirmLogout;
    window.toggleMobileNav = toggleMobileNav;
})();

// Professional Form Validation
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        // Add required asterisks to all required field labels
        const requiredInputs = document.querySelectorAll('input[required], select[required], textarea[required]');
        requiredInputs.forEach(function (input) {
            const label = document.querySelector('label[for="' + input.id + '"]');
            if (label && !label.querySelector('.required-star')) {
                const star = document.createElement('span');
                star.className = 'required-star';
                star.textContent = ' *';
                label.appendChild(star);
            }
            // Also check for parent label
            const parentLabel = input.closest('label');
            if (parentLabel && !parentLabel.querySelector('.required-star')) {
                const labelText = parentLabel.childNodes[0];
                if (labelText && labelText.nodeType === Node.TEXT_NODE) {
                    const star = document.createElement('span');
                    star.className = 'required-star';
                    star.textContent = ' *';
                    parentLabel.insertBefore(star, labelText.nextSibling);
                }
            }
        });

        // Custom validation for all forms
        const forms = document.querySelectorAll('form');
        forms.forEach(function (form) {
            // Disable default browser validation UI
            form.setAttribute('novalidate', 'true');

            // Validate on submit
            form.addEventListener('submit', function (e) {
                let isValid = true;
                const inputs = form.querySelectorAll('input, select, textarea');

                inputs.forEach(function (input) {
                    if (!validateField(input)) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    // Focus on first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            });

            // Real-time validation on blur
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function (input) {
                // Real-time validation on blur REMOVED as per user request
                /* 
                input.addEventListener('blur', function () {
                    validateField(input);
                });
                */

                // Clear error on input
                input.addEventListener('input', function () {
                    if (input.classList.contains('is-invalid')) {
                        validateField(input);
                    }
                });
            });
        });

        function validateField(input) {
            const errorContainer = getErrorContainer(input);
            let errorMessage = '';

            // Skip hidden or disabled inputs
            if (input.type === 'hidden' || input.disabled) {
                return true;
            }

            // Required validation
            if (input.hasAttribute('required')) {
                if (input.type === 'checkbox' && !input.checked) {
                    errorMessage = 'This field is required';
                } else if (input.type === 'radio') {
                    const radioGroup = document.querySelectorAll('input[name="' + input.name + '"]');
                    let checked = false;
                    radioGroup.forEach(function (radio) {
                        if (radio.checked) checked = true;
                    });
                    if (!checked) {
                        errorMessage = 'Please select an option';
                    }
                } else if (!input.value.trim()) {
                    errorMessage = getRequiredMessage(input);
                }
            }

            // Only validate further if has value
            if (!errorMessage && input.value.trim()) {
                // Email validation
                if (input.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        errorMessage = 'Please enter a valid email address';
                    }
                }

                // Phone validation
                if (input.type === 'tel' || input.name === 'phone' || input.id === 'phone') {
                    const phoneRegex = /^[0-9]{10,15}$/;
                    const cleanPhone = input.value.replace(/[\s\-\(\)]/g, '');
                    if (!phoneRegex.test(cleanPhone)) {
                        errorMessage = 'Please enter a valid phone number (10-15 digits)';
                    }
                }

                // Min length
                if (input.hasAttribute('minlength')) {
                    const minLength = parseInt(input.getAttribute('minlength'));
                    if (input.value.length < minLength) {
                        errorMessage = 'Minimum ' + minLength + ' characters required';
                    }
                }

                // Max length
                if (input.hasAttribute('maxlength')) {
                    const maxLength = parseInt(input.getAttribute('maxlength'));
                    if (input.value.length > maxLength) {
                        errorMessage = 'Maximum ' + maxLength + ' characters allowed';
                    }
                }

                // Pattern validation
                if (input.hasAttribute('pattern')) {
                    const pattern = new RegExp(input.getAttribute('pattern'));
                    if (!pattern.test(input.value)) {
                        errorMessage = input.getAttribute('data-error') || 'Please enter a valid format';
                    }
                }

                // Number min/max
                if (input.type === 'number') {
                    const value = parseFloat(input.value);
                    if (input.hasAttribute('min') && value < parseFloat(input.getAttribute('min'))) {
                        errorMessage = 'Minimum value is ' + input.getAttribute('min');
                    }
                    if (input.hasAttribute('max') && value > parseFloat(input.getAttribute('max'))) {
                        errorMessage = 'Maximum value is ' + input.getAttribute('max');
                    }
                }
            }

            // Show/hide error
            if (errorMessage) {
                showError(input, errorContainer, errorMessage);
                return false;
            } else {
                hideError(input, errorContainer);
                return true;
            }
        }

        function getRequiredMessage(input) {
            const label = document.querySelector('label[for="' + input.id + '"]');
            const fieldName = label ? label.textContent.replace(' *', '').trim() : 'This field';

            if (input.type === 'email') return 'Email address is required';
            if (input.type === 'password') return 'Password is required';
            if (input.name === 'name' || input.id === 'name') return 'Name is required';
            if (input.name === 'phone' || input.id === 'phone') return 'Phone number is required';
            if (input.tagName === 'SELECT') return 'Please select an option';

            return fieldName + ' is required';
        }

        function getErrorContainer(input) {
            let errorContainer = input.parentNode.querySelector('.validation-error');
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'validation-error';
                input.parentNode.appendChild(errorContainer);
            }
            return errorContainer;
        }

        function showError(input, container, message) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            container.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
            container.style.display = 'block';

            // Auto-dismiss after 3 seconds
            setTimeout(function () {
                hideError(input, container);
            }, 3000);
        }

        function hideError(input, container) {
            input.classList.remove('is-invalid');
            if (input.value.trim()) {
                input.classList.add('is-valid');
            }
            container.style.display = 'none';
            container.innerHTML = '';
        }

        // Auto-dismiss alerts after 3.5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alert) {
            // Check if it's a dismissible alert and not a permanent one (like 'Already Booked')
            // We'll auto-dismiss success and standard error alerts
            if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                if (!alert.classList.contains('no-auto-dismiss')) { // Optional class to prevent auto-dismiss if needed
                    setTimeout(function () {
                        alert.classList.remove('show');
                        alert.classList.add('fade');
                        setTimeout(function () {
                            alert.style.display = 'none';
                        }, 150); // Wait for fade transition
                    }, 3000);
                }
            }
        });
    });
})();
