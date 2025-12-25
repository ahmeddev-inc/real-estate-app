#!/bin/bash

# ============================================
# عقار زين - سكريبت النسخ الاحتياطي لقاعدة البيانات
# ============================================

# الألوان للرسائل
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# إعدادات النسخ الاحتياطي
BACKUP_ROOT="/data/data/com.termux/files/home/real-estate-app/storage/backups"
BACKUP_DIR="$BACKUP_ROOT/database"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="estate_db_backup_$TIMESTAMP.sql.gz"
FULL_PATH="$BACKUP_DIR/$BACKUP_FILE"
RETENTION_DAYS=7

# معلومات قاعدة البيانات (من .env)
ENV_FILE="/data/data/com.termux/files/home/real-estate-app/.env"
if [ -f "$ENV_FILE" ]; then
    DB_NAME=$(grep DB_DATABASE "$ENV_FILE" | cut -d '=' -f2 | tr -d '[:space:]')
    DB_USER=$(grep DB_USERNAME "$ENV_FILE" | cut -d '=' -f2 | tr -d '[:space:]')
    DB_PASS=$(grep DB_PASSWORD "$ENV_FILE" | cut -d '=' -f2 | tr -d '[:space:]')
    DB_HOST=$(grep DB_HOST "$ENV_FILE" | cut -d '=' -f2 | tr -d '[:space:]')
else
    DB_NAME="estate_db"
    DB_USER="postgres"
    DB_PASS="postgres"
    DB_HOST="postgres"
fi

# دالة للطباعة الملونة
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# التحقق من وجود مجلد النسخ الاحتياطي
create_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        log_info "إنشاء مجلد النسخ الاحتياطي: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR"
        chmod 755 "$BACKUP_DIR"
    fi
}

# النسخ الاحتياطي لقاعدة البيانات
backup_database() {
    log_info "بدء النسخ الاحتياطي لقاعدة البيانات..."
    log_info "قاعدة البيانات: $DB_NAME"
    log_info "المضيف: $DB_HOST"
    log_info "المستخدم: $DB_USER"
    
    # التحقق من اتصال قاعدة البيانات
    if ! PGPASSWORD="$DB_PASS" pg_isready -h "$DB_HOST" -U "$DB_USER" >/dev/null 2>&1; then
        log_error "لا يمكن الاتصال بقاعدة البيانات!"
        return 1
    fi
    
    # تنفيذ النسخ الاحتياطي
    log_info "جاري إنشاء النسخ الاحتياطي: $BACKUP_FILE"
    
    if PGPASSWORD="$DB_PASS" pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
        --clean --if-exists | gzip > "$FULL_PATH"; then
        
        # التحقق من حجم النسخة
        FILESIZE=$(stat -c%s "$FULL_PATH" 2>/dev/null || stat -f%z "$FULL_PATH")
        FILESIZE_MB=$((FILESIZE / 1024 / 1024))
        
        log_info "✅ النسخ الاحتياطي اكتمل بنجاح!"
        log_info "📦 الملف: $BACKUP_FILE"
        log_info "📊 الحجم: ${FILESIZE_MB}MB"
        log_info "📍 المسار: $FULL_PATH"
        
        # إنشاء رابط للنسخة الأخيرة
        LATEST_LINK="$BACKUP_DIR/latest_backup.sql.gz"
        ln -sf "$FULL_PATH" "$LATEST_LINK"
        
        return 0
    else
        log_error "❌ فشل النسخ الاحتياطي!"
        return 1
    fi
}

# تنظيف النسخ القديمة
clean_old_backups() {
    log_info "تنظيف النسخ القديمة (أكثر من $RETENTION_DAYS يوم)..."
    
    COUNT_BEFORE=$(find "$BACKUP_DIR" -name "estate_db_backup_*.sql.gz" | wc -l)
    
    find "$BACKUP_DIR" -name "estate_db_backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    
    COUNT_AFTER=$(find "$BACKUP_DIR" -name "estate_db_backup_*.sql.gz" | wc -l)
    REMOVED=$((COUNT_BEFORE - COUNT_AFTER))
    
    if [ $REMOVED -gt 0 ]; then
        log_info "تم حذف $REMOVED نسخة قديمة"
    fi
    
    log_info "عدد النسخ الحالية: $COUNT_AFTER"
}

# إنشاء تقرير النسخ الاحتياطي
create_backup_report() {
    REPORT_FILE="$BACKUP_DIR/backup_report_$(date +"%Y%m%d").txt"
    
    cat > "$REPORT_FILE" << EOF
📋 تقرير النسخ الاحتياطي - عقار زين
⏰ التاريخ: $(date)
📊 حالة النسخ: $(if [ $? -eq 0 ]; then echo "ناجح ✅"; else echo "فاشل ❌"; fi)

🔧 معلومات النسخ:
- قاعدة البيانات: $DB_NAME
- المضيف: $DB_HOST
- الملف الناتج: $BACKUP_FILE
- المسار: $FULL_PATH

📁 حالة المجلدات:
$(du -sh "$BACKUP_ROOT"/* 2>/dev/null || echo "غير متوفر")

🗄️ قائمة النسخ الحالية:
$(ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -10 || echo "لا توجد نسخ")

⚙️ إحصاءات:
- إجمالي النسخ: $(find "$BACKUP_DIR" -name "*.sql.gz" | wc -l)
- المساحة المستخدمة: $(du -sh "$BACKUP_DIR" | cut -f1)
- النسخ المحذوفة اليوم: $REMOVED

📅 السياسات:
- الاحتفاظ: $RETENTION_DAYS يوم
- التالي: $(date -d "+1 day" "+%Y-%m-%d %H:%M")

🔔 ملاحظات: يتم التشغيل تلقائياً عبر GitHub Actions
