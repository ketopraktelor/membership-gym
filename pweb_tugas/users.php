<?php
// users.php (User Management CRUD - Dashboard Admin)

include 'config.php';
session_start();

// --- PENGAMANAN HANYA UNTUK ADMIN ---
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') != 'admin') {
    header("Location: dashboard.php");
    exit();
}
// ------------------------------------

$message = '';
$action_success = false;
$user_to_edit = null;

// --- LOGIKA DELETE YANG HILANG (DIPERBAIKI) ---
if (isset($_GET['delete_id'])) {
    // Pastikan koneksi $conn masih aktif dari 'config.php'
    $id_to_delete = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    // Query DELETE
    $delete_query = "DELETE FROM users WHERE id = '$id_to_delete'";
    
    if (mysqli_query($conn, $delete_query)) {
        // Redirect setelah sukses agar tampilan tabel diperbarui dan menghilangkan parameter GET
        header("Location: users.php?status=deleted");
        exit();
    } else {
        // Jika gagal (kemungkinan masalah Foreign Key atau izin)
        // Walaupun Anda sudah set CASCADE, error bisa terjadi jika ada masalah lain
        $message = "Gagal menghapus pengguna: " . mysqli_error($conn);
        $action_success = false;
        // Lanjutkan eksekusi tanpa redirect agar pesan error tampil
    }
}
// --------------------------------------------

// --- LOGIKA EDIT FORM (Mengambil data) ---
if (isset($_GET['edit_id'])) {
    $id_to_edit = mysqli_real_escape_string($conn, $_GET['edit_id']);
    $edit_query = "SELECT id, name, email, role, expiry_date FROM users WHERE id = '$id_to_edit'"; 
    $result_edit = mysqli_query($conn, $edit_query);
    
    if ($result_edit && mysqli_num_rows($result_edit) == 1) {
        $user_to_edit = mysqli_fetch_assoc($result_edit);
    } else {
        $message = "Pengguna tidak ditemukan.";
    }
}

// --- LOGIKA UPDATE DATA (Perpanjangan Durasi) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $id_update = mysqli_real_escape_string($conn, $_POST['user_id']);
    $name_update = mysqli_real_escape_string($conn, $_POST['name']);
    $email_update = mysqli_real_escape_string($conn, $_POST['email']);
    $role_update = mysqli_real_escape_string($conn, $_POST['role']);
    $password_update = $_POST['password'];
    $duration_add = mysqli_real_escape_string($conn, $_POST['duration']); 

    $update_parts = [];
    $update_parts[] = "name = '$name_update'";
    $update_parts[] = "email = '$email_update'";
    $update_parts[] = "role = '$role_update'";

    // --- LOGIKA PENGHITUNGAN TANGGAL KEDALUWARSA BARU ---
    $current_expiry_query = "SELECT expiry_date FROM users WHERE id = '$id_update'";
    $current_expiry_result = mysqli_query($conn, $current_expiry_query);
    $current_expiry_row = mysqli_fetch_assoc($current_expiry_result);
    $current_expiry_date = $current_expiry_row['expiry_date'];
    
    $base_date = (strtotime($current_expiry_date) > time()) ? $current_expiry_date : date('Y-m-d');

    $new_expiry_date = date('Y-m-d', strtotime($base_date . " + {$duration_add}"));
    
    $update_parts[] = "expiry_date = '$new_expiry_date'"; 
    $update_parts[] = "renewal_request = FALSE"; 
    // --------------------------------------------------------

    if (!empty($password_update)) {
        $password_hash = password_hash($password_update, PASSWORD_DEFAULT);
        $update_parts[] = "password_hash = '$password_hash'";
    }

    $update_query = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE id = '$id_update'";

    if (mysqli_query($conn, $update_query)) {
        if ($id_update == $_SESSION['user_id']) {
            $_SESSION['user_role'] = $role_update;
        }
        header("Location: users.php?status=updated");
        exit();
    } else {
        $message = "Gagal memperbarui pengguna: " . mysqli_error($conn);
    }
}

// Cek status pesan dari redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') {
        $message = "Pengguna berhasil dihapus.";
        $action_success = true;
    } elseif ($_GET['status'] == 'updated') {
        $message = "Pengguna berhasil diperbarui dan durasi membership telah diperpanjang.";
        $action_success = true;
    }
}

