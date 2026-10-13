<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Locals a by-reference built-in rewrites in a form that keeps their type: each sort of the form it keeps, a count, a
 * push, pop, shift or splice of a list, a walk or a by-reference loop writing each element within its type, and a
 * recursive walk or a sort of a key likewise. A resource re-indexes a list a key-keeping sort reorders.
 *
 * @mixin Post
 */
class LocalVarByReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sorted = ['b', 'a'];
        sort($sorted);

        $reversed = [3, 1, 2];
        rsort($reversed);

        $userSorted = ['b', 'a'];
        usort($userSorted, fn (string $a, string $b): int => strcmp($a, $b));

        $plucked = $this->comments->pluck('content')->all();
        sort($plucked);

        $keySorted = ['b' => 1, 'a' => 2];
        ksort($keySorted);

        $keySortedList = ['b', 'a'];
        ksort($keySortedList);

        $userKeySorted = ['b' => 1, 'a' => 2];
        uksort($userKeySorted, fn (string $a, string $b): int => strcmp($a, $b));

        $reverseKeySorted = ['a' => 1, 'b' => 2];
        krsort($reverseKeySorted);

        $valueSorted = ['b' => 2, 'a' => 1];
        asort($valueSorted);

        $reverseValueSorted = ['a' => 1, 'b' => 2];
        arsort($reverseValueSorted);

        $userValueSorted = ['b' => 2, 'a' => 1];
        uasort($userValueSorted, fn (int $a, int $b): int => $a <=> $b);

        $naturalSorted = ['b' => 'img12', 'a' => 'img10'];
        natsort($naturalSorted);

        $naturalCaseSorted = ['b' => 'IMG12', 'a' => 'img10'];
        natcasesort($naturalCaseSorted);

        $replacedCount = 0;
        str_replace('a', 'b', (string) $this->title, $replacedCount);

        $caseReplacedCount = 0;
        str_ireplace('a', 'b', (string) $this->title, $caseReplacedCount);

        $patternCount = 0;
        preg_replace('/a/', 'b', (string) $this->title, -1, $patternCount);

        $callbackCount = 0;
        preg_replace_callback('/a/', fn (array $match): string => 'b', (string) $this->title, -1, $callbackCount);

        $pushed = ['a'];
        array_push($pushed, 'b');

        $unshifted = ['b'];
        array_unshift($unshifted, 'a');

        $walked = ['x' => 1];
        array_walk($walked, fn (int &$value) => $value++);

        $walkedList = ['a', 'b'];
        array_walk($walkedList, function (string &$value): void {
            $value = strtoupper($value);
        });

        $readOnlyWalk = ['b' => 1];
        array_walk($readOnlyWalk, fn (int $value): int => $value * 2);

        $walkedNested = ['a' => ['b' => 1]];
        array_walk_recursive($walkedNested, fn (int &$value) => $value++);

        $sortedMember = ['x' => [3, 1]];
        sort($sortedMember['x']);

        $nestedSortedMember = ['x' => ['y' => [2, 1]]];
        sort($nestedSortedMember['x']['y']);

        $keySortedMember = ['x' => ['b' => 1, 'a' => 2]];
        ksort($keySortedMember['x']);

        $popped = ['a', 'b'];
        array_pop($popped);

        $shifted = ['a', 'b'];
        array_shift($shifted);

        $spliced = ['a', 'b'];
        array_splice($spliced, 1, 1, ['z']);

        $valueSortedList = ['b', 'a'];
        asort($valueSortedList);

        $valueSortedMember = ['x' => ['b', 'a']];
        asort($valueSortedMember['x']);

        $mixedMember = ['x' => ['b', 'a'], 'c' => collect([3 => 'a'])];
        asort($mixedMember['x']);

        $walkedKeys = ['a' => 'x'];
        array_walk($walkedKeys, function (string &$value, string $key): void {
            $value = $key;
        });

        $suffix = '!';
        $walkedImport = ['a', 'b'];
        array_walk($walkedImport, function (string &$value) use ($suffix): void {
            $value = $suffix;
        });

        $concatWalked = ['a', 'b'];
        array_walk($concatWalked, function (string &$value): void {
            $value .= '!';
        });

        $plusWalked = [1, 2];
        array_walk($plusWalked, function (int &$value): void {
            $value += 1;
        });

        $original = ['k' => 1];
        $copiedBeforeSort = $original;
        sort($original);

        $listOriginal = ['b', 'a'];
        sort($listOriginal);
        $listCopiedAfterSort = $listOriginal;

        $preWritten = 1;
        $preWrittenList = ['a', 'b'];

        foreach ($preWrittenList as &$preWritten) {
            $preWritten = trim($preWritten);
        }

        unset($preWritten);

        $looped = ['a', 'b'];

        foreach ($looped as &$item) {
            $item = trim($item);
        }

        unset($item);

        return [
            'sorted' => $sorted,
            'reversed' => $reversed,
            'user_sorted' => $userSorted,
            'plucked' => $plucked,
            'key_sorted' => $keySorted,
            'key_sorted_list' => $keySortedList,
            'user_key_sorted' => $userKeySorted,
            'reverse_key_sorted' => $reverseKeySorted,
            'value_sorted' => $valueSorted,
            'reverse_value_sorted' => $reverseValueSorted,
            'user_value_sorted' => $userValueSorted,
            'natural_sorted' => $naturalSorted,
            'natural_case_sorted' => $naturalCaseSorted,
            'replaced_count' => $replacedCount,
            'case_replaced_count' => $caseReplacedCount,
            'pattern_count' => $patternCount,
            'callback_count' => $callbackCount,
            'pushed' => $pushed,
            'unshifted' => $unshifted,
            'walked' => $walked,
            'walked_list' => $walkedList,
            'read_only_walk' => $readOnlyWalk,
            'walked_nested' => $walkedNested,
            'sorted_member' => $sortedMember,
            'nested_sorted_member' => $nestedSortedMember,
            'key_sorted_member' => $keySortedMember,
            'looped' => $looped,
            'popped' => $popped,
            'shifted' => $shifted,
            'spliced' => $spliced,
            'value_sorted_list' => $valueSortedList,
            'value_sorted_member' => $valueSortedMember,
            'mixed_member' => $mixedMember,
            'walked_keys' => $walkedKeys,
            'walked_import' => $walkedImport,
            'pre_written_loop' => $preWrittenList,
            'concat_walked' => $concatWalked,
            'plus_walked' => $plusWalked,
            'copied_before_sort' => $copiedBeforeSort,
            'list_copied_after_sort' => $listCopiedAfterSort,
        ];
    }
}
