<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class ArchitectureRules
{
    private const array PRESENTATION = [
        'App\Http\Controllers', 'App\Http\Requests',
        'Illuminate\Routing\Controller', 'Illuminate\Foundation\Http\FormRequest', 'Inertia',
    ];

    /** @param string|list<string> $targets */
    public static function assertDtoBoundaries(array|string $targets): void
    {
        expect($targets)->not->toUse([
            ...self::PRESENTATION, 'App\Models', 'Illuminate\Database',
            'Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema',
        ]);
    }

    /** @param string|list<string> $targets */
    public static function assertActionBoundaries(array|string $targets): void
    {
        expect($targets)->not->toUse(self::PRESENTATION);
    }

    /** @param string|list<string> $targets */
    public static function assertNoControllers(array|string $targets): void
    {
        expect($targets)->not->toUse(['App\Http\Controllers', 'Illuminate\Routing\Controller']);
    }

    /** @param string|list<string> $targets */
    public static function assertNoUnsafeCalls(array|string $targets): void
    {
        expect($targets)->not->toUse([
            'dd', 'dump', 'var_dump', 'print_r', 'die', 'exit', 'eval',
            'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
        ]);
    }

    public static function assertNoShellExpressions(string $source, string $context): void
    {
        $nodes = (new NodeFinder)->findInstanceOf(self::parse($source), Expr\ShellExec::class);
        expect(array_map(fn (Node $node): int => $node->getStartLine(), $nodes))
            ->toBe([], 'Shell expressions are forbidden in '.$context);
    }

    public static function assertNoNativeFloats(string $source, string $context): void
    {
        $finder = new NodeFinder;
        $nodes = $finder->find(self::parse($source), function (Node $node): bool {
            if ($node instanceof Expr\Cast\Double || $node instanceof Scalar\Float_
                || $node instanceof Expr\BinaryOp\Div || $node instanceof Expr\AssignOp\Div) {
                return true;
            }

            return $node instanceof Expr\FuncCall && $node->name instanceof Name
                && in_array($node->name->toLowerString(), ['floatval', 'fdiv'], true);
        });
        expect(array_map(fn (Node $node): string => $node->getType().' at line '.$node->getStartLine(), $nodes))
            ->toBe([], 'Native floating-point constructs are forbidden in '.$context);
    }

    /** @return list<Node\Stmt> */
    private static function parse(string $source): array
    {
        $nodes = (new ParserFactory)->createForHostVersion()->parse($source) ?? [];

        return (new NodeTraverser(new NameResolver))->traverse($nodes);
    }
}
