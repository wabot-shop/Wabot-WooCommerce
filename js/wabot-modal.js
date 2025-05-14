jQuery(document).ready(function($) {

     // Initialize intl-tel-input with improved options to match design
     var input = document.querySelector("#wabot-guest-phone");
     var iti = window.intlTelInput(input, {
         initialCountry: "us",
         separateDialCode: false,
         utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js",
         customContainer: "wabot-phone-container",
         autoPlaceholder: "aggressive",
         preferredCountries: ["us", "gb", "ca", "au"],
         formatOnDisplay: true,
         nationalMode: false
     });

    // Set initial placeholder
    $(input).attr('placeholder', '+1 (234) 567-8910');

    // Show the modal with slight delay for better UX
    if (!getCookie('wabot_guest_info')) {
        setTimeout(function() {
            $('#wabot-email-modal').addClass('visible').css('display', 'flex');
        }, 500);
    }

    // Close modal via X button
    $('#wabot-modal-close').click(function() {
        hideModal();
    });
    
    // Close modal by clicking outside of it
    $(document).on('click', function(e) {
        if ($(e.target).is('#wabot-email-modal')) {
            hideModal();
        }
    });

    // Function to properly hide the modal
    function hideModal() {
        $('#wabot-email-modal').removeClass('visible');
        setTimeout(function() {
            $('#wabot-email-modal').css('display', 'none');
        }, 300); // Match this timing with the CSS transition
    }

    // Submit email and phone with validation
    $('#wabot-email-submit').click(function(e) {
        e.preventDefault();
        
        // Get form values
        var email = $('#wabot-guest-email').val();
        var phoneNumber = iti.getNumber(); // Get full international number
        
        // Clear previous validation errors
        $('.wabot-form-error').remove();
        $('.wabot-form-group').removeClass('has-error');
        
        // Validation flags
        var isValid = true;
        
        // Email validation
        if (!email || !validateEmail(email)) {
            isValid = false;
            $('#wabot-guest-email').parent().addClass('has-error');
            $('#wabot-guest-email').after('<div class="wabot-form-error">Please enter a valid email address.</div>');
        }
        
        // Phone validation
        if (!phoneNumber || !iti.isValidNumber()) {
            isValid = false;
            $('#wabot-guest-phone').closest('.wabot-form-group').addClass('has-error');
            $('#wabot-guest-phone').parent().after('<div class="wabot-form-error">Please enter a valid phone number.</div>');
        }
        
        // If validation passes, proceed with submission
        if (isValid) {
            // Show loading state
            var originalText = $('#wabot-email-submit').text();
            $('#wabot-email-submit').text('Processing...').prop('disabled', true);
            
            $.ajax({
                url: wabot_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'wabot_save_guest_info',
                    email: email,
                    phone: phoneNumber,
                    security: wabot_ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Store values in cookies directly from front-end too
                        setCookie('wabot_guest_info', '1', 30);
                        setCookie('wabot_guest_email', email, 30);
                        setCookie('wabot_guest_phone', phoneNumber, 30);
                        
                        // If cart has items, trigger cart update to save abandoned cart data
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                        
                        hideModal();
                    } else {
                        // Reset button state
                        $('#wabot-email-submit').text(originalText).prop('disabled', false);
                        // Show error message
                        alert('There was an error saving your information. Please try again.');
                    }
                },
                error: function() {
                    // Reset button state
                    $('#wabot-email-submit').text(originalText).prop('disabled', false);
                    alert('Connection error. Please try again later.');
                }
            });
        }
    });

    // Email validation function
    function validateEmail(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    // Helper functions for cookies
    function setCookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
});
