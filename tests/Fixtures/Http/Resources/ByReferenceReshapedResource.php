<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose locals a by-reference built-in leaves in another form than they were bound to, or writes
 * with a value the scan cannot follow, beside locals it keeps for a contrast; shuffle() is here, since the security
 * preset keeps it out of the workbench.
 *
 * @mixin Post
 */
class ByReferenceReshapedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $objectSorted = ['b' => 2, 'a' => 1];
        sort($objectSorted);

        $listValueSorted = ['b', 'a'];
        asort($listValueSorted);

        $listReverseKeySorted = ['a', 'b'];
        krsort($listReverseKeySorted);

        $pushedOther = ['a'];
        array_push($pushedOther, 1);

        $pushedObject = ['a' => 1];
        array_push($pushedObject, 2);

        $pushedUnpacked = ['a'];
        array_push($pushedUnpacked, ...['b']);

        $countedString = 'x';
        str_replace('a', 'b', 'abc', $countedString);

        $walkedOther = ['x' => 1];
        array_walk($walkedOther, function (int|string &$value): void {
            $value = 'z';
        });

        $walkedKey = [['a' => 1]];
        array_walk($walkedKey, function (array &$value): void {
            $value['b'] = 2;
        });

        $walkedNamed = ['a'];
        array_walk($walkedNamed, $this->upper(...));

        $loopedOther = ['a'];

        foreach ($loopedOther as &$looped) {
            $looped = 1;
        }

        unset($looped);

        $matched = [];
        preg_match('/a/', 'a', $matched);

        $objectMemberSorted = ['x' => ['b' => 2, 'a' => 1]];
        sort($objectMemberSorted['x']);

        $memberKey = 'x';
        $dynamicMemberSorted = ['x' => [3, 1]];
        sort($dynamicMemberSorted[$memberKey]);

        $walkedNestedOther = ['a' => ['b' => 1]];
        array_walk_recursive($walkedNestedOther, function (int|string &$value): void {
            $value = 'z';
        });

        $poppedObject = ['a' => 1, 'b' => 2];
        array_pop($poppedObject);

        $key = 'name';
        $labels = ['a', 'b'];
        array_walk($labels, function (string &$label, int $key): void {
            $label = $key;
        });

        $tmp = 'str';
        $words = ['a'];
        array_walk($words, function (string|int &$word): void {
            $tmp = 5;
            $word = $tmp;
        });

        $postWrittenList = ['a', 'b'];

        foreach ($postWrittenList as &$postWritten) {
            $postWritten = trim($postWritten);
        }

        $postWritten = 1;

        $roundList = ['a', 'b'];

        foreach ([1, 2] as $round) {
            $roundElement = $round;

            foreach ($roundList as &$roundElement) {
                $roundElement = trim($roundElement);
            }
        }

        $sortedOriginal = ['k' => 1];
        sort($sortedOriginal);
        $copiedAfterSort = $sortedOriginal;

        $concatNumbers = [1, 2];
        array_walk($concatNumbers, function (int|string &$value): void {
            $value .= '!';
        });

        $bitwise = [1, 2];
        array_walk($bitwise, function (int &$value): void {
            $value |= 4;
        });

        $shifted = [1, 2];
        array_walk($shifted, function (int &$value): void {
            $value <<= 1;
        });

        $keptList = ['b', 'a'];
        sort($keptList);

        $shuffled = [1, 2, 3];
        shuffle($shuffled);

        return [
            'object_sorted' => $objectSorted,
            'list_value_sorted' => $listValueSorted,
            'list_reverse_key_sorted' => $listReverseKeySorted,
            'pushed_other' => $pushedOther,
            'pushed_object' => $pushedObject,
            'pushed_unpacked' => $pushedUnpacked,
            'counted_string' => $countedString,
            'walked_other' => $walkedOther,
            'walked_key' => $walkedKey,
            'walked_named' => $walkedNamed,
            'looped_other' => $loopedOther,
            'matched' => $matched,
            'object_member_sorted' => $objectMemberSorted,
            'dynamic_member_sorted' => $dynamicMemberSorted,
            'walked_nested_other' => $walkedNestedOther,
            'popped_object' => $poppedObject,
            'labels_by_key' => $labels,
            'words_by_local' => $words,
            'post_written_loop' => $postWrittenList,
            'round_written_loop' => $roundList,
            'copied_after_sort' => $copiedAfterSort,
            'concat_numbers' => $concatNumbers,
            'bitwise' => $bitwise,
            'shifted' => $shifted,
            'kept_list' => $keptList,
            'key' => $key,
            'tmp' => $tmp,
            'shuffled' => $shuffled,
        ];
    }

    /** Upper-cases an element in place. */
    private function upper(string &$value): void
    {
        $value = strtoupper($value);
    }
}
