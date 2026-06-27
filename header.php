<?php
// includes/header.php
$notifs = isLoggedIn() ? notifCount() : 0;
$user   = currentUser();
$role   = userRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'RentX' ?> — RentX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/rentx/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <a href="/rentx/index.php" class="nav-brand">RENT<span>X</span></a>

  <div class="nav-links">
    <?php if (!isLoggedIn()): ?>
      <a href="/rentx/index.php" class="nav-link">Home</a>
      <a href="/rentx/cars.php" class="nav-link">Browse Cars</a>
    <?php elseif ($role === 'user'): ?>
      <a href="/rentx/user/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/rentx/cars.php" class="nav-link">Browse Cars</a>
      <a href="/rentx/user/my_bookings.php" class="nav-link">My Bookings</a>
    <?php elseif ($role === 'admin'): ?>
      <a href="/rentx/admin/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/rentx/admin/cars.php" class="nav-link">Cars</a>
      <a href="/rentx/admin/bookings.php" class="nav-link">Bookings</a>
      <a href="/rentx/admin/payments.php" class="nav-link">Payments</a>
      <a href="/rentx/admin/users.php" class="nav-link">Users</a>
    <?php elseif ($role === 'owner'): ?>
      <a href="/rentx/owner/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/rentx/owner/my_cars.php" class="nav-link">My Cars</a>
      <a href="/rentx/owner/bookings.php" class="nav-link">Bookings</a>
      <a href="/rentx/owner/revenue.php" class="nav-link">Revenue</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <?php if (isLoggedIn()): ?>
      <a href="/rentx/notifications.php" class="notif-bell">
        🔔 <?php if ($notifs > 0): ?><span class="badge"><?= $notifs ?></span><?php endif; ?>
      </a>
      <div class="nav-user">
        <span class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></span>
        <div class="user-drop">
          <span class="user-name"><?= s($user['name']) ?></span>
          <span class="user-role"><?= strtoupper($role) ?></span>
          <a href="/rentx/profile.php">Profile</a>
          <a href="/rentx/logout.php" class="logout-link">Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a href="/rentx/index.php#login" class="btn-nav">Login</a>
    <?php endif; ?>
  </div>
</nav>

<main class="main-wrap">
<?php showFlash(); ?>
