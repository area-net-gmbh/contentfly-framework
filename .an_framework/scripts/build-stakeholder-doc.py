#!/usr/bin/env python3
# PURPOSE: Render an_project/stakeholder/*.md into a self-contained, offline HTML documentation
# for stakeholders (no server, no internet, no build toolchain). Shipped read-only in the core,
# executed at project runtime by the `doc` workflow.
#
# Writes ONLY into an_project/stakeholder/html/ — never into .an_framework/.
# Fails LOUD (non-zero exit + message): it is invoked explicitly, so a broken build must be seen.
# Standard library only, Python 3.9 compatible (no match, no X | Y annotations).
"""Erzeugt die Stakeholder-Dokumentation als statisches HTML.

    python3 .an_framework/scripts/build-stakeholder-doc.py [--open|--check|--clean]

Quelle : an_project/stakeholder/steckbrief.md + an_project/stakeholder/*.md
Ziel   : an_project/stakeholder/html/
"""

import argparse
import hashlib
import html as html_mod
import json
import re
import shutil
import sys
import webbrowser
from pathlib import Path

GENERATOR_VERSION = "1"

# Der immer gleiche Dokumentkopf. Reihenfolge und Beschriftung sind hier festgelegt, damit
# jede Projektdokumentation identisch aufgebaut ist. (key, Beschriftung, ueber volle Breite)
FACT_FIELDS = (
    ("kunde", "Kunde", False),
    ("projekt", "Projekt", False),
    ("status", "Status", False),
    ("version", "Aktuelle Version", False),
    ("zweck", "Zweck", True),
    ("stack", "Technischer Stack", True),
    ("entwicklungsbeginn", "Entwicklungsbeginn", False),
    ("release", "Release / Live-Gang", False),
    ("stand", "Stand des Dokuments", False),
    ("hosting", "Hosting / Betrieb", False),
    ("url_live", "Live-Adresse", False),
    ("url_staging", "Staging / Testsystem", False),
    ("url_lokal", "Lokale Entwicklung", False),
    ("ansprechpartner_kunde", "Ansprechpartner Kunde", False),
    ("ansprechpartner_projektleitung", "Projektleitung AREA-NET", False),
    ("ansprechpartner_technik", "Technik AREA-NET", False),
    ("vertraulichkeit", "Vertraulichkeit", True),
)

PAGE_STATUS_NOTE = {
    "entwurf": ("", "Dieser Abschnitt ist ein Entwurf und noch nicht abgestimmt."),
    "offen": ("is-open", "Dieser Abschnitt ist noch nicht ausgefüllt."),
    "entfällt": ("", "Dieser Abschnitt ist für dieses Projekt nicht relevant."),
}

# "offen" heisst: fehlt noch, bitte nachfragen. "entfällt" heisst: bewusst abgewählt,
# nie wieder nachfragen. Ohne den zweiten Zustand bleibt jede nicht zutreffende Frage
# für immer eine Luecke, die bei jedem doc-/done-Lauf erneut gestellt wird.
PAGE_STATUS = ("gepflegt", "entwurf", "offen", "entfällt")
PAGE_STATUS_ALIAS = {"entfaellt": "entfällt", "n/a": "entfällt"}

UMLAUTS = (("ä", "ae"), ("ö", "oe"), ("ü", "ue"), ("ß", "ss"), ("à", "a"), ("é", "e"), ("è", "e"))


class BuildError(Exception):
    """Ein Fehler, der dem Aufrufer gemeldet und nicht stillschweigend übergangen wird."""


# --------------------------------------------------------------------- Hilfen

def slugify(text):
    value = text.strip().lower()
    for src, dst in UMLAUTS:
        value = value.replace(src, dst)
    value = re.sub(r"<[^>]+>", "", value)
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return value.strip("-") or "abschnitt"


def esc(text):
    return html_mod.escape(text, quote=False)


def attr(text):
    return html_mod.escape(text, quote=True)


