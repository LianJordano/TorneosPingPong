<?php

declare(strict_types=1);

namespace PingPong\Tests\Support;

use PHPUnit\Framework\TestCase;
use PingPong\Support\Validator;

final class ValidatorTest extends TestCase
{
    public function test_required_detecta_campos_vacios(): void
    {
        $v = (new Validator(['nombre' => '  ']))->required('nombre', 'Nombre');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('nombre', $v->errors());
    }

    public function test_email_valida_formato(): void
    {
        $this->assertTrue((new Validator(['e' => 'no-es-mail']))->email('e', 'Correo')->fails());
        $this->assertFalse((new Validator(['e' => 'ok@dom.com']))->email('e', 'Correo')->fails());
        // Email vacío no es error (es opcional salvo que se combine con required)
        $this->assertFalse((new Validator(['e' => '']))->email('e', 'Correo')->fails());
    }

    public function test_in_valida_valores_permitidos(): void
    {
        $permitidos = [1, 3, 5, 7];
        $this->assertFalse((new Validator(['f' => '3']))->in('f', $permitidos, 'Formato')->fails());
        $this->assertTrue((new Validator(['f' => '4']))->in('f', $permitidos, 'Formato')->fails());
    }

    public function test_intMin_exige_entero_y_minimo(): void
    {
        $this->assertTrue((new Validator(['n' => '1']))->intMin('n', 2, 'N')->fails());
        $this->assertTrue((new Validator(['n' => 'abc']))->intMin('n', 2, 'N')->fails());
        $this->assertFalse((new Validator(['n' => '8']))->intMin('n', 2, 'N')->fails());
    }

    public function test_primer_error_por_campo_se_conserva(): void
    {
        $v = (new Validator(['nombre' => '']))
            ->required('nombre', 'Nombre')
            ->maxLen('nombre', 5, 'Nombre');
        $this->assertSame('El campo Nombre es obligatorio.', $v->errors()['nombre']);
    }
}
