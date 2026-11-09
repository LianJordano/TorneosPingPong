<?php

declare(strict_types=1);

use PingPong\Controller\AuditoriaController;
use PingPong\Controller\AuthController;
use PingPong\Controller\AyudaController;
use PingPong\Controller\CatalogoController;
use PingPong\Controller\ConfiguracionController;
use PingPong\Controller\CuentaController;
use PingPong\Controller\DashboardController;
use PingPong\Controller\EliminatoriaController;
use PingPong\Controller\ErrorController;
use PingPong\Controller\InscripcionController;
use PingPong\Controller\LigaController;
use PingPong\Controller\PersonaController;
use PingPong\Controller\PremioController;
use PingPong\Controller\PublicoController;
use PingPong\Controller\RankingController;
use PingPong\Controller\TorneoController;
use PingPong\Controller\UsuarioController;
use PingPong\Database\Connection;
use PingPong\Http\Router;
use PingPong\Repository\AreaRepository;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\EstadisticaJugadorRepository;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\NivelRepository;
use PingPong\Repository\PartidoRepository;
use PingPong\Repository\PersonaRepository;
use PingPong\Repository\PremioRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Repository\UsuarioRepository;
use PingPong\Service\CargaMasivaJugadoresService;
use PingPong\Service\JugadorUsuarioService;
use PingPong\Service\RankingHistoricoService;
use PingPong\Support\Session;
use PingPong\Support\View;

// Servidor embebido de PHP: servir archivos estáticos existentes tal cual.
// Devolver false delega en el servidor para que entregue el archivo directo.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
    if (is_file($requested)) {
        return false;
    }
}

require dirname(__DIR__) . '/config/bootstrap.php';

Session::start();

// --- Dependencias (composición manual; sin contenedor mágico) ---
$pdo  = Connection::pdo();
$view = new View(dirname(__DIR__) . '/views');

$usuarios      = new UsuarioRepository($pdo);
$personas      = new PersonaRepository($pdo);
$torneos       = new TorneoRepository($pdo);
$inscripciones = new InscripcionRepository($pdo);
$partidos      = new PartidoRepository($pdo);
$premios       = new PremioRepository($pdo);
$auditoria     = new AuditoriaRepository($pdo);
$areas         = new AreaRepository($pdo);
$niveles       = new NivelRepository($pdo);
$estadisticas  = new EstadisticaJugadorRepository($pdo);

$rankingHistorico = new RankingHistoricoService($estadisticas);
$cargaJugadores   = new CargaMasivaJugadoresService($personas, $areas, $niveles);
$jugadorUsuario   = new JugadorUsuarioService($personas, $usuarios);

$auth        = new AuthController($view, $usuarios);
$dashboard   = new DashboardController($view, $torneos, $personas);
$personasCtl = new PersonaController($view, $personas, $areas, $niveles, $rankingHistorico, $usuarios, $jugadorUsuario);
$cuentaCtl   = new CuentaController($view, $usuarios);
$areasCtl    = new CatalogoController($view, $areas, 'Áreas', 'área', '/areas');
$nivelesCtl  = new CatalogoController($view, $niveles, 'Niveles', 'nivel', '/niveles');
$configCtl   = new ConfiguracionController($view, $areas, $niveles, $cargaJugadores);
$torneosCtl  = new TorneoController($view, $torneos, $inscripciones, $auditoria);
$inscCtl     = new InscripcionController($view, $torneos, $inscripciones, $personas);
$ligaCtl     = new LigaController($view, $torneos, $inscripciones, $partidos, $auditoria);
$elimCtl     = new EliminatoriaController($view, $torneos, $inscripciones, $partidos, $auditoria);
$premioCtl   = new PremioController($view, $torneos, $premios, $inscripciones, $partidos);
$publicoCtl  = new PublicoController($view, $torneos, $inscripciones, $partidos);
$auditCtl    = new AuditoriaController($view, $torneos, $auditoria);
$ayudaCtl    = new AyudaController($view);
$rankingCtl  = new RankingController($view, $rankingHistorico);
$usuariosCtl = new UsuarioController($view, $usuarios);

// --- Rutas ---
$router = new Router();
$errorCtl = new ErrorController($view);
$router->fallback(static fn (int $status): string => $errorCtl->show($status));

// Autenticación
$router->get('/login',  static fn (): string => $auth->showLogin());
$router->post('/login', static fn (): string => $auth->login());
$router->post('/logout', static fn (): string => $auth->logout());

// Panel
$router->get('/', static fn (): string => $dashboard->index());

// Ranking historico
$router->get('/ranking', static fn (): string => $rankingCtl->index());

// Glosario / ayuda
$router->get('/glosario', static fn (): string => $ayudaCtl->glosario());

// Configuración del sistema
$router->get('/configuracion', static fn (): string => $configCtl->index());
$router->get('/configuracion/jugadores/plantilla', static fn (): string => $configCtl->plantillaJugadores());
$router->post('/configuracion/jugadores/importar', static fn (): string => $configCtl->importarJugadores());
$router->get('/configuracion/usuarios', static fn (): string => $usuariosCtl->index());
$router->post('/configuracion/usuarios/{id}/rol', static fn (array $p): string => $usuariosCtl->actualizarRol($p));

