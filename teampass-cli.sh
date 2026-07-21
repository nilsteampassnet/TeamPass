#!/bin/bash

# ==============================================================================
# TeamPass CLI Script
# Command line interface for TeamPass REST API
# ==============================================================================

set -e

# 1. Configuration Management
# Load configuration file if it exists
CONFIG_FILE="${HOME}/.config/teampass/config"
if [[ -f "$CONFIG_FILE" ]]; then
    source "$CONFIG_FILE"
fi

# Check required tools
if ! command -v curl &> /dev/null; then
    echo "Error: 'curl' is not installed." >&2
    exit 1
fi
if ! command -v jq &> /dev/null; then
    echo "Error: 'jq' is not installed." >&2
    exit 1
fi

# Configuration Variables Check
# Note: The TeamPass API requires the apikey, login, and password to generate the initial JWT token.
if [[ -z "$TEAMPASS_URL" || -z "$TEAMPASS_APIKEY" || -z "$TEAMPASS_LOGIN" || -z "$TEAMPASS_PASSWORD" ]]; then
    echo "============================================================" >&2
    echo " ERROR: Missing Configuration Variables" >&2
    echo "============================================================" >&2
    echo "To use this script, you must provide your TeamPass credentials." >&2
    echo "The TeamPass API strictly requires the following 4 variables to authenticate:" >&2
    echo "" >&2
    echo "  1. TEAMPASS_URL      (e.g., https://your-teampass.com)" >&2
    echo "  2. TEAMPASS_APIKEY   (Your generated API Key)" >&2
    echo "  3. TEAMPASS_LOGIN    (Your username)" >&2
    echo "  4. TEAMPASS_PASSWORD (Your password)" >&2
    echo "" >&2
    echo "You can set them in two ways:" >&2
    echo "  A) Export them as environment variables." >&2
    echo "  B) Create the file $CONFIG_FILE and add them there, for example:" >&2
    echo "       export TEAMPASS_URL=\"https://your-teampass.com\"" >&2
    echo "       export TEAMPASS_APIKEY=\"your-api-key\"" >&2
    echo "       export TEAMPASS_LOGIN=\"your-login\"" >&2
    echo "       export TEAMPASS_PASSWORD=\"your-password\"" >&2
    echo "============================================================" >&2
    exit 1
fi

# 4. Usability: Help Function (Usage)
usage() {
    cat << EOF
Usage: $0 <command> [options...]

Available commands:
  folders
      List all accessible folders retrieving the tree structure.

  read <item_id>
      Read details of a specific entry given its ID.

  create <folder_id> <title> <login> <password> [description]
      Create a new entry inside a specific folder.

  update <item_id> <field> <value>
      Modify a field of an existing entry (e.g., label, login, password).

  search <term> [--by-desc | --by-url]
      Search for entries. Defaults to searching by name (label) via partial match.
      Use --by-desc to search in the description, or --by-url for an exact URL match.

  help
      Show this help message.

Examples:
  $0 folders
  $0 read 25
  $0 create 5 "My Server" "admin" "SuperSecret!" "Root credentials"
  $0 update 25 label "Updated Server"
  $0 search "server"
  $0 search "192.168." --by-desc
EOF
}

# ==============================================================================
# Core Functions
# ==============================================================================

# Generate the JWT token required for all subsequent API calls
authenticate() {
    local auth_response
    auth_response=$(curl -s -w "\n%{http_code}" -X POST "${TEAMPASS_URL}/api/index.php/authorize" \
        -H "Content-Type: application/json" \
        -d "{
          \"apikey\": \"${TEAMPASS_APIKEY}\",
          \"login\": \"${TEAMPASS_LOGIN}\",
          \"password\": \"${TEAMPASS_PASSWORD}\"
        }")

    local http_code=$(echo "$auth_response" | tail -n1)
    local body=$(echo "$auth_response" | sed '$ d')

    if [[ "$http_code" -ne 200 ]]; then
        echo "Authentication failed! HTTP Code: $http_code" >&2
        echo "$body" | jq . >&2
        exit 1
    fi

    # Extract the JWT token using jq
    local token
    token=$(echo "$body" | jq -r '.token')
    
    if [[ "$token" == "null" || -z "$token" ]]; then
        echo "Error: Unable to retrieve the JWT token from the response." >&2
        exit 1
    fi

    echo "$token"
}

