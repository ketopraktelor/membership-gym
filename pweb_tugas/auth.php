<?php
// auth.php - Login dan Register (JWT Version)

// =======================
// LOAD CONFIG
// =======================

include 'config.php';
include 'jwt_config.php';

// =======================
// AUTO REDIRECT JIKA SUDAH LOGIN
// =======================

if (isset($_COOKIE['token'])) {
    header("Location: dashboard.php");
    exit();
}

// =======================
// FORM CONFIG
// =======================

$action = (isset($_GET['action']) && $_GET['action'] == 'register') ? 'register' : 'login';

$message = '';
$name = '';
$email = '';

$form_title  = ($action == 'register') ? 'Halaman Register' : 'Halaman Login';
$submit_text = ($action == 'register') ? 'Daftar' : 'Masuk';
$form_action = "auth.php?action=$action";

// =======================
// FORM PROCESS
// =======================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email_input    = mysqli_real_escape_string($conn, $_POST['email']);
    $password_input = $_POST['password'];

    // =======================
    // REGISTER
    // =======================

    if ($action == 'register') {

        $name_input = mysqli_real_escape_string($conn, $_POST['name']);

        if (empty($name_input) || empty($email_input) || empty($password_input)) {

            $message = "Semua field wajib diisi!";

        } else {

            $check_query = "SELECT id FROM users WHERE email = '$email_input'";
            $result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($result) > 0) {

                $message = "Email sudah terdaftar. Silakan Login.";

            } else {

                $password_hash = password_hash($password_input, PASSWORD_DEFAULT);

                $insert_query = "INSERT INTO users (name, email, password_hash, role) 
                                VALUES ('$name_input', '$email_input', '$password_hash', 'user')";

                if (mysqli_query($conn, $insert_query)) {

                    header("Location: auth.php?action=login&status=registered");
                    exit();

                } else {

                    $message = "Database Error: " . mysqli_error($conn);
                }
            }
        }

        $name = $name_input;
    }

    // =======================
    // LOGIN
    // =======================

    if ($action == 'login') {

        $query = "SELECT id, name, password_hash, role FROM users WHERE email = '$email_input'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

            if (password_verify($password_input, $user['password_hash'])) {

                // =======================
                // CREATE JWT
                // =======================

                $payload = [
                    "iss" => "membership_gym",
                    "iat" => time(),
                    "exp" => time() + 3600, // 1 jam
                    "data" => [
                        "id"   => $user['id'],
                        "name" => $user['name'],
                        "role" => $user['role']
                    ]
                ];

                $jwt = JWT::encode($payload, $jwt_secret, $jwt_algo);

                // =======================
                // SET COOKIE
                // =======================

                if (headers_sent()) {
                    die("ERROR: HEADER SUDAH TERKIRIM - COOKIE GAGAL");
                }

                setcookie("jwt", $token, time()+3600, "/" "", false, true);
                
                header("Location: dashboard.php");
                exit();
            } else {
                $message = "Email atau Password salah.";
            }
        } else {
            $message = "Email atau Password salah.";
        }
    }
    $email = $email_input;
}

// =======================
// STATUS MESSAGE
// =======================

if (isset($_GET['status']) && $_GET['status'] == 'registered' && $action == 'login') {
    $message = "Pendaftaran Berhasil! Silakan Login.";
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $form_title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container">

    <h2><?= $form_title ?></h2>
    <hr>

    <?php if (!empty($message)): ?>
        <p class="<?= (strpos($message, 'Berhasil') !== false) ? 'message-success' : 'message-error' ?>">
            <?= $message ?>
        </p>
    <?php endif; ?>

    <form action="<?= $form_action ?>" method="POST">

        <?php if ($action == 'register'): ?>
            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="submit-btn">
            <?= $submit_text ?>
        </button>

    </form>

    <p style="text-align:center;margin-top:15px">

        <?php if ($action == 'login'): ?>
            Belum punya akun?
            <a href="auth.php?action=register">Daftar di sini</a>
        <?php else: ?>
            Sudah punya akun?
            <a href="auth.php?action=login">Login di sini</a>
        <?php endif; ?>

    </p>

</div>

</body>
</html>
