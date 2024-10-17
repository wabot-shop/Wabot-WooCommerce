jQuery(document).ready(function($) {

     // Initialize intl-tel-input
     var input = document.querySelector("#wabot-guest-phone");
     var iti = window.intlTelInput(input, {
         initialCountry: "auto",
         geoIpLookup: function(success, failure) {
             $.get('https://ipinfo.io', function() {}, "jsonp").always(function(resp) {
                 var countryCode = (resp && resp.country) ? resp.country : "us";
                 success(countryCode);
             });
         },
         utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js" // for formatting/validation
     });





    // Check if the modal should be displayed
    if ( ! getCookie('wabot_guest_info') ) {
        $('#wabot-email-modal').show();
    }

    // Close modal
    $('#wabot-modal-close').click(function() {
        $('#wabot-email-modal').hide();
    });

     // Submit email and phone
     $('#wabot-email-submit').click(function(e) {
        e.preventDefault();
        var email = $('#wabot-guest-email').val();
        var phoneNumber = iti.getNumber(); // Get full international number

        // Basic validation
        if ( ! email || ! phoneNumber ) {
            alert('Please enter both email and phone number.');
            return;
        }

        if ( ! iti.isValidNumber() ) {
            alert('Please enter a valid phone number.');
            return;
        }

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
                if ( response.success ) {
                    setCookie('wabot_guest_info', '1', 7);
                    $('#wabot-email-modal').hide();
                } else {
                    alert('There was an error saving your information. Please try again.');
                }
            }
        });
    });

    // Helper functions for cookies
    function setCookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime( d.getTime() + ( exdays * 24 * 60 * 60 * 1000 ) );
        var expires = "expires="+ d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent( document.cookie );
        var ca = decodedCookie.split( ';' );
        for( var i = 0; i < ca.length; i++ ) {
            var c = ca[i];
            while ( c.charAt(0) == ' ' ) {
                c = c.substring(1);
            }
            if ( c.indexOf( name ) == 0 ) {
                return c.substring( name.length, c.length );
            }
        }
        return "";
    }
});
