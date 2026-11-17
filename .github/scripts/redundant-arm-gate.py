#!/usr/bin/env python3
"""Standing gate: no published union may carry an arm a sibling arm already admits.

Reads every `.ts` file in the committed workbench type trees, splits each property with the
parser of unknown-regression-gate.py, and walks every union at every depth for three findings:

  never     `never[]` beside a single list arm (`X[]`, `Array<X>`, `ReadonlyArray<X>`) or `[]`
  literal   a string or number literal beside an enum alias whose `export type X = ...` lists it
  duplicate two arms of one union spelled exactly alike

An alias resolves through the file's `import ... from './dir'` lines to the enum files of that
directory, or, spelled qualified in laravel-ts-global.ts, through its namespace. A name that two enum
files in one directory export, or that resolves nowhere, admits nothing. See
docs/testing/type-inference-gates.md.

Usage: redundant-arm-gate.py              gate HEAD's committed trees, exit 1 on any finding
       redundant-arm-gate.py --parsetest  synthetic cases for each finding and each control
       redundant-arm-gate.py --selftest   run over the probe commit's trees, which must fail
"""
from __future__ import annotations

import importlib.util
import json
import os
import posixpath
import re
import subprocess
import sys
from collections import defaultdict
from collections.abc import Callable, Iterator
from pathlib import Path
from typing import NamedTuple


