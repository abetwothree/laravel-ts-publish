#!/usr/bin/env python3
"""Standing gate: no generated property may go from a real type to `unknown`.

Compares the committed workbench type trees at two revisions, so it also
requires that a change regenerating them was committed.

Keys every property by (file, enclosing interface/namespace path, property name)
so identically-named properties in different files -- or in different interfaces
within the same file, e.g. the nested namespaces in laravel-ts-global.ts -- are
never conflated. Matches any indent depth.

Single-line `type X = ...;` aliases are parsed too, and any inline object's
members are split into their own `prop.member` / `prop[i].member` keys *in
addition to* the parent key, so an already-`unknown` sibling no longer masks a
member regression, and an object that was entirely real-typed at base is caught
when it collapses wholesale. Residual: detect_regressions()'s `"unknown" not in
b[k]` test reads the parent's whole rendered value, so an object already holding
an `unknown` member disarms its own parent key and its wholesale collapse still
reports nothing. See docs/testing/type-inference-gates.md.

A resource split into arms, `type X = { rows } & ( | { arm } | { arm } );`, keys
its rows and arm members under X as an interface's; a key several arms send is
keyed once, its arms' distinct values joined, to compare with the flat key.

Usage: unknown-regression-gate.py [BASE_REV] [HEAD_REV]
Exit 1 if any property regressed. Self-test: --selftest RANGE expects a FAIL.
Parser self-test: --parsetest checks the member-splitting logic in isolation.
"""
import re
import subprocess
import sys

ROOT = "workbench/resources/js/types/data"
# A property name is an identifier or a quoted key (`"42"`, `'tags.*'`); the quotes are not part of the name.
NAME = r"""(?:"(?P<dq>[^"]+)"|'(?P<sq>[^']+)'|(?P<name>[A-Za-z_$][\w$]*))"""
PROP = re.compile(rf"^\s*{NAME}\??:\s*(?P<value>.+?);\s*$")
ALIAS = re.compile(r"^\s*(?:export\s+)?(?:declare\s+)?type\s+(?P<name>[A-Za-z_$][\w$]*)\s*=\s*(?P<value>.+?);\s*$")
ALIAS_OPEN = re.compile(r"^\s*(?:export\s+)?(?:declare\s+)?type\s+(?P<name>[A-Za-z_$][\w$]*)\s*=")
ARM = re.compile(r"^\s*\|\s*(?P<value>.+?);?\s*$")
MEMBER = re.compile(rf"^{NAME}\??:\s*(?P<value>.+)$")
BLOCK = re.compile(r"^\s*(?:export\s+)?(?:declare\s+)?(interface|namespace|module)\s+([^\s{]+)")
OPENERS, CLOSERS = "{[(<", "}])>"


def git(*args: str) -> str:
    return subprocess.run(["git", *args], capture_output=True, text=True, check=True).stdout


def name_of(match: re.Match) -> str:
    """The property name a PROP, ALIAS or MEMBER match spells, without its quotes."""
    groups = match.groupdict()
    return groups.get("dq") or groups.get("sq") or groups["name"]


def scan_objects(value: str) -> list[tuple[int, int]]:
    """Return (start, end) index pairs of each brace-balanced `{...}` not nested in another."""
    spans: list[tuple[int, int]] = []
    depth = 0
    start = None
    for i, ch in enumerate(value):
        if ch == "{":
            if depth == 0:
                start = i
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0 and start is not None:
                spans.append((start, i))
                start = None
    return spans


def blank_objects(s: str) -> str:
    """Replace every character nested inside any bracket pair with a space, same length."""
    out = []
    depth = 0
    for ch in s:
        if ch in OPENERS:
            depth += 1
            out.append(" ")
        elif ch in CLOSERS:
            depth = max(depth - 1, 0)
            out.append(" ")
        elif depth:
            out.append(" ")
        else:
            out.append(ch)
    return "".join(out)


def split_members(body: str) -> list[str]:
    """Split an object literal body into member segments at its top-level ';' or ','."""
    mask = blank_objects(body)
    parts = []
    last = 0
    for i, ch in enumerate(mask):
        if ch in ";,":
            parts.append(body[last:i])
            last = i + 1
    parts.append(body[last:])
    return [p.strip() for p in parts if p.strip()]


