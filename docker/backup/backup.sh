#!/bin/sh
# Daily MySQL backup: gzipped dump into /backups, keeping BACKUP_KEEP_DAYS days (default 14).
# Runs in the "backup" service (compose.yaml). Restore with:
#   gunzip -c backups/crm-YYYY-MM-DD_HHMM.sql.gz | docker compose exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" crm

set -eu

KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
INTERVAL="${BACKUP_INTERVAL_SECONDS:-86400}"

while true; do
    stamp="$(date +%Y-%m-%d_%H%M)"
    target="/backups/crm-${stamp}.sql.gz"

    if mysqldump -h "${DB_HOST:-mysql}" -u root -p"${MYSQL_ROOT_PASSWORD}" \
        --single-transaction --quick --routines --triggers --no-tablespaces "${DB_DATABASE:-crm}" | gzip > "${target}.tmp"; then
        mv "${target}.tmp" "${target}"
        echo "$(date -Iseconds) backup written: ${target}"
    else
        rm -f "${target}.tmp"
        echo "$(date -Iseconds) BACKUP FAILED" >&2
    fi

    find /backups -name 'crm-*.sql.gz' -mtime "+${KEEP_DAYS}" -delete

    sleep "${INTERVAL}"
done
