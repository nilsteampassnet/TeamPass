<#
.SYNOPSIS
    teampass-cli.ps1 — Command line client for the Teampass REST API.

.DESCRIPTION
    Wraps the JWT authentication and the item/folder endpoints behind a few
    readable commands, so the API can be used from a PowerShell console,
    Task Scheduler, or automation scripts without writing REST calls by hand.

.NOTES
    Requirements:
        PowerShell 5.1 or higher (built-in Invoke-RestMethod).

    Configuration — environment variables, or $ENV:LOCALAPPDATA\teampass\config:

        TEAMPASS_URL       https://teampass.example.com        (required)
        TEAMPASS_LOGIN     your login                          (required)
        Then ONE of the two authentication modes:
          - password mode: TEAMPASS_PASSWORD + TEAMPASS_APIKEY
          - token mode   : TEAMPASS_TOKEN    (Personal Access Token, /authorizeToken)

    Teampass - a collaborative passwords manager.
    ---
    This file is part of the TeamPass project.

    TeamPass is free software: you can redistribute it and/or modify it
    under the terms of the GNU General Public License as published by
    the Free Software Foundation, version 3 of the License.

    @copyright 2009-2026 Teampass.net
    @license   GPL-3.0

.LINK
    https://www.teampass.net
#>

$ErrorActionPreference = "Stop"

# Configuration paths
$CONFIG_DIR  = Join-Path $ENV:LOCALAPPDATA "teampass"
$CONFIG_FILE = Join-Path $CONFIG_DIR "config"

$Global:API_BASE  = ""
$Global:JWT_TOKEN = ""

# ------------------------------------------------------------------------------
# Helpers & Setup
# ------------------------------------------------------------------------------

function Write-ErrorAndExit([string]$Message) {
    [Console]::Error.WriteLine($Message)
    exit 1
}

