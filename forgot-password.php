<?php
// ============================================================
//  RentX — Forgot Password (forgot-password.php)
//  - Step 1: Enter email
//  - Step 2: Answer security question
//  - Step 3: Reset password
// ============================================================
require_once __DIR__ . '/includes/helpers.php';

// ── State ────────────────────────────────────────────────────
$errors   = [];
$success  = '';
$step     = 'email';      // email | security | reset
$userRole = '';
$user     = null;

// ════════════════════════════════════════════════════════════
//  POST HANDLER
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db     = getDB();

    // ── STEP 1: Verify Email ─────────────────────────────────
    if ($action === 'verify_email') {
        $email = trim($_POST['email'] ?? '');
        $userRole = trim($_POST['role'] ?? 'user');

        if (!$email) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $stmt = $db->prepare("SELECT id, name, email, security_question, role FROM users WHERE email=? AND role=? AND status='active' LIMIT 1");
            $stmt->execute([$email, $userRole]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors['email'] = 'No account found with this email address.';
            } elseif (!$user['security_question']) {
                $errors['email'] = 'This account does not have a security question set up yet. Please contact support.';
            } else {
                // Store in session for next step
                $_SESSION['forgot_user_id'] = $user['id'];
                $_SESSION['forgot_email'] = $user['email'];
                $_SESSION['forgot_role'] = $user['role'];
                $step = 'security';
            }
        }
    }

    // ── STEP 2: Verify Security Answer ──────────────────────
    if ($action === 'verify_security') {
        if (!isset($_SESSION['forgot_user_id'])) {
            $errors['security'] = 'Session expired. Please start again.';
            $step = 'email';
        } else {
            $answer = trim($_POST['security_answer'] ?? '');

            if (!$answer) {
                $errors['security'] = 'Please provide an answer to the security question.';
                $step = 'security';
            } else {
                $stmt = $db->prepare("SELECT security_answer FROM users WHERE id=? LIMIT 1");
                $stmt->execute([$_SESSION['forgot_user_id']]);
                $userData = $stmt->fetch();

                // Verify answer (case-insensitive, trimmed)
                if ($userData && password_verify(strtolower(trim($answer)), $userData['security_answer'])) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $tokenTime = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                    $db->prepare("UPDATE users SET reset_token=?, reset_token_time=? WHERE id=?")
                       ->execute([$token, $tokenTime, $_SESSION['forgot_user_id']]);

                    $_SESSION['reset_token'] = $token;
                    $step = 'reset';
                } else {
                    $errors['security'] = 'Your answer is incorrect. Please try again.';
                    $step = 'security';
                }
            }
        }
    }

    // ── STEP 3: Reset Password ──────────────────────────────
    if ($action === 'reset_password') {
        if (!isset($_SESSION['reset_token'])) {
            $errors['password'] = 'Session expired. Please start again.';
            $step = 'email';
        } else {
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (!$newPass) {
                $errors['password'] = 'Password is required.';
            } elseif (strlen($newPass) < 6) {
                $errors['password'] = 'Password must be at least 6 characters.';
            } elseif ($newPass !== $confirmPass) {
                $errors['confirm'] = 'Passwords do not match.';
            } else {
                // Verify token and user
                $stmt = $db->prepare("SELECT id, reset_token_time FROM users WHERE id=? AND reset_token=? LIMIT 1");
                $stmt->execute([$_SESSION['forgot_user_id'], $_SESSION['reset_token']]);
                $userData = $stmt->fetch();

                if (!$userData) {
                    $errors['password'] = 'Invalid reset token. Please start again.';
                    $step = 'email';
                } else {
                    $tokenTime = strtotime($userData['reset_token_time']);
                    if (time() > $tokenTime) {
                        $errors['password'] = 'Reset link has expired. Please start again.';
                        $step = 'email';
                    } else {
                        // Update password
                        $hash = password_hash($newPass, PASSWORD_BCRYPT);
                        $db->prepare("UPDATE users SET password=?, reset_token=NULL, reset_token_time=NULL WHERE id=?")
                           ->execute([$hash, $_SESSION['forgot_user_id']]);

                        // Clear session
                        unset($_SESSION['forgot_user_id']);
                        unset($_SESSION['forgot_email']);
                        unset($_SESSION['forgot_role']);
                        unset($_SESSION['reset_token']);

                        $success = 'Password reset successfully! You can now log in with your new password.';
                        $step = 'email';
                    }
                }
            }
        }
    }
}

// If session exists, determine current step
if (isset($_SESSION['forgot_user_id'])) {
    if (isset($_SESSION['reset_token'])) {
        $step = 'reset';
    } else {
        $step = 'security';
    }
}

