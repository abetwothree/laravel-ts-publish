<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\JsEmitter;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

beforeEach(function () {
    $this->service = new JsEmitter;
});

describe('validJsObjectKey', function () {
    test('validJsObjectKey returns valid identifiers as-is', function () {
        expect($this->service->validJsObjectKey('myKey'))->toBe('myKey')
            ->and($this->service->validJsObjectKey('_private'))->toBe('_private')
            ->and($this->service->validJsObjectKey('$dollar'))->toBe('$dollar')
            ->and($this->service->validJsObjectKey('camelCase123'))->toBe('camelCase123');
    });

    test('validJsObjectKey quotes keys with special characters', function () {
        expect($this->service->validJsObjectKey('my-key'))->toBe('"my-key"')
            ->and($this->service->validJsObjectKey('has space'))->toBe('"has space"')
            ->and($this->service->validJsObjectKey('123start'))->toBe('"123start"');
    });

    test('an index signature key is quoted unless allowIndexSignature is true', function () {
        expect($this->service->validJsObjectKey('[key: number]'))->toBe('"[key: number]"')
            ->and($this->service->validJsObjectKey('[key: number]', allowIndexSignature: true))->toBe('[key: number]')
            ->and($this->service->validJsObjectKey('[key: string]', allowIndexSignature: true))->toBe('[key: string]');
    });
});

describe('safeJsIdentifier', function () {
    test('appends suffix to reserved keywords', function () {
        expect($this->service->safeJsIdentifier('delete', 'Method'))->toBe('deleteMethod')
            ->and($this->service->safeJsIdentifier('export', 'Method'))->toBe('exportMethod')
            ->and($this->service->safeJsIdentifier('in', 'Method'))->toBe('inMethod')
            ->and($this->service->safeJsIdentifier('typeof', 'Method'))->toBe('typeofMethod')
            ->and($this->service->safeJsIdentifier('delete', 'Controller'))->toBe('deleteController');
    });

    test('returns non-reserved identifiers unchanged', function () {
        expect($this->service->safeJsIdentifier('index', 'Method'))->toBe('index')
            ->and($this->service->safeJsIdentifier('show', 'Method'))->toBe('show');
    });

    test('is case-sensitive — PascalCase is not reserved', function () {
        expect($this->service->safeJsIdentifier('Delete', 'Controller'))->toBe('Delete');
    });
});

describe('toJsLiteral', function () {
    test('toJsLiteral converts null', function () {
        expect($this->service->toJsLiteral(null))->toBe('null');
    });

    test('toJsLiteral converts booleans', function () {
        expect($this->service->toJsLiteral(true))->toBe('true')
            ->and($this->service->toJsLiteral(false))->toBe('false');
    });

    test('toJsLiteral converts integers and floats', function () {
        expect($this->service->toJsLiteral(42))->toBe('42')
            ->and($this->service->toJsLiteral(3.14))->toBe('3.14');
    });

    test('toJsLiteral converts strings with proper escaping', function () {
        expect($this->service->toJsLiteral('hello'))->toBe("'hello'")
            ->and($this->service->toJsLiteral("it's"))->toBe("'it\\'s'")
            ->and($this->service->toJsLiteral("line\nnew"))->toBe("'line\\nnew'");
    });

    test('toJsLiteral converts BackedEnum to its value', function () {
        expect($this->service->toJsLiteral(Status::Draft))->toBe('0')
            ->and($this->service->toJsLiteral(Status::Published))->toBe('1');
    });

    test('toJsLiteral converts UnitEnum to its name', function () {
        expect($this->service->toJsLiteral(Role::Admin))->toBe("'Admin'")
            ->and($this->service->toJsLiteral(Role::Guest))->toBe("'Guest'");
    });

    test('toJsLiteral converts associative arrays to JS objects', function () {
        expect($this->service->toJsLiteral(['Draft' => 0, 'Published' => 1]))
            ->toBe('{Draft: 0, Published: 1}');
    });

    test('toJsLiteral converts list arrays to JS arrays', function () {
        expect($this->service->toJsLiteral([1, 2, 3]))->toBe('[1, 2, 3]');
    });

    test('toJsLiteral converts objects to JS objects', function () {
        $obj = (object) ['name' => 'test', 'value' => 42];

        expect($this->service->toJsLiteral($obj))->toBe("{name: 'test', value: 42}");
    });

    test('toJsLiteral emits floats in their shortest round-trip form', function () {
        expect($this->service->toJsLiteral(0.1 + 0.2))->toBe('0.30000000000000004')
            ->and($this->service->toJsLiteral(1.2345678901234567))->toBe('1.2345678901234567')
            ->and($this->service->toJsLiteral(1.0))->toBe('1')
            ->and($this->service->toJsLiteral(1e25))->toBe('1.0e+25');
    });

    test('toJsLiteral rejects non-finite floats', function () {
        expect(fn () => $this->service->toJsLiteral(INF))->toThrow(InvalidArgumentException::class, 'non-finite')
            ->and(fn () => $this->service->toJsLiteral(NAN))->toThrow(InvalidArgumentException::class, 'non-finite');
    });

    test('toJsLiteral emits an empty stdClass as an empty object literal', function () {
        expect($this->service->toJsLiteral(new stdClass))->toBe('{}')
            ->and($this->service->toJsLiteral((object) ['a' => new stdClass]))->toBe('{a: {}}')
            ->and($this->service->toJsLiteral([]))->toBe('[]');
    });
});

