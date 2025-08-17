<?php
return [
    // Original alert types
    'Overspeed'          => ['template' => 'overspeed_alert_ar',   'priority' => 'high'],
    'Geofence Out'       => ['template' => 'geofence_exit_ar',     'priority' => 'high'],
    'SOS'                => ['template' => 'sos_alert_ar',         'priority' => 'crit'],
    'Fuel (Fill/Theft)'  => ['template' => 'fuel_alert_ar',        'priority' => 'normal'],
    'Ignition ON/OFF'    => ['template' => 'ignition_alert_ar',    'priority' => 'normal'],
    
    // Normalized alert type variants
    'ignition_off'       => ['template' => 'ignition_alert_ar',    'priority' => 'normal'],
    'ignition_on'        => ['template' => 'ignition_alert_ar',    'priority' => 'normal'],
    'geofence_out'       => ['template' => 'geofence_exit_ar',     'priority' => 'high'],
    'overspeed'          => ['template' => 'overspeed_alert_ar',   'priority' => 'high'],
    'sos'                => ['template' => 'sos_alert_ar',         'priority' => 'crit'],
    'fuel_fill_theft'    => ['template' => 'fuel_alert_ar',        'priority' => 'normal'],
];
