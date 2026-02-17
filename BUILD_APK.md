# Инструкция по сборке Android APK

## Установка Android SDK

Для сборки Android APK необходимо установить Android SDK. Выполните:

```bash
cd /var/www/www-root/data/www/excursion.panfilius.ru
./setup_android_sdk.sh
```

Этот скрипт:
1. Скачает Android SDK command-line tools
2. Установит необходимые компоненты (platform-tools, Android 34, build-tools)
3. Настроит Flutter для работы с Android SDK
4. Обновит `flutter_app/android/local.properties`

## Сборка APK

После установки Android SDK выполните:

```bash
cd /var/www/www-root/data/www/excursion.panfilius.ru/flutter_app

# Установите переменные окружения
export ANDROID_HOME=/var/www/www-root/data/www/excursion.panfilius.ru/android-sdk
export ANDROID_SDK_ROOT=$ANDROID_HOME
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools

# Проверьте настройки
flutter doctor

# Соберите APK
flutter build apk

# Или для release версии
flutter build apk --release
```

APK файл будет создан в: `flutter_app/build/app/outputs/flutter-apk/app-release.apk`

## Альтернативный способ (если скрипт не работает)

1. Скачайте Android SDK вручную:
```bash
cd /var/www/www-root/data/www/excursion.panfilius.ru
mkdir -p android-sdk
cd android-sdk
wget https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
unzip commandlinetools-linux-11076708_latest.zip
mkdir -p cmdline-tools
# Переместите содержимое распакованной папки в cmdline-tools/latest
```

2. Установите компоненты:
```bash
export ANDROID_HOME=$(pwd)
export ANDROID_SDK_ROOT=$ANDROID_HOME
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools

yes | sdkmanager --licenses
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"
```

3. Настройте Flutter:
```bash
cd ../flutter_app
flutter config --android-sdk $ANDROID_HOME
```

## Примечания

- Android SDK требует около 1-2 GB свободного места
- Установка может занять 10-15 минут в зависимости от скорости интернета
- Для production сборки рекомендуется настроить подпись APK (см. `android/app/build.gradle.kts`)
