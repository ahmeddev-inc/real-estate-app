# 🚀 دليل إعداد بيئة التطوير - عقار زين

## المتطلبات الأساسية
- Docker و Docker Compose
- Git

## الخطوات السريعة

### 1. استنساخ المشروع
```bash
git clone https://github.com/ahmeddev-inc/real-estate-app.git
cd real-estate-app
./start-dev.sh
# نسخ ملف البيئة
cp .env.example .env

# تشغيل Docker
docker-compose -f docker-compose.dev.yml up -d

# تثبيت الاعتماديات
docker-compose -f docker-compose.dev.yml exec app composer install
docker-compose -f docker-compose.dev.yml exec app php artisan key:generate
docker-compose -f docker-compose.dev.yml exec app php artisan migrate
docker-compose -f docker-compose.dev.yml exec app npm install
# إيقاف الخدمات
docker-compose -f docker-compose.dev.yml down

# إعادة التشغيل
docker-compose -f docker-compose.dev.yml restart

# مشاهدة اللوجات
docker-compose -f docker-compose.dev.yml logs -f app

# تشغ artisan commands
docker-compose -f docker-compose.dev.yml exec app php artisan [command]

# تشغيل الاختبارات
docker-compose -f docker-compose.dev.yml exec app php artisan test
# تحقق من حالة الخدمات
docker-compose -f docker-compose.dev.yml ps

# اختبار اتصال قاعدة البيانات
docker-compose -f docker-compose.dev.yml exec postgres pg_isready

# مسح الكاش
docker-compose -f docker-compose.dev.yml exec app php artisan cache:clear
