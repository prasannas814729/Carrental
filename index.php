<?php
// ============================================================
//  RentX — Login & Register  (index.php)
//  - Login:    User / Owner / Admin  (email + phone + password)
//  - Register: User (driving licence) | Owner (RC book + licence)
//  - Design:   Dark steel + gold — same as existing RentX files
// ============================================================
require_once __DIR__ . '/includes/helpers.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    $role = userRole();
    if ($role === 'admin') { header('Location: /rentx/admin/dashboard.php');  exit; }
    if ($role === 'owner') { header('Location: /rentx/owner/dashboard.php');  exit; }
    if ($role === 'user') { header('Location: /rentx/user/dashboard.php'); exit; }
}

// ── State ────────────────────────────────────────────────────
$errors   = [];
$success  = '';
$panel    = 'login';   // login | register
$loginTab = 'user';    // user | owner | admin
$regTab   = 'user';    // user | owner

// ── Helpers ──────────────────────────────────────────────────
function addErr(array &$e, string $k, string $m): void { $e[$k] = $m; }
function old(string $k, string $d = ''): string {
    return htmlspecialchars(trim($_POST[$k] ?? $d), ENT_QUOTES, 'UTF-8');
}
function uploadDoc(array $file, string $prefix): ?string {
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    if (!in_array($file['type'], $allowed, true)) return null;
    if ($file['size'] > 5 * 1024 * 1024)         return null;
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = uniqid($prefix . '_', true) . '.' . $ext;
    $dir  = __DIR__ . '/uploads/docs/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return move_uploaded_file($file['tmp_name'], $dir . $name) ? 'uploads/docs/' . $name : null;
}

