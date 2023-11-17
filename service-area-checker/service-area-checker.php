<?php
/*
Plugin Name: Service Area Checker
Description: A custom plugin to check whether an address is inside or outside of the service area.
Version: 1.0
Author: Achmad Azman
*/

// Add shortcode for displaying the user input field
add_shortcode( 'service_area_checker', 'service_area_checker_shortcode' );
function service_area_checker_shortcode() {
    ob_start();
    ?>
    <form id="service-area-form">
        <label for="address">Enter your address:</label>
        <input type="text" id="address" name="address" required>
        <button type="submit">Check</button>
    </form>
    <div id="result"></div>
    <?php
    return ob_get_clean();
}

// Enqueue scripts and styles
add_action( 'wp_enqueue_scripts', 'service_area_checker_scripts' );
function service_area_checker_scripts() {
    wp_enqueue_script( 'service-area-checker', plugin_dir_url( __FILE__ ) . 'service-area-checker.js', array( 'jquery' ), '1.0', true );
    wp_localize_script( 'service-area-checker', 'serviceAreaChecker', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'googleMapApiKey' => get_option( 'service_area_checker_google_map_api_key' ),
        'googleMapZoneUrl' => get_option( 'service_area_checker_google_map_zone_url' ),
        'insideActionUrl' => get_option( 'service_area_checker_inside_action_url' ),
        'outsideActionUrl' => get_option( 'service_area_checker_outside_action_url' )
    ) );
}


// Handle AJAX request
add_action( 'wp_ajax_service_area_checker', 'service_area_checker_ajax' );
add_action( 'wp_ajax_nopriv_service_area_checker', 'service_area_checker_ajax' );
function service_area_checker_ajax() {
    $address = $_POST['address'];
    $googleMapApiKey = $_POST['googleMapApiKey'];
    $googleMapZoneUrl = $_POST['googleMapZoneUrl'];
    $insideActionUrl = $_POST['insideActionUrl'];
    $outsideActionUrl = $_POST['outsideActionUrl'];

    // Get latitude and longitude of the address using Google Maps Geocoding API
    $geocodeUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $address ) . '&key=' . $googleMapApiKey;
    $geocodeResponse = wp_remote_get( $geocodeUrl );
    if ( is_wp_error( $geocodeResponse ) ) {
        wp_send_json_error( $geocodeResponse->get_error_message() );
        return;
    }
    $geocodeData = json_decode( wp_remote_retrieve_body( $geocodeResponse ) );
    $latitude = $geocodeData->results[0]->geometry->location->lat;
    $longitude = $geocodeData->results[0]->geometry->location->lng;

    // Check if the latitude and longitude are inside the service area using Google Maps JavaScript API
    $isInside = false;
    $zoneUrl = $googleMapZoneUrl;
    $zoneResponse = wp_remote_get( $zoneUrl );
    if ( is_wp_error( $zoneResponse ) ) {
        wp_send_json_error( $zoneResponse->get_error_message() );
    }
    $zoneData = wp_remote_retrieve_body( $zoneResponse );
    $dom = new DOMDocument();
    $dom->loadXML( $zoneData );
    $coordinates = $dom->getElementsByTagName( 'coordinates' );
    foreach ( $coordinates as $coordinate ) {
        $points = explode( ' ', trim( $coordinate->nodeValue ) );
        $polygon = array();
        foreach ( $points as $point ) {
            $latLng = explode( ',', $point );
                // Make sure there are at least 2 values and they are not both 0
            if (count($latLng) >= 2 && !(floatval($latLng[0]) == 0 && floatval($latLng[1]) == 0)) {
                $polygon[] = array( 
                    'lat' => floatval($latLng[1]), // Latitude
                    'lng' => floatval($latLng[0])  // Longitude
                );
            }
        }
        if ( is_point_in_polygon( $latitude, $longitude, $polygon ) ) {
            $isInside = true;
            break;
        }
    }

    // Send a JSON response instead of redirecting
    if ( $isInside ) {
        wp_send_json_success( $insideActionUrl );
    } else {
        wp_send_json_success( $outsideActionUrl );
    }
    exit;
}

// Helper function to check if a point is inside a polygon
function is_point_in_polygon($latitude, $longitude, $polygon) {
    $number_of_points = count($polygon);
    $inside = false;

    for ($i = 0, $j = $number_of_points - 1; $i < $number_of_points; $j = $i++) {
        $xi = $polygon[$i]['lat']; $yi = $polygon[$i]['lng'];
        $xj = $polygon[$j]['lat']; $yj = $polygon[$j]['lng'];

        $intersect = (($yi > $longitude) != ($yj > $longitude))
            && ($latitude < ($xj - $xi) * ($longitude - $yi) / ($yj - $yi) + $xi);
        if ($intersect) $inside = !$inside;
    }

    return $inside;
}

// Add settings page
add_action( 'admin_menu', 'service_area_checker_settings_page' );
function service_area_checker_settings_page() {
    add_options_page( 'Service Area Checker Settings', 'Service Area Checker', 'manage_options', 'service-area-checker-settings', 'service_area_checker_settings_page_callback' );
}

// Register settings
add_action( 'admin_init', 'service_area_checker_register_settings' );
function service_area_checker_register_settings() {
    register_setting( 'service_area_checker_settings_group', 'service_area_checker_google_map_api_key' );
    register_setting( 'service_area_checker_settings_group', 'service_area_checker_google_map_zone_url' );
    register_setting( 'service_area_checker_settings_group', 'service_area_checker_inside_action_url' );
    register_setting( 'service_area_checker_settings_group', 'service_area_checker_outside_action_url' );
}

// Settings page callback function
function service_area_checker_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1>Service Area Checker Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'service_area_checker_settings_group' ); ?>
            <?php do_settings_sections( 'service_area_checker_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="google-map-api-key">Google Map API Key</label></th>
                    <td><input type="text" id="google-map-api-key" name="service_area_checker_google_map_api_key" value="<?php echo esc_attr( get_option( 'service_area_checker_google_map_api_key' ) ); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google-map-zone-url">Google Map Zone URL</label></th>
                    <td><input type="text" id="google-map-zone-url" name="service_area_checker_google_map_zone_url" value="<?php echo esc_attr( get_option( 'service_area_checker_google_map_zone_url' ) ); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="inside-action-url">Inside Action URL</label></th>
                    <td><input type="text" id="inside-action-url" name="service_area_checker_inside_action_url" value="<?php echo esc_attr( get_option( 'service_area_checker_inside_action_url' ) ); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="outside-action-url">Outside Action URL</label></th>
                    <td><input type="text" id="outside-action-url" name="service_area_checker_outside_action_url" value="<?php echo esc_attr( get_option( 'service_area_checker_outside_action_url' ) ); ?>" required></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}