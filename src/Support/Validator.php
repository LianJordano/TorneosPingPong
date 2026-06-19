<?php

declare(strict_types=1);

namespace PingPong\Support;

/**
 * Validador encadenable. Recolecta errores por campo.
 *
 *   $v = (new Validator($data))
 *       ->required('nombre', 'Nombre')
 *       ->email('email', 'Correo');
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    private function value(string $field): string
    {
        return trim((string) ($this->data[$field] ?? ''));
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->errors[$field] ??= "El campo {$label} es obligatorio.";
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] ??= "El campo {$label} debe ser un correo válido.";
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->errors[$field] ??= "El campo {$label} no puede superar los {$max} caracteres.";
        }
        return $this;
    }

    public function minLen(string $field, int $min, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->errors[$field] ??= "El campo {$label} debe tener al menos {$min} caracteres.";
        }
        return $this;
    }

    /** @param array<int|string> $allowed */
    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && !in_array($value, array_map('strval', $allowed), true)) {
            $this->errors[$field] ??= "El valor de {$label} no es válido.";
        }
        return $this;
    }

    public function intMin(string $field, int $min, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && (!ctype_digit($value) || (int) $value < $min)) {
            $this->errors[$field] ??= "El campo {$label} debe ser un número mayor o igual a {$min}.";
        }
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
