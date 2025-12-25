#!/bin/bash

# Daily Backup Script for Aaker Real Estate
set -e

echo "💾 Starting daily backup - $(date)"

# Configuration
BACKUP_DIR="/backups/daily"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=7
ENCRYPT_KEY=${BACKUP_ENCRYPTION_KEY:-"change-me-in-production"}

# Create backup directory
mkdir -p "$BACKUP_DIR"

# 1. Backup PostgreSQL
echo "🗄️  Backing up PostgreSQL..."
PG_BACKUP_FILE="$BACKUP_DIR/postgres_${TIMESTAMP}.sql.gz"
docker exec aaker-postgres-master pg_dumpall -U postgres | gzip > "$PG_BACKUP_FILE"

# Encrypt backup if key is set
if [ "$ENCRYPT_KEY" != "change-me-in-production" ]; then
    echo "🔐 Encrypting PostgreSQL backup..."
    openssl enc -aes-256-cbc -salt -in "$PG_BACKUP_FILE" -out "${PG_BACKUP_FILE}.enc" -pass pass:"$ENCRYPT_KEY"
    rm "$PG_BACKUP_FILE"
    echo "✅ PostgreSQL backup encrypted"
else
    echo "⚠️  PostgreSQL backup not encrypted (using default key)"
fi

# 2. Backup Redis
echo "🔴 Backing up Redis..."
REDIS_BACKUP_FILE="$BACKUP_DIR/redis_${TIMESTAMP}.rdb"
docker exec aaker-redis-1 redis-cli save
docker cp aaker-redis-1:/data/dump.rdb "$REDIS_BACKUP_FILE"

if [ "$ENCRYPT_KEY" != "change-me-in-production" ]; then
    echo "🔐 Encrypting Redis backup..."
    openssl enc -aes-256-cbc -salt -in "$REDIS_BACKUP_FILE" -out "${REDIS_BACKUP_FILE}.enc" -pass pass:"$ENCRYPT_KEY"
    rm "$REDIS_BACKUP_FILE"
fi

# 3. Backup uploaded files
echo "📁 Backing up uploaded files..."
FILES_BACKUP_FILE="$BACKUP_DIR/files_${TIMESTAMP}.tar.gz"
tar -czf "$FILES_BACKUP_FILE" \
    -C /source/storage/app1 . \
    -C /source/storage/app2 . \
    -C /source/storage/app3 .

if [ "$ENCRYPT_KEY" != "change-me-in-production" ]; then
    echo "🔐 Encrypting files backup..."
    openssl enc -aes-256-cbc -salt -in "$FILES_BACKUP_FILE" -out "${FILES_BACKUP_FILE}.enc" -pass pass:"$ENCRYPT_KEY"
    rm "$FILES_BACKUP_FILE"
fi

# 4. Create backup manifest
echo "📋 Creating backup manifest..."
cat << MANIFEST > "$BACKUP_DIR/manifest_${TIMESTAMP}.json"
{
    "timestamp": "$(date -Iseconds)",
    "backup_id": "${TIMESTAMP}",
    "components": {
        "postgres": "$(basename ${PG_BACKUP_FILE}*)",
        "redis": "$(basename ${REDIS_BACKUP_FILE}*)",
        "files": "$(basename ${FILES_BACKUP_FILE}*)"
    },
    "size": {
        "postgres": "$(du -h ${PG_BACKUP_FILE}* | cut -f1)",
        "redis": "$(du -h ${REDIS_BACKUP_FILE}* | cut -f1)",
        "files": "$(du -h ${FILES_BACKUP_FILE}* | cut -f1)"
    },
    "encrypted": $([ "$ENCRYPT_KEY" != "change-me-in-production" ] && echo "true" || echo "false")
}
MANIFEST

# 5. Clean up old backups
echo "🧹 Cleaning up backups older than ${RETENTION_DAYS} days..."
find "$BACKUP_DIR" -name "*.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.enc" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.rdb" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.json" -mtime +$RETENTION_DAYS -delete

# 6. Backup verification
echo "✅ Verifying backups..."
if [ -f "${PG_BACKUP_FILE}*" ] && [ -f "${REDIS_BACKUP_FILE}*" ] && [ -f "${FILES_BACKUP_FILE}*" ]; then
    echo "🎉 Backup completed successfully!"
    echo "📊 Backup Summary:"
    echo "   - PostgreSQL: $(du -h ${PG_BACKUP_FILE}* | cut -f1)"
    echo "   - Redis: $(du -h ${REDIS_BACKUP_FILE}* | cut -f1)"
    echo "   - Files: $(du -h ${FILES_BACKUP_FILE}* | cut -f1)"
    echo "   - Total: $(du -sh $BACKUP_DIR | cut -f1)"
else
    echo "❌ Backup verification failed!"
    exit 1
fi
