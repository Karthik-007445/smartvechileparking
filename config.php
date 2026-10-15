<?php
/**
 * ParkSmart configuration.
 * Edit the values below, or set the MONGO_URI / MONGO_DB environment variables.
 *
 * MongoDB Atlas example:
 *   'mongo_uri' => 'mongodb+srv://USER:PASSWORD@cluster0.xxxxx.mongodb.net'
 */
return [
    'app_name'      => 'ParkSmart',
    'tagline'       => 'Smart Vehicle Parking System',

    // --- MongoDB ---------------------------------------------------------
    'mongo_uri'     => getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017',
    'mongo_db'      => getenv('MONGO_DB') ?: 'smart_parking',

    // --- Regional / billing ---------------------------------------------
    'timezone'      => 'Asia/Kolkata',
    'currency'      => '₹',
    'tax_percent'   => 18,          // set 0 to disable tax
    'tax_label'     => 'GST',
    'grace_minutes' => 10,          // free if the vehicle leaves within this time

    // Default hourly rates per vehicle type (used when adding slots)
    'rates'         => ['car' => 40, 'bike' => 20, 'ev' => 50],

    // Leave null for auto-detection. Set e.g. '/smart-parking' if links break.
    'base_path'     => null,

    // Show raw error details on the error page (turn on while debugging)
    'debug'         => false,
];
