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

Usage: unknown-regression-gate.py [BASE_REV] [HEAD_REV]
Exit 1 if any property regressed. Self-test: --selftest RANGE expects a FAIL.
Parser self-test: --parsetest checks the member-splitting logic in isolation.
"""
import re
import subprocess
import sys

ROOT = "workbench/resources/js/types/data"
PROP = re.compile(r"^\s*([A-Za-z_$][\w$]*)\??:\s*(.+?);\s*$")
ALIAS = re.compile(r"^\s*(?:export\s+)?(?:declare\s+)?type\s+([A-Za-z_$][\w$]*)\s*=\s*(.+?);\s*$")
MEMBER = re.compile(r"^([A-Za-z_$][\w$]*)\??:\s*(.+)$")
BLOCK = re.compile(r"^\s*(?:export\s+)?(?:declare\s+)?(interface|namespace|module)\s+([^\s{]+)")
OPENERS, CLOSERS = "{[(<", "}])>"


def git(*args: str) -> str:
    return subprocess.run(["git", *args], capture_output=True, text=True, check=True).stdout


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
                out.update(expand(f"{prefix}.{member.group(1)}", member.group(2)))
    return out


def parse_source(body: str) -> dict[tuple[str, str], str]:
    out: dict[tuple[str, str], str] = {}
    stack: list[tuple[str, int]] = []  # (name, brace depth at which it opened)
    pending: str | None = None  # block header whose '{' is on a later line
    depth = 0
    for raw in body.splitlines():
        line = raw.split("//")[0]
        block = BLOCK.match(line)
        if block:
            pending = block.group(2)
        if not block:
            match = PROP.match(raw) or ALIAS.match(raw)
            if match:
                scope = ".".join(n for n, _ in stack) or "<root>"
                for key, val in expand(match.group(1), match.group(2)).items():
                    out[(scope, key)] = val
        opens = line.count("{")
        # Push only once the brace is seen: this repo puts `interface Foo` and `{` on separate
        # lines, and pushing at the header's depth pops it again on that very line.
        if pending is not None and opens:
            stack.append((pending, depth))
            pending = None
        depth += opens - line.count("}")
        while stack and depth <= stack[-1][1]:
            stack.pop()
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


def detect_regressions(b: dict, h: dict) -> list:
    head_keys = list(h)
    return [
        (k, b[k], h[k])
        for k in h
        if k in b
        and "unknown" not in b[k]
        and "unknown" in h[k]
        # A key with members still under it in head is left to member-level comparison; only a
        # true whole-object collapse (no head members left) is compared at the parent level, so
        # a nested change doesn't also flag its parent.
        and not any(
            other[:-1] == k[:-1] and (other[-1].startswith(k[-1] + ".") or other[-1].startswith(k[-1] + "["))
            for other in head_keys
        )
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


def run_parsetest() -> int:
    failed = 0
    for name, source, want in PARSE_CASES:
        got = parse_source(source)
        if all(got.get(k) == v for k, v in want.items()):
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

    total = len(PARSE_CASES) + 3
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