// ════════════════════════════════════════════════════════════
//  POST HANDLER
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db     = getDB();

    // ── LOGIN ────────────────────────────────────────────────
    if ($action === 'login') {
        $loginTab = $_POST['login_role'] ?? 'user';
        $email    = trim($_POST['email']    ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $password = $_POST['password']      ?? '';

        if (!$email)    addErr($errors, 'email',    'Email address is required.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                        addErr($errors, 'email',    'Please enter a valid email address.');
        if (!$password) addErr($errors, 'password', 'Password is required.');

        if (empty($errors)) {
            $stmt = $db->prepare(
                "SELECT * FROM users WHERE email=? AND  role=? AND status='active' LIMIT 1"
            );
            $stmt->execute([$email,$loginTab]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                    
                ];
                if ($user['role'] === 'admin') { header('Location: /rentx/admin/dashboard.php');  exit; }
                if ($user['role'] === 'owner') { header('Location: /rentx/owner/dashboard.php');  exit; }
                header('Location: /rentx/user/dashboard.php'); exit;
            } else {
                addErr($errors, 'general',
                    'No matching account found. Check your email, phone and password.');
            }
        }
    }

    // ── REGISTER — USER ──────────────────────────────────────
    if ($action === 'register_user') {
        $panel  = 'register';
        $regTab = 'user';

        $name     = trim($_POST['u_name']     ?? '');
        $email    = trim($_POST['u_email']    ?? '');
        $phone    = trim($_POST['u_phone']    ?? '');
        $password = $_POST['u_password']      ?? '';
        $confirm  = $_POST['u_confirm']       ?? '';
        $secQues  = trim($_POST['u_security_question'] ?? '');
        $secAns   = trim($_POST['u_security_answer'] ?? '');

        if (!$name)   addErr($errors, 'u_name',  'Full name is required.');
        if (!$email)  addErr($errors, 'u_email', 'Email is required.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                      addErr($errors, 'u_email', 'Enter a valid email address.');
        if (!$phone)  addErr($errors, 'u_phone', 'Phone number is required.');
        elseif (!preg_match('/^\+?[\d\s\-]{7,15}$/', $phone))
                      addErr($errors, 'u_phone', 'Enter a valid phone number (7-15 digits).');
        if (!$password)          addErr($errors, 'u_password', 'Password is required.');
        elseif (strlen($password) < 6) addErr($errors, 'u_password', 'Minimum 6 characters.');
        if ($password !== $confirm)    addErr($errors, 'u_confirm',  'Passwords do not match.');
        if (!$secQues)  addErr($errors, 'u_security_question', 'Please select a security question.');
        if (!$secAns)   addErr($errors, 'u_security_answer', 'Please provide a security answer.');
        elseif (strlen($secAns) < 2) addErr($errors, 'u_security_answer', 'Answer must be at least 2 characters.');

        $licFile = $_FILES['u_licence'] ?? null;
        if (empty($licFile['name'])) addErr($errors, 'u_licence', 'Driving licence upload is required.');

        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email=? OR phone=? LIMIT 1");
            $chk->execute([$email, $phone]);
            if ($chk->fetch()) {
                addErr($errors, 'u_email', 'An account with this email or phone already exists.');
            } else {
                $licPath = uploadDoc($licFile, 'user_lic');
                if (!$licPath) {
                    addErr($errors, 'u_licence', 'Upload failed. Use JPG, PNG, WEBP or PDF (max 5MB).');
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ansHash = password_hash(strtolower(trim($secAns)), PASSWORD_BCRYPT);
                    $db->prepare("
                        INSERT INTO users (name,email,phone,password,role,driving_licence,security_question,security_answer,status)
                        VALUES (?,?,?,?,'user',?,?,?,'active')
                    ")->execute([$name, $email, $phone, $hash, $licPath, $secQues, $ansHash]);

                    $success  = 'User account created successfully! You can now log in.';
                    $panel    = 'login';
                    $loginTab = 'user';
                    $errors   = [];
                }
            }
        }
    }

    // ── REGISTER — OWNER ─────────────────────────────────────
    if ($action === 'register_owner') {
        $panel  = 'register';
        $regTab = 'owner';

        $name     = trim($_POST['o_name']     ?? '');
        $email    = trim($_POST['o_email']    ?? '');
        $phone    = trim($_POST['o_phone']    ?? '');
        $password = $_POST['o_password']      ?? '';
        $confirm  = $_POST['o_confirm']       ?? '';
        $secQues  = trim($_POST['o_security_question'] ?? '');
        $secAns   = trim($_POST['o_security_answer'] ?? '');

        if (!$name)   addErr($errors, 'o_name',  'Full name is required.');
        if (!$email)  addErr($errors, 'o_email', 'Email is required.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                      addErr($errors, 'o_email', 'Enter a valid email address.');
        
        if (!$password)          addErr($errors, 'o_password', 'Password is required.');
        elseif (strlen($password) < 6) addErr($errors, 'o_password', 'Minimum 6 characters.');
        if ($password !== $confirm)    addErr($errors, 'o_confirm',  'Passwords do not match.');
        if (!$secQues)  addErr($errors, 'o_security_question', 'Please select a security question.');
        if (!$secAns)   addErr($errors, 'o_security_answer', 'Please provide a security answer.');
        elseif (strlen($secAns) < 2) addErr($errors, 'o_security_answer', 'Answer must be at least 2 characters.');

        $licFile = $_FILES['o_licence'] ?? null;
        $rcFile  = $_FILES['o_rc_book'] ?? null;
        if (empty($licFile['name'])) addErr($errors, 'o_licence', 'Driving licence is required.');
        if (empty($rcFile['name']))  addErr($errors, 'o_rc_book', 'RC Book is required.');

        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email=? OR phone=? LIMIT 1");
            $chk->execute([$email, $phone]);
            if ($chk->fetch()) {
                addErr($errors, 'o_email', 'An account with this email or phone already exists.');
            } else {
                $licPath = uploadDoc($licFile, 'owner_lic');
                $rcPath  = uploadDoc($rcFile,  'owner_rc');
                if (!$licPath) addErr($errors, 'o_licence', 'Licence upload failed. JPG/PNG/PDF max 5MB.');
                if (!$rcPath)  addErr($errors, 'o_rc_book', 'RC Book upload failed. JPG/PNG/PDF max 5MB.');

                if (empty($errors)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ansHash = password_hash(strtolower(trim($secAns)), PASSWORD_BCRYPT);
                    $db->prepare("
                        INSERT INTO users (name,email,phone,password,role,driving_licence,rc_book,security_question,security_answer,status)
                        VALUES (?,?,?,?,'owner',?,?,?,?,'active')
                    ")->execute([$name, $email, $phone, $hash, $licPath, $rcPath, $secQues, $ansHash]);

                    notifyAllAdmins('New Owner Registered',
                        "$name registered as owner. Documents submitted for verification.");

                    $success  = 'Owner account created! You can now log in as Owner.';
                    $panel    = 'login';
                    $loginTab = 'owner';
                    $errors   = [];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RentX — Login &amp; Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/rentx/assets/css/style.css">
<style>
/* ── Page layout ─────────────────────────────────────────── */
body { 
display:flex;justify-content:center;align-items:center:
height:100vh; }



/* ── Hero / left panel ───────────────────────────────────── */

/* ── Auth / right panel ──────────────────────────────────── */
.auth-panel {
  background:var(--bg2);
  border-left:1px solid var(--border);
  width:100%;max-width:420px; 
  margin:0 auto;
  display:flex;
  flex-direction:column;
}
.auth-inner {
  flex: none; padding: 2.25rem 2rem;
  display: flex; flex-direction: column; gap: 0;
}
.auth-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.9rem; letter-spacing: .12em;
  color: var(--text); text-align: center; margin-bottom: 1.4rem;
}
.auth-logo span { color: var(--gold); }

/* Panel switcher */
.panel-switcher {
  display: flex; background: var(--bg3);
  border-radius: var(--radius); padding: .28rem; margin-bottom: 1.3rem;
  border: 1px solid var(--border);
}
.panel-btn {
  flex: 1; text-align: center; padding: .6rem;
  border-radius: 8px; font-size: .88rem; font-weight: 600;
  color: var(--text2); border: none; background: transparent;
  cursor: pointer; transition: all .2s; letter-spacing: .02em;
}
.panel-btn.active { background: var(--gold); color: #000; }

/* Role tabs */
.role-tabs { display: flex; gap: .3rem; margin-bottom: 1.15rem; }
.role-tab {
  flex: 1; text-align: center;
  padding: .52rem .4rem; border-radius: 8px;
  font-size: .78rem; font-weight: 600;
  color: var(--text2); border: 1px solid var(--border);
  background: transparent; cursor: pointer; transition: all .2s;
  white-space: nowrap;
}
.role-tab.active { background: var(--bg3); color: var(--gold); border-color: var(--gold); }

/* Form panels */
.form-panel { display: none; flex-direction: column; gap: .85rem; }
.form-panel.active { display: flex; }

/* Field */
.field { display: flex; flex-direction: column; gap: .38rem; }
.field label {
  font-size: .75rem; font-weight: 600; color: var(--text2);
  letter-spacing: .06em; text-transform: uppercase;
}
.field label .req { color: var(--red); margin-left: .15rem; }

.inp {
  background: var(--bg); border: 1px solid var(--border);
  color: var(--text); border-radius: var(--radius);
  padding: .72rem 1rem; font-family: 'DM Sans', sans-serif;
  font-size: .91rem; transition: border-color .2s; width: 100%;
}
.inp:focus { outline: none; border-color: var(--gold); }
.inp.has-err { border-color: var(--red); }
.inp::placeholder { color: var(--text3); }

/* Select styling */
select.inp {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M2 4l4 4 4-4z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  padding-right: 2rem;
}

/* File upload zone */
.file-zone {
  border: 1.5px dashed var(--border); border-radius: var(--radius);
  background: var(--bg); padding: .9rem; text-align: center;
  cursor: pointer; transition: border-color .2s, background .2s;
  position: relative;
}
.file-zone:hover { border-color: var(--gold); background: rgba(232,184,75,.04); }
.file-zone.has-err { border-color: var(--red); }
.file-zone input[type="file"] {
  position: absolute; inset: 0; opacity: 0;
  cursor: pointer; width: 100%; height: 100%;
}
.fz-icon  { font-size: 1.5rem; margin-bottom: .25rem; }
.fz-lbl   { font-size: .78rem; color: var(--text2); line-height: 1.5; }
.fz-hint  { font-size: .67rem; color: var(--text3); margin-top: .15rem; }

/* Error messages */
.ferr { font-size: .73rem; color: var(--red); margin-top: .1rem; }

/* 2-col grid */
.fg2 { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }

/* Password toggle */
.pw-wrap { position: relative; }
.pw-eye {
  position: absolute; right: .8rem; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: var(--text3);
  cursor: pointer; font-size: .82rem; padding: .2rem;
}
.pw-eye:hover { color: var(--gold); }

/* Alert banners */
.a-box {
  padding: .85rem 1rem; border-radius: var(--radius);
  font-size: .86rem; font-weight: 500;
  display: flex; align-items: flex-start; gap: .55rem;
  margin-bottom: .6rem; line-height: 1.5;
}
.a-err { background: rgba(232,75,75,.1); border: 1px solid rgba(232,75,75,.35); color: #ff9090; }
.a-ok  { background: rgba(75,232,122,.1); border: 1px solid rgba(75,232,122,.35); color: var(--green); }

/* Doc note */
.doc-note {
  background: rgba(232,184,75,.07); border: 1px solid rgba(232,184,75,.2);
  border-radius: var(--radius); padding: .7rem .9rem;
  font-size: .76rem; color: var(--gold); line-height: 1.65;
}
.doc-note strong { color: var(--gold2); }

/* Submit buttons */
.sbtn {
  background: var(--gold); color: #000;
  padding: .82rem 1.4rem; border-radius: var(--radius);
  font-family: 'DM Sans', sans-serif; font-weight: 700;
  font-size: .93rem; border: none; cursor: pointer;
  transition: background .2s; width: 100%; letter-spacing: .02em;
  margin-top: .3rem;
}
.sbtn:hover { background: var(--gold2); }
.sbtn.outline {
  background: transparent; border: 1.5px solid var(--gold); color: var(--gold);
}
.sbtn.outline:hover { background: rgba(232,184,75,.1); }

/* Or divider */
.or-div {
  display: flex; align-items: center; gap: .7rem;
  font-size: .72rem; color: var(--text3); margin: .2rem 0;
}
.or-div::before, .or-div::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* Demo credentials */
.demo-creds {
  font-size: .71rem; color: var(--text3); text-align: center;
  margin-top: .6rem; line-height: 1.8;
}
.demo-creds strong { color: var(--gold); }

/* Footer */
.auth-foot {
  text-align: center; font-size: .75rem; color: var(--text3);
  padding-top: 1.25rem; margin-top: auto;
}
.auth-foot a { color: var(--gold); }

/* ── Responsive ──────────────────────────────────────────── */
@media(max-width: 960px) {
  .page-layout { grid-template-columns: 1fr; }
  .hero-panel  { padding: 2.5rem 1.5rem; min-height: auto; }
  .hero-panel::before { font-size: 40vw; }
  .hero-stats  { gap: 1.5rem; }
  .auth-panel  { border-left: none; border-top: 1px solid var(--border); }
}
@media(max-width: 520px) {
  .auth-inner { padding: 1.6rem 1.1rem; }
  .fg2 { grid-template-columns: 1fr; }
  .hero-roles { display: none; }
  .hero-stats { display: none; }
}
</style>
</head>
<body>



  <!-- ══════════════════════════════════
       RIGHT — AUTH PANEL
  ══════════════════════════════════ -->
  <div class="auth-panel">
    <div class="auth-inner">

      <div class="auth-logo">RENT<span>X</span></div>

      <?php if ($success): ?>
        <div class="a-box a-ok">✅ <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- Panel switcher -->
      <div class="panel-switcher">
        <button class="panel-btn" id="pbtn-login"    onclick="switchPanel('login')">Login</button>
        <button class="panel-btn" id="pbtn-register" onclick="switchPanel('register')">Register</button>
      </div>

      <!-- ══════════════════════════════
           LOGIN PANEL
      ══════════════════════════════ -->
      <div id="panel-login">

        <div class="role-tabs" id="login-tabs">
          <button class="role-tab" id="ltab-user"  onclick="switchLoginTab('user')">👤 User</button>
          <button class="role-tab" id="ltab-owner" onclick="switchLoginTab('owner')">🚘 Owner</button>
          <button class="role-tab" id="ltab-admin" onclick="switchLoginTab('admin')">🛡️ Admin</button>
        </div>

        <?php if (!empty($errors['general']) && $panel === 'login'): ?>
          <div class="a-box a-err">⚠️ <?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <?php foreach (['user' => '👤 User', 'owner' => '🚘 Owner', 'admin' => '🛡️ Admin'] as $lr => $lrlabel): ?>
        <form method="POST" id="lf-<?= $lr ?>" class="form-panel" enctype="multipart/form-data">
          <input type="hidden" name="action"     value="login">
          <input type="hidden" name="login_role" value="<?= $lr ?>">

          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="email"
                   class="inp <?= (!empty($errors['email']) && $panel==='login')?'has-err':'' ?>"
                   placeholder="you@example.com"
                   value="<?= ($panel==='login' && $loginTab===$lr) ? old('email') : '' ?>" required>
            <?php if (!empty($errors['email']) && $panel==='login' && $loginTab===$lr): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['email']) ?></span>
            <?php endif; ?>
          </div>


          <div class="field">
            <label>Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="lp-<?= $lr ?>"
                     class="inp <?= (!empty($errors['password']) && $panel==='login')?'has-err':'' ?>"
                     placeholder="••••••••" required>
              <button type="button" class="pw-eye" onclick="togglePw('lp-<?= $lr ?>',this)">👁</button>
            </div>
            <?php if (!empty($errors['password']) && $panel==='login' && $loginTab===$lr): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['password']) ?></span>
            <?php endif; ?>
            <div style="text-align:right;margin-top:.4rem">
              <a href="/rentx/forgot-password.php" style="font-size:.78rem;color:var(--gold);text-decoration:none">Forgot Password?</a>
            </div>
          </div>

          <button type="submit" class="sbtn">Login as <?= ucfirst($lr) ?> →</button>

          <?php if ($lr === 'admin'): ?>
            <p style="font-size:.72rem;color:var(--text3);text-align:center">
              Admin accounts are created by the system only.
            </p>
          <?php endif; ?>
        </form>
        <?php endforeach; ?>

        <div class="or-div">or</div>
        <button class="sbtn outline" onclick="switchPanel('register')">Create New Account</button>

        
      </div><!-- /panel-login -->


      <!-- ══════════════════════════════
           REGISTER PANEL
      ══════════════════════════════ -->
      <div id="panel-register" style="display:none">

        <div class="role-tabs" id="reg-tabs">
          <button class="role-tab" id="rtab-user"  onclick="switchRegTab('user')">👤 Register as User</button>
          <button class="role-tab" id="rtab-owner" onclick="switchRegTab('owner')">🚘 Register as Owner</button>
        </div>

        <!-- ── USER REGISTER ── -->
        <form method="POST" id="rf-user" class="form-panel" enctype="multipart/form-data">
          <input type="hidden" name="action" value="register_user">

          <div class="fg2">
            <div class="field">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="u_name"
                     class="inp <?= !empty($errors['u_name'])?'has-err':'' ?>"
                     placeholder="John Smith" value="<?= old('u_name') ?>" required>
              <?php if (!empty($errors['u_name'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['u_name']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label>Phone Number <span class="req">*</span></label>
              <input type="tel" name="u_phone"
                     class="inp <?= !empty($errors['u_phone'])?'has-err':'' ?>"
                     placeholder="+91 98765 43210" value="<?= old('u_phone') ?>" required>
              <?php if (!empty($errors['u_phone'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['u_phone']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="u_email"
                   class="inp <?= !empty($errors['u_email'])?'has-err':'' ?>"
                   placeholder="you@example.com" value="<?= old('u_email') ?>" required>
            <?php if (!empty($errors['u_email'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['u_email']) ?></span>
            <?php endif; ?>
          </div>

          <div class="fg2">
            <div class="field">
              <label>Password <span class="req">*</span></label>
              <div class="pw-wrap">
                <input type="password" name="u_password" id="up1"
                       class="inp <?= !empty($errors['u_password'])?'has-err':'' ?>"
                       placeholder="Min 6 characters" required>
                <button type="button" class="pw-eye" onclick="togglePw('up1',this)">👁</button>
              </div>
              <?php if (!empty($errors['u_password'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['u_password']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label>Confirm Password <span class="req">*</span></label>
              <div class="pw-wrap">
                <input type="password" name="u_confirm" id="up2"
                       class="inp <?= !empty($errors['u_confirm'])?'has-err':'' ?>"
                       placeholder="Repeat password" required>
                <button type="button" class="pw-eye" onclick="togglePw('up2',this)">👁</button>
              </div>
              <?php if (!empty($errors['u_confirm'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['u_confirm']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label>Driving Licence <span class="req">*</span></label>
            <div class="doc-note">
              <strong>Required:</strong> Upload a clear photo or scan of your valid driving licence.
              Accepted formats: JPG, PNG, WEBP, PDF · Max 5 MB
            </div>
            <div class="file-zone <?= !empty($errors['u_licence'])?'has-err':'' ?>" id="fz-u-lic">
              <input type="file" name="u_licence" accept="image/*,application/pdf"
                     onchange="setFileLabel(this,'fz-u-lic')" required>
              <div class="fz-icon">🪪</div>
              <div class="fz-lbl">Click to upload Driving Licence</div>
              <div class="fz-hint">JPG · PNG · WEBP · PDF &nbsp;|&nbsp; Max 5 MB</div>
            </div>
            <?php if (!empty($errors['u_licence'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['u_licence']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Security Question <span class="req">*</span></label>
            <select name="u_security_question"
                    class="inp <?= !empty($errors['u_security_question'])?'has-err':'' ?>" required>
              <option value="">-- Select a Security Question --</option>
              <option value="What is your mother's maiden name?" <?= old('u_security_question') === "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
              <option value="What was the name of your first pet?" <?= old('u_security_question') === "What was the name of your first pet?" ? 'selected' : '' ?>>What was the name of your first pet?</option>
              <option value="What city were you born in?" <?= old('u_security_question') === "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
              <option value="What was the name of your first car?" <?= old('u_security_question') === "What was the name of your first car?" ? 'selected' : '' ?>>What was the name of your first car?</option>
              <option value="What is your favorite movie?" <?= old('u_security_question') === "What is your favorite movie?" ? 'selected' : '' ?>>What is your favorite movie?</option>
              <option value="What was your first school's name?" <?= old('u_security_question') === "What was your first school's name?" ? 'selected' : '' ?>>What was your first school's name?</option>
            </select>
            <?php if (!empty($errors['u_security_question'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['u_security_question']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Security Answer <span class="req">*</span></label>
            <input type="text" name="u_security_answer"
                   class="inp <?= !empty($errors['u_security_answer'])?'has-err':'' ?>"
                   placeholder="Your answer to the security question"
                   value="<?= old('u_security_answer') ?>" required>
            <?php if (!empty($errors['u_security_answer'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['u_security_answer']) ?></span>
            <?php endif; ?>
          </div>

          <button type="submit" class="sbtn">Create User Account →</button>
        </form>

        <!-- ── OWNER REGISTER ── -->
        <form method="POST" id="rf-owner" class="form-panel" enctype="multipart/form-data">
          <input type="hidden" name="action" value="register_owner">

          <div class="fg2">
            <div class="field">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="o_name"
                     class="inp <?= !empty($errors['o_name'])?'has-err':'' ?>"
                     placeholder="Raj Kumar" value="<?= old('o_name') ?>" required>
              <?php if (!empty($errors['o_name'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_name']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label>Phone Number <span class="req">*</span></label>
              <input type="tel" name="o_phone"
                     class="inp <?= !empty($errors['o_phone'])?'has-err':'' ?>"
                     placeholder="+91 98765 43210" value="<?= old('o_phone') ?>" required>
              <?php if (!empty($errors['o_phone'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_phone']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="o_email"
                   class="inp <?= !empty($errors['o_email'])?'has-err':'' ?>"
                   placeholder="you@example.com" value="<?= old('o_email') ?>" required>
            <?php if (!empty($errors['o_email'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['o_email']) ?></span>
            <?php endif; ?>
          </div>

          <div class="fg2">
            <div class="field">
              <label>Password <span class="req">*</span></label>
              <div class="pw-wrap">
                <input type="password" name="o_password" id="op1"
                       class="inp <?= !empty($errors['o_password'])?'has-err':'' ?>"
                       placeholder="Min 6 characters" required>
                <button type="button" class="pw-eye" onclick="togglePw('op1',this)">👁</button>
              </div>
              <?php if (!empty($errors['o_password'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_password']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label>Confirm Password <span class="req">*</span></label>
              <div class="pw-wrap">
                <input type="password" name="o_confirm" id="op2"
                       class="inp <?= !empty($errors['o_confirm'])?'has-err':'' ?>"
                       placeholder="Repeat password" required>
                <button type="button" class="pw-eye" onclick="togglePw('op2',this)">👁</button>
              </div>
              <?php if (!empty($errors['o_confirm'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_confirm']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="doc-note">
            <strong>Owner Verification — 2 documents required:</strong><br>
            Upload clear photos or scans. Admin will verify before your cars go live.<br>
            ① Driving Licence &nbsp;&nbsp; ② RC Book (Vehicle Registration Certificate)
          </div>

          <div class="fg2">
            <div class="field">
              <label>Driving Licence <span class="req">*</span></label>
              <div class="file-zone <?= !empty($errors['o_licence'])?'has-err':'' ?>" id="fz-o-lic">
                <input type="file" name="o_licence" accept="image/*,application/pdf"
                       onchange="setFileLabel(this,'fz-o-lic')" required>
                <div class="fz-icon">🪪</div>
                <div class="fz-lbl">Upload Licence</div>
                <div class="fz-hint">JPG·PNG·PDF · 5MB</div>
              </div>
              <?php if (!empty($errors['o_licence'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_licence']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label>RC Book <span class="req">*</span></label>
              <div class="file-zone <?= !empty($errors['o_rc_book'])?'has-err':'' ?>" id="fz-o-rc">
                <input type="file" name="o_rc_book" accept="image/*,application/pdf"
                       onchange="setFileLabel(this,'fz-o-rc')" required>
                <div class="fz-icon">📋</div>
                <div class="fz-lbl">Upload RC Book</div>
                <div class="fz-hint">JPG·PNG·PDF · 5MB</div>
              </div>
              <?php if (!empty($errors['o_rc_book'])): ?>
                <span class="ferr">⚠ <?= htmlspecialchars($errors['o_rc_book']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label>Security Question <span class="req">*</span></label>
            <select name="o_security_question"
                    class="inp <?= !empty($errors['o_security_question'])?'has-err':'' ?>" required>
              <option value="">-- Select a Security Question --</option>
              <option value="What is your mother's maiden name?" <?= old('o_security_question') === "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
              <option value="What was the name of your first pet?" <?= old('o_security_question') === "What was the name of your first pet?" ? 'selected' : '' ?>>What was the name of your first pet?</option>
              <option value="What city were you born in?" <?= old('o_security_question') === "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
              <option value="What was the name of your first car?" <?= old('o_security_question') === "What was the name of your first car?" ? 'selected' : '' ?>>What was the name of your first car?</option>
              <option value="What is your favorite movie?" <?= old('o_security_question') === "What is your favorite movie?" ? 'selected' : '' ?>>What is your favorite movie?</option>
              <option value="What was your first school's name?" <?= old('o_security_question') === "What was your first school's name?" ? 'selected' : '' ?>>What was your first school's name?</option>
            </select>
            <?php if (!empty($errors['o_security_question'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['o_security_question']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Security Answer <span class="req">*</span></label>
            <input type="text" name="o_security_answer"
                   class="inp <?= !empty($errors['o_security_answer'])?'has-err':'' ?>"
                   placeholder="Your answer to the security question"
                   value="<?= old('o_security_answer') ?>" required>
            <?php if (!empty($errors['o_security_answer'])): ?>
              <span class="ferr">⚠ <?= htmlspecialchars($errors['o_security_answer']) ?></span>
            <?php endif; ?>
          </div>

          <button type="submit" class="sbtn">Create Owner Account →</button>
        </form>

        <div class="or-div">already have an account?</div>
        <button class="sbtn outline" onclick="switchPanel('login')">← Back to Login</button>

      </div><!-- /panel-register -->

      <div class="auth-foot">
        &copy; <?= date('Y') ?> RentX &nbsp;·&nbsp;
        <a href="/rentx/home.html">Home</a> &nbsp;·&nbsp;
        <a href="/rentx/cars.php">Browse Cars</a>
      </div>

    </div><!-- /auth-inner -->
  </div><!-- /auth-panel -->

</div><!-- /page-layout -->

<script>
// ── Panel switcher ──────────────────────────────────────────
function switchPanel(p) {
  const isLogin = p === 'login';
  document.getElementById('panel-login').style.display    = isLogin ? 'block' : 'none';
  document.getElementById('panel-register').style.display = isLogin ? 'none'  : 'block';
  document.getElementById('pbtn-login').classList.toggle('active',    isLogin);
  document.getElementById('pbtn-register').classList.toggle('active', !isLogin);
}

// ── Login tab ───────────────────────────────────────────────
function switchLoginTab(role) {
  ['user','owner','admin'].forEach(r => {
    const f = document.getElementById('lf-' + r);
    const b = document.getElementById('ltab-' + r);
    if (f) f.classList.toggle('active', r === role);
    if (b) b.classList.toggle('active', r === role);
  });
}

// ── Register tab ────────────────────────────────────────────
function switchRegTab(role) {
  ['user','owner'].forEach(r => {
    const f = document.getElementById('rf-' + r);
    const b = document.getElementById('rtab-' + r);
    if (f) f.classList.toggle('active', r === role);
    if (b) b.classList.toggle('active', r === role);
  });
}

// ── Password show/hide ──────────────────────────────────────
function togglePw(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  const show = el.type === 'password';
  el.type    = show ? 'text' : 'password';
  btn.textContent = show ? '🙈' : '👁';
}

// ── File upload label ───────────────────────────────────────
function setFileLabel(inp, zoneId) {
  const zone = document.getElementById(zoneId);
  if (!zone || !inp.files[0]) return;
  zone.querySelector('.fz-lbl').textContent = '✅ ' + inp.files[0].name;
  zone.querySelector('.fz-lbl').style.color = '#4be87a';
  zone.style.borderColor = '#4be87a';
}

// ── Init from PHP state ─────────────────────────────────────
(function () {
  const panel    = '<?= $panel ?>';
  const loginTab = '<?= $loginTab ?>';
  const regTab   = '<?= $regTab ?>';

  switchPanel(panel);
  if (panel === 'login')    switchLoginTab(loginTab);
  if (panel === 'register') switchRegTab(regTab);
})();
</script>
</body>
</html>
