<?php
// auth.php - Login dan Register

include 'config.php'; 
session_start();

// Redirect ke dashboard jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Tentukan apakah user ingin Login atau Register
$action = isset($_GET['action']) && $_GET['action'] == 'register' ? 'register' : 'login';
$message = '';
$name = $email = '';
$form_title = ($action == 'register') ? 'Halaman Register' : 'Halaman Login';
$submit_text = ($action == 'register') ? 'Daftar' : 'Masuk';
$form_action = "auth.php?action=$action";

// --- LOGIKA PEMROSESAN FORMULIR ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_input = mysqli_real_escape_string($conn, $_POST['email']);
    $password_input = $_POST['password'];

    if ($action == 'register') {
        // --- LOGIKA REGISTER ---
        $name_input = mysqli_real_escape_string($conn, $_POST['name']);
        
        if (empty($name_input) || empty($email_input) || empty($password_input)) {
            $message = "Semua field wajib diisi!";
        } else {
            $check_query = "SELECT id FROM users WHERE email = '$email_input'";
            $result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($result) > 0) {
                $message = "Email sudah terdaftar. Silakan Login.";
            } else {
                // Default role adalah 'user'
                $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO users (name, email, password_hash, role) VALUES ('$name_input', '$email_input', '$password_hash', 'user')";

                if (mysqli_query($conn, $insert_query)) {
                    header("Location: auth.php?action=login&status=registered");
                    exit();
                } else {
                    $message = "Error: " . mysqli_error($conn);
                }
            }
        }
        $name = $name_input; 
    } 
    
    if ($action == 'login') {
        // --- LOGIKA LOGIN ---
        $query = "SELECT id, name, password_hash, role FROM users WHERE email = '$email_input'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password_input, $user['password_hash'])) {
                // Login Berhasil! Set Sesi
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role']; 
                
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

// Cek status pesan dari redirect
if (isset($_GET['status']) && $_GET['status'] == 'registered' && $action == 'login') {
    $message = "Pendaftaran Berhasil! Silakan **Masuk** menggunakan akun Anda.";
}

mysqli_close($conn); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $form_title; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h2><?php echo $form_title; ?></h2>
        <hr>

        <?php if (!empty($message)): ?>
            <p class="<?php echo (strpos($message, 'Berhasil') !== false) ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form action="<?php echo $form_action; ?>" method="POST">
            
            <?php if ($action == 'register'): ?>
                <div class="form-group">
                    <label for="name">Nama:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="submit-btn"><?php echo $submit_text; ?></button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            <?php if ($action == 'login'): ?>
                Belum punya akun? 
                <a href="auth.php?action=register" class="link-btn">Daftar di sini</a>
            <?php else: ?>
                Sudah punya akun? 
                <a href="auth.php?action=login" class="link-btn">Login di sini</a>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>