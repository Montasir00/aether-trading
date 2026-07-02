<?php
require_once '../config.php';
require_once 'alerts_core.php';

// Run the alert checks once (keeps behavior compatible with manual runs)
run_alert_checks($conn);