def attr_escaped(text):
    """Bereits escapten Text fuer ein Attribut absichern — nur noch Anfuehrungszeichen."""
    return text.replace('"', "&quot;")


def strip_markdown(text):
    """Markdown-Auszeichnung entfernen — fuer Anker, Titel und Suchtext."""
    value = re.sub(r"`([^`]*)`", r"\1", text)
    value = re.sub(r"!\[([^\]]*)\]\([^)]*\)", r"\1", value)
    value = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", value)
    value = re.sub(r"[*_#>|]+", " ", value)
    return re.sub(r"\s+", " ", value).strip()


def german_date(value):
    match = re.match(r"^(\d{4})-(\d{2})-(\d{2})$", value.strip())
    if not match:
        return value.strip()
    return "%s.%s.%s" % (match.group(3), match.group(2), match.group(1))


def status_pill_class(value):
    low = value.lower()
    if "live" in low or "betrieb" in low:
        return "pill-live"
    if "pilot" in low or "abnahme" in low or "test" in low:
        return "pill-pilot"
    if "wartung" in low or "pflege" in low:
        return "pill-wartung"
    return "pill-dev"


def strip_comments(lines):
    """HTML-Kommentare entfernen: sie tragen Hinweise fuer ANNA, nicht fuer den Leser."""
    text = re.sub(r"<!--.*?-->", "", "\n".join(lines), flags=re.S)
    return text.split("\n")


def parse_frontmatter(path):
    """`---`-Block am Dateianfang als flache key: value-Zuordnung lesen."""
    raw = path.read_text(encoding="utf-8")
    lines = raw.split("\n")
    meta = {}
    # Vor dem Frontmatter duerfen Leerzeilen und einzeilige HTML-Kommentare stehen —
    # die PURPOSE-Zeile ist in diesem Framework die erste Zeile jeder Datei.
    start = 0
    while start < len(lines) and (
        not lines[start].strip() or lines[start].lstrip().startswith("<!--")
    ):
        start += 1
    if start >= len(lines) or lines[start].strip() != "---":
        return meta, lines
    for index in range(start + 1, len(lines)):
        stripped = lines[index].strip()
        if stripped == "---":
            return meta, lines[index + 1:]
        if not stripped or stripped.startswith("#"):
            continue
        if ":" not in stripped:
            raise BuildError(
                "%s, Zeile %d: '%s' ist kein 'schluessel: wert'-Paar."
                % (path.name, index + 1, stripped)
            )
        key, value = stripped.split(":", 1)
        meta[key.strip().lower()] = value.strip()
    raise BuildError("%s: der Frontmatter-Block ist nicht mit '---' geschlossen." % path.name)


# ------------------------------------------------------------- Markdown-Block

FENCE_RE = re.compile(r"^(\s*)(```+|~~~+)\s*(.*)$")
HEADING_RE = re.compile(r"^(#{1,4})\s+(.*?)\s*#*\s*$")
HR_RE = re.compile(r"^\s*([-*_])(\s*\1){2,}\s*$")
LIST_RE = re.compile(r"^(\s*)([-*+]|\d+[.)])\s+(.*)$")
TASK_RE = re.compile(r"^\[([ xX])\]\s+(.*)$")
TABLE_SEP_RE = re.compile(r"^\s*\|?(\s*:?-{1,}:?\s*\|)+\s*:?-{1,}:?\s*\|?\s*$")


