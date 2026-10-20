#!/bin/bash

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="$PROJECT_ROOT/blast"
BLAST_VERSION="2.2.26"
DOWNLOAD_URL="https://ftp.ncbi.nlm.nih.gov/blast/executables/legacy.NOTSUPPORTED/$BLAST_VERSION/blast-$BLAST_VERSION-x64-linux.tar.gz"
TMP_FILE="/tmp/blast.tar.gz"

echo "Starting BLAST $BLAST_VERSION installation..."

if [ ! -d "$TARGET_DIR" ]; then
    mkdir -p "$TARGET_DIR"
    echo "Created directory: $TARGET_DIR"
fi

echo "Downloading BLAST..."
curl -L $DOWNLOAD_URL -o $TMP_FILE

echo "Extracting binaries..."
# --strip-components=1 avoids creating an extra nested 'blast-2.2.26' folder
tar -zxvf $TMP_FILE -C "$TARGET_DIR" --strip-components=1

rm $TMP_FILE

if [ -f "$TARGET_DIR/bin/blastall" ]; then
    echo "BLAST installed successfully in $TARGET_DIR/bin"
    "$TARGET_DIR/bin/blastall" | head -n 1
else
    echo "Installation failed."
    exit 1
fi

