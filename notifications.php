<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pageTitle = 'Notifications';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Mark all as read
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="page-container" style="max-width:700px">
  <div class="section-header mt-3">
    <div class="section-title">Notifications</div>
    <span class="text-muted text-sm"><?= is_array($notifs)? count($notifs):0 ?> total</span>
  </div>

  <?php if (empty($notifs)): ?>
    <div class="card text-center" style="padding:3rem">
      <div style="font-size:3rem">🔔</div>
      <h3 class="mt-2">No notifications</h3>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:.75rem">
      <?php foreach ($notifs as $n): ?>
        <div class="card" style="border-left:3px solid <?= $n['is_read']?'var(--border)':'var(--gold)' ?>">
          <div class="flex justify-between items-center mb-1">
            <strong><?= s($n['title']) ?></strong>
            <span class="text-xs text-muted"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></span>
          </div>
          <p class="text-muted text-sm"><?= s($n['message']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
