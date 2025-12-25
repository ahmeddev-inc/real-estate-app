#!/bin/bash
echo "🚀 بدء بيئة عقار زين للتطوير..."

# تحقق من وجود Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker غير مثبت! الرجاء تثبيت Docker أولاً."
    exit 1
fi

# نسخ ملف البيئة إذا لم يكن موجوداً
if [ ! -f .env ]; then
    echo "📄 نسخ ملف البيئة من المثال..."
    cp .env.example .env
fi

# تشغيل Docker Compose
echo "🐳 تشغيل حاويات Docker..."
docker-compose -f docker-compose.dev.yml up -d

# انتظار تشغيل الخدمات
echo "⏳ انتظار تشغيل الخدمات..."
sleep 10

# تثبيت اعتماديات PHP
echo "📦 تثبيت اعتماديات Composer..."
docker-compose -f docker-compose.dev.yml exec app composer install

# توليد مفتاح التطبيق
echo "🔑 توليد مفتاح التطبيق..."
docker-compose -f docker-compose.dev.yml exec app php artisan key:generate

# تشغيل ترحيلات قاعدة البيانات
echo "🗄️ تشغيل ترحيلات قاعدة البيانات..."
docker-compose -f docker-compose.dev.yml exec app php artisan migrate

# تثبيت اعتماديات NPM
echo "📦 تثبيت اعتماديات NPM..."
docker-compose -f docker-compose.dev.yml exec app npm install

echo "✅ تم! التطبيق يعمل على:"
echo "🌐 التطبيق: http://localhost:8000"
echo "📧 Mailpit: http://localhost:8025"
echo "🗄️ PostgreSQL: localhost:5432"
echo "🔴 Redis: localhost:6379"