class Renderer:
    """Begrenzter Markdown-Dialekt → HTML. Siehe .an_framework/docs/stakeholder-doc.md."""

    def __init__(self, page_name):
        self.page_name = page_name
        self.ids = {}
        self.toc = []
        self.diagrams = 0

    # -- Inline ---------------------------------------------------------
    def inline(self, text):
        codes = []

        def grab(match):
            codes.append(match.group(2))
            return "\x00%d\x00" % (len(codes) - 1)

        text = re.sub(r"(`+)(.+?)\1", grab, text, flags=re.S)
        text = esc(text)

        def image(match):
            url = self.safe_url(match.group(2))
            return '<img src="%s" alt="%s">' % (
                attr_escaped(url),
                attr_escaped(match.group(1)),
            )

        def link(match):
            url = self.safe_url(match.group(2))
            return '<a href="%s">%s</a>' % (attr_escaped(url), match.group(1))

        text = re.sub(r"!\[([^\]]*)\]\(([^)\s]+)\)", image, text)
        text = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", link, text)
        text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text, flags=re.S)
        text = re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"<em>\1</em>", text)
        text = re.sub(r"(?<![\w_])_([^_\n]+)_(?![\w_])", r"<em>\1</em>", text)
        text = re.sub(r" {2,}\n", "<br>\n", text)
        text = text.replace("\n", " ")
        for index, code in enumerate(codes):
            text = text.replace("\x00%d\x00" % index, "<code>%s</code>" % esc(code))
        return text

    def safe_url(self, url):
        cleaned = url.strip()
        low = cleaned.lower().replace(" ", "")
        dangerous = low.startswith("javascript:") or low.startswith("vbscript:")
        if low.startswith("data:") and not low.startswith("data:image/"):
            dangerous = True
        if dangerous:
            raise BuildError(
                "%s: die Adresse '%s' ist in einem Dokument nicht erlaubt."
                % (self.page_name, url.strip())
            )
        return cleaned

    # -- Blöcke ---------------------------------------------------------
    def heading_id(self, plain):
        base = slugify(plain)
        count = self.ids.get(base, 0) + 1
        self.ids[base] = count
        return base if count == 1 else "%s-%d" % (base, count)

    def starts_block(self, line):
        if not line.strip():
            return True
        return bool(
            FENCE_RE.match(line)
            or HEADING_RE.match(line)
            or HR_RE.match(line)
            or LIST_RE.match(line)
            or line.lstrip().startswith(">")
            or line.lstrip().startswith("<")
        )

    def render(self, lines):
        out = []
        index = 0
        total = len(lines)
        while index < total:
            line = lines[index]
            if not line.strip():
                index += 1
                continue

            fence = FENCE_RE.match(line)
            if fence:
                marker = fence.group(2)[0] * 3
                info = fence.group(3).strip()
                body = []
                index += 1
                while index < total and not lines[index].strip().startswith(marker):
                    body.append(lines[index])
                    index += 1
                if index >= total:
                    raise BuildError(
                        "%s: ein Code-Block (```) wurde nicht geschlossen." % self.page_name
                    )
                index += 1
                out.append(self.fenced(info, "\n".join(body)))
                continue

            heading = HEADING_RE.match(line)
            if heading:
                level = len(heading.group(1))
                raw = heading.group(2)
                plain = strip_markdown(raw)
                anchor = self.heading_id(plain)
                if level in (2, 3):
                    self.toc.append((level, anchor, plain))
                out.append(
                    '<h%d id="%s">%s<a class="heading-anchor" href="#%s" '
                    'aria-label="Direktlink zu diesem Abschnitt">#</a></h%d>'
                    % (level, anchor, self.inline(raw), anchor, level)
                )
                index += 1
                continue

            if HR_RE.match(line):
                out.append("<hr>")
                index += 1
                continue

            if line.lstrip().startswith(">"):
                quote = []
                while index < total and lines[index].lstrip().startswith(">"):
                    quote.append(re.sub(r"^\s*>\s?", "", lines[index]))
                    index += 1
                out.append("<blockquote>%s</blockquote>" % self.render(quote))
                continue

            if "|" in line and index + 1 < total and TABLE_SEP_RE.match(lines[index + 1]):
                table, index = self.table(lines, index)
                out.append(table)
                continue

            if LIST_RE.match(line):
                listing, index = self.list_block(lines, index)
                out.append(listing)
                continue

            if line.strip() == "{{STECKBRIEF}}":
                out.append(line.strip())
                index += 1
                continue

            if line.lstrip().startswith("<"):
                raw_block = []
                while index < total and lines[index].strip():
                    raw_block.append(lines[index])
                    index += 1
                out.append("\n".join(raw_block))
                continue

            paragraph = [line]
            index += 1
            while index < total and not self.starts_block(lines[index]):
                paragraph.append(lines[index])
                index += 1
            out.append("<p>%s</p>" % self.inline("\n".join(paragraph)))

        return "\n".join(out)

    def fenced(self, info, code):
        if info.split(" ")[0].lower() == "mermaid":
            self.diagrams += 1
            caption = info[len("mermaid"):].strip()
            label = caption or "Diagramm"
            figcaption = "<figcaption>%s</figcaption>" % esc(caption) if caption else ""
            return (
                '<figure class="diagram-panel">'
                '<div class="mermaid" role="img" aria-label="%s">%s</div>'
                '<details class="diagram-source"><summary>Diagrammcode anzeigen</summary>'
                "<pre><code>%s</code></pre></details>%s</figure>"
                % (attr(label), esc(code), esc(code), figcaption)
            )
        language = (
            '<span class="code-language">%s</span>' % esc(info) if info else ""
        )
        return (
            '<div class="code-block">%s<pre><code>%s</code></pre></div>'
            % (language, esc(code))
        )

    def table(self, lines, index):
        def cells(row):
            row = row.strip()
            if row.startswith("|"):
                row = row[1:]
            if row.endswith("|"):
                row = row[:-1]
            return [
                cell.replace("\\|", "|").strip()
                for cell in re.split(r"(?<!\\)\|", row)
            ]

        header = cells(lines[index])
        index += 2
        body = []
        while index < len(lines) and "|" in lines[index] and lines[index].strip():
            body.append(cells(lines[index]))
            index += 1

        head_html = "".join(
            '<th scope="col">%s</th>' % self.inline(cell) for cell in header
        )
        rows = []
        for row in body:
            padded = row + [""] * (len(header) - len(row))
            rows.append(
                "<tr>%s</tr>"
                % "".join("<td>%s</td>" % self.inline(cell) for cell in padded[: len(header)])
            )
        return (
            '<div class="table-wrap" tabindex="0"><table><thead><tr>%s</tr></thead>'
            "<tbody>%s</tbody></table></div>" % (head_html, "".join(rows))
        ), index

    def list_block(self, lines, index):
        first = LIST_RE.match(lines[index])
        base = len(first.group(1))
        ordered = first.group(2) not in ("-", "*", "+")
        items = []
        total = len(lines)
        while index < total:
            if not lines[index].strip():
                if index + 1 < total and LIST_RE.match(lines[index + 1]):
                    nxt = LIST_RE.match(lines[index + 1])
                    if len(nxt.group(1)) >= base:
                        index += 1
                        continue
                break
            match = LIST_RE.match(lines[index])
            if not match:
                break
            indent = len(match.group(1))
            if indent < base:
                break
            if indent == base and (match.group(2) not in ("-", "*", "+")) != ordered:
                break
            if indent > base:
                if not items:
                    raise BuildError("%s: eingerückte Liste ohne Elternpunkt." % self.page_name)
                nested, index = self.list_block(lines, index)
                items[-1] = items[-1][: -len("</li>")] + nested + "</li>"
                continue
            content = match.group(3)
            task = TASK_RE.match(content)
            if task:
                checked = " checked" if task.group(1).lower() == "x" else ""
                items.append(
                    '<li class="check-item"><input type="checkbox" disabled%s>'
                    "<span>%s</span></li>" % (checked, self.inline(task.group(2)))
                )
            else:
                items.append("<li>%s</li>" % self.inline(content))
            index += 1
        tag = "ol" if ordered else "ul"
        return "<%s>%s</%s>" % (tag, "".join(items), tag), index


