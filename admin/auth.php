<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function esAdminReal(): bool {
    return !empty($_SESSION['es_admin_real'])
        || (int)($_SESSION['tipo_usuario'] ?? 0) === 4
        || (!empty($_SESSION['es_admin']) && empty($_SESSION['es_oficinista']));
}

function esOficinista(): bool {
    return !empty($_SESSION['es_oficinista']) || (int)($_SESSION['tipo_usuario'] ?? 0) === 3;
}

function requireBackofficePage(): void {
    if (empty($_SESSION['es_admin'])) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdminRealPage(): void {
    requireBackofficePage();
    if (!esAdminReal()) {
        header('Location: postulantes.php');
        exit;
    }
}

function requireBackofficeApi(): void {
    if (empty($_SESSION['es_admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
}

function requireAdminRealApi(): void {
    requireBackofficeApi();
    if (!esAdminReal()) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado para este rol']);
        exit;
    }
}
