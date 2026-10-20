#!/bin/sh
# PURPOSE: Build the downloadable core archive for one release — the ZIP that replaces the
# git bootstrap for everyone who does not want to use git. It holds the tracked tree of one
# ref under exactly one top-level directory: `.an_framework/`.
#
# Contract:
#   * PARITY — the archive is file-for-file what the git route produces (clone a tag, then
#     drop its `.git`). Nothing is added, nothing is filtered out. Two install routes that
#     hand out different trees are worse than having only one.
#   * FAILS LOUD — like the stakeholder generator, not like the hooks. A release asset that
#     is silently wrong reaches every project that downloads it.
#   * RELEASE TIME ONLY — this never runs inside a project, so it is not a fourth exception
#     to the read-only rule. It belongs next to check-contracts.sh, not next to init.sh.
#   * POSIX sh, no bashisms — it also has to run in a minimal CI image, where bash is absent.
#
# Exec bits do not survive every unzip (Windows Explorer drops them). That is safe here
# because nothing in the core is ever invoked directly: the adapters call `bash …/hooks/*.sh`
# and `python3 …/scripts/build-stakeholder-doc.py`. Keep it that way.
set -eu

REF="HEAD"
OUT_DIR="dist"
EXPECT_TAG="${CI_COMMIT_TAG:-}"

die() {
  printf 'build-release-zip: %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: sh scripts/build-release-zip.sh [--ref <git-ref>] [--out-dir <dir>] [--expect-tag <vX.Y.Z>]
  --ref         Commit, tag or branch to package (default: HEAD). The version is read from
                this ref's VERSION file, not from the working copy — so an older release can
                be rebuilt without checking it out.
  --out-dir     Where the .zip is written (default: dist, git-ignored).
  --expect-tag  Abort unless the ref's VERSION matches this tag. Defaults to $CI_COMMIT_TAG,
                which makes a tag pipeline refuse a release whose VERSION was not bumped.
USAGE
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --ref)         [ "$#" -ge 2 ] || die "--ref needs a value";         REF="$2"; shift 2 ;;
    --ref=*)       REF="${1#--ref=}"; shift ;;
    --out-dir)     [ "$#" -ge 2 ] || die "--out-dir needs a value";     OUT_DIR="$2"; shift 2 ;;
    --out-dir=*)   OUT_DIR="${1#--out-dir=}"; shift ;;
    --expect-tag)  [ "$#" -ge 2 ] || die "--expect-tag needs a value";  EXPECT_TAG="$2"; shift 2 ;;
    --expect-tag=*) EXPECT_TAG="${1#--expect-tag=}"; shift ;;
    -h|--help)     usage; exit 0 ;;
    *)             usage >&2; die "unknown argument: $1" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

git rev-parse --git-dir >/dev/null 2>&1 || die "not a git repository: $ROOT"
COMMIT="$(git rev-parse --verify --quiet "$REF^{commit}" || true)"
[ -n "$COMMIT" ] || die "unknown git ref: $REF"

# The version comes out of the ref itself, so archive and version can never disagree.
VERSION="$(git show "$COMMIT:VERSION" 2>/dev/null | sed -n '1{s/^v//;s/[[:space:]]*$//;p;}')"
printf '%s\n' "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' ||
  die "VERSION at $REF is not a three-part number: '$VERSION'"

TAG="v$VERSION"
if [ -n "$EXPECT_TAG" ] && [ "$EXPECT_TAG" != "$TAG" ]; then
  die "tag/VERSION mismatch: the tag is $EXPECT_TAG, VERSION says $TAG.
Bump VERSION in the commit you tag. Otherwise the download and the git route
hand out two different version numbers for the same release."
fi

ZIP="$OUT_DIR/an_framework-$TAG.zip"
mkdir -p "$OUT_DIR" || die "cannot create output directory: $OUT_DIR"
rm -f "$ZIP"

git archive --format=zip -9 --prefix='.an_framework/' -o "$ZIP" "$COMMIT" ||
  die "git archive failed for $REF"
[ -s "$ZIP" ] || die "git archive produced an empty archive: $ZIP"

# One top-level directory, and it is the one the project expects. A changed prefix would
# only show up when somebody unpacks the release — too late.
if command -v unzip >/dev/null 2>&1; then
  STRAY="$(unzip -Z1 "$ZIP" | grep -v '^\.an_framework/' | head -n 3 || true)"
  [ -z "$STRAY" ] || die "archive holds entries outside .an_framework/:
$STRAY"
fi

printf '%s\n' "$ZIP"
printf '  ref     : %s (%s)\n' "$REF" "$(git rev-parse --short "$COMMIT")"
printf '  version : %s\n' "$TAG"
printf '  files   : %s under .an_framework/\n' \
  "$(git ls-tree -r --name-only "$COMMIT" | wc -l | tr -d ' ')"
printf '  size    : %s bytes\n' "$(wc -c <"$ZIP" | tr -d ' ')"