# ------------------------------------------------------------------ Dokument

def search_text(body_lines):
    """Suchtext aus dem Markdown — ohne Code- und Diagramm-Bloecke."""
    kept = []
    inside = False
    for line in body_lines:
        fence = FENCE_RE.match(line)
        if fence:
            inside = not inside
            continue
        if inside or TABLE_SEP_RE.match(line):
            continue
        kept.append(line)
    text = re.sub(r"\{\{[A-Z_]+\}\}", " ", " ".join(kept))
    return strip_markdown(text)


def load_pages(stake_dir):
    sources = sorted(
        path for path in stake_dir.glob("*.md") if path.name != "steckbrief.md"
    )
    if not sources:
        raise BuildError(
            "keine Kapitel gefunden: %s enthält keine .md-Datei außer steckbrief.md." % stake_dir
        )
    index_pages = [path for path in sources if path.stem == "index"]
    if not index_pages:
        raise BuildError("%s/index.md fehlt — das ist die Startseite." % stake_dir)
    ordered = index_pages + [path for path in sources if path.stem != "index"]

    pages = []
    position = 0
    for path in ordered:
        meta, body = parse_frontmatter(path)
        body = strip_comments(body)
        is_index = path.stem == "index"
        title = meta.get("title", "").strip()
        if not title:
            for line in body:
                heading = HEADING_RE.match(line)
                if heading:
                    title = strip_markdown(heading.group(2))
                    break
        if not title:
            raise BuildError("%s: kein 'title:' im Frontmatter und keine Überschrift." % path.name)
        status = meta.get("status", "gepflegt").strip().lower()
        status = PAGE_STATUS_ALIAS.get(status, status)
        if status not in PAGE_STATUS:
            raise BuildError(
                "%s: status: '%s' ist unbekannt (%s)."
                % (path.name, status, " | ".join(PAGE_STATUS))
            )
        if is_index:
            number = "Ü"
            url = "index.html"
        else:
            position += 1
            number = "%02d" % position
            url = "pages/%s.html" % slugify(re.sub(r"^\d+[-_]*", "", path.stem))
        pages.append(
            {
                "path": path,
                "meta": meta,
                "body": body,
                "title": title,
                "status": status,
                "stand": meta.get("stand", "").strip(),
                "number": number,
                "url": url,
                "is_index": is_index,
            }
        )
    return pages


