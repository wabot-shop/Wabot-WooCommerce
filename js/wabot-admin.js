jQuery(document).ready(function ($) {
    // Handle Preview Button click
    $(".wabot-preview-button").on("click", function () {
        const key = $(this).data("key");
        alert("Preview template for: " + key); // Replace with modal display logic
    });

    // Handle Test Button click
    $(".wabot-test-button").on("click", function () {
        const key = $(this).data("key");

        // Create and display modal dynamically
        const modalHtml = `
            <div id="wabot-test-modal" class="wabot-modal">
                <div class="wabot-modal-content">
                    <h2>Test Template: ${key}</h2>
                    <label for="test-recipient">Recipient Phone Number:</label>
                    <input type="tel" id="test-recipient" class="intl-tel-input" name="test-recipient" placeholder="Enter phone number">
                    <label for="test-variable">Variable Data:</label>
                    <textarea id="test-variable" name="test-variable" placeholder="Enter dynamic variables"></textarea>
                    <button type="button" id="send-test-message" class="button-primary">Send</button>
                    <button type="button" id="close-modal" class="button">Close</button>
                </div>
            </div>
        `;
        $("body").append(modalHtml);

        // Display the modal
        $("#wabot-test-modal").fadeIn();

            // Initialize intl-tel-input on the phone number field
        const input = document.querySelector("#test-recipient");
        const iti = window.intlTelInput(input, {
        initialCountry: "auto",
        geoIpLookup: function (callback) {
            $.get("https://ipinfo.io", function () {}, "jsonp").always(function (resp) {
                const countryCode = resp && resp.country ? resp.country : "us";
                callback(countryCode);
            });
        },
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        });


        // Close modal
        $("#close-modal").on("click", function () {
            $("#wabot-test-modal").fadeOut(function () {
                $(this).remove();
            });
        });

        // Send Test Button click
        $("#send-test-message").on("click", function () {
           
            const recipient = iti.getNumber(); // Get full number with country code
            const variables = $("#test-variable").val();

                // Validate phone number
                if (!iti.isValidNumber()) {
                const errorMessage = iti.getValidationError(); // Get error code
                let errorText;

                    // Convert error code to user-friendly message
                    switch (errorMessage) {
                        case intlTelInputUtils.validationError.INVALID_COUNTRY_CODE:
                            errorText = "Invalid country code.";
                            break;
                        case intlTelInputUtils.validationError.TOO_SHORT:
                            errorText = "The phone number is too short.";
                            break;
                        case intlTelInputUtils.validationError.TOO_LONG:
                            errorText = "The phone number is too long.";
                            break;
                        case intlTelInputUtils.validationError.NOT_A_NUMBER:
                            errorText = "The input is not a valid number.";
                            break;
                        default:
                            errorText = "Invalid phone number.";
                    }

                    alert(errorText); // Display error message
                    return; // Stop further execution
                }

             alert(`Sending test to ${recipient} with variables: ${variables}`);


             // AJAX request to call the backend function
            $.ajax({
                url: wabotAdmin.ajax_url,
                type: "POST",
                data: {
                    action: "wabot_send_message",
                    to: recipient,
                    template_name: "product", // Replace with your template name
                    template_params: { name: variables },
                },
                success: function (response) {
                    alert(response.message || "Message sent successfully!");
                    $("#wabot-test-modal").fadeOut(function () {
                        $(this).remove();
                    });
                },
                error: function (e) {
                    console.log(e)
                    alert("Error sending the message.");
                },
            });
        
          
        });
        
    });
});
