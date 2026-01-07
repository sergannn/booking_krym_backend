#!/bin/bash

# Скрипт для сборки и развертывания Flutter APK

echo "Building Flutter APK..."
cd flutter_app
flutter build apk --release

echo "Copying APK to public directory..."
cd ..

# Создаем папку если её нет
mkdir -p public/apk

# Ищем собранный APK
APK_PATH=$(find flutter_app/build/app/outputs/flutter-apk -name "*.apk" -type f | head -1)

if [ -z "$APK_PATH" ]; then
    echo "Error: APK file not found!"
    exit 1
fi

# Копируем APK с датой в имени
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
APK_NAME="excursion_${TIMESTAMP}.apk"
cp "$APK_PATH" "public/apk/$APK_NAME"

# Также копируем как latest.apk для удобства
cp "$APK_PATH" "public/apk/latest.apk"

echo "APK deployed to: https://excursion.panfilius.ru/download"
echo "APK file: $APK_NAME"
echo "Latest APK: latest.apk"
