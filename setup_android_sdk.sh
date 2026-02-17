#!/bin/bash
# Скрипт для установки Android SDK и настройки окружения для сборки Flutter APK

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANDROID_SDK_DIR="$SCRIPT_DIR/android-sdk"

echo "Setting up Android SDK in $ANDROID_SDK_DIR..."

# Проверяем и устанавливаем Java 17+, если нужно
if ! command -v java &> /dev/null || ! java -version 2>&1 | grep -q "version \"1[7-9]\|version \"2[0-9]"; then
    echo "Java 17+ not found. Installing OpenJDK 17..."
    apt-get update -qq > /dev/null 2>&1
    apt-get install -y -qq openjdk-17-jdk > /dev/null 2>&1 || {
        echo "Failed to install Java. Please install Java 17+ manually:"
        echo "  apt-get update && apt-get install -y openjdk-17-jdk"
        exit 1
    }
fi

# Устанавливаем JAVA_HOME
if [ -z "$JAVA_HOME" ]; then
    # Ищем Java 17 или выше
    JAVA_HOME=$(update-alternatives --list java 2>/dev/null | grep -E "java-17|java-21|java-19" | head -1 | sed "s:bin/java::" || \
                find /usr/lib/jvm -name "java-17*" -type d 2>/dev/null | head -1 || \
                find /usr/lib/jvm -name "java-21*" -type d 2>/dev/null | head -1 || \
                readlink -f /usr/bin/java | sed "s:bin/java::")
    export JAVA_HOME
    echo "JAVA_HOME set to: $JAVA_HOME"
fi

# Создаем директорию для Android SDK
mkdir -p "$ANDROID_SDK_DIR"
cd "$ANDROID_SDK_DIR"

# Скачиваем command-line tools
if [ ! -f "cmdline-tools.zip" ]; then
    echo "Downloading Android SDK command-line tools..."
    wget -q https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip -O cmdline-tools.zip
fi

# Распаковываем
if [ ! -d "cmdline-tools/latest" ]; then
    echo "Extracting command-line tools..."
    unzip -o -q cmdline-tools.zip
    # После распаковки создается директория cmdline-tools, нужно переместить её содержимое
    if [ -d "cmdline-tools" ] && [ ! -d "cmdline-tools/latest" ]; then
        # Проверяем структуру после распаковки
        if [ -d "cmdline-tools/bin" ]; then
            # Если уже есть cmdline-tools с содержимым, перемещаем в latest
            mkdir -p cmdline-tools-temp
            mv cmdline-tools/* cmdline-tools-temp/ 2>/dev/null || true
            rmdir cmdline-tools 2>/dev/null || rm -rf cmdline-tools
            mkdir -p cmdline-tools
            mv cmdline-tools-temp cmdline-tools/latest
        fi
    fi
fi

# Устанавливаем переменные окружения
export ANDROID_HOME="$ANDROID_SDK_DIR"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"

# Принимаем лицензии
echo "Accepting Android SDK licenses..."
yes | sdkmanager --licenses > /dev/null 2>&1 || true

# Устанавливаем необходимые компоненты
echo "Installing Android SDK components..."
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"

# Настраиваем Flutter
echo "Configuring Flutter..."
cd "$SCRIPT_DIR/flutter_app"
flutter config --android-sdk "$ANDROID_SDK_DIR"

# Обновляем local.properties
cat > android/local.properties << EOF
flutter.sdk=/root/flutter
sdk.dir=$ANDROID_SDK_DIR
EOF

echo ""
echo "✅ Android SDK setup complete!"
echo ""
echo "To build APK, run:"
echo "  cd $SCRIPT_DIR/flutter_app"
echo "  export ANDROID_HOME=$ANDROID_SDK_DIR"
echo "  export ANDROID_SDK_ROOT=\$ANDROID_HOME"
echo "  export PATH=\$PATH:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools"
echo "  flutter build apk"
echo ""
