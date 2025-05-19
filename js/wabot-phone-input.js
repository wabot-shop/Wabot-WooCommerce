jQuery(document).ready(function($) {
    // Initialize intl-tel-input on all phone number fields
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    const phoneInstances = {};

    phoneInputs.forEach(function(input) {
        if (input.id === 'wabot-guest-phone') return; // Skip wabot modal phone input

        const iti = window.intlTelInput(input, {
            initialCountry: "auto",
            separateDialCode: true,
            utilsScript: wabotPhone.utilsScript,
            geoIpLookup: function(callback) {
                $.get("https://ipinfo.io", function() {}, "jsonp")
                    .always(function(resp) {
                        const countryCode = (resp && resp.country) ? resp.country : "us";
                        callback(countryCode);
                    });
            },
            customContainer: "wabot-phone-container",
            preferredCountries: ["us", "gb", "ca", "au"],
            formatOnDisplay: true
        });

        // Store instance for later use
        phoneInstances[input.id] = iti;

        // Add error message element after the input
        const errorDiv = document.createElement('div');
        errorDiv.className = 'phone-error-message';
        errorDiv.id = input.id + '-error';
        input.parentNode.appendChild(errorDiv);

        // Handle input validation
        input.addEventListener('blur', function() {
            validatePhoneInput(iti, input);
        });

        // Handle form submission
        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!validatePhoneInput(iti, input)) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    // Set the full number with country code as the input value
                    input.value = iti.getNumber();
                }
            });
        }
    });

    // Validation function
    function validatePhoneInput(iti, input) {
        const errorDiv = document.getElementById(input.id + '-error');
        let isValid = true;

        if (!iti.isValidNumber()) {
            const errorCode = iti.getValidationError();
            let errorMsg = 'Invalid phone number.';

            switch(errorCode) {
                case intlTelInputUtils.validationError.INVALID_COUNTRY_CODE:
                    errorMsg = 'Invalid country code.';
                    break;
                case intlTelInputUtils.validationError.TOO_SHORT:
                    errorMsg = 'Phone number is too short.';
                    break;
                case intlTelInputUtils.validationError.TOO_LONG:
                    errorMsg = 'Phone number is too long.';
                    break;
                case intlTelInputUtils.validationError.NOT_A_NUMBER:
                    errorMsg = 'Please enter a valid number.';
                    break;
            }

            input.classList.add('error');
            errorDiv.textContent = errorMsg;
            errorDiv.style.display = 'block';
            isValid = false;
        } else {
            input.classList.remove('error');
            errorDiv.style.display = 'none';
        }

        return isValid;
    }
}); 