def fact_sheet_html(facts):
    known = set(key for key, _label, _wide in FACT_FIELDS)
    items = []

    def add(key, label, wide):
        value = facts.get(key, "").strip()
        classes = "fact fact-wide" if wide else "fact"
        if not value:
            body = '<dd class="is-open">offen</dd>'
        elif key == "status":
            body = '<dd><span class="pill %s">%s</span></dd>' % (
                status_pill_class(value),
                esc(value),
            )
        elif key == "stand":
            body = "<dd>%s</dd>" % esc(german_date(value))
        elif key.startswith("url_") and re.match(r"^[a-z]+://", value):
            body = '<dd><a href="%s">%s</a></dd>' % (attr(value), esc(value))
        else:
            body = "<dd>%s</dd>" % esc(value)
        items.append(
            '<div class="%s"><dt>%s</dt>%s</div>' % (classes, esc(label), body)
        )

    for key, label, wide in FACT_FIELDS:
        add(key, label, wide)
    for key in sorted(facts):
        if key not in known:
            add(key, key.replace("_", " ").strip().capitalize(), False)

    return (
        '<section class="fact-sheet" aria-labelledby="projekt-steckbrief">'
        '<h2 id="projekt-steckbrief">Projekt-Steckbrief</h2>'
        '<dl class="fact-grid">%s</dl></section>' % "".join(items)
    )


def sidebar_facts_html(facts):
    rows = []
    for key, label in (("kunde", "Kunde"), ("version", "Version"), ("stand", "Stand")):
        value = facts.get(key, "").strip()
        if not value:
            continue
        shown = german_date(value) if key == "stand" else value
        rows.append("<div><dt>%s</dt><dd>%s</dd></div>" % (esc(label), esc(shown)))
    if not rows:
        return ""
    return '<dl class="sidebar-facts">%s</dl>' % "".join(rows)


