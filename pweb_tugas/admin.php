<?php
// users.php (User Management CRUD)

include 'config.php';
session_start();



// Catatan: Dalam aplikasi nyata, Anda harus menambahkan cek otorisasi
// di sini untuk memastikan hanya Administrator yang bisa mengakses halaman ini.

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$message = '';
$action_success = false;
$user_to_edit = null;

// --- 1. LOGIKA HAPUS (DELETE) ---
if (isset($_GET['delete_id'])) {
    $id_to_delete = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    // Query Hapus
    $delete_query = "DELETE FROM users WHERE id = '$id_to_delete'";
    
    if (mysqli_query($conn, $delete_query)) {
        $message = "Pengguna dengan ID #$id_to_delete berhasil dihapus.";
        $action_success = true;
        // Redirect untuk menghilangkan parameter GET dari URL setelah aksi
        header("Location: users.php?status=deleted");
        exit();
    } else {
        $message = "Gagal menghapus pengguna: " . mysqli_error($conn);
    }
}

// --- 2. LOGIKA EDIT FORM (READ Single User) ---
if (isset($_GET['edit_id'])) {
    $id_to_edit = mysqli_real_escape_string($conn, $_GET['edit_id']);
    
    // Query Ambil data user
    $edit_query = "SELECT id, name, email FROM users WHERE id = '$id_to_edit'";
    $result_edit = mysqli_query($conn, $edit_query);
    
    if (mysqli_num_rows($result_edit) == 1) {
        $user_to_edit = mysqli_fetch_assoc($result_edit);
    } else {
        $message = "Pengguna tidak ditemukan.";
    }
}

// --- 3. LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $id_update = mysqli_real_escape_string($conn, $_POST['user_id']);
    $name_update = mysqli_real_escape_string($conn, $_POST['name']);
    $email_update = mysqli_real_escape_string($conn, $_POST['email']);
    $password_update = $_POST['password'];

    $update_parts = [];
    $update_parts[] = "name = '$name_update'";
    $update_parts[] = "email = '$email_update'";

    // Jika password diisi, hash dan masukkan ke query
    if (!empty($password_update)) {
        $password_hash = password_hash($password_update, PASSWORD_DEFAULT);
        $update_parts[] = "password_hash = '$password_hash'";
    }

    // Gabungkan bagian-bagian update
    $update_query = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE id = '$id_update'";

    if (mysqli_query($conn, $update_query)) {
        $message = "Pengguna **$name_update** berhasil diperbarui.";
        $action_success = true;
        // Redirect setelah update
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
        $message = "Pengguna berhasil diperbarui.";
        $action_success = true;
    }
}

// --- 4. LOGIKA TAMPILKAN SEMUA PENGGUNA (READ All) ---
$users_query = "SELECT id, name, email, created_at FROM users ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_query);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Gaya tambahan khusus untuk halaman ini */
        .page-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
        }
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .edit-btn {
            background-color: #ffc107;
            color: #333;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        /* Style untuk tombol kembali ke dashboard */
        .back-btn {
            margin-bottom: 20px;
            background-color: #6c757d; /* Abu-abu netral */
            color: white;
            padding: 10px 15px;
            display: inline-block;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <a href="dashboard.php" class="back-btn">← Kembali ke Dashboard</a>
        <h2>Manajemen Pengguna (CRUD)</h2>
        <hr>

        <?php if (!empty($message)): ?>
            <p class="<?php echo $action_success ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <?php if ($user_to_edit): ?>
            <div class="form-container" style="max-width: 100%; margin-bottom: 30px; padding: 25px;">
                <h3>Edit Pengguna: #<?php echo htmlspecialchars($user_to_edit['id']); ?></h3>
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
                        <label for="password">Password Baru (Kosongkan jika tidak ingin diubah):</label>
                        <input type="password" id="password" name="password">
                    </div>
                    
                    <button type="submit" class="submit-btn" style="background-color: #28a745;">Simpan Perubahan</button>
                    <a href="users.php" class="submit-btn" style="background-color: #6c757d; margin-top: 10px;">Batal</a>
                </form>
            </div>
            <hr>
        <?php endif; ?>

        <h3>Daftar Pengguna Terdaftar</h3>
        
        <?php if (mysqli_num_rows($users_result) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Terdaftar Sejak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <a href="users.php?edit_id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit</a>
                                <a href="users.php?delete_id=<?php echo htmlspecialchars($row['id']); ?>" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')" 
                                   class="action-btn delete-btn">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Belum ada pengguna terdaftar.</p>
        <?php endif; ?>

    </div>
</body>
</html>