// Personas (jugadores)
$router->get('/personas',                   static fn (): string => $personasCtl->index());
$router->get('/personas/nueva',             static fn (): string => $personasCtl->create());
// IMPORTANTE: la conversión en bloque debe declararse ANTES de POST /personas/{id},
// si no el comodín {id} captura "convertir-usuarios" y nunca llega acá.
$router->post('/personas/convertir-usuarios', static fn (): string => $personasCtl->convertirUsuariosLote());
$router->get('/personas/{id}',              static fn (array $p): string => $personasCtl->show($p));
$router->post('/personas',                  static fn (): string => $personasCtl->store());
$router->get('/personas/{id}/editar',       static fn (array $p): string => $personasCtl->edit($p));
$router->post('/personas/{id}',             static fn (array $p): string => $personasCtl->update($p));
$router->post('/personas/{id}/convertir-usuario', static fn (array $p): string => $personasCtl->convertirUsuario($p));
$router->post('/personas/{id}/desactivar',  static fn (array $p): string => $personasCtl->desactivar($p));
$router->post('/personas/{id}/activar',     static fn (array $p): string => $personasCtl->activar($p));

// Mi cuenta (cualquier rol logueado, incluido ESPECTADOR)
$router->get('/cuenta/password',  static fn (): string => $cuentaCtl->editarPassword());
$router->post('/cuenta/password', static fn (): string => $cuentaCtl->actualizarPassword());

// Catálogos: áreas y niveles
$router->get('/areas',                static fn (): string => $areasCtl->index());
$router->post('/areas',               static fn (): string => $areasCtl->store());
$router->post('/areas/{id}',          static fn (array $p): string => $areasCtl->update($p));
$router->post('/areas/{id}/eliminar', static fn (array $p): string => $areasCtl->delete($p));
$router->get('/niveles',                static fn (): string => $nivelesCtl->index());
$router->post('/niveles',               static fn (): string => $nivelesCtl->store());
$router->post('/niveles/{id}',          static fn (array $p): string => $nivelesCtl->update($p));
$router->post('/niveles/{id}/eliminar', static fn (array $p): string => $nivelesCtl->delete($p));

// Torneos
$router->get('/torneos',                static fn (): string => $torneosCtl->index());
$router->get('/torneos/nuevo',          static fn (): string => $torneosCtl->create());
$router->post('/torneos',               static fn (): string => $torneosCtl->store());
$router->get('/torneos/{id}',           static fn (array $p): string => $torneosCtl->show($p));
$router->get('/torneos/{id}/editar',    static fn (array $p): string => $torneosCtl->edit($p));
$router->post('/torneos/{id}',          static fn (array $p): string => $torneosCtl->update($p));
$router->post('/torneos/{id}/estado',   static fn (array $p): string => $torneosCtl->cambiarEstado($p));
$router->post('/torneos/{id}/duplicar', static fn (array $p): string => $torneosCtl->duplicar($p));
$router->post('/torneos/{id}/archivar', static fn (array $p): string => $torneosCtl->archivar($p));
$router->post('/torneos/{id}/eliminar', static fn (array $p): string => $torneosCtl->delete($p));

// Inscripciones (anidadas en el torneo)
$router->post('/torneos/{id}/inscripciones',                       static fn (array $p): string => $inscCtl->inscribir($p));
$router->post('/torneos/{id}/sortear-parejas',                     static fn (array $p): string => $inscCtl->sortearParejas($p));
$router->post('/torneos/{id}/inscripciones/{inscripcion}/eliminar', static fn (array $p): string => $inscCtl->desinscribir($p));
$router->post('/torneos/{id}/inscripciones/{inscripcion}/pago',     static fn (array $p): string => $inscCtl->togglePago($p));

// Liga: fixture, partidos, marcadores y tabla
$router->post('/torneos/{id}/fixture',                   static fn (array $p): string => $ligaCtl->generarFixture($p));
$router->get('/torneos/{id}/partidos',                   static fn (array $p): string => $ligaCtl->partidos($p));
$router->get('/torneos/{id}/anotador',                   static fn (array $p): string => $ligaCtl->anotador($p));
$router->get('/torneos/{id}/partidos/{partido}',         static fn (array $p): string => $ligaCtl->marcadorForm($p));
$router->post('/torneos/{id}/partidos/{partido}',        static fn (array $p): string => $ligaCtl->guardarMarcador($p));
$router->get('/torneos/{id}/tabla',                      static fn (array $p): string => $ligaCtl->tabla($p));

// Eliminatoria: bracket y campeón
$router->post('/torneos/{id}/bracket',   static fn (array $p): string => $elimCtl->promover($p));
$router->get('/torneos/{id}/bracket',    static fn (array $p): string => $elimCtl->bracket($p));
$router->post('/torneos/{id}/finalizar', static fn (array $p): string => $elimCtl->finalizar($p));

// Premiación y podio
$router->get('/torneos/{id}/premios',  static fn (array $p): string => $premioCtl->form($p));
$router->post('/torneos/{id}/premios', static fn (array $p): string => $premioCtl->guardar($p));
$router->get('/torneos/{id}/podio',    static fn (array $p): string => $premioCtl->podio($p));

// Exportación CSV
$router->get('/torneos/{id}/tabla.csv',    static fn (array $p): string => $ligaCtl->tablaCsv($p));
$router->get('/torneos/{id}/partidos.csv', static fn (array $p): string => $ligaCtl->partidosCsv($p));

// Auditoría
$router->get('/torneos/{id}/auditoria', static fn (array $p): string => $auditCtl->ver($p));

// Vista pública (sin autenticación)
$router->get('/publico/torneos/{id}', static fn (array $p): string => $publicoCtl->ver($p));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