def nav_html(pages, current, prefix):
    items = []
    for page in pages:
        active = page is current
        classes = "nav-link is-active" if active else "nav-link"
        aria = ' aria-current="page"' if active else ""
        items.append(
            '<li><a class="%s" href="%s%s"%s><span class="nav-number">%s</span>'
            "<span>%s</span></a></li>"
            % (classes, prefix, page["url"], aria, esc(page["number"]), esc(page["title"]))
        )
    return "".join(items)


def pager_html(pages, position, prefix):
    links = []
    if position > 0:
        previous = pages[position - 1]
        links.append(
            '<a class="pager-link pager-prev" href="%s%s"><span>Zurück</span>%s</a>'
            % (prefix, previous["url"], esc(previous["title"]))
        )
    if position + 1 < len(pages):
        following = pages[position + 1]
        links.append(
            '<a class="pager-link pager-next" href="%s%s"><span>Weiter</span>%s</a>'
            % (prefix, following["url"], esc(following["title"]))
        )
    if not links:
        return ""
    return (
        '        <nav class="pager" aria-label="Kapitelnavigation">%s</nav>' % "".join(links)
    )


def toc_html(entries):
    if len(entries) < 2:
        return ""
    items = "".join(
        '<li class="toc-level-%d"><a href="#%s">%s</a></li>' % (level, anchor, esc(label))
        for level, anchor, label in entries
    )
    return (
        '      <aside class="page-toc" aria-label="Auf dieser Seite">'
        "<h2>Auf dieser Seite</h2><ol>%s</ol></aside>" % items
    )


def fill(template, values):
    def replace(match):
        key = match.group(1)
        if key not in values:
            raise BuildError("page.template.html: unbekannter Platzhalter {{%s}}." % key)
        return values[key]

    return re.sub(r"\{\{([A-Z_]+)\}\}", replace, template)


# --------------------------------------------------------------------- Build

def fingerprint(core, stake_dir, assets):
    digest = hashlib.sha256()
    digest.update(GENERATOR_VERSION.encode("utf-8"))
    digest.update((core / "viewer" / "page.template.html").read_bytes())
    for path in sorted(stake_dir.glob("*.md")):
        digest.update(path.name.encode("utf-8"))
        digest.update(path.read_bytes())
    for path in sorted(assets):
        digest.update(path.name.encode("utf-8"))
        digest.update(path.read_bytes())
    return digest.hexdigest()