# Wrapper for API calls
api_call() {
    local method="$1"
    local endpoint="$2"
    local payload="$3"
    
    # 3. Error Handling and Output
    # Authenticate before each call to get a fresh token
    local token
    token=$(authenticate)
    
    local curl_opts=(
        -s -w "\n%{http_code}"
        -X "$method"
        "${TEAMPASS_URL}/api/index.php/${endpoint}"
        -H "Authorization: Bearer ${token}"
    )

    if [[ -n "$payload" ]]; then
        curl_opts+=(-H "Content-Type: application/json" -d "$payload")
    fi

    local response
    response=$(curl "${curl_opts[@]}")
    
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$ d')

    # Format the resulting JSON (if valid)
    if echo "$body" | jq . >/dev/null 2>&1; then
        body=$(echo "$body" | jq .)
    fi

    # Handle HTTP error codes
    if [[ "$http_code" -ge 400 ]]; then
        echo "API Error ($http_code):" >&2
        echo "$body" >&2
        exit 1
    fi

    # Return the output
    echo "$body"
}

# ==============================================================================
# 2. Commands Implementation (CLI Interface)
# ==============================================================================

cmd_folders() {
    api_call "GET" "folder/readFolders" ""
}

cmd_read() {
    local item_id="$1"
    if [[ -z "$item_id" ]]; then
        echo "Error: item_id is required." >&2
        usage
        exit 1
    fi
    api_call "GET" "item/get?id=${item_id}" ""
}

cmd_create() {
    local folder_id="$1"
    local label="$2"
    local login="$3"
    local password="$4"
    local description="$5"

    if [[ -z "$password" ]]; then
        echo "Error: folder_id, title, login, and password are required." >&2
        usage
        exit 1
    fi

    # Create the payload in a safe JSON format using jq
    local payload
    payload=$(jq -n \
        --arg folder_id "$folder_id" \
        --arg label "$label" \
        --arg login "$login" \
        --arg password "$password" \
        --arg description "$description" \
        '{folder_id: $folder_id|tonumber, label: $label, login: $login, password: $password, description: $description}'
    )

    api_call "POST" "item/create" "$payload"
}

cmd_update() {
    local item_id="$1"
    local field="$2"
    local value="$3"

    if [[ -z "$value" ]]; then
        echo "Error: item_id, field and value are required." >&2
        usage
        exit 1
    fi

    # Dynamically create the object to update, e.g. {"id": 123, "label": "New Name"}
    local payload
    payload=$(jq -n \
        --arg id "$item_id" \
        --arg field "$field" \
        --arg value "$value" \
        '{id: $id|tonumber} + {($field): $value}'
    )

    api_call "PUT" "item/update" "$payload"
}

cmd_search() {
    local term="$1"
    local mode="$2"
    
    if [[ -z "$term" ]]; then
        echo "Error: search term is required." >&2
        usage
        exit 1
    fi
    
    local encoded_term
    # jq performs URL-encoding automatically. The backend API (ItemController)
    # automatically adds the wildcard '%' characters if the like=1 parameter is present,
    # so we shouldn't include them here, otherwise they will be parsed as literal characters.
    encoded_term=$(jq -rn --arg x "${term}" '$x|@uri')

    if [[ "$mode" == "--by-url" ]]; then
        api_call "GET" "item/findByUrl?url=${encoded_term}" ""
    elif [[ "$mode" == "--by-desc" ]]; then
        api_call "GET" "item/get?description=${encoded_term}&like=1" ""
    else
        api_call "GET" "item/get?label=${encoded_term}&like=1" ""
    fi
}

# ==============================================================================
# Script entry point
# ==============================================================================

COMMAND="$1"
shift || true

case "$COMMAND" in
    folders)
        cmd_folders "$@"
        ;;
    read)
        cmd_read "$@"
        ;;
    create)
        cmd_create "$@"
        ;;
    update)
        cmd_update "$@"
        ;;
    search)
        cmd_search "$@"
        ;;
    help|--help|-h|"")
        usage
        ;;
    *)
        echo "Unknown command: $COMMAND" >&2
        usage
        exit 1
        ;;
esac
