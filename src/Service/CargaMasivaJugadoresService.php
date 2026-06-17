<?php

declare(strict_types=1);

namespace PingPong\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PingPong\Repository\AreaRepository;
use PingPong\Repository\NivelRepository;
use PingPong\Repository\PersonaRepository;

/**
 * Carga masiva de jugadores desde un archivo .xlsx.
 *
 * Reglas de negocio:
 *  - Obligatorios: nombre y apellido. El resto de columnas son opcionales.
 *  - Si existe una persona con la misma identificación → se ACTUALIZA; si no → se CREA.
 *  - Si el área o el nivel indicados no existen en el catálogo → se CREAN y se asignan.
 */
final class CargaMasivaJugadoresService
{
    /** Encabezados de la plantilla, en orden. */
    private const COLUMNAS = ['nombre', 'apellido', 'identificacion', 'area', 'nivel', 'email', 'telefono'];

    /** Alias aceptados en el encabezado del Excel → clave canónica. */
    private const ALIAS = [
        'nombre' => 'nombre',
        'nombres' => 'nombre',
        'apellido' => 'apellido',
        'apellidos' => 'apellido',
        'identificacion' => 'identificacion',
        'identificador' => 'identificacion',
        'identificador interno' => 'identificacion',
        'legajo' => 'identificacion',
        'documento' => 'identificacion',
        'cedula' => 'identificacion',
        'dni' => 'identificacion',
        'area' => 'area',
        'departamento' => 'area',
        'sector' => 'area',
        'nivel' => 'nivel',
        'categoria' => 'nivel',
        'email' => 'email',
        'correo' => 'email',
        'correo electronico' => 'email',
        'mail' => 'email',
        'telefono' => 'telefono',
        'celular' => 'telefono',
        'movil' => 'telefono',
        'tel' => 'telefono',
    ];

    /** @var array<string,int> cache nombre normalizado → id de área */
    private array $areaCache = [];
    /** @var array<string,int> cache nombre normalizado → id de nivel */
    private array $nivelCache = [];

    public function __construct(
        private PersonaRepository $personas,
        private AreaRepository $areas,
        private NivelRepository $niveles,
    ) {
    }

    /**
     * Procesa el archivo y devuelve un resumen del resultado.
     *
     * @return array{creados:int,actualizados:int,areasCreadas:list<string>,nivelesCreados:list<string>,errores:list<array{fila:int,motivo:string}>,procesadas:int}
     */
    public function importar(string $rutaArchivo): array
    {
        $hoja = IOFactory::load($rutaArchivo)->getActiveSheet();
        /** @var list<array<int,mixed>> $filas */
        $filas = $hoja->toArray(null, true, false, false);

        $resumen = [
            'creados' => 0,
            'actualizados' => 0,
            'areasCreadas' => [],
            'nivelesCreados' => [],
            'errores' => [],
            'procesadas' => 0,
        ];

        if ($filas === []) {
            $resumen['errores'][] = ['fila' => 0, 'motivo' => 'El archivo está vacío.'];
            return $resumen;
        }

        $mapa = $this->mapearEncabezado(array_shift($filas) ?? []);
        if (!isset($mapa['nombre'], $mapa['apellido'])) {
            $resumen['errores'][] = ['fila' => 1, 'motivo' => 'El encabezado debe incluir al menos las columnas "nombre" y "apellido".'];
            return $resumen;
        }

        foreach ($filas as $i => $fila) {
            $numeroFila = $i + 2; // +1 por el encabezado quitado, +1 porque Excel cuenta desde 1
            $valor = fn (string $clave): string => isset($mapa[$clave]) ? trim((string) ($fila[$mapa[$clave]] ?? '')) : '';

            $nombre = $valor('nombre');
            $apellido = $valor('apellido');

            // Saltar filas completamente vacías sin contarlas como error.
            if ($this->filaVacia($fila)) {
                continue;
            }

            $resumen['procesadas']++;

            if ($nombre === '' || $apellido === '') {
                $resumen['errores'][] = ['fila' => $numeroFila, 'motivo' => 'Falta nombre o apellido (ambos son obligatorios).'];
                continue;
            }

            $ident = $valor('identificacion');
            $areaId = $this->resolverArea($valor('area'), $resumen);
            $nivelId = $this->resolverNivel($valor('nivel'), $resumen);
            $email = $valor('email');
            $telefono = $valor('telefono');

            $existente = $ident !== '' ? $this->personas->findByIdentificador($ident) : null;

            if ($existente !== null) {
                // Update: solo se pisan los campos opcionales que vienen con valor; el resto se conserva.
                $this->personas->update((int) $existente['id'], [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'identificador_interno' => $ident,
                    'area_id' => $areaId ?? ($existente['area_id'] ?? null),
                    'nivel_id' => $nivelId ?? ($existente['nivel_id'] ?? null),
                    'email' => $email !== '' ? $email : (string) ($existente['email'] ?? ''),
                    'telefono' => $telefono !== '' ? $telefono : (string) ($existente['telefono'] ?? ''),
                ]);
                $resumen['actualizados']++;
                continue;
            }

            $this->personas->create([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'identificador_interno' => $ident,
                'area_id' => $areaId,
                'nivel_id' => $nivelId,
                'email' => $email,
                'telefono' => $telefono,
            ]);
            $resumen['creados']++;
        }

        return $resumen;
    }