def expand(name: str, value: str) -> dict[str, str]:
    """Recursively split a prop's value into name.member / name[i].member keys, if it holds objects."""
    spans = scan_objects(value)
    if not spans:
        return {name: value.strip()}
    # The parent key is kept alongside its members, not replaced by them: a property whose whole
    # inline object collapses to `unknown` shares no member key with its base and would otherwise
    # match nothing in `detect_regressions()`.
    out: dict[str, str] = {name: value.strip()}
    indexed = len(spans) > 1
    for i, (s, e) in enumerate(spans):
        prefix = f"{name}[{i}]" if indexed else name
        for part in split_members(value[s + 1:e]):
            member = MEMBER.match(part)
            if member:
                out.update(expand(f"{prefix}.{name_of(member)}", member.group("value")))
    return out


def arm_members(arm: str) -> dict[str, str]:
    """An arm's member values by name, or none when the arm is not one inline object."""
    if scan_objects(arm) != [(0, len(arm) - 1)]:
        return {}
    return {name_of(m): m.group("value").strip() for m in map(MEMBER.match, split_members(arm[1:-1])) if m}


def fold_arms(scope: str, arms: list[dict[str, str]], per_arm: bool) -> dict[tuple[str, str], str]:
    """A split alias's arm keys: each name once, its arms' distinct values joined in arm order, or one scope per arm."""
    if per_arm:
        return {
            (f"{scope}[{i}]", key): val
            for i, arm in enumerate(arms) for name, value in arm.items() for key, val in expand(name, value).items()
        }
    out: dict[tuple[str, str], str] = {}
    for name in dict.fromkeys(name for arm in arms for name in arm):
        joined = " | ".join(dict.fromkeys(arm[name] for arm in arms if name in arm))
        out.update(((scope, key), val) for key, val in expand(name, joined).items())
    return out


def parse_source(body: str, per_arm: bool = False) -> dict[tuple[str, str], str]:
    """Each property's value by scope and key; `per_arm` keys a split alias's arms apart, as `X[i]` scopes."""
    out: dict[tuple[str, str], str] = {}
    stack: list[tuple[str, int]] = []  # (name, brace depth at which it opened)
    pending: str | None = None  # block header whose '{' is on a later line
    alias: tuple[str, int] | None = None  # a multi-line `type X =` and the brace depth it opened at
    arms: list[dict[str, str]] = []
    depth = 0
    for raw in body.splitlines():
        line = raw.split("//")[0]
        scope = ".".join([n for n, _ in stack] + ([alias[0]] if alias else [])) or "<root>"
        block = BLOCK.match(line)
        arm = ARM.match(raw) if alias else None
        if block:
            pending = block.group(2)
        elif arm:
            arms.append(arm_members(arm.group("value")))
        else:
            match = PROP.match(raw) or ALIAS.match(raw)
            if match:
                for key, val in expand(name_of(match), match.group("value")).items():
                    out[(scope, key)] = val
            elif opened := ALIAS_OPEN.match(line):
                alias = (opened.group("name"), depth)
        opens = line.count("{")
        # Push only once the brace is seen: this repo puts `interface Foo` and `{` on separate
        # lines, and pushing at the header's depth pops it again on that very line.
        if pending is not None and opens:
            stack.append((pending, depth))
            pending = None
        depth += opens - line.count("}")
        while stack and depth <= stack[-1][1]:
            stack.pop()
        if alias and depth <= alias[1] and line.rstrip().endswith(";"):
            out.update(fold_arms(scope, arms, per_arm))
            alias, arms = None, []
    return out


def snapshot(rev: str) -> dict[tuple[str, str, str], str]:
    files = [
        f for f in git("ls-tree", "-r", "--name-only", rev, ROOT).splitlines()
        if f.endswith(".ts")
    ]
    out: dict[tuple[str, str, str], str] = {}
    for path in files:
        try:
            body = git("show", f"{rev}:{path}")
        except subprocess.CalledProcessError:
            continue
        for (scope, key), val in parse_source(body).items():
            out[(path, scope, key)] = val
    return out


