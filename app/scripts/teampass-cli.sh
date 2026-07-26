#!/usr/bin/env bash
#
# teampass-cli.sh — Command line client for the Teampass REST API.
#
# Wraps the JWT authentication and the item/folder endpoints behind a few
# readable commands, so the API can be used from a terminal or a cron job
# without writing curl calls by hand.
#
# Based on the CLI client contributed by @danfossi (GitHub PR #5298).
#
# Requirements: curl, jq
#
# Configuration — environment variables, or ~/.config/teampass/config:
#
#   TEAMPASS_URL       https://teampass.example.com        (required)
#   TEAMPASS_LOGIN     your login                          (required)
#   Then ONE of the two authentication modes:
#     - password mode: TEAMPASS_PASSWORD + TEAMPASS_APIKEY
#     - token mode   : TEAMPASS_TOKEN    (Personal Access Token, /authorizeToken)
#
# Teampass - a collaborative passwords manager.
# ---
# This file is part of the TeamPass project.
#
# TeamPass is free software: you can redistribute it and/or modify it
# under the terms of the GNU General Public License as published by
# the Free Software Foundation, version 3 of the License.
#
# @copyright 2009-2026 Teampass.net
# @license   GPL-3.0
# @see       https://www.teampass.net

set -euo pipefail

CONFIG_FILE="${HOME}/.config/teampass/config"
API_BASE=''
JWT_TOKEN=''
HEADER_FILE=''

# ------------------------------------------------------------------------------
# Setup
# ------------------------------------------------------------------------------

cleanup() {
    [[ -n "$HEADER_FILE" && -f "$HEADER_FILE" ]] && rm -f "$HEADER_FILE"
    return 0
}
trap cleanup EXIT

die() {
    echo "$*" >&2
    exit 1
}

for tool in curl jq; do
    command -v "$tool" >/dev/null 2>&1 || die "Error: '$tool' is not installed."
done

# shellcheck source=/dev/null
[[ -f "$CONFIG_FILE" ]] && source "$CONFIG_FILE"

TEAMPASS_URL="${TEAMPASS_URL:-}"
TEAMPASS_LOGIN="${TEAMPASS_LOGIN:-}"
TEAMPASS_PASSWORD="${TEAMPASS_PASSWORD:-}"
TEAMPASS_APIKEY="${TEAMPASS_APIKEY:-}"
TEAMPASS_TOKEN="${TEAMPASS_TOKEN:-}"