def build(root, core, do_write=True):
    stake_dir = root / "an_project" / "stakeholder"
    html_dir = stake_dir / "html"
    assets_src = core / "viewer" / "assets"
    template_path = core / "viewer" / "page.template.html"

    if not stake_dir.is_dir():
        raise BuildError(
            "%s fehlt. Die Stakeholder-Doku wird beim Setup angelegt — "
            "ist das Projekt mit init.sh initialisiert?" % stake_dir
        )
    if not assets_src.is_dir() or not template_path.is_file():
        raise BuildError("Viewer-Dateien fehlen im Framework-Kern (%s)." % (core / "viewer"))

    steckbrief_path = stake_dir / "steckbrief.md"
    if not steckbrief_path.is_file():
        raise BuildError("%s fehlt — er trägt den Dokumentkopf." % steckbrief_path)
    facts, _rest = parse_frontmatter(steckbrief_path)

    pages = load_pages(stake_dir)
    assets = sorted(path for path in assets_src.iterdir() if path.is_file())
    stamp = fingerprint(core, stake_dir, assets)

    rendered = []
    search_index = []
    diagrams = 0
    for page in pages:
        renderer = Renderer(page["path"].name)
        body = renderer.render(page["body"])
        diagrams += renderer.diagrams
        page["content"] = body
        page["toc"] = renderer.toc
        rendered.append(page)

        text = search_text(page["body"])
        search_index.append({"title": page["title"], "url": page["url"], "text": text})
        for level, anchor, label in renderer.toc:
            if level != 2:
                continue
            search_index.append(
                {
                    "title": "%s › %s" % (page["title"], label),
                    "url": "%s#%s" % (page["url"], anchor),
                    "text": section_text(page["body"], label),
                }
            )

    if not do_write:
        return {"stamp": stamp, "pages": pages, "diagrams": diagrams}

    if html_dir.exists():
        if html_dir.name != "html" or html_dir.parent != stake_dir:
            raise BuildError("unerwarteter Ausgabepfad: %s" % html_dir)
        shutil.rmtree(html_dir)
    (html_dir / "pages").mkdir(parents=True, exist_ok=True)
    (html_dir / "assets").mkdir(parents=True, exist_ok=True)
    for path in assets:
        shutil.copy2(path, html_dir / "assets" / path.name)
    (html_dir / "assets" / "search-index.js").write_text(
        "window.AREA_NET_SEARCH_INDEX=%s;\n"
        % json.dumps(search_index, ensure_ascii=False, separators=(",", ":")),
        encoding="utf-8",
    )

    template = template_path.read_text(encoding="utf-8")
    project_name = facts.get("projekt", "").strip() or facts.get("kunde", "").strip() or "Projekt"
    project_status = facts.get("status", "").strip() or "Status offen"
    newest = sorted(page["stand"] for page in pages if page["stand"])
    footer = "Automatisch erzeugt aus <code>an_project/stakeholder/</code>"
    if newest:
        footer += '<br>Stand <time datetime="%s">%s</time>' % (
            attr(newest[-1]),
            esc(german_date(newest[-1])),
        )
    facts_html = fact_sheet_html(facts)
    side_facts = sidebar_facts_html(facts)

    for position, page in enumerate(rendered):
        prefix = "" if page["is_index"] else "../"
        content = page["content"]
        if page["is_index"]:
            if "{{STECKBRIEF}}" in content:
                content = content.replace("{{STECKBRIEF}}", facts_html)
            else:
                content = inject_after_h1(content, facts_html)
        else:
            content = inject_after_h1(content, page_meta_html(page))
        values = {
            "TITLE": "%s | %s" % (page["title"], project_name)
            if not page["is_index"]
            else "%s — Projektdokumentation" % project_name,
            "DESCRIPTION": facts.get("zweck", "").strip()
            or "Projektdokumentation %s" % project_name,
            "PREFIX": prefix,
            "PAGE_URL": page["url"],
            "PROJECT_NAME": esc(project_name),
            "PROJECT_STATUS": esc(project_status),
            "SIDEBAR_FACTS": side_facts,
            "NAV": nav_html(rendered, page, prefix),
            "CONTENT": content,
            "PAGER": pager_html(rendered, position, prefix),
            "TOC": toc_html(page["toc"]),
            "FOOTER": footer,
        }
        target = html_dir / page["url"]
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(fill(template, values), encoding="utf-8")

    (html_dir / ".build-stamp").write_text(stamp + "\n", encoding="utf-8")
    return {"stamp": stamp, "pages": rendered, "diagrams": diagrams}


def section_text(body_lines, label):
    """Text eines ##-Abschnitts fuer die Suche."""
    collecting = False
    kept = []
    for line in body_lines:
        heading = HEADING_RE.match(line)
        if heading:
            plain = strip_markdown(heading.group(2))
            if len(heading.group(1)) == 2:
                if collecting:
                    break
                collecting = plain == label
                if collecting:
                    kept.append(plain)
                continue
        if collecting:
            kept.append(line)
    return search_text(kept)


def inject_after_h1(content, extra):
    match = re.search(r"</h1>", content)
    if not match:
        return extra + "\n" + content
    cut = match.end()
    return content[:cut] + "\n" + extra + content[cut:]