function Load-Configuration {
    # 1. Load config file if present
    if (Test-Path -Path $CONFIG_FILE -PathType Leaf) {
        Get-Content -Path $CONFIG_FILE | ForEach-Object {
            $line = $_.Trim()
            if ($line.Contains("#")) {
                $line = $line.Substring(0, $line.IndexOf("#")).Trim()
            }
            if ($line -and -not $line.StartsWith("#")) {
                $parts = $line -split "=", 2
                if ($parts.Count -eq 2) {
                    $varName = $parts[0].Trim().Replace("export ", "").Replace('$ENV:', '').Replace('$', '')
                    $varVal  = $parts[1].Trim().Trim('"').Trim("'")
                    if (-not [string]::IsNullOrEmpty($varName)) {
                        [Environment]::SetEnvironmentVariable($varName, $varVal, "Process")
                    }
                }
            }
        }
    }

    # 2. Check configuration
    $missing = [System.Collections.Generic.List[string]]::new()

    $script:TEAMPASS_URL      = $ENV:TEAMPASS_URL
    $script:TEAMPASS_LOGIN    = $ENV:TEAMPASS_LOGIN
    $script:TEAMPASS_PASSWORD = $ENV:TEAMPASS_PASSWORD
    $script:TEAMPASS_APIKEY   = $ENV:TEAMPASS_APIKEY
    $script:TEAMPASS_TOKEN    = $ENV:TEAMPASS_TOKEN

    if ([string]::IsNullOrWhiteSpace($script:TEAMPASS_URL))   { $missing.Add("TEAMPASS_URL") }
    if ([string]::IsNullOrWhiteSpace($script:TEAMPASS_LOGIN)) { $missing.Add("TEAMPASS_LOGIN") }

    if ([string]::IsNullOrWhiteSpace($script:TEAMPASS_TOKEN)) {
        if ([string]::IsNullOrWhiteSpace($script:TEAMPASS_PASSWORD)) { $missing.Add("TEAMPASS_PASSWORD (or TEAMPASS_TOKEN)") }
        if ([string]::IsNullOrWhiteSpace($script:TEAMPASS_APIKEY))   { $missing.Add("TEAMPASS_APIKEY (or TEAMPASS_TOKEN)") }
    }

    if ($missing.Count -gt 0) {
        [Console]::Error.WriteLine("Error: missing configuration: $($missing -join ', ')`n")
        [Console]::Error.WriteLine("Export environment variables or create ${CONFIG_FILE}:")
        [Console]::Error.WriteLine("    TEAMPASS_URL=`"https://teampass.example.com`"")
        [Console]::Error.WriteLine("    TEAMPASS_LOGIN=`"jdoe`"")
        [Console]::Error.WriteLine("    TEAMPASS_PASSWORD=`"...`"   # password mode")
        [Console]::Error.WriteLine("    TEAMPASS_APIKEY=`"...`"     # password mode")
        [Console]::Error.WriteLine("    TEAMPASS_TOKEN=`"...`"      # or token mode (Personal Access Token)")
        exit 1
    }

    $Global:API_BASE = "$($script:TEAMPASS_URL.TrimEnd('/'))/api/index.php"
}

function Show-Usage {
    $scriptName = "teampass-cli.ps1"
    @"
Usage: $scriptName <command> [options...]

Available commands:
  folders [--tree]
      List every accessible folder with its access rights, in tree order.
      --tree renders an indented view instead of raw JSON.

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
  .\$scriptName folders --tree
  .\$scriptName read 25
  .\$scriptName create 5 "My Server" "admin" "SuperSecret!" "Root credentials"
  .\$scriptName update 25 label "Updated Server"
  .\$scriptName search "server"
"@ | Write-Host
}

# ------------------------------------------------------------------------------
# API Plumbing
# ------------------------------------------------------------------------------

function Invoke-Authenticate {
    if (-not [string]::IsNullOrEmpty($Global:JWT_TOKEN)) { return }

    if (-not [string]::IsNullOrEmpty($script:TEAMPASS_TOKEN)) {
        $endpoint = "$Global:API_BASE/authorizeToken"
        $body = @{
            login = $script:TEAMPASS_LOGIN
            token = $script:TEAMPASS_TOKEN
        }
    } else {
        $endpoint = "$Global:API_BASE/authorize"
        $body = @{
            login    = $script:TEAMPASS_LOGIN
            password = $script:TEAMPASS_PASSWORD
            apikey   = $script:TEAMPASS_APIKEY
        }
    }

    $jsonBody = $body | ConvertTo-Json -Depth 5 -Compress

    try {
        $response = Invoke-RestMethod -Uri $endpoint -Method Post -ContentType "application/json" -Body $jsonBody
        if (-not $response.token) {
            Write-ErrorAndExit "Error: no JWT token in authentication response."
        }
        $Global:JWT_TOKEN = $response.token
    }
    catch {
        $statusCode = $_.Exception.Response.StatusCode.Value__
        [Console]::Error.WriteLine("Authentication failed (HTTP $statusCode)")
        if ($_.ErrorDetails.Message) {
            [Console]::Error.WriteLine($_.ErrorDetails.Message)
        }
        exit 1
    }
}

function Invoke-ApiCall {
    param(
        [string]$Method,
        [string]$Endpoint,
        [string]$Payload
    )

    Invoke-Authenticate

    $uri = "$Global:API_BASE/$Endpoint"
    $headers = @{
        "Authorization" = "Bearer $Global:JWT_TOKEN"
    }

    $attempt = 0
    while ($true) {
        try {
            $params = @{
                Uri     = $uri
                Method  = $Method
                Headers = $headers
            }

            # Impostiamo ContentType e Body SOLO se c'è un payload (POST/PUT)
            if (-not [string]::IsNullOrEmpty($Payload)) {
                $params["ContentType"] = "application/json"
                $params["Body"]        = $Payload
            }

            $response = Invoke-RestMethod @params
            return $response
        }
        catch {
            $responseObj = $_.Exception.Response
            if ($null -ne $responseObj) {
                $httpCode = [int]$responseObj.StatusCode

                if ($httpCode -eq 429 -and $attempt -eq 0) {
                    $retryHeader = $responseObj.Headers["Retry-After"]
                    $retryAfter = 5
                    if ($retryHeader) { [int]::TryParse($retryHeader, [ref]$retryAfter) | Out-Null }

                    [Console]::Error.WriteLine("Rate limit reached, retrying in ${retryAfter}s...")
                    Start-Sleep -Seconds $retryAfter
                    $attempt = 1
                    continue
                }

                [Console]::Error.WriteLine("API error (HTTP $httpCode):")
                if ($_.ErrorDetails.Message) {
                    [Console]::Error.WriteLine($_.ErrorDetails.Message)
                }
                exit 1
            } else {
                Write-ErrorAndExit "API call failed: $($_.Exception.Message)"
            }
        }
    }
}

# ------------------------------------------------------------------------------
# Commands
# ------------------------------------------------------------------------------

function Invoke-CmdFolders([string[]]$CommandArguments) {
    $folders = Invoke-ApiCall -Method "GET" -Endpoint "folder/writableFolders"

    if ($CommandArguments.Count -eq 0 -or $CommandArguments[0] -ne "--tree") {
        return ($folders | ConvertTo-Json -Depth 10)
    }

    foreach ($f in $folders) {
        $level    = [int]$f.level
        $label    = $f.label
        $id       = $f.id
        $readonly = $f.is_readonly

        $flag = ""
        if ($readonly -eq 1 -or $readonly -eq "1") {
            $flag = " (read-only)"
        }

        $indent = " " * ([Math]::Max(0, ($level - 1) * 2))
        Write-Host "$indent$label [$id]$flag"
    }
}

function Invoke-CmdRead([string[]]$CommandArguments) {
    if ($CommandArguments.Count -lt 1 -or [string]::IsNullOrWhiteSpace($CommandArguments[0])) {
        [Console]::Error.WriteLine("Error: item_id is required.")
        Show-Usage
        exit 1
    }

    $itemId = [Uri]::EscapeDataString($CommandArguments[0])
    $result = Invoke-ApiCall -Method "GET" -Endpoint "item/get?id=$itemId"
    return ($result | ConvertTo-Json -Depth 10)
}

function Invoke-CmdCreate([string[]]$CommandArguments) {
    if ($CommandArguments.Count -lt 4) {
        [Console]::Error.WriteLine("Error: folder_id, label, login and password are required.")
        Show-Usage
        exit 1
    }

    $folderId    = [int]$CommandArguments[0]
    $label       = $CommandArguments[1]
    $login       = $CommandArguments[2]
    $password    = $CommandArguments[3]
    $description = if ($CommandArguments.Count -gt 4) { $CommandArguments[4] } else { "" }

    $payloadObj = @{
        folder_id   = $folderId
        label       = $label
        login       = $login
        password    = $password
        description = $description
    }

    $payload = $payloadObj | ConvertTo-Json -Depth 5 -Compress
    $result  = Invoke-ApiCall -Method "POST" -Endpoint "item/create" -Payload $payload
    return ($result | ConvertTo-Json -Depth 10)
}

function Invoke-CmdUpdate([string[]]$CommandArguments) {
    if ($CommandArguments.Count -lt 3) {
        [Console]::Error.WriteLine("Error: item_id, field and value are required.")
        Show-Usage
        exit 1
    }

    $itemId = [int]$CommandArguments[0]
    $field  = $CommandArguments[1]
    $value  = $CommandArguments[2]

    $payloadObj = @{
        id = $itemId
    }
    $payloadObj[$field] = $value

    $payload = $payloadObj | ConvertTo-Json -Depth 5 -Compress
    $result  = Invoke-ApiCall -Method "PUT" -Endpoint "item/update" -Payload $payload
    return ($result | ConvertTo-Json -Depth 10)
}

function Invoke-CmdSearch([string[]]$CommandArguments) {
    if ($CommandArguments.Count -lt 1 -or [string]::IsNullOrWhiteSpace($CommandArguments[0])) {
        [Console]::Error.WriteLine("Error: search term is required.")
        Show-Usage
        exit 1
    }

    $term    = $CommandArguments[0]
    $mode    = if ($CommandArguments.Count -gt 1) { $CommandArguments[1] } else { "" }
    $encoded = [Uri]::EscapeDataString($term)

    switch ($mode) {
        "--by-url"  { $endpoint = "item/findByUrl?url=$encoded" }
        "--by-desc" { $endpoint = "item/get?description=$encoded&like=1" }
        default     { $endpoint = "item/get?label=$encoded&like=1" }
    }

    $result = Invoke-ApiCall -Method "GET" -Endpoint $endpoint
    return ($result | ConvertTo-Json -Depth 10)
}

# ------------------------------------------------------------------------------
# Entry Point
# ------------------------------------------------------------------------------

$Command = if ($args.Count -gt 0) { $args[0] } else { "help" }
$CommandArgs = if ($args.Count -gt 1) { $args[1..($args.Count - 1)] } else { @() }

switch ($Command.ToLower()) {
    "help"     { Show-Usage }
    "--help"   { Show-Usage }
    "-h"       { Show-Usage }
    "folders"  { Load-Configuration; Invoke-CmdFolders $CommandArgs }
    "read"     { Load-Configuration; Invoke-CmdRead $CommandArgs }
    "create"   { Load-Configuration; Invoke-CmdCreate $CommandArgs }
    "update"   { Load-Configuration; Invoke-CmdUpdate $CommandArgs }
    "search"   { Load-Configuration; Invoke-CmdSearch $CommandArgs }
    default {
        [Console]::Error.WriteLine("Unknown command: $Command")
        Show-Usage
        exit 1
    }
}