check_configuration() {
    local missing=()
    [[ -z "$TEAMPASS_URL" ]] && missing+=("TEAMPASS_URL")
    [[ -z "$TEAMPASS_LOGIN" ]] && missing+=("TEAMPASS_LOGIN")

    if [[ -z "$TEAMPASS_TOKEN" ]]; then
        [[ -z "$TEAMPASS_PASSWORD" ]] && missing+=("TEAMPASS_PASSWORD (or TEAMPASS_TOKEN)")
        [[ -z "$TEAMPASS_APIKEY" ]] && missing+=("TEAMPASS_APIKEY (or TEAMPASS_TOKEN)")
    fi

    if [[ ${#missing[@]} -gt 0 ]]; then
        {
            echo "Error: missing configuration: ${missing[*]}"
            echo
            echo "Export them as environment variables, or create $CONFIG_FILE:"
            echo "    export TEAMPASS_URL=\"https://teampass.example.com\""
            echo "    export TEAMPASS_LOGIN=\"jdoe\""
            echo "    export TEAMPASS_PASSWORD=\"...\"   # password mode"
            echo "    export TEAMPASS_APIKEY=\"...\"     # password mode"
            echo "    export TEAMPASS_TOKEN=\"...\"      # or token mode (Personal Access Token)"
        } >&2
        exit 1
    fi

    API_BASE="${TEAMPASS_URL%/}/api/index.php"
}

usage() {
    cat << EOF
Usage: ${0##*/} <command> [options...]

Available commands:
  folders [--tree]
      List every accessible folder with its access rights, in tree order.
      --tree renders an indented view instead of the raw JSON.

  read <item_id>
      Read an item by its ID.

  create <folder_id> <label> <login> <password> [description]
      Create a new item in a folder.

  update <item_id> <field> <value>
      Update one field of an item (label, login, password, description, url...).

  search <term> [--by-desc | --by-url]
      Search items by label (default), by description or by URL.

  help
      Show this message.

Examples:
  ${0##*/} folders --tree
  ${0##*/} read 25
  ${0##*/} create 5 "My Server" "admin" "SuperSecret!" "Root credentials"
  ${0##*/} update 25 label "Updated Server"
  ${0##*/} search "server"
EOF
}

# ------------------------------------------------------------------------------
# API plumbing
# ------------------------------------------------------------------------------

# Authenticate once per invocation and cache the JWT in memory.
# Each call to /authorize opens a new API session server-side, so it must not be
# repeated for every request.
authenticate() {
    [[ -n "$JWT_TOKEN" ]] && return 0

    local endpoint payload response http_code body token
    if [[ -n "$TEAMPASS_TOKEN" ]]; then
        endpoint="${API_BASE}/authorizeToken"
        payload=$(jq -n --arg login "$TEAMPASS_LOGIN" --arg token "$TEAMPASS_TOKEN" \
            '{login: $login, token: $token}')
    else
        endpoint="${API_BASE}/authorize"
        payload=$(jq -n --arg login "$TEAMPASS_LOGIN" --arg password "$TEAMPASS_PASSWORD" \
            --arg apikey "$TEAMPASS_APIKEY" \
            '{login: $login, password: $password, apikey: $apikey}')
    fi

    response=$(curl -s -w "\n%{http_code}" -X POST "$endpoint" \
        -H "Content-Type: application/json" \
        -d "$payload")

    http_code=$(printf '%s' "$response" | tail -n1)
    body=$(printf '%s' "$response" | sed '$ d')

    if [[ "$http_code" -ne 200 ]]; then
        echo "Authentication failed (HTTP $http_code)" >&2
        printf '%s\n' "$body" | jq . >&2 2>/dev/null || printf '%s\n' "$body" >&2
        exit 1
    fi

    token=$(printf '%s' "$body" | jq -r '.token // empty')
    [[ -z "$token" ]] && die "Error: no JWT token in the authentication response."

    JWT_TOKEN="$token"
}

# api_call <method> <endpoint> [json payload]
# Retries once when the server answers 429, honouring the Retry-After header.
api_call() {
    local method="$1" endpoint="$2" payload="${3:-}"
    local attempt=0 response http_code body retry_after

    authenticate
    cleanup
    HEADER_FILE=$(mktemp)

    while : ; do
        local curl_opts=(
            -s -w "\n%{http_code}"
            -D "$HEADER_FILE"
            -X "$method"
            "${API_BASE}/${endpoint}"
            -H "Authorization: Bearer ${JWT_TOKEN}"
        )
        [[ -n "$payload" ]] && curl_opts+=(-H "Content-Type: application/json" -d "$payload")

        response=$(curl "${curl_opts[@]}")
        http_code=$(printf '%s' "$response" | tail -n1)
        body=$(printf '%s' "$response" | sed '$ d')

        if [[ "$http_code" -eq 429 && $attempt -eq 0 ]]; then
            retry_after=$(grep -i '^retry-after:' "$HEADER_FILE" | tr -d '\r' | awk '{print $2}')
            [[ -z "$retry_after" ]] && retry_after=5
            echo "Rate limit reached, retrying in ${retry_after}s..." >&2
            sleep "$retry_after"
            attempt=1
            continue
        fi
        break
    done

    if [[ "$http_code" -ge 400 ]]; then
        echo "API error (HTTP $http_code):" >&2
        printf '%s\n' "$body" | jq -r '.detail // .error // .' >&2 2>/dev/null || printf '%s\n' "$body" >&2
        exit 1
    fi

    if printf '%s' "$body" | jq . >/dev/null 2>&1; then
        printf '%s' "$body" | jq .
    else
        printf '%s\n' "$body"
    fi
}

# ------------------------------------------------------------------------------
# Commands
# ------------------------------------------------------------------------------

cmd_folders() {
    local folders
    folders=$(api_call "GET" "folder/writableFolders")

    if [[ "${1:-}" != "--tree" ]]; then
        printf '%s\n' "$folders"
        return 0
    fi

    # The endpoint already returns the folders in tree order (position = nleft),
    # so a single pass with the depth level is enough to render the hierarchy.
    printf '%s' "$folders" \
        | jq -r '.[] | [.level, .label, .id, .is_readonly] | @tsv' \
        | while IFS=$'\t' read -r level label id readonly; do
            local flag=''
            [[ "$readonly" == "1" ]] && flag=' (read-only)'
            printf '%*s%s [%s]%s\n' "$(( (level - 1) * 2 ))" '' "$label" "$id" "$flag"
        done
}

cmd_read() {
    local item_id="${1:-}"
    [[ -z "$item_id" ]] && { echo "Error: item_id is required." >&2; usage; exit 1; }
    api_call "GET" "item/get?id=$(urlencode "$item_id")"
}

cmd_create() {
    local folder_id="${1:-}" label="${2:-}" login="${3:-}" password="${4:-}" description="${5:-}"
    if [[ -z "$password" ]]; then
        echo "Error: folder_id, label, login and password are required." >&2
        usage
        exit 1
    fi

    local payload
    payload=$(jq -n \
        --arg folder_id "$folder_id" \
        --arg label "$label" \
        --arg login "$login" \
        --arg password "$password" \
        --arg description "$description" \
        '{folder_id: ($folder_id|tonumber), label: $label, login: $login,
          password: $password, description: $description}')

    api_call "POST" "item/create" "$payload"
}

cmd_update() {
    local item_id="${1:-}" field="${2:-}" value="${3:-}"
    if [[ -z "$item_id" || -z "$field" || -z "$value" ]]; then
        echo "Error: item_id, field and value are required." >&2
        usage
        exit 1
    fi

    local payload
    payload=$(jq -n --arg id "$item_id" --arg field "$field" --arg value "$value" \
        '{id: ($id|tonumber)} + {($field): $value}')

    api_call "PUT" "item/update" "$payload"
}

cmd_search() {
    local term="${1:-}" mode="${2:-}"
    [[ -z "$term" ]] && { echo "Error: search term is required." >&2; usage; exit 1; }

    local encoded
    encoded=$(urlencode "$term")

    case "$mode" in
        --by-url)  api_call "GET" "item/findByUrl?url=${encoded}" ;;
        --by-desc) api_call "GET" "item/get?description=${encoded}&like=1" ;;
        *)         api_call "GET" "item/get?label=${encoded}&like=1" ;;
    esac
}

urlencode() {
    jq -rn --arg value "$1" '$value|@uri'
}

# ------------------------------------------------------------------------------
# Entry point
# ------------------------------------------------------------------------------

COMMAND="${1:-help}"
shift || true

case "$COMMAND" in
    help|--help|-h)
        usage
        ;;
    folders|read|create|update|search)
        check_configuration
        "cmd_${COMMAND}" "$@"
        ;;
    *)
        echo "Unknown command: $COMMAND" >&2
        usage
        exit 1
        ;;
esac
