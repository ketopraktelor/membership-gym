<?php
// dashboard.php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

// Data Dasar Pengguna dari Sesi
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'] ?? 'user';
$message_status = '';
$user_details = []; 
$user_id = $_SESSION['user_id'];
$remaining_days = null; 
$conn = null;

// --- 1. Ambil Detail Membership dari Database ---
if (isset($user_id)) {
    include 'config.php'; 
    $user_id_safe = mysqli_real_escape_string($conn, $user_id);
    
    $detail_query = "SELECT name, email, join_date, expiry_date, renewal_request, renewal_proof FROM users WHERE id = '$user_id_safe'";
    $detail_result = mysqli_query($conn, $detail_query);
    
    if ($detail_result && mysqli_num_rows($detail_result) == 1) {
        $user_details = mysqli_fetch_assoc($detail_result);
        
        $join_date = $user_details['join_date'];
        $expiry_date = $user_details['expiry_date'];
        $renewal_request_status = $user_details['renewal_request'];
        $renewal_proof_id = $user_details['renewal_proof'];
        
        // Hitung status keanggotaan
        $status_membership = (strtotime($expiry_date) >= strtotime(date('Y-m-d'))) ? 
                             'Aktif' : 'Kedaluwarsa';
        
        $formatted_join = date('d M Y', strtotime($join_date));
        $formatted_expiry = date('d M Y', strtotime($expiry_date));

        // HITUNG SISA HARI
        $today = time();
        $expiry_time = strtotime($expiry_date);
        $remaining_days_seconds = $expiry_time - $today;
        $remaining_days = floor($remaining_days_seconds / (60 * 60 * 24)); 
    }
}


// --- 2. Logika Pemilihan Kelas (Hanya Anggota) ---
if ($user_role == 'user' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['select_classes'])) {
    $selected_classes = $_POST['class_ids'] ?? [];
    
    if (count($selected_classes) > 2) {
        $message_status = "❌ Gagal: Anda hanya dapat memilih maksimal 2 kelas.";
    } elseif (count($selected_classes) > 0) {
        // Hapus kelas lama (jika ada)
        mysqli_query($conn, "DELETE FROM user_classes WHERE user_id = '$user_id_safe'");

        $success = true;
        foreach ($selected_classes as $class_id) {
            $class_id_safe = mysqli_real_escape_string($conn, $class_id);
            $insert_query = "INSERT INTO user_classes (user_id, class_id) VALUES ('$user_id_safe', '$class_id_safe')";
            if (!mysqli_query($conn, $insert_query)) {
                $success = false;
                break;
            }
        }

        if ($success) {
            $message_status = "✅ Pemilihan kelas berhasil disimpan!";
        } else {
            $message_status = "❌ Gagal menyimpan beberapa kelas: " . mysqli_error($conn);
        }
    } else {
        $message_status = "ℹ️ Anda belum memilih kelas.";
    }
}


// --- 3. Logika Logout ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: auth.php?action=login");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna</title>
    <link rel="stylesheet" href="style.css">
    <script>
        // JS untuk membatasi pemilihan 2 kelas
        function checkClassLimit() {
            const checkboxes = document.querySelectorAll('input[name="class_ids[]"]');
            let checkedCount = 0;
            checkboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });

            checkboxes.forEach(cb => {
                if (!cb.checked && checkedCount >= 2) {
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                }
            });
        }
    </script>
