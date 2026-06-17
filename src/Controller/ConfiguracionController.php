<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\AreaRepository;
use PingPong\Repository\NivelRepository;
use PingPong\Service\CargaMasivaJugadoresService;
use PingPong\Support\Session;
use PingPong\Support\View;

final class ConfiguracionController extends Controller
{
    public function __construct(
        View $view,
        private AreaRepository $areas,
        private NivelRepository $niveles,
        private CargaMasivaJugadoresService $cargaJugadores,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }

        // Resultado de la última carga masiva (se muestra una sola vez).
        $resultadoCarga = Session::get('carga_jugadores_resultado');
        Session::forget('carga_jugadores_resultado');

        return $this->render('configuracion/index', [
            'title' => 'Configuración',
            'totalAreas' => count($this->areas->all()),
            'totalNiveles' => count($this->niveles->all()),
            'resultadoCarga' => is_array($resultadoCarga) ? $resultadoCarga : null,
        ]);
    }

    /** Descarga la plantilla .xlsx para la carga masiva de jugadores. */
    public function plantillaJugadores(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_jugadores.xlsx"');
        header('Cache-Control: max-age=0');
        $this->cargaJugadores->escribirPlantilla('php://output');

        return '';
    }

    /** Procesa el .xlsx subido y crea/actualiza jugadores. */
    public function importarJugadores(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/configuracion');
        }

        $archivo = $_FILES['archivo'] ?? null;
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Subí un archivo .xlsx válido para procesar.');
            return $this->redirect('/configuracion');
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            Session::flash('error', 'El archivo debe ser un Excel (.xlsx). Descargá la plantilla si tenés dudas del formato.');
            return $this->redirect('/configuracion');
        }

        try {
            $resumen = $this->cargaJugadores->importar((string) $archivo['tmp_name']);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo leer el archivo. Verificá que sea un Excel válido y respete la plantilla.');
            return $this->redirect('/configuracion');
        }

        Session::set('carga_jugadores_resultado', $resumen);

        $errores = count($resumen['errores']);
        $mensaje = sprintf(
            '%d creados, %d actualizados.',
            $resumen['creados'],
            $resumen['actualizados'],
        );
        if ($errores > 0) {
            Session::flash('error', $mensaje . ' ' . $errores . ' fila(s) con problemas (ver detalle abajo).');
        } else {
            Session::flash('success', 'Carga completada: ' . $mensaje);
        }

        return $this->redirect('/configuracion');
    }
}