    /** Genera la plantilla .xlsx en el destino indicado (ruta o 'php://output'). */
    public function escribirPlantilla(string $destino): void
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Jugadores');

        $hoja->fromArray(self::COLUMNAS, null, 'A1');
        $hoja->fromArray([
            ['Juan', 'Pérez', '12345', 'Sistemas', 'Avanzado', 'juan@ejemplo.com', '3001234567'],
            ['Ana', 'Gómez', '', 'Ventas', '', '', ''],
        ], null, 'A2');

        $ultimaColumna = chr(ord('A') + count(self::COLUMNAS) - 1);
        $hoja->getStyle('A1:' . $ultimaColumna . '1')->getFont()->setBold(true);
        $hoja->getStyle('A1:' . $ultimaColumna . '1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');
        $hoja->getStyle('A1:' . $ultimaColumna . '1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('A', $ultimaColumna) as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        (new Xlsx($libro))->save($destino);
        $libro->disconnectWorksheets();
    }

    /**
     * Mapea la fila de encabezado a índices de columna por clave canónica.
     *
     * @param array<int,mixed> $encabezado
     * @return array<string,int>
     */
    private function mapearEncabezado(array $encabezado): array
    {
        $mapa = [];
        foreach ($encabezado as $indice => $titulo) {
            $clave = self::ALIAS[$this->normalizar((string) $titulo)] ?? null;
            if ($clave !== null && !isset($mapa[$clave])) {
                $mapa[$clave] = (int) $indice;
            }
        }
        return $mapa;
    }

    /** @param array{areasCreadas:list<string>,errores:list<array{fila:int,motivo:string}>} $resumen */
    private function resolverArea(string $nombre, array &$resumen): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }
        $clave = $this->normalizar($nombre);
        if (isset($this->areaCache[$clave])) {
            return $this->areaCache[$clave];
        }
        $fila = $this->areas->findByNombre($nombre);
        if ($fila !== null) {
            return $this->areaCache[$clave] = (int) $fila['id'];
        }
        $id = $this->areas->create($nombre);
        $resumen['areasCreadas'][] = $nombre;
        return $this->areaCache[$clave] = $id;
    }

    /** @param array{nivelesCreados:list<string>,errores:list<array{fila:int,motivo:string}>} $resumen */
    private function resolverNivel(string $nombre, array &$resumen): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }
        $clave = $this->normalizar($nombre);
        if (isset($this->nivelCache[$clave])) {
            return $this->nivelCache[$clave];
        }
        $fila = $this->niveles->findByNombre($nombre);
        if ($fila !== null) {
            return $this->nivelCache[$clave] = (int) $fila['id'];
        }
        $id = $this->niveles->create($nombre);
        $resumen['nivelesCreados'][] = $nombre;
        return $this->nivelCache[$clave] = $id;
    }

    /** @param array<int,mixed> $fila */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }
        return true;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    }
}
