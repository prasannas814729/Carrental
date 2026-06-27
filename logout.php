<?php
session_start();
session_destroy();
header('Location: /rentx/index.php');
exit;
