<?php
return [
    // Original alert types (Arabic templates - legacy)
    'Overspeed'          => ['template' => 'overspeed_alert_ar',   'priority' => 'high'],
    'Geofence Out'       => ['template' => 'geofence_exit_ar',     'priority' => 'high'],
    'SOS'                => ['template' => 'sos_alert_ar',         'priority' => 'crit'],
    'Fuel (Fill/Theft)'  => ['template' => 'fuel_alert_ar',        'priority' => 'normal'],
    'Ignition ON/OFF'    => ['template' => 'ignition_alert_ar',    'priority' => 'normal'],
    
    // English WhatsApp Templates (Primary)
    'overspeed'          => ['template' => 'overspeed_alert_en',   'priority' => 'high'],
    'ignition_on'        => ['template' => 'ignition_on_alert_en', 'priority' => 'normal'],
    'ignition_off'       => ['template' => 'ignition_off_alert_en','priority' => 'normal'],
    'geofence_out'       => ['template' => 'geofence_exit_en',     'priority' => 'high'],
    'sos'                => ['template' => 'sos_alert_en',         'priority' => 'crit'],
    'fuel_fill_theft'    => ['template' => 'fuel_alert_en',        'priority' => 'normal'],
    
    // Additional normalized variants for backward compatibility
    'Ignition On'        => ['template' => 'ignition_on_alert_en', 'priority' => 'normal'],
    'Ignition Off'       => ['template' => 'ignition_off_alert_en','priority' => 'normal'],
    'ignition_alert'     => ['template' => 'ignition_on_alert_en', 'priority' => 'normal'],
    // Add this for testing your Infobip template
   'test_infobip_hsm' => ['template' => 'infobip_test_hsm_2', 'priority' => 'high'],
    ];
