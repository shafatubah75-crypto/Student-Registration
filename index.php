<?php
declare(strict_types=1);
session_start();
function db(): PDO
{
    $dsn = 'mysql:host=localhost;dbname=lab01;charset=utf8mb4';
    return new PDO($dsn, 'root', '', [   // XAMPP defaults; change for your setup
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

const PROGRAMMES = ['Software Engineering', 'Computer Science', 'Information Systems', 'Cyber Security', 'Data Science'];
const LEVELS     = ['Year 1', 'Year 2', 'Year 3', 'Year 4'];
const GENDERS    = ['Female', 'Male'];

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }


// ---- Extension: searchable list (index.php?view=list) ----
if (($_GET['view'] ?? '') === 'list') {
    $q = trim((string)($_GET['q'] ?? ''));
    $rows = []; $fail = false;
    try {
        $sql = 'SELECT id, full_name, email, programme, level, submitted_at FROM students';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE full_name LIKE :q OR email LIKE :q OR programme LIKE :q';
            $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $stmt = db()->prepare($sql . ' ORDER BY submitted_at DESC LIMIT 200');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $ex) { error_log($ex->getMessage()); $fail = true; }
    ?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registrations</title><link rel="stylesheet" href="style.css"></head>
<body><main>
<h1>Registrations</h1>
<form method="get"><input type="hidden" name="view" value="list">
<label for="q">Search name, email or programme</label>
<input type="text" id="q" name="q" value="<?= e($q) ?>">
<div class="actions"><button class="primary">Search</button> <a href="index.php">Back to form</a></div></form>
<?php if ($fail): ?><p class="alert bad" role="alert">Could not load registrations.</p>
<?php else: ?>
<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Programme</th><th>Level</th><th>Submitted</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= (int)$r['id'] ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['programme']) ?></td><td><?= e($r['level']) ?></td><td><?= e($r['submitted_at']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6">No records found.</td></tr><?php endif; ?>
</table><?php endif; ?>
</main></body></html>
<?php
    exit;
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$old = ['full_name'=>'','email'=>'','telephone'=>'','dob'=>'','programme'=>'','level'=>'','gender'=>'','policy'=>''];
$errors = [];
$success = false;
$dbError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim and collect (never trust input)
    foreach (['full_name','email','telephone','dob','programme','level','gender'] as $k) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $old['policy'] = isset($_POST['policy']) ? '1' : '';

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors['form'] = 'Invalid form session. Please reload and try again.';
    }

    // Name: 3-80 chars, letters + space . ' -
    $len = mb_strlen($old['full_name']);
    if ($old['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif ($len < 3 || $len > 80) {
        $errors['full_name'] = 'Name must be 3–80 characters.';
    } elseif (!preg_match("/^\p{L}[\p{L} .'\-]*$/u", $old['full_name'])) {
        $errors['full_name'] = "Use letters, spaces and . ' - only.";
    }

    // Email
    if ($old['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (strlen($old['email']) > 255 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // Telephone: exactly 9 digits, e.g. 771234567 (no spaces, no +, no leading country code)
    if ($old['telephone'] === '') {
        $errors['telephone'] = 'Telephone is required.';
    } elseif (!preg_match('/^\d{9}$/', $old['telephone'])) {
        $errors['telephone'] = 'Telephone must be exactly 9 digits (e.g. 771234567).';
    }

    // DOB: real calendar date, strictly in the past
    $d = DateTime::createFromFormat('!Y-m-d', $old['dob']);
    $dErr = DateTime::getLastErrors();
    if ($old['dob'] === '') {
        $errors['dob'] = 'Date of birth is required.';
    } elseif (!$d || ($dErr && ($dErr['warning_count'] || $dErr['error_count'])) || $d->format('Y-m-d') !== $old['dob']) {
        $errors['dob'] = 'Enter a valid date (YYYY-MM-DD).';
    } elseif ($d >= new DateTime('today')) {
        $errors['dob'] = 'Date of birth must be in the past.';
    } elseif ($d < new DateTime('1900-01-01')) {
        $errors['dob'] = 'Date of birth is not plausible.';
    }

    // Programme / level: mandatory and from whitelist
    if ($old['programme'] === '') {
        $errors['programme'] = 'Select a programme.';
    } elseif (!in_array($old['programme'], PROGRAMMES, true)) {
        $errors['programme'] = 'Invalid programme.';
    }
    if ($old['level'] === '') {
        $errors['level'] = 'Select a level.';
    } elseif (!in_array($old['level'], LEVELS, true)) {
        $errors['level'] = 'Invalid level.';
    }

    // Gender: optional, but must be a known value if sent
    if ($old['gender'] !== '' && !in_array($old['gender'], GENDERS, true)) {
        $errors['gender'] = 'Invalid option.';
    }

    if ($old['policy'] !== '1') {
        $errors['policy'] = 'You must accept the registration policy.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $chk = $pdo->prepare('SELECT 1 FROM students WHERE email = :email LIMIT 1');
            $chk->execute([':email' => strtolower($old['email'])]);
            if ($chk->fetchColumn()) {
                $errors['email'] = 'This email is already registered.';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO students (full_name, email, telephone, date_of_birth, programme, level, gender, policy_accepted)
                     VALUES (:n, :e, :t, :d, :p, :l, :g, 1)'
                );
                $ins->execute([
                    ':n' => $old['full_name'],
                    ':e' => strtolower($old['email']),
                    ':t' => $old['telephone'],
                    ':d' => $old['dob'],
                    ':p' => $old['programme'],
                    ':l' => $old['level'],
                    ':g' => $old['gender'] === '' ? null : $old['gender'],
                ]);
                if ($ins->rowCount() === 1) {          // confirmed insert
                    $success = true;
                    $_SESSION['csrf'] = bin2hex(random_bytes(16));
                    $old = array_map(fn() => '', $old);
                }
            }
        } catch (PDOException $ex) {
            if (($ex->errorInfo[1] ?? 0) === 1062) {    // race-condition duplicate
                $errors['email'] = 'This email is already registered.';
            } else {
                error_log($ex->getMessage());           // log detail, show nothing sensitive
                $dbError = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lab 01 — Student Registration</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
<h1>Student Registration</h1>

<?php if ($success): ?>
  <div class="alert ok" role="status">Registration saved successfully.</div>
<?php endif; ?>
<?php if ($dbError): ?>
  <div class="alert bad" role="alert">Sorry, we couldn't save your registration right now. Please try again later.</div>
<?php endif; ?>
<?php if (isset($errors['form'])): ?>
  <div class="alert bad" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<form method="post" action="" novalidate>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

  <?php
  function err(array $errors, string $k): string {
      return isset($errors[$k]) ? '<span class="err" id="'.$k.'-err" role="alert">'.e($errors[$k]).'</span>' : '';
  }
  function aria(array $errors, string $k): string {
      return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="'.$k.'-err"' : '';
  }
  ?>

  <div class="row">
    <label for="full_name">Full name</label>
    <input type="text" id="full_name" name="full_name" maxlength="80" required autocomplete="name"
           value="<?= e($old['full_name']) ?>"<?= aria($errors,'full_name') ?>>
    <?= err($errors,'full_name') ?>
  </div>

  <div class="row">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email" maxlength="255" required autocomplete="email"
           value="<?= e($old['email']) ?>"<?= aria($errors,'email') ?>>
    <?= err($errors,'email') ?>
  </div>

  <div class="row">
    <label for="telephone">Telephone <small>(9 digits, e.g. 771234567)</small></label>
    <input type="tel" id="telephone" name="telephone" inputmode="numeric" maxlength="9" required autocomplete="tel-national"
           value="<?= e($old['telephone']) ?>"<?= aria($errors,'telephone') ?>>
    <?= err($errors,'telephone') ?>
  </div>

  <div class="row">
    <label for="dob">Date of birth</label>
    <input type="date" id="dob" name="dob" required max="<?= date('Y-m-d', strtotime('yesterday')) ?>"
           value="<?= e($old['dob']) ?>"<?= aria($errors,'dob') ?>>
    <?= err($errors,'dob') ?>
  </div>

  <div class="row">
    <label for="programme">Programme</label>
    <select id="programme" name="programme" required<?= aria($errors,'programme') ?>>
      <option value="">Choose an option</option>
      <?php foreach (PROGRAMMES as $p): ?>
        <option value="<?= e($p) ?>" <?= $old['programme'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <?= err($errors,'programme') ?>
  </div>

  <div class="row">
    <label for="level">Level</label>
    <select id="level" name="level" required<?= aria($errors,'level') ?>>
      <option value="">Choose an option</option>
      <?php foreach (LEVELS as $l): ?>
        <option value="<?= e($l) ?>" <?= $old['level'] === $l ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
    <?= err($errors,'level') ?>
  </div>

  <fieldset class="row">
    <legend>Gender <small>(optional)</small></legend>
    <?php foreach (GENDERS as $g): ?>
      <label class="inline"><input type="radio" name="gender" value="<?= e($g) ?>" <?= $old['gender'] === $g ? 'checked' : '' ?>> <?= e($g) ?></label>
    <?php endforeach; ?>
    <?= err($errors,'gender') ?>
  </fieldset>

  <div class="row">
    <span class="lbl">Accept registration policy</span>
    <label class="inline"><input type="checkbox" name="policy" value="1" <?= $old['policy'] === '1' ? 'checked' : '' ?><?= aria($errors,'policy') ?>> I agree</label>
    <?= err($errors,'policy') ?>
  </div>

  <div class="actions">
    <button type="submit" class="primary">Submit Form</button>
    <button type="reset">Reset</button>
  </div>
</form>
<p><a href="index.php?view=list">View registrations</a></p>
</main>
</body>
</html>
