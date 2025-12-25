# 🚨 خطة التعافي من الكوارث - عقار زين

## 📋 نظرة عامة
هذه الخطة توضح خطوات التعافي في حالة حدوث كارثة تؤثر على توفر النظام.

## 🎯 أهداف التعافي
- **RTO (Recovery Time Objective):** 4 ساعات
- **RPO (Recovery Point Objective):** 24 ساعة
- **SLA المستهدف بعد التعافي:** 99.9%

## 📞 جهات الاتصال في حالات الطوارئ
| الدور | الاسم | الهاتف | البريد الإلكتروني |
|-------|-------|--------|-------------------|
| Technical Director | أحمد | +2010XXXXXXX | ahmed@aakerz.com |
| DevOps Engineer | مروان | +2010XXXXXXX | marwan@aakerz.com |
| Backup Operator | النظام | - | alerts@aakerz.com |

## 🚨 سيناريوهات الكوارث

### 1. فشل قاعدة البيانات الرئيسية
**الأعراض:**
- تطبيق يعرض أخطاء اتصال بقاعدة البيانات
- ارتفاع معدل الأخطاء في لوحة المراقبة

**خطوات التعافي:**
```bash
# الخطوة 1: تفعيل وضع الصيانة
docker exec aaker-app-1 php artisan down --message="صيانة طارئة"

# الخطوة 2: تشغيل نسخة Replica
docker-compose -f docker-compose.prod.yml up -d postgres-replica1 --scale postgres-replica1=1

# الخطوة 3: تعديل إعدادات التطبيق
sed -i 's/DB_HOST=postgres-master/DB_HOST=postgres-replica1/' .env.production

# الخطوة 4: إعادة تشغيل التطبيق
docker-compose -f docker-compose.prod.yml restart app1 app2 app3

# الخطوة 5: تعطيل وضع الصيانة
docker exec aaker-app-1 php artisan up
# الخطوة 1: تحديد آخر نسخة احتياطية صالحة
BACKUP_FILE=$(ls -t /backups/daily/postgres_*.sql.gz.enc | head -1)

# الخطوة 2: فك تشفير النسخة الاحتياطية
openssl enc -d -aes-256-cbc -in "$BACKUP_FILE" -out /tmp/backup.sql.gz -pass pass:"$BACKUP_ENCRYPTION_KEY"

# الخطوة 3: استعادة قاعدة البيانات
gunzip -c /tmp/backup.sql.gz | docker exec -i aaker-postgres-master psql -U postgres

# الخطوة 4: استعادة ملفات التطبيق
BACKUP_FILE=$(ls -t /backups/daily/files_*.tar.gz.enc | head -1)
openssl enc -d -aes-256-cbc -in "$BACKUP_FILE" -out /tmp/files.tar.gz -pass pass:"$BACKUP_ENCRYPTION_KEY"
tar -xzf /tmp/files.tar.gz -C /source/storage/
# الخطوة 1: عزل النظام
docker-compose -f docker-compose.prod.yml stop

# الخطوة 2: تحليل السجلات
docker logs --tail 1000 aaker-app-1 > /tmp/attack_analysis.log
docker logs --tail 1000 aaker-nginx > /tmp/nginx_analysis.log

# الخطوة 3: استعادة من نسخة نظيفة
# استخدام نسخة احتياطية قبل تاريخ الهجوم
find /backups -name "*.enc" -mtime -1 | xargs -I {} sh -c '...'

# الخطوة 4: تحديث جميع كلمات المرور
# تحديث: DB_PASSWORD, REDIS_PASSWORD, APP_KEY, etc.

# الخطوة 5: تسجيل الحادث
echo "$(date): Security incident handled" >> /var/log/security_incidents.log
# الخطوة 1: إعداد الخادم الجديد
git clone https://github.com/ahmeddev-inc/real-estate-app.git
cd real-estate-app

# الخطوة 2: نسخ ملفات الإعداد
scp user@old-server:/path/to/.env.production .env.production
scp user@old-server:/path/to/backup-encryption-key.txt .

# الخطوة 3: سحب أحدث النسخ الاحتياطية من التخزين السحابي
aws s3 sync s3://aakerz-backups/daily/ ./backups/daily/

# الخطوة 4: استعادة النظام
./scripts/backup/restore.sh --full

# الخطوة 5: تحديث DNS
# تحديث سجلات DNS للإشارة إلى الخادم الجديد
