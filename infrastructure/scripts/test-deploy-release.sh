#!/bin/sh

# Exercise the release script without Docker, SSH, or a production database.
set -eu

repo_dir=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT

mkdir -p "$tmp_dir/bin"
cat > "$tmp_dir/bin/git" <<'EOF'
#!/bin/sh
case "$1 $2" in
    'rev-parse HEAD') printf '%s\n' "$MOCK_OLD_SHA";;
    'rev-parse FETCH_HEAD') printf '%s\n' "$MOCK_SHA";;
    *) exit 0;;
esac
EOF
cat > "$tmp_dir/bin/docker" <<'EOF'
#!/bin/sh
case "$1" in
    login) cat > /dev/null; exit 0;;
    image)
        case "$2" in
            inspect) printf '%s\n' "$MOCK_SHA";;
            ls)
                for sha in "$MOCK_SHA" "$MOCK_OLD_SHA" cccccccccccccccccccccccccccccccccccccccc; do
                    printf 'registry.example.test/indie-house/control-plane:sha-%s\n' "$sha"
                done
                ;;
            rm) printf 'rm %s\n' "$3" >> "$MOCK_LOG";;
            *) exit 1;;
        esac
        exit 0
        ;;
    tag) printf 'tag %s\n' "$3" >> "$MOCK_LOG"; exit 0;;
    inspect) printf 'true\n'; exit 0;;
    compose) shift;;
    *) exit 1;;
esac
while :; do
    case "$1" in
        --env-file | --profile | -f) shift 2;;
        *) break;;
    esac
done
command=$1
shift
case "$command" in
    config | pull) exit 0;;
    up) printf 'up %s\n' "$*" >> "$MOCK_LOG"; exit 0;;
    port) printf '127.0.0.1:8080\n'; exit 0;;
    ps) printf 'mock-container\n'; exit 0;;
    exec)
        case "$*" in
            *pg_dump*)
                printf 'dump\n' >> "$MOCK_LOG"
                if [ "$MOCK_SCENARIO" = backup_fail ]; then exit 1; fi
                printf 'PGDMPmock-data\n'
                ;;
            *pg_restore*)
                if [ "$MOCK_SCENARIO" = verify_fail ]; then exit 1; fi
                read -r header
                [ "$header" = PGDMPmock-data ]
                ;;
            *psql*)
                case "$MOCK_SCENARIO" in empty | empty_with_state) printf '0\n';; *) printf '2\n';; esac
                ;;
            *'CONFIG GET appendonly'*)
                case "$MOCK_SCENARIO" in
                    aof_switch | aof_stuck) printf 'appendonly\r\nno\r\n';;
                    *) printf 'appendonly\r\nyes\r\n';;
                esac
                ;;
            *'CONFIG SET appendonly yes'*) printf 'aof\n' >> "$MOCK_LOG"; printf 'OK\n';;
            *'caddy reload'*) printf 'reload\n' >> "$MOCK_LOG";;
            *'INFO persistence'*)
                if [ "$MOCK_SCENARIO" = aof_stuck ]; then in_progress=1; else in_progress=0; fi
                printf '# Persistence\r\naof_rewrite_in_progress:%s\r\naof_rewrite_scheduled:0\r\naof_last_bgrewrite_status:ok\r\n' "$in_progress"
                ;;
            *) exit 1;;
        esac
        ;;
    run)
        case "$*" in
            *migrate:status*)
                if [ "$MOCK_SCENARIO" = status_fail ]; then exit 1; fi
                if [ "$MOCK_SCENARIO" = pending ] || [ "$MOCK_SCENARIO" = backup_fail ] \
                    || [ "$MOCK_SCENARIO" = verify_fail ] \
                    || [ "$MOCK_SCENARIO" = migration_fail ]; then
                    if [ ! -e "$MOCK_MIGRATED" ]; then printf 'migration Pending\n'; exit 0; fi
                fi
                printf 'migration Ran\n'
                ;;
            *'artisan migrate '*)
                printf 'migrate\n' >> "$MOCK_LOG"
                if [ "$MOCK_SCENARIO" = migration_fail ]; then exit 1; fi
                touch "$MOCK_MIGRATED"
                ;;
            *) exit 1;;
        esac
        ;;
    *) exit 1;;
esac
EOF
cat > "$tmp_dir/bin/curl" <<'EOF'
#!/bin/sh
printf '200'
EOF
cat > "$tmp_dir/bin/sleep" <<'EOF'
#!/bin/sh
exit 0
EOF
chmod +x "$tmp_dir/bin/git" "$tmp_dir/bin/docker" "$tmp_dir/bin/curl" "$tmp_dir/bin/sleep"

mock_sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
mock_old_sha=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb

