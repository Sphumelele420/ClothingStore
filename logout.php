<?php
// logout.php - Simple version
session_start();
session_destroy();
header("Location: login.php");
exit();
?>