// --- LOGIKA TAMPILKAN SEMUA PENGGUNA ---
$users_query = "SELECT id, name, email, role, join_date, expiry_date, renewal_request FROM users ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_query);

// Menutup koneksi database sebelum output HTML (Wajib jika tidak ada logic DB lagi)
if (isset($conn)) {
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna (Admin)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <a href="dashboard.php" class="back-btn submit-btn">← Kembali ke Dashboard</a>
        <h2>Manajemen Anggota Gym</h2>
        <hr>

        <?php if (!empty($message)): ?>
            <p class="<?php echo $action_success ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <?php if ($user_to_edit): ?>
            <div class="form-container edit-user-form-container">
                <h3>Edit Anggota: <?php echo htmlspecialchars($user_to_edit['name']); ?> (#<?php echo htmlspecialchars($user_to_edit['id']); ?>)</h3>
                
                <p class="current-expiry-info">
                    **Kedaluwarsa Saat Ini:** <span class="status-expired-highlight"><?php echo htmlspecialchars($user_to_edit['expiry_date']); ?></span>
                </p>

                <form action="users.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
                    <input type="hidden" name="update_user" value="1">
                    
                    <div class="form-group">
                        <label for="name">Nama:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_to_edit['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="duration">Perpanjang Durasi Membership:</label>
                        <select id="duration" name="duration" class="form-control">
                            <option value="0 days">-- Pilih Durasi --</option>
                            <option value="1 month">1 Bulan</option>
                            <option value="3 months">3 Bulan</option>
                            <option value="6 months">6 Bulan</option>
                            <option value="1 year">1 Tahun</option>
                        </select>
                        <p class="form-hint-text">*Durasi akan ditambahkan dari tanggal kedaluwarsa saat ini (atau dari hari ini jika sudah expired).</p>
                    </div>

                    <div class="form-group">
                        <label for="role">Peran (Role):</label>
                        <select id="role" name="role" class="form-control">
                            <option value="user" <?php echo ($user_to_edit['role'] == 'user') ? 'selected' : ''; ?>>User Biasa</option>
                            <option value="admin" <?php echo ($user_to_edit['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password">Password Baru (Kosongkan jika tidak ingin diubah):</label>
                        <input type="password" id="password" name="password">
                    </div>
                    
                    <button type="submit" class="submit-btn save-changes-btn">Simpan Perubahan & Proses Perpanjangan</button>
                    <a href="users.php" class="submit-btn cancel-edit-btn">Batal Edit</a>
                </form>
            </div>
            <hr>
        <?php endif; ?>

        <h3>Daftar Anggota Terdaftar</h3>
        
        <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th>Mendaftar</th>
                            <th>Kedaluwarsa</th> 
                            <th>Sisa Hari</th>
                            <th>Status Permintaan</th> 
                            <th>Aksi</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><span class="<?php echo ($row['role'] == 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo ucwords($row['role']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['join_date']); ?></td>
                            <td>
                                <?php 
                                    $expiry_date = htmlspecialchars($row['expiry_date']);
                                    $is_expired = strtotime($expiry_date) < strtotime(date('Y-m-d'));
                                    $expiry_class = $is_expired ? 'status-expired-highlight' : 'status-active';
                                ?>
                                <span class="<?php echo $expiry_class; ?>">
                                    <?php echo $expiry_date; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                    $today = time();
                                    $expiry_time = strtotime($row['expiry_date']);
                                    $remaining_days_seconds = $expiry_time - $today;
                                    $remaining_days = floor($remaining_days_seconds / (60 * 60 * 24));

                                    if ($remaining_days > 0) {
                                        $day_class = ($remaining_days <= 30) ? 'days-warning' : 'days-safe';
                                        echo '<span class="' . $day_class . '">' . $remaining_days . ' Hari</span>';
                                    } else {
                                        echo '<span class="days-expired">EXPIRED</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php if ($row['renewal_request']): ?>
                                    <span class="request-new-alert">
                                        ⚠️ PERMINTAAN BARU
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="users.php?edit_id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit (Perpanjang)</a>
                                <a href="users.php?delete_id=<?php echo htmlspecialchars($row['id']); ?>" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?php echo htmlspecialchars($row['name']); ?>?')" 
                                   class="action-btn delete-btn">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>  
        <?php else: ?>
            <p>Belum ada pengguna terdaftar.</p>
        <?php endif; ?>

    </div>
</body>
</html>