// Get user data for security question display
if ($step === 'security' && isset($_SESSION['forgot_user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name, security_question FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$_SESSION['forgot_user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RentX — Forgot Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/rentx/assets/css/style.css">
<style>
body { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 2rem 1rem; }

.forgot-panel {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  width: 100%; max-width: 420px;
  padding: 2.5rem 2rem;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.forgot-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.6rem;
  letter-spacing: .12em;
  color: var(--text);
  text-align: center;
  margin-bottom: 0.5rem;
}

.forgot-logo span { color: var(--gold); }

.forgot-title {
  font-size: 0.85rem;
  color: var(--text2);
  text-align: center;
  margin-bottom: 1.8rem;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.step-indicator {
  display: flex;
  justify-content: space-between;
  margin-bottom: 1.8rem;
  gap: 0.5rem;
}

.step {
  flex: 1;
  height: 3px;
  background: var(--bg3);
  border-radius: 2px;
  position: relative;
}

.step.active { background: var(--gold); }
.step.completed { background: var(--green); }

.step-label {
  font-size: 0.65rem;
  color: var(--text3);
  text-align: center;
  margin-top: 0.4rem;
  text-transform: uppercase;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.38rem;
  margin-bottom: 1rem;
}

.field label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text2);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.field label .req { color: var(--red); margin-left: 0.15rem; }

.inp {
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: var(--radius);
  padding: 0.72rem 1rem;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.91rem;
  transition: border-color 0.2s;
  width: 100%;
}

.inp:focus { outline: none; border-color: var(--gold); }
.inp.has-err { border-color: var(--red); }
.inp::placeholder { color: var(--text3); }

.ferr {
  font-size: 0.73rem;
  color: var(--red);
  margin-top: 0.1rem;
}

.a-box {
  padding: 0.85rem 1rem;
  border-radius: var(--radius);
  font-size: 0.86rem;
  font-weight: 500;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.a-err { background: rgba(232, 75, 75, 0.1); border: 1px solid rgba(232, 75, 75, 0.35); color: #ff9090; }
.a-ok { background: rgba(75, 232, 122, 0.1); border: 1px solid rgba(75, 232, 122, 0.35); color: var(--green); }

.info-box {
  background: rgba(232, 184, 75, 0.07);
  border: 1px solid rgba(232, 184, 75, 0.2);
  border-radius: var(--radius);
  padding: 0.9rem;
  margin-bottom: 1.2rem;
  font-size: 0.85rem;
  line-height: 1.6;
  color: var(--text);
}

.info-box strong { color: var(--gold); }

.sbtn {
  background: var(--gold);
  color: #000;
  padding: 0.82rem 1.4rem;
  border-radius: var(--radius);
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
  font-size: 0.93rem;
  border: none;
  cursor: pointer;
  transition: background 0.2s;
  width: 100%;
  letter-spacing: 0.02em;
  margin-top: 0.3rem;
}

.sbtn:hover { background: var(--gold2); }

.sbtn.outline {
  background: transparent;
  border: 1.5px solid var(--gold);
  color: var(--gold);
}

.sbtn.outline:hover { background: rgba(232, 184, 75, 0.1); }

.back-link {
  text-align: center;
  margin-top: 1.2rem;
  font-size: 0.8rem;
}

.back-link a {
  color: var(--gold);
  text-decoration: none;
  font-weight: 600;
}

.back-link a:hover { text-decoration: underline; }

.pw-wrap { position: relative; }
.pw-eye {
  position: absolute;
  right: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text3);
  cursor: pointer;
  font-size: 0.82rem;
  padding: 0.2rem;
}
.pw-eye:hover { color: var(--gold); }

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.role-tabs {
  display: flex;
  gap: 0.3rem;
  margin-bottom: 1.2rem;
}

.role-tab {
  flex: 1;
  text-align: center;
  padding: 0.52rem 0.4rem;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text2);
  border: 1px solid var(--border);
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.role-tab.active {
  background: var(--bg3);
  color: var(--gold);
  border-color: var(--gold);
}
</style>
</head>
<body>

<div class="forgot-panel">

  <div class="forgot-logo">RENT<span>X</span></div>
  <div class="forgot-title">Account Recovery</div>

  <?php if ($success): ?>
    <div class="a-box a-ok">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- ════════════════════════════════════
       STEP 1: VERIFY EMAIL
  ═════════════════════════════════ -->
  <?php if ($step === 'email'): ?>

    <form method="POST" class="form-group">
      <input type="hidden" name="action" value="verify_email">

      <div class="role-tabs">
        <button type="button" class="role-tab" id="role-user" onclick="setRole('user', this)">👤 User</button>
        <button type="button" class="role-tab active" id="role-owner" onclick="setRole('owner', this)">🚘 Owner</button>
      </div>
      <input type="hidden" id="role-input" name="role" value="owner">

      <div class="info-box">
        🔐 <strong>Recover your password</strong> by verifying your email address and answering your security question.
      </div>

      <?php if (!empty($errors['email'])): ?>
        <div class="a-box a-err">⚠️ <?= htmlspecialchars($errors['email']) ?></div>
      <?php endif; ?>

      <div class="field">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email"
               class="inp <?= !empty($errors['email']) ? 'has-err' : '' ?>"
               placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        <?php if (!empty($errors['email'])): ?>
          <span class="ferr">⚠ <?= htmlspecialchars($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <button type="submit" class="sbtn">Continue →</button>
    </form>

  <?php endif; ?>

  <!-- ════════════════════════════════════
       STEP 2: ANSWER SECURITY QUESTION
  ═════════════════════════════════ -->
  <?php if ($step === 'security' && $user): ?>

    <form method="POST" class="form-group">
      <input type="hidden" name="action" value="verify_security">

      <div class="info-box">
        ✔️ Email verified for <strong><?= htmlspecialchars($user['name']) ?></strong><br>
        Now answer your security question to proceed.
      </div>

      <?php if (!empty($errors['security'])): ?>
        <div class="a-box a-err">⚠️ <?= htmlspecialchars($errors['security']) ?></div>
      <?php endif; ?>

      <div class="field">
        <label>Security Question <span class="req">*</span></label>
        <div style="background:var(--bg3);border:1px solid var(--border);padding:0.9rem;border-radius:var(--radius);color:var(--gold);font-weight:600;font-size:0.9rem;line-height:1.5">
          <?= htmlspecialchars($user['security_question']) ?>
        </div>
      </div>

      <div class="field">
        <label>Your Answer <span class="req">*</span></label>
        <input type="text" name="security_answer"
               class="inp <?= !empty($errors['security']) ? 'has-err' : '' ?>"
               placeholder="Enter your answer"
               autocomplete="off" required>
        <?php if (!empty($errors['security'])): ?>
          <span class="ferr">⚠ <?= htmlspecialchars($errors['security']) ?></span>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:0.6rem">
        <button type="submit" class="sbtn">Verify Answer →</button>
        <button type="button" class="sbtn outline" style="flex:0.4" onclick="startOver()">Back</button>
      </div>
    </form>

  <?php endif; ?>

  <!-- ════════════════════════════════════
       STEP 3: RESET PASSWORD
  ═════════════════════════════════ -->
  <?php if ($step === 'reset'): ?>

    <form method="POST" class="form-group">
      <input type="hidden" name="action" value="reset_password">

      <div class="info-box">
        ✔️ Security question verified!<br>
        Now create a new password for your account.
      </div>

      <?php if (!empty($errors['password'])): ?>
        <div class="a-box a-err">⚠️ <?= htmlspecialchars($errors['password']) ?></div>
      <?php endif; ?>

      <?php if (!empty($errors['confirm'])): ?>
        <div class="a-box a-err">⚠️ <?= htmlspecialchars($errors['confirm']) ?></div>
      <?php endif; ?>

      <div class="field">
        <label>New Password <span class="req">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="new_password" id="new-pw"
                 class="inp <?= !empty($errors['password']) ? 'has-err' : '' ?>"
                 placeholder="Min 6 characters" required>
          <button type="button" class="pw-eye" onclick="togglePw('new-pw', this)">👁</button>
        </div>
        <?php if (!empty($errors['password'])): ?>
          <span class="ferr">⚠ <?= htmlspecialchars($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Confirm Password <span class="req">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" id="confirm-pw"
                 class="inp <?= !empty($errors['confirm']) ? 'has-err' : '' ?>"
                 placeholder="Repeat password" required>
          <button type="button" class="pw-eye" onclick="togglePw('confirm-pw', this)">👁</button>
        </div>
        <?php if (!empty($errors['confirm'])): ?>
          <span class="ferr">⚠ <?= htmlspecialchars($errors['confirm']) ?></span>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:0.6rem">
        <button type="submit" class="sbtn">Reset Password →</button>
        <button type="button" class="sbtn outline" style="flex:0.4" onclick="startOver()">Cancel</button>
      </div>
    </form>

  <?php endif; ?>

  <div class="back-link">
    <a href="/rentx/index.php">← Back to Login</a>
  </div>

</div>

<script>
function setRole(role, btn) {
  document.getElementById('role-input').value = role;
  document.querySelectorAll('.role-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function togglePw(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  const show = el.type === 'password';
  el.type = show ? 'text' : 'password';
  btn.textContent = show ? '🙈' : '👁';
}

function startOver() {
  // Clear session and go back to email step
  fetch('/rentx/clear-forgot-session.php', { method: 'POST' })
    .then(() => location.href = '/rentx/forgot-password.php');
}
</script>

</body>
</html>
