<?php
require __DIR__ . "/_response.php";
require __DIR__ . "/_config.php";

$method = $_SERVER["REQUEST_METHOD"];
$id = isset($_GET["id"]) ? (int)$_GET["id"] : null;

// GET: list atau detail
if ($method === "GET") {
  if ($id) {
    $stmt = mysqli_prepare($conn, "SELECT id, name, email, role, join_date, expiry_date, renewal_request, created_at, renewal_proof FROM users WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    if (!$row) err("User not found", 404);
    ok($row);
  } else {
    $q = "SELECT id, name, email, created_at FROM users ORDER BY id DESC"; // sama seperti admin list:contentReference[oaicite:8]{index=8}
    $rows = mysqli_fetch_all(mysqli_query($conn, $q), MYSQLI_ASSOC);
    ok($rows);
  }
}

// POST: create user (butuh password)
if ($method === "POST") {
  $b = json_body();
  $name = trim($b["name"] ?? "");
  $email = trim($b["email"] ?? "");
  $password = $b["password"] ?? null;
  $role = $b["role"] ?? "user";

  if ($name === "" || $email === "" || !$password) err("name, email, password wajib");

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)");
  mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hash, $role);

  try {
    mysqli_stmt_execute($stmt);
  } catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), "Duplicate")) err("Email sudah terdaftar", 409);
    throw $e;
  }

  ok(["message" => "created", "id" => mysqli_insert_id($conn)], 201);
}

// PUT: update user (password optional seperti di admin):contentReference[oaicite:9]{index=9}
if ($method === "PUT") {
  if (!$id) err("id wajib", 400);
  $b = json_body();

  $name = trim($b["name"] ?? "");
  $email = trim($b["email"] ?? "");
  $password = $b["password"] ?? "";

  if ($name === "" || $email === "") err("name dan email wajib");

  if ($password !== "") {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, password_hash=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $hash, $id);
  } else {
    $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $id);
  }

  mysqli_stmt_execute($stmt);
  ok(["message" => "updated", "id" => $id]);
}

// DELETE
if ($method === "DELETE") {
  if (!$id) err("id wajib", 400);
  $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?"); // sama seperti delete admin:contentReference[oaicite:10]{index=10}
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  if (mysqli_affected_rows($conn) === 0) err("User not found", 404);
  ok(["message" => "deleted", "id" => $id]);
}

err("Method not allowed", 405);
