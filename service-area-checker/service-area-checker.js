jQuery(document).ready(function($) {
    $('#service-area-form').submit(function(event) {
        event.preventDefault();
        var address = $('#address').val();
        var googleMapApiKey = serviceAreaChecker.googleMapApiKey;
        var googleMapZoneUrl = serviceAreaChecker.googleMapZoneUrl;
        var insideActionUrl = serviceAreaChecker.insideActionUrl;
        var outsideActionUrl = serviceAreaChecker.outsideActionUrl;
        $.ajax({
            url: serviceAreaChecker.ajaxUrl,
            type: 'POST',
            data: {
                action: 'service_area_checker',
                address: address,
                googleMapApiKey: googleMapApiKey,
                googleMapZoneUrl: googleMapZoneUrl,
                insideActionUrl: insideActionUrl,
                outsideActionUrl: outsideActionUrl
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data; 
                } else {
                    $('#result').html("Error: " + response.data); 
                }
            }            
        });
    });
});