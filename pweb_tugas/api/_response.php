<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
http_response_code(204);
exit;
}

function json_body() {
$raw = file_get_contents("php://input");
if (!$raw) return [];
$data = json_decode($raw, true);
return is_array($data) ? $data : [];
}

function ok($data, $code = 200) {
http_response_code($code);
echo json_encode($data);
exit;
}

function err($message, $code = 400, $extra = []) {
http_response_code($code);
echo json_encode(array_merge(["error" => $message], $extra));
exit;
}