def page_meta_html(page):
    parts = ["Kapitel %s" % esc(page["number"])]
    if page["stand"]:
        parts.append("Stand: %s" % esc(german_date(page["stand"])))
    meta = '<div class="section-meta">%s</div>' % "".join(
        "<span>%s</span>" % part for part in parts
    )
    note = PAGE_STATUS_NOTE.get(page["status"])
    if note:
        meta += '<p class="section-status %s">%s</p>' % (note[0], esc(note[1]))
    return meta


# ----------------------------------------------------------------------- CLI

def find_root(given):
    if given:
        root = Path(given).expanduser().resolve()
        if not (root / "an_project").is_dir():
            raise BuildError("%s enthält kein an_project/." % root)
        return root
    candidates = [Path.cwd().resolve()]
    core_parent = Path(__file__).resolve().parent.parent.parent
    candidates.append(core_parent)
    for candidate in candidates:
        if (candidate / "an_project" / "stakeholder").is_dir():
            return candidate
    raise BuildError(
        "kein Projekt gefunden: an_project/stakeholder/ liegt weder unter %s "
        "noch unter %s. Mit --root <pfad> den Projektpfad angeben."
        % (candidates[0], core_parent)
    )


def report_unfinished(pages):
    # "entfällt" ist erledigt, nicht offen — es darf hier nicht als Luecke auftauchen.
    unfinished = [page for page in pages if page["status"] not in ("gepflegt", "entfällt")]
    if not unfinished:
        return
    print("noch nicht gepflegt:")
    for page in unfinished:
        print("  - %s %s (%s)" % (page["number"], page["title"], page["status"]))


def report(result, root, quiet):
    if quiet:
        return
    print(
        "%d Kapitel · %d Diagramme → %s"
        % (
            len(result["pages"]),
            result["diagrams"],
            (root / "an_project" / "stakeholder" / "html" / "index.html"),
        )
    )
    report_unfinished(result["pages"])


def main(argv):
    parser = argparse.ArgumentParser(
        description="Stakeholder-Dokumentation aus an_project/stakeholder/*.md bauen."
    )
    parser.add_argument("--root", help="Projektwurzel (Standard: aktuelles Verzeichnis)")
    parser.add_argument("--open", action="store_true", dest="open_browser",
                        help="die erzeugte Startseite im Standardbrowser öffnen")
    parser.add_argument("--check", action="store_true",
                        help="nur prüfen, ob html/ zur Quelle passt (Exit 1 = veraltet)")
    parser.add_argument("--clean", action="store_true",
                        help="Ausgabeverzeichnis vorher vollständig löschen")
    parser.add_argument("--quiet", action="store_true", help="keine Zusammenfassung ausgeben")
    args = parser.parse_args(argv)

    try:
        root = find_root(args.root)
        core = Path(__file__).resolve().parent.parent
        html_dir = root / "an_project" / "stakeholder" / "html"

        if args.check:
            result = build(root, core, do_write=False)
            stamp_file = html_dir / ".build-stamp"
            current = stamp_file.read_text(encoding="utf-8").strip() if stamp_file.is_file() else ""
            fresh = current == result["stamp"]
            if not args.quiet:
                if fresh:
                    print("aktuell: an_project/stakeholder/html/ ist auf dem Stand der Quelle.")
                else:
                    print("veraltet: an_project/stakeholder/html/ passt nicht zur Quelle.")
                report_unfinished(result["pages"])
            return 0 if fresh else 1

        if args.clean and html_dir.exists():
            shutil.rmtree(html_dir)
        result = build(root, core)
        report(result, root, args.quiet)
        if args.open_browser:
            webbrowser.open((html_dir / "index.html").as_uri())
        return 0
    except BuildError as error:
        sys.stderr.write("build-stakeholder-doc: %s\n" % error)
        return 2
    except OSError as error:
        sys.stderr.write("build-stakeholder-doc: Dateifehler — %s\n" % error)
        return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