</head>
<body>
    <div class="form-container dashboard-container">
        <h2>Dashboard <?php echo ($user_role == 'admin') ? 'Admin' : 'Anggota'; ?></h2>
        <hr>

        <?php if (!empty($message_status)): ?>
            <p class="message-success"><?php echo $message_status; ?></p>
        <?php endif; ?>

        <?php if ($user_role == 'admin'): ?>
            <p class="welcome-message">
                👋 Selamat datang, <span class="user-name-highlight"><?php echo htmlspecialchars($user_name); ?></span>! (Peran: <?php echo $user_role; ?>)
            </p>
            <div class="admin-area-card">
                <h4>Area Administrasi</h4>
                <a href="users.php" class="submit-btn admin-link-btn">Manajemen Anggota</a>
                <a href="payments.php" class="submit-btn payment-link-btn">Manajemen Pembayaran</a>
                <a href="classes.php" class="submit-btn class-link-btn">Manajemen Sesi Gym</a>
            </div>

        <?php else: // Mulai blok Anggota ?>
            <div class="user-profile-header">
                <div class="profile-icon">👤</div> 
                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                <p class="user-email-text"><?php echo htmlspecialchars($user_details['email'] ?? 'N/A'); ?></p>
            </div>
            
            <div class="card">
                <h3>Status Membership Gym</h3>
                
                <?php 
                // Cek status Pembayaran pending
                $payment_status_text = '';
                if ($renewal_proof_id) {
                    $payment_query = mysqli_query($conn, "SELECT status FROM payments WHERE id = '$renewal_proof_id'");
                    $payment_row = mysqli_fetch_assoc($payment_query);
                    $payment_status_text = $payment_row['status'] ?? 'N/A';
                }
                ?>
                
                <?php if ($status_membership == 'Aktif' && $remaining_days <= 7 && $remaining_days > 0): ?>
                    <div class="alert-warning">
                        🔔 Membership Anda akan kedaluwarsa dalam **<?php echo $remaining_days; ?> hari**! Segera perpanjang.
                    </div>
                <?php endif; ?>
                
                <?php if ($status_membership == 'Kedaluwarsa'): ?>
                    <div class="alert-error">
                        ⚠️ Membership Anda telah **KEDALUWARSA**! Segera perpanjang untuk melanjutkan akses.
                    </div>
                <?php endif; ?>

                <p><strong>Tanggal Mendaftar:</strong> <?php echo htmlspecialchars($formatted_join ?? 'N/A'); ?></p>
                <p><strong>Kedaluwarsa:</strong> 
                    <span class="<?php echo ($status_membership == 'Aktif') ? 'status-active' : 'status-expired'; ?>">
                        <?php echo htmlspecialchars($formatted_expiry ?? 'N/A'); ?> (<?php echo $status_membership ?? 'N/A'; ?>)
                    </span>
                </p>

                <hr>
                
                <?php if ($renewal_proof_id && $payment_status_text == 'Pending'): ?>
                    <p class="message-success request-pending-text">
                        Permintaan perpanjangan Anda sedang diproses. Status Pembayaran: **<?php echo $payment_status_text; ?>**.
                    </p>
                <?php else: ?>
                    <a href="payments.php" class="submit-btn renewal-request-btn">
                        Lakukan Pembayaran & Ajukan Perpanjangan
                    </a>
                    <p class="form-hint-text">Anda harus membayar terlebih dahulu di halaman pembayaran sebelum perpanjangan dapat diproses.</p>
                <?php endif; ?>
            </div>
            
            <div class="card class-schedule-card">
                <h3>Pilih Kelas Mingguan (Maks. 2 Kelas)</h3>
                
                <?php
                    $classes_query = "SELECT id, name, trainer, schedule, max_capacity FROM classes ORDER BY schedule ASC";
                    $classes_result = mysqli_query($conn, $classes_query);
                    
                    // Ambil kelas yang sudah dipilih anggota saat ini
                    $current_classes_query = mysqli_query($conn, "SELECT class_id FROM user_classes WHERE user_id = '$user_id_safe'");
                    $current_classes = [];
                    while ($row = mysqli_fetch_assoc($current_classes_query)) {
                        $current_classes[] = $row['class_id'];
                    }
                ?>

                <?php if ($classes_result && mysqli_num_rows($classes_result) > 0): ?>
                    <form action="dashboard.php" method="POST">
                        <input type="hidden" name="select_classes" value="1">
                        <div class="schedule-list class-selection-grid">
                            <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                                <label class="class-checkbox-item">
                                    <input type="checkbox" 
                                           name="class_ids[]" 
                                           value="<?php echo htmlspecialchars($class['id']); ?>"
                                           onchange="checkClassLimit()"
                                           <?php echo in_array($class['id'], $current_classes) ? 'checked' : ''; ?>
                                           >
                                    <div class="schedule-item">
                                        <h4><?php echo htmlspecialchars($class['name']); ?></h4>
                                        <p>Trainer: <strong><?php echo htmlspecialchars($class['trainer']); ?></strong></p>
                                        <p class="schedule-time">⏰ <?php echo htmlspecialchars($class['schedule']); ?></p>
                                        <p><small>Kapasitas: <?php echo htmlspecialchars($class['max_capacity']); ?></small></p>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <p class="form-hint-text">Anda saat ini memilih **<?php echo count($current_classes); ?>** kelas.</p>
                        <button type="submit" class="submit-btn save-classes-btn">Simpan Pilihan Kelas</button>
                    </form>
                <?php else: ?>
                    <p class="alert-warning">Belum ada sesi/kelas yang dijadwalkan saat ini.</p>
                <?php endif; ?>
            </div>
            
        <?php endif; // Akhir blok Anggota/Else ?>

        <a href="dashboard.php?action=logout" class="submit-btn logout-btn">Logout</a>
    </div>

<?php
// >>> TUTUP KONEKSI HANYA SEKALI DI AKHIR FILE <<<
if (isset($conn)) { mysqli_close($conn); }
?>
</body>
</html>