def holds_unkeyed_unknown(value: str) -> bool:
    """Whether `unknown` sits where no member key holds it: outside every inline object, or in a member MEMBER cannot
    name, such as an index signature. Quotes are not tracked, so a brace inside a string literal type misleads it."""
    rest, last = [], 0
    for start, end in scan_objects(value):
        rest.append(value[last:start])
        rest.extend(part for part in split_members(value[start + 1:end]) if not MEMBER.match(part))
        last = end + 1
    rest.append(value[last:])
    return any("unknown" in part for part in rest)


def detect_regressions(b: dict, h: dict) -> list:
    head_keys = list(h)

    def exhausted(k: tuple) -> bool:
        # True when k's members report its regression: each `unknown` sits in a member key and each head member existed
        # in base. A quoted `'tags.*'` sibling counts as a member of an inline-object `tags` and can mask it, and a
        # parent that is no inline object holds its `unknown` unkeyed (type-inference-gates.md).
        if holds_unkeyed_unknown(h[k]):
            return False
        members = [
            other for other in head_keys
            if other[:-1] == k[:-1] and (other[-1].startswith(k[-1] + ".") or other[-1].startswith(k[-1] + "["))
        ]
        return bool(members) and all(m in b for m in members)

    return [
        (k, b[k], h[k])
        for k in h
        if k in b and "unknown" not in b[k] and "unknown" in h[k] and not exhausted(k)
    ]


PARSE_CASES = [
    (
        "single-line export type alias (inertia-preserve-keys-controller.ts:6)",
        "export type NamedPageProps = Inertia.SharedData & { teams: PreserveKeysCollection };",
        {("<root>", "NamedPageProps.teams"): "PreserveKeysCollection"},
    ),
    (
        "nested alias inside declare global (inertia-config.d.ts:3)",
        "declare global {\n"
        "    namespace Inertia {\n"
        "        type SharedData = { auth: { user: { id: number; name: string; email: string } | null },"
        " flash: { success: string | null; error: string | null }, appName: string,"
        " filters?: Record<string, string> };\n"
        "    }\n"
        "}\n",
        {
            ("Inertia", "SharedData.auth.user.id"): "number",
            ("Inertia", "SharedData.auth.user.name"): "string",
            ("Inertia", "SharedData.auth.user.email"): "string",
            ("Inertia", "SharedData.flash.success"): "string | null",
            ("Inertia", "SharedData.flash.error"): "string | null",
            ("Inertia", "SharedData.appName"): "string",
            ("Inertia", "SharedData.filters"): "Record<string, string>",
        },
    ),
    (
        "inline object, one member (nested-edge-cases-request.ts:12)",
        "    items?: { name: string }[];",
        {("<root>", "items.name"): "string"},
    ),
    (
        "inline object, several members (post-resource.ts:25)",
        "    heading_content: { title: string; summary: string };",
        {
            ("<root>", "heading_content.title"): "string",
            ("<root>", "heading_content.summary"): "string",
        },
    ),
    (
        "union of two inline objects (nested-edge-cases-request.ts:14)",
        "    variants?: ({ name: string } | { email: string })[];",
        {
            ("<root>", "variants[0].name"): "string",
            ("<root>", "variants[1].email"): "string",
        },
    ),
    (
        "quoted property names, either quote (store-post-request.ts:11)",
        "export interface A {\n    'tags.*': string[];\n    \"42\"?: string;\n}\n",
        {("A", "tags.*"): "string[]", ("A", "42"): "string"},
    ),
    (
        "quoted inline object member",
        "    labelled: { name: string; \"42\": boolean };",
        {("<root>", "labelled.name"): "string", ("<root>", "labelled.42"): "boolean"},
    ),
]

