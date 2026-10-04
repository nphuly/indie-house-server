#!/bin/sh

# GitLab deploy job: send reviewed script over pinned SSH to the VM. The VM
# saves it to a 0600 temp file before execution so Docker cannot consume it
# from SSH stdin while the shell is still reading the script.
set -eu

: "${HOMELAB_VPS_HOST:?}"
: "${HOMELAB_VPS_USER:?}"
: "${HOMELAB_VPS_DEPLOY_PATH:?}"
: "${HOMELAB_VPS_SSH_KEY:?}"
: "${HOMELAB_VPS_KNOWN_HOSTS:?}"
: "${CI_COMMIT_SHA:?}"
: "${CI_COMMIT_BRANCH:?}"
: "${CI_REGISTRY_IMAGE:?}"
test -f "$HOMELAB_VPS_SSH_KEY"
test -f "$HOMELAB_VPS_KNOWN_HOSTS"
case "$HOMELAB_VPS_HOST" in '' | *[!A-Za-z0-9.:-]*) exit 2;; esac
case "$HOMELAB_VPS_USER" in '' | *[!A-Za-z0-9_-]*) exit 2;; esac
case "$HOMELAB_VPS_DEPLOY_PATH" in /*) ;; *) exit 2;; esac
case "$HOMELAB_VPS_DEPLOY_PATH" in *[!A-Za-z0-9_./-]* | *'..'*) exit 2;; esac
case "$CI_COMMIT_SHA" in '' | *[!0-9a-f]*) exit 2;; esac
test "${#CI_COMMIT_SHA}" -eq 40
printf '%s\n' "$CI_COMMIT_BRANCH" | grep -Eq '^release/[0-9]+\.[0-9]+(\.[0-9]+)?$'
case "$CI_REGISTRY_IMAGE" in '' | *[!A-Za-z0-9./:_-]*) exit 2;; esac

chmod 600 "$HOMELAB_VPS_SSH_KEY" "$HOMELAB_VPS_KNOWN_HOSTS"
if ! ssh-keygen -y -P '' -f "$HOMELAB_VPS_SSH_KEY" >/dev/null 2>&1; then
    printf '%s\n' 'Invalid HOMELAB_VPS_SSH_KEY: GitLab File variable must contain the raw, unencrypted private key with a final newline (not its .pub file or base64 text).' >&2
    exit 2
fi
remote_command="cd '$HOMELAB_VPS_DEPLOY_PATH' && umask 077 && deploy_tmp=\$(mktemp) && trap 'rm -f \"\$deploy_tmp\"' EXIT && cat > \"\$deploy_tmp\" && /bin/sh \"\$deploy_tmp\" '$CI_COMMIT_SHA' '$CI_REGISTRY_IMAGE' '$CI_COMMIT_BRANCH'"
ssh -i "$HOMELAB_VPS_SSH_KEY" -o IdentitiesOnly=yes -o BatchMode=yes \
    -o ConnectTimeout=15 -o StrictHostKeyChecking=yes \
    -o UserKnownHostsFile="$HOMELAB_VPS_KNOWN_HOSTS" \
    "$HOMELAB_VPS_USER@$HOMELAB_VPS_HOST" "$remote_command" \
    < infrastructure/scripts/deploy-release.sh