describe('enumScalar', function () {
    test('returns a backed enum value and a pure enum name', function () {
        expect($this->service->enumScalar(Status::Published))->toBe(1)
            ->and($this->service->enumScalar(Role::Admin))->toBe('Admin');
    });
});

describe('routeArgsToJs', function () {
    test('routeArgsToJs includes where constraint in output', function () {
        $args = [
            ['name' => 'id', 'required' => true, 'where' => '[0-9]+'],
        ];

        $result = $this->service->routeArgsToJs($args);

        expect($result)->toContain("where: '[0-9]+'");
    });
});

describe('toJsLiteral unhandled types', function () {
    test('toJsLiteral returns null for unhandled types like resources', function () {
        $resource = fopen('php://memory', 'r');
        $result = $this->service->toJsLiteral($resource);
        fclose($resource);

        expect($result)->toBe('null');
    });
});

describe('sanitizeJsDoc', function () {
    test('escapes closing comment sequence', function () {
        expect($this->service->sanitizeJsDoc('some */ text'))->toBe('some *\/ text');
    });

    test('leaves normal text unchanged', function () {
        expect($this->service->sanitizeJsDoc('A normal description'))->toBe('A normal description');
    });

    test('handles multiple occurrences', function () {
        expect($this->service->sanitizeJsDoc('a */ b */ c'))->toBe('a *\/ b *\/ c');
    });

    test('handles empty string', function () {
        expect($this->service->sanitizeJsDoc(''))->toBe('');
    });
});

describe('formatJsDoc', function () {
    test('renders single-line description as inline JSDoc', function () {
        expect($this->service->formatJsDoc('A simple description'))->toBe('/** A simple description */');
    });

    test('renders multi-line description as block JSDoc', function () {
        expect($this->service->formatJsDoc("First line\nSecond line"))->toBe(
            "/**\n * First line\n * Second line\n */"
        );
    });

    test('renders blank lines as empty asterisk lines', function () {
        expect($this->service->formatJsDoc("First paragraph\n\nSecond paragraph"))->toBe(
            "/**\n * First paragraph\n *\n * Second paragraph\n */"
        );
    });

    test('applies indent to single-line description', function () {
        expect($this->service->formatJsDoc('A simple description', 4))->toBe('    /** A simple description */');
    });

    test('applies indent to multi-line description', function () {
        expect($this->service->formatJsDoc("First line\nSecond line", 4))->toBe(
            "    /**\n     * First line\n     * Second line\n     */"
        );
    });

    test('applies 8-space indent correctly', function () {
        expect($this->service->formatJsDoc("Line 1\nLine 2", 8))->toBe(
            "        /**\n         * Line 1\n         * Line 2\n         */"
        );
    });

    test('escapes closing comment sequence via sanitizeJsDoc', function () {
        expect($this->service->formatJsDoc('Contains */ comment ender'))->toBe(
            '/** Contains *\/ comment ender */'
        );
    });

    test('returns inline JSDoc for empty description', function () {
        expect($this->service->formatJsDoc(''))->toBe('/**  */');
    });
});

describe('parseDocBlockDescription', function () {
    test('returns empty string for false', function () {
        expect($this->service->parseDocBlockDescription(false))->toBe('');
    });

    test('returns empty string for empty string', function () {
        expect($this->service->parseDocBlockDescription(''))->toBe('');
    });

    test('extracts description from single-line doc block', function () {
        $doc = '/** A simple description */';
        expect($this->service->parseDocBlockDescription($doc))->toBe('A simple description');
    });

    test('extracts description from multi-line doc block', function () {
        $doc = <<<'DOC'
/**
 * First line of description.
 * Second line of description.
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe(
            "First line of description.\nSecond line of description."
        );
    });

    test('filters out @-tag lines', function () {
        $doc = <<<'DOC'
/**
 * The actual description.
 *
 * @param string $name
 * @return void
 * @phpstan-type Foo = array{bar: string}
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('The actual description.');
    });

    test('returns empty string when doc block has only tags', function () {
        $doc = <<<'DOC'
/**
 * @param string $name
 * @return void
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('');
    });

    test('strips inline tags like {@inheritdoc}', function () {
        $doc = <<<'DOC'
/**
 * {@inheritdoc}
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('');
    });

    test('strips inline tags mixed with description text', function () {
        $doc = <<<'DOC'
/**
 * Some description {@see OtherClass} here.
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('Some description here.');
    });

    test('skips multi-line @phpstan-type continuation lines', function () {
        $doc = <<<'DOC'
/**
 * The model description.
 *
 * @phpstan-type ModelData = array{
 *    modelName: string,
 *    description: string,
 * }
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('The model description.');
    });

    test('handles description after multi-line tag block separated by blank line', function () {
        $doc = <<<'DOC'
/**
 * @phpstan-type Foo = array{
 *    bar: string,
 * }
 *
 * Visible description after tag block.
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('Visible description after tag block.');
    });

    test('preserves blank lines between description paragraphs', function () {
        $doc = <<<'DOC'
/**
 * First paragraph.
 *
 * Second paragraph.
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe(
            "First paragraph.\n\nSecond paragraph."
        );
    });
});