SPLIT_ROWS = "export type P = {\n    id: number;\n} & (\n"
# Cases matched exactly, so a split alias also keys nothing beyond its rows and arms.
SPLIT_CASES = [
    (
        "split resource: shared rows and arms (pinned-post-resource.ts:9)",
        SPLIT_ROWS + "    | { k: true; v: A }\n    | { k: false; v: B }\n);\n",
        {("P", "id"): "number", ("P", "k"): "true | false", ("P", "v"): "A | B"},
    ),
    (
        "split resource in a namespace, beside a later interface (laravel-ts-global.ts:6287)",
        "export namespace n {\n    export type P = {\n        id: number;\n    } & (\n"
        "        | { k: true; v: A }\n        | { k: false; v: B }\n    );\n"
        "    export interface Q {\n        id: string;\n    }\n}\n",
        {
            ("n.P", "id"): "number", ("n.P", "k"): "true | false", ("n.P", "v"): "A | B",
            ("n.Q", "id"): "string",
        },
    ),
    (
        "split resource: a key one arm sends (post-priority-resource.ts:9)",
        SPLIT_ROWS + "    | { k: true; until: string | null }\n    | { k: false; until?: never }\n);\n",
        {("P", "id"): "number", ("P", "k"): "true | false", ("P", "until"): "string | null | never"},
    ),
    (
        "split resource with no shared rows (pinned-wraps-resource.ts:14)",
        "export type P =\n    | { k: true; v: A }\n    | { k: false; v: B };\n",
        {("P", "k"): "true | false", ("P", "v"): "A | B"},
    ),
    (
        "split resource led by #[TsExtends], with two groups and one value in two arms",
        "export type P = Ext & Pick<R, \"a\" | \"b\"> & {\n    id: number;\n} & (\n"
        "    | { a: 0; x?: never }\n    | { a: 1; x: A }\n    | { a: 2; x?: never }\n) & (\n"
        "    | { b: true; y: B }\n    | { b: false; y: C }\n);\n",
        {
            ("P", "id"): "number", ("P", "a"): "0 | 1 | 2", ("P", "x"): "never | A",
            ("P", "b"): "true | false", ("P", "y"): "B | C",
        },
    ),
    (
        "split resource led by #[TsExtends], with no shared rows",
        "export type P = Ext & (\n    | { k: true; v: A }\n    | { k: false; v: B }\n);\n",
        {("P", "k"): "true | false", ("P", "v"): "A | B"},
    ),
    (
        "split resource: an arm's inline object splits as the flat key's does",
        "export type P =\n    | { k: true; meta: { id: number; note: string } }\n    | { k: false; meta?: never };\n",
        {
            ("P", "k"): "true | false", ("P", "meta"): "{ id: number; note: string } | never",
            ("P", "meta.id"): "number", ("P", "meta.note"): "string",
        },
    ),
    (
        "control: an alias of arms that are no object keys nothing (broadcast-channels.ts:1)",
        "export type C =\n    | `orders.${string | number}`\n    | { x: number }[];\n"
        "export interface Q\n{\n    x: string;\n}\n",
        {("Q", "x"): "string"},
    ),
]

REGRESS_BASE = (
    "    last_checked_by_mostly: { id: number; imageable_type: string; imageable_id: number; url: string;"
    " alt_text: string | null; disk: string; path: string; mime_type: string; size_bytes: number;"
    " width: number | null; height: number | null; sort_order: number; metadata: unknown[] | null }"
    " | { id: number; name: string; email: string; email_verified_at: string | null; password: string;"
    " options: unknown[] | null; remember_token: string | null; role: RoleType | null;"
    " membership_level: MembershipLevelType | null; phone: string | null; avatar: string | null;"
    " bio: string | null; settings: unknown[] | null; last_login_at: string | null;"
    " last_login_ip: string | null } | null;"
)
REGRESS_HEAD = REGRESS_BASE.replace("alt_text: string | null", "alt_text: unknown")


COLLAPSE_BASE = "    heading_content: { title: string; summary: string };"
COLLAPSE_HEAD = "    heading_content: unknown;"


MEMBER_BASE = "export interface A {\n    meta: { id: number; note: string };\n}\n"
MEMBER_HEAD = "export interface A {\n    meta: { id: number; note: unknown };\n}\n"


