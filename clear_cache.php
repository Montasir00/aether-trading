<?php
require 'config.php';
$conn->query('DELETE FROM fear_greed_index_cache;');
echo "Cache cleared.";
?>
