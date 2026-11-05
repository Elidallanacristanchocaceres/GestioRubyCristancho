<?php

namespace App\Support;

/**
 * Aritmética monetaria y de cantidades fraccionarias usando BCMath.
 *
 * Nunca se usan flotantes en cálculos críticos: todo se opera como cadena
 * decimal y se redondea con la escala indicada.
 */
final class Money
{
    public const ESCALA_DINERO = 2;

    public const ESCALA_CANTIDAD = 3;

    public const ESCALA_COSTO_UNITARIO = 6;

    public static function normal(string|int|float|null $valor, int $escala = self::ESCALA_DINERO): string
    {
        if ($valor === null || $valor === '') {
            return bcadd('0', '0', $escala);
        }

        if (is_float($valor)) {
            // Se convierte por string para no arrastrar el error binario del float.
            $valor = sprintf('%.14F', $valor);
        }

        return bcadd((string) $valor, '0', $escala);
    }

    public static function esValido(string $valor): bool
    {
        return preg_match('/^-?\d+(\.\d+)?$/', $valor) === 1;
    }

    public static function add(string $a, string $b, int $escala = self::ESCALA_DINERO): string
    {
        return bcadd(self::normal($a, $escala), self::normal($b, $escala), $escala);
    }

    public static function sub(string $a, string $b, int $escala = self::ESCALA_DINERO): string
    {
        return bcsub(self::normal($a, $escala), self::normal($b, $escala), $escala);
    }

    public static function mul(string $a, string $b, int $escala = self::ESCALA_DINERO): string
    {
        // Se multiplica con escala de operación amplia (6) para no truncar los
        // operandos (p. ej. gramos con 3 decimales o costos por gramo con 6) y
        // recién después se redondea el resultado a la escala pedida.
        $interno = max(self::ESCALA_COSTO_UNITARIO, $escala);

        return bcadd(bcmul(self::normal($a, $interno), self::normal($b, $interno), $interno), '0', $escala);
    }

    public static function div(string $a, string $b, int $escala = self::ESCALA_COSTO_UNITARIO): string
    {
        $b = self::normal($b, $escala);

        if (bccomp($b, '0', $escala) === 0) {
            return bcadd('0', '0', $escala);
        }

        return bcdiv(self::normal($a, $escala), $b, $escala);
    }

    public static function round(string $valor, int $escala = self::ESCALA_DINERO): string
    {
        return self::normal($valor, $escala);
    }

    public static function mayorQue(string $a, string $b, int $escala = self::ESCALA_DINERO): bool
    {
        return bccomp(self::normal($a, $escala), self::normal($b, $escala), $escala) === 1;
    }

    public static function menorQue(string $a, string $b, int $escala = self::ESCALA_DINERO): bool
    {
        return bccomp(self::normal($a, $escala), self::normal($b, $escala), $escala) === -1;
    }

    public static function igual(string $a, string $b, int $escala = self::ESCALA_DINERO): bool
    {
        return bccomp(self::normal($a, $escala), self::normal($b, $escala), $escala) === 0;
    }

    public static function esNegativo(string $valor, int $escala = self::ESCALA_DINERO): bool
    {
        return self::menorQue($valor, '0', $escala);
    }

    public static function cero(int $escala = self::ESCALA_DINERO): string
    {
        return bcadd('0', '0', $escala);
    }

    /**
     * Formatea como pesos colombianos sin decimales en pantalla.
     */
    public static function formato(string|int|float|null $valor): string
    {
        return '$'.number_format((float) self::normal($valor), 0, ',', '.');
    }

    public static function formatoDecimal(string|int|float|null $valor, int $decimales = 3): string
    {
        return number_format((float) self::normal($valor, $decimales), $decimales, ',', '.');
    }
}