GAINED_BASE = "export interface A {\n    meta: { id: number };\n}\n"
GAINED_HEAD = "export interface A {\n    meta: { id: number; note: unknown };\n}\n"


UNION_BASE = "    variants?: ({ name: string } | { email: string })[];"
UNION_HEAD = "    variants?: ({ name: unknown } | { email: string })[];"


SIBLING_BASE = "export interface StorePostRequestProbe {\n    tags: string[];\n    'tags.*': string;\n}\n"
SIBLING_HEAD = "export interface StorePostRequestProbe {\n    tags: unknown;\n    'tags.*': string;\n}\n"


FLAT_BASE = "export interface P\n{\n    id: number;\n    k: boolean;\n    v: A | B;\n}\n"
FOLD_BASE = "export interface R\n{\n    meta: { id: number };\n}\n"


def fold_head(first: str, second: str) -> str:
    """A split alias whose two arms carry `meta` as the given values, to fold against `FOLD_BASE`."""
    return f"export type R = (\n    | {{ meta: {first}; k: 1 }}\n    | {{ meta: {second}; k: 2 }}\n);\n"


# A folded parent beside the arms its `meta` takes, and the regressions it must report against `FOLD_BASE`.
FOLD_CASES = [
    ("an object beside a direct unknown arm", ("{ id: number }", "unknown"), ["R.meta"]),
    ("an object beside an `unknown[]` arm", ("{ id: number }", "unknown[]"), ["R.meta"]),
    ("an object beside a `Record<string, unknown>` arm", ("{ id: number }", "Record<string, unknown>"), ["R.meta"]),
    ("the same object in both arms", ("{ id: number }", "{ id: number }"), []),
]


def interface(value: str) -> str:
    """An interface whose one property `p` holds the given value."""
    return f"export interface A {{\n    p: {value};\n}}\n"


# A parent's base and head values, and what they must report: an `unknown` no member key holds is the parent's.
NESTED_CASES = [
    ("an object array gaining an unknown arm inside its parens", "{ id: number }[]",
     "({ id: number } | unknown)[]", ["A.p"]),
    ("an object gaining an unknown index signature", "{ id: number }",
     "{ id: number; [key: string]: unknown }", ["A.p"]),
    ("control: an object's own unknown member inside a generic", "Record<string, { id: number }>",
     "Record<string, { id: unknown }>", ["A.p.id"]),
]


SPLIT_HEAD = "export type P = {\n    id: unknown;\n} & (\n    | { k: true; v: A }\n    | { k: false; v: unknown }\n);\n"


