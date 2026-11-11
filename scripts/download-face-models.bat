@echo off
setlocal

echo ============================================================
echo  Download face-api.js Model Files
echo  Target: public\models\
echo ============================================================
echo.

cd /d "%~dp0..\public\models"
if errorlevel 1 (
    echo ERROR: Gagal masuk ke folder public\models\
    pause
    exit /b 1
)

set BASE=https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights

set FILES=^
    tiny_face_detector_model-weights_manifest.json ^
    tiny_face_detector_model-shard1 ^
    face_landmark_68_model-weights_manifest.json ^
    face_landmark_68_model-shard1 ^
    face_recognition_model-weights_manifest.json ^
    face_recognition_model-shard1 ^
    face_recognition_model-shard2

for %%F in (%FILES%) do (
    echo Downloading %%F ...
    curl -L --fail -o "%%F" "%BASE%/%%F"
    if errorlevel 1 (
        echo   ERROR: Gagal download %%F
    ) else (
        echo   OK: %%F
    )
)

echo.
echo ============================================================
echo  Selesai! Verifikasi file di folder public\models\
echo ============================================================
dir /b
echo.
pause
