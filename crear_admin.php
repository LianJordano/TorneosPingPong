<?php

declare(strict_types=1);

/**
 * Generador del usuario administrador.
 *
 * Uso (desde la raíz del proyecto):
 *   php crear_admin.php
 *   php crear_admin.php admin@pingpong.local "miClaveSegura" "Nombre Admin"
 *
 * Si el email ya existe, lo promueve a ADMIN y le resetea la contraseña.
 * SOLO se puede ejecutar desde la línea de comandos.
 */

use PingPong\Database\Connection;
use PingPong\Domain\Rol;
use PingPong\Repository\UsuarioRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

require __DIR__ . '/config/bootstrap.php';

/** Lee un valor por consola, con valor por defecto opcional. */
function preguntar(string $etiqueta, ?string $defecto = null): string
{
    $sufijo = $defecto !== null ? " [{$defecto}]" : '';
    fwrite(STDOUT, $etiqueta . $sufijo . ': ');
    $linea = fgets(STDIN);
    $valor = $linea === false ? '' : trim($linea);
    return ($valor === '' && $defecto !== null) ? $defecto : $valor;
}

// Argumentos posicionales o, si faltan, modo interactivo.
$email    = isset($argv[1]) ? trim($argv[1]) : preguntar('Email del administrador');
$password = isset($argv[2]) ? $argv[2] : preguntar('Contraseña (mínimo 8 caracteres)');
$nombre   = isset($argv[3]) ? trim($argv[3]) : preguntar('Nombre', 'Administrador');

$email  = strtolower(trim($email));
$nombre = trim($nombre) !== '' ? trim($nombre) : 'Administrador';

$errores = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email no es válido.';
}
if (strlen($password) < 8) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
}

if ($errores !== []) {
    fwrite(STDERR, "\n[X] No se pudo crear el administrador:\n");
    foreach ($errores as $error) {
        fwrite(STDERR, '  - ' . $error . "\n");
    }
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$usuarios = new UsuarioRepository(Connection::pdo());

$existente = $usuarios->findByEmail($email);
if ($existente !== null) {
    $usuarios->actualizarCredenciales((int) $existente['id'], $nombre, $hash, Rol::ADMIN->value);
    fwrite(STDOUT, "\n[OK] Usuario existente actualizado y promovido a ADMIN: {$email}\n");
} else {
    $usuarios->crear($nombre, $email, $hash, Rol::ADMIN->value);
    fwrite(STDOUT, "\n[OK] Administrador creado: {$email}\n");
}

fwrite(STDOUT, "     Iniciá sesión en /login con ese email y la contraseña indicada.\n");
exit(0);