def _regression_gate():
    """unknown-regression-gate.py loaded as a module, for its property parser."""
    path = Path(__file__).with_name("unknown-regression-gate.py")
    spec = importlib.util.spec_from_file_location("unknown_regression_gate", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


_gate = _regression_gate()
ROOT, git, parse_source = _gate.ROOT, _gate.git, _gate.parse_source
OPENERS, CLOSERS = _gate.OPENERS, _gate.CLOSERS
PAIRS = dict(zip(OPENERS, CLOSERS))
QUOTES = "'\"`"
EMPTY_LIST, EMPTY_TUPLE = "never[]", "[]"
# A number as JSON spells one, the spelling the package prints for an int or a float.
NUMBER = re.compile(r"-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?")
NAME_PATH = re.compile(r"[A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*")
IMPORT = re.compile(r"^\s*import\s+(?:type\s+)?\{(?P<names>[^}]*)\}\s*from\s*['\"](?P<spec>[^'\"]+)['\"]", re.M)
ENUM_ALIAS = re.compile(r"^export\s+type\s+(?P<name>[A-Za-z_$][\w$]*)\s*=\s*(?P<value>.+?);\s*$", re.M)
ESCAPE = re.compile(r"\\(u\{[0-9a-fA-F]+\}|u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2}|[\s\S])")
SIMPLE_ESCAPES = {"n": "\n", "r": "\r", "t": "\t", "b": "\b", "f": "\f", "v": "\v", "0": "\0"}
CAMEL = re.compile(r"-(\w)")

# develop's probe commit for #166: its goldens hold the redundant arms the fix removes.
PROBE_REV = "ec7876ad239217e05a0b1ebbb177cf5c78c62894"

# (scope, key, kind) findings laravel-ts-global.ts publishes, as it spells an enum resource by its value type (#109).
# A scope leaves out the tree's root namespace, such as testing's `workbench.`, and an entry excuses only its own kind.
BASELINE = {
    ("app.http.resources.EnumCollectionResource", "wrapped_history_or_array", "duplicate"),
    ("app.http.resources.TeamStatusAuditResource", "audit.status", "duplicate"),
}

KINDS = {
    "never": "never[] beside a list arm",
    "literal": "literal its alias lists",
    "duplicate": "arm spelled like a sibling",
}


class EnumAliases(NamedTuple):
    """The literals enum aliases list, per enum file and per directory, and the directory each namespace mirrors."""

    by_file: dict[str, dict[str, frozenset]]
    by_dir: dict[str, dict[str, list[frozenset]]]
    namespaces: dict[tuple[str, str], str]


class Finding(NamedTuple):
    """One redundant arm: where it is, which finding it is, and the union that holds it."""

    path: str
    scope: str
    key: str
    kind: str
    arm: str
    union: str


def char_depths(text: str) -> list[int]:
    """Each character's bracket depth, or -1 inside a string literal; the `>` of `=>` closes nothing."""
    depths: list[int] = []
    depth = 0
    quote = ""
    escaped = False
    for i, ch in enumerate(text):
        if quote:
            depths.append(-1)
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == quote:
                quote = ""
        elif ch in QUOTES:
            quote = ch
            depths.append(-1)
        elif ch in OPENERS:
            depths.append(depth)
            depth += 1
        elif ch in CLOSERS and not (ch == ">" and text[i - 1:i] == "="):
            depth = max(depth - 1, 0)
            depths.append(depth)
        else:
            depths.append(depth)
    return depths


def split_top(text: str, seps: str) -> list[str]:
    """The non-empty parts of a type between its top-level separators."""
    depths = char_depths(text)
    parts, last = [], 0
    for i, ch in enumerate(text):
        if depths[i] == 0 and ch in seps:
            parts.append(text[last:i])
            last = i + 1
    parts.append(text[last:])
    return [p.strip() for p in parts if p.strip()]


def top_level(text: str, token: str) -> int | None:
    """The index of a token's first top-level occurrence."""
    depths = char_depths(text)
    return next((i for i in range(len(text)) if depths[i] == 0 and text.startswith(token, i)), None)


def groups(text: str) -> list[tuple[int, int]]:
    """Each top-level bracket group's opener and closer index."""
    depths = char_depths(text)
    spans: list[tuple[int, int]] = []
    start = None
    for i, ch in enumerate(text):
        if depths[i] != 0:
            continue
        if start is None and ch in OPENERS:
            start = i
        elif start is not None and ch == PAIRS[text[start]] and not (ch == ">" and text[i - 1:i] == "="):
            spans.append((start, i))
            start = None
    return spans


def wrapped(text: str, opener: str) -> bool:
    """Whether the bracket pair opening the text closes at its last character."""
    spans = groups(text)
    return text.startswith(opener) and bool(spans) and spans[0] == (0, len(text) - 1)


def unions(text: str) -> Iterator[list[str]]:
    """Each union's arms in a type: top level, members, elements, arguments, tuples, groups and arrow returns."""
    arrow = top_level(text, "=>")
    if arrow is not None:
        yield from inner_unions(text[:arrow])
        yield from unions(text[arrow + 2:])
        return
    arms = split_top(text, "|")
    if len(arms) > 1:
        yield arms
    for arm in arms:
        yield from inner_unions(arm)


def inner_unions(text: str) -> Iterator[list[str]]:
    """The unions inside a type's top-level groups, each item read without its label or spread."""
    for start, end in groups(text):
        for item in split_top(text[start + 1:end], ",;"):
            colon = top_level(item, ":")
            yield from unions((item[colon + 1:] if colon is not None else item).strip().removeprefix("..."))


def is_list_arm(arm: str) -> bool:
    """Whether an arm is one list or `[]`, which admit `never[]` (R7); no union, intersection, arrow or conditional."""
    while wrapped(arm, "("):
        arm = arm[1:-1].strip()
    if arm == EMPTY_TUPLE:
        return True
    if arm == EMPTY_LIST or any(top_level(arm, token) is not None for token in ("|", "&", "=>", "?")):
        return False
    for name in ("Array", "ReadonlyArray"):
        if arm.startswith(name) and wrapped(arm[len(name):], "<"):
            return len(split_top(arm[len(name) + 1:-1], ",")) == 1
    return arm.endswith("[]") and len(arm) > len("[]")


def literal(arm: str) -> tuple[str, object] | None:
    """The value a string or number literal arm spells: `'a'` and `"a"` alike, and `1` apart from `'1'`."""
    if NUMBER.fullmatch(arm):
        return ("number", json.loads(arm))
    if len(arm) > 1 and arm[0] in "'\"" and arm[-1] == arm[0] and all(d == -1 for d in char_depths(arm)):
        return ("string", ESCAPE.sub(unescape, arm[1:-1]))
    return None


def unescape(match: re.Match) -> str:
    """The character a JavaScript string escape spells."""
    escape = match.group(1)
    if escape[0] in "ux" and len(escape) > 1:
        return chr(int(escape[1:].strip("{}"), 16))
    return SIMPLE_ESCAPES.get(escape, escape)


def sole_exporter_literals(sets: list[frozenset] | None) -> frozenset:
    """The literals an alias admits, or none when no enum file or two export it."""
    return sets[0] if sets and len(sets) == 1 else frozenset()


def tree_of(path: str) -> str:
    """The tree a generated file belongs to, such as `testing`."""
    return path[len(ROOT) + 1:].split("/", 1)[0]


def enum_aliases(files: dict[str, str]) -> EnumAliases:
    """The literals each enum file's aliases list, by file, by directory and by the namespace that mirrors one."""
    by_file = {
        path: {m["name"]: literals(m["value"]) for m in ENUM_ALIAS.finditer(body)}
        for path, body in files.items() if "defineEnum(" in body
    }
    by_dir: dict[str, dict[str, list[frozenset]]] = defaultdict(lambda: defaultdict(list))
    for path, aliases in by_file.items():
        for name, values in aliases.items():
            by_dir[posixpath.dirname(path)][name].append(values)
    return EnumAliases(by_file, by_dir, {(tree_of(d), namespace_of(d)): d for d in by_dir})


def find_redundant(files: dict[str, str]) -> list[Finding]:
    """Every redundant arm in the files, once each, on the deepest key whose value holds it."""
    aliases = enum_aliases(files)
    found: list[Finding] = []
    for path, body in files.items():
        admitted = resolver(path, body, aliases)
        for (scope, key), value in parse_source(body).items():
            for arms in unions(value):
                values = frozenset().union(*(admitted(arm, scope) for arm in arms))
                for kind, arm in redundant(arms, values):
                    found.append(Finding(path, scope, key, kind, arm, " | ".join(arms)))

    deeper: dict[tuple, list[str]] = defaultdict(list)
    for f in found:
        deeper[(f.path, f.scope, f.kind, f.arm, f.union)].append(f.key)
    return [
        f for f in found
        if not any(k.startswith((f.key + ".", f.key + "[")) for k in deeper[(f.path, f.scope, f.kind, f.arm, f.union)])
    ]


def namespace_of(directory: str) -> str:
    """The namespace laravel-ts-global.ts declares a tree directory's types in, such as `app.enums`."""
    segments = directory[len(ROOT) + 1:].split("/")[1:]
    return ".".join(CAMEL.sub(lambda m: m.group(1).upper(), segment) for segment in segments)


def literals(value: str) -> frozenset:
    """The literal arms an alias's value lists."""
    return frozenset(v for v in map(literal, split_top(value, "|")) if v is not None)


def resolver(path: str, body: str, aliases: EnumAliases) -> Callable[[str, str], frozenset]:
    """A function giving the literals an arm admits as an enum alias, read in this file at a scope."""
    imports: dict[str, tuple[str, str]] = {}
    for match in IMPORT.finditer(body):
        for name in match["names"].split(","):
            exported, _, local = name.strip().removeprefix("type ").partition(" as ")
            if exported.strip():
                imports[(local or exported).strip()] = (exported.strip(), match["spec"])
    tree = tree_of(path)

    def admitted(arm: str, scope: str) -> frozenset:
        """The literals the arm's enum alias lists; a qualified name resolves from the innermost namespace out."""
        if not NAME_PATH.fullmatch(arm):
            return frozenset()
        if "." in arm:
            namespace, name = arm.rsplit(".", 1)
            outer = [] if scope == "<root>" else scope.split(".")
            for depth in range(len(outer), -1, -1):
                directory = aliases.namespaces.get((tree, ".".join([*outer[:depth], namespace])))
                if directory:
                    return sole_exporter_literals(aliases.by_dir[directory].get(name))
            return frozenset()
        if arm in imports:
            exported, spec = imports[arm]
            if not spec.startswith("."):
                return frozenset()
            target = posixpath.normpath(posixpath.join(posixpath.dirname(path), spec))
            if target in aliases.by_dir:
                return sole_exporter_literals(aliases.by_dir[target].get(exported))
            return aliases.by_file.get(f"{target}.ts", {}).get(exported, frozenset())
        return aliases.by_file.get(path, {}).get(arm, frozenset())

    return admitted


def redundant(arms: list[str], admitted: frozenset) -> list[tuple[str, str]]:
    """Each arm of one union a sibling admits or an earlier arm spells, with its kind."""
    found = [("duplicate", arm) for i, arm in enumerate(arms) if arm in arms[:i]]
    found += [
        ("never", arm) for i, arm in enumerate(arms)
        if arm == EMPTY_LIST and any(is_list_arm(other) for j, other in enumerate(arms) if j != i)
    ]
    found += [("literal", arm) for arm in arms if literal(arm) in admitted]
    return found


def failing(findings: list[Finding]) -> list[Finding]:
    """The findings the #109 baseline does not cover."""
    return [f for f in findings if not baselined(f)]


def baselined(f: Finding) -> bool:
    """Whether a baseline entry names the finding's scope, key and kind in a laravel-ts-global.ts."""
    return f.path.endswith("/laravel-ts-global.ts") and any(
        (f.scope == scope or f.scope.endswith(f".{scope}")) and (f.key, f.kind) == (key, kind)
        for scope, key, kind in BASELINE
    )


def read_tree(rev: str) -> dict[str, str]:
    """Every generated `.ts` file's text at a revision; read from git, since /workbench is export-ignored."""
    paths = [p for p in git("ls-tree", "-r", "-z", "--name-only", rev, ROOT).split("\0") if p.endswith(".ts")]
    request = "".join(f"{rev}:{p}\n" for p in paths).encode()
    batch = subprocess.run(["git", "cat-file", "--batch"], input=request, capture_output=True, check=True).stdout
    files: dict[str, str] = {}
    offset = 0
    for path in paths:
        header_end = batch.index(b"\n", offset)
        header = batch[offset:header_end].split()
        offset = header_end + 1
        if len(header) == 3 and header[1] == b"blob":
            size = int(header[2])
            files[path] = batch[offset:offset + size].decode("utf-8")
            offset += size + 1
    return files


def report(findings: list[Finding]) -> None:
    """Print up to 40 findings with their file, scope, property, arm and union."""
    for f in sorted(findings)[:40]:
        print(f"  {f.path} :: {f.scope}.{f.key}\n     {KINDS[f.kind]}: {f.arm}   in   {f.union}")


# --- --parsetest --------------------------------------------------------------------------------------------------

PROBE = f"{ROOT}/probe"
MODULE = f"{PROBE}/app/http/resources/probe-resource.ts"
GLOBALS = f"{PROBE}/laravel-ts-global.ts"
ENUM_FILES = {
    f"{PROBE}/app/enums/index.ts": "export * from './media-type';\nexport * from './status';\n",
    f"{PROBE}/app/enums/media-type.ts": (
        "import { defineEnum } from '@tolki/ts';\n\nexport const MediaType = defineEnum({\n    Image: 'image',\n"
        "    Video: 'video',\n    backed: true,\n});\n\nexport type MediaTypeType = 'image' | 'video';\n\n"
        "export type MediaTypeKind = 'Image' | 'Video';\n\nexport type MediaType = MediaTypeType;\n"
    ),
    f"{PROBE}/app/enums/status.ts": (
        "import { defineEnum } from '@tolki/ts';\n\nexport const Status = defineEnum({\n    Draft: 0,\n"
        "    Published: 1,\n    backed: true,\n});\n\nexport type StatusType = 0 | 1;\n"
    ),
    f"{PROBE}/app/enums/twin-a.ts": "export const TwinA = defineEnum({ A: 'a' });\nexport type TwinType = 'a' | 'b';\n",
    f"{PROBE}/app/enums/twin-b.ts": "export const TwinB = defineEnum({ A: 'a' });\nexport type TwinType = 'a' | 'c';\n",
    f"{PROBE}/app/enums/plain.ts": "export type PlainType = 'image' | 'video';\n",
    # Resolved relative to the probe resource, `'external'` would reach this file, but a bare specifier names a package.
    f"{PROBE}/app/http/resources/external/external.ts": (
        "export const External = defineEnum({ Image: 'image' });\n\nexport type ExternalType = 'image';\n"
    ),
}
MODULE_HEAD = (
    "import { type AsEnum } from '@tolki/ts';\n"
    "import type { MediaTypeType, MediaTypeType as Media, PlainType, StatusType, TwinType } from '../../enums';\n"
    "import type { ExternalType } from 'external';\n"
    "import { MediaType } from '../../enums';\n\n"
)

# (label, property line in a module's interface, expected (key, kind, arm) findings)
MODULE_CASES = [
    ("never[] beside X[] at the top level", "a: never[] | number[];", [("a", "never", "never[]")]),
    ("never[] beside Array<X>", "a: Array<string> | never[];", [("a", "never", "never[]")]),
    ("never[] beside ReadonlyArray<X>", "a: ReadonlyArray<string> | never[] | null;", [("a", "never", "never[]")]),
    ("never[] beside the empty tuple", "a: [] | never[];", [("a", "never", "never[]")]),
    ("never[] beside a grouped list", "a: (StatusType | null)[] | never[];", [("a", "never", "never[]")]),
    ("never[] beside a parenthesized list", "a: never[] | (number[]);", [("a", "never", "never[]")]),
    ("never[] in an object member", "a: { f: never[] | number[] }[];", [("a.f", "never", "never[]")]),
    ("never[] in a list element", "a: (never[] | string[])[];", [("a", "never", "never[]")]),
    ("never[] in a generic argument", "a: Record<string, never[] | string[]>;", [("a", "never", "never[]")]),
    ("never[] in a tuple element", "a: [string, never[] | number[]];", [("a", "never", "never[]")]),
    ("never[] in a labeled tuple element", "a: [first: string, rest?: never[] | number[]];",
     [("a", "never", "never[]")]),
    ("never[] in an index signature", "a: { [key: string]: never[] | number[] };", [("a", "never", "never[]")]),
    ("never[] in a parenthesized group", "a: (never[] | number[]) | null;", [("a", "never", "never[]")]),
    ("never[] in an arrow's return type", "a: () => never[] | number[];", [("a", "never", "never[]")]),
    ("literal its alias lists", "a: 'image' | MediaTypeType;", [("a", "literal", "'image'")]),
    ("double-quoted literal after the alias", 'a: MediaTypeType | "video" | null;', [("a", "literal", '"video"')]),
    ("number literal its alias lists", "a: 0 | StatusType;", [("a", "literal", "0")]),
    ("literal beside an alias imported under another name", "a: Media | 'image';", [("a", "literal", "'image'")]),
    ("literal in a nested member's list", "a: { inner: ('image' | MediaTypeType)[] };",
     [("a.inner", "literal", "'image'")]),
    ("literal in an inline type alias", "export type PageProps = Inertia.SharedData & { p: never[] | number[], "
     "q: 0 | StatusType };", [("PageProps.p", "never", "never[]"), ("PageProps.q", "literal", "0")]),
    ("two arms spelled alike", "a: string[] | string[];", [("a", "duplicate", "string[]")]),
    ("two arms spelled alike in a member", "a: { s: StatusType | StatusType };", [("a.s", "duplicate", "StatusType")]),
    # R7: none of these admits never[].
    ("control: Record beside never[]", "a: never[] | Record<string, number>;", []),
    ("control: object beside never[]", "a: never[] | { a?: number };", []),
    ("control: non-empty tuple beside never[]", "a: never[] | [string];", []),
    ("control: AsEnum beside never[]", "a: never[] | AsEnum<typeof MediaType>;", []),
    ("control: named collection beside never[]", "a: never[] | UserCollection;", []),
    ("control: intersection beside never[]", "a: never[] | Foo & Bar[];", []),
    ("control: never[] alone", "a: never[];", []),
    ("control: never[] | null", "a: never[] | null;", []),
    ("control: arrow returning a list beside never[]", "a: never[] | (() => number[]);", []),
    ("control: conditional type beside never[]", "a: never[] | (T extends U ? X[] : Y[]);", []),
    ("control: a string spelled like a redundant union", "a: 'never[] | number[]' | null;", []),
    ("control: literal the alias does not list", "a: 'Image' | MediaTypeType;", []),
    ("control: string '1' beside a number alias", "a: '1' | StatusType;", []),
    ("control: literal beside an alias nothing imports", "a: 'image' | UnimportedType;", []),
    ("control: literal beside an alias two enum files export", "a: 'a' | TwinType;", []),
    ("control: literal beside an alias from a file without defineEnum(", "a: 'image' | PlainType;", []),
    ("control: literal beside an alias from a bare specifier", "a: 'image' | ExternalType;", []),
    ("control: literal beside its scalar", "a: 'image' | string;", []),
    ("control: arms alike only in part", "a: { x: number } | { x: string };", []),
]

# (label, scope, property line in laravel-ts-global.ts, expected findings, how many of them fail the gate); a scope
# without a namespace sits in app.http.resources.
GLOBAL_CASES = [
    ("literal beside a qualified alias", "ProbeResource", "a: 'image' | app.enums.MediaTypeType;",
     [("a", "literal", "'image'")], 1),
    ("literal beside a partly qualified alias", "ProbeResource", "a: enums.MediaTypeType | 'video';",
     [("a", "literal", "'video'")], 1),
    ("control: qualified literal the alias does not list", "ProbeResource", "a: 'Image' | app.enums.MediaTypeType;",
     [], 0),
    ("baselined duplicate (#109)", "EnumCollectionResource",
     "wrapped_history_or_array: app.enums.StatusType[] | app.enums.StatusType[];",
     [("wrapped_history_or_array", "duplicate", "app.enums.StatusType[]")], 0),
    ("baselined member duplicate (#109)", "TeamStatusAuditResource",
     "audit: { status: app.enums.StatusType[] | app.enums.StatusType[] };",
     [("audit.status", "duplicate", "app.enums.StatusType[]")], 0),
    ("duplicate the baseline does not list", "EnumCollectionResource",
     "other: app.enums.StatusType[] | app.enums.StatusType[];",
     [("other", "duplicate", "app.enums.StatusType[]")], 1),
    ("baselined key in another interface", "OtherResource",
     "wrapped_history_or_array: app.enums.StatusType[] | app.enums.StatusType[];",
     [("wrapped_history_or_array", "duplicate", "app.enums.StatusType[]")], 1),
    ("baselined interface in another namespace", "app.models.EnumCollectionResource",
     "wrapped_history_or_array: app.enums.StatusType[] | app.enums.StatusType[];",
     [("wrapped_history_or_array", "duplicate", "app.enums.StatusType[]")], 1),
    ("never[] on a baselined key", "EnumCollectionResource",
     "wrapped_history_or_array: never[] | app.enums.StatusType[];",
     [("wrapped_history_or_array", "never", "never[]")], 1),
    ("literal on a baselined key", "TeamStatusAuditResource", "audit: { status: 0 | app.enums.StatusType };",
     [("audit.status", "literal", "0")], 1),
]

# The baseline excuses a key only in laravel-ts-global.ts, so the same namespaces under another file name fail.
ELSEWHERE = f"{PROBE}/app/global-probe.d.ts"
ELSEWHERE_CASES = [
    ("baselined key outside laravel-ts-global.ts", "EnumCollectionResource",
     "wrapped_history_or_array: app.enums.StatusType[] | app.enums.StatusType[];",
     [("wrapped_history_or_array", "duplicate", "app.enums.StatusType[]")], 1),
]


def run_parsetest() -> int:
    """Check each finding fires once on its synthetic case, and every control stays quiet."""
    failed = 0
    total = 0

    def check(label: str, path: str, body: str, want: list[tuple[str, str, str]], fails: int) -> None:
        """Compare one file's findings, and how many of them fail, with the case's."""
        nonlocal failed, total
        total += 1
        found = [f for f in find_redundant({**ENUM_FILES, path: body}) if f.path == path]
        got = sorted((f.key, f.kind, f.arm) for f in found)
        if got == sorted(want) and len(failing(found)) == fails:
            print(f"PASS - {label}")
        else:
            failed += 1
            print(f"FAIL - {label}\n  want: {sorted(want)} ({fails} failing)")
            print(f"  got:  {got} ({len(failing(found))} failing)")

    for label, line, want in MODULE_CASES:
        check(label, MODULE, f"{MODULE_HEAD}export interface ProbeResource\n{{\n    {line}\n}}\n", want, len(want))

    def global_body(scope: str, line: str) -> str:
        """A globals file declaring one interface at the scope, with one property line."""
        namespace, _, interface = scope.rpartition(".")
        return (
            f"export {{}}\n\ndeclare global {{\n    export namespace {namespace or 'app.http.resources'} {{\n"
            f"        export interface {interface} {{\n            {line}\n        }}\n    }}\n}}\n"
        )

    for label, scope, line, want, fails in GLOBAL_CASES:
        check(label, GLOBALS, global_body(scope, line), want, fails)

    for label, scope, line, want, fails in ELSEWHERE_CASES:
        check(label, ELSEWHERE, global_body(scope, line), want, fails)

    if failed:
        print(f"FAIL - {failed} of {total} parser cases failed")
        return 1
    print(f"PASS - all {total} parser cases")
    return 0


# --- --selftest ---------------------------------------------------------------------------------------------------

TESTING = f"{ROOT}/testing"
# (path, scope, key, kind, arm) the probe commit's goldens must report: each kind, top-level and nested-member keys, and
# both alias spellings. The parsetest covers the other depths.
PROBE_FINDINGS = [
    (f"{TESTING}/analysis-probe/widget-props.ts", "WidgetPropsProbe", "kind", "literal", "'image'"),
    (f"{TESTING}/analysis-probe/widget-props.ts", "WidgetPropsProbe", "kind", "literal", "'video'"),
    (f"{TESTING}/analysis-probe/widget-props.ts", "WidgetPropsProbe", "modes", "never", "never[]"),
    (f"{TESTING}/workbench/app/http/resources/placeholder-readings-resource.ts", "PlaceholderReadingsResource",
     "fills.f", "never", "never[]"),
    (f"{TESTING}/analysis-probe/redundant-arms-payload.ts", "RedundantArmsPayloadProbe", "nested.inner", "literal",
     "'image'"),
    (f"{TESTING}/analysis-probe/redundant-arms-payload.ts", "RedundantArmsPayloadProbe", "listed", "literal",
     "'image'"),
    (f"{TESTING}/analysis-probe/redundant-arms-payload.ts", "RedundantArmsPayloadProbe", "int_case", "literal", "0"),
    (f"{TESTING}/analysis-probe/redundant-arms-payload.ts", "RedundantArmsPayloadProbe", "backed_name", "literal",
     "'Pass'"),
    (f"{TESTING}/workbench/app/http/controllers/inertia-redundant-arms-controller.ts", "<root>",
     "ShowPageProps.priorities", "never", "never[]"),
    (f"{TESTING}/workbench/accounting/models/payment.ts", "PaymentMutators", "status_or_pending", "literal",
     "'pending'"),
    (f"{TESTING}/laravel-ts-global.ts", "workbench.app.http.resources.RedundantArmsResource", "status", "literal",
     "1"),
    (f"{TESTING}/laravel-ts-global.ts", "workbench.app.http.resources.RedundantArmsResource", "priorities", "never",
     "never[]"),
    (f"{TESTING}/laravel-ts-global.ts", "workbench.app.http.resources.EnumCollectionResource",
     "wrapped_history_or_array", "duplicate", "workbench.app.enums.StatusType[]"),
]
# Keys the probe publishes beside a sibling that does not admit them, so no finding may name them.
PROBE_KEPT_FILE = f"{TESTING}/analysis-probe/redundant-arms-payload.ts"


def run_selftest() -> int:
    """Run over the probe commit's trees, which must fail with the expected findings; exit 1 if they pass."""
    files = read_tree(PROBE_REV)
    findings = find_redundant(files)
    print(f"files at {PROBE_REV[:9]}: {len(files)}   findings: {len(findings)}   failing: {len(failing(findings))}")
    report(failing(findings))
    got = {(f.path, f.scope, f.key, f.kind, f.arm) for f in findings}
    missing = [want for want in PROBE_FINDINGS if want not in got]
    kept = [f for f in findings if f.path == PROBE_KEPT_FILE and f.key.endswith("_kept")]
    ok = True
    if not failing(findings):
        ok = False
        print("SELFTEST FAILED: expected the probe commit's trees to fail")
    for want in missing:
        ok = False
        print(f"SELFTEST FAILED: missing {want}")
    for f in kept:
        ok = False
        print(f"SELFTEST FAILED: reported a kept arm {f}")
    if ok:
        print(f"PASS - selftest: the probe commit fails, with all {len(PROBE_FINDINGS)} expected findings")
    return 0 if ok else 1


def main() -> int:
    """Gate HEAD's committed trees, or run a self-test."""
    os.chdir(Path(__file__).resolve().parents[2])
    if "--parsetest" in sys.argv:
        return run_parsetest()
    if "--selftest" in sys.argv:
        return run_selftest()

    files = read_tree("HEAD")
    # A run that reads no file, or no enum alias, checks nothing and would pass.
    if not any("defineEnum(" in body for body in files.values()):
        print(f"FAIL - no enum file among the {len(files)} .ts files under {ROOT} at HEAD")
        return 1
    findings = find_redundant(files)
    bad = failing(findings)
    print(f"files: {len(files)}   findings: {len(findings)}   baselined (#109): {len(findings) - len(bad)}")
    if bad:
        print(f"FAIL - {len(bad)} redundant union arm{'' if len(bad) == 1 else 's'}:")
        report(bad)
        return 1
    print("PASS - no union arm a sibling admits")
    return 0


if __name__ == "__main__":
    sys.exit(main())