def run_parsetest() -> int:
    failed = 0
    for name, source, want in PARSE_CASES:
        got = parse_source(source)
        if all(got.get(k) == v for k, v in want.items()):
            print(f"PASS - {name}")
        else:
            failed += 1
            print(f"FAIL - {name}\n  want: {want}\n  got:  {got}")

    for name, source, want in SPLIT_CASES:
        got = parse_source(source)
        if got == want:
            print(f"PASS - {name}")
        else:
            failed += 1
            print(f"FAIL - {name}\n  want: {want}\n  got:  {got}")

    base, head = parse_source(REGRESS_BASE), parse_source(REGRESS_HEAD)
    bad = detect_regressions(base, head)
    caught = any(k[1].endswith(".alt_text") for k, _, _ in bad)
    masked = any(k[1].endswith(".metadata") for k, _, _ in bad)
    if caught and not masked and len(bad) == 1:
        print("PASS - member regressing beside an already-unknown sibling (synthetic)")
    else:
        failed += 1
        print(f"FAIL - member regressing beside an already-unknown sibling (synthetic)\n  got: {bad}")

    base, head = parse_source(COLLAPSE_BASE), parse_source(COLLAPSE_HEAD)
    bad = detect_regressions(base, head)
    if [(k[1], was, now) for k, was, now in bad] == [
        ("heading_content", "{ title: string; summary: string }", "unknown")
    ]:
        print("PASS - whole inline object collapsing to unknown (synthetic)")
    else:
        failed += 1
        print(f"FAIL - whole inline object collapsing to unknown (synthetic)\n  got: {bad}")

    base, head = parse_source(MEMBER_BASE), parse_source(MEMBER_HEAD)
    bad = detect_regressions(base, head)
    if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == ["A.meta.note"]:
        print("PASS - member gaining unknown beside an unchanged-shape parent (synthetic)")
    else:
        failed += 1
        print(f"FAIL - member gaining unknown beside an unchanged-shape parent (synthetic)\n  got: {bad}")

    base, head = parse_source(GAINED_BASE), parse_source(GAINED_HEAD)
    bad = detect_regressions(base, head)
    if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == ["A.meta"]:
        print("PASS - object gaining a brand-new unknown member (synthetic)")
    else:
        failed += 1
        print(f"FAIL - object gaining a brand-new unknown member (synthetic)\n  got: {bad}")

    base, head = parse_source(UNION_BASE), parse_source(UNION_HEAD)
    bad = detect_regressions(base, head)
    if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == ["<root>.variants[0].name"]:
        print("PASS - union member regressing without flagging the indexed parent (synthetic)")
    else:
        failed += 1
        print(f"FAIL - union member regressing without flagging the indexed parent (synthetic)\n  got: {bad}")

    base, head = parse_source(SIBLING_BASE), parse_source(SIBLING_HEAD)
    bad = detect_regressions(base, head)
    if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == ["StorePostRequestProbe.tags"]:
        print("PASS - property regressing beside a quoted 'tags.*' sibling (store-post-request.ts:10)")
    else:
        failed += 1
        print(f"FAIL - property regressing beside a quoted 'tags.*' sibling (store-post-request.ts:10)\n  got: {bad}")

    base, head = parse_source(FLAT_BASE), parse_source(SPLIT_HEAD)
    bad = detect_regressions(base, head)
    if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == ["P.id", "P.v"]:
        print("PASS - shared row and arm regressing after an interface splits (synthetic)")
    else:
        failed += 1
        print(f"FAIL - shared row and arm regressing after an interface splits (synthetic)\n  got: {bad}")

    base = parse_source(FOLD_BASE)
    for label, arms, want in FOLD_CASES:
        bad = detect_regressions(base, parse_source(fold_head(*arms)))
        if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == want:
            print(f"PASS - folded parent with {label} (synthetic)")
        else:
            failed += 1
            print(f"FAIL - folded parent with {label} (synthetic)\n  got: {bad}")

    for label, was, now, want in NESTED_CASES:
        bad = detect_regressions(parse_source(interface(was)), parse_source(interface(now)))
        if sorted(f"{k[0]}.{k[1]}" for k, _, _ in bad) == want:
            print(f"PASS - {label} (synthetic)")
        else:
            failed += 1
            print(f"FAIL - {label} (synthetic)\n  got: {bad}")

    total = len(PARSE_CASES) + len(SPLIT_CASES) + len(FOLD_CASES) + len(NESTED_CASES) + 7
    if failed:
        print(f"FAIL - {failed} of {total} parser cases failed")
        return 1
    print(f"PASS - all {total} parser cases")
    return 0


def main() -> int:
    if "--parsetest" in sys.argv:
        return run_parsetest()

    args = [a for a in sys.argv[1:] if a != "--selftest"]
    selftest = "--selftest" in sys.argv
    base = args[0] if args else "a6c268da05b814e76b1dad9faba6e71a9cb91b92"
    head = args[1] if len(args) > 1 else "HEAD"

    b, h = snapshot(base), snapshot(head)
    print(f"base properties: {len(b)}   head properties: {len(h)}")

    bad = detect_regressions(b, h)
    if bad:
        print(f"FAIL - {len(bad)} propert{'y' if len(bad) == 1 else 'ies'} regressed to unknown:")
        for (path, scope, prop), was, now in sorted(bad)[:40]:
            print(f"  {path} :: {scope}.{prop}\n     base: {was}\n     head: {now}")
        return 0 if selftest else 1

    print("PASS - no property regressed to unknown")
    if selftest:
        print("SELFTEST FAILED: expected this range to report a regression")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
