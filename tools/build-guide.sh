#!/bin/sh
# Builds the user guide PDFs: assets/docs/wp-vegvesen-guide-en.pdf and -nb.pdf.
# Usage (from the plugin folder): sh tools/build-guide.sh /path/to/wordpress [php] [chrome]
set -e
WP="${1:?Usage: sh tools/build-guide.sh /path/to/wordpress [php] [chrome]}"
PHP="${2:-php}"
CHROME="${3:-chrome}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
# Path for Chrome: a Windows path under Git Bash (pwd -W), the normal one elsewhere.
DIRB="$(cd "$DIR" && (pwd -W 2>/dev/null || pwd))"
"$PHP" "$DIR/tools/build-guide.php" "$WP"
for lang in en nb; do
	html="$DIR/assets/docs/wp-vegvesen-guide-$lang.html"
	"$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="$DIRB/assets/docs/wp-vegvesen-guide-$lang.pdf" "file:///$DIRB/assets/docs/wp-vegvesen-guide-$lang.html"
	rm -f "$html"
done
