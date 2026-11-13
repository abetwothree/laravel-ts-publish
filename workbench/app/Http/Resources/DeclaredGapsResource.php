<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\ContactSheet;
use Workbench\App\ValueObjects\MailRecipient;

/**
 * Maps contacts where the body cannot type every arm or key the `toArray()` `@return` docblock declares (GH-154): a
 * value with no type beside typed arms, a spread of an array with no element type, a branch that spreads one,
 * out-of-order int keys, and a value with no type beside a member the docblock declares more loosely than the body.
 *
 * @property-read ContactSheet $resource
 */
class DeclaredGapsResource extends JsonResource
{
    /**
     * @return array{
     *     scores: list<array{score: int|string|null}>,
     *     contacts: list<array{email: string, name: string|null}|null>,
     *     rows: list<array{name: string|null, locale: string}>,
     *     branches: list<array{name: string|null, locale: string}>,
     *     pairs: list<array{0: int, 1: string}>,
     *     summary: array{label: string|null, count: int|null}
     * }
     */
    public function toArray(Request $request): array
    {
        $sheet = $this->resource;

        return [
            'scores' => array_map(
                fn (MailRecipient $r) => ['score' => $r->email === null ? null : ($r->name !== null ? strlen($r->name) : $sheet->hint)],
                $sheet->contacts,
            ),
            'contacts' => array_map(
                fn (MailRecipient $r) => $r->email !== null ? ['email' => $r->email] : ($r->name !== null ? $sheet->hint : null),
                $sheet->contacts,
            ),
            'rows' => array_map(fn (MailRecipient $r) => [...$sheet->extras, 'name' => $r->name], $sheet->contacts),
            'branches' => array_map(function (MailRecipient $r) use ($sheet) {
                $row = ['name' => $r->name];

                if ($r->email !== null) {
                    $row = [...$sheet->extras, 'name' => $r->name];
                }

                return $row;
            }, $sheet->contacts),
            'pairs' => array_map(fn (MailRecipient $r) => [1 => $sheet->hint, 0 => $sheet->hint], $sheet->contacts),
            'summary' => ['label' => (string) $sheet->title, 'count' => $sheet->title !== null ? $sheet->hint : null],
        ];
    }
}
