<?php
// This PHP handles the incoming HTTP POST requests from the SIM800L containing GPS location data and 
// packages it into the GeoJSON file

// Set the directory where geoJSON files are stored
$directory = 'geojson_files/';

// Check if data is received via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the JSON data sent from the Python script
    $json_data = file_get_contents("php://input");
    echo "Received JSON data: $json_data\n";

    // Decode the JSON data into an associative array
    $data = json_decode($json_data, true);

    if ($data !== null) {
        // Process the decoded JSON data
        // You can access individual elements using array keys
        $lon = $data['lon'];
        $lat = $data['lat'];



        // Always write to the same file
        $filename = $directory . 'route.geojson';

        // Check if the file needs to be renamed (i.e., more than 5 minutes have passed since last update)
        if (file_exists($filename)) {
            $lastModifiedTime = filemtime($filename);
            $currentTime = time();
            $elapsedTime = $currentTime - $lastModifiedTime;
            if ($elapsedTime >= 10) { // 5 minutes (300 seconds)
                // Rename the file with a timestamp
                rename($filename, $directory . 'route_' . date('Ymd_His', $lastModifiedTime) . '.geojson');
                echo "Renamed file: $filename\n";
            }
        }
        if (file_exists($filename)) {
            $existing_geojson = file_get_contents($filename);
        }

        // Parse the existing GeoJSON
        $geojson_obj = json_decode($existing_geojson, true);

        // Construct the LineString feature if GeoJSON exists, or create a new one
        if ($geojson_obj !== null && isset($geojson_obj['type']) && $geojson_obj['type'] === 'FeatureCollection' && isset($geojson_obj['features']) && is_array($geojson_obj['features'])) {
            // Get the first (and only) feature in the FeatureCollection
            $feature = $geojson_obj['features'][0];
            // Check if the feature is a LineString
            if (isset($feature['type']) && $feature['type'] === 'Feature' && isset($feature['geometry']) && isset($feature['geometry']['type']) && $feature['geometry']['type'] === 'LineString' && isset($feature['geometry']['coordinates']) && is_array($feature['geometry']['coordinates'])) {
                // Append new coordinates to the LineString
                $feature['geometry']['coordinates'][] = array($lon, $lat);
                // Update the GeoJSON object
                $geojson_obj['features'][0] = $feature;
            }
        } else {
            // Create a new GeoJSON object with a LineString feature
            $geojson_obj = array(
                "type" => "FeatureCollection",
                "features" => array(
                    array(
                        "type" => "Feature",
                        "geometry" => array(
                            "type" => "LineString",
                            "coordinates" => array(
                                array($lon, $lat)
                            )
                        ),
                        "properties" => array(
                            "name" => "Route"
                        )
                    )
                )
            );
        }

        // Encode the updated GeoJSON object
        $updated_geojson = json_encode($geojson_obj);

        // Write the updated GeoJSON to the file
        if (!file_put_contents($filename, $updated_geojson)) {
            echo "Error: Failed to write coordinates to file.";
        } else {
            echo "Successfully updated coordinates in file: $filename\n";
        }

        // Send a success response
        echo "JSON data received and processed successfully";
    } else {
        // Send an error response if JSON data is invalid
        echo "Error: Invalid JSON data received";
    }
} else {
    // Send an error response if request method is not POST
    echo "Error: Only POST requests are allowed";
}
?>
