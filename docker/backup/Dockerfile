FROM minio/mc:RELEASE.2025-04-16T18-13-26Z AS mc
FROM postgres:16.8-alpine
COPY --from=mc /usr/bin/mc /usr/local/bin/mc
COPY docker/backup/backup.sh /usr/local/bin/atlas-backup
COPY docker/backup/restore.sh /usr/local/bin/atlas-restore
RUN chmod 0555 /usr/local/bin/atlas-backup /usr/local/bin/atlas-restore
USER 999:999
ENTRYPOINT ["/usr/local/bin/atlas-backup"]
