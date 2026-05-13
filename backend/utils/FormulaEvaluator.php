<?php
/**
 * Evaluador seguro de expresiones matemáticas (sin eval de PHP).
 *
 * Características (para tesis):
 * - Operadores soportados: + - * / y paréntesis ( )
 * - Reemplazo seguro: solo identificadores (variables/parámetros) con valores numéricos
 * - No se ejecuta código arbitrario: tokenización + RPN + pila de evaluación
 *
 * Flujo: expresión → tokenizar → convertir a RPN → evaluar con contexto
 */

class FormulaEvaluator
{
    /** Patrón permitido para nombres de variables/parámetros (alfanumérico y _) */
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /** Caracteres permitidos en la expresión (seguridad) */
    private const SAFE_CHARS = '/^[a-zA-Z0-9+\-*\/.()_\s]+$/';

    /**
     * Evalúa una expresión con un mapa de variables y parámetros.
     *
     * Ejemplo: expression = "nivel*a1 + temperatura*a2 + b"
     *          context = ['nivel' => 10, 'temperatura' => 30, 'a1' => 0.5, 'a2' => 0.2, 'b' => 0]
     *
     * @param string $expression Expresión con variables y parámetros (solo + - * / ( ) y nombres)
     * @param array  $context    ['nombre_var' => valor numérico, ...]
     * @return float Resultado de la expresión
     * @throws InvalidArgumentException Si la expresión es inválida o falta una variable
     */
    public static function evaluate(string $expression, array $context): float
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException('Expresión vacía');
        }

        $normalized = preg_replace('/\s+/', '', $expression);
        if (!preg_match(self::SAFE_CHARS, $normalized)) {
            throw new InvalidArgumentException('La expresión solo puede contener números, operadores + - * /, paréntesis y nombres de variables');
        }

        $tokens = self::tokenize($normalized);
        $rpn = self::toRPN($tokens);
        return self::evaluateRPN($rpn, $context);
    }

    /**
     * Tokeniza la expresión en números, identificadores, operadores y paréntesis.
     * Soporta signo menos unario (ej: -x, -1) insertando un cero implícito.
     *
     * @return array Lista de ['type' => 'number'|'identifier'|'operator'|'paren', 'value' => ...]
     */
    private static function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;
        $lastType = null; // 'number'|'identifier'|'operator'|'paren'|null

        while ($i < $len) {
            $c = $expr[$i];

            if ($c === ' ' || $c === "\t") {
                $i++;
                continue;
            }

            if ($c === '(' || $c === ')') {
                $tokens[] = ['type' => 'paren', 'value' => $c];
                $lastType = 'paren';
                $i++;
                continue;
            }

            // Menos unario: tras inicio, operador o '(' insertar 0 para que "-x" o "(-1)" sea "0-x" / "(0-1)"
            $lastToken = end($tokens);
            $allowUnaryMinus = ($lastType === null || $lastType === 'operator' ||
                ($lastType === 'paren' && $lastToken !== false && $lastToken['value'] === '('));
            if ($c === '-' && $allowUnaryMinus) {
                $tokens[] = ['type' => 'number', 'value' => 0.0];
            }

            if (in_array($c, ['+', '-', '*', '/'], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $c];
                $lastType = 'operator';
                $i++;
                continue;
            }

            if (ctype_digit($c) || $c === '.') {
                $num = '';
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i++];
                }
                if (!is_numeric($num)) {
                    throw new InvalidArgumentException('Número mal formado en la expresión');
                }
                $tokens[] = ['type' => 'number', 'value' => (float) $num];
                $lastType = 'number';
                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $id = '';
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $id .= $expr[$i++];
                }
                if (!preg_match(self::IDENTIFIER_PATTERN, $id)) {
                    throw new InvalidArgumentException("Identificador no válido: '{$id}'");
                }
                $tokens[] = ['type' => 'identifier', 'value' => $id];
                $lastType = 'identifier';
                continue;
            }

            throw new InvalidArgumentException("Carácter no permitido en la expresión: '{$c}'");
        }

        return $tokens;
    }

    /**
     * Convierte la lista de tokens a notación polaca inversa (RPN).
     * Elimina ambigüedad de precedencia y paréntesis para la evaluación.
     */
    private static function toRPN(array $tokens): array
    {
        $output = [];
        $stack = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $t) {
            if ($t['type'] === 'number' || $t['type'] === 'identifier') {
                $output[] = $t;
                continue;
            }
            if ($t['type'] === 'paren') {
                if ($t['value'] === '(') {
                    $stack[] = $t;
                } else {
                    while (!empty($stack)) {
                        $top = end($stack);
                        if ($top['type'] === 'operator') {
                            $output[] = array_pop($stack);
                        } elseif ($top['value'] === '(') {
                            array_pop($stack);
                            break;
                        } else {
                            throw new InvalidArgumentException('Paréntesis no balanceados');
                        }
                    }
                }
                continue;
            }
            if ($t['type'] === 'operator') {
                while (!empty($stack)) {
                    $top = end($stack);
                    if ($top['type'] !== 'operator' || $precedence[$top['value']] < $precedence[$t['value']]) {
                        break;
                    }
                    $output[] = array_pop($stack);
                }
                $stack[] = $t;
            }
        }

        while (!empty($stack)) {
            $op = array_pop($stack);
            if ($op['type'] === 'paren') {
                throw new InvalidArgumentException('Paréntesis no balanceados');
            }
            $output[] = $op;
        }

        return $output;
    }

    /**
     * Evalúa la cola RPN usando el contexto para reemplazar identificadores.
     * Reemplazo seguro: solo se aceptan valores numéricos del contexto.
     */
    private static function evaluateRPN(array $rpn, array $context): float
    {
        $stack = [];

        foreach ($rpn as $t) {
            if ($t['type'] === 'number') {
                $stack[] = $t['value'];
                continue;
            }
            if ($t['type'] === 'identifier') {
                $name = $t['value'];
                if (!array_key_exists($name, $context)) {
                    throw new InvalidArgumentException("Variable o parámetro no definido: '{$name}'");
                }
                $val = $context[$name];
                if (!is_numeric($val)) {
                    throw new InvalidArgumentException("El valor de '{$name}' debe ser numérico");
                }
                $stack[] = (float) $val;
                continue;
            }
            if ($t['type'] === 'operator') {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException('Expresión incompleta o mal formada');
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                switch ($t['value']) {
                    case '+':
                        $stack[] = $a + $b;
                        break;
                    case '-':
                        $stack[] = $a - $b;
                        break;
                    case '*':
                        $stack[] = $a * $b;
                        break;
                    case '/':
                        if (abs($b) < 1e-12) {
                            throw new InvalidArgumentException('División por cero');
                        }
                        $stack[] = $a / $b;
                        break;
                }
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Expresión inválida');
        }
        return (float) $stack[0];
    }
}
