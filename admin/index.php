<?php
session_start();

// ==========================================
// 1. KONFIGURÁCIÓ
// ==========================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'nodered');
define('DB_USER', 'user');         // Szükség esetén módosítsd (pl. debian-sys-maint vagy nodered_user)
define('DB_PASS', 'user123');             // A MariaDB root/felhasználó jelszavad

define('AUTH_USER', 'user');
define('AUTH_PASS', 'user123');

// Jogosultsági szintek elnevezése
$jogok = [
    0 => '0 - Nem használt',
    1 => '1 - Admin',
    2 => '2 - Dolgozó',
    3 => '3 - Mindenki más'
];

// ==========================================
// 2. KIJELENTKEZÉS
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ==========================================
// 3. BEJELENTKEZÉS KEZELÉSE
// ==========================================
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === AUTH_USER && $p === AUTH_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $login_error = 'Hibás felhasználónév vagy jelszó!';
    }
}

// Ha nincs bejelentkezve -> Login képernyő
if (empty($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Belépés - RFID Admin</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .login-box { background: #1e293b; padding: 32px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 320px; border: 1px solid #334155; }
            h2 { margin-top: 0; font-size: 1.3rem; text-align: center; color: #38bdf8; }
            label { display: block; margin: 14px 0 4px; font-size: 0.85rem; color: #94a3b8; }
            input[type="text"], input[type="password"] { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 4px; border: 1px solid #334155; background: #0f172a; color: #fff; font-size: 0.95rem; }
            button { width: 100%; margin-top: 22px; padding: 10px; background: #0284c7; border: none; border-radius: 4px; color: white; font-weight: bold; cursor: pointer; font-size: 0.95rem; }
            button:hover { background: #0369a1; }
            .error { background: #ef444422; border: 1px solid #ef4444; color: #f87171; padding: 8px 12px; border-radius: 4px; font-size: 0.85rem; margin-bottom: 12px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>RFID Admin Belépés</h2>
            <?php if ($login_error): ?><div class="error"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
            <form method="POST">
                <label>Felhasználó</label>
                <input type="text" name="username" required autofocus autocomplete="off">
                <label>Jelszó</label>
                <input type="password" name="password" required>
                <button type="submit" name="login_submit">Bejelentkezés</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// 4. ADATBÁZIS KAPCSOLAT (PDO)
// ==========================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("<div style='color:red; font-family:monospace; padding:20px; background:#1e293b;'>Adatbázis kapcsolódási hiba: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$message = '';
$msg_type = 'success';

// ==========================================
// 5. ŰRLAPFELDOLGOZÁS (Új User és Új Tag)
// ==========================================

// Új Felhasználó
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nev1 = trim($_POST['nev1'] ?? '');
    $nev2 = trim($_POST['nev2'] ?? '');
    $emil = trim($_POST['emil'] ?? '');
    $jog  = intval($_POST['jog'] ?? 3);

    if ($nev1 === '' || $emil === '') {
        $message = 'A Vezetéknév (Nev1) és az E-mail kitöltése kötelező!';
        $msg_type = 'danger';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (Nev1, Nev2, emil, jog) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nev1, $nev2, $emil, $jog]);
        $message = "Felhasználó ($nev1 $nev2) sikeresen létrehozva!";
    }
}

// Új RFID Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    $tag     = strtoupper(trim($_POST['tag'] ?? ''));
    $user_id = intval($_POST['user_id'] ?? 0);

    if (strlen($tag) !== 8) {
        $message = 'A Tag azonosítónak pontosan 8 karakteresnek kell lennie!';
        $msg_type = 'danger';
    } elseif ($user_id <= 0) {
        $message = 'Válassz ki egy felhasználót a kártyához!';
        $msg_type = 'danger';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tags (tag, user_id) VALUES (?, ?)");
        $stmt->execute([$tag, $user_id]);
        $message = "A(z) $tag kódú tag sikeresen rögzítve!";
    }
}

// Törlés műveletek
if (isset($_GET['del_tag'])) {
    $stmt = $pdo->prepare("DELETE FROM tags WHERE ID = ?");
    $stmt->execute([intval($_GET['del_tag'])]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (isset($_GET['del_user'])) {
    $u_id = intval($_GET['del_user']);
    $pdo->prepare("DELETE FROM tags WHERE user_id = ?")->execute([$u_id]);
    $pdo->prepare("DELETE FROM users WHERE ID = ?")->execute([$u_id]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Lekérdezések a listákhoz
$users = $pdo->query("SELECT * FROM users ORDER BY ID ASC")->fetchAll();
$tags = $pdo->query("
    SELECT t.ID, t.tag, t.user_id, u.Nev1, u.Nev2, u.jog 
    FROM tags t 
    LEFT JOIN users u ON t.user_id = u.ID 
    ORDER BY t.ID ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Node-RED / RFID Vezérlő Admin</title>
    <style>
        :root {
            --bg: #0f172a; --panel: #1e293b; --border: #334155;
            --text: #f8fafc; --muted: #94a3b8; --accent: #38bdf8;
            --danger: #ef4444; --success: #22c55e;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 1.35rem; color: var(--accent); }
        .logout { color: var(--muted); text-decoration: none; padding: 6px 12px; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; }
        .logout:hover { background: #334155; color: #fff; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }
        h2 { margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 8px; color: #cbd5e1; }
        .alert { padding: 10px 14px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-success { background: #14532d55; border: 1px solid var(--success); color: #86efac; }
        .alert-danger { background: #7f1d1d55; border: 1px solid var(--danger); color: #fca5a5; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px; }
        label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 4px; }
        input, select { width: 100%; box-sizing: border-box; padding: 8px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: #fff; font-size: 0.9rem; }
        button.btn-primary { background: #0284c7; border: none; color: white; padding: 9px 16px; border-radius: 4px; font-weight: 600; cursor: pointer; height: 38px; align-self: flex-end; }
        button.btn-primary:hover { background: #0369a1; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 0.88rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
        th { background: #0f172a88; color: var(--muted); font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .badge-1 { background: #7c3aed44; color: #c4b5fd; border: 1px solid #7c3aed; } /* Admin */
        .badge-2 { background: #0284c744; color: #7dd3fc; border: 1px solid #0284c7; } /* Dolgozo */
        .badge-3 { background: #47556944; color: #cbd5e1; border: 1px solid #64748b; } /* Mindenki mas */
        .badge-0 { background: #ef444433; color: #fca5a5; border: 1px solid #ef4444; } /* Nem hasznalt */
        .del-link { color: #f87171; text-decoration: none; font-size: 0.8rem; }
        .del-link:hover { text-decoration: underline; }
        .tag-code { font-family: monospace; font-size: 0.95rem; font-weight: bold; color: #facc15; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>RFID & Beléptető Adminisztráció</h1>
        <div>
            <span style="color: var(--muted); font-size: 0.85rem; margin-right: 12px;">Felhasználó: <strong><?= AUTH_USER ?></strong></span>
            <a href="?action=logout" class="logout">Kijelentkezés</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- 1. PANEL: FELHASZNÁLÓK -->
        <div class="panel">
            <h2>Új felhasználó felvétele</h2>
            <form method="POST">
                <div class="form-row">
                    <div>
                        <label>Vezetéknév / Nev1</label>
                        <input type="text" name="nev1" placeholder="pl. Kovács" required>
                    </div>
                    <div>
                        <label>Keresztnév / Nev2</label>
                        <input type="text" name="nev2" placeholder="pl. Béla">
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>E-mail</label>
                        <input type="email" name="emil" placeholder="bela@ceg.hu" required>
                    </div>
                    <div>
                        <label>Jogosultság</label>
                        <select name="jog">
                            <?php foreach ($jogok as $kod => $label): ?>
                                <option value="<?= $kod ?>" <?= ($kod === 2 ? 'selected' : '') ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn-primary">Mentés</button>
                </div>
            </form>

            <h2 style="margin-top: 28px;">Felhasználók (<?= count($users) ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Név</th>
                        <th>E-mail</th>
                        <th>Jog</th>
                        <th>Művelet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['ID'] ?></td>
                        <td><strong><?= htmlspecialchars($u['Nev1'] . ' ' . $u['Nev2']) ?></strong></td>
                        <td><?= htmlspecialchars($u['emil']) ?></td>
                        <td>
                            <span class="badge badge-<?= $u['jog'] ?>">
                                <?= $jogok[$u['jog']] ?? $u['jog'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="?del_user=<?= $u['ID'] ?>" class="del-link" onclick="return confirm('Törlöd ezt a felhasználót és a hozzá kapcsolt kártyákat?');">Törlés</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. PANEL: RFID KÁRTYÁK (TAG-EK) -->
        <div class="panel">
            <h2>Új RFID Tag hozzárendelése</h2>
            <form method="POST">
                <div class="form-row">
                    <div>
                        <label>Tag Kód (8 HEX)</label>
                        <input type="text" name="tag" placeholder="pl. 3348CBDE" maxlength="8" style="text-transform:uppercase;" required>
                    </div>
                    <div>
                        <label>Hozzárendelt személy</label>
                        <select name="user_id" required>
                            <option value="">-- Válassz usert --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['ID'] ?>"><?= htmlspecialchars($u['Nev1'] . ' ' . $u['Nev2']) ?> (#<?= $u['ID'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_tag" class="btn-primary">Tag Mentése</button>
                </div>
            </form>

            <h2 style="margin-top: 28px;">Kiadott Kártyák (<?= count($tags) ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag Kód</th>
                        <th>Tulajdonos</th>
                        <th>Jog</th>
                        <th>Művelet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $t): ?>
                    <tr>
                        <td>#<?= $t['ID'] ?></td>
                        <td><span class="tag-code"><?= htmlspecialchars($t['tag']) ?></span></td>
                        <td><?= htmlspecialchars(($t['Nev1'] ?? '') . ' ' . ($t['Nev2'] ?? '')) ?></td>
                        <td>
                            <?php if (isset($t['jog'])): ?>
                                <span class="badge badge-<?= $t['jog'] ?>">
                                    <?= $jogok[$t['jog']] ?? $t['jog'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--muted); font-style:italic;">Nincs gazdája</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?del_tag=<?= $t['ID'] ?>" class="del-link" onclick="return confirm('Biztosan törlöd a kártyát?');">Törlés</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
