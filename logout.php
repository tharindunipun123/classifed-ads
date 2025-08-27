<?php
require_once 'db.php';

// Destroy session and logout
session_destroy();

// Redirect to homepage
header('Location: index.php?logout=1');
exit;
?>