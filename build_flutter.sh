#!/bin/bash

# Скрипт для сборки и развертывания Flutter web приложения

echo "Building Flutter web app..."
cd flutter_app
flutter build web --base-href /flutter_app/

echo "Copying to public directory..."
cd ..
rm -rf public/flutter_app
cp -r flutter_app/build/web public/flutter_app

echo "Flutter app deployed to: https://excursion.panfilius.ru/flutter_app/"
echo "API redirect: https://excursion.panfilius.ru/api/app"


