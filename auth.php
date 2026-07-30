<?php

declare(strict_types=1);

require_once __DIR__ . '/database/database.php';

function startLogicAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function currentLogicUser(): ?array
{
    startLogicAdminSession();

    $user = $_SESSION['logic_user'] ?? null;

    return is_array($user) ? $user : null;
}

function currentLogicUserIsAdmin(): bool
{
    $user = currentLogicUser();

    return ($user['role'] ?? '') === 'admin';
}

function loginLogicUser($database, string $username, string $password): bool
{
    if ($database instanceof LogicSeedStore) {
        if (!$database->verifyAdminLogin($username, $password)) {
            return false;
        }

        startLogicAdminSession();
        session_regenerate_id(true);
        $_SESSION['logic_user'] = [
            'id' => 1,
            'username' => 'admin',
            'role' => 'admin',
        ];

        return true;
    }

    $statement = $database->prepare(
        'SELECT id, username, password_hash, role
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $statement->execute([':username' => $username]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    startLogicAdminSession();
    session_regenerate_id(true);
    $_SESSION['logic_user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
    ];

    return true;
}

function logoutLogicUser(): void
{
    startLogicAdminSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly'],
        );
    }

    session_destroy();
}
