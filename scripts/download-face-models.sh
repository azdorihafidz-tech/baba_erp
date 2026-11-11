#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="$SCRIPT_DIR/../public/models"

BASE="https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights"

FILES=(
    "tiny_face_detector_model-weights_manifest.json"
    "tiny_face_detector_model-shard1"
    "face_landmark_68_model-weights_manifest.json"
    "face_landmark_68_model-shard1"
    "face_recognition_model-weights_manifest.json"
    "face_recognition_model-shard1"
    "face_recognition_model-shard2"
)

echo "============================================================"
echo " Download face-api.js Model Files"
echo " Target: $TARGET_DIR"
echo "============================================================"
echo

mkdir -p "$TARGET_DIR"
cd "$TARGET_DIR"

for FILE in "${FILES[@]}"; do
    echo -n "Downloading $FILE ... "
    curl -L --fail -o "$FILE" "$BASE/$FILE"
    echo "OK"
done

echo
echo "============================================================"
echo " Selesai! File di public/models/:"
echo "============================================================"
ls -lh