run_case() {
    scenario=$1
    case_dir="$tmp_dir/$scenario"
    mkdir -p "$case_dir/.git" "$case_dir/infrastructure/stream-keys" "$case_dir/infrastructure/deploy"
    touch "$case_dir/.env" "$case_dir/infrastructure/stream-keys/private.key" "$case_dir/infrastructure/stream-keys/public.key"
    printf 'key\n' > "$case_dir/infrastructure/stream-keys/private.key"
    printf 'key\n' > "$case_dir/infrastructure/stream-keys/public.key"
    cat > "$case_dir/.env" <<'EOF'
APP_ENV=production
APP_DEBUG=false
PUBLIC_SCHEME=https
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
EDGE_BIND_ADDRESS=127.0.0.1
APP_URL=https://example.test
INSTANCE_BASE_URL=https://example.test
STREAM_JWT_ISSUER=https://example.test
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
REDIS_PASSWORD=0123456789abcdef0123456789abcdef
BACKUP_ENCRYPTION_KEY=MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=
EOF
    if [ "$scenario" = no_redis_password ]; then sed -i '/^REDIS_PASSWORD=/d' "$case_dir/.env"; fi
    if [ "$scenario" = no_backup_key ]; then sed -i '/^BACKUP_ENCRYPTION_KEY=/d' "$case_dir/.env"; fi
    cat > "$case_dir/registry.env" <<'EOF'
REGISTRY_HOST=registry.example.test
REGISTRY_IMAGE=registry.example.test/indie-house
REGISTRY_USERNAME=deploy
REGISTRY_PASSWORD=test
EOF
    if [ "$scenario" != empty ]; then
        printf 'DEPLOYED_SHA=%s\nDEPLOYED_REGISTRY_IMAGE=registry.example.test/indie-house\n' "$mock_old_sha" \
            > "$case_dir/infrastructure/deploy/current-release.env"
    fi
    if (cd "$case_dir" && PATH="$tmp_dir/bin:$PATH" \
        MOCK_SHA="$mock_sha" MOCK_OLD_SHA="$mock_old_sha" MOCK_SCENARIO="$scenario" \
        MOCK_LOG="$case_dir/mock.log" MOCK_MIGRATED="$case_dir/migrated" \
        HOMELAB_REGISTRY_CREDENTIALS_FILE="$case_dir/registry.env" \
        sh "$repo_dir/infrastructure/scripts/deploy-release.sh" "$mock_sha" \
        registry.example.test/indie-house release/2.0 > "$case_dir/output.log" 2>&1); then
        result=0
    else
        result=1
    fi
    if [ "$result" -ne 0 ] && [ "$scenario" != backup_fail ] \
        && [ "$scenario" != verify_fail ] && [ "$scenario" != status_fail ] \
        && [ "$scenario" != empty_with_state ] \
        && [ "$scenario" != migration_fail ] && [ "$scenario" != aof_stuck ] \
        && [ "$scenario" != no_redis_password ] && [ "$scenario" != no_backup_key ]; then
        cat "$case_dir/output.log" >&2
    fi
    case "$scenario" in
        current)
            [ "$result" -eq 0 ]
            ! grep -q '^migrate$\|^dump$\|^aof$' "$case_dir/mock.log"
            grep -q '^up -d --no-build$' "$case_dir/mock.log"
            awk '/^up -d --no-build$/{up=NR} /^reload$/{reload=NR} END{exit !(up > 0 && reload > up)}' "$case_dir/mock.log"
            test "$(grep -c '^tag indie-house/.*:current$' "$case_dir/mock.log")" -eq 3
            grep -q '^rm .*:sha-cccccccccccccccccccccccccccccccccccccccc$' "$case_dir/mock.log"
            ! grep -q "^rm .*:sha-$mock_sha$" "$case_dir/mock.log"
            ! grep -q "^rm .*:sha-$mock_old_sha$" "$case_dir/mock.log"
            ;;
        aof_switch)
            [ "$result" -eq 0 ]
            awk '/^aof$/{aof=NR} /^up .*postgres redis$/{up=NR} END{exit !(aof > 0 && up > aof)}' "$case_dir/mock.log"
            ;;
        aof_stuck)
            [ "$result" -eq 1 ]
            grep -q '^aof$' "$case_dir/mock.log"
            ! grep -q '^up ' "$case_dir/mock.log"
            ;;
        pending)
            [ "$result" -eq 0 ]
            grep -q '^dump$' "$case_dir/mock.log"
            grep -q '^migrate$' "$case_dir/mock.log"
            awk '/^dump$/{dump=NR} /^migrate$/{migration=NR} END{exit !(dump > 0 && migration > dump)}' "$case_dir/mock.log"
            test "$(find "$case_dir/infrastructure/deploy/backups" -name '*.dump' | wc -l)" -eq 1
            test "$(stat -c %a "$case_dir/infrastructure/deploy/backups")" = 700
            find "$case_dir/infrastructure/deploy/backups" -name '*.dump' -exec stat -c %a '{}' \; | grep -qx 600
            grep -q '^up -d --no-build$' "$case_dir/mock.log"
            ;;
        empty)
            [ "$result" -eq 0 ]
            grep -q '^migrate$' "$case_dir/mock.log"
            ! grep -q '^dump$' "$case_dir/mock.log"
            ;;
        backup_fail | verify_fail | status_fail | empty_with_state)
            [ "$result" -eq 1 ]
            ! grep -q '^migrate$' "$case_dir/mock.log"
            ! grep -q '^up -d --no-build$' "$case_dir/mock.log"
            ! grep -q '^reload$' "$case_dir/mock.log"
            ;;
        no_redis_password)
            [ "$result" -eq 1 ]
            grep -q 'must set REDIS_PASSWORD' "$case_dir/output.log"
            ! grep -qs '^up ' "$case_dir/mock.log"
            ;;
        no_backup_key)
            [ "$result" -eq 1 ]
            grep -q 'must set BACKUP_ENCRYPTION_KEY' "$case_dir/output.log"
            ! grep -qs '^up ' "$case_dir/mock.log"
            ;;
        migration_fail)
            [ "$result" -eq 1 ]
            grep -q '^dump$' "$case_dir/mock.log"
            grep -q '^migrate$' "$case_dir/mock.log"
            ! grep -q '^up -d --no-build$' "$case_dir/mock.log"
            grep -q 'Application rollback does not reverse them' "$case_dir/output.log"
            ;;
    esac
    printf 'PASS %s\n' "$scenario"
}

run_case current
run_case pending
run_case empty
run_case backup_fail
run_case verify_fail
run_case status_fail
run_case empty_with_state
run_case migration_fail
run_case aof_switch
run_case aof_stuck
run_case no_redis_password
run_case no_backup_key
