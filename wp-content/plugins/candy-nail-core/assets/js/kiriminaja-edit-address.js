(function ($) {
    "use strict";

    $(document).on(
        "select2:select",
        "#billing_kiriof_destination_area",
        function (event) {

            var selected = event.params && event.params.data
                ? event.params.data
                : {};

            var text = String(selected.text || "").trim();

            if (!text) {
                return;
            }

            var parts = text.split(",").map(function (part) {
                return $.trim(part);
            });

            if (parts.length < 5) {
                return;
            }

            var city = parts[2];
            var province = parts[3];
            var postcode = parts[4];

            // City
            $("#billing_city")
                .val(city)
                .trigger("input")
                .trigger("change");

            // Province
            var $state = $("#billing_state");

            $state.find("option").each(function () {

                if (
                    $.trim($(this).text()).toLowerCase() ===
                    province.toLowerCase()
                ) {

                    $state
                        .val($(this).val())
                        .trigger("change");

                    return false;
                }
            });

            // Postcode
            $("#billing_postcode")
                .val(postcode)
                .trigger("input")
                .trigger("change");
        }
    );

})(jQuery);