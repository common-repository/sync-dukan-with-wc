(function ($) {
    'use strict';
    $(document).ready(function () {

        $("#disconnectDukan").on('click', function () {
            if(confirm('Are you sure to disconnect the Dukan connection?')) {
                // set the data
                $('#process').fadeIn();
                var data = {
                    action: 'sync_dukan_disconnect',
                    security: ajax_object.nonce
                }

                $.ajax({
                    type: 'post',
                    url: ajax_object.ajaxurl,
                    data: data,
                    success: function (response) {
                        alert('Disconnected successfully.');
                        window.location.reload();

                    },
                    error: function (err) {
                        console.log(err);
                    }
                });

                return false;
            }
            return;
        });

        $("#syncDukan").on('click', function () {
            // set the data
            $('#process').fadeIn();
            var data = {
                action: 'sync_dukan_action',
                security: ajax_object.nonce,
                shopify_token: $("#shopify_token").val(),
                app_key: $("#app_key").val(),
                app_id: $("#app_id").val(),
                dukan_token: $("#dukan_token").val(),
                store_url: $("#store_url").val(),
                store_token: $("#store_token").val(),
                email: $("#email").val(),
            }

            $.ajax({
                type: 'post',
                url: ajax_object.ajaxurl,
                data: data,
                success: function (response) {
                    //output the response on success
                    $("#response").html(response);
                    $('#process').fadeOut();

                },
                error: function (err) {
                    console.log(err);
                }
            });

            return false;
        });

        $("#getProductsFromDukanStore").on('click', function() {

            $('#process').fadeIn();
            var data = {
                action: 'get_dukan_products'
            }

            $.ajax({
                type: 'get',
                url: ajax_object.ajaxurl,
                data: data,
                success: function (response) {
                    //output the response on success
                    $("#response").html(response);
                    $('#process').fadeOut();

                },
                error: function (err) {
                    console.log(err);
                }
            });
        });

        $("#getOrdersFromDukanStore").on('click', function() {

            $('#process').fadeIn();
            var data = {
                action: 'get_dukan_orders'
            }

            $.ajax({
                type: 'get',
                url: ajax_object.ajaxurl,
                data: data,
                success: function (response) {
                    //output the response on success
                    $("#response").html(response);
                    $('#process').fadeOut();

                },
                error: function (err) {
                    console.log(err);
                }
            });
        });
    });
})(jQuery);

