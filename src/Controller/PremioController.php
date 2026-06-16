<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\PodioCalculator;
use PingPong\Domain\TablaPosiciones;
use PingPong\Http\Controller;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\PartidoRepository;
use PingPong\Repository\PremioRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\Session;
use PingPong\Support\View;

final class PremioController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private PremioRepository $premios,
        private InscripcionRepository $inscripciones,
        private PartidoRepository $partidos,
    ) {
        parent::__construct($view);
    }

    /** Formulario de distribución del premio (porcentajes por posición). */
    public function form(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }

        return $this->render('torneos/premios', [
            'title'   => 'Premios · ' . $torneo['nombre'],
            'torneo'  => $torneo,
            'premios' => $this->premios->porTorneo((int) $torneo['id']),
            'pozo'    => $this->pozo($torneo),
            'errores' => [],
        ]);
    }

    public function guardar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        if ($torneo === null) {
            return $this->notFound();
        }
        $back = '/torneos/' . $id . '/premios';
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }

        $posiciones = (int) $torneo['posiciones_premiadas'];
        $entrada = $_POST['porcentaje'] ?? [];
        $porcentajes = [];
        $errores = [];
        $suma = 0.0;

        for ($pos = 1; $pos <= $posiciones; $pos++) {
            $valor = trim((string) ($entrada[$pos] ?? ''));
            if ($valor === '' || !is_numeric($valor) || (float) $valor < 0) {
                $errores[$pos] = 'Ingresá un porcentaje válido (≥ 0).';
                continue;
            }
            $porcentajes[$pos] = round((float) $valor, 2);
            $suma += $porcentajes[$pos];
        }

        if ($errores === [] && round($suma, 2) !== 100.0) {
            $errores[0] = 'Los porcentajes deben sumar exactamente 100% (suman ' . rtrim(rtrim(number_format($suma, 2), '0'), '.') . '%).';
        }

        if ($errores !== []) {
            return $this->render('torneos/premios', [
                'title'   => 'Premios · ' . $torneo['nombre'],
                'torneo'  => $torneo,
                'premios' => $this->premios->porTorneo($id),
                'pozo'    => $this->pozo($torneo),
                'errores' => $errores,
                'old'     => $entrada,
            ]);
        }

        $this->premios->reemplazarPorcentajes($id, $porcentajes);
        Session::flash('success', 'Distribución de premios guardada.');
        return $this->redirect('/torneos/' . $id . '/premios');
    }

    /** Podio final con la distribución del premio. */
    public function podio(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }
        $id = (int) $torneo['id'];

        [$podio, $pozo, $premios] = $this->construirPodio($torneo);

        return $this->render('torneos/podio', [
            'title'   => 'Podio · ' . $torneo['nombre'],
            'torneo'  => $torneo,
            'podio'   => $podio,
            'pozo'    => $pozo,
            'finalizado' => $torneo['estado'] === EstadoTorneo::FINALIZADO->value,
        ]);
    }

    /**
     * Construye el podio con etiquetas y montos de premio.
     *
     * @param array<string,mixed> $torneo
     * @return array{0:list<array<string,mixed>>,1:float,2:array<int,mixed>}
     */
    private function construirPodio(array $torneo): array
    {
        $id = (int) $torneo['id'];
        $inscripciones = $this->inscripciones->porTorneo($id);

        $labels = [];
        $jugadores = [];
        foreach ($inscripciones as $i) {
            $labels[(int) $i['id']] = $i['label'];
            $jugadores[] = ['id' => (int) $i['id'], 'nombre' => $labels[(int) $i['id']]];
        }

        $tabla = TablaPosiciones::calcular($jugadores, $this->partidos->ligaAgregada($id), (int) $torneo['puntos_por_victoria']);
        $posicionLiga = [];
        foreach ($tabla as $fila) {
            $posicionLiga[(int) $fila['insc_id']] = (int) $fila['posicion'];
        }

        $bracketRaw = $this->partidos->bracket($id);
        $totalRondas = 0;
        $bracket = [];
        foreach ($bracketRaw as $p) {
            $totalRondas = max($totalRondas, (int) $p['ronda']);
            $bracket[] = [
                'a'       => $p['inscripcion_a_id'] !== null ? (int) $p['inscripcion_a_id'] : null,
                'b'       => $p['inscripcion_b_id'] !== null ? (int) $p['inscripcion_b_id'] : null,
                'ganador' => $p['ganador_inscripcion_id'] !== null ? (int) $p['ganador_inscripcion_id'] : null,
                'ronda'   => (int) $p['ronda'],
                'estado'  => $p['estado'],
            ];
        }

        $orden = $bracket === [] ? [] : PodioCalculator::calcular($bracket, $totalRondas, $posicionLiga);
        $pozo = $this->pozo($torneo);
        $premios = $this->premios->porTorneo($id);

        $podio = [];
        foreach ($orden as $fila) {
            $pos = $fila['posicion'];
            $pct = isset($premios[$pos]['porcentaje']) ? (float) $premios[$pos]['porcentaje'] : null;
            $montoConfig = isset($premios[$pos]['monto']) && $premios[$pos]['monto'] !== null ? (float) $premios[$pos]['monto'] : null;
            $monto = $montoConfig ?? ($pct !== null ? round($pozo * $pct / 100, 2) : null);

            $podio[] = [
                'posicion'   => $pos,
                'nombre'     => $labels[$fila['insc_id']] ?? '—',
                'porcentaje' => $pct,
                'monto'      => $monto,
                'premiado'   => $pos <= (int) $torneo['posiciones_premiadas'],
            ];
        }

        return [$podio, $pozo, $premios];
    }

    /** Calcula el pozo: el total fijado o, si no, cuota × inscritos. */
    private function pozo(array $torneo): float
    {
        if ($torneo['pozo_total'] !== null && $torneo['pozo_total'] !== '') {
            return (float) $torneo['pozo_total'];
        }
        if ($torneo['cuota_inscripcion'] !== null && $torneo['cuota_inscripcion'] !== '') {
            return (float) $torneo['cuota_inscripcion'] * $this->inscripciones->contar((int) $torneo['id']);
        }
        return 0.0;
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El torneo no existe.']);
    }
}
